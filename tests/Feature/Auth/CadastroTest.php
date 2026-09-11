<?php

use App\Enums\Perfil;
use App\Enums\PlanoTenant;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;
use Database\Seeders\PerfilSeeder;

/**
 * Cadastro é porta do produto, e só dela.
 *
 * Em domínio de cliente ele não pode existir: até 11/09/2026 a rota criava
 * conta dentro do tenant do cliente, o que foi medido e provado.
 */
beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
});

function dadosDeCadastro(array $extra = []): array
{
    return array_merge([
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123',
        'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
    ], $extra);
}

it('nao existe em dominio de cliente', function () {
    Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => PlanoTenant::Avancado, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);

    $this->get('http://app.rcmdobrasil.com.br/register')->assertNotFound();

    $this->post('http://app.rcmdobrasil.com.br/register', dadosDeCadastro())->assertNotFound();

    expect(User::withoutGlobalScopes()->count())->toBe(0);
});

it('existe no dominio do produto', function () {
    $this->get('http://vendaredonda.com.br/register')->assertOk();
});

it('cria empresa, emitente, usuario e papel de uma vez', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro());

    $tenant = Tenant::firstWhere('nome', 'DISTRIBUIDORA RIO CLARO LTDA');

    expect($tenant)->not->toBeNull()
        ->and($tenant->plano)->toBe(PlanoTenant::Gratuito)
        ->and($tenant->dominio)->toBeNull();

    app(TenantAtual::class)->definir($tenant);

    $emitente = Emitente::firstWhere('cnpj', '11222333000181');
    $user = User::firstWhere('email', 'marcelo@exemplo.com.br');

    expect($emitente)->not->toBeNull()
        ->and($emitente->tenant_id)->toBe($tenant->id)
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->emitentes()->pluck('emitentes.id')->all())->toBe([$emitente->id]);

    setPermissionsTeamId($emitente->id);

    expect($user->fresh()->hasRole(Perfil::Administrador->value))->toBeTrue();
});

it('entra direto depois de se cadastrar e cai no painel', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('recusa cnpj invalido sem criar nada', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro(['cnpj' => '11222333000100']))
        ->assertSessionHasErrors('cnpj');

    expect(Tenant::count())->toBe(0)
        ->and(User::withoutGlobalScopes()->count())->toBe(0);
});

it('recusa cnpj ja cadastrado em outra empresa', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro());
    $this->post('http://vendaredonda.com.br/logout');

    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro([
        'email' => 'outro@exemplo.com.br',
    ]))->assertSessionHasErrors('cnpj');

    expect(Tenant::count())->toBe(1);
});

it('gera slug do nome da empresa, sem colidir com reservado', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro([
        'razao_social' => 'APP',
    ]));

    $tenant = Tenant::firstWhere('nome', 'APP');

    // `app` é reservado: capturaria o host do próprio produto.
    expect($tenant->slug)->not->toBe('app');
});
