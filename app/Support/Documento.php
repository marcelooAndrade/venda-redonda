<?php

namespace App\Support;

/**
 * Validação de CPF e CNPJ, incluindo o CNPJ alfanumérico.
 *
 * A Receita Federal emitiu o primeiro CNPJ com letra em 31/07/2026. O cálculo
 * do dígito verificador continua sendo módulo 11: o que muda é que cada
 * caractere vale seu código ASCII menos 48, então '0' vale 0, 'A' vale 17 e
 * 'Z' vale 42. Os dois últimos caracteres seguem sempre numéricos.
 *
 * Os dois formatos convivem: CNPJ numérico já emitido continua válido.
 *
 * Vetor de referência oficial: 12.ABC.345/01DE-35.
 */
class Documento
{
    /** @var array<int, int> */
    private const PESOS_CNPJ = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Tira máscara e sobe para maiúscula. Devolve sempre string: um CNPJ
     * iniciado por zero perderia o zero se fosse tratado como número.
     */
    public static function normalizarCnpj(string $valor): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $valor) ?? '');
    }

    public static function normalizarCpf(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    public static function cnpjValido(string $valor): bool
    {
        $cnpj = self::normalizarCnpj($valor);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Só dígitos e letras de A a Z. Acento ou símbolo reprova.
        if (preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) !== 1) {
            return false;
        }

        // Caractere repetido catorze vezes passa no módulo 11 mas não existe.
        if (preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $base = substr($cnpj, 0, 12);
        $primeiro = self::digitoCnpj($base);
        $segundo = self::digitoCnpj($base.$primeiro);

        return $cnpj === $base.$primeiro.$segundo;
    }

    public static function cpfValido(string $valor): bool
    {
        $cpf = self::normalizarCpf($valor);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
            }

            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $cpf[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /**
     * Módulo 11 sobre a base, com o valor de cada caractere sendo seu código
     * ASCII menos 48. Os pesos são os mesmos do CNPJ numérico de sempre.
     */
    private static function digitoCnpj(string $base): string
    {
        $pesos = array_slice(self::PESOS_CNPJ, -strlen($base));
        $soma = 0;

        foreach (str_split($base) as $i => $caractere) {
            $soma += (ord($caractere) - 48) * $pesos[$i];
        }

        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }
}
