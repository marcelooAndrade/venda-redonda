<?php

use App\Jobs\EnviarWebhookCliente;
use App\Models\ApiCliente;
use App\Models\WhatsappInstancia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

function clienteComInstancia(array $extraInstancia = []): WhatsappInstancia
{
    $cliente = ApiCliente::create(['nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br', 'modulos' => ['whatsapp']]);

    return $cliente->whatsappInstancia()->create(array_merge([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ], $extraInstancia));
}

it('cliente define a propria url de webhook', function () {
    $instancia = clienteComInstancia();
    Sanctum::actingAs($instancia->apiCliente);

    $this->putJson('http://api.vendaredonda.test/whatsapp/v1/webhook', [
        'webhook_url' => 'https://transm.exemplo.com.br/nodo/webhook',
    ])->assertOk()->assertJson(['webhook_url' => 'https://transm.exemplo.com.br/nodo/webhook']);

    expect($instancia->fresh()->webhook_url)->toBe('https://transm.exemplo.com.br/nodo/webhook');
});

it('cliente pode limpar a url de webhook', function () {
    $instancia = clienteComInstancia(['webhook_url' => 'https://transm.exemplo.com.br/nodo/webhook']);
    Sanctum::actingAs($instancia->apiCliente);

    $this->putJson('http://api.vendaredonda.test/whatsapp/v1/webhook', [])->assertOk();

    expect($instancia->fresh()->webhook_url)->toBeNull();
});

it('recusa url de webhook invalida', function () {
    $instancia = clienteComInstancia();
    Sanctum::actingAs($instancia->apiCliente);

    $this->putJson('http://api.vendaredonda.test/whatsapp/v1/webhook', ['webhook_url' => 'nao e url'])
        ->assertStatus(422);
});

it('devolve 404 para segredo que nao existe', function () {
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/uazapi-webhook/segredo-invalido', ['algo' => 'aqui'])
        ->assertNotFound();
});

it('repassa a mensagem recebida para a url do cliente', function () {
    $instancia = clienteComInstancia(['webhook_secret' => 'segredo-de-teste', 'webhook_url' => 'https://transm.exemplo.com.br/nodo/webhook']);

    Http::fake(['transm.exemplo.com.br/*' => Http::response(['ok' => true], 200)]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/uazapi-webhook/segredo-de-teste', [
        'event' => 'messages', 'message' => ['text' => 'oi, tudo bem?', 'from' => '5519999998888'],
    ])->assertNoContent();

    Http::assertSent(fn ($request) => $request->url() === 'https://transm.exemplo.com.br/nodo/webhook'
        && $request['message']['text'] === 'oi, tudo bem?');
});

it('nao repassa nada quando o cliente nao configurou url de webhook', function () {
    clienteComInstancia(['webhook_secret' => 'segredo-de-teste', 'webhook_url' => null]);

    // Sem Http::fake: se tentar repassar sem destino, o preventStrayRequests
    // global derruba o teste.
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/uazapi-webhook/segredo-de-teste', [
        'event' => 'messages',
    ])->assertNoContent();
});

it('registra erro quando o job de repasse esgota as tentativas', function () {
    Log::spy();

    $job = new EnviarWebhookCliente('https://transm.exemplo.com.br/nodo/webhook', ['event' => 'messages']);
    $job->failed(new RuntimeException('conexao recusada'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $mensagem, array $contexto) => $contexto['webhook_url'] === 'https://transm.exemplo.com.br/nodo/webhook'
            && $contexto['erro'] === 'conexao recusada');
});
