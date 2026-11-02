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
  `WATCH_FILES` dans le script (94 fichiers au 02/11/2026, dont, pour les passes de fidélité,
  le menu et la liste des états derrière « Marquer comme résolu », les actions groupées
  derrière « Réclamation », `thread.js`, `tips.js`, les compteurs de files et les jointures
  utilisateur/organisation de la file ; passe 4 : le plugin de menus `jquery.dropdown.js`,
  `thread-entries.tmpl.php` de l'onglet « Journal », `redactor-osticket.js` et
  `class.staff.php` pour la signature par défaut ; passe 5 : zone de dépôt et infobulles du
  cœur, et pour le tableau de bord `class.report.php` (période), `class.topic.php`,
  `class.format.php`, `class.config.php`, la signature du schéma
  `include/upgrader/streams/core.sig` (tables lues par les requêtes agrégées) et
  `premiere-reponse.sql` ; pour `admin.js` les listes d'administration dont il habille les
  colonnes état et nom : agents, services, rubriques, SLA, équipes, filtres, pages, gabarits,
  clés d'API, bannis, plugins ; passe mineure : `js/jquery.pjax.js`, dont la comparaison de
  version fait d'un lien PJAX vers un autre type de page un chargement complet, et
  `include/mysqli.php`, pour le drapeau de journalisation de `db_query()` et `db_error()`).
  Code de sortie 0 = aucune dérive.
- Tests : `sh deploy/themes-staff/tests/test-scripts.sh` (scripts), `sh
  deploy/themes-staff/tests/lint-php.sh` (`php -l` dans l'image de démo),
  `tests/test-helpers.php`, `tests/test-rail.php` et `tests/test-dashboard.php` (assistants PHP, en CLI dans l'image de
  démo avec `-d open_basedir=`), `node --check overlay/scp/coderise/js/coderise.js`,
  `deploy/themes-staff/tests/overflow.js` (débordement, Playwright),
  `deploy/themes-staff/tests/editor-check.js` (texte saisi dans les éditeurs Redactor de la
  page courante, Playwright ; voir « Saisie dans les éditeurs » ci-dessous).

## Fichiers

Fichiers du cœur remplacés (empreintes dans `BASE.sha256`) :

| Fichier | Pourquoi |
|---|---|
| `include/staff/header.inc.php` | Coquille : rail (navigation du cœur reprise par `cdr_rail()`), barre haute, lien d'évitement, feuille et script du thème ; garde `#pjax-container`, `#content`, les compteurs de files et le reste des contrats du JavaScript du cœur. Version PJAX (`x-pjax-version`, `X-PJAX-Version`) = celle du cœur (`$git`, la même dans toutes les images) + une empreinte des fichiers du thème (`cdr_layout_version()`) : après un déploiement ou un retour arrière, un onglet resté ouvert recharge entièrement sa page suivante au lieu de mêler nouveau balisage et anciens CSS/JS. |
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
Passe 5 (27/10/2026) : `login.css` (connexion et mot de passe oublié) et `dash.css` (tableau de bord),
`include/staff/coderise/dashboard.inc.php` (panneaux du tableau de bord, voir ci-dessous),
`admin.css` + `admin.js` (pages d'administration, voir « Administration »). Ces trois fichiers ne sont
chargés que par leurs pages (voir « Feuilles par type de page » ci-dessous).
Toutes les autres pages (utilisateurs, base de connaissances, tâches, journaux…) ne sont
habillées que par la feuille de style.

### Connexion et tableau de bord (passe 5)

Connexion (`login.css`, `login.tpl.php`, `login.header.php`) : rythme, signature Montserrat,
motif et champs de la maquette ; bandeau neutre à la première visite, rouge avec champs en
erreur et phrase d'aide sous le mot de passe après un échec (serveur : `$_POST && $errors` ;
AJAX : le gestionnaire en ligne existant ajoute `is-err`, aucun script nouveau) ; présentation
complète à 390 px ; bouton « Se connecter ». Contrats gardés : `#loginBox`, `#login`, `#name`,
`#pass`, `#reset-link`, CSRF, 2FA, authentifications externes. Mot de passe oublié : un filtre
`ob_start` limité à `pwreset.php` remplace deux chaînes exactes du cœur (paragraphe « envoyé » non
traduit, bouton « Login » en dur) par le catalogue du thème ; sans entrée, la page reste celle du cœur.
Tout texte du catalogue écrit dans ces pages passe par `Format::htmlchars()` (étiquettes, bouton,
lien, phrase de mot de passe oublié), sans changement visible (captures identiques en français et
en anglais).

Tableau de bord : `scp/dashboard.php` n'affiche que la barre de période, le graphique Raphael et les tableaux de
statistiques du cœur. La maquette a en plus un titre, un bandeau d'alerte, quatre indicateurs et
trois panneaux. `header.inc.php` (un seul bloc, sur `dashboard.php` uniquement) charge
`coderise/dashboard.inc.php`, qui lit les données avec cinq requêtes agrégées à lui (plus celles des
assistants du cœur qu'il appelle : services gérés, noms des sujets d'aide, organisation Priority,
services de l'agent s'ils ne sont pas déjà chargés, soit 8 à 9 requêtes pour la page), une fois
par requête HTTP, puis insère les panneaux dans la sortie du cœur (`ob_start`, `cdr_dash_inject()`) :
titre, bandeau et indicateurs avant le formulaire ; « Premier retour à risque » et « Par sujet
d'aide » dans la colonne de droite à côté du graphique ; « Charge par agent » sous le graphique ;
« Statistiques » du cœur dans un panneau. Le cœur n'est pas modifié ; si son balisage n'est pas celui
attendu (formulaire absent) la page reste telle que le cœur la rend.

- Périmètre : les départements de l'agent (ou ses tickets et ceux de ses équipes si son accès est
  limité). « Charge par agent » prend les services des statistiques « Agent » du cœur : l'agent
  lui-même, les services qu'il gère, et tous ses services s'il a la permission « stats.agents »
  (Administration > Agents > fiche > Permissions). Être administrateur ne suffit pas, comme dans le
  cœur : sur la démo, `dev.admin` (administrateur sans « stats.agents ») ne voit plus que sa ligne.
  Écart restant avec le cœur (choix assumé : le panneau montre une charge, pas une activité) : le cœur choisit les agents par le service de leur
  **activité** sur la période (événements du fil) ; le panneau, qui montre une charge **actuelle**,
  les choisit par **appartenance** (service principal ou accès étendu `staff_dept_access` à l'un de
  ces services). Un agent d'un autre service qui a traité un ticket du service apparaît chez le
  cœur, pas dans le panneau ; un membre sans activité apparaît dans le panneau avec sa charge.
