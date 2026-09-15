<?php

namespace App\Services\Financeiro;

use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\FaturaParcela;
use App\Models\MovimentoCaixa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * DRE simplificada: entrou vs. saiu no razão de caixa, por mês, quebrado por
 * centro de custo.
 *
 * Não existe DRE nenhuma para portar: `FinancialModulePage.tsx`, no projeto
 * Marcelo Andrade, é um placeholder estático compartilhado por contas a
 * pagar, a receber, caixa e DRE, sem cálculo nenhum atrás. Este relatório é
 * desenho novo, e por isso é regime de caixa, não de competência: soma o que
 * de fato entrou e saiu, pela data de `ocorrido_em` do razão. Uma DRE contábil
 * de verdade trabalha por competência (quando a receita ou a despesa foi
 * gerada, não quando o dinheiro mudou de mão) e não é o que este relatório
 * faz.
 *
 * O centro de custo de cada movimento não é gravado nele: é resolvido pela
 * origem. `conta_pagar` tem `centro_custo_id` direto; `fatura_parcela` pega o
 * da fatura. Um `ajuste` lançado direto numa conta não tem origem com centro
 * de custo, e cai no grupo "Sem centro de custo".
 */
class DreService
{
    /** @return array<string, mixed> */
    public function montar(int $emitenteId, string $mes): array
    {
        $inicio = Carbon::parse($mes.'-01')->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $movimentos = MovimentoCaixa::query()
            ->where('emitente_id', $emitenteId)
            ->whereBetween('ocorrido_em', [$inicio->toDateString(), $fim->toDateString()])
            ->get();

        $centroCustoPorMovimento = $this->resolverCentrosCusto($movimentos);

        $receitaCentavos = (int) $movimentos->where('sentido', 'credito')->sum('valor_centavos');
        $despesaCentavos = (int) $movimentos->where('sentido', 'debito')->sum('valor_centavos');

        return [
            'mes' => $mes,
            'receitaCentavos' => $receitaCentavos,
            'despesaCentavos' => $despesaCentavos,
            'resultadoCentavos' => $receitaCentavos - $despesaCentavos,
            'porCentroCusto' => $this->agruparPorCentroCusto($movimentos, $centroCustoPorMovimento, $emitenteId),
        ];
    }

    /**
     * Resolve o centro de custo de cada movimento pela origem, com um único
     * carregamento em lote por tipo de origem, para não consultar linha a
     * linha.
     *
     * @param  Collection<int, MovimentoCaixa>  $movimentos
     * @return array<int, ?int> chave é o id do movimento, valor é o id do centro de custo (ou null)
     */
    private function resolverCentrosCusto(Collection $movimentos): array
    {
        $idsContaPagar = $movimentos->where('origem_tipo', 'conta_pagar')->pluck('origem_id')->filter()->all();
        $idsFaturaParcela = $movimentos->where('origem_tipo', 'fatura_parcela')->pluck('origem_id')->filter()->all();

        $centroPorContaPagar = ContaPagar::query()
            ->whereIn('id', $idsContaPagar)
            ->pluck('centro_custo_id', 'id');

        $centroPorFaturaParcela = [];

        foreach (FaturaParcela::query()->whereIn('id', $idsFaturaParcela)->with('fatura')->get() as $parcela) {
            $fatura = $parcela->fatura;
            $centroPorFaturaParcela[$parcela->id] = $fatura?->centro_custo_id;
        }

        $resultado = [];

        foreach ($movimentos as $movimento) {
            $resultado[$movimento->id] = match ($movimento->origem_tipo) {
                'conta_pagar' => $centroPorContaPagar[$movimento->origem_id] ?? null,
                'fatura_parcela' => $centroPorFaturaParcela[$movimento->origem_id] ?? null,
                default => null,
            };
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, MovimentoCaixa>  $movimentos
     * @param  array<int, ?int>  $centroCustoPorMovimento
     * @return array<int, array<string, mixed>>
     */
    private function agruparPorCentroCusto(Collection $movimentos, array $centroCustoPorMovimento, int $emitenteId): array
    {
        $centros = CentroCusto::where('emitente_id', $emitenteId)->get()->keyBy('id');

        $porGrupo = $movimentos->groupBy(fn (MovimentoCaixa $m) => $centroCustoPorMovimento[$m->id] ?? 'sem_centro');

        $linhas = $porGrupo->map(function (Collection $doGrupo, int|string $centroCustoId) use ($centros): array {
            $centro = $centroCustoId === 'sem_centro' ? null : $centros->get($centroCustoId);

            return [
                'centroCustoId' => $centro?->id,
                'codigo' => $centro?->codigo,
                'nome' => $centro === null ? 'Sem centro de custo' : $centro->nome,
                'receitaCentavos' => (int) $doGrupo->where('sentido', 'credito')->sum('valor_centavos'),
                'despesaCentavos' => (int) $doGrupo->where('sentido', 'debito')->sum('valor_centavos'),
            ];
        })->values()->all();

        usort($linhas, fn (array $a, array $b): int => ($a['codigo'] ?? 'zzz') <=> ($b['codigo'] ?? 'zzz'));

        return $linhas;
    }
}
