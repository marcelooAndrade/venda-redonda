<?php

use App\Models\Tenant;

/**
 * A apresentação pública, redesenhada em 12/09/2026: estrutura inspirada no
 * site importado da Aura (av-design-system, Fluxo B), cores e conteúdo
 * próprios da Venda Redonda. `HostTest.php` já cobre host, título e dados
 * estruturados; este arquivo cobre o conteúdo do redesenho.
 */
beforeEach(function () {
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
});

it('tem um link para criar conta gratis e outro para entrar', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('href="http://vendaredonda.com.br/register"')
        ->and($html)->toContain('Criar conta grátis')
        ->and($html)->toContain('href="http://vendaredonda.com.br/login"')
        ->and($html)->toContain('Entrar no sistema');
});

it('a assinatura do rodape inclui financeiro', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('Fiscal · Estoque · Financeiro');
});

it('a descricao cita financeiro e nfs-e', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('NFS-e')
        ->and($html)->toContain('contas a pagar e a receber');
});

it('as secoes existentes ganham o atributo de revelar ao rolar', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect(substr_count($html, 'data-revelar'))->toBeGreaterThanOrEqual(4);
});

it('mostra a secao de dores antes dos recursos', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('Isso não chega organizado sozinho')
        ->and(strpos($html, 'Isso não chega organizado sozinho'))
        ->toBeLessThan(strpos($html, 'Sete coisas, e elas dependem uma da outra'));
});

it('recursos tem sete cartoes, incluindo nfse e contas a pagar e a receber', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('Sete coisas, e elas dependem uma da outra')
        ->assertSee('NFS-e de Araras, pelo SIGISS')
        ->assertSee('Contas a pagar e a receber, com baixa')
        ->assertSee('A compra entra pelo XML')
        ->assertSee('Um painel que abre com o que trava');
});

it('o link ver o que ele faz aponta para a secao de recursos', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('href="#recursos"')
        ->and($html)->toContain('id="recursos"')
        ->and($html)->not->toContain('id="o-que-faz"');
});
