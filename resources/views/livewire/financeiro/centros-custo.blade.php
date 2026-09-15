<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="Centros de custo"
        description="Plano de contas hierárquico, com código de até três níveis, para classificar receita e despesa." />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <x-ui.card title="Novo centro de custo">
            <form wire:submit="criar" class="grid gap-3">
                <x-ui.field label="Código" required :error="$errors->first('codigo')" hint="Até três grupos de três dígitos: 001, 001.002 ou 001.002.003.">
                    <x-ui.input wire:model="codigo" placeholder="001" />
                </x-ui.field>

                <x-ui.field label="Nome" required :error="$errors->first('nome')">
                    <x-ui.input wire:model="nome" placeholder="Ex.: Folha de pagamento" />
                </x-ui.field>

                <x-ui.field label="Natureza" required :error="$errors->first('natureza')">
                    <x-ui.select wire:model.live="natureza">
                        <option value="despesa">Despesa</option>
                        <option value="receita">Receita</option>
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Centro pai" hint="Só grupos da mesma natureza podem ser pai.">
                    <x-ui.select wire:model="paiId">
                        <option value="">Nenhum, é raiz</option>
                        @foreach ($this->possiveisPais as $pai)
                            <option value="{{ $pai->id }}">{{ $pai->codigo }} · {{ $pai->nome }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Essencial" hint="Vazio herda a classificação do pai. Usado no cálculo de reserva de emergência.">
                    <x-ui.select wire:model="essencial">
                        <option value="">Herda do pai</option>
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </x-ui.select>
                </x-ui.field>

                <label class="flex items-center gap-2 text-sm text-graphite-700">
                    <input type="checkbox" wire:model="grupo" class="rounded border-graphite-300">
                    É grupo, pode receber filhos
                </label>

                <x-ui.button type="submit">Criar centro de custo</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Plano de contas" :padded="false">
            @if ($this->centros->isEmpty())
                <div class="p-5">
                    <x-ui.empty-state title="Nenhum centro de custo cadastrado" description="Crie o primeiro ao lado." />
                </div>
            @else
                <x-ui.table>
                    <thead>
                        <tr class="border-b border-graphite-200 text-left">
                            <th class="etiqueta px-5 py-2 text-graphite-500">Código</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Nome</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Natureza</th>
                            <th class="etiqueta px-5 py-2 text-graphite-500">Essencial</th>
                            <th class="etiqueta px-5 py-2 text-right text-graphite-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->centros as $centro)
                            <tr class="border-b border-graphite-100 last:border-0 {{ $centro->ativo ? '' : 'opacity-50' }}" wire:key="centro-{{ $centro->id }}">
                                <td class="num px-5 py-3 text-graphite-700" style="padding-left: {{ 1.25 + (\App\Models\CentroCusto::nivelDoCodigo($centro->codigo) - 1) * 1 }}rem">
                                    {{ $centro->codigo }}
                                </td>
                                <td class="px-5 py-3">
                                    <p class="{{ $centro->grupo ? 'font-medium' : '' }} text-graphite-900">{{ $centro->nome }}</p>
                                </td>
                                <td class="px-5 py-3 text-graphite-700">{{ $centro->natureza === 'receita' ? 'Receita' : 'Despesa' }}</td>
                                <td class="px-5 py-3 text-graphite-500">
                                    {{ $centro->eEssencial() ? 'Sim' : 'Não' }}
                                    @if ($centro->essencial === null)
                                        <span class="etiqueta text-graphite-400">herdado</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button type="button" wire:click="alternarAtivo({{ $centro->id }})" class="etiqueta text-graphite-400 hover:text-graphite-700">
                                        {{ $centro->ativo ? 'Inativar' : 'Ativar' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</div>
