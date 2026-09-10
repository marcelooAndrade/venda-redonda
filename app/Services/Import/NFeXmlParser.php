<?php

namespace App\Services\Import;

use App\Support\Gtin;
use Carbon\Carbon;
use DOMDocument;
use RuntimeException;
use SimpleXMLElement;

/**
 * Interpreta o XML de uma NF-e.
 *
 * Classe pura: não toca em banco e não conhece o domínio de quem a usa.
 * A lógica defensiva veio do `NFeImporter` do app-transm, onde vivia dentro
 * de uma classe de 1474 linhas junto com persistência e regra de negócio.
 * Ver docs/analise-app-transm.md.
 */
class NFeXmlParser
{
    private const NAMESPACE_NFE = 'http://www.portalfiscal.inf.br/nfe';

    private const LIMITE_BYTES = 20 * 1024 * 1024;

    public function parse(string $xml): NotaImportada
    {
        $xml = trim($xml);

        if (strlen($xml) > self::LIMITE_BYTES) {
            throw new RuntimeException('O XML ultrapassa o limite permitido de 20 MB.');
        }

        // DOCTYPE abre porta para XXE, que leria arquivos do servidor.
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new RuntimeException('O XML informado possui uma estrutura insegura e foi recusado.');
        }

        $sx = $this->carregar($xml);

        if ($this->pareceCte($sx)) {
            throw new RuntimeException('Este arquivo é um CT-e, não uma NF-e de produto.');
        }

        $inf = $this->localizarInfNFe($sx, $xml);

        if ($inf === null) {
            throw new RuntimeException('Estrutura infNFe não localizada. O arquivo não parece uma NF-e.');
        }

        $protocolo = $this->protocolo($sx);

        // Só nota autorizada entra no estoque: o protocolo é a prova disso.
        if ($protocolo['numero'] === null || $protocolo['cStat'] !== '100') {
            throw new RuntimeException(
                'Este XML não traz protocolo de autorização. Só é possível importar nota autorizada pela SEFAZ.'
            );
        }

        $ide = $inf->ide ?? null;

