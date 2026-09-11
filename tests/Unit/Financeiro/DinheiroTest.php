<?php

use App\Support\Dinheiro;

/**
 * Casos portados de `src/lib/finance.test.ts` do projeto Marcelo Andrade, para
 * que a conversão em Laravel prove o mesmo comportamento da origem.
 */
describe('leitura do que o usuário digita', function () {
    it('converte valor brasileiro em centavos', function (string $entrada, int $centavos) {
        expect(Dinheiro::emCentavos($entrada))->toBe($centavos);
    })->with([
        ['R$ 1.250,90', 125090],
        ['1.250,90', 125090],
        // Ponto sozinho em grupos de três é separador de milhar, não decimal:
        // quem digita 1.500 num campo de dinheiro quer mil e quinhentos.
        ['1.500', 150000],
        ['1500', 150000],
        ['1.500,00', 150000],
        ['0,99', 99],
        ['  R$  42  ', 4200],
        // Ponto fora do padrão de milhar é decimal, como o teclado numérico manda.
        ['1500.50', 150050],
    ]);

    it('devolve zero para o que não é número', function (string $entrada) {
        expect(Dinheiro::emCentavos($entrada))->toBe(0);
    })->with([[''], ['   '], ['abc'], ['R$'], ['-']]);

    it('nunca perde centavo por arredondamento binário', function () {
        // 0,07 em float dá 7.000000000000001. Truncar daria 6.
        expect(Dinheiro::emCentavos('0,07'))->toBe(7)
            ->and(Dinheiro::emCentavos('1,10'))->toBe(110)
            ->and(Dinheiro::emCentavos('8,20'))->toBe(820);
    });
});

describe('escrita para o usuário', function () {
    it('formata centavos no padrão brasileiro', function (int $centavos, string $texto) {
        expect(Dinheiro::formatar($centavos))->toBe($texto);
    })->with([
        [125090, '1.250,90'],
        [0, '0,00'],
        [99, '0,99'],
        [100000000, '1.000.000,00'],
        [-4200, '-42,00'],
    ]);
});
