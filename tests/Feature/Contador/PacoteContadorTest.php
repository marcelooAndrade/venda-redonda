<?php

use App\Models\User;
use App\Services\Export\PacoteContadorService;
use App\Services\Fiscal\NFeEventService;
use App\Services\Fiscal\NFeTransmitter;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Import\NFeImportService;
use Illuminate\Support\Facades\Storage;

function conteudoDoZip(string $caminho): array
{
    $zip = new ZipArchive;
    $zip->open($caminho);
    $nomes = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nomes[] = $zip->getNameIndex($i);
    }
    $zip->close();

    return $nomes;
}

beforeEach(function () {
    Storage::fake('fiscal');
    $this->user = User::factory()->create();
});

it('inclui o xml das notas emitidas no periodo', function () {
    comGateway(['enviar' => autorizada()]);
    $nota = notaPronta(['numero' => null]);
    app(NFeTransmitter::class)->transmitir($nota, $this->user);

    $zip = app(PacoteContadorService::class)->gerar(
        $nota->emitente, now()->startOfMonth(), now()->endOfMonth(),
    );

    expect(conteudoDoZip($zip))->toContain('emitidas/'.$nota->fresh()->chave_acesso.'.xml');
});

it('separa as canceladas em pasta propria', function () {
    comGateway(['enviar' => autorizada(), 'cancelar' => new RespostaSefaz('135', 'Evento registrado')]);
    $nota = notaPronta(['numero' => null]);
    app(NFeTransmitter::class)->transmitir($nota, $this->user);
    app(NFeEventService::class)->cancelar($nota->fresh(), 'Erro na emissao identificado depois', $this->user);

    $nomes = conteudoDoZip(app(PacoteContadorService::class)->gerar(
        $nota->emitente, now()->startOfMonth(), now()->endOfMonth(),
    ));

    expect(collect($nomes)->filter(fn ($n) => str_starts_with($n, 'canceladas/')))->not->toBeEmpty();
});

it('inclui as notas de entrada importadas', function () {
    $emitente = notaPronta()->emitente;
    $emitente->forceFill(['cnpj' => '11222333000181'])->save();
    app(NFeImportService::class)->importar(xmlAutorizado(), $emitente->fresh(), $this->user);

    $nomes = conteudoDoZip(app(PacoteContadorService::class)->gerar(
        $emitente, now()->startOfYear(), now()->endOfYear(),
    ));

    expect(collect($nomes)->filter(fn ($n) => str_starts_with($n, 'entradas/')))->not->toBeEmpty();
});

it('inclui um resumo legivel do periodo', function () {
    comGateway(['enviar' => autorizada()]);
    $nota = notaPronta(['numero' => null]);
    app(NFeTransmitter::class)->transmitir($nota, $this->user);

    $nomes = conteudoDoZip(app(PacoteContadorService::class)->gerar(
        $nota->emitente, now()->startOfMonth(), now()->endOfMonth(),
    ));

    expect($nomes)->toContain('resumo.csv')->toContain('LEIA-ME.txt');
});

it('ignora nota fora do periodo', function () {
    comGateway(['enviar' => autorizada()]);
    $nota = notaPronta(['numero' => null]);
    app(NFeTransmitter::class)->transmitir($nota, $this->user);

    $nomes = conteudoDoZip(app(PacoteContadorService::class)->gerar(
        $nota->emitente, now()->subYear()->startOfMonth(), now()->subYear()->endOfMonth(),
    ));

    expect(collect($nomes)->filter(fn ($n) => str_starts_with($n, 'emitidas/')))->toBeEmpty();
});

it('recusa periodo invertido', function () {
    app(PacoteContadorService::class)->gerar(notaPronta()->emitente, now(), now()->subMonth());
})->throws(RuntimeException::class, 'período');
