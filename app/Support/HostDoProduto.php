<?php

namespace App\Support;

/**
 * Classifica o host da requisição.
 *
 * O host responde duas perguntas: qual tenant, e qual superfície. Esta classe
 * cuida da segunda, e do prefixo `app.`, que é do produto e nunca de um
 * cliente.
 */
final class HostDoProduto
{
    private const PREFIXO = 'app.';

    public static function dominio(): string
    {
        return strtolower(trim((string) config('produto.dominio')));
    }

    /** @return array<int, string> */
    public static function slugsReservados(): array
    {
        return array_map('strtolower', (array) config('produto.slugs_reservados', []));
    }

    /** Remove um `app.` inicial. O resto do host fica intacto. */
    public static function semPrefixo(string $host): string
    {
        $host = strtolower(trim($host));

        return str_starts_with($host, self::PREFIXO)
            ? substr($host, strlen(self::PREFIXO))
            : $host;
    }

    /**
     * Verdadeiro para o domínio nu do produto e para o `www`.
     *
     * É o único host que mostra a apresentação. Todo o resto é aplicação,
     * porque a exceção é mais fácil de acertar do que a lista de exceções.
     */
    public static function eDominioDoProduto(string $host): bool
    {
        $host = strtolower(trim($host));
        $dominio = self::dominio();

        return $host === $dominio || $host === 'www.'.$dominio;
    }
}
