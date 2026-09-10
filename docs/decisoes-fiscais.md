# Decisões fiscais

> Registro obrigatório pela regra 9 do projeto: dúvida sobre regra fiscal não se resolve por suposição.
> Cada entrada cita a fonte e a data em que foi verificada.

## DF-001 · Reforma Tributária já é obrigatória para CRT 3

**Verificado em:** 2026-09-10
**Fonte:** NT 2025.002-RTC versão 1.40, publicada em 20/05/2026 no Portal da NF-e.

| Perfil | Homologação | Produção |
|---|---|---|
| CRT 3 (Regime Normal) | desde **01/07/2026** | desde **03/08/2026** |
| CRT 1, 2 e 4 (Simples, Simples com excesso, MEI) | a partir de 01/04/2027 | a partir de 01/04/2027 |

**Consequência para o projeto.** O grupo IBS/CBS não é trabalho futuro. Se o emitente for CRT 3, ele é exigido **hoje**, e uma NF-e sem esses grupos é rejeitada. O `TaxCalculator` e o `NFeBuilder` precisam emitir IBS, CBS e IS desde a primeira versão, e não em uma fase posterior.

**Pendência.** O CRT da RCM do Brasil ainda não foi informado. Ele decide se a Reforma entra como bloqueio de lançamento ou como preparação para abril de 2027. O sistema suporta os dois casos, então isso afeta prioridade, não arquitetura.

## DF-002 · Devolução referencia a nota original em DFeReferenciado

**Verificado em:** 2026-09-10
**Fonte:** NT 2025.002-RTC v1.40, regra de validação **VC02-14**.

Na devolução, o referenciamento da nota original passa a ser feito **exclusivamente** pelo grupo `DFeReferenciado`. Em produção desde **01/09/2026**.

**Consequência.** O Módulo 7, item 7 do escopo original fala em "chave da NF-e referenciada" de forma genérica. A implementação precisa usar `DFeReferenciado`, e não o antigo `refNFe` dentro de `ide`. Isso vale para o critério de aceite 6, a NF-e de devolução referenciando nota importada.

## DF-003 · Biblioteca e versão mínima

**Verificado em:** 2026-09-10
**Fonte:** inspeção do código instalado em `app-transm/vendor` e `processo-comercial/vendor`.

| Pacote | Versão | Suporte a RTC |
|---|---|---|
| `nfephp-org/sped-nfe` v5.2.8 | em `app-transm` | `TraitTagDetIBSCBS`, `TraitTagDetIS`, `TraitTagGALCZFMCBS`, `cClassTrib` presentes |
| `nfephp-org/sped-nfe` v5.2.6 | em `processo-comercial` | RTC parcial, sem `TraitTagGALCZFMCBS` |

**Decisão.** Piso em `^5.2.8`. Nunca usar `dev-master`, como o `app-transm` faz com `sped-da`, porque quebra a reprodutibilidade do build.

**Pendência de verificação no momento do `composer install`.** O maior schema presente nas duas instalações é `PL_010_V1.30`. Não foi possível confirmar daqui se ele corresponde ao leiaute da NT v1.40. Antes de fechar o Módulo 7, conferir a release da `sped-nfe` contra a NT vigente e, se necessário, atualizar o pacote de schemas.

## DF-004 · Ambiente de produção exige liberação explícita

**Verificado em:** 2026-09-10
**Fonte:** regra 3 do projeto.

O `app-transm` resolve isso travando o ambiente em homologação via `Rule::in(['homologacao'])` no Form Request. É seguro, porém rígido demais para um sistema que precisa emitir de verdade.

**Decisão.** O emitente nasce em homologação. A virada para produção é uma ação própria, com permissão exclusiva de Administrador, confirmação digitada na tela, registro em auditoria e verificação prévia de que existe certificado válido e de que o status do serviço da SEFAZ responde. Nunca um campo de formulário comum.

## DF-005 · Tabelas oficiais semeadas e não semeadas

**Verificado em:** 2026-09-10

Fontes oficiais testadas e funcionando, com importadores implementados:

| Tabela | Fonte | Resultado |
|---|---|---|
| UFs | `servicodados.ibge.gov.br/api/v1/localidades/estados` | 27 |
| Municípios | `servicodados.ibge.gov.br/api/v1/localidades/municipios` | 5.571 |
| NCM | Portal Único Siscomex, `portalunico.siscomex.gov.br/classif/api/publico/nomenclatura/download/json` | 15.156, sendo 10.515 válidas para NF-e. Vigência declarada pela própria fonte: "Vigente em 10/09/2026", Resolução Gecex nº 926/2026 |

A URL do Siscomex responde **HTTP 307**, então o cliente precisa seguir redirecionamento. Sem isso, o download volta vazio.

Semeadas por serem pequenas e estáveis: CST de ICMS (11), CSOSN (10), CST de IPI (14), CST de PIS e de COFINS (11 cada) e unidades de medida (20).

**Deliberadamente não semeadas.** A regra 9 proíbe inventar regra fiscal, e estas quatro exigem fonte oficial que ainda não foi confirmada:

