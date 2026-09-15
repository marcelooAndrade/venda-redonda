<?php

use App\Enums\Perfil;
use App\Livewire\Produto\NodoClientes;
use App\Models\ApiCliente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

function donoDoProdutoNodo(): User
{
    $user = usuarioMarca(Perfil::Administrador->value);
    $user->forceFill(['dono_do_produto' => true])->save();

    return $user;
}

it('o dono do produto abre a tela e ve cliente cadastrado', function () {
    ApiCliente::create(['nome' => 'Distribuidora Rio Claro', 'email' => 'contato@rioclaro.com.br']);

    $this->actingAs(donoDoProdutoNodo())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Distribuidora Rio Claro')
        ->assertSee('contato@rioclaro.com.br');
});

it('administrador comum nao abre a tela', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/admin')
        ->assertForbidden();
});

it('o item Clientes Nodo so aparece na navegacao para o dono', function () {
    $this->actingAs(donoDoProdutoNodo())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('admin'));

    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('admin'));
});

it('mostra sem instancia para quem ainda nao criou uma', function () {
    ApiCliente::create(['nome' => 'Sem Instancia', 'email' => 'sem@exemplo.com.br']);

    $this->actingAs(donoDoProdutoNodo())
        ->get('/admin')
        ->assertSee('Sem instância');
});

it('mostra o status da instancia de quem ja tem uma', function () {
    $cliente = ApiCliente::create(['nome' => 'Com Instancia', 'email' => 'com@exemplo.com.br']);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    $this->actingAs(donoDoProdutoNodo())
        ->get('/admin')
        ->assertSee('Conectado');
});

it('reemite o token, revogando o antigo e mostrando o novo uma vez', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    $cliente->createToken('token velho', ['whatsapp']);

    expect($cliente->tokens()->count())->toBe(1);

    $componente = Livewire::actingAs(donoDoProdutoNodo())
        ->test(NodoClientes::class)
        ->call('reemitirToken', $cliente->id)
        ->assertSet('clienteDoTokenReemitido', $cliente->id);

    expect($componente->get('tokenReemitido'))->toBeString()->not->toBeEmpty()
        ->and($cliente->tokens()->count())->toBe(1)
        ->and($cliente->tokens()->first()->name)->toBe('Marcelo');
});
