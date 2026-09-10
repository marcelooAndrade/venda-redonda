# emissor-nfe

Sistema emissor de NF-e modelo 55, layout 4.00, multiempresa e com marca dinâmica por URL.

Construído para a RCM do Brasil, mas parametrizável: nenhuma alíquota, CST, CSOSN ou CFOP vive no código. A regra fiscal é escrita pelo contador, na tela.

## Rodando na sua máquina

### O que você precisa

Já está tudo instalado na sua máquina, mas confira:

```bash
php -v          # precisa ser 8.3 ou maior
composer -V
node -v
```

O banco é **SQLite** em desenvolvimento, então não precisa subir MySQL.

### Três comandos

```bash
cd ~/Projetos/emissor-nfe

composer install && npm install && npm run build

php artisan emissor:demo --fresh
```

O último comando recria o banco e popula tudo: dois tenants com marcas diferentes, quatro perfis de usuário, produtos com estoque, regras fiscais com vigência, notas em rascunho e uma autorizada com carta de correção.

### Subindo o servidor

```bash
php artisan serve --host=0.0.0.0
```

O `--host=0.0.0.0` importa: sem ele os subdomínios `*.localhost` não resolvem.

### Onde entrar

| Tenant | URL | E-mail | Senha |
|---|---|---|---|
| RCM do Brasil | http://rcm.localhost:8000 | `admin@rcm.test` | `demo1234` |
| Transportes Leme | http://leme.localhost:8000 | `admin@leme.test` | `demo1234` |

**Abra os dois.** É o mesmo sistema, o mesmo bundle de CSS, servindo marcas diferentes: a RCM em vermelho e grafite, a Leme em verde e preto. A troca acontece por sobrescrita de variável CSS, sem recompilar nada.

## O que vale a pena olhar

Sugestão de roteiro, começando pela RCM:

| Tela | O que observar |
|---|---|
| **Painel** | A tela que abre depois do login. Repare na ordem: pendência antes de faturamento |
| **Qualquer tela, em monitor largo** | O conteúdo ocupa a largura toda. Estreite a janela até a largura de celular: nada rola na horizontal |
| **Design System** | A paleta completa, os oito status da NF-e e a decisão de por que o botão de ação primária não é vermelho |
| **Marca** | Troque a cor e salve. Recarregue: o sistema inteiro muda. Tente um amarelo claro para ver o ajuste automático de contraste |
| **Regras fiscais** | Entre como `contador@rcm.test`. Repare no histórico de vigência: a regra antiga encerrada em 02/08 e a nova, com IBS e CBS |
| **Notas fiscais** | Abra o rascunho. Os tributos são recalculados no servidor a cada mudança. Abra também a nota 1.480, autorizada, para ver a linha do tempo com a carta de correção |
| **Estoque** | O Kardex do "Disco usinado" mostra uma saída seguida de estorno: nada é apagado, correção é movimento novo |
| **Importação** | Envie `tests/Fixtures/xml/nfe-autorizada.xml`. Veja o fornecedor sendo criado do XML, o CFOP 5102 virando 1102, e um item se vinculando sozinho por GTIN |
| **Contabilidade** | O pacote do período, organizado por pasta |

Troque de usuário para ver a navegação mudar: o contador não enxerga Certificado, o faturamento não enxerga Regras fiscais.

## Limites da demonstração

Duas coisas **não** dá para testar sem certificado digital de verdade:

1. **Transmitir para a SEFAZ.** O código está pronto e coberto por 13 testes com gateway simulado, mas transmitir de fato exige um certificado A1 da RCM em ambiente de homologação.
2. **Gerar o DANFE.** Ele é feito a partir do XML protocolado, que só existe depois de uma autorização real.

A nota 1.480 da demonstração aparece autorizada porque foi marcada assim no seed, para você ver as telas de evento. Ela não passou pela SEFAZ.

## Comandos úteis

```bash
php artisan test                          # 417 testes
./vendor/bin/pint                         # formatação
php artisan emissor:demo --fresh          # recomeça do zero

php artisan fiscal:importar-municipios    # IBGE: 27 UFs e 5.571 municípios
php artisan fiscal:importar-ncm           # Siscomex: tabela NCM vigente
php artisan fiscal:alertar-certificados    # avisa vencimento em 30, 15 e 7 dias
```

Os dois primeiros baixam dados oficiais de verdade e levam alguns segundos.

## Documentação

| Arquivo | Conteúdo |
|---|---|
| `CLAUDE.md` | Stack, convenções e o registro de decisões |
| `docs/funcionalidades.md` | **O que o sistema faz**, o que ficou de fora e o que falta |
| `docs/analise-app-transm.md` | O que foi aproveitado do sistema da Trans M, e o que não |
| `docs/design-system.md` | A paleta extraída do site da RCM, com o método |
| `docs/arquitetura.md` | Modelo de dados e fluxos, com diagramas |
| `docs/decisoes-fiscais.md` | **24 decisões fiscais**, cada uma com fonte e data de verificação |

O `decisoes-fiscais.md` é o mais importante para conferir com o contador: é onde está registrado por que cada regra é como é.

## Pendências conhecidas

- **Certificado A1 real** em homologação, para provar a emissão contra a SEFAZ
- **Dados do responsável técnico** (`infRespTec`) no `.env`, obrigatórios antes de emitir em produção
- **CRT da RCM**: se for 3, o IBS/CBS já é exigência desde 03/08/2026
- Tabelas **CFOP, CEST, cClassTrib e tPag** ficaram vazias de propósito, aguardando fonte oficial
- **Distribuição DF-e** (buscar notas na SEFAZ automaticamente) e **contingência SVC**
- Módulo 10: faltam os relatórios; o painel já está pronto
