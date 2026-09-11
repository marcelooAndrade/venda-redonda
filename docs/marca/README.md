# Marca Venda Redonda

Arquivos finais da identidade. A folha de marca completa, com paleta, tipografia,
variações e aplicações, está no artifact publicado em 10/09/2026.

## Arquivos

| Arquivo | Uso |
|---|---|
| `venda-redonda-icone-fundo-{claro,escuro}.png` | Avatar, ícone de app, favicon. 622 por 622, fundo transparente |
| `venda-redonda-horizontal-fundo-{claro,escuro}.png` | Padrão. Cabeçalho, rodapé de DANFE, assinatura de e-mail |
| `venda-redonda-empilhada-fundo-{claro,escuro}.png` | Formato quadrado e onde a largura não dá |
| `venda-redonda-icone-{encaixe,contrapeso,prumo}-fundo-{claro,escuro}.svg` | Símbolo vetorial, uma por direção |

`fundo-claro` é a versão que **vai sobre** fundo claro, portanto desenhada em grafite.
`fundo-escuro` é a que vai sobre grafite, desenhada em papel. As duas mantêm o acento.

Todo PNG já vem com a área de respiro de 24 unidades da grade embutida. Não acrescente
margem por cima disso, e não encoste nada dentro dela.

## Cores

| Papel | Hex | Regra |
|---|---|---|
| Base e fundo | `#0E1B1F` | 70% ou mais de qualquer peça |
| Texto claro | `#F2EFE8` | Responde ao grafite |
| Cinza-pedra | `#7F8E8B` | Linhas, legendas, versão metálica. Sobre papel escurece para `#5F6E6B`, senão fica com 2,97 de contraste |
| Vermelhão | `#E4572E` | Carimbo. No máximo 10%, só no ponto que confere |

## Tipografia

Archivo, do Google Fonts. Wordmark em 800 ExtraBold, caixa alta, tracking +0.06em.

O **O de REDONDA** é a única letra em vermelhão e a única forma curva de todo o sistema,
que no resto é reto e de 90°. Ele carrega sozinho o sentido do nome: conta redonda,
diferença zero. Nenhuma outra letra recebe cor, em nenhuma aplicação.

Os PNG já estão rasterizados com a fonte aplicada. Para gráfica, converta o wordmark em
curvas ou entregue a fonte junto. Os SVG do símbolo não dependem de fonte alguma.

## Como regerar

Os SVG são geometria pura, escritos à mão em grade de 100 por 100. Os PNG saem do script
de render em `docs/marca/gerar-png.py`, que desenha no Chrome headless, recorta no conteúdo
e devolve o respiro.

```bash
python3 docs/marca/gerar-png.py
```

## Em aberto

O símbolo **Encaixe** nasceu no briefing como monograma de um L. Com o nome Venda Redonda
esse L deixa de ser monograma, embora a geometria continue válida e talvez melhor: o
quadrado declarado encaixa exato na base que o sustenta, sem sobra nem falta, que é a venda
redonda em forma. Se a intenção for ter monograma, a letra teria de ser V ou R, e isso é
um redesenho do símbolo, não um ajuste.
