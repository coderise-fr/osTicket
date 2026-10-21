// Captures PNG (bureau 1280 px, mobile 390 px, pleine hauteur) de chaque page de chaque proposition.
// Usage : npm i playwright-core (dans un dossier temporaire, hors depot) puis, depuis ce dossier :
//   node capture.mjs <chemin absolu de docs/theme-mockups> [01 02 ...]
// Utilise le Chromium installe dans %LOCALAPPDATA%/ms-playwright (adapter le chemin sur une autre machine).
import { chromium } from 'playwright-core';
import { readdirSync, mkdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import os from 'node:os';

const root = process.argv[2];
const only = process.argv.slice(3);
const pw = join(os.homedir(), 'AppData/Local/ms-playwright');
const dirs = readdirSync(pw).filter((d) => d.startsWith('chromium-')).sort();
const exe = join(pw, dirs[dirs.length - 1], 'chrome-win64', 'chrome.exe');
const browser = await chromium.launch({ executablePath: existsSync(exe) ? exe : undefined });
const pages = [['index', 'accueil'], ['ouvrir-un-ticket', 'ouverture-ticket'], ['connexion', 'connexion'], ['suivi-ticket', 'suivi-ticket'], ['mes-tickets', 'mes-tickets'], ['base-de-connaissances', 'base-de-connaissances']];
const sizes = [['bureau', 1280, 800], ['mobile', 390, 844]];
for (const d of readdirSync(root).filter((d) => /^0\d-/.test(d) && (!only.length || only.some((o) => d.startsWith(o))))) {
  mkdirSync(join(root, d, 'captures'), { recursive: true });
  for (const [sn, w, h] of sizes) {
    const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1, locale: 'fr-FR' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('requestfailed', (r) => errors.push('FAILED ' + r.url()));
    for (const [f, name] of pages) {
      await page.goto(pathToFileURL(join(root, d, f + '.html')).href);
      await page.evaluate(() => document.fonts.ready);
      await page.waitForTimeout(150);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      if (overflow > 0) console.log(`  ! ${d}/${f} ${sn}: debordement horizontal de ${overflow}px`);
      await page.screenshot({ path: join(root, d, 'captures', `${name}-${sn}.png`), fullPage: true });
    }
    if (errors.length) console.log(d, sn, [...new Set(errors)].slice(0, 5));
    await ctx.close();
  }
  console.log('ok', d);
}
await browser.close();
