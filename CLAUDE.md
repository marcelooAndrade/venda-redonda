# emissor-nfe

Sistema emissor de NF-e modelo 55 (layout 4.00), parametrizável e reutilizável entre empresas clientes.

> Documento vivo. Atualizar ao final de cada fase.

## Situação atual

**Módulo 10, parte 1: o painel substituiu a tela do starter kit em `/dashboard`.** 417 testes passando, Pint limpo.
Falta a parte 2 do Módulo 10, os relatórios. Seguem pendentes a contingência SVC e a distribuição DF-e.

| Fase | Entrega | Status |
|---|---|---|
| 0 | Análise do APP - transm | Concluída e aprovada |
| 1 | Design system a partir de rcmdobrasil.com.br | Concluída. Tokens, 12 componentes, layout fiscal e rota /design-system |
| 2 | Arquitetura e modelagem | Concluída, aguardando aprovação |
| 3 | Implementação, módulos 1 a 10 | Módulos 1 a 9 prontos. Do 10, falta só os relatórios |

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
| 2026-09-10 | Vedações da CC-e cobrem os cinco incisos, não três | A lista parou nos incisos I a III e deixava passar campo de DU-E e parcela. Ver DF-023 |
| 2026-09-10 | O limite de 20 cartas vem do XSD, não do Ajuste | O Ajuste manda consolidar, sem número. Quem limita é `nSeqEvento` no `leiauteCCe_v1.00.xsd`, então é limite técnico e pode mudar por NT |
| 2026-09-10 | DANFE só a partir do `nfeProc` guardado | Montar do banco arriscaria imprimir algo diferente do que a SEFAZ autorizou, se um cadastro mudar depois. Ver DF-024 |
| 2026-09-10 | O painel abre com pendência, não com faturamento | Quem abre o sistema de manhã precisa saber o que travou ontem antes de saber quanto faturou. Nota em processamento vem primeiro porque reemitir duplica |
| 2026-09-10 | Título da aba usa o nome do tenant | Num sistema que serve várias empresas, "Laravel" na aba entrega que o sistema é de outro. A aba é parte da marca |
| 2026-09-10 | Certificado com algoritmo antigo é convertido, não recusado | Ver DF-007 |
| 2026-09-10 | Fixtures de certificado versionadas | Autoassinados, CNPJ fictício, chave descartável. Tornam os testes de certificado reais em vez de mockados |
| 2026-09-10 | Documento sempre `string`, nunca inteiro | CNPJ pode ter letra, e um documento iniciado por zero perderia o zero. Ver DF-009 |
| 2026-09-10 | Município e código IBGE vêm ambos do ViaCEP | A ReceitaWS não devolve IBGE. Usar o município da Receita com o IBGE do ViaCEP arrisca um par inconsistente, que a SEFAZ rejeita |
| 2026-09-10 | `Http::preventStrayRequests()` global no Pest | `Http::fake` com padrão só intercepta o que casa: o resto sai de verdade para a internet. Um teste chegou a bater na API real sem eu perceber |
| 2026-09-10 | Coerência indIEDest x IE validada no cadastro | Contribuinte sem IE, ou isento com IE preenchida, é rejeição na transmissão. Melhor barrar no cadastro |
| 2026-09-10 | Perfil `Contador` e página `/regras-fiscais` | A regra fiscal é responsabilidade de quem entende de tributação. Nenhuma alíquota, CST ou CFOP vive no código. Ver DF-010 |
| 2026-09-10 | Toda regra fiscal tem vigência | Regra muda com o tempo. A anterior recebe fim de vigência em vez de ser apagada, para nota antiga continuar conferindo |
| 2026-09-10 | `TaxCalculator` resolve pela data da operação | Nunca por "a regra atual". Nota retroativa usa a regra da época, e isso está coberto por teste |
| 2026-09-10 | Navegação filtra por permissão | Ver DF-011 |
| 2026-09-10 | Tenant acima do emitente, resolvido pelo host | Um tenant pode ter matriz e filiais, cada uma com seu CNPJ. Ver DF-012 |
| 2026-09-10 | Sem tenant resolvido, consulta não devolve nada | Falha de resolução vira "não encontrei", nunca vazamento entre clientes |
| 2026-09-10 | Escopo de tenant também no `User` | Não é só listagem: filtra a busca do provider de autenticação. Sem isso, credencial de um tenant autentica no host de outro |
| 2026-09-10 | Marca trocada por sobrescrita de variável CSS | Todo utilitário do Tailwind 4 aponta para `var(--color-*)`. Mesmo bundle para todos os tenants, sem CSS por cliente |
| 2026-09-10 | Neutra ancorada no tom 900, primária no 600 | O "preto" de uma marca é o tom mais escuro, não o do meio. A curva reproduz a escala grafite medida no site |
| 2026-09-10 | Contraste insuficiente é corrigido, não recusado | Dizer ao cliente que a marca dele está errada não é opção. Escurece o mínimo até passar em AA |
| 2026-09-10 | Preparo de teste na `TestCase`, não no `Pest.php` | Parte da suíte são classes PHPUnit do starter kit, que o `beforeEach` do Pest não alcança |
| 2026-09-10 | Movimento de estoque recusa `update` e `delete` no model | Razão imutável não é convenção, é regra imposta pelo código. Ver DF-013 |
| 2026-09-10 | Custo médio vigente gravado no movimento de saída | A média muda depois, e o custo daquela saída se perderia |
| 2026-09-10 | `lockForUpdate` no saldo a cada movimento | Sem ele, duas saídas simultâneas passam na mesma checagem e o estoque fica negativo sem autorização |
| 2026-09-10 | Inventário movimenta a diferença, não o total contado | Lançar o total zeraria o histórico e faria o Kardex mentir |
| 2026-09-10 | Default booleano `true` declarado em `$attributes` | O mesmo bug apareceu quatro vezes. Um teste parametrizado agora guarda a classe inteira. Ver DF-014 |
| 2026-09-10 | Importar, conciliar e confirmar são etapas distintas | Entre registrar o XML e mexer no estoque existe conferência humana. Ver DF-015 |
| 2026-09-10 | `NFeXmlParser` é classe pura, sem banco | No `app-transm` a mesma lógica vivia dentro de um God class de 1474 linhas. Aqui é testável com XML real sem subir a aplicação |
| 2026-09-10 | Vínculo fornecedor-produto salvo a cada conciliação manual | A segunda nota do mesmo fornecedor já entra conciliada |
| 2026-09-10 | Lote de importação não para no primeiro erro | Em fechamento de mês, parar no primeiro faria reprocessar tudo |
| 2026-09-10 | Consultar a chave antes de retransmitir | Depois de timeout não se sabe se a SEFAZ recebeu. Reenviar às cegas queima número. Ver DF-019 |
| 2026-09-10 | Número consumido só na transmissão | Rascunho abandonado não pode queimar numeração. Ver DF-020 |
| 2026-09-10 | Estoque conferido antes de transmitir, tolerado depois | Barrar cedo evita queimar número; depois da autorização a nota é fato e não se desfaz. Ver DF-021 |
| 2026-09-10 | Schema do leiaute vem da configuração | `new Make()` assume PL_009 e descarta os grupos da Reforma em silêncio. Ver DF-016 |
| 2026-09-10 | Helpers de teste no `Pest.php` | Assim cada arquivo de teste roda isoladamente |

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
php artisan fiscal:alertar-certificados   # marcos de 30, 15 e 7 dias
npm run build                             # assets
php artisan emissor:demo --fresh          # dois tenants, quatro perfis, dados de exemplo
php artisan serve --host=0.0.0.0          # o host importa: sem ele *.localhost não resolve
```

Banco: SQLite local e em teste, MySQL em produção. Não há servidor MySQL nesta máquina.
Migrations são mantidas **portáveis**, sem o DDL específico de MySQL que o `app-transm` usa.
