<?php

namespace App\Console\Commands;

use App\Services\Fiscal\Tabelas\NcmParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importa a tabela NCM vigente do Portal Único Siscomex.
 * A URL responde 307, então é preciso seguir o redirecionamento.
 */
class ImportarNcm extends Command
{
    protected $signature = 'fiscal:importar-ncm';

    protected $description = 'Importa a tabela NCM vigente da fonte oficial (Siscomex)';

    private const URL = 'https://portalunico.siscomex.gov.br/classif/api/publico/nomenclatura/download/json';

    public function handle(NcmParser $parser): int
    {
        $this->info('Baixando tabela NCM oficial...');

        $payload = Http::timeout(180)->retry(3, 3000)->get(self::URL)->throw()->json();

        $this->line('  Vigência informada: '.($payload['Data_Ultima_Atualizacao_NCM'] ?? 'não informada'));
        $this->line('  Ato: '.($payload['Ato'] ?? 'não informado'));

        $linhas = $parser->parse($payload['Nomenclaturas'] ?? []);

        collect($linhas)
            ->chunk(500)
            ->each(fn ($lote) => DB::table('ncms')->upsert(
                $lote->all(),
                ['codigo', 'vigente_de'],
                ['descricao', 'valido_nfe', 'vigente_ate'],
            ));

        $validos = collect($linhas)->where('valido_nfe', true)->count();
        $this->line('  '.count($linhas).' nomenclaturas, sendo '.$validos.' válidas para NF-e.');
        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
