<?php

use App\Models\User;
use App\Services\Fiscal\CertificateService;
use App\Services\Fiscal\NFeBuilder;
use App\Services\Fiscal\NfephpToolsFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NFePHP\Common\Validator;

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
