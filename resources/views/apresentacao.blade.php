{{--
    Apresentação do produto.

    Provisória de propósito: o roteamento por host já está de pé, mas o
    conteúdo desta página é trabalho próprio, e depende de decidir o que a
    marca promete. Hoje a assinatura diz FISCAL · ESTOQUE · FINANCEIRO e o
    módulo financeiro ainda não existe.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head', ['title' => 'Venda Redonda'])
</head>
<body class="min-h-dvh bg-graphite-900 font-sans text-graphite-200 antialiased">
    <main class="mx-auto flex min-h-dvh max-w-2xl flex-col items-center justify-center gap-8 px-6 text-center">

        <div class="flex items-center gap-3">
            <x-marca-padrao class="size-10 text-graphite-50" />
            <span class="font-display text-2xl font-extrabold uppercase tracking-[0.04em] text-graphite-50">
                Venda Redonda
            </span>
        </div>

        <p class="text-xl font-semibold leading-snug text-graphite-50">
            Da nota ao caixa, a venda fecha redonda.
        </p>

        <p class="max-w-prose text-graphite-400">
            Sistema fiscal e de estoque para distribuidoras independentes de bebidas.
            Emite NF-e com ICMS-ST, FCP, IBS, CBS e IS calculados no servidor, importa
            compras por XML e fecha o estoque contra o galpão.
        </p>

        <p class="etiqueta text-graphite-500">Fiscal · Estoque</p>

        <a href="https://app.{{ config('produto.dominio') }}"
           class="inline-flex min-h-11 items-center justify-center rounded-md border border-graphite-700 px-6 text-[13px] font-semibold uppercase tracking-[0.05em] text-graphite-50 transition-colors hover:bg-white/5">
            Entrar no sistema
        </a>

        <p class="etiqueta text-graphite-600">
            Página provisória
        </p>
    </main>
</body>
</html>
