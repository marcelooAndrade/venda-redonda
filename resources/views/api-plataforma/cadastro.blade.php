<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nodo — Cadastro</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-dvh items-center justify-center bg-graphite-50 px-4 py-10 font-sans text-graphite-900 antialiased">
    <div class="w-full max-w-md rounded-lg border border-graphite-200 bg-white p-8 shadow-sm">
        <p class="etiqueta text-graphite-500">Nodo</p>
        <h1 class="display-title mt-1 text-2xl text-graphite-900">Módulo WhatsApp</h1>
        <p class="mt-3 text-sm text-graphite-600">
            Cadastre-se e receba um token de acesso. Sem senha: o token é a sua
            credencial para toda chamada à API.
        </p>

        @if (! empty($erros))
            <div class="mt-5 rounded-md border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
                <ul class="list-disc pl-4">
                    @foreach ($erros as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('api-plataforma.cadastro.store') }}" class="mt-6 grid gap-4">
            <div>
                <label for="nome" class="etiqueta text-graphite-500">Nome</label>
                <input id="nome" name="nome" value="{{ $antigo['nome'] ?? '' }}" required
                       class="mt-1 w-full rounded-md border border-graphite-300 px-3 py-2 text-sm focus:border-primary-600 focus:outline-none">
            </div>
            <div>
                <label for="email" class="etiqueta text-graphite-500">E-mail</label>
                <input id="email" name="email" type="email" value="{{ $antigo['email'] ?? '' }}" required
                       class="mt-1 w-full rounded-md border border-graphite-300 px-3 py-2 text-sm focus:border-primary-600 focus:outline-none">
            </div>
            <button type="submit"
                    class="mt-2 rounded-md bg-graphite-900 px-4 py-2.5 text-sm font-semibold uppercase tracking-[0.05em] text-white hover:bg-graphite-700">
                Criar minha conta
            </button>
        </form>
    </div>
</body>
</html>
