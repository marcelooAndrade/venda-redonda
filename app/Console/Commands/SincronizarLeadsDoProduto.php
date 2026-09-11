<?php

namespace App\Console\Commands;

use App\Jobs\EnviarLeads;
use App\Services\Integrations\MontadorDeLeads;
use Illuminate\Console\Command;

/**
 * Reenvia todos os tenants para o admin pessoal.
 *
 * O envio do cadastro resolve "entrou agora". Este resolve "ainda está usando":
 * o último acesso muda todo dia, e sem este comando a lista envelheceria.
 *
 * Reenvia todos, inclusive os já marcados como cliente lá: o upsert do outro
 * lado preserva a situação, e mandar tudo mantém um caminho de código só.
 */
class SincronizarLeadsDoProduto extends Command
{
    protected $signature = 'produto:sincronizar-leads';

    protected $description = 'Envia ao admin pessoal a situação atual de todos os tenants';

    public function handle(MontadorDeLeads $montador): int
    {
        $leads = $montador->paraTodos();

        if ($leads === []) {
            $this->info('Nenhum tenant para enviar.');

            return self::SUCCESS;
        }

        EnviarLeads::dispatch($leads);

        $this->info(count($leads).' tenants enviados para a fila.');

        return self::SUCCESS;
    }
}
