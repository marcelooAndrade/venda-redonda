<?php

namespace App\Enums\Fiscal;

/**
 * tpAmb da NF-e. Produção é 1, homologação é 2.
 */
enum Ambiente: string
{
    case Homologacao = 'homologacao';
    case Producao = 'producao';

    public function tpAmb(): int
    {
        return $this === self::Producao ? 1 : 2;
    }

    public function rotulo(): string
    {
        return $this === self::Producao ? 'Produção' : 'Homologação';
    }
}
