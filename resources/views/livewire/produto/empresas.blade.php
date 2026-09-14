@php
    $formatarCnpj = fn (?string $cnpj): string => $cnpj === null || strlen($cnpj) !== 14
        ? ($cnpj ?? '')
        : preg_replace('/(.{2})(.{3})(.{3})(.{4})(.{2})/', '$1.$2.$3/$4-$5', $cnpj);
@endphp

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Produto"
        title="Empresas"
        description="Quem se cadastrou no EmitirAgora, e quem voltou depois de entrar." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.card>
            <p class="etiqueta text-graphite-500">Empresas cadastradas</p>
            <p class="num mt-1 text-4xl font-bold tracking-tight text-graphite-900">{{ $totais['total'] }}</p>
            <p class="mt-1 text-xs text-graphite-500">Desde o começo.</p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Novas neste mês</p>
            <p class="num mt-1 text-4xl font-bold tracking-tight text-graphite-900">{{ $totais['novasNoMes'] }}</p>
            <p class="mt-1 text-xs text-graphite-500">Cadastradas em {{ today()->translatedFormat('F') }}.</p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Ativas em 30 dias</p>
            <p class="num mt-1 text-4xl font-bold tracking-tight text-graphite-900">{{ $totais['ativasEm30Dias'] }}</p>
            <p class="mt-1 text-xs text-graphite-500">Alguém da empresa entrou no último mês.</p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Por plano</p>
            <dl class="mt-2 grid gap-1">
                @foreach (\App\Enums\PlanoTenant::cases() as $plano)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sm text-graphite-700">{{ $plano->rotulo() }}</dt>
                        <dd class="num text-xl font-bold text-graphite-900">{{ $totais['porPlano'][$plano->value] ?? 0 }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>

    <x-ui.card title="Todas as empresas" subtitle="Mais recente primeiro" :padded="false">
        @if ($this->empresas->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="Nenhuma empresa cadastrada" description="Quando alguém criar uma conta no EmitirAgora, ela aparece aqui." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Empresa</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">CNPJ</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Contato</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Plano</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Cadastrada em</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Último acesso</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->empresas as $tenant)
                        @php
                            $emitente = $tenant->emitentes->first();
                            $contato = $tenant->users->first();
                            // O último acesso da empresa é o mais recente entre as pessoas dela.
                            $ultimoAcesso = $tenant->users->max('ultimo_acesso_em');
                        @endphp
                        <tr class="border-b border-graphite-100 last:border-0" wire:key="empresa-{{ $tenant->id }}">
                            <td class="px-5 py-3">
                                <p class="font-medium text-graphite-900">{{ $tenant->nome }}</p>
                                @if ($tenant->dominio)
                                    <p class="text-xs text-graphite-500">{{ $tenant->dominio }}</p>
                                @else
                                    <p class="text-xs text-graphite-500">{{ $tenant->slug }}</p>
                                @endif
                            </td>
                            <td class="num px-5 py-3 text-graphite-700">{{ $formatarCnpj($emitente?->cnpj) ?: 'sem emitente' }}</td>
                            <td class="px-5 py-3">
                                @if ($contato)
                                    <p class="text-graphite-900">{{ $contato->name }}</p>
                                    <p class="text-xs text-graphite-500">{{ $contato->email }}</p>
                                    @if ($emitente?->telefone)
                                        <p class="num text-xs text-graphite-500">{{ $emitente->telefone }}</p>
                                    @endif
                                @else
                                    <span class="text-graphite-500">sem usuário</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-graphite-700">{{ $tenant->plano->rotulo() }}</td>
                            <td class="num px-5 py-3 text-graphite-700">{{ $tenant->created_at?->format('d/m/Y') }}</td>
                            <td class="num px-5 py-3 text-graphite-700">
                                @if ($ultimoAcesso)
                                    {{ $ultimoAcesso->format('d/m/Y H:i') }}
                                @else
                                    <span class="text-graphite-500">nunca entrou</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            @if ($this->empresas->hasPages())
                <div class="px-5 py-4">{{ $this->empresas->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