- Dates : la période du cœur (`getDateRange()`) est en heure de la base ; le SQL la garde telle
  quelle (comme les statistiques du cœur), mais toute comparaison avec les dates d'un ticket et le
  sous-titre « Du … au … » passent en UTC (`Misc::db2gmtime()`, `cdr_dash_period()`) : avec une
  base qui n'est pas en UTC, les tickets en bordure de période sont bien classés et le sous-titre
  donne le bon jour (vérifié avec `db_timezone` à +14 h, puis retiré).
- « Voir les N tickets » (bandeau) et « Tout voir (N) » (panneau) ouvrent la liste de **ces**
  tickets : `dashboard.php?cdr_risk=1` (lien `no-pjax`) relit les tickets à risque dans le périmètre
  de l'agent, enregistre la recherche ponctuelle du cœur « Numéro de ticket correspond à
  ^(n1|n2|…)$ » (`$_SESSION['advsearch']['cdr-risk']`, comme les liens `?orgid=` / `?uid=` du cœur)
  et redirige vers `tickets.php?queue=adhoc,cdr-risk` ; la visibilité de l'agent reste celle du
  cœur. La recherche apparaît ensuite dans « Recherches récentes » du menu Recherche (après un clic
  seulement). Sans ticket à risque, ou si un numéro contient autre chose que lettres, chiffres et
  « - », le lien mène à la file par défaut. Un seul ticket : lien direct vers le ticket.
- Requêtes en échec : le panneau n'affiche rien et une ligne part dans le journal PHP (une fois par
  session), sans la journalisation SQL du cœur (`db_query($sql, false)`), qui enverrait un courriel
  d'alerte à l'administrateur à chaque affichage si « alerte sur erreur SQL » est active.
- Plafond : au plus 3000 tickets Priority lus (`CDR_DASH_PRIORITY_LIMIT`), les ouverts d'abord
  (le bandeau et le panneau à risque restent complets), puis les plus récents ; s'il est atteint,
  une note sous les indicateurs le dit (« Moyenne et taux calculés sur les 3 000 tickets Priority
  les plus récents ») et une ligne part dans le journal PHP (une fois par session).
- Contrat Priority (bandeau, panneau « à risque », moyenne et taux de premiers retours, colonne
  « Premier retour tenu ») : seulement si le plugin `sla_first_response` est actif
  (`coderise_contract_level()` et `SlaContractLevel`). Même règle que la jauge de la file : premier
  retour = première réponse d'un agent adressée au client (définition de
  `contrib/sla-first-response/sql/premiere-reponse.sql`), échéance = création + 4 h ou échéance du plan
  « Priority 4h » (actif, ticket jamais rouvert). Sans le plugin, les indicateurs 3 et 4 deviennent
  « Tickets fermés » et « Tickets en retard » et les deux panneaux Priority ne sont pas dessinés.
- Textes : msgids anglais, français dans `i18n/fr.php` (lus par `cdr_t()`, qui donne la priorité au
  catalogue du thème). Tests : `tests/test-dashboard.php` (assistants purs, requêtes simulées et
  comptées, injection, catalogue français complet).

### Administration (passe 5)

`admin.css` et `admin.js` (portée `#content.cdr-is-admin` ; `dashboard.php`, `logs.php` et
`system.php` gardent la carte) suivent `admin-parametres.html` et `admin-agents.html` :

- Titre sur le fond gris (h2 21 px Montserrat 700) ; `admin.js` sort le h2 que le cœur imprime
  dans le formulaire pour le placer devant. Panneaux blancs (rayon 10, anneau 1 px) pour le
  tableau d'une liste ou le formulaire d'une page de réglages ; onglets hors formulaire en haut
  du panneau.
- Listes : bouton d'ajout cyan, « Plus » en contour, pied « Sélectionner » gris. Agents : bande de
  filtres collée au tableau, disque d'initiales 24 px, nom en 600, état « icône + texte »
  (`span.cdr-st`, colonne repérée par son lien de tri `sort=status`, jamais par le texte traduit ;
  pas sur les services, où le cœur compare l'état traduit au mot anglais), dernière connexion en ardoise.
- Formulaires : étiquettes 220 px, sans filet entre champs, filet et barre cyan 28 × 3 sous le titre
  de section, listes seules à 520 px, champs modifiés repérés (barre cyan 4 px, marque « modifié »).
- Barre d'enregistrement nuit **fixée** en bas de la fenêtre sur la largeur de la colonne (54 px,
  boutons 30 px), toujours visible ; compteur « N réglages modifiés » (`coderise.js`) à gauche,
  « Enregistrer » en dernier (déplacé dans le DOM : ordre clavier = ordre visuel). `admin.js` publie
  sa hauteur en `--cdr-savebar-h` (place réservée sous la page, `scroll-padding-bottom`).
- `admin.js` : IIFE idempotente (prêt et `pjax:end`), aucun texte propre, aucun `eval`. Sans lui,
  les pages restent complètes. La place réservée sous la page est la hauteur de la plus haute barre
  épinglée (toutes sont mesurées : elles partagent le bas de la fenêtre ; une barre dans un onglet
  masqué mesure 0) ; elle est recalculée quand une barre change de taille, au redimensionnement et
  après un changement d'onglet.
