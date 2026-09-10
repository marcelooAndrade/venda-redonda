import { chromium } from 'playwright';

const OUT = '/Users/marceloandrade/Projetos/emissor-nfe/docs/design/referencia';
const URL = 'https://rcmdobrasil.com.br/';

const browser = await chromium.launch();

// ---------- Desktop 1440 ----------
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();
await page.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(2500);

// Rola a página inteira para disparar as animações de reveal (framer-motion whileInView)
await page.evaluate(async () => {
  const step = window.innerHeight;
  for (let y = 0; y < document.body.scrollHeight; y += step) {
    window.scrollTo(0, y);
    await new Promise(r => setTimeout(r, 350));
  }
  window.scrollTo(0, 0);
});
await page.waitForTimeout(1200);

const dados = await page.evaluate(() => {
  const norm = (c) => {
    if (!c || c === 'rgba(0, 0, 0, 0)' || c === 'transparent') return null;
    return c;
  };
  const freq = {};
  const bump = (c, peso = 1) => { const n = norm(c); if (n) freq[n] = (freq[n] || 0) + peso; };

  // Frequência ponderada por área visível do elemento
  document.querySelectorAll('*').forEach(el => {
    const s = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    const area = Math.max(0, r.width) * Math.max(0, r.height);
    const peso = area > 0 ? Math.min(50, Math.ceil(area / 20000)) : 0;
    if (peso > 0) bump(s.backgroundColor, peso);
    if (el.textContent && el.textContent.trim().length > 0) bump(s.color, 1);
    if (s.borderTopWidth !== '0px') bump(s.borderTopColor, 1);
  });

  const amostra = (sel, rotulo) => {
    const el = document.querySelector(sel);
    if (!el) return { rotulo, seletor: sel, ausente: true };
    const s = getComputedStyle(el);
    return {
      rotulo, seletor: sel,
      color: s.color, backgroundColor: s.backgroundColor,
      backgroundImage: s.backgroundImage.slice(0, 180),
      fontFamily: s.fontFamily, fontSize: s.fontSize, fontWeight: s.fontWeight,
      lineHeight: s.lineHeight, letterSpacing: s.letterSpacing,
      textTransform: s.textTransform,
      borderRadius: s.borderRadius, borderWidth: s.borderTopWidth, borderColor: s.borderTopColor,
      boxShadow: s.boxShadow.slice(0, 160),
      padding: s.padding, margin: s.margin, minHeight: s.minHeight,
    };
  };

  const alvos = [
    ['body', 'body'], ['main', 'main'],
    ['#inicio', 'secao hero'], ['#hero-title', 'h1 hero'],
    ['#inicio p', 'eyebrow hero'],
    ['#inicio a:nth-of-type(1)', 'botao primario'],
    ['#inicio a:nth-of-type(2)', 'botao secundario'],
    ['#sobre', 'secao sobre'], ['#processo', 'secao processo'],
    ['#pecas', 'secao pecas'], ['#diferenciais', 'secao diferenciais'],
    ['#contato', 'secao contato'],
    ['h2', 'h2'], ['h3', 'h3'],
    ['form', 'formulario'], ['input', 'input'], ['select', 'select'],
    ['textarea', 'textarea'], ['button[type=submit]', 'botao submit'],
    ['label', 'label'], ['footer', 'footer'], ['footer a', 'link footer'],
    ['header', 'header'], ['nav', 'nav'],
  ];

  // Variáveis CSS declaradas em :root
  const vars = {};
  for (const sheet of Array.from(document.styleSheets)) {
    let regras; try { regras = sheet.cssRules; } catch { continue; }
    for (const regra of Array.from(regras || [])) {
      if (regra.style && regra.selectorText && regra.selectorText.includes(':root')) {
        for (const prop of Array.from(regra.style)) {
          if (prop.startsWith('--')) vars[prop] = regra.style.getPropertyValue(prop).trim();
        }
      }
    }
  }

  // Raios, sombras e fontes efetivamente usados
  const raios = {}, sombras = {}, fontes = {}, pesos = {}, tamanhos = {};
  document.querySelectorAll('*').forEach(el => {
    const s = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) return;
    if (s.borderRadius !== '0px') raios[s.borderRadius] = (raios[s.borderRadius] || 0) + 1;
    if (s.boxShadow !== 'none') { const k = s.boxShadow.slice(0, 90); sombras[k] = (sombras[k] || 0) + 1; }
    if (el.textContent && el.textContent.trim()) {
      const f = s.fontFamily.split(',')[0].replace(/["']/g, '');
      fontes[f] = (fontes[f] || 0) + 1;
      pesos[s.fontWeight] = (pesos[s.fontWeight] || 0) + 1;
      tamanhos[s.fontSize] = (tamanhos[s.fontSize] || 0) + 1;
    }
  });

  const ord = (o) => Object.entries(o).sort((a, b) => b[1] - a[1]);

  return {
    titulo: document.title,
    cores: ord(freq).slice(0, 28),
    amostras: alvos.map(([s, r]) => amostra(s, r)),
    variaveis: vars,
    raios: ord(raios).slice(0, 10),
    sombras: ord(sombras).slice(0, 8),
    fontes: ord(fontes),
    pesos: ord(pesos),
    tamanhos: ord(tamanhos).slice(0, 16),
    secoes: Array.from(document.querySelectorAll('section')).map(s => ({
      id: s.id, bg: getComputedStyle(s).backgroundColor,
      bgImg: getComputedStyle(s).backgroundImage.slice(0, 120),
    })),
  };
});

// Estados de hover dos botões
const hovers = [];
for (const sel of ['#inicio a:nth-of-type(1)', '#inicio a:nth-of-type(2)', 'button[type=submit]', 'footer a']) {
  try {
    const el = page.locator(sel).first();
    if (await el.count() === 0) continue;
    const antes = await el.evaluate(n => { const s = getComputedStyle(n); return { bg: s.backgroundColor, color: s.color, border: s.borderTopColor }; });
    await el.hover({ timeout: 4000 });
    await page.waitForTimeout(500);
    const depois = await el.evaluate(n => { const s = getComputedStyle(n); return { bg: s.backgroundColor, color: s.color, border: s.borderTopColor }; });
    hovers.push({ seletor: sel, antes, depois });
  } catch (e) { hovers.push({ seletor: sel, erro: String(e).slice(0, 80) }); }
}
dados.hovers = hovers;

await page.screenshot({ path: `${OUT}/desktop-1440-completo.png`, fullPage: true });
await page.screenshot({ path: `${OUT}/desktop-1440-hero.png` });

// ---------- Mobile 390 ----------
const ctxM = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
const pageM = await ctxM.newPage();
await pageM.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
await pageM.waitForTimeout(2000);
await pageM.evaluate(async () => {
  const step = window.innerHeight;
  for (let y = 0; y < document.body.scrollHeight; y += step) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 300)); }
  window.scrollTo(0, 0);
});
await pageM.waitForTimeout(1000);
await pageM.screenshot({ path: `${OUT}/mobile-390-completo.png`, fullPage: true });
await pageM.screenshot({ path: `${OUT}/mobile-390-hero.png` });

await browser.close();
console.log(JSON.stringify(dados, null, 2));
