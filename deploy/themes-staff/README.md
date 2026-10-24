# Thèmes du panneau agent et administrateur

Thèmes installables pour le panneau `/scp/` d'osTicket, parallèles aux thèmes du portail
client de `deploy/themes/`. Ils sont volontairement dans un dossier séparé : les
`apply.sh` des thèmes client refusent de s'installer quand un autre dossier de
`deploy/themes/` est présent dans l'overlay, alors qu'un thème agent doit cohabiter avec
n'importe quel thème client. Un thème agent et un thème client s'installent donc ensemble
dans `deploy/docker/overlay/` ; un seul thème agent à la fois.

| Dossier | Contenu |
|---|---|
| `coderise-agent/` | Thème Coderise du panneau agent et administrateur (voir son README : fichiers remplacés, jauge, lacunes, non réalisé, vérifications, captures) |
| `tests/test-scripts.sh` | Teste `apply.sh` / `remove.sh` sur des copies temporaires de l'overlay, dont la cohabitation avec le thème client installé |
| `tests/lint-php.sh` | `php -l` de chaque fichier PHP du thème, dans l'image de démo |
| `tests/overflow.js` | Mesure du débordement horizontal (Playwright, session authentifiée) |

Chaque thème suit les conventions des thèmes client : `apply.sh`, `remove.sh`,
`check-drift.sh`, `MANIFEST`, `overlay/`, manifeste des fichiers installés
`include/staff/<slug>/installed.manifest` (le nom du dossier est le slug, lu par
`local-demo.sh`).

Démonstration locale (image construite depuis le commit courant) :
`deploy/docker/scripts/local-demo.sh staff-theme coderise-agent` (ou `none`), ou
`up --staff-theme coderise-agent`. Production : `apply.sh`, commit, `build.sh`, puis
`droplet/update.sh` (voir `docs/guide-utilisateur.md`, « Thème du panneau agent »).

Tests : `sh deploy/themes-staff/tests/test-scripts.sh`, `sh deploy/themes-staff/tests/lint-php.sh`.
