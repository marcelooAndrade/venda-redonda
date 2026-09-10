<?php

use App\Enums\Fiscal\AmbitoOperacao;
use App\Enums\Fiscal\IndIEDest;
use App\Models\Emitente;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Services\Fiscal\ContextoTributario;
use App\Services\Fiscal\TaxCalculator;

function perfilCom(array $regra, string $ambito = 'interna'): PerfilFiscal
{
    $emitente = Emitente::factory()->create(['uf' => 'SP', 'crt' => '3']);
    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Peças microfundidas']);

    PerfilFiscalRegra::create(array_merge([
        'perfil_fiscal_id' => $perfil->id,
        'ambito' => $ambito,
        'vigente_de' => '2026-01-01',
    ], $regra));

    return $perfil;
}

function contexto(PerfilFiscal $perfil, array $extra = []): ContextoTributario
{
    return new ContextoTributario(...array_merge([
        'perfil' => $perfil,
        'crt' => '3',
        'ufEmitente' => 'SP',
        'ufDestinatario' => 'SP',
        'indIEDest' => IndIEDest::Contribuinte,
        'consumidorFinal' => false,
        'quantidade' => 10.0,
        'valorUnitario' => 100.0,
        'desconto' => 0.0,
        'frete' => 0.0,
        'seguro' => 0.0,
        'outrasDespesas' => 0.0,
        'data' => '2026-09-10',
    ], $extra));
}

beforeEach(fn () => $this->calc = app(TaxCalculator::class));

describe('valor do produto', function () {
    it('multiplica quantidade por valor unitario', function () {
        $r = $this->calc->calcular(contexto(perfilCom(['cst_icms' => '00', 'aliquota_icms' => 18])));

        expect($r->valorProduto)->toBe(1000.0);
    });

    it('subtrai desconto e soma frete, seguro e outras despesas', function () {
        $r = $this->calc->calcular(contexto(perfilCom(['cst_icms' => '00', 'aliquota_icms' => 18]), [
            'desconto' => 100.0, 'frete' => 50.0, 'seguro' => 20.0, 'outrasDespesas' => 30.0,
        ]));

        // 1000 - 100 + 50 + 20 + 30
        expect($r->baseIcms)->toBe(1000.0);
    });
});

describe('ICMS no regime normal', function () {
    it('calcula icms sobre a base cheia', function () {
        $r = $this->calc->calcular(contexto(perfilCom(['cst_icms' => '00', 'aliquota_icms' => 18])));

        expect($r->cstIcms)->toBe('00')
            ->and($r->baseIcms)->toBe(1000.0)
            ->and($r->valorIcms)->toBe(180.0);
    });

    it('aplica reducao de base', function () {
        // Base 1000 com redução de 30% vira 700. 18% de 700 = 126.
        $r = $this->calc->calcular(contexto(perfilCom([
            'cst_icms' => '20', 'aliquota_icms' => 18, 'reducao_bc' => 30,
        ])));

        expect($r->baseIcms)->toBe(700.0)
            ->and($r->valorIcms)->toBe(126.0);
    });

    it('nao destaca icms em cst isento', function () {
        $r = $this->calc->calcular(contexto(perfilCom(['cst_icms' => '40'])));

        expect($r->valorIcms)->toBe(0.0)->and($r->baseIcms)->toBe(0.0);
    });

    it('calcula fcp junto do icms', function () {
        $r = $this->calc->calcular(contexto(perfilCom([
            'cst_icms' => '00', 'aliquota_icms' => 18, 'aliquota_fcp' => 2,
        ])));

        expect($r->valorFcp)->toBe(20.0);
    });
});

