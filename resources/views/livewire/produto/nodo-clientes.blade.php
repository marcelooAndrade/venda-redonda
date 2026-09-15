@php
    $rotuloStatus = fn (?string $status): string => match ($status) {
        'connected' => 'Conectado',
        'connecting' => 'Conectando',
        'disconnected' => 'Desconectado',
        default => 'Sem instância',
    };

    $corStatus = fn (?string $status): string => match ($status) {
        'connected' => 'bg-success-100 text-success-700',
        'connecting' => 'bg-primary-100 text-primary-700',
        default => 'bg-graphite-100 text-graphite-700',
    };
@endphp

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Produto"
        title="Clientes Nodo"
        description="Cada módulo é pago à parte: escolha aqui quais um cliente usa.">
        <x-slot:actions>
            <x-ui.button size="sm" wire:click="$toggle('formularioAberto')">
                {{ $formularioAberto ? 'Cancelar' : 'Novo cliente' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($formularioAberto)
        <x-ui.card title="Novo cliente">
            <div class="mb-5">
                <x-ui.field label="Buscar destinatário já cadastrado" for="busca-destinatario"
                    hint="Preenche nome e e-mail a partir de um destinatário marcado como cliente, em qualquer empresa do sistema.">
                    <x-ui.input id="busca-destinatario" wire:model.live.debounce.400ms="buscaDestinatario" placeholder="Nome ou documento" />
                </x-ui.field>

                @if ($this->resultadosBusca->isNotEmpty())
                    <div class="mt-2 divide-y divide-graphite-100 rounded-md border border-graphite-200">
                        @foreach ($this->resultadosBusca as $pessoa)
                            <button type="button" wire:click="selecionarDestinatario({{ $pessoa->id }})"
                                    class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-graphite-50">
                                <span class="font-medium text-graphite-900">{{ $pessoa->nome_fantasia ?: $pessoa->razao_social }}</span>
                                <span class="text-xs text-graphite-500">{{ $pessoa->documento }} — {{ $pessoa->emitente?->nome_fantasia ?: $pessoa->emitente?->razao_social }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <form wire:submit="criarCliente" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nome" for="novo-nome" required :error="$errors->first('novoNome')">
                    <x-ui.input id="novo-nome" wire:model="novoNome" />
                </x-ui.field>
                <x-ui.field label="E-mail" for="novo-email" required :error="$errors->first('novoEmail')">
                    <x-ui.input id="novo-email" type="email" wire:model="novoEmail" />
                </x-ui.field>

                <div class="sm:col-span-2">
                    <p class="etiqueta mb-2 text-graphite-500">Módulos</p>
                    <div class="flex flex-wrap gap-5">
                        @foreach (\App\Enums\ModuloApi::cases() as $modulo)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="novosModulos" value="{{ $modulo->value }}" class="size-4 accent-graphite-900">
                                {{ $modulo->rotulo() }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <x-ui.button type="submit">Cadastrar e gerar token</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    @if ($tokenRevelado)
        <x-ui.alert variant="success" title="Token do cliente">
            <p class="mb-2">Copie agora: ele não aparece de novo em lugar nenhum.</p>
            <p class="num break-all rounded-md border border-graphite-200 bg-white p-3 text-sm">{{ $tokenRevelado }}</p>
            <button type="button" wire:click="fecharToken" class="etiqueta mt-2 text-graphite-500 hover:text-graphite-700">Fechar</button>
        </x-ui.alert>
    @endif

    <x-ui.card title="Todos os clientes" subtitle="Mais recente primeiro" :padded="false">
        @if ($this->clientes->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="Nenhum cliente cadastrado" description="Use o botão “Novo cliente” acima para cadastrar o primeiro." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Cliente</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Cadastrado em</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Módulos</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">WhatsApp</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->clientes as $cliente)
                        <tr class="border-b border-graphite-100 last:border-0" wire:key="cliente-{{ $cliente->id }}">
                            <td class="px-5 py-3">
                                <p class="font-medium text-graphite-900">{{ $cliente->nome }}</p>
                                <p class="text-xs text-graphite-500">{{ $cliente->email }}</p>
                            </td>
                            <td class="num px-5 py-3 text-graphite-700">{{ $cliente->created_at?->format('d/m/Y') }}</td>
                            <td class="px-5 py-3">
                                @foreach (\App\Enums\ModuloApi::cases() as $modulo)
                                    @if ($cliente->temModulo($modulo))
                                        <span class="etiqueta mr-1 rounded-full bg-graphite-100 px-2 py-1 text-graphite-700">{{ $modulo->rotulo() }}</span>
                                    @endif
                                @endforeach
                                @if (empty($cliente->modulos))
                                    <span class="text-graphite-500">nenhum</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="etiqueta rounded-full px-2 py-1 {{ $corStatus($cliente->whatsappInstancia?->status) }}">
                                    {{ $rotuloStatus($cliente->whatsappInstancia?->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-ui.button variant="ghost" size="sm" wire:click="iniciarEdicaoModulos({{ $cliente->id }})">Módulos</x-ui.button>
                                <x-ui.button
                                    variant="ghost" size="sm"
                                    wire:click="reemitirToken({{ $cliente->id }})"
                                    wire:confirm="Isso invalida o token atual de {{ $cliente->nome }}. Continuar?">
                                    Reemitir token
                                </x-ui.button>
                            </td>
                        </tr>

                        @if ($editandoModulosDe === $cliente->id)
                            <tr class="border-b border-graphite-100 bg-graphite-50 last:border-0">
                                <td colspan="5" class="px-5 py-4">
                                    <p class="etiqueta mb-2 text-graphite-500">Módulos de {{ $cliente->nome }}</p>
                                    <div class="flex flex-wrap items-center gap-5">
                                        @foreach (\App\Enums\ModuloApi::cases() as $modulo)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="checkbox" wire:model="modulosEmEdicao" value="{{ $modulo->value }}" class="size-4 accent-graphite-900">
                                                {{ $modulo->rotulo() }}
                                            </label>
                                        @endforeach
                                        <x-ui.button size="sm" wire:click="salvarModulos({{ $cliente->id }})">Salvar</x-ui.button>
                                        <x-ui.button variant="ghost" size="sm" wire:click="cancelarEdicaoModulos">Cancelar</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </x-ui.table>

            @if ($this->clientes->hasPages())
                <div class="px-5 py-4">{{ $this->clientes->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
