<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

it('redireciona visitante para o login', function () {
    $this->get('/design-system')->assertRedirect('/login');
});

it('nega acesso a quem nao for administrador', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Faturamento->value);

    $this->actingAs($user)->get('/design-system')->assertForbidden();
});

it('permite acesso ao administrador', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Administrador->value);

    $this->actingAs($user)->get('/design-system')->assertOk();
});
