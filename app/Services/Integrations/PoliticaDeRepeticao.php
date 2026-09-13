<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Quando vale repetir uma chamada síncrona a uma integração externa.
 *
 * Falha de conexão e erro do servidor podem ser passageiros, e meio segundo
 * depois a chamada pode passar. Resposta 4xx é definitiva: 429 é limite
 * por minuto, que não abre em meio segundo, e 402 é cota esgotada, que não
 * volta sozinha. O 504 também é definitivo: na API Pública da ReceitaWS ele
 * significa "este CNPJ não está no cache", não "tente de novo", e na
 * Comercial significa que a própria ReceitaWS já esperou 30 segundos pela
 * Receita Federal. Repetir nesses casos só gasta cota e segura o clique.
 */
final class PoliticaDeRepeticao
{
    public static function valeRepetir(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status >= 500 && $status !== 504;
        }

        return false;
    }
}
