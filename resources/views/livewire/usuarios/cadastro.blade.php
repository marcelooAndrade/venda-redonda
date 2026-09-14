<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Configuração"
        title="Usuários"
        description="Quem tem acesso a esta empresa, e com qual perfil em cada emitente dela." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <x-ui.card title="Usuários desta empresa">
        @if ($this->usuarios->isEmpty())
            <x-ui.empty-state title="Nenhum usuário ainda" description="Cadastre o primeiro logo abaixo." />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Nome</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">E-mail</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->usuarios as $usuario)
                        <tr class="border-b border-graphite-100" wire:key="usuario-{{ $usuario->id }}">
                            <td class="px-2 py-2">{{ $usuario->name }}</td>
                            <td class="px-2 py-2">{{ $usuario->email }}</td>
                            <td class="px-2 py-2">{{ $usuario->ativo ? 'Ativo' : 'Inativo' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

</div>
