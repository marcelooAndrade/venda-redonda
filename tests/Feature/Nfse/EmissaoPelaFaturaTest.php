<?php

use App\Enums\Nfse\NfseStatus;
use App\Enums\Perfil;
use App\Livewire\Financeiro\ContasReceber;
use App\Models\EmitenteNfse;
use App\Models\NotaServico;
use App\Models\ServicoNfse;
use App\Models\User;
use App\Services\Nfse\RespostaNfse;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('fiscal');
    $this->seed(PerfilSeeder::class);
    $this->emitente = emitenteComNfse();
    $this->servico = ServicoNfse::create([
        'emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00',
        'descricao_padrao' => 'Consultoria em processos comerciais.',
    ]);
    ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Inativo', 'codigo_servico' => '01.01.00', 'ativo' => false]);
    $this->parcela = parcelaParaNfse($this->emitente);

    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
});

it('o botao so aparece com a nfse ligada', function () {
    $this->actingAs($this->user)->get('/contas-a-receber')->assertOk()->assertSee('Emitir NFS-e');

    EmitenteNfse::query()->update(['habilitado' => false]);
    $this->actingAs($this->user)->get('/contas-a-receber')->assertOk()->assertDontSee('Emitir NFS-e');
});

it('abrir a emissao preenche o servico e a descricao padrao, e so lista servicos ativos', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->call('abrirNfse', $this->parcela->id)
        ->assertSet('nfseParcelaId', $this->parcela->id)
        ->assertSet('nfseServicoId', $this->servico->id)
        ->assertSet('nfseDescricao', 'Consultoria em processos comerciais.')
        ->assertSee('Consultoria')
        ->assertDontSee('Inativo');
});

it('emite pela tela e mostra a nota na linha da parcela', function () {
    comGatewayNfse(['emitir' => nfseAutorizada()]);

    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->call('abrirNfse', $this->parcela->id)
        ->set('nfseDescricao', 'Consultoria de setembro, conforme proposta.')
        ->call('emitirNfse')
        ->assertHasNoErrors()
        ->assertSet('nfseParcelaId', null)
        ->assertSee('NFS-e 700');

    $nota = NotaServico::query()->sole();
    expect($nota->status)->toBe(NfseStatus::Autorizada)
        ->and($nota->descricao)->toBe('Consultoria de setembro, conforme proposta.')
        ->and($nota->emitida_por)->toBe($this->user->id);
});

it('rejeicao fica no formulario e a linha mostra a situacao', function () {
    comGatewayNfse(['emitir' => new RespostaNfse(false, motivo: 'Alíquota inválida', bruto: '<erro>Alíquota inválida</erro>')]);

    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->call('abrirNfse', $this->parcela->id)
        ->call('emitirNfse')
        ->assertHasErrors('nfse')
        ->assertSet('nfseParcelaId', $this->parcela->id)
        ->assertSee('Rejeitada')
        ->assertSee('Tentar de novo');
});

it('sem servico escolhido nao emite', function () {
    $fake = comGatewayNfse(['emitir' => nfseAutorizada()]);

    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->call('abrirNfse', $this->parcela->id)
        ->set('nfseServicoId', null)
        ->call('emitirNfse')
        ->assertHasErrors('nfse');

    expect($fake->chamadas)->toBeEmpty();
});

it('quem nao emite nfse nao abre a emissao', function () {
    $contador = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $contador->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $contador->assignRole(Perfil::Contador->value);

    $this->actingAs($contador)->get('/contas-a-receber')->assertOk()->assertDontSee('Emitir NFS-e');

    Livewire::actingAs($contador)->test(ContasReceber::class)
        ->call('abrirNfse', $this->parcela->id)
        ->assertForbidden();
});

it('parcela autorizada mostra pdf e xml em vez do botao', function () {
    comGatewayNfse(['emitir' => nfseAutorizada()]);
    Livewire::actingAs($this->user)->test(ContasReceber::class)->call('abrirNfse', $this->parcela->id)->call('emitirNfse');

    $nota = NotaServico::query()->sole();

    $this->actingAs($this->user)->get('/contas-a-receber')
        ->assertSee(route('notas-servico.pdf', $nota))
        ->assertSee(route('notas-servico.xml', $nota));
});
