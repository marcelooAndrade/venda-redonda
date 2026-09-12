<?php

namespace App\Livewire\Financeiro;

use App\Models\ContaFinanceira;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\NotaServico;
use App\Models\Pessoa;
use App\Models\ServicoNfse;
use App\Services\Financeiro\BaixaService;
use App\Services\Nfse\NfseEmissor;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

    /**
     * As parcelas, uma por linha, com valor e vencimento próprios.
     *
     * É assim que a origem trabalha, e é assim que negociação funciona: entrada
     * de R$ 500 e o saldo em duas, com datas que não caem de mês em mês. A
     * divisão automática é atalho para o caso comum, não a única forma.
     *
     * @var array<int, array{descricao: string, valor: string, vencimento: string}>
     */
    public array $linhas = [];

    public ?int $contaBaixaId = null;

    /** Parcela cuja NFS-e está sendo preparada. Nulo fecha o formulário. */
    public ?int $nfseParcelaId = null;

    public ?int $nfseServicoId = null;

    public string $nfseDescricao = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->primeiroVencimento = today()->toDateString();
        $this->contaBaixaId = $this->contas->first()?->getKey();
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
     * As notas do ambiente atual, uma por parcela desta lista.
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
            ->whereIn('fatura_parcela_id', $this->titulos->pluck('id'))
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

    private function parcelaDoEmitente(int $id): FaturaParcela
    {
        return FaturaParcela::query()
            ->whereHas('fatura', fn ($q) => $q->where('emitente_id', $this->emitente->getKey()))
            ->findOrFail($id);
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
            // Não é "por vencimento": são seis faixas, portadas da origem.
            ->emOrdemDeCobranca()
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

    /** Preenche as linhas a partir do total, do número de parcelas e da data. */
    public function gerarLinhas(): void
    {
        $centavos = Dinheiro::emCentavos($this->valor);

        if ($centavos <= 0 || $this->parcelas < 1) {
            $this->addError('valor', 'Informe o total e o número de parcelas.');

            return;
        }

        $primeiro = Carbon::parse($this->primeiroVencimento ?: today()->toDateString());

        $this->linhas = [];

        foreach ($this->dividir($centavos, $this->parcelas) as $i => $valorParcela) {
            $this->linhas[] = [
                'descricao' => $this->parcelas > 1
                    ? sprintf('%s, parcela %d de %d', $this->titulo ?: 'Parcela', $i + 1, $this->parcelas)
                    : ($this->titulo ?: 'Parcela'),
                'valor' => Dinheiro::formatar($valorParcela),
                'vencimento' => $primeiro->copy()->addMonthsNoOverflow($i)->toDateString(),
            ];
        }
    }

    public function adicionarLinha(): void
    {
        $ultima = end($this->linhas) ?: null;

        $this->linhas[] = [
            'descricao' => $this->titulo ?: 'Parcela',
            'valor' => '',
            'vencimento' => $ultima
                ? Carbon::parse($ultima['vencimento'])->addMonthNoOverflow()->toDateString()
                : today()->toDateString(),
        ];
    }

    public function removerLinha(int $indice): void
    {
        unset($this->linhas[$indice]);
        $this->linhas = array_values($this->linhas);
    }

    public function lancar(): void
    {
        $this->authorize('financeiro.gerenciar');

        $this->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'pessoaId' => ['nullable', 'integer'],
        ], [], ['titulo' => 'título']);

        // Caminho rápido: quem preencheu total e parcelas e mandou lançar não
        // precisa passar pelo botão de gerar.
        if ($this->linhas === []) {
            $this->gerarLinhas();
        }

        $parcelas = $this->parcelasValidadas();

        if ($parcelas === null) {
            return;
        }

        DB::transaction(function () use ($parcelas): void {
            $fatura = Fatura::create([
                'emitente_id' => $this->emitente->getKey(),
                'pessoa_id' => $this->pessoaId,
                'titulo' => $this->titulo,
            ]);

            foreach ($parcelas as $i => $linha) {
                FaturaParcela::create([
                    'fatura_id' => $fatura->getKey(),
                    'numero' => $i + 1,
                    'descricao' => $linha['descricao'],
                    'valor_centavos' => $linha['centavos'],
                    'vencimento' => $linha['vencimento'],
                ])->setRelation('fatura', $fatura)->gerarCobrancaPix();
            }
        });

        $this->reset(['titulo', 'valor', 'pessoaId', 'linhas']);
        $this->parcelas = 1;
        $this->primeiroVencimento = today()->toDateString();
        $this->esquecerTotais();

        session()->flash('sucesso', 'Fatura lançada.');
    }

    /**
     * Valida linha a linha antes de abrir transação.
     *
     * Uma linha ruim reprova a fatura inteira: meia fatura gravada é pior do
     * que nenhuma, porque o cliente recebe cobrança de parte do combinado.
     *
     * @return array<int, array{descricao: string, centavos: int, vencimento: string}>|null
     */
    private function parcelasValidadas(): ?array
    {
        if ($this->linhas === []) {
            $this->addError('linhas', 'Informe ao menos uma parcela.');

            return null;
        }

        $parcelas = [];

        foreach (array_values($this->linhas) as $i => $linha) {
            $centavos = Dinheiro::emCentavos((string) ($linha['valor'] ?? ''));
            $vencimento = trim((string) ($linha['vencimento'] ?? ''));

            if ($centavos <= 0) {
                $this->addError("linhas.{$i}.valor", 'Valor da parcela precisa ser maior que zero.');
            }

            if ($vencimento === '' || strtotime($vencimento) === false) {
                $this->addError("linhas.{$i}.vencimento", 'Informe o vencimento da parcela.');
            }

            $parcelas[] = [
                'descricao' => trim((string) ($linha['descricao'] ?? '')) ?: $this->titulo,
                'centavos' => $centavos,
                'vencimento' => $vencimento,
            ];
        }

        return $this->getErrorBag()->isEmpty() ? $parcelas : null;
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
