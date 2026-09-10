<?php

use App\Enums\Perfil;
use App\Livewire\Tenancy\Marca;
use App\Models\Tenant;
use App\Support\TemaMarca;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(PerfilSeeder::class));

it('so o administrador abre a tela de marca', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))->get('/marca')->assertOk();
});

it('faturamento nao abre a tela de marca', function () {
    $this->actingAs(usuarioMarca(Perfil::Faturamento->value))->get('/marca')->assertForbidden();
});

it('grava as cores da marca', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Marca::class)
        ->set('primaria', '#146B3A')
        ->set('neutra', '#0E0E0E')
        ->call('salvar')
        ->assertHasNoErrors();

    $tema = Tenant::find($user->tenant_id)->tema;
    expect($tema['primaria'])->toBe('#146b3a')->and($tema['neutra'])->toBe('#0e0e0e');
});

it('corrige a cor que nao passa em contraste, em vez de recusar', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Marca::class)
        // 4,39: parece seguro e não passa.
        ->set('primaria', '#1B8A4B')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('avisoContraste', true);

    $tema = Tenant::find($user->tenant_id)->tema;
    expect($tema['primaria'])->not->toBe('#1b8a4b')
        ->and(TemaMarca::contrasteComBranco($tema['primaria']))->toBeGreaterThanOrEqual(4.5);
});

it('nao avisa quando a cor ja passa', function () {
    Livewire::actingAs(usuarioMarca(Perfil::Administrador->value))->test(Marca::class)
        ->set('primaria', '#146B3A')
        ->call('salvar')
        ->assertSet('avisoContraste', false);
});

it('recusa hexadecimal invalido', function () {
    Livewire::actingAs(usuarioMarca(Perfil::Administrador->value))->test(Marca::class)
        ->set('primaria', 'verde')
        ->call('salvar')
        ->assertHasErrors('primaria');
});

it('mostra a previa com a escala gerada', function () {
    Livewire::actingAs(usuarioMarca(Perfil::Administrador->value))->test(Marca::class)
        ->set('primaria', '#146B3A')
        ->assertSee('#146b3a');
});
