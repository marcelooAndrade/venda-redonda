<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Nfse\NfseStatus;
use App\Enums\Perfil;
use App\Livewire\Nfse\Notas;
use App\Models\Emitente;
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
    $this->servico = ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00']);

    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
});

function notaDeTeste(Emitente $emitente, ServicoNfse $servico, NfseStatus $status, array $extra = []): NotaServico
{
    static $rps = 0;
    $parcela = parcelaParaNfse($emitente);

    return NotaServico::create(array_merge([
        'emitente_id' => $emitente->id,
        'fatura_parcela_id' => $parcela->id,
        'servico_nfse_id' => $servico->id,
        'ambiente' => Ambiente::Homologacao,
        'status' => $status,
        'numero_rps' => ++$rps,
        'serie_rps' => '1',
        'numero_nfse' => $status->temDocumento() ? (string) (700 + $rps) : null,
        'serie_nfse' => $status->temDocumento() ? 'NFE' : null,
        'codigo_servico' => '17.01.00', 'aliquota_iss_bp' => 200, 'iss_retido' => false,
        'descricao' => 'Consultoria de setembro', 'valor_centavos' => 150000,
    ], $extra));
}

it('quem nao ve nfse leva 403', function () {
    $estoque = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $estoque->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $estoque->assignRole(Perfil::Estoque->value);

    $this->actingAs($estoque)->get('/notas-servico')->assertForbidden();
});

it('lista as notas com documento, cliente, valor e situacao, e filtra por situacao', function () {
    notaDeTeste($this->emitente, $this->servico, NfseStatus::Autorizada);
    notaDeTeste($this->emitente, $this->servico, NfseStatus::Rejeitada, ['motivo_rejeicao' => 'Alíquota inválida']);

    $this->actingAs($this->user)->get('/notas-servico')
        ->assertOk()
        ->assertSee('NFS-e 701')
        ->assertSee('RPS 2')
        ->assertSee('METALURGICA PIRACICABA LTDA')
        ->assertSee('1.500,00')
        ->assertSee('Alíquota inválida');

    Livewire::actingAs($this->user)->test(Notas::class)
        ->set('situacao', 'autorizada')
        ->assertSee('NFS-e 701')
        ->assertDontSee('RPS 2')
        ->set('situacao', 'rejeitada')
        ->assertSee('RPS 2')
        ->assertDontSee('NFS-e 701');
});

it('nao mostra nota de outro emitente do mesmo tenant', function () {
    $outro = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $outro->update(['codigo_municipio' => '3503307', 'inscricao_municipal' => '1']);
    $servicoOutro = ServicoNfse::create(['emitente_id' => $outro->id, 'nome' => 'Alheio', 'codigo_servico' => '17.01.00']);
    notaDeTeste($outro, $servicoOutro, NfseStatus::Autorizada, ['descricao' => 'Serviço alheio']);

    $this->actingAs($this->user)->get('/notas-servico')->assertOk()->assertDontSee('Serviço alheio');
});

it('cancela pela tela com justificativa', function () {
    $nota = notaDeTeste($this->emitente, $this->servico, NfseStatus::Autorizada);
    comGatewayNfse(['cancelar' => new RespostaNfse(true)]);

    Livewire::actingAs($this->user)->test(Notas::class)
        ->call('abrirCancelamento', $nota->id)
        ->assertSet('cancelandoId', $nota->id)
        ->set('motivoCancelamento', 'Serviço cancelado a pedido do cliente.')
        ->call('cancelar')
        ->assertHasNoErrors()
        ->assertSet('cancelandoId', null);

    expect($nota->fresh()->status)->toBe(NfseStatus::Cancelada);
});

it('justificativa curta e recusa do sigiss ficam no campo, sem cancelar', function () {
    $nota = notaDeTeste($this->emitente, $this->servico, NfseStatus::Autorizada);
    comGatewayNfse(['cancelar' => new RespostaNfse(false, motivo: 'Erro: prazo expirado')]);

    Livewire::actingAs($this->user)->test(Notas::class)
        ->call('abrirCancelamento', $nota->id)
        ->set('motivoCancelamento', 'curta')
        ->call('cancelar')
        ->assertHasErrors('nfse')
        ->set('motivoCancelamento', 'Serviço cancelado a pedido do cliente.')
        ->call('cancelar')
        ->assertHasErrors('nfse');

    expect($nota->fresh()->status)->toBe(NfseStatus::Autorizada);
});

it('quem so ve nao cancela', function () {
    $nota = notaDeTeste($this->emitente, $this->servico, NfseStatus::Autorizada);
    $contador = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $contador->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $contador->assignRole(Perfil::Contador->value);

    Livewire::actingAs($contador)->test(Notas::class)
        ->assertOk()
        ->assertDontSee('Cancelar NFS-e')
        ->call('abrirCancelamento', $nota->id)
        ->assertForbidden();
});

it('a lista aparece na navegacao de quem ve nfse', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertSee(route('notas-servico'));
});
