<?php

namespace App\Livewire\Financeiro\Concerns;

use App\Models\FaturaParcela;
use App\Models\NotaServico;
use App\Models\ServicoNfse;
use App\Services\Nfse\NfseEmissor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Emissão de NFS-e a partir de uma parcela, compartilhada entre a lista de
 * contas a receber e o detalhe da fatura. Quem usa precisa expor `emitente`
 * (computed) e dizer quais parcelas estão na tela, em `idsDasParcelas()`.
 */
trait EmiteNfseDaParcela
{
    /** Parcela cuja NFS-e está sendo preparada. Nulo fecha o formulário. */
    public ?int $nfseParcelaId = null;

    public ?int $nfseServicoId = null;

    public string $nfseDescricao = '';

    /** @return array<int, int> */
    abstract protected function idsDasParcelas(): array;

    #[Computed]
    public function nfseHabilitada(): bool
    {
        return $this->emitente?->nfse?->habilitado === true;
    }

    #[Computed]
    public function servicosNfse(): Collection
    {
        return ServicoNfse::query()
            ->where('emitente_id', $this->emitente?->getKey())
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();
    }

    /**
     * As notas do ambiente atual, uma por parcela desta tela.
     *
     * @return Collection<int, NotaServico>
     */
    #[Computed]
    public function notasServico(): Collection
    {
        $ambiente = $this->emitente?->nfse?->ambiente;

        if ($ambiente === null) {
            return collect();
        }

        return NotaServico::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->where('ambiente', $ambiente->value)
            ->whereIn('fatura_parcela_id', $this->idsDasParcelas())
            ->get()
            ->keyBy('fatura_parcela_id');
    }

    public function abrirNfse(int $parcelaId): void
    {
        $this->authorize('nfse.emitir');

        $parcela = $this->parcelaDoEmitente($parcelaId);
        $servico = $this->servicosNfse->first();

        $this->nfseParcelaId = $parcela->getKey();
        $this->nfseServicoId = $servico?->getKey();
        $this->nfseDescricao = $servico?->descricao_padrao ?: $parcela->descricao;
        $this->resetErrorBag('nfse');
    }

    /** Trocar o serviço traz a descrição padrão dele, se houver. */
    public function updatedNfseServicoId(mixed $valor): void
    {
        $servico = $this->servicosNfse->firstWhere('id', (int) $valor);

        if ($servico !== null && filled($servico->descricao_padrao)) {
            $this->nfseDescricao = $servico->descricao_padrao;
        }
    }

    public function fecharNfse(): void
    {
        $this->reset('nfseParcelaId', 'nfseServicoId', 'nfseDescricao');
        $this->resetErrorBag('nfse');
    }

    public function emitirNfse(NfseEmissor $emissor): void
    {
        $this->authorize('nfse.emitir');

        $parcela = $this->parcelaDoEmitente((int) $this->nfseParcelaId);
        $servico = ServicoNfse::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->find((int) $this->nfseServicoId);

        if ($servico === null) {
            $this->addError('nfse', 'Escolha o serviço fiscal.');

            return;
        }

        // Rejeição e erro sobem como ValidationException e ficam no campo
        // `nfse`, com o formulário aberto para a nova tentativa.
        $nota = $emissor->emitir($parcela, $servico, $this->nfseDescricao, Auth::user());

        $this->fecharNfse();
        unset($this->notasServico);

        session()->flash('sucesso', "NFS-e {$nota->numero_nfse} autorizada em ".mb_strtolower($nota->ambiente->rotulo()).'.');
    }

    protected function parcelaDoEmitente(int $id): FaturaParcela
    {
        return FaturaParcela::query()
            ->whereHas('fatura', fn ($q) => $q->where('emitente_id', $this->emitente->getKey()))
            ->findOrFail($id);
    }
}
