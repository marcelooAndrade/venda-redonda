<?php

use App\Enums\Perfil;
use App\Livewire\Usuarios\Cadastro;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function livewireComoAdministrador(): array
{
    test()->seed(PerfilSeeder::class);
    $emitente = Emitente::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $admin->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $admin->assignRole(Perfil::Administrador->value);
    test()->actingAs($admin);

    return [$admin, $emitente];
}

it('cria conta nova quando o e-mail nao existe', function () {
    [, $emitente] = livewireComoAdministrador();

    Livewire::test(Cadastro::class)
        ->set('form.email', 'contador@fora.test')
        ->call('verificarEmail')
        ->assertSet('contaExistente', false)
        ->set('form.name', 'Contador Externo')
        ->set('form.senha', 'senha-do-contador')
        ->set("papeis.{$emitente->id}", Perfil::Contador->value)
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = User::where('email', 'contador@fora.test')->firstOrFail();

    expect($criado->name)->toBe('Contador Externo')
        ->and($criado->podeAcessar($emitente))->toBeTrue()
        ->and($criado->ativo)->toBeTrue();
});

it('exige pelo menos um emitente com perfil', function () {
    livewireComoAdministrador();

    Livewire::test(Cadastro::class)
        ->set('form.email', 'ninguem@fora.test')
        ->call('verificarEmail')
        ->set('form.name', 'Ninguém')
        ->set('form.senha', 'senha-qualquer')
        ->call('salvar')
        ->assertHasErrors('papeis');
});
