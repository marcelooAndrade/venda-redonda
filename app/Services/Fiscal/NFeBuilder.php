<?php

namespace App\Services\Fiscal;

use App\Models\Nota;
use App\Models\NotaItem;
use NFePHP\NFe\Make;
use RuntimeException;
use stdClass;

/**
 * Monta o XML da NF-e modelo 55, leiaute 4.00.
 *
 * É o único componente do sistema sem precedente no `app-transm`, que emite
 * CT-e e MDF-e mas apenas importa NF-e. O estilo segue o `CteXmlBuilder` de
 * lá: um método por grupo, o `Make` acumulando, e o render no fim.
 *
 * Nenhuma regra tributária aparece aqui: os valores já vêm calculados pelo
 * TaxCalculator, a partir da regra que o contador escreveu.
 */
class NFeBuilder
{
    public function montar(Nota $nota): string
    {
        $nota->loadMissing(['emitente', 'destinatario', 'itens.produto', 'referencias', 'pagamentos', 'duplicatas', 'volumes', 'transportadora']);

        if ($nota->itens->isEmpty()) {
            throw new RuntimeException('A nota não tem itens. Adicione ao menos um produto antes de transmitir.');
        }

        if ($nota->numero === null) {
            throw new RuntimeException('A nota ainda não tem número atribuído.');
        }

        // Sem schema explícito o Make assume PL_009, anterior à Reforma, e
        // descarta os grupos IBS, CBS, IS e DFeReferenciado em silêncio.
        $make = new Make(config('fiscal.schema', 'PL_010_V1.30'));

        $this->infNFe($make);
        $this->ide($make, $nota);
        $this->emitente($make, $nota);
        $this->destinatario($make, $nota);

        foreach ($nota->itens as $item) {
            $this->item($make, $nota, $item);
        }

        $this->totais($make, $nota);
        $this->transporte($make, $nota);
        $this->pagamento($make, $nota);
        $this->informacoesAdicionais($make, $nota);
        $this->responsavelTecnico($make);

        if (! $make->montaNFe()) {
            throw new RuntimeException(
                'Não foi possível montar o XML: '.implode(' | ', $make->getErrors())
            );
        }

        $xml = $make->getXML();

        // A chave é calculada pelo Make a partir dos dados; guardá-la agora
        // permite consultar a nota na SEFAZ mesmo se a transmissão falhar.
        $nota->forceFill(['chave_acesso' => $make->getChave()])->save();

        return $xml;
    }

    private function infNFe(Make $make): void
    {
        $make->taginfNFe($this->std([
            'versao' => config('fiscal.versao_nfe', '4.00'),
        ]));
    }

    private function ide(Make $make, Nota $nota): void
    {
        $emitente = $nota->emitente;

        $make->tagide($this->std([
            'cUF' => substr($emitente->codigo_municipio, 0, 2),
            'natOp' => $nota->natureza_operacao ?: 'Venda',
            'mod' => config('fiscal.modelo', 55),
            'serie' => $nota->serie,
            'nNF' => $nota->numero,
            'dhEmi' => $nota->data_emissao->format('Y-m-d\TH:i:sP'),
            'dhSaiEnt' => $nota->data_saida?->format('Y-m-d\TH:i:sP'),
            'tpNF' => $nota->tipo,
            'idDest' => $nota->id_dest,
            'cMunFG' => $emitente->codigo_municipio,
            'tpImp' => 1,
            'tpEmis' => $nota->tp_emis,
            'tpAmb' => $nota->ambiente->tpAmb(),
            'finNFe' => $nota->fin_nfe,
            'indFinal' => $nota->consumidor_final ? 1 : 0,
            'indPres' => $nota->ind_pres,
            'procEmi' => 0,
            'verProc' => 'emissor-nfe 1.0',
            // Contingência exige data e justificativa.
            'dhCont' => $nota->tp_emis !== '1' ? $nota->contingencia_em?->format('Y-m-d\TH:i:sP') : null,
            'xJust' => $nota->tp_emis !== '1' ? $nota->justificativa_contingencia : null,
        ]));
    }

