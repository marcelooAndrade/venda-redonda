<?php

use App\Models\Emitente;
use App\Models\NaturezaOperacao;
use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\User;
use App\Services\Fiscal\CertificateService;
use App\Services\Fiscal\NFeBuilder;
use App\Services\Fiscal\NfephpToolsFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NFePHP\Common\Validator;

function emitenteCompleto(): Emitente
{
    return Emitente::factory()->create([
        'razao_social' => 'RCM DO BRASIL LTDA',
        'nome_fantasia' => 'RCM do Brasil',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'logradouro' => 'Rua Joao Grigoleto', 'numero' => '83',
        'bairro' => 'Distrito Industrial II',
        'codigo_municipio' => '3503307', 'municipio' => 'Araras',
        'uf' => 'SP', 'cep' => '13602200', 'telefone' => '1930960072',
    ]);
}

function destinatarioCompleto(Emitente $e, array $extra = []): Pessoa
{
    return Pessoa::create(array_merge([
        'emitente_id' => $e->id, 'tipo_pessoa' => 'J',
        'documento' => '11444777000161',
        'razao_social' => 'METALURGICA PIRACICABA LTDA',
        'ind_ie_dest' => '1', 'inscricao_estadual' => '111222333444',
        'logradouro' => 'Avenida Industrial', 'numero' => '450',
        'bairro' => 'Distrito Industrial',
        'codigo_municipio' => '3538709', 'municipio' => 'Piracicaba',
        'uf' => 'SP', 'cep' => '13400000',
        'e_cliente' => true,
    ], $extra));
}

function notaPronta(array $extraNota = [], array $extraRegra = []): Nota
{
    $emitente = emitenteCompleto();
    $dest = destinatarioCompleto($emitente);

    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Peças']);
    PerfilFiscalRegra::create(array_merge([
        'perfil_fiscal_id' => $perfil->id, 'ambito' => 'interna', 'vigente_de' => '2026-01-01',
        'cst_icms' => '00', 'aliquota_icms' => 18,
        'cst_pis' => '01', 'aliquota_pis' => 1.65,
        'cst_cofins' => '01', 'aliquota_cofins' => 7.6,
    ], $extraRegra));

    $natureza = NaturezaOperacao::create([
        'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfil->id,
        'descricao' => 'Venda de producao propria',
        'cfop_interno' => '5101', 'cfop_interestadual' => '6101',
    ]);

    $produto = Produto::create([
        'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfil->id,
        'codigo' => 'PC-001', 'descricao' => 'Peca microfundida em aco inox 316L',
        'ncm' => '73259910', 'unidade_comercial' => 'PC', 'unidade_tributavel' => 'PC',
        'fator_conversao' => 1, 'origem' => '0', 'preco_venda' => 145.90,
        'peso_liquido' => 0.45, 'peso_bruto' => 0.48,
    ]);

    $nota = Nota::create(array_merge([
        'emitente_id' => $emitente->id,
        'pessoa_id' => $dest->id,
        'natureza_operacao_id' => $natureza->id,
        'natureza_operacao' => $natureza->descricao,
        'serie' => 1, 'numero' => 1480,
        'ambiente' => 'homologacao',
        'data_emissao' => now(),
        'id_dest' => '1', 'mod_frete' => '9',
        'valor_produtos' => 1459.00, 'valor_nota' => 1459.00,
        'base_icms' => 1459.00, 'valor_icms' => 262.62,
        'valor_pis' => 24.07, 'valor_cofins' => 110.88,
    ], $extraNota));

    NotaItem::create([
        'nota_id' => $nota->id, 'produto_id' => $produto->id, 'numero' => 1,
        'codigo' => 'PC-001', 'descricao' => 'Peca microfundida em aco inox 316L',
        'ncm' => '73259910', 'cfop' => '5101', 'unidade' => 'PC', 'unidade_tributavel' => 'PC',
        'origem' => '0', 'quantidade' => 10, 'quantidade_tributavel' => 10,
        'valor_unitario' => 145.90, 'valor_produto' => 1459.00,
        'cst_icms' => '00', 'mod_bc' => '3', 'base_icms' => 1459.00,
        'aliquota_icms' => 18, 'valor_icms' => 262.62,
        'cst_pis' => '01', 'valor_pis' => 24.07,
        'cst_cofins' => '01', 'valor_cofins' => 110.88,
    ]);

    return $nota->fresh(['itens', 'destinatario', 'emitente']);
}

beforeEach(fn () => $this->builder = app(NFeBuilder::class));

