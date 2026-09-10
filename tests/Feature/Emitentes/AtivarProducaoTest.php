<?php

use App\Enums\Fiscal\Ambiente;
use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\User;
use App\Services\Fiscal\AtivarProducao;
use Illuminate\Validation\ValidationException;

function comCertificadoValido(Emitente $emitente): EmitenteCertificado
{
    return EmitenteCertificado::create([
        'emitente_id' => $emitente->id,
        'arquivo_path' => 'certificados/x.pfx.enc',
        'senha' => 'x',
        'titular' => 'RCM DO BRASIL LTDA',
        'cnpj' => $emitente->cnpj,
        'fingerprint' => str_repeat('b', 64),
        'valido_de' => now()->subMonth(),
        'valido_ate' => now()->addYear(),
        'ativo' => true,
    ]);
}

function comRespTecnico(): void
{
    config()->set('fiscal.responsavel_tecnico', [
        'cnpj' => '11444777000161',
        'contato' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'telefone' => '1996747372',
    ]);
}

beforeEach(function () {
    $this->servico = app(AtivarProducao::class);
    $this->user = User::factory()->create();
    config()->set('fiscal.responsavel_tecnico', ['cnpj' => null, 'contato' => null, 'email' => null, 'telefone' => null]);
});

it('recusa sem certificado ativo', function () {
    comRespTecnico();
    $emitente = Emitente::factory()->create();

    $this->servico->ativar($emitente, $this->user);
})->throws(ValidationException::class, 'certificado');

it('recusa com certificado vencido', function () {
    comRespTecnico();
    $emitente = Emitente::factory()->create();
    comCertificadoValido($emitente)->forceFill(['valido_ate' => now()->subDay()])->save();

    $this->servico->ativar($emitente, $this->user);
})->throws(ValidationException::class, 'vencido');

it('recusa sem responsavel tecnico configurado', function () {
    $emitente = Emitente::factory()->create();
    comCertificadoValido($emitente);

    $this->servico->ativar($emitente, $this->user);
})->throws(ValidationException::class, 'Responsável técnico');

it('ativa quando tudo esta em ordem', function () {
    comRespTecnico();
    $emitente = Emitente::factory()->create();
    comCertificadoValido($emitente);

    $this->servico->ativar($emitente, $this->user);

    expect($emitente->fresh()->ambiente)->toBe(Ambiente::Producao);
});

it('registra quem ativou e quando', function () {
    comRespTecnico();
    $emitente = Emitente::factory()->create();
    comCertificadoValido($emitente);

    $this->servico->ativar($emitente, $this->user);

    expect($emitente->fresh()->producao_ativada_por)->toBe($this->user->id)
        ->and($emitente->fresh()->producao_ativada_em)->not->toBeNull();
});

it('volta para homologacao sem exigir nada', function () {
    comRespTecnico();
    $emitente = Emitente::factory()->create();
    comCertificadoValido($emitente);
    $this->servico->ativar($emitente, $this->user);

    $this->servico->voltarParaHomologacao($emitente, $this->user);

    expect($emitente->fresh()->ambiente)->toBe(Ambiente::Homologacao);
});
