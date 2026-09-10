<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Cadastros"
        :title="$editandoId ? 'Editar cadastro' : 'Destinatários e parceiros'"
        description="Cliente, fornecedor e transportadora ficam no mesmo cadastro. A mesma empresa pode ter mais de um papel.">
        <x-slot:actions>
            @if ($editandoId)
                <x-ui.button variant="ghost" wire:click="limparFormulario">Cancelar edição</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($avisoSituacao)
        <x-ui.alert variant="warning" title="Situação cadastral: {{ $avisoSituacao }}">
            A Receita informa que esta empresa não está ativa. O cadastro é permitido, mas confira antes de emitir.
        </x-ui.alert>
    @endif

    @can('pessoa.gerenciar')
    <x-ui.card :title="$editandoId ? 'Editando' : 'Novo cadastro'">
        <form wire:submit="salvar" class="grid gap-5">

            {{-- Identificação --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field label="Tipo" for="p-tipo">
                    <x-ui.select id="p-tipo" wire:model.live="form.tipo_pessoa">
                        @foreach (\App\Enums\Fiscal\TipoPessoa::cases() as $tipo)
                            <option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field
                    :label="$form['tipo_pessoa'] === 'F' ? 'CPF' : 'CNPJ'"
                    for="p-doc" required
                    :hint="$form['tipo_pessoa'] === 'J' ? 'Aceita o formato alfanumérico da Receita.' : null"
                    :error="$errors->first('documento')">
                    <div class="flex gap-2">
                        <x-ui.input id="p-doc" wire:model="form.documento" numeric class="flex-1" />
                        @if ($form['tipo_pessoa'] === 'J')
                            <x-ui.button variant="secondary" size="md" wire:click="buscarCnpj" wire:loading.attr="disabled" wire:target="buscarCnpj">
                                <span wire:loading.remove wire:target="buscarCnpj">Buscar</span>
                                <span wire:loading wire:target="buscarCnpj">...</span>
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.field>

                <x-ui.field label="Nome fantasia" for="p-fant">
                    <x-ui.input id="p-fant" wire:model="form.nome_fantasia" />
                </x-ui.field>
            </div>

            <x-ui.field
                :label="$form['tipo_pessoa'] === 'F' ? 'Nome' : 'Razão social'"
                for="p-razao" required :error="$errors->first('razao_social')">
                <x-ui.input id="p-razao" wire:model="form.razao_social" />
            </x-ui.field>

            {{-- Situação fiscal --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field label="Indicador de IE" for="p-ind" required>
                    <x-ui.select id="p-ind" wire:model.live="form.ind_ie_dest">
                        @foreach (\App\Enums\Fiscal\IndIEDest::cases() as $ind)
                            <option value="{{ $ind->value }}">{{ $ind->value }} - {{ $ind->rotulo() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field
                    label="Inscrição Estadual" for="p-ie"
                    :required="$form['ind_ie_dest'] === '1'"
                    :hint="$form['ind_ie_dest'] !== '1' ? 'Deve ficar vazia neste indicador.' : null"
                    :error="$errors->first('inscricao_estadual')">
                    <x-ui.input id="p-ie" wire:model="form.inscricao_estadual" numeric
                        :disabled="$form['ind_ie_dest'] !== '1'" />
                </x-ui.field>

                <x-ui.field label="SUFRAMA" for="p-suf">
                    <x-ui.input id="p-suf" wire:model="form.suframa" numeric />
                </x-ui.field>
            </div>

            {{-- Endereço --}}
            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.field label="CEP" for="p-cep" required :error="$errors->first('cep')">
                    <div class="flex gap-2">
                        <x-ui.input id="p-cep" wire:model="form.cep" numeric class="flex-1" />
                        <x-ui.button variant="secondary" size="md" wire:click="buscarCep" wire:loading.attr="disabled" wire:target="buscarCep">
                            <span wire:loading.remove wire:target="buscarCep">CEP</span>
                            <span wire:loading wire:target="buscarCep">...</span>
                        </x-ui.button>
                    </div>
                </x-ui.field>

                <x-ui.field label="Logradouro" for="p-log" required class="sm:col-span-2" :error="$errors->first('logradouro')">
                    <x-ui.input id="p-log" wire:model="form.logradouro" />
                </x-ui.field>

                <x-ui.field label="Número" for="p-num" required :error="$errors->first('numero')">
                    <x-ui.input id="p-num" wire:model="form.numero" />
                </x-ui.field>

                <x-ui.field label="Complemento" for="p-comp">
                    <x-ui.input id="p-comp" wire:model="form.complemento" />
                </x-ui.field>

                <x-ui.field label="Bairro" for="p-bai" required :error="$errors->first('bairro')">
                    <x-ui.input id="p-bai" wire:model="form.bairro" />
                </x-ui.field>

                <x-ui.field label="Município" for="p-mun" required :error="$errors->first('municipio')">
                    <x-ui.input id="p-mun" wire:model="form.municipio" />
                </x-ui.field>

                <x-ui.field label="UF" for="p-uf" required :error="$errors->first('uf')">
                    <x-ui.input id="p-uf" wire:model="form.uf" maxlength="2" />
                </x-ui.field>

                <x-ui.field label="Código IBGE" for="p-ibge" required
                    hint="Obrigatório na NF-e. Vem do CEP." :error="$errors->first('codigo_municipio')">
                    <x-ui.input id="p-ibge" wire:model="form.codigo_municipio" numeric readonly />
                </x-ui.field>

                <x-ui.field label="Telefone" for="p-tel">
                    <x-ui.input id="p-tel" wire:model="form.telefone" />
                </x-ui.field>

                <x-ui.field label="E-mail" for="p-mail" class="sm:col-span-2" :error="$errors->first('email')">
                    <x-ui.input id="p-mail" type="email" wire:model="form.email" />
                </x-ui.field>
            </div>

            {{-- Papéis --}}
            <div>
                <p class="etiqueta mb-2 text-graphite-500">Papéis</p>
                <div class="flex flex-wrap gap-5">
                    @foreach ([['e_cliente','Cliente'],['e_fornecedor','Fornecedor'],['e_transportadora','Transportadora']] as [$campo, $rotulo])
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="form.{{ $campo }}" class="size-4 accent-graphite-900">
                            {{ $rotulo }}
                        </label>
                    @endforeach
                </div>
                @error('e_cliente')<p class="mt-2 text-xs text-danger-700">{{ $message }}</p>@enderror
            </div>

            @if ($form['e_transportadora'])
                <div class="grid gap-4 border-l-2 border-graphite-200 pl-4 sm:grid-cols-3">
                    <x-ui.field label="Placa" for="p-placa"><x-ui.input id="p-placa" wire:model="form.placa" maxlength="7" /></x-ui.field>
                    <x-ui.field label="UF da placa" for="p-puf"><x-ui.input id="p-puf" wire:model="form.placa_uf" maxlength="2" /></x-ui.field>
                    <x-ui.field label="RNTC" for="p-rntc"><x-ui.input id="p-rntc" wire:model="form.rntc" /></x-ui.field>
                </div>
            @endif

            <div class="flex gap-2">
                <x-ui.button type="submit" size="lg">{{ $editandoId ? 'Salvar alterações' : 'Cadastrar' }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
    @endcan

    {{-- Listagem --}}
    <x-ui.card title="Cadastrados" :padded="false">
        <x-slot:actions>
            <x-ui.select wire:model.live="papel" class="w-auto">
                <option value="todos">Todos os papéis</option>
                <option value="clientes">Clientes</option>
                <option value="fornecedores">Fornecedores</option>
                <option value="transportadoras">Transportadoras</option>
            </x-ui.select>
            <x-ui.input wire:model.live.debounce.400ms="busca" placeholder="Buscar nome ou documento" class="w-56" />
        </x-slot:actions>

        @if ($this->pessoas->isEmpty())
            <x-ui.empty-state class="m-5" title="Nenhum cadastro" description="Use o formulário acima para incluir o primeiro." />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Nome</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Documento</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Município</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Papéis</th>
                        <th class="etiqueta px-2 py-2 text-right text-graphite-500">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pessoas as $pessoa)
                        <tr class="border-b border-graphite-100" wire:key="pessoa-{{ $pessoa->id }}">
                            <td class="px-2 py-2">
                                {{ $pessoa->razao_social }}
                                @if ($pessoa->nome_fantasia)
                                    <span class="block text-xs text-graphite-500">{{ $pessoa->nome_fantasia }}</span>
                                @endif
                            </td>
                            <td class="num px-2 py-2">{{ $pessoa->documentoFormatado() }}</td>
                            <td class="px-2 py-2">{{ $pessoa->municipio }}/{{ $pessoa->uf }}</td>
                            <td class="px-2 py-2">
                                <span class="flex flex-wrap gap-1">
                                    @if ($pessoa->e_cliente)<span class="etiqueta bg-graphite-100 px-1.5 py-0.5 text-graphite-700">Cliente</span>@endif
                                    @if ($pessoa->e_fornecedor)<span class="etiqueta bg-graphite-100 px-1.5 py-0.5 text-graphite-700">Fornecedor</span>@endif
                                    @if ($pessoa->e_transportadora)<span class="etiqueta bg-graphite-100 px-1.5 py-0.5 text-graphite-700">Transp.</span>@endif
                                </span>
                            </td>
                            <td class="px-2 py-2 text-right">
                                <x-ui.button variant="ghost" size="sm" wire:click="editar({{ $pessoa->id }})">Editar</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div class="mt-4">{{ $this->pessoas->links() }}</div>
        @endif
    </x-ui.card>
</div>
