#!/usr/bin/env node
// Fidelity regression check: computed styles of the static mockups (docs/theme-mockups-admin)
// against the real staff panel on the local demo. Stops font sizes shrinking silently.
//
// Usage:  LOGIN_PASS=<password> node deploy/themes-staff/tests/fidelity-check.mjs
// Env:    LOGIN_PASS (required, never printed), LOGIN_USER (dev.admin), BASE (http://127.0.0.1:8480),
//         PW_CORE (playwright-core module name or path), CHROME (chromium.exe path),
//         VIEW_W / VIEW_H (1280 / 800), MOCKUP_DIR (default: <repo>/docs/theme-mockups-admin).
// Output: one line per metric, "OK|DIFF  <name>  mockup=<v>  real=<v>", then "OK n  DIFF n
//         ALLOWED n". Exit code = number of DIFF lines (capped at 125). Exit 2 on configuration
//         or login errors (not counted as diffs). A missing element prints MISSING (a DIFF).
//
// Real-page selectors (verified on the demo, 25/10/2026) come from the overlay and the core
// templates: queue title '.cdr-titlebar h1' (queue.js), ticket subject '#content .tixTitle h3',
// property values 'aside.cdr-props table.ticket_info table td' (inner cells, not the column
// cells), thread entries '.thread-entry > .header > b|.cdr-role|time', '.cdr-av' (ticket.js),
// queue '.cdr-bulk .cdr-sel', '.cdr-unassigned', '.cdr-who .cdr-av' (queue.js). Demo data:
// ticket 2 has a client message, replies and events; ticket 1 the internal note.
// Identity block: '.cdr-idline .idline|.cdr-id|.sep|.status|.plan|.urg', '#content p.cdr-sub'
// (header.inc.php, cdr_ticket_idline), labelled buttons 'a.action-button.cdr-has-lbl' and
// '.cdr-resolve' (ticket-chrome.js; ticket 1 is open). Ticket 45 is open, Standard and High
// (urgent). Props 'top'/'left'/'width' are getBoundingClientRect() values at scroll 0.
// Queue columns (queue-tickets.tmpl.php): contract chip 'td > .stack .plan', client line
// 'small.cdr-client', first-reply cell '.cdr-gauge-col', top-level queue counts '.cdr-qcount'
// (coderise.js). 'row-top' = top of the element minus the top of its table row; 'col-delta' =
// visible column index of the element's cell minus that of the pair's mockRef / realRef cell
// (column order, independent of the column widths). 'right' = getBoundingClientRect().right.
// 'text-row-top' = top of the element's first text line (Range) minus the top of its row: the
// queue subject link is a block since it is clamped to two lines (queue.css), the mockup's inline.
// Wave 4: action group wrappers '.cdr-acts' / '.cdr-tools', title block 'section.cdr-idline'
// (the subject is moved into it), "Log" tab 'a#cdr-journal-tab', reply options line
// 'form#reply > .cdr-opts' (ticket-chrome.js). A pair with minWidth / maxWidth is only measured
// at those viewport widths (layout that differs with the width, like the mockup's title row).

import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

