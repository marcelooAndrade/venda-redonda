<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Paleta da marca de um tenant.
 *
 * Todo utilitário do Tailwind 4 compila para `var(--color-*)`, então trocar
 * a marca é sobrescrever as variáveis em tempo de execução. Não há CSS
 * recompilado por tenant nem classe condicional: uma transportadora verde e
 * preta recebe o mesmo bundle que a RCM vermelha e grafite.
 */
class TemaMarca
{
    /** Vermelho e grafite da RCM, usados quando o tenant não define marca. */
    public const PRIMARIA_PADRAO = '#e8192c';

    public const NEUTRA_PADRAO = '#1a1a1a';

    /**
     * Curva de luminosidade da escala, em porcentagem. O tom 600 é a cor da
     * marca, e é onde a curva foi ancorada.
     *
     * @var array<int, float>
     */
    private const CURVA = [
        50 => 0.97, 100 => 0.93, 200 => 0.85, 300 => 0.74, 400 => 0.60,
        500 => 0.50, 600 => 0.0, 700 => -0.16, 800 => -0.32, 900 => -0.45, 950 => -0.68,
    ];

    /**
     * Curva da neutra, ancorada no 900. O "preto" de uma marca é o tom mais
     * escuro utilizável, não o do meio.
     *
     * Os fatores foram extraídos da escala grafite medida no site da RCM, de
     * modo que informar #1A1A1A reproduz exatamente a paleta do design system.
     *
     * @var array<int, float>
     */
    private const CURVA_NEUTRA = [
        50 => 0.9607, 100 => 0.8996, 200 => 0.7991, 300 => 0.6550, 400 => 0.4891,
        500 => 0.3624, 600 => 0.2576, 700 => 0.1572, 800 => 0.0699, 900 => 0.0, 950 => -0.4231,
    ];

    private function __construct(
        private readonly string $primaria,
        private readonly string $neutra,
    ) {}

    public static function de(string $primaria, ?string $neutra = null): self
    {
        return new self(
            self::ajustarParaContraste($primaria),
            self::normalizar($neutra ?? self::NEUTRA_PADRAO),
        );
    }

    /**
     * Devolve null quando o tenant não tem marca própria: o bundle compilado
     * já carrega a paleta padrão, então injetar sobrescrita idêntica seria
     * peso sem efeito.
     *
     * @param  array<string, mixed>  $tema
     */
    public static function deArray(array $tema): ?self
    {
        if (blank($tema['primaria'] ?? null) && blank($tema['neutra'] ?? null)) {
            return null;
        }

        return self::de(
            $tema['primaria'] ?? self::PRIMARIA_PADRAO,
            $tema['neutra'] ?? self::NEUTRA_PADRAO,
        );
    }

    /**
     * Escala de 11 tons. O 600 é exatamente a cor informada; os demais
     * caminham para o branco ou para o preto pela curva.
     *
     * @return array<int, string>
     */
    public static function escalaDe(string $hex): array
    {
        return self::gerar($hex, self::CURVA);
    }

    /**
     * Escala neutra, ancorada no 900.
     *
     * @return array<int, string>
     */
    public static function escalaNeutraDe(string $hex): array
    {
        return self::gerar($hex, self::CURVA_NEUTRA);
    }

    /**
     * @param  array<int, float>  $curva
     * @return array<int, string>
     */
    private static function gerar(string $hex, array $curva): array
    {
        $normalizado = self::normalizar($hex);
        $base = self::rgb($normalizado);
        $escala = [];

        foreach ($curva as $tom => $fator) {
            $escala[$tom] = $fator === 0.0
                ? $normalizado
                : self::hex(self::misturar($base, $fator));
        }

        return $escala;
    }

    public static function luminancia(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb(self::normalizar($hex)));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function contrasteComBranco(string $hex): float
    {
        return round(1.05 / (self::luminancia($hex) + 0.05), 2);
    }

    /**
     * Escurece a cor até passar em WCAG AA com texto branco.
     *
     * Marca clara demais não é recusada: seria dizer ao cliente que a marca
     * dele está errada. Ela é escurecida no tom de ação, e a cor original
     * segue disponível nos tons claros da escala.
     */
    public static function ajustarParaContraste(string $hex): string
    {
        $cor = self::normalizar($hex);
        $rgb = self::rgb($cor);
        $tentativas = 0;

        while (self::contrasteComBranco(self::hex($rgb)) < 4.5 && $tentativas < 40) {
            $rgb = self::misturar($rgb, -0.05);
            $tentativas++;
        }

        return self::hex($rgb);
    }

    /** Bloco de sobrescrita para injetar no `<head>`. */
    public function paraCss(): string
    {
        $linhas = [];

        foreach (self::escalaDe($this->primaria) as $tom => $cor) {
            $linhas[] = "--color-primary-{$tom}:{$cor}";
        }

        foreach (self::escalaNeutraDe($this->neutra) as $tom => $cor) {
            $linhas[] = "--color-graphite-{$tom}:{$cor}";
            // O Flux usa zinc. Mantendo os dois alinhados, as telas de
            // autenticação e ajustes acompanham a marca sem refactor.
            $linhas[] = "--color-zinc-{$tom}:{$cor}";
        }

        $linhas[] = '--color-accent:var(--color-graphite-900)';
        $linhas[] = '--color-accent-content:var(--color-graphite-900)';

        return ':root{'.implode(';', $linhas).'}';
    }

    private static function normalizar(string $hex): string
    {
        $hex = strtolower(ltrim(trim($hex), '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-f]{6}$/', $hex) !== 1) {
            throw new InvalidArgumentException("Cor inválida: \"{$hex}\". Informe um hexadecimal como #1B8A4B.");
        }

        return '#'.$hex;
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Fator positivo mistura com branco, negativo com preto.
     *
     * @param  array{int, int, int}  $rgb
     * @return array{int, int, int}
     */
    private static function misturar(array $rgb, float $fator): array
    {
        $alvo = $fator > 0 ? 255 : 0;
        $peso = abs($fator);

        return array_map(
            fn (int $c): int => (int) round($c + ($alvo - $c) * $peso),
            $rgb,
        );
    }

    /** @param array{int, int, int} $rgb */
    private static function hex(array $rgb): string
    {
        return '#'.implode('', array_map(
            fn (int $c): string => str_pad(dechex(max(0, min(255, $c))), 2, '0', STR_PAD_LEFT),
            $rgb,
        ));
    }
}
