<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Repassa ao cliente o payload que a uazapi mandou para o Nodo. Fila pelo
 * mesmo motivo de EnviarLeads: a uazapi está esperando resposta rápida do
 * receptor do Nodo, e não pode ficar presa esperando o servidor do cliente.
 */
class EnviarWebhookCliente implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $webhookUrl,
        public readonly array $payload,
    ) {}

    /** Espera crescente entre as tentativas: 1 min, 5 min, 15 min, 1 h. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $resposta = Http::timeout(15)->post($this->webhookUrl, $this->payload);

        if ($resposta->failed()) {
            throw new RuntimeException("O webhook do cliente recusou a mensagem. Status {$resposta->status()}.");
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Falha definitiva ao repassar mensagem recebida ao cliente do Nodo.', [
            'webhook_url' => $this->webhookUrl,
            'erro' => $e?->getMessage(),
        ]);
    }
}
