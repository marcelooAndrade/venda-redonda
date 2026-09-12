<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Nfse\NfseStatus;
use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\NotaServico;
use App\Models\ServicoNfse;
use App\Models\User;
use App\Services\Nfse\FalhaDeComunicacaoNfse;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('fiscal');
    $this->seed(PerfilSeeder::class);
    $this->emitente = emitenteComNfse();
    $this->servico = ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00']);

    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);

    Storage::disk('fiscal')->put('nfse/x/retorno.xml', '<notafiscal><numero_nf>700</numero_nf></notafiscal>');

    $this->nota = NotaServico::create([
        'emitente_id' => $this->emitente->id,
        'fatura_parcela_id' => parcelaParaNfse($this->emitente)->id,
        'servico_nfse_id' => $this->servico->id,
        'ambiente' => Ambiente::Homologacao,
        'status' => NfseStatus::Autorizada,
        'numero_rps' => 1, 'serie_rps' => '1', 'numero_nfse' => '700', 'serie_nfse' => 'NFE',
        'codigo_servico' => '17.01.00', 'aliquota_iss_bp' => 0, 'iss_retido' => false,
        'descricao' => 'Consultoria', 'valor_centavos' => 100,
        'xml_retorno_path' => 'nfse/x/retorno.xml',
    ]);
});

it('serve o pdf buscado no sigiss, sem cache', function () {
    comGatewayNfse(['pdf' => '%PDF-1.4 nota']);

    $this->actingAs($this->user)->get(route('notas-servico.pdf', $this->nota))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('%PDF-1.4 nota', false);
});

it('serve o xml devolvido pelo sigiss como download', function () {
    $resposta = $this->actingAs($this->user)->get(route('notas-servico.xml', $this->nota));

    $resposta->assertOk()->assertDownload('nfse-700.xml');
});

it('falha do sigiss no pdf vira 404 com a mensagem, nao 500', function () {
    comGatewayNfse(['pdf' => new FalhaDeComunicacaoNfse('Falha de comunicação com o SIGISS ao obter o PDF da NFS-e: timeout')]);

    $this->actingAs($this->user)->get(route('notas-servico.pdf', $this->nota))->assertNotFound();
});

it('nota sem documento nao tem pdf nem xml', function () {
    $this->nota->forceFill(['status' => NfseStatus::Rejeitada, 'numero_nfse' => null])->save();
    $fake = comGatewayNfse(['pdf' => '%PDF']);

    $this->actingAs($this->user)->get(route('notas-servico.pdf', $this->nota))->assertNotFound();
    $this->actingAs($this->user)->get(route('notas-servico.xml', $this->nota))->assertNotFound();
    expect($fake->chamadas)->toBeEmpty();
});

it('nota de outro emitente do mesmo tenant e 404, mesmo para administrador', function () {
    $outro = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->nota->forceFill(['emitente_id' => $outro->id])->save();
    comGatewayNfse(['pdf' => '%PDF']);

    $this->actingAs($this->user)->get(route('notas-servico.pdf', $this->nota))->assertNotFound();
    $this->actingAs($this->user)->get(route('notas-servico.xml', $this->nota))->assertNotFound();
});

it('sem nfse.ver e 403', function () {
    $estoque = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $estoque->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $estoque->assignRole(Perfil::Estoque->value);

    $this->actingAs($estoque)->get(route('notas-servico.pdf', $this->nota))->assertForbidden();
});
