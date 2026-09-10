<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Identidade"
        title="Marca"
        :description="$this->tenant->nome" />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($avisoContraste)
        <x-ui.alert variant="warning" title="Cor escurecida para garantir leitura">
            A cor informada não alcançava o contraste mínimo com texto branco, então foi escurecida
            o mínimo necessário. A cor original continua presente nos tons claros da escala.
        </x-ui.alert>
    @endif

    <x-ui.card title="Cores" subtitle="Todo o sistema usa variáveis CSS, então salvar aqui repinta as telas inteiras.">
        <form wire:submit="salvar" class="grid gap-5">

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field label="Nome curto" for="m-nome" hint="Aparece na barra lateral.">
                    <x-ui.input id="m-nome" wire:model="nomeCurto" maxlength="40" />
                </x-ui.field>

                <x-ui.field label="Cor da marca" for="m-prim" required :error="$errors->first('primaria')"
                    hint="A cor de ação e de destaque.">
                    <div class="flex gap-2">
                        <input type="color" id="m-prim" wire:model.live="primaria"
                            class="h-[38px] w-14 shrink-0 cursor-pointer border border-graphite-300 bg-white p-1">
                        <x-ui.input wire:model.live.debounce.400ms="primaria" class="flex-1" />
                    </div>
                </x-ui.field>

                <x-ui.field label="Cor escura" for="m-neut" required
                    hint="O preto da marca. Vira a barra lateral e o texto.">
                    <div class="flex gap-2">
                        <input type="color" id="m-neut" wire:model.live="neutra"
                            class="h-[38px] w-14 shrink-0 cursor-pointer border border-graphite-300 bg-white p-1">
                        <x-ui.input wire:model.live.debounce.400ms="neutra" class="flex-1" />
                    </div>
                </x-ui.field>
            </div>

            @if ($this->contrastePrimaria !== null)
                <div class="flex flex-wrap items-center gap-3 border-l-2 border-graphite-200 pl-4 text-sm">
                    <span class="text-graphite-600">Contraste com texto branco:</span>
                    <span class="num font-semibold {{ $this->contrastePrimaria >= 4.5 ? 'text-success-700' : 'text-ember-700' }}">
                        {{ number_format($this->contrastePrimaria, 2, ',', '.') }}
                    </span>
                    @if ($this->contrastePrimaria >= 4.5)
                        <span class="etiqueta bg-success-100 px-2 py-1 text-success-800">Passa em WCAG AA</span>
                    @else
                        <span class="etiqueta bg-ember-100 px-2 py-1 text-ember-800">Será escurecida ao salvar</span>
                    @endif
                </div>
            @endif

            <div><x-ui.button type="submit" size="lg">Salvar marca</x-ui.button></div>
        </form>
    </x-ui.card>

    {{-- Prévia --}}
    <x-ui.card title="Prévia da escala" subtitle="Onze tons gerados a partir das duas cores. A marca fica no tom 600, o escuro no 900.">
        <div class="grid gap-5">
            @foreach ([['Cor da marca', $this->previaPrimaria, 600], ['Cor escura', $this->previaNeutra, 900]] as [$rotulo, $escala, $ancora])
                @if ($escala)
                    <div>
                        <h3 class="mb-2 text-xs font-semibold text-graphite-900">{{ $rotulo }}</h3>
                        <div class="grid grid-cols-6 gap-1 sm:grid-cols-11">
                            @foreach ($escala as $tom => $cor)
                                <div class="grid gap-1">
                                    <div class="h-10 ring-1 ring-black/15" style="background-color: {{ $cor }}"></div>
                                    <span class="num text-[10px] {{ $tom === $ancora ? 'font-bold text-primary-700' : 'text-graphite-500' }}">
                                        {{ $tom }}
                                    </span>
                                    <span class="num text-[9px] leading-none text-graphite-400">{{ $cor }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </x-ui.card>
</div>
