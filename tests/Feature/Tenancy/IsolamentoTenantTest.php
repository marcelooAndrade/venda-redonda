<?php

use App\Enums\PlanoTenant;
use App\Models\Emitente;
use App\Models\Pessoa;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmitenteAtual;
use App\Support\TenantAtual;

function tenantCom(string $slug): Tenant
{
    return Tenant::create(['nome' => ucfirst($slug), 'slug' => $slug]);
}

function usuarioDo(Tenant $tenant): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $emitente = Emitente::factory()->create(['tenant_id' => $tenant->id]);
    $user->emitentes()->attach($emitente);

    return $user;
}

beforeEach(function () {
    $this->rcm = tenantCom('rcm');
    $this->transm = tenantCom('transportes-leme');
});

it('resolve o tenant pelo subdominio', function () {
    app(TenantAtual::class)->definirPorHost('rcm.emissor.test');

    expect(app(TenantAtual::class)->obter()->id)->toBe($this->rcm->id);
});

it('resolve o tenant por dominio proprio', function () {
    // Domínio próprio é benefício de plano, então vem com o plano junto.
    $this->rcm->update(['plano' => PlanoTenant::Avancado, 'dominio' => 'sistema.rcmdobrasil.com.br']);

    app(TenantAtual::class)->definirPorHost('sistema.rcmdobrasil.com.br');

    expect(app(TenantAtual::class)->obter()->id)->toBe($this->rcm->id);
});

it('devolve nulo para host desconhecido', function () {
    app(TenantAtual::class)->definirPorHost('inexistente.emissor.test');

    expect(app(TenantAtual::class)->obter())->toBeNull();
});

it('ignora tenant inativo', function () {
    $this->rcm->update(['ativo' => false]);

    app(TenantAtual::class)->definirPorHost('rcm.emissor.test');

    expect(app(TenantAtual::class)->obter())->toBeNull();
});

it('esconde emitente de outro tenant', function () {
    Emitente::factory()->create(['tenant_id' => $this->rcm->id, 'razao_social' => 'RCM do Brasil']);
    Emitente::factory()->create(['tenant_id' => $this->transm->id, 'razao_social' => 'Transportes Leme']);

    app(TenantAtual::class)->definir($this->rcm);

    expect(Emitente::query()->pluck('razao_social')->all())->toBe(['RCM do Brasil']);
});

it('esconde pessoa de outro tenant', function () {
    $eRcm = Emitente::factory()->create(['tenant_id' => $this->rcm->id]);
    $eOutro = Emitente::factory()->create(['tenant_id' => $this->transm->id]);

    $dados = fn (Emitente $e, string $doc, string $nome) => Pessoa::withoutGlobalScopes()->create([
        'emitente_id' => $e->id, 'tipo_pessoa' => 'J', 'documento' => $doc, 'razao_social' => $nome,
        'ind_ie_dest' => '2', 'logradouro' => 'R', 'numero' => '1', 'bairro' => 'C',
        'codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP', 'cep' => '13602200',
        'e_cliente' => true,
    ]);
    $dados($eRcm, '11222333000181', 'Cliente da RCM');
    $dados($eOutro, '12ABC34501DE35', 'Cliente da Leme');

    app(TenantAtual::class)->definir($this->rcm);

    expect(Pessoa::query()->pluck('razao_social')->all())->toBe(['Cliente da RCM']);
});

it('nao resolve emitente de outro tenant como atual', function () {
    $user = usuarioDo($this->rcm);
    // Vínculo indevido, deixado para trás por uma migração ou erro operacional.
    $alheio = Emitente::factory()->create(['tenant_id' => $this->transm->id]);
    $user->emitentes()->attach($alheio);

    app(TenantAtual::class)->definir($this->rcm);
    $this->actingAs($user);
    session()->put('emitente_atual_id', $alheio->id);

    expect(app(EmitenteAtual::class)->resolver()->tenant_id)->toBe($this->rcm->id);
});

it('sem tenant definido nao vaza dado de ninguem', function () {
    Emitente::factory()->create(['tenant_id' => $this->rcm->id]);
    Emitente::factory()->create(['tenant_id' => $this->transm->id]);

    app(TenantAtual::class)->limpar();

    expect(Emitente::query()->count())->toBe(0);
});
