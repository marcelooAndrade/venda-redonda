<?php

namespace App\Services\Fiscal;

/**
 * Resposta da SEFAZ, já interpretada.
 *
 * O cStat carrega o significado: 100 autorizado, 110 denegado, 104 lote
 * processado, 103 lote recebido, e a faixa 2xx/5xx é rejeição.
 */
readonly class RespostaSefaz
{
    public function __construct(
        public string $cStat,
        public string $xMotivo,
        public ?string $protocolo = null,
        public ?string $recibo = null,
        public ?string $xmlProtocolado = null,
        public ?string $chave = null,
    ) {}

    public function autorizada(): bool
    {
        return $this->cStat === '100';
    }

    public function denegada(): bool
    {
        return in_array($this->cStat, ['110', '301', '302', '303'], true);
    }

    /** Lote recebido, ainda sem resultado: precisa consultar o recibo. */
    public function emProcessamento(): bool
    {
        return in_array($this->cStat, ['103', '105'], true);
    }

    public function rejeitada(): bool
    {
        return ! $this->autorizada() && ! $this->denegada() && ! $this->emProcessamento();
    }
}
