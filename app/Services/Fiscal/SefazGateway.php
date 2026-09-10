<?php

namespace App\Services\Fiscal;

use App\Models\Emitente;

/**
 * Comunicação com a SEFAZ, atrás de interface.
 *
 * Assim o transmissor é testável sem certificado e sem rede, e trocar de
 * ambiente ou de forma de envio não mexe na regra de negócio.
 */
interface SefazGateway
{
    /** Assina e envia. Pode voltar autorizado direto ou só com recibo. */
    public function enviar(Emitente $emitente, string $xml): RespostaSefaz;

    /** Consulta o resultado de um lote pelo recibo. */
    public function consultarRecibo(Emitente $emitente, string $recibo): RespostaSefaz;

    /**
     * Consulta a situação de uma NF-e pela chave.
     *
     * É o que garante a idempotência: antes de retransmitir depois de um
     * timeout, pergunta-se à SEFAZ se ela já recebeu.
     */
    public function consultarChave(Emitente $emitente, string $chave): RespostaSefaz;

    public function statusServico(Emitente $emitente): RespostaSefaz;
}
