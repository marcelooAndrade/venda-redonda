<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\NFeStatus;
use App\Models\Emitente;
use App\Models\Inutilizacao;
use App\Models\Nota;
use App\Models\NotaEvento;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Eventos da NF-e: cancelamento, carta de correção e inutilização.
 *
 * Todos seguem a mesma disciplina do transmissor: a SEFAZ é a autoridade.
 * Só depois que ela homologa o evento é que o sistema muda de estado.
 */
class NFeEventService
{
    private const TIPO_CANCELAMENTO = '110111';

    private const TIPO_CCE = '110110';

    /**
     * O schema da CC-e limita nSeqEvento a 20 (padrão `[1-9]|[1][0-9]{0,1}|20`
     * em leiauteCCe_v1.00.xsd). O Ajuste SINIEF 07/05 não põe número: manda
     * consolidar na última carta tudo que já foi retificado (cl. 14-A, § 4º).
     */
    private const MAXIMO_CCE = 20;

    private const MINIMO_TEXTO = 15;

    /**
     * Ajuste SINIEF 07/05, cláusula décima segunda: prazo "não superior a vinte
     * e quatro horas, contado do momento em que foi concedida a Autorização de
     * Uso". O parágrafo único deixa o cancelamento extemporâneo a critério de
     * cada UF, em casos excepcionais. Ver DF-022.
     */
    private const HORAS_PARA_CANCELAR = 24;

    /**
     * O que a carta de correção não pode alterar, por lei.
     *
     * Ajuste SINIEF 07/05, cláusula décima quarta-A, incisos I a V. Ver DF-023.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const VEDACOES = [
        ['valor', 'valores da nota, como base de cálculo, alíquota, preço ou quantidade'],
        ['aliquota', 'valores da nota, como base de cálculo, alíquota, preço ou quantidade'],
        ['alíquota', 'valores da nota, como base de cálculo, alíquota, preço ou quantidade'],
        ['preco', 'valores da nota, como base de cálculo, alíquota, preço ou quantidade'],
        ['preço', 'valores da nota, como base de cálculo, alíquota, preço ou quantidade'],
        ['quantidade', 'valores da nota, como base de cálculo, alíquota, preço ou quantidade'],
        ['destinatario', 'dados cadastrais que mudem o remetente ou o destinatário'],
        ['destinatário', 'dados cadastrais que mudem o remetente ou o destinatário'],
        ['remetente', 'dados cadastrais que mudem o remetente ou o destinatário'],
        ['cnpj', 'dados cadastrais que mudem o remetente ou o destinatário'],
        ['data de emiss', 'a data de emissão ou de saída'],
        ['data de sa', 'a data de emissão ou de saída'],
        ['du-e', 'os campos de exportação vinculados à DU-E'],
        ['due', 'os campos de exportação vinculados à DU-E'],
        ['exporta', 'os campos de exportação vinculados à DU-E'],
        ['parcela', 'a inclusão ou alteração de parcelas de pagamento'],
        ['duplicata', 'a inclusão ou alteração de parcelas de pagamento'],
    ];

    public function __construct(
        private readonly SefazGateway $gateway,
        private readonly SefazErrorTranslator $tradutor,
        private readonly StockService $estoque,
    ) {}

    public function cancelar(Nota $nota, string $justificativa, ?User $user = null): NotaEvento
    {
        $this->conferirTexto($justificativa, 'justificativa');

        if ($nota->status !== NFeStatus::Autorizada) {
            throw new RuntimeException(
                "Só nota autorizada pode ser cancelada. Esta está {$nota->status->rotulo()}."
            );
        }

        $horas = $nota->autorizada_em?->diffInHours(now()) ?? 0;

        if ($horas > self::HORAS_PARA_CANCELAR) {
            throw new RuntimeException(
                'O prazo de cancelamento de '.self::HORAS_PARA_CANCELAR.' horas já passou. '
                .'Para anular a operação, emita uma nota de devolução referenciando esta.'
            );
        }

        $resposta = $this->gateway->cancelar(
            $nota->emitente,
            (string) $nota->chave_acesso,
            (string) $nota->protocolo,
            $justificativa,
        );

        $evento = $this->registrarEvento($nota, self::TIPO_CANCELAMENTO, $resposta, $user, [
            'justificativa' => $justificativa,
            'sequencia' => 1,
        ]);

        if (! $this->homologado($resposta)) {
            throw new RuntimeException(
                'A SEFAZ recusou o cancelamento. '
                .$this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo)
            );
        }

        // Só depois da homologação: cancelar no sistema uma nota que a SEFAZ
        // manteve deixaria os dois em desacordo, com o fisco tendo razão.
        DB::transaction(function () use ($nota, $user): void {
            $nota->forceFill([
                'status' => NFeStatus::Cancelada,
                'c_stat' => '101',
                'x_motivo' => 'Cancelamento de NF-e homologado',
            ])->save();

            $this->estornarEstoque($nota, $user);
        });

        return $evento;
    }

    public function cartaCorrecao(Nota $nota, string $correcao, ?User $user = null): NotaEvento
    {
        $this->conferirTexto($correcao, 'texto da correção');
        $this->conferirVedacoes($correcao);

        if ($nota->status !== NFeStatus::Autorizada) {
            throw new RuntimeException(
                "Só nota autorizada aceita carta de correção. Esta está {$nota->status->rotulo()}."
            );
        }

        $sequencia = ((int) NotaEvento::query()
            ->where('nota_id', $nota->getKey())
            ->where('tipo', self::TIPO_CCE)
            ->max('sequencia')) + 1;

        if ($sequencia > self::MAXIMO_CCE) {
            throw new RuntimeException(
                'Esta nota já tem '.self::MAXIMO_CCE.' cartas de correção, que é o limite legal. '
                .'Para ajustar mais, cancele e reemita, ou emita nota complementar.'
            );
        }

        $resposta = $this->gateway->cartaCorrecao(
            $nota->emitente,
            (string) $nota->chave_acesso,
            $correcao,
            $sequencia,
        );

        $evento = $this->registrarEvento($nota, self::TIPO_CCE, $resposta, $user, [
            'correcao' => $correcao,
            'sequencia' => $sequencia,
        ]);

        if (! $this->homologado($resposta)) {
            throw new RuntimeException(
                'A SEFAZ recusou a carta de correção. '
                .$this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo)
            );
        }

        return $evento;
    }

    public function inutilizar(
        Emitente $emitente,
        int $serie,
        int $inicial,
        int $final,
        string $justificativa,
        ?User $user = null,
        ?int $ano = null,
    ): Inutilizacao {
        $this->conferirTexto($justificativa, 'justificativa');

        if ($inicial > $final) {
            throw new RuntimeException('A faixa está invertida: o número inicial é maior que o final.');
        }

        $usados = Nota::query()
            ->where('emitente_id', $emitente->getKey())
            ->where('serie', $serie)
            ->whereBetween('numero', [$inicial, $final])
            ->pluck('numero');

        if ($usados->isNotEmpty()) {
            throw new RuntimeException(
                'O número '.$usados->first().' já foi usado nesta série e não pode ser inutilizado.'
            );
        }

        $ano ??= (int) now()->format('Y');

        $resposta = $this->gateway->inutilizar($emitente, $ano, $serie, $inicial, $final, $justificativa);

        $inutilizacao = Inutilizacao::create([
            'emitente_id' => $emitente->getKey(),
            'ano' => $ano,
            'serie' => $serie,
            'numero_inicial' => $inicial,
            'numero_final' => $final,
            'justificativa' => $justificativa,
            'protocolo' => $resposta->protocolo,
            'c_stat' => $resposta->cStat,
            'x_motivo' => $resposta->xMotivo,
            'homologada_em' => $resposta->cStat === '102' ? now() : null,
            'user_id' => $user?->getKey(),
        ]);

        if ($resposta->cStat !== '102') {
            throw new RuntimeException(
                'A SEFAZ recusou a inutilização. '
                .$this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo)
            );
        }

        return $inutilizacao;
    }

    /** 135 é evento vinculado, 136 é vinculado com aviso. */
    private function homologado(RespostaSefaz $resposta): bool
    {
        return in_array($resposta->cStat, ['135', '136'], true);
    }

