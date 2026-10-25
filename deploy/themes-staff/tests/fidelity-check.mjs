#!/usr/bin/env node
// Fidelity regression check: computed styles of the static mockups (docs/theme-mockups-admin)
// against the real staff panel on the local demo. Stops font sizes shrinking silently.
//
// Usage:  LOGIN_PASS=<password> node deploy/themes-staff/tests/fidelity-check.mjs
// Env:    LOGIN_PASS (required, never printed), LOGIN_USER (dev.admin), BASE (http://127.0.0.1:8480),
//         PW_CORE (playwright-core module name or path), CHROME (chromium.exe path),
//         VIEW_W / VIEW_H (1280 / 800), MOCKUP_DIR (default: <repo>/docs/theme-mockups-admin).
// Output: one line per metric, "OK|DIFF  <name>  mockup=<v>  real=<v>". Exit code = number of DIFF
//         lines (capped at 125). Exit 2 on configuration or login errors (not counted as diffs).
//
// Real-page selectors come from the overlay (coderise.css / coderise.js) and core templates.
// Chosen: title '#content .tixTitle, #content h2' (core ticket-view uses .tixTitle); props
// 'aside.cdr-props table.ticket_info th/td' (coderise.js moves ticket_info into the aside);
// thread '.thread-body' and '.thread-event'; table 'table.list.queue'; tabs 'ul.tabs li a';
// rail queues '.rail .queues #sub_nav > li > a'. Unverified guesses (may print MISSING):
// '.mode a', '.topbar .btn', '.gauge-txt', '.panel-h h2', '.crumbs', '.btn'.

import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join, resolve, isAbsolute } from 'node:path';

// { metric: '<pair name> <property>', reason: '...' }: printed as OK with the reason, not counted.
const ALLOWED_DIFFS = [];

const env = process.env;
const PW_CORE = env.PW_CORE || 'playwright-core';
const CHROME = env.CHROME || 'C:/Users/Julien/AppData/Local/ms-playwright/chromium-1248/chrome-win64/chrome.exe';
const BASE = env.BASE || 'http://127.0.0.1:8480';
const LOGIN_USER = env.LOGIN_USER || 'dev.admin';
const LOGIN_PASS = env.LOGIN_PASS;
const VIEW_W = Number(env.VIEW_W || 1280);
const VIEW_H = Number(env.VIEW_H || 800);
const HERE = dirname(fileURLToPath(import.meta.url));
const MOCKUP_DIR = env.MOCKUP_DIR || join(HERE, '..', '..', '..', 'docs', 'theme-mockups-admin');

const KINDS = {
  queue: { mock: 'tickets.html', real: '/scp/tickets.php' },
  ticket: { mock: 'ticket.html', real: '/scp/tickets.php?id=2' },
};

// name, kind, mock/real selector, css properties, px tolerance (default 0.5).
const PAIRS = [
  { name: 'rail link', kind: 'queue', mock: '.rail a.item', real: '.rail a.item', props: ['font-size', 'font-weight'] },
  { name: 'mode switch link', kind: 'queue', mock: '.mode a', real: '.mode a', props: ['font-size', 'font-weight'] },
  { name: 'rail queue item', kind: 'queue', mock: '.rail .queues a', real: '.rail .queues #sub_nav > li > a', props: ['font-size', 'height'] },
  { name: 'topbar New ticket button', kind: 'queue', mock: '.topbar .btn-primary', real: '.topbar .btn', props: ['font-size', 'height', 'background-color'] },
  { name: 'queue h1 title', kind: 'queue', mock: 'h1', real: '.cdr-titlebar h1', props: ['font-size', 'font-weight', 'font-family'] },
  { name: 'table header th', kind: 'queue', mock: '.data thead th', real: 'table.list.queue thead th', props: ['font-size'] },
  { name: 'table row td padding', kind: 'queue', mock: '.data tbody td', real: 'table.list.queue tbody td', props: ['padding-top', 'padding-left'] },
  { name: 'table row td', kind: 'queue', mock: '.data tbody td', real: 'table.list.queue tbody td', props: ['font-size'] },
  { name: 'gauge text', kind: 'queue', mock: '.gauge-txt', real: '.gauge-txt', props: ['font-size'] },
  { name: 'ticket h1 title', kind: 'ticket', mock: 'h1', real: '#content .tixTitle h3', props: ['font-size', 'font-weight', 'font-family'] },
  { name: 'tabs link', kind: 'ticket', mock: '.tabs a', real: 'ul.tabs li a', props: ['font-size'] },
  { name: 'panel title', kind: 'ticket', mock: '.panel-h h2', real: '.panel-h h2', props: ['font-size'] },
  { name: 'props label', kind: 'ticket', mock: '.props dt', real: 'aside.cdr-props table.ticket_info th', props: ['font-size', 'color'] },
  { name: 'props value', kind: 'ticket', mock: '.props dd', real: 'aside.cdr-props table.ticket_info table td', props: ['font-size', 'font-weight'] },
  { name: 'thread body', kind: 'ticket', mock: '.entry .body', real: '.thread-body', props: ['font-size', 'font-family'] },
  { name: 'event', kind: 'ticket', mock: '.event', real: '.thread-event', props: ['font-size'] },
  { name: 'button', kind: 'ticket', mock: '.btn', real: '.btn', props: ['font-size'] },
  { name: 'breadcrumb', kind: 'ticket', mock: '.crumbs', real: '.crumbs', props: ['font-size'] },
];

