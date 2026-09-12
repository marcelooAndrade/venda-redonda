<?php

namespace App\Http\Controllers;

use App\Models\NotaServico;
use App\Services\Nfse\NfseEmissor;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF e XML da NFS-e.
 *
 * O PDF vem do SIGISS a cada pedido e nunca é guardado. O XML é o que o
 * SIGISS devolveu, no disco fiscal. A nota precisa ser do emitente em foco:
 * o escopo global só garante o tenant, e matriz e filial dividem o tenant.
 */
class NotaServicoArquivoController extends Controller
{
    public function pdf(NotaServico $nota, NfseEmissor $emissor, EmitenteAtual $emitenteAtual): Response
    {
        $this->conferir($nota, $emitenteAtual);

        try {
            $pdf = $emissor->pdf($nota);
        } catch (ValidationException $e) {
            abort(404, implode(' ', $e->errors()['nfse'] ?? []));
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="nfse-'.$nota->numero_nfse.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function xml(NotaServico $nota, EmitenteAtual $emitenteAtual): StreamedResponse
    {
        $this->conferir($nota, $emitenteAtual);

        abort_unless($nota->temDocumento(), 404);
        abort_if(blank($nota->xml_retorno_path) || ! Storage::disk('fiscal')->exists($nota->xml_retorno_path), 404);

        return Storage::disk('fiscal')->download($nota->xml_retorno_path, "nfse-{$nota->numero_nfse}.xml", [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function conferir(NotaServico $nota, EmitenteAtual $emitenteAtual): void
    {
        Gate::authorize('nfse.ver');

        abort_unless((int) $nota->emitente_id === (int) $emitenteAtual->resolver()?->getKey(), 404);
    }
}
