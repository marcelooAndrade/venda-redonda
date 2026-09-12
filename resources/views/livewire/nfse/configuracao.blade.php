@php($config = $this->configuracao)

<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Configuração"
        title="NFS-e"
        description="Emissão pelo SIGISS de Araras. Valide em homologação antes de ligar a produção." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    @if ($this->pendencias !== [])
        <x-ui.alert variant="warning" title="O emitente ainda não pode emitir NFS-e">
            <ul class="list-disc pl-5">
                @foreach ($this->pendencias as $pendencia)
                    <li>{{ $pendencia }}</li>
                @endforeach
            </ul>
            <p class="mt-2">Corrija em <a href="{{ route('emitente') }}" class="underline">Emitente</a>.</p>
        </x-ui.alert>
    @endif

    <form wire:submit="salvar" class="grid gap-6">
        <x-ui.card title="Emissão" subtitle="Inscrição municipal {{ $this->emitente->inscricao_municipal ?: 'não informada' }} · {{ $this->emitente->municipio ?: 'município não informado' }}">
            <div class="grid gap-4">
                <label class="flex items-center gap-2 text-sm text-graphite-800">
                    <input type="checkbox" wire:model="habilitado" class="size-4 rounded border-graphite-300 text-graphite-900 focus:ring-primary-600/25">
                    Ligar a emissão de NFS-e
                </label>
                @error('habilitado')
                    <p class="text-xs text-danger-700">{{ $message }}</p>
                @enderror

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field label="Série do RPS" for="nf-serie" required :error="$errors->first('serieRps')">
                        <x-ui.input id="nf-serie" wire:model="serieRps" maxlength="5" numeric />
                    </x-ui.field>
                    <x-ui.field label="Próximo RPS de homologação" for="nf-rps-hml" required :error="$errors->first('proximoRpsHomologacao')">
                        <x-ui.input id="nf-rps-hml" type="number" min="1" wire:model="proximoRpsHomologacao" numeric />
                    </x-ui.field>
                    <x-ui.field label="Próximo RPS de produção" for="nf-rps-prod" required :error="$errors->first('proximoRpsProducao')"
                        hint="Antes de ativar produção, informe o próximo número depois do último usado no sistema anterior. Número repetido é rejeição.">
                        <x-ui.input id="nf-rps-prod" type="number" min="1" wire:model="proximoRpsProducao" numeric />
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <x-ui.field label="Senha SIGISS de homologação" for="nf-senha-hml" :error="$errors->first('senhaHomologacao')">
                            <x-ui.input id="nf-senha-hml" type="password" wire:model="senhaHomologacao" autocomplete="new-password"
                                :disabled="$removerSenhaHomologacao"
                                placeholder="{{ filled($config->senha_homologacao) ? 'Senha cadastrada. Digite para substituir' : 'Digite a senha do web service' }}" />
                        </x-ui.field>
                        @if (filled($config->senha_homologacao))
                            <label class="flex items-center gap-2 text-xs text-graphite-600">
                                <input type="checkbox" wire:model.live="removerSenhaHomologacao" class="size-4 rounded border-graphite-300">
                                Remover a senha salva
                            </label>
                        @endif
                    </div>
                    <div class="grid gap-2">
                        <x-ui.field label="Senha SIGISS de produção" for="nf-senha-prod" :error="$errors->first('senhaProducao')">
                            <x-ui.input id="nf-senha-prod" type="password" wire:model="senhaProducao" autocomplete="new-password"
                                :disabled="$removerSenhaProducao"
                                placeholder="{{ filled($config->senha_producao) ? 'Senha cadastrada. Digite para substituir' : 'Digite a senha do web service' }}" />
                        </x-ui.field>
                        @if (filled($config->senha_producao))
                            <label class="flex items-center gap-2 text-xs text-graphite-600">
                                <input type="checkbox" wire:model.live="removerSenhaProducao" class="size-4 rounded border-graphite-300">
                                Remover a senha salva
                            </label>
                        @endif
                    </div>
                </div>

                <p class="text-xs text-graphite-500">As senhas ficam cifradas no servidor e nunca voltam ao navegador.</p>

                <div>
                    <x-ui.button type="submit">Salvar configuração</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    </form>

    <x-ui.card title="Ambiente" subtitle="A NFS-e tem ambiente próprio, separado do da NF-e.">
        @if ($config->ambiente === \App\Enums\Fiscal\Ambiente::Homologacao)
            <p class="text-sm text-graphite-600">
                A NFS-e está em <strong>homologação</strong>. As notas não têm valor fiscal.
                A virada exige a senha de produção cadastrada.
            </p>
            <form wire:submit="ativarProducao" class="mt-4 grid gap-4 sm:max-w-sm">
                <x-ui.field label="Digite PRODUCAO para confirmar" for="nf-conf-prod"
                    hint="A partir daqui toda NFS-e emitida tem valor fiscal."
                    :error="$errors->first('confirmacaoProducao') ?: $errors->first('ambiente')">
                    <x-ui.input id="nf-conf-prod" wire:model="confirmacaoProducao" autocomplete="off" placeholder="PRODUCAO" />
                </x-ui.field>
                <div><x-ui.button type="submit" variant="destructive">Ativar produção</x-ui.button></div>
            </form>
        @else
            <x-ui.alert variant="warning" title="NFS-e em produção">
                Ativada em {{ $config->producao_ativada_em?->format('d/m/Y H:i') }}. Toda nota emitida tem valor fiscal.
            </x-ui.alert>
            <div class="mt-4">
                <x-ui.button variant="secondary" wire:click="voltarParaHomologacao">Voltar para homologação</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Serviços fiscais" subtitle="Um por tipo de serviço prestado, com os códigos que a orientação contábil indicar." :padded="false">
        <x-slot:actions>
            <x-ui.button variant="secondary" size="sm" wire:click="novoServico">Novo serviço</x-ui.button>
        </x-slot:actions>

        @if ($editandoServico)
            <form wire:submit="salvarServico" class="grid gap-4 border-b border-graphite-200/70 p-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.field label="Nome" for="sv-nome" required :error="$errors->first('servico.nome')" class="lg:col-span-2">
                        <x-ui.input id="sv-nome" wire:model="servico.nome" maxlength="120" />
                    </x-ui.field>
                    <x-ui.field label="Código do serviço" for="sv-codigo" required :error="$errors->first('servico.codigo_servico')" hint="Lista da LC 116, no formato 00.00.00.">
                        <x-ui.input id="sv-codigo" wire:model="servico.codigo_servico" maxlength="12" placeholder="10.08.01" />
                    </x-ui.field>
                    <x-ui.field label="Código NBS" for="sv-nbs" :error="$errors->first('servico.codigo_nbs')">
                        <x-ui.input id="sv-nbs" wire:model="servico.codigo_nbs" maxlength="20" placeholder="1.1406.20.00" />
                    </x-ui.field>
                    <x-ui.field label="cClassTrib" for="sv-classtrib" :error="$errors->first('servico.c_class_trib')">
                        <x-ui.input id="sv-classtrib" wire:model="servico.c_class_trib" maxlength="10" numeric />
                    </x-ui.field>
                    <x-ui.field label="Indicador de operação" for="sv-indop" :error="$errors->first('servico.ind_op')">
                        <x-ui.input id="sv-indop" wire:model="servico.ind_op" maxlength="10" numeric />
                    </x-ui.field>
                    <x-ui.field label="Alíquota do ISS (%)" for="sv-aliquota" required :error="$errors->first('servico.aliquota_iss')" hint="De 0 a 5, como 2,00.">
                        <x-ui.input id="sv-aliquota" wire:model="servico.aliquota_iss" numeric />
                    </x-ui.field>
                    <x-ui.field label="Descrição padrão" for="sv-descricao" :error="$errors->first('servico.descricao_padrao')" class="sm:col-span-2 lg:col-span-3"
                        hint="Preenche a descrição da nota; dá para mudar na hora de emitir.">
                        <x-ui.textarea id="sv-descricao" wire:model="servico.descricao_padrao" rows="3" maxlength="1000" />
                    </x-ui.field>
                    <label class="flex items-center gap-2 text-sm text-graphite-800">
                        <input type="checkbox" wire:model="servico.iss_retido" class="size-4 rounded border-graphite-300">
                        ISS retido pelo tomador
                    </label>
                    <label class="flex items-center gap-2 text-sm text-graphite-800">
                        <input type="checkbox" wire:model="servico.ativo" class="size-4 rounded border-graphite-300">
                        Serviço ativo
                    </label>
                </div>
                <div class="flex gap-2">
                    <x-ui.button type="submit">Salvar serviço</x-ui.button>
                    <x-ui.button type="button" variant="ghost" wire:click="cancelarServico">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($this->servicos->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="Nenhum serviço fiscal" description="Cadastre ao menos um para a emissão saber o código do serviço e a alíquota." />
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200 text-left">
                        <th class="etiqueta px-5 py-2 text-graphite-500">Serviço</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Código</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">NBS</th>
                        <th class="etiqueta px-5 py-2 text-right text-graphite-500">ISS</th>
                        <th class="etiqueta px-5 py-2 text-graphite-500">Situação</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->servicos as $servicoLinha)
                        <tr class="border-b border-graphite-100 last:border-0" wire:key="servico-{{ $servicoLinha->id }}">
                            <td class="px-5 py-3 font-medium text-graphite-900">{{ $servicoLinha->nome }}</td>
                            <td class="num px-5 py-3 text-graphite-700">{{ $servicoLinha->codigo_servico }}</td>
                            <td class="num px-5 py-3 text-graphite-700">{{ $servicoLinha->codigo_nbs ?: '—' }}</td>
                            <td class="num px-5 py-3 text-right text-graphite-700">{{ $servicoLinha->aliquotaFormatada() }}%{{ $servicoLinha->iss_retido ? ' retido' : '' }}</td>
                            <td class="px-5 py-3">
                                <span class="etiqueta {{ $servicoLinha->ativo ? 'bg-success-100 text-success-800' : 'bg-graphite-100 text-graphite-700' }} px-2 py-1">
                                    {{ $servicoLinha->ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-ui.button variant="ghost" size="sm" wire:click="editarServico({{ $servicoLinha->id }})">Editar</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
