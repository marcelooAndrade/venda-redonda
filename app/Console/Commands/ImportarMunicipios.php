<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importa UFs e municípios do IBGE. O código IBGE do município é
 * obrigatório na NF-e (cMun) e é o campo que o ViaCEP devolve.
 */
class ImportarMunicipios extends Command
{
    protected $signature = 'fiscal:importar-municipios';

    protected $description = 'Importa UFs e municípios da API oficial do IBGE';

    private const BASE = 'https://servicodados.ibge.gov.br/api/v1/localidades';

    public function handle(): int
    {
        $this->info('Baixando UFs do IBGE...');
        $ufs = Http::timeout(60)->retry(3, 2000)->get(self::BASE.'/estados')->throw()->json();

        DB::table('ufs')->upsert(
            collect($ufs)->map(fn (array $uf): array => [
                'sigla' => $uf['sigla'],
                'nome' => $uf['nome'],
                'codigo_ibge' => (string) $uf['id'],
            ])->all(),
            ['sigla'],
            ['nome', 'codigo_ibge'],
        );
        $this->line('  '.count($ufs).' UFs.');

        $this->info('Baixando municípios do IBGE...');
        $municipios = Http::timeout(120)->retry(3, 2000)->get(self::BASE.'/municipios')->throw()->json();

        collect($municipios)
            ->map(fn (array $m): array => [
                'codigo_ibge' => (string) $m['id'],
                'nome' => $m['nome'],
                'uf' => $m['microrregiao']['mesorregiao']['UF']['sigla']
                    ?? $m['regiao-imediata']['regiao-intermediaria']['UF']['sigla'],
            ])
            ->chunk(500)
            ->each(fn ($lote) => DB::table('municipios')->upsert($lote->all(), ['codigo_ibge'], ['nome', 'uf']));

        $this->line('  '.count($municipios).' municípios.');
        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
