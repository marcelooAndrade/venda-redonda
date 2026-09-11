@props(['title' => null])

@php
    $emitente = app(\App\Support\EmitenteAtual::class)->resolver();
    $tenant = app(\App\Support\TenantAtual::class)->obter();
    $user = auth()->user();

    // A navegação reflete os módulos e a permissão de quem está olhando.
    // Item sem permissão não aparece: mostrar um caminho que leva a 403 é
    // pior do que não mostrar. `rota` nula é módulo ainda não construído.
    // Agrupada por ritmo de uso: o que se faz todo dia, o que se cadastra de
    // vez em quando, e o que se configura uma vez. Onze itens chapados
    // obrigam a ler a lista inteira toda vez.
    $grupos = ['Operação', 'Cadastros', 'Configuração'];

    $navegacao = collect([
        ['Painel', 'dashboard', 'relatorio.ver', 'Operação', 'M3 12h18M3 6h18M3 18h18'],
        ['Notas fiscais', 'notas', 'nota.ver', 'Operação', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Destinatários', 'destinatarios', 'pessoa.ver', 'Cadastros', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['Produtos', 'produtos', 'produto.ver', 'Cadastros', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ['Estoque', 'estoque', 'estoque.ver', 'Operação', 'M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7h16M9 11h6'],
        ['Importação', 'importacao', 'importacao.ver', 'Operação', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-8-4v8m0-8l-3 3m3-3l3 3M12 4v4'],
        ['Financeiro', 'financeiro', 'financeiro.ver', 'Operação', 'M3 3v16a2 2 0 002 2h16M7 15l3.5-4 3 3L20 7'],
        ['Contas a pagar', 'contas-a-pagar', 'financeiro.ver', 'Operação', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['Contas a receber', 'contas-a-receber', 'financeiro.ver', 'Operação', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Contabilidade', 'contabilidade', 'contador.exportar', 'Operação', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['Regras fiscais', 'regras-fiscais', 'tributacao.gerenciar', 'Configuração', 'M9 12h6m-6 4h4m4-11V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V9l-4-4z'],
        ['Certificado', 'certificados', 'certificado.ver', 'Configuração', 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
        ['Marca', 'marca', 'emitente.gerenciar', 'Configuração', 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343'],
    ])->filter(fn (array $item): bool => $item[2] === null || $user?->can($item[2]))->groupBy(3);
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
        <aside class="hidden w-60 shrink-0 flex-col bg-graphite-900 md:flex">
            <div class="flex h-14 items-center gap-2.5 border-b border-white/10 px-5">
                @if ($tenant?->logo_path)
                    <img src="{{ route('logo') }}" alt="{{ $tenant->nome }}" class="h-7 w-auto max-w-[9rem] object-contain">
                @else
                    {{-- A cor do texto vem da marca, não está cravada: sobre marca clara
                         escreve-se em grafite, sobre marca escura em branco. Ver
                         TemaMarca::textoSobre. --}}
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary-600 font-display text-xs font-bold text-on-primary">
                        {{ mb_strtoupper(mb_substr($tenant?->rotulo() ?? 'N', 0, 1)) }}
                    </span>
                    <span class="truncate font-display text-base font-bold uppercase tracking-wide text-white">
                        {{ $tenant?->rotulo() ?? 'Venda Redonda' }}
                    </span>
                @endif
            </div>

            <nav class="flex flex-1 flex-col gap-6 overflow-y-auto py-5" aria-label="Navegação principal">
                @foreach ($grupos as $grupo)
                    @continue (! isset($navegacao[$grupo]))
                    <div class="flex flex-col gap-0.5">
                        <p class="etiqueta px-5 pb-2 text-graphite-500">{{ $grupo }}</p>

                        @foreach ($navegacao[$grupo] as [$rotulo, $rota, $permissao, $secao, $icone])
                            @php
                                $ativo = $rota && request()->routeIs($rota);
                                $disponivel = $rota !== null;
                            @endphp
                            <a @if($disponivel) href="{{ route($rota) }}" @else aria-disabled="true" @endif
                               @class([
                                   'group relative flex items-center gap-3 py-2 pl-5 pr-4 text-[13px] transition-colors',
                                   'bg-white/10 font-medium text-white' => $ativo,
                                   'text-graphite-300 hover:bg-white/5 hover:text-white' => ! $ativo && $disponivel,
                                   'text-graphite-600 cursor-default' => ! $disponivel,
                               ])>
                                {{-- A barra vive num filho, e não numa borda do link, para
                                     que o fundo do item ativo comece na borda esquerda. --}}
                                <span aria-hidden="true"
                                      @class([
                                          'absolute inset-y-0 left-0 w-[3px] rounded-r-sm',
                                          'bg-primary-600' => $ativo,
                                          'bg-transparent' => ! $ativo,
                                      ])></span>
                                <svg class="size-[18px] shrink-0 {{ $ativo ? 'text-primary-600' : 'text-graphite-500 group-hover:text-graphite-300' }}"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}" />
                                </svg>
                                <span class="truncate">{{ $rotulo }}</span>
                                @unless ($disponivel)
                                    <span class="etiqueta ml-auto text-[9px] text-graphite-600">em breve</span>
                                @endunless
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Topbar clara. Escura, ela empilhava uma terceira faixa sob o
                 banner de ambiente e a área de trabalho virava um poço. --}}
            <header class="flex h-14 flex-wrap items-center gap-3 border-b border-graphite-200 bg-white px-5">
                @if ($emitente)
                    <span class="flex items-center gap-2 rounded-md border border-graphite-200 bg-graphite-50 px-2.5 py-1.5 text-xs font-medium text-graphite-700">
                        <span class="size-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                        {{ $emitente->nome_fantasia ?: $emitente->razao_social }}
                    </span>
                @else
                    <span class="text-xs text-graphite-500">Nenhum emitente vinculado</span>
                @endif

                <span class="ml-auto hidden items-center gap-2 text-xs text-graphite-500 sm:flex">
                    <span class="size-1.5 rounded-full bg-success-600" aria-hidden="true"></span>
                    SEFAZ-SP em operação
                </span>

                <span class="flex items-center gap-2 text-xs text-graphite-600">
                    <span class="flex size-7 items-center justify-center rounded-full bg-graphite-800 text-[10px] font-semibold text-white">
                        {{ mb_strtoupper(mb_substr($user?->name ?? '?', 0, 2)) }}
                    </span>
                    <span class="hidden sm:inline">{{ $user?->name }}</span>
                </span>
            </header>

            {{-- O contêiner da página vive aqui, uma vez só. Cada view repetia
                 `mx-auto max-w-6xl`, e em monitor largo isso deixava faixas
                 vazias dos dois lados. --}}
            <main class="min-w-0 flex-1">
                <div class="mx-auto w-full max-w-[1800px] px-5 py-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @fluxScripts
</body>
</html>
