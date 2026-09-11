<?php

use App\Models\CentroCusto;
use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\MovimentoCaixa;
use App\Services\Financeiro\BaixaService;

/**
 * Núcleo financeiro portado do projeto Marcelo Andrade.
 *
 * As regras aqui vêm de `src/lib/finance.ts` e do esquema em
 * `supabase/migrations`, adaptadas às convenções deste projeto: escopo por
 * emitente, nomes em português e razão imutável, como no estoque.
 */
beforeEach(function () {
    $this->emitente = emitenteCompleto();
    $this->conta = ContaFinanceira::create([
        'emitente_id' => $this->emitente->id,
        'nome' => 'Conta movimento',
        'banco' => 'Banco do Brasil',
        'saldo_inicial_centavos' => 100_000,
    ]);
});

describe('plano de contas', function () {
    it('valida e mede o nivel do codigo', function (string $codigo, bool $valido, int $nivel) {
        expect(CentroCusto::codigoValido($codigo))->toBe($valido)
            ->and(CentroCusto::nivelDoCodigo($codigo))->toBe($nivel);
    })->with([
        ['015', true, 1],
        ['015.001', true, 2],
        ['001.002.003', true, 3],
        ['0151', false, 0],
        ['015.1', false, 0],
        ['001.002.003.004', false, 0],
    ]);

    it('formata o que a pessoa digita sem separador', function () {
        expect(CentroCusto::formatarCodigo('015'))->toBe('015')
            ->and(CentroCusto::formatarCodigo('015001'))->toBe('015.001')
            ->and(CentroCusto::formatarCodigo('001002003'))->toBe('001.002.003')
            // Além de nove dígitos, o excedente é descartado.
            ->and(CentroCusto::formatarCodigo('001.002.00399'))->toBe('001.002.003');
    });

    it('a classificacao individual vence a do grupo', function () {
        $grupo = CentroCusto::create([
            'emitente_id' => $this->emitente->id, 'codigo' => '021', 'nome' => 'Despesas',
            'natureza' => 'despesa', 'grupo' => true, 'essencial' => true,
        ]);

        $herda = CentroCusto::create([
            'emitente_id' => $this->emitente->id, 'codigo' => '021.001', 'nome' => 'Aluguel',
            'natureza' => 'despesa', 'pai_id' => $grupo->id,
        ]);

        $proprio = CentroCusto::create([
            'emitente_id' => $this->emitente->id, 'codigo' => '021.002', 'nome' => 'Confraternização',
            'natureza' => 'despesa', 'pai_id' => $grupo->id, 'essencial' => false,
        ]);

        expect($herda->eEssencial())->toBeTrue()
            ->and($proprio->eEssencial())->toBeFalse();
    });
});

describe('saldo da conta', function () {
    it('e o inicial mais os creditos menos os debitos', function () {
        MovimentoCaixa::create([
            'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
            'sentido' => 'credito', 'valor_centavos' => 50_000,
            'descricao' => 'Recebimento', 'ocorrido_em' => today(), 'origem_tipo' => 'ajuste',
        ]);
        MovimentoCaixa::create([
            'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
            'sentido' => 'debito', 'valor_centavos' => 20_000,
            'descricao' => 'Pagamento', 'ocorrido_em' => today(), 'origem_tipo' => 'ajuste',
        ]);

        expect($this->conta->fresh()->saldoCentavos())->toBe(130_000);
    });
});

describe('razão de caixa', function () {
    it('recusa edicao e exclusao, como o movimento de estoque', function () {
        $m = MovimentoCaixa::create([
            'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
            'sentido' => 'credito', 'valor_centavos' => 1_000,
            'descricao' => 'Ajuste', 'ocorrido_em' => today(), 'origem_tipo' => 'ajuste',
        ]);

        expect(fn () => $m->update(['valor_centavos' => 2_000]))->toThrow(RuntimeException::class)
            ->and(fn () => $m->delete())->toThrow(RuntimeException::class);
    });
});

describe('baixa', function () {
    it('pagar um titulo marca como pago e lanca no caixa', function () {
        $titulo = ContaPagar::create([
            'emitente_id' => $this->emitente->id, 'descricao' => 'Energia',
            'fornecedor' => 'CPFL', 'valor_centavos' => 35_000, 'vencimento' => today(),
        ]);

        app(BaixaService::class)->pagar($titulo, $this->conta, today());

        $titulo->refresh();

        expect($titulo->status)->toBe('pago')
            ->and($titulo->pago_em)->not->toBeNull()
            ->and($this->conta->fresh()->saldoCentavos())->toBe(65_000);

        $mov = MovimentoCaixa::where('origem_tipo', 'conta_pagar')->where('origem_id', $titulo->id)->first();

        expect($mov)->not->toBeNull()
            ->and($mov->sentido)->toBe('debito')
            ->and($mov->valor_centavos)->toBe(35_000);
    });

    it('receber uma parcela credita a conta', function () {
        $fatura = Fatura::create([
            'emitente_id' => $this->emitente->id, 'titulo' => 'Venda 1001',
        ]);
        $parcela = FaturaParcela::create([
            'fatura_id' => $fatura->id, 'numero' => 1, 'descricao' => 'Parcela 1 de 2',
            'valor_centavos' => 25_000, 'vencimento' => today(),
        ]);

        app(BaixaService::class)->receber($parcela, $this->conta, today());

        expect($parcela->fresh()->status)->toBe('pago')
            ->and($this->conta->fresh()->saldoCentavos())->toBe(125_000);
    });

    it('baixa sem conta nao mexe em saldo, mas fecha o titulo', function () {
        // Regra da origem: lançamento liquidado sem conta bancária não altera o
        // saldo, e o painel precisa saber quantos existem para conciliar.
        $titulo = ContaPagar::create([
            'emitente_id' => $this->emitente->id, 'descricao' => 'Taxa',
            'valor_centavos' => 5_000, 'vencimento' => today(),
        ]);

        app(BaixaService::class)->pagar($titulo, null, today());

        expect($titulo->fresh()->status)->toBe('pago')
            ->and($this->conta->fresh()->saldoCentavos())->toBe(100_000)
            ->and(MovimentoCaixa::count())->toBe(0);
    });

    it('nao paga duas vezes', function () {
        $titulo = ContaPagar::create([
            'emitente_id' => $this->emitente->id, 'descricao' => 'Energia',
            'valor_centavos' => 35_000, 'vencimento' => today(),
        ]);

        app(BaixaService::class)->pagar($titulo, $this->conta, today());

        expect(fn () => app(BaixaService::class)->pagar($titulo->fresh(), $this->conta, today()))
            ->toThrow(RuntimeException::class);

        expect(MovimentoCaixa::count())->toBe(1);
    });

    it('conta de outro emitente e recusada, e o titulo nao fica pago', function () {
        // Prova a atomicidade junto com a regra: se a validação falha depois de
        // marcar o título, a transação desfaz tudo.
        $outro = Emitente::factory()->create();
        $contaAlheia = ContaFinanceira::create([
            'emitente_id' => $outro->id, 'nome' => 'Conta de outro',
            'saldo_inicial_centavos' => 0,
        ]);

        $titulo = ContaPagar::create([
            'emitente_id' => $this->emitente->id, 'descricao' => 'Energia',
            'valor_centavos' => 35_000, 'vencimento' => today(),
        ]);

        expect(fn () => app(BaixaService::class)->pagar($titulo, $contaAlheia, today()))
            ->toThrow(RuntimeException::class);

        expect($titulo->fresh()->status)->toBe('pendente')
            ->and(MovimentoCaixa::count())->toBe(0);
    });
});
