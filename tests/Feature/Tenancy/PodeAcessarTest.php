<?php

use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;

it('confirma acesso a emitente de outro tenant, mesmo com outro tenant em foco', function () {
    $tenantA = Tenant::create(['nome' => 'A', 'slug' => 'a-'.uniqid()]);
    $tenantB = Tenant::create(['nome' => 'B', 'slug' => 'b-'.uniqid()]);

    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    $emitenteB = Emitente::factory()->create(['tenant_id' => $tenantB->id]);
    $user->emitentes()->attach($emitenteB);

    // Foco na empresa A: o vinculo com a empresa B continua valendo.
    app(TenantAtual::class)->definir($tenantA);

    expect($user->podeAcessar($emitenteB))->toBeTrue();
});

it('nega acesso a emitente sem vinculo nenhum', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();

    expect($user->podeAcessar($emitente))->toBeFalse();
});
