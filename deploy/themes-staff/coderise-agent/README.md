# Thème coderise-agent

Thème Coderise du panneau agent et administrateur d'osTicket (`/scp/`), d'après la maquette
`docs/theme-mockups-admin/` : rail latéral bleu nuit avec bascule Agent / Administration et
files de tickets, barre haute avec recherche, file de tickets avec jauge de premier retour,
fiche ticket à colonne de propriétés, note interne teintée, formulaires d'administration à
réglages modifiés repérés, connexion en carte. Il s'installe par recopie de fichiers dans
`deploy/docker/overlay/` : l'arbre d'osTicket n'est jamais modifié.

## Installer, retirer, surveiller

    sh deploy/themes-staff/coderise-agent/apply.sh [--dry-run] [--force] [OVERLAY_DIR]
    sh deploy/themes-staff/coderise-agent/remove.sh [--dry-run] [--force] [OVERLAY_DIR]
    sh deploy/themes-staff/coderise-agent/check-drift.sh [--rev REV | --write-base [REV] | ARBRE]

- Démonstration locale : `cd deploy/docker && ./scripts/local-demo.sh staff-theme coderise-agent`
  (ou `none`), à partir du commit courant (`git archive` de `HEAD` : committer d'abord).
- Production : `apply.sh`, committer `deploy/docker/overlay/`, construire avec
  `deploy/docker/scripts/build.sh`, déployer avec `deploy/docker/droplet/update.sh`.
  Retrait : `remove.sh`, committer, reconstruire.
- `MANIFEST` liste les fichiers de `overlay/` (`apply.sh` refuse s'ils diffèrent).
- Le manifeste des fichiers installés est `include/staff/coderise-agent/installed.manifest`
  (le nom du dossier est le slug du thème, requis par `local-demo.sh`).
- Le thème cohabite avec tout thème client de `deploy/themes/` (il n'est pas dans ce dossier
  pour cette raison : les `apply.sh` client refusent un autre dossier de `deploy/themes/`).
- Après une mise à jour d'osTicket : `check-drift.sh`. `BASE.sha256` porte les empreintes
  des fichiers du cœur **remplacés** (à rebaser s'ils changent), `WATCH.sha256` celles des
  fichiers du cœur dont le thème **dépend** sans les remplacer (pages et gabarits dont la
  feuille de style et `coderise.js` habillent ou déplacent le balisage, classes et routes
  AJAX appelées, feuilles du cœur surchargées, plugin `sla_first_response`) : la liste est
  `WATCH_FILES` dans le script. Code de sortie 0 = aucune dérive.
- Tests : `sh deploy/themes-staff/tests/test-scripts.sh` (scripts), `sh
  deploy/themes-staff/tests/lint-php.sh` (`php -l` dans l'image de démo),
  `tests/test-helpers.php` et `tests/test-rail.php` (assistants PHP, en CLI dans l'image de
  démo avec `-d open_basedir=`), `node --check overlay/scp/coderise/js/coderise.js`,
  `deploy/themes-staff/tests/overflow.js` (débordement, Playwright),
  `deploy/themes-staff/tests/editor-check.js` (texte saisi dans les éditeurs Redactor de la
  page courante, Playwright ; voir « Saisie dans les éditeurs » ci-dessous).

## Fichiers

Fichiers du cœur remplacés (empreintes dans `BASE.sha256`) :

| Fichier | Pourquoi |
|---|---|
| `include/staff/header.inc.php` | Coquille : rail (navigation du cœur reprise par `cdr_rail()`), barre haute, lien d'évitement, feuille et script du thème ; garde `#pjax-container`, `#content`, les compteurs de files et le reste des contrats du JavaScript du cœur. |
| `include/staff/footer.inc.php` | Ferme la coquille ; mêmes scripts et configuration que le pied du cœur. |
| `include/staff/login.header.php`, `include/staff/login.tpl.php` | Connexion et pages de mot de passe oublié en carte sur fond nuit (mêmes ids et champs). |
| `include/staff/templates/queue-tickets.tmpl.php` | File de tickets : colonne « Premier retour » (jauge) ajoutée en dernière position ; le reste est le gabarit du cœur. |

Fichiers ajoutés : `include/staff/coderise/coderise.inc.php` (traductions `cdr__()`, rail,
jauge), `include/staff/coderise/i18n/fr.php` (catalogue français), `scp/coderise/css/coderise.css`,
`scp/coderise/js/coderise.js` (compteurs des files, fiche ticket, barre d'enregistrement,
tableaux larges), polices Montserrat et Inter locales avec leurs licences OFL, logo et favicon.
Toutes les autres pages (administration, utilisateurs, base de connaissances, tâches,
tableau de bord, journaux…) ne sont habillées que par la feuille de style.

## Jauge de premier retour (règle I-4)

La jauge suit la règle du projet (`contrib/sla-first-response/README.md`) : premier retour =
première entrée de type réponse d'un agent (`staff_id > 0`), ni masquée ni système, **adressée
au client** (`SlaFirstResponsePlugin::isClientReply()` quand le plugin est chargé, sinon un
destinataire « to »). La fenêtre part de la création du ticket (pas de nouvelle fenêtre à la
réouverture). Échéance : celle du plan SLA `Priority 4h` (`CDR_SLA_PRIORITY`) quand le ticket
est sur ce plan actif et n'a jamais été rouvert ; sinon création + `CDR_FIRST_RESPONSE_HOURS`
(osTicket recalcule l'échéance du plan à partir de la date de réouverture, ce qui ouvrirait une
nouvelle fenêtre : elle est donc ignorée pour un ticket rouvert) (4 h ; les deux constantes
se redéfinissent dans `ost-config.php`). Elle n'apparaît que si le plugin `sla_first_response`
est actif (fonction `coderise_contract_level()`), pour les tickets ouverts d'un propriétaire
Priority ; sinon « — ». L'état se lit dans le texte (« Reste 38 min », « Dépassé de 47 min »,
« Tenu en 44 min »), jamais par la couleur seule.

## Textes et langues

Toutes les chaînes du thème sont des msgid anglais passés par `cdr__()` : le pack de langue
d'osTicket d'abord (`__()`), puis `include/staff/coderise/i18n/fr.php` pour un agent dont la
langue est le français. La démo étant en anglais sans pack français, le panneau y est en
anglais ; le français n'apparaît que pour un agent réglé en français.

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

État après le tableau de bord (tâche 9), vérifié en anglais sur la démo locale.

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
5. Icônes GIF du cœur (calendrier, dossiers de la base de connaissances) : aspect
   d'origine. Le calendrier jQuery UI (`#ui-datepicker-div`) est habillé (en-tête nuit,
   jour courant voile, boutons du thème) ; ses flèches restent les icônes du cœur.
   Éditeur Redactor en plein écran : la boîte passe sous `<body>` ; le thème n'y garde
   que la police, la colonne centrée de 950 px et la barre d'outils sont celles du cœur.
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
9. Logo : le rail et la page de connexion affichent le logo Coderise du thème ; le réglage
   « logo du panneau agent » de l'administration n'a plus d'effet visible (le logo
   `logo.php?login` du cœur reste seulement dans le titre masqué de la connexion).
10. Les libellés de sous-menu calculés par le cœur avec un compteur (« My Tasks (3) »,
    composé de `__('My Tasks')` et du nombre dans `scp/tasks.php`) ne peuvent pas être
    traduits par `i18n/fr.php` : seul le pack de langue d'osTicket les traduit.
11. `captures/` pèse environ 15 Mo (174 PNG, dont 72 `interaction-*` pour 4,4 Mo) dans le
    dépôt.
12. Démonstration locale : le thème agent par défaut de `local-demo.sh` est `none` ; sur un
    `up` neuf, passer `--staff-theme coderise-agent` (ensuite `local-demo.sh` garde le choix).

## Non réalisé (Not done)

Éléments de la maquette « Tableau de bord » qui demandent de nouvelles requêtes, donc hors
d'un thème en CSS seul (écarts à revoir après la première recette locale) :

- bandeau d'alerte « N tickets Priority risquent de dépasser leur premier retour » ;
- bandeau de quatre indicateurs (tickets reçus, ouverts, premier retour moyen, premiers
  retours dans l'objectif) et leurs variations ;
- panneaux « Premier retour à risque », « Par sujet d'aide » et « Charge par agent » ;
- sélecteur de période et bouton « Exporter » de l'en-tête : le cœur garde son formulaire
  (date de début, période, Actualiser) et ses boutons « Export » par onglet.

Autres éléments non réalisés :

- **File « À risque »** du rail : le thème n'ajoute ni table ni réglage ; un administrateur
  la crée comme file enregistrée (Admin > Paramètres > Tickets, onglet « Queues »), voir « Thème du panneau agent » dans `docs/guide-utilisateur.md`. Le
  rail l'affiche avec les autres files, sous le nom saisi (non traduit). Elle approche la jauge (champ
  « Answered » et échéance SLA du cœur) sans reprendre exactement la règle I-4 : « Answered »
  repasse à « non » à chaque message du client (`Ticket::onMessage()`), donc un ticket servi à
  temps puis relancé peut y réapparaître alors que la jauge affiche « Tenu en … ».
- Écrans d'administration autres que Paramètres > Tickets et Agents (maquettés) : habillés
  par la feuille de style seulement (listes `table.list`, formulaires, barre
  d'enregistrement), sans gabarit dédié.
- Libellés : les textes du cœur restent ceux d'osTicket (anglais sur la démo) ; seules les
  chaînes du thème passent par `cdr__()` et son catalogue français.
- RTL non vérifié (voir lacune 6).

## Vérifications (tâche 10)

- Débordement horizontal (`tests/overflow.js`, largeur utile sans la barre de défilement),
  à 390, 820 et 1280 px : aucun.
  - Administrateur (`dev.admin`) : accueil, file, fiche ticket, tableau de bord, tâches, base
    de connaissances, profil, administration, Paramètres > Tickets, agents, sujets d'aide,
    plugins, journaux ; et, après lui avoir donné la permission d'agent « User Directory »
    sur la démo locale, liste des utilisateurs, fiche utilisateur, organisations, fiche
    organisation, annuaire des agents.
  - Agent non administrateur (`dev.agent`) : accueil, file, fiche ticket, tableau de bord,
    tâches, base de connaissances, profil ; ses autres chemins (utilisateurs, pages
    d'administration) le renvoient à l'accueil, mesuré à la place.
  - Connexion et mot de passe oublié dans un contexte neuf sans session.
  - Corrigés pendant l'audit : profil à 390 px (tableau imbriqué à `width: 760px` en ligne),
    sélecteur de réponse type de la fiche ticket (select2 à `width: 100%` plus marge
    interne), fiche utilisateur à 390 px (tableau d'informations enfant direct de
    `#content`, désormais empilé sous 920 px).
- Contraste (ratio WCAG de chaque nœud de texte visible, couleur composée sur les fonds des
  ancêtres, seuil 4,5:1 et 3:1 pour le grand texte) : minimum 9,52 (rail), 5,83 (barre
  haute), 4,91 (contenu), 5,83 (connexion). **Périmètre** : 1280 px seulement, compte
  administrateur, en anglais, pages file, fiche ticket, Paramètres > Tickets, agents,
  tableau de bord et connexion ; **non mesurés** : 390 px et menu ouvert, boîtes de
  dialogue, menus déroulants ouverts, RTL, français. Corrigé : texte de la zone de dépôt
  de fichiers de la fiche ticket (`#999` sur `#f2f2f2`, 2,6:1, du cœur), règle limitée à
  `#content.cdr` (les zones de dépôt des boîtes de dialogue gardent la couleur du cœur).
  Exception d'outil : les étiquettes de la légende du graphique sont signalées à tort (le
  texte est sur un calque blanc en dégradé, la couleur de série n'occupe que la pastille de
  12 px). Hors périmètre : contenu des messages (`.thread-body`) et tracé du graphique.
- JavaScript : la suite du dépôt (`setup/test/run-tests.php`) ne vérifie rien en JS dans
  l'image de démo (`jsl` absent) ; `coderise.js` est vérifié avec
  `node --check deploy/themes-staff/coderise-agent/overlay/scp/coderise/js/coderise.js`.
- Clavier : parcours à la touche Tab de la file (114 éléments), d'une fiche ticket et de
  Paramètres > Tickets : chaque élément focalisé a un contour d'au moins 2 px (3 px du thème,
  ou le contour automatique du navigateur sur 5 boutons de l'éditeur). Corrigés : onglets
  `ul.tabs` (le cœur retire le contour) et zone d'édition Redactor (contour sur le cadre).
  Tab reste dans l'éditeur Redactor (comportement du cœur) : les 18 éléments qui le suivent
  ont été vérifiés un par un.

### Saisie dans les éditeurs (QA d'interaction, 2026-10-24)

Les audits précédents ne regardaient que des pages statiques ; un texte tapé dans la zone de
réponse était décalé puis centré (règle `p:last-child` descendante, `max-width: 950px;
margin: auto` posé par Redactor). Règle depuis : **le contenu saisi ou collé n'est jamais
restylé**. Toute règle dont un sélecteur descendant peut atteindre un élément que
l'utilisateur produit (h1-h4, p, em, hr, th, table…) part d'un élément du cœur par des
combinateurs enfant, ou se termine par
`:not(:is(.thread-body, .redactor-box, [contenteditable], .quicknote, .cat-desc) *)` (même
liste que `RICH` dans `coderise.js`, qui ne déplace ni n'étiquette rien dedans).

Mesuré avec `tests/editor-check.js` après saisie réelle (deux paragraphes avec Entrée, gras
et italique par la barre d'outils, liste à puces, titre 2 par le menu « Format », collage
HTML, perte puis reprise du focus, vue source) à **1280, 1720, 2200 et 390 px**, compte
administrateur, en anglais : premier et dernier paragraphe à 18 px du bord (le padding),
`text-align: start`, `display: block`, zone éditable = largeur intérieure du cadre, titre
sans la police du thème, italique de la couleur du texte, vue source à chasse fixe et
pleine largeur. Contextes, tous conformes : réponse, note interne, nouveau ticket (réponse
et note), modification d'un ticket (note), boîte « Edit » d'une entrée du fil, boîte
« Create Task », réponse à une tâche, réponse type, page du portail, FAQ (largeur fixe de
670 px voulue par le cœur, `data-width`), modèle d'e-mail, note d'utilisateur et
d'organisation, signature du profil et du département (éditeurs sans barre d'outils), plein
écran. Aussi vérifiés à 1280 px : menus « Format » et couleur, brouillon enregistré, zone
de dépôt (fichier joint), réponse type et collaborateurs (select2), texte indicatif, calendrier,
`spellcheck`/`lang` de la zone éditable. Écart entre le cadre focalisé (anneau 3 px + 1 px) et
la zone de dépôt qui le suit : 10 px (réponse, note, nouveau ticket, boîtes de dialogue,
tâche).

Corrigés pendant ce passage : titres h1-h4, `hr`, `em` des tableaux de formulaire, `th` de
`table.table` et des boîtes de dialogue, `h3` des boîtes de dialogue, titres de la barre
collante (la zone de réponse est elle-même un `.sticky.bar`) qui atteignaient le contenu ;
vue source prise pour un champ du thème (police, fond, bordure, largeur 80 % en ligne) ;
`em` grisé par le cœur (`.form_table em`, #666) neutralisé ; zone de dépôt collée au cadre
de l'éditeur ; sous 600 px, cellules de formulaire pleine largeur limitées à 20 px de haut
par le cœur (l'éditeur et les lignes suivantes se chevauchaient, réponse type et nouveau
ticket) ; champ de recherche désactivé d'un select2 multiple peint en gris. Règles
restantes qui atteignent le contenu, voulues : couleur des liens, `img { max-width: 100% }`,
coupure des mots dans les cellules.

## Captures

`captures/` : coquille (`shell-*`), connexion (`connexion-*`, `pwreset-*`), composants
(`components-*`, `dropdown-*`, `typeahead-*`), file (`queue-*`), fiche ticket (`ticket-*`),
administration (`admin-*`), tableau de bord (`dashboard-*`), à 1280, 820 et 390 px ;
éditeurs après saisie (`interaction-<contexte>-<largeur>`, 1280, 1720, 2200 et 390 px, plus
menus, plein écran, calendrier, select2 et zone de dépôt à 1280 px).
