<?php

use App\Enums\PlanoTenant;
use App\Models\Tenant;

/**
 * O plano é gravado, e não deduzido do host.
 *
 * Deduzir pelo domínio funcionaria enquanto ninguém cancelasse: o plano cai,
 * o DNS continua apontado, e o cliente seguiria com o benefício.
 */
beforeEach(fn () => Tenant::query()->delete());

it('tenant nasce no plano gratuito', function () {
    $t = Tenant::create(['nome' => 'Leme', 'slug' => 'leme']);

    expect($t->plano)->toBe(PlanoTenant::Gratuito)
        ->and($t->plano->permiteMarcaPropria())->toBeFalse();
});

it('plano gratuito recusa dominio proprio', function () {
    expect(fn () => Tenant::create([
        'nome' => 'Leme', 'slug' => 'leme', 'dominio' => 'app.leme.com.br',
    ]))->toThrow(InvalidArgumentException::class);
});

it('plano avancado aceita dominio proprio', function () {
    $t = Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => PlanoTenant::Avancado, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);

    expect($t->fresh()->dominio)->toBe('app.rcmdobrasil.com.br');
});

it('rebaixar o plano com dominio apontado e recusado', function () {
    $t = Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => PlanoTenant::Avancado, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);

    // Rebaixar sem tirar o domínio deixaria o cliente com o benefício que
    // deixou de pagar. O domínio sai primeiro.
    expect(fn () => $t->update(['plano' => PlanoTenant::Gratuito]))
        ->toThrow(InvalidArgumentException::class);
});

it('no dominio do produto a porta e da venda redonda, mesmo havendo cliente com logo', function () {
    // Nenhum tenant é resolvido pelo host do produto, então não há marca de
    // cliente a mostrar: é essa a porta única do plano gratuito.
    $t = Tenant::create(['nome' => 'Leme', 'slug' => 'leme']);
    $t->forceFill(['logo_path' => 'marca/tenant/leme.png'])->saveQuietly();

    $this->get('http://vendaredonda.com.br/login')
        ->assertOk()
        ->assertSee('Venda Redonda')
        ->assertDontSee(route('logo'));
});

it('no dominio do cliente a marca dele abre a porta', function () {
    $rcm = Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => PlanoTenant::Avancado, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);
    $rcm->forceFill(['logo_path' => 'marca/tenant/rcm.png'])->saveQuietly();

    $this->get('http://app.rcmdobrasil.com.br/login')
        ->assertOk()
        ->assertSee('src="'.route('logo').'"', false);
});
