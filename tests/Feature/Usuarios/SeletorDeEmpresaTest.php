<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\PermissionRegistrar;

function entrarComEmitentes(array $emitentes): User
{
    test()->seed(PerfilSeeder::class);

    $primeiro = $emitentes[0];
    $user = User::factory()->create(['tenant_id' => $primeiro->tenant_id]);

    foreach ($emitentes as $emitente) {
        $user->emitentes()->attach($emitente);
        app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
        $user->assignRole(Perfil::Administrador->value);
    }

    test()->actingAs($user);

    return $user;
}

// No domínio do produto: no host padrão dos testes o `TestCase` já fixa um
// tenant "teste", e a busca ficaria presa a ele. Ver a memória
// host-de-teste-resolve-tenant.
function irParaODashboardNoDominioComum(): string
{
    config(['produto.dominio' => 'vendaredonda.com.br']);

    return test()->get('http://vendaredonda.com.br'.route('dashboard', absolute: false))->getContent();
}

it('com um emitente so, nao mostra o seletor', function () {
    $emitente = Emitente::factory()->create();
    entrarComEmitentes([$emitente]);

    // `route('emitente.escolher')` compila para o caminho da rota, com
    // barra, não para o nome da rota com ponto: é a URL de ação do
    // formulário do seletor que precisa sumir, não o nome interno da rota.
    expect(irParaODashboardNoDominioComum())->not->toContain('emitente/escolher');
});

it('com mais de um emitente, o seletor lista cada empresa', function () {
    // `nome_fantasia` é preenchido pela factory com um nome aleatório, e o
    // seletor prioriza esse campo sobre a razão social: sem fixar os dois,
    // "Empresa A" nunca apareceria no texto do botão.
    $tenantB = Tenant::create(['nome' => 'Empresa B', 'slug' => 'b-'.uniqid()]);
    $emA = Emitente::factory()->create(['razao_social' => 'Empresa A', 'nome_fantasia' => 'Empresa A']);
    $emB = Emitente::factory()->create([
        'tenant_id' => $tenantB->id, 'razao_social' => 'Empresa B Emitente', 'nome_fantasia' => 'Empresa B Emitente',
    ]);
    entrarComEmitentes([$emA, $emB]);

    $html = irParaODashboardNoDominioComum();

    expect($html)->toContain('emitente/escolher')
        ->and($html)->toContain('Empresa A')
        ->and($html)->toContain('Empresa B Emitente');
});
