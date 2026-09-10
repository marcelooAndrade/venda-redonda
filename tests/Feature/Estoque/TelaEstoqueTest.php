<?php

use App\Enums\Perfil;
use App\Livewire\Estoque\Painel;
use App\Models\Emitente;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

function usuarioEstoque(string $perfil): array
{
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);

    return [$user, $emitente];
}

beforeEach(fn () => $this->seed(PerfilSeeder::class));

it('o perfil de estoque abre a tela', function () {
    [$user] = usuarioEstoque(Perfil::Estoque->value);

    $this->actingAs($user)->get('/estoque')->assertOk();
});

it('consulta enxerga o estoque mas nao inventaria', function () {
    [$user] = usuarioEstoque(Perfil::Consulta->value);

    $this->actingAs($user)->get('/estoque')->assertOk();

    Livewire::actingAs($user)->test(Painel::class)
        ->call('prepararInventario')
        ->assertForbidden();
});

it('grava o inventario movimentando so a diferenca', function () {
    [$user, $emitente] = usuarioEstoque(Perfil::Estoque->value);
    $produto = produtoDe($emitente);
    app(StockService::class)->entrada($produto, 100, 10.00, 'NF 1', $user);

    Livewire::actingAs($user)->test(Painel::class)
        ->call('prepararInventario')
        ->set("contagem.{$produto->id}", '120')
        ->set('justificativa', 'Contagem anual de fechamento')
        ->call('gravarInventario')
        ->assertHasNoErrors();

    expect(app(StockService::class)->saldo($produto)->quantidade)->toBe(120.0);
});

it('exige justificativa no inventario', function () {
    [$user, $emitente] = usuarioEstoque(Perfil::Estoque->value);
    $produto = produtoDe($emitente);
    app(StockService::class)->entrada($produto, 100, 10.00, 'NF 1', $user);

    Livewire::actingAs($user)->test(Painel::class)
        ->call('prepararInventario')
        ->set("contagem.{$produto->id}", '120')
        ->set('justificativa', '')
        ->call('gravarInventario')
        ->assertHasErrors('justificativa');

    expect(app(StockService::class)->saldo($produto)->quantidade)->toBe(100.0);
});

it('mostra o kardex do produto', function () {
    [$user, $emitente] = usuarioEstoque(Perfil::Estoque->value);
    $produto = produtoDe($emitente, ['descricao' => 'Peça de teste kardex']);
    app(StockService::class)->entrada($produto, 100, 10.00, 'NF 4321', $user);

    Livewire::actingAs($user)->test(Painel::class)
        ->call('verKardex', $produto->id)
        ->assertSet('aba', 'kardex')
        ->assertSee('NF 4321')
        ->assertSee('Entrada');
});

it('destaca produto abaixo do minimo', function () {
    [$user, $emitente] = usuarioEstoque(Perfil::Estoque->value);
    $produto = produtoDe($emitente, ['descricao' => 'Peça escassa', 'estoque_minimo' => 50]);
    app(StockService::class)->entrada($produto, 10, 10.00, 'NF 1', $user);

    Livewire::actingAs($user)->test(Painel::class)
        ->assertSee('abaixo do estoque mínimo')
        ->assertSee('Peça escassa');
});
