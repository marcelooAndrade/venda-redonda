<?php

namespace App\Services\Integrations;

/**
 * Aviso de lead para fora, atrás de interface.
 *
 * Assim o cadastro e o comando diário são testáveis sem rede, e trocar o
 * destino não mexe em regra de negócio.
 */
interface GatewayDeLeads
{
    /** @param array<int, array<string, mixed>> $leads */
    public function enviar(array $leads): void;
}