    private function emitente(Make $make, Nota $nota): void
    {
        $e = $nota->emitente;

        $make->tagemit($this->std([
            'xNome' => $e->razao_social,
            'xFant' => $e->nome_fantasia,
            'IE' => preg_replace('/\D/', '', (string) $e->inscricao_estadual),
            'IM' => $e->inscricao_municipal,
            'CNAE' => $e->cnae,
            'CRT' => $e->crt,
            'CNPJ' => $e->cnpj,
        ]));

        $make->tagenderEmit($this->std([
            'xLgr' => $e->logradouro,
            'nro' => $e->numero,
            'xCpl' => $e->complemento,
            'xBairro' => $e->bairro,
            'cMun' => $e->codigo_municipio,
            'xMun' => $e->municipio,
            'UF' => $e->uf,
            'CEP' => $e->cep,
            'cPais' => '1058',
            'xPais' => 'BRASIL',
            'fone' => preg_replace('/\D/', '', (string) $e->telefone) ?: null,
        ]));
    }

    private function destinatario(Make $make, Nota $nota): void
    {
        $d = $nota->destinatario;

        if ($d === null) {
            throw new RuntimeException('A nota não tem destinatário.');
        }

        $pessoaFisica = $d->tipo_pessoa->value === 'F';

        $make->tagdest($this->std([
            'xNome' => $d->razao_social,
            'indIEDest' => $d->ind_ie_dest->value,
            'IE' => $d->ind_ie_dest->exigeInscricaoEstadual()
                ? preg_replace('/\D/', '', (string) $d->inscricao_estadual)
                : null,
            'ISUF' => $d->suframa,
            'email' => $d->email,
            $pessoaFisica ? 'CPF' : 'CNPJ' => $d->documento,
        ]));

        $make->tagenderDest($this->std([
            'xLgr' => $d->logradouro,
            'nro' => $d->numero,
            'xCpl' => $d->complemento,
            'xBairro' => $d->bairro,
            'cMun' => $d->codigo_municipio,
            'xMun' => $d->municipio,
            'UF' => $d->uf,
            'CEP' => $d->cep,
            'cPais' => '1058',
            'xPais' => 'BRASIL',
            'fone' => preg_replace('/\D/', '', (string) $d->telefone) ?: null,
        ]));
    }

    private function item(Make $make, Nota $nota, NotaItem $item): void
    {
        $n = $item->numero;

        $make->tagprod($this->std([
            'item' => $n,
            'cProd' => $item->codigo,
            'cEAN' => $item->gtin ?: 'SEM GTIN',
            'cEANTrib' => $item->gtin ?: 'SEM GTIN',
            'xProd' => $item->descricao,
            'NCM' => $item->ncm,
            'CEST' => $item->cest,
            'CFOP' => $item->cfop,
            'uCom' => $item->unidade,
            'qCom' => $this->numero($item->quantidade, 4),
            'vUnCom' => $this->numero($item->valor_unitario, 10),
            'vProd' => $this->numero($item->valor_produto, 2),
            'uTrib' => $item->unidade_tributavel,
            'qTrib' => $this->numero($item->quantidade_tributavel, 4),
            'vUnTrib' => $this->numero($item->valor_unitario, 10),
            'vFrete' => $item->valor_frete > 0 ? $this->numero($item->valor_frete, 2) : null,
            'vSeg' => $item->valor_seguro > 0 ? $this->numero($item->valor_seguro, 2) : null,
            'vDesc' => $item->valor_desconto > 0 ? $this->numero($item->valor_desconto, 2) : null,
            'vOutro' => $item->valor_outros > 0 ? $this->numero($item->valor_outros, 2) : null,
            'indTot' => 1,
        ]));

        // Devolução e nota complementar referenciam a nota original item a
        // item, no grupo DFeReferenciado do próprio det. Ver DF-002.
        if (filled($item->chave_referenciada)) {
            $make->tagDFeReferenciado($this->std([
                'item' => $n,
                'chaveAcesso' => $item->chave_referenciada,
                'nItem' => $item->item_referenciado,
            ]));
        }

        $make->tagimposto($this->std(['item' => $n]));

        $this->icms($make, $nota, $item);
        $this->ipi($make, $item);
        $this->pisCofins($make, $item);
        $this->ibsCbs($make, $item);
    }

