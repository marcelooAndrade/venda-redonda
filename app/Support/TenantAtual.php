<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Guarda o tenant da requisição corrente.
 *
 * É um singleton do container, não sessão: o tenant vem do host, e o host
 * não muda no meio de uma requisição. Guardar em sessão permitiria que um
 * usuário carregasse o tenant de um host para outro.
 */
class TenantAtual
{
    private ?Tenant $tenant = null;

    public function definir(?Tenant $tenant): void
    {
        $this->tenant = $tenant?->ativo === true ? $tenant : null;
    }

    public function definirPorHost(string $host): void
    {
        $this->definir($this->resolverHost($host));
    }

    public function obter(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function limpar(): void
    {
        $this->tenant = null;
    }

    private function resolverHost(string $host): ?Tenant
    {
        $host = strtolower(trim($host));

        // Domínio próprio tem prioridade: é mais específico que subdomínio.
        $porDominio = Tenant::query()->where('dominio', $host)->first();

        if ($porDominio !== null) {
            return $porDominio;
        }

        $partes = explode('.', $host);

        if (count($partes) < 2) {
            return null;
        }

        return Tenant::query()->where('slug', $partes[0])->first();
    }
}
