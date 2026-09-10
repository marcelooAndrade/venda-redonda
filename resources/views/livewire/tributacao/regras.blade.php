<div class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-6">

    <x-ui.page-header
        eyebrow="Contabilidade"
        title="Regras fiscais"
        description="A tributação é escrita aqui, pelo contador, e o sistema apenas aplica. Nenhuma alíquota, CST ou CFOP vive no código." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <x-ui.alert variant="info" title="Como isto funciona">
        Cada regra vale a partir de uma data. Ao registrar uma nova para o mesmo âmbito, a anterior
        não é apagada: ela ganha fim de vigência na véspera. Assim uma nota emitida em março continua
        conferindo com a regra que valia em março. Toda alteração fica na auditoria com autor e horário.
    </x-ui.alert>

    <div class="grid gap-6 lg:grid-cols-[280px_1fr]">

        {{-- Perfis --}}
        <div class="grid gap-4 content-start">
            <x-ui.card title="Perfis fiscais">
                @if ($this->perfis->isEmpty())
                    <p class="text-sm text-graphite-500">Nenhum perfil ainda.</p>
                @else
                    <ul class="grid gap-px">
                        @foreach ($this->perfis as $perfil)
                            <li wire:key="perfil-{{ $perfil->id }}">
                                <button type="button" wire:click="selecionarPerfil({{ $perfil->id }})"
                                    @class([
                                        'w-full border-l-[3px] px-3 py-2 text-left text-sm transition-colors',
                                        'border-primary-600 bg-graphite-100 font-medium text-graphite-900' => $perfilSelecionadoId === $perfil->id,
                                        'border-transparent text-graphite-600 hover:bg-graphite-50' => $perfilSelecionadoId !== $perfil->id,
                                    ])>
                                    {{ $perfil->nome }}
                                    <span class="num block text-xs text-graphite-500">{{ $perfil->regras_count }} regra(s)</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Novo perfil">
                <form wire:submit="criarPerfil" class="grid gap-3">
                    <x-ui.field label="Nome" for="pf-nome" :error="$errors->first('perfilNome')"
                        hint="Ex.: Peças microfundidas em aço inox.">
                        <x-ui.input id="pf-nome" wire:model="perfilNome" />
                    </x-ui.field>
                    <div><x-ui.button type="submit" size="sm">Criar perfil</x-ui.button></div>
                </form>
            </x-ui.card>
        </div>

        {{-- Regra --}}
        <div class="grid gap-4 content-start">
            @if (! $this->perfilSelecionado)
                <x-ui.empty-state
                    title="Escolha um perfil"
                    description="Selecione um perfil à esquerda para ver e escrever suas regras, ou crie um novo." />
            @else
                <x-ui.card title="Nova regra" :subtitle="$this->perfilSelecionado->nome">
                    <form wire:submit="salvarRegra" class="grid gap-5">

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Âmbito da operação" for="r-amb" required :error="$errors->first('regra.ambito')">
                                <x-ui.select id="r-amb" wire:model="regra.ambito">
                                    @foreach (\App\Enums\Fiscal\AmbitoOperacao::cases() as $amb)
                                        <option value="{{ $amb->value }}">{{ $amb->rotulo() }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field label="Regime (CRT)" for="r-crt" hint="Vazio vale para qualquer regime.">
                                <x-ui.select id="r-crt" wire:model="regra.crt">
                                    <option value="">Qualquer</option>
                                    <option value="1">1 - Simples Nacional</option>
                                    <option value="2">2 - Simples, excesso de sublimite</option>
                                    <option value="3">3 - Regime Normal</option>
                                    <option value="4">4 - MEI</option>
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field label="Vigente a partir de" for="r-vig" required :error="$errors->first('regra.vigente_de')">
                                <x-ui.input id="r-vig" type="date" wire:model="regra.vigente_de" />
                            </x-ui.field>
                        </div>

                        @php
                            $grupos = [
                                ['ICMS', [
                                    ['cst_icms', 'CST (regime normal)', 'text'],
                                    ['csosn', 'CSOSN (Simples)', 'text'],
                                    ['mod_bc', 'Modalidade da BC', 'text'],
                                    ['aliquota_icms', 'Alíquota %', 'num'],
                                    ['reducao_bc', 'Redução da BC %', 'num'],
                                    ['aliquota_credito_sn', 'Crédito SN %', 'num'],
                                    ['aliquota_fcp', 'FCP %', 'num'],
                                    ['percentual_diferimento', 'Diferimento %', 'num'],
                                ]],
                                ['Substituição tributária', [
                                    ['mva_st', 'MVA %', 'num'],
                                    ['reducao_bc_st', 'Redução da BC ST %', 'num'],
                                    ['aliquota_st', 'Alíquota ST %', 'num'],
                                ]],
                                ['IPI, PIS e COFINS', [
                                    ['cst_ipi', 'CST IPI', 'text'],
                                    ['codigo_enquadramento_ipi', 'Enquadramento IPI', 'text'],
                                    ['aliquota_ipi', 'Alíquota IPI %', 'num'],
                                    ['cst_pis', 'CST PIS', 'text'],
                                    ['aliquota_pis', 'Alíquota PIS %', 'num'],
                                    ['cst_cofins', 'CST COFINS', 'text'],
                                    ['aliquota_cofins', 'Alíquota COFINS %', 'num'],
                                ]],
                                ['Reforma Tributária (IBS, CBS e IS)', [
                                    ['cst_ibscbs', 'CST IBS/CBS', 'text'],
                                    ['cclasstrib', 'cClassTrib', 'text'],
                                    ['aliquota_ibs_uf', 'IBS UF %', 'num'],
                                    ['aliquota_ibs_mun', 'IBS Município %', 'num'],
                                    ['aliquota_cbs', 'CBS %', 'num'],
                                    ['cst_is', 'CST IS', 'text'],
                                    ['aliquota_is', 'Alíquota IS %', 'num'],
                                ]],
                            ];
                        @endphp

                        @foreach ($grupos as [$titulo, $campos])
                            <fieldset class="border-t border-graphite-200 pt-4">
                                <legend class="overline text-graphite-500">{{ $titulo }}</legend>
                                @if ($titulo === 'Reforma Tributária (IBS, CBS e IS)')
                                    <p class="mb-3 text-xs text-ember-700">
                                        Obrigatório em produção desde 03/08/2026 para emitente CRT 3, conforme NT 2025.002-RTC v1.40.
                                    </p>
                                @endif
                                <div class="grid gap-3 sm:grid-cols-4">
                                    @foreach ($campos as [$campo, $rotulo, $tipo])
                                        <x-ui.field :label="$rotulo" :for="'r-'.$campo" :error="$errors->first('regra.'.$campo)">
                                            <x-ui.input :id="'r-'.$campo" wire:model="regra.{{ $campo }}"
                                                :numeric="$tipo === 'num'" />
                                        </x-ui.field>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach

                        <x-ui.field
                            label="Fundamentação"
                            for="r-obs"
                            hint="Por que esta regra é assim. Fica registrado junto com seu nome e a data.">
                            <x-ui.textarea id="r-obs" rows="3" wire:model="regra.observacao_contador" />
                        </x-ui.field>

                        <div><x-ui.button type="submit" size="lg">Registrar regra</x-ui.button></div>
                    </form>
                </x-ui.card>

                {{-- Histórico --}}
                <x-ui.card title="Regras deste perfil" subtitle="Nenhuma é apagada. A anterior recebe fim de vigência.">
                    @if ($this->perfilSelecionado->regras->isEmpty())
                        <x-ui.empty-state title="Nenhuma regra escrita ainda" />
                    @else
                        <x-ui.table>
                            <thead>
                                <tr class="border-b border-graphite-200">
                                    <th class="overline px-2 py-2 text-left text-graphite-500">Vigência</th>
                                    <th class="overline px-2 py-2 text-left text-graphite-500">Âmbito</th>
                                    <th class="overline px-2 py-2 text-left text-graphite-500">CRT</th>
                                    <th class="overline px-2 py-2 text-left text-graphite-500">CST/CSOSN</th>
                                    <th class="overline px-2 py-2 text-right text-graphite-500">ICMS</th>
                                    <th class="overline px-2 py-2 text-right text-graphite-500">IBS+CBS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->perfilSelecionado->regras as $r)
                                    <tr class="border-b border-graphite-100" wire:key="regra-{{ $r->id }}">
                                        <td class="num px-2 py-2">
                                            {{ $r->vigente_de->format('d/m/Y') }}
                                            @if ($r->vigente_ate)
                                                <span class="text-graphite-500">a {{ $r->vigente_ate->format('d/m/Y') }}</span>
                                            @else
                                                <span class="overline ml-1 bg-success-100 px-1.5 py-0.5 text-success-800">Vigente</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2">{{ $r->ambito->rotulo() }}</td>
                                        <td class="num px-2 py-2">{{ $r->crt ?? 'Qualquer' }}</td>
                                        <td class="num px-2 py-2">{{ $r->cst_icms ?? $r->csosn ?? '-' }}</td>
                                        <td class="num px-2 py-2 text-right">{{ $r->aliquota_icms !== null ? number_format((float) $r->aliquota_icms, 2, ',', '.').'%' : '-' }}</td>
                                        <td class="num px-2 py-2 text-right">
                                            {{ $r->aliquota_cbs !== null || $r->aliquota_ibs_uf !== null
                                                ? number_format((float) $r->aliquota_ibs_uf + (float) $r->aliquota_ibs_mun + (float) $r->aliquota_cbs, 2, ',', '.').'%'
                                                : '-' }}
                                        </td>
                                    </tr>
                                    @if ($r->observacao_contador)
                                        <tr class="border-b border-graphite-100">
                                            <td colspan="6" class="px-2 pb-2 text-xs text-graphite-500">{{ $r->observacao_contador }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    @endif
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
