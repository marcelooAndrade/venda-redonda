<?php

namespace App\Services\Import;

use App\Models\Emitente;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Recebe arquivos avulsos ou ZIP e importa cada XML encontrado.
 *
 * Um arquivo com erro não interrompe os demais: em lote de fim de mês, parar
 * no primeiro problema faria o operador reprocessar tudo.
 */
class ImportarArquivos
{
    public function __construct(
        private readonly NFeImportService $importador,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $arquivos
     * @return array{importadas: int, falhas: array<int, array{arquivo: string, erro: string}>}
     */
    public function processar(array $arquivos, Emitente $emitente, ?User $user = null): array
    {
        $importadas = 0;
        $falhas = [];

        foreach ($arquivos as $arquivo) {
            foreach ($this->extrair($arquivo) as $nome => $xml) {
                try {
                    $this->importador->importar($xml, $emitente, $user);
                    $importadas++;
                } catch (Throwable $e) {
                    $falhas[] = ['arquivo' => $nome, 'erro' => $e->getMessage()];
                }
            }
        }

        return ['importadas' => $importadas, 'falhas' => $falhas];
    }

    /**
     * @return array<string, string> nome do arquivo => conteúdo
     */
    private function extrair(UploadedFile $arquivo): array
    {
        $nome = $arquivo->getClientOriginalName();

        if (strcasecmp($arquivo->getClientOriginalExtension(), 'zip') !== 0) {
            return [$nome => (string) $arquivo->get()];
        }

        $zip = new ZipArchive;

        if ($zip->open($arquivo->getRealPath()) !== true) {
            throw new RuntimeException("Não foi possível abrir o arquivo {$nome}.");
        }

        $encontrados = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $interno = $zip->getNameIndex($i);

                if ($interno === false || ! str_ends_with(strtolower($interno), '.xml')) {
                    continue;
                }

                // Ignora entradas com caminho para fora do destino (zip slip).
                if (str_contains($interno, '..')) {
                    continue;
                }

                $conteudo = $zip->getFromIndex($i);

                if ($conteudo !== false) {
                    $encontrados["{$nome} › {$interno}"] = $conteudo;
                }
            }
        } finally {
            $zip->close();
        }

        if ($encontrados === []) {
            throw new RuntimeException("O arquivo {$nome} não contém nenhum XML.");
        }

        return $encontrados;
    }
}
