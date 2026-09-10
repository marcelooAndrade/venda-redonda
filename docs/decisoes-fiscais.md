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

## DF-010 · Quem escreve a regra fiscal é o contador

**Decidido em:** 2026-09-10, por definição de Marcelo.

A responsabilidade tributária é de quem entende de tributação. O sistema não decide tributação: **nenhuma alíquota, CST, CSOSN, CFOP ou cClassTrib existe no código**. Tudo vem de `perfil_fiscal_regras`, preenchida pelo contador na tela `/regras-fiscais`.

Três consequências:

| Consequência | Como ficou |
|---|---|
| **Perfil próprio** | Novo perfil `Contador`. Escreve regra fiscal, consulta notas e exporta o pacote da contabilidade. Não emite, não cancela, não mexe no certificado, não vira o ambiente. O contador costuma ser externo à empresa |
| **Vigência obrigatória** | Toda regra vale a partir de uma data. Ao registrar uma nova para o mesmo âmbito, a anterior **não é apagada**: recebe fim de vigência na véspera. Uma nota emitida em março continua conferindo com a regra de março |
| **Registro de responsabilidade** | `PerfilFiscal` e `PerfilFiscalRegra` são auditáveis. Cada alteração grava autor, horário e IP. O campo `observacao_contador` guarda a fundamentação, por escrito, junto com o autor |

O `TaxCalculator` recebe a data da operação e resolve a regra por ela, nunca por "a regra atual". Isso está coberto por teste: uma nota retroativa a 15/03/2026 usa a alíquota de 12% que valia então, não os 18% de hoje.

## DF-011 · Navegação esconde o que o usuário não pode acessar

**Decidido em:** 2026-09-10

O contador via "Certificado" no menu e levaria 403 ao clicar. Mostrar um caminho que não leva a lugar nenhum é pior do que não mostrar.

Cada item de navegação declara a permissão que exige, e some para quem não a tem. A permissão continua sendo verificada na rota: esconder o item é usabilidade, não segurança.

## DF-012 · Multitenancy e marca dinâmica

**Decidido em:** 2026-09-10, por definição de Marcelo.

### Estrutura

`Tenant` é a empresa cliente que usa o sistema, e fica **acima** do emitente: um tenant pode ter matriz e filiais, cada uma com seu CNPJ. Resolvido pelo host da requisição, com domínio próprio tendo prioridade sobre subdomínio.

| Camada | Escopo |
|---|---|
| `Emitente`, `User` | `tenant_id` direto |
| `Pessoa`, `PerfilFiscal` | Via `emitente_id` |

**Sem tenant resolvido, nada é devolvido.** O padrão é o silêncio: uma falha de resolução vira "não encontrei", nunca "olha o dado do vizinho".

### O escopo alcança a autenticação

O escopo de tenant no `User` não é só de listagem: ele filtra também a busca do provider de autenticação. Sem isso, a credencial de um tenant autentica no host de outro, o que é falha de segurança, não de usabilidade. Coberto por teste.

### Marca dinâmica sem CSS por tenant

Todo utilitário do Tailwind 4 compila para `var(--color-*)`:

```css
.bg-primary-600{background-color:var(--color-primary-600)}
```

Então trocar a marca é sobrescrever os tokens no `<head>`. Uma transportadora verde e preta recebe **o mesmo bundle** que a RCM vermelha e grafite. Não há CSS compilado por tenant, nem classe condicional, e o Flux acompanha porque `zinc` está alinhado ao `graphite`.

A escala de 11 tons é gerada a partir de duas cores da marca:

| Cor | Âncora | Motivo |
|---|---|---|
| Primária | tom **600** | É a cor de ação da marca |
| Neutra | tom **900** | O "preto" de uma marca é o tom mais escuro, não o do meio |

A curva da neutra foi extraída da escala grafite medida no site da RCM, então informar `#1A1A1A` reproduz exatamente a paleta do design system. Isso está travado por teste.

