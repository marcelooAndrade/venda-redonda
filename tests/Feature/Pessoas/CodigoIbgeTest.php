<?php

/**
 * O código IBGE do destinatário, que antes de 13/09 só podia vir do ViaCEP.
 *
 * O campo era `readonly` e o ViaCEP nem sempre devolve o código: quando não
 * devolvia, a pessoa ficava travada, sem poder salvar e sem poder digitar.
 * Agora o campo é digitável, a tabela oficial resolve o código a partir de
 * município e UF, e a validação confere o par quando a tabela sabe responder.
 */

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Fiscal\TipoPessoa;
use App\Enums\Perfil;
use App\Livewire\Pessoas\Cadastro;
use App\Models\Emitente;
use App\Models\User;
use App\Services\Integrations\ViaCepService;
use App\Services\Pessoas\ValidarPessoa;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);

    $this->emitente = Emitente::factory()->create();
    $this->usuario = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->usuario->emitentes()->attach($this->emitente);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->emitente->getKey());
    $this->usuario->assignRole(Perfil::Administrador->value);

    $this->actingAs($this->usuario);
    session(['emitente_id' => $this->emitente->getKey()]);
});

function semearAraras(): void
{
    DB::table('municipios')->insert([
        ['codigo_ibge' => '3503307', 'nome' => 'Araras', 'uf' => 'SP'],
        ['codigo_ibge' => '3304557', 'nome' => 'Rio de Janeiro', 'uf' => 'RJ'],
    ]);
}

/** Resposta do ViaCEP sem o campo `ibge`, que é o caso que travava a tela. */
function fakeCepSemIbge(): void
{
    Http::fake(['viacep.com.br/*' => Http::response([
        'cep' => '13600-000',
        'logradouro' => 'Rua das Flores',
        'bairro' => 'Centro',
        'localidade' => 'Araras',
        'uf' => 'SP',
    ], 200)]);
}

it('nao apaga o codigo digitado quando o cep volta sem ibge', function () {
    fakeCepSemIbge();

    Livewire::test(Cadastro::class)
        ->set('form.codigo_municipio', '3503307')
        ->set('form.cep', '13600000')
        ->call('buscarCep', app(ViaCepService::class))
        ->assertSet('form.codigo_municipio', '3503307');
});

it('resolve o codigo pela tabela oficial quando o cep volta sem ibge', function () {
    semearAraras();
    fakeCepSemIbge();

    Livewire::test(Cadastro::class)
        ->set('form.cep', '13600000')
        ->call('buscarCep', app(ViaCepService::class))
        ->assertSet('form.municipio', 'Araras')
        ->assertSet('form.codigo_municipio', '3503307');
});

it('corrige o codigo da cidade anterior quando o municipio muda', function () {
    semearAraras();
    fakeCepSemIbge();

    // Código do Rio no campo, mas o CEP traz Araras: sem a tabela, o par
    // ficaria inconsistente e a SEFAZ rejeitaria a nota.
    Livewire::test(Cadastro::class)
        ->set('form.codigo_municipio', '3304557')
        ->set('form.cep', '13600000')
        ->call('buscarCep', app(ViaCepService::class))
        ->assertSet('form.codigo_municipio', '3503307');
});

it('aceita o codigo digitado quando a tabela oficial nao foi importada', function () {
    expect(DB::table('municipios')->count())->toBe(0);

    $dados = [
        'tipo_pessoa' => TipoPessoa::Juridica->value,
        'documento' => '11222333000181',
        'razao_social' => 'Comércio Araras Ltda',
        'ind_ie_dest' => IndIEDest::Contribuinte->value,
        'inscricao_estadual' => '111222333444',
        'logradouro' => 'Rua das Flores',
        'numero' => '100',
        'bairro' => 'Centro',
        'codigo_municipio' => '3503307',
        'municipio' => 'Araras',
        'uf' => 'SP',
        'cep' => '13600000',
        'e_cliente' => true,
    ];

    $recusou = false;

    try {
        app(ValidarPessoa::class)->validar($dados);
    } catch (ValidationException $e) {
        $recusou = true;
    }

    expect($recusou)->toBeFalse('a validação travou o código digitado sem ter tabela para conferir');
});

it('recusa codigo de outra uf quando a tabela oficial conhece o codigo', function () {
    semearAraras();

    $dados = [
        'tipo_pessoa' => TipoPessoa::Juridica->value,
        'documento' => '11222333000181',
        'razao_social' => 'Comércio Araras Ltda',
        'ind_ie_dest' => IndIEDest::Contribuinte->value,
        'inscricao_estadual' => '111222333444',
        'logradouro' => 'Rua das Flores',
        'numero' => '100',
        'bairro' => 'Centro',
        'codigo_municipio' => '3304557',
        'municipio' => 'Araras',
        'uf' => 'SP',
        'cep' => '13600000',
        'e_cliente' => true,
    ];

    try {
        app(ValidarPessoa::class)->validar($dados);
        $this->fail('a validação deixou passar um código de outra UF');
    } catch (ValidationException $e) {
        expect($e->errors()['codigo_municipio'][0])
            ->toBe('O código 3304557 é de Rio de Janeiro/RJ, e não da UF SP.');
    }
});

it('o campo do codigo ibge nao esta mais bloqueado na tela', function () {
    $html = Livewire::test(Cadastro::class)->html();

    $trecho = substr($html, (int) strpos($html, 'id="p-ibge"'), 220);

    expect($trecho)->not->toContain('readonly')
        ->and($trecho)->not->toContain('disabled');
});
