<?php

namespace App\Support;

use InvalidArgumentException;
use Normalizer;

/**
 * BR Code estático do Pix, no formato EMV do Banco Central.
 *
 * Portado de `src/lib/pix.ts` do projeto Marcelo Andrade, onde está em
 * produção, com os mesmos casos de teste. Não é lugar para improviso: o
 * payload é lido pelo aplicativo do banco do cliente, e um campo com tamanho
 * errado não dá erro aqui, dá erro na mão de quem ia pagar.
 *
 * A estrutura é uma sequência de campos `ID + tamanho em 2 dígitos + valor`,
 * fechada por um CRC16 calculado sobre tudo o que veio antes, inclusive o
 * próprio marcador `6304`.
 */
final class Pix
{
    /** CRC16 CCITT-FALSE, que é o que o BR Code exige. */
    public static function crc16(string $valor): string
    {
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($valor); $i++) {
            $crc ^= ord($valor[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return str_pad(strtoupper(dechex($crc)), 4, '0', STR_PAD_LEFT);
    }

    public static function payload(
        string $chave,
        string $nomeRecebedor,
        string $cidadeRecebedor,
        int $centavos,
        string $identificador,
    ): string {
        $chave = trim($chave);
        $nome = self::sanear($nomeRecebedor, 25);
        $cidade = self::sanear($cidadeRecebedor, 15);
        $txid = (string) preg_replace('/[^A-Z0-9]/', '', self::sanear($identificador, 25));
        $txid = $txid !== '' ? $txid : '***';

        if ($chave === '' || strlen($chave) > 77) {
            throw new InvalidArgumentException('Chave Pix inválida.');
        }

        if ($nome === '' || $cidade === '') {
            throw new InvalidArgumentException('Nome e cidade do recebedor são obrigatórios.');
        }

        if ($centavos <= 0) {
            throw new InvalidArgumentException('Valor Pix inválido.');
        }

        $conta = self::campo('00', 'BR.GOV.BCB.PIX').self::campo('01', $chave);

        $payload = implode('', [
            self::campo('00', '01'),
            self::campo('01', '12'),
            self::campo('26', $conta),
            self::campo('52', '0000'),
            self::campo('53', '986'),
            // Reais com duas casas, montados a partir do inteiro: dividir por
            // 100 em float traria de volta o erro que o `Dinheiro` evita.
            self::campo('54', intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT)),
            self::campo('58', 'BR'),
            self::campo('59', $nome),
            self::campo('60', $cidade),
            self::campo('62', self::campo('05', $txid)),
            '6304',
        ]);

        return $payload.self::crc16($payload);
    }

    /** Identificador estável por fatura e parcela, para conciliar o recebimento. */
    public static function identificador(string|int $faturaId, int $parcela): string
    {
        $referencia = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $faturaId), 0, 18));

        return substr($referencia.str_pad((string) $parcela, 3, '0', STR_PAD_LEFT), 0, 25);
    }

    private static function campo(string $id, string $valor): string
    {
        if (strlen($valor) > 99) {
            throw new InvalidArgumentException("Campo Pix {$id} ultrapassa o limite permitido.");
        }

        return $id.str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT).$valor;
    }

    /**
     * O BR Code só aceita um conjunto restrito de caracteres.
     *
     * Acento vira a letra sem acento, e o que não couber sai. "Distribuição"
     * precisa virar "DISTRIBUICAO", e não ser recusado.
     */
    private static function sanear(string $valor, int $limite): string
    {
        $texto = class_exists(Normalizer::class)
            ? (string) Normalizer::normalize($valor, Normalizer::FORM_D)
            : $valor;

        $texto = (string) preg_replace('/\p{Mn}/u', '', $texto);
        $texto = (string) preg_replace('/[^A-Za-z0-9 $%*+\-.\/:]/', '', $texto);

        return substr(strtoupper(trim($texto)), 0, $limite);
    }
}
