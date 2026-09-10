<?php

namespace App\Http\Middleware;

use App\Support\EmitenteAtual;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o emitente em foco como "time" das permissões da requisição.
 *
 * As permissões são escopadas por emitente, então sem isso o usuário chega
 * sem papel nenhum e recebe 403 mesmo sendo administrador. É por requisição,
 * e não por sessão, porque o escopo precisa acompanhar a troca de emitente.
 */
class DefinirEmitenteDoContexto
{
    public function __construct(
        private readonly EmitenteAtual $emitenteAtual,
        private readonly PermissionRegistrar $permissoes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $emitente = $this->emitenteAtual->resolver();

            $this->permissoes->setPermissionsTeamId($emitente?->getKey());
        }

        return $next($request);
    }
}
