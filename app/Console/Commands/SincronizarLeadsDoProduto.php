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

    /**
     * Tamanho de lote que mantém o payload bem abaixo do limite de 1 MB que a
     * rota do outro lado recusa. Sem quebrar em lotes, por volta de três mil
     * tenants num POST só trava a sincronização inteira, todo dia, em
     * silêncio: o comando "roda com sucesso" e nenhum lead chega.
     */
    private const TAMANHO_DO_LOTE = 500;

    public function handle(MontadorDeLeads $montador): int
    {
        $leads = $montador->paraTodos();

        if ($leads === []) {
            $this->info('Nenhum tenant para enviar.');

            return self::SUCCESS;
        }

        $lotes = array_chunk($leads, self::TAMANHO_DO_LOTE);

        foreach ($lotes as $lote) {
            EnviarLeads::dispatch($lote);
        }

        $this->info(sprintf(
            '%d tenants enviados para a fila em %d lote(s).',
            count($leads),
            count($lotes)
        ));

        return self::SUCCESS;
    }
}
