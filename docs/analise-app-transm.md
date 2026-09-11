# Análise do projeto APP - transm

> Fase 0 do projeto `venda-redonda`.
> Origem analisada: `/Users/marceloandrade/Projetos/app-transm` (somente leitura, nada foi alterado).
> Arquivos `.env`, certificados e tokens não foram abertos, conforme a regra 1 do `CONTEXT.md`.
> Data da análise: 2026-09-10.

## 1. Stack

| Camada | O que é usado |
|---|---|
| PHP | `^8.2` no `composer.json`. O CLI da máquina roda 8.4.19 |
| Framework | Laravel `^12.63` |
| Banco | MySQL ou MariaDB. O `config/database.php` traz `sqlite` como default do skeleton, mas as migrations usam `ALTER TABLE ... MODIFY COLUMN ... ENUM(...)`, sintaxe exclusiva de MySQL/MariaDB |
| Front-end | Blade + Alpine.js 3 + Tailwind CSS 3.4.17, empacotado por Vite 6.2.4. **Não usa Livewire nem Inertia** |
| Autenticação | Laravel Breeze 2.3 |
| Permissões | Helper próprio, `app/Helpers/PermissionHelper.php` (269 linhas), carregado via `autoload.files`. **Não usa `spatie/laravel-permission`** |
| Filas | Driver `database`. **Não há worker persistente** |
| Agendamento | `routes/console.php`, com `Schedule::` |
| Storage | Disco default `local`, apontando para `storage_path('app/private')`. Discos `public` e `s3` configurados. `aws/aws-sdk-php` e `league/flysystem-aws-s3-v3` presentes |
| Timezone | `America/Sao_Paulo` (`config/app.php`) |
| Testes | Pest 3.8 + `pest-plugin-laravel` 3.2, Mockery, Collision |
| Lint | Laravel Pint 1.13 |
| PDF | `barryvdh/laravel-dompdf`, `setasign/fpdf`, `setasign/fpdi`, `smalot/pdfparser` |
| Extras | `openai-php/laravel`, `webklex/php-imap`, `phpoffice/phpspreadsheet`, `greenlion/php-sql-parser`, `symfony/dom-crawler` |

### Detalhe importante sobre filas

O scheduler drena a fila por cron, não por daemon. O próprio código documenta o motivo:

```php
// Processa a fila ... este hosting não roda um worker persistente (`queue:work` daemon),
// então reaproveita o mesmo cron do schedule:run pra drenar a fila em lotes curtos.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()->withoutOverlapping()->runInBackground();
```

Isso é uma adaptação a hospedagem limitada. No Laravel Cloud não precisamos herdar esse padrão, porque lá existe worker gerenciado.

Agendamentos fiscais existentes: `fiscal:dfe-sincronizar` a cada 30 minutos, com `withoutOverlapping()` e log dedicado.

## 2. Biblioteca fiscal

Versões reais, lidas do `composer.lock`:

| Pacote | Versão travada |
|---|---|
| `nfephp-org/sped-nfe` | **v5.2.8** |
| `nfephp-org/sped-common` | v5.1.17 |
| `nfephp-org/sped-cte` | v5.0.1 |
| `nfephp-org/sped-mdfe` | V4.2.0 |
| `nfephp-org/sped-gtin` | v1.1.2 |
| `nfephp-org/sped-da` | **dev-master** |

`sped-da` sem versão fixa é um risco de build não reprodutível. No `venda-redonda` isso precisa ser travado.

### Como o `Tools` é construído

`app/Services/Fiscal/NfephpToolsFactory.php` centraliza a criação de `Tools` para CT-e, MDF-e e NF-e, injetando o certificado. Pontos a observar:

- `tpAmb` derivado de `$empresa->ambiente` (`producao` vira 1, qualquer outro vira 2).
- O método `nfe()` chama `$tools->model(55)`, mas usa `$empresa->ambienteDistribuicaoNfe()`, ou seja, é montado **para Distribuição DF-e**, não para emissão.
- `schemes` fica vazio para CT-e e MDF-e, e recebe `PL_010_V1.30` no caso da NF-e.
- `tokenIBPT`, `CSC` e `CSCid` estão vazios (não há NFC-e nem cálculo de tributos aproximados).

