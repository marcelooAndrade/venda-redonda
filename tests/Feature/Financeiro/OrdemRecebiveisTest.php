<?php

use App\Models\Fatura;
use App\Models\FaturaParcela;

/**
 * Ordem portada de `sortReceivables` do projeto Marcelo Andrade.
 *
 * Não é "por vencimento": são seis faixas, e a direção inverte conforme a
 * faixa. O que já venceu dentro do mês corrente vem primeiro, porque é a
 * cobrança que ainda dá para salvar. O vencido de meses anteriores vem depois
 * do que ainda vai vencer neste mês, porque aquele já virou negociação e este
 * ainda é operação.
 */
beforeEach(function () {
    $this->emitente = emitenteCompleto();
    $this->fatura = Fatura::create([
        'emitente_id' => $this->emitente->id, 'titulo' => 'Venda',
    ]);
    $this->numero = 0;
});

function parcela(string $vencimento, string $status = 'pendente'): FaturaParcela
{
    return FaturaParcela::create([
        'fatura_id' => test()->fatura->id,
        'numero' => ++test()->numero,
        'descricao' => $vencimento.' '.$status,
        'valor_centavos' => 1000,
        'vencimento' => $vencimento,
        'status' => $status,
        'pago_em' => $status === 'pago' ? now() : null,
    ]);
}

it('ordena nas seis faixas, na ordem da origem', function () {
    $hoje = today();
    $inicioMes = $hoje->copy()->startOfMonth();
    $fimMes = $hoje->copy()->endOfMonth();

    // Criadas fora de ordem de propósito.
    $futuro = parcela($fimMes->copy()->addDays(20)->toDateString());
    $vencidoAnterior = parcela($inicioMes->copy()->subDays(40)->toDateString());
    $aVencerNoMes = parcela($hoje->copy()->addDay()->min($fimMes)->toDateString());
    $pago = parcela($hoje->toDateString(), 'pago');
    $vencidoNoMes = parcela($inicioMes->toDateString());
    $cancelado = parcela($hoje->toDateString(), 'cancelado');

    $ordem = FaturaParcela::query()->emOrdemDeCobranca()->pluck('id')->all();

    expect($ordem)->toBe([
        $vencidoNoMes->id,     // 0, venceu neste mês
        $aVencerNoMes->id,     // 1, ainda vence neste mês
        $vencidoAnterior->id,  // 2, vencido de mês anterior
        $futuro->id,           // 3, mês seguinte
        $pago->id,             // 4
        $cancelado->id,        // 5
    ]);
});

it('dentro do que ainda vai vencer, o mais proximo vem primeiro', function () {
    $hoje = today();
    $fimMes = $hoje->copy()->endOfMonth();

    if ($hoje->diffInDays($fimMes) < 3) {
        $this->markTestSkipped('Mês corrente curto demais para o caso.');
    }

    $longe = parcela($hoje->copy()->addDays(3)->toDateString());
    $perto = parcela($hoje->copy()->addDay()->toDateString());

    expect(FaturaParcela::query()->emOrdemDeCobranca()->pluck('id')->all())
        ->toBe([$perto->id, $longe->id]);
});

it('dentro do vencido, o mais recente vem primeiro', function () {
    $inicioMes = today()->copy()->startOfMonth();

    $antigo = parcela($inicioMes->copy()->subDays(60)->toDateString());
    $recente = parcela($inicioMes->copy()->subDays(5)->toDateString());

    expect(FaturaParcela::query()->emOrdemDeCobranca()->pluck('id')->all())
        ->toBe([$recente->id, $antigo->id]);
});
