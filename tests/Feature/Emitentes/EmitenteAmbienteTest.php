<?php

use App\Enums\Fiscal\Ambiente;
use App\Models\Emitente;

it('nasce sempre em homologacao', function () {
    $emitente = Emitente::factory()->create();

    expect($emitente->ambiente)->toBe(Ambiente::Homologacao);
});

it('ignora ambiente vindo de atribuicao em massa', function () {
    // Caminho real de produção: dados de request chegando em create().
    // Factories do Laravel rodam sem guarding, então não servem para este teste.
    $emitente = Emitente::create([
        'razao_social' => 'RCM do Brasil Ltda',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'ambiente' => 'producao',
    ]);

    expect($emitente->ambiente)->toBe(Ambiente::Homologacao);
});
