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
  `WATCH_FILES` dans le script (68 fichiers au 25/10/2026, dont, pour les passes de fidélité,
  le menu et la liste des états derrière « Marquer comme résolu », les actions groupées
  derrière « Réclamation », `thread.js`, `tips.js`, les compteurs de files et les jointures
  utilisateur/organisation de la file). Code de sortie 0 = aucune dérive.
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
| `include/staff/templates/queue-tickets.tmpl.php` | File de tickets dans l'ordre de la maquette : puce de contrat sous le numéro, client et organisation sous le sujet, colonne « Premier retour » (jauge) juste après le sujet ; les cellules des colonnes configurées restent celles du cœur (`$C->render($T)`). |

Fichiers ajoutés : `include/staff/coderise/coderise.inc.php` (traductions `cdr__()`, rail,
jauge), `include/staff/coderise/i18n/fr.php` (catalogue français), `scp/coderise/css/coderise.css`,
`scp/coderise/js/coderise.js` (compteurs des files, fiche ticket, barre d'enregistrement,
tableaux larges), polices Montserrat et Inter locales avec leurs licences OFL, logo et favicon.
Passe de fidélité (25/10/2026), chargés après `coderise.css` / `coderise.js` : `rail.css`
(rail, barre haute, pied), `icons.css` (icônes Font Awesome et GIF du cœur remplacées par les
icônes au trait de la maquette, en masques CSS), `ticket.css` + `ticket.js` (fil : initiales,
rôle, en-tête d'entrée, événements, défilement), `ticket-chrome.css` + `ticket-chrome.js`
(onglets, barre d'actions, colonne de propriétés, zone de réponse), `queue.css` + `queue.js`
(bloc titre, barre d'actions groupées, assigné, sélection, pied). Les textes dont ces scripts
ont besoin arrivent traduits par des attributs `data-cdr-*` de `#content` (`header.inc.php`,
`cdr_data_attrs()`).
Toutes les autres pages (administration, utilisateurs, base de connaissances, tâches,
tableau de bord, journaux…) ne sont habillées que par la feuille de style.

## Jauge de premier retour (règle I-4)

La jauge suit la règle du projet (`contrib/sla-first-response/README.md`) : premier retour =
première entrée de type réponse d'un agent (`staff_id > 0`), ni masquée ni système, **adressée
au client** (`SlaFirstResponsePlugin::isClientReply()` quand le plugin est chargé, sinon un
destinataire « to »). La fenêtre part de la création du ticket (pas de nouvelle fenêtre à la
réouverture). Échéance : celle du plan SLA `Priority 4h` (`CDR_SLA_PRIORITY`) quand le ticket
est sur ce plan actif et n'a jamais été rouvert ; sinon création + `CDR_FIRST_RESPONSE_HOURS`
(4 h). Les deux constantes se redéfinissent dans `ost-config.php`. Pour un ticket rouvert,
l'échéance du plan est ignorée : osTicket la recalcule à partir de la date de réouverture, ce
qui ouvrirait une nouvelle fenêtre. La jauge n'apparaît que si le plugin `sla_first_response`
est actif (fonction `coderise_contract_level()`), pour les tickets ouverts d'un propriétaire
Priority ; sinon « — ». L'état se lit dans le texte (« Reste 38 min », « Dépassé de 47 min »,
« Tenu en 44 min »), jamais par la couleur seule.

## Textes et langues

Toutes les chaînes du thème sont des msgid anglais passés par `cdr__()` : le pack de langue
d'osTicket d'abord (`__()`), puis `include/staff/coderise/i18n/fr.php` pour un agent dont la
langue est le français. Depuis le 24/10/2026 l'image contient le pack officiel `fr.phar`
(`deploy/docker/overlay/include/i18n/`) et la démo locale est en français (langue principale
`fr`, tous les agents en `fr`).

Avec le pack, **le pack l'emporte** pour les msgid qu'il traduit : 30 entrées de `fr.php` ne
servent alors plus (libellés de menu surtout : « Tableau de Bord », « Services » pour
Departments, « Rubriques d'aide », « Filtre », « Rôle », « Gabarits », « e-mails bannis »,
« Courriel ou nom d'utilisateur », « Connexion »…). Elles restent pour un panneau sans pack.
Le pack traduit « Ticket #%s » par lui-même ; `fr.php` ne le surcharge plus (24/10/2026), le fil
d'Ariane du thème et la barre du cœur affichent donc tous deux « Ticket #… ».
Vérification en français (libellés, jauge, compteur de la barre d'enregistrement, menu mobile, débordements, éditeur) : voir
« Vérifications en français ».

## Écart assumé

- Barre collante (`div.sticky.bar` du cœur) : toujours affichée avec son titre et ses
  actions, comme dans le cœur (la maquette ne montre la barre d'actions groupées qu'une
  fois une ligne cochée). Elle prend l'aspect de la barre nuit une fois flottante ; sur
  la file de tickets, elle est toujours la barre nuit (tâche 6), avec « Aucune sélection »
  ou « N tickets sélectionnés ».
- Recherche : une seule par page. Sur les files de tickets (et les résultats de recherche),
  le champ de la file du cœur reste (requête en cours, saisie semi-automatique, « Avancé »,
  tri) et prend le libellé de la barre haute ; la recherche de la barre haute, identique
  (même requête GET), n'y est pas affichée ; la touche « / » va au champ de la file. Partout
  ailleurs, la recherche est dans la barre haute comme la maquette.
- Fiche ticket (passe de fidélité 2) : ligne d'identité « Ticket #N | état | contrat |
  urgence » et sous-titre « Client, organisation. Reçu le … à … via … » émis par
  `header.inc.php` (`cdr_ticket_idline()`), boutons « Modifier / Imprimer / Marquer comme
  résolu » sous le titre (`ticket-chrome.js` ; « Marquer comme résolu » déclenche le lien de
  fermeture du menu d'état du cœur, donc son dialogue et ses contrôles). Le statut visé est
  choisi par `header.inc.php` (`cdr_resolve_status_id()`) : le statut « Resolved » d'origine
  (id 2) s'il existe et est un état fermé, sinon l'unique statut fermé ; s'il y en a plusieurs
  sans « Resolved », pas de bouton (le thème ne devine pas). Jamais sur un ticket fermé. Le
  dialogue du cœur peut encore refuser la fermeture (tâches ouvertes, champs requis). Écarts : à 2000 px les
  7 à 9 actions du cœur restent sous le titre (la maquette met ses 3 boutons à droite) ; la
  barre nuit fixée n'affiche plus « Ticket #N » (le fil d'Ariane le montre) ; le libellé du
  contrat est celui du plugin (« Priorité » avec le pack français, la maquette écrit
  « Priority ») ; l'état est le nom du statut en base (« Open »).
- Rôle des entrées du fil, d'après le type d'entrée du cœur : « Client » (message),
  « Réponse au client » (réponse d'agent : toujours visible du client, mais pas toujours
  envoyée par e-mail, d'où pas « envoyée »), « Note interne, invisible du client ». Le
  masculin générique remplace « Cliente » (le thème ne connaît pas le genre).
- Message client long : l'aplat voile de la maquette est conservé (rangée plate, teinte
  #E3F8FB très pâle ; libellé ardoise à 5,3:1 dessus) plutôt qu'un en-tête seul teinté : le
  côté client reste repérable d'un coup d'œil, comme dans la maquette.
- File de tickets (`include/staff/templates/queue-tickets.tmpl.php`, gabarit du cœur
  remplacé par le thème ; passe de fidélité 3) : les colonnes configurées de la file et leurs
  cellules restent celles du cœur ; le thème y ajoute, avec une requête par page (propriétaire,
  nom, organisation et état de chaque ligne ; avec le plugin, en plus, une recherche par
  propriétaire distinct et deux à trois requêtes par ticket ouvert Priority pour la jauge,
  bornées par la taille de page) :
  - le client et son organisation sous le sujet (« Léa Moreau, Priority », maquette « Sujet et
    client »), en PHP plutôt qu'en JS (présent sans JS, organisation comprise). Quand la file a
    la colonne « From » du cœur (`user__name`), la ligne reprend **le rendu du cœur de cette
    cellule** (`$C->render($T)` : lien éventuel posé par l'administrateur et pastille du nombre
    de collaborateurs compris), suivi de l'organisation échappée ; sans cette colonne, le nom
    du propriétaire, échappé. La colonne « From » est alors masquée en CSS (classe
    `cdr-col-from`) mais garde sa place dans le DOM (cellules du cœur, lecture sans CSS) ; son
    lien de tri est repris dans l'en-tête du sujet (« Subject / From ») ; ses styles
    conditionnels éventuels (gras…) ne sont pas repris sous le sujet ;
  - la colonne « Premier retour » juste après la colonne du sujet, reconnue par son chemin de
    données (`cdata__subject`, jamais par son en-tête) ; sans colonne sujet, en dernière position ;
  - la puce de contrat sous le numéro (colonne `number`) : « Priority » nuit avec éclair,
    « Standard » au contour, libellé du plugin, seulement si le plugin a une organisation
    Priority pour décider (comme la ligne d'identité).

  Puce et jauge n'apparaissent que si le plugin sla_first_response est actif ; la jauge
  seulement pour les tickets **ouverts** d'un propriétaire Priority ; ticket fermé ou client
  Standard : « — » (« Sans objet »). Les autres colonnes et leur ordre (« Last Updated » entre
  le numéro et le sujet sur la démo, « Priority », en-têtes anglais) sont des réglages de la
  file (classe 3). « Dernière activité : Sacha, 12 min » n'est pas fait : l'auteur de la
  dernière activité n'est pas dans la ligne (il faudrait une requête par ticket).
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
2. Fiche ticket (tâche 7, passes de fidélité des 25/10/2026) : le gabarit du cœur
   (`ticket-view.inc.php`, `thread-entry.tmpl.php`) est conservé. Fait côté thème :
   initiales dans le disque d'avatar, rôle et heure à droite dans l'en-tête d'entrée,
   texte Inter 13,5 px, événements à icône de 14 px et heure à droite, note « Note interne,
   invisible du client » avec cadenas, onglets à icône et pastille de compteur, titres
   « Propriétés » / « Client » avec icône, libellés « Éditer » et « Imprimer » dans la barre,
   éditeur 10/12 px, état et boutons d'envoi sur une ligne alignée à droite, défilement à
   l'ouverture qui ne cache plus l'en-tête de la dernière entrée.
   - Fait dans la passe 2 (classe 1, `header.inc.php` étant déjà remplacé) : ligne d'identité
     (état, contrat, urgence), sous-titre client, boutons « Modifier / Imprimer / Marquer comme
     résolu » sous le titre (voir « Écart assumé »). Classe 1 possible mais non faite, à
     décider : libellés du pack (« Éditer » ailleurs, « Réclamation », « Fil du ticket »), sujet
     d'entrée masqué quand il égale celui du ticket, boutons par entrée au survol.
   - Demanderait de remplacer un autre gabarit du cœur (`ticket-view.inc.php`,
     `thread-entry.tmpl.php`), **non fait par décision** : panneau d'actions, ordre et
     contenu des propriétés, zone de réponse compacte.
   - Impossible ou configuration du cœur (classe 3) : propriétés en liens d'édition plutôt
     qu'en listes, onglet « Journal », champs De / Répondre à / Signature, barre de Redactor
     complète. La colonne n'est collante que si elle tient dans la fenêtre sous la barre
     d'actions flottante, sinon elle défile avec la page.
3. File de tickets : fait côté thème : bloc titre (nom de la file, texte « Visible 1 - 25
   sur environ N » du cœur, Actualiser, Exporter), tableau bord à bord dans le panneau,
   barre d'actions groupées libellée avec compteur de sélection et « Réclamation » (le
   libellé du pack pour Claim), case « tout sélectionner », numéro « #… » souligné, sujet
   non tronqué, assigné avec initiales ou « Non assigné », flèche de tri sur la seule
   colonne triée, pied avec pagination, pastille de compteur du rail ; passe 3 : puce de
   contrat sous le numéro, client et organisation sous le sujet (colonne « From » masquée),
   « Premier retour » juste après le sujet, lignes de 75 px comme la maquette, compteurs sur
   les files de premier niveau du rail (« Open 41 », même réponse `ajax.php/queue/counts`).
   - Non fait : « Dernière activité » relative avec auteur (l'auteur n'est pas dans la ligne).
   - Classe 3 (configuration ou données du cœur) : colonnes, ordre et en-têtes (éditeur de
     colonnes des files, Administration), noms des files, couleurs de priorité ; filtres
     « Contrat / Département / Assigné à / Reçus » (n'existent pas dans le cœur), sous-titre
     « 24 tickets, triés par… » (le total du cœur est approché, « environ »),
     « Enregistrer la recherche ».
   - Le numéro reste en gras sur les tickets que le cœur marque non répondus. Sous ~1180 px,
     le tableau défile latéralement dans `.cdr-hscroll` (pas de cartes empilées comme la
     maquette sous 760 px, faute d'étiquettes de colonne).
   - Pastilles du rail : « Tickets » porte le compteur de la file Open sur toutes les pages
     (une requête `ajax.php/queue/counts`, qui donne aussi ceux des files de premier niveau ;
     une file vide n'a pas de pastille) ; « Tâches » n'en a pas, le cœur ne renvoie aucun
     compteur de tâches dans cette réponse (le sous-menu des tâches garde le sien).
4. Les erreurs de champ du cœur sont un texte rouge avec astérisque à côté du champ ; le
   champ lui-même n'est pas encadré (le cœur ne marque que le message).
5. Icônes GIF du cœur (calendrier, dossiers de la base de connaissances) : aspect
   d'origine. Le calendrier jQuery UI (`#ui-datepicker-div`) est habillé (en-tête nuit,
   jour courant voile, boutons du thème) ; ses flèches restent les icônes du cœur.
   Éditeur Redactor en plein écran : la boîte passe sous `<body>` ; le thème n'y garde
   que la police, la colonne centrée de 950 px et la barre d'outils sont celles du cœur.
6. RTL : vérifié seulement en forçant `dir="rtl"` sur la file et la fiche (rail à droite,
   pas de débordement, propriétés logiques) ; aucune langue RTL n'est installée sur la démo.
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
    traduits par `i18n/fr.php` : seul le pack de langue d'osTicket les traduit (c'est le cas
    depuis l'ajout de `fr.phar` à l'image).
11. `captures/` pèse environ 20 Mo (210 PNG, dont 72 `interaction-*` pour 4,4 Mo, 19
    `fr-*` pour 1,6 Mo, 9 `fidelity-*` pour 1,6 Mo et 8 `fidelity-w3-*` pour 1,4 Mo) dans le dépôt.
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

- Gabarits du cœur non remplacés par décision (`ticket-view.inc.php`, `thread-entry.tmpl.php`,
  gabarits de navigation) : tout écart qui l'exigerait est listé « non fait par décision » ci-dessus.
- **File « À risque »** du rail : le thème n'ajoute ni table ni réglage ; un administrateur
  la crée comme file enregistrée (Admin > Paramètres > Tickets, onglet « Queues »), voir « Thème du panneau agent » dans `docs/guide-utilisateur.md`. Le
  rail l'affiche avec les autres files, sous le nom saisi (non traduit). Elle approche la jauge (champ
  « Answered » et échéance SLA du cœur) sans reprendre exactement la règle I-4 : « Answered »
  repasse à « non » à chaque message du client (`Ticket::onMessage()`), donc un ticket servi à
  temps puis relancé peut y réapparaître alors que la jauge affiche « Tenu en … ».
- Écrans d'administration autres que Paramètres > Tickets et Agents (maquettés) : habillés
  par la feuille de style seulement (listes `table.list`, formulaires, barre
  d'enregistrement), sans gabarit dédié.
- Libellés : les textes du cœur sont ceux d'osTicket et de son pack français ; seules les
  chaînes du thème passent par `cdr__()` et son catalogue. Les contenus en base créés en
  anglais à l'installation (noms et colonnes des files, statuts, priorités…) restent en
  anglais : liste dans `docs/guide-utilisateur.md`, « Interface en français ».
- RTL vérifié en forçant `dir="rtl"` seulement (voir lacune 6).

## Fidélité à la maquette (25/10/2026)

Trois passes « classe 1 » : CSS et JS du thème, et les fichiers qu'il remplace déjà
(`header.inc.php`, `queue-tickets.tmpl.php`, …) ; aucun gabarit du cœur de plus. Passe 1 :
tailles, polices, icônes, bloc titre et barre groupée de la file, fil. Passe 2 : ligne
d'identité, sous-titre client et boutons libellés de la fiche, pastilles et « Ajouter une
file » du rail, exclusion du contenu riche d'`icons.css`. Passe 3 : colonnes de la file dans
l'ordre de la maquette (puce de contrat, client sous le sujet, « Premier retour » après le
sujet), compteurs des files de premier niveau. Les écarts restants sont classés dans les
lacunes 2 et 3 : autre gabarit à remplacer (non fait par décision), ou configuration/absent
du cœur. Tailles en px (le cœur fixe `html` à 14 px).

Contrôle de non-régression : `deploy/themes-staff/tests/fidelity-check.mjs` compare les styles
calculés de la maquette (`docs/theme-mockups-admin/tickets.html`, `ticket.html`) et de la démo
(file, tickets 2, 1 et 45) : tailles, graisses, polices, couleurs et hauteurs de 51 éléments, et
des mesures de mise en page (`top`/`left`, position dans la ligne `row-top`, ordre des colonnes
`col-delta`).
`LOGIN_PASS=… PW_CORE=<chemin de playwright-core> node deploy/themes-staff/tests/fidelity-check.mjs`
(compte `dev.admin`, 1280 × 800) ; une ligne `OK|DIFF` par mesure, puis le total ; code de sortie
= nombre de `DIFF`. Un écart voulu s'ajoute à `ALLOWED_DIFFS` avec sa raison. État au
25/10/2026 (démo 16f92cfe, 1280 × 800) : 157 OK, 0 DIFF, 1 ALLOWED (« Last Updated » entre le
numéro et le sujet : réglage de la file). À 2000 × 1000 (`VIEW_W=2000 VIEW_H=1000`) : 4 DIFF de
position, les boutons de la fiche restent sous le titre au lieu d'être à sa droite (voir « Écart
assumé »).

## Vérifications (tâche 10)

Comptes utilisés : `dev.admin` (administrateur) et `dev.agent` (agent non administrateur) sont
des comptes jetables créés à la main dans la base de la démo locale pour ces vérifications ;
`local-demo/seed.php` ne les crée pas (sur une autre démo : `camille.durand` et `theo.martin`).

- Débordement horizontal (`tests/overflow.js`, largeur utile sans la barre de défilement),
  à 390, 820 et 1280 px : aucun. La liste ci-dessous est celle de la tâche 10 ; la liste
  committée dans `tests/overflow.js` (32 chemins depuis le 24/10/2026) reprend ces pages et
  celles du passage en français.
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
HTML, perte puis reprise du focus, vue source), compte administrateur, en anglais : premier
et dernier paragraphe à 18 px du bord (le padding), `text-align: start`, `display: block`,
zone éditable = largeur intérieure du cadre, titre sans la police du thème, italique de la
couleur du texte, vue source à chasse fixe et pleine largeur. **Ce qui a été mesuré sur la
version finale** : à 1280 px, 16 contextes (réponse, note interne, nouveau ticket (réponse
et note), modification d'un ticket (note), boîte « Edit » d'une entrée du fil, boîte
« Create Task », réponse à une tâche, réponse type, page du portail, FAQ (largeur fixe de
670 px voulue par le cœur, `data-width`), modèle d'e-mail, note d'utilisateur et
d'organisation, signature du profil et du département (éditeurs sans barre d'outils), plein
écran) ; à 390 et 2200 px, 8 de ces contextes ; 1720 px n'a été mesuré que sur une version
antérieure (par une surcharge locale de la route), pas sur la version finale. Aussi vérifiés
à 1280 px : menus « Format » et couleur, brouillon enregistré, zone de dépôt (fichier joint),
réponse type et collaborateurs (select2), texte indicatif, calendrier, `spellcheck`/`lang` de
la zone éditable. Écart entre le cadre focalisé (anneau 3 px + 1 px) et la zone de dépôt qui
le suit : 10 px (réponse, note, nouveau ticket, boîtes de dialogue, tâche) ; cet écart
suppose un `.redactor-box` juste avant la zone de dépôt : avec un simple `textarea` (texte
riche désactivé dans l'administration), la zone de dépôt reste collée au champ. En français
(24/10/2026) : réponse de la fiche ticket à 1280 et 390 px et réponse du nouveau ticket à
1280 px, avec un tableau collé (voir « Vérifications en français »).

**Non testé** : RTL, compte agent non administrateur, glisser-déposer réel d'un fichier
(seul le choix par la boîte de dialogue l'a été), boîtes « lien » et « image » de Redactor.

Règles du cœur qui atteignent encore un tableau collé dans un éditeur placé dans un
`.form_table` (nouveau ticket, réponse type) : `.form_table th` (fond `#eee`),
`.form_table th h4` (blanc sur `#929292`), `.form_table th em` (`display: block`) de
`scp/css/scp.css`, comme sans le thème ; les règles du thème pour ces en-têtes (`th h4`,
`th h4::after`, `th h4 a`, `th em`, `th em b/strong`) portent depuis le 24/10/2026
l'exclusion `RICH` et ne l'atteignent plus.

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

### Vérifications en français (2026-10-24)

Démo locale reconstruite depuis le commit, pack `fr.phar` présent, langue principale `fr`,
agents en `fr` ; comptes jetables `dev.agent` (non administrateur) et `dev.admin` (voir « Vérifications (tâche 10) »).

- `<html lang="fr">` sur le portail, la connexion agent, le panneau et l'administration ;
  dates `22/10/2026 20:09` ; calendrier jQuery UI en français (`janvier`, lundi premier
  jour) ; Redactor en français (`$R.lang.fr` ; boutons « Gras », « Listes », « Lien »…) ;
  textes JavaScript du cœur traduits (`$.oststrings`).
- Chaînes du thème : rail (« Tableau de Bord », « Tâches », « Base de connaissances », « Mon
  profil », « Déconnexion », groupes de l'administration), bascule Agent / Administration,
  jauge (« Tenu en 3 h 09 », « Dépassé de 15 h 39 », « Sans objet » ; « Reste » non visible
  sur les données de la démo, couvert par `tests/test-helpers.php`), compteur « 2 réglages
  modifiés » et marque « modifié », bouton « Menu » à 390 px, page de connexion. Contrôle
  statique : chaque `cdr__('…')` littéral du thème est traduit par le pack ou par `fr.php`
  (aucun manque ; « Ticket #%s » reste tel quel, comme dans le pack).
- Débordement (`tests/overflow.js` tel que committé, 32 chemins agent et administration, à 390,
  820 et 1280 px, connecté `dev.admin`) : aucun. Texte tronqué par le thème : aucun (seuls les sujets de
  tickets, coupés par le cœur, et le texte indicatif du select2 de réponse type à 390 px,
  « Sélectionner la répons… »).
- `tests/editor-check.js` sur la réponse de la fiche ticket après saisie (paragraphes, gras,
  italique, titre, liste, `hr`, tableau collé) : conforme à 1280 et 390 px ; nouveau ticket
  (éditeur dans un `.form_table`) avec un tableau collé : ni police de titre, ni trait cyan,
  ni gris ardoise du thème dans le tableau.
- Captures `captures/fr-*.png`.

## Captures

`captures/` : coquille (`shell-*`), connexion (`connexion-*`, `pwreset-*`), composants
(`components-*`, `dropdown-*`, `typeahead-*`), file (`queue-*`), fiche ticket (`ticket-*`),
administration (`admin-*`), tableau de bord (`dashboard-*`), à 1280, 820 et 390 px ;
éditeurs après saisie (`interaction-<contexte>-<largeur>`, 1280, 1720, 2200 et 390 px, plus
menus, plein écran, calendrier, select2 et zone de dépôt à 1280 px) ; interface en français
(`fr-*`, 1280 et 390 px : connexion, file, rail mobile, fiche ticket et propriétés, éditeur
de réponse, barre d'enregistrement, nouveau ticket avec tableau collé, et portail client
Parcours : accueil, ouverture de ticket, ticket vu par lien d'accès) ; passe de fidélité
(`fidelity-*`, 25/10/2026, avatars activés : file et ticket 2 à 1280 et 2000 px, note interne
du ticket 1, sélection et barre flottante de la file, file à 390 px, ticket à 820 px, RTL
forcé) ; passe 3 (`fidelity-w3-*`) : file à 1280, 2000 et 390 px, sélection et menu Assigner,
file vide, RTL forcé, interface anglaise, maquette et file côte à côte à 1280 px.
