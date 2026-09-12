<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Perfil;
use App\Livewire\Nfse\Configuracao;
use App\Models\Emitente;
use App\Models\EmitenteNfse;
use App\Models\ServicoNfse;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->user = usuarioMarca(Perfil::Administrador->value);
    $this->emitente = $this->user->emitentes()->first();
    $this->emitente->update(['codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP', 'inscricao_municipal' => '44307', 'cnpj' => '11222333000181']);
});

function tela(User $user)
{
    return Livewire::actingAs($user)->test(Configuracao::class);
}

it('so quem configura a nfse abre a tela', function () {
    $this->actingAs($this->user)->get('/nfse')->assertOk();
    $this->actingAs(usuarioMarca(Perfil::Faturamento->value))->get('/nfse')->assertForbidden();
});

it('abrir a tela cria a configuracao desligada, em homologacao', function () {
    expect(EmitenteNfse::query()->count())->toBe(0);

    tela($this->user)->assertSet('habilitado', false)->assertSet('serieRps', '1');

    $config = EmitenteNfse::query()->sole();
    expect($config->emitente_id)->toBe($this->emitente->id)->and($config->ambiente)->toBe(Ambiente::Homologacao);
});

it('aponta as pendencias do emitente com link para a tela emitente', function () {
    $this->emitente->update(['codigo_municipio' => '3538709', 'municipio' => 'Piracicaba', 'inscricao_municipal' => null]);

    $this->actingAs($this->user)->get('/nfse')
        ->assertSee('não está em Araras')
        ->assertSee('não tem inscrição municipal')
        ->assertSee(route('emitente'));
});

it('salva a senha cifrada e nunca a devolve no html', function () {
    tela($this->user)
        ->set('senhaHomologacao', 'segredo-hml')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('senhaHomologacao', '');

    $config = EmitenteNfse::query()->sole();
    expect($config->senha(Ambiente::Homologacao))->toBe('segredo-hml')
        ->and(DB::table('emitente_nfse')->value('senha_homologacao'))->not->toContain('segredo-hml');

    $this->actingAs($this->user)->get('/nfse')
        ->assertOk()
        ->assertDontSee('segredo-hml')
        ->assertSee('Senha cadastrada');
});

it('remover a senha zera a coluna', function () {
    EmitenteNfse::create(['emitente_id' => $this->emitente->id, 'senha_homologacao' => 'segredo']);

    tela($this->user)->set('removerSenhaHomologacao', true)->call('salvar')->assertHasNoErrors();

    expect(EmitenteNfse::query()->sole()->senha_homologacao)->toBeNull();
});

it('nao liga a emissao sem a senha do ambiente atual', function () {
    tela($this->user)
        ->set('habilitado', true)
        ->call('salvar')
        ->assertHasErrors('habilitado');

    expect(EmitenteNfse::query()->sole()->habilitado)->toBeFalse();
});

it('liga a emissao e salva serie e proximos rps', function () {
    tela($this->user)
        ->set('habilitado', true)
        ->set('senhaHomologacao', 'segredo')
        ->set('serieRps', '2')
        ->set('proximoRpsHomologacao', 15)
        ->set('proximoRpsProducao', 340)
        ->call('salvar')
        ->assertHasNoErrors();

    $config = EmitenteNfse::query()->sole();
    expect($config->habilitado)->toBeTrue()
        ->and($config->serie_rps)->toBe('2')
        ->and($config->proximo_rps_homologacao)->toBe(15)
        ->and($config->proximo_rps_producao)->toBe(340);
});

it('recusa serie fora do padrao e rps menor que 1', function () {
    tela($this->user)
        ->set('serieRps', 'A1')
        ->set('proximoRpsHomologacao', 0)
        ->call('salvar')
        ->assertHasErrors(['serieRps', 'proximoRpsHomologacao']);
});

