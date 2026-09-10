<?php

use App\Models\Tenant;
use App\Support\TenantAtual;

/**
 * A aba do navegador é parte da marca. Num sistema que serve várias
 * empresas, "Laravel" na aba entrega que o sistema é de outro.
 */
it('usa o nome do tenant no titulo da pagina', function () {
    $tenant = app(TenantAtual::class)->obter();
    $tenant->update(['nome' => 'Transportes Leme']);

    $this->get('/login')->assertSee('<title>', false)->assertSee('Transportes Leme', false);
});

it('cai no nome do sistema quando nenhum tenant foi resolvido', function () {
    app()->forgetInstance(TenantAtual::class);
    Tenant::query()->delete();

    $this->get('/login')->assertOk()->assertDontSee('Transportes Leme');
});
