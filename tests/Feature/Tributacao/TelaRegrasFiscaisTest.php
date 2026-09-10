<?php

use App\Enums\Perfil;
use App\Livewire\Tributacao\Regras;
use App\Models\AuditLog;
use App\Models\Emitente;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

function usuarioTrib(string $perfil): array
{
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create(['uf' => 'SP', 'crt' => '3']);
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);

    return [$user, $emitente];
}

beforeEach(fn () => $this->seed(PerfilSeeder::class));

it('o contador abre a pagina de regras', function () {
    [$user] = usuarioTrib(Perfil::Contador->value);

    $this->actingAs($user)->get('/regras-fiscais')->assertOk();
});

it('o administrador tambem abre', function () {
    [$user] = usuarioTrib(Perfil::Administrador->value);

    $this->actingAs($user)->get('/regras-fiscais')->assertOk();
});

it('faturamento nao abre a pagina de regras', function () {
    [$user] = usuarioTrib(Perfil::Faturamento->value);

    $this->actingAs($user)->get('/regras-fiscais')->assertForbidden();
});

it('o contador cria um perfil fiscal', function () {
    [$user, $emitente] = usuarioTrib(Perfil::Contador->value);

    Livewire::actingAs($user)->test(Regras::class)
        ->set('perfilNome', 'Peças microfundidas em aço inox')
        ->call('criarPerfil')
        ->assertHasNoErrors();

    expect(PerfilFiscal::where('emitente_id', $emitente->id)->count())->toBe(1);
});

it('o contador escreve a regra com vigencia', function () {
    [$user, $emitente] = usuarioTrib(Perfil::Contador->value);
    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Aço inox']);

    Livewire::actingAs($user)->test(Regras::class)
        ->call('selecionarPerfil', $perfil->id)
        ->set('regra.ambito', 'interna')
        ->set('regra.vigente_de', '2026-09-01')
        ->set('regra.cst_icms', '00')
        ->set('regra.aliquota_icms', '18')
        ->set('regra.observacao_contador', 'Alíquota interna de SP para produto industrializado.')
        ->call('salvarRegra')
        ->assertHasNoErrors();

    $regra = PerfilFiscalRegra::first();
    expect($regra->cst_icms)->toBe('00')
        ->and((float) $regra->aliquota_icms)->toBe(18.0)
        ->and($regra->observacao_contador)->toContain('Alíquota interna');
});

it('exige a data de inicio de vigencia', function () {
    [$user, $emitente] = usuarioTrib(Perfil::Contador->value);
    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Aço inox']);

    Livewire::actingAs($user)->test(Regras::class)
        ->call('selecionarPerfil', $perfil->id)
        ->set('regra.vigente_de', '')
        ->set('regra.cst_icms', '00')
        ->call('salvarRegra')
        ->assertHasErrors('regra.vigente_de');
});

it('encerra a regra anterior do mesmo ambito ao criar a nova', function () {
    [$user, $emitente] = usuarioTrib(Perfil::Contador->value);
    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Aço inox']);
    $antiga = PerfilFiscalRegra::create([
        'perfil_fiscal_id' => $perfil->id, 'ambito' => 'interna',
        'vigente_de' => '2026-01-01', 'cst_icms' => '00', 'aliquota_icms' => 12,
    ]);

    Livewire::actingAs($user)->test(Regras::class)
        ->call('selecionarPerfil', $perfil->id)
        ->set('regra.ambito', 'interna')
        ->set('regra.vigente_de', '2026-09-01')
        ->set('regra.cst_icms', '00')
        ->set('regra.aliquota_icms', '18')
        ->call('salvarRegra');

    // A anterior não é apagada: passa a ter fim de vigência na véspera.
    expect($antiga->fresh()->vigente_ate->toDateString())->toBe('2026-08-31')
        ->and(PerfilFiscalRegra::count())->toBe(2);
});

it('registra na auditoria quem escreveu a regra', function () {
    [$user, $emitente] = usuarioTrib(Perfil::Contador->value);
    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Aço inox']);

    Livewire::actingAs($user)->test(Regras::class)
        ->call('selecionarPerfil', $perfil->id)
        ->set('regra.ambito', 'interna')
        ->set('regra.vigente_de', '2026-09-01')
        ->set('regra.cst_icms', '00')
        ->call('salvarRegra');

    $log = AuditLog::where('auditavel_type', PerfilFiscalRegra::class)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->evento)->toBe('criado')
        ->and($log->user_id)->toBe($user->id);
});
