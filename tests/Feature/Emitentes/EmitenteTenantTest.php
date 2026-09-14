<?php

use App\Models\Emitente;
use App\Models\Tenant;

it('emitente aponta para o proprio tenant', function () {
    $tenant = Tenant::create(['nome' => 'RCM', 'slug' => 'rcm-'.uniqid()]);
    $emitente = Emitente::factory()->create(['tenant_id' => $tenant->id]);

    expect($emitente->tenant)->toBeInstanceOf(Tenant::class)
        ->and($emitente->tenant->id)->toBe($tenant->id);
});
