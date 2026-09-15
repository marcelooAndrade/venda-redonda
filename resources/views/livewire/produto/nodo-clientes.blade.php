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
        description="Quem se cadastrou no Nodo, e o status da instância de WhatsApp de cada um." />

    @if ($tokenReemitido)
        <x-ui.alert variant="success" title="Novo token gerado">
            <p class="mb-2">Copie agora: ele não aparece de novo em lugar nenhum.</p>
            <p class="num break-all rounded-md border border-graphite-200 bg-white p-3 text-sm">{{ $tokenReemitido }}</p>
            <button type="button" wire:click="fecharToken" class="etiqueta mt-2 text-graphite-500 hover:text-graphite-700">Fechar</button>
        </x-ui.alert>
    @endif

    <x-ui.card title="Todos os clientes" subtitle="Mais recente primeiro" :padded="false">
        @if ($this->clientes->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="Nenhum cliente cadastrado" description="Quando alguém se cadastrar em nodo.dev.br/cadastro, aparece aqui." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Cliente</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Cadastrado em</th>
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
                                <span class="etiqueta rounded-full px-2 py-1 {{ $corStatus($cliente->whatsappInstancia?->status) }}">
                                    {{ $rotuloStatus($cliente->whatsappInstancia?->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-ui.button
                                    variant="ghost" size="sm"
                                    wire:click="reemitirToken({{ $cliente->id }})"
                                    wire:confirm="Isso invalida o token atual de {{ $cliente->nome }}. Continuar?">
                                    Reemitir token
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            @if ($this->clientes->hasPages())
                <div class="px-5 py-4">{{ $this->clientes->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