## 3. Certificado digital

Arquivos: `app/Services/Fiscal/FiscalCertificateService.php`, `app/Http/Requests/Fiscal/UploadFiscalCertificateRequest.php`, `app/Models/FiscalEmpresaConfiguracao.php`, `app/Casts/SafeEncrypted.php`.

### 3.1 Upload

Validação de entrada enxuta:

```php
'certificado' => ['required', 'file', 'max:5120', 'extensions:pfx,p12'],
'senha' => ['required', 'string', 'max:255'],
```

### 3.2 Validação

O service faz, nesta ordem:

1. `Certificate::readPfx($content, $password)` do `nfephp-org/sped-common`.
2. Compara o CNPJ do titular com o CNPJ da empresa, apenas dígitos. Diferente, bloqueia.
3. `$certificate->isExpired()`. Vencido, bloqueia.
4. Extrai `getCompanyName()`, `getValidFrom()`, `getValidTo()` e roda `openssl_x509_parse()` para pegar o serial.
5. Grava `fingerprint` como `hash('sha256', (string) $certificate)`.

Todas as mensagens de erro são `ValidationException::withMessages()` em português.

### 3.3 Armazenamento

- Caminho: `fiscal/certificados/{uuid}.pfx`, via `Storage::put()` no **disco default**.
- O conteúdo do `.pfx` é gravado **cru, sem criptografia em repouso**.
- A senha é gravada no banco com o cast `SafeEncrypted` (`Crypt::encrypt`), portanto **criptografada**.
- `$hidden` protege `certificado_path`, `certificado_senha`, `efrete_integrador_hash` e `efrete_senha` na serialização.
- Não existe rota de download do certificado.
- Ao trocar o certificado, o anterior é apagado com `Storage::delete($oldPath)`.

### 3.4 Carregamento para assinar

`FiscalCertificateService::certificate()` valida `possuiCertificadoValido()`, confere a existência do arquivo e reabre o PFX com a senha descriptografada. Erro genérico se falhar.

### 3.5 Tratamento de erros

| Situação | Comportamento atual |
|---|---|
| Senha errada | Mensagem "Não foi possível ler o certificado. Confira o arquivo A1 e a senha." |
| CNPJ divergente | Mensagem específica e clara |
| Certificado vencido no upload | Mensagem específica e clara |
| Certificado vencido depois | `possuiCertificadoValido()` bloqueia na hora de transmitir |
| Arquivo sumiu do storage | Mensagem específica |
| **Certificado legado no OpenSSL 3** | **Não tratado.** Cai no mesmo `catch (\Throwable)` genérico da senha errada |
| **Aviso de vencimento próximo** | **Não existe.** Nenhum job, nenhuma notificação |

### 3.6 O cast `SafeEncrypted`

Variante do cast nativo `encrypted` que devolve `null` em vez de estourar `DecryptException` quando a `APP_KEY` mudou. Documentado no código como suporte a dado importado de outro ambiente.

Para a senha do certificado esse comportamento é perigoso: uma rotação de `APP_KEY` transforma a senha em `null` silenciosamente, e o usuário recebe "O certificado armazenado não pôde ser aberto", sem nenhuma pista da causa real.

## 4. Importação de notas fiscais

Arquivos: `app/Services/NFeImporter.php` (1474 linhas), `app/Services/Fiscal/SefazDfeImportService.php`, `NfephpSefazDfeGateway.php`, `SefazDfeGateway.php`, `SefazDfeResponseParser.php`, `NfeXmlReader.php`, `FiscalProcessNfeXmlSyncService.php`, `app/Jobs/RetryFiscalProcessNfeXmlJob.php`, comandos `ImportNfeData` e `ImportarNfeAutomaticamente`, controller `SincronizarNotasFiscaisController`.

### 4.1 Formas de entrada

