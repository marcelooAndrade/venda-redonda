<?php

namespace App\Http\Middleware;

use App\Support\TenantAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descobre o tenant pelo host da requisição.
 *
 * Roda antes de tudo: o escopo global de tenant depende dele, e sem tenant
 * resolvido as consultas não devolvem nada. Falhar em silêncio é o
 * comportamento desejado aqui.
 */
class ResolverTenant
{
    public function __construct(
        private readonly TenantAtual $tenantAtual,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantAtual->definirPorHost($request->getHost());

        return $next($request);
    }
}
