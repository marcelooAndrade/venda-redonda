<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * As permissões são escopadas por emitente (teams do spatie). Sem definir o
 * time a cada requisição, o usuário chega sem papel nenhum e leva 403 mesmo
 * sendo administrador. Testes que chamam setPermissionsTeamId() na mão não
 * pegam isso, então estes vão pelo caminho HTTP de verdade.
 */
beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

function usuarioHttp(string $perfil): User
{
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);

    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);
    // Limpa o contexto: a requisição precisa resolver o time sozinha.
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('administrador acessa certificados por requisicao http', function () {
    $this->actingAs(usuarioHttp(Perfil::Administrador->value))
        ->get('/certificados')
        ->assertOk();
});

it('faturamento tambem enxerga a tela de certificados', function () {
    $this->actingAs(usuarioHttp(Perfil::Faturamento->value))
        ->get('/certificados')
        ->assertOk();
});

it('usuario sem emitente vinculado nao acessa', function () {
    $this->actingAs(User::factory()->create())
        ->get('/certificados')
        ->assertNotFound();
});
