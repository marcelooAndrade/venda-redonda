<?php

use App\Enums\Fiscal\AmbitoOperacao;
use App\Models\Emitente;
use App\Models\NaturezaOperacao;

function natureza(array $extra = []): NaturezaOperacao
{
    return NaturezaOperacao::create(array_merge([
        'emitente_id' => Emitente::factory()->create()->id,
        'descricao' => 'Venda de produção própria',
        'cfop_interno' => '5101',
        'cfop_interestadual' => '6101',
        'fin_nfe' => '1',
        'tipo' => '1',
    ], $extra));
}

it('usa o cfop interno na operacao dentro do estado', function () {
    expect(natureza()->cfopPara(AmbitoOperacao::Interna))->toBe('5101');
});

it('usa o cfop interestadual fora do estado', function () {
    expect(natureza()->cfopPara(AmbitoOperacao::Interestadual))->toBe('6101');
});

it('exige cfop de exportacao para o exterior', function () {
    natureza()->cfopPara(AmbitoOperacao::Exterior);
})->throws(RuntimeException::class, 'exterior');

it('usa o cfop de exportacao quando cadastrado', function () {
    expect(natureza(['cfop_exterior' => '7101'])->cfopPara(AmbitoOperacao::Exterior))->toBe('7101');
});

it('explica quando falta o cfop do ambito', function () {
    natureza(['cfop_interestadual' => null])->cfopPara(AmbitoOperacao::Interestadual);
})->throws(RuntimeException::class, 'Venda de produção própria');

it('sabe que devolucao exige documento referenciado', function () {
    expect(natureza(['fin_nfe' => '4'])->exigeReferencia())->toBeTrue()
        ->and(natureza()->exigeReferencia())->toBeFalse();
});

it('sabe que nota complementar tambem exige referencia', function () {
    expect(natureza(['fin_nfe' => '2'])->exigeReferencia())->toBeTrue();
});

it('separa entrada de saida', function () {
    expect(natureza(['tipo' => '0'])->entrada())->toBeTrue()
        ->and(natureza(['tipo' => '1'])->entrada())->toBeFalse();
});
