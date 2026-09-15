<?php

namespace App\Services\Integrations;

/**
 * Envio de evento de conversão para fora, atrás de interface.
 *
 * Assim o cadastro é testável sem rede, e trocar o destino não mexe em regra
 * de negócio. Ver GatewayDeLeads, mesmo motivo.
 */
interface GatewayDeConversoes
{
    /** @param array<string, mixed> $evento */
    public function enviar(array $evento): void;
}
