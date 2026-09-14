<?php

use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;

it('troca para um emitente que o login alcanca', function () {
    // Precisa do domínio do produto: no host padrão dos testes de Feature
    // (`localhost`), o `TestCase` já semeia um tenant "teste" com esse
    // domínio, e o host sempre venceria a troca. No domínio do produto
    // nenhum tenant é resolvido, que é justamente onde a troca livre vale.
    config(['produto.dominio' => 'vendaredonda.com.br']);

    $tenantA = Tenant::create(['nome' => 'A', 'slug' => 'a-'.uniqid()]);
    $tenantB = Tenant::create(['nome' => 'B', 'slug' => 'b-'.uniqid()]);
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    $emA = Emitente::factory()->create(['tenant_id' => $tenantA->id]);
    $emB = Emitente::factory()->create(['tenant_id' => $tenantB->id]);
    $user->emitentes()->attach([$emA->id, $emB->id]);

    $this->actingAs($user)
        ->post('http://vendaredonda.com.br'.route('emitente.escolher', absolute: false), ['emitente_id' => $emB->id])
        ->assertRedirect(route('dashboard'));

    $this->get('http://vendaredonda.com.br'.route('dashboard', absolute: false));

    expect(app(TenantAtual::class)->id())->toBe($tenantB->id);
});

it('recusa emitente sem vinculo', function () {
    $user = User::factory()->create();
    $alheio = Emitente::factory()->create();

    $this->actingAs($user)->post(route('emitente.escolher'), ['emitente_id' => $alheio->id])
        ->assertForbidden();
});
