// Captures PNG pleine hauteur (bureau 1280 px, mobile 390 px ; tablette 820 px pour deux pages) de chaque page.
// Usage : node capture.mjs [01 02 ...]
// Prerequis : playwright-core (npm i playwright-core --ignore-scripts dans un dossier temporaire hors depot ; variable PW_CORE =
// chemin de son index.mjs) et un Chromium dans %LOCALAPPDATA%/ms-playwright.
import { readdirSync, mkdirSync, existsSync } from 'node:fs';
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
const sizes = [['bureau', 1280, 800], ['mobile', 390, 844], ['tablette', 820, 1100]];
for (const d of readdirSync(root).filter((d) => /^0\d-/.test(d) && (!only.length || only.some((o) => d.startsWith(o))))) {
  mkdirSync(join(root, d, 'captures'), { recursive: true });
  for (const [sn, w, h] of sizes) {
    const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1, locale: 'fr-FR' });
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('requestfailed', (r) => errors.push('FAILED ' + r.url()));
    for (const p of PAGES) {
      if (sn === 'tablette' && !['home', 'ticket'].includes(p.key)) continue;
      await page.goto(pathToFileURL(join(root, d, p.file)).href);
      await page.evaluate(() => document.fonts.ready);
      await page.waitForTimeout(120);
      await page.screenshot({ path: join(root, d, 'captures', `${p.shot}-${sn}.png`), fullPage: true });
    }
    if (errors.length) console.log(d, sn, [...new Set(errors)].slice(0, 5));
    await ctx.close();
  }
  console.log('ok', d);
}
await browser.close();
