<?php

use App\Enums\Perfil;
use App\Enums\PlanoTenant;
use App\Livewire\Produto\Empresas;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->travelTo('2026-09-12 10:00:00');

    // O tenant de teste da TestCase nasce "agora". Uma data fixa e antiga
    // deixa os contadores previsíveis, sem depender do dia em que a suíte roda.
    app(TenantAtual::class)->obter()->forceFill(['created_at' => '2026-01-15 09:00:00'])->save();
});

/**
 * Quem administra o produto: usuário do tenant de teste com a marca de dono.
 * Tem emitente e papel de administrador porque um dono também usa o sistema.
 */
function donoDoProduto(): User
{
    $user = usuarioMarca(Perfil::Administrador->value);
    $user->forceFill(['dono_do_produto' => true])->save();

    return $user;
}

/**
 * Uma empresa cadastrada, completa: tenant, emitente e usuário, como o
 * cadastro cria. O tenant vai explícito em cada model para atravessar o
 * escopo global, que só enxerga o tenant do contêiner.
 */
function empresaCadastrada(string $nome, string $cadastradaEm, PlanoTenant $plano = PlanoTenant::Gratuito, ?string $ultimoAcesso = null, array $emitente = [], array $usuario = []): Tenant
{
    $tenant = Tenant::create(['nome' => $nome, 'slug' => str($nome)->slug()->toString(), 'plano' => $plano]);
    $tenant->forceFill(['created_at' => $cadastradaEm])->save();

    Emitente::factory()->create(array_merge(['tenant_id' => $tenant->id], $emitente));

    User::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'ultimo_acesso_em' => $ultimoAcesso,
    ], $usuario));

    return $tenant;
}

it('o dono do produto abre a tela e ve empresa de outro tenant', function () {
    empresaCadastrada('Distribuidora Rio Claro', '2026-09-01 08:00:00');

    $this->actingAs(donoDoProduto())
        ->get('/empresas')
        ->assertOk()
        ->assertSee('Distribuidora Rio Claro');
});

it('administrador comum nao abre a tela, mesmo sendo administrador do proprio tenant', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/empresas')
        ->assertForbidden();
});

it('o item Empresas so aparece na navegacao para o dono', function () {
    $this->actingAs(donoDoProduto())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('empresas'));

    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('empresas'));
});

it('conta o total, as novas no mes, as ativas em 30 dias e por plano', function () {
    // A: antiga e ativa. B: nova, mas quem entrou foi em julho. C: nova, nunca entrou.
    empresaCadastrada('Empresa A', '2026-08-01 08:00:00', PlanoTenant::Gratuito, '2026-09-10 18:00:00');
    empresaCadastrada('Empresa B', '2026-09-05 08:00:00', PlanoTenant::Gratuito, '2026-07-01 18:00:00');
    empresaCadastrada('Empresa C', '2026-09-11 08:00:00', PlanoTenant::Avancado);

    // Mais o tenant de teste, de janeiro, avançado, sem acesso registrado.
    Livewire::actingAs(donoDoProduto())
        ->test(Empresas::class)
        ->assertSet('totais.total', 4)
        ->assertSet('totais.novasNoMes', 2)
        ->assertSet('totais.ativasEm30Dias', 1)
        ->assertSet('totais.porPlano.gratuito', 2)
        ->assertSet('totais.porPlano.avancado', 2);
});

it('mostra cnpj, contato, plano, cadastro e ultimo acesso de cada empresa', function () {
    empresaCadastrada(
        'Metalurgica Leme',
        '2026-09-03 08:00:00',
        PlanoTenant::Avancado,
        '2026-09-11 17:45:00',
        ['cnpj' => '11222333000181', 'telefone' => '1935551234'],
        ['name' => 'Fulana de Tal', 'email' => 'fulana@leme.com.br'],
    );

    $this->actingAs(donoDoProduto())
        ->get('/empresas')
        ->assertSee('Metalurgica Leme')
        ->assertSee('11.222.333/0001-81')
        ->assertSee('Fulana de Tal')
        ->assertSee('fulana@leme.com.br')
        ->assertSee('1935551234')
        ->assertSee('Avançado')
        ->assertSee('03/09/2026')
        ->assertSee('11/09/2026 17:45');
});

it('lista a mais recente primeiro', function () {
    empresaCadastrada('Empresa Antiga', '2026-03-01 08:00:00');
    empresaCadastrada('Empresa Nova', '2026-09-10 08:00:00');

    $this->actingAs(donoDoProduto())
        ->get('/empresas')
        ->assertSeeInOrder(['Empresa Nova', 'Empresa Antiga']);
});

it('o ultimo acesso da empresa e o mais recente entre os usuarios dela', function () {
    $tenant = empresaCadastrada('Duas Pessoas', '2026-08-20 08:00:00', PlanoTenant::Gratuito, '2026-09-01 08:00:00');
    User::factory()->create(['tenant_id' => $tenant->id, 'ultimo_acesso_em' => '2026-09-09 14:30:00']);

    $this->actingAs(donoDoProduto())
        ->get('/empresas')
        ->assertSee('09/09/2026 14:30')
        ->assertDontSee('01/09/2026 08:00');
});

it('empresa que nunca entrou aparece sem ultimo acesso, e nao some da lista', function () {
    empresaCadastrada('Nunca Entrou', '2026-09-08 08:00:00');

    $this->actingAs(donoDoProduto())
        ->get('/empresas')
        ->assertSee('Nunca Entrou')
        ->assertSee('nunca entrou');
});

it('o comando marca o usuario como dono do produto', function () {
    $user = User::factory()->create(['email' => 'dono@vendaredonda.com.br']);

    expect($user->dono_do_produto)->toBeFalse();

    $this->artisan('produto:definir-dono', ['email' => 'dono@vendaredonda.com.br'])
        ->assertSuccessful();

    expect($user->fresh()->dono_do_produto)->toBeTrue();
});

it('o comando recusa email que nao existe', function () {
    $this->artisan('produto:definir-dono', ['email' => 'ninguem@exemplo.com.br'])
        ->assertFailed();
});

it('a marca de dono nao entra por preenchimento em massa', function () {
    $user = User::create([
        'name' => 'Esperto',
        'email' => 'esperto@exemplo.com.br',
        'password' => 'segredo-forte-123',
        'dono_do_produto' => true,
    ]);

    expect($user->fresh()->dono_do_produto)->toBeFalse();
});
