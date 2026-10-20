# Overlay

Files placed in this folder are copied over the osTicket tree at build time, at the
same relative path. Nothing is writable at runtime, so everything that osTicket would
normally install from its admin panel has to be added here and the image rebuilt.

| What | Where in the overlay |
|---|---|
| Client portal stylesheet and images | `assets/default/css/theme.css`, `assets/default/images/` |
| Client portal templates | `include/client/*.inc.php` |
| Language packs (`fr.phar`, ...) | `include/i18n/<lang>.phar` |
| Plugins | `include/plugins/<name>.phar` |

Keep this folder small: every file that replaces a core file has to be reviewed
again at each osTicket upgrade.

`.phar` files are ignored by the repository `.gitignore` and by the Docker build context
(`Dockerfile.dockerignore`) by default. To bake a language pack or plugin, remove the
`**/*.phar` line of `Dockerfile.dockerignore` and add the file with `git add -f`.
