@props(['title' => null])

@php
    $emitente = app(\App\Support\EmitenteAtual::class)->resolver();
    $tenant = app(\App\Support\TenantAtual::class)->obter();
    $user = auth()->user();

    // A navegação reflete os módulos e a permissão de quem está olhando.
    // Item sem permissão não aparece: mostrar um caminho que leva a 403 é
    // pior do que não mostrar. `rota` nula é módulo ainda não construído.
    $navegacao = collect([
        ['Painel', 'dashboard', 'relatorio.ver', 'M3 12h18M3 6h18M3 18h18'],
        ['Notas fiscais', 'notas', 'nota.ver', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Destinatários', 'destinatarios', 'pessoa.ver', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['Produtos', 'produtos', 'produto.ver', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ['Estoque', 'estoque', 'estoque.ver', 'M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7h16M9 11h6'],
        ['Importação', 'importacao', 'importacao.ver', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-8-4v8m0-8l-3 3m3-3l3 3M12 4v4'],
        ['Contabilidade', 'contabilidade', 'contador.exportar', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Regras fiscais', 'regras-fiscais', 'tributacao.gerenciar', 'M9 12h6m-6 4h4m4-11V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V9l-4-4z'],
        ['Certificado', 'certificados', 'certificado.ver', 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
        ['Marca', 'marca', 'emitente.gerenciar', 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343'],
        ['Design System', 'design-system', 'ver-design-system', 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01'],
    ])->filter(fn (array $item): bool => $item[2] === null || $user?->can($item[2]))->values()->all();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh bg-graphite-50 font-sans text-sm text-graphite-900 antialiased">

    @if ($emitente)
        <x-ui.env-banner :ambiente="$emitente->ambiente" />
    @endif

    <div class="flex min-h-dvh">
        {{-- Sidebar. A barra vermelha de 3px no item ativo herda o motivo do
             hero do site, onde uma barra vertical vermelha corta a fachada. --}}
        <aside class="hidden w-56 shrink-0 flex-col bg-graphite-900 md:flex">
            <div class="flex items-center gap-2.5 border-b border-white/10 px-4 py-4">
                @if ($tenant?->logo_path)
                    <img src="{{ $tenant->logo_path }}" alt="{{ $tenant->nome }}" class="h-7 w-auto max-w-[9rem] object-contain">
                @else
                    <span class="flex size-7 shrink-0 items-center justify-center bg-primary-600 font-display text-xs font-bold text-white">
                        {{ mb_strtoupper(mb_substr($tenant?->rotulo() ?? 'N', 0, 1)) }}
                    </span>
                    <span class="truncate font-display text-base font-bold uppercase tracking-wide text-white">
                        {{ $tenant?->rotulo() ?? 'Emissor NF-e' }}
                    </span>
                @endif
            </div>

            <nav class="flex flex-1 flex-col gap-px py-3" aria-label="Navegação principal">
                @foreach ($navegacao as [$rotulo, $rota, $permissao, $icone])
                    @php
                        $ativo = $rota && request()->routeIs($rota);
                        $disponivel = $rota !== null;
                    @endphp
                    <a @if($disponivel) href="{{ route($rota) }}" @else aria-disabled="true" @endif
                       @class([
                           'flex items-center gap-2.5 border-l-[3px] px-4 py-2 text-[13px] transition-colors',
                           'border-primary-600 bg-graphite-800 font-medium text-white' => $ativo,
                           'border-transparent text-graphite-300 hover:bg-graphite-800 hover:text-white' => ! $ativo && $disponivel,
                           'border-transparent text-graphite-500 cursor-default' => ! $disponivel,
                       ])>
                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}" />
                        </svg>
                        <span class="truncate">{{ $rotulo }}</span>
                        @unless ($disponivel)
                            <span class="ml-auto text-[9px] uppercase tracking-wider text-graphite-600">em breve</span>
                        @endunless
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Topbar --}}
            <header class="flex flex-wrap items-center gap-3 bg-graphite-800 px-4 py-2.5 text-white">
                @if ($emitente)
                    <span class="border border-white/20 px-2.5 py-1 text-xs">
                        {{ $emitente->nome_fantasia ?: $emitente->razao_social }}
                    </span>
                @else
                    <span class="text-xs text-graphite-300">Nenhum emitente vinculado</span>
                @endif

                <span class="ml-auto flex items-center gap-2 text-[11px] text-graphite-300">
                    <span class="size-1.5 bg-success-600" aria-hidden="true"></span>
                    SEFAZ-SP em operação
                </span>

                <span class="text-xs text-graphite-300">{{ $user?->name }}</span>
            </header>

            <main class="min-w-0 flex-1">
                {{ $slot }}
            </main>
        </div>
    </div>

    @fluxScripts
</body>
</html>
