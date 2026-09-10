<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\AmbitoOperacao;
use App\Enums\Fiscal\IndIEDest;
use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\PerfilFiscal;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recalcula os tributos e os totais da nota.
 *
 * Roda no servidor sempre, inclusive antes de transmitir. Imposto calculado
 * no navegador é imposto que o operador pode adulterar, e a diferença só
 * apareceria na fiscalização.
 */
class CalcularNota
{
    public function __construct(
        private readonly TaxCalculator $calculadora,
    ) {}

    public function recalcular(Nota $nota): Nota
    {
        $nota->loadMissing(['emitente', 'destinatario', 'itens.produto', 'naturezaOperacao']);

        $destinatario = $nota->destinatario;

        if ($destinatario === null) {
            throw new RuntimeException('Escolha o destinatário antes de calcular a nota.');
        }

        return DB::transaction(function () use ($nota, $destinatario): Nota {
            $totais = $this->zerados();

            foreach ($nota->itens as $item) {
                $perfil = $this->perfilDe($nota, $item->produto);

                if ($perfil === null) {
                    throw new RuntimeException(
                        "O produto \"{$item->descricao}\" não tem perfil fiscal, e a natureza da operação "
                        .'também não define um. Peça ao contador para vincular a regra.'
                    );
                }

                $r = $this->calculadora->calcular(new ContextoTributario(
                    perfil: $perfil,
                    crt: (string) $nota->emitente->crt,
                    ufEmitente: (string) $nota->emitente->uf,
                    ufDestinatario: (string) $destinatario->uf,
                    indIEDest: $destinatario->ind_ie_dest ?? IndIEDest::NaoContribuinte,
                    consumidorFinal: (bool) $nota->consumidor_final,
                    quantidade: (float) $item->quantidade,
                    valorUnitario: (float) $item->valor_unitario,
                    desconto: (float) $item->valor_desconto,
                    frete: (float) $item->valor_frete,
                    seguro: (float) $item->valor_seguro,
                    outrasDespesas: (float) $item->valor_outros,
                    data: $nota->data_emissao->toDateString(),
                ));

                $item->forceFill([
                    'cst_icms' => $r->cstIcms,
                    'csosn' => $r->csosn,
                    'mod_bc' => $r->cstIcms !== null ? '3' : null,
                    'base_icms' => $r->baseIcms,
                    'aliquota_icms' => $r->aliquotaIcms,
                    'valor_icms' => $r->valorIcms,
                    'valor_fcp' => $r->valorFcp,
                    'base_icms_st' => $r->baseIcmsSt,
                    'valor_icms_st' => $r->valorIcmsSt,
                    'credito_sn' => $r->creditoSimplesNacional,
                    'cst_ipi' => $r->cstIpi,
                    'valor_ipi' => $r->valorIpi,
                    'cst_pis' => $r->cstPis,
                    'valor_pis' => $r->valorPis,
                    'cst_cofins' => $r->cstCofins,
                    'valor_cofins' => $r->valorCofins,
                    'cst_ibscbs' => $r->cstIbsCbs,
                    'cclasstrib' => $r->cClassTrib,
                    'valor_ibs_uf' => $r->valorIbsUf,
                    'valor_ibs_mun' => $r->valorIbsMun,
                    'valor_cbs' => $r->valorCbs,
                    'valor_is' => $r->valorIs,
                    'valor_produto' => $r->valorProduto,
                ])->save();

                $totais['valor_produtos'] += $r->valorProduto;
                $totais['valor_desconto'] += $r->valorDesconto;
                $totais['valor_frete'] += (float) $item->valor_frete;
                $totais['valor_seguro'] += (float) $item->valor_seguro;
                $totais['valor_outros'] += (float) $item->valor_outros;
                $totais['base_icms'] += $r->baseIcms;
                $totais['valor_icms'] += $r->valorIcms;
                $totais['valor_icms_st'] += $r->valorIcmsSt;
                $totais['valor_fcp'] += $r->valorFcp;
                $totais['valor_ipi'] += $r->valorIpi;
                $totais['valor_pis'] += $r->valorPis;
                $totais['valor_cofins'] += $r->valorCofins;
                $totais['valor_ibs'] += $r->valorIbsUf + $r->valorIbsMun;
                $totais['valor_cbs'] += $r->valorCbs;
                $totais['valor_is'] += $r->valorIs;
            }

            // vNF = produtos - desconto + frete + seguro + outros + IPI + ST
            $totais['valor_nota'] = round(
                $totais['valor_produtos'] - $totais['valor_desconto']
                + $totais['valor_frete'] + $totais['valor_seguro'] + $totais['valor_outros']
                + $totais['valor_ipi'] + $totais['valor_icms_st'],
                2,
            );

            $nota->forceFill(array_map(fn ($v) => round($v, 2), $totais) + [
                'id_dest' => $this->calculadora
                    ? AmbitoOperacao::paraUf((string) $nota->emitente->uf, (string) $destinatario->uf)->idDest()
                    : '1',
            ])->save();

            return $nota->fresh(['itens']);
        });
    }

    /** Copia os dados do produto para o item, congelando-os na nota. */
    public function adicionarItem(Nota $nota, Produto $produto, float $quantidade, ?float $valorUnitario = null): NotaItem
    {
        $natureza = $nota->naturezaOperacao;
        $ambito = AmbitoOperacao::paraUf(
            (string) $nota->emitente->uf,
            (string) ($nota->destinatario?->uf ?? $nota->emitente->uf),
        );

        return NotaItem::create([
            'nota_id' => $nota->getKey(),
            'produto_id' => $produto->getKey(),
            'numero' => ((int) $nota->itens()->max('numero')) + 1,
            'codigo' => $produto->codigo,
            'descricao' => $produto->descricao,
            'gtin' => $produto->gtin,
            'ncm' => $produto->ncm,
            'cest' => $produto->cest,
            'cfop' => $natureza?->cfopPara($ambito) ?? '5102',
            'unidade' => $produto->unidade_comercial,
            'unidade_tributavel' => $produto->unidade_tributavel,
            'origem' => $produto->origem,
            'quantidade' => $quantidade,
            'quantidade_tributavel' => round($quantidade * (float) $produto->fator_conversao, 4),
            'valor_unitario' => $valorUnitario ?? (float) $produto->preco_venda,
            'valor_produto' => round($quantidade * ($valorUnitario ?? (float) $produto->preco_venda), 2),
        ]);
    }

    /** @return array<string, float> */
    private function zerados(): array
    {
        return [
            'valor_produtos' => 0, 'valor_desconto' => 0, 'valor_frete' => 0,
            'valor_seguro' => 0, 'valor_outros' => 0, 'base_icms' => 0,
            'valor_icms' => 0, 'valor_icms_st' => 0, 'valor_fcp' => 0,
            'valor_ipi' => 0, 'valor_pis' => 0, 'valor_cofins' => 0,
            'valor_ibs' => 0, 'valor_cbs' => 0, 'valor_is' => 0, 'valor_nota' => 0,
        ];
    }

    private function perfilDe(Nota $nota, ?Produto $produto): ?PerfilFiscal
    {
        return $produto?->perfilFiscal ?? $nota->naturezaOperacao?->perfilFiscal;
    }
}
