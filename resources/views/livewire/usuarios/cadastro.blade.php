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
                        <th class="etiqueta px-2 py-2 text-right text-graphite-500">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->usuarios as $usuario)
                        <tr class="border-b border-graphite-100" wire:key="usuario-{{ $usuario->id }}">
                            <td class="px-2 py-2">{{ $usuario->name }}</td>
                            <td class="px-2 py-2">{{ $usuario->email }}</td>
                            <td class="px-2 py-2">{{ $usuario->ativo ? 'Ativo' : 'Inativo' }}</td>
                            <td class="px-2 py-2 text-right">
                                <x-ui.button variant="ghost" size="sm" wire:click="editar({{ $usuario->id }})">Editar</x-ui.button>
                                @if ($usuario->id !== auth()->id())
                                    @if ($usuario->ativo)
                                        <x-ui.button variant="ghost" size="sm" wire:click="inativar({{ $usuario->id }})">Inativar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm" wire:click="reativar({{ $usuario->id }})">Reativar</x-ui.button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Novo usuário">
        <div class="grid gap-4">
            <x-ui.field label="E-mail" for="u-email" required :error="$errors->first('form.email')">
                <div class="flex gap-2">
                    <x-ui.input id="u-email" type="email" wire:model="form.email" maxlength="254" class="flex-1" />
                    <x-ui.button variant="secondary" size="md" wire:click="verificarEmail" wire:loading.attr="disabled" wire:target="verificarEmail">
                        <span wire:loading.remove wire:target="verificarEmail">Verificar</span>
                        <span wire:loading wire:target="verificarEmail">...</span>
                    </x-ui.button>
                </div>
            </x-ui.field>

            @if ($emailVerificado)
                @if ($contaExistente)
                    <x-ui.alert variant="info">
                        Já existe uma conta com este e-mail, de {{ $form['name'] }}. Vamos apenas dar acesso a esta empresa.
                    </x-ui.alert>
                @else
                    <x-ui.field label="Nome" for="u-nome" required :error="$errors->first('form.name')">
                        <x-ui.input id="u-nome" wire:model="form.name" maxlength="160" />
                    </x-ui.field>
                    <x-ui.field label="Senha inicial" for="u-senha" required :error="$errors->first('form.senha')" hint="A pessoa troca depois, em Configurações da conta.">
                        <x-ui.input id="u-senha" type="password" wire:model="form.senha" minlength="8" />
                    </x-ui.field>
                @endif

                <div class="grid gap-2">
                    <p class="etiqueta text-graphite-500">Perfil por emitente</p>
                    @error('papeis') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    @foreach ($this->emitentesDaEmpresa as $emitenteDaEmpresa)
                        <x-ui.field :label="$emitenteDaEmpresa->nome_fantasia ?: $emitenteDaEmpresa->razao_social" :for="'u-papel-'.$emitenteDaEmpresa->id">
                            <x-ui.select :id="'u-papel-'.$emitenteDaEmpresa->id" wire:model="papeis.{{ $emitenteDaEmpresa->id }}">
                                <option value="">Sem acesso</option>
                                @foreach (\App\Enums\Perfil::cases() as $perfil)
                                    <option value="{{ $perfil->value }}">{{ $perfil->value }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <x-ui.button variant="primary" wire:click="salvar">Salvar</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="novoUsuario">Cancelar</x-ui.button>
                </div>
            @endif
        </div>
    </x-ui.card>

</div>
