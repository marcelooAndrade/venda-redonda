<?php

namespace App\Support;

/**
 * Validação de GTIN (código de barras) nos formatos aceitos pela NF-e:
 * GTIN-8, 12, 13 e 14.
 *
 * O dígito verificador é módulo 10 com pesos alternados 3 e 1, aplicados da
 * direita para a esquerda.
 */
class Gtin
{
    /** Literal que a NF-e espera quando o produto não tem código de barras. */
    public const SEM_GTIN = 'SEM GTIN';

    private const TAMANHOS = [8, 12, 13, 14];

    public static function normalizar(?string $valor): string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? self::SEM_GTIN : $valor;
    }

    public static function valido(?string $valor): bool
    {
        $valor = self::normalizar($valor);

        if (strcasecmp($valor, self::SEM_GTIN) === 0) {
            return true;
        }

        if (preg_match('/^\d+$/', $valor) !== 1 || ! in_array(strlen($valor), self::TAMANHOS, true)) {
            return false;
        }

        $digitos = array_map('intval', str_split($valor));
        $informado = array_pop($digitos);

        $soma = 0;
        // Pesos 3 e 1 alternados, a partir da direita da base.
        foreach (array_reverse($digitos) as $i => $digito) {
            $soma += $digito * ($i % 2 === 0 ? 3 : 1);
        }

        return $informado === (10 - ($soma % 10)) % 10;
    }
}
