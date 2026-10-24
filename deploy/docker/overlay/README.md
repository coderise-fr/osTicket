# Overlay

Files placed in this folder are copied over the osTicket tree at build time, at the
same relative path. Nothing is writable at runtime, so everything that osTicket would
normally install from its admin panel has to be added here and the image rebuilt.

| What | Where in the overlay |
|---|---|
| Client portal stylesheet and images | `assets/default/css/theme.css`, `assets/default/images/` |
| Client portal templates | `include/client/*.inc.php` |
| Staff panel theme (`deploy/themes-staff/<slug>/apply.sh`) | `include/staff/*.php`, `include/staff/templates/`, `include/staff/coderise/`, `scp/coderise/` |
| Language packs (`fr.phar`, ...) | `include/i18n/<lang>.phar` (committed: `include/i18n/fr.phar`, see below) |
| Plugins (dossier) | `include/plugins/<name>/plugin.php` et fichiers du plugin (testé : `contrib/stripe-sync`, `contrib/sla-first-response`) |
| Plugins (archive) | `include/plugins/<name>.phar` |

Plugins : procédure exacte, activation, secrets (fichier sous `/etc/osticket/` ou `plugins.env`) et
obstacles constatés dans la section « Plugins et personnalisation » du `README.md` parent.
Un fichier de l'overlay **remplace** le fichier du même chemin (copier l'original puis le modifier).
Tout doit être **committé** avant `scripts/build.sh` (l'image est construite depuis `HEAD`). Git ignore
`*.phar` et `.htaccess`. Un `.htaccess` s'ajoute avec `git add -f`. Un `.phar` demande en plus une
exception étroite dans `.gitignore` **et** dans `Dockerfile.dockerignore` (voir ci-dessous) : avec
`git add -f` seul, il serait committé mais resterait hors de l'image (`**/*.phar` exclu du contexte de
construction).

Keep this folder small: every file that replaces a core file has to be reviewed
again at each osTicket upgrade.

`.phar` files are ignored by the repository `.gitignore` and by the Docker build context
(`Dockerfile.dockerignore`) by default. To bake another language pack or a plugin archive, add a
narrow exception for **that one path** to both files, as done for `fr.phar` (`.gitignore`:
`!deploy/docker/overlay/include/i18n/<lang>.phar`; `Dockerfile.dockerignore`: the same line, the
build context being the repository root). Never drop the `**/*.phar` / `*.phar` lines themselves.
`*.phar binary` (root `.gitattributes` and `deploy/docker/.gitattributes`) keeps `core.autocrlf`
away from the archive.

## French language pack (`include/i18n/fr.phar`)

| | |
|---|---|
| Origin | Official osTicket pack, downloaded by the project owner from <https://osticket.com/download/> (language packs selector, osTicket 1.18.x, French) |
| `MANIFEST.php` | `Language` `fr_FR`, `Id` `lang:fr`, `Phrases-Version` 1.18, `Build-Version` v1.18.2, `Build-Date` Tue, 04 Feb 25 17:26:44 +0000 |
| Size, SHA-256 | 454,090 bytes, `76dfdfc2b94f0698be165fc0533c0704dc60599b3f3ef89d2548874c05ea4201` |
| Content (inspected read-only, 24/10/2026) | 103 files: `MANIFEST.php` and `LC_MESSAGES/messages.mo.php` (each a single `return array(...)` of strings, nothing else), seed data `*.yaml`, `help/tips/*.yaml`, `templates/{email,page,ticket}/*.yaml`, `js/osticket-strings.js`, `js/redactor.js`, `js/jquery.ui.datepicker.js` (string tables only). Stub `<?php __HALT_COMPILER(); ?>`, SHA-1 signature |

osTicket names the language after the file: the code is **`fr`** (not `fr_FR`): this is the value of
Admin > Settings > System > Primary Language (`system_language`) and of an agent's language.
After a rebuild, check `sha256sum` of the file in the image
(`/var/www/html/include/i18n/fr.phar`) against the value above.

**Upgrades:** the pack follows osTicket's minor releases (`Build-Major-Version` 1.18). Download the
pack matching the new osTicket version from the same page with each minor upgrade (1.18.x to 1.19),
replace the file, update the table above (version, date, size, SHA-256), commit, rebuild. A pack
older than the code only leaves the new strings in English; it never blocks the application.
