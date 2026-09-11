<?php

use App\Enums\PlanoTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;

/**
 * O sistema mora no domínio do produto, por caminho. Lá nenhum tenant é
 * resolvido pelo host, então quem diz de quem é a sessão é o próprio usuário.
 *
 * A restrição por host continua valendo onde ela faz sentido: no domínio
 * próprio de um cliente. Ver LoginEntreTenantsTest.
 */
function clienteCom(string $slug, ?string $host = null): Tenant
{
    return Tenant::create(['nome' => ucfirst($slug), 'slug' => $slug, 'dominio' => $host,
        'plano' => $host === null ? PlanoTenant::Gratuito : PlanoTenant::Avancado]);
}

function usuarioDe(Tenant $t, string $email): User
{
    return User::withoutGlobalScopes()->create([
        'tenant_id' => $t->id,
        'name' => 'Operador',
        'email' => $email,
        'password' => 'senha-de-teste',
        'email_verified_at' => now(),
    ]);
}

beforeEach(function () {
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
});

it('usuario de qualquer cliente entra pelo dominio do produto', function () {
    $leme = clienteCom('leme');
    usuarioDe($leme, 'operador@leme.test');

    $this->post('http://vendaredonda.com.br/login', [
        'email' => 'operador@leme.test',
        'password' => 'senha-de-teste',
    ]);

    $this->assertAuthenticated();
});

it('depois de entrar, o contexto e o tenant do usuario', function () {
    $leme = clienteCom('leme');
    $user = usuarioDe($leme, 'operador@leme.test');

    $this->actingAs($user)->get('http://vendaredonda.com.br/dashboard');

    expect(app(TenantAtual::class)->id())->toBe($leme->id);
});

it('o tenant vem do usuario, e nao do host, quando os dois discordam', function () {
    $leme = clienteCom('leme');
    clienteCom('rcm', 'app.rcmdobrasil.com.br');
    $user = usuarioDe($leme, 'operador@leme.test');

    $this->actingAs($user)->get('http://app.rcmdobrasil.com.br/dashboard');

    expect(app(TenantAtual::class)->id())->toBe($leme->id);
});

it('visitante no dominio do produto nao tem tenant', function () {
    clienteCom('leme');

    $this->get('http://vendaredonda.com.br/login')->assertOk();

    expect(app(TenantAtual::class)->id())->toBeNull();
});
