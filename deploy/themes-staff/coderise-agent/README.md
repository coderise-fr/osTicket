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
- Administration (tâche 8) : la sous-navigation reste dans les groupes du rail
  (Paramètres, Gérer, E-mails, Agents, comme les onglets d'administration du cœur), pas
  dans la bande d'onglets horizontale de la maquette.
- Barre d'enregistrement : c'est la ligne de boutons du cœur (dernier `<p>` du formulaire
  `form.save` contenant un bouton d'envoi), collée en bas de la fenêtre **dans la carte**
  (la maquette la montre sur toute la largeur de la colonne) et toujours visible ;
  `coderise.js` n'y ajoute que le compteur « N réglage(s) modifié(s) », et seulement une
  fois un champ modifié. Sans JavaScript, la barre colle sans compteur. Le suivi
  (`is-changed`, `is-dirty`) ne concerne que les formulaires de l'administration.

## Lacunes connues (Known gaps)

État après l'administration (tâche 8), vérifié en anglais sur la démo locale.

1. Sous 600 px, les formulaires (`table.form_table`, `table.table` des formulaires
   `form.save`) empilent le libellé au-dessus du champ. Les listes à nombreuses colonnes
   (`table.list`) et les tableaux à plus de deux cellules par ligne défilent encore
   latéralement dans leur propre conteneur (`.cdr-hscroll`) ; aucune page ne déborde.
2. Fiche ticket (tâche 7) : le gabarit du cœur est conservé ; `coderise.js` déplace la
   bande d'onglets dans `#ticket_tabs_container` et regroupe le panneau « Premier retour »
   et les tableaux `table.ticket_info` dans une colonne `aside.cdr-props`. Écarts avec la
   maquette : pas de ligne d'identité (état, contrat, urgence) ni de sous-titre client
   sous le sujet ; les actions restent les boutons-icônes de la barre du cœur (pas de
   « Modifier / Imprimer / Marquer comme résolu ») ; les propriétés sont des liens
   d'édition du cœur, pas des listes déroulantes, sans titre de panneau (« Propriétés »,
   « Client ») ni panneau d'actions ; les avatars sont un disque avec une icône (pas
   d'initiales, aucune donnée pour le CSS) ; pas de compteur dans les onglets ni d'onglet
   « Journal » ; la zone de réponse garde les champs du cœur (De, Destinataires, Répondre
   à, Réponse type, Signature, État). La colonne n'est collante que si elle tient dans
   la fenêtre sous la barre d'actions flottante, sinon elle défile avec la page.
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
7. Administration : les listes (agents, équipes, départements, sujets d'aide, SLA,
   plugins, formulaires…) prennent le style `table.list` ; les étiquettes d'état et de
   rôle de la maquette ne sont **pas faisables en CSS** (« Active / Disabled » sont du
   texte brut sans classe), pas plus que l'avatar d'initiales, les filtres « Statut » et
   le pied « N agents ». Les champs en erreur gardent le message du cœur (texte rouge en
   gras), sans cadre rouge. Marques « modifié » : barre cyan et texte sous le libellé de
   la ligne (pas sur le champ lui-même comme la maquette) ; un éditeur riche (Redactor)
   n'est suivi que s'il déclenche `input`/`change` sur son `textarea`.
8. Tableau de bord (tâche 9, `dashboard.php` ; `logs.php` reprend `table.list`) : le
   graphique reste celui du cœur (Raphael) ; les couleurs des séries sont posées par le
   script et ne sont pas modifiables en CSS (le panneau, la police et la légende le sont :
   pastille de la série + texte nuit, barré quand la série est masquée). Sous ~500 px les
   dates de l'axe horizontal se chevauchent (le script du cœur ne redessine pas au
   redimensionnement). La légende reste cliquable à la souris seulement (éléments `span`).

## Non réalisé (Not done)

Éléments de la maquette « Tableau de bord » qui demandent de nouvelles requêtes, donc hors
d'un thème en CSS seul (écarts à revoir après la première recette locale) :

- bandeau d'alerte « N tickets Priority risquent de dépasser leur premier retour » ;
- bandeau de quatre indicateurs (tickets reçus, ouverts, premier retour moyen, premiers
  retours dans l'objectif) et leurs variations ;
- panneaux « Premier retour à risque », « Par sujet d'aide » et « Charge par agent » ;
- sélecteur de période et bouton « Exporter » de l'en-tête : le cœur garde son formulaire
  (date de début, période, Actualiser) et ses boutons « Export » par onglet.
