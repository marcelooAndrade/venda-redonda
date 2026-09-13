{{--
    Casca das páginas de erro.

    O CSS vai embutido de propósito, sem `@vite`: a página de erro precisa
    aparecer inteira justamente quando alguma coisa quebrou, e depender do
    manifesto de assets criaria o caso em que a tela do erro 500 também
    falha. As cores são as mesmas do `TemaMarca`, cravadas aqui porque
    folha embutida não enxerga as variáveis do bundle.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo') - Venda Redonda</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        /* O `x-marca-padrao` pinta o quarto de disco com esta variável. */
        :root { color-scheme: light; --color-primary-600: #e4572e; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f6f6f6;
            color: #343f42;
            font-family: 'Manrope', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .cartao {
            width: 100%;
            max-width: 30rem;
            padding: 40px;
            background: #fff;
            border: 1px solid #e7e8e9;
            border-radius: 20px;
            box-shadow: 0 24px 60px -40px rgba(14, 27, 31, 0.45);
        }
        .marca { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
        .marca svg { width: 28px; height: 28px; }
        .marca span {
            font-weight: 800;
            font-size: 14px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #0e1b1f;
        }
        .codigo {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.16em;
            color: #c04927;
            margin: 0 0 8px;
        }
        h1 { margin: 0 0 12px; font-size: 27px; line-height: 1.25; color: #0e1b1f; font-weight: 800; }
        p { margin: 0; color: #4c5659; }
        .acoes { margin-top: 28px; display: flex; flex-wrap: wrap; gap: 10px; }
        a.botao {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 20px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            background: #e4572e;
            color: #0e1b1f;
            border: 1px solid #c04927;
        }
        a.botao.secundario { background: #fff; color: #343f42; border-color: #cfd1d2; font-weight: 600; }
        a.botao:focus-visible { outline: 2px solid #c04927; outline-offset: 2px; }
    </style>
</head>
<body>
    <main class="cartao">
        <div class="marca">
            <x-marca-padrao style="color:#0e1b1f" />
            <span>Venda Redonda</span>
        </div>

        <p class="codigo">ERRO @yield('codigo')</p>
        <h1>@yield('titulo')</h1>
        <p>@yield('mensagem')</p>

        <div class="acoes">
            <a class="botao" href="{{ url('/') }}">Voltar ao início</a>
            @hasSection('acao')
                @yield('acao')
            @endif
        </div>
    </main>
</body>
</html>
