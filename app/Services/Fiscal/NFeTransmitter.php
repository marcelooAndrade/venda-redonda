<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\NFeStatus;
use App\Models\Nota;
use App\Models\NotaArquivo;
use App\Models\SefazLog;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Transmite a NF-e à SEFAZ.
 *
 * A parte mais delicada é a idempotência. Diante de timeout, o sistema não
 * sabe se a SEFAZ recebeu ou não. Retransmitir às cegas gera duplicidade e
 * queima o número da nota, que depois precisa de inutilização formal. Por
 * isso, antes de qualquer reenvio, pergunta-se à SEFAZ pela chave.
 */
class NFeTransmitter
{
    public function __construct(
        private readonly NFeBuilder $builder,
        private readonly NumeracaoService $numeracao,
        private readonly SefazGateway $gateway,
        private readonly SefazErrorTranslator $tradutor,
        private readonly StockService $estoque,
    ) {}

    public function transmitir(Nota $nota, ?User $user = null): Nota
    {
        $this->conferirEstado($nota);
        $this->conferirEstoque($nota);

        // O número só é consumido agora: rascunho abandonado não pode
        // queimar numeração, e número queimado exige inutilização formal.
        if ($nota->numero === null) {
            $nota->forceFill([
                'numero' => $this->numeracao->proximo($nota->emitente, $nota->serie),
            ])->save();
        }

        $xml = $this->builder->montar($nota->fresh());
        $this->guardarArquivo($nota, 'gerado', $xml);

        $nota->forceFill([
            'status' => NFeStatus::EmProcessamento,
            'transmitida_por' => $user?->getKey(),
        ])->save();

        $inicio = microtime(true);

        try {
            $resposta = $this->gateway->enviar($nota->emitente, $xml);
        } catch (Throwable $e) {
            // Não sabemos se chegou. Perguntar é a única saída segura.
            $this->registrarLog($nota, 'autorizacao', null, null, $inicio, $e->getMessage());

            return $this->resolverPelaChave($nota, $user);
        }

        // Lote recebido sem resultado: o retorno vem pelo recibo.
        if ($resposta->emProcessamento() && filled($resposta->recibo)) {
            $nota->forceFill(['recibo' => $resposta->recibo])->save();
            $resposta = $this->gateway->consultarRecibo($nota->emitente, $resposta->recibo);
        }

        $this->registrarLog($nota, 'autorizacao', $resposta->cStat, $resposta->xMotivo, $inicio);

        return $this->aplicar($nota, $resposta, $user);
    }

    /**
     * Depois de uma falha de comunicação, descobre o que de fato aconteceu.
     */
    private function resolverPelaChave(Nota $nota, ?User $user): Nota
    {
        $inicio = microtime(true);

        try {
            $resposta = $this->gateway->consultarChave($nota->emitente, (string) $nota->chave_acesso);
        } catch (Throwable $e) {
            $this->registrarLog($nota, 'consulta-chave', null, null, $inicio, $e->getMessage());

            // Sem resposta, a nota fica em processamento de propósito: é o
            // estado honesto. Retransmitir seria apostar.
            $nota->forceFill([
                'status' => NFeStatus::EmProcessamento,
                'x_motivo' => 'Falha de comunicação com a SEFAZ. A situação desta nota ainda é desconhecida: '
                    .'consulte novamente antes de emitir outra.',
            ])->save();

            return $nota->fresh();
        }

        $this->registrarLog($nota, 'consulta-chave', $resposta->cStat, $resposta->xMotivo, $inicio);

        return $this->aplicar($nota, $resposta, $user);
    }

    private function aplicar(Nota $nota, RespostaSefaz $resposta, ?User $user): Nota
    {
        if ($resposta->autorizada()) {
            return $this->autorizar($nota, $resposta, $user);
        }

        if ($resposta->denegada()) {
            $nota->forceFill([
                'status' => NFeStatus::Denegada,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo),
            ])->save();

            return $nota->fresh();
        }

        if ($resposta->emProcessamento()) {
            $nota->forceFill([
                'status' => NFeStatus::EmProcessamento,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $resposta->xMotivo,
            ])->save();

            return $nota->fresh();
        }

