<?php

namespace App\Services\Stock;

use App\Models\EstoqueMovimento;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inventário: acerta o saldo pela contagem física.
 *
 * O movimento gerado é sempre a **diferença**, nunca o total contado. Lançar
 * o total zeraria o histórico e faria o Kardex mentir sobre o que aconteceu.
 */
class InventarioService
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    /** Devolve null quando a contagem confere e nada precisa ser movimentado. */
    public function contar(
        Produto $produto,
        float $contado,
        string $justificativa,
        ?User $user = null,
    ): ?EstoqueMovimento {
        if (trim($justificativa) === '') {
            throw new RuntimeException(
                'Inventário exige justificativa: é ela que explica a diferença para o fisco e para o gestor.'
            );
        }

        $atual = (float) $this->stock->saldo($produto)->quantidade;
        $diferenca = round($contado - $atual, 4);

        if ($diferenca === 0.0) {
            return null;
        }

        return $this->stock->movimentarInventario($produto, abs($diferenca), $diferenca > 0, $justificativa, $user);
    }

    /**
     * @param  array<int, float>  $contagens  produto_id => quantidade contada
     * @return array{ajustados: int, conferidos: int}
     */
    public function contarLote(array $contagens, string $justificativa, ?User $user = null): array
    {
        return DB::transaction(function () use ($contagens, $justificativa, $user): array {
            $ajustados = 0;
            $conferidos = 0;

            foreach ($contagens as $produtoId => $contado) {
                $produto = Produto::find($produtoId);

                if ($produto === null) {
                    continue;
                }

                $this->contar($produto, (float) $contado, $justificativa, $user) === null
                    ? $conferidos++
                    : $ajustados++;
            }

            return ['ajustados' => $ajustados, 'conferidos' => $conferidos];
        });
    }
}
