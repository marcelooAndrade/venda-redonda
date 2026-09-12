<?php

namespace App\Services\Nfse;

use App\Enums\Nfse\NfseStatus;
use App\Models\Emitente;
use App\Models\EmitenteNfse;
use App\Models\FaturaParcela;
use App\Models\NotaServico;
use App\Models\ServicoNfse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Emite, cancela e imprime NFS-e a partir da parcela da fatura.
 *
 * O RPS é reservado sob lock e só na emissão. Rejeição e erro guardam o RPS
 * e o registro para a nova tentativa; autorizada, em processamento e
 * cancelada bloqueiam. O que o provedor devolve é gravado no disco fiscal,
 * inclusive na rejeição: é a única prova do que ele disse.
 *
 * Limitação registrada em DF-025: o SIGISS é síncrono e não oferece consulta
 * por RPS. Depois de uma falha de comunicação não há como perguntar se a nota
 * entrou. A nota fica em erro com a orientação de conferir no portal, e a
 * nova tentativa fica liberada.
 */
class NfseEmissor
{
    private const IBGE_ARARAS = '3503307';

    /** Envio que não voltou em 15 minutos morreu no meio. Libera a tentativa. */
    private const MINUTOS_PROCESSANDO = 15;

    public function __construct(
        private readonly GatewayNfse $gateway,
        private readonly NfseXmlBuilder $builder,
    ) {}

    public function emitir(FaturaParcela $parcela, ServicoNfse $servico, string $descricao, ?User $user = null): NotaServico
    {
        $parcela->loadMissing('fatura.emitente.nfse', 'fatura.destinatario');
        $emitente = $parcela->fatura->emitente;
        $descricao = trim($descricao);

        $this->conferirEmissao($parcela, $servico, $descricao, $emitente);

        [$nota, $xml] = DB::transaction(function () use ($parcela, $servico, $descricao, $emitente, $user): array {
            // Lock na configuração: sem ele, duas emissões simultâneas leem o
            // mesmo próximo RPS, e a segunda é rejeitada por duplicidade.
            $config = EmitenteNfse::query()->lockForUpdate()->findOrFail($emitente->nfse->getKey());
            $ambiente = $config->ambiente;

            $existente = NotaServico::query()
                ->where('fatura_parcela_id', $parcela->getKey())
                ->where('ambiente', $ambiente->value)
                ->lockForUpdate()
                ->first();

            $this->conferirExistente($existente);

            $nota = $existente ?? new NotaServico([
                'emitente_id' => $emitente->getKey(),
                'fatura_parcela_id' => $parcela->getKey(),
                'ambiente' => $ambiente,
                'numero_rps' => $config->proximoRps($ambiente),
                'serie_rps' => $config->serie_rps,
            ]);

            // Os campos do serviço são copiados aqui, e não lidos na hora de
            // montar o XML: editar o catálogo depois não muda a nota.
            $nota->forceFill([
                'servico_nfse_id' => $servico->getKey(),
                'status' => NfseStatus::Processando,
                'codigo_servico' => $servico->codigo_servico,
                'codigo_nbs' => $servico->codigo_nbs,
                'c_class_trib' => $servico->c_class_trib,
                'ind_op' => $servico->ind_op,
                'aliquota_iss_bp' => $servico->aliquota_iss_bp,
                'iss_retido' => $servico->iss_retido,
                'descricao' => $descricao,
                'valor_centavos' => (int) $parcela->valor_centavos,
                'emitida_por' => $user?->getKey(),
                'xml_retorno_path' => null,
                'motivo_rejeicao' => null,
            ])->save();

            if ($existente === null) {
                $config->forceFill([$config->colunaProximoRps($ambiente) => $nota->numero_rps + 1])->save();
            }

            $xml = $this->builder->montar($emitente, $parcela->fatura->destinatario, $nota);
            $nota->forceFill(['xml_envio_path' => $this->guardar($nota, 'envio', $xml)])->save();

            return [$nota, $xml];
        });

        try {
            $resposta = $this->gateway->emitir($emitente, $nota->ambiente, $xml);
        } catch (FalhaDeComunicacaoNfse $e) {
            $motivo = $e->getMessage().' Confira no portal do SIGISS se a nota entrou antes de tentar de novo.';
            $nota->forceFill(['status' => NfseStatus::Erro, 'motivo_rejeicao' => $motivo])->save();

            throw $this->erro($motivo);
        }

        if (filled($resposta->bruto)) {
            $nota->forceFill(['xml_retorno_path' => $this->guardar($nota, 'retorno', (string) $resposta->bruto)])->save();
        }

        if (! $resposta->sucesso) {
            $motivo = $resposta->motivo ?: 'O SIGISS não informou o número da NFS-e.';
            $nota->forceFill(['status' => NfseStatus::Rejeitada, 'motivo_rejeicao' => $motivo])->save();

            throw $this->erro('O SIGISS rejeitou a NFS-e: '.$motivo);
        }

        $nota->forceFill([
            'status' => NfseStatus::Autorizada,
            'numero_nfse' => $resposta->numero,
            'serie_nfse' => $resposta->serie ?: 'NFE',
            'codigo_verificacao' => $resposta->codigoVerificacao,
            'emitida_em' => now(),
            'motivo_rejeicao' => null,
        ])->save();

        return $nota->fresh();
    }

