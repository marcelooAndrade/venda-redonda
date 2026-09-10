<div class="mx-auto grid w-full max-w-3xl gap-6 px-4 py-6">

    <x-ui.page-header
        eyebrow="Contabilidade"
        title="Pacote da contabilidade"
        description="Tudo que o contador precisa para escriturar o período, organizado por pasta." />

    @error('periodo')
        <x-ui.alert variant="danger">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card title="Período">
        <form wire:submit="baixar" class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="De" for="c-de" required>
                    <x-ui.input id="c-de" type="date" wire:model.live="de" />
                </x-ui.field>
                <x-ui.field label="Até" for="c-ate" required>
                    <x-ui.input id="c-ate" type="date" wire:model.live="ate" />
                </x-ui.field>
            </div>

            <div class="grid grid-cols-2 gap-px border border-graphite-200 bg-graphite-200 sm:grid-cols-4">
                @foreach ([
                    'Autorizadas' => $this->previa['autorizadas'],
                    'Canceladas' => $this->previa['canceladas'],
                    'Entradas' => $this->previa['entradas'],
                ] as $rotulo => $valor)
                    <div class="bg-white p-3">
                        <span class="num display-title block text-2xl">{{ $valor }}</span>
                        <span class="overline text-graphite-500">{{ $rotulo }}</span>
                    </div>
                @endforeach
                <div class="bg-white p-3">
                    <span class="num display-title block text-2xl">{{ number_format($this->previa['valor'], 0, ',', '.') }}</span>
                    <span class="overline text-graphite-500">Faturado</span>
                </div>
            </div>

            <div><x-ui.button type="submit" size="lg">Baixar pacote</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card title="O que vai no pacote">
        <dl class="grid gap-2 text-sm">
            @foreach ([
                'emitidas/' => 'XML autorizado das notas de saída',
                'canceladas/' => 'XML das notas que foram canceladas',
                'eventos/' => 'Eventos de cancelamento homologados',
                'cartas-de-correcao/' => 'Cartas de correção homologadas',
                'inutilizacoes/' => 'Faixas de numeração inutilizadas',
                'entradas/' => 'XML das notas de fornecedores importadas',
                'resumo.csv' => 'Planilha com uma linha por nota de saída',
            ] as $pasta => $desc)
                <div class="flex flex-wrap gap-x-3 border-b border-graphite-100 pb-1">
                    <dt class="num w-48 font-semibold text-graphite-900">{{ $pasta }}</dt>
                    <dd class="text-graphite-600">{{ $desc }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4 text-xs text-graphite-500">
            O XML é o documento fiscal. O DANFE é apenas a representação impressa e não o substitui.
        </p>
    </x-ui.card>
</div>