- Le calendrier jQuery UI passe au-dessus de la barre épinglée sans règle du thème : le cœur
  (`scp.css`) impose `.ui-datepicker { z-index: 200 !important }` (mesuré : calendrier au premier
  plan sur la barre, la barre haute et les dialogues). Une règle `#ui-datepicker-div` avec
  `!important` gagnerait sur celle du cœur (poids de l'id) et ne pourrait que l'abaisser.
- `fidelity-check.mjs` : types `agents` et `settings` ; `OVERLAY_DIR=<overlay>` sert les fichiers
  d'un paquet plus récent que la démo et injecte `admin.css` / `admin.js` dans les pages
  d'administration qui ne les lient pas encore.

### Feuilles par type de page (passe mineure)

`admin.css` + `admin.js` ne sont chargés que par les pages d'administration (`ADMINPAGE`), `dash.css`
que par `dashboard.php` (`cdr_page_kind()`, `cdr_page_assets()`). Une navigation PJAX ne remplace que
`#pjax-container`, jamais `<head>` : la version PJAX (`x-pjax-version` / `X-PJAX-Version`) porte donc
le type de page (`cdr_page_version()` : version du thème, plus `-admin` ou `-dashboard`). Un lien PJAX
vers une page d'un autre type reçoit une autre version et jquery.pjax la charge en entier, avec ses
feuilles. Le cœur fait déjà de la bascule Agent / Administration et de l'onglet Tableau de bord des
chargements complets ; ce qui change : quitter le tableau de bord par un lien PJAX recharge la page
(une requête PJAX perdue puis un chargement complet), et l'entrée « Tableau de bord » du sous-menu,
lien PJAX du cœur, charge maintenant la page en entier (le graphique Raphael du cœur échouait en
PJAX : « Raphael is not defined »). Vérifié avec Playwright (`fixmin/m10.mjs`) : file → utilisateurs
→ tableau de bord → file → réglages → agents → réglages → file, retour et avance de l'historique :
feuilles présentes sur leurs pages seulement, `admin.js` lié une fois, styles appliqués, 0 erreur.

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
  ailleurs, la recherche est dans la barre haute comme la maquette ; sous 920 px, le champ y
  est replié derrière un bouton loupe (`.cdr-search-toggle`, nom accessible du champ,
  `aria-expanded`) qui l'affiche sur une ligne sous la barre ; Échap le referme, et « / »
  passe par ce bouton.
- Fiche ticket (passe de fidélité 2) : ligne d'identité « Ticket #N | état | contrat |
  urgence » et sous-titre « Client, organisation. Reçu le … à … via … » émis par
  `header.inc.php` (`cdr_ticket_idline()`), boutons « Modifier / Imprimer / Marquer comme
  résolu » sous le titre (`ticket-chrome.js` ; « Marquer comme résolu » déclenche le lien de
  fermeture du menu d'état du cœur, donc son dialogue et ses contrôles). Le statut visé est
  choisi par `header.inc.php` (`cdr_resolve_status_id()`) : le statut « Resolved » d'origine
  (id 2) s'il existe et est un état fermé, sinon l'unique statut fermé ; s'il y en a plusieurs
  sans « Resolved », pas de bouton (le thème ne devine pas). Jamais sur un ticket fermé. Le
  dialogue du cœur peut encore refuser la fermeture (tâches ouvertes, champs requis).
  Disposition (passe 4) : les trois boutons libellés forment le groupe de la maquette, les
  autres actions du cœur (répondre, note, état, assigner, transférer, plus) un groupe
  d'icônes compact de 32 px juste après (deux enveloppes du thème, `span.cdr-acts` et
  `span.cdr-tools`, autour des nœuds du cœur déplacés : identifiants, menus et gestionnaires
  intacts). À partir de 1560 px (largeur à laquelle la maquette passe ses boutons à droite du
  titre), le groupe est à droite du bloc titre, sur deux rangées (libellés, puis icônes) aussi
  hautes que le bloc titre ; en dessous, sous le titre comme avant. Même conteneur et même
  barre collante du cœur : la cellule garde sa largeur quand la barre flotte (aucun
  déplacement de la page) et la barre flottante aligne ses boutons à droite.
  Passe 5 (27/10/2026) : le groupe libellé a la largeur de celui de la maquette (le chevron
  du menu d'impression du cœur, toujours là car c'est le seul accès visible à ce menu, prend
  la place de la marge droite d'« Imprimer » : 117 px comme la maquette), donc « Modifier »,
  « Imprimer » et « Marquer comme résolu » sont aux positions de la maquette à toutes les
  largeurs ; à partir de 1840 px (le bloc titre garde la largeur du titre de la maquette,
  785 px), le groupe d'icônes passe sur la même rangée, **avant** le groupe libellé (qui finit
  la rangée comme dans la maquette ; l'ordre du DOM suit, donc l'ordre du clavier aussi) ; de
  1560 à 1839 px, deux rangées comme avant. Les menus à chevron du groupe d'icônes sont plus
  étroits (chevron de 12 px). Sous 1024 px, les libellés restent dans la page (maquette à 820
  et 390 px) ; à 760 px et moins, le groupe libellé prend toute la largeur (« Modifier » et
  « Imprimer » se partagent la rangée, « Marquer comme résolu » sur la sienne) ; la barre
  flottante garde des icônes seules (une rangée compacte), sa réserve dans la page garde la
  hauteur de la barre dans la page (aucun saut). L'infobulle de « Marquer comme résolu » est
  son libellé (msgid du thème), plus le nom brut du statut (« Resolved »). L'infobulle d'un
  bouton à menu est masquée tant que son menu est ouvert (elle en couvrait le haut, en RTL).
  Écarts : la barre nuit fixée n'affiche plus « Ticket #N » (le fil d'Ariane le montre) ; le
  libellé du contrat est celui du plugin (« Priorité » avec le pack français, la maquette écrit
  « Priority ») ; l'état est le nom du statut en base (« Open ») ; les six actions du cœur
  (répondre, note, état, assigner, transférer, plus) restent des icônes en plus des trois
  boutons (la maquette les range ailleurs : onglets du compositeur, propriétés, panneau
  d'actions, ce qui demanderait de remplacer `ticket-view.inc.php`).
- Zone de réponse (passe 4) : une ligne au-dessus de l'éditeur, comme la maquette
  (« À : » suivi du client et de son adresse pour la réponse, « Visible de l'équipe uniquement. Le client ne
  reçoit rien. » pour la note), avec un bouton « Options d'envoi » / « Options de la note »
  (`aria-expanded`, `aria-controls`) qui replie les champs secondaires du cœur : De,
  Destinataires et collaborateurs, Répondre à, Signature (réponse), titre de la note (note).
  Seule leur visibilité change (une classe sur le formulaire) : aucun champ ne sort du
  formulaire, aucun nom ni identifiant ne change, le formulaire envoie les mêmes valeurs
  (vérifié avec `FormData`). Le panneau s'ouvre de lui-même si un champ replié n'a pas sa
  valeur par défaut (première option pour De et Répondre à, signature par défaut de l'agent,
  collaborateurs de la page, titre vide) ou affiche une erreur du serveur (décidé au
  chargement, revu à `pageshow`, retour arrière du navigateur compris, et juste avant l'envoi,
  sans jamais bloquer ni modifier l'envoi) ; tant qu'un champ
  replié diffère de sa valeur par défaut, le bouton porte la marque « modifié ». Restent
  visibles : réponses types, éditeur (barre Redactor complète, composant tiers), zone de
  dépôt, état et boutons d'envoi.
  Passe 5 : la ligne de la note est en deux graisses comme la maquette (« **Visible de
  l'équipe uniquement.** Le client ne reçoit rien. », deux msgids du thème) ; le pied suit
  `.compose-foot` : « Joindre un fichier » au début, puis « Passer l'état à [liste] » et le
  bouton d'envoi. « Joindre un fichier » est la zone de dépôt du cœur elle-même (bloc
  `.attachments` déplacé dans le pied, dans le même formulaire, sans réexécuter son script),
  habillée en bouton `.btn-line.btn-sm` : elle reste la cible du glisser-déposer, son lien
  ouvre toujours le sélecteur de fichiers (nom accessible « Joindre un fichier ») et le texte
  du cœur (« Déposez les fichiers ici ou… ») devient son infobulle ; les fichiers joints
  s'affichent sous le bouton. L'étiquette « Passer l'état à » est un `<label>` du thème lié à
  la liste (celle du cœur reste, masquée) ; l'astérisque « requis » seul du cœur est masqué
  (un vrai message d'erreur ne l'est pas). « Réinitialiser » (absent de la maquette) devient
  un bouton fantôme à icône de 36 px (sa valeur reste son nom accessible et son infobulle).
  La ligne des réponses types est compacte : libellé « Réponse : » masqué, liste select2 de
  220 px alignée à droite, sans cadre, première entrée « Réponses types » (msgid du thème ;
  la maquette met ce bouton dans la barre de l'éditeur) ; la ligne vide du cœur avant
  l'éditeur est masquée. La barre de Redactor (composant tiers, ses 15 boutons) prend les
  dimensions de la maquette (boutons de 30 × 30 px espacés de 1 px, icônes de 16 px, barre
  de 39 px). Mesuré : `FormData` des formulaires de réponse et de note identique avec et sans
  le thème ; sélecteur de fichiers ouvert par le bouton ; réponse type insérée.
- Onglet « Journal » (passe 4) : ajouté après les onglets du cœur, même balisage
  (`ul#ticket_tabs > li > a`), il montre le même contenu que « Fil du ticket » en mode
  journal : seulement les événements (`.thread-event`), en liste compacte, ou « Aucun
  événement n'a encore été enregistré pour ce ticket. ». Son lien (`#cdr-journal`) a son
  propre gestionnaire, qui arrête le clic avant celui des onglets du cœur ; tout autre onglet
  (et les boutons Répondre / Note du cœur, qui cliquent l'onglet du fil) quitte le mode ;
  l'adresse `#cdr-journal` le rouvre au rechargement. La zone de réponse reste affichée (elle
  est hors des onglets dans la maquette ; la cacher ferait aussi casser Redactor, qui mesure
  son éditeur à travers les parents masqués au chargement et les laisse `display: none`).
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
     décider : libellés du pack (« Éditer » ailleurs, « Réclamation », « Fil du ticket » au
     lieu de « Conversation »), boutons par entrée au survol.
   - Fait dans la passe 4 : groupe d'actions à droite du titre à partir de 1560 px, actions
     du cœur en groupe d'icônes compact, champs secondaires de la réponse et de la note
     repliés derrière « Options », onglet « Journal » (voir « Écart assumé »).
   - Fait dans la passe 5 (27/10/2026) : groupe libellé à la largeur de la maquette, groupe
     d'icônes sur la rangée du titre à partir de 1840 px, libellés sous 1024 px, ligne de la
     note en deux graisses, pied « Joindre un fichier | Passer l'état à | bouton » (zone de
     dépôt du cœur en bouton), réponses types compactes, barre Redactor aux dimensions de la
     maquette, sujet d'entrée masqué quand il répète celui du ticket, étiquette de note placée
     comme la maquette, pièces jointes à la hauteur de la maquette, valeur de propriété vide
     affichée « — », boutons à menu du cœur atteignables au clavier (voir « Vérifications »).
   - Demanderait de remplacer un autre gabarit du cœur (`ticket-view.inc.php`,
     `thread-entry.tmpl.php`), **non fait par décision** : panneau d'actions, ordre et
     contenu des propriétés, zone de réponse réécrite (champs supprimés plutôt que repliés,
     bouton « Réponses types » dans la barre de l'éditeur, libellés des boutons d'envoi
     « Publier la note » au lieu de ceux du cœur, sans « Réinitialiser »).
   - Impossible ou configuration du cœur (classe 3) : propriétés en liens d'édition plutôt
     qu'en listes, barre de Redactor complète (15 boutons du cœur au lieu de 7, aux dimensions
     de la maquette), les six
     actions du cœur en plus des trois boutons de la maquette (fonctions réelles, gardées). La colonne n'est collante que si elle tient dans la fenêtre sous la barre
     d'actions flottante, sinon elle défile avec la page.
3. File de tickets : fait côté thème : bloc titre (nom de la file, texte « Visible 1 - 25
   sur environ N » du cœur, Actualiser, Exporter), tableau bord à bord dans le panneau,
   barre d'actions groupées libellée avec compteur de sélection et « Réclamation » (le
   libellé du pack pour Claim), case « tout sélectionner », numéro « #… » souligné, sujet
   sur deux lignes au plus (points de suspension au-delà, texte complet dans le DOM ; les
   annotations du cœur placées après le sujet, trombone des pièces jointes…, sont déplacées
   en fin de première ligne avec les autres pastilles), assigné avec initiales ou « Non assigné », flèche de tri sur la seule
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
   - Le numéro reste en gras sur les tickets que le cœur marque non répondus. De 761 à
     ~1180 px, le tableau défile latéralement dans `.cdr-hscroll`. À 760 px et moins (passe
     5), une carte par ticket comme la maquette : chaque cellule porte l'en-tête de sa colonne
     (`data-label`, `queue-tickets.tmpl.php`), affiché devant la valeur ; le sujet sans
     étiquette sur toute la largeur ; la case à cocher en haut à la fin de la carte (absente
     de la maquette, nécessaire aux actions groupées) ; l'en-tête du tableau n'est pas affiché
     (le tri reste dans « Trier »), la ligne du cœur « Sélectionner : Tous / Aucun / Inverser »
     l'est à sa place.
   - Passe 5 : colonne « Status » du cœur avec l'icône d'état de la maquette (ouvert : boîte
     cyan-profond, fermé : coche succes ; Montserrat 600 12,5 px), colonne du numéro aussi
     étroite que son contenu (`.c-n` de la maquette), quelle que soit sa largeur réglée.
   - Pastilles du rail : « Tickets » porte le compteur de la file Open sur toutes les pages
     (une requête `ajax.php/queue/counts`, qui donne aussi ceux des files de premier niveau ;
     une file vide n'a pas de pastille ; la réponse est gardée 60 s dans le `sessionStorage`
     de l'onglet, par session d'agent, donc la navigation PJAX ne la redemande pas) ; « Tâches » n'en a pas, le cœur ne renvoie aucun
     compteur de tâches dans cette réponse (le sous-menu des tâches garde le sien).
