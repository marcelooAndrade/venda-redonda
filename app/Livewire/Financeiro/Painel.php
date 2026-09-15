<?php

namespace App\Livewire\Financeiro;

use App\Enums\EtapaCrm;
use App\Models\ContatoCrm;
use App\Models\Emitente;
use App\Services\Financeiro\PainelFinanceiroService;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.fiscal')]
#[Title('Financeiro')]
class Painel extends Component
{
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

    /** @return array<string, mixed> */
    #[Computed]
    public function dados(): array
    {
        return app(PainelFinanceiroService::class)->montar($this->emitente->getKey());
    }

    /**
     * Contatos do funil que ainda não fecharam. O CRM é do dono do produto,
     * não do tenant: quem não é dono não vê a contagem, e ela vem nula.
     */
    #[Computed]
    public function contatosEmAndamento(): ?int
    {
        if (! Gate::allows('produto.administrar')) {
            return null;
        }

        $terminais = collect(EtapaCrm::cases())->filter(fn (EtapaCrm $e): bool => $e->terminal())->map->value->all();

        return ContatoCrm::query()->whereNotIn('etapa', $terminais)->count();
    }

    /**
     * A maior barra da série, para escalar as demais.
     *
     * Sem isso cada mês seria desenhado na própria escala, e o gráfico mentiria:
     * um mês de mil reais pareceria igual a um de cem mil.
     */
    #[Computed]
    public function tetoDaSerie(): int
    {
        $valores = [];

        foreach ($this->dados['meses'] as $mes) {
            $valores[] = $mes['creditosCentavos'];
            $valores[] = $mes['debitosCentavos'];
        }

        return max(1, ...$valores);
    }

    public function render()
    {
        return view('livewire.financeiro.painel');
    }
}
