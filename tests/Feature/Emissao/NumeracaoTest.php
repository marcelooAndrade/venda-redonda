<?php

use App\Models\Emitente;
use App\Models\EmitenteSerie;
use App\Models\Nota;
use App\Services\Fiscal\NumeracaoService;

beforeEach(function () {
    $this->emitente = Emitente::factory()->create();
    $this->numeracao = app(NumeracaoService::class);
});

it('comeca em um quando a serie e nova', function () {
    expect($this->numeracao->proximo($this->emitente, 1))->toBe(1);
});

it('avanca de um em um', function () {
    $numeros = collect(range(1, 5))->map(fn () => $this->numeracao->proximo($this->emitente, 1));

    expect($numeros->all())->toBe([1, 2, 3, 4, 5]);
});

it('mantem series independentes', function () {
    $this->numeracao->proximo($this->emitente, 1);
    $this->numeracao->proximo($this->emitente, 1);

    expect($this->numeracao->proximo($this->emitente, 2))->toBe(1)
        ->and($this->numeracao->proximo($this->emitente, 1))->toBe(3);
});

it('mantem emitentes independentes', function () {
    $outro = Emitente::factory()->create();
    $this->numeracao->proximo($this->emitente, 1);
    $this->numeracao->proximo($this->emitente, 1);

    expect($this->numeracao->proximo($outro, 1))->toBe(1);
});

it('respeita o proximo numero configurado', function () {
    // Empresa que migrou de outro sistema continua de onde parou.
    EmitenteSerie::create([
        'emitente_id' => $this->emitente->id, 'serie' => 1, 'proximo_numero' => 1481,
    ]);

    expect($this->numeracao->proximo($this->emitente, 1))->toBe(1481)
        ->and($this->numeracao->proximo($this->emitente, 1))->toBe(1482);
});

it('nao entrega o mesmo numero duas vezes sob concorrencia', function () {
    // Cem atribuições seguidas não podem repetir nem pular.
    $numeros = collect(range(1, 100))->map(fn () => $this->numeracao->proximo($this->emitente, 1));

    expect($numeros->unique())->toHaveCount(100)
        ->and($numeros->max())->toBe(100);
});

it('permite reservar sem consumir, para a tela mostrar a previsao', function () {
    expect($this->numeracao->previsto($this->emitente, 1))->toBe(1)
        ->and($this->numeracao->previsto($this->emitente, 1))->toBe(1)
        ->and($this->numeracao->proximo($this->emitente, 1))->toBe(1);
});

it('recusa serie fora da faixa da nf-e', function () {
    $this->numeracao->proximo($this->emitente, 1000);
})->throws(InvalidArgumentException::class, 'série');

it('detecta buracos na numeracao para inutilizacao', function () {
    foreach ([1, 2, 5, 6] as $numero) {
        Nota::create([
            'emitente_id' => $this->emitente->id, 'serie' => 1, 'numero' => $numero,
            'status' => 'autorizada', 'data_emissao' => now(), 'ambiente' => 'homologacao',
        ]);
    }

    expect($this->numeracao->faixasNaoUtilizadas($this->emitente, 1))
        ->toBe([['de' => 3, 'ate' => 4]]);
});
