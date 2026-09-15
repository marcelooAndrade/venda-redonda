<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Contas bancárias"
        description="Onde o dinheiro está de fato, o saldo de cada conta e o extrato de quem abrir uma." />

    @if (session('sucesso'))
        <div class="rounded-md border border-success-200 bg-success-50 px-4 py-2 text-sm text-success-700">{{ session('sucesso') }}</div>
    @endif

    <x-ui.card>
        <p class="etiqueta text-graphite-500">Saldo total</p>
        <p class="num mt-1 text-3xl font-bold tracking-tight text-graphite-900">
            {{ App\Support\Dinheiro::formatar($this->saldoTotalCentavos) }}
        </p>
        <p class="mt-1 text-xs text-graphite-500">Soma do saldo inicial e do razão de todas as contas ativas.</p>
    </x-ui.card>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <div class="grid gap-6">
            <x-ui.card title="Nova conta">
                <form wire:submit="criarConta" class="grid gap-3">
                    <x-ui.field label="Nome" required :error="$errors->first('nome')">
                        <x-ui.input wire:model="nome" placeholder="Conta corrente Banco X" />
                    </x-ui.field>

                    <x-ui.field label="Banco" :error="$errors->first('banco')">
                        <x-ui.input wire:model="banco" placeholder="Opcional" />
                    </x-ui.field>

                    <x-ui.field label="Tipo" required :error="$errors->first('tipo')">
                        <x-ui.select wire:model="tipo">
                            <option value="corrente">Corrente</option>
                            <option value="poupanca">Poupança</option>
                            <option value="caixa">Caixa</option>
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Saldo inicial" hint="Em reais, como você digita.">
                        <x-ui.input wire:model="saldoInicial" placeholder="0,00" numeric />
                    </x-ui.field>

                    <label class="flex items-center gap-2 text-sm text-graphite-700">
                        <input type="checkbox" wire:model="padrao" class="rounded border-graphite-300">
                        Conta padrão
                    </label>

                    <x-ui.button type="submit">Criar conta</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card title="Contas" :padded="false">
                @if ($this->contas->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state title="Nenhuma conta cadastrada" description="Crie a primeira conta ao lado." />
                    </div>
                @else
                    <ul class="divide-y divide-graphite-100">
                        @foreach ($this->contas as $conta)
                            <li wire:key="conta-{{ $conta->id }}"
                                wire:click="selecionar({{ $conta->id }})"
                                @class([
                                    'flex cursor-pointer items-center justify-between gap-3 px-5 py-3',
                                    'bg-primary-50' => $contaSelecionadaId === $conta->id,
                                ])>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-graphite-900">
                                        {{ $conta->nome }}
                                        @if ($conta->padrao)
                                            <span class="etiqueta ml-1 text-graphite-400">padrão</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-graphite-500">{{ $conta->banco ?: ucfirst($conta->tipo) }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <p @class([
                                        'num text-sm font-semibold',
                                        'text-graphite-900' => $conta->saldoCentavos() >= 0,
                                        'text-danger-700' => $conta->saldoCentavos() < 0,
                                    ])>{{ App\Support\Dinheiro::formatar($conta->saldoCentavos()) }}</p>
                                    <button type="button" wire:click.stop="alternarAtiva({{ $conta->id }})" class="etiqueta text-graphite-400 hover:text-graphite-700">
                                        {{ $conta->ativo ? 'Inativar' : 'Ativar' }}
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="grid gap-6">
            @if ($this->contaSelecionada)
                <x-ui.card title="Ajuste manual" subtitle="Lançamento sem título: saque, taxa, transferência entre contas.">
                    <form wire:submit="lancarAjuste" class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field label="Sentido" required class="sm:col-span-1">
                            <x-ui.select wire:model="ajusteSentido">
                                <option value="debito">Saída</option>
                                <option value="credito">Entrada</option>
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="Valor" required :error="$errors->first('ajusteValor')" class="sm:col-span-1">
                            <x-ui.input wire:model="ajusteValor" placeholder="0,00" numeric />
                        </x-ui.field>

                        <x-ui.field label="Descrição" required :error="$errors->first('ajusteDescricao')" class="sm:col-span-2">
                            <x-ui.input wire:model="ajusteDescricao" placeholder="Ex.: taxa de manutenção" />
                        </x-ui.field>

                        <x-ui.field label="Data" required :error="$errors->first('ajusteOcorridoEm')" class="sm:col-span-1">
                            <x-ui.input type="date" wire:model="ajusteOcorridoEm" />
                        </x-ui.field>

                        <div class="flex items-end sm:col-span-1">
                            <x-ui.button type="submit">Lançar ajuste</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>

                <x-ui.card title="Extrato de {{ $this->contaSelecionada->nome }}" subtitle="Últimos 50 lançamentos" :padded="false">
                    @if ($this->movimentos->isEmpty())
                        <div class="p-5">
                            <x-ui.empty-state title="Nenhum movimento ainda" description="Baixas de título e ajustes manuais aparecem aqui." />
                        </div>
                    @else
                        <x-ui.table>
                            <thead>
                                <tr class="border-b border-graphite-200 text-left">
                                    <th class="etiqueta px-5 py-2 text-graphite-500">Data</th>
                                    <th class="etiqueta px-5 py-2 text-graphite-500">Descrição</th>
                                    <th class="etiqueta px-5 py-2 text-graphite-500">Origem</th>
                                    <th class="etiqueta px-5 py-2 text-right text-graphite-500">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->movimentos as $movimento)
                                    <tr class="border-b border-graphite-100 last:border-0" wire:key="movimento-{{ $movimento->id }}">
                                        <td class="num px-5 py-3 text-graphite-700">{{ $movimento->ocorrido_em->format('d/m/Y') }}</td>
                                        <td class="px-5 py-3 text-graphite-900">{{ $movimento->descricao }}</td>
                                        <td class="px-5 py-3 text-graphite-500">
                                            {{ match ($movimento->origem_tipo) {
                                                'conta_pagar' => 'Conta a pagar',
                                                'fatura_parcela' => 'Conta a receber',
                                                default => 'Ajuste manual',
                                            } }}
                                        </td>
                                        <td @class([
                                            'num px-5 py-3 text-right font-medium',
                                            'text-success-700' => $movimento->sentido === 'credito',
                                            'text-danger-700' => $movimento->sentido === 'debito',
                                        ])>
                                            {{ $movimento->sentido === 'debito' ? '-' : '+' }}{{ App\Support\Dinheiro::formatar($movimento->valor_centavos) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card>
                    <x-ui.empty-state title="Nenhuma conta selecionada" description="Crie ou escolha uma conta para ver o extrato e lançar ajustes." />
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