### Contraste é corrigido, não recusado

Marca clara demais não é rejeitada: dizer ao cliente que a marca dele está errada não é opção. A cor é escurecida **o mínimo necessário** até passar em WCAG AA com texto branco, e a original continua disponível nos tons claros da escala.

Medido: `#1B8A4B`, um verde de transportadora que parece seguro, tem contraste **4,39** e não passa. Vira `#1a8347`, com 4,79. Um amarelo `#F5D90A` (1,42) vira `#85760a` (4,57).

## DF-013 · Estoque como razão imutável

**Decidido em:** 2026-09-10

`estoque_movimentos` nunca é editado nem apagado. O model recusa `update` e `delete` na própria camada, com exceção explícita. Correção é sempre movimento novo, de sinal contrário.

O que isso preserva: o Kardex conta o que **de fato aconteceu**. Uma nota cancelada aparece como saída seguida de estorno, e não como uma saída que sumiu. Em fiscalização, a diferença é enorme.

### Custo médio ponderado

Só entrada com custo conhecido move a média:

```
novo = (saldo × custo_atual + entrada × custo_entrada) / (saldo + entrada)
```

Saída, estorno e inventário mantêm a média intacta. **Na saída, o custo médio vigente é gravado no movimento**, porque a média muda depois e o custo daquela saída se perderia.

Conferido em produção com dados reais: 200 a 62,40 mais 100 a 71,20 resulta em custo médio de 65,3333.

### Concorrência

Toda escrita acontece em transação com `lockForUpdate` sobre o saldo. Sem o lock, duas saídas simultâneas leem o mesmo saldo, ambas passam na checagem de disponibilidade, e o estoque fica negativo sem autorização.

### Inventário movimenta a diferença

A contagem física gera um movimento da **diferença apurada**, nunca do total contado. Lançar o total zeraria o histórico e faria o Kardex mentir. Justificativa é obrigatória e fica gravada no movimento.

### Saldo negativo

Bloqueado por padrão, liberável por emitente em `permite_saldo_negativo`. Produto com `controla_estoque = false` não entra no razão: o Kardex reflete só o que tem saldo.

## DF-014 · Defaults de banco precisam existir também em memória

**Decidido em:** 2026-09-10, depois da quarta ocorrência do mesmo bug.

Coluna booleana com default `true` no banco vem `null` num model recém instanciado, e `null` é falsy. Quem lê o atributo antes de reler do banco enxerga `false` e decide errado.

Aconteceu quatro vezes:

| Onde | Consequência |
|---|---|
| `Emitente::$ambiente` | Objeto recém-criado vinha sem ambiente |
| `Tenant::$ativo` | Tenant era tratado como inativo e o escopo global filtrava tudo |
| `Produto::$controla_estoque` | Checagem de saldo era pulada, permitindo estoque negativo sem autorização |
| `Emitente::$ativo` | Idem |

**Remédio:** todo default booleano `true` é declarado também em `$attributes`. Um teste parametrizado em `tests/Feature/DefaultsEmMemoriaTest.php` guarda a classe inteira do bug e quebra se alguém adicionar uma coluna nova sem o default em memória.

## DF-015 · Importação separa registrar de confirmar

**Decidido em:** 2026-09-10

O XML entra em duas etapas, e a separação é deliberada:

| Etapa | O que faz |
|---|---|
| **Importar** | Registra o que chegou. Cria o fornecedor a partir do XML, converte o CFOP, calcula o custo rateado. **Não movimenta estoque** |
| **Conciliar** | Casa os itens do fornecedor com o cadastro de produtos. Parte é automática, o resto é decisão humana |
| **Confirmar** | Aí sim dá entrada no estoque, com o custo já rateado |

Entre registrar e movimentar existe uma conferência humana. Sem essa separação, um XML com item desconhecido criaria produto errado no estoque sem ninguém olhar.

