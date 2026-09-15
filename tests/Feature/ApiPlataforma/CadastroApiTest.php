<?php

use App\Models\ApiCliente;

it('mostra o formulario de cadastro no dominio da api', function () {
    $this->get('http://api.vendaredonda.test/cadastro')->assertOk();
});

it('cria o cliente e mostra o token uma vez', function () {
    $resposta = $this->post('http://api.vendaredonda.test/cadastro', [
        'nome' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
    ]);

    $resposta->assertOk();

    $cliente = ApiCliente::firstWhere('email', 'marcelo@exemplo.com.br');

    expect($cliente)->not->toBeNull()
        ->and($cliente->ativo)->toBeTrue()
        ->and($cliente->tokens()->count())->toBe(1)
        ->and($cliente->tokens()->first()->abilities)->toBe(['whatsapp']);
});

it('recusa e-mail duplicado', function () {
    ApiCliente::create(['nome' => 'Já existe', 'email' => 'marcelo@exemplo.com.br']);

    $this->post('http://api.vendaredonda.test/cadastro', [
        'nome' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
    ])->assertOk()->assertSee('já está em uso', false);

    expect(ApiCliente::count())->toBe(1);
});

it('exige nome e email', function () {
    $this->post('http://api.vendaredonda.test/cadastro', [])
        ->assertOk()
        ->assertSee('obrigatório', false);

    expect(ApiCliente::count())->toBe(0);
});

it('nao existe fora do dominio da api', function () {
    $this->get('http://vendaredonda.com.br/cadastro')->assertNotFound();
});
