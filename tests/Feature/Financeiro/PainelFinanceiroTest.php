<?php

use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\MovimentoCaixa;
use App\Services\Financeiro\PainelFinanceiroService;

/**
 * Portado de `buildExecutiveDashboard` do projeto Marcelo Andrade.
 *
 * Os mesmos números foram passados para a função original, rodando em Node, e
 * a saída conferida contra esta. O teste aqui fixa o resultado; a comparação
 * com a origem está registrada no commit.
 */
beforeEach(function () {
    $this->travelTo('2026-09-11 10:00:00');

    $this->emitente = emitenteCompleto();

    $conta = ContaFinanceira::create([
        'emitente_id' => $this->emitente->id,
        'nome' => 'Conta', 'saldo_inicial_centavos' => 100_000,
    ]);

    foreach ([
        ['credito', 50_000, '2026-09-05'],
        ['debito', 20_000, '2026-09-07'],
        ['credito', 30_000, '2026-08-20'],
    ] as [$sentido, $valor, $quando]) {
        MovimentoCaixa::create([
            'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $conta->id,
            'sentido' => $sentido, 'valor_centavos' => $valor,
            'descricao' => 'Movimento', 'ocorrido_em' => $quando, 'origem_tipo' => 'ajuste',
        ]);
    }

    $fatura = Fatura::create(['emitente_id' => $this->emitente->id, 'titulo' => 'Venda']);

    foreach ([[1, 25_000, '2026-09-01'], [2, 25_000, '2026-10-01']] as [$n, $valor, $venc]) {
        FaturaParcela::create([
            'fatura_id' => $fatura->id, 'numero' => $n, 'descricao' => "Parcela {$n}",
            'valor_centavos' => $valor, 'vencimento' => $venc,
        ]);
    }

    foreach ([['Energia', 35_000, '2026-09-02'], ['Frete', 15_000, '2026-09-25']] as [$desc, $valor, $venc]) {
        ContaPagar::create([
            'emitente_id' => $this->emitente->id, 'descricao' => $desc, 'fornecedor' => $desc,
            'valor_centavos' => $valor, 'vencimento' => $venc,
        ]);
    }

    $this->painel = app(PainelFinanceiroService::class)->montar($this->emitente->id);
});

it('soma o saldo pelo razao, e nao por campo gravado', function () {
    // 1.000,00 inicial + 800,00 de créditos − 200,00 de débitos
    expect($this->painel['saldoCentavos'])->toBe(160_000);
});

it('separa o que entrou do que saiu no mes', function () {
    expect($this->painel['recebidoNoMesCentavos'])->toBe(50_000)
        ->and($this->painel['pagoNoMesCentavos'])->toBe(20_000)
        ->and($this->painel['resultadoDoMesCentavos'])->toBe(30_000);
});

it('conta o vencido, em valor e em quantidade', function () {
    expect($this->painel['receberVencidoCentavos'])->toBe(25_000)
        ->and($this->painel['receberVencidoQuantidade'])->toBe(1)
        ->and($this->painel['pagarVencidoCentavos'])->toBe(35_000)
        ->and($this->painel['pagarVencidoQuantidade'])->toBe(1);
});

it('projeta o saldo somando o que esta em aberto', function () {
    expect($this->painel['saldoProjetadoCentavos'])->toBe(160_000);
});

it('entrega seis meses, fechando no atual', function () {
    $meses = $this->painel['meses'];

    expect($meses)->toHaveCount(6)
        ->and($meses[5]['chave'])->toBe('2026-09')
        ->and($meses[5]['rotulo'])->toBe('Set')
        ->and($meses[5]['resultadoCentavos'])->toBe(30_000)
        ->and($meses[4]['chave'])->toBe('2026-08')
        ->and($meses[4]['creditosCentavos'])->toBe(30_000)
        ->and($meses[0]['chave'])->toBe('2026-04');
});

it('lista compromissos com o vencido na frente', function () {
    $c = $this->painel['compromissos'];

    expect($c)->toHaveCount(4)
        // Vencidos primeiro, entre eles o mais antigo antes.
        ->and($c[0]['vencimento'])->toBe('2026-09-01')
        ->and($c[0]['vencido'])->toBeTrue()
        ->and($c[1]['vencimento'])->toBe('2026-09-02')
        // Depois o que ainda vai vencer, por data.
        ->and($c[2]['vencimento'])->toBe('2026-09-25')
        ->and($c[2]['vencido'])->toBeFalse()
        ->and($c[3]['vencimento'])->toBe('2026-10-01');
});

it('conta a base ativa: clientes e faturas ativas', function () {
    destinatarioCompleto($this->emitente);
    destinatarioCompleto($this->emitente, ['documento' => '11222333000181', 'razao_social' => 'Fornecedor', 'e_cliente' => false, 'e_fornecedor' => true]);
    Fatura::create(['emitente_id' => $this->emitente->id, 'titulo' => 'Cancelada', 'status' => 'cancelada']);

    $painel = app(PainelFinanceiroService::class)->montar($this->emitente->id);

    expect($painel['clientes'])->toBe(1)
        ->and($painel['faturasAtivas'])->toBe(1);
});
