<x-layouts.fiscal title="Design System">
    <div class="mx-auto grid w-full max-w-5xl gap-8 px-4 py-6">

        <x-ui.page-header
            eyebrow="emissor-nfe"
            title="Design System RCM"
            description="Extraído de rcmdobrasil.com.br por renderização real e validado em WCAG AA. Documentação completa em docs/design-system.md." />

        {{-- Paleta --}}
        <x-ui.card title="Paleta" subtitle="Marcas em vermelho indicam valor medido no site, não interpolado.">
            <div class="grid gap-5">
                @foreach ([
                    ['primary', 'Vermelho RCM', [600, 700]],
                    ['graphite', 'Grafite industrial', [800, 900]],
                    ['steel', 'Azul-aço da fachada', [400]],
                    ['ember', 'Âmbar do metal fundido', [600]],
                    ['success', 'Confirmação', []],
                    ['danger', 'Ação destrutiva', []],
                ] as [$nome, $desc, $medidos])
                    <div>
                        <div class="mb-1.5 flex flex-wrap items-baseline gap-2">
                            <h3 class="display-title text-sm text-graphite-900">{{ $nome }}</h3>
                            <span class="text-xs text-graphite-500">{{ $desc }}</span>
                        </div>
                        <div class="grid grid-cols-6 gap-1 sm:grid-cols-11">
                            @foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $tom)
                                <div class="grid gap-1">
                                    {{-- Lê o token direto: o Tailwind não gera classe a partir de string --}}
                                    {{-- dinâmica, então bg-{{ $nome }}-{{ $tom }} sairia vazio. --}}
                                    <div class="h-10 ring-1 ring-black/15"
                                         style="background-color: var(--color-{{ $nome }}-{{ $tom }})"></div>
                                    <span class="num text-[10px] {{ in_array($tom, $medidos) ? 'font-bold text-primary-700' : 'text-graphite-500' }}">
                                        @if (in_array($tom, $medidos))<span class="mr-0.5 inline-block size-1 bg-primary-600 align-middle"></span>@endif{{ $tom }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Botões --}}
        <x-ui.card title="Botões" subtitle="A ação primária é grafite. O vermelho da marca nunca vira botão.">
            <div class="grid gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button>Transmitir</x-ui.button>
                    <x-ui.button variant="secondary">Salvar rascunho</x-ui.button>
                    <x-ui.button variant="destructive">Cancelar NF-e</x-ui.button>
                    <x-ui.button variant="ghost">Voltar</x-ui.button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button size="sm">Compacto</x-ui.button>
                    <x-ui.button size="md">Padrão</x-ui.button>
                    <x-ui.button size="lg">Ação principal</x-ui.button>
                    <x-ui.button disabled>Desabilitado</x-ui.button>
                </div>
                <x-ui.alert variant="warning" title="Por que a primária não é vermelha">
                    <code class="text-xs">primary-600</code> e <code class="text-xs">danger-600</code> têm contraste de apenas
                    <strong class="num">1,43</strong> entre si. Com grafite a separação vai para <strong class="num">2,66</strong>.
                </x-ui.alert>
            </div>
        </x-ui.card>

        {{-- Status --}}
        <x-ui.card title="Status da NF-e" subtitle="Cor e tratamento carregam a informação: em curso usa tonalidade, terminal usa sólido.">
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Enums\Fiscal\NFeStatus::cases() as $status)
                    <x-ui.badge-status :status="$status" />
                @endforeach
            </div>
        </x-ui.card>

        {{-- Formulário --}}
        <x-ui.card title="Controles de formulário" subtitle="Altura de 38px e raio zero, para densidade de admin.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Razão social" for="ds-razao" required>
                    <x-ui.input id="ds-razao" placeholder="RCM do Brasil Ltda" />
                </x-ui.field>
                <x-ui.field label="CNPJ" for="ds-cnpj" hint="Aceita o formato alfanumérico da Receita.">
                    <x-ui.input id="ds-cnpj" numeric placeholder="00.000.000/0000-00" />
                </x-ui.field>
                <x-ui.field label="Natureza da operação" for="ds-nat">
                    <x-ui.select id="ds-nat">
                        <option>Venda de produção própria</option>
                        <option>Devolução de compra</option>
                        <option>Remessa para conserto</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Série" for="ds-serie" error="Informe uma série entre 1 e 999.">
                    <x-ui.input id="ds-serie" numeric value="1" />
                </x-ui.field>
                <x-ui.field label="Informações complementares" for="ds-obs" class="sm:col-span-2">
                    <x-ui.textarea id="ds-obs" rows="3" placeholder="Texto que sai no DANFE..." />
                </x-ui.field>
            </div>
        </x-ui.card>

        {{-- Tabela --}}
        <x-ui.card title="Tabela" subtitle="Coluna numérica sempre em tabular-nums, senão conferir totais fica sofrível.">
            <x-ui.table>
                <thead>
                    <tr class="border-b border-graphite-200">
                        <th class="overline px-2 py-2 text-left text-graphite-500">Número</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Destinatário</th>
                        <th class="overline px-2 py-2 text-left text-graphite-500">Status</th>
                        <th class="overline px-2 py-2 text-right text-graphite-500">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['1.482', 'Metalúrgica Piracicaba', \App\Enums\Fiscal\NFeStatus::Autorizada, '18.740,00'],
                        ['1.481', 'Indústria Leme Ltda', \App\Enums\Fiscal\NFeStatus::Rejeitada, '6.215,50'],
                        ['1.480', 'Usinagem Araras ME', \App\Enums\Fiscal\NFeStatus::Cancelada, '2.980,00'],
                        ['1.479', 'Fundipeças Campinas', \App\Enums\Fiscal\NFeStatus::Contingencia, '41.325,90'],
                    ] as [$numero, $dest, $status, $valor])
                        <tr class="border-b border-graphite-100">
                            <td class="num px-2 py-2">{{ $numero }}</td>
                            <td class="px-2 py-2">{{ $dest }}</td>
                            <td class="px-2 py-2"><x-ui.badge-status :status="$status" /></td>
                            <td class="num px-2 py-2 text-right">{{ $valor }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>

        {{-- Avisos e vazio --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card title="Avisos">
                <div class="grid gap-2">
                    <x-ui.alert variant="info">SEFAZ-SP respondendo normalmente.</x-ui.alert>
                    <x-ui.alert variant="success">NF-e 1.482 autorizada.</x-ui.alert>
                    <x-ui.alert variant="warning">Certificado A1 vence em 15 dias.</x-ui.alert>
                    <x-ui.alert variant="danger" title="Rejeição 539">Duplicidade de NF-e com diferença na chave de acesso.</x-ui.alert>
                </div>
            </x-ui.card>

            <x-ui.card title="Estado vazio">
                <x-ui.empty-state
                    title="Nenhuma nota no período"
                    description="Ajuste o filtro de datas ou emita a primeira NF-e deste emitente.">
                    <x-slot:action>
                        <x-ui.button>Emitir NF-e</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        </div>

        {{-- Tipografia --}}
        <x-ui.card title="Tipografia" subtitle="Barlow Condensed nos títulos, Inter no corpo. Base de 14px.">
            <dl class="grid gap-3">
                @foreach ([
                    ['display', 'Barlow Condensed 700', 'display-title text-3xl'],
                    ['title', 'Barlow Condensed 700', 'display-title text-2xl'],
                    ['subtitle', 'Barlow Condensed 700', 'display-title text-xl'],
                    ['overline', 'Inter 600 · 0.16em', 'overline text-graphite-600'],
                    ['body', 'Inter 400 · base', 'text-sm'],
                    ['caption', 'Inter 400', 'text-xs text-graphite-500'],
                ] as [$token, $config, $classe])
                    <div class="grid items-baseline gap-1 border-b border-graphite-100 pb-2 sm:grid-cols-[150px_1fr] sm:gap-4">
                        <dt class="grid">
                            <span class="text-xs font-semibold text-graphite-900">{{ $token }}</span>
                            <span class="num text-[11px] text-graphite-500">{{ $config }}</span>
                        </dt>
                        <dd class="m-0 {{ $classe }}">Emissão de NF-e</dd>
                    </div>
                @endforeach
                <div class="grid items-baseline gap-1 sm:grid-cols-[150px_1fr] sm:gap-4">
                    <dt class="grid">
                        <span class="text-xs font-semibold text-graphite-900">numeric</span>
                        <span class="num text-[11px] text-graphite-500">Inter · tabular-nums</span>
                    </dt>
                    <dd class="num m-0 text-sm break-all">3526 0925 8474 1800 0155 0010 0000 4417 1099 3620 4418</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.fiscal>
