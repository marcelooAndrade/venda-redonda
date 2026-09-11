<?php

namespace App\Livewire\Financeiro;

use App\Models\ContaFinanceira;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Pessoa;
use App\Services\Financeiro\BaixaService;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.fiscal')]
#[Title('Contas a receber')]
class ContasReceber extends Component
{
    public string $situacao = 'pendentes';

    public string $titulo = '';

    public ?int $pessoaId = null;

    public string $valor = '';

    public int $parcelas = 1;

    public string $primeiroVencimento = '';

    public ?int $contaBaixaId = null;

    /**
     * A chave é do emitente, e é editada aqui porque é aqui que ela serve.
     *
     * O lugar definitivo é a tela de cadastro de emitente, que ainda não
     * existe. Enquanto isso, deixar o recurso inalcançável seria pior do que
     * abrigá-lo na tela que o usa.
     */
    public string $chavePix = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->primeiroVencimento = today()->toDateString();
        $this->contaBaixaId = $this->contas->first()?->getKey();
        $this->chavePix = (string) ($this->emitente->chave_pix ?? '');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function contas(): Collection
    {
        return ContaFinanceira::where('emitente_id', $this->emitente?->getKey())
            ->where('ativo', true)->orderBy('nome')->get();
    }

    #[Computed]
    public function clientes(): Collection
    {
        return Pessoa::where('emitente_id', $this->emitente?->getKey())->where('e_cliente', true)->orderBy('razao_social')->limit(500)->get();
    }

    /**
     * A lista é de parcelas, não de faturas: a parcela é o título, e é ela que
     * vence, atrasa e é recebida.
     */
    #[Computed]
    public function titulos(): Collection
    {
        return FaturaParcela::query()
            ->whereHas('fatura', fn ($q) => $q->where('status', 'ativa')
                ->where('emitente_id', $this->emitente?->getKey()))
            ->when($this->situacao === 'pendentes', fn ($q) => $q->where('status', 'pendente'))
            ->when($this->situacao === 'recebidos', fn ($q) => $q->where('status', 'pago'))
            ->with('fatura.destinatario')
            ->orderBy('vencimento')
            ->limit(300)
            ->get();
    }

    #[Computed]
    public function totalPendenteCentavos(): int
    {
        return (int) FaturaParcela::whereHas('fatura', fn ($q) => $q->where('status', 'ativa')
            ->where('emitente_id', $this->emitente?->getKey()))
            ->where('status', 'pendente')->sum('valor_centavos');
    }

    #[Computed]
    public function totalVencidoCentavos(): int
    {
        return (int) FaturaParcela::whereHas('fatura', fn ($q) => $q->where('status', 'ativa')
            ->where('emitente_id', $this->emitente?->getKey()))
            ->where('status', 'pendente')->whereDate('vencimento', '<', today())->sum('valor_centavos');
    }

    public function lancar(): void
    {
        $this->authorize('financeiro.gerenciar');

        $this->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'pessoaId' => ['nullable', 'integer'],
            'parcelas' => ['required', 'integer', 'min:1', 'max:120'],
            'primeiroVencimento' => ['required', 'date'],
        ], [], ['titulo' => 'título', 'parcelas' => 'número de parcelas']);

        $centavos = Dinheiro::emCentavos($this->valor);

        if ($centavos <= 0) {
            $this->addError('valor', 'Informe um valor maior que zero.');

            return;
        }

        DB::transaction(function () use ($centavos): void {
            $fatura = Fatura::create([
                'emitente_id' => $this->emitente->getKey(),
                'pessoa_id' => $this->pessoaId,
                'titulo' => $this->titulo,
            ]);

            $vencimento = Carbon::parse($this->primeiroVencimento);

            foreach ($this->dividir($centavos, $this->parcelas) as $i => $valorParcela) {
                $parcela = FaturaParcela::create([
                    'fatura_id' => $fatura->getKey(),
                    'numero' => $i + 1,
                    'descricao' => $this->parcelas > 1
                        ? sprintf('%s, parcela %d de %d', $this->titulo, $i + 1, $this->parcelas)
                        : $this->titulo,
                    'valor_centavos' => $valorParcela,
                    'vencimento' => $vencimento->copy()->addMonthsNoOverflow($i),
                ]);

                $parcela->setRelation('fatura', $fatura)->gerarCobrancaPix();
            }
        });

        $this->reset(['titulo', 'valor', 'pessoaId']);
        $this->parcelas = 1;
        $this->primeiroVencimento = today()->toDateString();
        $this->esquecerTotais();

        session()->flash('sucesso', 'Fatura lançada.');
    }

    public function salvarChavePix(): void
    {
        $this->authorize('financeiro.gerenciar');

        $this->validate(['chavePix' => ['nullable', 'string', 'max:77']], [], ['chavePix' => 'chave Pix']);

        $this->emitente->forceFill(['chave_pix' => $this->chavePix ?: null])->save();

        unset($this->emitente);

        session()->flash('sucesso', $this->chavePix === ''
            ? 'Chave Pix removida. As próximas parcelas saem sem cobrança.'
            : 'Chave Pix salva. Vale para as próximas parcelas lançadas.');
    }

    public function baixar(int $id): void
    {
        $this->authorize('financeiro.gerenciar');

        $parcela = FaturaParcela::findOrFail($id);
        $conta = $this->contaBaixaId !== null ? ContaFinanceira::find($this->contaBaixaId) : null;

        try {
            app(BaixaService::class)->receber($parcela, $conta);
        } catch (RuntimeException $e) {
            $this->addError('baixa', $e->getMessage());

            return;
        }

        $this->esquecerTotais();

        session()->flash('sucesso', 'Recebimento registrado.');
    }

    public function render()
    {
        return view('livewire.financeiro.contas-receber');
    }

    /**
     * Divide em centavos inteiros e devolve a sobra nas primeiras parcelas.
     *
     * R$ 100 em três não são três de R$ 33,33: isso perde um centavo, e um
     * centavo perdido por venda vira diferença no fim do mês. A primeira
     * parcela leva a sobra, que é o que a praxe comercial faz.
     *
     * @return array<int, int>
     */
    private function dividir(int $centavos, int $partes): array
    {
        $base = intdiv($centavos, $partes);
        $sobra = $centavos % $partes;

        return array_map(
            fn (int $i): int => $base + ($i < $sobra ? 1 : 0),
            range(0, $partes - 1),
        );
    }

    private function esquecerTotais(): void
    {
        unset($this->titulos, $this->totalPendenteCentavos, $this->totalVencidoCentavos, $this->contas);
    }
}
