<?php

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Fiscal\TipoPessoa;
use App\Models\Emitente;
use App\Models\Pessoa;
use App\Services\Pessoas\ValidarPessoa;
use Illuminate\Validation\ValidationException;

function dadosBase(array $extra = []): array
{
    return array_merge([
        'tipo_pessoa' => TipoPessoa::Juridica->value,
        'documento' => '11222333000181',
        'razao_social' => 'Metalúrgica Piracicaba Ltda',
        'ind_ie_dest' => IndIEDest::Contribuinte->value,
        'inscricao_estadual' => '111222333444',
        'logradouro' => 'Rua Industrial',
        'numero' => '100',
        'bairro' => 'Centro',
        'codigo_municipio' => '3538709',
        'municipio' => 'Piracicaba',
        'uf' => 'SP',
        'cep' => '13400000',
        'e_cliente' => true,
    ], $extra);
}

beforeEach(function () {
    $this->emitente = Emitente::factory()->create();
    $this->validador = app(ValidarPessoa::class);
});

describe('documento', function () {
    it('aceita cnpj valido para pessoa juridica', function () {
        expect(fn () => $this->validador->validar(dadosBase()))->not->toThrow(ValidationException::class);
    });

    it('recusa cnpj invalido', function () {
        $this->validador->validar(dadosBase(['documento' => '11222333000182']));
    })->throws(ValidationException::class);

    it('aceita cnpj alfanumerico', function () {
        expect(fn () => $this->validador->validar(dadosBase(['documento' => '12ABC34501DE35'])))
            ->not->toThrow(ValidationException::class);
    });

    it('exige cpf para pessoa fisica', function () {
        $this->validador->validar(dadosBase([
            'tipo_pessoa' => TipoPessoa::Fisica->value,
            'documento' => '11222333000181',
            'ind_ie_dest' => IndIEDest::NaoContribuinte->value,
            'inscricao_estadual' => null,
        ]));
    })->throws(ValidationException::class);

    it('aceita cpf valido para pessoa fisica', function () {
        expect(fn () => $this->validador->validar(dadosBase([
            'tipo_pessoa' => TipoPessoa::Fisica->value,
            'documento' => '52998224725',
            'razao_social' => 'João da Silva',
            'ind_ie_dest' => IndIEDest::NaoContribuinte->value,
            'inscricao_estadual' => null,
        ])))->not->toThrow(ValidationException::class);
    });
});

describe('coerência do indIEDest', function () {
    it('exige inscricao estadual do contribuinte', function () {
        $this->validador->validar(dadosBase(['inscricao_estadual' => null]));
    })->throws(ValidationException::class, 'Inscrição Estadual');

    it('recusa inscricao estadual em contribuinte isento', function () {
        $this->validador->validar(dadosBase([
            'ind_ie_dest' => IndIEDest::Isento->value,
            'inscricao_estadual' => '111222333444',
        ]));
    })->throws(ValidationException::class, 'isento');

    it('recusa inscricao estadual em nao contribuinte', function () {
        $this->validador->validar(dadosBase([
            'ind_ie_dest' => IndIEDest::NaoContribuinte->value,
            'inscricao_estadual' => '111222333444',
        ]));
    })->throws(ValidationException::class);

    it('aceita isento sem inscricao', function () {
        expect(fn () => $this->validador->validar(dadosBase([
            'ind_ie_dest' => IndIEDest::Isento->value,
            'inscricao_estadual' => null,
        ])))->not->toThrow(ValidationException::class);
    });
});

describe('papéis', function () {
    it('exige ao menos um papel', function () {
        $this->validador->validar(dadosBase(['e_cliente' => false]));
    })->throws(ValidationException::class, 'papel');

    it('permite ser cliente e fornecedor ao mesmo tempo', function () {
        $pessoa = Pessoa::create([...dadosBase(['e_fornecedor' => true]), 'emitente_id' => $this->emitente->id]);

        expect($pessoa->e_cliente)->toBeTrue()->and($pessoa->e_fornecedor)->toBeTrue();
    });

    it('guarda o documento normalizado, sempre como string', function () {
        $pessoa = Pessoa::create([...dadosBase(['documento' => '11.222.333/0001-81']), 'emitente_id' => $this->emitente->id]);

        expect($pessoa->documento)->toBe('11222333000181')->toBeString();
    });
});
