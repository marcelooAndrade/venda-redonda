<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    // A aba do navegador é parte da marca. Num sistema que serve várias
    // empresas, o nome do tenant vale mais que o nome do produto. Fora de
    // uma requisição com host resolvido, cai no nome do sistema.
    // `marca` pode vir injetada por quem inclui. A apresentação usa isso: o
    // nome do produto numa página pública não pode depender de variável de
    // ambiente, senão um erro de configuração mostra a marca errada ao mercado.
    $marca = $marca
        ?? app(\App\Support\TenantAtual::class)->obter()?->nome
        ?? config('app.name', 'Laravel');
@endphp

<title>
    {{ filled($title ?? null) ? $title.' - '.$marca : $marca }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Manrope em toda a identidade: é a fonte do wordmark, e uma só família
     mantém marca e produto falando igual. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Marca do tenant. Todo utilitário do Tailwind aponta para var(--color-*),
     então sobrescrever os tokens aqui repinta o sistema inteiro, inclusive o
     Flux. Vem depois do @vite de propósito, para ganhar do bundle. --}}
@php($marcaCss = app(\App\Support\TenantAtual::class)->obter()?->marca()?->paraCss())
@if ($marcaCss)
    <style>{!! $marcaCss !!}</style>
@endif
@fluxAppearance
