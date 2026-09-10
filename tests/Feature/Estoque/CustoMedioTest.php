<?php

use App\Enums\Fiscal\TipoMovimentoEstoque;
use App\Models\Emitente;
use App\Models\User;
use App\Services\Stock\StockService;

beforeEach(function () {
    $this->emitente = Emitente::factory()->create();
    $this->produto = produtoDe($this->emitente);
    $this->user = User::factory()->create();
    $this->stock = app(StockService::class);
});

describe('custo médio ponderado', function () {
    it('define o custo na primeira entrada', function () {
        $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);

        $saldo = $this->stock->saldo($this->produto);

        expect($saldo->quantidade)->toBe(100.0)
            ->and($saldo->custo_medio)->toBe(10.0);
    });

    it('pondera a segunda entrada pelo saldo existente', function () {
        // 100 a 10,00 mais 50 a 16,00 = (1000 + 800) / 150 = 12,00
        $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);
        $this->stock->entrada($this->produto, 50, 16.00, 'NF 2', $this->user);

        $saldo = $this->stock->saldo($this->produto);

        expect($saldo->quantidade)->toBe(150.0)
            ->and($saldo->custo_medio)->toBe(12.0);
    });

    it('nao altera o custo medio na saida', function () {
        $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);
        $this->stock->entrada($this->produto, 50, 16.00, 'NF 2', $this->user);

        $this->stock->saida($this->produto, 30, 'NF-e 100', $this->user);

        $saldo = $this->stock->saldo($this->produto);

        expect($saldo->quantidade)->toBe(120.0)
            ->and($saldo->custo_medio)->toBe(12.0);
    });

    it('grava o custo medio vigente no movimento de saida', function () {
        $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);
        $this->stock->entrada($this->produto, 50, 16.00, 'NF 2', $this->user);

        $mov = $this->stock->saida($this->produto, 30, 'NF-e 100', $this->user);

        // Sem isto, o custo da saída se perde quando a média muda depois.
        expect((float) $mov->custo_unitario)->toBe(12.0);
    });

    it('ignora entrada sem custo no calculo da media', function () {
        $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);
        $this->stock->ajuste($this->produto, 20, 'Contagem', $this->user);

        expect($this->stock->saldo($this->produto)->custo_medio)->toBe(10.0)
            ->and($this->stock->saldo($this->produto)->quantidade)->toBe(120.0);
    });
});

describe('saldo negativo', function () {
    it('bloqueia saida maior que o saldo', function () {
        $this->stock->entrada($this->produto, 10, 5.00, 'NF 1', $this->user);

        $this->stock->saida($this->produto, 15, 'NF-e 100', $this->user);
    })->throws(RuntimeException::class, 'insuficiente');

    it('permite negativo quando o emitente autoriza', function () {
        $this->emitente->forceFill(['permite_saldo_negativo' => true])->save();
        $this->stock->entrada($this->produto, 10, 5.00, 'NF 1', $this->user);

        $this->stock->saida($this->produto, 15, 'NF-e 100', $this->user);

        expect($this->stock->saldo($this->produto)->quantidade)->toBe(-5.0);
    });

    it('nao bloqueia produto que nao controla estoque', function () {
        $servico = produtoDe($this->emitente, ['controla_estoque' => false]);

        $this->stock->saida($servico, 5, 'NF-e 100', $this->user);

        expect($this->stock->saldo($servico)->quantidade)->toBe(0.0);
    });
});

describe('razão imutável', function () {
    it('registra quem movimentou e quando', function () {
        $mov = $this->stock->entrada($this->produto, 10, 5.00, 'NF 1', $this->user);

        expect($mov->user_id)->toBe($this->user->id)
            ->and($mov->documento)->toBe('NF 1')
            ->and($mov->created_at)->not->toBeNull();
    });

    it('recusa alteracao de movimento', function () {
        $mov = $this->stock->entrada($this->produto, 10, 5.00, 'NF 1', $this->user);

        $mov->update(['quantidade' => 999]);
    })->throws(RuntimeException::class, 'imutável');

    it('recusa exclusao de movimento', function () {
        $mov = $this->stock->entrada($this->produto, 10, 5.00, 'NF 1', $this->user);

        $mov->delete();
    })->throws(RuntimeException::class, 'imutável');

    it('corrige por estorno, criando movimento novo', function () {
        $saida = $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);
        $this->stock->saida($this->produto, 30, 'NF-e 100', $this->user);

        $this->stock->estornar($this->produto, 30, 'NF-e 100 cancelada', $this->user);

        expect($this->stock->saldo($this->produto)->quantidade)->toBe(100.0)
            ->and($this->stock->movimentos($this->produto)->count())->toBe(3);
    });

    it('marca o tipo de cada movimento', function () {
        $this->stock->entrada($this->produto, 10, 5.00, 'NF 1', $this->user);
        $this->stock->saida($this->produto, 2, 'NF-e 1', $this->user);
        $this->stock->estornar($this->produto, 2, 'cancelamento', $this->user);

        expect($this->stock->movimentos($this->produto)->pluck('tipo')->map->value->all())
            ->toBe([
                TipoMovimentoEstoque::Entrada->value,
                TipoMovimentoEstoque::Saida->value,
                TipoMovimentoEstoque::Estorno->value,
            ]);
    });
});

describe('isolamento', function () {
    it('mantem saldo separado por emitente', function () {
        $outro = Emitente::factory()->create();
        $produtoOutro = produtoDe($outro);

        $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);
        $this->stock->entrada($produtoOutro, 7, 3.00, 'NF 1', $this->user);

        expect($this->stock->saldo($this->produto)->quantidade)->toBe(100.0)
            ->and($this->stock->saldo($produtoOutro)->quantidade)->toBe(7.0);
    });
});
