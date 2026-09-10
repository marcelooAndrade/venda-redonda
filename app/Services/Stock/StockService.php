<?php

namespace App\Services\Stock;

use App\Enums\Fiscal\TipoMovimentoEstoque;
use App\Models\EstoqueMovimento;
use App\Models\EstoqueSaldo;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Movimenta o estoque como razão imutável.
 *
 * Toda escrita acontece em transação com lock sobre o saldo. Sem o lock, duas
 * saídas simultâneas leriam o mesmo saldo e ambas passariam na checagem de
 * disponibilidade, deixando o estoque negativo sem autorização.
 */
class StockService
{
    public function entrada(
        Produto $produto,
        float $quantidade,
        ?float $custoUnitario,
        ?string $documento = null,
        ?User $user = null,
    ): ?EstoqueMovimento {
        return $this->registrar($produto, TipoMovimentoEstoque::Entrada, $quantidade, $custoUnitario, $documento, $user);
    }

    public function saida(
        Produto $produto,
        float $quantidade,
        ?string $documento = null,
        ?User $user = null,
    ): ?EstoqueMovimento {
        return $this->registrar($produto, TipoMovimentoEstoque::Saida, $quantidade, null, $documento, $user);
    }

    /** Devolve ao estoque o que saiu, quando a nota é cancelada. */
    public function estornar(
        Produto $produto,
        float $quantidade,
        ?string $documento = null,
        ?User $user = null,
    ): ?EstoqueMovimento {
        return $this->registrar($produto, TipoMovimentoEstoque::Estorno, $quantidade, null, $documento, $user);
    }

    public function ajuste(
        Produto $produto,
        float $quantidade,
        ?string $justificativa = null,
        ?User $user = null,
        ?float $custoUnitario = null,
    ): ?EstoqueMovimento {
        $tipo = $quantidade >= 0 ? TipoMovimentoEstoque::Entrada : TipoMovimentoEstoque::Saida;

        return $this->registrar(
            $produto,
            $quantidade >= 0 ? TipoMovimentoEstoque::Ajuste : $tipo,
            abs($quantidade),
            $custoUnitario,
            null,
            $user,
            $justificativa,
            somaAoSaldo: $quantidade >= 0,
        );
    }

    /**
     * Acerto de inventário. Movimenta a diferença apurada na contagem, e não
     * mexe no custo médio: contagem corrige quantidade, não valor.
     */
    public function movimentarInventario(
        Produto $produto,
        float $diferenca,
        bool $soma,
        string $justificativa,
        ?User $user = null,
    ): ?EstoqueMovimento {
        return $this->registrar(
            $produto,
            TipoMovimentoEstoque::Inventario,
            $diferenca,
            null,
            null,
            $user,
            $justificativa,
            somaAoSaldo: $soma,
        );
    }

    public function saldo(Produto $produto): EstoqueSaldo
    {
        return EstoqueSaldo::query()->firstOrCreate(
            ['produto_id' => $produto->getKey()],
            ['emitente_id' => $produto->emitente_id, 'quantidade' => 0, 'custo_medio' => 0],
        );
    }

    /** @return Builder<EstoqueMovimento> */
    public function movimentos(Produto $produto): Builder
    {
        return EstoqueMovimento::query()
            ->where('produto_id', $produto->getKey())
            ->orderBy('id');
    }

    private function registrar(
        Produto $produto,
        TipoMovimentoEstoque $tipo,
        float $quantidade,
        ?float $custoUnitario,
        ?string $documento,
        ?User $user,
        ?string $justificativa = null,
        ?bool $somaAoSaldo = null,
    ): ?EstoqueMovimento {
        if ($quantidade <= 0) {
            throw new RuntimeException('A quantidade do movimento precisa ser maior que zero.');
        }

        // Serviço e produto sem controle de estoque não entram no razão: o
        // Kardex deve refletir só o que de fato tem saldo.
        if (! $produto->controla_estoque) {
            return null;
        }

        $soma = $somaAoSaldo ?? $tipo->positivo();

        return DB::transaction(function () use ($produto, $tipo, $quantidade, $custoUnitario, $documento, $user, $justificativa, $soma) {
            $saldo = $this->saldo($produto);

            // Lock pessimista: sem ele, duas saídas simultâneas leem o mesmo
            // saldo e ambas passam na checagem de disponibilidade.
            $saldo = EstoqueSaldo::query()->lockForUpdate()->find($saldo->getKey());

            $quantidadeAtual = (float) $saldo->quantidade;
            $custoAtual = (float) $saldo->custo_medio;

            if (! $soma) {
                $this->conferirDisponibilidade($produto, $quantidadeAtual, $quantidade);
            }

            $novaQuantidade = round($soma ? $quantidadeAtual + $quantidade : $quantidadeAtual - $quantidade, 4);
            $novoCusto = $this->recalcularCustoMedio(
                $tipo, $custoUnitario, $quantidadeAtual, $custoAtual, $quantidade,
            );

            $saldo->forceFill([
                'quantidade' => $novaQuantidade,
                'custo_medio' => $novoCusto,
            ])->save();

            return EstoqueMovimento::create([
                'emitente_id' => $produto->emitente_id,
                'produto_id' => $produto->getKey(),
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                // Na saída, guarda o custo médio vigente: a média muda depois
                // e o custo daquela saída se perderia.
                'custo_unitario' => $custoUnitario ?? ($soma ? null : $custoAtual),
                'saldo_apos' => $novaQuantidade,
                'custo_medio_apos' => $novoCusto,
                'documento' => $documento,
                'justificativa' => $justificativa,
                'user_id' => $user?->getKey() ?? auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    private function conferirDisponibilidade(Produto $produto, float $atual, float $quantidade): void
    {
        if ($produto->emitente->permite_saldo_negativo) {
            return;
        }

        if ($atual - $quantidade < 0) {
            throw new RuntimeException(
                "Saldo insuficiente para \"{$produto->descricao}\": disponível "
                .rtrim(rtrim(number_format($atual, 4, ',', '.'), '0'), ',')
                .', solicitado '.rtrim(rtrim(number_format($quantidade, 4, ',', '.'), '0'), ',').'.'
            );
        }
    }

    /**
     * Média ponderada: só entrada com custo conhecido move a média.
     * Saída, estorno e ajuste sem custo mantêm a média intacta.
     */
    private function recalcularCustoMedio(
        TipoMovimentoEstoque $tipo,
        ?float $custoUnitario,
        float $quantidadeAtual,
        float $custoAtual,
        float $quantidade,
    ): float {
        if (! $tipo->afetaCustoMedio() || $custoUnitario === null) {
            return round($custoAtual, 4);
        }

        $total = $quantidadeAtual + $quantidade;

        if ($total <= 0) {
            return round($custoUnitario, 4);
        }

        return round((($quantidadeAtual * $custoAtual) + ($quantidade * $custoUnitario)) / $total, 4);
    }
}
