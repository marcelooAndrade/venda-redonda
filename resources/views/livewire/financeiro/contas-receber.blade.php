<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Contas a receber"
        description="A parcela é o título: é ela que vence, atrasa e é recebida." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @error('baixa')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.card>
            <p class="etiqueta text-graphite-500">A receber</p>
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
        <x-ui.card title="Lançar fatura"
            subtitle="O valor é o total. Dividido em parcelas, a sobra de centavo vai para a primeira.">
            <form wire:submit="lancar" class="grid gap-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field label="Título" for="cr-titulo" required :error="$errors->first('titulo')">
                        <x-ui.input id="cr-titulo" wire:model="titulo" maxlength="160" placeholder="Venda 1001" />
                    </x-ui.field>

                    <x-ui.field label="Valor total" for="cr-valor" required :error="$errors->first('valor')">
                        <x-ui.input id="cr-valor" wire:model="valor" placeholder="R$ 0,00" />
                    </x-ui.field>

                    <x-ui.field label="Parcelas" for="cr-parc" required :error="$errors->first('parcelas')">
                        <x-ui.input id="cr-parc" type="number" min="1" max="120" wire:model="parcelas" />
                    </x-ui.field>

                    <x-ui.field label="Primeiro vencimento" for="cr-venc" required
                        :error="$errors->first('primeiroVencimento')" hint="As demais caem de mês em mês.">
                        <x-ui.input id="cr-venc" type="date" wire:model="primeiroVencimento" />
                    </x-ui.field>
                </div>

                @if ($this->clientes->isNotEmpty())
                    <x-ui.field label="Cliente" for="cr-cli" class="max-w-md">
                        <x-ui.select id="cr-cli" wire:model="pessoaId">
                            <option value="">Sem cliente vinculado</option>
                            @foreach ($this->clientes as $cliente)
                                <option value="{{ $cliente->id }}">{{ $cliente->razao_social }}</option>
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
                @foreach (['pendentes' => 'A receber', 'recebidos' => 'Recebidos', 'todos' => 'Todos'] as $chave => $rotulo)
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
                    <x-ui.field label="Receber na conta" for="cr-conta" class="w-full max-w-xs"
                        hint="Sem conta, o título fecha e o saldo não muda.">
                        <x-ui.select id="cr-conta" wire:model="contaBaixaId">
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
                description="Lance uma fatura acima. Se ela tiver parcelas, cada uma vira um título com vencimento próprio." />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Vencimento</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Título</th>
                        <th class="etiqueta px-2 py-2 text-left text-graphite-500">Cliente</th>
                        <th class="etiqueta px-2 py-2 text-right text-graphite-500">Parcela</th>
                        <th class="etiqueta px-2 py-2 text-right text-graphite-500">Valor</th>
                        <th class="etiqueta px-2 py-2 text-right text-graphite-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->titulos as $parcela)
                        @php($vencida = $parcela->estaPendente() && $parcela->vencimento->toDateString() < today()->toDateString())
                        <tr class="border-b border-graphite-100">
                            <td class="num px-2 py-2 whitespace-nowrap {{ $vencida ? 'font-semibold text-danger-700' : 'text-graphite-700' }}">
                                {{ $parcela->vencimento->format('d/m/Y') }}
                            </td>
                            <td class="px-2 py-2 text-graphite-900">{{ $parcela->fatura->titulo }}</td>
                            <td class="px-2 py-2 text-graphite-600">
                                {{ $parcela->fatura->destinatario?->razao_social ?: '—' }}
                            </td>
                            <td class="num px-2 py-2 text-right text-graphite-600">{{ $parcela->numero }}</td>
                            <td class="num px-2 py-2 text-right font-semibold text-graphite-900">
                                {{ App\Support\Dinheiro::formatar($parcela->valor_centavos) }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                @if ($parcela->status === 'pago')
                                    <span class="etiqueta bg-success-100 px-2 py-1 text-success-800">Recebido</span>
                                @else
                                    @can('financeiro.gerenciar')
                                        <x-ui.button size="sm" :variant="$vencida ? 'destructive' : 'secondary'"
                                            wire:click="baixar({{ $parcela->id }})"
                                            wire:confirm="Registrar o recebimento desta parcela?">Receber</x-ui.button>
                                    @else
                                        <span class="etiqueta bg-graphite-100 px-2 py-1 text-graphite-700">
                                            {{ $vencida ? 'Vencido' : 'Em aberto' }}
                                        </span>
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
