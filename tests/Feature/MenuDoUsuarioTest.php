<?php

/**
 * Até 13/09 o avatar no topo do layout fiscal era só decoração: sem link,
 * sem menu, nenhum jeito de sair do sistema de dentro dele. Quem precisasse
 * trocar de conta, ou ficasse com a sessão presa depois de um erro, tinha
 * que apagar o cookie na mão.
 *
 * O menu novo usa `<details>`, não Alpine: o layout inteiro é hand-rolled,
 * sem `x-data` em lugar nenhum, então HTML nativo evita introduzir a
 * primeira dependência de JavaScript ali dentro. Mesma solução do FAQ da
 * apresentação.
 */

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);

    $emitente = Emitente::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $this->user->emitentes()->attach($emitente);

    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $this->user->assignRole(Perfil::Administrador->value);

    session(['emitente_id' => $emitente->getKey()]);
});

it('o layout fiscal tem um formulario de logout que aponta para a rota certa', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('action="'.route('logout').'"')
        ->and($html)->toContain('Sair')
        ->and($html)->toContain('href="'.route('profile.edit').'"');
});

it('o botao de sair desloga de verdade a partir dessa mesma tela', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

    $this->post(route('logout'))->assertRedirect();

    $this->assertGuest();
});
