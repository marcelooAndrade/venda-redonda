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

    it('mede que amarelo nao sustenta texto branco', function () {
        expect(TemaMarca::contrasteComBranco('#F5D90A'))->toBeLessThan(4.5);
    });

    /*
     * A garantia mudou em 11/09/2026. Antes era "escurece até o branco ler",
     * o que reprovava cor que o sistema nunca usaria com texto branco, e
     * chegou a acusar de defeituosa a marca do próprio produto. Agora é
     * "sobre qualquer marca existe texto legível", e quem não tem saída é
     * escurecido.
     */
    it('garante texto legivel sobre qualquer marca informada', function (string $cor) {
        $ajustada = TemaMarca::ajustarParaContraste($cor);

        $melhor = max(
            TemaMarca::contrasteEntre($ajustada, '#ffffff'),
            TemaMarca::contrasteEntre($ajustada, TemaMarca::NEUTRA_PADRAO),
        );

        expect($melhor)->toBeGreaterThanOrEqual(4.5)
            ->and(TemaMarca::contrasteEntre($ajustada, TemaMarca::textoSobre($ajustada)))
            ->toBeGreaterThanOrEqual(4.5);
    })->with(['#F5D90A', '#1B8A4B', '#FF6B00', '#00A3FF', '#e4572e', '#0b3d91', '#808080']);

    it('deixa em paz a marca clara que sustenta texto escuro', function () {
        // Amarelo tem 1,42 com branco e 15,6 com grafite. Escurecê-lo seria
        // estragar a marca do cliente para resolver um problema inexistente.
        expect(TemaMarca::ajustarParaContraste('#F5D90A'))->toBe('#f5d90a');
    });

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

describe('padrão do produto', function () {
    it('nasce com a marca da venda redonda, nao com a de um cliente', function () {
        expect(TemaMarca::PRIMARIA_PADRAO)->toBe('#e4572e')
            ->and(TemaMarca::NEUTRA_PADRAO)->toBe('#0e1b1f');
    });

    it('ancora o grafite-petroleo no tom 900 da neutra padrao', function () {
        expect(TemaMarca::escalaNeutraDe(TemaMarca::NEUTRA_PADRAO)[900])->toBe('#0e1b1f');
    });

    it('o cinza-pedra da marca ja vive na escala, sem token proprio', function () {
        // O briefing define #7F8E8B como tom de apoio. A rampa gerada a partir
        // do grafite-petróleo entrega #848b8d no tom 400: 1,01 de contraste
        // entre os dois, ou seja, a mesma cor. Por isso a paleta não ganha um
        // quinto token só para ele.
        $tom400 = TemaMarca::escalaNeutraDe(TemaMarca::NEUTRA_PADRAO)[400];

        // Fixa o tom, senão dois cinzas médios quaisquer passariam e o teste
        // não guardaria nada.
        expect($tom400)->toBe('#848b8d');

        $pedra = TemaMarca::luminancia('#7f8e8b');
        $gerado = TemaMarca::luminancia($tom400);
        $contraste = (max($pedra, $gerado) + 0.05) / (min($pedra, $gerado) + 0.05);

        expect($contraste)->toBeLessThan(1.1);
    });
});

describe('legibilidade sobre a marca', function () {
    it('aceita o vermelhao da marca sem escurecer, porque grafite le sobre ele', function () {
        // 3,68 com branco, 4,77 com grafite. A regra antiga media só o branco
        // e escurecia a cor do produto sem necessidade.
        expect(TemaMarca::ajustarParaContraste('#e4572e'))->toBe('#e4572e');
    });

    it('escolhe grafite como texto sobre uma marca clara', function () {
        expect(TemaMarca::textoSobre('#e4572e'))->toBe(TemaMarca::NEUTRA_PADRAO);
    });

    it('escolhe branco como texto sobre uma marca escura', function () {
        expect(TemaMarca::textoSobre('#0b3d91'))->toBe('#ffffff');
    });

    it('escurece so quando nenhuma cor de texto le sobre a marca', function () {
        // Cinza médio: falha contra branco e contra grafite ao mesmo tempo.
        $antes = '#808080';
        $depois = TemaMarca::ajustarParaContraste($antes);

        expect($depois)->not->toBe($antes)
            ->and(TemaMarca::contrasteEntre($depois, '#ffffff'))->toBeGreaterThanOrEqual(4.5);
    });

    it('publica a cor de texto da primaria como variavel css', function () {
        $css = TemaMarca::de('#e4572e', '#0e1b1f')->paraCss();

        expect($css)->toContain('--color-on-primary:#0e1b1f');
    });
});
