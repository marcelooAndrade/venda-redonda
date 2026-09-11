# O que o sistema faz

Inventário do venda-redonda: o que está pronto, o que ficou de fora de propósito, o que falta e o que depende de decisão sua.

Este é o documento para responder "o sistema já faz X?". Para **por que** cada coisa é como é, veja `decisoes-fiscais.md` (regra fiscal), `design-system.md` (visual) e o registro de decisões no `CLAUDE.md`.

> Atualizado em 10/09/2026. 431 testes passando, Pint limpo.

## Em uma frase

Emissor de NF-e modelo 55, layout 4.00, multiempresa, com a marca trocando pela URL e **nenhuma alíquota, CST, CSOSN ou CFOP escrita no código**: a regra fiscal é escrita pelo contador, na tela.

## Estado por módulo

| # | Módulo | Situação |
|---|---|---|
| 1 | Base: perfis, vínculo, auditoria, tabelas oficiais | Pronto |
| 2 | Emitente e certificado digital | Pronto |
| 3 | Cadastros e integrações | Pronto |
| 4 | Tributação parametrizável e página do contador | Pronto |
| 5 | Estoque | Pronto |
| 6 | Importação de XML | Pronto, sem distribuição DF-e |
| 7 | Emissão e transmissão | Pronto, sem contingência SVC |
| 8 | Eventos e DANFE | Pronto |
| 9 | Pacote do contador | Pronto |
| 10 | Painel e relatórios | Painel pronto, relatórios não |

## O que já funciona

### Emissão da nota

- Tela de emissão com **recálculo no servidor a cada mudança**. Nenhum total vem do navegador.
- Numeração sob `lockForUpdate`, e o **número só é consumido na transmissão**: rascunho abandonado não queima faixa.
- Disponibilidade de estoque conferida **antes** de transmitir, para não queimar número nem chamar a SEFAZ à toa.
- Montagem do XML com `sped-nfe`, incluindo os grupos **IBS, CBS e IS** da Reforma Tributária.
- **Idempotência**: se o transporte falhar, o sistema consulta pela chave antes de qualquer retentativa. Se a consulta também falhar, a nota fica em processamento com mensagem explícita, nunca reemite.
- Tradução de 18 códigos `cStat` para orientação prática, do tipo "não retransmita".

### Eventos

- **Cancelamento** dentro das 24 horas legais, com estorno de estoque só depois da homologação pela SEFAZ.
- **Carta de correção** com sequência, limite de 20 e recusa prévia do que a lei veda nos cinco incisos.
- **Inutilização** de faixa não usada.
- **DANFE** gerado a partir do XML protocolado, nunca dos campos do banco.

### Regra fiscal escrita pelo contador

- Tela própria, separada do faturamento, com permissão própria.
- Regras com **vigência**: a resolução é sempre pela data da operação, nunca pela "regra atual".
- Cobre ICMS com redução e FCP, CSOSN com crédito do Simples, ST com MVA, IPI, PIS, COFINS, IBS, CBS e IS.
- Regra específica por CRT vence a genérica.

### Estoque

- **Razão imutável**: movimento nunca é editado nem apagado. Correção é movimento de estorno.
- Custo médio ponderado, com a saída gravando o custo médio vigente no momento.
- Inventário move **a diferença**, nunca o total contado, e exige justificativa.
- Kardex por produto, com quem fez e quando.

### Importação de XML de entrada

- Aceita arquivo solto, lote e ZIP. Não para no primeiro erro.
- Defesa contra XXE, limite de 20 MB, e exige `protNFe` com `cStat` 100.
- Cria o fornecedor a partir do próprio XML.
- Conciliação em escada: vínculo salvo, depois GTIN, depois decisão humana.
- Separa **registrar** de **confirmar**: nada entra no estoque sem confirmação.

### Certificado digital A1

- Upload com validação de titular contra o CNPJ do emitente.
- Conversão automática de certificado com algoritmo antigo, em vez de recusar.
- Histórico: nenhum certificado é apagado.
- Alerta de vencimento em 30, 15 e 7 dias.
- **Nunca** loga a senha nem o conteúdo, e não há rota de download.

### Multiempresa e marca

- O **host identifica o tenant**: domínio próprio vence subdomínio.
- Sem tenant resolvido, a consulta não devolve nada. Isolamento por escopo global.
- Cores trocam por **sobrescrita de variável CSS**, sem recompilar.
- Rampa de 11 tons gerada a partir de uma cor, com **ajuste automático de contraste** para WCAG AA.
- **Duas logos, enviadas na tela Marca.** A do sistema aparece na barra lateral; a do DANFE sai impressa na via auxiliar. São separadas porque matriz e filial dividem o sistema e têm CNPJs distintos.
- A logo fica no disco privado e é servida por rota autenticada, resolvida pelo host. Não existe URL pública, nem pedido possível para a logo de outro cliente.
- Trocar a logo apaga a anterior. Remover volta para o quadrado com a inicial.

### Contabilidade

- Pacote do período em ZIP, organizado por pasta: emitidas, canceladas, eventos, cartas de correção, inutilizações e entradas.
- `resumo.csv` com BOM, senão o Excel em português abre os acentos errados.

### Painel

- Abre com **o que exige ação**, e só depois o que aconteceu.
- Nota em processamento encabeça a lista, porque é a única em que reemitir cria duplicidade.
- Faturamento do mês **não soma cancelada**.

### Dados oficiais importados

- 27 UFs e 5.571 municípios do IBGE.
- 15.156 NCM do Siscomex, dos quais 10.515 válidos para NF-e.
- Consulta de CNPJ na ReceitaWS e de CEP no ViaCEP.
- Validação de **CNPJ alfanumérico**, conferida contra o vetor oficial da RFB.

