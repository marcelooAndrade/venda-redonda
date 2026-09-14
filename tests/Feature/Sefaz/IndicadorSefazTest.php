<?php

/**
 * O indicador no topo do layout fiscal. Lê a última consulta guardada pelo
 * MonitorSefaz e nomeia o autorizador pela UF do emitente.
 */

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\User;
use App\Services\Fiscal\MonitorSefaz;
use App\Services\Fiscal\RespostaSefaz;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\PermissionRegistrar;

function entrarComEmitente(array $atributos = [], bool $comCertificado = true): Emitente
{
    test()->seed(PerfilSeeder::class);

    $emitente = Emitente::factory()->create(['uf' => 'SP', ...$atributos]);

    if ($comCertificado) {
        EmitenteCertificado::create([
            'emitente_id' => $emitente->id,
            'arquivo_path' => 'certificados/x.pfx.enc',
            'senha' => 'x',
            'titular' => 'RCM DO BRASIL LTDA',
            'cnpj' => '11222333000181',
            'fingerprint' => str_repeat('a', 64),
            'valido_de' => now()->subYear(),
            'valido_ate' => now()->addYear(),
            'ativo' => true,
        ]);
    }

    $user = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $user->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $user->assignRole(Perfil::Administrador->value);

    test()->actingAs($user);

    return $emitente;
}

it('mostra em operacao quando a ultima consulta deu 107', function () {
    $emitente = entrarComEmitente();
    comGateway([]);
    app(MonitorSefaz::class)->consultar($emitente);

    $html = $this->get(route('dashboard'))->getContent();

    expect($html)->toContain('SEFAZ-SP em operação')
        ->and($html)->toContain('bg-success-600')
        ->and($html)->toContain('cStat 107: Servico em Operacao. Consultado às');
});

it('sem consulta ainda, diz que nao foi consultada e nao afirma que esta em operacao', function () {
    entrarComEmitente();

    $html = $this->get(route('dashboard'))->getContent();

    expect($html)->toContain('SEFAZ-SP não consultada')
        ->and($html)->not->toContain('SEFAZ-SP em operação');
});

it('sem certificado, diz sem certificado', function () {
    entrarComEmitente(comCertificado: false);

    $html = $this->get(route('dashboard'))->getContent();

    expect($html)->toContain('SEFAZ-SP sem certificado')
        ->and($html)->toContain('Envie o certificado A1 do emitente');
});

it('paralisada aparece em vermelho com o motivo da sefaz', function () {
    $emitente = entrarComEmitente();
    comGateway(['statusServico' => new RespostaSefaz('109', 'Servico Paralisado sem Previsao')]);
    app(MonitorSefaz::class)->consultar($emitente);

    $html = $this->get(route('dashboard'))->getContent();

    expect($html)->toContain('SEFAZ-SP paralisada')
        ->and($html)->toContain('bg-danger-600')
        ->and($html)->toContain('cStat 109: Servico Paralisado sem Previsao.');
});

it('nomeia o autorizador pela uf do emitente, nao por SP fixo', function () {
    entrarComEmitente(['uf' => 'MG']);

    $html = $this->get(route('dashboard'))->getContent();

    expect($html)->toContain('SEFAZ-MG não consultada')
        ->and($html)->not->toContain('SEFAZ-SP');
});
