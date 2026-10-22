# Thème « Ownalytics Atelier » du portail client (`ownalytics-atelier`)

Implémentation de la proposition 03 des maquettes (`docs/theme-mockups/03-atelier/`, issue n° 4)
**aux couleurs d'Ownalytics** (charte « The receipt ») : fond prune `#3B1E54`, papier `#FBFBF6`,
lilas `#CDB6E3`, et pour le jaune le « soleil » `#FFD23F` de la maquette (et non le surligneur
`#F4E04D` de la charte, voir « Points à valider »), des touches de couleurs des plugins (rose,
menthe, mandarine), Bricolage Grotesque pour les titres, Archivo pour le texte, IBM Plex Mono pour les
numéros, cartes arrondies, boutons-pastilles, avatars à initiales et fil de ticket en bulles.
Portail **client** uniquement : le panneau agent (`scp/`) n'est pas touché.

## Place parmi les thèmes

`deploy/themes/` contient plusieurs thèmes alternatifs du portail ; **un seul** peut être
installé à la fois dans `deploy/docker/overlay/`.

| Thème | Marque | Dossier |
|---|---|---|
| Atelier | Coderise (rebrandé depuis la version Ownalytics) | `deploy/themes/atelier/` |
| **Ownalytics Atelier** (ce dossier) | Ownalytics | `deploy/themes/ownalytics-atelier/` |
| Poste de travail | voir son README | `deploy/themes/poste-de-travail/` |
| Autres (`sommet`, `parcours`, `ownalytics-ticket-de-caisse`…) | à venir | `deploy/themes/<nom>/` |

Ce thème est une **copie autonome** de la version Ownalytics du thème Atelier, faite avant que
`atelier` ne passe aux couleurs de Coderise. Les deux partagent la mise en page, les gabarits et
la feuille de style ; ils ne diffèrent que par la marque (voir « Différences avec la version
Coderise ») et par leurs noms internes, distincts pour qu'aucun fichier ne se recouvre :

| Élément | `atelier` | `ownalytics-atelier` |
|---|---|---|
| Dossier des ressources | `assets/atelier/` | `assets/ownalytics-atelier/` |
| Feuille de style | `atelier.css` | `ownalytics-atelier.css` |
| Classe du `<body>` (portée de la feuille) | `atelier` | `ownalytics-atelier` |
| Aide PHP | `include/client/atelier/atelier.inc.php` | `include/client/ownalytics-atelier/ownalytics-atelier.inc.php` |
| Domaine gettext / fonction de traduction | `atelier` / `__a()` | `ownalytics-atelier` / `__oa()` |
| Fonctions, constantes, variables | `atelier_*`, `ATELIER_*` | `oa_atelier_*`, `OA_ATELIER_*` |
| Manifeste d'installation | `include/client/atelier/installed.manifest` | `include/client/ownalytics-atelier/installed.manifest` |

Les deux thèmes remplacent les mêmes gabarits du cœur (`include/client/header.inc.php`…) : c'est
pourquoi ils ne peuvent pas cohabiter et pourquoi `apply.sh` refuse de s'installer par-dessus un
autre thème.

Ce dossier est la seule source du thème. Il ne modifie ni le cœur d'osTicket ni `deploy/docker/` :
`apply.sh` copie `overlay/` dans `deploy/docker/overlay/`, que l'image Docker superpose à osTicket.

## Principe

| Élément | Où | Rôle |
|---|---|---|
| Feuille de style | `overlay/assets/ownalytics-atelier/css/ownalytics-atelier.css` | Remplace `assets/default/css/theme.css` (qui n'est plus chargée) ; écran et impression |
| Polices | `overlay/assets/ownalytics-atelier/fonts/` | Archivo (variable), Bricolage Grotesque 700-800, IBM Plex Mono 500-600, sous-ensemble latin WOFF2, licence SIL OFL 1.1 (`OFL.txt`) |
| Règles Git | `overlay/assets/ownalytics-atelier/.gitattributes` | Marque les polices comme binaires dans `deploy/docker/overlay/` (voir « Installation ») ; refusé par Apache (403) |
| Favicon | `overlay/assets/ownalytics-atelier/img/favicon.svg` | Pictogramme Ownalytics (ticket sur prune) |
| Aide PHP | `overlay/include/client/ownalytics-atelier/ownalytics-atelier.inc.php` | Fonction `__oa()` (chaînes propres au thème), initiales et couleurs des avatars, nom de marque (`OA_ATELIER_BRAND` = « Ownalytics »), niveau de contrat (`oa_atelier_contract()`, `oa_atelier_contract_badge()`, `oa_atelier_contract_promise()`, voir « Niveau de contrat ») |
| Traductions du thème | `overlay/include/client/ownalytics-atelier/i18n/fr/LC_MESSAGES/ownalytics-atelier.mo.php` | Catalogue français des 14 chaînes que seul le thème utilise |
| Gabarits | `overlay/include/client/…` | 9 fichiers du cœur remplacés (liste ci-dessous) |

Rien n'est servi depuis l'extérieur : polices, icônes (Font Awesome déjà fourni par osTicket,
pictogramme en SVG en ligne) et scripts sont locaux. La politique CSP d'osTicket et de
`deploy/docker` n'a pas besoin de changer : le thème n'ajoute aucun script, et ses seules images
en ligne sont un SVG dans le HTML et une flèche de liste déroulante en `data:` dans la feuille.

## Fichiers du cœur remplacés

Chaque gabarit remplacé garde les mêmes champs, noms de champs, actions de formulaire, jeton CSRF
(`csrf_token()`), contrôles d'accès, appels `__()` et échappements (`Format::htmlchars`,
`Format::display`…) que l'original ; la logique (requêtes, filtres, brouillons, pièces jointes)
est recopiée telle quelle. Empreintes d'origine : `BASE.sha256`.