4. Les erreurs de champ du cœur sont un texte rouge avec astérisque à côté du champ ; le
   champ lui-même n'est pas encadré (le cœur ne marque que le message).
5. Icônes GIF du cœur (calendrier, dossiers de la base de connaissances) : aspect
   d'origine. Le calendrier jQuery UI (`#ui-datepicker-div`) est habillé (en-tête nuit,
   jour courant voile, boutons du thème) ; ses flèches restent les icônes du cœur.
   Éditeur Redactor en plein écran : la boîte passe sous `<body>` ; le thème n'y garde
   que la police, la colonne centrée de 950 px et la barre d'outils sont celles du cœur.
6. RTL : vérifié seulement en forçant `dir="rtl"` et `html.rtl` (rail à droite, pas de
   débordement, propriétés logiques) : passe 5 sur la file (1280 et 390 px, cartes), la fiche
   (390, 1280, 2000 px : groupe d'icônes à droite du groupe libellé, chevron d'Imprimer à
   gauche), le tableau de bord et Paramètres > Tickets ; menus de la barre dans la fenêtre et
   au premier plan à 390, 1280, 1600, 1840, 2000 px. Hors thème agent : le graphique Raphael
   du tableau de bord reste de gauche à droite. Aucune langue RTL n'est installée sur la démo.
