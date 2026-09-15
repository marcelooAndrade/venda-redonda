<?php

namespace App\Services\Financeiro;

use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\MovimentoCaixa;
use App\Models\Pessoa;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Painel executivo do financeiro.
 *
 * Portado de `buildExecutiveDashboard`, em `src/lib/dashboard.ts` do projeto
 * Marcelo Andrade, onde está em produção. Os números e as regras são os de lá;
 * o que mudou é a origem dos dados, que aqui vem do banco escopado por
 * emitente em vez de vir por parâmetro.
 *
 * O que ele responde, em ordem de importância para quem abre:
 *
 * - quanto existe hoje, somando saldo inicial e razão;
 * - quanto entrou e quanto saiu neste mês;
 * - quanto está vencido, e quantos títulos são;
 * - quanto sobraria se tudo o que está em aberto fosse liquidado.
 *
 * O saldo projetado **não é previsão**: é aritmética sobre o que já foi
 * lançado. Ele não considera venda que ainda não aconteceu nem conta que ainda
 * não chegou.
 */
class PainelFinanceiroService
{
    private const MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    /** @return array<string, mixed> */
    public function montar(int $emitenteId, ?CarbonInterface $hoje = null): array
    {
        // O projeto usa datas imutáveis; a conta interna é feita em Carbon
        // mutável para o encadeamento ficar legível.
        $hoje = Carbon::parse($hoje ?? today());
        $hojeIso = $hoje->toDateString();
        $mesAtual = $hoje->format('Y-m');

        $saldo = $this->saldoTotal($emitenteId);

        $recebidoNoMes = $this->somaMovimentos($emitenteId, 'credito', $mesAtual);
        $pagoNoMes = $this->somaMovimentos($emitenteId, 'debito', $mesAtual);

        $aReceber = $this->parcelasPendentes($emitenteId);
        $aPagar = $this->titulosPendentes($emitenteId);

        $receberVencidos = $aReceber->filter(fn ($p) => $p->vencimento->toDateString() < $hojeIso);
        $pagarVencidos = $aPagar->filter(fn ($t) => $t->vencimento->toDateString() < $hojeIso);

        $totalAReceber = (int) $aReceber->sum('valor_centavos');
        $totalAPagar = (int) $aPagar->sum('valor_centavos');

        return [
            'saldoCentavos' => $saldo,
            'recebidoNoMesCentavos' => $recebidoNoMes,
            'pagoNoMesCentavos' => $pagoNoMes,
            'resultadoDoMesCentavos' => $recebidoNoMes - $pagoNoMes,

            'aReceberCentavos' => $totalAReceber,
            'aPagarCentavos' => $totalAPagar,

            'receberVencidoCentavos' => (int) $receberVencidos->sum('valor_centavos'),
            'pagarVencidoCentavos' => (int) $pagarVencidos->sum('valor_centavos'),
            'receberVencidoQuantidade' => $receberVencidos->count(),
            'pagarVencidoQuantidade' => $pagarVencidos->count(),

            // Aritmética sobre o que já foi lançado, e não previsão de venda.
            'saldoProjetadoCentavos' => $saldo + $totalAReceber - $totalAPagar,

            'meses' => $this->seisMeses($emitenteId, $hoje),
            'compromissos' => $this->compromissos($aReceber, $aPagar, $hojeIso),

            // "Base ativa" do painel de lá: o tamanho da operação em duas contagens.
            'clientes' => Pessoa::where('emitente_id', $emitenteId)->where('e_cliente', true)->where('ativo', true)->count(),
            'faturasAtivas' => Fatura::where('emitente_id', $emitenteId)->where('status', 'ativa')->count(),
        ];
    }

    /** Saldo inicial de cada conta mais o razão dela. */
    private function saldoTotal(int $emitenteId): int
    {
        $inicial = (int) ContaFinanceira::where('emitente_id', $emitenteId)->sum('saldo_inicial_centavos');

        $creditos = (int) MovimentoCaixa::where('emitente_id', $emitenteId)
            ->where('sentido', 'credito')->sum('valor_centavos');

        $debitos = (int) MovimentoCaixa::where('emitente_id', $emitenteId)
            ->where('sentido', 'debito')->sum('valor_centavos');

        return $inicial + $creditos - $debitos;
    }

    private function somaMovimentos(int $emitenteId, string $sentido, string $mes): int
    {
        return (int) MovimentoCaixa::where('emitente_id', $emitenteId)
            ->where('sentido', $sentido)
            ->whereBetween('ocorrido_em', $this->limitesDoMes($mes))
            ->sum('valor_centavos');
    }

    /** @return Collection<int, FaturaParcela> */
    private function parcelasPendentes(int $emitenteId)
    {
        return FaturaParcela::query()
            ->whereHas('fatura', fn ($q) => $q->where('emitente_id', $emitenteId)->where('status', 'ativa'))
            ->where('status', 'pendente')
            ->with('fatura.destinatario')
            ->get();
    }

    /** @return Collection<int, ContaPagar> */
    private function titulosPendentes(int $emitenteId)
    {
        return ContaPagar::where('emitente_id', $emitenteId)->where('status', 'pendente')->get();
    }

    /**
     * Seis meses fechando no atual, com entradas, saídas e resultado.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seisMeses(int $emitenteId, Carbon $hoje): array
    {
        $meses = [];

        for ($i = 5; $i >= 0; $i--) {
            $referencia = $hoje->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $chave = $referencia->format('Y-m');

            $creditos = $this->somaMovimentos($emitenteId, 'credito', $chave);
            $debitos = $this->somaMovimentos($emitenteId, 'debito', $chave);

            $meses[] = [
                'chave' => $chave,
                'rotulo' => self::MESES[(int) $referencia->format('n') - 1],
                'creditosCentavos' => $creditos,
                'debitosCentavos' => $debitos,
                'resultadoCentavos' => $creditos - $debitos,
            ];
        }

        return $meses;
    }

    /**
     * O que exige ação, vencido primeiro e depois por vencimento.
     *
     * Sete linhas, como na origem: a lista existe para caber na tela sem
     * rolagem, e não para ser a lista completa, que já tem tela própria.
     *
     * @return array<int, array<string, mixed>>
     */
    private function compromissos($aReceber, $aPagar, string $hojeIso): array
    {
        $itens = [];

        foreach ($aReceber as $p) {
            $itens[] = [
                'tipo' => 'receber',
                'titulo' => $p->fatura?->destinatario?->razao_social ?: 'Cliente não identificado',
                'subtitulo' => $p->descricao,
                'valorCentavos' => (int) $p->valor_centavos,
                'vencimento' => $p->vencimento->toDateString(),
                'vencido' => $p->vencimento->toDateString() < $hojeIso,
            ];
        }

        foreach ($aPagar as $t) {
            $itens[] = [
                'tipo' => 'pagar',
                'titulo' => $t->fornecedor ?: 'Fornecedor não informado',
                'subtitulo' => $t->descricao,
                'valorCentavos' => (int) $t->valor_centavos,
                'vencimento' => $t->vencimento->toDateString(),
                'vencido' => $t->vencimento->toDateString() < $hojeIso,
            ];
        }

        usort($itens, function (array $a, array $b): int {
            return ((int) $b['vencido'] - (int) $a['vencido'])
                ?: strcmp($a['vencimento'], $b['vencimento']);
        });

        return array_slice($itens, 0, 7);
    }

    /** @return array{0: string, 1: string} */
    private function limitesDoMes(string $mes): array
    {
        $inicio = Carbon::parse($mes.'-01');

        return [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()];
    }
}