| Fichier | Pourquoi il est remplacé |
|---|---|
| `include/client/header.inc.php` | Marque (pictogramme, « Ownalytics », pastille « Support »), menu en pastilles avec `aria-current`, avatar et nom du client, langues en texte (EN, FR), lien d'évitement, feuille du thème à la place de `theme.css`, classes de page sur `<body>` (`p-<script>`, `v-<vue>`) pour la feuille de style |
| `include/client/footer.inc.php` | Pied de page prune avec la marque, les pages « Autres ressources » actives, copyright et « propulsé par osTicket » ; mêmes scripts de fin que l'original |
| `include/client/open.inc.php` | Formulaire en cartes (coordonnées, thème d'aide + champs dynamiques, captcha), colonne de conseils et questions à la une ; `#topicId` recharge toujours `#dynamic-form` en AJAX (le tableau et le `tbody` sont conservés pour que la réponse AJAX s'y insère) |
| `include/client/login.inc.php` | Deux cartes côte à côte : connexion au compte et accès par e-mail + numéro de ticket (même formulaire et même cible `login.php` que `accesslink.inc.php`) |
| `include/client/tickets.inc.php` | Tickets en cartes au lieu du tableau, onglets Ouverts/Fermés, recherche et filtre par thème, barre de tri (les colonnes triables du tableau), pastille « Nouvelle réponse », pastille de niveau de contrat (avec le plugin `sla_first_response`) ; requêtes d'origine inchangées |
| `include/client/view.inc.php` | En-tête du ticket (numéro, sujet, statut, niveau de contrat avec le plugin `sla_first_response`, date, actions), fil en conversation, formulaire de réponse en carte, colonne « Informations du ticket », « Informations client », champs personnalisés et liste de toutes les pièces jointes |
| `include/client/kb-categories.inc.php` | Accueil de la base de connaissances : grande recherche (absente de cette page à l'origine), filtre par thème, catégories en cartes avec leurs articles |
| `include/client/templates/thread-entry.tmpl.php` | Bulles : messages du client à droite, réponses de l'équipe à gauche, rôle (« Vous », « Équipe support »), avatars à initiales au lieu des images distantes |
| `include/client/templates/thread-event.tmpl.php` | Même balisage ; retire les images d'avatar des événements (osTicket les prend sur Gravatar par défaut) |

Les autres pages gardent leur gabarit d'origine et sont habillées par la feuille de style seule :
accueil (`index.php`), page hors ligne, « Vérifier un ticket » (`accesslink.inc.php`), inscription
et confirmation, mot de passe oublié (3 pages), profil, modification d'un ticket, recherche et
catégorie de la base de connaissances, fiche d'article, page de remerciement (contenu administrable).
Ces fichiers sont listés dans `WATCH.sha256` : s'ils changent, il faut revoir les pages concernées.


## Niveau de contrat (Priority / Standard)

Le support d'Ownalytics (Heatlens, Auditlens) passe par le même portail osTicket que celui de
Coderise : **Priority** = premier retour sous 4 h, 24 h/24 ; **Standard** = jours ouvrés. osTicket
n'a pas de notion de niveau de contrat : le niveau est l'appartenance à l'organisation « Priority »
(alimentée par le plugin `contrib/stripe-sync`). Le thème le lit dans le plugin
`contrib/sla-first-response` (version 0.3 et suivantes), qui expose
`coderise_contract_level($subject)` (README du plugin, sections 9 et 10). Même approche et mêmes
règles que le thème Coderise `atelier`.

Où il apparaît :

| Page | Affichage |
|---|---|
| Liste des tickets (`tickets.inc.php`) | Pastille à droite de l'état, sur chaque carte (onglets Ouverts et Fermés) |
| Suivi d'un ticket (`view.inc.php`) | Pastille dans l'en-tête, après l'état (comme la pastille « Support prioritaire » de la maquette) ; dans « Basic Ticket Information », ligne « First reply » / « Premier retour » : « Within 4 h, 24/7 » (« Sous 4 h, 24 h/24 ») ou « On business days » (« Les jours ouvrés »), **seulement** tant que le ticket est ouvert et que l'équipe n'y a jamais répondu |

- **Dépendance facultative.** Le thème appelle la fonction seulement si
  `function_exists('coderise_contract_level')`. Plugin absent de l'overlay, plugin ou instance
  désactivés : la fonction n'existe pas, **aucune pastille ni aucun délai** ne sont affichés (jamais
  « Standard » par défaut, jamais de promesse 24 h/24) ; le reste du thème est inchangé.
