<?php

use App\Livewire\Pessoas\Cadastro;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * O CNPJ válido e ativo cujo CEP a ViaCEP não conhece.
 *
 * Caso real: 47.528.089/0001-27, TRANSM TRANSPORTES, de Urânia/SP, CEP
 * 15760-000. A ReceitaWS devolve tudo em 0,2s. A ViaCEP responde 200 com
 * `{"erro":"true"}` para esse CEP. A consulta de CNPJ deu certo, mas quem
 * clicou vê um erro vermelho no CEP, como se tivesse falhado.
 */
beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    $this->seed(PerfilSeeder::class);

    $this->user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $this->user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $this->user->assignRole('Faturamento');
});

it('preenche o cnpj mesmo quando a viacep nao conhece o cep, sem erro vermelho', function () {
    Http::fake([
        'receitaws.com.br/*' => Http::response([
            'status' => 'OK', 'nome' => 'TRANSM TRANSPORTES LTDA', 'fantasia' => 'WSP TRANSPORTES',
            'situacao' => 'ATIVA', 'logradouro' => 'RUA CURITIBA', 'numero' => '2026',
            'bairro' => 'NOSSA SENHORA DE FATIMA', 'municipio' => 'URANIA', 'uf' => 'SP',
            'cep' => '15.760-000', 'telefone' => '(17) 8227-5365', 'email' => 'x@y.com',
        ]),
        'viacep.com.br/*' => Http::response(['erro' => 'true']),
    ]);

    Livewire::actingAs($this->user)->test(Cadastro::class)
        ->set('form.documento', '47528089000127')
        ->call('buscarCnpj')
        ->assertHasNoErrors()
        ->assertSet('form.razao_social', 'TRANSM TRANSPORTES LTDA')
        ->assertSet('form.municipio', 'URANIA')
        ->assertSet('form.uf', 'SP')
        ->assertSet('form.cep', '15760000');
});

it('o botao de cep, clicado de proposito, ainda avisa quando o cep nao existe', function () {
    Http::fake(['viacep.com.br/*' => Http::response(['erro' => 'true'])]);

    Livewire::actingAs($this->user)->test(Cadastro::class)
        ->set('form.cep', '15760000')
        ->call('buscarCep')
        ->assertHasErrors('cep');
});

it('resolve o codigo ibge pela tabela oficial quando a viacep falha na busca por cnpj', function () {
    // A tabela oficial de municípios é a fonte primária do IBGE. Com ela
    // populada, o código sai dela mesmo sem o ViaCEP.
    DB::table('municipios')->insert([
        'codigo_ibge' => '3556453', 'nome' => 'Urânia', 'uf' => 'SP',
    ]);

    Http::fake([
        'receitaws.com.br/*' => Http::response([
            'status' => 'OK', 'nome' => 'TRANSM TRANSPORTES LTDA', 'situacao' => 'ATIVA',
            'logradouro' => 'RUA CURITIBA', 'numero' => '2026', 'bairro' => 'NSF',
            'municipio' => 'URANIA', 'uf' => 'SP', 'cep' => '15760000',
        ]),
        'viacep.com.br/*' => Http::response(['erro' => 'true']),
    ]);

    Livewire::actingAs($this->user)->test(Cadastro::class)
        ->set('form.documento', '47528089000127')
        ->call('buscarCnpj')
        ->assertHasNoErrors()
        ->assertSet('form.codigo_municipio', '3556453');
});
