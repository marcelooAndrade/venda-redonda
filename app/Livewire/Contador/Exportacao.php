<?php

namespace App\Livewire\Contador;

use App\Enums\Fiscal\NFeStatus;
use App\Models\Emitente;
use App\Models\Nota;
use App\Models\NotaEntrada;
use App\Services\Export\PacoteContadorService;
use App\Support\EmitenteAtual;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.fiscal')]
#[Title('Pacote da contabilidade')]
class Exportacao extends Component
{
    public string $de = '';

    public string $ate = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404);
        $this->authorize('contador.exportar');

        $this->de = now()->subMonth()->startOfMonth()->toDateString();
        $this->ate = now()->subMonth()->endOfMonth()->toDateString();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** @return array<string, int> */
    #[Computed]
    public function previa(): array
    {
        [$de, $ate] = $this->periodo();

        $saidas = Nota::query()
            ->whereBetween('data_emissao', [$de, $ate])
            ->whereIn('status', [NFeStatus::Autorizada->value, NFeStatus::Cancelada->value])
            ->get();

        return [
            'autorizadas' => $saidas->where('status', NFeStatus::Autorizada)->count(),
            'canceladas' => $saidas->where('status', NFeStatus::Cancelada)->count(),
            'entradas' => NotaEntrada::query()->whereBetween('data_emissao', [$de, $ate])->count(),
            'valor' => (int) round($saidas->where('status', NFeStatus::Autorizada)->sum('valor_nota')),
        ];
    }

    public function baixar(PacoteContadorService $servico)
    {
        $this->authorize('contador.exportar');

        [$de, $ate] = $this->periodo();

        try {
            $caminho = $servico->gerar($this->emitente, $de, $ate);
        } catch (Throwable $e) {
            $this->addError('periodo', $e->getMessage());

            return null;
        }

        $nome = 'contabilidade-'.$this->emitente->cnpj.'-'.$de->format('Y-m').'.zip';

        return response()->download($caminho, $nome)->deleteFileAfterSend();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodo(): array
    {
        return [
            Carbon::parse($this->de ?: now()->startOfMonth())->startOfDay(),
            Carbon::parse($this->ate ?: now())->endOfDay(),
        ];
    }

    public function render()
    {
        return view('livewire.contador.exportacao');
    }
}
