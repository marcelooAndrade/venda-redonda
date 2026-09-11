<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Contas a pagar"
        description="O que sai, e quando." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @error('baixa')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    {{-- Os dois números pelos quais a pessoa abre esta tela. O vencido vem em
         vermelho porque é o que exige ação hoje. --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.card>
            <p class="etiqueta text-graphite-500">Em aberto</p>
            <p class="num mt-1 text-2xl font-bold text-graphite-900">
                {{ App\Support\Dinheiro::formatar($this->totalPendenteCentavos) }}
            </p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Vencido</p>
            <p @class([
                'num mt-1 text-2xl font-bold',
                'text-danger-700' => $this->totalVencidoCentavos > 0,
                'text-graphite-900' => $this->totalVencidoCentavos === 0,
            ])>
                {{ App\Support\Dinheiro::formatar($this->totalVencidoCentavos) }}
            </p>
        </x-ui.card>
    </div>

    @can('financeiro.gerenciar')
        <x-ui.card title="Lançar título" subtitle="O valor aceita R$, ponto de milhar e vírgula de centavo.">
            <form wire:submit="lancar" class="grid gap-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field label="Descrição" for="cp-desc" required :error="$errors->first('descricao')">
                        <x-ui.input id="cp-desc" wire:model="descricao" maxlength="160" />
                    </x-ui.field>

                    <x-ui.field label="Fornecedor" for="cp-forn" :error="$errors->first('fornecedor')">
                        <x-ui.input id="cp-forn" wire:model="fornecedor" maxlength="160" />
                    </x-ui.field>

                    <x-ui.field label="Valor" for="cp-valor" required :error="$errors->first('valor')">
                        <x-ui.input id="cp-valor" wire:model="valor" placeholder="R$ 0,00" />
                    </x-ui.field>

                    <x-ui.field label="Vencimento" for="cp-venc" required :error="$errors->first('vencimento')">
                        <x-ui.input id="cp-venc" type="date" wire:model="vencimento" />
                    </x-ui.field>
                </div>

                @if ($this->centros->isNotEmpty())
                    <x-ui.field label="Centro de custo" for="cp-centro" class="max-w-md">
                        <x-ui.select id="cp-centro" wire:model="centroCustoId">
                            <option value="">Sem classificação</option>
                            @foreach ($this->centros as $centro)
                                <option value="{{ $centro->id }}">{{ $centro->codigo }} · {{ $centro->nome }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                @endif

                <div><x-ui.button type="submit">Lançar</x-ui.button></div>
            </form>
        </x-ui.card>
    @endcan

    <x-ui.card>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div class="flex gap-1">
                @foreach (['pendentes' => 'Em aberto', 'pagos' => 'Pagos', 'todos' => 'Todos'] as $chave => $rotulo)
                    <button type="button" wire:click="$set('situacao', '{{ $chave }}')"
                        @class([
                            'border-b-2 px-3 py-2 text-[13px] transition-colors',
                            'border-primary-600 font-semibold text-graphite-900' => $situacao === $chave,
                            'border-transparent text-graphite-500 hover:text-graphite-900' => $situacao !== $chave,
                        ])>{{ $rotulo }}</button>
                @endforeach
            </div>

            @can('financeiro.gerenciar')
                @if ($this->contas->isNotEmpty())
                    <x-ui.field label="Baixar na conta" for="cp-conta" class="w-full max-w-xs"
                        hint="Sem conta, o título fecha e o saldo não muda.">
                        <x-ui.select id="cp-conta" wire:model="contaBaixaId">
                            <option value="">Sem conta bancária</option>
                            @foreach ($this->contas as $conta)
                                <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                @endif
            @endcan
        </div>

        @if ($this->titulos->isEmpty())
            <x-ui.empty-state
                title="Nenhum título aqui"
                description="Quando houver conta a pagar, ela aparece nesta lista, da mais próxima de vencer para a mais distante." />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Vencimento</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Descrição</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Fornecedor</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Centro de custo</th>
                        <th class="etiqueta px-2 py-2 text-right text-graphite-500">Valor</th>
                        <th class="etiqueta px-2 py-2 text-right text-graphite-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->titulos as $titulo)
                        <tr class="border-b border-graphite-100">
                            <td class="num px-2 py-2 whitespace-nowrap {{ $titulo->estaVencido() ? 'font-semibold text-danger-700' : 'text-graphite-700' }}">
                                {{ $titulo->vencimento->format('d/m/Y') }}
                            </td>
                            <td class="px-2 py-2 text-graphite-900">{{ $titulo->descricao }}</td>
                            <td class="px-2 py-2 text-graphite-600">{{ $titulo->fornecedor ?: '—' }}</td>
                            <td class="px-2 py-2 text-graphite-600">
                                {{ $titulo->centroCusto?->nome ?: '—' }}
                            </td>
                            <td class="num px-2 py-2 text-right font-semibold text-graphite-900">
                                {{ App\Support\Dinheiro::formatar($titulo->valor_centavos) }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                @if ($titulo->status === 'pago')
                                    <span class="etiqueta bg-success-100 px-2 py-1 text-success-800">Pago</span>
                                @elseif ($titulo->estaVencido())
                                    @can('financeiro.gerenciar')
                                        <x-ui.button size="sm" variant="destructive"
                                            wire:click="baixar({{ $titulo->id }})"
                                            wire:confirm="Baixar este título?">Pagar</x-ui.button>
                                    @else
                                        <span class="etiqueta bg-danger-100 px-2 py-1 text-danger-800">Vencido</span>
                                    @endcan
                                @else
                                    @can('financeiro.gerenciar')
                                        <x-ui.button size="sm" variant="secondary"
                                            wire:click="baixar({{ $titulo->id }})"
                                            wire:confirm="Baixar este título?">Pagar</x-ui.button>
                                    @else
                                        <span class="etiqueta bg-graphite-100 px-2 py-1 text-graphite-700">Em aberto</span>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
