<div class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-6">

    <x-ui.page-header
        eyebrow="Visão geral"
        title="Painel"
        description="O que exige ação vem antes do que já aconteceu." />

    {{-- Pendências primeiro. Quem abre o sistema de manhã precisa saber o que
         travou ontem antes de saber quanto faturou no mês. --}}
    @if ($this->semRegraVigente)
        <x-ui.alert variant="danger" title="Nenhuma regra fiscal vigora hoje">
            Sem regra vigente a emissão para, porque o sistema não inventa alíquota nem CST.
            O contador precisa escrever a regra em <a href="{{ route('regras-fiscais') }}" class="underline">Regras fiscais</a>.
        </x-ui.alert>
    @endif

    @if ($this->certificado === null)
        <x-ui.alert variant="danger" title="Nenhum certificado digital cadastrado">
            Sem certificado A1 não há assinatura, e sem assinatura a SEFAZ não recebe nada.
            @can('certificado.gerenciar')
                Cadastre em <a href="{{ route('certificados') }}" class="underline">Certificado</a>.
            @endcan
        </x-ui.alert>
    @elseif ($this->diasDeCertificado < 0)
        <x-ui.alert variant="danger" title="O certificado digital venceu">
            Venceu em {{ $this->certificado->valido_ate->format('d/m/Y') }}, há {{ abs($this->diasDeCertificado) }} dia(s).
            Nenhuma nota será transmitida até a troca.
        </x-ui.alert>
    @elseif ($this->diasDeCertificado <= 30)
        <x-ui.alert variant="warning" title="O certificado vence em {{ $this->diasDeCertificado }} dia(s)">
            Vence em {{ $this->certificado->valido_ate->format('d/m/Y') }}.
            Renove antes: certificado vencido para o faturamento por completo.
        </x-ui.alert>
    @endif

    @if ($this->pendentes->isNotEmpty())
        <x-ui.card title="Notas que pararam no caminho" subtitle="{{ $this->pendentes->count() }} nota(s) aguardando uma decisão sua">
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="overline px-4 py-2 text-graphite-500">Número</th>
                        <th class="overline px-4 py-2 text-graphite-500">Destinatário</th>
                        <th class="overline px-4 py-2 text-graphite-500">Situação</th>
                        <th class="overline px-4 py-2 text-graphite-500">O que fazer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pendentes as $nota)
                        <tr class="border-b border-graphite-100 last:border-0">
                            <td class="num px-4 py-3 font-medium text-graphite-900">
                                {{ $nota->numero ? number_format($nota->numero, 0, ',', '.').'/'.$nota->serie : 'sem número' }}
                            </td>
                            <td class="px-4 py-3 text-graphite-700">{{ $nota->destinatario?->razao_social ?? 'sem destinatário' }}</td>
                            <td class="px-4 py-3"><x-ui.badge-status :status="$nota->status" /></td>
                            <td class="px-4 py-3 text-graphite-600">
                                @if ($nota->status === \App\Enums\Fiscal\NFeStatus::EmProcessamento)
                                    Consulte pela chave antes de qualquer coisa. A SEFAZ pode já ter autorizado sem a resposta ter voltado, e reemitir criaria duplicidade.
                                @elseif ($nota->status === \App\Enums\Fiscal\NFeStatus::Rejeitada)
                                    {{ $nota->x_motivo ?: 'Corrija o que a SEFAZ apontou e transmita de novo. O número não foi consumido.' }}
                                @else
                                    Emitida em contingência. Transmita para a SEFAZ assim que o serviço voltar.
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($this->abaixoDoMinimo->isNotEmpty())
        <x-ui.alert variant="warning" title="{{ $this->abaixoDoMinimo->count() }} produto(s) abaixo do estoque mínimo">
            {{ $this->abaixoDoMinimo->take(4)->map(fn ($s) => $s->produto->descricao)->implode(' · ') }}
            @if ($this->abaixoDoMinimo->count() > 4)
                e outros {{ $this->abaixoDoMinimo->count() - 4 }}.
            @endif
        </x-ui.alert>
    @endif

    {{-- Só depois, o retrato do mês. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card class="px-4 py-4">
            <p class="overline text-graphite-500">Faturado no mês</p>
            <p class="num display-title mt-1 text-3xl text-graphite-900">{{ number_format($mes['faturado'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-graphite-500">Sem as canceladas</p>
        </x-ui.card>

        <x-ui.card class="px-4 py-4">
            <p class="overline text-graphite-500">Autorizadas no mês</p>
            <p class="num display-title mt-1 text-3xl text-graphite-900">{{ number_format($mes['autorizadas'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-graphite-500">{{ $mes['canceladas'] }} cancelada(s)</p>
        </x-ui.card>

        <x-ui.card class="px-4 py-4">
            <p class="overline text-graphite-500">Rascunhos abertos</p>
            <p class="num display-title mt-1 text-3xl text-graphite-900">{{ number_format($mes['rascunhos'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-graphite-500">Ainda não consomem número</p>
        </x-ui.card>

        <x-ui.card class="px-4 py-4">
            <p class="overline text-graphite-500">Certificado</p>
            @if ($this->certificado === null)
                <p class="display-title mt-1 text-3xl text-danger-700">Nenhum</p>
                <p class="mt-1 text-xs text-graphite-500">Necessário para transmitir</p>
            @else
                <p class="num display-title mt-1 text-3xl {{ $this->diasDeCertificado < 0 ? 'text-danger-700' : ($this->diasDeCertificado <= 30 ? 'text-ember-700' : 'text-graphite-900') }}">
                    {{ $this->diasDeCertificado < 0 ? 'vencido' : $this->diasDeCertificado }}
                </p>
                <p class="mt-1 text-xs text-graphite-500">
                    {{ $this->diasDeCertificado < 0 ? 'Desde '.$this->certificado->valido_ate->format('d/m/Y') : 'dia(s) até vencer' }}
                </p>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Últimas notas">
        @if ($this->ultimas->isEmpty())
            <x-ui.empty-state
                class="m-4"
                title="Nenhuma nota ainda"
                description="Quando a primeira nota for lançada, ela aparece aqui.">
                <x-slot:action>
                    @can('nota.criar')
                        <x-ui.button href="{{ route('notas') }}">Nova nota</x-ui.button>
                    @endcan
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="overline px-4 py-2 text-graphite-500">Número</th>
                        <th class="overline px-4 py-2 text-graphite-500">Destinatário</th>
                        <th class="overline px-4 py-2 text-graphite-500">Emissão</th>
                        <th class="overline px-4 py-2 text-graphite-500">Situação</th>
                        <th class="overline px-4 py-2 text-right text-graphite-500">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->ultimas as $nota)
                        <tr class="border-b border-graphite-100 last:border-0">
                            <td class="num px-4 py-3 font-medium text-graphite-900">
                                {{ $nota->numero ? number_format($nota->numero, 0, ',', '.').'/'.$nota->serie : 'sem número' }}
                            </td>
                            <td class="px-4 py-3 text-graphite-700">{{ $nota->destinatario?->razao_social ?? 'sem destinatário' }}</td>
                            <td class="num px-4 py-3 text-graphite-600">{{ $nota->data_emissao?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3"><x-ui.badge-status :status="$nota->status" /></td>
                            <td class="num px-4 py-3 text-right text-graphite-900">{{ number_format((float) $nota->valor_nota, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
