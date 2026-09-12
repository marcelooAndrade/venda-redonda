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
        @if (blank($this->emitente->chave_pix))
            <x-ui.alert variant="info" title="Cobrança Pix desligada">
                Sem chave cadastrada a parcela nasce sem código de pagamento.
                @can('emitente.gerenciar')
                    Cadastre a chave em <a href="{{ route('emitente') }}" class="underline">Emitente</a>.
                @else
                    Peça a quem administra o emitente para cadastrar a chave.
                @endcan
            </x-ui.alert>
        @endif

        <x-ui.card title="Lançar fatura"
            subtitle="Gere as parcelas a partir do total, e ajuste linha a linha se a negociação foi outra.">
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

                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="gerarLinhas">
                        Gerar parcelas
                    </x-ui.button>
                    <x-ui.button type="submit">Lançar</x-ui.button>
                </div>

                {{-- As parcelas viram linhas editáveis. É aqui que negociação de
                     verdade cabe: entrada maior, saldo em datas irregulares. --}}
                @if ($linhas !== [])
                    <div class="border-t border-graphite-200 pt-5">
                        <p class="etiqueta mb-3 text-graphite-500">
                            Parcelas · total {{ App\Support\Dinheiro::formatar(collect($linhas)->sum(fn ($l) => App\Support\Dinheiro::emCentavos((string) ($l['valor'] ?? '')))) }}
                        </p>

                        <div class="grid gap-3">
                            @foreach ($linhas as $i => $linha)
                                <div class="grid min-w-0 gap-3 sm:grid-cols-[1fr_9rem_10rem_auto] sm:items-start">
                                    <x-ui.field :label="$i === 0 ? 'Descrição' : null" :error="$errors->first('linhas.'.$i.'.descricao')">
                                        <x-ui.input wire:model="linhas.{{ $i }}.descricao" maxlength="160" />
                                    </x-ui.field>

                                    <x-ui.field :label="$i === 0 ? 'Valor' : null" :error="$errors->first('linhas.'.$i.'.valor')">
                                        <x-ui.input wire:model="linhas.{{ $i }}.valor" placeholder="R$ 0,00" />
                                    </x-ui.field>

                                    <x-ui.field :label="$i === 0 ? 'Vencimento' : null" :error="$errors->first('linhas.'.$i.'.vencimento')">
                                        <x-ui.input type="date" wire:model="linhas.{{ $i }}.vencimento" />
                                    </x-ui.field>

                                    <div class="{{ $i === 0 ? 'sm:mt-6' : '' }}">
                                        <x-ui.button type="button" variant="ghost" size="sm"
                                            wire:click="removerLinha({{ $i }})"
                                            aria-label="Remover parcela {{ $i + 1 }}">Remover</x-ui.button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="adicionarLinha">
                                Acrescentar parcela
                            </x-ui.button>
                        </div>
                    </div>
                @endif

                @error('linhas')
                    <p class="text-xs text-danger-700">{{ $message }}</p>
                @enderror
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
                        <th class="etiqueta px-2 py-2 text-center text-graphite-500">Cobrança</th>
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
                            <td class="px-2 py-2 text-center">
                                @if (filled($parcela->pix_payload))
                                    {{-- Copia e cola: é assim que o cliente paga no aplicativo
                                         do banco dele, sem precisar de câmera. --}}
                                    <button type="button"
                                        x-data="{ copiado: false }"
                                        @click="navigator.clipboard.writeText(@js($parcela->pix_payload)); copiado = true; setTimeout(() => copiado = false, 2000)"
                                        class="inline-flex min-h-8 items-center rounded-md border border-graphite-300 px-3 text-xs font-semibold text-graphite-700 transition-colors hover:bg-graphite-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                        <span x-show="!copiado">Copiar Pix</span>
                                        <span x-show="copiado" x-cloak class="text-success-700">Copiado</span>
                                    </button>
                                @else
                                    <span class="text-xs text-graphite-400">—</span>
                                @endif
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
