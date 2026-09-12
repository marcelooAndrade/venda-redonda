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

        // A resposta pode ter sucesso HTTP e ainda assim recusar leads
        // individuais por dado ruim (o corpo devolve {processados, recusados}).
        // Sem ler isso aqui, a contagem de recusados morre na resposta e
        // ninguém do lado Laravel fica sabendo que leads estão sendo perdidos.
        $recusados = (int) $resposta->json('recusados', 0);

        if ($recusados > 0) {
            Log::warning('O admin pessoal recusou leads do lote.', [
                'recusados' => $recusados,
                'enviados' => count($leads),
            ]);
        }
    }
}
