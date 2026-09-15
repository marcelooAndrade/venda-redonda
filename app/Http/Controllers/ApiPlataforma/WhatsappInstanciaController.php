<?php

namespace App\Http\Controllers\ApiPlataforma;

use App\Exceptions\UazapiIndisponivel;
use App\Http\Controllers\Controller;
use App\Models\ApiCliente;
use App\Models\WhatsappInstancia;
use App\Services\Integrations\GatewayDeWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappInstanciaController extends Controller
{
    public function __construct(private readonly GatewayDeWhatsapp $gateway) {}

    /**
     * Cria a instância uazapi do cliente autenticado. Idempotente: se o
     * cliente já tem uma, devolve a existente em vez de criar outra —
     * clique duplo não pode custar uma instância a mais na uazapi.
     */
    public function store(Request $request): JsonResponse
    {
        $cliente = $this->clienteAutenticado($request);

        $instancia = $cliente->whatsappInstancia;

        if (! $instancia) {
            try {
                $criada = $this->gateway->criarInstancia("cliente-{$cliente->id}");
            } catch (UazapiIndisponivel $e) {
                return response()->json(['erro' => $e->getMessage()], 503);
            }

            $instancia = $cliente->whatsappInstancia()->create([
                'uazapi_instance_id' => $criada['id'],
                'uazapi_token' => $criada['token'],
                'nome' => "cliente-{$cliente->id}",
                'status' => 'disconnected',
            ]);
        }

        return response()->json(['status' => $instancia->status], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $instancia = $this->instanciaOu404($request);

        try {
            $status = $this->gateway->status($instancia->uazapi_token);
        } catch (UazapiIndisponivel $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        $instancia->update(['status' => $status['status']]);

        return response()->json($status);
    }

    public function conectar(Request $request): JsonResponse
    {
        $instancia = $this->instanciaOu404($request);

        try {
            $conexao = $this->gateway->conectar($instancia->uazapi_token);
        } catch (UazapiIndisponivel $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        $instancia->update(['status' => $conexao['status']]);

        return response()->json($conexao);
    }

    private function instanciaOu404(Request $request): WhatsappInstancia
    {
        $instancia = $this->clienteAutenticado($request)->whatsappInstancia;

        abort_if($instancia === null, 404, 'Crie a instância primeiro, em POST /whatsapp/v1/instancia.');

        return $instancia;
    }

    /**
     * `Request::user()` devolve `Authenticatable` genérico: o guard
     * `sanctum` é polimórfico por token, e o Larastan não enxerga isso
     * sozinho. Aqui é sempre `ApiCliente`, garantido pelo middleware
     * `auth:sanctum` da rota.
     */
    private function clienteAutenticado(Request $request): ApiCliente
    {
        /** @var ApiCliente */
        return $request->user();
    }
}
