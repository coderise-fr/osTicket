# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

osTicket: a PHP/MySQL help-desk system (PHP 8.2–8.4, `mysqli`). There is no Composer, npm or build step — the repo is the deployable source tree. Active branch is `develop`; maintenance branches are `1.x.x` (e.g. `1.17.x`, `1.18.x`) and are merged forward into `develop`. Contributions follow git-flow via forks and PRs.

## Commands

All tooling is plain PHP CLI (`php` is not on PATH in the default Git Bash on this machine — locate or add it first).

- **Run the test suite:** `php setup/test/run-tests.php` (run from repo root). Run one suite by its class name: `php setup/test/run-tests.php SyntaxTest`. Exit code = number of failures.
- **CLI app:** `php manage.php <module> ...` (modules in `include/cli/modules/`: `agent`, `cron`, `deploy`, `export`, `file`, `i18n`, `import`, `list`, `org`, `package`, `serve`, `unpack`, `upgrade`, `user`). `php manage.php <module> --help` for options.
- **Local dev server:** `php manage.php serve` (default `localhost:8000`).
- **Deploy a build to a web root:** `php manage.php deploy --setup /path/to/www/osticket/` (first time) / `php manage.php deploy -v /path/` (updates).
- **i18n template:** `php manage.php i18n make-pot > message.pot`.
- **Install:** copy `include/ost-sampleconfig.php` → `include/ost-config.php` (gitignored) and run the web installer at `/setup/`; delete `setup/` afterwards in real deployments.

### What the tests are

`setup/test/tests/test.*.php` are mostly **static code-inspection checks**, not behavioural unit tests: `php -l` syntax of every script, `var_dump` leftovers, short open tags, git conflict markers, extra whitespace, undefined/static method calls, uninitialized vars, jslint, plus a few real ones (crypto, mail parsing, email bounces, validation, signals). Notably, **every `Signal::connect('name', ...)` must have a matching `Signal::send('name', ...)` somewhere** or `test.signals.php` fails. Tests run with `TABLE_PREFIX = '%'` and a mock DB (`mockdb.php`) — no real database is needed.

## Docker production deployment (`deploy/docker/`)

Hardened image + Compose stack (`web`, `cron`, MariaDB) for the Coderise support portal. Full documentation, hardening table and the verified / not-verified lists are in `deploy/docker/README.md`; read it before changing anything there. Work on it lives on `feature/docker-production` (a fork-only addition: nothing in the osTicket tree itself is modified).

- **Never build the images from the working tree on Windows.** `core.autocrlf=true` rewrites LF as CRLF, which changes the md5 of `setup/inc/streams/core/install-mysql.sql` and breaks the schema signature check (`include/upgrader/streams/core.sig`) at install time. Always build with `deploy/docker/scripts/build.sh`, which builds from `git archive` of the **committed** `HEAD` with `core.autocrlf=false`. Consequence: uncommitted changes (including edits under `deploy/`) are not in the image, so commit before building. The Dockerfile fails the build on a signature mismatch on purpose.
- **Git Bash path mangling:** do not export `MSYS_NO_PATHCONV=1` for `build.sh` or any git command (native Git then cannot read `/e/...` paths). Set it only inline for `docker exec` calls that pass absolute container paths.
- **Two targets:** `runtime` (production, no `setup/` in any layer) and `install` (adds the setup wizard; first install and upgrades only, never public). The config volume (`/etc/osticket`, holds `ost-config.php` with the DB password and `SECRET_SALT`) is read-write only in install mode; the runtime entrypoint refuses to start if it is writable.
- **Immutable image:** root filesystem read-only, code owned by root, so plugins, language packs and theme files cannot be installed from the admin UI. Put them in `deploy/docker/overlay/` and rebuild. `.phar` files are excluded from the build context by default (see `overlay/README.md`).
- **Do not weaken without a reason:** non-root `www-data` on port 8080, `cap_drop: ALL`, `no-new-privileges`, `disable_functions`, `open_basedir`, the Apache module allowlist, the `internal` database network. Re-test after any change: install through the wizard, load the admin pages, `cron` healthy, sensitive paths still 403.
- **Base images are pinned by digest** (`PHP_IMAGE` in the Dockerfile, `mariadb` in `compose.yaml`). After a rebuild, re-scan (`docker scout cves`) and refresh the digests. Known state (20/10/2026): 26 critical/high CVEs, all inherited from `php:8.2-apache-bookworm`, none fixable yet.
- **Test environment hygiene:** use a separate Compose project (`OST_PROJECT`), a non-default port and a secrets folder outside the repository (`OST_SECRETS_DIR`); remove containers, `-v` volumes, networks and images afterwards. `deploy/docker/.env` and `secrets/` are git-ignored.
- **Staff-panel theme:** the `/scp/` theme lives in `deploy/themes-staff/` (`coderise-agent/`), not in `deploy/themes/`, so it coexists with one client theme in `deploy/docker/overlay/` (a client `apply.sh` refuses any other `deploy/themes/*` folder found in the overlay). Same conventions (`apply.sh`, `remove.sh`, `check-drift.sh`, `MANIFEST`; installed manifest `include/staff/<slug>/installed.manifest`). Build only through `./scripts/local-demo.sh staff-theme <slug|none>` or `build.sh`, after committing.
- Open work and decisions are tracked in the GitHub issues (SLA first response, Stripe sync, image/updates, theme, support policies).

