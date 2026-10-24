# Overlay

Files placed in this folder are copied over the osTicket tree at build time, at the
same relative path. Nothing is writable at runtime, so everything that osTicket would
normally install from its admin panel has to be added here and the image rebuilt.

| What | Where in the overlay |
|---|---|
| Client portal stylesheet and images | `assets/default/css/theme.css`, `assets/default/images/` |
| Client portal templates | `include/client/*.inc.php` |
| Staff panel theme (`deploy/themes-staff/<slug>/apply.sh`) | `include/staff/*.php`, `include/staff/templates/`, `include/staff/coderise/`, `scp/coderise/` |
| Language packs (`fr.phar`, ...) | `include/i18n/<lang>.phar` |
| Plugins (dossier) | `include/plugins/<name>/plugin.php` et fichiers du plugin (testé : `contrib/stripe-sync`, `contrib/sla-first-response`) |
| Plugins (archive) | `include/plugins/<name>.phar` |

Plugins : procédure exacte, activation, secrets (fichier sous `/etc/osticket/` ou `plugins.env`) et
obstacles constatés dans la section « Plugins et personnalisation » du `README.md` parent.
Un fichier de l'overlay **remplace** le fichier du même chemin (copier l'original puis le modifier).
Tout doit être **committé** avant `scripts/build.sh` (l'image est construite depuis `HEAD`) ; Git ignore
`*.phar` et `.htaccess` (ajouter avec `git add -f` si nécessaire).

Keep this folder small: every file that replaces a core file has to be reviewed
again at each osTicket upgrade.

`.phar` files are ignored by the repository `.gitignore` and by the Docker build context
(`Dockerfile.dockerignore`) by default. To bake a language pack or plugin, remove the
`**/*.phar` line of `Dockerfile.dockerignore` and add the file with `git add -f`.
