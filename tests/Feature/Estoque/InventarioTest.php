<?php

use App\Enums\Fiscal\TipoMovimentoEstoque;
use App\Models\Emitente;
use App\Models\User;
use App\Services\Stock\InventarioService;
use App\Services\Stock\StockService;

beforeEach(function () {
    $this->emitente = Emitente::factory()->create();
    $this->produto = produtoDe($this->emitente);
    $this->user = User::factory()->create();
    $this->stock = app(StockService::class);
    $this->inventario = app(InventarioService::class);

    $this->stock->entrada($this->produto, 100, 10.00, 'NF 1', $this->user);
});

it('gera entrada da diferenca quando a contagem e maior', function () {
    $this->inventario->contar($this->produto, 120, 'Contagem anual', $this->user);

    $saldo = $this->stock->saldo($this->produto);
    $ultimo = $this->stock->movimentos($this->produto)->get()->last();

    expect($saldo->quantidade)->toBe(120.0)
        ->and($ultimo->tipo)->toBe(TipoMovimentoEstoque::Inventario)
        // Movimenta a diferença, não o total contado.
        ->and((float) $ultimo->quantidade)->toBe(20.0);
});

it('gera saida da diferenca quando a contagem e menor', function () {
    $this->inventario->contar($this->produto, 85, 'Quebra identificada', $this->user);

    expect($this->stock->saldo($this->produto)->quantidade)->toBe(85.0)
        ->and((float) $this->stock->movimentos($this->produto)->get()->last()->quantidade)->toBe(15.0);
});

it('nao movimenta quando a contagem confere', function () {
    $antes = $this->stock->movimentos($this->produto)->count();

    $movimento = $this->inventario->contar($this->produto, 100, 'Contagem anual', $this->user);

    expect($movimento)->toBeNull()
        ->and($this->stock->movimentos($this->produto)->count())->toBe($antes);
});

it('exige justificativa', function () {
    $this->inventario->contar($this->produto, 120, '', $this->user);
})->throws(RuntimeException::class, 'justificativa');

it('guarda a justificativa no movimento', function () {
    $this->inventario->contar($this->produto, 120, 'Sobra encontrada no pátio', $this->user);

    expect($this->stock->movimentos($this->produto)->get()->last()->justificativa)
        ->toBe('Sobra encontrada no pátio');
});

it('nao altera o custo medio', function () {
    $this->inventario->contar($this->produto, 120, 'Contagem anual', $this->user);

    expect($this->stock->saldo($this->produto)->custo_medio)->toBe(10.0);
});

it('conta em lote e devolve o que mudou', function () {
    $outro = produtoDe($this->emitente);
    $this->stock->entrada($outro, 50, 4.00, 'NF 2', $this->user);
    $terceiro = produtoDe($this->emitente);
    $this->stock->entrada($terceiro, 30, 7.00, 'NF 3', $this->user);

    $resultado = $this->inventario->contarLote([
        $this->produto->id => 120,
        $outro->id => 50,      // confere, não movimenta
        $terceiro->id => 25,
    ], 'Inventário de fechamento', $this->user);

    expect($resultado['ajustados'])->toBe(2)
        ->and($resultado['conferidos'])->toBe(1)
        ->and($this->stock->saldo($this->produto)->quantidade)->toBe(120.0)
        ->and($this->stock->saldo($terceiro)->quantidade)->toBe(25.0);
});
