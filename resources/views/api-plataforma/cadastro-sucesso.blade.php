<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nodo — Seu token</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-dvh items-center justify-center bg-graphite-50 px-4 py-10 font-sans text-graphite-900 antialiased">
    <div class="w-full max-w-lg rounded-lg border border-graphite-200 bg-white p-8 shadow-sm">
        <p class="etiqueta text-primary-600">Conta criada</p>
        <h1 class="display-title mt-1 text-2xl text-graphite-900">Este é o seu token</h1>
        <p class="mt-3 text-sm text-graphite-600">
            Guarde agora: ele não aparece de novo em lugar nenhum. Mande em
            todo pedido no cabeçalho <code class="rounded bg-graphite-100 px-1 py-0.5 text-xs">Authorization: Bearer &lt;token&gt;</code>.
        </p>

        <p class="num mt-6 break-all rounded-md border border-graphite-200 bg-graphite-50 p-4 text-sm">{{ $token }}</p>

        <div class="mt-6 grid gap-2 text-xs text-graphite-500">
            <p><span class="font-semibold text-graphite-700">1.</span> <code class="rounded bg-graphite-100 px-1 py-0.5">POST /whatsapp/v1/instancia</code> — cria sua instância de WhatsApp.</p>
            <p><span class="font-semibold text-graphite-700">2.</span> <code class="rounded bg-graphite-100 px-1 py-0.5">POST /whatsapp/v1/instancia/conectar</code> — pega o QR code e escaneia no celular.</p>
            <p><span class="font-semibold text-graphite-700">3.</span> <code class="rounded bg-graphite-100 px-1 py-0.5">POST /whatsapp/v1/mensagens</code> — manda mensagem, com <code class="rounded bg-graphite-100 px-1 py-0.5">numero</code> e <code class="rounded bg-graphite-100 px-1 py-0.5">texto</code>.</p>
        </div>
    </div>
</body>
</html>
