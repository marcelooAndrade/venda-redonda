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
        // Sem repetição automática aqui, diferente dos outros métodos: não
        // dá para saber se uma falha "sem resposta" já mandou a mensagem do
        // outro lado. Repetir arriscaria mandar a mesma mensagem duas vezes,
        // o que WhatsApp não tem como desfazer.
        $resposta = $this->cliente(comRepeticao: false)
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

    public function listarGrupos(string $instanceToken): array
    {
        $resposta = $this->cliente()
            ->withHeader('token', $instanceToken)
            ->get('/group/list');

        $this->falharSeErro($resposta, 'listar os grupos');

        return (array) $resposta->json('groups', []);
    }

    /**
     * Repetição ligada por padrão: a uazapi já falhou de forma passageira
     * em produção (medido com a instância real de um cliente, que
     * funcionou normalmente ao repetir a mesma chamada minutos depois).
     * `AdminPessoalGateway` já usa o mesmo padrão. Desligada só onde
     * repetir é arriscado — ver enviarTexto().
     */
    private function cliente(bool $comRepeticao = true): PendingRequest
    {
        $url = (string) config('integracao.uazapi.url');

        if ($url === '') {
            throw new UazapiIndisponivel('Módulo WhatsApp sem configuração da uazapi.');
        }

        $cliente = Http::baseUrl(rtrim($url, '/'))->timeout(15)->acceptJson();

        return $comRepeticao ? $cliente->retry(2, 1000, throw: false) : $cliente;
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
