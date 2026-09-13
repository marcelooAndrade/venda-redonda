<?php

/**
 * Tentativas e pior caso de tempo das integrações síncronas, chamadas de
 * dentro de um clique do usuário, não de uma fila.
 *
 * Em 13/09 um cadastro de destinatário travou com "This page has expired"
 * bem no clique de "Buscar CNPJ". A causa: `ReceitaWsService` tinha
 * `timeout(20)->retry(2, 1500)`, pior caso de 41,5s num clique só, mais do
 * que a plataforma deixa uma requisição web durar. O que chega ao navegador
 * depois desse limite não é mais resposta do Livewire, e o próprio Livewire
 * mostra esse aviso genérico para qualquer resposta que não reconhece.
 *
 * O que `Http::retry($n)` conta: `$n` é o total de tentativas, não o número
 * de repetições. `retry(1)` faz uma tentativa e nenhuma repetição. A
 * primeira versão desta correção leu ao contrário, escreveu `retry(1)`
 * querendo uma repetição, e concluiu que "o fake pula a repetição" porque
 * media uma tentativa só. Não pulava: não havia repetição configurada. Os
 * testes abaixo contam as tentativas de verdade, pelo registro do fake, e
 * prendem a política: repete em falha de conexão e em erro do servidor,
 * nunca em 429, 402 ou 504, que são respostas definitivas e repetir só
 * gastaria cota e tempo.
 */

use App\Services\Integrations\ReceitaWsService;
use App\Services\Integrations\ViaCepService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    Sleep::fake();
});

function receitaOk(): array
{
    return ['status' => 'OK', 'nome' => 'RCM DO BRASIL LTDA', 'situacao' => 'ATIVA', 'municipio' => 'ARARAS', 'uf' => 'SP'];
}

function viaCepOk(): array
{
    return ['logradouro' => 'Rua João Grigoleto', 'localidade' => 'Araras', 'uf' => 'SP', 'ibge' => '3503307'];
}

it('o pior caso de tempo de rede da consulta de cnpj fica bem abaixo de 30s', function () {
    $piorCaso = ReceitaWsService::TIMEOUT_SEGUNDOS * (1 + ReceitaWsService::REPETICOES)
        + (ReceitaWsService::REPETICOES * ReceitaWsService::ESPERA_MS / 1000);

    expect($piorCaso)->toBeLessThan(20.0);
});

it('o pior caso de tempo de rede da consulta de cep fica bem abaixo de 30s', function () {
    $piorCaso = ViaCepService::TIMEOUT_SEGUNDOS * (1 + ViaCepService::REPETICOES)
        + (ViaCepService::REPETICOES * ViaCepService::ESPERA_MS / 1000);

    expect($piorCaso)->toBeLessThan(20.0);
});

it('a consulta de cnpj repete quando a conexao falha, e a repeticao resolve', function () {
    Http::fake(['receitaws.com.br/*' => Http::sequence()->pushFailedConnection()->push(receitaOk())]);

    $r = app(ReceitaWsService::class)->consultar('11222333000181');

    expect($r->razaoSocial)->toBe('RCM DO BRASIL LTDA');
    Http::assertSentCount(1 + ReceitaWsService::REPETICOES);
});

it('a consulta de cnpj repete em erro do servidor', function () {
    Http::fake(['receitaws.com.br/*' => Http::sequence()->push([], 503)->push(receitaOk())]);

    $r = app(ReceitaWsService::class)->consultar('11222333000181');

    expect($r->razaoSocial)->toBe('RCM DO BRASIL LTDA');
    Http::assertSentCount(2);
});

it('a consulta de cnpj desiste depois de esgotar as repeticoes', function () {
    Http::fake(['receitaws.com.br/*' => Http::failedConnection()]);

    expect(fn () => app(ReceitaWsService::class)->consultar('11222333000181'))
        ->toThrow(RuntimeException::class, 'Não foi possível consultar a Receita agora');
    Http::assertSentCount(1 + ReceitaWsService::REPETICOES);
});

it('a consulta de cnpj nao repete no 429, no 402 nem no 504', function (int $status) {
    Http::fake(['receitaws.com.br/*' => Http::response([], $status)]);

    expect(fn () => app(ReceitaWsService::class)->consultar('11222333000181'))
        ->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
})->with([429, 402, 504]);

it('a consulta de cep repete quando a conexao falha, e a repeticao resolve', function () {
    Http::fake(['viacep.com.br/*' => Http::sequence()->pushFailedConnection()->push(viaCepOk())]);

    $r = app(ViaCepService::class)->consultar('13602200');

    expect($r->codigoIbge)->toBe('3503307');
    Http::assertSentCount(1 + ViaCepService::REPETICOES);
});

it('a consulta de cep nao repete em resposta definitiva', function () {
    Http::fake(['viacep.com.br/*' => Http::response([], 400)]);

    expect(fn () => app(ViaCepService::class)->consultar('13602200'))
        ->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
});
