<?php

namespace App\Livewire\Financeiro;

use App\Models\CentroCusto;
use App\Models\ContaFinanceira;
use App\Models\ContaPagar as Titulo;
use App\Models\Emitente;
use App\Services\Financeiro\BaixaService;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.fiscal')]
#[Title('Contas a pagar')]
class ContasPagar extends Component
{
    public string $situacao = 'pendentes';

    public string $descricao = '';

    public string $fornecedor = '';

    /** Em reais, como a pessoa digita. Quem converte para centavos é o Dinheiro. */
    public string $valor = '';

    public string $vencimento = '';

    public ?int $centroCustoId = null;

    public ?int $contaBaixaId = null;

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->vencimento = today()->toDateString();
        $this->contaBaixaId = $this->contas->first()?->getKey();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /**
     * Tudo nesta tela é do emitente em foco, e não do tenant.
     *
     * O escopo global filtra por tenant, e um tenant pode ter matriz e filial.
     * Misturar as contas a pagar das duas seria erro caro e silencioso.
     */
    #[Computed]
    public function contas(): Collection
    {
        return ContaFinanceira::where('emitente_id', $this->emitente?->getKey())
            ->where('ativo', true)->orderBy('nome')->get();
    }

    #[Computed]
    public function centros(): Collection
    {
        return CentroCusto::where('emitente_id', $this->emitente?->getKey())
            ->where('natureza', 'despesa')->where('grupo', false)
            ->where('ativo', true)->orderBy('codigo')->get();
    }

    #[Computed]
    public function titulos(): Collection
    {
        return Titulo::query()
            ->where('emitente_id', $this->emitente?->getKey())
            ->when($this->situacao === 'pendentes', fn ($q) => $q->where('status', 'pendente'))
            ->when($this->situacao === 'pagos', fn ($q) => $q->where('status', 'pago'))
            ->with('centroCusto')
            ->orderBy('vencimento')
            ->limit(200)
            ->get();
    }

    /** O tamanho do buraco, que é o número que a pessoa abre a tela para ver. */
    #[Computed]
    public function totalPendenteCentavos(): int
    {
        return (int) Titulo::where('emitente_id', $this->emitente?->getKey())
            ->where('status', 'pendente')->sum('valor_centavos');
    }

    #[Computed]
    public function totalVencidoCentavos(): int
    {
        return (int) Titulo::where('emitente_id', $this->emitente?->getKey())
            ->where('status', 'pendente')
            ->whereDate('vencimento', '<', today())->sum('valor_centavos');
    }

    public function lancar(): void
    {
        $this->authorize('financeiro.gerenciar');

        $this->validate([
            'descricao' => ['required', 'string', 'max:160'],
            'fornecedor' => ['nullable', 'string', 'max:160'],
            'vencimento' => ['required', 'date'],
            'centroCustoId' => ['nullable', 'integer'],
        ], [], [
            'descricao' => 'descrição',
            'vencimento' => 'vencimento',
        ]);

        // Validado fora do `validate` porque o campo é texto livre em reais:
        // "R$ 1.250,90" é válido para a pessoa e inválido para `numeric`.
        $centavos = Dinheiro::emCentavos($this->valor);

        if ($centavos <= 0) {
            $this->addError('valor', 'Informe um valor maior que zero.');

            return;
        }

        Titulo::create([
            'emitente_id' => $this->emitente->getKey(),
            'centro_custo_id' => $this->centroCustoId,
            'descricao' => $this->descricao,
            'fornecedor' => $this->fornecedor ?: null,
            'valor_centavos' => $centavos,
            'vencimento' => $this->vencimento,
        ]);

        $this->reset(['descricao', 'fornecedor', 'valor', 'centroCustoId']);
        $this->vencimento = today()->toDateString();
        $this->esquecerTotais();

        session()->flash('sucesso', 'Título lançado.');
    }

    public function baixar(int $id): void
    {
        $this->authorize('financeiro.gerenciar');

        $titulo = Titulo::findOrFail($id);
        $conta = $this->contaBaixaId !== null ? ContaFinanceira::find($this->contaBaixaId) : null;

        try {
            app(BaixaService::class)->pagar($titulo, $conta);
        } catch (RuntimeException $e) {
            $this->addError('baixa', $e->getMessage());

            return;
        }

        $this->esquecerTotais();

        session()->flash('sucesso', 'Título baixado.');
    }

    public function render()
    {
        return view('livewire.financeiro.contas-pagar');
    }

    private function esquecerTotais(): void
    {
        unset($this->titulos, $this->totalPendenteCentavos, $this->totalVencidoCentavos, $this->contas);
    }
}
