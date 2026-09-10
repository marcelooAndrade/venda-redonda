<?php

namespace App\Services\Fiscal\Tabelas;

/**
 * Interpreta o arquivo oficial de NCM do Portal Único Siscomex.
 *
 * O arquivo é hierárquico: traz capítulo ("01"), posição ("01.01") e
 * o item de oito dígitos ("0101.21.00"). Só o de oito dígitos vale no
 * campo NCM da NF-e; os demais existem para navegação e descrição.
 */
class NcmParser
{
    /** Data_Fim que o arquivo usa para "sem fim de vigência". */
    private const SEM_FIM = '31/12/9999';

    /**
     * @param  array<int, array<string, string|null>>  $nomenclaturas
     * @return array<int, array<string, mixed>>
     */
    public function parse(array $nomenclaturas): array
    {
        $linhas = [];

        foreach ($nomenclaturas as $item) {
            $codigo = preg_replace('/\D/', '', (string) ($item['Codigo'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            $linhas[] = [
                'codigo' => $codigo,
                'descricao' => trim((string) ($item['Descricao'] ?? '')),
                'valido_nfe' => strlen($codigo) === 8,
                'vigente_de' => $this->data($item['Data_Inicio'] ?? null),
                'vigente_ate' => $this->dataFim($item['Data_Fim'] ?? null),
            ];
        }

        return $linhas;
    }

    private function data(?string $valor): ?string
    {
        if (! $valor || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m)) {
            return null;
        }

        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    private function dataFim(?string $valor): ?string
    {
        return $valor === self::SEM_FIM ? null : $this->data($valor);
    }
}