7. Administration : les listes (agents, équipes, départements, sujets d'aide, SLA,
   plugins, formulaires…) prennent le style `table.list` ; les étiquettes d'état et de
   rôle de la maquette ne sont **pas faisables en CSS** (« Active / Disabled » sont du
   texte brut sans classe), pas plus que l'avatar d'initiales, les filtres « Statut » et
   le pied « N agents ». Les champs en erreur gardent le message du cœur (texte rouge en
   gras), sans cadre rouge. Marques « modifié » : barre cyan et texte sous le libellé de
   la ligne (pas sur le champ lui-même comme la maquette) ; un éditeur riche (Redactor) est
   suivi par les frappes dans sa zone éditable (sa source, réécrite par Redactor au
   démarrage, sert de référence) ; revenir exactement au texte initial ne retire pas
   toujours la marque.
8. Tableau de bord (tâche 9, `dashboard.php` ; `logs.php` reprend `table.list`) : le
   graphique reste celui du cœur (Raphael) ; les couleurs des séries sont posées par le
   script et ne sont pas modifiables en CSS (le panneau, la police et la légende le sont :
   pastille de la série + texte nuit, barré quand la série est masquée). Jusqu'à 780 px, une
   date sur deux de l'axe horizontal est masquée (elles se chevauchaient ; le script du cœur
   ne redessine pas au redimensionnement). Les étiquettes de la légende sont des boutons au
   clavier (`tabindex`, `role="button"`, `aria-pressed`, Entrée et Espace).
9. Logo : le rail, la page de connexion et les pages de réinitialisation du mot de passe
   (bandeau nuit, l'image du cœur restant dans le DOM pour son `alt`) affichent le logo
   Coderise du thème ; le réglage
   « logo du panneau agent » de l'administration n'a plus d'effet visible (le logo
   `logo.php?login` du cœur reste seulement dans le titre masqué de la connexion).
10. Les libellés de sous-menu calculés par le cœur avec un compteur (« My Tasks (3) »,
    composé de `__('My Tasks')` et du nombre dans `scp/tasks.php`) ne peuvent pas être
    traduits par `i18n/fr.php` : seul le pack de langue d'osTicket les traduit (c'est le cas
    depuis l'ajout de `fr.phar` à l'image).
11. `captures/` pèse environ 19 Mo (194 PNG, dont 56 `interaction-*` pour 3,4 Mo, 19
    `fr-*` pour 1,5 Mo, 5 `fidelity-*` des passes 1-2 pour 0,9 Mo, 8 `fidelity-w3-*` pour
    1,4 Mo et 8 `fidelity-w4-*` pour 1,1 Mo) dans le dépôt ; 24 captures redondantes ou
    périmées ont été retirées le 25/10/2026 (au moins une capture par écran est gardée).
12. Démonstration locale : le thème agent par défaut de `local-demo.sh` est `none` ; sur un
    `up` neuf, passer `--staff-theme coderise-agent` (ensuite `local-demo.sh` garde le choix).
13. Avatars : le thème affiche des initiales (`ticket.js`, quel que soit le réglage) et masque
    les images d'avatar du cœur, mais le cœur les **charge** quand les avatars sont activés ;
    avec une source Gravatar (défaut d'osTicket, « Gravatar / L'homme mystérieux »), chaque
    fiche envoie à `www.gravatar.com` (en HTTP) l'empreinte de l'e-mail de chaque auteur. Pour
    l'éviter, en production (interface française, relevé sur la démo) : **Administration >
    Paramètres > Agents**, « Source de l'avatar » = **« Intégré / Membre de la team Oscar »**
    (images locales), et de même **Administration > Paramètres > Utilisateurs**, « Source de
    l'avatar » ; ou bien **Administration > Paramètres > Système**, décocher « Voir les
    avatars » (« Voir les avatars sur les vues de fils. »), ce qui supprime aussi les images.
    Le thème ne peut pas empêcher ce chargement sans modifier le cœur.

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

