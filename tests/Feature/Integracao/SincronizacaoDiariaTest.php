<?php

use App\Jobs\EnviarLeads;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'integracao.admin_pessoal.url' => 'https://exemplo.test',
        'integracao.admin_pessoal.token' => 'segredo-de-teste',
    ]);
});

/**
 * Dois tenants de verdade, e não dois emitentes: a fábrica de emitente
 * reaproveita o tenant que o `TenantAtual` já resolve, então criar dois
 * emitentes daria um tenant só.
 */
it('envia todos os tenants de uma vez', function () {
    Queue::fake();

    emitenteCompleto();
    $primeiro = app(TenantAtual::class)->obter();
    User::factory()->create(['tenant_id' => $primeiro->id]);

    $segundo = Tenant::create(['nome' => 'SEGUNDA EMPRESA LTDA', 'slug' => 'segunda-empresa']);
    app(TenantAtual::class)->definir($segundo);
    Emitente::factory()->create(['cnpj' => '11444777000161']);
    User::factory()->create(['tenant_id' => $segundo->id]);

    $this->artisan('produto:sincronizar-leads')->assertSuccessful();

    Queue::assertPushed(EnviarLeads::class, fn (EnviarLeads $job) => count($job->leads) === 2);
});

/** Tenant sem emitente e sem usuário não tem lead nenhum a mandar. */
it('nao despacha nada quando nenhum tenant esta completo', function () {
    Queue::fake();

    $this->artisan('produto:sincronizar-leads')->assertSuccessful();

    Queue::assertNotPushed(EnviarLeads::class);
});
