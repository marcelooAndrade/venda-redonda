<?php

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Perfil;
use App\Livewire\Pessoas\Cadastro;
use App\Models\Emitente;
use App\Models\Pessoa;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function usuarioPessoas(string $perfil = 'Faturamento'): array
{
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);

    return [$user, $emitente];
}

beforeEach(function () {
    Cache::flush();
    // Sem isto, requisição que não casa com nenhum fake sai de verdade para a
    // internet e o teste passa a depender da rede.
    Http::preventStrayRequests();
    $this->seed(PerfilSeeder::class);
});

function fakeViaCep(): array
{
    return ['viacep.com.br/*' => Http::response([
        'logradouro' => 'Rua João Grigoleto', 'bairro' => 'Distrito Industrial II',
        'localidade' => 'Araras', 'uf' => 'SP', 'ibge' => '3503307',
    ])];
}

it('preenche o formulario com os dados da receita', function () {
    [$user] = usuarioPessoas();
    Http::fake([...fakeViaCep(), 'receitaws.com.br/*' => Http::response([
        'status' => 'OK', 'nome' => 'RCM DO BRASIL LTDA', 'fantasia' => 'RCM',
        'situacao' => 'ATIVA', 'logradouro' => 'RUA JOAO GRIGOLETO', 'numero' => '83',
        'bairro' => 'DISTRITO INDUSTRIAL II', 'municipio' => 'ARARAS', 'uf' => 'SP',
        'cep' => '13602-200', 'telefone' => '(19) 3096-0072', 'email' => 'adm@rcm.com.br',
    ])]);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.documento', '11222333000181')
        ->call('buscarCnpj')
        ->assertSet('form.razao_social', 'RCM DO BRASIL LTDA')
        ->assertSet('form.numero', '83')
        // O município vem do ViaCEP, não da Receita: é ele que devolve o código
        // IBGE, e os dois precisam ser do mesmo par para a NF-e não rejeitar.
        ->assertSet('form.municipio', 'Araras')
        ->assertSet('form.codigo_municipio', '3503307')
        ->assertSet('form.uf', 'SP');
});

it('avisa quando a empresa nao esta ativa', function () {
    [$user] = usuarioPessoas();
    Http::fake([...fakeViaCep(), 'receitaws.com.br/*' => Http::response([
        'status' => 'OK', 'nome' => 'EMPRESA BAIXADA LTDA', 'situacao' => 'BAIXADA',
        'logradouro' => 'R X', 'numero' => '1', 'bairro' => 'C', 'municipio' => 'ARARAS',
        'uf' => 'SP', 'cep' => '13602200',
    ])]);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.documento', '11222333000181')
        ->call('buscarCnpj')
        ->assertSet('avisoSituacao', 'BAIXADA')
        ->assertSet('form.razao_social', 'EMPRESA BAIXADA LTDA');
});

it('busca o codigo ibge pelo cep', function () {
    [$user] = usuarioPessoas();
    Http::fake(['viacep.com.br/*' => Http::response([
        'logradouro' => 'Rua João Grigoleto', 'bairro' => 'Distrito Industrial II',
        'localidade' => 'Araras', 'uf' => 'SP', 'ibge' => '3503307',
    ])]);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.cep', '13602200')
        ->call('buscarCep')
        ->assertSet('form.codigo_municipio', '3503307')
        ->assertSet('form.municipio', 'Araras');
});

it('salva uma pessoa valida', function () {
    [$user, $emitente] = usuarioPessoas();

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.tipo_pessoa', 'J')
        ->set('form.documento', '11222333000181')
        ->set('form.razao_social', 'Metalúrgica Piracicaba Ltda')
        ->set('form.ind_ie_dest', IndIEDest::Contribuinte->value)
        ->set('form.inscricao_estadual', '111222333444')
        ->set('form.logradouro', 'Rua Industrial')->set('form.numero', '100')
        ->set('form.bairro', 'Centro')->set('form.codigo_municipio', '3538709')
        ->set('form.municipio', 'Piracicaba')->set('form.uf', 'SP')->set('form.cep', '13400000')
        ->set('form.e_cliente', true)
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Pessoa::where('emitente_id', $emitente->id)->count())->toBe(1);
});

it('bloqueia contribuinte sem inscricao estadual', function () {
    [$user] = usuarioPessoas();

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.tipo_pessoa', 'J')
        ->set('form.documento', '11222333000181')
        ->set('form.razao_social', 'Metalúrgica Piracicaba Ltda')
        ->set('form.ind_ie_dest', IndIEDest::Contribuinte->value)
        ->set('form.inscricao_estadual', '')
        ->set('form.logradouro', 'Rua Industrial')->set('form.numero', '100')
        ->set('form.bairro', 'Centro')->set('form.codigo_municipio', '3538709')
        ->set('form.municipio', 'Piracicaba')->set('form.uf', 'SP')->set('form.cep', '13400000')
        ->set('form.e_cliente', true)
        ->call('salvar')
        ->assertHasErrors('inscricao_estadual');
});

it('nega gravar ao perfil de consulta', function () {
    [$user] = usuarioPessoas(Perfil::Consulta->value);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.documento', '11222333000181')
        ->call('salvar')
        ->assertForbidden();
});

it('lista apenas pessoas do emitente em foco', function () {
    [$user, $emitente] = usuarioPessoas();
    $outro = Emitente::factory()->create();

    Pessoa::create(['emitente_id' => $emitente->id, 'tipo_pessoa' => 'J', 'documento' => '11222333000181',
        'razao_social' => 'Minha Cliente', 'ind_ie_dest' => '2', 'logradouro' => 'R', 'numero' => '1',
        'bairro' => 'C', 'codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP',
        'cep' => '13602200', 'e_cliente' => true]);
    Pessoa::create(['emitente_id' => $outro->id, 'tipo_pessoa' => 'J', 'documento' => '12ABC34501DE35',
        'razao_social' => 'Cliente Alheia', 'ind_ie_dest' => '2', 'logradouro' => 'R', 'numero' => '1',
        'bairro' => 'C', 'codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP',
        'cep' => '13602200', 'e_cliente' => true]);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->assertSee('Minha Cliente')
        ->assertDontSee('Cliente Alheia');
});

it('guarda a observacao e a traz de volta ao editar', function () {
    [$user, $emitente] = usuarioPessoas();

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.tipo_pessoa', 'J')
        ->set('form.documento', '11222333000181')
        ->set('form.razao_social', 'Metalúrgica Piracicaba Ltda')
        ->set('form.ind_ie_dest', IndIEDest::NaoContribuinte->value)
        ->set('form.logradouro', 'Rua Industrial')->set('form.numero', '100')
        ->set('form.bairro', 'Centro')->set('form.codigo_municipio', '3538709')
        ->set('form.municipio', 'Piracicaba')->set('form.uf', 'SP')->set('form.cep', '13400000')
        ->set('form.e_cliente', true)
        ->set('form.observacoes', '  Paga sempre no dia 10. Falar com a Ana.  ')
        ->call('salvar')
        ->assertHasNoErrors();

    $pessoa = Pessoa::where('emitente_id', $emitente->id)->sole();

    expect($pessoa->observacoes)->toBe('Paga sempre no dia 10. Falar com a Ana.');

    Livewire::actingAs($user)->test(Cadastro::class)
        ->call('editar', $pessoa->id)
        ->assertSet('form.observacoes', 'Paga sempre no dia 10. Falar com a Ana.')
        ->set('form.observacoes', '')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($pessoa->fresh()->observacoes)->toBeNull();
});
