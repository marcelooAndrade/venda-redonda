{{--
    Apresentação do produto, servida no domínio nu pelo RaizController.

    O revelar ao rolar é progressive enhancement: as seções levam
    `data-revelar`, e é `resources/js/app.js` que observa isso com
    `IntersectionObserver` e aplica o movimento. A garantia de nunca esconder
    conteúdo permanentemente sem JS ou com `prefers-reduced-motion` vive lá,
    não aqui. Sem JS, ou com movimento reduzido, o conteúdo já nasce visível.

    Visual claro e com profundidade (cartões flutuantes, gradiente suave em
    blur, botões em pílula), redesenhado em 12/09/2026 a partir de uma
    referência externa de mercado (ver spec do redesenho), mas só com cores
    das rampas `TemaMarca`: nenhum hex novo entrou na view.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    {{-- A marca vai cravada, não vem de `APP_NAME`. E sem `title`, senão o
         partial concatena e sai "Venda Redonda - Venda Redonda". --}}
    @include('partials.head', ['marca' => 'Venda Redonda'])

    @php
        $descricao = 'Sistema fiscal, de estoque e financeiro para distribuidoras independentes '
            .'de bebidas. Emite NF-e com ICMS-ST, FCP, IBS, CBS e IS calculados no servidor, emite '
            .'NFS-e pelo SIGISS de Araras, importa compras por XML, fecha o estoque contra o galpão '
            .'e lança contas a pagar e a receber com baixa.';
        $site = 'https://'.config('produto.dominio');

        // Montado aqui, e não com `@json`, porque o parser de diretiva do Blade
        // não fecha o array multilinha e quebra a view com ParseError.
        // Sem nota, preço ou contagem de clientes: dado que não existe não entra
        // em marcação estruturada, que é onde a mentira fica auditável.
        $dadosEstruturados = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'Venda Redonda',
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Emissor de NF-e e NFS-e, controle de estoque e financeiro',
            'operatingSystem' => 'Navegador',
            'inLanguage' => 'pt-BR',
            'url' => $site,
            'description' => $descricao,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @endphp

    <meta name="description" content="{{ $descricao }}">
    <link rel="canonical" href="{{ $site }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Venda Redonda">
    <meta property="og:title" content="Venda Redonda, da nota ao caixa">
    <meta property="og:description" content="{{ $descricao }}">
    <meta property="og:url" content="{{ $site }}">
    <meta property="og:locale" content="pt_BR">
    <meta name="twitter:card" content="summary">

    <script type="application/ld+json">{!! $dadosEstruturados !!}</script>
</head>
<body class="min-h-dvh bg-white font-sans text-graphite-600 antialiased">

@php
    // Ícones decorativos, geométricos e genéricos, desenhados à mão para não
    // trazer dependência nova. Cada entrada é o miolo (`<path>`/`<rect>`/
    // `<circle>`) de um SVG 24x24 de traço, aplicado com `currentColor`.
    $icones = [
        'recibo' => '<path d="M6 3h9l3 3v15l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'caixas' => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
        'dinheiro' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/>',
        'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'escudo' => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
        'upload' => '<path d="M12 15V4M8 8l4-4 4 4"/><path d="M4 15v3a2 2 0 002 2h12a2 2 0 002-2v-3"/>',
        'predio' => '<path d="M4 21V5a1 1 0 011-1h6a1 1 0 011 1v16"/><path d="M13 21V9l6 2v10"/><path d="M7 8h1M7 12h1M7 16h1M11 8h1M11 12h1M11 16h1"/>',
        'painel' => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M7 15l3-4 3 2 4-6"/>',
        'pasta' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
        'check' => '<path d="M5 12l5 5L19 7"/>',
    ];

    $iconeChip = function (string $nome, string $tamanho = 'size-10') use ($icones) {
        return '<span class="inline-flex '.$tamanho.' shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">'
            .'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true">'
            .$icones[$nome]
            .'</svg></span>';
    };
@endphp

<a href="#conteudo"
   class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-graphite-900 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
    Ir para o conteúdo
</a>

{{-- ---------------------------------------------------------------- topo --}}
<header class="sticky top-0 z-40 border-b border-graphite-100 bg-white/85 backdrop-blur">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4">
        <span class="flex items-center gap-2.5">
            <x-marca-padrao class="size-7 text-graphite-900" />
            <span class="font-display text-base font-extrabold uppercase tracking-[0.04em] text-graphite-900">
                Venda Redonda
            </span>
        </span>

        <nav aria-label="Seções da página" class="hidden items-center gap-8 text-sm font-semibold text-graphite-600 lg:flex">
            <a href="#recursos" class="transition-colors hover:text-graphite-900">Recursos</a>
            <a href="#sequencia" class="transition-colors hover:text-graphite-900">Sequência</a>
            <a href="#planos" class="transition-colors hover:text-graphite-900">Planos</a>
            <a href="#faq" class="transition-colors hover:text-graphite-900">Perguntas</a>
        </nav>

        <span class="flex flex-wrap items-center gap-3">
            <a href="{{ route('login') }}"
               class="inline-flex min-h-10 items-center rounded-full border border-graphite-200 px-4 text-[13px] font-semibold text-graphite-700 transition-colors hover:bg-graphite-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                Entrar no sistema
            </a>
            <a href="{{ route('register') }}"
               class="inline-flex min-h-10 items-center rounded-full bg-primary-600 px-5 text-[13px] font-bold text-on-primary shadow-sm transition-colors hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                Criar conta grátis
            </a>
        </span>
    </div>
</header>

<main id="conteudo">

    {{-- ------------------------------------------------------------ herói --}}
    <section class="relative isolate overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute -top-32 right-[-10%] size-[34rem] rounded-full bg-primary-200/40 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute top-64 -left-40 size-[26rem] rounded-full bg-graphite-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-6 py-16 md:py-24">
            <div class="grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">

                <div class="min-w-0">
                    <span class="inline-flex items-center gap-2 rounded-full border border-graphite-200 bg-white px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-graphite-600 shadow-sm">
                        <span aria-hidden="true" class="size-1.5 rounded-full bg-primary-600"></span>
                        Sistema fiscal, de estoque e financeiro
                    </span>

                    <h1 class="mt-6 font-display text-4xl font-extrabold leading-[1.08] tracking-[-0.01em] text-graphite-900 sm:text-5xl">
                        O sistema diz 200 caixas.<br>
                        <span class="text-primary-600">O galpão tem 170.</span>
                    </h1>

                    <p class="mt-6 max-w-[46ch] text-lg leading-relaxed text-graphite-600">
                        Venda redonda é quando esses dois números são o mesmo, e a nota, o
                        galpão e o caixa contam a mesma história no fim do mês.
                    </p>

                    <p class="mt-4 max-w-[52ch] text-graphite-500">
                        Sistema fiscal, de estoque e financeiro feito para distribuidoras
                        independentes de bebidas.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <a href="{{ route('register') }}"
                           class="inline-flex min-h-12 items-center rounded-full bg-primary-600 px-6 text-[13px] font-bold uppercase tracking-[0.05em] text-on-primary shadow-md transition-colors hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            Criar conta grátis
                        </a>
                        <a href="{{ route('login') }}"
                           class="inline-flex min-h-12 items-center rounded-full border border-graphite-200 bg-white px-5 text-[13px] font-semibold text-graphite-700 transition-colors hover:bg-graphite-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            Entrar no sistema
                        </a>
                        <a href="#recursos"
                           class="inline-flex min-h-12 items-center px-2 text-[13px] font-semibold text-graphite-600 underline decoration-graphite-300 underline-offset-4 transition-colors hover:text-graphite-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            Ver o que ele faz
                        </a>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-semibold text-graphite-500">
                        <span class="inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-primary-600" aria-hidden="true">{!! $icones['escudo'] !!}</svg>
                            Tributo calculado no servidor
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-primary-600" aria-hidden="true">{!! $icones['caixas'] !!}</svg>
                            Estoque como razão imutável
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4 text-primary-600" aria-hidden="true">{!! $icones['pasta'] !!}</svg>
                            Dados isolados por emitente
                        </span>
                    </div>
                </div>

                {{-- A conferência. Cartão flutuante, com uma etiqueta solta por
                     cima para reforçar a leitura, do jeito que o resto da
                     página também usa cartão com sombra. --}}
                <div class="relative min-w-0">
                    <span aria-hidden="true" class="absolute -top-4 -left-4 hidden rounded-full border border-graphite-100 bg-white px-3 py-1.5 text-xs font-semibold text-graphite-600 shadow-lg sm:inline-flex sm:items-center sm:gap-1.5">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-primary-600">{!! $icones['upload'] !!}</svg>
                        Baixa automática
                    </span>

                    <figure class="rounded-3xl border border-graphite-100 bg-white p-6 shadow-xl shadow-graphite-200/50 sm:p-7">
                        <figcaption class="text-sm text-graphite-500">
                            Conferência de estoque, exemplo
                            <span class="mt-1 block font-semibold text-graphite-700">Cerveja 600&nbsp;ml, caixa com 12</span>
                        </figcaption>

                        <dl class="mt-6 divide-y divide-graphite-100 border-y border-graphite-100">
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-graphite-500">O sistema diz</dt>
                                <dd class="num text-lg font-semibold text-graphite-800">200</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-graphite-500">O galpão tem</dt>
                                <dd class="num text-lg font-semibold text-graphite-800">200</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="font-semibold text-graphite-900">Diferença</dt>
                                <dd class="flex items-center gap-2.5">
                                    <span class="num text-lg font-bold text-graphite-900">0</span>
                                    <span class="rounded-full bg-primary-600 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.08em] text-on-primary">
                                        Confere
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        <p class="mt-5 text-sm leading-relaxed text-graphite-500">
                            A compra entra pelo XML do fornecedor e a baixa acontece no
                            momento da emissão. A diferença não é corrigida depois: ela
                            nasce zero.
                        </p>
                    </figure>

                    <span aria-hidden="true" class="absolute -bottom-4 -right-3 hidden rounded-full border border-graphite-100 bg-white px-3 py-1.5 text-xs font-semibold text-graphite-600 shadow-lg sm:inline-flex sm:items-center sm:gap-1.5">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-success-600">{!! $icones['check'] !!}</svg>
                        Diferença zero
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------ dores --}}
    <section data-revelar class="border-t border-graphite-100 bg-graphite-50">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                Isso não chega organizado sozinho
            </h2>
            <p class="mt-3 max-w-[58ch] text-graphite-600">
                É o que acontece antes de existir um sistema que faça a nota, o
                estoque e o caixa conversarem.
            </p>

            <div class="mt-10 grid gap-5 sm:grid-cols-2">
                @foreach ([
                    ['recibo', 'A nota sai, e ninguém sabe se saiu certa', 'Cálculo feito na hora, sem conferência, e o erro só aparece quando a SEFAZ rejeita.'],
                    ['caixas', 'O estoque diz um número, o galpão diz outro', 'Sem razão imutável, toda contagem vira desconfiança.'],
                    ['dinheiro', 'A conta existe, mas ninguém sabe se foi paga', 'Lançamento solto, sem baixa, sem saber quanto entrou nem quanto falta.'],
                    ['relogio', 'O contador corre atrás no fim do mês', 'Cada emissão, cada nota de entrada, cada carta de correção, juntadas na mão.'],
                ] as [$icone, $titulo, $texto])
                    <div class="rounded-2xl border border-graphite-100 bg-white p-6 shadow-sm">
                        {!! $iconeChip($icone) !!}
                        <h3 class="mt-4 font-display text-base font-bold text-graphite-900">{{ $titulo }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-graphite-600">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------- recursos --}}
    <section id="recursos" data-revelar class="border-t border-graphite-100">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                Sete coisas, e elas dependem uma da outra
            </h2>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['escudo', 'A nota sai certa de primeira', 'ICMS-ST, FCP, IBS, CBS e IS calculados no servidor, nunca no navegador. Se a SEFAZ recusar, o sistema traduz o código do erro em português e diz o que fazer.'],
                    ['caixas', 'O estoque bate com o galpão', 'Movimento nunca é editado nem apagado. Correção é lançamento de estorno, então o Kardex continua sendo registro fiel de tudo o que entrou e saiu.'],
                    ['upload', 'A compra entra pelo XML', 'Solta, em lote ou em ZIP. O fornecedor nasce do próprio arquivo, sem digitar de novo o que já veio na nota.'],
                    ['dinheiro', 'Contas a pagar e a receber, com baixa', 'Título com vencimento por parcela, baixa lançada no caixa, cobrança por Pix quando a chave está cadastrada.'],
                    ['predio', 'NFS-e de Araras, pelo SIGISS', 'Emitida a partir da parcela da fatura, com PDF, XML e cancelamento.'],
                    ['painel', 'Um painel que abre com o que trava', 'Pendência antes de faturamento, porque reemitir sem saber se já saiu duplica nota.'],
                    ['pasta', 'O contador recebe fechado', 'O período inteiro em um ZIP: emitidas, canceladas, cartas de correção, inutilizações e entradas, com resumo que abre no Excel em português sem acento quebrado.'],
                ] as [$icone, $titulo, $texto])
                    <div class="rounded-2xl border border-graphite-100 bg-white p-6 shadow-sm transition-shadow hover:shadow-md">
                        {!! $iconeChip($icone) !!}
                        <h3 class="mt-4 font-display text-base font-bold text-graphite-900">{{ $titulo }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-graphite-600">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------- sequência de fato --}}
    <section id="sequencia" data-revelar class="border-t border-graphite-100 bg-graphite-50">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                Da compra até o contador
            </h2>
            <p class="mt-3 max-w-[58ch] text-graphite-600">
                É uma volta só, e cada etapa é a entrada da seguinte. Nenhuma pede
                digitação do que já estava na nota do fornecedor.
            </p>

            <div class="relative mt-12 rounded-4xl border border-graphite-100 bg-white p-8 shadow-sm sm:p-10">
                <ol class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach ([
                        ['upload', 'Chega a compra', 'O XML do fornecedor entra solto, em lote ou em ZIP. O fornecedor é criado a partir do próprio arquivo.'],
                        ['check', 'Confere quem é o quê', 'O produto do fornecedor é ligado ao seu. Na segunda nota do mesmo fornecedor, ele já entra reconhecido.'],
                        ['caixas', 'Entra no estoque', 'Só depois da sua confirmação. Registrar e confirmar são etapas separadas de propósito.'],
                        ['recibo', 'Sai a venda', 'A nota é montada, os tributos são calculados no servidor e a baixa acontece na transmissão.'],
                        ['pasta', 'Fecha o mês', 'O pacote do período sai pronto para o contador, sem ninguém montar pasta à mão.'],
                    ] as $i => [$icone, $titulo, $texto])
                        <li class="min-w-0">
                            <span class="num inline-flex size-10 items-center justify-center rounded-full bg-primary-600 text-sm font-bold text-on-primary">
                                {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            {!! $iconeChip($icone, 'size-9') !!}
                            <h3 class="mt-3 font-display text-base font-bold text-graphite-900">{{ $titulo }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-graphite-600">{{ $texto }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------- regra é do contador --}}
    <section data-revelar class="border-t border-graphite-100">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <div class="rounded-4xl border border-primary-100 bg-primary-50/60 p-8 sm:p-12">
                <div class="flex flex-wrap items-start gap-6">
                    {!! $iconeChip('escudo', 'size-12') !!}
                    <div class="min-w-0 flex-1">
                        <h2 class="max-w-[24ch] font-display text-2xl font-extrabold leading-tight tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                            Quem escreve a regra fiscal é o seu contador
                        </h2>
                        <div class="mt-6 min-w-0 max-w-[62ch] space-y-4 text-graphite-700">
                            <p class="leading-relaxed">
                                Não existe alíquota, CST, CSOSN ou CFOP escrito dentro do
                                sistema. O contador entra numa tela própria, com permissão
                                própria, e escreve a regra que vale para a sua operação.
                            </p>
                            <p class="leading-relaxed">
                                Toda regra tem data de vigência. Quando a lei muda, a anterior
                                recebe fim de vigência em vez de ser apagada, e a nota de março
                                continua conferindo com a regra de março.
                            </p>
                            <p class="leading-relaxed text-graphite-600">
                                É por isso que a Reforma não vira reescrita de sistema: IBS, CBS
                                e IS são regra cadastrada, com a data em que passam a valer.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------ planos --}}
    <section id="planos" data-revelar class="border-t border-graphite-100">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                Comece pelo gratuito
            </h2>
            <p class="mt-3 max-w-[58ch] text-graphite-600">
                Os dois planos têm o mesmo sistema fiscal, de estoque e
                financeiro. O que muda é a porta de entrada.
            </p>

            <div class="mt-10 grid gap-6 sm:grid-cols-2">
                <div class="rounded-3xl border border-graphite-100 bg-white p-7 shadow-sm sm:p-8">
                    <p class="etiqueta text-primary-700">Gratuito</p>
                    <p class="mt-2 text-sm text-graphite-500">Entra pelo endereço da Venda Redonda.</p>
                    <ul class="mt-6 space-y-3 text-sm text-graphite-700">
                        <li class="flex gap-2.5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-primary-600" aria-hidden="true">{!! $icones['check'] !!}</svg> Cadastro imediato, sem contrato</li>
                        <li class="flex gap-2.5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-primary-600" aria-hidden="true">{!! $icones['check'] !!}</svg> Emissão de NF-e e de NFS-e</li>
                        <li class="flex gap-2.5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-primary-600" aria-hidden="true">{!! $icones['check'] !!}</svg> Estoque como razão imutável</li>
                        <li class="flex gap-2.5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-primary-600" aria-hidden="true">{!! $icones['check'] !!}</svg> Contas a pagar e a receber</li>
                    </ul>
                    <a href="{{ route('register') }}"
                       class="mt-8 inline-flex min-h-11 items-center rounded-full bg-primary-600 px-5 text-[13px] font-bold uppercase tracking-[0.05em] text-on-primary transition-colors hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                        Criar conta grátis
                    </a>
                </div>

                <div class="rounded-3xl border border-graphite-100 bg-graphite-50 p-7 sm:p-8">
                    <p class="etiqueta text-graphite-500">Avançado</p>
                    <p class="mt-2 text-sm text-graphite-500">Domínio próprio, com a marca do cliente já na tela de login.</p>
                    <ul class="mt-6 space-y-3 text-sm text-graphite-700">
                        <li class="flex gap-2.5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-graphite-400" aria-hidden="true">{!! $icones['check'] !!}</svg> Tudo do plano gratuito</li>
                        <li class="flex gap-2.5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-graphite-400" aria-hidden="true">{!! $icones['check'] !!}</svg> Domínio próprio</li>
                        <li class="flex gap-2.5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-graphite-400" aria-hidden="true">{!! $icones['check'] !!}</svg> Marca do cliente na tela de login</li>
                    </ul>
                    <p class="mt-8 text-sm leading-relaxed text-graphite-500">
                        Comece no gratuito. O avançado é ativado para quem já é
                        cliente.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------ o que falta --}}
    <section data-revelar class="border-t border-graphite-100 bg-graphite-50">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                O que ele ainda não faz
            </h2>
            <p class="mt-3 max-w-[58ch] text-graphite-600">
                Está aqui porque descobrir depois da contratação é pior do que ler
                agora.
            </p>

            <ul class="mt-8 grid gap-x-10 gap-y-4 sm:grid-cols-2">
                @foreach ([
                    'DRE e fechamento do mês',
                    'Tesouraria: caixa livre, reserva, meses de sobrevivência',
                    'Distribuição DF-e e manifestação do destinatário',
                    'Relatórios gerenciais',
                    'Cálculo de DIFAL',
                    'Contingência SVC',
                ] as $item)
                    <li class="flex gap-3 border-b border-graphite-200 py-3 text-graphite-700">
                        <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-graphite-400"></span>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>

            <p class="mt-8 max-w-[58ch] rounded-2xl border border-graphite-200 bg-white p-6 text-sm leading-relaxed text-graphite-600">
                O núcleo do financeiro já funciona, por isso a assinatura já diz Financeiro.
                O que falta é a leitura do resultado: DRE e
                tesouraria.
            </p>
        </div>
    </section>

    {{-- --------------------------------------------------------------- faq --}}
    <section id="faq" data-revelar class="border-t border-graphite-100">
        <div class="mx-auto max-w-3xl px-6 py-16">
            <h2 class="text-center font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                Perguntas frequentes
            </h2>

            <div class="mt-8 space-y-3">
                @foreach ([
                    ['Preciso trocar de sistema fiscal para usar?', 'Sim, é o sistema que emite a nota. Não precisa trocar o resto: importa o que já existe por XML.'],
                    ['Meu contador consegue mexer na regra fiscal sozinho?', 'Sim. Tela própria, permissão própria, sem depender de programador para mudar alíquota ou CST.'],
                    ['Serve para matriz e filial?', 'Sim. Um tenant pode ter mais de um emitente, cada um com CNPJ e certificado próprios.'],
                    ['O que ainda falta no sistema?', 'Está listado na seção acima, sem esconder.'],
                    ['O plano gratuito cobra alguma coisa?', 'Não. Entra pelo endereço da Venda Redonda, sem domínio próprio.'],
                    ['Onde ficam os meus dados?', 'No disco privado da conta, isolado por emitente. Certificado e senha nunca são logados.'],
                ] as [$pergunta, $resposta])
                    <details class="group rounded-2xl border border-graphite-100 bg-white px-6 py-5 open:shadow-sm">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-display text-base font-bold text-graphite-900 marker:content-none">
                            {{ $pergunta }}
                            <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full bg-graphite-50 text-graphite-500 transition-transform group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 max-w-[60ch] text-sm leading-relaxed text-graphite-600">{{ $resposta }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------- ação --}}
    <section data-revelar class="border-t border-graphite-100">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <div class="relative isolate overflow-hidden rounded-4xl border border-graphite-100 bg-graphite-50 px-8 py-14 text-center sm:px-14">
                <div aria-hidden="true" class="pointer-events-none absolute -top-20 left-1/2 size-96 -translate-x-1/2 rounded-full bg-primary-200/40 blur-3xl"></div>

                <div class="relative">
                    <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-900 sm:text-3xl">
                        Da nota ao caixa, a venda fecha redonda
                    </h2>
                    <p class="mt-3 text-graphite-600">Comece grátis, ou entre se já tem acesso.</p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('register') }}"
                           class="inline-flex min-h-12 items-center rounded-full bg-primary-600 px-6 text-[13px] font-bold uppercase tracking-[0.05em] text-on-primary shadow-md transition-colors hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            Criar conta grátis
                        </a>
                        <a href="{{ route('login') }}"
                           class="inline-flex min-h-12 items-center rounded-full border border-graphite-200 bg-white px-5 text-[13px] font-semibold text-graphite-700 transition-colors hover:bg-graphite-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            Entrar no sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

{{-- ------------------------------------------------------------- rodapé --}}
<footer class="border-t border-graphite-100">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-graphite-500">
        <span class="flex items-center gap-2.5">
            <x-marca-padrao class="size-5 text-graphite-700" />
            Venda Redonda
        </span>
        <span>Fiscal · Estoque · Financeiro</span>
    </div>
</footer>

</body>
</html>
