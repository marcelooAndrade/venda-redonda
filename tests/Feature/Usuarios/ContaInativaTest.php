<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\PermissionRegistrar;

function usuarioComAcesso(): User
{
    test()->seed(PerfilSeeder::class);
    $emitente = Emitente::factory()->create();
    $user = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $user->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $user->assignRole(Perfil::Administrador->value);

    return $user;
}

it('usuario inativo e derrubado na proxima requisicao autenticada', function () {
    $user = usuarioComAcesso();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['ativo' => false])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('usuario ativo continua navegando normalmente', function () {
    $user = usuarioComAcesso();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticated();
});
