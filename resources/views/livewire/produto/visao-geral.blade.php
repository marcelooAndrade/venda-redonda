@php
    $crm = $this->crm;
    $financeiro = $this->financeiro;
@endphp

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Produto"
        title="Visão geral"
        description="Resumo do funil de negócio, dos clientes e do financeiro da empresa Marcelo Andrade." />

    @if ($this->tenantConfigurado === null)
        <x-ui.card>
            <x-ui.empty-state
                title="Falta configurar o tenant administrativo"
                description="Defina TENANT_ADMINISTRATIVO_SLUG no .env com o slug da empresa Marcelo Andrade, para o resumo de clientes e financeiro aparecerem aqui." />
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="CRM" subtitle="Funil de negócio">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <p class="etiqueta text-graphite-500">Total</p>
                    <p class="num mt-1 text-2xl font-bold text-graphite-900">{{ $crm['total'] }}</p>
                </div>
                <div>
                    <p class="etiqueta text-graphite-500">Em aberto</p>
                    <p class="num mt-1 text-2xl font-bold text-graphite-900">{{ $crm['emAberto'] }}</p>
                </div>
                <div>
                    <p class="etiqueta text-graphite-500">Ganhos</p>
                    <p class="num mt-1 text-2xl font-bold text-success-700">{{ $crm['ganhos'] }}</p>
                </div>
                <div>
                    <p class="etiqueta text-graphite-500">Perdidos</p>
                    <p class="num mt-1 text-2xl font-bold text-danger-700">{{ $crm['perdidos'] }}</p>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('crm') }}" class="text-xs font-medium text-primary-600 hover:underline">Abrir o CRM</a>
            </div>
        </x-ui.card>

        <x-ui.card title="Clientes" subtitle="Destinatários marcados como cliente">
            <p class="etiqueta text-graphite-500">Total de clientes</p>
            <p class="num mt-1 text-4xl font-bold tracking-tight text-graphite-900">{{ $this->totalClientes }}</p>
            <div class="mt-4">
                <a href="{{ route('clientes') }}" class="text-xs font-medium text-primary-600 hover:underline">Abrir Clientes</a>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card title="Financeiro" subtitle="Empresa Marcelo Andrade">
        @if ($financeiro === null)
            <x-ui.empty-state title="Sem emitente" description="A empresa Marcelo Andrade ainda não tem um emitente cadastrado." />
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <p class="etiqueta text-graphite-500">Saldo em caixa</p>
                    <p @class([
                        'num mt-1 text-2xl font-bold',
                        'text-graphite-900' => $financeiro['saldoCentavos'] >= 0,
                        'text-danger-700' => $financeiro['saldoCentavos'] < 0,
                    ])>{{ App\Support\Dinheiro::formatar($financeiro['saldoCentavos']) }}</p>
                </div>
                <div>
                    <p class="etiqueta text-graphite-500">Recebido no mês</p>
                    <p class="num mt-1 text-2xl font-bold text-success-700">{{ App\Support\Dinheiro::formatar($financeiro['recebidoNoMesCentavos']) }}</p>
                </div>
                <div>
                    <p class="etiqueta text-graphite-500">Pago no mês</p>
                    <p class="num mt-1 text-2xl font-bold text-danger-700">{{ App\Support\Dinheiro::formatar($financeiro['pagoNoMesCentavos']) }}</p>
                </div>
            </div>
            <p class="mt-4 text-xs text-graphite-500">
                Para ver o financeiro completo, entre no sistema como a empresa Marcelo Andrade e abra o menu Financeiro.
            </p>
        @endif
    </x-ui.card>
</div>