- **Niveau inconnu = rien.** Une réponse de source `default` (organisation Priority non configurée
  ou supprimée dans le plugin, utilisateur inconnu) n'est pas affichée.
- **Qui.** Toujours le **propriétaire** du ticket, jamais un collaborateur : dans la liste, ses propres
  tickets donnent le niveau du client connecté (`coderise_contract_level()`), un ticket partagé celui
  de son propriétaire (`User::lookup($T['user_id'])`, une fois par propriétaire distinct) ; sur le
  ticket, `coderise_contract_level($ticket)`, y compris pour un invité (lien d'accès ou numéro +
  e-mail) : un invité collaborateur voit le niveau du propriétaire, jamais le sien.
- **Coût.** Une valeur par personne et par page (mémo de `oa_atelier_contract()`, en plus du cache du
  plugin) : aucune requête par ligne (mesures dans « Vérifications »).
- **Rendu.** `<span class="pill pill-contract pill-priority">` : soleil `#FFD23F` sur prune `#3B1E54`,
  éclair (Font Awesome local), 9,7:1 ; `<span class="pill pill-contract pill-standard">` : discrète,
  encre douce `#5F5C59` sur le fond de la carte, contour lilas (5,98:1 sur le lilas de l'en-tête,
  davantage sur le blanc et le papier). Libellé configuré dans le plugin, échappé
  (`Format::htmlchars`) ; préfixe « Contract: » / « Contrat : » (`__oa()`) lu par les lecteurs
  d'écran seulement. Impression : pastille noir sur blanc comme les autres.
- **Limite : niveau actuel.** C'est le niveau du propriétaire **aujourd'hui**, pas celui qu'il avait à
  la création du ticket : à la fin d'un abonnement, ses anciens tickets passent en « Standard » (et
  inversement), et le délai affiché suit. Un collègue qui écrit depuis une autre adresse est un autre
  utilisateur, avec son propre niveau. Le délai affiché est celui du contrat, pas une échéance
  calculée : le plan de SLA du ticket (posé par le filtre de création) n'est pas lu, un client
  standard dont le ticket est sur le plan « Priority 4h » voit « Standard » et « jours ouvrés ».

## Chaînes et langues

- Toutes les chaînes déjà présentes dans osTicket passent par `__()` et sont traduites par les packs
  de langue officiels (le pack français `fr.phar` se place dans `deploy/docker/overlay/include/i18n/`,
  voir `deploy/docker/overlay/README.md`).
- Les 14 chaînes propres au thème (« Skip to content », « Support », « Main menu », « You »,
  « Support team », « New reply », « Tips », deux conseils, l'accroche du pied de page, et pour le
  niveau de contrat « Contract: », « First reply », « Within 4 h, 24/7 », « On business days ») passent par
  `__oa()`, c'est-à-dire `_dgettext('ownalytics-atelier', …)`, la couche gettext d'osTicket avec un
  domaine dédié. Le catalogue français est fourni ; il ne s'affiche que lorsque le français est une
  langue active du portail (donc avec le pack officiel). Pour une autre langue : copier
  `i18n/fr/LC_MESSAGES/ownalytics-atelier.mo.php` dans `i18n/<code>/LC_MESSAGES/` et traduire.
  Le catalogue n'est pas sous `include/i18n/` exprès : un dossier à cet endroit ferait apparaître
  une langue de plus dans osTicket.
- Les accroches de l'accueil, de la page de remerciement et de la page hors ligne sont du **contenu
  administrable** (Admin > Gérer > Pages), traduisible par langue dans osTicket. Contenu proposé
  ci-dessous.

## Installation

Depuis la racine du dépôt :

```sh
deploy/themes/ownalytics-atelier/check-drift.sh       # le thème correspond-il à la version d'osTicket ?
deploy/themes/ownalytics-atelier/apply.sh --dry-run   # ce qui va être copié
deploy/themes/ownalytics-atelier/apply.sh             # copie dans deploy/docker/overlay/ + manifeste
git add -A deploy/docker/overlay && git commit -m "overlay: thème Ownalytics Atelier"
deploy/docker/scripts/build.sh build                  # l'image est construite depuis le commit
cd deploy/docker && docker compose up -d --wait web cron
```

`apply.sh` note dans `deploy/docker/overlay/include/client/ownalytics-atelier/installed.manifest`
(non servi par Apache) chaque fichier installé avec son empreinte. Il est idempotent : relancé, il
ne copie que ce qui a changé et retire les fichiers d'une ancienne version du thème. `--force`
écrase les fichiers du thème modifiés à la main dans l'overlay.

