<?php

namespace App\Livewire\Produto;

use App\Enums\EtapaCrm;
use App\Models\ContaFinanceira;
use App\Models\ContatoCrm;
use App\Models\Emitente;
use App\Models\MovimentoCaixa;
use App\Models\Pessoa;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Visão geral da área administrativa: um resumo do CRM, dos clientes e do
 * financeiro da empresa Marcelo Andrade, na mesma tela.
 *
 * O financeiro aqui é o da empresa Marcelo Andrade como tenant, resolvido
 * pelo primeiro emitente dela. Não reaproveita `PainelFinanceiroService`
 * porque ele lê pelo tenant da sessão (`DoTenantViaEmitente`), e quem abre
 * esta tela está com o próprio tenant do dono em foco, não o da Marcelo
 * Andrade: a mesma leitura sem escopo que o painel de Empresas já faz.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Visão geral')]
class VisaoGeral extends Component
{
    public function mount(): void
    {
        $this->authorize('produto.administrar');
    }

    private function tenant(): ?Tenant
    {
        $slug = (string) config('produto.tenant_administrativo');

        return $slug === '' ? null : Tenant::where('slug', $slug)->first();
    }

    private function emitente(): ?Emitente
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return null;
        }

        return Emitente::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->first();
    }

    /** @return array<string, int> */
    #[Computed]
    public function crm(): array
    {
        $porEtapa = ContatoCrm::query()->selectRaw('etapa, count(*) as total')->groupBy('etapa')->pluck('total', 'etapa');

        $emAberto = collect(EtapaCrm::cases())
            ->reject(fn (EtapaCrm $e): bool => $e->terminal())
            ->sum(fn (EtapaCrm $e): int => (int) ($porEtapa[$e->value] ?? 0));

        return [
            'total' => (int) $porEtapa->sum(),
            'emAberto' => $emAberto,
            'ganhos' => (int) ($porEtapa[EtapaCrm::Ganho->value] ?? 0),
            'perdidos' => (int) ($porEtapa[EtapaCrm::Perdido->value] ?? 0),
        ];
    }

    #[Computed]
    public function tenantConfigurado(): ?Tenant
    {
        return $this->tenant();
    }

    #[Computed]
    public function totalClientes(): int
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return 0;
        }

        $emitenteIds = Emitente::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->pluck('id');

        if ($emitenteIds->isEmpty()) {
            return 0;
        }

        return Pessoa::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('emitente_id', $emitenteIds)
            ->where('e_cliente', true)
            ->count();
    }

    /** @return array{saldoCentavos: int, recebidoNoMesCentavos: int, pagoNoMesCentavos: int}|null */
    #[Computed]
    public function financeiro(): ?array
    {
        $emitente = $this->emitente();

        if ($emitente === null) {
            return null;
        }

        $emitenteId = $emitente->getKey();

        $inicial = (int) ContaFinanceira::query()
            ->withoutGlobalScope('tenant')
            ->where('emitente_id', $emitenteId)
            ->sum('saldo_inicial_centavos');

        $creditos = (int) $this->somaMovimentos($emitenteId, 'credito');
        $debitos = (int) $this->somaMovimentos($emitenteId, 'debito');

        $inicioMes = Carbon::today()->startOfMonth();
        $fimMes = $inicioMes->copy()->endOfMonth();

        return [
            'saldoCentavos' => $inicial + $creditos - $debitos,
            'recebidoNoMesCentavos' => (int) $this->somaMovimentos($emitenteId, 'credito', $inicioMes, $fimMes),
            'pagoNoMesCentavos' => (int) $this->somaMovimentos($emitenteId, 'debito', $inicioMes, $fimMes),
        ];
    }

    private function somaMovimentos(int $emitenteId, string $sentido, ?Carbon $inicio = null, ?Carbon $fim = null): int
    {
        return (int) MovimentoCaixa::query()
            ->withoutGlobalScope('tenant')
            ->where('emitente_id', $emitenteId)
            ->where('sentido', $sentido)
            ->when($inicio && $fim, fn ($q) => $q->whereBetween('ocorrido_em', [$inicio->toDateString(), $fim->toDateString()]))
            ->sum('valor_centavos');
    }

    public function render()
    {
        return view('livewire.produto.visao-geral');
    }
}
