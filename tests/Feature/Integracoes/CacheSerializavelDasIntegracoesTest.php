<?php

/**
 * A segunda consulta do mesmo CNPJ ou CEP, servida pelo cache em banco.
 *
 * Descoberto em 13/09: a primeira consulta de um CNPJ funciona (cache miss,
 * o objeto vem direto do serviço), mas a segunda quebrava com TypeError,
 * "must be of type RespostaCnpj, __PHP_Incomplete_Class returned". Causa: o
 * cache em banco serializa o valor guardado, e o padrão do Laravel 13,
 * `cache.serializable_classes => false`, manda desserializar com
 * `allowed_classes: false`, o que vira todo objeto em classe incompleta. Era
 * invisível na suíte porque `phpunit.xml` usa o cache `array`, que guarda o
 * objeto vivo em memória, sem serializar.
 *
 * Estes testes rodam contra o cache em banco de propósito, com o padrão
 * `serializable_classes` de produção, que é o cenário real. Não podem voltar
 * para o cache `array`, senão deixam de proteger contra a regressão.
 */

use App\Services\Integrations\ReceitaWsService;
use App\Services\Integrations\RespostaCep;
use App\Services\Integrations\RespostaCnpj;
use App\Services\Integrations\ViaCepService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('cache.default', 'database');
    config()->set('cache.serializable_classes', false);
    Cache::purge('database');
    Cache::store('database')->clear();
    Http::preventStrayRequests();
});

it('a segunda consulta de cnpj vem do cache como objeto, nao classe incompleta', function () {
    Http::fake(['receitaws.com.br/*' => Http::response([
        'status' => 'OK', 'nome' => 'RCM DO BRASIL LTDA', 'situacao' => 'ATIVA',
        'municipio' => 'ARARAS', 'uf' => 'SP', 'cep' => '13602200',
    ])]);

    $service = app(ReceitaWsService::class);
    $primeira = $service->consultar('11222333000181');
    $segunda = $service->consultar('11222333000181');

    expect($segunda)->toBeInstanceOf(RespostaCnpj::class)
        ->and($segunda->razaoSocial)->toBe('RCM DO BRASIL LTDA')
        ->and($segunda->municipio)->toBe('ARARAS')
        ->and($segunda->ativa)->toBeTrue();
    Http::assertSentCount(1);
});

it('a segunda consulta de cep vem do cache como objeto, nao classe incompleta', function () {
    Http::fake(['viacep.com.br/*' => Http::response([
        'logradouro' => 'Rua João Grigoleto', 'bairro' => 'Distrito Industrial II',
        'localidade' => 'Araras', 'uf' => 'SP', 'ibge' => '3503307',
    ])]);

    $service = app(ViaCepService::class);
    $service->consultar('13602200');
    $segunda = $service->consultar('13602200');

    expect($segunda)->toBeInstanceOf(RespostaCep::class)
        ->and($segunda->codigoIbge)->toBe('3503307')
        ->and($segunda->municipio)->toBe('Araras');
    Http::assertSentCount(1);
});
