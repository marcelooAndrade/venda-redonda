<?php

use App\Models\Emitente;
use App\Models\User;

it('lista apenas os emitentes vinculados ao usuario', function () {
    $user = User::factory()->create();
    $vinculado = Emitente::factory()->create();
    Emitente::factory()->create(); // outro emitente, sem vínculo

    $user->emitentes()->attach($vinculado);

    expect($user->emitentes()->pluck('emitentes.id')->all())->toBe([$vinculado->id]);
});

it('confirma acesso a emitente vinculado', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);

    expect($user->podeAcessar($emitente))->toBeTrue();
});

it('nega acesso a emitente nao vinculado', function () {
    $user = User::factory()->create();
    $alheio = Emitente::factory()->create();

    expect($user->podeAcessar($alheio))->toBeFalse();
});
