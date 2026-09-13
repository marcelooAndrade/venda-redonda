<?php

use App\Services\Integrations\ReceitaWsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    $this->service = app(ReceitaWsService::class);
});

function respostaOk(array $extra = []): array
{
    return array_merge([
        'status' => 'OK',
        'nome' => 'RCM DO BRASIL LTDA',
        'fantasia' => 'RCM DO BRASIL',
        'situacao' => 'ATIVA',
        'logradouro' => 'RUA JOAO GRIGOLETO',
        'numero' => '83',
        'complemento' => '',
        'bairro' => 'DISTRITO INDUSTRIAL II',
        'municipio' => 'ARARAS',
        'uf' => 'SP',
        'cep' => '13.602-200',
        'telefone' => '(19) 3096-0072',
        'email' => 'adm@rcmdobrasil.com.br',
    ], $extra);
}

it('preenche os dados do cnpj', function () {
    Http::fake(['receitaws.com.br/*' => Http::response(respostaOk())]);

    $r = $this->service->consultar('11222333000181');

    expect($r->razaoSocial)->toBe('RCM DO BRASIL LTDA')
        ->and($r->municipio)->toBe('ARARAS')
        ->and($r->uf)->toBe('SP')
        ->and($r->cep)->toBe('13602200')
        ->and($r->ativa)->toBeTrue();
});

it('sinaliza situacao diferente de ativa sem bloquear', function () {
    Http::fake(['receitaws.com.br/*' => Http::response(respostaOk(['situacao' => 'BAIXADA']))]);

    $r = $this->service->consultar('11222333000181');

    expect($r->ativa)->toBeFalse()
        ->and($r->situacao)->toBe('BAIXADA')
        ->and($r->razaoSocial)->toBe('RCM DO BRASIL LTDA');
});

it('devolve a mensagem quando a receita responde erro', function () {
    Http::fake(['receitaws.com.br/*' => Http::response(['status' => 'ERROR', 'message' => 'CNPJ inválido'])]);

    expect(fn () => $this->service->consultar('11222333000181'))
        ->toThrow(RuntimeException::class, 'CNPJ inválido');
});

it('explica o limite de consultas no 429', function () {
    Http::fake(['receitaws.com.br/*' => Http::response([], 429)]);

    expect(fn () => $this->service->consultar('11222333000181'))
        ->toThrow(RuntimeException::class, 'Limite de consultas');
});

it('recusa cnpj invalido antes de gastar consulta', function () {
    Http::fake();

    expect(fn () => $this->service->consultar('11222333000182'))->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('guarda em cache para nao gastar consulta repetida', function () {
    Http::fake(['receitaws.com.br/*' => Http::response(respostaOk())]);

    $this->service->consultar('11222333000181');
    $this->service->consultar('11.222.333/0001-81');

    Http::assertSentCount(1);
});

// A ReceitaWS tem duas APIs, e a spec oficial as separa pela URL: a Pública
// é `/v1/cnpj/{cnpj}`, sem autenticação, e a Comercial é
// `/v1/cnpj/{cnpj}/days/{dias}`, com Bearer. Mandar o token para a URL
// pública não compra nada: a consulta segue com a cota gratuita.

it('com token consulta a api comercial, com a defasagem configurada', function () {
    config()->set('services.receitaws.token', 'tok-123');
    config()->set('services.receitaws.dias', 30);
    Http::fake(['receitaws.com.br/*' => Http::response(respostaOk())]);

    $this->service->consultar('11222333000181');

    Http::assertSent(fn ($request) => $request->url() === 'https://receitaws.com.br/v1/cnpj/11222333000181/days/30'
        && $request->hasHeader('Authorization', 'Bearer tok-123'));
});

it('sem token consulta a api publica, sem defasagem nem authorization', function () {
    config()->set('services.receitaws.token', null);
    Http::fake(['receitaws.com.br/*' => Http::response(respostaOk())]);

    $this->service->consultar('11222333000181');

    Http::assertSent(fn ($request) => $request->url() === 'https://receitaws.com.br/v1/cnpj/11222333000181'
        && ! $request->hasHeader('Authorization'));
});

it('explica a cota do plano no 402', function () {
    config()->set('services.receitaws.token', 'tok-123');
    Http::fake(['receitaws.com.br/*' => Http::response([], 402)]);

    expect(fn () => $this->service->consultar('11222333000181'))
        ->toThrow(RuntimeException::class, 'cota do plano');
});

// A API Pública só responde CNPJ que já está no banco da ReceitaWS. Fora
// disso devolve 504, que não é instabilidade: é a limitação do plano, e a
// mensagem precisa dizer isso para quem está cadastrando.

it('explica que o cnpj nao esta na base gratuita no 504 sem token', function () {
    config()->set('services.receitaws.token', null);
    Http::fake(['receitaws.com.br/*' => Http::response([], 504)]);

    expect(fn () => $this->service->consultar('11222333000181'))
        ->toThrow(RuntimeException::class, 'base gratuita');
});

it('explica que a receita nao respondeu a tempo no 504 com token', function () {
    config()->set('services.receitaws.token', 'tok-123');
    Http::fake(['receitaws.com.br/*' => Http::response([], 504)]);

    expect(fn () => $this->service->consultar('11222333000181'))
        ->toThrow(RuntimeException::class, 'não respondeu a tempo');
});

// O log é o que diz ao dono do produto se o plano gratuito basta: quantas
// vezes por dia a base não tinha o CNPJ, ou o limite por minuto bateu.

it('registra em log a resposta que nega a consulta', function (int $status, ?string $token) {
    config()->set('services.receitaws.token', $token);
    Http::fake(['receitaws.com.br/*' => Http::response([], $status)]);
    Log::spy();

    try {
        $this->service->consultar('11222333000181');
    } catch (RuntimeException) {
    }

    Log::shouldHaveReceived('warning')->once();
})->with([
    'limite por minuto' => [429, null],
    'cota do plano' => [402, 'tok-123'],
    'fora da base gratuita' => [504, null],
]);