**Polices et Git sous Windows.** Avec `core.autocrlf=true`, Git peut prendre une police WOFF2 pour
du texte et l'altérer au `git add` de l'overlay (constaté : `bricolage-grotesque-700-800-latin.woff2`
committée avec un autre contenu, donc une police cassée dans l'image). Le thème installe pour cela
`assets/ownalytics-atelier/.gitattributes` (`*.woff2 binary`) avec ses fichiers : il doit être
présent **avant** le `git add` (c'est le cas avec `apply.sh`). Vérifié : polices servies par
l'image identiques octet pour octet à celles du thème.

### Refus si un autre thème est installé

`apply.sh` refuse (code 1, rien n'est copié) dès qu'il trouve l'une de ces traces d'un autre thème
dans `deploy/docker/overlay/`, **sans liste de noms codée en dur** (un thème ajouté plus tard est
reconnu de la même façon) :

1. un fichier `installed.manifest` (ou `*.installed.manifest`) ailleurs que dans
   `include/client/ownalytics-atelier/` : c'est le cas des thèmes qui tiennent un manifeste,
   comme `atelier` (`include/client/atelier/installed.manifest`) ;
2. un dossier `include/client/<nom>/` autre que `templates/` et le sien, ou `assets/<nom>/` autre
   que `default/`, `font/` et le sien : osTicket n'a que ceux-là, tout autre dossier vient d'un
   thème, y compris d'un thème sans manifeste (`poste-de-travail` a `include/client/poste-de-travail/`
   et `assets/poste-de-travail/`) ;
3. un fichier identique à un fichier de `overlay/` de n'importe quel autre dossier de
   `deploy/themes/` (sauf s'il figure dans son propre manifeste).

Le message nomme le thème trouvé et sa commande de retrait (`deploy/themes/<nom>/remove.sh` s'il
existe). Il refuse aussi d'écraser un fichier de l'overlay qui n'appartient pas au thème
(personnalisation locale), signalé comme « étranger ». Dans l'autre sens, les scripts actuels de
`atelier` (manifeste `include/client/<dossier de deploy/themes>/installed.manifest`) et de
`poste-de-travail` (fichiers identiques) détectent ce thème installé et refusent eux aussi (vérifié).

### Réglages conseillés dans l'administration (une fois)

1. **Pages** : remplacer le contenu de « Landing » (accueil), « Thank You » et « Offline ». Exemple
   pour l'accueil (anglais, puis traduction française dans l'onglet de langue de la page) :
   ```html
   <h1>What is going on at your end?</h1>
   <p>Tell us in a sentence. A person from the Ownalytics team reads every ticket, in English or in French.</p>
   <ul><li>A person answers you</li><li>In English or French</li><li>Your data stays with you</li></ul>
   ```
   ```html
   <h1>Que se passe-t-il de votre côté&nbsp;?</h1>
   <p>Dites-le-nous en une phrase. Une personne de l’équipe Ownalytics lit chaque ticket, en français ou en anglais.</p>
   <ul><li>Une personne vous répond</li><li>En français ou en anglais</li><li>Vos données restent chez vous</li></ul>
   ```
   La liste `<ul>` de l'accueil devient la ligne de garanties avec coches ; le `<h1>` devient le titre
   géant. Page de remerciement, par exemple :
   `<h1>Thank you, %{ticket.name.first}.</h1><p>Your ticket <strong>#%{ticket.number}</strong> is open…</p>`.
2. **Base de connaissances** : mettre en avant (« Featured ») les catégories et articles à montrer à
   l'accueil ; activer la base (elle n'apparaît que si au moins un article est publié).
3. **Logo** : ne rien téléverser pour garder le logotype du thème. Si un logo est téléversé
   (Admin > Paramètres > Pages), l'en-tête l'affiche à la place du pictogramme et du nom.
4. **Nom de l'entreprise** (Admin > Gérer > Formulaires > Company Information) : affiché dans le
   copyright du pied de page.
5. **Niveau de contrat** (pastilles et premier retour attendu, facultatif) : plugin
   `contrib/sla-first-response/plugin/sla_first_response/` (0.3 ou plus) copié dans
   `deploy/docker/overlay/include/plugins/sla_first_response/`, installé et activé (Admin > Gérer >
   Extensions), avec une instance active dont le champ « Organisation Priority » désigne la même
   organisation que le plugin Stripe. Sans ce réglage, ni pastille ni délai ne sont affichés.

## Désinstallation

```sh
deploy/themes/ownalytics-atelier/remove.sh --dry-run
deploy/themes/ownalytics-atelier/remove.sh
git add -A deploy/docker/overlay && git commit -m "overlay: retrait du thème Ownalytics Atelier"
deploy/docker/scripts/build.sh build && (cd deploy/docker && docker compose up -d --wait web cron)
```

`remove.sh` ne retire que les fichiers listés dans le manifeste et **non modifiés** depuis
l'installation (les autres sont signalés et gardés, le manifeste aussi ; `--force` les retire).
Il ne touche jamais aux plugins, packs de langue ou autre thème présents dans l'overlay.
Le portail retrouve alors le thème d'origine d'osTicket. Aucune donnée n'est modifiée en base ;
les contenus de pages saisis dans l'administration restent valables.

## Basculer vers un autre thème (ou en revenir)

```sh
deploy/themes/ownalytics-atelier/remove.sh        # retirer le thème installé
deploy/themes/atelier/apply.sh                    # installer l'autre (ici la version Coderise)
git add -A deploy/docker/overlay && git commit -m "overlay: thème Atelier (Coderise)"
deploy/docker/scripts/build.sh build && (cd deploy/docker && docker compose up -d --wait web cron)
```

Et inversement (`deploy/themes/atelier/remove.sh` puis `deploy/themes/ownalytics-atelier/apply.sh`).
Même chose avec `poste-de-travail` ou tout autre thème : toujours retirer l'installé avec **son**
`remove.sh` avant d'appliquer le suivant. Les contenus de pages (accroches qui citent
« Ownalytics ») sont à adapter dans l'administration lors d'un changement de marque.

