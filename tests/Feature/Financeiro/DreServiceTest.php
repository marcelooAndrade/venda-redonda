<?php

use App\Models\CentroCusto;
use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\MovimentoCaixa;
use App\Services\Financeiro\DreService;

beforeEach(function () {
    $this->emitente = emitenteCompleto();
    $this->conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Caixa']);
});

it('soma receita e despesa do mes pelo razao de caixa', function () {
    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'credito', 'valor_centavos' => 10_000, 'descricao' => 'Venda',
        'ocorrido_em' => '2026-09-10', 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'debito', 'valor_centavos' => 4_000, 'descricao' => 'Aluguel',
        'ocorrido_em' => '2026-09-15', 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    // Fora do mês: não pode entrar na soma.
    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'credito', 'valor_centavos' => 99_999, 'descricao' => 'Mes errado',
        'ocorrido_em' => '2026-08-31', 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    $dados = app(DreService::class)->montar($this->emitente->id, '2026-09');

    expect($dados['receitaCentavos'])->toBe(10_000)
        ->and($dados['despesaCentavos'])->toBe(4_000)
        ->and($dados['resultadoCentavos'])->toBe(6_000);
});

it('resolve o centro de custo de uma conta a pagar baixada', function () {
    $centro = CentroCusto::create([
        'emitente_id' => $this->emitente->id, 'codigo' => '001',
        'nome' => 'Aluguel', 'natureza' => 'despesa',
    ]);

    $titulo = ContaPagar::create([
        'emitente_id' => $this->emitente->id, 'centro_custo_id' => $centro->id,
        'descricao' => 'Aluguel de setembro', 'valor_centavos' => 3_000, 'vencimento' => '2026-09-05',
        'status' => 'pago',
    ]);

    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'debito', 'valor_centavos' => 3_000, 'descricao' => 'Aluguel de setembro',
        'ocorrido_em' => '2026-09-05', 'origem_tipo' => 'conta_pagar', 'origem_id' => $titulo->id,
        'created_at' => now(),
    ]);

    $dados = app(DreService::class)->montar($this->emitente->id, '2026-09');

    expect($dados['porCentroCusto'])->toHaveCount(1)
        ->and($dados['porCentroCusto'][0]['nome'])->toBe('Aluguel')
        ->and($dados['porCentroCusto'][0]['despesaCentavos'])->toBe(3_000);
});

it('resolve o centro de custo de uma parcela recebida pela fatura', function () {
    $centro = CentroCusto::create([
        'emitente_id' => $this->emitente->id, 'codigo' => '001',
        'nome' => 'Vendas', 'natureza' => 'receita',
    ]);

    $fatura = Fatura::create([
        'emitente_id' => $this->emitente->id, 'centro_custo_id' => $centro->id, 'titulo' => 'Venda 1',
    ]);

    $parcela = FaturaParcela::create([
        'fatura_id' => $fatura->id, 'numero' => 1, 'descricao' => 'Parcela 1',
        'valor_centavos' => 8_000, 'vencimento' => '2026-09-10', 'status' => 'pago',
    ]);

    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'credito', 'valor_centavos' => 8_000, 'descricao' => 'Parcela 1',
        'ocorrido_em' => '2026-09-10', 'origem_tipo' => 'fatura_parcela', 'origem_id' => $parcela->id,
        'created_at' => now(),
    ]);

    $dados = app(DreService::class)->montar($this->emitente->id, '2026-09');

    expect($dados['porCentroCusto'])->toHaveCount(1)
        ->and($dados['porCentroCusto'][0]['nome'])->toBe('Vendas')
        ->and($dados['porCentroCusto'][0]['receitaCentavos'])->toBe(8_000);
});

it('ajuste sem origem cai no grupo sem centro de custo', function () {
    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'debito', 'valor_centavos' => 500, 'descricao' => 'Taxa bancaria',
        'ocorrido_em' => '2026-09-12', 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    $dados = app(DreService::class)->montar($this->emitente->id, '2026-09');

    expect($dados['porCentroCusto'])->toHaveCount(1)
        ->and($dados['porCentroCusto'][0]['nome'])->toBe('Sem centro de custo')
        ->and($dados['porCentroCusto'][0]['centroCustoId'])->toBeNull();
});
