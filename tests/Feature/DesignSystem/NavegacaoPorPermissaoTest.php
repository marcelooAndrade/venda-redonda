<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;

beforeEach(fn () => $this->seed(PerfilSeeder::class));

function usuarioNav(string $perfil): User
{
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);
    setPermissionsTeamId(null);

    return $user;
}

it('o contador nao ve certificado na navegacao', function () {
    $this->actingAs(usuarioNav(Perfil::Contador->value))
        ->get('/regras-fiscais')
        ->assertOk()
        ->assertDontSee('Certificado');
});

it('o contador ve regras fiscais na navegacao', function () {
    $this->actingAs(usuarioNav(Perfil::Contador->value))
        ->get('/regras-fiscais')
        ->assertSee('Regras fiscais');
});

it('faturamento nao ve regras fiscais na navegacao', function () {
    $this->actingAs(usuarioNav(Perfil::Faturamento->value))
        ->get('/destinatarios')
        ->assertOk()
        ->assertDontSee('Regras fiscais');
});

it('o administrador ve tudo', function () {
    $this->actingAs(usuarioNav(Perfil::Administrador->value))
        ->get('/certificados')
        ->assertOk()
        ->assertSee('Certificado')
        ->assertSee('Regras fiscais')
        ->assertSee('Design System');
});

it('consulta nao ve design system', function () {
    $this->actingAs(usuarioNav(Perfil::Consulta->value))
        ->get('/destinatarios')
        ->assertOk()
        ->assertDontSee('Design System');
});
