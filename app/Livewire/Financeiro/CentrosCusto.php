<?php

namespace App\Livewire\Financeiro;

use App\Models\CentroCusto as Centro;
use App\Models\Emitente;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Plano de contas hierárquico, código de até três níveis. Ver `CentroCusto`
 * para o formato do código e a herança de "essencial".
 */
#[Layout('components.layouts.fiscal')]
#[Title('Centros de custo')]
class CentrosCusto extends Component
{
    public string $codigo = '';

    public string $nome = '';

    public string $natureza = 'despesa';

    public ?int $paiId = null;

    public bool $grupo = false;

    /** '', '1' ou '0': vazio é "herda do pai". */
    public string $essencial = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** Do mais raso ao mais fundo, por código, que já ordena a hierarquia. */
    #[Computed]
    public function centros(): Collection
    {
        return Centro::where('emitente_id', $this->emitente?->getKey())
            ->with('pai')
            ->orderBy('codigo')
            ->get();
    }

    /** Só os que podem ser pai: grupos, porque um centro folha não recebe filho. */
    #[Computed]
    public function possiveisPais(): Collection
    {
        return $this->centros->where('grupo', true)->where('natureza', $this->natureza);
    }

    public function criar(): void
    {
        $this->authorize('financeiro.gerenciar');

        $codigoFormatado = Centro::formatarCodigo($this->codigo);

        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:120'],
            'natureza' => ['required', 'in:receita,despesa'],
            'paiId' => ['nullable', 'integer'],
        ], attributes: ['nome' => 'nome', 'natureza' => 'natureza']);

        if (! Centro::codigoValido($codigoFormatado)) {
            $this->addError('codigo', 'Código inválido. Use até três grupos de três dígitos, como 001 ou 001.002.');

            return;
        }

        $existe = Centro::where('emitente_id', $this->emitente->getKey())
            ->where('codigo', $codigoFormatado)->exists();

        if ($existe) {
            $this->addError('codigo', 'Já existe um centro de custo com este código.');

            return;
        }

        Centro::create([
            'emitente_id' => $this->emitente->getKey(),
            'pai_id' => $this->paiId,
            'codigo' => $codigoFormatado,
            'nome' => $dados['nome'],
            'natureza' => $dados['natureza'],
            'grupo' => $this->grupo,
            'essencial' => $this->essencial === '' ? null : (bool) (int) $this->essencial,
        ]);

        $this->reset(['codigo', 'nome', 'paiId', 'grupo', 'essencial']);
        unset($this->centros);
    }

    public function alternarAtivo(int $centroId): void
    {
        $this->authorize('financeiro.gerenciar');

        $centro = Centro::where('emitente_id', $this->emitente?->getKey())->findOrFail($centroId);
        $centro->update(['ativo' => ! $centro->ativo]);

        unset($this->centros);
    }

    public function render()
    {
        return view('livewire.financeiro.centros-custo');
    }
}
