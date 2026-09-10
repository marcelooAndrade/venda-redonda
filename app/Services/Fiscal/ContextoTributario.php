<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\AmbitoOperacao;
use App\Enums\Fiscal\IndIEDest;
use App\Models\PerfilFiscal;

/**
 * Tudo que o cálculo precisa saber, reunido num objeto só.
 *
 * É deliberadamente imutável: o cálculo não altera o pedido, e o mesmo
 * contexto sempre produz o mesmo resultado. Isso permite recalcular no
 * servidor na hora de transmitir e conferir contra o que a tela mostrou.
 */
readonly class ContextoTributario
{
    public function __construct(
        public PerfilFiscal $perfil,
        public string $crt,
        public string $ufEmitente,
        public string $ufDestinatario,
        public IndIEDest $indIEDest,
        public bool $consumidorFinal,
        public float $quantidade,
        public float $valorUnitario,
        public float $desconto = 0.0,
        public float $frete = 0.0,
        public float $seguro = 0.0,
        public float $outrasDespesas = 0.0,
        public string $data = 'now',
    ) {}

    public function ambito(): AmbitoOperacao
    {
        return AmbitoOperacao::paraUf($this->ufEmitente, $this->ufDestinatario);
    }
}
