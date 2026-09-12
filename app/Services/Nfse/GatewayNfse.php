<?php

namespace App\Services\Nfse;

use App\Enums\Fiscal\Ambiente;
use App\Models\Emitente;

/**
 * Comunicação com o provedor de NFS-e, atrás de interface.
 *
 * Assim o emissor é testável sem rede, e o provedor nacional entra como
 * outra implementação sem mexer em regra de negócio. Mesmo desenho do
 * `SefazGateway` da NF-e.
 */
interface GatewayNfse
{
    public function emitir(Emitente $emitente, Ambiente $ambiente, string $xml): RespostaNfse;

    public function cancelar(Emitente $emitente, Ambiente $ambiente, string $numero, string $serie, string $motivo): RespostaNfse;

    /** Bytes do PDF. Lança `FalhaDeComunicacaoNfse` se não vier um PDF. */
    public function pdf(Emitente $emitente, Ambiente $ambiente, string $numero, string $serie): string;
}
