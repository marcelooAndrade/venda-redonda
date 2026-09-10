<?php

use App\Support\Gtin;

it('aceita gtin-13 valido', function () {
    // Código de barras real de produto industrial.
    expect(Gtin::valido('7891000315507'))->toBeTrue();
});

it('aceita gtin-8 valido', function () {
    expect(Gtin::valido('96385074'))->toBeTrue();
});

it('aceita gtin-14 valido', function () {
    expect(Gtin::valido('17891000315504'))->toBeTrue();
});

it('recusa digito verificador errado', function () {
    expect(Gtin::valido('7891000315508'))->toBeFalse();
});

it('recusa tamanho fora do padrao', function () {
    expect(Gtin::valido('789100031550'))->toBeFalse();
});

it('aceita a ausencia declarada de gtin', function () {
    // A NF-e usa a string literal SEM GTIN quando o produto não tem código.
    expect(Gtin::valido('SEM GTIN'))->toBeTrue();
});

it('recusa texto qualquer', function () {
    expect(Gtin::valido('ABC123'))->toBeFalse();
});

it('normaliza para o literal da nf-e quando vazio', function () {
    expect(Gtin::normalizar(''))->toBe('SEM GTIN')
        ->and(Gtin::normalizar(null))->toBe('SEM GTIN')
        ->and(Gtin::normalizar(' 7891000315507 '))->toBe('7891000315507');
});