describe('estrutura', function () {
    it('gera xml que, assinado, passa no xsd oficial', function () {
        // O XSD exige o nó Signature, então validar o XML cru sempre falha.
        // Este teste prova a cadeia inteira: montar, assinar e validar.
        Storage::fake('fiscal');

        $nota = notaPronta();
        $emitente = $nota->emitente;
        $emitente->forceFill(['cnpj' => '11222333000181'])->save();

        app(CertificateService::class)->enviar(
            $emitente,
            UploadedFile::fake()->createWithContent(
                'valido.pfx',
                (string) file_get_contents(base_path('tests/Fixtures/certificados/valido.pfx')),
            ),
            'teste123',
            User::factory()->create(),
        );

        $xml = $this->builder->montar($nota);
        $assinado = app(NfephpToolsFactory::class)->para($emitente->fresh())->signNFe($xml);

        $xsd = base_path('vendor/nfephp-org/sped-nfe/schemes/PL_010_V1.30/nfe_v4.00.xsd');
        expect(is_file($xsd))->toBeTrue('schema oficial não encontrado no pacote');
        expect(Validator::isValid($assinado, $xsd))->toBeTrue();
    });

    it('produz chave de acesso de 44 digitos', function () {
        $xml = $this->builder->montar(notaPronta());

        preg_match('/Id="NFe(\d{44})"/', $xml, $m);

        expect($m[1] ?? null)->not->toBeNull()->toHaveLength(44);
    });

    it('grava a chave na nota', function () {
        $nota = notaPronta();
        $this->builder->montar($nota);

        expect($nota->fresh()->chave_acesso)->toHaveLength(44);
    });

    it('usa o ambiente de homologacao no tpAmb', function () {
        $xml = $this->builder->montar(notaPronta());

        expect($xml)->toContain('<tpAmb>2</tpAmb>');
    });

    it('marca modelo 55', function () {
        expect($this->builder->montar(notaPronta()))->toContain('<mod>55</mod>');
    });
});

describe('conteúdo', function () {
    it('inclui emitente e destinatario', function () {
        $xml = $this->builder->montar(notaPronta());

        expect($xml)->toContain('<CNPJ>11222333000181</CNPJ>')
            ->and($xml)->toContain('<CNPJ>11444777000161</CNPJ>')
            ->and($xml)->toContain('RCM DO BRASIL LTDA');
    });

    it('inclui o item com ncm e cfop', function () {
        $xml = $this->builder->montar(notaPronta());

        expect($xml)->toContain('<NCM>73259910</NCM>')
            ->and($xml)->toContain('<CFOP>5101</CFOP>');
    });

    it('inclui os totais', function () {
        expect($this->builder->montar(notaPronta()))->toContain('<vNF>1459.00</vNF>');
    });

    it('inclui o responsavel tecnico quando configurado', function () {
        config()->set('fiscal.responsavel_tecnico', [
            'cnpj' => '11444777000161', 'contato' => 'Marcelo Andrade',
            'email' => 'marcelo@exemplo.com.br', 'telefone' => '1996747372',
        ]);

        expect($this->builder->montar(notaPronta()))->toContain('<infRespTec>');
    });
});

describe('Reforma Tributária', function () {
    it('inclui os grupos IBS e CBS quando a regra define', function () {
        $nota = notaPronta();
        $nota->itens[0]->update([
            'cst_ibscbs' => '000', 'cclasstrib' => '000001',
            'valor_ibs_uf' => 0.73, 'valor_ibs_mun' => 0.73, 'valor_cbs' => 13.13,
        ]);

        expect($this->builder->montar($nota->fresh('itens')))->toContain('<IBSCBS>');
    });

    it('omite os grupos quando a regra nao define', function () {
        expect($this->builder->montar(notaPronta()))->not->toContain('<IBSCBS>');
    });
});

describe('devolução', function () {
    it('referencia a nota original item a item', function () {
        // O referenciamento da NT v1.40 é por item, dentro do det, e não no
        // cabeçalho. Descoberto lendo a sped-nfe. Ver DF-002.
        $nota = notaPronta(['fin_nfe' => '4']);
        $nota->itens[0]->update([
            'chave_referenciada' => '35260911444777000161550010000088211234567897',
            'item_referenciado' => 1,
        ]);

        $xml = $this->builder->montar($nota->fresh('itens'));

        expect($xml)->toContain('<DFeReferenciado>')
            ->and($xml)->toContain('35260911444777000161550010000088211234567897')
            ->and($xml)->toContain('<finNFe>4</finNFe>');
    });

    it('nao emite o grupo em nota normal', function () {
        expect($this->builder->montar(notaPronta()))->not->toContain('<DFeReferenciado>');
    });
});
