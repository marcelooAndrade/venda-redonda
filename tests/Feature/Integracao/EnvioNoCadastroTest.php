<?php

use App\Jobs\EnviarLeads;
use App\Models\Tenant;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    Tenant::query()->delete();
    config([
        'produto.dominio' => 'vendaredonda.com.br',
        'integracao.admin_pessoal.url' => 'https://exemplo.test',
        'integracao.admin_pessoal.token' => 'segredo-de-teste',
    ]);
});

function cadastroValido(array $extra = []): array
{
    return array_merge([
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123',
        'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'telefone' => '1930960072',
    ], $extra);
}

it('o cadastro despacha o envio do lead', function () {
    Queue::fake();

    $this->post('http://vendaredonda.com.br/register', cadastroValido());

    $tenant = Tenant::firstWhere('nome', 'DISTRIBUIDORA RIO CLARO LTDA');

    Queue::assertPushed(EnviarLeads::class, function (EnviarLeads $job) use ($tenant) {
        return $job->leads[0]['tenant_id'] === $tenant->id
            && $job->leads[0]['telefone'] === '1930960072'
            && $job->leads[0]['email'] === 'marcelo@exemplo.com.br';
    });
});

/**
 * O admin pessoal fora do ar não pode impedir alguém de criar a conta. O envio
 * é consequência do cadastro, e nunca condição dele.
 */
it('o cadastro sobrevive a falha no envio', function () {
    Http::fake(['exemplo.test/*' => Http::response('fora do ar', 500)]);

    $this->post('http://vendaredonda.com.br/register', cadastroValido());

    expect(Tenant::where('nome', 'DISTRIBUIDORA RIO CLARO LTDA')->exists())->toBeTrue();
});
