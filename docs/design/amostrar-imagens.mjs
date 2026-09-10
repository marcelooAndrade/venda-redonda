import { chromium } from 'playwright';
import { readFileSync } from 'fs';

const imgs = [
  'hero-rcm-fachada.png', 'quem-somos-fundicao.png',
  'peca-01.png', 'peca-02.png', 'peca-03.png', 'peca-06.png',
];
const base = '/Users/marceloandrade/Projetos/rcmdobrasil/public/assets/';

const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto('about:blank');

for (const nome of imgs) {
  const b64 = readFileSync(base + nome).toString('base64');
  const r = await page.evaluate(async (dataUrl) => {
    const img = new Image();
    img.src = dataUrl;
    await img.decode();
    const c = document.createElement('canvas');
    const W = 160, H = Math.round(160 * img.height / img.width);
    c.width = W; c.height = H;
    const ctx = c.getContext('2d');
    ctx.drawImage(img, 0, 0, W, H);
    const { data } = ctx.getImageData(0, 0, W, H);

    const rgb2hsl = (r, g, b) => {
      r /= 255; g /= 255; b /= 255;
      const mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2;
      let h = 0, s = 0;
      if (mx !== mn) {
        const d = mx - mn;
        s = l > .5 ? d / (2 - mx - mn) : d / (mx + mn);
        if (mx === r) h = ((g - b) / d + (g < b ? 6 : 0));
        else if (mx === g) h = (b - r) / d + 2;
        else h = (r - g) / d + 4;
        h *= 60;
      }
      return [h, s * 100, l * 100];
    };

    // Agrupa por faixa de matiz, só pixels com saturação real
    const buckets = {};
    let cromatico = 0, total = 0;
    for (let i = 0; i < data.length; i += 4) {
      const [r, g, b, a] = [data[i], data[i + 1], data[i + 2], data[i + 3]];
      if (a < 200) continue;
      total++;
      const [h, s, l] = rgb2hsl(r, g, b);
      if (s < 12 || l < 8 || l > 94) continue;
      cromatico++;
      const k = Math.floor(h / 15) * 15;
      buckets[k] = buckets[k] || { n: 0, r: 0, g: 0, b: 0, s: 0, l: 0 };
      const o = buckets[k];
      o.n++; o.r += r; o.g += g; o.b += b; o.s += s; o.l += l;
    }
    const top = Object.entries(buckets).sort((a, b) => b[1].n - a[1].n).slice(0, 3)
      .map(([h, o]) => ({
        matiz: `${h}-${+h + 15}`,
        pct: +(100 * o.n / total).toFixed(1),
        hex: '#' + [o.r, o.g, o.b].map(v => Math.round(v / o.n).toString(16).padStart(2, '0')).join(''),
        sat: Math.round(o.s / o.n), lum: Math.round(o.l / o.n),
      }));
    return { cromaticoPct: +(100 * cromatico / total).toFixed(1), top };
  }, `data:image/png;base64,${b64}`);
  console.log(`\n${nome}  (pixels cromáticos: ${r.cromaticoPct}%)`);
  r.top.forEach(t => console.log(`   matiz ${t.matiz.padStart(7)}°  ${String(t.pct).padStart(5)}%  ${t.hex}  sat=${t.sat} lum=${t.lum}`));
}
await browser.close();
