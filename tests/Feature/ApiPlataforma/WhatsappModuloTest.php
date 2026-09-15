<?php

use App\Models\ApiCliente;
use App\Models\WhatsappInstancia;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'integracao.uazapi.url' => 'https://uazapi.test',
        'integracao.uazapi.admin_token' => 'admin-de-teste',
    ]);
});

it('recusa sem token', function () {
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertUnauthorized();
});

it('recusa token sem a habilidade whatsapp', function () {
    $cliente = ApiCliente::create(['nome' => 'Sem acesso', 'email' => 'semacesso@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['outra-coisa']);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertForbidden();
});

it('cria a instancia do cliente autenticado', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['whatsapp']);

    Http::fake(['uazapi.test/instance/init' => Http::response([
        'token' => 'token-uazapi-1', 'instance' => ['id' => 'inst-1'],
    ], 200)]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')
        ->assertCreated()
        ->assertJson(['status' => 'disconnected']);

    $instancia = WhatsappInstancia::firstWhere('api_cliente_id', $cliente->id);

    expect($instancia)->not->toBeNull()
        ->and($instancia->uazapi_instance_id)->toBe('inst-1')
        ->and($instancia->uazapi_token)->toBe('token-uazapi-1');
});

it('nao cria uma segunda instancia para quem ja tem uma', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['whatsapp']);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-existente', 'uazapi_token' => 'token-existente', 'nome' => 'x',
    ]);

    // Sem Http::fake: se tentar criar de novo, o preventStrayRequests
    // global derruba o teste. É essa a prova de que não chamou a uazapi.
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertCreated();

    expect(WhatsappInstancia::where('api_cliente_id', $cliente->id)->count())->toBe(1);
});

it('devolve 404 ao consultar status sem instancia criada', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['whatsapp']);

    $this->getJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertNotFound();
});

it('conecta e devolve o qrcode, atualizando o status guardado', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['whatsapp']);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'disconnected',
    ]);

    Http::fake(['uazapi.test/instance/connect' => Http::response([
        'instance' => ['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting'],
    ], 200)]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia/conectar')
        ->assertOk()
        ->assertJson(['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting']);

    expect($cliente->whatsappInstancia->fresh()->status)->toBe('connecting');
});

it('recusa mandar mensagem sem instancia conectada', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['whatsapp']);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'disconnected',
    ]);

    // Sem Http::fake: instância desconectada nem deveria tentar mandar.
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/mensagens', [
        'numero' => '5519999998888', 'texto' => 'oi',
    ])->assertStatus(422);
});

it('manda mensagem quando a instancia esta conectada', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['whatsapp']);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    Http::fake(['uazapi.test/send/text' => Http::response(['messageid' => 'msg-1', 'status' => 'Pending'], 200)]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/mensagens', [
        'numero' => '5519999998888', 'texto' => 'oi',
    ])->assertCreated()->assertJson(['enviado' => true, 'id' => 'msg-1']);

    Http::assertSent(fn ($request) => $request->hasHeader('token', 'token-uazapi-1'));
});

it('exige numero e texto', function () {
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br']);
    Sanctum::actingAs($cliente, ['whatsapp']);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/mensagens', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['numero', 'texto']);
});
