<?php

/**
 * O indicador "SEFAZ-SP em operação" no topo do layout era texto fixo com
 * uma bolinha verde: nunca consultava nada. Agora ele lê a última consulta
 * ao serviço de status, feita com o certificado do emitente, e mostra o que a
 * SEFAZ respondeu de fato.
 */

use App\Enums\Fiscal\Ambiente;
use App\Enums\Fiscal\EstadoSefaz;
use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\Tenant;
use App\Services\Fiscal\MonitorSefaz;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Fiscal\SituacaoSefaz;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

function emitenteMonitorado(array $atributos = [], bool $certificadoVencido = false): Emitente
{
    $emitente = Emitente::factory()->create(['uf' => 'SP', ...$atributos]);

    EmitenteCertificado::create([
        'emitente_id' => $emitente->id,
        'arquivo_path' => 'certificados/x.pfx.enc',
        'senha' => 'x',
        'titular' => 'RCM DO BRASIL LTDA',
        'cnpj' => '11222333000181',
        'fingerprint' => str_repeat('a', 64),
        'valido_de' => now()->subYear(),
        'valido_ate' => $certificadoVencido ? now()->subDay() : now()->addYear(),
        'ativo' => true,
    ]);

    return $emitente;
}

beforeEach(function () {
    $this->gateway = comGateway([]);
    $this->monitor = app(MonitorSefaz::class);
});

it('sem certificado, a consulta nem chega na sefaz e a situacao e sem certificado', function () {
    $emitente = Emitente::factory()->create();

    $situacao = $this->monitor->consultar($emitente);

    expect($situacao->estado)->toBe(EstadoSefaz::SemCertificado)
        ->and($this->gateway->chamadas)->toBe([]);
});

it('certificado vencido conta como sem certificado', function () {
    $emitente = emitenteMonitorado(certificadoVencido: true);

    expect($this->monitor->consultar($emitente)->estado)->toBe(EstadoSefaz::SemCertificado)
        ->and($this->gateway->chamadas)->toBe([]);
});

it('com certificado e nenhuma consulta feita ainda, a situacao lida e nao consultada', function () {
    $emitente = emitenteMonitorado();

    expect($this->monitor->situacao($emitente)->estado)->toBe(EstadoSefaz::SemConsulta);
});

it('sem certificado e nenhuma consulta, a situacao lida e sem certificado', function () {
    $emitente = Emitente::factory()->create();

    expect($this->monitor->situacao($emitente)->estado)->toBe(EstadoSefaz::SemCertificado);
});

it('cStat 107 vira em operacao e fica guardado para a leitura seguinte', function () {
    $emitente = emitenteMonitorado();

    $this->monitor->consultar($emitente);
    $situacao = $this->monitor->situacao($emitente);

    expect($situacao->estado)->toBe(EstadoSefaz::Operando)
        ->and($situacao->cStat)->toBe('107')
        ->and($situacao->consultadoEm)->not->toBeNull()
        ->and($this->gateway->chamadas)->toBe(['statusServico']);
});

it('qualquer cStat diferente de 107 vira paralisada, com o motivo que a sefaz deu', function () {
    comGateway(['statusServico' => new RespostaSefaz('108', 'Servico Paralisado Momentaneamente (curta duracao)')]);
    $emitente = emitenteMonitorado();

    // O monitor nasce com o gateway do momento, então é resolvido depois do roteiro.
    $situacao = app(MonitorSefaz::class)->consultar($emitente);

    expect($situacao->estado)->toBe(EstadoSefaz::Paralisada)
        ->and($situacao->cStat)->toBe('108')
        ->and($situacao->detalhe)->toContain('Paralisado Momentaneamente');
});

it('falha de comunicacao vira sem resposta, com a mensagem do erro, e tambem fica guardada', function () {
    comGateway(['statusServico' => new RuntimeException('cURL error 28: Connection timed out')]);
    $emitente = emitenteMonitorado();

    app(MonitorSefaz::class)->consultar($emitente);
    $situacao = $this->monitor->situacao($emitente);

    expect($situacao->estado)->toBe(EstadoSefaz::SemResposta)
        ->and($situacao->detalhe)->toContain('Connection timed out');
});

// O cache em banco desserializa com `allowed_classes: false` e um objeto
// guardado volta como `__PHP_Incomplete_Class`. Já aconteceu com a consulta
// de CNPJ; aqui o guarda é este teste.
it('guarda a situacao como array, nunca como objeto', function () {
    $emitente = emitenteMonitorado();

    $this->monitor->consultar($emitente);

    expect(Cache::get(MonitorSefaz::chave($emitente)))->toBeArray();
});

it('a situacao e por ambiente: virar para producao nao reaproveita a leitura de homologacao', function () {
    $emitente = emitenteMonitorado();
    $this->monitor->consultar($emitente);

    $emitente->forceFill(['ambiente' => Ambiente::Producao])->save();

    expect($this->monitor->situacao($emitente)->estado)->toBe(EstadoSefaz::SemConsulta);
});

it('a leitura expira se a consulta parar de rodar, em vez de mostrar um estado velho', function () {
    $emitente = emitenteMonitorado();
    $this->monitor->consultar($emitente);

    $this->travel(MonitorSefaz::VALIDADE_MINUTOS + 1)->minutes();

    expect($this->monitor->situacao($emitente)->estado)->toBe(EstadoSefaz::SemConsulta);
});

it('consultar todos alcanca emitentes de qualquer tenant e pula quem nao tem certificado valido ou esta inativo', function () {
    $outroTenant = Tenant::create(['nome' => 'Outro', 'slug' => 'outro']);
    $daqui = emitenteMonitorado();
    $deOutroTenant = emitenteMonitorado(['tenant_id' => $outroTenant->id]);
    Emitente::factory()->create();
    emitenteMonitorado(certificadoVencido: true);
    emitenteMonitorado(['ativo' => false]);

    $consultados = $this->monitor->consultarTodos();

    expect($consultados)->toBe(2)
        ->and($this->gateway->chamadas)->toBe(['statusServico', 'statusServico'])
        ->and($this->monitor->situacao($daqui)->estado)->toBe(EstadoSefaz::Operando)
        ->and($this->monitor->situacao($deOutroTenant)->estado)->toBe(EstadoSefaz::Operando);
});

it('o rotulo nomeia o autorizador pela uf do emitente', function () {
    $situacao = new SituacaoSefaz(EstadoSefaz::Operando, 'cStat 107: Servico em Operacao.');

    expect($situacao->rotulo('MG'))->toBe('SEFAZ-MG em operação')
        ->and($situacao->rotulo(null))->toBe('SEFAZ em operação');
});

it('a descricao acrescenta a hora da consulta em horario de brasilia', function () {
    $consulta = CarbonImmutable::parse('2026-09-14 13:32:00', 'UTC');
    $situacao = new SituacaoSefaz(EstadoSefaz::Operando, 'cStat 107: Servico em Operacao.', '107', $consulta);

    expect($situacao->descricao())->toBe('cStat 107: Servico em Operacao. Consultado às 10:32.');
});

it('o comando consulta e diz quantos emitentes foram consultados', function () {
    emitenteMonitorado();

    $this->artisan('fiscal:consultar-status-sefaz')
        ->expectsOutputToContain('1 emitente(s) consultado(s)')
        ->assertSuccessful();
});

it('a consulta esta agendada a cada dez minutos, sem sobreposicao', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'fiscal:consultar-status-sefaz'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('*/10 * * * *')
        ->and($evento->withoutOverlapping)->toBeTrue();
});
