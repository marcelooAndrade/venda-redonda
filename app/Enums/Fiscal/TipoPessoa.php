<?php

namespace App\Enums\Fiscal;

enum TipoPessoa: string
{
    case Fisica = 'F';
    case Juridica = 'J';

    public function rotulo(): string
    {
        return $this === self::Fisica ? 'Pessoa física' : 'Pessoa jurídica';
    }

    public function documentoRotulo(): string
    {
        return $this === self::Fisica ? 'CPF' : 'CNPJ';
    }
}
