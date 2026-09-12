<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Operação"
        title="Notas de serviço"
        description="As NFS-e emitidas a partir das parcelas, com PDF, XML e situação no SIGISS." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($cancelandoId !== null)
        @php($cancelando = $this->notas->firstWhere('id', $cancelandoId))
        <x-ui.card title="Cancelar {{ $cancelando?->documento() }}" subtitle="O cancelamento é registrado no SIGISS e não se desfaz.">
            <form wire:submit="cancelar" class="grid gap-4 sm:max-w-xl">
                <x-ui.field label="Justificativa" for="nf-motivo" required
                    hint="Entre 15 e 255 caracteres. Ela vai para o SIGISS."
                    :error="$errors->first('nfse')">
                    <x-ui.textarea id="nf-motivo" wire:model="motivoCancelamento" rows="3" maxlength="255" />
                </x-ui.field>
                <div class="flex gap-2">
                    <x-ui.button type="submit" variant="destructive">Cancelar NFS-e</x-ui.button>
                    <x-ui.button type="button" variant="ghost" wire:click="fecharCancelamento">Desistir</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card :padded="false">
        <div class="flex flex-wrap gap-1 border-b border-graphite-200/70 px-5 pt-3">
            @foreach ($this->filtros() as $chave => $rotulo)
                <button type="button" wire:click="$set('situacao', '{{ $chave }}')"
                    @class([
                        'border-b-2 px-3 py-2 text-[13px] transition-colors',
                        'border-primary-600 font-semibold text-graphite-900' => $situacao === $chave,
                        'border-transparent text-graphite-500 hover:text-graphite-900' => $situacao !== $chave,
                    ])>{{ $rotulo }}</button>
            @endforeach
        </div>

        @if ($this->notas->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="Nenhuma NFS-e aqui" description="Abra contas a receber e emita a nota na parcela." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Documento</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Cliente</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Fatura</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Ambiente</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">Valor</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Situação</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->notas as $nota)
                        <tr class="border-b border-graphite-100 last:border-0 align-top" wire:key="nota-{{ $nota->id }}">
                            <td class="px-5 py-3">
                                <p class="num font-medium text-graphite-900">{{ $nota->documento() }}</p>
                                <p class="text-xs text-graphite-500">{{ $nota->emitida_em?->format('d/m/Y H:i') ?? $nota->created_at?->format('d/m/Y H:i') }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-graphite-900">{{ $nota->parcela?->fatura?->destinatario?->razao_social ?? 'Cliente não identificado' }}</p>
                                <p class="text-xs text-graphite-500">{{ $nota->descricao }}</p>
                                @if ($nota->motivo_rejeicao)
                                    <p class="mt-1 text-xs text-danger-700">{{ $nota->motivo_rejeicao }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-graphite-700">
                                {{ $nota->parcela?->fatura?->titulo }}
                                <span class="text-xs text-graphite-500">parcela {{ $nota->parcela?->numero }}</span>
                            </td>
                            <td class="px-5 py-3 text-graphite-700">{{ $nota->ambiente->rotulo() }}</td>
                            <td class="num px-5 py-3 text-right font-semibold text-graphite-900">{{ App\Support\Dinheiro::formatar($nota->valor_centavos) }}</td>
                            <td class="px-5 py-3"><x-ui.badge-status :status="$nota->status" /></td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($nota->temDocumento())
                                    <a href="{{ route('notas-servico.pdf', $nota) }}" target="_blank" rel="noreferrer" class="text-xs font-semibold text-graphite-700 underline">PDF</a>
                                    <a href="{{ route('notas-servico.xml', $nota) }}" class="ml-2 text-xs font-semibold text-graphite-700 underline">XML</a>
                                @endif
                                @can('nfse.cancelar')
                                    @if ($nota->status === \App\Enums\Nfse\NfseStatus::Autorizada)
                                        <x-ui.button variant="ghost" size="sm" class="ml-2" wire:click="abrirCancelamento({{ $nota->id }})">Cancelar NFS-e</x-ui.button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            @if ($this->notas->hasPages())
                <div class="px-5 py-4">{{ $this->notas->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
