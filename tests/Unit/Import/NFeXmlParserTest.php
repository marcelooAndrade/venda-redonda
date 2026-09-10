<?php

use App\Services\Import\NFeXmlParser;

function xml(string $nome): string
{
    // Caminho relativo de propósito: o parser é puro e o teste não sobe a
    // aplicação, então `base_path()` não existe aqui.
    return (string) file_get_contents(__DIR__."/../../Fixtures/xml/{$nome}.xml");
}

beforeEach(fn () => $this->parser = new NFeXmlParser);

describe('segurança', function () {
    it('recusa xml com DOCTYPE, que abre porta para XXE', function () {
        $this->parser->parse(xml('xxe'));
    })->throws(RuntimeException::class, 'insegura');

    it('recusa arquivo acima do limite', function () {
        $this->parser->parse(str_repeat('a', 21 * 1024 * 1024));
    })->throws(RuntimeException::class, 'limite');

    it('recusa conteudo que nao e xml', function () {
        $this->parser->parse('isto nao e um xml');
    })->throws(RuntimeException::class);
});

describe('identificação', function () {
    it('extrai a chave de acesso do atributo Id', function () {
        expect($this->parser->parse(xml('nfe-autorizada'))->chave)
            ->toBe('35260911444777000161550010000088211234567897')
            ->toHaveLength(44);
    });

    it('recusa cte, que nao e nota de produto', function () {
        $this->parser->parse(xml('cte'));
    })->throws(RuntimeException::class, 'CT-e');

    it('recusa nota sem protocolo de autorizacao', function () {
        // Só nota autorizada entra no estoque. Ver o escopo do Módulo 6.
        $this->parser->parse(xml('nfe-sem-protocolo'));
    })->throws(RuntimeException::class, 'autorizada');

    it('le o protocolo quando presente', function () {
        $nota = $this->parser->parse(xml('nfe-autorizada'));

        expect($nota->protocolo)->toBe('135260000123456')
            ->and($nota->cStat)->toBe('100');
    });
});

describe('cabeçalho', function () {
    it('extrai numero, serie e data', function () {
        $nota = $this->parser->parse(xml('nfe-autorizada'));

        expect($nota->numero)->toBe('8821')
            ->and($nota->serie)->toBe('1')
            ->and($nota->dataEmissao->format('d/m/Y'))->toBe('02/09/2026');
    });

    it('extrai o emitente', function () {
        $e = $this->parser->parse(xml('nfe-autorizada'))->emitente;

        expect($e['cnpj'])->toBe('11444777000161')
            ->and($e['razao_social'])->toBe('METALURGICA PIRACICABA LTDA')
            ->and($e['municipio'])->toBe('Piracicaba')
            ->and($e['codigo_municipio'])->toBe('3538709')
            ->and($e['uf'])->toBe('SP')
            ->and($e['cep'])->toBe('13400000');
    });

    it('extrai o destinatario', function () {
        expect($this->parser->parse(xml('nfe-autorizada'))->destinatario['cnpj'])
            ->toBe('11222333000181');
    });

    it('extrai os totais', function () {
        $t = $this->parser->parse(xml('nfe-autorizada'))->totais;

        expect($t['valor_produtos'])->toBe(19290.0)
            ->and($t['valor_frete'])->toBe(350.0)
            ->and($t['valor_ipi'])->toBe(730.0)
            ->and($t['valor_nota'])->toBe(20370.0);
    });
});

describe('itens', function () {
    it('extrai todos os itens', function () {
        expect($this->parser->parse(xml('nfe-autorizada'))->itens)->toHaveCount(2);
    });

    it('extrai os dados do produto', function () {
        $item = $this->parser->parse(xml('nfe-autorizada'))->itens[0];

        expect($item['codigo'])->toBe('MP-INOX-316')
            ->and($item['descricao'])->toBe('Barra de aco inox 316L 50mm')
            ->and($item['ncm'])->toBe('72222000')
            ->and($item['cfop'])->toBe('5102')
            ->and($item['unidade'])->toBe('KG')
            ->and($item['quantidade'])->toBe(500.0)
            ->and($item['valor_unitario'])->toBe(28.5)
            ->and($item['valor_total'])->toBe(14250.0);
    });

    it('preserva o gtin quando informado e reconhece a ausencia', function () {
        $itens = $this->parser->parse(xml('nfe-autorizada'))->itens;

        expect($itens[0]['gtin'])->toBeNull()
            ->and($itens[1]['gtin'])->toBe('7891000315507');
    });

    it('extrai frete rateado no item', function () {
        expect($this->parser->parse(xml('nfe-autorizada'))->itens[0]['valor_frete'])->toBe(350.0);
    });

    it('extrai os impostos do item', function () {
        $item = $this->parser->parse(xml('nfe-autorizada'))->itens[0];

        expect($item['cst_icms'])->toBe('00')
            ->and($item['origem'])->toBe('0')
            ->and($item['valor_icms'])->toBe(2628.0)
            ->and($item['valor_ipi'])->toBe(730.0);
    });

    it('calcula o custo unitario com frete e ipi', function () {
        // (14250 + 350 + 730) / 500 = 30,66
        expect($this->parser->parse(xml('nfe-autorizada'))->itens[0]['custo_unitario'])->toBe(30.66);
    });
});
