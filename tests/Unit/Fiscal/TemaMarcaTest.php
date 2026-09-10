<?php

use App\Support\TemaMarca;

describe('geração da escala', function () {
    it('gera onze tons a partir de uma cor base', function () {
        expect(TemaMarca::escalaDe('#E8192C'))->toHaveCount(11)
            ->toHaveKeys([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
    });

    it('coloca a cor informada no tom 600', function () {
        expect(TemaMarca::escalaDe('#E8192C')[600])->toBe('#e8192c');
    });

    it('vai do mais claro para o mais escuro', function () {
        $escala = TemaMarca::escalaDe('#1B8A4B');
        $lums = array_map(fn (string $hex): float => TemaMarca::luminancia($hex), array_values($escala));

        expect($lums)->toBe(collect($lums)->sortDesc()->values()->all());
    });

    it('aceita cor sem cerquilha', function () {
        expect(TemaMarca::escalaDe('1B8A4B')[600])->toBe('#1b8a4b');
    });

    it('recusa hex invalido', function () {
        TemaMarca::escalaDe('verde');
    })->throws(InvalidArgumentException::class);
});

describe('contraste', function () {
    it('aprova cor que passa em AA com texto branco', function () {
        // Verde escuro de transportadora: 6,57.
        expect(TemaMarca::contrasteComBranco('#146B3A'))->toBeGreaterThanOrEqual(4.5);
    });

    it('reprova verde de marca que passa perto mas nao chega', function () {
        // 4,39. Parece seguro a olho nu e não é.
        expect(TemaMarca::contrasteComBranco('#1B8A4B'))->toBeLessThan(4.5);
    });

    it('reprova cor clara demais para texto branco', function () {
        expect(TemaMarca::contrasteComBranco('#F5D90A'))->toBeLessThan(4.5);
    });

    it('escurece automaticamente a cor que nao passa', function (string $cor) {
        $ajustada = TemaMarca::ajustarParaContraste($cor);

        expect(TemaMarca::contrasteComBranco($ajustada))->toBeGreaterThanOrEqual(4.5);
    })->with(['#F5D90A', '#1B8A4B', '#FF6B00', '#00A3FF']);

    it('escurece o minimo necessario', function () {
        // 4,39 vira 4,79, não 8. A marca continua reconhecível.
        expect(TemaMarca::contrasteComBranco(TemaMarca::ajustarParaContraste('#1B8A4B')))
            ->toBeLessThan(5.5);
    });

    it('nao mexe na cor que ja passa', function () {
        expect(TemaMarca::ajustarParaContraste('#E8192C'))->toBe('#e8192c');
    });
});

describe('âncora da escala', function () {
    it('ancora a primaria no tom 600, que e a cor de marca', function () {
        expect(TemaMarca::escalaDe('#E8192C')[600])->toBe('#e8192c');
    });

    it('ancora a neutra no tom 900, que e o preto da marca', function () {
        expect(TemaMarca::escalaNeutraDe('#1A1A1A')[900])->toBe('#1a1a1a');
    });

    it('reproduz a escala grafite medida no site da rcm', function () {
        $escala = TemaMarca::escalaNeutraDe('#1A1A1A');

        // Valores medidos por renderização real. Ver docs/design-system.md.
        expect($escala[900])->toBe('#1a1a1a')
            ->and($escala[800])->toBe('#2a2a2a')
            ->and($escala[700])->toBe('#3e3e3e')
            ->and($escala[100])->toBe('#e8e8e8')
            ->and($escala[50])->toBe('#f6f6f6');
    });
});

describe('variáveis CSS', function () {
    it('produz sobrescrita para cada tom', function () {
        $css = TemaMarca::de('#146B3A', '#111111')->paraCss();

        expect($css)->toContain('--color-primary-600:#146b3a')
            ->and($css)->toContain('--color-primary-50:')
            ->and($css)->toContain('--color-graphite-900:');
    });

    it('mantem zinc alinhado ao grafite, para o flux acompanhar', function () {
        $css = TemaMarca::de('#146B3A', '#111111')->paraCss();

        expect($css)->toContain('--color-zinc-900:');
    });

    it('sem tema nao injeta nada, o bundle ja traz o padrao', function () {
        expect(TemaMarca::deArray([]))->toBeNull();
    });

    it('injeta apenas quando ha marca propria', function () {
        expect(TemaMarca::deArray(['primaria' => '#146B3A']))->not->toBeNull();
    });
});
