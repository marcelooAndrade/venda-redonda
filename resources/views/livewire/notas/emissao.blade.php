<div class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-6">

    <x-ui.page-header
        eyebrow="Faturamento"
        title="Notas fiscais"
        description="Todo tributo e todo total são calculados no servidor. O número só é consumido na transmissão.">
        <x-slot:actions>
            <span class="num text-xs text-graphite-500">Próximo número: {{ $this->proximoNumero }}/{{ $this->emitente->serie_padrao }}</span>
            @can('nota.criar')
                <x-ui.button wire:click="novaNota">Nova nota</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif
    @if (session('aviso'))
        <x-ui.alert variant="warning">{{ session('aviso') }}</x-ui.alert>
    @endif
    @error('transmissao')
        <x-ui.alert variant="danger" title="Não foi possível transmitir">
            <span class="whitespace-pre-line">{{ $message }}</span>
        </x-ui.alert>
    @enderror
    @error('calculo')
        <x-ui.alert variant="warning" title="Cálculo pendente">{{ $message }}</x-ui.alert>
    @enderror

    @if ($this->nota)
        @php $nota = $this->nota; @endphp

        <x-ui.card :title="'Nota '.$nota->numeroFormatado()">
            <x-slot:actions>
                <x-ui.badge-status :status="$nota->status" />
                <x-ui.button variant="ghost" wire:click="$set('notaId', null)">Fechar</x-ui.button>
                @if ($nota->editavel())
                    @can('nota.emitir')
                        <x-ui.button size="lg" wire:click="transmitir" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="transmitir">Transmitir para a SEFAZ</span>
                            <span wire:loading wire:target="transmitir">Transmitindo...</span>
                        </x-ui.button>
                    @endcan
                @endif
            </x-slot:actions>

            @if ($nota->c_stat && ! $nota->status->is(\App\Enums\Fiscal\NFeStatus::Autorizada))
                <x-ui.alert :variant="$nota->status->terminal() ? 'danger' : 'warning'" class="mb-4"
                    :title="'SEFAZ '.$nota->c_stat">
                    <span class="whitespace-pre-line">{{ $nota->x_motivo }}</span>
                </x-ui.alert>
            @endif

            @if ($nota->status->is(\App\Enums\Fiscal\NFeStatus::Autorizada))
                <x-ui.alert variant="success" class="mb-4" title="Autorizada">
                    Protocolo <span class="num">{{ $nota->protocolo }}</span> em
                    {{ $nota->autorizada_em?->format('d/m/Y H:i') }}.
                    <span class="num mt-1 block break-all text-xs">{{ $nota->chave_acesso }}</span>
                </x-ui.alert>
            @endif

            {{-- Cabeçalho --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field label="Destinatário" for="n-dest" required>
                    <x-ui.select id="n-dest" wire:model="cabecalho.pessoa_id" :disabled="! $nota->editavel()">
                        <option value="">Escolher...</option>
                        @foreach ($this->destinatarios as $d)
                            <option value="{{ $d->id }}">{{ $d->razao_social }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Natureza da operação" for="n-nat" required>
                    <x-ui.select id="n-nat" wire:model="cabecalho.natureza_operacao_id" :disabled="! $nota->editavel()">
                        <option value="">Escolher...</option>
                        @foreach ($this->naturezas as $nat)
                            <option value="{{ $nat->id }}">{{ $nat->descricao }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Modalidade do frete" for="n-frete">
                    <x-ui.select id="n-frete" wire:model="cabecalho.mod_frete" :disabled="! $nota->editavel()">
                        @foreach ([
                            '0' => '0 - Por conta do remetente (CIF)',
                            '1' => '1 - Por conta do destinatário (FOB)',
                            '2' => '2 - Por conta de terceiros',
                            '3' => '3 - Transporte próprio, remetente',
                            '4' => '4 - Transporte próprio, destinatário',
                            '9' => '9 - Sem ocorrência de transporte',
                        ] as $cod => $rot)
                            <option value="{{ $cod }}">{{ $rot }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Informações complementares" for="n-info" class="sm:col-span-3">
                    <x-ui.textarea id="n-info" rows="2" wire:model="cabecalho.info_complementares" :disabled="! $nota->editavel()" />
                </x-ui.field>
            </div>

            @if ($nota->editavel())
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="cabecalho.consumidor_final" class="size-4 accent-graphite-900">
                        Consumidor final
                    </label>
                    <x-ui.button variant="secondary" size="sm" wire:click="salvarCabecalho">Salvar cabeçalho</x-ui.button>
                </div>
            @endif
        </x-ui.card>

        {{-- Itens --}}
        <x-ui.card title="Itens" :subtitle="$nota->itens->count().' item(ns)'">
            @if ($nota->editavel())
                <form wire:submit="adicionarItem" class="mb-4 grid gap-3 sm:grid-cols-[1fr_7rem_9rem_auto]">
                    <x-ui.field label="Produto" for="i-prod" :error="$errors->first('produtoId')">
                        <x-ui.select id="i-prod" wire:model="produtoId">
                            <option value="">Escolher...</option>
                            @foreach ($this->produtos as $p)
                                <option value="{{ $p->id }}">{{ $p->codigo }} · {{ $p->descricao }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Quantidade" for="i-qtd" :error="$errors->first('quantidade')">
                        <x-ui.input id="i-qtd" wire:model="quantidade" numeric />
                    </x-ui.field>
                    <x-ui.field label="Valor unitário" for="i-vu" hint="Vazio usa o preço do cadastro.">
                        <x-ui.input id="i-vu" wire:model="valorUnitario" numeric />
                    </x-ui.field>
                    <div class="flex items-end"><x-ui.button type="submit">Adicionar</x-ui.button></div>
                </form>
            @endif

            @if ($nota->itens->isEmpty())
                <x-ui.empty-state title="Nenhum item" description="Adicione ao menos um produto para transmitir." />
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200">
                            <th class="overline px-2 py-2 text-left text-graphite-500">#</th>
                            <th class="overline px-2 py-2 text-left text-graphite-500">Produto</th>
                            <th class="overline px-2 py-2 text-left text-graphite-500">CFOP</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Qtd.</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Unitário</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">ICMS</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">IBS+CBS</th>
                            <th class="overline px-2 py-2 text-right text-graphite-500">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($nota->itens as $item)
                            <tr class="border-b border-graphite-100" wire:key="ni-{{ $item->id }}">
                                <td class="num px-2 py-2">{{ $item->numero }}</td>
                                <td class="px-2 py-2">
                                    {{ $item->descricao }}
                                    <span class="num block text-xs text-graphite-500">NCM {{ $item->ncm }} · CST {{ $item->cst_icms ?? $item->csosn ?? '-' }}</span>
                                </td>
                                <td class="num px-2 py-2">{{ $item->cfop }}</td>
                                <td class="num px-2 py-2 text-right">{{ number_format((float) $item->quantidade, 2, ',', '.') }}</td>
                                <td class="num px-2 py-2 text-right">{{ number_format((float) $item->valor_unitario, 2, ',', '.') }}</td>
                                <td class="num px-2 py-2 text-right">{{ number_format((float) $item->valor_icms, 2, ',', '.') }}</td>
                                <td class="num px-2 py-2 text-right">{{ number_format((float) $item->valor_ibs_uf + (float) $item->valor_ibs_mun + (float) $item->valor_cbs, 2, ',', '.') }}</td>
                                <td class="num px-2 py-2 text-right font-semibold">{{ number_format((float) $item->valor_produto, 2, ',', '.') }}</td>
                                <td class="px-2 py-2 text-right">
                                    @if ($nota->editavel())
                                        <x-ui.button variant="ghost" size="sm" wire:click="removerItem({{ $item->id }})">Remover</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                {{-- Totais --}}
                <dl class="mt-4 grid gap-x-8 gap-y-2 border-t-2 border-graphite-900 pt-4 sm:grid-cols-3">
                    @foreach ([
                        'Produtos' => $nota->valor_produtos,
                        'Base de ICMS' => $nota->base_icms,
                        'ICMS' => $nota->valor_icms,
                        'ICMS ST' => $nota->valor_icms_st,
                        'IPI' => $nota->valor_ipi,
                        'PIS + COFINS' => (float) $nota->valor_pis + (float) $nota->valor_cofins,
                        'IBS' => $nota->valor_ibs,
                        'CBS' => $nota->valor_cbs,
                    ] as $rotulo => $valor)
                        <div class="flex justify-between gap-4 text-sm">
                            <dt class="text-graphite-600">{{ $rotulo }}</dt>
                            <dd class="num">{{ number_format((float) $valor, 2, ',', '.') }}</dd>
                        </div>
                    @endforeach
                    <div class="flex justify-between gap-4 border-t border-graphite-200 pt-2 sm:col-span-3">
                        <dt class="display-title text-lg">Total da nota</dt>
                        <dd class="num display-title text-lg">{{ number_format((float) $nota->valor_nota, 2, ',', '.') }}</dd>
                    </div>
                </dl>
            @endif
        </x-ui.card>
    @endif

    {{-- Listagem --}}
    <x-ui.card title="Notas">
        <x-slot:actions>
            <x-ui.select wire:model.live="filtro" class="w-auto">
                <option value="todas">Todas</option>
                @foreach (\App\Enums\Fiscal\NFeStatus::cases() as $s)
                    <option value="{{ $s->value }}">{{ $s->rotulo() }}</option>
                @endforeach
            </x-ui.select>
        </x-slot:actions>

        @if ($this->notas->isEmpty())
            <x-ui.empty-state title="Nenhuma nota" description="Comece criando uma nota nova." />
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="overline px-2 py-2 text-left text-graphite-500">Número</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Destinatário</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Emissão</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Situação</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Valor</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->notas as $n)
                        <tr class="border-b border-graphite-100" wire:key="nota-{{ $n->id }}">
                            <td class="num px-2 py-2">{{ $n->numeroFormatado() }}</td>
                            <td class="px-2 py-2">{{ $n->destinatario?->razao_social ?? '-' }}</td>
                            <td class="num px-2 py-2">{{ $n->data_emissao->format('d/m/Y') }}</td>
                            <td class="px-2 py-2"><x-ui.badge-status :status="$n->status" /></td>
                            <td class="num px-2 py-2 text-right">{{ number_format((float) $n->valor_nota, 2, ',', '.') }}</td>
                            <td class="px-2 py-2 text-right">
                                <x-ui.button variant="ghost" size="sm" wire:click="abrir({{ $n->id }})">Abrir</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
