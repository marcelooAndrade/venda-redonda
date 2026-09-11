{{--
    Apresentação do produto, servida no domínio nu pelo RaizController.

    Sem movimento de rolagem de propósito. A marca pede peso de livro-razão, e
    livro-razão não anima. Isso também elimina a classe de bug em que elemento
    nasce invisível esperando JS e some para quem usa movimento reduzido.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    {{-- A marca vai cravada, não vem de `APP_NAME`. E sem `title`, senão o
         partial concatena e sai "Venda Redonda - Venda Redonda". --}}
    @include('partials.head', ['marca' => 'Venda Redonda'])

    @php
        $descricao = 'Sistema fiscal e de estoque para distribuidoras independentes de bebidas. '
            .'Emite NF-e com ICMS-ST, FCP, IBS, CBS e IS calculados no servidor, importa compras '
            .'por XML e fecha o estoque contra o galpão.';
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
            'applicationSubCategory' => 'Emissor de NF-e e controle de estoque',
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
<body class="min-h-dvh bg-graphite-900 font-sans text-graphite-300 antialiased">

<a href="#conteudo"
   class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-graphite-50 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-graphite-900">
    Ir para o conteúdo
</a>

{{-- ---------------------------------------------------------------- topo --}}
<header class="border-b border-white/10">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-5">
        <span class="flex items-center gap-2.5">
            <x-marca-padrao class="size-7 text-graphite-50" />
            <span class="font-display text-base font-extrabold uppercase tracking-[0.04em] text-graphite-50">
                Venda Redonda
            </span>
        </span>

        <a href="{{ route('login') }}"
           class="inline-flex min-h-10 items-center rounded-md border border-white/20 px-4 text-[13px] font-semibold text-graphite-100 transition-colors hover:bg-white/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
            Entrar no sistema
        </a>
    </div>
</header>

<main id="conteudo">

    {{-- ------------------------------------------------------------ herói --}}
    <section class="mx-auto max-w-5xl px-6 py-16 md:py-24">
        <div class="grid items-start gap-12 lg:grid-cols-[1.05fr_0.95fr]">

            <div class="min-w-0">
                <h1 class="font-display text-3xl font-extrabold leading-[1.08] tracking-[-0.01em] text-graphite-50 sm:text-4xl">
                    O sistema diz 200 caixas.<br>
                    O galpão tem 170.
                </h1>

                <p class="mt-6 max-w-[46ch] text-lg leading-relaxed text-graphite-300">
                    Venda redonda é quando esses dois números são o mesmo, e a nota, o
                    galpão e o caixa contam a mesma história no fim do mês.
                </p>

                <p class="mt-4 max-w-[52ch] text-graphite-400">
                    Sistema fiscal e de estoque feito para distribuidoras independentes
                    de bebidas.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a href="{{ route('login') }}"
                       class="inline-flex min-h-12 items-center rounded-md bg-graphite-50 px-6 text-[13px] font-bold uppercase tracking-[0.05em] text-graphite-900 transition-colors hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                        Entrar no sistema
                    </a>
                    <a href="#o-que-faz"
                       class="inline-flex min-h-12 items-center px-2 text-[13px] font-semibold text-graphite-300 underline decoration-graphite-600 underline-offset-4 transition-colors hover:text-graphite-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                        Ver o que ele faz
                    </a>
                </div>
            </div>

            {{-- A conferência. É aqui que o vermelhão aparece, e só aqui na
                 dobra: é o ponto que fecha, que é a regra de uso da cor. --}}
            <figure class="min-w-0 rounded-lg border border-white/10 bg-graphite-800 p-6 sm:p-7">
                {{-- Sobre o grafite-800 o piso de texto é o tom 300: o 400 dá 4,19 e o
                     500 dá 2,78, ambos medidos. A hierarquia entre as duas linhas
                     vem de subir a primeira, não de baixar a segunda. --}}
                <figcaption class="text-sm text-graphite-200">
                    Conferência de estoque, exemplo
                    <span class="mt-1 block text-graphite-300">Cerveja 600&nbsp;ml, caixa com 12</span>
                </figcaption>

                <dl class="mt-6 divide-y divide-white/10 border-y border-white/10">
                    <div class="flex items-baseline justify-between gap-4 py-3">
                        <dt class="text-graphite-300">O sistema diz</dt>
                        <dd class="num text-lg font-semibold text-graphite-100">200</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 py-3">
                        <dt class="text-graphite-300">O galpão tem</dt>
                        <dd class="num text-lg font-semibold text-graphite-100">200</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 py-3">
                        <dt class="font-semibold text-graphite-50">Diferença</dt>
                        <dd class="flex items-center gap-2.5">
                            <span class="num text-lg font-bold text-graphite-50">0</span>
                            <span class="rounded bg-primary-600 px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.08em] text-on-primary">
                                Confere
                            </span>
                        </dd>
                    </div>
                </dl>

                <p class="mt-5 text-sm leading-relaxed text-graphite-300">
                    A compra entra pelo XML do fornecedor e a baixa acontece no
                    momento da emissão. A diferença não é corrigida depois: ela
                    nasce zero.
                </p>
            </figure>
        </div>
    </section>

    {{-- ------------------------------------------------------- o que faz --}}
    <section id="o-que-faz" class="border-t border-white/10">
        <div class="mx-auto max-w-5xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-50">
                Três coisas, e elas dependem uma da outra
            </h2>

            <div class="mt-10 grid gap-px overflow-hidden rounded-lg bg-white/10 sm:grid-cols-3">
                @foreach ([
                    ['A nota sai certa de primeira', 'ICMS-ST, FCP, IBS, CBS e IS calculados no servidor, nunca no navegador. Se a SEFAZ recusar, o sistema traduz o código do erro em português e diz o que fazer.'],
                    ['O estoque bate com o galpão', 'Movimento nunca é editado nem apagado. Correção é lançamento de estorno, então o Kardex continua sendo registro fiel de tudo o que entrou e saiu.'],
                    ['O contador recebe fechado', 'O período inteiro em um ZIP: emitidas, canceladas, cartas de correção, inutilizações e entradas, com resumo que abre no Excel em português sem acento quebrado.'],
                ] as [$titulo, $texto])
                    <div class="bg-graphite-900 p-6">
                        <h3 class="font-display text-base font-bold text-graphite-50">{{ $titulo }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-graphite-400">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------- sequência de fato --}}
    <section class="border-t border-white/10">
        <div class="mx-auto max-w-5xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-50">
                Da compra até o contador
            </h2>
            <p class="mt-3 max-w-[58ch] text-graphite-400">
                É uma volta só, e cada etapa é a entrada da seguinte. Nenhuma pede
                digitação do que já estava na nota do fornecedor.
            </p>

            <ol class="mt-10 grid gap-x-8 gap-y-8 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['Chega a compra', 'O XML do fornecedor entra solto, em lote ou em ZIP. O fornecedor é criado a partir do próprio arquivo.'],
                    ['Confere quem é o quê', 'O produto do fornecedor é ligado ao seu. Na segunda nota do mesmo fornecedor, ele já entra reconhecido.'],
                    ['Entra no estoque', 'Só depois da sua confirmação. Registrar e confirmar são etapas separadas de propósito.'],
                    ['Sai a venda', 'A nota é montada, os tributos são calculados no servidor e a baixa acontece na transmissão.'],
                    ['Fecha o mês', 'O pacote do período sai pronto para o contador, sem ninguém montar pasta à mão.'],
                ] as $i => [$titulo, $texto])
                    <li class="min-w-0">
                        <span class="num block text-sm font-bold text-primary-600">{{ $i + 1 }}</span>
                        <h3 class="mt-2 font-display text-base font-bold text-graphite-50">{{ $titulo }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-graphite-400">{{ $texto }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ------------------------------------------------- regra é do contador --}}
    <section class="border-t border-white/10">
        <div class="mx-auto max-w-5xl px-6 py-16">
            {{-- Uma coluna só, como as outras seções. Em duas colunas o título
                 é curto e deixava metade da largura vazia abaixo dele. --}}
            <div>
                <h2 class="max-w-[20ch] font-display text-2xl font-extrabold leading-tight tracking-[-0.01em] text-graphite-50">
                    Quem escreve a regra fiscal é o seu contador
                </h2>
                <div class="mt-6 min-w-0 space-y-4 text-graphite-300">
                    <p class="max-w-[60ch] leading-relaxed">
                        Não existe alíquota, CST, CSOSN ou CFOP escrito dentro do
                        sistema. O contador entra numa tela própria, com permissão
                        própria, e escreve a regra que vale para a sua operação.
                    </p>
                    <p class="max-w-[60ch] leading-relaxed">
                        Toda regra tem data de vigência. Quando a lei muda, a anterior
                        recebe fim de vigência em vez de ser apagada, e a nota de março
                        continua conferindo com a regra de março.
                    </p>
                    <p class="max-w-[60ch] leading-relaxed text-graphite-400">
                        É por isso que a Reforma não vira reescrita de sistema: IBS, CBS
                        e IS são regra cadastrada, com a data em que passam a valer.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------ o que falta --}}
    <section class="border-t border-white/10 bg-graphite-800">
        <div class="mx-auto max-w-5xl px-6 py-16">
            <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-50">
                O que ele ainda não faz
            </h2>
            <p class="mt-3 max-w-[58ch] text-graphite-300">
                Está aqui porque descobrir depois da contratação é pior do que ler
                agora.
            </p>

            <ul class="mt-8 grid gap-x-10 gap-y-4 sm:grid-cols-2">
                @foreach ([
                    'DRE e fechamento do mês',
                    'Fluxo de caixa projetado',
                    'Distribuição DF-e e manifestação do destinatário',
                    'Relatórios gerenciais',
                    'Cálculo de DIFAL',
                    'Contingência SVC',
                ] as $item)
                    <li class="flex gap-3 border-b border-white/10 py-3 text-graphite-300">
                        <span aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-graphite-600"></span>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>

            <p class="mt-8 max-w-[58ch] text-sm leading-relaxed text-graphite-300">
                Contas a pagar e a receber já funcionam, com baixa lançada no caixa.
                O que falta é a leitura do resultado: DRE e projeção. Até ela existir a
                assinatura da marca diz o que o sistema entrega hoje.
            </p>
        </div>
    </section>

    {{-- ------------------------------------------------------------- ação --}}
    <section class="border-t border-white/10">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-6 px-6 py-16">
            <div class="min-w-0">
                <h2 class="font-display text-2xl font-extrabold tracking-[-0.01em] text-graphite-50">
                    Da nota ao caixa, a venda fecha redonda
                </h2>
                <p class="mt-2 text-graphite-400">Já tem acesso? Entre pelo sistema.</p>
            </div>
            <a href="{{ route('login') }}"
               class="inline-flex min-h-12 items-center rounded-md bg-graphite-50 px-6 text-[13px] font-bold uppercase tracking-[0.05em] text-graphite-900 transition-colors hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                Entrar no sistema
            </a>
        </div>
    </section>
</main>

{{-- ------------------------------------------------------------- rodapé --}}
<footer class="border-t border-white/10">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-graphite-400">
        <span class="flex items-center gap-2.5">
            <x-marca-padrao class="size-5 text-graphite-300" />
            Venda Redonda
        </span>
        <span>Fiscal · Estoque</span>
    </div>
</footer>

</body>
</html>
