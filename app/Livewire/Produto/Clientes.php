<?php

namespace App\Livewire\Produto;

use App\Models\Emitente;
use App\Models\Pessoa;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Clientes de desenvolvimento sob medida da empresa Marcelo Andrade — não é
 * tabela nova: são os Destinatários dela mesma, marcados como cliente,
 * só com uma visão diferente. Sem tenant fixo no código: o slug vem de
 * `config('produto.tenant_administrativo')`, porque o id muda entre
 * ambientes (local, homologação, produção).
 *
 * Mesma leitura sem escopo de tenant que o painel de Empresas já faz: o
 * tenant do contêiner é o do próprio dono, não o da empresa Marcelo Andrade.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Clientes')]
class Clientes extends Component
{
    public function mount(): void
    {
        $this->authorize('produto.administrar');
    }

    private function tenant(): ?Tenant
    {
        $slug = (string) config('produto.tenant_administrativo');

        return $slug === '' ? null : Tenant::where('slug', $slug)->first();
    }

    /** @return Collection<int, Pessoa> */
    private function clientes(): Collection
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return new Collection;
        }

        $emitenteIds = Emitente::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->pluck('id');

        if ($emitenteIds->isEmpty()) {
            return new Collection;
        }

        return Pessoa::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('emitente_id', $emitenteIds)
            ->where('e_cliente', true)
            ->orderBy('razao_social')
            ->get();
    }

    public function render()
    {
        return view('livewire.produto.clientes', [
            'tenant' => $this->tenant(),
            'clientes' => $this->clientes(),
        ]);
    }
}
