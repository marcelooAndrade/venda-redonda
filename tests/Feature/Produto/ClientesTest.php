<?php

use App\Enums\Perfil;
use App\Livewire\Produto\Clientes;
use App\Models\Emitente;
use App\Models\Pessoa;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

function donoDoProdutoClientes(): User
{
    $user = usuarioMarca(Perfil::Administrador->value);
    $user->forceFill(['dono_do_produto' => true])->save();

    return $user;
}

/**
 * Tenant administrativo completo: tenant, emitente e destinatários, como a
 * tela Clientes espera encontrar. O tenant vai explícito em cada model para
 * atravessar o escopo global, que só enxerga o tenant do contêiner.
 */
function tenantAdministrativoDeTeste(): Tenant
{
    $tenant = Tenant::create(['nome' => 'Marcelo Andrade', 'slug' => 'marcelo-andrade']);
    config(['produto.tenant_administrativo' => $tenant->slug]);

    return $tenant;
}

it('o dono do produto abre a tela', function () {
    tenantAdministrativoDeTeste();

    $this->actingAs(donoDoProdutoClientes())->get('/clientes')->assertOk();
});

it('administrador comum nao abre a tela', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))->get('/clientes')->assertForbidden();
});

it('o item Clientes so aparece na navegacao para o dono', function () {
    $this->actingAs(donoDoProdutoClientes())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('clientes'));

    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('clientes'));
});

it('avisa que falta configurar quando o slug esta vazio', function () {
    config(['produto.tenant_administrativo' => null]);

    Livewire::actingAs(donoDoProdutoClientes())
        ->test(Clientes::class)
        ->assertSee('Falta configurar o tenant administrativo');
});

it('mostra os destinatarios marcados como cliente do tenant administrativo', function () {
    $tenant = tenantAdministrativoDeTeste();
    $emitente = Emitente::factory()->create(['tenant_id' => $tenant->id]);

    destinatarioCompleto($emitente, ['razao_social' => 'Cliente Um', 'documento' => '11444777000161']);
    destinatarioCompleto($emitente, ['razao_social' => 'Fornecedor Dois', 'documento' => '11222333000181', 'e_cliente' => false, 'e_fornecedor' => true]);

    Livewire::actingAs(donoDoProdutoClientes())
        ->test(Clientes::class)
        ->assertSee('Cliente Um')
        ->assertDontSee('Fornecedor Dois');
});

it('nao mostra destinatario de outro tenant', function () {
    tenantAdministrativoDeTeste();

    $outroTenant = Tenant::create(['nome' => 'Outra Empresa', 'slug' => 'outra-empresa']);
    $outroEmitente = Emitente::factory()->create(['tenant_id' => $outroTenant->id]);
    destinatarioCompleto($outroEmitente, ['razao_social' => 'Cliente De Outra Empresa']);

    Livewire::actingAs(donoDoProdutoClientes())
        ->test(Clientes::class)
        ->assertDontSee('Cliente De Outra Empresa');

    expect(Pessoa::withoutGlobalScope('tenant')->count())->toBe(1);
});
