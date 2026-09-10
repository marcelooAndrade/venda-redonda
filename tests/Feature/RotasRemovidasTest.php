<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;

/**
 * Guarda contra a volta de rotas que já foram retiradas de propósito.
 *
 * A vitrine do design system era ferramenta interna de construção. Os tokens
 * e os componentes `x-ui.*` continuam, porque o sistema inteiro depende deles:
 * o que saiu foi só a página que os exibia.
 */
beforeEach(fn () => $this->seed(PerfilSeeder::class));

it('nao serve mais a vitrine do design system, nem para administrador', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Administrador->value);

    $this->actingAs($user)->get('/design-system')->assertNotFound();
});
