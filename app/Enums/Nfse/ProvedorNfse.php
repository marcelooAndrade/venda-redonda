<?php

namespace App\Enums\Nfse;

/**
 * Quem recebe a nota. Só o SIGISS existe hoje; o padrão nacional entra
 * como outro caso, com outro gateway, sem mexer no emissor.
 */
enum ProvedorNfse: string
{
    case Sigiss = 'sigiss';

    public function rotulo(): string
    {
        return match ($this) {
            self::Sigiss => 'SIGISS Araras',
        };
    }
}
