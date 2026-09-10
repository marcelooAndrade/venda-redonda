<?php

use App\Services\Integrations\ReceitaWsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

it('envia o token quando configurado', function () {
    config()->set('services.receitaws.token', 'tok-123');
    Http::fake(['receitaws.com.br/*' => Http::response(respostaOk())]);

    $this->service->consultar('11222333000181');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok-123'));
});

it('nao envia authorization sem token', function () {
    config()->set('services.receitaws.token', null);
    Http::fake(['receitaws.com.br/*' => Http::response(respostaOk())]);

    $this->service->consultar('11222333000181');

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
});