    private function icms(Make $make, Nota $nota, NotaItem $item): void
    {
        $comum = [
            'item' => $item->numero,
            'orig' => $item->origem,
        ];

        // Simples Nacional usa CSOSN; regime normal usa CST.
        if (filled($item->csosn)) {
            $make->tagICMSSN($this->std($comum + [
                'CSOSN' => $item->csosn,
                'pCredSN' => $item->credito_sn > 0 ? $this->numero($item->aliquota_icms, 4) : null,
                'vCredICMSSN' => $item->credito_sn > 0 ? $this->numero($item->credito_sn, 2) : null,
            ]));

            return;
        }

        $make->tagICMS($this->std($comum + [
            'CST' => $item->cst_icms,
            'modBC' => $item->mod_bc ?? '3',
            'vBC' => $this->numero($item->base_icms, 2),
            'pICMS' => $this->numero($item->aliquota_icms, 4),
            'vICMS' => $this->numero($item->valor_icms, 2),
            'modBCST' => $item->valor_icms_st > 0 ? '4' : null,
            'vBCST' => $item->valor_icms_st > 0 ? $this->numero($item->base_icms_st, 2) : null,
            'pICMSST' => $item->valor_icms_st > 0 ? $this->numero($item->aliquota_icms, 4) : null,
            'vICMSST' => $item->valor_icms_st > 0 ? $this->numero($item->valor_icms_st, 2) : null,
            'pFCP' => $item->valor_fcp > 0 ? $this->numero($item->valor_fcp / max(0.01, (float) $item->base_icms) * 100, 4) : null,
            'vFCP' => $item->valor_fcp > 0 ? $this->numero($item->valor_fcp, 2) : null,
        ]));
    }

    private function ipi(Make $make, NotaItem $item): void
    {
        if (blank($item->cst_ipi)) {
            return;
        }

        $make->tagIPI($this->std([
            'item' => $item->numero,
            'cEnq' => $item->cod_enq_ipi ?: '999',
            'CST' => $item->cst_ipi,
            'vBC' => $this->numero($item->valor_produto, 2),
            'pIPI' => $this->numero($item->valor_produto > 0 ? ($item->valor_ipi / (float) $item->valor_produto * 100) : 0, 4),
            'vIPI' => $this->numero($item->valor_ipi, 2),
        ]));
    }

    private function pisCofins(Make $make, NotaItem $item): void
    {
        if (filled($item->cst_pis)) {
            $make->tagPIS($this->std([
                'item' => $item->numero,
                'CST' => $item->cst_pis,
                'vBC' => $this->numero($item->base_icms ?: $item->valor_produto, 2),
                'pPIS' => $this->numero($this->aliquotaDe($item->valor_pis, $item->valor_produto), 4),
                'vPIS' => $this->numero($item->valor_pis, 2),
            ]));
        }

        if (filled($item->cst_cofins)) {
            $make->tagCOFINS($this->std([
                'item' => $item->numero,
                'CST' => $item->cst_cofins,
                'vBC' => $this->numero($item->base_icms ?: $item->valor_produto, 2),
                'pCOFINS' => $this->numero($this->aliquotaDe($item->valor_cofins, $item->valor_produto), 4),
                'vCOFINS' => $this->numero($item->valor_cofins, 2),
            ]));
        }
    }

    /** Grupos da Reforma Tributária. Obrigatórios para CRT 3. Ver DF-001. */
    private function ibsCbs(Make $make, NotaItem $item): void
    {
        if (blank($item->cst_ibscbs)) {
            return;
        }

        $base = $this->numero($item->valor_produto, 2);

        $make->tagIBSCBS($this->std([
            'item' => $item->numero,
            'CST' => $item->cst_ibscbs,
            'cClassTrib' => $item->cclasstrib,
            'vBC' => $base,
            'gIBSUF_pIBSUF' => $this->numero($this->aliquotaDe($item->valor_ibs_uf, $item->valor_produto), 4),
            'gIBSUF_vIBSUF' => $this->numero($item->valor_ibs_uf, 2),
            'gIBSMun_pIBSMun' => $this->numero($this->aliquotaDe($item->valor_ibs_mun, $item->valor_produto), 4),
            'gIBSMun_vIBSMun' => $this->numero($item->valor_ibs_mun, 2),
            'gCBS_pCBS' => $this->numero($this->aliquotaDe($item->valor_cbs, $item->valor_produto), 4),
            'gCBS_vCBS' => $this->numero($item->valor_cbs, 2),
        ]));
    }

