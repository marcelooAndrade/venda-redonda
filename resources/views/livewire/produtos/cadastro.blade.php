<div class="mx-auto grid w-full max-w-5xl gap-6 px-4 py-6">

    <x-ui.page-header
        eyebrow="Cadastros"
        :title="$editandoId ? 'Editar produto' : 'Produtos'"
        description="O NCM é conferido contra a tabela oficial do Siscomex. Só item de oito dígitos vale na nota.">
        <x-slot:actions>
            @if ($editandoId)
                <x-ui.button variant="ghost" wire:click="limpar">Cancelar edição</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @can('produto.gerenciar')
    <x-ui.card :title="$editandoId ? 'Editando' : 'Novo produto'">
        <form wire:submit="salvar" class="grid gap-5">

            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.field label="Código interno" for="pr-cod" required :error="$errors->first('codigo')">
                    <x-ui.input id="pr-cod" wire:model="form.codigo" />
                </x-ui.field>
                <x-ui.field label="Descrição" for="pr-desc" required class="sm:col-span-3" :error="$errors->first('descricao')">
                    <x-ui.input id="pr-desc" wire:model="form.descricao" />
                </x-ui.field>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.field label="NCM" for="pr-ncm" required :error="$errors->first('ncm')"
                    :hint="$ncmDescricao">
                    <x-ui.input id="pr-ncm" wire:model.live.debounce.500ms="form.ncm" numeric maxlength="8" />
                </x-ui.field>
                <x-ui.field label="CEST" for="pr-cest" :error="$errors->first('cest')">
                    <x-ui.input id="pr-cest" wire:model="form.cest" numeric maxlength="7" />
                </x-ui.field>
                <x-ui.field label="GTIN" for="pr-gtin" hint="Vazio vira SEM GTIN." :error="$errors->first('gtin')">
                    <x-ui.input id="pr-gtin" wire:model="form.gtin" numeric />
                </x-ui.field>
                <x-ui.field label="Origem" for="pr-orig" required>
                    <x-ui.select id="pr-orig" wire:model="form.origem">
                        @foreach ([
                            '0' => 'Nacional',
                            '1' => 'Estrangeira, importação direta',
                            '2' => 'Estrangeira, mercado interno',
                            '3' => 'Nacional, mais de 40% de conteúdo importado',
                            '4' => 'Nacional, processos produtivos básicos',
                            '5' => 'Nacional, até 40% de conteúdo importado',
                            '6' => 'Estrangeira, importação direta sem similar',
                            '7' => 'Estrangeira, mercado interno sem similar',
                            '8' => 'Nacional, mais de 70% de conteúdo importado',
                        ] as $cod => $rot)
                            <option value="{{ $cod }}">{{ $cod }} - {{ $rot }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.field label="Unidade comercial" for="pr-un" required :error="$errors->first('unidade_comercial')">
                    <x-ui.select id="pr-un" wire:model.live="form.unidade_comercial">
                        @foreach ($this->unidades as $u)
                            <option value="{{ $u->sigla }}">{{ $u->sigla }} - {{ $u->descricao }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Unidade tributável" for="pr-unt" required>
                    <x-ui.select id="pr-unt" wire:model.live="form.unidade_tributavel">
                        @foreach ($this->unidades as $u)
                            <option value="{{ $u->sigla }}">{{ $u->sigla }} - {{ $u->descricao }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Fator de conversão" for="pr-fat" required
                    :hint="$form['unidade_comercial'] !== $form['unidade_tributavel'] ? 'Quantas '.$form['unidade_tributavel'].' cabem em uma '.$form['unidade_comercial'].'.' : null"
                    :error="$errors->first('fator_conversao')">
                    <x-ui.input id="pr-fat" wire:model="form.fator_conversao" numeric />
                </x-ui.field>
                <x-ui.field label="Perfil fiscal" for="pr-pf" hint="Escrito pelo contador.">
                    <x-ui.select id="pr-pf" wire:model="form.perfil_fiscal_id">
                        <option value="">Sem perfil</option>
                        @foreach ($this->perfis as $p)
                            <option value="{{ $p->id }}">{{ $p->nome }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>

            <div class="grid gap-4 sm:grid-cols-5">
                <x-ui.field label="Preço de venda" for="pr-pv" :error="$errors->first('preco_venda')">
                    <x-ui.input id="pr-pv" wire:model="form.preco_venda" numeric />
                </x-ui.field>
                <x-ui.field label="Custo" for="pr-cst"><x-ui.input id="pr-cst" wire:model="form.custo" numeric /></x-ui.field>
                <x-ui.field label="Peso líquido (kg)" for="pr-pl"><x-ui.input id="pr-pl" wire:model="form.peso_liquido" numeric /></x-ui.field>
                <x-ui.field label="Peso bruto (kg)" for="pr-pb" hint="Confere no posto fiscal."><x-ui.input id="pr-pb" wire:model="form.peso_bruto" numeric /></x-ui.field>
                <x-ui.field label="Estoque mínimo" for="pr-em"><x-ui.input id="pr-em" wire:model="form.estoque_minimo" numeric /></x-ui.field>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="form.controla_estoque" class="size-4 accent-graphite-900">
                Controla estoque
            </label>

            <div><x-ui.button type="submit" size="lg">{{ $editandoId ? 'Salvar alterações' : 'Cadastrar produto' }}</x-ui.button></div>
        </form>
    </x-ui.card>
    @endcan

    <x-ui.card title="Cadastrados">
        <x-slot:actions>
            <x-ui.input wire:model.live.debounce.400ms="busca" placeholder="Buscar descrição, código, NCM ou GTIN" class="w-72" />
        </x-slot:actions>

        @if ($this->produtos->isEmpty())
            <x-ui.empty-state title="Nenhum produto" description="Cadastre o primeiro produto para poder emitir notas." />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="overline px-2 py-2 text-left text-graphite-500">Código</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Descrição</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">NCM</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Un.</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Perfil fiscal</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Preço</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->produtos as $produto)
                        <tr class="border-b border-graphite-100" wire:key="prod-{{ $produto->id }}">
                            <td class="num px-2 py-2">{{ $produto->codigo }}</td>
                            <td class="px-2 py-2">{{ $produto->descricao }}</td>
                            <td class="num px-2 py-2">{{ $produto->ncm }}</td>
                            <td class="px-2 py-2">
                                {{ $produto->unidade_comercial }}
                                @if ($produto->unidade_comercial !== $produto->unidade_tributavel)
                                    <span class="num text-xs text-graphite-500">→ {{ $produto->unidade_tributavel }} ×{{ rtrim(rtrim($produto->fator_conversao, '0'), '.') }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if ($produto->perfilFiscal)
                                    {{ $produto->perfilFiscal->nome }}
                                @else
                                    <span class="overline bg-ember-100 px-1.5 py-0.5 text-ember-800">Sem perfil</span>
                                @endif
                            </td>
                            <td class="num px-2 py-2 text-right">{{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</td>
                            <td class="px-2 py-2 text-right">
                                <x-ui.button variant="ghost" size="sm" wire:click="editar({{ $produto->id }})">Editar</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $this->produtos->links() }}</div>
        @endif
    </x-ui.card>
</div>
