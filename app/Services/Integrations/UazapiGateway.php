<?php

namespace App\Services\Integrations;

use App\Exceptions\UazapiIndisponivel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class UazapiGateway implements GatewayDeWhatsapp
{
    public function criarInstancia(string $nome): array
    {
        $adminToken = $this->adminToken();

        $resposta = $this->cliente()
            ->withHeader('AdminToken', $adminToken)
            ->post('/instance/init', ['name' => $nome]);

        $this->falharSeErro($resposta, 'criar a instância');

        return [
            'id' => (string) $resposta->json('instance.id'),
            'token' => (string) $resposta->json('token'),
        ];
    }

    public function conectar(string $instanceToken): array
    {
        $resposta = $this->cliente()
            ->withHeader('token', $instanceToken)
            ->post('/instance/connect');

        $this->falharSeErro($resposta, 'conectar a instância');

        return [
            'qrcode' => $resposta->json('instance.qrcode') ?: null,
            'status' => (string) $resposta->json('instance.status'),
        ];
    }

    public function status(string $instanceToken): array
    {
        $resposta = $this->cliente()
            ->withHeader('token', $instanceToken)
            ->get('/instance/status');

        $this->falharSeErro($resposta, 'consultar o status da instância');

        return [
            'status' => (string) $resposta->json('instance.status'),
            'conectado' => (bool) $resposta->json('status.connected'),
        ];
    }

    public function enviarTexto(string $instanceToken, string $numero, string $texto): array
    {
        $resposta = $this->cliente()
            ->withHeader('token', $instanceToken)
            ->post('/send/text', ['number' => $numero, 'text' => $texto]);

        $this->falharSeErro($resposta, 'mandar a mensagem');

        return (array) $resposta->json();
    }

    public function configurarWebhook(string $instanceToken, string $urlDoNodo): void
    {
        $resposta = $this->cliente()
            ->withHeader('token', $instanceToken)
            ->post('/webhook', ['url' => $urlDoNodo, 'enabled' => true, 'events' => ['messages']]);

        $this->falharSeErro($resposta, 'configurar o webhook');
    }

    private function cliente(): PendingRequest
    {
        $url = (string) config('integracao.uazapi.url');

        if ($url === '') {
            throw new UazapiIndisponivel('Módulo WhatsApp sem configuração da uazapi.');
        }

        return Http::baseUrl(rtrim($url, '/'))->timeout(15)->acceptJson();
    }

    private function adminToken(): string
    {
        $token = (string) config('integracao.uazapi.admin_token');

        if ($token === '') {
            throw new UazapiIndisponivel('Módulo WhatsApp sem configuração da uazapi.');
        }

        return $token;
    }

    private function falharSeErro(Response $resposta, string $acao): void
    {
        if ($resposta->failed()) {
            throw new UazapiIndisponivel("A uazapi recusou {$acao}. Status {$resposta->status()}.");
        }
    }
}
