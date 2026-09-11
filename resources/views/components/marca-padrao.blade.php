{{--
    Marca do produto, usada quando o tenant não tem logo própria.

    Geometria pura em grade de 100 por 100, o símbolo "Encaixe redondo": não
    depende de fonte, de arquivo no storage nem de requisição. O bloco herda
    `currentColor`, então funciona sobre claro e sobre escuro; o quarto de
    disco é sempre o vermelhão, que é o ponto que fecha.
--}}
{{-- Sem tamanho padrão: o `merge` concatenaria `size-9` com o tamanho que o
     chamador passa, e duas classes de tamanho brigando é bug esperando data. --}}
<svg viewBox="0 0 100 100" {{ $attributes }} role="img" aria-label="Venda Redonda">
    <path fill="currentColor" d="M0 0 H44 A56 56 0 0 0 100 56 V100 H0 Z" />
    <path fill="var(--color-primary-600)" d="M100 0 H56 A44 44 0 0 0 100 44 Z" />
</svg>
