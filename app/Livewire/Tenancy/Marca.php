<?php

namespace App\Livewire\Tenancy;

use App\Models\Tenant;
use App\Support\TemaMarca;
use App\Support\TenantAtual;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configura a identidade visual do tenant.
 *
 * Todo utilitário do Tailwind aponta para `var(--color-*)`, então salvar aqui
 * repinta o sistema inteiro na próxima requisição, sem recompilar nada.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Marca')]
class Marca extends Component
{
    public string $primaria = '';

    public string $neutra = '';

    public string $nomeCurto = '';

    /** Verdadeiro quando a cor informada precisou ser escurecida. */
    public bool $avisoContraste = false;

    public function mount(): void
    {
        abort_unless($this->tenant !== null, 404);
        $this->authorize('emitente.gerenciar');

        $tema = $this->tenant->tema ?? [];
        $this->primaria = $tema['primaria'] ?? TemaMarca::PRIMARIA_PADRAO;
        $this->neutra = $tema['neutra'] ?? TemaMarca::NEUTRA_PADRAO;
        $this->nomeCurto = $this->tenant->nome_curto ?? '';
    }

    #[Computed]
    public function tenant(): ?Tenant
    {
        return app(TenantAtual::class)->obter();
    }

    /** @return array<int, string> */
    #[Computed]
    public function previaPrimaria(): array
    {
        try {
            return TemaMarca::escalaDe($this->primaria);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /** @return array<int, string> */
    #[Computed]
    public function previaNeutra(): array
    {
        try {
            return TemaMarca::escalaNeutraDe($this->neutra);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    #[Computed]
    public function contrastePrimaria(): ?float
    {
        try {
            return TemaMarca::contrasteComBranco($this->primaria);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function salvar(): void
    {
        $this->authorize('emitente.gerenciar');

        $this->validate([
            'primaria' => ['required', 'string'],
            'neutra' => ['required', 'string'],
            'nomeCurto' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            // Contraste insuficiente é corrigido, não recusado: dizer ao cliente
            // que a marca dele está errada não é opção.
            $ajustada = TemaMarca::ajustarParaContraste($this->primaria);
            $neutra = TemaMarca::escalaNeutraDe($this->neutra)[900];
        } catch (InvalidArgumentException $e) {
            $this->addError('primaria', $e->getMessage());

            return;
        }

        $this->avisoContraste = strcasecmp($ajustada, ltrim($this->primaria, '#') === $this->primaria ? '#'.$this->primaria : $this->primaria) !== 0;

        $this->tenant->update([
            'nome_curto' => $this->nomeCurto ?: null,
            'tema' => ['primaria' => $ajustada, 'neutra' => $neutra],
        ]);

        $this->primaria = $ajustada;
        $this->neutra = $neutra;
        unset($this->tenant, $this->previaPrimaria, $this->previaNeutra, $this->contrastePrimaria);

        session()->flash('sucesso', 'Marca salva. Recarregue para ver o sistema com as novas cores.');
    }

    public function render()
    {
        return view('livewire.tenancy.marca');
    }
}
