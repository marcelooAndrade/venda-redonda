<?php

namespace App\Jobs;

use App\Services\Integrations\GatewayDeConversoes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Leva o evento de conversão do cadastro até o Meta.
 *
 * Vive na fila pelo mesmo motivo do EnviarLeads: o cadastro não pode esperar
 * rede, nem falhar por causa dela.
 */
class EnviarConversaoMeta implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param array<string, mixed> $evento */
    public function __construct(public readonly array $evento) {}

    /** Espera crescente entre as tentativas: 1 min, 5 min, 15 min, 1 h. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(GatewayDeConversoes $gateway): void
    {
        $gateway->enviar($this->evento);
    }

    /**
     * Esgotadas as tentativas, o job some para `failed_jobs` sem mais nenhum
     * aviso. Este log é o último antes do silêncio, mesmo motivo do
     * EnviarLeads.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('Falha definitiva ao enviar conversão de cadastro ao Meta.', [
            'event_id' => $this->evento['event_id'] ?? null,
            'erro' => $e?->getMessage(),
        ]);
    }
}
