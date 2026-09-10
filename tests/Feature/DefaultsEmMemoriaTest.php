<?php

use App\Models\Emitente;
use App\Models\NaturezaOperacao;
use App\Models\PerfilFiscal;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Tenant;

/**
 * Guarda contra uma classe de bug que já apareceu quatro vezes no projeto:
 * coluna booleana com default `true` no banco vem `null` num model recém
 * instanciado, e `null` é falsy. Quem lê o atributo antes de reler do banco
 * enxerga `false` e toma a decisão errada.
 *
 * Foi assim que `Emitente::$ambiente` veio nulo, que `Tenant::$ativo` derrubou
 * o escopo de tenant, e que `Produto::$controla_estoque` pulou a checagem de
 * saldo. O remédio é declarar o default também em `$attributes`.
 */
it('reflete em memoria todo default booleano verdadeiro do banco', function (string $model, string $campo) {
    expect((new $model)->{$campo})->toBeTrue("{$model}::\${$campo} precisa vir true num model novo");
})->with([
    [Emitente::class, 'ativo'],
    [Tenant::class, 'ativo'],
    [Pessoa::class, 'ativo'],
    [Pessoa::class, 'consumidor_final'],
    [Produto::class, 'ativo'],
    [Produto::class, 'controla_estoque'],
    [PerfilFiscal::class, 'ativo'],
    [NaturezaOperacao::class, 'ativo'],
    [NaturezaOperacao::class, 'movimenta_estoque'],
    [NaturezaOperacao::class, 'gera_financeiro'],
]);
