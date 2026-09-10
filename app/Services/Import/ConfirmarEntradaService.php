<?php

namespace App\Services\Import;

use App\Models\NotaEntrada;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Efetiva a entrada no estoque.
 *
 * Separado da importação de propósito: importar é registrar o que chegou,
 * confirmar é assumir que aquilo entrou. Entre um e outro está a conciliação,
 * que é onde um humano confere.
 */
class ConfirmarEntradaService
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function confirmar(NotaEntrada $nota, ?User $user = null): NotaEntrada
    {
        if ($nota->confirmada()) {
            throw new RuntimeException("A nota {$nota->numero} já foi confirmada em ".$nota->confirmada_em?->format('d/m/Y H:i').'.');
        }

        $nota->loadMissing('itens.produto');

        // Nota própria é histórico de outro sistema: registra, não movimenta.
        if ($nota->tipo !== 'propria' && ! $nota->totalmenteConciliada()) {
            $pendentes = $nota->itens->whereNull('produto_id')->pluck('descricao')->take(3)->implode(', ');

            throw new RuntimeException(
                "Há itens ainda não conciliados nesta nota: {$pendentes}. "
                .'Vincule cada item a um produto antes de confirmar a entrada.'
            );
        }

        return DB::transaction(function () use ($nota, $user): NotaEntrada {
            if ($nota->tipo !== 'propria') {
                $documento = "NF {$nota->numero}/{$nota->serie} · ".($nota->pessoa?->razao_social ?? 'fornecedor');

                foreach ($nota->itens as $item) {
                    if ($item->produto === null) {
                        continue;
                    }

                    $this->stock->entrada(
                        $item->produto,
                        $item->quantidadeInterna(),
                        $item->custoInterno(),
                        $documento,
                        $user,
                    );
                }
            }

            $nota->update([
                'status' => 'confirmada',
                'confirmada_em' => now(),
                'confirmada_por' => $user?->getKey(),
            ]);

            return $nota->fresh();
        });
    }
}
