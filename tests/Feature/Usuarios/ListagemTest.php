<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\PermissionRegistrar;

function administradorDoEmitente(?Emitente $emitente = null): array
{
    test()->seed(PerfilSeeder::class);

    $emitente ??= Emitente::factory()->create();
    $user = User::factory()->create(['tenant_id' => $emitente->tenant_id, 'name' => 'Administradora']);
    $user->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $user->assignRole(Perfil::Administrador->value);

    return [$user, $emitente];
}

it('so administrador abre a tela', function () {
    $emitente = Emitente::factory()->create();
    test()->seed(PerfilSeeder::class);
    $consulta = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $consulta->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $consulta->assignRole(Perfil::Consulta->value);

    $this->actingAs($consulta)->get(route('usuarios'))->assertForbidden();
});

it('administrador abre a tela e ve a si mesmo na lista', function () {
    [$admin, $emitente] = administradorDoEmitente();

    $this->actingAs($admin)->get(route('usuarios'))
        ->assertOk()
        ->assertSee('Administradora');
});

it('nao lista usuario de outra empresa', function () {
    [$admin] = administradorDoEmitente();

    $outroTenant = Tenant::create(['nome' => 'Outra Empresa', 'slug' => 'outra-'.uniqid()]);
    $outroEmitente = Emitente::factory()->create(['tenant_id' => $outroTenant->id]);
    administradorDoEmitente($outroEmitente);
    $deOutraEmpresa = User::factory()->create(['tenant_id' => $outroTenant->id, 'name' => 'Fulano de Outra Empresa']);
    $deOutraEmpresa->emitentes()->attach($outroEmitente);

    $this->actingAs($admin)->get(route('usuarios'))
        ->assertOk()
        ->assertDontSee('Fulano de Outra Empresa');
});
