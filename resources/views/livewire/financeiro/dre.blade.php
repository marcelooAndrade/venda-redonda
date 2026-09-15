@php
    $dados = $this->dados;
@endphp

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Financeiro"
        title="DRE"
        description="Simplificada: soma o que entrou e saiu do caixa no mês, por regime de caixa, não de competência. Não substitui uma DRE contábil." />

    <x-ui.card>
        <x-ui.field label="Mês">
            <input type="month" wire:model.live="mes" class="min-h-[38px] min-w-0 rounded-md border border-graphite-300 bg-white px-3 text-sm text-graphite-900 shadow-xs focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/25" />
        </x-ui.field>
    </x-ui.card>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.card>
            <p class="etiqueta text-graphite-500">Receita</p>
            <p class="num mt-1 text-2xl font-bold text-success-700">
                {{ App\Support\Dinheiro::formatar($dados['receitaCentavos']) }}
            </p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Despesa</p>
            <p class="num mt-1 text-2xl font-bold text-danger-700">
                {{ App\Support\Dinheiro::formatar($dados['despesaCentavos']) }}
            </p>
        </x-ui.card>

        <x-ui.card>
            <p class="etiqueta text-graphite-500">Resultado</p>
            <p @class([
                'num mt-1 text-2xl font-bold',
                'text-graphite-900' => $dados['resultadoCentavos'] >= 0,
                'text-danger-700' => $dados['resultadoCentavos'] < 0,
            ])>
                {{ App\Support\Dinheiro::formatar($dados['resultadoCentavos']) }}
            </p>
        </x-ui.card>
    </div>

    <x-ui.card title="Por centro de custo" subtitle="Receita e despesa do mês, agrupadas" :padded="false">
        @if (empty($dados['porCentroCusto']))
            <div class="p-5">
                <x-ui.empty-state title="Nenhum movimento neste mês" description="Quando houver título baixado ou ajuste lançado no caixa, o mês aparece aqui." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Centro de custo</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">Receita</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">Despesa</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dados['porCentroCusto'] as $linha)
                        <tr class="border-b border-graphite-100 last:border-0">
                            <td class="px-5 py-3">
                                <p class="text-graphite-900">{{ $linha['nome'] }}</p>
                                @if ($linha['codigo'])
                                    <p class="num text-xs text-graphite-500">{{ $linha['codigo'] }}</p>
                                @endif
                            </td>
                            <td class="num px-5 py-3 text-right text-graphite-700">
                                {{ $linha['receitaCentavos'] > 0 ? App\Support\Dinheiro::formatar($linha['receitaCentavos']) : '—' }}
                            </td>
                            <td class="num px-5 py-3 text-right text-graphite-700">
                                {{ $linha['despesaCentavos'] > 0 ? App\Support\Dinheiro::formatar($linha['despesaCentavos']) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
