"""Gera os arquivos finais da marca Venda Redonda.

Os SVG do símbolo são geometria pura, escritos aqui em grade de 100 por 100.
Os PNG saem do Chrome headless, são recortados no conteúdo real e recebem a
área de respiro de 24 unidades, que é a regra da folha de marca.

    python3 docs/marca/gerar-marca.py
"""

import os
import subprocess

from PIL import Image

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
AQUI = os.path.dirname(os.path.abspath(__file__))
TMP = os.path.join(AQUI, "_tmp")

# {t} é a cor de texto, {a} a cor de acento.
SIMBOLOS = {
    # Anel Ø84 e espessura 16, interrompido em 12h por um vão circular de 8
    # em volta de um disco Ø32 assentado sobre a linha média do anel.
    # O conjunto desce 4 unidades para centrar na grade.
    "volta": (
        '<g transform="translate(0,4)">'
        '<path fill="{t}" d="M77.74 18.46 A42 42 0 1 1 22.26 18.46 '
        'L32.83 30.48 A26 26 0 1 0 67.17 30.48 Z"/>'
        '<circle fill="{a}" cx="50" cy="16" r="16"/>'
        "</g>"
    ),
    # Quadrado cheio com corte côncavo de raio 56 no vértice superior
    # direito e quarto de disco de raio 44 dentro dele: vão curvo de 12.
    "encaixe": (
        '<path fill="{t}" d="M0 0 H44 A56 56 0 0 0 100 56 V100 H0 Z"/>'
        '<path fill="{a}" d="M100 0 H56 A44 44 0 0 0 100 44 Z"/>'
    ),
    # Base 100x20 e disco Ø68 apoiado sobre ela com vão de 12.
    "assentada": (
        '<rect fill="{a}" x="0" y="80" width="100" height="20"/>'
        '<circle fill="{t}" cx="50" cy="34" r="34"/>'
    ),
}

RECOMENDADO = "encaixe"

FUNDOS = {"fundo-claro": ("#0E1B1F", "#E4572E"), "fundo-escuro": ("#F2EFE8", "#E4572E")}

# font-size do render. O símbolo tem exatamente 1em de altura.
EM = 420

# O anel que substitui o O de REDONDA. A haste da Manrope 800 mede 19,7
# unidades da caixa alta; o anel usa 21, porque curva com a mesma
# espessura de uma reta lê mais fina que ela.
ANEL = ('<svg class="anel" viewBox="0 0 100 100">'
        '<circle cx="50" cy="50" r="39.5" fill="none" stroke="{a}" stroke-width="21"/>'
        "</svg>")

BASE = """<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@800&display=swap">
<style>
  html,body {{ margin:0; padding:0; background:transparent; }}
  /* O corpo se dimensiona pelo conteúdo, e não pela viewport: senão o
     wordmark em nowrap estoura a caixa e o screenshot corta. */
  body {{ display:inline-block; width:max-content; }}
  .lk {{ display:inline-flex; align-items:center; gap:0.4em; font-size:{em}px; }}
  .lk.stack {{ flex-direction:column; align-items:center; gap:0.32em; }}
  .lk.stack .wm {{ white-space:normal; text-align:center; max-width:7.2em; line-height:0.98; }}
  .wm {{ flex:none; font-family:"Manrope",sans-serif; font-weight:800;
         text-transform:uppercase; letter-spacing:0.04em; line-height:0.84;
         margin-right:-0.04em; white-space:nowrap; color:{t}; }}
  /* O anel abre oportunidade de quebra dentro da palavra. */
  .wm .nb {{ white-space:nowrap; }}
  .anel {{ display:inline-block; width:0.715em; height:0.715em;
           vertical-align:baseline; margin-inline:0.035em 0.045em; }}
  .sym {{ width:1em; height:1em; flex:none; display:block; }}
</style></head><body>{corpo}</body></html>"""


def escrever_svgs():
    for nome, corpo in SIMBOLOS.items():
        for fundo, (t, a) in FUNDOS.items():
            svg = (
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" '
                'width="100" height="100"><title>Venda Redonda</title>'
                + corpo.format(t=t, a=a)
                + "</svg>"
            )
            destino = os.path.join(AQUI, f"venda-redonda-icone-{nome}-{fundo}.svg")
            open(destino, "w").write(svg)
            yield destino


def render(nome, corpo, largura, altura):
    origem = os.path.join(TMP, f"{nome}.html")
    bruto = os.path.join(TMP, f"{nome}.png")
    open(origem, "w").write(corpo)

    subprocess.run(
        [
            CHROME, "--headless", "--disable-gpu", "--hide-scrollbars",
            "--force-color-profile=srgb", "--default-background-color=00000000",
            "--virtual-time-budget=7000",
            f"--window-size={largura},{altura}",
            f"--screenshot={bruto}", f"file://{origem}",
        ],
        capture_output=True,
    )
    return bruto


def recortar_e_respirar(bruto, destino):
    img = Image.open(bruto).convert("RGBA")
    caixa = img.getbbox()
    if caixa is None:
        raise SystemExit(f"{bruto} saiu vazio")

    if caixa[2] >= img.width or caixa[3] >= img.height:
        raise SystemExit(f"{bruto} encostou na borda da janela: aumente o render")

    corte = img.crop(caixa)
    respiro = round(EM * 0.24)  # 24 unidades da grade do símbolo
    fundo = Image.new("RGBA", (corte.width + respiro * 2, corte.height + respiro * 2), (0, 0, 0, 0))
    fundo.paste(corte, (respiro, respiro))
    fundo.save(destino)
    return fundo.size


def main():
    os.makedirs(TMP, exist_ok=True)

    for caminho in escrever_svgs():
        print(f"  {os.path.basename(caminho)}")

    for fundo, (t, a) in FUNDOS.items():
        sym = (f'<svg class="sym" viewBox="0 0 100 100">'
               + SIMBOLOS[RECOMENDADO].format(t=t, a=a) + "</svg>")
        anel = ANEL.format(a=a)
        wm = f'<span class="wm">VENDA <span class="nb">RED{anel}NDA</span></span>'

        pecas = [
            (f"icone-{fundo}", f'<div class="lk">{sym}</div>', 1400, 1400),
            (f"horizontal-{fundo}", f'<div class="lk">{sym}{wm}</div>', 6400, 900),
            (f"empilhada-{fundo}", f'<div class="lk stack">{sym}{wm}</div>', 3400, 1900),
        ]

        for nome, miolo, lw, lh in pecas:
            bruto = render(nome, BASE.format(em=EM, t=t, corpo=miolo), lw, lh)
            destino = os.path.join(AQUI, f"venda-redonda-{nome}.png")
            w, h = recortar_e_respirar(bruto, destino)
            print(f"  {os.path.basename(destino):42s} {w} x {h}")


if __name__ == "__main__":
    main()