    /** @param array<string, mixed> $extra */
    private function registrarEvento(
        Nota $nota,
        string $tipo,
        RespostaSefaz $resposta,
        ?User $user,
        array $extra,
    ): NotaEvento {
        return NotaEvento::create([
            'nota_id' => $nota->getKey(),
            'emitente_id' => $nota->emitente_id,
            'tipo' => $tipo,
            'protocolo' => $resposta->protocolo,
            'c_stat' => $resposta->cStat,
            'x_motivo' => $resposta->xMotivo,
            'homologado_em' => $this->homologado($resposta) ? now() : null,
            'user_id' => $user?->getKey(),
            ...$extra,
        ]);
    }

    private function estornarEstoque(Nota $nota, ?User $user): void
    {
        $documento = 'NF-e '.$nota->numeroFormatado().' cancelada';

        foreach ($nota->itens()->with('produto')->get() as $item) {
            if ($item->produto === null) {
                continue;
            }

            $this->estoque->estornar($item->produto, (float) $item->quantidade, $documento, $user);
        }
    }

    private function conferirTexto(string $texto, string $campo): void
    {
        if (mb_strlen(trim($texto)) < self::MINIMO_TEXTO) {
            throw new RuntimeException(
                ucfirst($campo).' precisa ter ao menos '.self::MINIMO_TEXTO.' caracteres. '
                .'A SEFAZ rejeita textos curtos demais.'
            );
        }
    }

    /**
     * A carta de correção tem vedações legais: ela existe para corrigir
     * erro de digitação em campo não fiscal, não para mudar a operação.
     */
    private function conferirVedacoes(string $texto): void
    {
        $normalizado = mb_strtolower($texto);

        foreach (self::VEDACOES as [$termo, $descricao]) {
            // Só no começo da palavra. Por substring solta, "due" casaria
            // dentro de "aduela" e bloquearia uma correção de descrição, que
            // é justamente para o que a CC-e serve. A âncora fica apenas no
            // início para que "valor" continue pegando "valores".
            if (preg_match('/\b'.preg_quote($termo, '/').'/u', $normalizado) === 1) {
                throw new RuntimeException(
                    "A carta de correção não pode alterar {$descricao}. "
                    .'Para isso, cancele a nota e reemita, ou emita nota complementar.'
                );
            }
        }
    }
}