| Forma | Existe |
|---|---|
| XML avulso, por conteúdo string | Sim, `NFeImporter::importFromXml()` |
| Distribuição DF-e por NSU | Sim, `SefazDfeImportService::sincronizar()` |
| Busca por chave de 44 dígitos | Sim, `SefazDfeImportService::importarPorChave()` |
| Integração NSDocs (terceiro) | Sim, com merge de dados sobre o XML |
| **Upload de múltiplos XMLs ou ZIP** | **Não** |
| **Manifestação do destinatário** | **Não.** Nenhuma referência a `sefazManifesta` ou aos eventos 210200/210210/210220/210240 |

### 4.2 Parse do XML

`NFeImporter::parseNFeXml()` é a peça mais aproveitável do projeto. Qualidades:

- Limite de 20 MB.
- Rejeita `<!DOCTYPE`, defesa contra XXE.
- `simplexml_load_string` com `LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA`, com `libxml_use_internal_errors` restaurado em `finally`.
- Quatro estratégias em cascata para localizar `infNFe`: acesso direto, `children()` com namespace, XPath `//nfe:infNFe` e fallback via `DOMDocument`.
- Detecta CT-e e desvia para `parseCteXml()`.
- Extrai cabeçalho, emitente, destinatário, totais e itens, mais ICMS, IPI, PIS e COFINS por item, em métodos dedicados.
- Normaliza número no formato brasileiro e datas.

Ponto de atenção: aceita `NFe` **sem** `protNFe`, ou seja, não exige nota autorizada.

### 4.3 Duplicidade

Dois níveis:

1. No DF-e, `FiscalDfeDocumento::firstOrCreate()` com chave composta empresa + ambiente + NSU, mais verificação de integridade por SHA-256:

```php
if (! hash_equals($stored->xml_sha256, $hash)) {
    throw new RuntimeException("A SEFAZ retornou conteúdo divergente para o NSU {$document['nsu']}.");
}
```

2. No processamento, `processado_em !== null` faz o documento ser ignorado, salvo quando `$force = true`.

O arquivo é gravado em `fiscal/dfe/{empresa}/{ambiente}/{nsu}-{sha256}.xml`, então o hash também evita colisão no disco.

### 4.4 Fornecedor e itens

É aqui que o reuso acaba. O `NFeImporter` não faz entrada de estoque: ele alimenta o domínio da transportadora.

- Cliente é buscado ou criado em `Pessoa`, por CNPJ (`findClienteByCnpj`, `resolveClienteIdByCnpj`, `fillMissingCustomerData`).
- Os itens viram registros de `PedidoLogistica` (`persistNotaNosPedidos`, `buildPedidoFieldsFromNota`, `matchItemForPedido`).
- **Não existe** vínculo entre código do produto do fornecedor e produto interno.
- **Não existe** conversão de unidade.
- **Não existe** tabela de produtos, saldo ou custo médio.
- Há string de domínio cravada dentro do parser: `'status' => 'Faturado na Cerâmica'`.

### 4.5 Controle de consumo da SEFAZ

A melhor parte do módulo, e integralmente aproveitável:

- `Cache::lock()` por empresa e ambiente, com `block(5)` e TTL de 300s.
- Limite de lotes por sincronização, entre 1 e 20.
- Tratamento explícito dos status: `138` documento localizado, `137` nenhum documento, `656` consumo indevido com bloqueio de 1 hora gravado em `proxima_consulta_em`.
- Cooldown de 5 minutos por chave consultada.
- Estado da sincronização persistido em `FiscalDfeSincronizacao` (último NSU, último status, último motivo, próxima consulta).
- Duas fases separadas: `storeDocument()` grava, `processDocument()` interpreta. Falha no processamento não perde o XML, só grava `erro_processamento`.
- `SefazDfeGateway` é interface, com `NfephpSefazDfeGateway` como implementação, o que torna os testes fáceis.

## 5. Convenções de código

### Pastas

