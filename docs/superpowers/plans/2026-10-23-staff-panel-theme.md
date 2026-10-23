# Staff panel and admin theme (Coderise) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the validated mockup as a real, installable theme for osTicket's agent panel (`/scp/`) and admin panel, in the Coderise charter.

**Architecture:** A new theme package under `deploy/themes-staff/coderise-agent/` (parallel to the client themes in `deploy/themes/`, same `overlay/` + `MANIFEST` + `apply.sh`/`remove.sh`/`check-drift.sh` pattern) that the Docker build copies over the osTicket tree. It replaces only the staff shell templates (`header.inc.php`, `footer.inc.php`, login templates, the queue row template) and adds one stylesheet that is loaded **after** `scp/css/scp.css`, so every other staff screen keeps working and is restyled by CSS. The rail, mode switch and topbar are emitted by the replaced `header.inc.php` inside `#pjax-container`, so PJAX navigation refreshes them.

**Tech Stack:** PHP 8.2 (osTicket templates), plain CSS, a small vanilla JS file, POSIX shell for the package scripts, Docker (`deploy/docker/scripts/local-demo.sh`) and Playwright MCP for visual checks. No build step, no external requests.

**Spec:** `docs/theme-mockups-admin/README.md` and the validated mockup `docs/theme-mockups-admin/` (`theme.css` is the visual source of truth; `_build/build.mjs` has the markup of each component). Validated by the user on 2026-10-23.

## Global Constraints