## Différences avec la version Coderise (`deploy/themes/atelier/`)

Uniquement la **marque** : nom affiché dans l'en-tête, le pied de page et le titre (`Ownalytics`),
pictogramme et favicon, couleurs de la charte, et les contenus proposés (accroches, exemples). La
structure, les gabarits, les parcours, l'accessibilité et les scripts sont les mêmes au moment de
la copie (commit `2c9292ef`, avant le rebranding de `atelier`), aux noms internes près (tableau
ci-dessus) et à deux ajouts propres à ce dossier : le refus générique multi-thèmes d'`apply.sh` et
le `.gitattributes` des polices. Les évolutions faites ensuite dans l'un des deux thèmes ne sont
**pas** reportées automatiquement dans l'autre. Le niveau de contrat (Priority / Standard) a été
reporté à la main (mêmes règles ; en plus ici, le délai « premier retour » selon le niveau ; sans
l'indicateur « premier retour sous 4 h » tiré du plan de SLA, propre à `atelier`).

## Mise à jour d'osTicket

Un gabarit remplacé ne suit plus les correctifs du gabarit d'origine : à chaque mise à jour
d'osTicket (fusion d'une nouvelle version dans `develop`), avant de reconstruire l'image :

1. `deploy/themes/ownalytics-atelier/check-drift.sh` (compare au commit courant ; `--rev <tag>` pour
   un autre commit, ou un chemin vers une arborescence osTicket extraite). Il liste :
   - les **fichiers remplacés** qui ont changé (`BASE.sha256`) : reporter le changement dans le
     fichier de `overlay/` (`git diff <commit de base> HEAD -- <fichier>` donne la différence ;
     les correctifs de sécurité sur les gabarits sont la priorité) ;
   - les **fichiers habillés** qui ont changé (`WATCH.sha256`) : vérifier visuellement les pages
     concernées et ajuster `ownalytics-atelier.css` si le balisage a bougé.
