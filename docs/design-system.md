# Design System do venda-redonda

> **Nota de 11/09/2026 — a paleta mudou de marca.**
>
> Este documento descreve a extração feita em rcmdobrasil.com.br, e é essa medição
> que continua calibrando a curva das escalas. O que mudou é a **âncora**: o padrão
> do produto deixou de ser o vermelho e o grafite da RCM e passou a ser o vermelhão
> carimbo `#E4572E` e o grafite-petróleo `#0E1B1F` da Venda Redonda, com Manrope no
> lugar de Barlow Condensed e Inter.
>
> A RCM não sumiu: virou tenant, com a marca dela salva em `tenants.tema`, que é
> exatamente o mecanismo que esta fase construiu. O que estava errado era tratar a
> marca de um cliente como padrão do produto.
>
> As rampas em `@theme static` não são mais escritas à mão: saem de
> `TemaMarca::escalaDe` e `escalaNeutraDe`, as mesmas funções que geram a marca de
> um tenant em runtime, para que o padrão compilado e o padrão do código não possam
> divergir.
>
> Três consequências medidas, e não estimadas:
>
> - **A ação primária continua grafite.** O motivo original era o contraste de 1,43
>   entre primário e perigo. Com o vermelhão ele sobe para 1,77, ainda longe de 3.
>   O grafite separa a 2,69.
> - **O cinza-pedra `#7F8E8B` do briefing não ganhou token.** A rampa do
>   grafite-petróleo entrega `#848b8d` no tom 400: 1,01 de contraste entre os dois,
>   ou seja, a mesma cor. Está coberto por teste.
> - **O vermelhão tem 3,68 contra branco.** Serve para borda, anel de foco, barra e
>   elemento gráfico, que pedem 3:1. Não serve para texto miúdo sobre ele, e por
>   isso o quadrado da inicial do tenant passou a escrever em grafite, com 4,77.



> Fase 1. Extraído de https://rcmdobrasil.com.br/ em 2026-09-10, por renderização real com Playwright (Chromium headless, `getComputedStyle`), com conferência cruzada no código-fonte do site em `/Users/marceloandrade/Projetos/rcmdobrasil`.
> Referências visuais em `docs/design/referencia/`.

## 1. Como a extração foi feita

O site é uma SPA React: o HTML bruto tem 1005 bytes e nenhum conteúdo. Toda a identidade só existe depois da hidratação, então a leitura foi feita com o navegador de verdade.

1. Renderização em Chromium, viewport 1440x900 e 390x844, `deviceScaleFactor: 2`.
2. Rolagem programada da página inteira, para disparar as animações de reveal do framer-motion, seguida de retorno ao topo.
3. `getComputedStyle` em 23 seletores alvo, mais varredura de todos os elementos do DOM.
4. Frequência de cor **ponderada por área visível**, e não por contagem de nós. Uma seção de fundo inteiro pesa mais que um ícone.
5. Estados de `:hover` capturados de verdade, com o mouse sobre o elemento.
6. Cores dominantes das fotografias amostradas em canvas, agrupadas por faixa de matiz.

O build publicado tem os mesmos hashes do build local (`index-BREuhcTA.js`, `index-CZrMcVyN.css`), então o site no ar e o código-fonte são o mesmo artefato.

## 2. O que o site é, em números

### Cores por área visível

| Ordem | Cor | Peso | Papel no site |
|---|---|---|---|
| 1 | `rgb(255,255,255)` #FFFFFF | 298 | Fundo dominante |
| 2 | `rgb(26,26,26)` #1A1A1A | 164 | Fundo escuro e texto |
| 3 | `rgb(245,245,245)` #F5F5F5 | 50 | Fundo alternado de seção |
| 4 | `rgb(42,42,42)` #2A2A2A | 26 | Topo do gradiente de contato |
| 5 | `rgb(250,250,250)` #FAFAFA | 24 | Superfície sutil |
| 6 | **`rgb(232,25,44)` #E8192C** | **22** | **Acento da marca** |
| 7 | `rgba(255,255,255,0.05)` | 17 | Superfície de vidro do formulário |
| 8 | `rgb(229,229,229)` #E5E5E5 | 16 | Bordas |

