<?php

namespace App\Services\Import;

use App\Models\NotaEntrada;
use App\Models\NotaEntradaItem;
use App\Models\Produto;
use App\Models\ProdutoFornecedor;
use Illuminate\Support\Facades\DB;

/**
 * Casa os itens da nota do fornecedor com o cadastro de produtos.
 *
 * A ordem das tentativas importa: o vínculo salvo vence tudo, porque foi um
 * humano que decidiu. GTIN vem depois, por ser identificador global. O resto
 * fica para o operador resolver na tela.
 */
class ConciliacaoService
{
    public function conciliarAutomaticamente(NotaEntrada $nota): int
    {
        $conciliados = 0;

        foreach ($nota->itens as $item) {
            if ($item->produto_id !== null) {
                continue;
            }

            $vinculo = $this->porVinculoSalvo($nota, $item);

            if ($vinculo !== null) {
                $item->update([
                    'produto_id' => $vinculo->produto_id,
                    'fator_conversao' => $vinculo->fator_conversao,
                ]);
                $conciliados++;

                continue;
            }

            $porGtin = $this->porGtin($item);

            if ($porGtin !== null) {
                $item->update(['produto_id' => $porGtin->getKey()]);
                $conciliados++;
            }
        }

        $this->atualizarStatus($nota->fresh('itens'));

        return $conciliados;
    }

    /** Vincula manualmente e guarda a decisão para as próximas importações. */
    public function vincular(
        NotaEntradaItem $item,
        Produto $produto,
        float $fatorConversao = 1.0,
        bool $lembrar = true,
    ): void {
        DB::transaction(function () use ($item, $produto, $fatorConversao, $lembrar): void {
            $item->update(['produto_id' => $produto->getKey(), 'fator_conversao' => $fatorConversao]);

            $nota = $item->notaEntrada;

            if ($lembrar && $nota->pessoa_id !== null) {
                ProdutoFornecedor::query()->updateOrCreate(
                    ['pessoa_id' => $nota->pessoa_id, 'codigo_fornecedor' => $item->codigo_fornecedor],
                    [
                        'emitente_id' => $nota->emitente_id,
                        'produto_id' => $produto->getKey(),
                        'descricao_fornecedor' => $item->descricao,
                        'fator_conversao' => $fatorConversao,
                    ],
                );
            }

            $this->atualizarStatus($nota->fresh('itens'));
        });
    }

    /** Cria o produto já preenchido com o que veio do XML. */
    public function criarProdutoDoItem(NotaEntradaItem $item, string $codigoInterno): Produto
    {
        $nota = $item->notaEntrada;

        $produto = Produto::create([
            'emitente_id' => $nota->emitente_id,
            'codigo' => $codigoInterno,
            'descricao' => $item->descricao,
            'gtin' => $item->gtin,
            'ncm' => $item->ncm,
            'unidade_comercial' => $item->unidade ?: 'UN',
            'unidade_tributavel' => $item->unidade ?: 'UN',
            'fator_conversao' => 1,
            'origem' => '0',
            'custo' => $item->custo_unitario,
        ]);

        $this->vincular($item, $produto);

        return $produto;
    }

    private function porVinculoSalvo(NotaEntrada $nota, NotaEntradaItem $item): ?ProdutoFornecedor
    {
        if ($nota->pessoa_id === null) {
            return null;
        }

        return ProdutoFornecedor::query()
            ->where('pessoa_id', $nota->pessoa_id)
            ->where('codigo_fornecedor', $item->codigo_fornecedor)
            ->first();
    }

    private function porGtin(NotaEntradaItem $item): ?Produto
    {
        if (blank($item->gtin)) {
            return null;
        }

        return Produto::query()
            ->where(fn ($q) => $q->where('gtin', $item->gtin)->orWhere('gtin_tributavel', $item->gtin))
            ->first();
    }

    private function atualizarStatus(NotaEntrada $nota): void
    {
        if ($nota->confirmada()) {
            return;
        }

        $nota->update([
            'status' => $nota->totalmenteConciliada() ? 'conciliada' : 'pendente',
        ]);
    }
}