// { metric: '<pair name> <property>', reason: '...' }: printed as OK with the reason, not counted.
const ALLOWED_DIFFS = [
  { metric: 'queue number to subject columns col-delta',
    reason: 'the demo queue has "Last Updated" between them: column set and order are queue settings (admin, class 3)' },
];

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
  // demo ticket 1 holds the internal note
  note: { mock: 'ticket.html', real: '/scp/tickets.php?id=1' },
  // demo ticket 45: open, Standard contract, High priority (urgent)
  urgent: { mock: 'ticket.html', real: '/scp/tickets.php?id=45' },
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
  { name: 'queue subtitle', kind: 'queue', mock: '.title-row .sub', real: '.cdr-titlebar .sub', props: ['font-size', 'color'] },
  { name: 'title button', kind: 'queue', mock: '.title-row .btn', real: '.cdr-titlebar .btn', props: ['font-size', 'font-weight', 'height'] },
  { name: 'bulk counter', kind: 'queue', mock: '.bulk b', real: '.cdr-bulk .cdr-sel', props: ['font-size', 'font-weight', 'font-family', 'color'] },
  { name: 'bulk button', kind: 'queue', mock: '.bulk .btn', real: '.cdr-bulk .action-button', props: ['font-size', 'height'] },
  { name: 'ticket number link', kind: 'queue', mock: '.data .c-n a', real: 'table.queue tbody a.preview', props: ['font-size', 'color'] },
  { name: 'unassigned', kind: 'queue', mock: '.data .unassigned', real: 'table.queue .cdr-unassigned', props: ['font-style', 'color'] },
  { name: 'assignee avatar', kind: 'queue', mock: '.data .avatar', real: 'table.queue .cdr-who:not(.is-none) .cdr-av', props: ['width', 'font-size'] },
  { name: 'ticket h1 title', kind: 'ticket', mock: 'h1', real: '#content .tixTitle h3', props: ['font-size', 'font-weight', 'font-family'] },
  { name: 'tabs link', kind: 'ticket', mock: '.tabs a', real: 'ul.tabs li a', props: ['font-size', 'height'] },
  { name: 'panel title', kind: 'ticket', mock: '.panel-h h2', real: '.panel-h h2', props: ['font-size'] },
  { name: 'props panel title', kind: 'ticket', mock: '.panel-h h2', real: 'aside.cdr-props h2.cdr-title', props: ['font-size', 'font-weight'] },
  { name: 'entry avatar', kind: 'ticket', mock: '.entry .avatar', real: '.thread-entry > .cdr-av', props: ['width', 'font-size'] },
  { name: 'entry author', kind: 'ticket', mock: '.entry .head strong', real: '.thread-entry > .header > b', props: ['font-size', 'font-family'] },
  { name: 'entry role', kind: 'ticket', mock: '.entry .head .role', real: '.thread-entry > .header > .cdr-role', props: ['font-size', 'color'] },
  { name: 'entry time', kind: 'ticket', mock: '.entry .head time', real: '.thread-entry > .header time', props: ['font-size', 'color'] },
  { name: 'note flag', kind: 'note', mock: '.note-flag', real: '.thread-entry > .header > .cdr-role-note', props: ['font-size', 'font-weight', 'color'] },
  { name: 'props label', kind: 'ticket', mock: '.props dt', real: 'aside.cdr-props table.ticket_info th', props: ['font-size', 'color'] },
  { name: 'props value', kind: 'ticket', mock: '.props dd', real: 'aside.cdr-props table.ticket_info table td', props: ['font-size', 'font-weight'] },
  { name: 'thread body', kind: 'ticket', mock: '.entry .body', real: '.thread-body', props: ['font-size', 'font-family'] },
  { name: 'event', kind: 'ticket', mock: '.event', real: '.thread-event', props: ['font-size'] },
  { name: 'button', kind: 'ticket', mock: '.btn', real: '.btn', props: ['font-size'] },
  { name: 'breadcrumb', kind: 'ticket', mock: '.crumbs', real: '.crumbs', props: ['font-size'] },
  // Identity block (cdr_ticket_idline) and labelled action buttons. top/left: layout (viewport px).
  { name: 'idline', kind: 'ticket', mock: '.idline', real: '.cdr-idline .idline', props: ['font-size', 'font-weight', 'font-family', 'line-height', 'color', 'column-gap', 'top', 'left'] },
  { name: 'idline number', kind: 'ticket', mock: '.idline > span:first-child', real: '.cdr-idline .cdr-id', props: ['font-size', 'font-weight', 'color', 'height'] },
  { name: 'idline separator', kind: 'ticket', mock: '.idline .sep', real: '.cdr-idline .sep', props: ['width', 'height', 'background-color', 'top'] },
  { name: 'idline state', kind: 'ticket', mock: '.idline .status', real: '.cdr-idline .status', props: ['font-size', 'font-weight', 'font-family', 'height', 'top'] },
  { name: 'idline contract', kind: 'ticket', mock: '.idline .plan', real: '.cdr-idline .plan', props: ['font-size', 'font-weight', 'font-family', 'padding-left', 'border-radius', 'background-color', 'color', 'height', 'top'] },
  { name: 'idline urgency', kind: 'urgent', mock: '.idline .urg', real: '.cdr-idline .urg', props: ['font-size', 'font-weight', 'color', 'height', 'top'] },
  { name: 'ticket h1 position', kind: 'ticket', mock: 'h1', real: '#content .tixTitle h3', props: ['top', 'left', 'line-height'] },
  { name: 'client subtitle', kind: 'ticket', mock: '.title-row .sub', real: '#content p.cdr-sub', props: ['font-size', 'font-weight', 'line-height', 'color', 'top', 'left'] },
  { name: 'labelled button (Edit)', kind: 'ticket', mock: '.t-actions .btn-line', real: '#content .sticky.bar a.action-button.cdr-has-lbl', props: ['font-size', 'font-weight', 'font-family', 'height', 'width', 'border-top-color', 'border-top-left-radius', 'background-color', 'top'] },
  // Action group placement (wave 4). The mockup's .title-row wraps its buttons under the title
  // block below ~1558 px and puts them at the right of the row above. The real group is wider
  // (core's print-options caret on Print, +30 px), so its start is compared where it starts the
  // row (narrow) and its end and the resolve button where they end it (wide).
  { name: 'labelled button (Edit) start', kind: 'ticket', maxWidth: 1559, mock: '.t-actions .btn-line', real: '#content .sticky.bar a.action-button.cdr-has-lbl', props: ['left'] },
  { name: 'action group end', kind: 'note', minWidth: 1560, mock: '.t-actions', real: '#content .sticky.bar .cdr-acts', props: ['right', 'top'] },
  { name: 'resolve button position', kind: 'note', minWidth: 1560, mock: '.t-actions .btn-primary', real: '#content .sticky.bar .cdr-resolve', props: ['left'] },
  { name: 'title block', kind: 'ticket', mock: '.title-row > div', real: '#content > section.cdr-idline', props: ['top', 'left'] },
  { name: 'resolve button', kind: 'note', mock: '.t-actions .btn-primary', real: '#content .sticky.bar .cdr-resolve', props: ['font-size', 'font-weight', 'height', 'width', 'background-color', 'color', 'border-top-left-radius', 'top'] },
  { name: 'ticket tabs position', kind: 'ticket', mock: '.tabs', real: 'ul#ticket_tabs', props: ['top', 'left'] },
  // "Log" tab (ticket-chrome.js) and the line above the reply editor (mockup .compose .to)
  { name: 'log tab', kind: 'ticket', mock: '.tabs a:nth-child(3)', real: 'ul#ticket_tabs a#cdr-journal-tab', props: ['font-size', 'font-weight', 'font-family', 'color', 'height', 'top'] },
  { name: 'compose to line', kind: 'ticket', mock: '.compose .to', real: '#response_options > form#reply > .cdr-opts', props: ['font-size', 'line-height', 'color', 'padding-top', 'padding-left', 'border-bottom-width', 'border-bottom-color'] },
  // Queue, wave 3: "Ticket et contrat", "Sujet et client", "Premier retour" after the subject, rail counts.
  { name: 'queue Priority chip', kind: 'queue', mock: '.data .c-n .plan-priority', real: 'table.queue tbody td > .stack .plan-priority', props: ['font-size', 'font-weight', 'font-family', 'padding-left', 'border-top-left-radius', 'background-color', 'color', 'height', 'row-top'] },
  { name: 'queue Standard chip', kind: 'queue', mock: '.data .c-n .plan-standard', real: 'table.queue tbody td > .stack .plan-standard', props: ['font-size', 'color', 'box-shadow', 'height', 'row-top'] },
  { name: 'queue subject link', kind: 'queue', mock: '.data .subj a', real: 'table.queue tbody td .truncate a', props: ['font-size', 'font-weight', 'text-row-top'] },
  { name: 'queue client line', kind: 'queue', mock: '.data .subj small', real: 'table.queue tbody td > small.cdr-client', props: ['font-size', 'font-weight', 'font-family', 'color', 'display'] },
  { name: 'queue first reply follows subject', kind: 'queue', mock: '.data thead th[aria-sort]', mockRef: '.data thead th:nth-child(3)', real: 'table.queue thead th.cdr-gauge-col', realRef: 'table.queue thead th[data-id="3"]', props: ['col-delta'] },
  { name: 'queue first reply cell follows the client cell', kind: 'queue', mock: '.data tbody tr:first-child td:has(.gauge)', mockRef: '.data tbody tr:first-child td.subj', real: 'table.queue tbody tr:has(small.cdr-client) td.cdr-gauge-col', realRef: 'table.queue tbody tr:has(small.cdr-client) td:has(> small.cdr-client)', props: ['col-delta', 'padding-top'] },
  { name: 'queue number to subject columns', kind: 'queue', mock: '.data tbody tr:first-child td.subj', mockRef: '.data tbody tr:first-child td.c-n', real: 'table.queue tbody tr:has(small.cdr-client) td:has(> small.cdr-client)', realRef: 'table.queue tbody tr:has(small.cdr-client) td:has(a.preview)', props: ['col-delta'] },
  { name: 'queue From column hidden', kind: 'queue', mock: '.data thead th:nth-child(3)', real: 'table.queue thead th.cdr-col-from', props: ['display'], expect: { mock: 'table-cell', real: 'none' } },
  { name: 'rail top-level queue count', kind: 'queue', mock: '.rail .queues .badge', real: '#cdr-queues .cdr-qcount:not(.cdr-zero)', props: ['font-size', 'font-weight', 'height', 'min-width', 'background-color'] },
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
  // top/left are viewport positions: measured from the top of the page (thread.js scrolls)
  // (thread.js scrolls to the last entry after load, with an animation): back to the top until it stays
  for (let i = 0, still = 0; i < 20 && still < 2; i++) {
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(300);
    still = (await page.evaluate(() => window.scrollY)) === 0 ? still + 1 : 0;
  }
  await page.waitForTimeout(300);
  return page.evaluate((targets) => {
    const res = {};
    for (const t of targets) {
      const el = document.querySelector(t.sel);
      if (!el) { res[t.key] = null; continue; }
      // visible column index of the cell holding el (cells hidden by CSS are not columns)
      const colIndex = (node) => {
        const cell = node && node.closest('td, th');
        if (!cell) return null;
        return [...cell.parentElement.children].filter((c) => getComputedStyle(c).display !== 'none').indexOf(cell);
      };
      const cs = getComputedStyle(el), r = {};
      for (const p of t.props) {
        if (p === 'height') r[p] = el.getBoundingClientRect().height + 'px';
        else if (p === 'width') r[p] = el.getBoundingClientRect().width + 'px';
        else if (p === 'top' || p === 'left' || p === 'right') r[p] = el.getBoundingClientRect()[p] + 'px';
        else if (p === 'row-top') {
          const tr = el.closest('tr');
          r[p] = tr ? (el.getBoundingClientRect().top - tr.getBoundingClientRect().top) + 'px' : null;
        }
        else if (p === 'text-row-top') {
          // first line of the element's text (a Range), so an inline link and a block one
          // (the clamped queue subject) compare by where the text is, not by the box
          const tr = el.closest('tr'), rg = document.createRange();
          rg.selectNodeContents(el);
          const first = rg.getClientRects()[0];
          r[p] = tr && first ? (first.top - tr.getBoundingClientRect().top) + 'px' : null;
        }
        else if (p === 'col-delta') {
          const ref = t.ref ? document.querySelector(t.ref) : null, a = colIndex(el), b = colIndex(ref);
          r[p] = a === null || b === null || a < 0 || b < 0 ? null : String(a - b);
        }
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
    let diffs = 0, oks = 0, allowedN = 0;
    for (const [kind, urls] of Object.entries(KINDS)) {
      // minWidth / maxWidth: a pair measured only at the viewport widths where it applies
      const pairs = PAIRS.map((p, i) => ({ ...p, i })).filter((p) => p.kind === kind
        && VIEW_W >= (p.minWidth ?? 0) && VIEW_W <= (p.maxWidth ?? Infinity));
      const mock = await measure(page, pathToFileURL(join(MOCKUP_DIR, urls.mock)).href,
        pairs.map((p) => ({ key: String(p.i), sel: p.mock, ref: p.mockRef, props: p.props })));
      const real = await measure(page, BASE + urls.real,
        pairs.map((p) => ({ key: String(p.i), sel: p.real, ref: p.realRef, props: p.props })));
      for (const p of pairs) {
        for (const prop of p.props) {
          const name = `${p.name} ${prop}`;
          const a = mock[p.i]?.[prop] ?? 'MISSING';
          const b = real[p.i]?.[prop] ?? 'MISSING';
          let same;
          if (p.expect) same = a === p.expect.mock && b === p.expect.real;
          else if (a === 'MISSING' || b === 'MISSING') same = false;
          else if (/px$/.test(a) && /px$/.test(b)) same = Math.abs(parseFloat(a) - parseFloat(b)) <= (p.tol ?? 0.5);
          else same = a === b;
          const allowed = ALLOWED_DIFFS.find((d) => d.metric === name);
          const ok = same || !!allowed;
          if (!ok) diffs++;
          else if (same) oks++;
          else allowedN++;
          const note = allowed && !same ? `  (allowed: ${allowed.reason})` : '';
          console.log(`${ok ? 'OK' : 'DIFF'}  ${name}  mockup=${a}  real=${b}${note}`);
        }
      }
    }
    console.log(`
OK ${oks}  DIFF ${diffs}  ALLOWED ${allowedN}`);
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
