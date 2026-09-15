<?php

namespace App\Livewire\Produto;

use App\Enums\EtapaCrm;
use App\Models\ContatoCrm;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Funil de negócio enxuto: só pipeline e contatos, sem o motor de cadência
 * automática nem a qualificação BANT do projeto Marcelo Andrade — medido
 * lá que quase não eram usados. Só abre para o dono do produto, mesma
 * regra de /empresas e /admin.
 */
#[Layout('components.layouts.fiscal')]
#[Title('CRM')]
class Crm extends Component
{
    public bool $formularioAberto = false;

    public string $novoNome = '';

    public string $novaEmpresa = '';

    public string $novoTelefone = '';

    public string $novoEmail = '';

    /** Id do contato com a observação em edição, um por vez. */
    public ?int $editandoObservacaoDe = null;

    public string $observacaoEmEdicao = '';

    public function mount(): void
    {
        $this->authorize('produto.administrar');
    }

    /** @return array<string, Collection<int, ContatoCrm>> */
    #[Computed]
    public function contatosPorEtapa(): array
    {
        $todos = ContatoCrm::query()->orderByDesc('created_at')->get();

        return collect(EtapaCrm::cases())
            ->mapWithKeys(fn (EtapaCrm $etapa): array => [
                $etapa->value => $todos->filter(fn (ContatoCrm $c): bool => $c->etapa === $etapa)->values(),
            ])
            ->all();
    }

    public function criarContato(): void
    {
        $this->authorize('produto.administrar');

        $dados = $this->validate([
            'novoNome' => ['required', 'string', 'min:2', 'max:160'],
            'novaEmpresa' => ['nullable', 'string', 'max:160'],
            'novoTelefone' => ['nullable', 'string', 'max:20'],
            'novoEmail' => ['nullable', 'string', 'email', 'max:254'],
        ], attributes: ['novoNome' => 'nome', 'novaEmpresa' => 'empresa', 'novoTelefone' => 'telefone', 'novoEmail' => 'e-mail']);

        ContatoCrm::create([
            'nome' => $dados['novoNome'],
            'empresa' => $dados['novaEmpresa'] ?: null,
            'telefone' => $dados['novoTelefone'] ?: null,
            'email' => $dados['novoEmail'] ?: null,
        ]);

        $this->reset(['novoNome', 'novaEmpresa', 'novoTelefone', 'novoEmail', 'formularioAberto']);
        unset($this->contatosPorEtapa);
    }

    public function moverEtapa(int $contatoId, string $novaEtapa): void
    {
        $this->authorize('produto.administrar');

        ContatoCrm::findOrFail($contatoId)->update(['etapa' => EtapaCrm::from($novaEtapa)]);

        unset($this->contatosPorEtapa);
    }

    public function iniciarEdicaoObservacao(int $contatoId): void
    {
        $this->authorize('produto.administrar');

        $this->editandoObservacaoDe = $contatoId;
        $this->observacaoEmEdicao = (string) ContatoCrm::findOrFail($contatoId)->observacao;
    }

    public function cancelarEdicaoObservacao(): void
    {
        $this->editandoObservacaoDe = null;
        $this->observacaoEmEdicao = '';
    }

    public function salvarObservacao(int $contatoId): void
    {
        $this->authorize('produto.administrar');

        $dados = $this->validate(['observacaoEmEdicao' => ['nullable', 'string', 'max:2000']]);

        ContatoCrm::findOrFail($contatoId)->update(['observacao' => $dados['observacaoEmEdicao'] ?: null]);

        $this->cancelarEdicaoObservacao();
        unset($this->contatosPorEtapa);
    }

    public function render()
    {
        return view('livewire.produto.crm');
    }
}
