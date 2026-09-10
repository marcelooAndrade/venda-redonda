<?php

namespace App\Livewire\Estoque;

use App\Models\Emitente;
use App\Models\EstoqueMovimento;
use App\Models\EstoqueSaldo;
use App\Models\Produto;
use App\Services\Stock\InventarioService;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.fiscal')]
#[Title('Estoque')]
class Painel extends Component
{
    public string $aba = 'saldos';

    public string $busca = '';

    public bool $somenteAbaixoDoMinimo = false;

    /** Produto em foco no Kardex. */
    public ?int $produtoId = null;

    public string $de = '';

    public string $ate = '';

    /** @var array<int, string> produto_id => quantidade contada */
    public array $contagem = [];

    public string $justificativa = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('estoque.ver');
        $this->de = now()->subMonth()->toDateString();
        $this->ate = now()->toDateString();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function saldos()
    {
        return EstoqueSaldo::query()
            ->with('produto')
            ->whereHas('produto', function ($q) {
                $q->where('controla_estoque', true);
                if ($this->busca !== '') {
                    $termo = '%'.$this->busca.'%';
                    $q->where(fn ($s) => $s->where('descricao', 'like', $termo)->orWhere('codigo', 'like', $termo));
                }
            })
            ->get()
            ->when($this->somenteAbaixoDoMinimo, fn ($c) => $c->filter->abaixoDoMinimo())
            ->sortBy(fn ($s) => $s->produto->descricao)
            ->values();
    }

    #[Computed]
    public function abaixoDoMinimo()
    {
        return EstoqueSaldo::query()->with('produto')->get()->filter->abaixoDoMinimo();
    }

    #[Computed]
    public function produto(): ?Produto
    {
        return $this->produtoId === null ? null : Produto::find($this->produtoId);
    }

    #[Computed]
    public function kardex()
    {
        if ($this->produto === null) {
            return collect();
        }

        return EstoqueMovimento::query()
            ->with('user')
            ->where('produto_id', $this->produtoId)
            ->when($this->de !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->de))
            ->when($this->ate !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->ate))
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function valorTotalEstoque(): float
    {
        return round(EstoqueSaldo::query()->get()->sum(fn ($s) => $s->valorTotal()), 2);
    }

    public function verKardex(int $produtoId): void
    {
        $this->authorize('estoque.ver');
        $this->produtoId = $produtoId;
        $this->aba = 'kardex';
        unset($this->kardex, $this->produto);
    }

    public function prepararInventario(): void
    {
        $this->authorize('estoque.inventariar');
        $this->aba = 'inventario';
        // Começa com o saldo atual: o operador altera só o que divergir.
        $this->contagem = $this->saldos
            ->mapWithKeys(fn ($s) => [$s->produto_id => (string) (float) $s->quantidade])
            ->all();
    }

    public function gravarInventario(InventarioService $inventario): void
    {
        $this->authorize('estoque.inventariar');

        $this->validate(
            ['justificativa' => ['required', 'string', 'min:5', 'max:2000']],
            attributes: ['justificativa' => 'justificativa'],
        );

        try {
            $r = $inventario->contarLote(
                collect($this->contagem)->map(fn ($v) => (float) str_replace(',', '.', (string) $v))->all(),
                $this->justificativa,
                Auth::user(),
            );
        } catch (RuntimeException $e) {
            $this->addError('justificativa', $e->getMessage());

            return;
        }

        $this->reset('justificativa', 'contagem');
        unset($this->saldos, $this->abaixoDoMinimo, $this->valorTotalEstoque);
        $this->aba = 'saldos';

        session()->flash('sucesso', "Inventário gravado: {$r['ajustados']} produto(s) ajustado(s), {$r['conferidos']} conferido(s).");
    }

    public function render()
    {
        return view('livewire.estoque.painel');
    }
}
