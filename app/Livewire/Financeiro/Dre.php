<?php

namespace App\Livewire\Financeiro;

use App\Models\Emitente;
use App\Services\Financeiro\DreService;
use App\Support\EmitenteAtual;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * DRE simplificada, por mês: regime de caixa sobre o razão, quebrada por
 * centro de custo. Ver `DreService` para o porquê de não ser DRE contábil.
 */
#[Layout('components.layouts.fiscal')]
#[Title('DRE')]
class Dre extends Component
{
    public string $mes = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->mes = today()->format('Y-m');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function dados(): array
    {
        return app(DreService::class)->montar($this->emitente->getKey(), $this->mes);
    }

    public function updatedMes(): void
    {
        unset($this->dados);
    }

    public function render()
    {
        return view('livewire.financeiro.dre');
    }
}
