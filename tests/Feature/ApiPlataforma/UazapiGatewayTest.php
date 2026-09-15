<?php

use App\Exceptions\UazapiIndisponivel;
use App\Services\Integrations\UazapiGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'integracao.uazapi.url' => 'https://uazapi.test',
        'integracao.uazapi.admin_token' => 'admin-de-teste',
    ]);
});

it('cria instancia com o admin token no cabecalho', function () {
    Http::fake(['uazapi.test/instance/init' => Http::response([
        'token' => 'token-da-instancia',
        'instance' => ['id' => 'inst-1'],
    ], 200)]);

    $criada = app(UazapiGateway::class)->criarInstancia('cliente-1');

    expect($criada)->toBe(['id' => 'inst-1', 'token' => 'token-da-instancia']);

    Http::assertSent(fn ($request) => $request->url() === 'https://uazapi.test/instance/init'
        && $request->hasHeader('AdminToken', 'admin-de-teste')
        && $request['name'] === 'cliente-1');
});

it('conecta a instancia com o token dela, nao o admin token', function () {
    Http::fake(['uazapi.test/instance/connect' => Http::response([
        'instance' => ['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting'],
    ], 200)]);

    $conexao = app(UazapiGateway::class)->conectar('token-da-instancia');

    expect($conexao)->toBe(['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting']);

    Http::assertSent(fn ($request) => $request->hasHeader('token', 'token-da-instancia')
        && ! $request->hasHeader('AdminToken'));
});

it('le o status da instancia', function () {
    Http::fake(['uazapi.test/instance/status' => Http::response([
        'instance' => ['status' => 'connected'],
        'status' => ['connected' => true],
    ], 200)]);

    $status = app(UazapiGateway::class)->status('token-da-instancia');

    expect($status)->toBe(['status' => 'connected', 'conectado' => true]);
});

it('manda texto com numero e texto no corpo', function () {
    Http::fake(['uazapi.test/send/text' => Http::response(['messageid' => 'msg-1', 'status' => 'Pending'], 200)]);

    $enviado = app(UazapiGateway::class)->enviarTexto('token-da-instancia', '5519999998888', 'ola');

    expect($enviado['messageid'])->toBe('msg-1');

    Http::assertSent(fn ($request) => $request['number'] === '5519999998888' && $request['text'] === 'ola');
});

it('lista os grupos da instancia', function () {
    Http::fake(['uazapi.test/group/list' => Http::response([
        'groups' => [['id' => '123-456@g.us', 'name' => 'Grupo de teste']],
    ], 200)]);

    $grupos = app(UazapiGateway::class)->listarGrupos('token-da-instancia');

    expect($grupos)->toBe([['id' => '123-456@g.us', 'name' => 'Grupo de teste']]);

    Http::assertSent(fn ($request) => $request->url() === 'https://uazapi.test/group/list'
        && $request->hasHeader('token', 'token-da-instancia')
        && $request->method() === 'GET');
});

it('lanca UazapiIndisponivel quando a uazapi recusa', function () {
    Http::fake(['uazapi.test/*' => Http::response(['error' => 'motivo qualquer'], 400)]);

    app(UazapiGateway::class)->status('token-qualquer');
})->throws(UazapiIndisponivel::class);

/**
 * A uazapi já falhou de forma passageira em produção nesta mesma chamada
 * (medido: a mesma instância real de um cliente funcionou ao repetir minutos
 * depois). Repetição automática cobre exatamente esse caso.
 */
it('repete conectar quando a uazapi falha na primeira tentativa', function () {
    Http::fake(['uazapi.test/instance/connect' => Http::sequence()
        ->push(['error' => 'fora do ar'], 503)
        ->push(['instance' => ['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting']], 200)]);

    $conexao = app(UazapiGateway::class)->conectar('token-da-instancia');

    expect($conexao)->toBe(['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting']);
    Http::assertSentCount(2);
});

/**
 * mandar mensagem não pode repetir sozinho: uma falha sem resposta pode já
 * ter entregado a mensagem do outro lado, e repetir arriscaria duplicar.
 */
it('nao repete enviarTexto quando a uazapi falha', function () {
    Http::fake(['uazapi.test/send/text' => Http::response(['error' => 'fora do ar'], 503)]);

    app(UazapiGateway::class)->enviarTexto('token-da-instancia', '5519999998888', 'ola');
})->throws(UazapiIndisponivel::class);

it('confirma que enviarTexto so tentou uma vez', function () {
    Http::fake(['uazapi.test/send/text' => Http::response(['error' => 'fora do ar'], 503)]);

    try {
        app(UazapiGateway::class)->enviarTexto('token-da-instancia', '5519999998888', 'ola');
    } catch (UazapiIndisponivel) {
        // esperado
    }

    Http::assertSentCount(1);
});

it('lanca UazapiIndisponivel sem configuracao, sem chamar a rede', function () {
    config(['integracao.uazapi.url' => null, 'integracao.uazapi.admin_token' => null]);

    // Sem Http::fake: se sair qualquer chamada, o preventStrayRequests
    // global da suíte derruba o teste antes mesmo da exceção.
    app(UazapiGateway::class)->criarInstancia('cliente-1');
})->throws(UazapiIndisponivel::class);
