<?php

use App\Support\Documento;

describe('CNPJ numérico', function () {
    it('aceita cnpj valido', function () {
        expect(Documento::cnpjValido('11222333000181'))->toBeTrue();
    });

    it('aceita com mascara', function () {
        expect(Documento::cnpjValido('11.222.333/0001-81'))->toBeTrue();
    });

    it('recusa digito verificador errado', function () {
        expect(Documento::cnpjValido('11222333000182'))->toBeFalse();
    });

    it('recusa todos os caracteres iguais', function () {
        expect(Documento::cnpjValido('00000000000000'))->toBeFalse();
        expect(Documento::cnpjValido('11111111111111'))->toBeFalse();
    });

    it('recusa tamanho errado', function () {
        expect(Documento::cnpjValido('112223330001'))->toBeFalse();
        expect(Documento::cnpjValido('112223330001812'))->toBeFalse();
    });
});

describe('CNPJ alfanumérico', function () {
    // Exemplo oficial da Receita Federal: 12.ABC.345/01DE-35.
    // Conferido à mão: DV1 soma 459, resto 8, dígito 3. DV2 soma 424, resto 6, dígito 5.
    it('aceita o exemplo oficial da receita', function () {
        expect(Documento::cnpjValido('12ABC34501DE35'))->toBeTrue();
    });

    it('aceita o exemplo oficial com mascara', function () {
        expect(Documento::cnpjValido('12.ABC.345/01DE-35'))->toBeTrue();
    });

    it('normaliza letra minuscula', function () {
        expect(Documento::cnpjValido('12abc34501de35'))->toBeTrue();
    });

    it('recusa digito verificador errado', function () {
        expect(Documento::cnpjValido('12ABC34501DE34'))->toBeFalse();
    });

    it('recusa letra na posicao do digito verificador', function () {
        // Os dois últimos caracteres continuam sendo sempre numéricos.
        expect(Documento::cnpjValido('12ABC34501DEA5'))->toBeFalse();
    });

    it('recusa caractere fora de A-Z e 0-9', function () {
        expect(Documento::cnpjValido('12ÁBC34501DE35'))->toBeFalse();
    });
});

describe('normalização', function () {
    it('remove mascara e sobe para maiuscula', function () {
        expect(Documento::normalizarCnpj('12.abc.345/01de-35'))->toBe('12ABC34501DE35');
    });

    it('preserva o cnpj como string, nunca como inteiro', function () {
        // Um CNPJ começando com zero perderia o zero se virasse número.
        expect(Documento::normalizarCnpj('01.234.567/0001-89'))->toBe('01234567000189');
    });
});

describe('CPF', function () {
    it('aceita cpf valido', function () {
        expect(Documento::cpfValido('52998224725'))->toBeTrue();
    });

    it('aceita com mascara', function () {
        expect(Documento::cpfValido('529.982.247-25'))->toBeTrue();
    });

    it('recusa digito errado', function () {
        expect(Documento::cpfValido('52998224726'))->toBeFalse();
    });

    it('recusa todos os digitos iguais', function () {
        expect(Documento::cpfValido('11111111111'))->toBeFalse();
    });

    it('recusa tamanho errado', function () {
        expect(Documento::cpfValido('5299822472'))->toBeFalse();
    });
});
