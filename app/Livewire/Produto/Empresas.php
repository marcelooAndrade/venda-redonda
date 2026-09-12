<?php

namespace App\Livewire\Produto;

use App\Enums\PlanoTenant;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Quem se cadastrou na Venda Redonda.
 *
 * É a única tela que enxerga todos os tenants, e por isso só abre para o
 * dono do produto. `Tenant` não tem escopo global, mas `Emitente` e `User`
 * têm, e aqui o tenant do contêiner é o do próprio dono: toda leitura das
 * duas relações passa por `withoutGlobalScope('tenant')`, senão a lista
 * mostraria o CNPJ e o contato só da empresa dele.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Empresas')]
class Empresas extends Component
{
    use WithPagination;

    /**
     * Propriedade, e não computed, para asserção direta no teste, como o
     * painel de entrada faz com os números do mês.
     *
     * @var array{total:int, novasNoMes:int, ativasEm30Dias:int, porPlano:array<string,int>}
     */
    public array $totais = [
        'total' => 0,
        'novasNoMes' => 0,
        'ativasEm30Dias' => 0,
        'porPlano' => [],
    ];

    public function mount(): void
    {
        $this->authorize('produto.administrar');

        $this->totais = $this->apurar();
    }

    /** @return array{total:int, novasNoMes:int, ativasEm30Dias:int, porPlano:array<string,int>} */
    private function apurar(): array
    {
        $hoje = today();

        $porPlano = Tenant::query()
            ->select('plano', DB::raw('count(*) as quantidade'))
            ->groupBy('plano')
            ->pluck('quantidade', 'plano');

        return [
            'total' => Tenant::query()->count(),

            'novasNoMes' => Tenant::query()
                ->whereBetween('created_at', [$hoje->startOfMonth(), $hoje->endOfMonth()->endOfDay()])
                ->count(),

            // Ativa é a empresa em que alguém entrou nos últimos 30 dias. O
            // cadastro sozinho não conta: o que interessa é quem voltou.
            'ativasEm30Dias' => Tenant::query()
                ->whereHas('users', fn ($q) => $q
                    ->withoutGlobalScope('tenant')
                    ->where('ultimo_acesso_em', '>=', $hoje->subDays(30)->startOfDay()))
                ->count(),

            // Os dois planos sempre aparecem, mesmo zerados, para a tela não
            // sumir com um plano que ninguém contratou ainda.
            'porPlano' => collect(PlanoTenant::cases())
                ->mapWithKeys(fn (PlanoTenant $p): array => [$p->value => (int) ($porPlano[$p->value] ?? 0)])
                ->all(),
        ];
    }

    /**
     * Mais recente primeiro. O primeiro emitente e o primeiro usuário de cada
     * tenant são o CNPJ e o contato da empresa, como o cadastro os criou.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    #[Computed]
    public function empresas(): LengthAwarePaginator
    {
        return Tenant::query()
            ->with([
                'emitentes' => fn ($q) => $q->withoutGlobalScope('tenant')->orderBy('id'),
                'users' => fn ($q) => $q->withoutGlobalScope('tenant')->orderBy('id'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function render()
    {
        return view('livewire.produto.empresas');
    }
}
