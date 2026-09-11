<?php

namespace App\Services\Integrations;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Monta o que o admin pessoal precisa saber sobre um tenant.
 *
 * O telefone vem do emitente, e não do usuário: o cadastro pede um telefone da
 * empresa, que é com quem se fala.
 */
class MontadorDeLeads
{
    /** @return array<string, mixed> */
    public function paraTenant(Tenant $tenant): array
    {
        $emitente = $tenant->emitentes()->orderBy('id')->first();
        $primeiro = $tenant->users()->orderBy('id')->first();

        // O último acesso do tenant é o mais recente entre as pessoas dele.
        $ultimoAcesso = $tenant->users()->max('ultimo_acesso_em');

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
     * @return array<int, array<string, mixed>>
     */
    public function paraTodos(): array
    {
        return Tenant::query()
            ->whereHas('emitentes')
            ->whereHas('users')
            ->orderBy('id')
            ->get()
            ->map(fn (Tenant $tenant): array => $this->paraTenant($tenant))
            ->all();
    }
}