| Tabela | Por quê |
|---|---|
| **CFOP** | Cerca de 600 códigos, definidos pelo CONFAZ. Precisa da fonte oficial, não de lista reproduzida de memória |
| **CEST** | Convênio ICMS 142/2018 e alterações. Muda com frequência e é vinculada a NCM |
| **cClassTrib** | Definida pela NT 2025.002-RTC. Como a NT mudou de versão várias vezes até a v1.40, a tabela precisa vir da versão vigente, não de uma anterior |
| **tPag** (meios de pagamento) | Os códigos são conhecidos, mas a Reforma alterou o grupo de pagamento. Precisa de conferência contra o MOC vigente antes de virar dado semeado |

As tabelas existem no schema e estão vazias. Os comandos `fiscal:importar-cfop`, `fiscal:importar-cest` e `fiscal:importar-cclasstrib` serão implementados quando as fontes forem confirmadas, no mesmo padrão dos dois que já funcionam.

## DF-006 · Responsável técnico em configuração global

**Verificado em:** 2026-09-10

O `infRespTec` identifica a software house perante a SEFAZ e é o mesmo em todos os emitentes desta instalação, então vive em `config/fiscal.php`, alimentado pelo `.env`:

```
FISCAL_RESP_TEC_CNPJ=
FISCAL_RESP_TEC_CONTATO=
FISCAL_RESP_TEC_EMAIL=
FISCAL_RESP_TEC_TELEFONE=
```

**Fica vazio de propósito.** Marcelo preenche antes da primeira emissão em produção. Isso não trava o desenvolvimento nem a homologação: a validação só aparece na virada de ambiente, onde `AtivarProducao` recusa e diz exatamente quais campos faltam.

## DF-007 · Certificado com algoritmo antigo é convertido, não recusado

**Verificado em:** 2026-09-10, contra OpenSSL 3.6.3.

Certificados A1 emitidos até alguns anos atrás vêm cifrados em RC2-40, que o OpenSSL 3 desabilitou. A leitura falha com `error:0308010C:digital envelope routines::unsupported`.

O `app-transm` trata isso no mesmo `catch` genérico da senha errada, então o usuário recebe "confira o arquivo A1 e a senha" para um certificado que é perfeitamente válido.

**Decisão.** Detectar a assinatura do erro e converter pelo provider legacy (`openssl pkcs12 -legacy`), reexportando em formato atual. Confirmado funcionando com fixture real. Só quando a conversão não é possível é que aparece mensagem de erro, e ela explica o algoritmo antigo sem culpar a senha.

O registro guarda `convertido_de_legado`, e a tela avisa para pedir o A1 em formato atual na próxima renovação.

## DF-008 · Transportadora não é obrigatória na NF-e

**Verificado em:** 2026-09-10, contra `leiauteNFe_v4.00.xsd` do pacote `PL_010_V1.30`.

O grupo `transp` exige apenas **`modFrete`**:

```xml
<xs:element name="modFrete">              <!-- obrigatório -->
<xs:element name="transporta" minOccurs="0">   <!-- opcional -->
```

Valores de `modFrete`: 0 CIF, 1 FOB, 2 por conta de terceiros, 3 transporte próprio do remetente, 4 transporte próprio do destinatário, 9 sem ocorrência de transporte.

Dentro de `transporta`, até o CNPJ é opcional. **Uma NF-e sem transportadora nenhuma é autorizada normalmente.**

**Situação da RCM.** O dono leva (modFrete 3) ou o cliente retira (modFrete 4). Nenhum dos dois exige identificar transportador.

**Decisão.** Implementar assim mesmo, porque o sistema é reutilizável entre empresas clientes e as demais contratam frete. O custo é baixo: transportadora é uma flag na tabela `pessoas`, não uma tabela nem um módulo. Campos de placa, UF e RNTC só aparecem no formulário quando o papel é marcado.

## DF-009 · CNPJ alfanumérico

**Verificado em:** 2026-09-10
**Fonte:** NT Conjunta CNPJ Alfanumérico (NT 2025.001) e Nota Técnica COCAD/SUARA/RFB nº 49/2024.

A Receita Federal gerou o primeiro CNPJ alfanumérico em **31/07/2026**, então já é realidade em produção. Os dois formatos convivem, e o CNPJ numérico já emitido continua válido.

**Algoritmo.** Módulo 11 com os mesmos pesos de sempre. O que muda é o valor de cada caractere: código ASCII menos 48, então `0` vale 0, `A` vale 17 e `Z` vale 42. As duas últimas posições seguem sempre numéricas.

**Vetor de referência oficial:** `12.ABC.345/01DE-35`. Conferido à mão antes de implementar: DV1 soma 459, resto 8, dígito 3; DV2 soma 424, resto 6, dígito 5.

**Consequência prática.** O documento é sempre `string`, nunca inteiro, em toda a base. Um CNPJ iniciado por zero perderia o zero, e um alfanumérico não caberia num campo numérico.
