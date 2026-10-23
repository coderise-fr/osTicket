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

## Écart assumé

- Barre collante (`div.sticky.bar` du cœur) : toujours affichée avec son titre et ses
  actions, comme dans le cœur (la maquette ne montre la barre d'actions groupées qu'une
  fois une ligne cochée). Elle prend l'aspect de la barre nuit une fois flottante ; sur
  la file de tickets, elle est toujours la barre nuit (tâche 6).
- File de tickets (`include/staff/templates/queue-tickets.tmpl.php`, gabarit du cœur
  remplacé) : seule une colonne « Premier retour » est ajoutée, en dernière position
  (la maquette la place après le sujet) ; les colonnes configurées de la file et leurs
  cellules restent celles du cœur. La jauge n'apparaît que si le plugin
  sla_first_response est actif, et seulement pour les tickets **ouverts** d'un
  propriétaire Priority ; ticket fermé ou client Standard : « — » (« Sans objet »).

## Lacunes connues (Known gaps)

État après la file de tickets (tâche 6), vérifié en anglais sur la démo locale.

1. Sous ~600 px, les tableaux hérités trop larges (formulaires de réglages, profil,
   listes à nombreuses colonnes) défilent latéralement dans leur propre conteneur
   (`.cdr-hscroll`) : aucune page ne déborde, mais les formulaires d'administration ne
   sont pas encore empilés (tâche 8).
2. Fiche ticket : la mise en page reste celle du cœur (en-tête d'informations, fil,
   zone de réponse) avec les composants restylés ; les liens des messages gardent les
   couleurs du courriel (tâche 7).
3. File de tickets : pas de puce de contrat sous le numéro, pas d'avatar d'initiales
   pour l'assigné, pas de filtres « Contrat / Département / Assigné à / Reçus » ni de
   compteur de sélection : les cellules sont rendues par les colonnes configurables du
   cœur (`$C->render()`), inchangées. La priorité devient une étiquette qui garde la
   couleur du cœur. Sous ~1180 px, le tableau défile latéralement dans `.cdr-hscroll`
   (pas de cartes empilées comme la maquette sous 760 px, faute d'étiquettes de colonne).
4. Les erreurs de champ du cœur sont un texte rouge avec astérisque à côté du champ ; le
   champ lui-même n'est pas encadré (le cœur ne marque que le message).
5. Datepicker jQuery UI, icônes GIF du cœur (calendrier, dossiers de la base de
   connaissances) : aspect d'origine.
6. Composants non vérifiés en RTL.