    private function totais(Make $make, Nota $nota): void
    {
        $make->tagICMSTot($this->std([
            'vBC' => $this->numero($nota->base_icms, 2),
            'vICMS' => $this->numero($nota->valor_icms, 2),
            'vICMSDeson' => '0.00',
            'vFCP' => $this->numero($nota->valor_fcp, 2),
            'vBCST' => '0.00',
            'vST' => $this->numero($nota->valor_icms_st, 2),
            'vFCPST' => '0.00',
            'vFCPSTRet' => '0.00',
            'vProd' => $this->numero($nota->valor_produtos, 2),
            'vFrete' => $this->numero($nota->valor_frete, 2),
            'vSeg' => $this->numero($nota->valor_seguro, 2),
            'vDesc' => $this->numero($nota->valor_desconto, 2),
            'vII' => '0.00',
            'vIPI' => $this->numero($nota->valor_ipi, 2),
            'vIPIDevol' => '0.00',
            'vPIS' => $this->numero($nota->valor_pis, 2),
            'vCOFINS' => $this->numero($nota->valor_cofins, 2),
            'vOutro' => $this->numero($nota->valor_outros, 2),
            'vNF' => $this->numero($nota->valor_nota, 2),
        ]));
    }

    private function transporte(Make $make, Nota $nota): void
    {
        $make->tagtransp($this->std(['modFrete' => $nota->mod_frete]));

        $t = $nota->transportadora;

        if ($t !== null) {
            $make->tagtransporta($this->std([
                'xNome' => $t->razao_social,
                'IE' => preg_replace('/\D/', '', (string) $t->inscricao_estadual) ?: null,
                'xEnder' => trim($t->logradouro.', '.$t->numero),
                'xMun' => $t->municipio,
                'UF' => $t->uf,
                $t->tipo_pessoa->value === 'F' ? 'CPF' : 'CNPJ' => $t->documento,
            ]));
        }

        foreach ($nota->volumes as $volume) {
            $make->tagvol($this->std([
                'qVol' => $volume->quantidade,
                'esp' => $volume->especie,
                'marca' => $volume->marca,
                'nVol' => $volume->numeracao,
                'pesoL' => $volume->peso_liquido !== null ? $this->numero($volume->peso_liquido, 3) : null,
                'pesoB' => $volume->peso_bruto !== null ? $this->numero($volume->peso_bruto, 3) : null,
            ]));
        }
    }

    private function pagamento(Make $make, Nota $nota): void
    {
        $make->tagpag($this->std([]));

        if ($nota->pagamentos->isEmpty()) {
            // Sem forma informada, a NF-e exige ao menos "sem pagamento".
            $make->tagdetPag($this->std(['tPag' => '90', 'vPag' => '0.00']));

            return;
        }

        foreach ($nota->pagamentos as $pagamento) {
            $make->tagdetPag($this->std([
                'indPag' => $pagamento->ind_pag,
                'tPag' => $pagamento->t_pag,
                'vPag' => $this->numero($pagamento->valor, 2),
            ]));
        }
    }

    private function informacoesAdicionais(Make $make, Nota $nota): void
    {
        $complementares = trim((string) $nota->info_complementares);

        if ($complementares === '' && blank($nota->info_fisco)) {
            return;
        }

        $make->taginfAdic($this->std([
            'infCpl' => $complementares ?: null,
            'infAdFisco' => $nota->info_fisco ?: null,
        ]));
    }

    private function responsavelTecnico(Make $make): void
    {
        $resp = config('fiscal.responsavel_tecnico');

        if (blank($resp['cnpj'] ?? null)) {
            return;
        }

        $make->taginfRespTec($this->std([
            'CNPJ' => preg_replace('/\D/', '', (string) $resp['cnpj']),
            'xContato' => $resp['contato'],
            'email' => $resp['email'],
            'fone' => preg_replace('/\D/', '', (string) $resp['telefone']),
        ]));
    }

    /** @param array<string, mixed> $dados */
    private function std(array $dados): stdClass
    {
        $std = new stdClass;

        foreach ($dados as $campo => $valor) {
            if ($valor !== null && $valor !== '') {
                $std->{$campo} = $valor;
            }
        }

        return $std;
    }

    private function numero(mixed $valor, int $casas): string
    {
        return number_format((float) $valor, $casas, '.', '');
    }

    private function aliquotaDe(mixed $imposto, mixed $base): float
    {
        $base = (float) $base;

        return $base > 0 ? round((float) $imposto / $base * 100, 4) : 0.0;
    }
}
