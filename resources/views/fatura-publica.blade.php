{{--
    A fatura como o cliente a vê. Porta de `PublicInvoice.tsx` e `invoice.css`
    do projeto Marcelo Andrade, sobre os tokens daqui: o dourado de lá virou a
    primária do tenant dono da fatura, que chega pelo partial `head` depois
    que o controller define o tenant da requisição. Sem login, sem menu, e
    sem pedir nada além de ler o QR Code ou copiar o código.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head', ['title' => 'Fatura '.$fatura->titulo, 'marca' => $tenant?->rotulo() ?? $emitente->nome_fantasia ?: $emitente->razao_social])
    {{-- Sem componente Livewire na página, o Alpine não vem sozinho: é o
         `@livewireScripts` no fim do body que o traz. Até ele rodar, o
         `x-cloak` esconde as parcelas que não estão selecionadas. --}}
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-graphite-50 text-graphite-900 antialiased"
    x-data="{ selecionada: {{ $selecionada }}, copiado: false, copiar(codigo) { navigator.clipboard.writeText(codigo); this.copiado = true; setTimeout(() => this.copiado = false, 1800) } }">

    <main class="mx-auto w-full max-w-5xl px-4 pt-6 pb-14 sm:px-6 sm:pt-8">

        <header class="flex items-center gap-3 border-b border-graphite-200 pb-5">
            @if ($temLogo)
                <img src="{{ route('fatura.publica.logo', ['token' => $fatura->public_token]) }}" alt="" aria-hidden="true" class="size-10 rounded-md bg-graphite-900 object-contain p-1.5">
            @else
                <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-md bg-graphite-900 text-sm font-bold text-white">
                    {{ mb_strtoupper(mb_substr($tenant?->rotulo() ?? $emitente->razao_social, 0, 1)) }}
                </span>
            @endif
            <div class="text-sm leading-tight font-bold">
                <span class="text-primary-600">{{ $tenant?->rotulo() ?? ($emitente->nome_fantasia ?: $emitente->razao_social) }}</span>
                <small class="etiqueta mt-1 block text-graphite-500">Fatura digital</small>
            </div>
            <p class="ml-auto hidden items-center gap-1.5 text-xs text-graphite-500 sm:flex">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Link seguro
            </p>
        </header>

        <section class="pt-10 pb-8 sm:pt-14">
            <p class="etiqueta text-graphite-500">Fatura para</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-5xl">{{ $cliente?->razao_social ?? 'Cliente' }}</h1>
            @if ($cliente?->documento)
                <span class="num mt-2 block text-sm text-graphite-500">{{ strlen($cliente->documento) === 14 ? preg_replace('/(.{2})(.{3})(.{3})(.{4})(.{2})/', '$1.$2.$3/$4-$5', $cliente->documento) : preg_replace('/(.{3})(.{3})(.{3})(.{2})/', '$1.$2.$3-$4', $cliente->documento) }}</span>
            @endif
            <div class="mt-7 border-l-[3px] border-primary-600 bg-white px-5 py-4 shadow-xs">
                <strong class="text-sm">{{ $fatura->titulo }}</strong>
                @if (filled($fatura->observacoes))
                    <p class="mt-2 text-sm leading-relaxed whitespace-pre-line text-graphite-600">{{ $fatura->observacoes }}</p>
                @endif
            </div>
        </section>

        <section class="grid overflow-hidden rounded-lg border border-graphite-200 bg-white shadow-sm sm:grid-cols-3" aria-label="Resumo da fatura">
            @foreach ([['Total da fatura', $totais['total']], ['Já pago', $totais['pago']], ['Em aberto', $totais['emAberto']]] as [$rotulo, $valor])
                <article class="border-t border-graphite-200 px-5 py-5 first:border-t-0 sm:border-t-0 sm:border-l sm:first:border-l-0">
                    <span class="block text-xs text-graphite-500">{{ $rotulo }}</span>
                    <strong class="num mt-2 block text-xl tracking-tight">R$ {{ App\Support\Dinheiro::formatar($valor) }}</strong>
                </article>
            @endforeach
        </section>

        <section class="mt-9" aria-label="Parcelas da fatura">
            <header class="flex items-end justify-between gap-5">
                <div>
                    <p class="etiqueta text-primary-600">Escolha a parcela</p>
                    <h2 class="mt-1 text-base font-bold">{{ $parcelas->count() }} {{ $parcelas->count() === 1 ? 'parcela nesta fatura' : 'parcelas nesta fatura' }}</h2>
                </div>
                <div class="flex gap-2">
                    <button type="button" :disabled="selecionada === 0" @click="selecionada--" aria-label="Parcela anterior"
                        class="flex size-9 items-center justify-center rounded-md border border-graphite-300 bg-white text-graphite-600 disabled:cursor-not-allowed disabled:opacity-35">‹</button>
                    <button type="button" :disabled="selecionada === {{ $parcelas->count() - 1 }}" @click="selecionada++" aria-label="Próxima parcela"
                        class="flex size-9 items-center justify-center rounded-md border border-graphite-300 bg-white text-graphite-600 disabled:cursor-not-allowed disabled:opacity-35">›</button>
                </div>
            </header>

            <div class="mt-4 flex gap-2 overflow-x-auto pb-2">
                @foreach ($parcelas as $i => $p)
                    <button type="button" @click="selecionada = {{ $i }}"
                        :class="selecionada === {{ $i }} ? 'border-primary-600 ring-2 ring-primary-600/15' : 'border-graphite-200'"
                        class="min-w-40 rounded-md border bg-white px-4 py-3 text-left {{ $p['estado'] === 'paga' ? 'bg-success-50' : '' }} {{ $p['estado'] === 'vencida' ? 'border-danger-200' : '' }}">
                        <span class="block text-xs text-graphite-500">{{ $p['numero'] }}ª parcela</span>
                        <strong class="num mt-2 block text-sm">R$ {{ App\Support\Dinheiro::formatar($p['valorCentavos']) }}</strong>
                        <small @class([
                            'num mt-1 block text-xs',
                            'font-bold text-success-700' => $p['estado'] === 'paga',
                            'text-danger-700' => $p['estado'] === 'vencida',
                            'text-graphite-500' => in_array($p['estado'], ['aguardando', 'cancelada'], true),
                        ])>{{ ['paga' => 'Paga', 'vencida' => 'Vencida', 'cancelada' => 'Cancelada', 'aguardando' => $p['vencimento']->format('d/m/Y')][$p['estado']] }}</small>
                    </button>
                @endforeach
            </div>
        </section>

        @foreach ($parcelas as $i => $p)
            <section x-show="selecionada === {{ $i }}" x-cloak
                class="mt-4 grid gap-8 rounded-xl border border-graphite-200 bg-white p-6 shadow-md sm:p-10 {{ $p['qr'] ? 'lg:grid-cols-[minmax(0,1fr)_20rem]' : '' }}">
                <div class="self-center">
                    <span @class([
                        'etiqueta inline-flex rounded-full border px-2.5 py-1.5',
                        'border-success-200 bg-success-50 text-success-700' => $p['estado'] === 'paga',
                        'border-danger-200 bg-danger-50 text-danger-700' => $p['estado'] === 'vencida',
                        'border-graphite-300 text-graphite-600' => in_array($p['estado'], ['aguardando', 'cancelada'], true),
                    ])>{{ ['paga' => 'Parcela paga', 'vencida' => 'Parcela vencida', 'cancelada' => 'Parcela cancelada', 'aguardando' => 'Aguardando pagamento'][$p['estado']] }}</span>
                    <h2 class="mt-5 text-2xl font-bold tracking-tight sm:text-3xl">{{ $p['descricao'] }}</h2>
                    <p class="num mt-2 text-sm text-graphite-600">Vencimento em {{ $p['vencimento']->translatedFormat('d \d\e F \d\e Y') }}</p>
                    <strong class="num mt-8 block text-4xl tracking-tight sm:text-5xl">R$ {{ App\Support\Dinheiro::formatar($p['valorCentavos']) }}</strong>

                    @if ($p['estado'] === 'paga')
                        <div class="mt-8 flex max-w-md items-start gap-3 rounded-md border border-success-200 bg-success-50 p-4 text-success-800">
                            <svg class="mt-0.5 size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <b class="block text-sm">Pagamento recebido</b>
                                <span class="mt-1 block text-xs text-success-700">Selecione outra parcela acima para consultar ou pagar.</span>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($p['qr'])
                    <div class="border-t border-graphite-200 pt-6 text-center lg:border-t-0 lg:pt-0">
                        <div class="mx-auto grid size-60 place-items-center rounded-lg border border-graphite-200 bg-white p-2.5 [&_svg]:h-full [&_svg]:w-full">
                            {!! $p['qr'] !!}
                        </div>
                        <h3 class="mt-4 text-base font-bold">Pague com Pix</h3>
                        <p class="mx-auto mt-2 max-w-xs text-xs leading-relaxed text-graphite-500">Leia o QR Code no aplicativo do seu banco ou copie o código abaixo.</p>
                        <button type="button" @click="copiar(@js($p['pix']))"
                            class="mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-graphite-900 px-4 text-xs font-bold text-white hover:bg-graphite-700">
                            <span x-show="!copiado">Copiar código Pix</span>
                            <span x-show="copiado" x-cloak>Código copiado</span>
                        </button>
                    </div>
                @endif
            </section>
        @endforeach

        <footer class="mt-6 border-t border-graphite-200 pt-5 text-center text-graphite-500">
            <p class="text-xs">Após o pagamento, envie o comprovante ao responsável pelo seu atendimento.</p>
            <span class="mt-2 block text-[11px]">Esta página não solicita senha, código bancário ou dados do cartão.</span>
        </footer>
    </main>

    @livewireScripts
</body>
</html>
