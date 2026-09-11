<?php

namespace App\Services\Integrations;

use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Monta o que o admin pessoal precisa saber sobre um tenant.
 *
 * O telefone vem do emitente, e não do usuário: o cadastro pede um telefone da
 * empresa, que é com quem se fala.
 *
 * `Emitente` e `User` escopam suas próprias consultas pelo tenant do
 * container (`TenantAtual`), e não pelo tenant deste método. Este serviço lê
 * de propósito acima dessa fronteira: ele processa qualquer tenant, inclusive
 * quando não há nenhum tenant resolvido no container (comando agendado, sem
 * host) ou quando o tenant resolvido é outro (chamado de dentro de uma
 * requisição comum). Por isso toda leitura de `Emitente` e `User` aqui passa
 * por `withoutGlobalScope('tenant')`, com o `tenant_id` do parâmetro amarrado
 * explicitamente na consulta. Sem isso, o escopo do `Emitente` (que nega tudo
 * quando não há tenant no container) faria este serviço devolver sempre
 * zero leads a partir de um comando agendado, que é exatamente o uso que o
 * motiva.
 */
class MontadorDeLeads
{
    /** @return array<string, mixed> */
    public function paraTenant(Tenant $tenant): array
    {
        $emitente = Emitente::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id')
            ->first();

        $usuarios = User::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id')
            ->get();

        $primeiro = $usuarios->first();

        // O último acesso do tenant é o mais recente entre as pessoas dele.
        $ultimoAcesso = $usuarios->max('ultimo_acesso_em');

        return [
            'tenant_id' => $tenant->getKey(),
            'cnpj' => (string) $emitente?->cnpj,
            'empresa' => $tenant->nome,
            'nome' => (string) $primeiro?->name,
            'email' => (string) $primeiro?->email,
            'telefone' => $emitente?->telefone,
            'plano' => $tenant->plano->value,
            'cadastrado_em' => $tenant->created_at?->toIso8601String(),
            'ultimo_acesso_em' => $ultimoAcesso ? Carbon::parse($ultimoAcesso)->toIso8601String() : null,
        ];
    }

    /**
     * Todos os tenants que têm o que mandar.
     *
     * Tenant sem emitente ou sem usuário sairia com cnpj, nome e email vazios,
     * e o outro lado recusa o lote inteiro no primeiro registro inválido. Um
     * cadastro pela metade não pode travar a sincronização dos outros.
     *
     * O filtro de existência também precisa ler acima da fronteira de
     * tenant, pelo mesmo motivo de paraTenant(): whereHas() aplica o escopo
     * global do model relacionado à subconsulta, e sem removê-lo aqui um
     * tenant só passaria pelo filtro se fosse, por acaso, o tenant do
     * container no momento da chamada.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paraTodos(): array
    {
        return Tenant::query()
            ->whereHas('emitentes', fn ($query) => $query->withoutGlobalScope('tenant'))
            ->whereHas('users', fn ($query) => $query->withoutGlobalScope('tenant'))
            ->orderBy('id')
            ->get()
            ->map(fn (Tenant $tenant): array => $this->paraTenant($tenant))
            ->all();
    }
}
