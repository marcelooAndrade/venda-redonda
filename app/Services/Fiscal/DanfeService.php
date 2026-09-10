<?php

namespace App\Services\Fiscal;

use App\Models\Nota;
use App\Models\NotaArquivo;
use App\Models\NotaEvento;
use Illuminate\Support\Facades\Storage;
use NFePHP\DA\NFe\Daevento;
use NFePHP\DA\NFe\Danfe;
use RuntimeException;

/**
 * Gera o DANFE e o documento auxiliar de evento.
 *
 * O DANFE é feito do XML **protocolado**: é ele que carrega o número do
 * protocolo, sem o qual a via impressa não vale como documento auxiliar.
 */
class DanfeService
{
    public function gerar(Nota $nota): string
    {
        $xml = $this->xmlProtocolado($nota);

        $danfe = new Danfe($xml);
        $danfe->obsContShow(false);
        $danfe->setExibirEmailDestinatario(true);

        $pdf = $danfe->render($this->logo($nota));

        $this->guardar($nota, 'danfe', $pdf, 'pdf');

        return $pdf;
    }

    /** Documento auxiliar do evento: carta de correção ou cancelamento. */
    public function gerarEvento(NotaEvento $evento): string
    {
        $nota = $evento->nota;
        $xmlEvento = $this->xmlDoEvento($evento);

        $da = new Daevento($xmlEvento, $this->dadosEmitente($nota));

        return $da->render($this->logo($nota));
    }

    /** Rascunho para conferência antes de transmitir, sem valor fiscal. */
    public function previa(Nota $nota, string $xmlNaoProtocolado): string
    {
        $danfe = new Danfe($xmlNaoProtocolado);
        $danfe->obsContShow(false);

        return $danfe->render($this->logo($nota));
    }

    private function xmlProtocolado(Nota $nota): string
    {
        $arquivo = NotaArquivo::query()
            ->where('nota_id', $nota->getKey())
            ->where('tipo', 'protocolado')
            ->latest('id')
            ->first();

        if ($arquivo === null || ! Storage::disk('fiscal')->exists($arquivo->path)) {
            throw new RuntimeException(
                'O XML protocolado desta nota não foi encontrado. O DANFE só pode ser gerado '
                .'a partir do XML com protocolo de autorização.'
            );
        }

        return (string) Storage::disk('fiscal')->get($arquivo->path);
    }

    private function xmlDoEvento(NotaEvento $evento): string
    {
        if (blank($evento->xml_path) || ! Storage::disk('fiscal')->exists($evento->xml_path)) {
            throw new RuntimeException('O XML deste evento não foi encontrado.');
        }

        return (string) Storage::disk('fiscal')->get($evento->xml_path);
    }

    /** @return array<string, string> */
    private function dadosEmitente(Nota $nota): array
    {
        $e = $nota->emitente;

        return [
            'razao' => (string) $e->razao_social,
            'logradouro' => (string) $e->logradouro,
            'numero' => (string) $e->numero,
            'complemento' => (string) $e->complemento,
            'bairro' => (string) $e->bairro,
            'CEP' => (string) $e->cep,
            'municipio' => (string) $e->municipio,
            'UF' => (string) $e->uf,
            'telefone' => (string) $e->telefone,
            'email' => (string) $e->email,
        ];
    }

    private function logo(Nota $nota): string
    {
        $path = $nota->emitente->logo_path;

        if (blank($path) || ! Storage::disk('fiscal')->exists($path)) {
            return '';
        }

        return 'data://text/plain;base64,'.base64_encode((string) Storage::disk('fiscal')->get($path));
    }

    private function guardar(Nota $nota, string $tipo, string $conteudo, string $extensao): void
    {
        $path = "notas/{$nota->emitente_id}/".$nota->data_emissao->format('Y/m')."/{$nota->chave_acesso}-{$tipo}.{$extensao}";

        Storage::disk('fiscal')->put($path, $conteudo);

        NotaArquivo::create([
            'nota_id' => $nota->getKey(),
            'tipo' => $tipo,
            'path' => $path,
            'sha256' => hash('sha256', $conteudo),
            'created_at' => now(),
        ]);
    }
}
