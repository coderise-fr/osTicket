// Verifie que chaque element atteint au clavier (Tab) affiche un contour de focus visible (>= 2 px, style non nul).
// Usage : node focus.mjs  (PW_CORE comme pour capture.mjs)
import { readdirSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import os from 'node:os';

const { chromium } = await import(process.env.PW_CORE ? pathToFileURL(process.env.PW_CORE).href : 'playwright-core');
const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const pw = join(os.homedir(), 'AppData/Local/ms-playwright');
const dirs = readdirSync(pw).filter((d) => d.startsWith('chromium-')).sort();
const exe = join(pw, dirs[dirs.length - 1], 'chrome-win64', 'chrome.exe');
const browser = await chromium.launch({ executablePath: existsSync(exe) ? exe : undefined });
let bad = 0;
for (const d of readdirSync(root).filter((x) => /^0\d-/.test(x))) {
  for (const f of ['index.html', 'ouvrir-un-ticket.html', 'suivi-ticket.html', 'mes-tickets.html', 'article.html']) {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    await page.goto(pathToFileURL(join(root, d, f)).href);
    let n = 0; const seen = new Set();
    for (let i = 0; i < 80; i++) {
      await page.keyboard.press('Tab');
      const r = await page.evaluate(() => {
        const el = document.activeElement; if (!el || el === document.body) return null; if (el.dataset.fseen) return { dup: true }; el.dataset.fseen = '1';
        const cs = getComputedStyle(el);
        const w = parseFloat(cs.outlineWidth);
        // un champ de formulaire peut aussi montrer son focus par un changement de bordure ; on exige quand meme le contour
        return { id: el.tagName + '.' + (el.className || '').toString().slice(0, 24) + (el.textContent || '').trim().slice(0, 14), w, style: cs.outlineStyle, color: cs.outlineColor };
      });
      if (!r) continue; if (r.dup) break;
      seen.add(r.id); n++;
      if (!(r.w >= 2 && r.style !== 'none')) { bad++; console.log(`${d}/${f} : pas de contour de focus sur ${r.id} (${r.w}px ${r.style})`); }
    }
    console.log(`${d}/${f} : ${n} elements focalisables parcourus`);
    await page.close();
  }
}
console.log(bad ? `TOTAL sans contour : ${bad}` : 'Tous les elements parcourus ont un contour de focus visible');
await browser.close();
