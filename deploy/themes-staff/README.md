# Thèmes du panneau agent et administrateur

Thèmes installables pour le panneau `/scp/` d'osTicket, parallèles aux thèmes du portail
client de `deploy/themes/`. Ils sont volontairement dans un dossier séparé : les
`apply.sh` des thèmes client refusent de s'installer quand un autre dossier de
`deploy/themes/` est présent dans l'overlay, alors qu'un thème agent doit cohabiter avec
n'importe quel thème client.

| Dossier | Contenu |
|---|---|
| `coderise-agent/` | Thème Coderise du panneau agent et administrateur |
| `tests/test-scripts.sh` | Teste `apply.sh` / `remove.sh` sur des copies temporaires de l'overlay |
| `tests/overflow.js` | Mesure du débordement horizontal (Playwright, session authentifiée) |

Démonstration locale : `deploy/docker/scripts/local-demo.sh staff-theme coderise-agent`
(ou `none`), ou `up --staff-theme coderise-agent`.

Tests : `sh deploy/themes-staff/tests/test-scripts.sh`.
