<?php

namespace App\Jobs;

use App\Services\Integrations\GatewayDeLeads;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Leva o lead para o admin pessoal.
 *
 * Vive na fila porque o cadastro não pode esperar rede, nem falhar por causa
 * dela. Se o outro lado estiver fora do ar, a fila tenta de novo.
 */
class EnviarLeads implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param array<int, array<string, mixed>> $leads */
    public function __construct(public readonly array $leads) {}

    /** Espera crescente entre as tentativas: 1 min, 5 min, 15 min, 1 h. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(GatewayDeLeads $gateway): void
    {
        $gateway->enviar($this->leads);
    }
}
