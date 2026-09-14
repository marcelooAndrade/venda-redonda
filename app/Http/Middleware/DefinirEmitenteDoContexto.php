<?php

namespace App\Http\Middleware;

use App\Support\EmitenteAtual;
use App\Support\TenantAtual;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o emitente em foco e, a partir dele, o tenant da sessão.
 *
 * Até 14/09/2026 quem fixava o tenant era `DefinirTenantDoUsuario`, a partir
 * de `users.tenant_id`, sempre, mesmo quando o host já tinha resolvido outro
 * tenant. Isso parou de fazer sentido quando um login passou a poder
 * alcançar mais de uma empresa: agora a regra é "o host manda quando já
 * mandou".
 *
 * Com um tenant já fixado pelo host (domínio próprio de um cliente), este
 * middleware só confirma o emitente dentro dele: `EmitenteAtual::resolver()`
 * já restringe a busca sozinho, porque o escopo de `Emitente` está ativo.
 * Sem tenant fixado (domínio comum, onde todo cliente entra hoje), o
 * emitente é resolvido através de todas as empresas do login, e é o tenant
 * *dele* que passa a valer para o resto da requisição.
 *
 * Depois, o time das permissões do spatie vira o emitente resolvido: sem
 * isso o usuário chega sem papel nenhum e leva 403 mesmo sendo administrador.
 */
class DefinirEmitenteDoContexto
{
    public function __construct(
        private readonly EmitenteAtual $emitenteAtual,
        private readonly TenantAtual $tenantAtual,
        private readonly PermissionRegistrar $permissoes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $emitente = $this->emitenteAtual->resolver();

            if ($this->tenantAtual->id() === null && $emitente !== null) {
                $this->tenantAtual->definirDoEmitente($emitente->tenant);
            }

            $this->permissoes->setPermissionsTeamId($emitente?->getKey());
        }

        return $next($request);
    }
}
