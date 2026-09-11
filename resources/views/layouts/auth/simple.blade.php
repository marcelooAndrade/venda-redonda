<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    {{-- Fundo claro de propósito. O `class="dark"` acima é do starter kit e
         está inerte: os componentes do Flux resolvem nas cores de modo claro.
         Escurecer só o fundo deixaria todo o texto ilegível. Tornar esta tela
         escura de verdade é outro trabalho, e não é este. --}}
    <body class="min-h-screen bg-white antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                @php
                    // O tenant já vem resolvido pelo host: o ResolverTenant roda no
                    // grupo web inteiro, inclusive nas rotas de visitante. Então em
                    // app.rcmdobrasil.com.br esta tela já sabe de quem é, antes de
                    // existir usuário.
                    $tenant = app(\App\Support\TenantAtual::class)->obter();
                @endphp

                <a href="{{ route('home') }}" class="mb-2 flex flex-col items-center gap-3 font-medium" wire:navigate>
                    {{-- A logo vai sobre uma placa grafite, e não sobre o branco
                         da página. O sistema guarda um arquivo só por tenant, e
                         ele foi enviado para viver na barra lateral, que é
                         escura. Sobre branco, logo clara desapareceria. --}}
                    <span class="flex items-center justify-center rounded-md bg-graphite-900 px-5 py-4">
                        {{-- Marca do cliente na porta é benefício de plano. Hoje a
                             condição do plano é redundante, porque só o plano
                             avançado pode ter domínio próprio, e só por domínio
                             próprio o host resolve um tenant. Fica explícita
                             porque é regra de negócio, e regra que vive só em
                             comentário some na primeira refatoração. --}}
                        @if ($tenant?->logo_path && $tenant->plano->permiteMarcaPropria())
                            <img src="{{ route('logo') }}" alt="{{ $tenant->nome }}"
                                 class="h-9 w-auto max-w-[12rem] object-contain">
                        @else
                            <span class="flex items-center gap-2.5">
                                <x-marca-padrao class="size-7 text-graphite-50" />
                                <span class="font-display text-sm font-extrabold uppercase tracking-[0.04em] text-graphite-50">
                                    Venda Redonda
                                </span>
                            </span>
                        @endif
                    </span>
                    <span class="sr-only">{{ $tenant?->nome ?? config('app.name') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
