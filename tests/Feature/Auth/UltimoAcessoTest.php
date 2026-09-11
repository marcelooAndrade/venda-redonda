<?php

use App\Models\User;

it('grava o ultimo acesso quando a pessoa entra', function () {
    $this->travelTo('2026-09-11 10:00:00');

    $user = User::factory()->create(['password' => 'senha-muito-longa-123']);

    expect($user->ultimo_acesso_em)->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'senha-muito-longa-123']);

    expect($user->fresh()->ultimo_acesso_em->toDateTimeString())->toBe('2026-09-11 10:00:00');
});
