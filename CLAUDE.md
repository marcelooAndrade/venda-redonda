# emissor-nfe

Sistema emissor de NF-e modelo 55 (layout 4.00), parametrizável e reutilizável entre empresas clientes.

> Documento vivo. Atualizar ao final de cada fase.

## Situação atual

**Módulo 3 (Cadastros e integrações) concluído.** 147 testes passando, Pint limpo.
Aguardando aprovação para o Módulo 4 (Produtos e Tributação).

| Fase | Entrega | Status |
|---|---|---|
| 0 | Análise do APP - transm | Concluída e aprovada |
| 1 | Design system a partir de rcmdobrasil.com.br | Concluída. Tokens, 12 componentes, layout fiscal e rota /design-system |
| 2 | Arquitetura e modelagem | Concluída, aguardando aprovação |
| 3 | Implementação, módulos 1 a 10 | Módulos 1, 2 e 3 prontos |

## Stack

Definida na Fase 2. Detalhes e justificativas em `docs/arquitetura.md`.

| Camada | Escolha |
|---|---|
| PHP | `^8.3` |
| Framework | Laravel 13.x |
| Banco | MySQL 8 |
| Front | Livewire 3 + Alpine 3 + Tailwind 4 |
| Fiscal | `nfephp-org/sped-nfe ^5.2.8`, `sped-common`, `sped-da` com versão fixa |
| Permissões | `spatie/laravel-permission`, com `emitente_id` como team key |
| Filas | `database`, worker gerenciado |
| Storage | S3 privado obrigatório |
| Testes | Pest 3 |
| Deploy | Laravel Cloud |

Divergências deliberadas em relação ao `app-transm`, ambas justificadas em `docs/arquitetura.md`: **Livewire** no lugar de Blade puro, e **`spatie/laravel-permission`** no lugar do helper próprio de 269 linhas.

## Variáveis do projeto

| Variável | Valor |
|---|---|
| Caminho do projeto de referência | `/Users/marceloandrade/Projetos/app-transm` (somente leitura) |
| Nome do sistema | `emissor-nfe` (assumido a partir do exemplo do prompt, confirmar) |
| Dados do responsável técnico (`infRespTec`) | Em `config/fiscal.php` via `.env`, vazio. `AtivarProducao` recusa a virada enquanto faltar. Ver DF-006 |
| CRT da RCM do Brasil | **Pendente.** Define se IBS/CBS é exigência de hoje ou de abr/2027 |
| Banco de produção no Laravel Cloud | **Pendente.** MySQL 8 ou PostgreSQL |

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
| 2026-09-10 | Livewire 3, contrariando o Blade puro da referência | A tela de emissão recalcula tributos a cada mudança. Como todo total precisa ser recalculado no servidor por segurança fiscal, o Livewire evita manter duas implementações do mesmo cálculo |
| 2026-09-10 | `spatie/laravel-permission` com teams | O acesso é por emitente, não global. O recurso de teams mapeia direto para multiemitente. O helper da referência não cobre isso |
| 2026-09-10 | Uma tabela `pessoas`, não três | Uma metalúrgica pode ser cliente e fornecedora ao mesmo tempo. Três tabelas transformariam esse CNPJ em dois cadastros que divergem no primeiro endereço atualizado |
| 2026-09-10 | IBS/CBS entra no Módulo 4, não em fase posterior | NT 2025.002-RTC v1.40: obrigatório em produção desde 03/08/2026 para CRT 3. Ver DF-001 |
| 2026-09-10 | Estoque como razão imutável | Movimento nunca é editado ou apagado. Correção é movimento de estorno, o que preserva o Kardex como registro fiel |
| 2026-09-10 | Manter o Flux e reposicionar por tokens, em vez de removê-lo | O Flux está em 26 arquivos do starter kit. Como o `@theme` do Tailwind 4 é global, redefinir a paleta, mapear `zinc` para `graphite` e zerar os raios traz as telas de autenticação para a identidade RCM sem refactor |
| 2026-09-10 | `@theme static` em vez de `@theme` | O Tailwind faz tree-shaking dos tokens e só emite as variáveis usadas por utilitários gerados. Um design system precisa expor todos os 66 tokens |
| 2026-09-10 | `EmitenteAtual` reconfere o vínculo a cada resolução | Nunca confiar só na sessão. Sessão adulterada ou vínculo revogado depois da escolha não pode dar acesso |
| 2026-09-10 | Middleware `DefinirEmitenteDoContexto` no grupo web | Permissões são escopadas por emitente. Sem definir o time por requisição, o usuário chega sem papel e leva 403 mesmo sendo administrador. Testes que chamam `setPermissionsTeamId()` na mão mascaram isso |
| 2026-09-10 | Certificado com algoritmo antigo é convertido, não recusado | Ver DF-007 |
| 2026-09-10 | Fixtures de certificado versionadas | Autoassinados, CNPJ fictício, chave descartável. Tornam os testes de certificado reais em vez de mockados |
| 2026-09-10 | Documento sempre `string`, nunca inteiro | CNPJ pode ter letra, e um documento iniciado por zero perderia o zero. Ver DF-009 |
| 2026-09-10 | Município e código IBGE vêm ambos do ViaCEP | A ReceitaWS não devolve IBGE. Usar o município da Receita com o IBGE do ViaCEP arrisca um par inconsistente, que a SEFAZ rejeita |
| 2026-09-10 | `Http::preventStrayRequests()` global no Pest | `Http::fake` com padrão só intercepta o que casa: o resto sai de verdade para a internet. Um teste chegou a bater na API real sem eu perceber |
| 2026-09-10 | Coerência indIEDest x IE validada no cadastro | Contribuinte sem IE, ou isento com IE preenchida, é rejeição na transmissão. Melhor barrar no cadastro |

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

```bash
php artisan test                          # suíte completa (Pest, SQLite em memória)
./vendor/bin/pint                         # formatação
php artisan migrate:fresh                 # recria o schema
php artisan db:seed --class=PerfilSeeder            # 4 perfis, 25 permissões
php artisan db:seed --class=TabelasFiscaisSeeder    # CST, CSOSN, unidades
php artisan fiscal:importar-municipios    # IBGE: 27 UFs e 5.571 municípios
php artisan fiscal:importar-ncm           # Siscomex: tabela NCM vigente
npm run build                             # assets
php artisan serve                         # /design-system mostra a vitrine (só admin)
```

Banco: SQLite local e em teste, MySQL em produção. Não há servidor MySQL nesta máquina.
Migrations são mantidas **portáveis**, sem o DDL específico de MySQL que o `app-transm` usa.
