// Audit des maquettes : debordement horizontal (390, 820, 1280), contrastes texte/fond (WCAG AA), bordures de champs (3:1),
// logo (largeur minimale, zone de protection 1X). Usage : node audit.mjs [01 02 ...]  (PW_CORE comme pour capture.mjs)
import { readdirSync, existsSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import os from 'node:os';
import { PAGES } from './common.mjs';

const { chromium } = await import(process.env.PW_CORE ? pathToFileURL(process.env.PW_CORE).href : 'playwright-core');
const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const only = process.argv.slice(2);
const pw = join(os.homedir(), 'AppData/Local/ms-playwright');
const dirs = readdirSync(pw).filter((d) => d.startsWith('chromium-')).sort();
const exe = join(pw, dirs[dirs.length - 1], 'chrome-win64', 'chrome.exe');
const browser = await chromium.launch({ executablePath: existsSync(exe) ? exe : undefined });

function inPage() {
  const parse = (c) => { const cs = c.match(/color\(srgb ([^)]+)\)/); if (cs) { const q = cs[1].split(/[ /]+/).map(Number); return [q[0] * 255, q[1] * 255, q[2] * 255, q.length > 3 ? q[3] : 1]; } const m = c.match(/rgba?\(([^)]+)\)/); if (!m) return [0, 0, 0, 0]; const p = m[1].split(/[ ,/]+/).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; };
  const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const ratio = (a, b) => { const l1 = lum(a), l2 = lum(b); return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05); };
  const over = (top, bot) => { const a = top[3]; return [top[0] * a + bot[0] * (1 - a), top[1] * a + bot[1] * (1 - a), top[2] * a + bot[2] * (1 - a), 1]; };
  const visible = (el) => { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && !(cs.clip === 'rect(0px, 0px, 0px, 0px)') && !(el.closest('.sr-only, .skip, .mockup-back') && !el.matches(':focus')); };
  function bgOf(el) {
    const layers = []; let unknown = false;
    for (let e = el; e; e = e.parentElement) {
      const cs = getComputedStyle(e);
      if (cs.backgroundImage !== 'none') unknown = true;
      const c = parse(cs.backgroundColor);
      if (c[3] > 0) { layers.push(c); if (c[3] === 1) break; }
    }
    let base = [255, 255, 255, 1];
    for (let i = layers.length - 1; i >= 0; i--) base = over(layers[i], base);
    return { color: base, unknown };
  }
  const out = { contrast: [], unknown: [], fields: [], logo: [], min: 99 };
  const sel = (el) => el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  const seen = new Set();
  for (let n = walker.nextNode(); n; n = walker.nextNode()) {
    const t = n.textContent.trim(); if (!t) continue;
    const el = n.parentElement; if (!el || ['SCRIPT', 'STYLE', 'OPTION'].includes(el.tagName) || !visible(el)) continue;
    const key = el; if (seen.has(key)) continue; seen.add(key);
    const cs = getComputedStyle(el);
    const bg = bgOf(el);
    let fg = parse(cs.color); let op = 1; for (let e = el; e; e = e.parentElement) op *= parseFloat(getComputedStyle(e).opacity);
    fg = [fg[0], fg[1], fg[2], fg[3] * op];
    const fgc = over(fg, bg.color);
    const r = ratio(fgc, bg.color);
    const size = parseFloat(cs.fontSize); const w = parseInt(cs.fontWeight, 10);
    const large = size >= 24 || (size >= 18.66 && w >= 700);
    const need = large ? 3 : 4.5;
    out.min = Math.min(out.min, r);
    const rec = { sel: sel(el), text: t.slice(0, 40), ratio: +r.toFixed(2), need, fg: cs.color, bg: `rgb(${bg.color.map(Math.round).slice(0, 3)})` };
    if (bg.unknown) out.unknown.push(rec);
    else if (r < need) out.contrast.push(rec);
  }
  // champs : placeholder, valeur, et frontiere du champ
  for (const el of document.querySelectorAll('input:not([type=checkbox]):not([type=radio]), select, textarea, .redactor-box')) {
    if (!visible(el) || el.closest('.bigsearch')) continue; // champ sans bord dans un conteneur lui-meme delimite
    const cs = getComputedStyle(el); const par = bgOf(el.parentElement);
    const bc = parse(cs.borderTopColor); const bw = parseFloat(cs.borderTopWidth);
    const own = parse(cs.backgroundColor);
    const edge = bw > 0 && bc[3] > 0 ? ratio(over(bc, par.color), par.color) : 1;
    const fill = own[3] > 0 ? ratio(over(own, par.color), par.color) : 1;
    const outline = Math.max(edge, fill);
    if (outline < 3 && !el.matches('.redactor-box input')) out.fields.push({ sel: sel(el), edge: +edge.toFixed(2), fill: +fill.toFixed(2) });
    if (el.placeholder) {
      const ph = getComputedStyle(el, '::placeholder'); const r = ratio(over(parse(ph.color), bgOf(el).color), bgOf(el).color);
      if (r < 4.5) out.contrast.push({ sel: sel(el) + '::placeholder', text: el.placeholder.slice(0, 30), ratio: +r.toFixed(2), need: 4.5 });
    }
  }
  // logo : largeur minimale et zone de protection
  const ink = [];
  const tw = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  for (let n = tw.nextNode(); n; n = tw.nextNode()) { if (!n.textContent.trim() || !n.parentElement || !visible(n.parentElement)) continue; const rg = document.createRange(); rg.selectNodeContents(n); for (const r of rg.getClientRects()) if (r.width > 0) ink.push({ r, el: n.parentElement, kind: 'texte' }); }
  for (const el of document.querySelectorAll('img, svg, input, select, textarea, button, .sommet, .avatar')) if (visible(el)) ink.push({ r: el.getBoundingClientRect(), el, kind: el.tagName.toLowerCase() });
  for (const el of document.querySelectorAll('a, span, div, li, p')) { if (!visible(el)) continue; const cs = getComputedStyle(el); if (parse(cs.backgroundColor)[3] > 0.05 && el.getBoundingClientRect().width < 600) ink.push({ r: el.getBoundingClientRect(), el, kind: 'fond' }); }
  for (const img of document.querySelectorAll('img[src*="logo-"], img[src*="symbole-"]')) {
    if (!visible(img)) continue;
    const r = img.getBoundingClientRect();
    const full = /logo-/.test(img.src);
    const minW = full ? 120 : 56;
    const X = full && /horizontal/.test(img.src) ? r.height : (full ? r.height * 0.6 : r.height);
    const z = { l: r.left - X, t: r.top - X, r: r.right + X, b: r.bottom + X };
    const viol = [];
    for (const k of ink) {
      if (k.el === img || k.el.contains(img) || img.contains(k.el)) continue;
      const a = k.r;
      if (a.right > z.l + 0.5 && a.left < z.r - 0.5 && a.bottom > z.t + 0.5 && a.top < z.b - 0.5) viol.push(`${k.kind}:${sel(k.el)}`);
    }
    out.logo.push({ src: img.src.split('/').pop(), w: Math.round(r.width), h: Math.round(r.height), minOk: r.width >= minW - 0.5, X: Math.round(X), viol: [...new Set(viol)].slice(0, 4), offscreen: r.left < 0 || r.right > innerWidth });
  }
  out.overflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
  return out;
}

