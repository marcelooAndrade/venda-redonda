<?php

namespace App\Livewire\Nfse;

use App\Enums\Nfse\NfseStatus;
use App\Models\Emitente;
use App\Models\NotaServico;
use App\Services\Nfse\NfseEmissor;
use App\Support\EmitenteAtual;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Todas as NFS-e do emitente, com PDF, XML e cancelamento.
 *
 * A emissão não acontece aqui: ela nasce da parcela, em contas a receber.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Notas de serviço')]
class Notas extends Component
{
    use WithPagination;

    public string $situacao = 'todas';

    public ?int $cancelandoId = null;

    public string $motivoCancelamento = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('nfse.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** @return LengthAwarePaginator<int, NotaServico> */
    #[Computed]
    public function notas(): LengthAwarePaginator
    {
        return NotaServico::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->when($this->situacao !== 'todas', fn ($q) => $q->where('status', $this->situacao))
            ->with(['parcela.fatura.destinatario', 'servico'])
            ->orderByDesc('id')
            ->paginate(50);
    }

    /** @return array<string, string> */
    public function filtros(): array
    {
        return ['todas' => 'Todas'] + collect(NfseStatus::cases())
            ->mapWithKeys(fn (NfseStatus $s): array => [$s->value => $s->rotulo()])
            ->all();
    }

    public function updatedSituacao(): void
    {
        $this->resetPage();
    }

    public function abrirCancelamento(int $id): void
    {
        $this->authorize('nfse.cancelar');

        $nota = NotaServico::query()->where('emitente_id', $this->emitente->getKey())->find($id);
        abort_unless($nota !== null, 404);

        $this->cancelandoId = $nota->getKey();
        $this->motivoCancelamento = '';
        $this->resetErrorBag('nfse');
    }

    public function fecharCancelamento(): void
    {
        $this->reset('cancelandoId', 'motivoCancelamento');
        $this->resetErrorBag('nfse');
    }

    public function cancelar(NfseEmissor $emissor): void
    {
        $this->authorize('nfse.cancelar');

        $nota = NotaServico::query()->where('emitente_id', $this->emitente->getKey())->find((int) $this->cancelandoId);
        abort_unless($nota !== null, 404);

        // ValidationException sobe daqui e o Livewire mostra em `nfse`.
        $nota = $emissor->cancelar($nota, $this->motivoCancelamento);

        $this->fecharCancelamento();
        unset($this->notas);

        session()->flash('sucesso', "NFS-e {$nota->numero_nfse} cancelada no SIGISS.");
    }

    public function render()
    {
        return view('livewire.nfse.notas');
    }
}
