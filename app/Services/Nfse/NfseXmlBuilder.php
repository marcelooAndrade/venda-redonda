<?php

namespace App\Services\Nfse;

use App\Enums\Fiscal\TipoPessoa;
use App\Models\Emitente;
use App\Models\NotaServico;
use App\Models\Pessoa;
use DOMDocument;

/**
 * Monta o `<notafiscal>` do SIGISS de Araras.
 *
 * Classe pura: recebe models e devolve string, sem banco. Os 57 campos e a
 * ordem deles são os de `KervalisNfseXmlBuilder` do Oficina Fluxo, que o
 * SIGISS autorizou em produção. Sem o manual do web service, nenhum campo
 * é acrescentado, retirado ou reinterpretado. Ver DF-025.
 *
 * Valores em `1234,56`, sem separador de milhar, e o ISS calculado em
 * centavos inteiros: nada vira float até o XML.
 */
class NfseXmlBuilder
{
    public function montar(Emitente $emitente, Pessoa $tomador, NotaServico $nota): string
    {
        $valor = (int) $nota->valor_centavos;
        $aliquota = (int) $nota->aliquota_iss_bp;

        $campos = [
            'cnpj_cpf_prestador' => $emitente->cnpj,
            'exterior_dest' => '0',
            'cnpj_cpf_destinatario' => $tomador->documento,
            'pessoa_destinatario' => $tomador->tipo_pessoa === TipoPessoa::Fisica ? 'F' : 'J',
            'ie_destinatario' => $tomador->inscricao_estadual,
            'im_destinatario' => $tomador->inscricao_municipal,
            'razao_social_destinatario' => $tomador->razao_social,
            'endereco_destinatario' => $tomador->logradouro,
            'numero_ende_destinatario' => $tomador->numero,
            'complemento_ende_destinatario' => $tomador->complemento,
            'bairro_destinatario' => $tomador->bairro,
            'cep_destinatario' => preg_replace('/\D/', '', (string) $tomador->cep),
            'cidade_destinatario' => $tomador->municipio,
            'uf_destinatario' => strtoupper((string) $tomador->uf),
            'pais_destinatario' => 'Brasil',
            'fone_destinatario' => preg_replace('/\D/', '', (string) $tomador->telefone),
            'email_destinatario' => $tomador->email,
            'valor_nf' => self::moeda($valor),
            'deducao' => self::moeda(0),
            'valor_servico' => self::moeda($valor),
            'data_emissao' => now()->setTimezone('America/Sao_Paulo')->format('d/m/Y'),
            // A parcela não sabe como será paga.
            'forma_de_pagamento' => '',
            'descricao' => mb_substr((string) $nota->descricao, 0, 1000),
            'id_codigo_servico' => $nota->codigo_servico,
            'cancelada' => 'N',
            'iss_retido' => $nota->iss_retido ? 'S' : 'N',
            'aliq_iss' => self::moeda($aliquota),
            'valor_iss' => self::moeda(self::iss($valor, $aliquota)),
        ];

        // Federais zerados, como na origem. Ver DF-025.
        foreach (['pis', 'cofins', 'csll', 'irrf', 'inss'] as $imposto) {
            $campos["bc_{$imposto}"] = self::moeda(0);
            $campos["aliq_{$imposto}"] = self::moeda(0);
            $campos["valor_{$imposto}"] = self::moeda(0);
        }

        $campos += [
            'sistema_gerador' => 'Venda Redonda',
            'serie_rps' => $nota->serie_rps,
            'rps' => (string) $nota->numero_rps,
            'codigo_nbs' => $nota->codigo_nbs,
            'exterior_prestacao_servico' => '0',
            'pais_local_prest' => 'Brasil',
            'cidade_local_prest' => $emitente->municipio,
            'uf_local_prest' => strtoupper((string) $emitente->uf),
            'c_classtrib' => $nota->c_class_trib,
            'ind_op' => $nota->ind_op,
            'exterior_op' => '0',
            'uf_local_op' => strtoupper((string) $emitente->uf),
            'cidade_local_op' => $emitente->municipio,
            'consumo_pessoal' => '0',
        ];

        $documento = new DOMDocument('1.0', 'UTF-8');
        $documento->formatOutput = true;
        $raiz = $documento->appendChild($documento->createElement('notafiscal'));

        foreach ($campos as $nome => $conteudo) {
            $elemento = $documento->createElement($nome);
            // createTextNode escapa & e <; createElement com valor não.
            $elemento->appendChild($documento->createTextNode((string) ($conteudo ?? '')));
            $raiz->appendChild($elemento);
        }

        return (string) $documento->saveXML();
    }

    /** ISS em centavos, meio para cima, em aritmética inteira. */
    public static function iss(int $valorCentavos, int $aliquotaBp): int
    {
        return intdiv($valorCentavos * $aliquotaBp + 5000, 10000);
    }

    /** `1234,56`, sem separador de milhar: é assim que o SIGISS lê. */
    public static function moeda(int $centavos): string
    {
        return sprintf('%d,%02d', intdiv($centavos, 100), $centavos % 100);
    }
}
