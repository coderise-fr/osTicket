// Genere les pages HTML des maquettes : node docs/theme-mockups/_build/build.mjs [01 02 ...]
// Les feuilles de style (theme.css) sont ecrites a la main dans chaque dossier ; seul le HTML est genere.
import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PAGES } from './common.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const only = process.argv.slice(2);
const ids = ['01', '02', '03', '04', '05'].filter((i) => !only.length || only.includes(i));

for (const id of ids) {
  const file = join(dirname(fileURLToPath(import.meta.url)), `p${id}.mjs`);
  if (!existsSync(file)) continue;
  const mod = (await import(new URL(`file:///${file.replace(/\\/g, '/')}`).href)).default;
  const out = join(root, mod.dir);
  mkdirSync(out, { recursive: true });
  for (const p of PAGES) {
    writeFileSync(join(out, p.file), mod.pages[p.key]());
  }
  console.log(`${mod.dir}: ${PAGES.length} pages`);
}
