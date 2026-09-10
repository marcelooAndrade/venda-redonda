<?php

use App\Enums\Fiscal\NFeStatus;
use App\Enums\Perfil;
use App\Livewire\Painel\Inicio;
use App\Models\EmitenteCertificado;
use App\Models\Nota;
use App\Models\PerfilFiscalRegra;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->nota = notaPronta();
    $this->emitente = $this->nota->emitente;

    $this->user = User::factory()->create();
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
});

/**
 * Nota crua, só com o que o painel lê. Não usa `notaPronta` de propósito:
 * aqui não interessa item nem tributo, e sim status, valor e data.
 */
function notaCom(array $atributos): Nota
{
    return Nota::create(array_merge([
        'emitente_id' => test()->emitente->id,
        'serie' => 1,
        'ambiente' => 'homologacao',
        'data_emissao' => now(),
    ], $atributos));
}

it('soma o autorizado do mes e nao conta a cancelada', function () {
    notaCom(['status' => NFeStatus::Autorizada, 'valor_nota' => 1000]);
    notaCom(['status' => NFeStatus::Autorizada, 'valor_nota' => 500]);
    notaCom(['status' => NFeStatus::Cancelada, 'valor_nota' => 9999]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSet('mes.autorizadas', 2)
        ->assertSet('mes.faturado', 1500.0);
});

it('ignora nota de mes anterior no total do mes', function () {
    notaCom(['status' => NFeStatus::Autorizada, 'valor_nota' => 1000]);
    notaCom([
        'status' => NFeStatus::Autorizada,
        'valor_nota' => 7777,
        'data_emissao' => now()->subMonthNoOverflow()->startOfMonth(),
    ]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSet('mes.faturado', 1000.0);
});

it('mostra nota travada em processamento como pendencia', function () {
    notaCom(['status' => NFeStatus::EmProcessamento, 'numero' => 42]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('Em processamento')
        ->assertSee('42')
        // O alerta que importa: reemitir uma nota em processamento duplica.
        ->assertSee('duplicidade');
});

it('mostra nota rejeitada como pendencia', function () {
    notaCom(['status' => NFeStatus::Rejeitada, 'x_motivo' => 'Rejeicao 225 falha no schema']);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('Rejeitada')
        ->assertSee('Rejeicao 225 falha no schema');
});

it('avisa quando nenhuma regra fiscal vigora hoje', function () {
    PerfilFiscalRegra::query()->update(['vigente_ate' => now()->subDay()->toDateString()]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('regra fiscal');
});

it('nao avisa de regra quando ha uma vigente hoje', function () {
    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertDontSee('Nenhuma regra fiscal vigora hoje');
});

it('avisa certificado proximo do vencimento', function () {
    EmitenteCertificado::create([
        'emitente_id' => $this->emitente->id,
        'arquivo_path' => 'certificados/x.pfx.enc',
        'senha' => 'x',
        'titular' => 'RCM DO BRASIL LTDA',
        'cnpj' => '11222333000181',
        'fingerprint' => str_repeat('a', 64),
        'valido_de' => now()->subYear(),
        'valido_ate' => now()->addDays(9),
        'ativo' => true,
    ]);

    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('certificado');
});

it('avisa quando nao ha certificado cadastrado', function () {
    Livewire::actingAs($this->user)->test(Inicio::class)
        ->assertSee('Nenhum certificado');
});

it('exige a permissao de relatorio', function () {
    $sem = User::factory()->create();
    $sem->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($sem)->get('/dashboard')->assertForbidden();
});

it('o painel responde na rota do dashboard', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertSee('Painel');
});
