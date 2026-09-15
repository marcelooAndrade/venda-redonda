<?php

namespace App\Http\Controllers\ApiPlataforma;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarWebhookCliente;
use App\Models\ApiCliente;
use App\Models\WhatsappInstancia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsappWebhookController extends Controller
{
    /** O cliente escolhe para onde o Nodo repassa a mensagem que ele receber. */
    public function atualizarUrl(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:255'],
        ]);

        /** @var ApiCliente $cliente */
        $cliente = $request->user();
        $instancia = $cliente->whatsappInstancia;

        abort_if($instancia === null, 404, 'Crie a instância primeiro, em POST /whatsapp/v1/instancia.');

        $instancia->update(['webhook_url' => $dados['webhook_url'] ?? null]);

        return response()->json(['webhook_url' => $instancia->webhook_url]);
    }

    /**
     * Quem chama aqui é a uazapi, não o cliente: sem token, o segredo na
     * própria URL é que diz de qual instância veio. 200 sempre que o
     * segredo existe, mesmo sem webhook_url configurada — devolver erro
     * faria a uazapi ficar retentando uma mensagem que o cliente nunca
     * disse que queria receber.
     */
    public function receber(Request $request, string $secret): Response
    {
        $instancia = WhatsappInstancia::where('webhook_secret', $secret)->first();

        abort_if($instancia === null, 404);

        if (filled($instancia->webhook_url)) {
            EnviarWebhookCliente::dispatch($instancia->webhook_url, $request->all());
        }

        return response()->noContent();
    }
}
