<?php

namespace App\Livewire\Notas;

use App\Enums\Fiscal\NFeStatus;
use App\Models\Emitente;
use App\Models\NaturezaOperacao;
use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Services\Fiscal\CalcularNota;
use App\Services\Fiscal\NFeTransmitter;
use App\Services\Fiscal\NumeracaoService;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.fiscal')]
#[Title('Notas fiscais')]
class Emissao extends Component
{
    public ?int $notaId = null;

    public string $filtro = 'todas';

    /** Cabeçalho em edição. */
    public array $cabecalho = [];

    /** Item sendo adicionado. */
    public string $produtoId = '';

    public string $quantidade = '1';

    public string $valorUnitario = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('nota.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function notas()
    {
        return Nota::query()
            ->with('destinatario')
            ->when($this->filtro !== 'todas', fn ($q) => $q->where('status', $this->filtro))
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function nota(): ?Nota
    {
        return $this->notaId === null
            ? null
            : Nota::with(['itens.produto', 'destinatario', 'naturezaOperacao'])->find($this->notaId);
    }

    #[Computed]
    public function destinatarios()
    {
        return Pessoa::query()->clientes()->where('ativo', true)->orderBy('razao_social')->get();
    }

    #[Computed]
    public function naturezas()
    {
        return NaturezaOperacao::query()->where('ativo', true)->orderBy('descricao')->get();
    }

    #[Computed]
    public function produtos()
    {
        return Produto::query()->where('ativo', true)->orderBy('descricao')->get();
    }

    #[Computed]
    public function proximoNumero(): int
    {
        return app(NumeracaoService::class)->previsto($this->emitente, (int) $this->emitente->serie_padrao);
    }

    public function novaNota(): void
    {
        $this->authorize('nota.criar');

        $nota = Nota::create([
            'emitente_id' => $this->emitente->getKey(),
            'serie' => $this->emitente->serie_padrao ?: 1,
            'ambiente' => $this->emitente->ambiente,
            'data_emissao' => now(),
            'info_complementares' => $this->emitente->info_complementares_padrao,
            'criada_por' => Auth::id(),
        ]);

        $this->abrir($nota->id);
        unset($this->notas);
    }

    public function abrir(int $id): void
    {
        $this->authorize('nota.ver');

        $this->notaId = $id;
        $this->resetErrorBag();
        unset($this->nota);

        $nota = $this->nota;

        $this->cabecalho = [
            'pessoa_id' => (string) ($nota->pessoa_id ?? ''),
            'natureza_operacao_id' => (string) ($nota->natureza_operacao_id ?? ''),
            'mod_frete' => $nota->mod_frete,
            'consumidor_final' => (bool) $nota->consumidor_final,
            'info_complementares' => (string) $nota->info_complementares,
        ];
    }

    public function salvarCabecalho(CalcularNota $calculadora): void
    {
        $this->authorize('nota.criar');

        $nota = $this->nota;
        abort_if($nota === null || ! $nota->editavel(), 403);

        $natureza = filled($this->cabecalho['natureza_operacao_id'])
            ? NaturezaOperacao::find($this->cabecalho['natureza_operacao_id'])
            : null;

        $nota->forceFill([
            'pessoa_id' => $this->cabecalho['pessoa_id'] ?: null,
            'natureza_operacao_id' => $natureza?->getKey(),
            'natureza_operacao' => $natureza?->descricao,
            'fin_nfe' => $natureza?->fin_nfe ?? '1',
            'tipo' => $natureza?->tipo ?? '1',
            'mod_frete' => $this->cabecalho['mod_frete'],
            'consumidor_final' => (bool) $this->cabecalho['consumidor_final'],
            'info_complementares' => $this->cabecalho['info_complementares'] ?: null,
        ])->save();

        $this->recalcular($calculadora);
    }

    public function adicionarItem(CalcularNota $calculadora): void
    {
        $this->authorize('nota.criar');

        $nota = $this->nota;
        abort_if($nota === null || ! $nota->editavel(), 403);

        $this->validate([
            'produtoId' => ['required', 'exists:produtos,id'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
        ], attributes: ['produtoId' => 'produto', 'quantidade' => 'quantidade']);

        $calculadora->adicionarItem(
            $nota,
            Produto::findOrFail((int) $this->produtoId),
            (float) str_replace(',', '.', $this->quantidade),
            filled($this->valorUnitario) ? (float) str_replace(',', '.', $this->valorUnitario) : null,
        );

        $this->reset('produtoId', 'quantidade', 'valorUnitario');
        $this->quantidade = '1';

        $this->recalcular($calculadora);
    }

    public function removerItem(int $itemId, CalcularNota $calculadora): void
    {
        $this->authorize('nota.criar');
        abort_if(! $this->nota?->editavel(), 403);

        NotaItem::where('nota_id', $this->notaId)->whereKey($itemId)->delete();

        $this->recalcular($calculadora);
    }

    public function transmitir(NFeTransmitter $transmissor, CalcularNota $calculadora): void
    {
        $this->authorize('nota.emitir');

        $nota = $this->nota;
        abort_if($nota === null, 404);

        try {
            // Recalcula antes de transmitir: o que a tela mostrou pode estar
            // velho, e o que vale é o que o servidor calcula agora.
            $nota = $calculadora->recalcular($nota);
            $transmissor->transmitir($nota, Auth::user());
        } catch (Throwable $e) {
            $this->addError('transmissao', $e->getMessage());

            return;
        }

        unset($this->nota, $this->notas, $this->proximoNumero);

        $atual = $this->nota;

        session()->flash($atual->status === NFeStatus::Autorizada ? 'sucesso' : 'aviso',
            $atual->status === NFeStatus::Autorizada
                ? "NF-e {$atual->numeroFormatado()} autorizada."
                : "A SEFAZ respondeu {$atual->status->rotulo()}.");
    }

    private function recalcular(CalcularNota $calculadora): void
    {
        try {
            $calculadora->recalcular($this->nota);
        } catch (Throwable $e) {
            $this->addError('calculo', $e->getMessage());
        }

        unset($this->nota, $this->notas);
    }

    public function render()
    {
        return view('livewire.notas.emissao');
    }
}