## O que não faz, de propósito

| Item | Por quê |
|---|---|
| Tabelas de **CFOP, CEST, cClassTrib e tPag** vazias | Sem fonte oficial conferida, preencher seria inventar regra fiscal. Ver DF-005 |
| **Cancelamento extemporâneo** | Depende de norma estadual que varia por UF. Ver DF-022 |
| Ação primária **não usa o vermelho da marca** | `primary-600` e `danger-600` têm contraste de 1,43 entre si, e "Transmitir" convive com "Cancelar NF-e" na mesma tela |
| Nota autorizada **não é desfeita** por erro nosso | Se a baixa de estoque falhar depois da autorização, a nota segue autorizada e a divergência é registrada. Ver DF-021 |
| Produção **não liga sozinha** | Só manualmente, nas configurações do emitente, com confirmação na tela |

## O que falta construir

- **Relatórios** (Módulo 10, parte 2)
- **Distribuição DF-e** e manifestação do destinatário: buscar na SEFAZ as notas emitidas contra a RCM
- **Contingência SVC** e o botão de testar comunicação com a SEFAZ
- Telas de **cadastro de emitente**, **usuários** e **naturezas de operação**
- Cálculo de **DIFAL**
- Guia de deploy para Laravel Cloud

## O que depende de você

Nada disso eu consigo resolver sozinho:

1. **Certificado A1 real em homologação**, para provar a emissão contra a SEFAZ. Hoje a transmissão é coberta por testes com gateway simulado, o que prova a lógica, não a integração.
2. **Dados do responsável técnico** (`infRespTec`) no `.env`. São obrigatórios antes de emitir em produção.
3. **O CRT da RCM.** Se for 3, o IBS e o CBS já são exigência em produção desde 03/08/2026.
4. **Confirmar com o contador** que o schema `PL_010_V1.30` corresponde à NT 2025.002-RTC v1.40.

## Onde a rota mudou

As correções que mais mudaram o rumo, e que valem lembrar:

**`logo_path` sumia sem erro no Emitente.** O upload gravava, o `update()` retornava sucesso e a coluna continuava nula: o campo não estava no `$fillable`. É a mesma classe de bug do `tenant_id` abaixo, e a terceira vez que ela aparece. O teste que a pegou vai da tela até o PDF, e falha se alguém tirar o campo da lista de novo.

**Login entre tenants funcionava.** Era buraco de segurança real. O `tenant_id` estava sendo descartado em silêncio porque o atributo `#[Fillable]` do Laravel 13 não o listava. Um usuário de um tenant autenticava no outro.

**IBS e CBS não eram trabalho futuro.** A NT 2025.002-RTC v1.40 tornou obrigatório em produção desde 03/08/2026 para CRT 3. Entraram no Módulo 4, não em fase posterior.

**O `Make` do sped-nfe descartava a Reforma em silêncio.** Sem schema explícito ele assume `PL_009`, anterior à Reforma, e joga fora os grupos IBS, CBS, IS e o `DFeReferenciado` sem erro nenhum.

**403 no navegador com 95 testes verdes.** O `spatie/laravel-permission` com teams precisa de `setPermissionsTeamId` por requisição. Os testes chamavam na mão e mascaravam a falha.

**Um teste falava com a ReceitaWS de verdade.** `Http::fake` com padrão deixa passar o que não casa. Descoberto por causa de `Araras` contra `ARARAS`.

**As vedações da carta de correção cobriam três incisos de cinco.** Ao conferir o texto no CONFAZ para registrar a decisão, minhas próprias citações estavam erradas, e a lista do código deixava passar campo de DU-E e parcela.

**O risco acima de cada rótulo era colisão de nome.** O utilitário `overline` que criei colide com o nativo do Tailwind, `text-decoration-line: overline`. As duas regras somavam.

**A faixa branca nas laterais era o contêiner repetido.** Cada tela trazia o próprio `mx-auto max-w-6xl`.

**O raio zero foi revisto.** Medido no site da RCM e copiado sem ressalva. Numa tela operada o dia inteiro, aresta viva lê como dureza, não como precisão.

**No celular a página rolava na horizontal.** Item de grid nasce com `min-width: auto`, então o `overflow-x-auto` da tabela não continha nada.

## Cobertura de teste

431 testes executados. A contagem por área abaixo é de testes **declarados**, e soma 417: a diferença são testes parametrizados, que expandem em vários na execução.

| Área | Testes | Área | Testes |
|---|---|---|---|
| Emissão | 41 | Tenancy | 36 |
| Tributação | 38 | Emitentes | 19 |
| Importação | 28 | Pessoas | 19 |
| Estoque | 27 | Eventos | 18 |
| Certificado | 22 | Auth | 18 |
| Perfis | 15 | Design System | 11 |
| Integrações | 12 | Settings | 11 |
| Painel | 10 | Produtos | 10 |
| Contador | 6 | Auditoria | 4 |
| Unitários | 66 | Raiz | 6 |

## Os outros documentos

| Arquivo | Conteúdo |
|---|---|
| `CLAUDE.md` | Stack, regras de trabalho e o registro de decisões |
| `README.md` | Como rodar na sua máquina |
| `docs/arquitetura.md` | Modelo de dados e fluxos, com diagramas |
| `docs/decisoes-fiscais.md` | 24 decisões fiscais, cada uma com fonte e data |
| `docs/design-system.md` | A paleta extraída do site da RCM, com o método |
| `docs/analise-app-transm.md` | O que foi aproveitado do sistema da Trans M, e o que não |
