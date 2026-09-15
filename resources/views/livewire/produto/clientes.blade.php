@php
    $formatarDocumento = fn (?string $documento): string => $documento === null
        ? ''
        : (strlen($documento) === 14
            ? preg_replace('/(.{2})(.{3})(.{3})(.{4})(.{2})/', '$1.$2.$3/$4-$5', $documento)
            : preg_replace('/(.{3})(.{3})(.{3})(.{2})/', '$1.$2.$3-$4', $documento));
@endphp

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Produto"
        title="Clientes"
        description="Clientes de desenvolvimento sob medida da empresa Marcelo Andrade, marcados como cliente no cadastro de destinatários dela." />

    @if ($tenant === null)
        <x-ui.card>
            <x-ui.empty-state
                title="Falta configurar o tenant administrativo"
                description="Defina TENANT_ADMINISTRATIVO_SLUG no .env com o slug da empresa Marcelo Andrade, para esta tela saber de qual empresa mostrar os clientes." />
        </x-ui.card>
    @else
        <x-ui.card title="Clientes de {{ $tenant->nome }}" subtitle="Destinatários marcados como cliente" :padded="false">
            @if ($clientes->isEmpty())
                <div class="p-5">
                    <x-ui.empty-state title="Nenhum cliente ainda" description="Quando um destinatário desta empresa for marcado como cliente, ele aparece aqui." />
                </div>
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200 text-left">
                            <th class="etiqueta px-5 py-2 text-graphite-500">Cliente</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Documento</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Contato</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Cidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $cliente)
                            <tr class="border-b border-graphite-100 last:border-0" wire:key="cliente-{{ $cliente->id }}">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-graphite-900">{{ $cliente->razao_social }}</p>
                                    @if ($cliente->nome_fantasia)
                                        <p class="text-xs text-graphite-500">{{ $cliente->nome_fantasia }}</p>
                                    @endif
                                </td>
                                <td class="num px-5 py-3 text-graphite-700">{{ $formatarDocumento($cliente->documento) }}</td>
                                <td class="px-5 py-3">
                                    @if ($cliente->email || $cliente->telefone)
                                        @if ($cliente->email)
                                            <p class="text-graphite-900">{{ $cliente->email }}</p>
                                        @endif
                                        @if ($cliente->telefone)
                                            <p class="num text-xs text-graphite-500">{{ $cliente->telefone }}</p>
                                        @endif
                                    @else
                                        <span class="text-graphite-500">sem contato</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-graphite-700">{{ $cliente->municipio ? "{$cliente->municipio}/{$cliente->uf}" : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif
</div>
