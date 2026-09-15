<?php

namespace App\Http\Middleware;

use App\Enums\ModuloApi;
use App\Models\ApiCliente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que o cliente do Nodo autenticado tenha o módulo ligado.
 *
 * Não é habilidade do token (Sanctum `ability`): é dado do cliente,
 * guardado em `api_clientes.modulos` e controlado pelo admin em /admin.
 * Assim trocar o módulo de alguém não exige reemitir o token dele.
 */
class ExigeModuloApi
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        /** @var ApiCliente $cliente */
        $cliente = $request->user();

        abort_unless($cliente->temModulo(ModuloApi::from($modulo)), 403, 'Este cliente não tem este módulo ativo.');

        return $next($request);
    }
}
