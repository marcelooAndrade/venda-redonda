<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Integrations\AdminPessoalGateway;
use App\Services\Integrations\MontadorDeLeads;
use App\Support\TenantAtual;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo('2026-09-11 10:00:00');

    config([
        'integracao.admin_pessoal.url' => 'https://exemplo.test',
        'integracao.admin_pessoal.token' => 'segredo-de-teste',
    ]);
});

/**
 * O tenant vem do contêiner, e não do emitente: `Emitente` não tem relação
 * `tenant()`, e a `TestCase` já cria e resolve um tenant que a fábrica de
 * emitente reaproveita.
 */
it('monta o lead a partir do tenant', function () {
    $emitente = emitenteCompleto();
    $tenant = app(TenantAtual::class)->obter();
    $tenant->update(['nome' => 'DISTRIBUIDORA RIO CLARO LTDA']);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'ultimo_acesso_em' => '2026-09-10 18:30:00',
    ]);

    $lead = app(MontadorDeLeads::class)->paraTenant($tenant->fresh());

    expect($lead['tenant_id'])->toBe($tenant->id)
        ->and($lead['cnpj'])->toBe('11222333000181')
        ->and($lead['empresa'])->toBe('DISTRIBUIDORA RIO CLARO LTDA')
        ->and($lead['nome'])->toBe('Marcelo Andrade')
        ->and($lead['email'])->toBe('marcelo@exemplo.com.br')
        ->and($lead['telefone'])->toBe('1930960072')
        // O "Tenant de teste" da TestCase tem domínio (localhost), e a regra
        // do model exige plano avançado para quem tem domínio próprio: não dá
        // para ser "gratuito" aqui sem violar essa regra.
        ->and($lead['plano'])->toBe('avancado')
        ->and($lead['ultimo_acesso_em'])->toStartWith('2026-09-10T18:30:00');
});

/** O último acesso do tenant é o mais recente entre as pessoas dele. */
it('usa o acesso mais recente entre os usuarios do tenant', function () {
    emitenteCompleto();
    $tenant = app(TenantAtual::class)->obter();

    User::factory()->create(['tenant_id' => $tenant->id, 'ultimo_acesso_em' => '2026-09-01 08:00:00']);
    User::factory()->create(['tenant_id' => $tenant->id, 'ultimo_acesso_em' => '2026-09-09 21:15:00']);

    $lead = app(MontadorDeLeads::class)->paraTenant($tenant->fresh());

    expect($lead['ultimo_acesso_em'])->toStartWith('2026-09-09T21:15:00');
});

it('manda o lead com o token no cabecalho', function () {
    Http::fake(['exemplo.test/*' => Http::response(['processados' => 1], 200)]);

    app(AdminPessoalGateway::class)->enviar([['tenant_id' => 7]]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://exemplo.test/api/integrations/venda-redonda/leads'
            && $request->hasHeader('Authorization', 'Bearer segredo-de-teste')
            && $request['leads'] === [['tenant_id' => 7]];
    });
});

/**
 * Um tenant sem emitente ou sem usuário sai com cnpj, nome e email vazios, e a
 * rota do outro lado recusa o lote inteiro no primeiro registro inválido. Um
 * cadastro pela metade travaria a sincronização de todos os outros.
 */
it('paraTodos pula tenant sem emitente ou sem usuario', function () {
    emitenteCompleto();
    $completo = app(TenantAtual::class)->obter();
    User::factory()->create(['tenant_id' => $completo->id]);

    Tenant::create(['nome' => 'Só o tenant', 'slug' => 'so-o-tenant']);

    $leads = app(MontadorDeLeads::class)->paraTodos();

    expect($leads)->toHaveCount(1)
        ->and($leads[0]['tenant_id'])->toBe($completo->id);
});

it('nao chama a rede sem configuracao', function () {
    config(['integracao.admin_pessoal.url' => null, 'integracao.admin_pessoal.token' => null]);

    // Sem Http::fake: se sair qualquer chamada, o preventStrayRequests global
    // da suíte derruba o teste. É essa a asserção.
    app(AdminPessoalGateway::class)->enviar([['tenant_id' => 7]]);
})->throwsNoExceptions();