### Só nota autorizada

O parser exige `protNFe` com `cStat` 100. Nota sem protocolo é recusada com mensagem explícita: não é possível dar entrada no estoque com base em documento que a SEFAZ não autorizou.

### A escada da conciliação

A ordem das tentativas importa:

1. **Vínculo salvo** (`produto_fornecedor`) vence tudo, porque foi um humano que decidiu
2. **GTIN**, por ser identificador global
3. O que sobrar fica para o operador resolver na tela

Cada vínculo manual é gravado, então a segunda nota do mesmo fornecedor já entra conciliada. É o que faz a importação deixar de ser trabalho repetitivo.

### Custo de entrada

```
custo_unitario = (produtos + frete + seguro + outros + IPI − desconto) / quantidade
```

Conferido com XML real: (14.250 + 350 + 730) / 500 = **30,66**.

O fator de conversão do fornecedor é aplicado na confirmação: se ele vende em KG e contamos em unidades de meio quilo, a quantidade dobra e o custo por unidade cai pela metade.

### Lote não para no primeiro erro

Um arquivo com problema não interrompe os demais. Em lote de fim de mês, parar no primeiro faria o operador reprocessar tudo. As falhas são relatadas por arquivo, com o motivo.

ZIP é aceito, entradas com `..` no caminho são ignoradas (zip slip), e arquivos que não são XML são pulados em silêncio.

## DF-016 · O `Make` sem schema assume PL_009, anterior à Reforma

**Verificado em:** 2026-09-10, lendo o código da `sped-nfe` v5.2.8.

O construtor do `Make` tem `$schema = 9` como padrão. O render só emite os grupos da Reforma sob `if ($this->schema > 9)`:

```php
public function __construct($schema = null)
{
    $this->schema = 9; //PL_009_V4
```

**Consequência.** `new Make()` sem argumento descarta **em silêncio** os grupos IBS, CBS, IS e `DFeReferenciado`. Não há erro, não há aviso: o XML sai bonito e sem a Reforma. Para um emitente CRT 3, isso é rejeição garantida desde 03/08/2026.

**Decisão.** O schema vem de `config('fiscal.schema')`, padrão `PL_010_V1.30`, e é usado tanto no `Make` quanto no `Tools`. Um teste verifica que os grupos IBS/CBS aparecem no XML quando a regra fiscal os define.

## DF-017 · O referenciamento da devolução é por item

**Verificado em:** 2026-09-10, lendo `TraitTagDetOptions` e o render do `Make`.

`tagDFeReferenciado` grava em `aDFeReferenciado[$item]`, e o render anexa ao `det`, não ao `ide`:

```php
if (!empty($this->aDFeReferenciado[$item])) {
    $this->addTag($det, $this->aDFeReferenciado[$item], 'Falta a tag det!');
}
```

Ou seja: numa devolução, **cada item aponta o item correspondente da nota original**, com chave de acesso e número do item. Não é uma referência única no cabeçalho, como era o antigo `refNFe`.

**Consequência no modelo.** A referência mora em `nota_itens` (`chave_referenciada` e `item_referenciado`), não numa tabela separada ligada à nota. O modelo inicial estava errado e foi corrigido.

## DF-018 · XML sem assinatura nunca valida contra o XSD

**Verificado em:** 2026-09-10

O `nfe_v4.00.xsd` exige o nó `Signature`. Validar o XML recém-montado sempre falha com "Missing child element(s)".

A ordem correta é **montar, assinar, validar**. O teste do builder faz exatamente isso, com um certificado de teste, e é ele que prova que o XML gerado é aceitável pela SEFAZ do ponto de vista estrutural.

## DF-019 · Idempotência na transmissão

**Decidido em:** 2026-09-10

Diante de falha de comunicação, o sistema **não sabe** se a SEFAZ recebeu. Retransmitir às cegas gera duplicidade, queima o número e obriga inutilização formal.