    public function cancelar(NotaServico $nota, string $motivo): NotaServico
    {
        $motivo = trim($motivo);

        if ($nota->status !== NfseStatus::Autorizada || blank($nota->numero_nfse)) {
            throw $this->erro('Só é possível cancelar uma NFS-e autorizada.');
        }

        if (mb_strlen($motivo) < 15 || mb_strlen($motivo) > 255) {
            throw $this->erro('Informe uma justificativa entre 15 e 255 caracteres.');
        }

        $nota->loadMissing('emitente.nfse');

        try {
            // O ambiente é o da nota: se a configuração mudou depois, a nota
            // continua onde foi emitida.
            $resposta = $this->gateway->cancelar(
                $nota->emitente,
                $nota->ambiente,
                (string) $nota->numero_nfse,
                $nota->serie_nfse ?: 'NFE',
                $motivo,
            );
        } catch (FalhaDeComunicacaoNfse $e) {
            throw $this->erro($e->getMessage());
        }

        if (! $resposta->sucesso) {
            throw $this->erro('O SIGISS recusou o cancelamento: '.($resposta->motivo ?: 'sem detalhe.'));
        }

        $nota->forceFill([
            'status' => NfseStatus::Cancelada,
            'cancelada_em' => now(),
            'motivo_cancelamento' => $motivo,
        ])->save();

        return $nota->fresh();
    }

    /** Bytes do PDF, buscados no SIGISS a cada pedido. Nunca guardado. */
    public function pdf(NotaServico $nota): string
    {
        if (! $nota->temDocumento()) {
            throw $this->erro('Esta NFS-e ainda não tem número no SIGISS.');
        }

        $nota->loadMissing('emitente.nfse');

        try {
            return $this->gateway->pdf($nota->emitente, $nota->ambiente, (string) $nota->numero_nfse, $nota->serie_nfse ?: 'NFE');
        } catch (FalhaDeComunicacaoNfse $e) {
            throw $this->erro($e->getMessage());
        }
    }

    private function conferirExistente(?NotaServico $existente): void
    {
        if ($existente === null) {
            return;
        }

        if ($existente->status === NfseStatus::Autorizada) {
            throw $this->erro('Esta parcela já tem NFS-e autorizada.');
        }

        if ($existente->status === NfseStatus::Cancelada) {
            throw $this->erro('A NFS-e desta parcela foi cancelada e não pode ser reutilizada.');
        }

        if ($existente->status === NfseStatus::Processando
            && $existente->updated_at?->gt(now()->subMinutes(self::MINUTOS_PROCESSANDO))) {
            throw $this->erro('A emissão desta NFS-e já está em processamento.');
        }
    }

    private function conferirEmissao(FaturaParcela $parcela, ServicoNfse $servico, string $descricao, Emitente $emitente): void
    {
        $config = $emitente->nfse;

        if ($config === null || ! $config->habilitado) {
            throw $this->erro('A emissão de NFS-e está desligada. Ligue em NFS-e, no menu Configuração.');
        }

        if ((string) $emitente->codigo_municipio !== self::IBGE_ARARAS) {
            throw $this->erro('A integração disponível é exclusiva para prestador de Araras/SP (IBGE 3503307). Confira o endereço em Emitente.');
        }

        if (strlen((string) $emitente->cnpj) !== 14) {
            throw $this->erro('O emitente precisa ter CNPJ para emitir NFS-e.');
        }

        if (blank($emitente->inscricao_municipal)) {
            throw $this->erro('Informe a inscrição municipal do emitente, em Emitente.');
        }

        if (blank($config->senha($config->ambiente))) {
            throw $this->erro('Cadastre a senha do SIGISS de '.mb_strtolower($config->ambiente->rotulo()).' em NFS-e.');
        }

        if ($parcela->status === 'cancelado' || $parcela->fatura->status !== 'ativa') {
            throw $this->erro('A parcela está cancelada e não gera nota.');
        }

        if ((int) $parcela->valor_centavos <= 0) {
            throw $this->erro('A parcela precisa ter valor maior que zero.');
        }

        $tomador = $parcela->fatura->destinatario;

        if ($tomador === null) {
            throw $this->erro('A fatura precisa ter um cliente, que é o tomador da nota.');
        }

        if (! in_array(strlen((string) $tomador->documento), [11, 14], true)) {
            throw $this->erro('O cliente precisa ter CPF ou CNPJ.');
        }

        if ((int) $servico->emitente_id !== (int) $emitente->getKey() || ! $servico->ativo) {
            throw $this->erro('Serviço fiscal inválido. Escolha um serviço ativo deste emitente.');
        }

        if (mb_strlen($descricao) < 2 || mb_strlen($descricao) > 1000) {
            throw $this->erro('Informe a descrição do serviço, com até 1.000 caracteres.');
        }
    }

    private function guardar(NotaServico $nota, string $tipo, string $conteudo): string
    {
        $path = sprintf(
            'nfse/%d/%s/rps-%s-%d-%s.xml',
            $nota->emitente_id,
            now()->format('Y/m'),
            $nota->serie_rps,
            $nota->numero_rps,
            $tipo,
        );

        Storage::disk('fiscal')->put($path, $conteudo);

        return $path;
    }

    private function erro(string $mensagem): ValidationException
    {
        return ValidationException::withMessages(['nfse' => $mensagem]);
    }
}