2. Quand c'est fait : `deploy/themes/ownalytics-atelier/check-drift.sh --write-base` (réécrit les
   empreintes à partir du nouveau commit), committer, puis `apply.sh` (met à jour l'overlay),
   committer, reconstruire.

Code de sortie de `check-drift.sh` : 0 sans dérive, 1 avec dérive (utilisable en intégration continue).
Commit de base des empreintes actuelles : `2c9292ef` (branche `develop`, osTicket 1.18.x ; mêmes
empreintes qu'au commit `51112176` de la version d'origine : aucun fichier du cœur n'a changé entre les deux).

## Vérifications faites (22/10/2026, sur ce thème)

Instance réelle : images `runtime` et `install` construites par la recette `deploy/docker`
(`scripts/build.sh`, depuis un commit contenant l'overlay produit par `apply.sh`), installation
par l'assistant `/setup/`, puis passage sur l'image `runtime` durcie (racine en lecture seule,
`www-data`, `open_basedir`), MariaDB 11.4. Données de test : un client, 6 tickets ouverts et fermés
avec pièces jointes et réponses de deux agents, un collaborateur, un ticket d'un autre client,
9 articles en 4 catégories, un formulaire personnalisé à 6 champs lié à 3 thèmes d'aide.
Navigateur : Chromium privé piloté par `playwright-core`. Détail : `captures/parcours-*.txt`,
`captures/audit.json`.

- **Parcours réels**, à 1280 px et à 390 px, 17 contrôles par largeur, tous passés : ouverture d'un
  ticket sans compte (erreurs, champs dynamiques rechargés en AJAX, éditeur riche, pièce jointe,
  page de remerciement avec numéro) ; demande de lien d'accès (message affiché) ; connexion (erreur
  puis succès) ; liste (onglet Fermés, recherche par numéro, état vide, isolation du ticket d'un
  autre client) ; suivi d'un ticket et réponse avec pièce jointe (publiée, message de succès) ;
  inscription (page « à confirmer ») ; demande de mot de passe oublié ; base de connaissances
  (recherche, fiche d'article, filtre par thème).
- **Captures** (`captures/`) : 18 pages en bureau 1280 px et mobile 390 px, plus les étapes de
  parcours, l'impression du suivi et le focus clavier, toutes refaites sur ce thème (les captures
  copiées de la version d'origine ont été remplacées).
- **Console et réseau** : aucune erreur JavaScript, aucune réponse HTTP ≥ 400, aucune requête vers
  un autre hôte, sur toutes les pages et tous les parcours.
- **Journaux** : aucune erreur ni avertissement PHP dans les journaux du conteneur (seulement les
  refus 403 attendus des tests d'accès direct) ; journal système d'osTicket : uniquement « Mailer
  Error » (aucun serveur de courrier configuré sur l'instance de test, sans rapport avec le thème).
- **Débordement horizontal à 390 px** : aucun (ni à 1280 px). **Contrastes** : minimum 5,37:1,
  aucun texte sous le seuil WCAG AA.
- **Clavier** : lien d'évitement en premier, qui place le focus sur `#content` ; focus visible de
  3 px (soleil sur prune, prune sur clair) ; `prefers-reduced-motion` coupe les transitions.
- **Traductions du thème** : dans le conteneur, le domaine `ownalytics-atelier` charge le catalogue
  français (`Skip to content` → « Aller au contenu », etc.) ; `__oa()` retombe sur l'anglais.
- **Apache** : en accès direct, les gabarits, l'aide PHP, le catalogue, le manifeste et le
  `.gitattributes` répondent 403 ; feuille de style, polices (`font/woff2`), `OFL.txt` et favicon
  répondent 200.
- **Scripts** : `apply.sh` (simulation, installation de 19 fichiers, idempotence) ; `remove.sh`
  (simulation, retrait, idempotence, conservation d'un fichier modifié puis `--force`, plugin
  présent dans l'overlay conservé) ; refus d'un fichier étranger ; refus quand un autre thème est
  présent : manifeste de `atelier`, manifeste d'un thème inconnu (`sommet`), dossier d'un thème
  sans manifeste (`assets/parcours/`), `poste-de-travail` réellement installé par son script,
  `atelier` réellement installé par son script ; refus de `atelier/apply.sh` et
  `poste-de-travail/apply.sh` quand ce thème est installé. `git status` propre ensuite.
- **Dérive** : `check-drift.sh` sans dérive sur le commit ; sur une copie modifiée, détection d'un
  gabarit remplacé modifié, d'un supprimé et d'un fichier habillé modifié ; fins de ligne CRLF ignorées.

Non vérifié sur ce thème : les étapes qui demandent un courrier reçu (lien d'accès d'un invité et
réponse de l'invité, confirmation d'inscription, nouveau mot de passe), la page hors ligne et le
rendu en français avec le pack officiel ; elles l'ont été sur la version d'origine, au code
identique hors renommage.

### Niveau de contrat (22/10/2026, second passage)

Nouvelle instance, même recette : `git archive` du commit dans une copie, `sommet` (installé dans
l'overlay committé) retiré par son `remove.sh`, ce thème appliqué par `apply.sh`, plugin
`contrib/sla-first-response/plugin/sla_first_response/` (0.3) copié dans
`include/plugins/sla_first_response/` de l'overlay **de la copie** (non committé) ; images `install`
puis `runtime` construites par `docker compose` sur cette copie, installation par l'assistant web
(requêtes HTTP), MariaDB 11.4, cron arrêté. Plugin installé et activé (script PHP dans le conteneur,
mêmes appels que l'administration), instance active avec les plans « Priority 4h » / « Priority 4h -
1re reponse faite » et l'organisation « Priority ». Données : Léa (organisation Priority, 38 tickets :
31 ouverts, 7 fermés ; 12 en Default SLA, 17 en Priority 4h, 9 basculés **par le plugin** sur le plan
désactivé après une réponse adressée au client), Hugo (organisation « Atelier Lumen », standard, un
ticket sur le plan Priority 4h), Nina (sans organisation), Marc et Zoé (organisation Priority, sans
compte), tickets partagés dans les deux sens. Navigateur : Chromium privé (`playwright-core`).
Détail : `captures/niveau-contrat-{actif,desactive,absent}.txt` et `.json`.

- **Plugin actif**, à 1280 et 390 px, 54 contrôles, tous passés : liste de Léa, une pastille par ligne
  (32 ouverts dont le ticket de Hugo : 25 + 7 en page 2 ; 7 fermés), « Priority » sur ses tickets quel que soit le plan, « Standard » sur
  le ticket de Hugo dont elle est collaboratrice ; suivi de Léa : « Priority » et « Within 4 h, 24/7 »
  sur le ticket sans réponse, pas de délai sur un ticket répondu (y compris après un nouveau message
  de Léa, qui remet `isanswered` à 0) ni sur un ticket fermé ; événement de bascule du plugin
  invisible ; réponse de Léa publiée ; Léa sur le ticket de Hugo : « Standard », « On business
  days » ; Hugo : « Standard » sur ses tickets (y compris celui sur le plan Priority 4h), « Priority »
  sur celui de Léa ; Nina : « Standard » ; invité Marc (propriétaire, Priority) : « Priority » et
  « Within 4 h, 24/7 » ; invitée Zoé (collaboratrice, elle-même Priority) sur le ticket de Nina :
  « Standard ». Préfixe « Contract: » masqué à l'écran, présent pour les lecteurs d'écran.
- **Plugin désactivé** (en base) puis **absent de l'image** (dossier retiré de l'overlay, image
  reconstruite, plugin toujours enregistré actif en base) : aucune pastille, aucun « Contract: »,
  aucun délai ni « 24/7 » / « business days » sur la liste, le suivi et l'accès invité ; aucune erreur.
- **Requêtes SQL** (compteur `Questions` de MariaDB, minimum de 3 mesures, page HTML seule) :

  | Page (Léa sauf mention) | Plugin actif | Désactivé | Absent |
  |---|---|---|---|
  | Accueil (aucune pastille : coût du chargement du plugin) | 30 | 26 | |
  | Liste ouverts, page de 25 lignes | 38 | 34 | 34 |
  | Liste ouverts, 32 lignes (50 par page) | 38 | 33 | 34 |
  | Liste fermés, 7 lignes | 37 | 33 | 34 |
  | Liste de Hugo (un ticket d'un autre propriétaire) | 39 | 33 | 34 |
  | Suivi | 52 | 48 | 49 |

  Le chargement du plugin coûte 4 requêtes ; les 32 pastilles de la liste de Léa (dont une d'un autre propriétaire) **0 à 1**, la même chose pour 25
  ou 32 lignes et pour les 7 fermés (aucune requête par ligne) ; la liste de Hugo, avec un autre propriétaire, 2.
- **Console et réseau** : 0 erreur JavaScript, 0 réponse HTTP ≥ 400, 0 requête vers un autre hôte ;
  journaux du conteneur sans erreur ni avertissement PHP (seuls « sendmail: not found » et « Mailer
  Error » : pas de serveur de courrier sur l'instance de test). Aucun débordement horizontal à 390 et
  1280 px.
- **Contrastes mesurés** (fond réel composé) : « Priority » 9,7:1 ; « Standard » 5,98:1 (sur le lilas
  de l'en-tête) ; minimum de toutes les pages contrôlées 5,37:1, aucun texte sous 4,5:1.
- **Scripts** : `check-drift.sh` sans dérive ; sur un export du commit : `apply.sh` refusé tant que
  `sommet` est installé, puis simulation, installation (19 fichiers), idempotence, refus de
  `sommet/apply.sh` tant que ce thème est installé, `remove.sh` : overlay identique (empreintes) à
  l'état d'avant l'installation. Suite statique `setup/test/run-tests.php` (php:8.3-cli) : 0 échec.
- **Captures remplacées** : `mes-tickets-*`, `mes-tickets-fermes-*`, `suivi-ticket-*`,
  `suivi-ticket-ferme-*`, `suivi-reponse-envoyee-*`, `impression-suivi-ticket.png` ; nouvelles :
  `suivi-ticket-repondu-*`, `niveau-collaborateur-standard-*`, `niveau-standard-liste-*`,
  `niveau-standard-suivi-*`, `niveau-sans-organisation-liste-*`, `niveau-invite-priority-*`,
  `niveau-invite-collaborateur-*`, `niveau-sans-plugin-{desactive,absent}-{liste,suivi}-*`.

Non vérifié : rendu en français (pas de pack officiel hors ligne ; le catalogue du thème contient
les 4 nouvelles chaînes), lecteur d'écran, navigateurs autres que Chromium, plugin Stripe réel
(organisation Priority remplie à la main), libellés de niveau personnalisés ou traduits, liste
d'organisation partagée (`canSeeOrgTickets`) à grand nombre de propriétaires distincts.

## Écarts assumés avec la maquette

- **Accueil** : gabarit d'origine conservé (`index.php` est un fichier racine, pas un gabarit) ;
  la carte prune contient les deux boutons d'osTicket et les questions mises en avant, au lieu de
  « Du nouveau sur vos demandes » (dernière réponse) ; pas de bloc « Qui vous répond » (équipe
  nommée, absente du modèle) ; les sujets sont les catégories mises en avant avec leurs articles
  (pas de compteurs). La recherche de l'accueil cherche dans la base de connaissances (comme
  osTicket), elle n'ouvre pas un ticket. Sur mobile, la carte des actions reste avant l'accroche
  (ordre du code, donc ordre du clavier).
- **Ouverture de ticket** : le thème d'aide vient avant les champs du ticket (il décide des champs
  à afficher) ; les titres des sections sont ceux des formulaires d'osTicket (administrables) ; la
  colonne de conseils n'a pas d'avatar d'agent nommé.
- **Suivi de ticket** : pas de frise d'avancement (le modèle d'osTicket n'a pas ces étapes) ; la
  pastille « Support prioritaire » devient la pastille de niveau de contrat (libellé du plugin,
  « Priority » ou « Standard »), seulement avec le plugin `sla_first_response` ; le titre de réponse est « Répondre » et non « Répondre à
  Inès » ; colonne latérale plus complète (coordonnées, champs personnalisés, toutes les pièces
  jointes) ; actions Actualiser / Imprimer / Modifier conservées. Le titre de chaque message
  (souvent le sujet) est affiché en petit sous l'auteur, comme à l'origine.
- **Liste** : pas d'avatar de l'agent assigné ni de « réponse d'Inès il y a 6 jours » (données non
  exposées au client) ; une icône d'état les remplace ; barre de tri ajoutée (le tri par colonne
  existait). Le libellé « Showing 1 - 3 of 3 » et la pagination « [1] » sont ceux d'osTicket.
- **Connexion** : titres « Se connecter » et « Vérifier le statut d'un ticket » (chaînes d'osTicket)
  au lieu de « J'ai un compte » / « Je suis un ticket sans compte ». Le lien « Mot de passe oublié »
  est toujours affiché (à l'origine, seulement après un échec ; la page est publique de toute façon).
- **Base de connaissances** : pas de « Les plus lus cette semaine » (pas de statistiques de lecture
  côté client) ; icône de dossier commune à toutes les catégories (pas d'icône par sujet).
- **Pied de page** : pas de liens juridiques codés en dur ni de mention Matomo/InnoCraft ; il
  affiche les pages « Autres ressources » actives (à créer dans l'administration : mentions légales,
  conditions, politique de support…).
- **Divers** : la mise en avant en rouge du bouton d'envoi par osticket.js quand le formulaire
  change est neutralisée (rouge sur jaune illisible) ; le message « quitter la page ? » est
  conservé.

## Limites connues

- Navigateurs : la mise en page en deux colonnes des champs et quelques sélecteurs utilisent
  `:has()`, `:is()` et `:where()` (Chrome/Edge 105+, Firefox 121+, Safari 15.4+). Sur un navigateur
  plus ancien, les champs passent en une colonne et les cartes des pages de compte perdent leur cadre.
- Les messages d'erreur des champs dynamiques ne sont pas reliés par `aria-describedby` (ils viennent
  de `dynamic-form.tmpl.php`, laissé d'origine) ; ceux du thème d'aide, du captcha et de la réponse
  le sont.
- Sur mobile, le champ « Ext. » du téléphone passe à la ligne sous le numéro.
- Les polices sont en sous-ensemble latin : un nom en alphabet non latin s'affiche avec la police
  système.
- Un même message d'erreur de champ peut apparaître plusieurs fois (vu : « Enter a valid email
  address » trois fois) : il vient de la validation et de `dynamic-form.tmpl.php`, laissé d'origine.
  Le double message d'erreur (haut de page et formulaire de réponse) est repris de l'original.
- Le PDF d'impression d'osTicket (`tickets.php?a=print`) garde sa mise en page ; seule l'impression
  du navigateur est habillée.
- RTL : règles de base prévues, non testées.
- Niveau de contrat : niveau **actuel** du propriétaire (un ancien ticket change de pastille et de
  délai affiché à la fin d'un abonnement) ; rien sans le plugin `sla_first_response` actif et
  configuré ; le délai « premier retour » est celui du contrat, pas l'échéance du plan de SLA du
  ticket. Une liste d'organisation partagée coûte une recherche par propriétaire distinct de la page.


## Points à valider

1. **Logo** : il n'existe pas de logo vectoriel autonome d'Ownalytics ; le thème **redessine** en SVG
   le pictogramme du site (ticket à bord dentelé), suivi du mot « Ownalytics » en Archivo 800 et
   d'une pastille « Support ». À confirmer, ou fournir un fichier officiel (le remplacer dans
   `header.inc.php`, `footer.inc.php` et `img/favicon.svg`).
2. **Jaune** : la feuille utilise le « soleil » `#FFD23F` de la maquette 03 (boutons principaux,
   pastille « Support », focus sur prune) et non le surligneur `#F4E04D` de la charte « The
   receipt ». Garder, ou remplacer la variable `--sun` (puis revérifier les contrastes).
3. **Couleurs d'état** hors charte : erreur `#A3202D`, succès `#17603F` sur menthe, avertissement
   sur jaune pâle.
4. Les accroches proposées pour l'accueil, la page de remerciement et la page hors ligne (contenu
   administrable), et les deux conseils du formulaire d'ouverture.
5. Le pack de langue français officiel à ajouter à l'overlay, puis une relecture des écrans en
   français.
6. Le choix des articles et catégories « mis en avant », qui alimentent l'accueil.
7. **Niveau de contrat** visible du client : niveau actuel du propriétaire plutôt que niveau au
   moment du ticket ; « Standard » affiché (discret) ; pastille montrée aussi à un invité (niveau du
   propriétaire) ; ligne « Premier retour : sous 4 h, 24 h/24 / les jours ouvrés » tant que l'équipe
   n'a pas répondu (formulation, et faut-il l'afficher à un client standard ?).

## Contenu du dossier

```
deploy/themes/ownalytics-atelier/
├── README.md            ce fichier
├── apply.sh / remove.sh installation et retrait dans deploy/docker/overlay/ (refus multi-thèmes)
├── check-drift.sh       contrôle de dérive après une mise à jour d'osTicket
├── BASE.sha256          empreintes des fichiers du cœur remplacés (commit de base)
├── WATCH.sha256         empreintes des fichiers du cœur habillés par la feuille de style
├── captures/            captures réelles (bureau 1280 px, mobile 390 px), parcours et audit
└── overlay/             arborescence copiée telle quelle sur osTicket
```
