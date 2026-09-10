<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

it('cria os cinco perfis do sistema', function () {
    expect(Role::query()->pluck('name')->all())
        ->toEqualCanonicalizing(['Administrador', 'Faturamento', 'Estoque', 'Contador', 'Consulta']);
});

it('permite perfis diferentes para o mesmo usuario em emitentes diferentes', function () {
    $user = User::factory()->create();
    $matriz = Emitente::factory()->create();
    $filial = Emitente::factory()->create();

    setPermissionsTeamId($matriz->id);
    $user->assignRole(Perfil::Faturamento->value);

    setPermissionsTeamId($filial->id);
    $user->assignRole(Perfil::Consulta->value);

    setPermissionsTeamId($matriz->id);
    expect($user->fresh()->hasRole(Perfil::Faturamento->value))->toBeTrue();

    setPermissionsTeamId($filial->id);
    expect($user->fresh()->hasRole(Perfil::Faturamento->value))->toBeFalse()
        ->and($user->fresh()->hasRole(Perfil::Consulta->value))->toBeTrue();
});

it('nega emissao de nota ao perfil de consulta', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Consulta->value);

    expect($user->can('nota.emitir'))->toBeFalse();
});

it('permite emissao de nota ao perfil de faturamento', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Faturamento->value);

    expect($user->can('nota.emitir'))->toBeTrue();
});

it('nega virada para producao a quem nao for administrador', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Faturamento->value);

    expect($user->can('emitente.ativar-producao'))->toBeFalse();
});
