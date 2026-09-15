<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Produto"
        title="CRM"
        description="Funil de quem pode virar cliente de desenvolvimento sob medida.">
        <x-slot:actions>
            <x-ui.button size="sm" wire:click="$toggle('formularioAberto')">
                {{ $formularioAberto ? 'Cancelar' : 'Novo contato' }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($formularioAberto)
        <x-ui.card title="Novo contato">
            <form wire:submit="criarContato" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nome" for="crm-nome" required :error="$errors->first('novoNome')">
                    <x-ui.input id="crm-nome" wire:model="novoNome" />
                </x-ui.field>
                <x-ui.field label="Empresa" for="crm-empresa" :error="$errors->first('novaEmpresa')">
                    <x-ui.input id="crm-empresa" wire:model="novaEmpresa" />
                </x-ui.field>
                <x-ui.field label="Telefone / WhatsApp" for="crm-telefone" :error="$errors->first('novoTelefone')">
                    <x-ui.input id="crm-telefone" wire:model="novoTelefone" />
                </x-ui.field>
                <x-ui.field label="E-mail" for="crm-email" :error="$errors->first('novoEmail')">
                    <x-ui.input id="crm-email" type="email" wire:model="novoEmail" />
                </x-ui.field>
                <div class="sm:col-span-2">
                    <x-ui.button type="submit">Adicionar ao funil</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <div class="overflow-x-auto pb-2">
        <div class="grid grid-flow-col auto-cols-[16rem] gap-4">
            @foreach (\App\Enums\EtapaCrm::cases() as $etapa)
                @php $contatos = $this->contatosPorEtapa[$etapa->value]; @endphp
                <div class="flex min-h-0 flex-col rounded-lg border border-graphite-200 bg-graphite-50">
                    <div class="flex shrink-0 items-center justify-between border-b border-graphite-200 px-3 py-2.5">
                        <p class="etiqueta text-graphite-600">{{ $etapa->rotulo() }}</p>
                        <span class="num etiqueta text-graphite-400">{{ $contatos->count() }}</span>
                    </div>

                    <div class="flex flex-col gap-2 p-2">
                        @foreach ($contatos as $contato)
                            <div wire:key="contato-{{ $contato->id }}" class="rounded-md border border-graphite-200 bg-white p-3 shadow-sm">
                                <p class="text-sm font-medium text-graphite-900">{{ $contato->nome }}</p>
                                @if ($contato->empresa)
                                    <p class="text-xs text-graphite-500">{{ $contato->empresa }}</p>
                                @endif
                                @if ($contato->telefone)
                                    <p class="num text-xs text-graphite-500">{{ $contato->telefone }}</p>
                                @endif
                                @if ($contato->email)
                                    <p class="text-xs text-graphite-500">{{ $contato->email }}</p>
                                @endif

                                <select
                                    wire:change="moverEtapa({{ $contato->id }}, $event.target.value)"
                                    class="etiqueta mt-2 w-full rounded-md border border-graphite-300 bg-white px-2 py-1 text-graphite-700">
                                    @foreach (\App\Enums\EtapaCrm::cases() as $opcao)
                                        <option value="{{ $opcao->value }}" @selected($opcao === $contato->etapa)>{{ $opcao->rotulo() }}</option>
                                    @endforeach
                                </select>

                                @if ($editandoObservacaoDe === $contato->id)
                                    <div class="mt-2 grid gap-1.5">
                                        <textarea wire:model="observacaoEmEdicao" rows="3"
                                                  class="w-full rounded-md border border-graphite-300 p-2 text-xs"></textarea>
                                        <div class="flex gap-1.5">
                                            <x-ui.button size="sm" wire:click="salvarObservacao({{ $contato->id }})">Salvar</x-ui.button>
                                            <x-ui.button variant="ghost" size="sm" wire:click="cancelarEdicaoObservacao">Cancelar</x-ui.button>
                                        </div>
                                    </div>
                                @else
                                    <button type="button" wire:click="iniciarEdicaoObservacao({{ $contato->id }})"
                                            class="etiqueta mt-2 text-left text-graphite-400 hover:text-graphite-700">
                                        {{ $contato->observacao ? 'Editar observação' : '+ observação' }}
                                    </button>
                                    @if ($contato->observacao)
                                        <p class="mt-1 text-xs text-graphite-600">{{ $contato->observacao }}</p>
                                    @endif
                                @endif
                            </div>
                        @endforeach

                        @if ($contatos->isEmpty())
                            <p class="px-1 py-2 text-xs text-graphite-400">Nada aqui.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
