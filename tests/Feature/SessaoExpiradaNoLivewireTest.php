<?php

/**
 * Guarda o comportamento de `resources/js/app.js` para o 419 do Livewire.
 *
 * Sem teste de JavaScript neste projeto (nenhum runner configurado, nem em
 * `package.json`), então este teste lê o arquivo fonte e confere pela
 * presença do código, não pela execução. A verificação de comportamento
 * real (o quê acontece de fato no navegador) foi feita manualmente com
 * Playwright em 13/09, nos dois cenários: sessão ainda válida com token
 * velho, e sessão de verdade inválida. Os dois recarregam sem diálogo
 * nativo, e cada um cai onde deveria: a válida continua na mesma tela com
 * token novo, a inválida cai no login pelo próprio middleware de
 * autenticação.
 *
 * O que este teste travou de propósito: a versão anterior mandava sempre
 * para `/login`, e isso colidia com `RedirectIfAuthenticated` quando a
 * sessão ainda era válida, voltando ao painel em silêncio, sem o usuário
 * ver nada. `window.location.reload()` resolve os dois casos sozinho, sem
 * a página precisar adivinhar se a sessão caiu de verdade ou só o token
 * envelheceu.
 */
it('desliga o dialogo nativo do livewire e recarrega a pagina, sem redirecionar para uma rota fixa', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)->toContain("document.addEventListener('livewire:init'")
        ->and($js)->toContain("Livewire.hook('request'")
        ->and($js)->toContain('status === 419')
        ->and($js)->toContain('preventDefault()')
        ->and($js)->toContain('window.location.reload()')
        ->and($js)->not->toContain("window.location.href = '/login");
});