A regra: **antes de qualquer reenvio, consultar a SEFAZ pela chave.**

| Situação | O que o sistema faz |
|---|---|
| Consulta diz que já autorizou | Aplica o resultado. Não reenvia |
| Consulta diz que não consta | Aí sim pode reenviar |
| Consulta também falha | A nota fica em processamento, com aviso explícito. Retransmitir seria apostar |

A chave é calculada e gravada **antes** do envio, justamente para que a consulta seja possível mesmo quando o envio falha.

## DF-020 · O número é consumido só na transmissão

Rascunho abandonado não pode queimar numeração. O número é atribuído no momento do envio, sob lock pessimista.

E a disponibilidade de estoque é conferida **antes** disso: barrar cedo evita queimar número e evita chamar a SEFAZ à toa.

## DF-021 · Nota autorizada não é desfeita por erro nosso

Se a baixa de estoque falhar **depois** da autorização, a nota **continua autorizada**. A SEFAZ já disse que ela existe, e desfazer isso no sistema seria negar um fato registrado no fisco.

A divergência de estoque é registrada em `sefaz_logs` para o operador resolver por ajuste ou inventário. Errar para o lado de refletir a realidade é sempre melhor do que errar para o lado de esconder.

## DF-022 · O prazo de cancelamento é de 24 horas, e o sistema não o estende

Fonte: Ajuste SINIEF 07/05, **cláusula décima segunda**, no texto consolidado do CONFAZ. Verificado em 10/09/2026.

O prazo é "não superior a vinte e quatro horas, contado do momento em que foi concedida a Autorização de Uso da NF-e". O sistema barra antes de chamar a SEFAZ, com a mensagem dizendo o que fazer no lugar: emitir nota de devolução ou de anulação.

O parágrafo único da mesma cláusula permite o cancelamento extemporâneo "a critério de cada unidade federada, em casos excepcionais". O sistema **não** implementa isso: depende de norma estadual que varia por UF, e chutar seria inventar regra fiscal. Quando a RCM precisar, entra como parâmetro do emitente, com a fonte registrada aqui.

## DF-023 · O que a carta de correção não pode corrigir é lista da lei, não escolha nossa

Fonte: Ajuste SINIEF 07/05, **cláusula décima quarta-A, incisos I a V**. Verificado em 10/09/2026.

Os cinco incisos vedam corrigir: variáveis que determinam o valor do imposto, como base de cálculo, alíquota, preço e quantidade (I); dados que mudem o remetente ou o destinatário (II); a data de emissão ou de saída (III); os campos de exportação vinculados à DU-E (IV); e a inclusão ou alteração de parcelas (V). O sistema recusa o texto **antes** de enviar quando reconhece termos desses cinco grupos.

A checagem é por termo e não é infalível: pega o caso óbvio, não o disfarçado. Não substitui o contador, apenas evita o erro comum.

O limite de 20 cartas por nota **não** vem do Ajuste, que só manda consolidar na última carta tudo que já foi retificado (cláusula 14-A, § 4º). Vem do schema: `leiauteCCe_v1.00.xsd` restringe `nSeqEvento` ao padrão `[1-9]|[1][0-9]{0,1}|20`. Limite técnico, não legal, e por isso pode mudar com uma NT sem mudar o Ajuste.

## DF-024 · O DANFE nasce do XML protocolado, nunca do que está no banco

O DANFE é representação gráfica do documento **autorizado**. Gerá-lo a partir dos campos do banco abriria a chance de imprimir algo diferente do que a SEFAZ registrou, se um cadastro mudar depois da autorização.

Por isso o `DanfeService` lê o `nfeProc` guardado, e a nota sem XML protocolado simplesmente não tem DANFE. É também o motivo de a demonstração local não conseguir gerar um: a nota do seed nunca passou pela SEFAZ.
