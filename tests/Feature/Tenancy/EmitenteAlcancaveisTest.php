<?php

use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmitenteAtual;
use App\Support\TenantAtual;

it('lista vazia sem usuario autenticado', function () {
    expect(app(EmitenteAtual::class)->alcancaveis())->toBeEmpty();
});

it('sem tenant fixado, lista emitentes de qualquer empresa do login', function () {
    $tenantA = Tenant::create(['nome' => 'A', 'slug' => 'a-'.uniqid()]);
    $tenantB = Tenant::create(['nome' => 'B', 'slug' => 'b-'.uniqid()]);
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    $emA = Emitente::factory()->create(['tenant_id' => $tenantA->id, 'razao_social' => 'Empresa A']);
    $emB = Emitente::factory()->create(['tenant_id' => $tenantB->id, 'razao_social' => 'Empresa B']);
    $user->emitentes()->attach([$emA->id, $emB->id]);

    app(TenantAtual::class)->limpar();
    $this->actingAs($user);

    expect(app(EmitenteAtual::class)->alcancaveis()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$emA->id, $emB->id])->sort()->values()->all());
});

it('com tenant fixado, lista so os emitentes daquele tenant', function () {
    $tenantA = Tenant::create(['nome' => 'A', 'slug' => 'a-'.uniqid()]);
    $tenantB = Tenant::create(['nome' => 'B', 'slug' => 'b-'.uniqid()]);
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    $emA = Emitente::factory()->create(['tenant_id' => $tenantA->id]);
    $emB = Emitente::factory()->create(['tenant_id' => $tenantB->id]);
    $user->emitentes()->attach([$emA->id, $emB->id]);

    app(TenantAtual::class)->definir($tenantA);
    $this->actingAs($user);

    expect(app(EmitenteAtual::class)->alcancaveis()->pluck('id')->all())->toBe([$emA->id]);
});

it('tenant derivado do emitente nao fixa a busca do proprio seletor', function () {
    // Reproduz a sequência real de uma requisição no domínio comum:
    // `DefinirEmitenteDoContexto` resolve o emitente (sem tenant fixado, then
    // atravessa) e, a partir dele, define o tenant da sessão. O seletor no
    // topo do layout chama `alcancaveis()` *depois* disso, na mesma
    // requisição, e precisa continuar enxergando as duas empresas.
    $tenantA = Tenant::create(['nome' => 'A', 'slug' => 'a-'.uniqid()]);
    $tenantB = Tenant::create(['nome' => 'B', 'slug' => 'b-'.uniqid()]);
    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    $emA = Emitente::factory()->create(['tenant_id' => $tenantA->id]);
    $emB = Emitente::factory()->create(['tenant_id' => $tenantB->id]);
    $user->emitentes()->attach([$emA->id, $emB->id]);

    app(TenantAtual::class)->limpar();
    $this->actingAs($user);

    $emitenteAtual = app(EmitenteAtual::class);
    $resolvido = $emitenteAtual->resolver();
    app(TenantAtual::class)->definirDoEmitente($resolvido->tenant);

    expect($emitenteAtual->alcancaveis()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$emA->id, $emB->id])->sort()->values()->all());
});
