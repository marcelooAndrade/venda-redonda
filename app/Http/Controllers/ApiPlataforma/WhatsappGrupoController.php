<?php

namespace App\Http\Controllers\ApiPlataforma;

use App\Exceptions\UazapiIndisponivel;
use App\Http\Controllers\Controller;
use App\Models\ApiCliente;
use App\Services\Integrations\GatewayDeWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappGrupoController extends Controller
{
    public function __construct(private readonly GatewayDeWhatsapp $gateway) {}

    public function index(Request $request): JsonResponse
    {
        /** @var ApiCliente $cliente */
        $cliente = $request->user();
        $instancia = $cliente->whatsappInstancia;

        abort_if($instancia === null, 404, 'Crie a instância primeiro, em POST /whatsapp/v1/instancia.');

        if ($instancia->status !== 'connected') {
            return response()->json([
                'erro' => 'Instância não conectada. Chame POST /whatsapp/v1/instancia/conectar e escaneie o QR code antes de listar grupos.',
            ], 422);
        }

        try {
            $grupos = $this->gateway->listarGrupos($instancia->uazapi_token);
        } catch (UazapiIndisponivel $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        return response()->json(['grupos' => $grupos]);
    }
}
