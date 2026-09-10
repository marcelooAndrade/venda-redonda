<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\AmbitoOperacao;

readonly class ResultadoTributario
{
    public function __construct(
        public AmbitoOperacao $ambito,
        public float $valorProduto,
        public float $valorDesconto,
        public ?string $cstIcms,
        public ?string $csosn,
        public float $baseIcms,
        public float $aliquotaIcms,
        public float $valorIcms,
        public float $valorFcp,
        public float $creditoSimplesNacional,
        public float $baseIcmsSt,
        public float $valorIcmsSt,
        public ?string $cstIpi,
        public float $valorIpi,
        public ?string $cstPis,
        public float $valorPis,
        public ?string $cstCofins,
        public float $valorCofins,
        public ?string $cstIbsCbs,
        public ?string $cClassTrib,
        public float $valorIbsUf,
        public float $valorIbsMun,
        public float $valorCbs,
        public float $valorIs,
    ) {}

    /** Total de tributos que compõem o valor da nota. */
    public function totalItem(): float
    {
        return round($this->valorProduto - $this->valorDesconto + $this->valorIpi + $this->valorIcmsSt, 2);
    }
}
