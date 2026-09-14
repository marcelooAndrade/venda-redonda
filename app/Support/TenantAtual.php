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

    /**
     * Verdadeiro quando o tenant veio do host (ou foi definido diretamente,
     * o que os testes tratam como equivalente), falso quando veio derivado
     * do emitente resolvido. É o que diferencia "domínio próprio de um
     * cliente, a busca de emitente fica presa a ele" de "domínio comum, a
     * busca atravessa empresas": sem essa distinção, depois que
     * `DefinirEmitenteDoContexto` deriva o tenant do primeiro emitente
     * resolvido, uma segunda leitura na mesma requisição (o seletor de
     * empresa no topo do layout) veria esse tenant como se o host o tivesse
     * fixado, e nunca mostraria a segunda empresa. Ver EmitenteAtual.
     */
    private bool $fixadoPeloHost = false;

    public function definir(?Tenant $tenant): void
    {
        $this->tenant = $tenant?->ativo === true ? $tenant : null;
        $this->fixadoPeloHost = $this->tenant !== null;
    }

    public function definirPorHost(string $host): void
    {
        $this->definir($this->resolverHost($host));
    }

    /**
     * Usado quando o tenant vem do emitente resolvido, não do host. Ao
     * contrário de `definir()`, não marca como fixado: uma leitura seguinte
     * de `EmitenteAtual` na mesma requisição continua podendo atravessar
     * empresas.
     */
    public function definirDoEmitente(?Tenant $tenant): void
    {
        $this->tenant = $tenant?->ativo === true ? $tenant : null;
    }

    public function fixadoPeloHost(): bool
    {
        return $this->fixadoPeloHost;
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
        $this->fixadoPeloHost = false;
    }

    private function resolverHost(string $host): ?Tenant
    {
        $host = strtolower(trim($host));

        // Domínio próprio tem prioridade, e com o host inteiro: é assim que
        // um cliente cadastrado como `app.rcmdobrasil.com.br` continua casando.
        $porDominio = Tenant::query()->where('dominio', $host)->first();

        if ($porDominio !== null) {
            return $porDominio;
        }

        $semPrefixo = HostDoProduto::semPrefixo($host);

        // Cadastrado sem o prefixo, atendido com ele.
        if ($semPrefixo !== $host) {
            $porDominioNu = Tenant::query()->where('dominio', $semPrefixo)->first();

            if ($porDominioNu !== null) {
                return $porDominioNu;
            }
        }

        // O domínio do produto não é de tenant nenhum: é superfície própria,
        // apresentação ou login. Sem esta parada, `app.<dominio>` cairia na
        // regra de slug abaixo e um tenant de slug `app` o capturaria.
        if (HostDoProduto::eDominioDoProduto($semPrefixo)) {
            return null;
        }

        $partes = explode('.', $semPrefixo);

        if (count($partes) < 2) {
            return null;
        }

        return Tenant::query()->where('slug', $partes[0])->first();
    }
}
