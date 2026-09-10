<?php

namespace App\Services\Fiscal;

use App\Models\Emitente;
use NFePHP\NFe\Common\Standardize;
use RuntimeException;

/**
 * Comunicação real com a SEFAZ, pela sped-nfe.
 *
 * Fica atrás da interface `SefazGateway` para que o transmissor seja testável
 * sem certificado e sem rede. É a mesma escolha que o `app-transm` faz na
 * distribuição DF-e, e que tornou aquele módulo o mais confiável de lá.
 */
class NfephpSefazGateway implements SefazGateway
{
    public function __construct(
        private readonly NfephpToolsFactory $tools,
    ) {}

    public function enviar(Emitente $emitente, string $xml): RespostaSefaz
    {
        $tools = $this->tools->para($emitente);

        $assinado = $tools->signNFe($xml);
        // idLote arbitrário; a SEFAZ usa só para correlacionar o lote.
        $retorno = $tools->sefazEnviaLote([$assinado], (int) now()->format('ymdHis'), 1);

        $std = $this->padronizar($retorno);

        // Envio síncrono devolve o protocolo direto em protNFe.
        $prot = $std->protNFe->infProt ?? null;

        if ($prot !== null) {
            return new RespostaSefaz(
                cStat: (string) $prot->cStat,
                xMotivo: (string) $prot->xMotivo,
                protocolo: isset($prot->nProt) ? (string) $prot->nProt : null,
                xmlProtocolado: $this->protocolar($tools, $assinado, $retorno),
                chave: isset($prot->chNFe) ? (string) $prot->chNFe : null,
            );
        }

        return new RespostaSefaz(
            cStat: (string) ($std->cStat ?? '999'),
            xMotivo: (string) ($std->xMotivo ?? 'Retorno não reconhecido'),
            recibo: isset($std->infRec->nRec) ? (string) $std->infRec->nRec : null,
        );
    }

    public function consultarRecibo(Emitente $emitente, string $recibo): RespostaSefaz
    {
        $tools = $this->tools->para($emitente);
        $std = $this->padronizar($tools->sefazConsultaRecibo($recibo));

        $prot = $std->protNFe->infProt ?? null;

        return new RespostaSefaz(
            cStat: (string) ($prot->cStat ?? $std->cStat ?? '999'),
            xMotivo: (string) ($prot->xMotivo ?? $std->xMotivo ?? ''),
            protocolo: isset($prot->nProt) ? (string) $prot->nProt : null,
            recibo: $recibo,
            chave: isset($prot->chNFe) ? (string) $prot->chNFe : null,
        );
    }

    public function consultarChave(Emitente $emitente, string $chave): RespostaSefaz
    {
        $tools = $this->tools->para($emitente);
        $std = $this->padronizar($tools->sefazConsultaChave($chave));

        $prot = $std->protNFe->infProt ?? null;

        return new RespostaSefaz(
            cStat: (string) ($prot->cStat ?? $std->cStat ?? '999'),
            xMotivo: (string) ($prot->xMotivo ?? $std->xMotivo ?? ''),
            protocolo: isset($prot->nProt) ? (string) $prot->nProt : null,
            chave: $chave,
        );
    }

    public function statusServico(Emitente $emitente): RespostaSefaz
    {
        $std = $this->padronizar($this->tools->para($emitente)->sefazStatus());

        return new RespostaSefaz(
            cStat: (string) ($std->cStat ?? '999'),
            xMotivo: (string) ($std->xMotivo ?? ''),
        );
    }

    public function cancelar(Emitente $emitente, string $chave, string $protocolo, string $justificativa): RespostaSefaz
    {
        $std = $this->padronizar(
            $this->tools->para($emitente)->sefazCancela($chave, $justificativa, $protocolo)
        );

        return $this->doEvento($std);
    }

    public function cartaCorrecao(Emitente $emitente, string $chave, string $correcao, int $sequencia): RespostaSefaz
    {
        $std = $this->padronizar(
            $this->tools->para($emitente)->sefazCCe($chave, $correcao, $sequencia)
        );

        return $this->doEvento($std);
    }

    public function inutilizar(
        Emitente $emitente,
        int $ano,
        int $serie,
        int $inicial,
        int $final,
        string $justificativa,
    ): RespostaSefaz {
        $std = $this->padronizar(
            $this->tools->para($emitente)->sefazInutiliza($serie, $inicial, $final, $justificativa, $ano)
        );

        return new RespostaSefaz(
            cStat: (string) ($std->infInut->cStat ?? $std->cStat ?? '999'),
            xMotivo: (string) ($std->infInut->xMotivo ?? $std->xMotivo ?? ''),
            protocolo: isset($std->infInut->nProt) ? (string) $std->infInut->nProt : null,
        );
    }

    private function doEvento(object $std): RespostaSefaz
    {
        $ret = $std->retEvento->infEvento ?? $std->infEvento ?? $std;

        return new RespostaSefaz(
            cStat: (string) ($ret->cStat ?? '999'),
            xMotivo: (string) ($ret->xMotivo ?? ''),
            protocolo: isset($ret->nProt) ? (string) $ret->nProt : null,
            chave: isset($ret->chNFe) ? (string) $ret->chNFe : null,
        );
    }

    /** Junta o XML assinado com o protocolo, que é o arquivo de guarda legal. */
    private function protocolar(object $tools, string $assinado, string $retorno): ?string
    {
        try {
            return $tools->addProtocolo($assinado, $retorno);
        } catch (\Throwable) {
            return null;
        }
    }

    private function padronizar(string $retorno): object
    {
        if (trim($retorno) === '') {
            throw new RuntimeException('A SEFAZ devolveu resposta vazia.');
        }

        return (new Standardize)->toStd($retorno);
    }
}
