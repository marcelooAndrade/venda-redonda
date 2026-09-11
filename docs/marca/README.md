# Marca Venda Redonda

Arquivos finais da identidade. A folha de marca completa, com as três direções de
símbolo, paleta, tipografia, variações e aplicações, está no artifact publicado.

Conceito: **a volta completa que fecha sem sobra.**
Frase: *Da nota ao caixa, a venda fecha redonda.*

## Arquivos

| Arquivo | Uso |
|---|---|
| `venda-redonda-icone-fundo-{claro,escuro}.png` | Avatar, ícone de app, favicon. 622 por 622 |
| `venda-redonda-horizontal-fundo-{claro,escuro}.png` | Padrão. Cabeçalho, rodapé de DANFE, assinatura de e-mail |
| `venda-redonda-empilhada-fundo-{claro,escuro}.png` | Formato quadrado e onde a largura não dá |
| `venda-redonda-icone-{volta,encaixe,assentada}-fundo-{claro,escuro}.svg` | Símbolo vetorial, uma por direção |
| `gerar-marca.py` | Regera tudo, SVG e PNG |

`fundo-claro` é a versão que **vai sobre** fundo claro, portanto desenhada em grafite.
`fundo-escuro` é a que vai sobre grafite, desenhada em papel. As duas mantêm o acento.
Todo PNG tem fundo transparente e já traz a área de respiro de 24 unidades embutida.
Não acrescente margem por cima disso, e não encoste nada dentro dela.

O símbolo aplicado nos PNG é o **Encaixe redondo**, a direção recomendada. Para trocar,
mude `RECOMENDADO` no topo de `gerar-marca.py` e rode de novo.

## Os três símbolos

Todos em grade de 100 por 100, geometria pura, sem dependência de fonte.

| Direção | Geometria | Leitura |
|---|---|---|
| **Encaixe redondo** *(recomendada)* | Quadrado cheio, canto superior direito cortado por côncavo de raio 56 no vértice, quarto de disco de raio 44 dentro, vão de 12 | O redondo encaixa exato no bloco, e a silhueta fecha um quadrado inteiro |
| **Volta completa** | Anel Ø84 e espessura 16, interrompido em 12h por vão de 8 em volta de um disco Ø32 | A venda sai de um ponto e volta exatamente a ele |
| **Assentada** | Base 100 por 20 e disco Ø68 apoiado com vão de 12 | O redondo parado sobre o nível, que não rola |

## Cores

| Papel | Hex | Regra |
|---|---|---|
| Base e fundo | `#0E1B1F` | 70% ou mais de qualquer peça |
| Texto claro | `#F2EFE8` | Responde ao grafite |
| Cinza-pedra | `#7F8E8B` | Linhas, legendas, versão metálica. Sobre papel escurece para `#5F6E6B`, senão fica com 2,97 de contraste |
| Vermelhão | `#E4572E` | Carimbo. No máximo 10%, só no ponto que fecha |

## Tipografia

Manrope, do Google Fonts. Wordmark em 800 ExtraBold, caixa alta, tracking +0.04em.

O **O de REDONDA não é a letra da fonte**: é um anel geométrico desenhado, de diâmetro
igual à altura de caixa alta. A haste da Manrope 800 mede 19,7 unidades dessa altura e o
anel usa **21**, porque curva com a mesma espessura de uma reta lê mais fina que ela. É a
mesma compensação que desenhista de tipo aplica no O, e foi medida no render, não estimada.

Os PNG já estão rasterizados com a fonte aplicada. Para gráfica, converta o wordmark em
curvas ou entregue a fonte junto. Os SVG do símbolo não dependem de fonte alguma.

## Uma ressalva sobre a Volta completa

O anel interrompido no topo com um ponto sobre a abertura fica perto da leitura de botão
de liga e desliga. A geometria é exatamente a do briefing e pode ser que essa vizinhança
não incomode, mas ela existe e é melhor decidir com ela à vista do que descobrir depois
num crachá. As outras duas direções não têm esse problema.