**A leitura mais importante deste documento:** o vermelho da marca pesa **22**, contra 298 do branco e 164 do preto. Ele não é uma cor de preenchimento. É um acento cirúrgico, usado em botão principal, barra vertical, eyebrow e link de rodapé. Qualquer sistema derivado daqui precisa preservar essa contenção.

### Traços estruturais

| Traço | Medida | Observação |
|---|---|---|
| **Raio de borda** | **0px em todos os controles** | Medido no site. **Não foi mantido no sistema**: ver a revisão da Decisão 3 |
| Alternância de seções | branco, #1A1A1A, #F5F5F5, branco, gradiente #2A2A2A para #1A1A1A | Ritmo claro e escuro |
| Altura mínima de botão | 48px | |
| Altura mínima de input | 44px | |
| Bordas | 1px | Nunca mais grosso |
| Sombra industrial | `0 18px 40px rgba(0,0,0,0.35)` | Uso pontual |
| Header e nav | **Não existem no site** | O admin não tem referência direta de navegação. Derivado do rodapé |

### Tipografia medida

| Elemento | Fonte | Tamanho | Peso | Tracking | Caixa |
|---|---|---|---|---|---|
| h1 hero | Barlow Condensed | 72px / 72px | 700 | normal | ALTA |
| h2 | Barlow Condensed | 48px / 48px | 700 | normal | ALTA |
| h3 | Barlow Condensed | 24px / 32px | 700 | normal | ALTA |
| Eyebrow | Inter | 14px / 20px | 400 | **3.92px** (0.28em) | ALTA |
| Corpo | Inter | 16px / 24px | 400 | normal | normal |
| Botão | Inter | 14px / 20px | 600 | 0.7px (0.05em) | ALTA |
| Link de rodapé | Inter | 14px | 400 | 0.7px | ALTA |

Barlow Condensed com `line-height` igual ao `font-size` nos títulos grandes. Títulos comprimidos e colados, corpo respirado. Ambas as fontes são Google Fonts livres, então não há substituição a fazer.

### Hover medido

| Elemento | Repouso | Hover |
|---|---|---|
| Botão primário | fundo #E8192C | fundo **#B91C1C** |
| Botão secundário | contorno branco, fundo transparente | fundo branco, texto #1A1A1A (inversão) |
| Link de rodapé | texto branco | texto #E8192C |

O #B91C1C medido no hover define, sozinho, o degrau 700 da escala primária.

### Cores derivadas das fotografias

| Imagem | Pixels cromáticos | Matiz dominante | Cor média |
|---|---|---|---|
| `hero-rcm-fachada.png` | 45,2% | **195 a 210 graus (25,8%)** | `#7D97A3` azul-aço |
| `quem-somos-fundicao.png` | 98,7% | 15 a 60 graus | `#9E6C17` âmbar de metal fundido |
| `peca-01`, `peca-02`, `peca-06` | **0%** | nenhum | acromáticas |
| `peca-03` | 1,9% | 180 a 210 graus | `#C1CED0` |

As peças microfundidas são literalmente sem cor. O produto da RCM é aço. Isso justifica um sistema de base neutra, com cor reservada para significado.

## 3. Decisões de design

### Decisão 1: o botão de ação primária não é vermelho

