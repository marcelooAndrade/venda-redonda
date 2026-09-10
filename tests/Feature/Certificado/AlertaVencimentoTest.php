<?php

use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\User;
use App\Notifications\CertificadoVencendo;
use App\Services\Fiscal\AlertaVencimentoCertificado;
use Illuminate\Support\Facades\Notification;

function certificadoQueVenceEm(int $dias): EmitenteCertificado
{
    $emitente = Emitente::factory()->create();
    $user = User::factory()->create();
    $user->emitentes()->attach($emitente);

    return EmitenteCertificado::create([
        'emitente_id' => $emitente->id,
        'arquivo_path' => 'certificados/x.pfx.enc',
        'senha' => 'x',
        'titular' => 'RCM DO BRASIL LTDA',
        'cnpj' => '11222333000181',
        'fingerprint' => str_repeat('a', 64),
        'valido_de' => now()->subYear(),
        'valido_ate' => now()->addDays($dias),
        'ativo' => true,
    ]);
}

beforeEach(function () {
    Notification::fake();
    $this->servico = app(AlertaVencimentoCertificado::class);
});

it('alerta nos marcos de 30, 15 e 7 dias', function (int $dias) {
    certificadoQueVenceEm($dias);

    $this->servico->executar();

    Notification::assertSentTimes(CertificadoVencendo::class, 1);
})->with([30, 15, 7]);

it('nao alerta certificado com folga', function () {
    certificadoQueVenceEm(45);

    $this->servico->executar();

    Notification::assertNothingSent();
});

it('nao repete o alerta do mesmo marco', function () {
    certificadoQueVenceEm(15);

    $this->servico->executar();
    $this->servico->executar();

    Notification::assertSentTimes(CertificadoVencendo::class, 1);
});

it('alerta de novo ao cruzar o proximo marco', function () {
    $cert = certificadoQueVenceEm(15);
    $this->servico->executar();

    $cert->forceFill(['valido_ate' => now()->addDays(7)])->save();
    $this->servico->executar();

    Notification::assertSentTimes(CertificadoVencendo::class, 2);
});

it('ignora certificado inativo', function () {
    certificadoQueVenceEm(7)->forceFill(['ativo' => false])->save();

    $this->servico->executar();

    Notification::assertNothingSent();
});

it('avisa quem tem acesso ao emitente', function () {
    $cert = certificadoQueVenceEm(7);
    $usuario = $cert->emitente->users()->first();

    $this->servico->executar();

    Notification::assertSentTo($usuario, CertificadoVencendo::class);
});