it('ativar producao exige a palavra PRODUCAO e a senha de producao', function () {
    tela($this->user)->call('ativarProducao')->assertHasErrors('confirmacaoProducao');

    tela($this->user)->set('confirmacaoProducao', 'PRODUCAO')->call('ativarProducao')->assertHasErrors('ambiente');

    tela($this->user)->set('senhaProducao', 'segredo-prod')->call('salvar')->assertHasNoErrors();
    tela($this->user)->set('confirmacaoProducao', 'PRODUCAO')->call('ativarProducao')->assertHasNoErrors();

    expect(EmitenteNfse::query()->sole()->ambiente)->toBe(Ambiente::Producao);

    tela($this->user)->call('voltarParaHomologacao')->assertHasNoErrors();
    expect(EmitenteNfse::query()->sole()->ambiente)->toBe(Ambiente::Homologacao);
});

it('cadastra um servico com a aliquota digitada em reais de porcento', function () {
    tela($this->user)
        ->call('novoServico')
        ->set('servico.nome', 'Publicidade e propaganda')
        ->set('servico.codigo_servico', '10.08.01')
        ->set('servico.codigo_nbs', '1.1406.20.00')
        ->set('servico.c_class_trib', '000001')
        ->set('servico.ind_op', '050101')
        ->set('servico.aliquota_iss', '2,00')
        ->set('servico.iss_retido', false)
        ->set('servico.descricao_padrao', 'Planejamento e acompanhamento de ações digitais.')
        ->call('salvarServico')
        ->assertHasNoErrors()
        ->assertSet('editandoServico', false);

    $servico = ServicoNfse::query()->sole();
    expect($servico->emitente_id)->toBe($this->emitente->id)
        ->and($servico->aliquota_iss_bp)->toBe(200)
        ->and($servico->codigo_nbs)->toBe('1.1406.20.00')
        ->and($servico->ativo)->toBeTrue();
});

it('recusa codigo fora do formato, nbs invalido e aliquota acima de 5', function () {
    tela($this->user)
        ->call('novoServico')
        ->set('servico.nome', 'Consultoria')
        ->set('servico.codigo_servico', '1701')
        ->set('servico.codigo_nbs', 'abc')
        ->set('servico.aliquota_iss', '5,01')
        ->call('salvarServico')
        ->assertHasErrors(['servico.codigo_servico', 'servico.codigo_nbs']);

    tela($this->user)
        ->call('novoServico')
        ->set('servico.nome', 'Consultoria')
        ->set('servico.codigo_servico', '17.01.00')
        ->set('servico.aliquota_iss', '5,01')
        ->call('salvarServico')
        ->assertHasErrors('servico.aliquota_iss');

    expect(ServicoNfse::query()->count())->toBe(0);
});

it('edita e inativa um servico, e nao aceita nome repetido', function () {
    $servico = ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00', 'aliquota_iss_bp' => 200]);
    ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Desenvolvimento', 'codigo_servico' => '01.01.00']);

    tela($this->user)
        ->call('editarServico', $servico->id)
        ->assertSet('servico.nome', 'Consultoria')
        ->assertSet('servico.aliquota_iss', '2,00')
        ->set('servico.nome', 'Desenvolvimento')
        ->call('salvarServico')
        ->assertHasErrors('servico.nome');

    tela($this->user)
        ->call('editarServico', $servico->id)
        ->set('servico.aliquota_iss', '3,00')
        ->set('servico.ativo', false)
        ->call('salvarServico')
        ->assertHasNoErrors();

    expect($servico->fresh()->aliquota_iss_bp)->toBe(300)->and($servico->fresh()->ativo)->toBeFalse();
});

it('nao edita servico de outro emitente do mesmo tenant', function () {
    $outro = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $servico = ServicoNfse::create(['emitente_id' => $outro->id, 'nome' => 'Alheio', 'codigo_servico' => '17.01.00']);

    tela($this->user)->call('editarServico', $servico->id)->assertNotFound();
    $this->actingAs($this->user)->get('/nfse')->assertDontSee('Alheio');
});