**Problema.** A marca é vermelha e o sistema precisa de uma cor de perigo. Medido, `primary-600` (#E8192C) e `danger-600` (#B3261E) têm contraste de apenas **1,43 entre si**. Num sistema fiscal, "Transmitir" e "Cancelar NF-e" convivem na mesma tela. Dois vermelhos parecidos para ações opostas é risco operacional real, não preciosismo estético.

**Decisão.**

| Papel | Cor | Contraste com branco |
|---|---|---|
| Ação primária (Salvar, Emitir, Transmitir) | `graphite-900` #1A1A1A | 17,40 AAA |
| Ação destrutiva (Cancelar, Inutilizar) | `danger-600` #B3261E | 6,54 AA |
| Marca (logo, nav ativa, faixa, foco) | `primary-600` #E8192C | reservado |

A separação entre ação primária e destrutiva sobe de **1,43 para 2,66**, e nenhum botão usa o vermelho da marca.

**Por que isso é fiel, e não uma traição.** O site usa vermelho com peso 22 contra 164 do #1A1A1A. Espalhar vermelho por todos os botões de um admin denso multiplicaria a presença dele em uma ordem de grandeza e destruiria exatamente a contenção que dá força à marca. O preto industrial já é a cor de ação dominante do site.

### Decisão 2: o vermelho vira o indicador de navegação

O hero traz `absolute left-4 top-1/2 h-48 w-1 bg-rcm-red`, uma barra vermelha vertical de 4px. A seção de contato repete o motivo com `border-l-2 border-rcm-red`. É a assinatura gráfica da marca.

No admin, essa barra vira o **indicador de item ativo na sidebar**: 3px vermelhos na borda esquerda do item selecionado. A marca aparece exatamente onde a identidade original a colocava, sem competir com nenhuma ação.

### Decisão 3: raio zero era lei, e foi revista

**Como estava.** Todo controle medido no site tem `border-radius: 0px`, e a primeira versão do sistema copiou isso em botões, inputs, cards, badges e tabelas, com o argumento de que era o traço que mais distinguia a identidade de um admin genérico.

**Por que mudou.** Revisto em 10/09/2026, depois de ver o sistema montado. O argumento estava certo sobre a marca e errado sobre o contexto. Num site de seis campos, a aresta viva lê como precisão; numa tela operada o dia inteiro, com dezenas de campos e linhas de tabela, lê como dureza. A palavra do cliente ao ver as telas foi "quadradão".

**Como ficou.** Escala contida, pequena de propósito:

| Token | Valor | Onde aparece |
|---|---|---|
| `--radius-xs` | 2px | Detalhes |
| `--radius-sm` | 3px | Barra do item ativo |
| `--radius-md` | 5px | Botões, inputs, select, textarea, alertas |
| `--radius-lg` | 7px | Cards, estado vazio |
| `--radius-xl` a `4xl` | 10 a 28px | Reservados |

Pastilha de status é a exceção: usa `rounded-full`, porque chip de estado lê melhor arredondado e o ponto quadrado dentro dela parecia defeito.

**O que segurou a identidade.** O caráter industrial não vinha do raio, e sim da tipografia condensada em caixa alta, do grafite quase preto e da barra vermelha de 3px no item ativo. Esses três ficaram intactos, e a marca continua reconhecível com as arestas suavizadas.

### Decisão 4: as duas cores derivadas das fotos ganham papel semântico

Nada foi inventado. `steel` sai do azul-aço da fachada e vira a cor informativa. `ember` sai do metal fundido e vira o alerta. Isso amarra as cores semânticas à própria fotografia da empresa, em vez de importar um azul e um amarelo genéricos.

### Decisão 5: o formulário do admin é claro, não escuro

O site usa formulário sobre superfície escura, com `rgba(255,255,255,0.05)` e bordas translúcidas. Bonito para uma seção de contato de seis campos. Para um operador que passa o expediente lançando itens de NF-e, fundo escuro em formulário denso cansa e piora a leitura de números.

O admin usa **superfície clara para conteúdo e formulários**, e reserva o escuro para **sidebar, topbar e rodapé**, que é justamente como o site distribui claro e escuro entre suas seções.

## 4. Paleta

Todos os valores validados para WCAG AA. **Zero falhas** em 36 combinações testadas.

### primary, vermelho RCM

Âncoras medidas: `600` é a cor da marca, `700` é o hover real do site.

| Token | Hex | Uso |
|---|---|---|
| primary-50 | `#FEF2F3` | Fundo de destaque muito sutil |
| primary-100 | `#FCE0E3` | Fundo de badge |
| primary-200 | `#F9C3C9` | Borda de destaque |
| primary-300 | `#F49AA4` | |
| primary-400 | `#EE6575` | Texto de marca sobre fundo escuro (5,60 AA) |
| primary-500 | `#EB3A4F` | Anel de foco sobre escuro |
| **primary-600** | **`#E8192C`** | **Cor da marca. Logo, barra de nav ativa, anel de foco** |
| primary-700 | `#B91C1C` | Hover da marca, medido no site |
| primary-800 | `#8F1519` | Texto sobre primary-100 (7,39 AAA) |
| primary-900 | `#6B1214` | |
| primary-950 | `#3D0809` | |

### graphite, neutro industrial

Âncoras medidas: `800` é o rcm-charcoal, `900` é o rcm-black. Cinzas puros, sem tingimento, como no site.

| Token | Hex | Uso |
|---|---|---|
| graphite-50 | `#F6F6F6` | Fundo da aplicação |
| graphite-100 | `#E8E8E8` | Fundo de badge neutro, linha zebrada |
| graphite-200 | `#D1D1D1` | Bordas e divisores |
| graphite-300 | `#B0B0B0` | Texto desabilitado sobre escuro (8,03 AAA) |
| graphite-400 | `#8A8A8A` | Placeholder |
| graphite-500 | `#6D6D6D` | Texto terciário (5,17 AA) |
| graphite-600 | `#555555` | Texto secundário (7,46 AAA) |
| graphite-700 | `#3E3E3E` | Hover da ação primária |
| **graphite-800** | **`#2A2A2A`** | **rcm-charcoal. Topbar, badge sólido** |
| **graphite-900** | **`#1A1A1A`** | **rcm-black. Sidebar, texto principal, ação primária** |
| graphite-950 | `#0F0F0F` | Fundo mais profundo |

### steel, azul-aço da fachada

Derivado do matiz 195 a 210 graus, 25,8% dos pixels do hero. Papel informativo.

| Token | Hex | Uso |
|---|---|---|
| steel-50 | `#F2F6F8` | Fundo informativo |
| steel-100 | `#E1ECF0` | Badge "em processamento" |
| steel-200 | `#C4D9E1` | |
| steel-300 | `#9FBFCB` | Texto info sobre escuro (8,94 AAA) |
| steel-400 | `#7D97A3` | Cor média extraída da foto |
| steel-500 | `#5F7F8D` | |
| steel-600 | `#4B6875` | Botão informativo (5,94 AA) |
| steel-700 | `#3D5561` | Link e texto info sobre claro (7,86 AAA) |
| steel-800 | `#34474F` | Texto sobre steel-100 (8,08 AAA) |
| steel-900 | `#2D3C43` | |
| steel-950 | `#1A252A` | |

### ember, âmbar do metal fundido

Derivado do matiz 30 a 45 graus da foto de fundição. Papel de alerta.

| Token | Hex | Uso |
|---|---|---|
| ember-50 | `#FDF8ED` | Fundo de alerta |
| ember-100 | `#F9EDD0` | Badge de contingência |
| ember-200 | `#F2D89C` | |
| ember-300 | `#E8BC5F` | Alerta sobre escuro (9,77 AAA) |
| ember-400 | `#DDA132` | **Faixa de ambiente HOMOLOGAÇÃO** (7,64 AAA com texto grafite) |
| ember-500 | `#C4861C` | |
| ember-600 | `#9E6C17` | Cor média extraída da foto (4,55 AA com branco) |
| ember-700 | `#7C5314` | Texto de alerta sobre claro (6,76 AA) |
| ember-800 | `#5F4014` | Texto sobre ember-100 (8,10 AAA) |
| ember-900 | `#4A3212` | |
| ember-950 | `#2A1C09` | |

### success

| Token | Hex | Uso |
|---|---|---|
| success-50 | `#F0FAF4` | |
| success-100 | `#DBF2E3` | Badge "autorizada" |
| success-200 | `#B9E5C9` | |
| success-300 | `#88D0A5` | Sobre escuro (9,62 AAA) |
| success-400 | `#4FB47B` | |
| success-500 | `#2C9760` | |
| success-600 | `#1E7A4C` | Botão de confirmação (5,33 AA) |
| success-700 | `#1A613E` | **Faixa de ambiente PRODUÇÃO** (7,44 AAA) |
| success-800 | `#174D33` | Texto sobre success-100 (8,31 AAA) |
| success-900 | `#14402B` | |
| success-950 | `#0A2417` | |

### danger

Deliberadamente mais escuro e menos vivo que o vermelho da marca, para não se confundir com ele.

| Token | Hex | Uso |
|---|---|---|
| danger-50 | `#FDF3F2` | |
| danger-100 | `#FAE3E0` | Badge "rejeitada" |
| danger-200 | `#F5C9C4` | |
| danger-300 | `#EBA49C` | |
| danger-400 | `#DC7266` | |
| danger-500 | `#C74A3C` | |
| danger-600 | `#B3261E` | **Botão destrutivo** (6,54 AA) |
| danger-700 | `#8F1E17` | Hover destrutivo (8,88 AAA) |
| danger-800 | `#741C16` | Texto sobre danger-100, badge "denegada" sólido |
| danger-900 | `#611B16` | |
| danger-950 | `#360B08` | |

## 5. Status da NF-e

Oito estados, distinguidos por **cor e também por tratamento**. Estados terminais usam preenchimento sólido, estados em curso usam tonalidade. Isso garante leitura mesmo em monocromia ou para daltônicos.

| Status | Texto | Fundo | Tratamento | Contraste |
|---|---|---|---|---|
| Rascunho | `graphite-800` | `graphite-100` | tonalidade | 11,71 AAA |
| Em processamento | `steel-800` | `steel-100` | tonalidade, com pulso | 8,08 AAA |
| Autorizada | `success-800` | `success-100` | tonalidade | 8,31 AAA |
| Rejeitada | `danger-800` | `danger-100` | tonalidade | 8,92 AAA |
| Denegada | `#FFFFFF` | `danger-800` | **sólido** | 10,94 AAA |
| Cancelada | `#FFFFFF` | `graphite-800` | **sólido** | 14,35 AAA |
| Inutilizada | `graphite-600` | `graphite-50` | tonalidade, borda tracejada | 6,90 AA |
| Contingência | `ember-800` | `ember-100` | tonalidade, com ícone de alerta | 8,10 AAA |

Rejeitada é recuperável, então usa tonalidade. Denegada e Cancelada são terminais, então usam sólido. Inutilizada recebe borda tracejada porque nada chegou a existir.

## 6. Tipografia do sistema

Mesmas duas fontes do site. Escala redesenhada para densidade de admin.

| Token | Fonte | Tamanho / Altura | Peso | Tracking | Uso |
|---|---|---|---|---|---|
| `display` | Barlow Condensed | 32px / 32px | 700 | normal | Título de página, CAIXA ALTA |
| `title` | Barlow Condensed | 24px / 28px | 700 | normal | Título de seção, CAIXA ALTA |
| `subtitle` | Barlow Condensed | 20px / 24px | 700 | normal | Título de card, CAIXA ALTA |
| `heading` | Barlow Condensed | 16px / 20px | 600 | 0.02em | Cabeçalho de bloco, CAIXA ALTA |
| `overline` | Inter | 11px / 16px | 600 | **0.16em** | Eyebrow e rótulo de grupo, CAIXA ALTA |
| `body` | Inter | 14px / 20px | 400 | normal | **Base do sistema** |
| `body-strong` | Inter | 14px / 20px | 600 | normal | Ênfase |
| `small` | Inter | 13px / 18px | 400 | normal | Texto auxiliar |
| `caption` | Inter | 12px / 16px | 400 | normal | Legenda e ajuda |
| `button` | Inter | 13px / 16px | 600 | 0.05em | Botão, CAIXA ALTA |
| `numeric` | Inter | 14px / 20px | 400 | normal | **`font-variant-numeric: tabular-nums`** |

O `overline` reduz o tracking de 0.28em do site para 0.16em. A 11px, 0.28em quebra a leitura.

**`numeric` é obrigatório** em toda coluna de valor, quantidade, alíquota, chave de acesso, CNPJ e número de nota. Sem `tabular-nums` os dígitos dançam entre linhas e a conferência de uma coluna de totais fica sofrível. É um requisito funcional em sistema fiscal, não um detalhe.

## 7. Densidade

O site respira porque é institucional, com `py-20` e `py-24`, ou seja, 80 a 96px por seção. Um admin com tabela de itens de nota precisa do oposto.

| Medida | Site | venda-redonda | Motivo |
|---|---|---|---|
| Base tipográfica | 16px | **14px** | Mais linhas úteis por tela |
| Altura de botão | 48px | 40px padrão, 32px compacto, **48px na ação principal** | A ação principal mantém a presença do site |
| Altura de input | 44px | **38px** | |
| Espaço vertical de seção | 80 a 96px | **24px** | |
| Altura de linha de tabela | n/a | **40px**, 32px no modo compacto | |
| Grade de espaçamento | n/a | 4px | 4, 8, 12, 16, 24, 32, 48 |
| Largura máxima de formulário | n/a | 720px | Formulário longo em coluna única cansa menos |

## 8. Elevação e bordas

O sistema é definido por **borda**, não por sombra. É o que o site faz: 1px em tudo, sombra só no card de peças.

| Token | Valor | Uso |
|---|---|---|
| `border` | 1px `graphite-200` | Padrão, superfície clara |
| `border-dark` | 1px `rgba(255,255,255,0.10)` | Sobre sidebar, medido no rodapé do site |
| `border-input` | 1px `graphite-300` | Campo em repouso |
| `border-strong` | 1px `graphite-900` | Ênfase |
| `shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Dropdown, medido no site |
| `shadow-industrial` | `0 18px 40px rgba(0,0,0,0.35)` | Modal, herdado do site |
| `radius` | `2` a `28px` | `md` (5px) em controle, `lg` (7px) em card, `full` em pastilha de status |
| `focus` | `outline: 2px solid primary-600; outline-offset: 2px` | Marca no foco, como no site |

O site já usa `focus-visible:outline-rcm-red` em todos os interativos. O anel de foco vermelho é herança direta, e é onde a marca aparece com mais frequência no admin.

## 9. Layout base

```
┌──────────────────────────────────────────────────────────────┐
│ FAIXA DE AMBIENTE  ember-400, só em homologação, 28px        │
├────────────┬─────────────────────────────────────────────────┤
│            │ TOPBAR  graphite-800, 56px                      │
│ SIDEBAR    │ seletor de emitente | usuário | status SEFAZ    │
│ graphite   ├─────────────────────────────────────────────────┤
│ -900       │                                                 │
│ 240px      │ CONTEÚDO  graphite-50                           │
│ 64px       │                                                 │
│ recolhida  │ cards e tabelas em branco, borda graphite-200   │
│            │                                                 │
│ item ativo:│                                                 │
│ barra      │                                                 │
│ vermelha   │                                                 │
│ 3px à      │                                                 │
│ esquerda   │                                                 │
└────────────┴─────────────────────────────────────────────────┘
```

A faixa de ambiente ocupa a largura toda e só existe em homologação, em `ember-400` com texto `graphite-900`, 7,64 AAA. Em produção ela some, e o estado passa a ser indicado por um ponto `success-700` discreto na topbar. Ambiente errado é a falha mais cara de um emissor fiscal, então o aviso é impossível de ignorar sem virar ruído permanente.

## 10. Pendências desta fase

Os demais entregáveis da Fase 1 (tokens no Tailwind via `@theme`, componentes Blade em `resources/views/components/ui/`, layout base e rota `/design-system`) dependem de um projeto Laravel que ainda não existe. O prompt cria esse projeto na Fase 2.

Proposta: aprovar a paleta agora, e implementar tokens, componentes e a rota `/design-system` logo após o scaffold da Fase 2. Nenhum trabalho se perde, e a ordem passa a fazer sentido.

> **Nota posterior, 10/09/2026.** A rota `/design-system` citada na seção 10 foi construída, serviu de vitrine durante a implementação e depois foi retirada: era andaime, não tela de operação. Os tokens do `@theme` e os componentes em `resources/views/components/ui/` continuam, e são eles que sustentam tudo o que este documento descreve.