## Architecture

### Request bootstrapping
Every web entry point does `require('main.inc.php')` (directly or via `client.inc.php` / `scp/staff.inc.php` / `scp/admin.inc.php`). `main.inc.php` calls `Bootstrap` (in `bootstrap.php`): `loadConfig()` (reads `include/ost-config.php`), `defineTables()` (defines ~100 `*_TABLE` constants from `TABLE_PREFIX`), `loadCode()`, `connect()`, then `osTicket::start()` which sets up `$ost`, `$cfg`, and the session. `bootstrap.php` also defines `THIS_VERSION`/`MAJOR_VERSION`. Globals `$ost`, `$cfg`, `$thisstaff`, `$thisclient`, `$errors`, `$msg` are used pervasively by page scripts and templates.

### Three front-ends, one shared `include/`
- **Client portal** — top-level `*.php` (`open.php`, `tickets.php`, `view.php`, `login.php`, `kb/`, …); views in `include/client/`, themes/templates in `include/client/templates` and `assets/`, `css/`, `js/`.
- **Staff/admin panel ("SCP")** — `scp/*.php`; views in `include/staff/`; assets in `scp/css`, `scp/js`. Admin pages include `scp/admin.inc.php`, staff pages `scp/staff.inc.php`.
- **HTTP API** — `api/` (ticket creation via XML/JSON/email-pipe, authenticated by API key + source IP); see `setup/doc/api.md`. `api/pipe.php` and `setup/scripts/` handle piped mail/cron.

The pattern for a page: a thin controller script (e.g. `scp/faq.php`) handles POST actions and validation, then includes a view from `include/staff/*.inc.php` or `include/client/*.inc.php`.

### AJAX routing
`scp/ajax.php`, `ajax.php` (client) and `apps/dispatcher.php` build a `Dispatcher` (`include/class.dispatcher.php`) with `url()/url_get()/url_post()` + `patterns('ajax.xxx.php:ClassName', ...)` route tables that map regexes to methods on `include/ajax.*.php` controller classes (extending `AjaxController`). Plugins extend routes through the `ajax.scp` / `ajax.client` signals.

### Model layer (custom ORM)
`include/class.orm.php` + `class.model.php` implement a Django-style ORM. Models extend `VerySimpleModel` and declare `static $meta` (`table`, `pk`, `joins`, `select_related`, `defer`, `ordering`, …); querysets are built with `Model::objects()->filter(...)`. Many legacy classes (`class.ticket.php`, `class.staff.php`, …) wrap these models. See `setup/doc/orm.md`. Class files are `include/class.<name>.php`, one major class each; most are loaded with explicit `require_once`, not an autoloader.

### Extensibility
- **Signals** (`include/class.signal.php`): string-named pub/sub (`Signal::send('name', $obj, $data)` / `Signal::connect`). See `setup/doc/signals.md`.
- **Plugins** (`include/class.plugin.php`, `.phar` packages — gitignored): hook in via signals; `setup/doc/package.md` for packaging.
- **Dynamic forms / custom data** (`class.dynamic_forms.php`, `class.forms.php`): user-defined fields stored in `*__cdata` tables, which are regenerated; don't assume fixed columns on tickets/users/orgs/tasks.
- **Queues/search** (`class.queue.php`, `class.search.php`) drive the ticket lists in the agent UI.

### Database schema & upgrades
- Fresh-install schema: `setup/inc/streams/core/install-mysql.sql`.
- Upgrades: `include/upgrader/streams/core/<oldsig8>-<newsig8>.patch.sql` (plus optional `.task.php` data migrations). The current tip hash is in `include/upgrader/streams/core.sig` and equals the **md5 of `install-mysql.sql`**. Any schema change requires: editing the install SQL, adding a new patch named from the previous signature to the new one, and updating `core.sig` (and the `schema_signature` set at the end of the patch). See `setup/doc/streams.md`.
- `setup/cli/cleanup-codebase.sh` deletes files that were removed in newer releases (used when updating an existing tree).

### i18n
All user-visible strings must be wrapped: `__('...')` (current user's language), `_S()` (system language), `_N()/_NS()` plurals, `_L('...', locale)` (named locale, e.g. per-ticket language), `_H()` for comments/context. Choosing the wrong one matters (e.g. emails to admins vs. text shown to a user). Rules in `setup/doc/i18n.md`; translations live in `include/i18n/` (managed via Crowdin — don't hand-edit language packs).

## Conventions

- **Commits and pull requests: no Claude/Anthropic attribution.** Never add `Co-Authored-By: Claude ...`, "Generated with Claude Code" or any similar trailer or mention to commit messages or PR descriptions. This overrides the default attribution.

- Files use `vim: expandtab sw=4 ts=4 sts=4` (4-space indent, no tabs) and carry the GPL header block; keep it on new files.
- Every non-entry include guards against direct access (`defined('INCLUDE_DIR')` / `die(...)`); preserve this in new includes.
- Third-party code is vendored under `include/` (`laminas-mail`, `mpdf`, `pear`, `htmLawed.php`, `Spyc.php`, `phpseclib`, …) — treat as read-only unless upgrading the dependency.
- Security patches land on the oldest supported `1.x.x` branch and are merged up into `develop`; user input should go through `Format::htmlchars`/`Validator` and output through the existing sanitizers (recent history is dominated by XSS/injection fixes).
