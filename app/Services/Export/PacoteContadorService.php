<?php

namespace App\Services\Export;

use App\Enums\Fiscal\NFeStatus;
use App\Models\Emitente;
use App\Models\Inutilizacao;
use App\Models\Nota;
use App\Models\NotaArquivo;
use App\Models\NotaEntrada;
use App\Models\NotaEvento;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Monta o pacote que a contabilidade precisa para escriturar o período.
 *
 * A organização por pasta é proposital: o contador abre o ZIP e já sabe o
 * que é saída, o que foi cancelado e o que entrou, sem precisar abrir XML
 * por XML para descobrir.
 */
class PacoteContadorService
{
    public function gerar(Emitente $emitente, CarbonInterface $de, CarbonInterface $ate): string
    {
        if ($de->greaterThan($ate)) {
            throw new RuntimeException('O período está invertido: a data inicial é maior que a final.');
        }

        $de = $de->copy()->startOfDay();
        $ate = $ate->copy()->endOfDay();

        $caminho = tempnam(sys_get_temp_dir(), 'contador_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo do pacote.');
        }

        try {
            $notas = $this->notasDoPeriodo($emitente, $de, $ate);

            $this->adicionarNotas($zip, $notas);
            $this->adicionarEventos($zip, $notas);
            $this->adicionarInutilizacoes($zip, $emitente, $de, $ate);
            $this->adicionarEntradas($zip, $emitente, $de, $ate);

            $zip->addFromString('resumo.csv', $this->resumo($notas));
            $zip->addFromString('LEIA-ME.txt', $this->leiaMe($emitente, $de, $ate, $notas->count()));
        } finally {
            $zip->close();
        }

        return $caminho;
    }

    private function notasDoPeriodo(Emitente $emitente, CarbonInterface $de, CarbonInterface $ate)
    {
        return Nota::query()
            ->where('emitente_id', $emitente->getKey())
            ->whereIn('status', [NFeStatus::Autorizada->value, NFeStatus::Cancelada->value])
            ->whereBetween('data_emissao', [$de, $ate])
            ->with('destinatario')
            ->orderBy('numero')
            ->get();
    }

    private function adicionarNotas(ZipArchive $zip, $notas): void
    {
        foreach ($notas as $nota) {
            $xml = $this->xmlDe($nota);

            if ($xml === null) {
                continue;
            }

            $pasta = $nota->status === NFeStatus::Cancelada ? 'canceladas' : 'emitidas';
            $zip->addFromString("{$pasta}/{$nota->chave_acesso}.xml", $xml);
        }
    }

    private function adicionarEventos(ZipArchive $zip, $notas): void
    {
        $eventos = NotaEvento::query()
            ->whereIn('nota_id', $notas->pluck('id'))
            ->whereNotNull('homologado_em')
            ->get();

        foreach ($eventos as $evento) {
            if (blank($evento->xml_path) || ! Storage::disk('fiscal')->exists($evento->xml_path)) {
                continue;
            }

            $pasta = $evento->tipo === '110110' ? 'cartas-de-correcao' : 'eventos';
            $zip->addFromString(
                "{$pasta}/{$evento->nota->chave_acesso}-seq{$evento->sequencia}.xml",
                (string) Storage::disk('fiscal')->get($evento->xml_path),
            );
        }
    }

    private function adicionarInutilizacoes(ZipArchive $zip, Emitente $emitente, CarbonInterface $de, CarbonInterface $ate): void
    {
        $inutilizacoes = Inutilizacao::query()
            ->where('emitente_id', $emitente->getKey())
            ->whereNotNull('homologada_em')
            ->whereBetween('homologada_em', [$de, $ate])
            ->get();

        foreach ($inutilizacoes as $i) {
            if (blank($i->xml_path) || ! Storage::disk('fiscal')->exists($i->xml_path)) {
                continue;
            }

            $zip->addFromString(
                "inutilizacoes/serie{$i->serie}-{$i->numero_inicial}-a-{$i->numero_final}.xml",
                (string) Storage::disk('fiscal')->get($i->xml_path),
            );
        }
    }

    private function adicionarEntradas(ZipArchive $zip, Emitente $emitente, CarbonInterface $de, CarbonInterface $ate): void
    {
        $entradas = NotaEntrada::query()
            ->where('emitente_id', $emitente->getKey())
            ->whereBetween('data_emissao', [$de, $ate])
            ->get();

        foreach ($entradas as $entrada) {
            if (blank($entrada->xml_path) || ! Storage::disk('fiscal')->exists($entrada->xml_path)) {
                continue;
            }

            $zip->addFromString(
                "entradas/{$entrada->chave_acesso}.xml",
                (string) Storage::disk('fiscal')->get($entrada->xml_path),
            );
        }
    }

    private function xmlDe(Nota $nota): ?string
    {
        $arquivo = NotaArquivo::query()
            ->where('nota_id', $nota->getKey())
            ->whereIn('tipo', ['protocolado', 'assinado', 'gerado'])
            ->orderByRaw("CASE tipo WHEN 'protocolado' THEN 0 WHEN 'assinado' THEN 1 ELSE 2 END")
            ->first();

        if ($arquivo === null || ! Storage::disk('fiscal')->exists($arquivo->path)) {
            return null;
        }

        return (string) Storage::disk('fiscal')->get($arquivo->path);
    }

    private function resumo($notas): string
    {
        $linhas = ['numero;serie;chave;emissao;destinatario;cnpj_cpf;situacao;valor_produtos;valor_icms;valor_ipi;valor_nota'];

        foreach ($notas as $n) {
            $linhas[] = implode(';', [
                $n->numero,
                $n->serie,
                $n->chave_acesso,
                $n->data_emissao->format('d/m/Y'),
                str_replace(';', ',', (string) $n->destinatario?->razao_social),
                (string) $n->destinatario?->documento,
                $n->status->rotulo(),
                number_format((float) $n->valor_produtos, 2, ',', ''),
                number_format((float) $n->valor_icms, 2, ',', ''),
                number_format((float) $n->valor_ipi, 2, ',', ''),
                number_format((float) $n->valor_nota, 2, ',', ''),
            ]);
        }

        // BOM para o Excel abrir com acento correto.
        return "\u{FEFF}".implode("\n", $linhas);
    }

    private function leiaMe(Emitente $emitente, CarbonInterface $de, CarbonInterface $ate, int $quantidade): string
    {
        return <<<TXT
        Pacote da contabilidade
        =======================

        Emitente : {$emitente->razao_social}
        CNPJ     : {$emitente->cnpj}
        Período  : {$de->format('d/m/Y')} a {$ate->format('d/m/Y')}
        Gerado em: {$de->copy()->setTimeFrom(now())->format('d/m/Y H:i')}

        Notas de saída no período: {$quantidade}

        Pastas
        ------
        emitidas/            XML autorizado das notas de saída
        canceladas/          XML das notas que foram canceladas
        eventos/             Eventos de cancelamento homologados
        cartas-de-correcao/  Cartas de correção homologadas
        inutilizacoes/       Faixas de numeração inutilizadas
        entradas/            XML das notas de fornecedores importadas

        resumo.csv           Planilha com uma linha por nota de saída

        Observação: o XML é o documento fiscal. O DANFE é apenas a
        representação impressa e não substitui o arquivo.
        TXT;
    }
}