```
app/Casts/                  SafeEncrypted
app/Enums/
app/Helpers/                PermissionHelper.php (autoload.files)
app/Http/Controllers/Fiscal/
app/Http/Requests/Fiscal/
app/Jobs/
app/Models/                 flat, prefixo Fiscal* nos models fiscais
app/Services/               services de domínio geral (NFeImporter mora aqui)
app/Services/Fiscal/        42 arquivos, o núcleo fiscal
app/Support/<Dominio>/      Whatsapp, Pix, Ads, Ai, Dashboard etc.
resources/views/<dominio>/  Blade por domínio
tests/Unit/ e tests/Feature/Fiscal/
```

### Nomes

- Tabelas em português, snake_case, prefixo `fiscal_`: `fiscal_empresa_configuracoes`, `fiscal_dfe_documentos`, `fiscal_dfe_sincronizacoes`, `fiscal_processos`, `fiscal_ctes`, `fiscal_regras_imposto`.
- Migrations agrupadas por módulo (`create_fiscal_module`, `create_fiscal_configuration_tables`, `create_fiscal_dfe_distribution_tables`), com migrations de permissão em arquivo separado.
- Classes e métodos em inglês, mensagens ao usuário em português.

### Padrões

- Services com `__construct(private readonly ...)`.
- Escrita em campos fora do `$fillable` via `forceFill([...])->save()`.
- Erros de negócio como `ValidationException::withMessages()`, o que os liga direto ao campo do formulário.
- `authorize(): bool { return true; }` em todos os Form Requests lidos, com autorização real delegada a middleware e rotas.
- Auditoria leve por colunas `created_by` e `updated_by`.

### Testes

22 arquivos de teste tocando o módulo fiscal, entre eles `NFeImporterXmlTest`, `SefazDfeImportServiceTest`, `SefazDfeResponseParserTest`, `FiscalNfephpBuildersTest` e `FiscalNfeAutomaticRetryTest`. Cobertura concentrada em parser, gateway e serviços, não em telas.

## 6. Recomendação por funcionalidade

### 6.1 Certificado digital

| Item | Decisão | Justificativa |
|---|---|---|
| Leitura via `Certificate::readPfx` | **Reaproveitar** | Correto e direto ao ponto |
| Validação de CNPJ do titular e validade | **Reaproveitar** | Regra certa, mensagens boas |
| Extração de titular, serial, fingerprint e validade | **Reaproveitar** | Útil para a tela e para auditoria |
| Cast `SafeEncrypted` | **Reaproveitar com mudança** | Manter para campos tolerantes, mas a senha do certificado precisa falhar de forma explícita, nunca virar `null` em silêncio |
| Gravação do `.pfx` | **Reescrever** | Precisa de `Crypt` em repouso e object storage privado, não disco local |
| Troca de certificado | **Reescrever** | Hoje apaga o anterior. O `venda-redonda` exige histórico com um ativo por emitente |
| Erro de OpenSSL 3 legado | **Novo** | Detectar o algoritmo legado, tentar o provider `legacy` e, se falhar, dar mensagem que explique a causa |
| Alerta de vencimento | **Novo** | Job agendado em 30, 15 e 7 dias, com notificação e card no dashboard |
| Botão testar comunicação | **Novo** | `sefazStatus` da UF usando o certificado ativo |

### 6.2 Importação de notas