## Configurer les colonnes de la file pour coller à la maquette

La maquette montre les colonnes « Ticket », « Sujet » (client dessous), « Premier retour »,
« État », « Assigné à », « Dernière activité ». Les colonnes d'une file sont un **réglage de
la file** (en base), pas du thème : un administrateur les change sans code. Chemin relevé sur
la démo (compte administrateur, interface en français, 25/10/2026) :

1. Basculer sur « Administration » (en haut du rail), puis groupe **Paramètres > Tickets**.
2. Onglet **« files »** (liste « file de ticket » : Open, Open / Open, Open / Answered,
   Open / Overdue, My Tickets…, Closed…). Cliquer le nom de la file, par exemple **« Open »**.
3. Page « file de ticket › Gérer une file personnalisée — Open » : onglet **« Colonnes »**
   (« Gérer les colonnes de cette file »). Chaque ligne a un champ d'en-tête, une largeur, un
   bouton « Configuration », une case « Triable » et une corbeille ; la poignée ⇕ à gauche
   déplace la ligne.
4. Renommer en tapant dans le champ d'en-tête : « Last Updated » → **« Dernière activité »**,
   et, pour des en-têtes en français, « Subject » → « Sujet », « Assigned To » → « Assigné à »
   (sans langue secondaire configurée, comme sur la démo, c'est le seul moyen de traduire un
   en-tête : il est stocké tel que saisi, dans une seule langue pour tous les agents ; le champ
   porte une clé de traduction du cœur, `data-translate-tag`, non essayée ici).
5. Ajouter l'état : liste « — Ajouter une colonne — » sous les lignes, choisir **« Status »**,
   bouton **« Ajouter »**, puis renommer son en-tête « État ».
6. Retirer **« Priority »** avec sa corbeille (pas de confirmation : la ligne disparaît, rien
   n'est enregistré avant l'étape 9).
7. Ordonner avec les poignées : **Ticket, Sujet, État, Assigné à, Dernière activité**. Garder
   la colonne « From » (n'importe où) : le thème la masque et affiche son contenu sous le sujet
   (lien et pastille de collaborateurs compris) ; sans elle, le nom du client reste affiché sous
   le sujet, mais en texte simple. « Premier retour » n'est pas une colonne de la file : le thème
   l'insère toujours juste après le sujet. Renommer « From » → **« Client »** : son lien de tri
   est repris dans l'en-tête du sujet, qui se lit alors « Sujet / Client » (maquette « Sujet et
   client ») au lieu de « Sujet / From ».
8. Largeurs (champ à droite de l'en-tête, en px ; des indications pour le navigateur, le
   tableau occupe toute la largeur) : **Sujet 420, État 80, Assigné à 140, Dernière activité
   120** donnent les proportions de la maquette (le sujet prend la place). La largeur de
   « Ticket » est ignorée par le thème (colonne aussi étroite que son contenu).
9. **« Enregistrer les changements »** (message « Mis à jour avec succès de Open »).

Le réglage vaut **pour une file** : chaque file (et chaque sous-file) a ses propres colonnes.
Sur la démo, les sous-files (« Open / Open », « Open / Answered »…) ont la case « Hériter les
colonnes de la file parente » décochée : il faut soit refaire les étapes pour chacune, soit
cocher cette case dans leur onglet « Colonnes » pour qu'elles suivent « Open ». Les files
personnelles des agents ne sont pas touchées. Capture : `captures/fidelity-w4-queue-columns-admin.png`.

**Démo locale (27/10/2026)** : ce guide a été suivi tel quel dans l'interface (compte
`dev.admin`, par un script Playwright qui clique les mêmes contrôles) sur la file « Open »
seulement ; libellés vérifiés (onglet « files », « Colonnes », « — Ajouter une colonne — »,
« Status », « Ajouter », corbeille « Supprimer », « Enregistrer les changements »). Avant :
Ticket 100, Last Updated 150, Subject 300, From 185, Priority 85, Assigned To 160. Après :
**Ticket 100, Sujet 420, État 80, Assigné à 140, Dernière activité 120, Client 185** (Client
masquée par le thème). Gardé sur la démo (données de démonstration). Pour revenir à l'état
d'origine : mêmes écrans, en-têtes et largeurs d'avant, ajouter « Priority », retirer
« Status », ordre d'avant. Le contrôle `fidelity-check.mjs` garde une exception (« Last
Updated » entre le numéro et le sujet) pour une file aux colonnes d'origine ; sur la démo
actuelle elle ne sert plus (la mesure est conforme).

## Fidélité à la maquette (25/10/2026)

Quatre passes « classe 1 » : CSS et JS du thème, et les fichiers qu'il remplace déjà
(`header.inc.php`, `queue-tickets.tmpl.php`, …) ; aucun gabarit du cœur de plus. Passe 1 :
tailles, polices, icônes, bloc titre et barre groupée de la file, fil. Passe 2 : ligne
d'identité, sous-titre client et boutons libellés de la fiche, pastilles et « Ajouter une
file » du rail, exclusion du contenu riche d'`icons.css`. Passe 3 : colonnes de la file dans
l'ordre de la maquette (puce de contrat, client sous le sujet, « Premier retour » après le
sujet), compteurs des files de premier niveau. Passe 4 : voir ci-dessous. Les écarts restants sont classés dans les
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