async function login(page) {
  await page.goto(BASE + '/scp/login.php', { waitUntil: 'load' });
  await page.fill('#name', LOGIN_USER);
  await page.fill('#pass', LOGIN_PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}),
    page.click('#login button[type=submit]'),
  ]);
  await page.waitForTimeout(800);
  if (page.url().includes('login.php')) throw new Error(`login failed for ${LOGIN_USER}`);
}

// Returns { <pair index>: { <prop>: value } | null (element missing) } for one page.
async function measure(page, url, targets) {
  await page.goto(url, { waitUntil: 'load' });
  await page.waitForTimeout(500);
  return page.evaluate((targets) => {
    const res = {};
    for (const t of targets) {
      const el = document.querySelector(t.sel);
      if (!el) { res[t.key] = null; continue; }
      const cs = getComputedStyle(el), r = {};
      for (const p of t.props) {
        if (p === 'height') r[p] = el.getBoundingClientRect().height + 'px';
        else if (p === 'font-family') r[p] = cs.fontFamily.split(',')[0].trim().replace(/["']/g, '');
        else r[p] = cs.getPropertyValue(p);
      }
      res[t.key] = r;
    }
    return res;
  }, targets);
}

async function run() {
  if (!LOGIN_PASS) { console.error('LOGIN_PASS is required'); return 2; }
  const core = /^([./]|[A-Za-z]:)/.test(PW_CORE) ? pathToFileURL(resolve(PW_CORE)).href : PW_CORE;
  const { chromium } = await import(core);
  const browser = await chromium.launch({ executablePath: CHROME, headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: VIEW_W, height: VIEW_H }, locale: 'fr-FR' });
    const page = await context.newPage();
    await login(page);
    let diffs = 0;
    for (const [kind, urls] of Object.entries(KINDS)) {
      const pairs = PAIRS.map((p, i) => ({ ...p, i })).filter((p) => p.kind === kind);
      const mock = await measure(page, pathToFileURL(join(MOCKUP_DIR, urls.mock)).href,
        pairs.map((p) => ({ key: String(p.i), sel: p.mock, props: p.props })));
      const real = await measure(page, BASE + urls.real,
        pairs.map((p) => ({ key: String(p.i), sel: p.real, props: p.props })));
      for (const p of pairs) {
        for (const prop of p.props) {
          const name = `${p.name} ${prop}`;
          const a = mock[p.i]?.[prop] ?? 'MISSING';
          const b = real[p.i]?.[prop] ?? 'MISSING';
          let same;
          if (a === 'MISSING' || b === 'MISSING') same = false;
          else if (/px$/.test(a) && /px$/.test(b)) same = Math.abs(parseFloat(a) - parseFloat(b)) <= (p.tol ?? 0.5);
          else same = a === b;
          const allowed = ALLOWED_DIFFS.find((d) => d.metric === name);
          const ok = same || !!allowed;
          if (!ok) diffs++;
          const note = allowed && !same ? `  (allowed: ${allowed.reason})` : '';
          console.log(`${ok ? 'OK' : 'DIFF'}  ${name}  mockup=${a}  real=${b}${note}`);
        }
      }
    }
    return diffs;
  } finally {
    await browser.close();
  }
}

run()
  .then((code) => { process.exitCode = Math.min(code, 125); })
  .catch((e) => {
    // Scrub the password from any error text before printing.
    console.error('error: ' + String(e.message).split(LOGIN_PASS || '\u0000').join('***'));
    process.exitCode = 2;
  });