- Repo rules (`CLAUDE.md`): **no Claude/Anthropic attribution in commits** (overrides any default trailer); never build the Docker image from the working tree (commit first, build with `deploy/docker/scripts/build.sh` or `local-demo.sh`, which use `git archive` of `HEAD`); do not `export MSYS_NO_PATHCONV=1` globally (inline only for `docker exec`/`docker run` with absolute container paths).
- New PHP files: GPL header block, `vim: expandtab sw=4 ts=4 sts=4`, `if (!defined('INCLUDE_DIR')) die('Access Denied');` unless the file is a web entry point. 4-space indent, no tabs, LF line endings in every new file.
- Never modify the osTicket tree itself: the theme lives only in `deploy/themes-staff/coderise-agent/overlay/`. Do not touch `deploy/themes/` (client themes): their `apply.sh` scripts must keep working with the staff theme installed (this is why the staff theme is **not** under `deploy/themes/`: a client `apply.sh` treats every `deploy/themes/*/` directory whose overlay files are present in the target as "another theme installed" and refuses).
- The demo's login credentials are in `.local-demo/credentials.txt`; do not read that file yourself. Ask the user for a staff and an admin login when a verification step needs one.
- Every user-visible string goes through `cdr__()` (osTicket's `__()` first, then `include/staff/coderise/i18n/fr.php`); every output of user data goes through `Format::htmlchars()` (or `Format::display()` for rich text as the original templates do).
- Charter (values from `docs/theme-mockups-coderise/_shared/tokens.css`): nuit `#000F26`, cyan `#10C9DE`, cyan profond `#0A7C8C`, ardoise `#5B6675`, gris `#E8ECF1`, voile `#E3F8FB`; état: erreur `#B3261E`/`#FCECEA`, succès `#0F6B4B`/`#E4F5EC`, alerte `#7A4B00`/`#FFF3D1`. **Cyan is never text on white; ardoise is never text on nuit** (use white at 78 %+); a cyan button carries nuit text. Fonts: Montserrat 600/700/800 and Inter 400/500/600, local WOFF2 only (copy from `docs/theme-mockups-coderise/_shared/fonts/`, with their OFL files). Body text 13.5 px in the panel.
- Hardening is not weakened: osTicket's staff CSP (`script-src 'self' 'unsafe-inline' 'unsafe-eval'`) is kept as is; no inline event handlers added, no CDN, no `eval`.
- Keep every core contract the staff JS relies on: `#pjax-container`, `#content`, `ul#nav`/`#sub_nav` classes `jb-overflowmenu`/`customQ_nav`, `.queue-count[data-queue-id]` spans, `#overlay`, `#loading`, `#popup`, `#alert` dialogs, the `autocron.php` image, the script list and `getConfig().resolve(...)` of the original footer.
- The theme only reads data; it adds no table, no setting, no migration. It must work with `sla_first_response` absent (guard with `function_exists('coderise_contract_level')`).
- Installing in `deploy/docker/overlay/` and committing is in scope; **deploying to the droplet is not** (the user decides).

## Review Focus

1. **PJAX navigation** (most likely to bite): clicking rail items, queues and subnav items must refresh the rail's active state, the mode switch, the page title and the crumbs without a full reload; a full reload of the same URL must give the same rail. Pinned in Task 3.
2. **Non-admin agent / restricted permissions**: no "Administration" segment for `!$thisstaff->isAdmin()`; tabs the agent lacks (no Tasks, no Users) must simply be absent, never an empty group or a PHP notice. Pinned in Task 3.
3. **Plugin absent or ticket without data**: no `sla_first_response` plugin, ticket without owner, closed ticket with no staff reply, missing `created`: no gauge and no PHP warning, never "NaN". Pinned in Tasks 2 and 6.
4. **Hostile or long content**: subject `<script>alert(1)</script>`, a 300-character subject, an organization named `A&B "quoted"`, a staff name with an apostrophe: escaped, rail and queue still fit at 1280 and 390 px. Pinned in Tasks 3 and 6.
5. **RTL and narrow screens**: osTicket supports `dir="rtl"` (`css/rtl.css`); the rail must not overlap content in RTL, and nothing overflows horizontally at 390, 820 and 1280 px on every restyled page. Pinned in Task 10 (overflow audit) and Task 3 (RTL check).

---

## File Structure

```
deploy/themes-staff/
  README.md                                   how staff themes differ from client themes
  tests/
    test-scripts.sh                           apply/remove/idempotence/coexistence test
    lint-php.sh                               php -l of every overlay PHP file, in the demo image
    overflow.js                               snippet for Playwright: horizontal-overflow audit
  coderise-agent/
    README.md  MANIFEST  BASE.sha256  WATCH.sha256
    apply.sh   remove.sh   check-drift.sh
    tests/test-helpers.php                    pure-function tests (run in the demo image)
    captures/                                 screenshots of the real pages
    overlay/
      include/staff/header.inc.php            replaces core: rail, mode switch, topbar, #content
      include/staff/footer.inc.php            replaces core: same closers and scripts
      include/staff/login.header.php          replaces core
      include/staff/login.tpl.php             replaces core
      include/staff/templates/queue-tickets.tmpl.php   replaces core: adds the gauge column
      include/staff/coderise/coderise.inc.php helpers (cdr__, cdr_icon, cdr_initials, cdr_asset, cdr_duration, cdr_gauge_state, cdr_ticket_gauge, cdr_rail)
      include/staff/coderise/i18n/fr.php      French catalogue of the theme's own strings
      scp/coderise/css/coderise.css           the stylesheet (loaded after scp.css)
      scp/coderise/js/coderise.js             changed-field tracking (progressive enhancement)
      scp/coderise/fonts/*.woff2, OFL-*.txt   local fonts
      scp/coderise/img/logo-horizontal-sombre.png, favicon.svg
```

Also modified: `deploy/docker/scripts/local-demo.sh` (staff theme option), `docs/guide-utilisateur.md`, `CLAUDE.md` (one paragraph), and, in Task 10, `deploy/docker/overlay/` (install).

---

### Task 1: Package scaffold, scripts, demo wiring

**Files:**
- Create: `deploy/themes-staff/README.md`, `deploy/themes-staff/coderise-agent/{apply.sh,remove.sh,check-drift.sh,MANIFEST,BASE.sha256,WATCH.sha256,README.md}`, `deploy/themes-staff/coderise-agent/overlay/include/staff/coderise/.gitkeep` (replaced in Task 2), `deploy/themes-staff/tests/overflow.js`
- Modify: `deploy/docker/scripts/local-demo.sh`
- Test: `deploy/themes-staff/tests/test-scripts.sh`

**Interfaces:**
- Produces: `sh deploy/themes-staff/coderise-agent/apply.sh [--dry-run] [--force] [OVERLAY_DIR]`, `remove.sh [--dry-run] [--force] [OVERLAY_DIR]`, `check-drift.sh [--rev REV|--write-base [REV]|TREE]`; installed-files manifest at `<overlay>/include/staff/coderise/installed.manifest` (under `include/`, which Apache refuses). Later tasks only add files under `overlay/` and list them in `MANIFEST`. `local-demo.sh up --staff-theme <slug>` and `local-demo.sh staff-theme <slug|none>` (state key `OST_DEMO_STAFF_THEME`, default `none`): `build_images` runs `deploy/themes-staff/<slug>/apply.sh` on the exported copy **after** the client theme, first removing any other staff theme found through `include/staff/*/installed.manifest` (mirrors the client-theme logic); `status` prints it. `tests/overflow.js`: the body of an async function for Playwright `browser_evaluate` that, for a hard-coded `paths` array, loads each path in a hidden iframe at 390, 820 and 1280 px, waits `load` + 200 ms, and returns the string `aucun débordement` or a `{"<width>/<path>": <overflow px>}` map.

- [ ] **Step 1: Write `tests/test-scripts.sh`** (POSIX sh, `set -eu`, runs against a temp copy of `deploy/docker/overlay` and prints `ok`/`FAIL` per check, exits non-zero on any failure). Checks, in order: (a) `apply.sh --dry-run <tmp>` writes nothing; (b) `apply.sh <tmp>` installs every file of `MANIFEST` and writes `include/staff/coderise/installed.manifest`; (c) a second `apply.sh` reports 0 changes; (d) `remove.sh <tmp>` leaves `<tmp>` byte-identical to its starting state (compare `find | sort | sha256sum` lists); (e) **coexistence**: with the Parcours client theme installed in `<tmp>` (`deploy/themes/parcours/apply.sh <tmp>`), `coderise-agent/apply.sh <tmp>` succeeds, and then `deploy/themes/parcours/remove.sh <tmp>` and `deploy/themes/sommet/apply.sh <tmp>` still succeed; (f) `apply.sh` refuses (exit 1, nothing copied) when an installed file was edited by hand, and `--force` overwrites it; (g) `apply.sh` refuses when `MANIFEST` and `overlay/` differ.
- [ ] **Step 2: Run it to verify it fails** — `sh deploy/themes-staff/tests/test-scripts.sh` → FAIL (`apply.sh` missing).
- [ ] **Step 3: Implement the three scripts** by adapting `deploy/themes/parcours/{apply,remove,check-drift}.sh`: `THEME=coderise-agent`; manifest path `include/staff/coderise/installed.manifest`; **drop the "other client theme installed" check** (the staff theme coexists with any client theme and must not trip on `deploy/themes/*`); keep the "foreign file would be overwritten / modified by hand" and idempotence logic; `check-drift.sh` keeps the same interface and compares `BASE.sha256` (replaced core files) and `WATCH.sha256` (core files the theme depends on). Seed `MANIFEST` with the `.gitkeep`; `BASE.sha256`/`WATCH.sha256` are generated at the end of Task 10 with `check-drift.sh --write-base`.
- [ ] **Step 4: Run the test** — expect every check `ok`, exit 0.
- [ ] **Step 5: Wire the demo** — implement the two `local-demo.sh` commands described in Interfaces; `sh -n deploy/docker/scripts/local-demo.sh` passes. Write `tests/overflow.js`.
- [ ] **Step 6: Commit** — `git add deploy/themes-staff deploy/docker/scripts/local-demo.sh && git commit -m "themes-staff: package scaffold, install scripts and local-demo wiring for the coderise-agent theme"`.
- [ ] **Step 7: Prove the demo wiring** — `cd deploy/docker && ./scripts/local-demo.sh staff-theme coderise-agent` rebuilds and restarts (the image is built from `HEAD`, hence after the commit), `status` shows the staff theme, `staff-theme none` rebuilds without it; run `tests/overflow.js` authenticated against the stock panel (`/scp/index.php`, `/scp/tickets.php`) to prove the harness returns a result.

### Task 2: Helpers (pure logic, tested)

**Files:**
- Create: `overlay/include/staff/coderise/coderise.inc.php`, `overlay/include/staff/coderise/i18n/fr.php`
- Test: `deploy/themes-staff/coderise-agent/tests/test-helpers.php`, `deploy/themes-staff/tests/lint-php.sh`

**Interfaces:**
- Produces (all in `coderise.inc.php`, guarded by `if (!defined('CDR_VERSION'))`, constants `CDR_VERSION='1.0.0'`, `CDR_FIRST_RESPONSE_HOURS` default `4`, overridable in `ost-config.php`):
  - `cdr__(string $msgid): string` — `__()` then catalogue `i18n/<lang>.php` (`fr_FR`, `fr`), else the msgid.
  - `cdr_icon(string $name, int $size = 18): string` — inline SVG, trait 1.75, grid 24, `aria-hidden`. Names: copy the paths from `docs/theme-mockups-admin/_build/build.mjs` (`EXTRA`) and `docs/theme-mockups-coderise/_build/common.mjs` (`ICONS`) for: `inbox ticket users user book tasks chart sliders mail tag clock filter layers plug key building gauge alert check checkc lock bolt search plus refresh star upload edit message clip swap merge ban chevr chevd menu logout flag`.
  - `cdr_initials(string $name): string` — `"Nora Benali"` → `"NB"`, `"nora"` → `"N"`, `""` → `"?"`, uppercased, at most 2 letters, multibyte safe.
  - `cdr_asset(string $path): string` — `ROOT_PATH . 'scp/coderise/' . $path . '?v=' . CDR_VERSION`.
  - `cdr_duration(int $seconds): string` — `"44 min"`, `"3 h 27"` (minutes padded to 2 digits), `"0 min"` for `<= 0`.
  - `cdr_gauge_state(int $start, int $due, ?int $firstResponse, int $now): ?array` — returns `['kind' => 'ok'|'warn'|'over'|'done', 'ratio' => float 0..1, 'label' => string]` or `null` when `$due <= $start`. Rules: if `$firstResponse !== null` → `done`, ratio = `(first-start)/(due-start)` capped at 1, label `Tenu en {duration}` when `first <= due`, else kind `over` with label `Rendu avec {duration} de retard`; else if `$now > $due` → `over`, ratio 1, label `Dépassé de {duration}`; else ratio = `(now-start)/(due-start)`, `warn` when ratio `>= 0.75`, else `ok`, label `Reste {duration}` (duration of `due-now`).
  - `cdr_ticket_gauge(Ticket $t): ?array` — `null` unless the owner's contract is Priority (`function_exists('coderise_contract_level')` and `coderise_contract_level($t)['level'] === 'priority'`, wrapped in try/catch) and the ticket is open **or** has a staff reply; window = `getReopenDate() ?: getCreateDate()` to start + `CDR_FIRST_RESPONSE_HOURS` h; first response = earliest visible, non-system staff reply (`ThreadEntry` type `R`, `staff_id > 0`) on or after the window start (same query shape as `pcrs_first_response()` in `deploy/themes/parcours/overlay/include/client/parcours/parcours.inc.php`, but copied, not required); memoised per ticket id; result of `cdr_gauge_state`.
  - `cdr_gauge_html(?array $g, bool $large = false): string` — the `.gauge` markup of the mockup (bar + icon + text, `role="img"`, `aria-label` = the label), `''` for `null`; state class `gauge-warn|gauge-over|gauge-done`; label `htmlchars`-ed.

- [ ] **Step 1: Write `tests/test-helpers.php`** (plain PHP script, defines `INCLUDE_DIR`, a stub `__()` returning its argument, a stub `Internationalization` is not needed: `cdr__` must tolerate its absence by checking `class_exists`; no assert library, a tiny `check($name, $got, $want)` that prints `ok`/`FAIL` and exits 1 on failure). Assertions: `cdr_initials('Nora Benali') === 'NB'`, `'nora'→'N'`, `''→'?'`, `"Éloïse d'Arc"→'ÉD'`; `cdr_duration(44*60) === '44 min'`, `cdr_duration(3*3600+27*60) === '3 h 27'`, `cdr_duration(3600+5*60) === '1 h 05'`, `cdr_duration(-5) === '0 min'`; `cdr_gauge_state` with `start=0, due=14400`: `(now=1980, first=null)` → `ok`, label `Reste 3 h 27`, ratio `0.1375`; `(now=10800)` → `warn` (ratio 0.75 exactly is `warn`); `(now=17220)` → `over`, label `Dépassé de 47 min`; `(now=20000, first=2640)` → `done`, label `Tenu en 44 min`; `(first=15000)` → `over`, label `Rendu avec 10 min de retard`... compute `15000-14400=600` → `10 min`; `due<=start` → `null`; `cdr_gauge_html(null) === ''`; `cdr_gauge_html` of a label containing `<b>"x"` has no raw `<b>`.
- [ ] **Step 2: Write `tests/lint-php.sh`** — for each `*.php` under `coderise-agent/overlay/`, run `docker run --rm -i --entrypoint php ostdemo/osticket:demo -l < file` (stdin form: no path conversion needed; the image is the one `local-demo.sh` builds); print the failing file and exit 1.
- [ ] **Step 3: Run the helper test to verify it fails** — the test `require`s `__DIR__ . '/../overlay/include/staff/coderise/coderise.inc.php'`, so mount the repo: `MSYS_NO_PATHCONV=1 docker run --rm -v "$(cygpath -m "$PWD"):/w:ro" -w /w --entrypoint php ostdemo/osticket:demo deploy/themes-staff/coderise-agent/tests/test-helpers.php` (the variable is set inline for this one command only). Expected: FAIL, `coderise.inc.php` missing.
- [ ] **Step 4: Implement `coderise.inc.php` and `i18n/fr.php`** with the signatures above; French strings for: `Agent`, `Administration`, `First reply`, `Reste`/`Dépassé de`/`Tenu en`/`Rendu avec … de retard` templates (as `%s`-formats in the catalogue, `sprintf`), `Panel`, `Search tickets`, `Skip to content`, `Main menu`, `At risk`, `Not assigned`.
- [ ] **Step 5: Run the test again** — expect every line `ok`; then `sh deploy/themes-staff/tests/lint-php.sh` passes.
- [ ] **Step 6: Add the files to `MANIFEST`, run `tests/test-scripts.sh` (still green), commit** — `themes-staff: helpers (icons, initials, durations, first-reply gauge) with tests`.

### Task 3: Shell — tokens, fonts, header, footer, rail, topbar

**Files:**
- Create: `overlay/scp/coderise/css/coderise.css`, `overlay/scp/coderise/fonts/*`, `overlay/scp/coderise/img/*`
- Create (replace core): `overlay/include/staff/header.inc.php`, `overlay/include/staff/footer.inc.php`
- Update: `MANIFEST`

**Interfaces:**
- Consumes: Task 2 helpers (`cdr__`, `cdr_icon`, `cdr_initials`, `cdr_asset`).
- Produces: `cdr_rail(StaffNav $nav, Staff $staff): string` (in `coderise.inc.php`) returning the `<aside class="rail">` HTML; markup classes of the mockup (`.rail`, `.mode`, `.group`, `.item`, `.queues`, `.badge`, `.foot`, `.topbar`, `.crumbs`, `.tsearch`) so Tasks 5–9 style against them. `#content` gets classes `cdr cdr-<script basename>` plus `cdr-ticket` when `tickets.php` has `id=` and `cdr-admin` when `defined('ADMINPAGE')`.

- [ ] **Step 1: Copy assets** — fonts and OFL files from `docs/theme-mockups-coderise/_shared/fonts/` and `logo-horizontal-sombre.png` from `docs/theme-mockups-coderise/_shared/img/`; `favicon.svg` from `deploy/themes/_shared/coderise/symbole-sur-bleu-nuit.svg`.
- [ ] **Step 2: Write the shell stylesheet** — `@font-face` (relative `../fonts/`), tokens as CSS custom properties copied from the mockup (`docs/theme-mockups-admin/theme.css` sections Rail, Barre haute, Boutons, Panneaux, then the media queries), scoped so they win over `scp.css` without `!important` where possible: `#container{width:auto;max-width:none;margin:0;padding:0}`, `body{background:var(--gris);font:13.5px/1.5 Inter,Arial,sans-serif}`, `#pjax-container` as the grid (`240px minmax(0,1fr)`, rail sticky, `#content` as the main column). Hide the legacy `#header` (`display:none`) and the legacy horizontal `ul#nav` inside the rail only after Step 4.
- [ ] **Step 3: Rewrite `header.inc.php`** from the core file: keep byte-for-byte the CSP/`Content-Type` headers, the `X-PJAX` branch (title + `X-PJAX-Version` + extra pjax scripts), the `<head>` (add `<link rel="stylesheet" href="<?php echo cdr_asset('css/coderise.css'); ?>">` **after** `scp/css/scp.css`, after `rtl.css`/`translatable.css` so it wins; Coderise favicon), error/warning/notice bars, `$ost->getExtraHeaders()`, and the `#msg_error`/`#msg_notice`/`#msg_warning`/`Messages` block. Replace the `#header` + `<ul id="nav">` + `sub-navigation` markup with, inside `#pjax-container`: `<?php echo cdr_rail($nav, $thisstaff); ?>`, a `<header class="topbar">` (crumbs from the active tab and active sub-item, search form `GET tickets.php` with hidden `a=search` and `name="query"` — same contract as `queue-tickets.tmpl.php` — and, when `$thisstaff->hasPerm(Ticket::PERM_CREATE, false)`, the "Nouveau ticket" button to `tickets.php?a=open`), then `<div id="content" class="cdr …">`. `cdr_rail` rules: mode switch shows "Administration" only if `$thisstaff->isAdmin()`, active segment from `defined('ADMINPAGE')`; agent mode = one `.item` per `$nav->getTabs()` tab with icon by tab key (`dashboard→chart`, `users→users`, `tasks→tasks`, `tickets→ticket`, `kbase→book`, default `layers`); under the **active** tab, the output of `include STAFFINC_DIR.'templates/sub-navigation.tmpl.php'` captured with `ob_start()` inside `<div class="queues">` (it keeps the queue callables, counts and "add personal queue"); admin mode = one `.group` per tab (title = tab desc) listing `$nav->getSubMenu($name)` items (skip `droponly`, mark the active item with `aria-current="page"` using the same test as `sub-navigation.tmpl.php`); foot = avatar with `cdr_initials`, name, "Mon profil" (`profile.php`), "Se déconnecter" (`logout.php?auth=` + `$ost->getLinkToken()`, class `no-pjax`). All labels through `htmlchars`. Mobile: `<details class="menu-mobile">` with the same links.
- [ ] **Step 4: Rewrite `footer.inc.php`** by copying the core file and keeping every element listed in Global Constraints; only the closing tags change to match the new header (`#content`, `#pjax-container`, `#container`); the copyright `#footer` stays but is restyled small.
- [ ] **Step 5: Install and view** — add the new files to `MANIFEST`, `sh deploy/themes-staff/tests/lint-php.sh`, commit (the image is built from `HEAD`), then `cd deploy/docker && ./scripts/local-demo.sh staff-theme coderise-agent`.
- [ ] **Step 6: Check, against the live demo (log in with the demo credentials supplied by the user)** — Playwright screenshot of `/scp/` and `/scp/tickets.php` at 1280×800 next to `docs/theme-mockups-admin/captures/`: rail, mode switch, topbar match; clicking "Tickets", a queue, then "Tableau de bord" changes the active item **without a full reload** (assert `performance.getEntriesByType('navigation').length` unchanged, `document.title` updated); a **non-admin agent** (create one in the demo via `php manage.php agent` or the admin UI) sees no "Administration" segment; a staff member named `D'Arc "Jo" <b>` (create it in the demo) shows escaped in the rail foot and the crumbs; load `/scp/tickets.php` with `dir="rtl"` forced via devtools (`document.documentElement.dir='rtl'`) and confirm the rail is on the right and nothing overlaps; 390 px shows the "Menu" button.
- [ ] **Step 7: Commit** — `themes-staff: coderise-agent shell (rail, mode switch, topbar, header and footer)`.

### Task 4: Login and password reset

**Files:** Create (replace core): `overlay/include/staff/login.header.php`, `overlay/include/staff/login.tpl.php`. Update `coderise.css`, `MANIFEST`.

**Interfaces:** Consumes `cdr__`, `cdr_asset`. Produces classes `.cdr-login`, `.login-art`, `.login-card` (from the mockup).

- [ ] **Step 1: `login.header.php`** — copy core, load `coderise.css` after `scp.css`, body class `cdr-login`. **`login.tpl.php`** — copy core and keep: `include login.header.php`, `<form action="login.php" method="post" id="login" onsubmit="attemptLoginAjax(event)">`, `csrf_token()`, 2FA branch (`dynamic-form-simple.tmpl.php`, `do=2fa`, hidden fields), `name="userid"`/`name="passwd"`, `#reset-link` visibility rule, external auth backends, `#loading` dialog, `$msg` as `#login-message`, `$content` banner. Wrap in the split layout of the mockup (`.login-art` left with logo, the line "Chaque ticket Priority a son compte à rebours." through `cdr__`, signature; `.login-card` right). Error text keeps coming from osTicket (`$msg`), restyled as `.banner-err` with `role="alert"`.
- [ ] **Step 2: CSS** for `.cdr-login` from the mockup's "Connexion" block; hide the legacy `#brickwall`, `#blur`, `#background`, `#poweredBy` logo only visually (keep in DOM).
- [ ] **Step 3: Verify** — `lint-php.sh`; rebuild demo; in Playwright: wrong password shows the banner and keeps the userid field value; correct login reaches `/scp/`; `pwreset.php` renders without the legacy background (it reuses `login.header.php`) — fix its two templates with CSS only; 390 px stacks the two halves; no overflow.
- [ ] **Step 4: Commit** — `themes-staff: login and password-reset pages`.

### Task 5: Global components (buttons, forms, tables, tabs, menus, dialogs, messages)

**Files:** Modify `coderise.css` (split into clearly commented sections; if it passes ~1500 lines, split into `base.css`, `components.css`, `screens.css` imported by one `coderise.css` — decide by size, not in advance).

**Interfaces:** Produces the component vocabulary Tasks 6–9 rely on: `.button`, `.action-button`, `input[type=submit|button]`, `.form_table`, `.list` (and `.list.queue`), `ul.tabs` + `.tab_content`, `.action-dropdown`, `.noclick-dropdown`, `.dialog`/`#popup`, `#msg_error|#msg_notice|#msg_warning`, `.pagination`, `.select2-*`, `.redactor-*` toolbar, `.sticky.bar`.

- [ ] **Step 1: Restyle with the mockup's component rules** — buttons (primary cyan/nuit text; `.button` default = `btn-line`; `.red.button`/`.danger` = `btn-danger`), inputs and selects (1 px ardoise border, 36 px height, 6 px radius, visible 3 px focus ring cyan-profond on light, cyan on dark), `.list` tables (nuit header, alternating gris, voile hover, cells padded 8×12, `width:100%` overriding the `width="940"` attributes), tabs (cyan 3 px underline), dropdown menus (white panel, 10 px radius, ring shadow), dialogs, message bars (état colours, with icon via CSS mask or leading text; never colour alone), pagination, `fieldset`/`legend` of the settings forms.
- [ ] **Step 2: Neutralise fixed widths** in the legacy markup with CSS (`table[width="940"]`, `.list`, `#content` children) so nothing forces horizontal scroll.
- [ ] **Step 3: Verify on real pages** — open each of: `tickets.php` (queue), `tickets.php?id=<n>`, `users.php`, `tasks.php`, `kb.php`, `profile.php`, and in admin `settings.php?t=tickets`, `staff.php`, `helptopics.php`, `plugins.php`; screenshot at 1280; open one modal (e.g. ticket "Assign", user lookup) and one dropdown; each must be readable, with no unstyled region. Record any page that still looks broken as a numbered note in the theme README ("Known gaps") rather than hiding it.
- [ ] **Step 4: Run `tests/overflow.js`** (Task 1) with `paths` set to those pages → `aucun débordement`.
- [ ] **Step 5: Commit** — `themes-staff: global components`.

### Task 6: Ticket queue with the first-reply gauge

**Files:** Create (replace core): `overlay/include/staff/templates/queue-tickets.tmpl.php`. Modify `coderise.css`, `coderise.inc.php` (no new public function), `MANIFEST`.

**Interfaces:** Consumes `cdr_ticket_gauge`, `cdr_gauge_html`. Produces a queue table whose rows keep `name="tids[]"` checkboxes and the `$C->render($T)` cells unchanged.

- [ ] **Step 1: Write the failing check** — with the demo seeded (create 4 tickets via the admin UI or `scp` API: one Priority-owner ticket received 5 h ago with no reply, one 3 h ago, one answered after 44 min, one Standard-owner ticket), `GET /scp/tickets.php` has no element `.gauge`. Run it (Playwright `browser_evaluate`) → 0 gauges.
- [ ] **Step 2: Implement** — copy the core template; after the configured columns, when `function_exists('coderise_contract_level')`, add a "Premier retour" header cell and, per ticket row, `cdr_gauge_html(cdr_ticket_gauge(Ticket::lookup($T['ticket_id'])))` (rows are arrays: read `$T['ticket_id']`; if the key is absent for a custom queue, skip the cell), `—` text `cdr__('Not applicable')` for Standard. Column count in the footer `colspan` and the empty-queue row must include the extra cell. Keep sorting, bulk actions (`tickets-actions.tmpl.php`), pagination, export and the quick-search form exactly as in core. The "actions on selection" bar uses the existing `.sticky.bar` restyled as the mockup's `.bulk` (nuit bar) — CSS only.
- [ ] **Step 3: Restyle the queue page** in CSS: `.customQ-dropdown` shown permanently inside `.rail .queues` (static position, no hover), `.queue-count` pills as the mockup's `.badge`, saved-search items, "Add personal queue" as a ghost button; the queue's search row and `.sticky.bar` as the mockup's filter row.
- [ ] **Step 4: Verify** — gauges now show `Dépassé de …`, `Reste …` (warn at ≥ 75 %), `Tenu en 44 min`, none for the Standard ticket; subject `<script>alert(1)</script>` and a 300-character subject render escaped and truncated without overflow; a queue with 0 tickets shows the core empty message styled; with the plugin disabled in the admin (Gérer > Plugins) the column disappears and the page loads (no PHP warning in `docker logs ostdemo-web-1`); queue counts in the rail still fill in (AJAX `.queue-count`). Screenshot vs `captures/file-de-tickets.png`.
- [ ] **Step 5: Commit** — `themes-staff: ticket queue with the first-reply gauge`.

### Task 7: Ticket view

**Files:** Modify `coderise.css`, `coderise.js` (create it here if Task 8 has not yet), `overlay/include/staff/header.inc.php` (load the script, connect the signal), `MANIFEST`.

**Interfaces:** Consumes `cdr_ticket_gauge`, `cdr_gauge_html`. Core ticket view is **not** replaced (1446 lines, drift-prone).

- [ ] **Step 1: Layout by CSS** — on `#content.cdr-ticket`: two-column grid (`minmax(0,1fr) 330px`): the thread (`#ticket_tabs_container`) and reply area (`#response_options`, `#reply`, `#note`) in column 1; the `table.ticket_info` blocks (and `.custom-data`) in column 2 as sticky stacked panels, restyled as the mockup's `.props` (label left, value right, alternating rows); `.tixTitle` and the `.sticky.bar` action bar span both columns. Below 1180 px: single column.
- [ ] **Step 2: Gauge panel** — in `header.inc.php`, `Signal::connect('object.view', function ($ticket, $type) { if ($ticket instanceof Ticket && $type === 'view' ...) echo '<section class="cdr-sla">' . cdr_gauge_html(cdr_ticket_gauge($ticket), true) . '</section>'; })` registered only when `basename($_SERVER['SCRIPT_NAME']) === 'tickets.php'` (the core sends `object.view` at line 12 of `ticket-view.inc.php`, so the markup lands at the top of `#content` and CSS places it in column 2). Verify the actual `$type` value in `include/class.signal.php`/`ticket-view.inc.php:12` before filtering; if the signal's output position makes the grid placement unreliable, stop and ask the user before overriding `ticket-view.inc.php`.
- [ ] **Step 3: Thread and reply** — thread entries as the mockup's `.entry` (client = voile, internal note = alerte-fond with 4 px alerte left bar and a "Note interne" label, latest = cyan bar, events as `.event` lines); reply area tabs "Post Reply" / "Post Internal Note": the selected **note** tab tints the whole form alerte-fond (`coderise.js` adds `is-note` to `#response_options` on tab click; no `:has()`, for older browsers); Redactor toolbar restyled; "Post Reply"/"Post Internal Note" submit buttons use the primary style.
- [ ] **Step 4: Verify** — open a ticket with a client message, an agent reply, an internal note and an assignment event: grid, tinted note tab (switching tabs toggles the tint), gauge panel present for the Priority ticket and absent for the Standard one and for a ticket with no owner; edit/assign/transfer modals still open and submit; a 1,000-character unbroken string in a message wraps (`overflow-wrap:anywhere`); 820 px single column; no overflow at 390.
- [ ] **Step 5: Commit** — `themes-staff: ticket view layout, note tint and gauge panel`.

### Task 8: Administration

**Files:** Modify `coderise.css`; Create `overlay/scp/coderise/js/coderise.js`; Modify `header.inc.php` (load the script), `MANIFEST`.

**Interfaces:** Produces `coderise.js` (no globals; one IIFE; re-initialises on `pjax:end`): for each `form.save` (osTicket's own "warn on unsaved" class), adds class `is-changed` to the `.field`-equivalent cell of any input whose value differs from its initial value, and shows/hides the `.savebar` message "N réglage(s) modifié(s)". Uses only `input`, `select`, `textarea` events; no `eval`; no markup injection except text via `textContent`.

- [ ] **Step 1: Settings forms** — `form_table` rows restyled as the mockup's left-label fields (label column 220 px, control max 520 px), `tr.header th` sections as `fieldset > legend` look (title + 28×3 px cyan bar), help tips (`.help-tip`) and inline errors (`.error`) as in the mockup; on Settings the sub-menu is shown **in the rail group** (Task 3) — the mockup's horizontal sub-navigation is replaced by the rail groups (a deliberate deviation: osTicket's admin tabs are Settings, Manage, Emails, Agents; record it in the theme README).
- [ ] **Step 2: Save bar** — the bottom button row of settings forms (`p.centered` with submit/reset/cancel) becomes the sticky nuit bar of the mockup by CSS (`position:sticky; bottom:0`); `coderise.js` toggles `.is-dirty` on the form and writes the count; with JS disabled the bar still sticks, only the counter is absent.
- [ ] **Step 3: Lists** — agents, teams, departments, help topics, SLA plans, plugins, forms: the `.list` style of Task 5 plus the mockup's status/role tags (`.tag`, `.status`) applied through CSS on the existing classes where osTicket provides them (`.faded`, `.isactive`, `.inactive`), without new markup.
- [ ] **Step 4: Verify** — change two fields on `settings.php?t=tickets` → both marked, bar says "2 réglages modifiés", Save persists (reload shows the values; revert them afterwards), Reset clears the marks; PJAX to another admin page and back re-initialises (change a field again → marked); `staff.php` and `helptopics.php` match the mockup's list look; as a non-admin, `admin.php` still redirects as in core.
- [ ] **Step 5: Commit** — `themes-staff: administration screens and save bar`.

### Task 9: Dashboard

**Files:** Modify `coderise.css`. No template replaced.

- [ ] **Step 1: Restyle** `dashboard.php` (agent) and `logs.php` (admin dashboard) with the component vocabulary: panels, tables, the existing activity chart container (osTicket's chart script is unchanged; set its container to a white panel; palette passed by CSS only where the library allows), date-range form as inline selects.
- [ ] **Step 2: Verify** both pages at 1280 and 390 px, no overflow; the mockup's alert strip and stat strip are **not implemented** (they need new queries) — list them under "Not done" in the theme README.
- [ ] **Step 3: Commit** — `themes-staff: dashboard`.

### Task 10: Wiring, documentation, audit, install

**Files:** Modify `docs/guide-utilisateur.md`, `CLAUDE.md`; generate `BASE.sha256`/`WATCH.sha256`; write `coderise-agent/README.md`, `deploy/themes-staff/README.md`; install in `deploy/docker/overlay/`.

**Interfaces:** Consumes everything above; produces the documentation, the drift baseline and the installed overlay.

- [ ] **Step 1: Overflow audit** — run `tests/overflow.js` authenticated with `paths` covering every page restyled in Tasks 3–9 (`/scp/index.php`, `/scp/tickets.php`, a ticket, `/scp/dashboard.php`, `/scp/users.php`, `/scp/tasks.php`, `/scp/kb.php`, `/scp/profile.php`, `/scp/admin.php`, `/scp/settings.php?t=tickets`, `/scp/staff.php`, `/scp/helptopics.php`, `/scp/plugins.php`, `/scp/logs.php`, and the login page without a session); expect `aucun débordement`.
- [ ] **Step 2: Contrast and keyboard audit** — with Playwright `browser_evaluate`, for the rail, topbar, queue, ticket view and a settings page compute text/background contrast of every text node (composite alpha) and fail below 4.5:1 (3:1 large text); Tab through the queue page and a ticket and confirm every focused element has a ≥ 2 px outline. Fix findings in the CSS, re-run.
- [ ] **Step 3: Package files** — `check-drift.sh --write-base` (BASE = replaced core files, WATCH = core files the theme depends on: `include/class.nav.php`, `scp/staff.inc.php`, `scp/admin.inc.php`, `scp/tickets.php`, `include/staff/ticket-view.inc.php`, `include/staff/templates/{navigation,sub-navigation,queue-navigation,queue-subnavigation,queue-savedsearches-nav}.tmpl.php`, `scp/css/scp.css`, `scp/js/scp.js`); `check-drift.sh` exits 0 right after. Write both READMEs (what is replaced and why, known gaps, "Not done": alert strip and stat strip of the dashboard, "À risque" queue must be created by an admin as a saved queue, other admin screens only restyled by CSS, English strings only through `__()`, contrast audit scope, screenshots in `captures/`). Add the section "Thème du panneau agent" to `docs/guide-utilisateur.md` (replacing the sentence "Le panneau agent n'est pas concerné"), and a short paragraph to `CLAUDE.md` under the Docker section (location `deploy/themes-staff/`, coexistence with client themes, build only via `local-demo.sh`/`build.sh`).
- [ ] **Step 4: Final verification** — `sh deploy/themes-staff/tests/test-scripts.sh`, `sh deploy/themes-staff/tests/lint-php.sh`, the helper test (Task 2 command), the overflow and contrast audits, `deploy/themes-staff/coderise-agent/check-drift.sh`, and the client themes' own test (`deploy/themes/parcours/apply.sh --dry-run` on a scratch overlay with the staff theme installed). Show the output of each.
- [ ] **Step 5: Install in the production overlay** — `sh deploy/themes-staff/coderise-agent/apply.sh` (into `deploy/docker/overlay/`), `git add -A deploy/docker/overlay deploy/themes-staff docs CLAUDE.md`, commit `docker: install the coderise-agent staff theme in the production overlay`. **Do not build for or deploy to the droplet.**
- [ ] **Step 6: Report** — tell the user what was verified, the known gaps, and that deployment (`build.sh` then `droplet/update.sh sha-…`) awaits their go-ahead.
