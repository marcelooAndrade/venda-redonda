<?php

namespace App\Livewire\Financeiro;

use App\Models\ContaFinanceira;
use App\Models\Emitente;
use App\Models\MovimentoCaixa;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Contas bancárias e tesouraria: as contas onde o dinheiro está, o saldo de
 * cada uma (saldo inicial mais razão, nunca gravado), e o extrato de quem
 * quiser abrir uma conta específica.
 *
 * O ajuste manual existe para o lançamento que não nasce de título nenhum:
 * um saque, uma taxa de banco, uma transferência entre contas. Vai para o
 * razão com `origem_tipo` `ajuste`, sem `origem_id`, exatamente como as
 * baixas de título vão com `conta_pagar`/`fatura_parcela`.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Contas bancárias')]
class ContasBancarias extends Component
{
    public string $nome = '';

    public string $banco = '';

    public string $tipo = 'corrente';

    public string $saldoInicial = '';

    public bool $padrao = false;

    public ?int $contaSelecionadaId = null;

    public string $ajusteSentido = 'debito';

    public string $ajusteValor = '';

    public string $ajusteDescricao = '';

    public string $ajusteOcorridoEm = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->ajusteOcorridoEm = today()->toDateString();
        $this->contaSelecionadaId = $this->contas->first()?->getKey();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** @return Collection<int, ContaFinanceira> */
    #[Computed]
    public function contas(): Collection
    {
        return ContaFinanceira::where('emitente_id', $this->emitente?->getKey())
            ->orderByDesc('ativo')->orderBy('nome')->get();
    }

    #[Computed]
    public function saldoTotalCentavos(): int
    {
        return (int) $this->contas->sum(fn (ContaFinanceira $c): int => $c->saldoCentavos());
    }

    #[Computed]
    public function contaSelecionada(): ?ContaFinanceira
    {
        return $this->contas->firstWhere('id', $this->contaSelecionadaId);
    }

    /** @return Collection<int, MovimentoCaixa> */
    #[Computed]
    public function movimentos(): Collection
    {
        if ($this->contaSelecionadaId === null) {
            return new Collection;
        }

        return MovimentoCaixa::where('conta_financeira_id', $this->contaSelecionadaId)
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function selecionar(int $contaId): void
    {
        $this->contaSelecionadaId = $contaId;
        unset($this->movimentos);
    }

    public function criarConta(): void
    {
        $this->authorize('financeiro.gerenciar');

        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:100'],
            'banco' => ['nullable', 'string', 'max:100'],
            'tipo' => ['required', 'in:corrente,poupanca,caixa'],
        ], attributes: ['nome' => 'nome', 'banco' => 'banco', 'tipo' => 'tipo']);

        $conta = ContaFinanceira::create([
            'emitente_id' => $this->emitente->getKey(),
            'nome' => $dados['nome'],
            'banco' => $dados['banco'] ?: null,
            'tipo' => $dados['tipo'],
            'saldo_inicial_centavos' => Dinheiro::emCentavos($this->saldoInicial),
            'padrao' => $this->padrao,
        ]);

        $this->reset(['nome', 'banco', 'tipo', 'saldoInicial', 'padrao']);
        $this->tipo = 'corrente';
        $this->contaSelecionadaId = $conta->getKey();

        unset($this->contas, $this->movimentos, $this->saldoTotalCentavos);
    }

    public function alternarAtiva(int $contaId): void
    {
        $this->authorize('financeiro.gerenciar');

        $conta = ContaFinanceira::where('emitente_id', $this->emitente?->getKey())->findOrFail($contaId);
        $conta->update(['ativo' => ! $conta->ativo]);

        unset($this->contas);
    }

    public function lancarAjuste(): void
    {
        $this->authorize('financeiro.gerenciar');

        $conta = $this->contaSelecionada;

        if ($conta === null) {
            $this->addError('ajusteConta', 'Selecione uma conta para lançar o ajuste.');

            return;
        }

        $this->validate([
            'ajusteSentido' => ['required', 'in:credito,debito'],
            'ajusteDescricao' => ['required', 'string', 'max:200'],
            'ajusteOcorridoEm' => ['required', 'date'],
        ], attributes: ['ajusteDescricao' => 'descrição', 'ajusteOcorridoEm' => 'data']);

        $centavos = Dinheiro::emCentavos($this->ajusteValor);

        if ($centavos <= 0) {
            $this->addError('ajusteValor', 'Informe um valor maior que zero.');

            return;
        }

        MovimentoCaixa::create([
            'emitente_id' => $this->emitente->getKey(),
            'conta_financeira_id' => $conta->getKey(),
            'user_id' => auth()->id(),
            'sentido' => $this->ajusteSentido,
            'valor_centavos' => $centavos,
            'descricao' => $this->ajusteDescricao,
            'ocorrido_em' => $this->ajusteOcorridoEm,
            'origem_tipo' => 'ajuste',
            'origem_id' => null,
            'created_at' => now(),
        ]);

        $this->reset(['ajusteValor', 'ajusteDescricao']);
        $this->ajusteOcorridoEm = today()->toDateString();

        unset($this->contas, $this->movimentos, $this->saldoTotalCentavos);

        session()->flash('sucesso', 'Ajuste lançado.');
    }

    public function render()
    {
        return view('livewire.financeiro.contas-bancarias');
    }
}
