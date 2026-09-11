<?php

namespace App\Support;

/**
 * Dinheiro em centavos, inteiro, sem float em nenhum ponto.
 *
 * Portado de `parseMoneyToCents` do projeto Marcelo Andrade, com uma diferença
 * deliberada: lá a conversão passa por `Number(texto) * 100`, e é de onde vem a
 * classe de erro em que 0,07 vira 7.000000000000001. Aqui a conta é feita sobre
 * a string, então nenhum centavo depende de arredondamento binário.
 *
 * A regra de leitura é a do teclado brasileiro:
 *
 * - vírgula decide, sempre: é o separador decimal;
 * - só pontos, em grupos de três, é separador de milhar, porque quem digita
 *   `1.500` num campo de dinheiro quer mil e quinhentos;
 * - ponto fora desse padrão é decimal, que é o que o teclado numérico entrega.
 */
final class Dinheiro
{
    /** Lê o que a pessoa digitou e devolve centavos. */
    public static function emCentavos(string $valor): int
    {
        $texto = (string) preg_replace('/\s+/', '', $valor);
        $texto = (string) preg_replace('/r\$/i', '', $texto);

        if (! preg_match('/\d/', $texto)) {
            return 0;
        }

        $negativo = str_starts_with($texto, '-');
        $texto = ltrim($texto, '+-');

        [$inteiro, $fracao] = self::separar($texto);

        $inteiro = (string) preg_replace('/\D/', '', $inteiro);
        $fracao = (string) preg_replace('/\D/', '', $fracao);

        $centavos = (int) ($inteiro === '' ? '0' : $inteiro) * 100
            + (int) str_pad(substr($fracao, 0, 2), 2, '0');

        return $negativo ? -$centavos : $centavos;
    }

    /** Escreve centavos no padrão brasileiro, sem o símbolo da moeda. */
    public static function formatar(int $centavos): string
    {
        $sinal = $centavos < 0 ? '-' : '';
        $absoluto = abs($centavos);

        return $sinal
            .number_format(intdiv($absoluto, 100), 0, ',', '.')
            .','
            .str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @return array{0: string, 1: string} parte inteira e parte fracionária */
    private static function separar(string $texto): array
    {
        if (str_contains($texto, ',')) {
            $corte = (int) strrpos($texto, ',');

            return [str_replace('.', '', substr($texto, 0, $corte)), substr($texto, $corte + 1)];
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            return [str_replace('.', '', $texto), ''];
        }

        if (str_contains($texto, '.')) {
            $corte = (int) strrpos($texto, '.');

            return [substr($texto, 0, $corte), substr($texto, $corte + 1)];
        }

        return [$texto, ''];
    }
}