Passe 4 (démo 3d0bf444) : groupe d'actions à droite du titre au-delà de 1560 px, zone de
réponse repliée, onglet « Journal », lien d'évitement qui atterrit avant le bloc titre de la file.
Paires ajoutées : bloc titre, fin du groupe d'actions et position de « Marquer comme résolu »
(mesurées seulement à partir de 1560 px, `minWidth`), début du groupe (jusqu'à 1559 px,
`maxWidth` : le groupe réel est plus large de 30 px, chevron d'Imprimer), onglet « Journal »,
ligne « À : … » de la zone de réponse. Résultat : **1280 × 800 : 172 OK, 0 DIFF, 1 ALLOWED** ;
**2000 × 1000 : 174 OK, 0 DIFF, 1 ALLOWED** (avant : 153 OK, 4 DIFF).

Passe 5 (27/10/2026, démo b0afed40, colonnes de la file « Open » réglées selon le guide) : le
début du groupe (« Modifier ») est comparé à toutes les largeurs (plus de `maxWidth`), plus la
largeur d'« Imprimer », le groupe d'icônes centré sur la rangée du titre à partir de 1840 px
(mesure `center-y`), la ligne de la note (deux graisses), le pied (« Joindre un fichier »,
« Passer l'état à », hauteur de la liste), la barre Redactor et ses boutons, les réponses
types, l'étiquette de note et le corps du message client (mesure `entry-top`, position dans
l'entrée), les pièces jointes (ticket 3) et la cellule d'état de la file. Résultat :
**1280 × 800 : 218 OK, 0 DIFF, 0 ALLOWED** ; **2000 × 1000 : 222 OK, 0 DIFF, 0 ALLOWED** ;
1600 × 1000 : 221 OK ; 1840 × 1000 : 222 OK (l'exception « Last Updated » ne sert plus sur
la démo : la mesure est conforme).

Passe 5, intégration (démo 42781531, les trois chantiers fusionnés) : types `login` (déconnecté),
`dash`, `agents` et `settings` en plus ; `ONLY=<type>,…` limite la mesure à certains types,
`OVERLAY_DIR` sert un paquet plus récent que la démo. Résultat : **1280 × 800 : 505 OK, 0 DIFF,
0 ALLOWED** ; **2000 × 1000 : 509 OK, 0 DIFF, 0 ALLOWED**. Par type (1280 / 2000) : file 71 / 71,
fiche 116 / 116, note 19 / 23, urgent 5 / 5, pièce jointe 7 / 7, connexion 63 / 63, tableau de
bord 105 / 105, agents 57 / 57, Paramètres > Tickets 62 / 62.

## Vérifications (tâche 10)

Comptes utilisés : `dev.admin` (administrateur) et `dev.agent` (agent non administrateur) sont
des comptes jetables créés à la main dans la base de la démo locale pour ces vérifications ;
`local-demo/seed.php` ne les crée pas (sur une autre démo : `camille.durand` et `theo.martin`).

- Débordement horizontal (`tests/overflow.js`, largeur utile sans la barre de défilement),
  à 390, 820 et 1280 px : aucun. La liste ci-dessous est celle de la tâche 10 ; la liste
  committée dans `tests/overflow.js` (32 chemins depuis le 24/10/2026, 55 et la largeur 2000 px
  depuis le 27/10/2026) reprend ces pages et celles du passage en français.
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

### Passe 5 : contraste, clavier, mouvement, sans plugin, navigateurs (27/10/2026)

Démo b0afed40, interface en français, comptes `dev.admin` et `dev.agent`, requêtes non-GET
bloquées dans les scripts (sauf les réglages volontaires ci-dessous).

- Contraste (chaque nœud de texte visible, couleurs composées avec leur transparence sur les
  fonds des ancêtres et l'opacité, seuil 4,5:1, 3:1 pour le grand texte), à 390 et 1280 px :
  file (et ses menus État / Assigner, barre groupée), fiche 1 et 2 (onglet note compris), les
  quatre menus de la barre (Imprimer, État, Assigner, Plus), dialogues Transférer et
  Assigner, tableau de bord, menu mobile ouvert : **0 échec** (admin et agent). Minimums :
  contenu 4,91, barre haute 5,83, rail 8,21, barres 5,27, menus 19,16, dialogues 6,54.
  Hors mesure : 5 éléments de la légende du graphique (fond en dégradé, voir plus haut).
- Clavier (Tab depuis le haut, 80 arrêts, 390 / 1280 / 2000 px, admin et agent) : chaque
  arrêt a un contour d'au moins 2 px ; ordre rail, barre haute, bloc titre (« Ticket #N »),
  barre d'actions (dans l'ordre visuel, y compris à partir de 1840 px), onglets, fil, zone de
  réponse, colonne des propriétés. Corrigés : les boutons à menu du cœur sans lien (« Plus »,
  chevron de chaque entrée du fil, « Trier » de la file) n'étaient pas atteignables : ils ont
  `tabindex`, `role=button`, `aria-haspopup`, `aria-expanded` ; Entrée / Espace ouvrent le
  menu (Tab y entre), Échap le ferme et rend le focus ; nom : le texte visible, sinon
  l'infobulle, sinon « Plus d'actions ». Une valeur de propriété vide (lien d'édition vide du
  cœur, 0 × 0) affiche « — ».
- `prefers-reduced-motion: reduce` : animations jQuery coupées (`$.fx.off` : barres
  flottantes du cœur, défilement du fil, correction de défilement du thème), plus de
  rotation du chevron « Options ».
- Sans le plugin `sla_first_response` (désactivé puis réactivé dans Administration > Gérer >
  Plugins, « Plus » > « Désactiver » / « Activer ») : file, fiches 1, 2, 45 et tableau de bord
  sans erreur PHP (journaux du conteneur), sans jauge, colonne « Premier retour », puce de
  contrat ni partie contrat de la ligne d'identité, mise en page sans trou ; réactivé : 15
  jauges et 25 puces revenues.
- Firefox et WebKit (versions Playwright déjà installées, rien téléchargé) : file et fiche
  identiques à Chromium (mêmes éléments, 0 débordement, aucune erreur du thème) ; PJAX
  file → fiche → file → fiche ; repli « Options » : WebKit restaure la valeur modifiée au
  retour arrière et le panneau s'ouvre avec la marque « modifié » ; Firefox ne restaure pas
  la valeur (défaut, rien de masqué). Firefox signale le cookie de session `SameSite=None`
  sans `Secure` (démo en HTTP, réglage du cœur, hors thème).

Intégration de la passe 5 (démo 42781531, français, `dev.admin` et `dev.agent`) :
`tests/overflow.js` porte désormais 55 chemins (pièce jointe, réglages, listes et éditions
d'administration) à 390, 820, 1280 et 2000 px : aucun débordement ; console : 0 erreur sur
55 pages par compte (et 110 en RTL forcé à 390 et 1280, sans débordement) ; connexion et mot de
passe oublié à 390 / 820 / 1280 / 2000, état d'erreur compris. PJAX file → fiche → file (deux
fois, sans doublon) et réglages → agents → fiche agent → SLA → réglages dans le même document ;
tableau de bord (lien `no-pjax` et accès direct) : titre, bandeau, 4 indicateurs, 2 panneaux,
graphique, statistiques ; barre d'enregistrement (compteur 1 puis 2 réglages, dernier champ
au-dessus de la barre, réinitialisation) ; repli « Options d'envoi », onglet « Journal »,
« Marquer comme résolu » (dialogue du cœur), 12 menus × 4 largeurs × LTR/RTL dans la fenêtre et
au premier plan ; `editor-check.js` (réponse, note, dialogue Transférer) : ok. Anglais : la
langue d'un seul agent ne suffit pas (le cœur n'accepte qu'une langue activée : principale ou
secondaire) ; vérifié avec `local-demo.sh language en_US`, puis retour à `fr`.

