<?php

namespace App\Console\Commands;

use App\Services\Fiscal\MonitorSefaz;
use Illuminate\Console\Command;

class ConsultarStatusSefaz extends Command
{
    protected $signature = 'fiscal:consultar-status-sefaz';

    protected $description = 'Consulta o status do serviço da SEFAZ para cada emitente com certificado e guarda o resultado para o indicador do topo';

    public function handle(MonitorSefaz $monitor): int
    {
        $total = $monitor->consultarTodos();

        $this->info($total === 0
            ? 'Nenhum emitente com certificado válido para consultar.'
            : "{$total} emitente(s) consultado(s).");

        return self::SUCCESS;
    }
}