        return new NotaImportada(
            chave: $this->chave($inf, $sx) ?? throw new RuntimeException('Chave de acesso não localizada.'),
            numero: (string) ($ide->nNF ?? ''),
            serie: (string) ($ide->serie ?? ''),
            dataEmissao: Carbon::parse((string) ($ide->dhEmi ?? $ide->dEmi ?? 'now')),
            naturezaOperacao: (string) ($ide->natOp ?? ''),
            emitente: $this->pessoa($inf->emit ?? null, 'enderEmit'),
            destinatario: $this->pessoa($inf->dest ?? null, 'enderDest'),
            itens: $this->itens($inf),
            totais: $this->totais($inf),
            protocolo: $protocolo['numero'],
            cStat: $protocolo['cStat'],
            xml: $xml,
        );
    }

    private function carregar(string $xml): SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);

        try {
            $sx = simplexml_load_string(
                $xml,
                SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        if ($sx === false) {
            throw new RuntimeException('XML inválido ou não reconhecido.');
        }

        return $sx;
    }

    private function pareceCte(SimpleXMLElement $sx): bool
    {
        return str_contains($sx->getName(), 'cte')
            || str_contains($sx->asXML() ?: '', 'portalfiscal.inf.br/cte');
    }

    /**
     * Quatro estratégias em cascata: acesso direto, filhos com namespace,
     * XPath e, por último, DOMDocument. XML de NF-e chega de origens muito
     * diferentes, e nem todas declaram o namespace do mesmo jeito.
     */
    private function localizarInfNFe(SimpleXMLElement $sx, string $xml): ?SimpleXMLElement
    {
        if (isset($sx->NFe->infNFe)) {
            return $sx->NFe->infNFe;
        }

        if (isset($sx->infNFe)) {
            return $sx->infNFe;
        }

        $filhos = $sx->children(self::NAMESPACE_NFE);

        if (isset($filhos->NFe->infNFe)) {
            return $filhos->NFe->infNFe;
        }

        $sx->registerXPathNamespace('nfe', self::NAMESPACE_NFE);
        $nodes = $sx->xpath('//nfe:infNFe');

        if (! empty($nodes)) {
            return $nodes[0];
        }

        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);

        try {
            $carregou = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        if ($carregou) {
            $lista = $dom->getElementsByTagName('infNFe');

            if ($lista->length > 0) {
                return simplexml_import_dom($lista->item(0));
            }
        }

        return null;
    }

    private function chave(SimpleXMLElement $inf, SimpleXMLElement $sx): ?string
    {
        foreach ([(string) ($inf['Id'] ?? ''), (string) ($sx->protNFe->infProt->chNFe ?? '')] as $bruto) {
            $digitos = preg_replace('/\D/', '', $bruto) ?? '';

            if (strlen($digitos) === 44) {
                return $digitos;
            }
        }

        return null;
    }

    /** @return array{numero: ?string, cStat: ?string} */
    private function protocolo(SimpleXMLElement $sx): array
    {
        $prot = $sx->protNFe->infProt ?? null;

        if ($prot === null) {
            $sx->registerXPathNamespace('nfe', self::NAMESPACE_NFE);
            $nodes = $sx->xpath('//nfe:protNFe/nfe:infProt');
            $prot = $nodes[0] ?? null;
        }

        return [
            'numero' => filled($prot->nProt ?? null) ? (string) $prot->nProt : null,
            'cStat' => filled($prot->cStat ?? null) ? (string) $prot->cStat : null,
        ];
    }

    /** @return array<string, mixed> */
    private function pessoa(?SimpleXMLElement $node, string $chaveEndereco): array
    {
        if ($node === null) {
            return [];
        }

        $end = $node->{$chaveEndereco} ?? null;

        return [
            'cnpj' => filled($node->CNPJ ?? null) ? (string) $node->CNPJ : null,
            'cpf' => filled($node->CPF ?? null) ? (string) $node->CPF : null,
            'razao_social' => (string) ($node->xNome ?? ''),
            'nome_fantasia' => filled($node->xFant ?? null) ? (string) $node->xFant : null,
            'inscricao_estadual' => filled($node->IE ?? null) ? (string) $node->IE : null,
            'ind_ie_dest' => filled($node->indIEDest ?? null) ? (string) $node->indIEDest : null,
            'logradouro' => (string) ($end->xLgr ?? ''),
            'numero' => (string) ($end->nro ?? ''),
            'complemento' => filled($end->xCpl ?? null) ? (string) $end->xCpl : null,
            'bairro' => (string) ($end->xBairro ?? ''),
            'codigo_municipio' => (string) ($end->cMun ?? ''),
            'municipio' => (string) ($end->xMun ?? ''),
            'uf' => (string) ($end->UF ?? ''),
            'cep' => preg_replace('/\D/', '', (string) ($end->CEP ?? '')),
            'telefone' => filled($end->fone ?? null) ? (string) $end->fone : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function itens(SimpleXMLElement $inf): array
    {
        $itens = [];

        foreach ($inf->det ?? [] as $det) {
            $prod = $det->prod ?? null;

            if ($prod === null) {
                continue;
            }

            $quantidade = (float) ($prod->qCom ?? 0);
            $valorProduto = (float) ($prod->vProd ?? 0);
            $frete = (float) ($prod->vFrete ?? 0);
            $seguro = (float) ($prod->vSeg ?? 0);
            $outros = (float) ($prod->vOutro ?? 0);
            $desconto = (float) ($prod->vDesc ?? 0);

            $imposto = $det->imposto ?? null;
            $icms = $this->primeiroFilho($imposto->ICMS ?? null);
            $ipi = $this->primeiroFilho($imposto->IPI->IPITrib ?? null) ?? ($imposto->IPI->IPITrib ?? null);
            $valorIpi = (float) ($imposto->IPI->IPITrib->vIPI ?? 0);

            $gtin = (string) ($prod->cEAN ?? '');

            $itens[] = [
                'numero' => (int) ($det['nItem'] ?? 0),
                'codigo' => (string) ($prod->cProd ?? ''),
                'descricao' => (string) ($prod->xProd ?? ''),
                'gtin' => (strcasecmp($gtin, Gtin::SEM_GTIN) === 0 || $gtin === '') ? null : $gtin,
                'ncm' => (string) ($prod->NCM ?? ''),
                'cest' => filled($prod->CEST ?? null) ? (string) $prod->CEST : null,
                'cfop' => (string) ($prod->CFOP ?? ''),
                'unidade' => (string) ($prod->uCom ?? ''),
                'unidade_tributavel' => (string) ($prod->uTrib ?? ''),
                'quantidade' => $quantidade,
                'quantidade_tributavel' => (float) ($prod->qTrib ?? $quantidade),
                'valor_unitario' => (float) ($prod->vUnCom ?? 0),
                'valor_total' => $valorProduto,
                'valor_frete' => $frete,
                'valor_seguro' => $seguro,
                'valor_desconto' => $desconto,
                'valor_outros' => $outros,
                'origem' => filled($icms->orig ?? null) ? (string) $icms->orig : null,
                'cst_icms' => filled($icms->CST ?? null) ? (string) $icms->CST : (filled($icms->CSOSN ?? null) ? (string) $icms->CSOSN : null),
                'valor_icms' => (float) ($icms->vICMS ?? 0),
                'valor_ipi' => $valorIpi,
                // Custo de entrada: o que de fato foi pago pela mercadoria,
                // rateando frete, IPI e despesas, menos desconto.
                'custo_unitario' => $quantidade > 0
                    ? round(($valorProduto + $frete + $seguro + $outros + $valorIpi - $desconto) / $quantidade, 4)
                    : 0.0,
            ];
        }

        return $itens;
    }

    /** O grupo de ICMS vem num filho cujo nome varia: ICMS00, ICMS20, ICMSSN101... */
    private function primeiroFilho(?SimpleXMLElement $node): ?SimpleXMLElement
    {
        if ($node === null) {
            return null;
        }

        foreach ($node->children() as $filho) {
            return $filho;
        }

        return null;
    }

    /** @return array<string, float> */
    private function totais(SimpleXMLElement $inf): array
    {
        $t = $inf->total->ICMSTot ?? null;

        return [
            'base_icms' => (float) ($t->vBC ?? 0),
            'valor_icms' => (float) ($t->vICMS ?? 0),
            'valor_produtos' => (float) ($t->vProd ?? 0),
            'valor_frete' => (float) ($t->vFrete ?? 0),
            'valor_seguro' => (float) ($t->vSeg ?? 0),
            'valor_desconto' => (float) ($t->vDesc ?? 0),
            'valor_ipi' => (float) ($t->vIPI ?? 0),
            'valor_pis' => (float) ($t->vPIS ?? 0),
            'valor_cofins' => (float) ($t->vCOFINS ?? 0),
            'valor_outros' => (float) ($t->vOutro ?? 0),
            'valor_nota' => (float) ($t->vNF ?? 0),
        ];
    }
}
