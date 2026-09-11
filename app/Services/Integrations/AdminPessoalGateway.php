<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AdminPessoalGateway implements GatewayDeLeads
{
    private const CAMINHO = '/api/integrations/venda-redonda/leads';

    public function enviar(array $leads): void
    {
        $url = (string) config('integracao.admin_pessoal.url');
        $token = (string) config('integracao.admin_pessoal.token');

        // Sem configuração o envio simplesmente não acontece. É o que mantém
        // desenvolvimento e teste sem chamada externa, sem precisar de if
        // espalhado em quem chama.
        if ($url === '' || $token === '' || $leads === []) {
            Log::info('Envio de leads desligado ou sem conteúdo.', ['quantidade' => count($leads)]);

            return;
        }

        $resposta = Http::timeout(15)
            ->retry(2, 1000, throw: false)
            ->withToken($token)
            ->post(rtrim($url, '/').self::CAMINHO, ['leads' => $leads]);

        if ($resposta->failed()) {
            // Lançar é de propósito: quem chama é um job com tentativas, e a
            // falha precisa chegar nele para a fila tentar de novo.
            throw new RuntimeException(
                'O admin pessoal recusou os leads. Status '.$resposta->status()
            );
        }
    }
}
