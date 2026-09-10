<?php

namespace App\Livewire\Importacao;

use App\Models\Emitente;
use App\Models\NotaEntrada;
use App\Models\NotaEntradaItem;
use App\Models\Produto;
use App\Services\Import\ConciliacaoService;
use App\Services\Import\ConfirmarEntradaService;
use App\Services\Import\ImportarArquivos;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

#[Layout('components.layouts.fiscal')]
#[Title('Importação de notas')]
class Painel extends Component
{
    use WithFileUploads;

    /** @var array<int, mixed> */
    public array $arquivos = [];

    /** @var array<int, array{arquivo: string, erro: string}> */
    public array $falhas = [];

    public ?int $notaId = null;

    public string $filtro = 'pendente';

    /** Vínculo escolhido por item na conciliação: item_id => produto_id */
    public array $vinculos = [];

    /** Fator de conversão por item. */
    public array $fatores = [];

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('importacao.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function notas()
    {
        return NotaEntrada::query()
            ->with('pessoa')
            ->withCount('itens')
            ->when($this->filtro !== 'todas', fn ($q) => $q->where('status', $this->filtro))
            ->orderByDesc('data_emissao')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function nota(): ?NotaEntrada
    {
        return $this->notaId === null
            ? null
            : NotaEntrada::with(['itens.produto', 'pessoa'])->find($this->notaId);
    }

    #[Computed]
    public function produtos()
    {
        return Produto::query()->where('ativo', true)->orderBy('descricao')->get();
    }

    public function importar(ImportarArquivos $service): void
    {
        $this->authorize('importacao.processar');

        $this->validate([
            'arquivos' => ['required', 'array', 'min:1'],
            'arquivos.*' => ['file', 'max:20480', 'extensions:xml,zip'],
        ], attributes: ['arquivos' => 'arquivos']);

        try {
            $r = $service->processar($this->arquivos, $this->emitente, Auth::user());
        } catch (Throwable $e) {
            $this->addError('arquivos', $e->getMessage());

            return;
        }

        $this->falhas = $r['falhas'];
        $this->reset('arquivos');
        unset($this->notas);

        session()->flash('sucesso', $r['importadas'] === 0
            ? 'Nenhuma nota nova foi importada.'
            : "{$r['importadas']} nota(s) importada(s).");
    }

    public function conciliar(int $notaId): void
    {
        $this->authorize('importacao.ver');
        $this->notaId = $notaId;
        $this->vinculos = [];
        $this->fatores = [];
        unset($this->nota);

        foreach ($this->nota?->itens ?? [] as $item) {
            $this->vinculos[$item->id] = (string) ($item->produto_id ?? '');
            $this->fatores[$item->id] = (string) (float) $item->fator_conversao;
        }
    }

    public function vincular(int $itemId, ConciliacaoService $service): void
    {
        $this->authorize('importacao.processar');

        $item = NotaEntradaItem::findOrFail($itemId);
        $produtoId = $this->vinculos[$itemId] ?? '';

        if ($produtoId === '') {
            return;
        }

        $service->vincular(
            $item,
            Produto::findOrFail((int) $produtoId),
            (float) str_replace(',', '.', (string) ($this->fatores[$itemId] ?? 1)) ?: 1.0,
        );

        unset($this->nota, $this->notas);
        session()->flash('sucesso', 'Item vinculado. O vínculo fica salvo para as próximas notas deste fornecedor.');
    }

    public function criarProduto(int $itemId, ConciliacaoService $service): void
    {
        $this->authorize('produto.gerenciar');

        $item = NotaEntradaItem::findOrFail($itemId);
        $produto = $service->criarProdutoDoItem($item, $item->codigo_fornecedor);

        unset($this->nota, $this->notas, $this->produtos);
        session()->flash('sucesso', "Produto \"{$produto->descricao}\" criado a partir do XML e vinculado.");
    }

    public function confirmar(ConfirmarEntradaService $service): void
    {
        $this->authorize('estoque.movimentar');

        try {
            $service->confirmar($this->nota, Auth::user());
        } catch (RuntimeException $e) {
            $this->addError('confirmacao', $e->getMessage());

            return;
        }

        unset($this->nota, $this->notas);
        session()->flash('sucesso', 'Entrada confirmada. Os itens foram somados ao estoque com o custo rateado.');
    }

    public function render()
    {
        return view('livewire.importacao.painel');
    }
}
