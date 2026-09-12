<?php

use App\Enums\Perfil;
use App\Livewire\Emitentes\Cadastro;
use App\Livewire\Financeiro\ContasReceber;
use App\Models\FaturaParcela;
use App\Services\Integrations\RespostaCep;
use App\Services\Integrations\ViaCepService;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(PerfilSeeder::class));

it('so quem gerencia o emitente abre a tela', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))->get('/emitente')->assertOk();
    $this->actingAs(usuarioMarca(Perfil::Faturamento->value))->get('/emitente')->assertForbidden();
});

it('carrega o que ja existe e mostra o cnpj formatado', function () {
    $user = usuarioMarca(Perfil::Administrador->value);
    $emitente = $user->emitentes()->first();
    $emitente->update(['razao_social' => 'MARCELO ANDRADE LTDA', 'municipio' => 'Araras', 'cnpj' => '11222333000181']);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->assertSet('form.razao_social', 'MARCELO ANDRADE LTDA')
        ->assertSet('form.municipio', 'Araras')
        ->assertSee('11.222.333/0001-81');
});

it('salva inscricao municipal, endereco e chave pix, normalizando cep e uf', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.inscricao_municipal', '44307')
        ->set('form.logradouro', 'Rua Um')
        ->set('form.numero', '10')
        ->set('form.bairro', 'Centro')
        ->set('form.cep', '13600-000')
        ->set('form.codigo_municipio', '3503307')
        ->set('form.municipio', 'Araras')
        ->set('form.uf', 'sp')
        ->set('form.chave_pix', 'financeiro@exemplo.com.br')
        ->call('salvar')
        ->assertHasNoErrors();

    $emitente = $user->emitentes()->first()->fresh();

    expect($emitente->inscricao_municipal)->toBe('44307')
        ->and($emitente->cep)->toBe('13600000')
        ->and($emitente->uf)->toBe('SP')
        ->and($emitente->codigo_municipio)->toBe('3503307')
        ->and($emitente->chave_pix)->toBe('financeiro@exemplo.com.br');
});

it('campo esvaziado vira nulo, e nao string vazia', function () {
    $user = usuarioMarca(Perfil::Administrador->value);
    $user->emitentes()->first()->update(['chave_pix' => 'antiga']);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.chave_pix', '')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($user->emitentes()->first()->fresh()->chave_pix)->toBeNull();
});

it('consulta o cep e preenche logradouro, municipio, uf e ibge', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    $this->mock(ViaCepService::class)
        ->shouldReceive('consultar')
        ->andReturn(new RespostaCep('Rua Joao Grigoleto', 'Distrito Industrial II', 'Araras', 'SP', '3503307'));

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.cep', '13602-200')
        ->call('buscarCep')
        ->assertHasNoErrors()
        ->assertSet('form.logradouro', 'Rua Joao Grigoleto')
        ->assertSet('form.bairro', 'Distrito Industrial II')
        ->assertSet('form.municipio', 'Araras')
        ->assertSet('form.uf', 'SP')
        ->assertSet('form.codigo_municipio', '3503307');
});

it('cep que o viacep nao acha vira erro no campo, sem derrubar a tela', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    $this->mock(ViaCepService::class)
        ->shouldReceive('consultar')
        ->andThrow(new RuntimeException('CEP não encontrado. Preencha o endereço manualmente e escolha o município pela tabela IBGE.'));

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.cep', '99999-999')
        ->call('buscarCep')
        ->assertHasErrors('form.cep');
});

it('recusa cep sem 8 digitos, uf sem 2 letras e crt fora da tabela', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.cep', '1360')
        ->set('form.uf', 'SPX')
        ->set('form.crt', '9')
        ->call('salvar')
        ->assertHasErrors(['form.cep', 'form.uf', 'form.crt']);
});

it('a chave pix salva aqui faz a parcela nascer com cobranca', function () {
    $user = usuarioMarca(Perfil::Administrador->value);
    $user->emitentes()->first()->update(['municipio' => 'Araras']);

    Livewire::actingAs($user)->test(Cadastro::class)
        ->set('form.chave_pix', '11222333000181')
        ->call('salvar')
        ->assertHasNoErrors();

    Livewire::actingAs($user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 3001')
        ->set('valor', '100,00')
        ->set('parcelas', 1)
        ->set('primeiroVencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasNoErrors();

    expect(FaturaParcela::query()->sole()->pix_payload)->toStartWith('000201');
});

it('contas a receber deixa de ter o formulario da chave e aponta para a tela emitente', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/contas-a-receber')
        ->assertOk()
        ->assertDontSee('salvarChavePix')
        ->assertSee(route('emitente'));
});

it('a tela emitente aparece na navegacao de quem gerencia o emitente', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/marca')
        ->assertOk()
        ->assertSee(route('emitente'));
});
