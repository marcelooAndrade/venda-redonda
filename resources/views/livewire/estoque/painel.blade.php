<div class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-6">

    <x-ui.page-header
        eyebrow="Operação"
        title="Estoque"
        description="Razão imutável: nenhum movimento é editado ou apagado. Correção é sempre movimento novo." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($this->abaixoDoMinimo->isNotEmpty())
        <x-ui.alert variant="warning" title="{{ $this->abaixoDoMinimo->count() }} produto(s) abaixo do estoque mínimo">
            {{ $this->abaixoDoMinimo->take(4)->map(fn ($s) => $s->produto->descricao)->implode(' · ') }}
            @if ($this->abaixoDoMinimo->count() > 4)
                e outros {{ $this->abaixoDoMinimo->count() - 4 }}.
            @endif
        </x-ui.alert>
    @endif

    {{-- Abas --}}
    <div class="flex flex-wrap gap-px border-b border-graphite-200">
        @foreach (['saldos' => 'Saldos', 'kardex' => 'Kardex', 'inventario' => 'Inventário'] as $chave => $rotulo)
            @if ($chave !== 'inventario' || auth()->user()->can('estoque.inventariar'))
                <button type="button"
                    wire:click="{{ $chave === 'inventario' ? 'prepararInventario' : '$set(\'aba\', \''.$chave.'\')' }}"
                    @class([
                        'border-b-2 px-4 py-2 text-sm transition-colors',
                        'border-primary-600 font-semibold text-graphite-900' => $aba === $chave,
                        'border-transparent text-graphite-500 hover:text-graphite-900' => $aba !== $chave,
                    ])>{{ $rotulo }}</button>
            @endif
        @endforeach
    </div>

    {{-- Saldos --}}
    @if ($aba === 'saldos')
        <x-ui.card title="Posição de estoque" subtitle="Valorizada pelo custo médio ponderado.">
            <x-slot:actions>
                <label class="flex items-center gap-2 text-xs text-graphite-600">
                    <input type="checkbox" wire:model.live="somenteAbaixoDoMinimo" class="size-4 accent-graphite-900">
                    Só abaixo do mínimo
                </label>
                <x-ui.input wire:model.live.debounce.400ms="busca" placeholder="Buscar produto" class="w-56" />
            </x-slot:actions>

            @if ($this->saldos->isEmpty())
                <x-ui.empty-state title="Sem posição de estoque" description="Nenhum produto com controle de estoque movimentado ainda." />
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200">
                            <th class="overline px-2 py-2 text-left text-graphite-500">Código</th>
                            <th class="overline px-2 py-2 text-left text-graphite-500">Produto</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Saldo</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Mínimo</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Custo médio</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Valor</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->saldos as $saldo)
                            <tr class="border-b border-graphite-100" wire:key="saldo-{{ $saldo->id }}">
                                <td class="num px-2 py-2">{{ $saldo->produto->codigo }}</td>
                                <td class="px-2 py-2">{{ $saldo->produto->descricao }}</td>
                                <td class="num px-2 py-2 text-right {{ $saldo->abaixoDoMinimo() ? 'font-semibold text-ember-700' : '' }}">
                                    {{ number_format($saldo->quantidade, 2, ',', '.') }}
                                    <span class="text-xs text-graphite-500">{{ $saldo->produto->unidade_comercial }}</span>
                                </td>
                                <td class="num px-2 py-2 text-right text-graphite-500">{{ number_format((float) $saldo->produto->estoque_minimo, 2, ',', '.') }}</td>
                                <td class="num px-2 py-2 text-right">{{ number_format($saldo->custo_medio, 4, ',', '.') }}</td>
                                <td class="num px-2 py-2 text-right">{{ number_format($saldo->valorTotal(), 2, ',', '.') }}</td>
                                <td class="px-2 py-2 text-right">
                                    <x-ui.button variant="ghost" size="sm" wire:click="verKardex({{ $saldo->produto_id }})">Kardex</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-graphite-900">
                            <td colspan="5" class="px-2 py-2 text-right font-semibold">Valor total em estoque</td>
                            <td class="num px-2 py-2 text-right font-semibold">{{ number_format($this->valorTotalEstoque, 2, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif

    {{-- Kardex --}}
    @if ($aba === 'kardex')
        <x-ui.card :title="$this->produto ? 'Kardex: '.$this->produto->descricao : 'Kardex'"
            subtitle="Cada linha é um fato registrado. Nada aqui foi editado.">
            <x-slot:actions>
                <x-ui.input type="date" wire:model.live="de" class="w-40" />
                <x-ui.input type="date" wire:model.live="ate" class="w-40" />
            </x-slot:actions>

            @if (! $this->produto)
                <x-ui.empty-state title="Escolha um produto" description="Volte para Saldos e clique em Kardex na linha do produto." />
            @elseif ($this->kardex->isEmpty())
                <x-ui.empty-state title="Nenhum movimento no período" />
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200">
                            <th class="overline px-2 py-2 text-left text-graphite-500">Data</th>
                            <th class="overline px-2 py-2 text-left text-graphite-500">Tipo</th>
                            <th class="overline px-2 py-2 text-left text-graphite-500">Documento</th>
                            <th class="overline px-2 py-2 text-left text-graphite-500">Usuário</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Qtd.</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Custo un.</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->kardex as $mov)
                            <tr class="border-b border-graphite-100" wire:key="mov-{{ $mov->id }}">
                                <td class="num px-2 py-2">{{ $mov->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-2 py-2">
                                    <span class="overline px-2 py-1 {{ $mov->tipo->classesBadge() }}">{{ $mov->tipo->rotulo() }}</span>
                                </td>
                                <td class="px-2 py-2">{{ $mov->documento ?? '-' }}</td>
                                <td class="px-2 py-2">{{ $mov->user?->name ?? '-' }}</td>
                                <td class="num px-2 py-2 text-right {{ $mov->tipo->positivo() ? 'text-success-700' : 'text-danger-700' }}">
                                    {{ $mov->tipo->positivo() ? '+' : '−' }}{{ number_format((float) $mov->quantidade, 2, ',', '.') }}
                                </td>
                                <td class="num px-2 py-2 text-right">{{ $mov->custo_unitario !== null ? number_format((float) $mov->custo_unitario, 4, ',', '.') : '-' }}</td>
                                <td class="num px-2 py-2 text-right font-semibold">{{ number_format((float) $mov->saldo_apos, 2, ',', '.') }}</td>
                            </tr>
                            @if ($mov->justificativa)
                                <tr class="border-b border-graphite-100">
                                    <td colspan="7" class="px-2 pb-2 text-xs text-graphite-500">{{ $mov->justificativa }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif

    {{-- Inventário --}}
    @if ($aba === 'inventario')
        <x-ui.card title="Contagem de inventário" subtitle="Informe o contado. O sistema movimenta apenas a diferença.">
            <form wire:submit="gravarInventario" class="grid gap-4">
                <x-ui.field label="Justificativa" for="inv-just" required
                    hint="Fica registrada em cada movimento gerado."
                    :error="$errors->first('justificativa')">
                    <x-ui.textarea id="inv-just" rows="2" wire:model="justificativa"
                        placeholder="Contagem anual, quebra identificada, sobra encontrada..." />
                </x-ui.field>

                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200">
                            <th class="overline px-2 py-2 text-left text-graphite-500">Produto</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Sistema</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Contado</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Diferença</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->saldos as $saldo)
                            @php
                                $contado = (float) str_replace(',', '.', (string) ($contagem[$saldo->produto_id] ?? $saldo->quantidade));
                                $dif = round($contado - $saldo->quantidade, 4);
                            @endphp
                            <tr class="border-b border-graphite-100" wire:key="inv-{{ $saldo->produto_id }}">
                                <td class="px-2 py-2">{{ $saldo->produto->descricao }}</td>
                                <td class="num px-2 py-2 text-right text-graphite-500">{{ number_format($saldo->quantidade, 2, ',', '.') }}</td>
                                <td class="px-2 py-2 text-right">
                                    <x-ui.input wire:model.live.debounce.500ms="contagem.{{ $saldo->produto_id }}" numeric class="ml-auto w-28" />
                                </td>
                                <td class="num px-2 py-2 text-right {{ $dif > 0 ? 'text-success-700' : ($dif < 0 ? 'text-danger-700' : 'text-graphite-400') }}">
                                    {{ $dif > 0 ? '+' : '' }}{{ number_format($dif, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                <div><x-ui.button type="submit" size="lg">Gravar inventário</x-ui.button></div>
            </form>
        </x-ui.card>
    @endif
</div>
