<?php

/**
 * Guarda contra a interface voltar a falar inglês, ou pior, a falar em
 * chave crua.
 *
 * O `APP_LOCALE` era `pt_BR` desde 11/09, mas a pasta `lang/pt_BR/` não
 * existia. Como o `fallback_locale` também é `pt_BR`, o Laravel não tinha
 * para onde cair: um formulário vazio mostrava literalmente
 * "validation.required" para o usuário. Medido, não suposto.
 *
 * A paridade é conferida contra o arquivo que vem dentro do framework, e
 * não contra uma cópia em `lang/en`, para que uma atualização do Laravel
 * que traga regra nova reprove aqui em vez de aparecer na tela do cliente.
 */

use Illuminate\Support\Facades\Lang;

/** @return array<int, string> */
function chavesAchatadas(array $itens, string $prefixo = ''): array
{
    $chaves = [];

    foreach ($itens as $chave => $valor) {
        $caminho = $prefixo === '' ? (string) $chave : $prefixo.'.'.$chave;

        if (is_array($valor)) {
            $chaves = array_merge($chaves, chavesAchatadas($valor, $caminho));

            continue;
        }

        $chaves[] = $caminho;
    }

    return $chaves;
}

it('resolve as mensagens do sistema em portugues, nunca na chave crua', function (string $chave) {
    app()->setLocale('pt_BR');

    expect(Lang::has($chave))->toBeTrue("a chave {$chave} não existe em lang/pt_BR")
        ->and(trans($chave))->not->toBe($chave);
})->with([
    'validation.required',
    'validation.email',
    'validation.unique',
    'validation.confirmed',
    'validation.min.string',
    'validation.max.file',
    'validation.current_password',
    'auth.failed',
    'auth.throttle',
    'passwords.sent',
    'passwords.token',
    'pagination.previous',
    'pagination.next',
]);

it('traduz o nome do campo, para a mensagem nao citar o nome da coluna', function () {
    app()->setLocale('pt_BR');

    $mensagem = trans('validation.required', ['attribute' => trans('validation.attributes.razao_social')]);

    expect($mensagem)->toBe('O campo razão social é obrigatório.');
});

it('cobre toda chave que o framework define, para atualizacao do laravel nao deixar buraco', function (string $arquivo) {
    $origem = 'vendor/laravel/framework/src/Illuminate/Translation/lang/en/'.$arquivo.'.php';

    expect(file_exists(base_path($origem)))->toBeTrue("o framework mudou de lugar: {$origem}");

    $doFramework = chavesAchatadas(require base_path($origem));
    $nossas = chavesAchatadas(require lang_path('pt_BR/'.$arquivo.'.php'));

    $faltando = array_diff($doFramework, $nossas);

    expect($faltando)->toBeEmpty(
        "sem tradução em pt_BR/{$arquivo}.php: ".implode(', ', $faltando)
    );
})->with(['validation', 'auth', 'passwords', 'pagination']);

it('traduz toda frase que as telas pedem com __()', function () {
    $traduzidas = json_decode((string) file_get_contents(lang_path('pt_BR.json')), true);

    $usadas = [];

    foreach ([resource_path('views'), app_path('Livewire')] as $raiz) {
        $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz));

        foreach ($arquivos as $arquivo) {
            if ($arquivo->isDir() || ! str_ends_with($arquivo->getFilename(), '.php')) {
                continue;
            }

            preg_match_all("/__\('([^']+)'\)/", (string) file_get_contents($arquivo->getPathname()), $achadas);
            $usadas = array_merge($usadas, $achadas[1]);
        }
    }

    expect($usadas)->not->toBeEmpty('a varredura não achou nenhuma chamada de __(): o padrão quebrou');

    $semTraducao = array_values(array_filter(
        array_unique($usadas),
        fn (string $frase): bool => ! isset($traduzidas[$frase]),
    ));

    expect($semTraducao)->toBeEmpty('frases sem tradução: '.implode(' | ', $semTraducao));
});

it('mostra a pagina de erro em portugues, com a marca', function () {
    config(['produto.dominio' => 'vendaredonda.com.br']);

    $this->get('http://vendaredonda.com.br/rota-que-nao-existe')
        ->assertNotFound()
        ->assertSee('Página não encontrada')
        ->assertSee('EmitirAgora')
        ->assertDontSee('Not Found');
});
