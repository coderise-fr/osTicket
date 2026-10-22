// Genere les pages HTML des 5 maquettes : node docs/theme-mockups-coderise/_build/build.mjs [01 02 ...]
// Les feuilles de style (theme.css) sont ecrites a la main dans chaque dossier ; seul le HTML est genere.
import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { PAGES } from './common.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..');
const only = process.argv.slice(2);
for (const id of ['01', '02', '03', '04', '05'].filter((i) => !only.length || only.includes(i))) {
  const file = join(here, `p${id}.mjs`);
  if (!existsSync(file)) continue;
  const mod = (await import(pathToFileURL(file).href)).default;
  const out = join(root, mod.dir);
  mkdirSync(out, { recursive: true });
  for (const p of PAGES) writeFileSync(join(out, p.file), mod.pages[p.key]());
  console.log(`${mod.dir}: ${PAGES.length} pages`);
}
