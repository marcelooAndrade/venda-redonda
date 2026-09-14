<?php

use App\Enums\Perfil;
use App\Livewire\Usuarios\Cadastro;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

it('administrador nao consegue remover o proprio acesso de administrador nesta empresa', function () {
    test()->seed(PerfilSeeder::class);
    $emitente = Emitente::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $admin->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $admin->assignRole(Perfil::Administrador->value);
    test()->actingAs($admin);

    Livewire::test(Cadastro::class)
        ->call('editar', $admin->id)
        ->set("papeis.{$emitente->id}", Perfil::Consulta->value)
        ->call('salvar')
        ->assertHasErrors('form.email');

    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    expect($admin->fresh()->hasRole(Perfil::Administrador->value))->toBeTrue();
});

it('administrador nao consegue inativar a propria conta', function () {
    test()->seed(PerfilSeeder::class);
    $emitente = Emitente::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $admin->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $admin->assignRole(Perfil::Administrador->value);
    test()->actingAs($admin);

    Livewire::test(Cadastro::class)->call('inativar', $admin->id);

    expect($admin->fresh()->ativo)->toBeTrue();
});
