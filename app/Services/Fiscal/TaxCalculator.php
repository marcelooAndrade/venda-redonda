<?php

namespace App\Services\Fiscal;

use App\Models\PerfilFiscalRegra;

/**
 * Aplica a regra fiscal escrita pelo contador. Não decide tributação:
 * nenhuma alíquota, CST ou CFOP está codificado aqui.
 *
 * Todo total é calculado no servidor. Imposto calculado no navegador é
 * imposto que o operador pode adulterar.
 */
class TaxCalculator
{
    /** CSOSN que permitem aproveitamento de crédito pelo destinatário. */
    private const CSOSN_COM_CREDITO = ['101', '201'];

    /** CST de ICMS sem destaque de imposto. */
    private const CST_SEM_DESTAQUE = ['40', '41', '50', '60'];

    public function __construct(
        private readonly ResolverRegraFiscal $resolver,
    ) {}

    public function calcular(ContextoTributario $ctx): ResultadoTributario
    {
        $ambito = $ctx->ambito();
        $regra = $this->resolver->resolver($ctx->perfil, $ambito, $ctx->crt, $ctx->data);

        $valorProduto = $this->arredondar($ctx->quantidade * $ctx->valorUnitario);

        // Base comum: produto menos desconto, mais os acessórios rateados.
        $baseComum = $this->arredondar(
            $valorProduto - $ctx->desconto + $ctx->frete + $ctx->seguro + $ctx->outrasDespesas
        );

        $icms = $this->calcularIcms($regra, $baseComum);
        $st = $this->calcularSt($regra, $baseComum, $icms['valor']);

        return new ResultadoTributario(
            ambito: $ambito,
            valorProduto: $valorProduto,
            valorDesconto: $this->arredondar($ctx->desconto),
            cstIcms: $regra->cst_icms,
            csosn: $regra->csosn,
            baseIcms: $icms['base'],
            aliquotaIcms: (float) ($regra->aliquota_icms ?? 0),
            valorIcms: $icms['valor'],
            valorFcp: $this->percentual($icms['base'], $regra->aliquota_fcp),
            creditoSimplesNacional: $this->creditoSn($regra, $baseComum),
            baseIcmsSt: $st['base'],
            valorIcmsSt: $st['valor'],
            cstIpi: $regra->cst_ipi,
            valorIpi: $this->percentual($valorProduto, $regra->aliquota_ipi),
            cstPis: $regra->cst_pis,
            valorPis: $this->percentual($baseComum, $regra->aliquota_pis),
            cstCofins: $regra->cst_cofins,
            valorCofins: $this->percentual($baseComum, $regra->aliquota_cofins),
            cstIbsCbs: $regra->cst_ibscbs,
            cClassTrib: $regra->cclasstrib,
            valorIbsUf: $this->percentual($baseComum, $regra->aliquota_ibs_uf),
            valorIbsMun: $this->percentual($baseComum, $regra->aliquota_ibs_mun),
            valorCbs: $this->percentual($baseComum, $regra->aliquota_cbs),
            valorIs: $this->percentual($baseComum, $regra->aliquota_is),
        );
    }

    /** @return array{base: float, valor: float} */
    private function calcularIcms(PerfilFiscalRegra $regra, float $baseComum): array
    {
        // Simples Nacional não destaca ICMS: usa CSOSN.
        if ($regra->csosn !== null) {
            return ['base' => 0.0, 'valor' => 0.0];
        }

        if ($regra->cst_icms === null || in_array($regra->cst_icms, self::CST_SEM_DESTAQUE, true)) {
            return ['base' => 0.0, 'valor' => 0.0];
        }

        $base = $baseComum;

        if ($regra->reducao_bc !== null) {
            $base = $this->arredondar($base * (1 - ((float) $regra->reducao_bc / 100)));
        }

        return [
            'base' => $base,
            'valor' => $this->percentual($base, $regra->aliquota_icms),
        ];
    }

    /**
     * ST: a base é a base comum acrescida da MVA, e o imposto devido é o ST
     * calculado sobre ela menos o ICMS próprio já destacado.
     *
     * @return array{base: float, valor: float}
     */
    private function calcularSt(PerfilFiscalRegra $regra, float $baseComum, float $icmsProprio): array
    {
        if ($regra->mva_st === null && $regra->aliquota_st === null) {
            return ['base' => 0.0, 'valor' => 0.0];
        }

        $base = $this->arredondar($baseComum * (1 + ((float) ($regra->mva_st ?? 0) / 100)));

        if ($regra->reducao_bc_st !== null) {
            $base = $this->arredondar($base * (1 - ((float) $regra->reducao_bc_st / 100)));
        }

        $bruto = $this->percentual($base, $regra->aliquota_st);

        return [
            'base' => $base,
            'valor' => max(0.0, $this->arredondar($bruto - $icmsProprio)),
        ];
    }

    private function creditoSn(PerfilFiscalRegra $regra, float $baseComum): float
    {
        if ($regra->csosn === null || ! in_array($regra->csosn, self::CSOSN_COM_CREDITO, true)) {
            return 0.0;
        }

        return $this->percentual($baseComum, $regra->aliquota_credito_sn);
    }

    private function percentual(float $base, mixed $aliquota): float
    {
        if ($aliquota === null) {
            return 0.0;
        }

        return $this->arredondar($base * ((float) $aliquota / 100));
    }

    private function arredondar(float $valor): float
    {
        return round($valor, 2);
    }
}
