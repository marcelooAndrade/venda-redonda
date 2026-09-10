<?php

use App\Models\Emitente;
use App\Models\NotaEntrada;
use App\Models\User;
use App\Services\Import\ImportarArquivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function upload(string $nome, string $conteudo): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nome, $conteudo);
}

function zipCom(array $arquivos): UploadedFile
{
    $caminho = tempnam(sys_get_temp_dir(), 'lote_').'.zip';
    $zip = new ZipArchive;
    $zip->open($caminho, ZipArchive::CREATE);
    foreach ($arquivos as $nome => $conteudo) {
        $zip->addFromString($nome, $conteudo);
    }
    $zip->close();

    return new UploadedFile($caminho, 'lote.zip', 'application/zip', null, true);
}

beforeEach(function () {
    Storage::fake('fiscal');
    $this->emitente = Emitente::factory()->create(['cnpj' => '11222333000181', 'uf' => 'SP']);
    $this->user = User::factory()->create();
    $this->service = app(ImportarArquivos::class);
});

it('importa um xml avulso', function () {
    $r = $this->service->processar([upload('nota.xml', xmlAutorizado())], $this->emitente, $this->user);

    expect($r['importadas'])->toBe(1)->and($r['falhas'])->toBeEmpty();
});

it('importa varios xml de uma vez', function () {
    $outro = str_replace(
        ['0000088211234567897', '<nNF>8821</nNF>'],
        ['0000088221234567890', '<nNF>8822</nNF>'],
        xmlAutorizado(),
    );

    $r = $this->service->processar([
        upload('a.xml', xmlAutorizado()),
        upload('b.xml', $outro),
    ], $this->emitente, $this->user);

    expect($r['importadas'])->toBe(2)->and(NotaEntrada::count())->toBe(2);
});

it('importa xml de dentro de um zip', function () {
    $r = $this->service->processar([zipCom(['nota-8821.xml' => xmlAutorizado()])], $this->emitente, $this->user);

    expect($r['importadas'])->toBe(1);
});

it('um arquivo com erro nao interrompe os demais', function () {
    $r = $this->service->processar([
        upload('quebrado.xml', 'isto nao e xml'),
        upload('boa.xml', xmlAutorizado()),
    ], $this->emitente, $this->user);

    expect($r['importadas'])->toBe(1)
        ->and($r['falhas'])->toHaveCount(1)
        ->and($r['falhas'][0]['arquivo'])->toBe('quebrado.xml');
});

it('relata a duplicidade como falha, sem parar o lote', function () {
    $this->service->processar([upload('a.xml', xmlAutorizado())], $this->emitente, $this->user);

    $r = $this->service->processar([upload('a.xml', xmlAutorizado())], $this->emitente, $this->user);

    expect($r['importadas'])->toBe(0)
        ->and($r['falhas'][0]['erro'])->toContain('já foi importada');
});

it('ignora arquivos que nao sao xml dentro do zip', function () {
    $r = $this->service->processar([
        zipCom(['nota.xml' => xmlAutorizado(), 'leiame.txt' => 'ignore isto']),
    ], $this->emitente, $this->user);

    expect($r['importadas'])->toBe(1)->and($r['falhas'])->toBeEmpty();
});

it('avisa quando o zip nao tem xml algum', function () {
    $r = $this->service->processar([zipCom(['leiame.txt' => 'nada aqui'])], $this->emitente, $this->user);

    expect($r['importadas'])->toBe(0);
})->throws(RuntimeException::class, 'não contém nenhum XML');
