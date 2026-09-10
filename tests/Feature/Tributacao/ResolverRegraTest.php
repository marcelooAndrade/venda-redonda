<?php

use App\Enums\Fiscal\AmbitoOperacao;
use App\Models\Emitente;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Services\Fiscal\ResolverRegraFiscal;

function regra(PerfilFiscal $perfil, array $extra = []): PerfilFiscalRegra
{
    return PerfilFiscalRegra::create(array_merge([
        'perfil_fiscal_id' => $perfil->id,
        'ambito' => AmbitoOperacao::Interna->value,
        'vigente_de' => '2026-01-01',
        'cst_icms' => '00',
        'aliquota_icms' => 18,
    ], $extra));
}

beforeEach(function () {
    $this->emitente = Emitente::factory()->create();
    $this->perfil = PerfilFiscal::create(['emitente_id' => $this->emitente->id, 'nome' => 'Aço inox venda']);
    $this->resolver = app(ResolverRegraFiscal::class);
});

it('devolve a regra vigente na data', function () {
    $r = regra($this->perfil);

    $achada = $this->resolver->resolver($this->perfil, AmbitoOperacao::Interna, '3', now());

    expect($achada->id)->toBe($r->id);
});

it('ignora regra que ainda nao comecou', function () {
    regra($this->perfil, ['vigente_de' => now()->addMonth()->toDateString()]);

    expect(fn () => $this->resolver->resolver($this->perfil, AmbitoOperacao::Interna, '3', now()))
        ->toThrow(RuntimeException::class);
});

it('ignora regra ja encerrada', function () {
    regra($this->perfil, ['vigente_de' => '2025-01-01', 'vigente_ate' => '2025-12-31']);

    expect(fn () => $this->resolver->resolver($this->perfil, AmbitoOperacao::Interna, '3', now()))
        ->toThrow(RuntimeException::class);
});

it('usa a regra da epoca para nota retroativa', function () {
    $antiga = regra($this->perfil, ['vigente_de' => '2026-01-01', 'vigente_ate' => '2026-06-30', 'aliquota_icms' => 12]);
    regra($this->perfil, ['vigente_de' => '2026-07-01', 'aliquota_icms' => 18]);

    $achada = $this->resolver->resolver($this->perfil, AmbitoOperacao::Interna, '3', '2026-03-15');

    expect($achada->id)->toBe($antiga->id)
        ->and((float) $achada->aliquota_icms)->toBe(12.0);
});

it('separa a regra por ambito', function () {
    regra($this->perfil, ['ambito' => AmbitoOperacao::Interna->value, 'aliquota_icms' => 18]);
    regra($this->perfil, ['ambito' => AmbitoOperacao::Interestadual->value, 'aliquota_icms' => 12]);

    expect((float) $this->resolver->resolver($this->perfil, AmbitoOperacao::Interestadual, '3', now())->aliquota_icms)
        ->toBe(12.0);
});

it('prefere a regra especifica do crt sobre a generica', function () {
    regra($this->perfil, ['crt' => null, 'aliquota_icms' => 18]);
    $especifica = regra($this->perfil, ['crt' => '1', 'csosn' => '101', 'aliquota_icms' => null]);

    expect($this->resolver->resolver($this->perfil, AmbitoOperacao::Interna, '1', now())->id)
        ->toBe($especifica->id);
});

it('cai na regra generica quando nao ha especifica do crt', function () {
    $generica = regra($this->perfil, ['crt' => null]);
    regra($this->perfil, ['crt' => '1', 'csosn' => '101']);

    expect($this->resolver->resolver($this->perfil, AmbitoOperacao::Interna, '3', now())->id)
        ->toBe($generica->id);
});

it('explica quando o contador ainda nao escreveu a regra', function () {
    expect(fn () => $this->resolver->resolver($this->perfil, AmbitoOperacao::Interna, '3', now()))
        ->toThrow(RuntimeException::class, 'Aço inox venda');
});
