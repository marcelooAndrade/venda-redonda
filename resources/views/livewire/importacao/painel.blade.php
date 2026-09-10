<div class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-6">

    <x-ui.page-header
        eyebrow="Operação"
        title="Importação de notas"
        description="Só nota autorizada entra. O XML original fica guardado e o custo de entrada já vem rateado com frete e IPI." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @error('confirmacao')
        <x-ui.alert variant="danger" title="Não foi possível confirmar">{{ $message }}</x-ui.alert>
    @enderror

    @if ($falhas)
        <x-ui.card title="Arquivos com problema" subtitle="Os demais foram importados normalmente.">
            <div class="grid gap-2">
                @foreach ($falhas as $falha)
                    <x-ui.alert variant="warning" :title="$falha['arquivo']">{{ $falha['erro'] }}</x-ui.alert>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    @can('importacao.processar')
        <x-ui.card title="Enviar XML" subtitle="Arquivo avulso, vários de uma vez, ou um ZIP com o lote do mês.">
            <form wire:submit="importar" class="grid gap-4 sm:max-w-xl">
                <x-ui.field label="Arquivos .xml ou .zip" for="imp-arq" :error="$errors->first('arquivos').$errors->first('arquivos.*')">
                    <x-ui.input id="imp-arq" type="file" wire:model="arquivos" multiple accept=".xml,.zip" />
                </x-ui.field>
                <div>
                    <x-ui.button type="submit" size="lg">
                        <span wire:loading.remove wire:target="importar,arquivos">Importar</span>
                        <span wire:loading wire:target="importar,arquivos">Processando...</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan

    {{-- Conciliação --}}
    @if ($this->nota)
        <x-ui.card :title="'Nota '.$this->nota->numero.'/'.$this->nota->serie"
            :subtitle="($this->nota->pessoa?->razao_social ?? 'Nota própria').' · emitida em '.$this->nota->data_emissao->format('d/m/Y')">
            <x-slot:actions>
                <x-ui.button variant="ghost" wire:click="$set('notaId', null)">Fechar</x-ui.button>
                @if (! $this->nota->confirmada())
                    @can('estoque.movimentar')
                        <x-ui.button wire:click="confirmar">Confirmar entrada</x-ui.button>
                    @endcan
                @endif
            </x-slot:actions>

            @if ($this->nota->confirmada())
                <x-ui.alert variant="success" class="mb-4">
                    Entrada confirmada em {{ $this->nota->confirmada_em?->format('d/m/Y H:i') }}
                    por {{ $this->nota->confirmadaPor?->name ?? 'sistema' }}.
                </x-ui.alert>
            @elseif ($this->nota->tipo === 'propria')
                <x-ui.alert variant="info" class="mb-4">
                    Nota própria, emitida em outro sistema. Entra como histórico e não movimenta estoque.
                </x-ui.alert>
            @endif

            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="overline px-2 py-2 text-left text-graphite-500">Item do fornecedor</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Qtd.</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Custo un.</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">CFOP</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Produto interno</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->nota->itens as $item)
                        <tr class="border-b border-graphite-100 align-top" wire:key="item-{{ $item->id }}">
                            <td class="px-2 py-2">
                                <span class="num text-xs text-graphite-500">{{ $item->codigo_fornecedor }}</span>
                                <span class="block">{{ $item->descricao }}</span>
                                <span class="num block text-xs text-graphite-500">
                                    NCM {{ $item->ncm }}@if ($item->gtin) · GTIN {{ $item->gtin }} @endif
                                </span>
                            </td>
                            <td class="num px-2 py-2 text-right">
                                {{ number_format((float) $item->quantidade, 2, ',', '.') }}
                                <span class="text-xs text-graphite-500">{{ $item->unidade }}</span>
                            </td>
                            <td class="num px-2 py-2 text-right">{{ number_format((float) $item->custo_unitario, 4, ',', '.') }}</td>
                            <td class="num px-2 py-2">
                                {{ $item->cfop_origem }}
                                <span class="block text-xs text-graphite-500">→ {{ $item->cfop_entrada ?? '?' }}</span>
                            </td>
                            <td class="px-2 py-2">
                                @if ($this->nota->confirmada())
                                    {{ $item->produto?->descricao ?? '-' }}
                                @else
                                    <div class="grid gap-2 sm:grid-cols-[1fr_5rem_auto]">
                                        <x-ui.select wire:model="vinculos.{{ $item->id }}">
                                            <option value="">Escolher produto...</option>
                                            @foreach ($this->produtos as $p)
                                                <option value="{{ $p->id }}">{{ $p->codigo }} · {{ $p->descricao }}</option>
                                            @endforeach
                                        </x-ui.select>
                                        <x-ui.input wire:model="fatores.{{ $item->id }}" numeric title="Fator de conversão" />
                                        <div class="flex gap-1">
                                            <x-ui.button size="sm" wire:click="vincular({{ $item->id }})">Vincular</x-ui.button>
                                            @can('produto.gerenciar')
                                                <x-ui.button size="sm" variant="secondary" wire:click="criarProduto({{ $item->id }})"
                                                    title="Cria o produto já preenchido com os dados do XML">Criar</x-ui.button>
                                            @endcan
                                        </div>
                                    </div>
                                    @if ($item->produto)
                                        <span class="overline mt-1 inline-block bg-success-100 px-1.5 py-0.5 text-success-800">
                                            Vinculado a {{ $item->produto->codigo }}
                                        </span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif

    {{-- Listagem --}}
    <x-ui.card title="Notas importadas">
        <x-slot:actions>
            <x-ui.select wire:model.live="filtro" class="w-auto">
                <option value="pendente">Pendentes de conciliação</option>
                <option value="conciliada">Conciliadas</option>
                <option value="confirmada">Confirmadas</option>
                <option value="todas">Todas</option>
            </x-ui.select>
        </x-slot:actions>

        @if ($this->notas->isEmpty())
            <x-ui.empty-state title="Nenhuma nota neste filtro" description="Envie um XML acima para começar." />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="overline px-2 py-2 text-left text-graphite-500">Nota</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Fornecedor</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Emissão</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Itens</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Valor</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Situação</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->notas as $n)
                        <tr class="border-b border-graphite-100" wire:key="nota-{{ $n->id }}">
                            <td class="num px-2 py-2">{{ $n->numero }}/{{ $n->serie }}</td>
                            <td class="px-2 py-2">{{ $n->pessoa?->razao_social ?? 'Nota própria' }}</td>
                            <td class="num px-2 py-2">{{ $n->data_emissao->format('d/m/Y') }}</td>
                            <td class="num px-2 py-2 text-right">{{ $n->itens_count }}</td>
                            <td class="num px-2 py-2 text-right">{{ number_format((float) $n->valor_nota, 2, ',', '.') }}</td>
                            <td class="px-2 py-2">
                                @php
                                    $classes = match ($n->status) {
                                        'confirmada' => 'bg-success-100 text-success-800',
                                        'conciliada' => 'bg-steel-100 text-steel-800',
                                        default => 'bg-ember-100 text-ember-800',
                                    };
                                @endphp
                                <span class="overline px-2 py-1 {{ $classes }}">{{ ucfirst($n->status) }}</span>
                            </td>
                            <td class="px-2 py-2 text-right">
                                <x-ui.button variant="ghost" size="sm" wire:click="conciliar({{ $n->id }})">Abrir</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
