<?php

namespace App\Services\Fiscal;

/**
 * Converte um PKCS#12 cifrado com algoritmo antigo (RC2-40 e afins) para
 * um formato que o OpenSSL 3 aceita sem o provider legacy.
 */
interface ConversorLegado
{
    /** Devolve o PFX convertido, ou null quando não for possível converter. */
    public function converter(string $pfx, string $senha): ?string;
}
