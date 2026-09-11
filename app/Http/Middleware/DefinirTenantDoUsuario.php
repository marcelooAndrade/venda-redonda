<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixa o tenant a partir do usuário autenticado.
 *
 * O host resolve o tenant antes de tudo, mas no domínio do produto não há
 * tenant nenhum para resolver: é lá que todo cliente entra pela mesma porta.
 * Depois da autenticação, quem diz de quem é a sessão é o vínculo do usuário,
 * e ele vence o que o host tiver dito.
 */
class DefinirTenantDoUsuario
{
    public function __construct(
        private readonly TenantAtual $tenantAtual,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->user()?->tenant_id;

        if ($id !== null) {
            $this->tenantAtual->definir(Tenant::find($id));
        }

        return $next($request);
    }
}