const report = [];
let bad = 0;
for (const d of readdirSync(root).filter((d) => /^0\d-/.test(d) && (!only.length || only.some((o) => d.startsWith(o))))) {
  for (const [sn, w] of [['mobile', 390], ['tablette', 820], ['bureau', 1280]]) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 900 } });
    const page = await ctx.newPage();
    for (const p of PAGES) {
      await page.goto(pathToFileURL(join(root, d, p.file)).href);
      await page.evaluate(() => document.fonts.ready);
      const r = await page.evaluate(inPage);
      const problems = [];
      if (r.overflow > 0) problems.push(`debordement horizontal ${r.overflow}px`);
      for (const c of r.contrast) problems.push(`contraste ${c.ratio}<${c.need} ${c.sel} "${c.text}" ${c.fg} sur ${c.bg}`);
      for (const f of r.fields) problems.push(`bord de champ <3:1 ${f.sel} (bord ${f.edge}, fond ${f.fill})`);
      for (const l of r.logo) {
        if (!l.minOk) problems.push(`logo trop petit ${l.src} ${l.w}px`);
        if (l.viol.length) problems.push(`zone de protection (${l.X}px) ${l.src} : ${l.viol.join(', ')}`);
        if (l.offscreen) problems.push(`logo hors ecran ${l.src}`);
      }
      report.push({ d, sn, page: p.file, min: +r.min.toFixed(2), unknown: r.unknown.length, logos: r.logo.length, problems });
      if (problems.length) { bad += problems.length; console.log(`${d}/${p.file} @${w}`); problems.slice(0, 12).forEach((x) => console.log('   ' + x)); }
    }
    await ctx.close();
  }
  const mine = report.filter((x) => x.d === d);
  console.log(`== ${d} : ${mine.length} rendus, contraste minimal mesure ${Math.min(...mine.map((x) => x.min))}:1, problemes ${mine.reduce((a, x) => a + x.problems.length, 0)}, zones a controler a l'oeil (fond image) ${mine.reduce((a, x) => a + x.unknown, 0)}`);
}
if (!only.length) {
  for (const [sn, w] of [['mobile', 390], ['tablette', 820], ['bureau', 1280]]) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(pathToFileURL(join(root, 'index.html')).href);
    await page.evaluate(() => document.fonts.ready);
    const r = await page.evaluate(inPage);
    const problems = [];
    if (r.overflow > 0) problems.push(`debordement horizontal ${r.overflow}px`);
    for (const c of r.contrast) problems.push(`contraste ${c.ratio}<${c.need} ${c.sel} "${c.text}"`);
    for (const l of r.logo) { if (!l.minOk) problems.push(`logo trop petit ${l.w}px`); if (l.viol.length) problems.push(`zone de protection ${l.viol.join(', ')}`); }
    console.log(`index.html @${w} : contraste min ${r.min.toFixed(2)}:1, problemes ${problems.length}`, problems.slice(0, 6));
    bad += problems.length;
    await ctx.close();
  }
}
writeFileSync(join(dirname(fileURLToPath(import.meta.url)), 'audit-report.json'), JSON.stringify(report, null, 1));
console.log(bad ? `TOTAL problemes : ${bad}` : 'Aucun probleme');
await browser.close();