describe('Simples Nacional', function () {
    it('usa csosn e nao destaca icms', function () {
        $perfil = perfilCom(['crt' => '1', 'csosn' => '101', 'aliquota_credito_sn' => 2.5]);

        $r = $this->calc->calcular(contexto($perfil, ['crt' => '1']));

        expect($r->csosn)->toBe('101')
            ->and($r->cstIcms)->toBeNull()
            ->and($r->valorIcms)->toBe(0.0)
            // 2,5% de 1000
            ->and($r->creditoSimplesNacional)->toBe(25.0);
    });

    it('nao gera credito em csosn sem permissao de credito', function () {
        $perfil = perfilCom(['crt' => '1', 'csosn' => '102']);

        $r = $this->calc->calcular(contexto($perfil, ['crt' => '1']));

        expect($r->creditoSimplesNacional)->toBe(0.0);
    });
});

describe('IPI, PIS e COFINS', function () {
    it('calcula ipi sobre o valor do produto', function () {
        $r = $this->calc->calcular(contexto(perfilCom([
            'cst_icms' => '00', 'aliquota_icms' => 18, 'cst_ipi' => '50', 'aliquota_ipi' => 5,
        ])));

        expect($r->valorIpi)->toBe(50.0)->and($r->cstIpi)->toBe('50');
    });

    it('calcula pis e cofins', function () {
        $r = $this->calc->calcular(contexto(perfilCom([
            'cst_icms' => '00', 'aliquota_icms' => 18,
            'cst_pis' => '01', 'aliquota_pis' => 1.65,
            'cst_cofins' => '01', 'aliquota_cofins' => 7.6,
        ])));

        expect($r->valorPis)->toBe(16.5)->and($r->valorCofins)->toBe(76.0);
    });
});

describe('Reforma Tributária', function () {
    it('calcula ibs e cbs quando a regra define', function () {
        $r = $this->calc->calcular(contexto(perfilCom([
            'cst_icms' => '00', 'aliquota_icms' => 18,
            'cst_ibscbs' => '000', 'cclasstrib' => '000001',
            'aliquota_ibs_uf' => 0.05, 'aliquota_ibs_mun' => 0.05, 'aliquota_cbs' => 0.9,
        ])));

        expect($r->cstIbsCbs)->toBe('000')
            ->and($r->cClassTrib)->toBe('000001')
            ->and($r->valorIbsUf)->toBe(0.5)
            ->and($r->valorIbsMun)->toBe(0.5)
            ->and($r->valorCbs)->toBe(9.0);
    });

    it('fica zerado quando a regra nao define', function () {
        $r = $this->calc->calcular(contexto(perfilCom(['cst_icms' => '00', 'aliquota_icms' => 18])));

        expect($r->valorIbsUf)->toBe(0.0)->and($r->valorCbs)->toBe(0.0);
    });
});

describe('ICMS ST', function () {
    it('calcula a substituicao com mva', function () {
        // Base ST = 1000 * (1 + 40%) = 1400. ICMS ST = 18% de 1400 = 252,
        // menos o ICMS próprio de 180, sobra 72.
        $r = $this->calc->calcular(contexto(perfilCom([
            'cst_icms' => '10', 'aliquota_icms' => 18,
            'mva_st' => 40, 'aliquota_st' => 18,
        ])));

        expect($r->baseIcmsSt)->toBe(1400.0)->and($r->valorIcmsSt)->toBe(72.0);
    });
});

describe('escolha da regra', function () {
    it('usa a regra interestadual quando as ufs diferem', function () {
        $perfil = perfilCom(['cst_icms' => '00', 'aliquota_icms' => 18]);
        PerfilFiscalRegra::create([
            'perfil_fiscal_id' => $perfil->id, 'ambito' => AmbitoOperacao::Interestadual->value,
            'vigente_de' => '2026-01-01', 'cst_icms' => '00', 'aliquota_icms' => 12,
        ]);

        $r = $this->calc->calcular(contexto($perfil, ['ufDestinatario' => 'MG']));

        expect($r->valorIcms)->toBe(120.0)->and($r->ambito)->toBe(AmbitoOperacao::Interestadual);
    });
});
