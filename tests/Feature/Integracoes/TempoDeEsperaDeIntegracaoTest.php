<?php

/**
 * O pior caso de tempo das integrações síncronas, chamadas de dentro de um
 * clique do usuário, não de uma fila.
 *
 * Em 13/09 um cadastro de destinatário travou com "This page has expired"
 * bem no clique de "Buscar CNPJ". A causa: `ReceitaWsService` tinha
 * `timeout(20)->retry(2, 1500)`, pior caso de quase um minuto num clique só,
 * mais do que qualquer plataforma deixa uma requisição web durar. O que
 * chega ao navegador depois desse limite não é mais resposta do Livewire, e
 * o próprio Livewire mostra esse aviso genérico para qualquer resposta que
 * não reconhece. `ViaCepService` tinha a mesma forma e o mesmo risco.
 *
 * Por que este teste lê constantes em vez de medir tempo de verdade: o
 * `retry()` do cliente HTTP roda dentro do adaptador real do Guzzle, e
 * `Http::fake()` substitui esse adaptador inteiro. Uma exceção de conexão
 * lançada de dentro do fake nunca passa pela repetição de verdade, porque a
 * repetição é justamente a parte que o fake pula. Path testado com
 * `php artisan tinker`, não suposto: com o fake, uma falha simulada faz
 * exatamente uma tentativa, nunca duas, não importa o que `retry()` diga.
 * Medir o pior caso pelas constantes é o jeito que sobra de prender o
 * número sem reintroduzir uma chamada de rede de verdade na suíte.
 */

use App\Services\Integrations\ReceitaWsService;
use App\Services\Integrations\ViaCepService;

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
