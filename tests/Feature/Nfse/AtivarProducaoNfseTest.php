<?php

use App\Enums\Fiscal\Ambiente;
use App\Models\User;
use App\Services\Nfse\AtivarProducaoNfse;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-09-12 10:00:00');
    $this->emitente = emitenteComNfse();
    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
});

it('exige a senha de producao para ativar', function () {
    expect(fn () => app(AtivarProducaoNfse::class)->ativar($this->emitente->nfse, $this->user))
        ->toThrow(ValidationException::class, 'senha de produção');

    expect($this->emitente->nfse->fresh()->ambiente)->toBe(Ambiente::Homologacao);
});

it('ativa a producao e guarda quem e quando', function () {
    $this->emitente->nfse->update(['senha_producao' => 'segredo-prod']);

    app(AtivarProducaoNfse::class)->ativar($this->emitente->nfse->fresh(), $this->user);

    $config = $this->emitente->nfse->fresh();
    expect($config->ambiente)->toBe(Ambiente::Producao)
        ->and($config->producao_ativada_em?->toDateTimeString())->toBe('2026-09-12 10:00:00')
        ->and($config->producao_ativada_por)->toBe($this->user->id);
});

it('voltar para homologacao nao exige nada e limpa o rastro', function () {
    $this->emitente->nfse->update(['senha_producao' => 'segredo-prod']);
    app(AtivarProducaoNfse::class)->ativar($this->emitente->nfse->fresh(), $this->user);

    app(AtivarProducaoNfse::class)->voltarParaHomologacao($this->emitente->nfse->fresh(), $this->user);

    $config = $this->emitente->nfse->fresh();
    expect($config->ambiente)->toBe(Ambiente::Homologacao)
        ->and($config->producao_ativada_em)->toBeNull()
        ->and($config->producao_ativada_por)->toBeNull();
});
