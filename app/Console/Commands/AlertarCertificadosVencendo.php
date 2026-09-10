<?php

namespace App\Console\Commands;

use App\Services\Fiscal\AlertaVencimentoCertificado;
use Illuminate\Console\Command;

class AlertarCertificadosVencendo extends Command
{
    protected $signature = 'fiscal:alertar-certificados';

    protected $description = 'Avisa sobre certificados A1 que vencem em 30, 15 ou 7 dias';

    public function handle(AlertaVencimentoCertificado $servico): int
    {
        $enviados = $servico->executar();

        $this->info($enviados === 0
            ? 'Nenhum certificado em marco de vencimento.'
            : "{$enviados} certificado(s) em marco de vencimento avisados.");

        return self::SUCCESS;
    }
}
