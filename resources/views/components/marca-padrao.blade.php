{{--
    Marca do produto, usada quando o tenant não tem logo própria.

    Geometria pura em grade de 100 por 100: uma moldura arredondada, aberta à
    esquerda, com as linhas de velocidade do "agora" atravessando por dentro.
    Tudo no vermelhão da marca, sólido, do jeito que sai na logo oficial. Não
    depende de fonte, de arquivo no storage nem de requisição.
--}}
{{-- Sem tamanho padrão: o `merge` concatenaria `size-9` com o tamanho que o
     chamador passa, e duas classes de tamanho brigando é bug esperando data. --}}
<svg viewBox="0 0 100 100" {{ $attributes }} role="img" aria-label="EmitirAgora">
    <path
        fill="none" stroke="var(--color-primary-600)" stroke-width="12"
        stroke-linecap="round" stroke-linejoin="round"
        d="M42,20 H72 A18 18 0 0 1 90 38 V62 A18 18 0 0 1 72 80 H42"
    />
    <rect fill="var(--color-primary-600)" x="14" y="37" width="30" height="8" rx="4" />
    <rect fill="var(--color-primary-600)" x="52" y="37" width="11" height="8" rx="4" />
    <rect fill="var(--color-primary-600)" x="8" y="50" width="42" height="8" rx="4" />
    <rect fill="var(--color-primary-600)" x="58" y="50" width="9" height="8" rx="4" />
    <rect fill="var(--color-primary-600)" x="16" y="63" width="27" height="8" rx="4" />
    <rect fill="var(--color-primary-600)" x="14" y="75" width="17" height="8" rx="4" />
</svg>
