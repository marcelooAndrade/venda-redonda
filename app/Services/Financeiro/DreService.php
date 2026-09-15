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
 * de custo, e cai no grupo "Sem centro de custo". Um `estorno` aponta para o
 * movimento original e é descontado dele: um recebimento estornado some da
 * receita, em vez de aparecer como despesa.
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

        $lancamentos = $this->lancamentosEfetivos($movimentos);

        $receitaCentavos = (int) $lancamentos->where('sentido', 'credito')->sum('valor');
        $despesaCentavos = (int) $lancamentos->where('sentido', 'debito')->sum('valor');

        return [
            'mes' => $mes,
            'receitaCentavos' => $receitaCentavos,
            'despesaCentavos' => $despesaCentavos,
            'resultadoCentavos' => $receitaCentavos - $despesaCentavos,
            'porCentroCusto' => $this->agruparPorCentroCusto($lancamentos, $emitenteId),
        ];
    }

    /**
     * Cada movimento vira um lançamento com centro resolvido e valor com
     * sinal. O estorno herda o centro e o sentido do original, com valor
     * negativo: é assim que ele desconta em vez de somar do outro lado.
     *
     * @param  Collection<int, MovimentoCaixa>  $movimentos
     * @return Collection<int, array{centro: int|null, sentido: string, valor: int}>
     */
    private function lancamentosEfetivos(Collection $movimentos): Collection
    {
        $idsContaPagar = $movimentos->where('origem_tipo', 'conta_pagar')->pluck('origem_id')->filter()->all();
        $idsFaturaParcela = $movimentos->where('origem_tipo', 'fatura_parcela')->pluck('origem_id')->filter()->all();
        $idsEstornados = $movimentos->where('origem_tipo', 'estorno')->pluck('origem_id')->filter()->all();

        $centroPorContaPagar = ContaPagar::query()->whereIn('id', $idsContaPagar)->pluck('centro_custo_id', 'id');

        $centroPorFaturaParcela = [];

        foreach (FaturaParcela::query()->whereIn('id', $idsFaturaParcela)->with('fatura')->get() as $parcela) {
            $fatura = $parcela->fatura;
            $centroPorFaturaParcela[$parcela->id] = $fatura?->centro_custo_id;
        }

        // O original de um estorno pode estar noutro mês: busca à parte, e
        // resolve o centro dele pela mesma regra, recursivamente uma vez.
        $originais = MovimentoCaixa::query()->whereIn('id', $idsEstornados)->get()->keyBy('id');
        $centroDosOriginais = $originais->isEmpty() ? collect() : $this->lancamentosEfetivos($originais)->keyBy('id');

        return $movimentos->map(function (MovimentoCaixa $m) use ($centroPorContaPagar, $centroPorFaturaParcela, $originais, $centroDosOriginais): array {
            if ($m->origem_tipo === 'estorno') {
                $original = $originais->get($m->origem_id);

                return [
                    'id' => $m->id,
                    'centro' => $centroDosOriginais->get($m->origem_id)['centro'] ?? null,
                    'sentido' => $original?->sentido ?? ($m->sentido === 'credito' ? 'debito' : 'credito'),
                    'valor' => -(int) $m->valor_centavos,
                ];
            }

            $centro = match ($m->origem_tipo) {
                'conta_pagar' => $centroPorContaPagar[$m->origem_id] ?? null,
                'fatura_parcela' => $centroPorFaturaParcela[$m->origem_id] ?? null,
                default => null,
            };

            return ['id' => $m->id, 'centro' => $centro === null ? null : (int) $centro, 'sentido' => $m->sentido, 'valor' => (int) $m->valor_centavos];
        });
    }

    /**
     * @param  Collection<int, array{centro: int|null, sentido: string, valor: int}>  $lancamentos
     * @return array<int, array<string, mixed>>
     */
    private function agruparPorCentroCusto(Collection $lancamentos, int $emitenteId): array
    {
        $centros = CentroCusto::where('emitente_id', $emitenteId)->get()->keyBy('id');

        $porGrupo = $lancamentos->groupBy(fn (array $l) => $l['centro'] ?? 'sem_centro');

        $linhas = $porGrupo->map(function (Collection $doGrupo, int|string $centroCustoId) use ($centros): array {
            $centro = $centroCustoId === 'sem_centro' ? null : $centros->get($centroCustoId);

            return [
                'centroCustoId' => $centro?->id,
                'codigo' => $centro?->codigo,
                'nome' => $centro === null ? 'Sem centro de custo' : $centro->nome,
                'receitaCentavos' => (int) $doGrupo->where('sentido', 'credito')->sum('valor'),
                'despesaCentavos' => (int) $doGrupo->where('sentido', 'debito')->sum('valor'),
            ];
        })->values()->all();

        usort($linhas, fn (array $a, array $b): int => ($a['codigo'] ?? 'zzz') <=> ($b['codigo'] ?? 'zzz'));

        return $linhas;
    }
}
