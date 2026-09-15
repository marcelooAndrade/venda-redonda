<?php

use App\Models\CentroCusto;
use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\Fatura;
use App\Models\MovimentoCaixa;
use App\Services\Financeiro\BaixaService;
use App\Services\Financeiro\DreService;

/**
 * Reabrir é estorno, não apagamento: o razão de caixa é imutável. O projeto
 * Marcelo Andrade apaga o lançamento ao reabrir; aqui entra o contrário.
 */
beforeEach(function () {
    $this->travelTo('2026-09-15 10:00:00');
    $this->emitente = emitenteCompleto();
    $this->conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Conta', 'saldo_inicial_centavos' => 100_000]);
    $this->baixa = app(BaixaService::class);
});

it('estorna um recebimento com lancamento contrario e reabre a parcela', function () {
    $fatura = Fatura::create(['emitente_id' => $this->emitente->id, 'titulo' => 'Venda']);
    $parcela = $fatura->parcelas()->create(['numero' => 1, 'descricao' => 'Parcela', 'valor_centavos' => 25_000, 'vencimento' => today()]);

    $this->baixa->receber($parcela, $this->conta);
    expect($this->conta->fresh()->saldoCentavos())->toBe(125_000);

    $this->baixa->estornar($parcela->fresh());

    $estorno = MovimentoCaixa::where('origem_tipo', 'estorno')->sole();
    $original = MovimentoCaixa::where('origem_tipo', 'fatura_parcela')->sole();

    expect($estorno->origem_id)->toBe($original->id)
        ->and($estorno->sentido)->toBe('debito')
        ->and($estorno->valor_centavos)->toBe(25_000)
        ->and($estorno->conta_financeira_id)->toBe($this->conta->id)
        ->and($estorno->descricao)->toStartWith('Estorno: ')
        ->and($this->conta->fresh()->saldoCentavos())->toBe(100_000)
        ->and($parcela->fresh()->status)->toBe('pendente');
});

it('estorna um pagamento de conta a pagar', function () {
    $titulo = ContaPagar::create(['emitente_id' => $this->emitente->id, 'descricao' => 'Energia', 'valor_centavos' => 10_000, 'vencimento' => today()]);

    $this->baixa->pagar($titulo, $this->conta);
    $this->baixa->estornar($titulo->fresh());

    expect(MovimentoCaixa::where('origem_tipo', 'estorno')->value('sentido'))->toBe('credito')
        ->and($this->conta->fresh()->saldoCentavos())->toBe(100_000)
        ->and($titulo->fresh()->status)->toBe('pendente');
});

it('baixa sem conta reabre sem lancar nada', function () {
    $titulo = ContaPagar::create(['emitente_id' => $this->emitente->id, 'descricao' => 'Sem conta', 'valor_centavos' => 10_000, 'vencimento' => today()]);

    $this->baixa->pagar($titulo, null);
    $this->baixa->estornar($titulo->fresh());

    expect(MovimentoCaixa::count())->toBe(0)
        ->and($titulo->fresh()->status)->toBe('pendente');
});

it('so titulo pago pode ser reaberto', function () {
    $titulo = ContaPagar::create(['emitente_id' => $this->emitente->id, 'descricao' => 'Pendente', 'valor_centavos' => 10_000, 'vencimento' => today()]);

    expect(fn () => $this->baixa->estornar($titulo))->toThrow(RuntimeException::class);
});

it('a dre desconta o estorno da receita do centro, em vez de somar como despesa', function () {
    $centro = CentroCusto::create(['emitente_id' => $this->emitente->id, 'codigo' => '015.001.001', 'nome' => 'Projetos', 'natureza' => 'receita']);
    $fatura = Fatura::create(['emitente_id' => $this->emitente->id, 'titulo' => 'Venda', 'centro_custo_id' => $centro->id]);
    $parcela = $fatura->parcelas()->create(['numero' => 1, 'descricao' => 'Parcela', 'valor_centavos' => 25_000, 'vencimento' => today()]);

    $this->baixa->receber($parcela, $this->conta);
    $this->baixa->estornar($parcela->fresh());

    $dre = app(DreService::class)->montar($this->emitente->id, '2026-09');

    expect($dre['receitaCentavos'])->toBe(0)
        ->and($dre['despesaCentavos'])->toBe(0)
        ->and($dre['porCentroCusto'])->toHaveCount(1)
        ->and($dre['porCentroCusto'][0]['nome'])->toBe('Projetos')
        ->and($dre['porCentroCusto'][0]['receitaCentavos'])->toBe(0);
});
