"""Gera os PNG finais da marca Venda Redonda, com fundo transparente.

Cada peça é renderizada no Chrome em alta resolução, recortada no
conteúdo real e depois recebe a área de respiro de 24 unidades da grade
do símbolo, que é a regra da folha de marca.
"""

import os
import subprocess

from PIL import Image

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
TMP = os.path.dirname(os.path.abspath(__file__))
SAIDA = "/Users/marceloandrade/Projetos/emissor-nfe/docs/marca"

SIMBOLO = (
    '<svg viewBox="0 0 100 100" style="width:1em;height:1em;flex:none;display:block">'
    '<path fill="{t}" d="M0 0h44v56h56v44H0Z"/>'
    '<rect fill="{a}" x="56" y="0" width="44" height="44"/>'
    "</svg>"
)

FUNDOS = {"fundo-claro": ("#0E1B1F", "#E4572E"), "fundo-escuro": ("#F2EFE8", "#E4572E")}

# font-size de render. O símbolo tem exatamente 1em de altura.
EM = 420

BASE = """<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@800&display=swap">
<style>
  html,body {{ margin:0; padding:0; background:transparent; }}
  /* O corpo se dimensiona pelo conteúdo, e não pela viewport: senão
     o wordmark em nowrap estoura a caixa e o screenshot corta. */
  body {{ display:inline-block; width:max-content; }}
  .lk {{ display:inline-flex; align-items:center; gap:0.42em; font-size:{em}px; }}
  .lk.stack {{ flex-direction:column; align-items:center; gap:0.34em; }}
  .lk.stack .wm {{ white-space:normal; text-align:center; max-width:6.4em; line-height:0.92; }}
  .wm {{ flex:none; font-family:"Archivo",sans-serif; font-weight:800; text-transform:uppercase;
         letter-spacing:0.06em; line-height:0.82; margin-right:-0.06em;
         white-space:nowrap; color:{t}; }}
  .wm .o {{ color:{a}; }}
</style></head><body>{corpo}</body></html>"""


def render(nome, corpo, t, a, largura, altura):
    html = BASE.format(em=EM, t=t, a=a, corpo=corpo)
    origem = os.path.join(TMP, f"_{nome}.html")
    bruto = os.path.join(TMP, f"_{nome}.png")
    open(origem, "w").write(html)

    subprocess.run(
        [
            CHROME, "--headless", "--disable-gpu", "--hide-scrollbars",
            "--force-color-profile=srgb",
            "--default-background-color=00000000",
            "--virtual-time-budget=6000",
            f"--window-size={largura},{altura}",
            f"--screenshot={bruto}",
            f"file://{origem}",
        ],
        capture_output=True,
    )
    return bruto


def recortar_e_respirar(bruto, destino):
    """Recorta no conteúdo e devolve a área de respiro de 24 unidades."""
    img = Image.open(bruto).convert("RGBA")
    caixa = img.getbbox()
    if caixa is None:
        raise SystemExit(f"{bruto} saiu vazio")

    corte = img.crop(caixa)
    # O símbolo tem 1em de altura, e o respiro é 24% dele.
    respiro = round(EM * 0.24)
    fundo = Image.new(
        "RGBA",
        (corte.width + respiro * 2, corte.height + respiro * 2),
        (0, 0, 0, 0),
    )
    fundo.paste(corte, (respiro, respiro))
    fundo.save(destino)
    return fundo.size


PECAS = []
for fundo, (t, a) in FUNDOS.items():
    sym = SIMBOLO.format(t=t, a=a)
    wm = '<span class="wm">VENDA RED<span class="o">O</span>NDA</span>'
    PECAS += [
        (f"icone-{fundo}", f'<div class="lk">{sym}</div>', t, a, 1400, 1400),
        (f"horizontal-{fundo}", f'<div class="lk">{sym}{wm}</div>', t, a, 6400, 900),
        (f"empilhada-{fundo}", f'<div class="lk stack">{sym}{wm}</div>', t, a, 3000, 1800),
    ]

os.makedirs(SAIDA, exist_ok=True)

for nome, corpo, t, a, lw, lh in PECAS:
    bruto = render(nome, corpo, t, a, lw, lh)
    destino = os.path.join(SAIDA, f"venda-redonda-{nome}.png")
    w, h = recortar_e_respirar(bruto, destino)
    print(f"{os.path.basename(destino):38s} {w} x {h}")