### Passe de correction mineure (02/11/2026)

Les dix constats mineurs de la revue pré-production de la vague 5 (m1 à m10), corrigés sur
`fid4/base`, démo reconstruite (ce034e71), chaque point montré avant / après avec Playwright :

- m1, calendrier sous la barre épinglée : **non reproduit**. Le cœur force déjà
  `.ui-datepicker { z-index: 200 !important }` ; calendrier ouvert sur la barre (z 4) : le point
  du recouvrement renvoie le calendrier, comme dans un dialogue (z 10). La règle proposée
  (`#ui-datepicker-div { z-index: 10 !important }`) l'aurait abaissé de 200 à 10 (à égalité avec
  les dialogues) : non appliquée, raison notée dans `coderise.css`.
- m2, fuseau : comparaisons et sous-titre en UTC, SQL en heure de la base (`cdr_dash_period()`) ;
  démo avec `db_timezone` à +14 h : « Du 3 octobre au 3 novembre » avant, « Du 2 octobre au
  2 novembre » après ; tests avec un décalage de 14 h.
- m3, « Charge par agent » : plus d'élargissement pour un administrateur (comme le cœur), accès
  étendu compté comme appartenance ; écart restant documenté plus haut.
- m4, plafond de 3000 tickets Priority : ouverts d'abord, note visible et une ligne de journal
  (une fois par session) ; testé avec un plafond abaissé (`CDR_DASH_PRIORITY_LIMIT`).
- m5, en-tête du fichier : 5 requêtes propres + 3 à 4 des assistants du cœur ; requêtes sans la
  journalisation SQL du cœur (pas de courriel d'alerte par affichage), une ligne de journal ; testé.
- m6, « Voir les 7 tickets » / « Tout voir (7) » : la file par défaut (41 tickets, 3 des 5 tickets
  du panneau absents de la première page) avant ; la recherche ponctuelle du cœur avec exactement
  les 7 tickets à risque après (« Visible 1 - 7 », `queue-tickets.tmpl.php` compte exactement cette
  recherche ; le cœur affiche « environ 500 » et dix pages pour toute recherche ponctuelle).
- m7, texte du catalogue échappé dans `login.tpl.php` et `login.header.php` : connexion, mot de
  passe oublié et page « envoyé », en français et en anglais, captures identiques au pixel près.
- m8, unités `em` converties en px (taille réelle × em) : `dash.css`, `queue.css`, `login.css` et
  `coderise.css` ; valeurs calculées identiques (606 valeurs, écart 0) à 390 / 820 / 1280 / 2000 et
  en RTL forcé. Seule exception gardée : la marge des paragraphes de `.thread-body` (contenu riche,
  `ticket.css`), proportionnelle au texte du message.
- m9, `admin.js` : réserve = plus haute barre épinglée (deux barres de 130 et 54 px : 54 avant,
  76 px du pied masqués ; 130 après), recalcul au changement d'onglet (sans ResizeObserver : 0 px
  avant, 54 px après).
- m10, `admin.css` / `admin.js` / `dash.css` chargés par leurs pages seulement, version PJAX par
  type de page (voir « Feuilles par type de page ») ; le tableau de bord ouvert par un lien PJAX ne
  donne plus les 5 erreurs du cœur (« Raphael is not defined »).

Contrôles après la passe (démo ce034e71) : `fidelity-check` **1280 × 800 : 505 OK, 0 DIFF** ;
**2000 × 1000 : 509 OK, 0 DIFF** ; `overflow.js` 55 chemins × 390 / 820 / 1280 / 2000, admin et
agent : aucun débordement ; console : 0 erreur (55 pages par compte, 110 en RTL forcé) ; connexion
et mot de passe oublié à 4 largeurs, LTR et RTL : 0 débordement ; `editor-check.js` (réponse, note,
dialogue Transférer) : ok ; tests PHP : test-dashboard 151, test-helpers 235, test-rail 109, 0 échec ;
suite du dépôt : 15 suites, 0 échec, 90 avertissements (inchangé) ; `check-drift.sh` : aucune dérive.

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
éditeurs après saisie (`interaction-<contexte>-<largeur>`, 1280, 2200 et 390 px, plus
menus, plein écran, calendrier, select2 et zone de dépôt à 1280 px) ; interface en français
(`fr-*`, 1280 et 390 px : connexion, file, rail mobile, fiche ticket et propriétés, éditeur
de réponse, barre d'enregistrement, nouveau ticket avec tableau collé, et portail client
Parcours : accueil, ouverture de ticket, ticket vu par lien d'accès) ; passe de fidélité
(`fidelity-*`, 25/10/2026, avatars activés : ticket 2 à 1280, 2000 et 820 px, note interne
du ticket 1, ticket 2 en RTL forcé ; la file est dans `fidelity-w3-*`) ; passe 3 (`fidelity-w3-*`) : file à 1280, 2000 et 390 px, sélection et menu Assigner,
file vide, RTL forcé, interface anglaise, maquette et file côte à côte à 1280 px ; passe 4
(`fidelity-w4-*`) : fiche à 2000 px (groupe à droite du titre) et barre flottante, onglet
« Journal », zone de réponse repliée et ouverte (« modifié »), note repliée, RTL forcé à
2000 px, éditeur de colonnes d'une file (administration) ; passe 5 (`fidelity-w5-*`, 8
captures) : fiche à 2000 px, file, tableau de bord à 1280 et 390 px, liste des agents,
Paramètres > Tickets avec la barre d'enregistrement (réglage modifié), connexion à 1280 et
390 px. 202 captures en tout.
