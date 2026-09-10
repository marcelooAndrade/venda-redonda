# emissor-nfe

Sistema emissor de NF-e modelo 55 (layout 4.00), parametrizável e reutilizável entre empresas clientes.

> Documento vivo. Atualizar ao final de cada fase.

## Situação atual

**Fase 1 concluída.** Design system extraído do site e escrito em `docs/design-system.md`.
Aguardando aprovação da paleta para iniciar a Fase 2 (arquitetura e modelagem).

| Fase | Entrega | Status |
|---|---|---|
| 0 | Análise do APP - transm | Concluída e aprovada |
| 1 | Design system a partir de rcmdobrasil.com.br | Paleta concluída, aguardando aprovação |
| 2 | Arquitetura e modelagem | Pendente |
| 3 | Implementação, módulos 1 a 10 | Pendente |

## Stack

Definida na Fase 2. O prompt do projeto estabelece como alvo: Laravel estável mais recente, PHP 8.3 ou superior, MySQL ou PostgreSQL, Livewire + Alpine.js + Tailwind, `nfephp-org/sped-nfe` + `sped-common` + `sped-da`, filas e scheduler, `spatie/laravel-permission`, Pest, deploy no Laravel Cloud.

Divergências conhecidas em relação ao projeto de referência, a justificar na Fase 2:

- O `app-transm` usa **Blade puro + Alpine**, sem Livewire.
- O `app-transm` usa **helper próprio de permissões**, não o `spatie/laravel-permission`.
- O `app-transm` roda a fila **por cron**, não por worker persistente, por limitação da hospedagem atual. No Laravel Cloud isso não se aplica.

## Variáveis do projeto

| Variável | Valor |
|---|---|
| Caminho do projeto de referência | `/Users/marceloandrade/Projetos/app-transm` (somente leitura) |
| Nome do sistema | `emissor-nfe` (assumido a partir do exemplo do prompt, confirmar) |
| Dados do responsável técnico (`infRespTec`) | **Pendente.** Necessário no Módulo 2 |

## Regras de trabalho

1. Trabalho em fases. Ao final de cada uma, resumo e parada para aprovação.
2. `app-transm` é somente leitura. Nunca alterar, mover ou apagar nada nele.
3. Ambiente padrão da NF-e é sempre homologação (`tpAmb = 2`). Produção só por ativação manual com confirmação explícita na tela.
4. Nenhum segredo no repositório. Certificados, senhas, tokens e XMLs reais ficam fora do Git. `.env.example` sempre atualizado.
5. Commits pequenos e descritivos, um por entrega relevante.
6. Ao final de cada módulo: testes verdes e Laravel Pint limpo antes de avançar.
7. Interface 100% em português do Brasil, sem travessões nos textos. Datas em `America/Sao_Paulo`, números e moeda no formato brasileiro.
8. Termos fiscais oficiais mantêm o nome da SEFAZ em campos e enums (`cfop`, `ncm`, `cst`, `csosn`, `cStat`), para conferência direta com o MOC.
9. Dúvida sobre regra fiscal não se resolve por suposição. Consultar o MOC e as Notas Técnicas vigentes no Portal da NF-e e registrar a decisão em `docs/decisoes-fiscais.md`.
10. Manter este arquivo atualizado.

## Convenções herdadas do projeto de referência

Adotadas por consistência entre os sistemas:

- Tabelas em português, snake_case.
- Classes e métodos em inglês, mensagens ao usuário em português.
- Services com `__construct(private readonly ...)`.
- Erros de negócio como `ValidationException::withMessages()`, ligados ao campo do formulário.
- Migrations agrupadas por módulo, com permissões em arquivo separado.
- Gateways externos atrás de interface, para permitir mock nos testes.

## Decisões registradas

| Data | Decisão | Motivo |
|---|---|---|
| 2026-09-10 | Projeto novo, não fork do `app-transm` | O `app-transm` resolve entrada de notas e é acoplado ao domínio de transportadora. O `emissor-nfe` resolve saída, que não existe lá |
| 2026-09-10 | Reaproveitar o controle de Distribuição DF-e do `app-transm` | Locks por empresa, NSU, cooldown e tratamento de cStat 656 são conhecimento caro e já validado em produção |
| 2026-09-10 | Reescrever o armazenamento do certificado | A origem grava o `.pfx` sem criptografia em repouso e em disco local, incompatível com Laravel Cloud |
| 2026-09-10 | Extrair o parser de XML para classe pura | Na origem ele vive dentro de um God class de 1474 linhas com string de domínio cravada |
| 2026-09-10 | Ação primária usa `graphite-900`, não o vermelho da marca | `primary-600` e `danger-600` têm contraste de apenas 1,43 entre si. Num sistema fiscal, "Transmitir" e "Cancelar" na mesma tela em dois vermelhos parecidos é risco operacional. Com grafite a separação vai a 2,66 |
| 2026-09-10 | Vermelho da marca reservado para identidade e foco | No site ele pesa 22 contra 164 do #1A1A1A. É acento, não preenchimento. Vira a barra de 3px do item ativo na sidebar, herdando o motivo do hero |
| 2026-09-10 | `border-radius: 0` em todo o sistema | Todo controle medido no site tem raio zero. É o traço que mais distingue a identidade |
| 2026-09-10 | `steel` e `ember` derivados das fotos da empresa | Em vez de importar azul e amarelo genéricos, as cores semânticas saem do azul-aço da fachada (195 a 210 graus) e do âmbar do metal fundido (30 a 45 graus) |
| 2026-09-10 | Formulários do admin em superfície clara | O site usa formulário sobre fundo escuro, adequado a 6 campos de contato. Em lançamento de itens de NF-e o dia inteiro, fundo escuro cansa e piora a leitura numérica |
| 2026-09-10 | `tabular-nums` obrigatório em colunas numéricas | Sem isso os dígitos desalinham entre linhas e conferir uma coluna de totais fiscais fica sofrível |

## Documentação

| Arquivo | Conteúdo |
|---|---|
| `docs/analise-app-transm.md` | Análise do projeto de referência (Fase 0) |
| `docs/design-system.md` | Paleta, tipografia e regras de uso (Fase 1) |
| `docs/design/referencia/` | Screenshots de referência, desktop 1440 e mobile 390 |
| `docs/design/extracao-computed-styles.json` | Dados brutos da extração via Playwright |
| `docs/arquitetura.md` | Stack, entidades, fluxos (Fase 2) |
| `docs/decisoes-fiscais.md` | Decisões de regra fiscal com fonte no MOC e nas NTs |
| `docs/deploy-laravel-cloud.md` | Guia de deploy |

## Comandos

Definidos na Fase 2, quando o projeto Laravel for criado.