| Item | Decisão | Justificativa |
|---|---|---|
| `parseNFeXml` | **Reaproveitar, extraindo** | Melhor código do projeto. Vira `Services/Import/NFeXmlParser`, puro, sem tocar em banco e sem string de domínio |
| Detecção de CT-e | **Reaproveitar** | Barato e evita importar documento errado |
| Extratores de ICMS, IPI, PIS e COFINS | **Reaproveitar** | Prontos e testados |
| Exigir `protNFe` | **Melhorar** | O `venda-redonda` só aceita nota autorizada, conforme o requisito |
| `SefazDfeGateway` e implementação | **Reaproveitar quase intacto** | Interface limpa, fácil de mockar |
| `SefazDfeResponseParser` | **Reaproveitar** | Já trata a resposta compactada da SEFAZ |
| Controle de NSU, locks, 656, 137, 138 e cooldown | **Reaproveitar a arquitetura** | É o ativo mais valioso do módulo. Erra pouco e respeita os limites da SEFAZ |
| Duas fases, gravar e depois processar | **Reaproveitar** | Torna a importação resiliente |
| Dedup por NSU e SHA-256 | **Reaproveitar** | Sólido. Somar dedup por chave de acesso |
| `persistNotaNosPedidos` e afins | **Descartar** | Domínio de transportadora, sem uso aqui |
| Merge NSDocs | **Descartar** | Dependência de terceiro que não entra no escopo |
| Upload múltiplo e ZIP | **Novo** | Requisito do `venda-redonda`, inexistente na origem |
| Conciliação de itens e vínculo fornecedor x produto | **Novo** | Núcleo da importação para estoque, inexistente na origem |
| Conversão de unidade | **Novo** | Idem |
| CFOP de entrada a partir do CFOP de saída | **Novo** | Idem |
| Manifestação do destinatário | **Novo** | Inexistente na origem |
| God class de 1474 linhas | **Não repetir** | Separar em parser, importador e conciliador |

### 6.3 Bugs, riscos e pontos frágeis encontrados

| # | Gravidade | Achado |
|---|---|---|
| 1 | **Alta** | O `.pfx` é gravado sem criptografia em repouso. Quem ler o storage tem o certificado. Só a senha está protegida |
| 2 | **Alta** | Disco default `local` em `storage/app/private`. No Laravel Cloud o filesystem é efêmero, então certificados e XMLs somem a cada deploy. A guarda legal de 5 anos exige object storage |
| 3 | **Alta** | `SafeEncrypted` devolve `null` em falha de decriptação. Aplicado à senha do certificado, converte um problema de chave em erro genérico e confuso |
| 4 | **Média** | Certificado legado do OpenSSL 3 cai no `catch` genérico e vira "confira o arquivo e a senha", escondendo a causa |
| 5 | **Média** | `Storage::delete($oldPath)` elimina o certificado anterior. Sem histórico e sem rollback |
| 6 | **Média** | Nenhum alerta de vencimento de certificado |
| 7 | **Média** | `NFeImporter` com 1474 linhas concentra parse de NF-e, parse de CT-e, merge de terceiro e persistência. Difícil de testar e de evoluir |
| 8 | **Média** | String de domínio `'Faturado na Cerâmica'` cravada dentro do parser de XML |
| 9 | **Média** | `sped-da` em `dev-master`, sem versão travada |
| 10 | **Baixa** | Parser aceita `NFe` sem `protNFe`, permitindo importar nota não autorizada |
| 11 | **Baixa** | `authorize()` sempre `true` nos Form Requests. Funciona, mas deixa a autorização longe da regra |
| 12 | **Baixa** | Emissão travada em `homologacao` por `Rule::in`, enquanto a distribuição roda em produção. Correto para a transportadora, mas no `venda-redonda` a virada para produção precisa ser um fluxo explícito e auditado |

## 7. Conclusão

O `app-transm` entrega três ativos reais para o `venda-redonda`:

1. **O controle de consumo da Distribuição DF-e.** Locks, NSU, cooldown e tratamento de 656 são conhecimento caro de adquirir e estão prontos.
2. **O parser de XML da NF-e.** Robusto, defensivo e testado. Precisa apenas ser extraído do God class.
3. **O ciclo de vida do certificado A1.** A leitura e a validação estão certas. O armazenamento precisa ser refeito.

O que **não** vem junto é justamente o coração do novo sistema: não há emissão de NF-e 55 (existem `CteXmlBuilder` e `MdfeXmlBuilder`, mas nenhum `NfeXmlBuilder`), não há produtos, não há estoque e não há cálculo de tributos de saída. O `NfephpToolsFactory::nfe()` existe, porém só para distribuição.

Em resumo: o `app-transm` resolve **entrada** de notas. O `venda-redonda` precisa resolver **saída**, e essa parte nasce do zero, tendo o `CteXmlBuilder` como referência de estilo.
