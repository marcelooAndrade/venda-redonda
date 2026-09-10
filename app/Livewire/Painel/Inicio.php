<?php

namespace App\Livewire\Painel;

use App\Enums\Fiscal\NFeStatus;
use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\EstoqueSaldo;
use App\Models\Nota;
use App\Models\PerfilFiscalRegra;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Painel de entrada.
 *
 * A ordem da tela é deliberada: primeiro o que exige ação, depois o que
 * aconteceu. Quem abre o sistema de manhã precisa saber o que travou ontem
 * antes de saber quanto faturou no mês.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Painel')]
class Inicio extends Component
{
    /**
     * Números do mês corrente.
     *
     * Propriedade e não computed: são quatro contagens que a tela sempre
     * mostra, e deixá-las públicas permite asserção direta no teste.
     *
     * @var array{autorizadas:int, faturado:float, canceladas:int, rascunhos:int}
     */
    public array $mes = [
        'autorizadas' => 0,
        'faturado' => 0.0,
        'canceladas' => 0,
        'rascunhos' => 0,
    ];

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('relatorio.ver');

        $this->mes = $this->apurarMes();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /**
     * @return array{autorizadas:int, faturado:float, canceladas:int, rascunhos:int}
     */
    private function apurarMes(): array
    {
        $doMes = fn () => Nota::query()->whereBetween('data_emissao', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);

        // Cancelada não fatura: ela existiu e foi desfeita. Somá-la infla o
        // faturamento e é o tipo de número que ninguém confere depois.
        $autorizadas = $doMes()->where('status', NFeStatus::Autorizada->value);

        return [
            'autorizadas' => (clone $autorizadas)->count(),
            'faturado' => round((float) (clone $autorizadas)->sum('valor_nota'), 2),
            'canceladas' => $doMes()->where('status', NFeStatus::Cancelada->value)->count(),
            'rascunhos' => Nota::query()->where('status', NFeStatus::Rascunho->value)->count(),
        ];
    }

    /**
     * Notas que pararam no meio do caminho.
     *
     * Em processamento é a mais urgente: a SEFAZ pode já ter autorizado sem
     * que a resposta tenha voltado, então reemitir criaria duplicidade.
     *
     * @return Collection<int, Nota>
     */
    #[Computed]
    public function pendentes(): Collection
    {
        return Nota::query()
            ->with('destinatario')
            ->whereIn('status', [
                NFeStatus::EmProcessamento->value,
                NFeStatus::Rejeitada->value,
                NFeStatus::Contingencia->value,
            ])
            ->orderByDesc('data_emissao')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, Nota>
     */
    #[Computed]
    public function ultimas(): Collection
    {
        return Nota::query()
            ->with('destinatario')
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function certificado(): ?EmitenteCertificado
    {
        return EmitenteCertificado::query()
            ->where('emitente_id', $this->emitente?->getKey())
            ->where('ativo', true)
            ->orderByDesc('valido_ate')
            ->first();
    }

    /**
     * Dias até o certificado vencer. Negativo quando já venceu.
     */
    #[Computed]
    public function diasDeCertificado(): ?int
    {
        return $this->certificado === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->certificado->valido_ate->startOfDay(), false);
    }

    /**
     * Sem regra vigente hoje, toda emissão para. É pendência do contador, e
     * quem descobre isso na hora de faturar já perdeu a manhã.
     */
    #[Computed]
    public function semRegraVigente(): bool
    {
        $hoje = now()->toDateString();

        return ! PerfilFiscalRegra::query()
            ->whereDate('vigente_de', '<=', $hoje)
            ->where(fn ($q) => $q->whereNull('vigente_ate')->orWhereDate('vigente_ate', '>=', $hoje))
            ->exists();
    }

    /**
     * @return Collection<int, EstoqueSaldo>
     */
    #[Computed]
    public function abaixoDoMinimo(): Collection
    {
        return EstoqueSaldo::query()->with('produto')->get()->filter->abaixoDoMinimo();
    }

    public function render()
    {
        return view('livewire.painel.inicio');
    }
}
