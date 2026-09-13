<?php

/**
 * A regra de senha só aperta em produção, então a suíte inteira roda sob a
 * regra frouxa e nunca exercita a que o cliente encontra de verdade. Estes
 * testes forçam o ambiente para cobrir justamente essa.
 *
 * O piso caiu de 12 para 8 em 13/09: o de 12 barrava cadastro legítimo na
 * porta de entrada. O que segura senha fraca é o `uncompromised`, que recusa
 * senha já vista em vazamento, e não o comprimento.
 */

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

beforeEach(function () {
    app()['env'] = 'production';
});

/**
 * O fake fica em cada teste, e não no `beforeEach`: o primeiro stub que casa
 * é o que vence, então um fake definido antes tornaria o do teste inútil.
 *
 * Corpo vazio é a resposta do serviço para "esse hash não aparece em
 * vazamento nenhum". Sem fake, `Http::preventStrayRequests` reprovaria.
 */
function fakeSenhaNuncaVazada(): void
{
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
}

function validarSenha(string $senha): Illuminate\Validation\Validator
{
    return Validator::make(['password' => $senha], ['password' => Password::default()]);
}

it('aceita senha de oito caracteres em producao', function () {
    fakeSenhaNuncaVazada();

    expect(validarSenha('Rt7#kzQw')->passes())->toBeTrue();
});

it('recusa senha de sete caracteres, e diz o piso em portugues', function () {
    fakeSenhaNuncaVazada();

    $validador = validarSenha('Rt7#kzQ');

    expect($validador->passes())->toBeFalse()
        ->and($validador->errors()->first('password'))
        ->toBe('O campo senha deve ter pelo menos 8 caracteres.');
});

it('mantem as exigencias de composicao junto com o piso menor', function (string $senha, string $esperado) {
    fakeSenhaNuncaVazada();

    $validador = validarSenha($senha);

    expect($validador->passes())->toBeFalse()
        ->and($validador->errors()->first('password'))->toBe($esperado);
})->with([
    'sem maiúscula' => ['rt7#kzqw', 'A senha deve conter pelo menos uma letra maiúscula e uma minúscula.'],
    'sem número' => ['Rt#kzQwx', 'A senha deve conter pelo menos um número.'],
    'sem símbolo' => ['Rt7kzQw9', 'A senha deve conter pelo menos um símbolo.'],
]);

it('recusa senha que ja apareceu em vazamento', function () {
    // O serviço devolve o sufixo do hash e a contagem quando a senha vazou.
    $hash = strtoupper(sha1('Rt7#kzQw'));
    Http::fake([
        'api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).':42', 200),
    ]);

    $validador = validarSenha('Rt7#kzQw');

    expect($validador->passes())->toBeFalse()
        ->and($validador->errors()->first('password'))
        ->toBe('A senha informada apareceu em um vazamento de dados. Escolha outra.');
});

it('fora de producao nao exige composicao, para o ambiente de teste nao atrapalhar', function () {
    app()['env'] = 'local';
    fakeSenhaNuncaVazada();

    expect(validarSenha('segredo1')->passes())->toBeTrue();
});