        $nota->forceFill([
            'status' => NFeStatus::Rejeitada,
            'c_stat' => $resposta->cStat,
            'x_motivo' => $this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo),
        ])->save();

        return $nota->fresh();
    }

    private function autorizar(Nota $nota, RespostaSefaz $resposta, ?User $user): Nota
    {
        return DB::transaction(function () use ($nota, $resposta, $user): Nota {
            if (filled($resposta->xmlProtocolado)) {
                $this->guardarArquivo($nota, 'protocolado', $resposta->xmlProtocolado);
            }

            $nota->forceFill([
                'status' => NFeStatus::Autorizada,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $resposta->xMotivo,
                'protocolo' => $resposta->protocolo,
                'autorizada_em' => now(),
            ])->save();

            $this->baixarEstoque($nota, $user);

            return $nota->fresh();
        });
    }

    /**
     * Confere disponibilidade antes de transmitir.
     *
     * Barrar aqui evita queimar número de nota e evita chamar a SEFAZ à toa.
     * Depois da autorização já é tarde: a nota existe.
     */
    private function conferirEstoque(Nota $nota): void
    {
        if ($nota->emitente->permite_saldo_negativo) {
            return;
        }

        foreach ($nota->itens()->with('produto')->get() as $item) {
            $produto = $item->produto;

            if ($produto === null || ! $produto->controla_estoque) {
                continue;
            }

            $disponivel = (float) $this->estoque->saldo($produto)->quantidade;

            if ($disponivel < (float) $item->quantidade) {
                throw new RuntimeException(
                    "Saldo insuficiente para \"{$produto->descricao}\": disponível "
                    .rtrim(rtrim(number_format($disponivel, 4, ',', '.'), '0'), ',')
                    .', a nota pede '.rtrim(rtrim(number_format((float) $item->quantidade, 4, ',', '.'), '0'), ',')
                    .'. Ajuste o estoque ou a quantidade antes de transmitir.'
                );
            }
        }
    }

    /**
     * A baixa acontece só na autorização: antes disso a nota não existe.
     *
     * Se o movimento falhar aqui, a nota **continua autorizada**: a SEFAZ já
     * disse que ela existe, e desfazer isso seria negar um fato. A divergência
     * de estoque é registrada para o operador resolver.
     */
    private function baixarEstoque(Nota $nota, ?User $user): void
    {
        $documento = 'NF-e '.$nota->numeroFormatado();

        foreach ($nota->itens()->with('produto')->get() as $item) {
            if ($item->produto === null) {
                continue;
            }

            try {
                $this->estoque->saida($item->produto, (float) $item->quantidade, $documento, $user);
            } catch (Throwable $e) {
                $this->registrarLog(
                    $nota,
                    'baixa-estoque',
                    null,
                    null,
                    microtime(true),
                    "Item {$item->numero} ({$item->descricao}): {$e->getMessage()}",
                );
            }
        }
    }

    private function conferirEstado(Nota $nota): void
    {
        if ($nota->status === NFeStatus::Autorizada) {
            throw new RuntimeException(
                "A nota {$nota->numeroFormatado()} já está autorizada. Para anulá-la, use o cancelamento."
            );
        }

        if ($nota->status->terminal()) {
            throw new RuntimeException(
                "A nota {$nota->numeroFormatado()} está {$nota->status->rotulo()} e não pode ser transmitida."
            );
        }

        if ($nota->itens()->count() === 0) {
            throw new RuntimeException('A nota não tem itens.');
        }
    }

    private function guardarArquivo(Nota $nota, string $tipo, string $conteudo): void
    {
        $chave = $nota->chave_acesso ?: $nota->getKey();
        $path = "notas/{$nota->emitente_id}/".now()->format('Y/m')."/{$chave}-{$tipo}.xml";

        Storage::disk('fiscal')->put($path, $conteudo);

        NotaArquivo::create([
            'nota_id' => $nota->getKey(),
            'tipo' => $tipo,
            'path' => $path,
            'sha256' => hash('sha256', $conteudo),
            'created_at' => now(),
        ]);
    }

    private function registrarLog(
        Nota $nota,
        string $operacao,
        ?string $cStat,
        ?string $xMotivo,
        float $inicio,
        ?string $erro = null,
    ): void {
        SefazLog::create([
            'emitente_id' => $nota->emitente_id,
            'nota_id' => $nota->getKey(),
            'operacao' => $operacao,
            'ambiente' => $nota->ambiente->value,
            'c_stat' => $cStat,
            'x_motivo' => $xMotivo,
            'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
            // Nunca guarda o XML nem a senha: só o suficiente para diagnosticar.
            'erro' => $erro === null ? null : mb_substr($erro, 0, 2000),
            'created_at' => now(),
        ]);
    }
}
