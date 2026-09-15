<?php

use App\Enums\ModuloApi;
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
    $cliente->createToken('token velho');

    expect($cliente->tokens()->count())->toBe(1);

    $componente = Livewire::actingAs(donoDoProdutoNodo())
        ->test(NodoClientes::class)
        ->call('reemitirToken', $cliente->id)
        ->assertSet('clienteDoTokenRevelado', $cliente->id);

    expect($componente->get('tokenRevelado'))->toBeString()->not->toBeEmpty()
        ->and($cliente->tokens()->count())->toBe(1)
        ->and($cliente->tokens()->first()->name)->toBe('Marcelo');
});

it('cadastra cliente novo com os modulos escolhidos, e mostra o token uma vez', function () {
    $componente = Livewire::actingAs(donoDoProdutoNodo())
        ->test(NodoClientes::class)
        ->set('novoNome', 'Transm')
        ->set('novoEmail', 'contato@transm.com.br')
        ->set('novosModulos', ['whatsapp'])
        ->call('criarCliente');

    $cliente = ApiCliente::firstWhere('email', 'contato@transm.com.br');

    expect($cliente)->not->toBeNull()
        ->and($cliente->modulos)->toBe(['whatsapp'])
        ->and($cliente->temModulo(ModuloApi::Whatsapp))->toBeTrue()
        ->and($cliente->tokens()->count())->toBe(1);

    $componente->assertSet('clienteDoTokenRevelado', $cliente->id);
    expect($componente->get('tokenRevelado'))->toBeString()->not->toBeEmpty();
});

it('cadastro novo recusa e-mail duplicado', function () {
    ApiCliente::create(['nome' => 'Ja existe', 'email' => 'contato@transm.com.br']);

    Livewire::actingAs(donoDoProdutoNodo())
        ->test(NodoClientes::class)
        ->set('novoNome', 'Transm')
        ->set('novoEmail', 'contato@transm.com.br')
        ->call('criarCliente')
        ->assertHasErrors('novoEmail');

    expect(ApiCliente::where('email', 'contato@transm.com.br')->count())->toBe(1);
});

it('cliente novo sem modulo nenhum marcado nasce sem acesso a nada', function () {
    Livewire::actingAs(donoDoProdutoNodo())
        ->test(NodoClientes::class)
        ->set('novoNome', 'Sem Modulo')
        ->set('novoEmail', 'semmodulo@exemplo.com.br')
        ->call('criarCliente');

    $cliente = ApiCliente::firstWhere('email', 'semmodulo@exemplo.com.br');

    expect($cliente->modulos)->toBe([]);
});

it('edita os modulos de um cliente existente', function () {
    $cliente = ApiCliente::create(['nome' => 'Transm', 'email' => 'contato@transm.com.br', 'modulos' => []]);

    Livewire::actingAs(donoDoProdutoNodo())
        ->test(NodoClientes::class)
        ->call('iniciarEdicaoModulos', $cliente->id)
        ->set('modulosEmEdicao', ['whatsapp'])
        ->call('salvarModulos', $cliente->id);

    expect($cliente->fresh()->modulos)->toBe(['whatsapp']);
});
