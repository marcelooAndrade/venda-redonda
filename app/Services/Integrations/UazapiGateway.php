<?php

namespace App\Services\Integrations;

use App\Exceptions\UazapiIndisponivel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class UazapiGateway implements GatewayDeWhatsapp
{
    private const REPETICOES = 2;

    private const ESPERA_MS = 1000;

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
        // Corpo `{}` explícito: `post()` sem dados serializa `[]`, e a uazapi
        // responde "Invalid payload" (400) para isso. Medido em produção com
        // a instância real de um cliente: `[]` recusa, `{}` devolve o QR code.
        $resposta = $this->cliente()
            ->withHeader('token', $instanceToken)
            ->withBody('{}', 'application/json')
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
     * Repetição ligada por padrão, só em falha de conexão e erro 5xx
     * (PoliticaDeRepeticao, a mesma da ReceitaWS e do ViaCEP): 4xx é
     * definitivo, repetir só segura a resposta ao cliente. Desligada só
     * onde repetir é arriscado — ver enviarTexto(). `retry()` conta
     * tentativas, não repetições, por isso o `1 +`.
     */
    private function cliente(bool $comRepeticao = true): PendingRequest
    {
        $url = (string) config('integracao.uazapi.url');

        if ($url === '') {
            throw new UazapiIndisponivel('Módulo WhatsApp sem configuração da uazapi.');
        }

        $cliente = Http::baseUrl(rtrim($url, '/'))->timeout(15)->acceptJson();

        return $comRepeticao
            ? $cliente->retry(1 + self::REPETICOES, self::ESPERA_MS, fn (Throwable $e) => PoliticaDeRepeticao::valeRepetir($e), throw: false)
            : $cliente;
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
