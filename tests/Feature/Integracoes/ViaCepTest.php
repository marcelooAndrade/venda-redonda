<?php

use App\Services\Integrations\ViaCepService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    $this->service = app(ViaCepService::class);
});

it('devolve o codigo ibge, que a nf-e exige', function () {
    Http::fake(['viacep.com.br/*' => Http::response([
        'cep' => '13602-200', 'logradouro' => 'Rua João Grigoleto',
        'bairro' => 'Distrito Industrial II', 'localidade' => 'Araras',
        'uf' => 'SP', 'ibge' => '3503307',
    ])]);

    $r = $this->service->consultar('13602200');

    expect($r->codigoIbge)->toBe('3503307')
        ->and($r->municipio)->toBe('Araras')
        ->and($r->uf)->toBe('SP')
        ->and($r->logradouro)->toBe('Rua João Grigoleto');
});

it('avisa quando o cep nao existe', function () {
    Http::fake(['viacep.com.br/*' => Http::response(['erro' => 'true'])]);

    expect(fn () => $this->service->consultar('99999999'))
        ->toThrow(RuntimeException::class, 'CEP não encontrado');
});

it('recusa cep com tamanho errado sem chamar a api', function () {
    Http::fake();

    expect(fn () => $this->service->consultar('1360'))->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('guarda em cache', function () {
    Http::fake(['viacep.com.br/*' => Http::response([
        'logradouro' => 'Rua João Grigoleto', 'bairro' => 'Distrito Industrial II',
        'localidade' => 'Araras', 'uf' => 'SP', 'ibge' => '3503307',
    ])]);

    $this->service->consultar('13602200');
    $this->service->consultar('13.602-200');

    Http::assertSentCount(1);
});
