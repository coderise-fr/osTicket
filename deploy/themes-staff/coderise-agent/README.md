# Thème coderise-agent

Thème Coderise du panneau agent et administrateur d'osTicket, installé par recopie de
fichiers dans `deploy/docker/overlay/` : l'arbre d'osTicket n'est jamais modifié.

    sh deploy/themes-staff/coderise-agent/apply.sh [--dry-run] [--force] [OVERLAY_DIR]
    sh deploy/themes-staff/coderise-agent/remove.sh [--dry-run] [--force] [OVERLAY_DIR]
    sh deploy/themes-staff/coderise-agent/check-drift.sh [--rev REV | --write-base [REV] | ARBRE]

- `MANIFEST` liste les fichiers de `overlay/` (`apply.sh` refuse s'ils diffèrent).
- Le manifeste des fichiers installés est `include/staff/coderise-agent/installed.manifest`
  (le nom du dossier est le slug du thème, requis par `local-demo.sh`).
- Le thème cohabite avec tout thème client de `deploy/themes/`.
- `BASE.sha256` (fichiers du cœur remplacés) et `WATCH.sha256` (fichiers du cœur dont le
  thème dépend) sont des gabarits vides, générés par `check-drift.sh --write-base` en fin
  de chantier ; `check-drift.sh` les tolère en attendant.
