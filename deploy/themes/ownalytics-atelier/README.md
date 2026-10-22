# Thème « Atelier » du portail client (Ownalytics)

Implémentation de la proposition 03 des maquettes (`docs/theme-mockups/03-atelier/`, issue n° 4) :
le prune et le papier d'Ownalytics, des touches de couleurs des plugins (soleil, rose, menthe,
mandarine), Bricolage Grotesque pour les titres, Archivo pour le texte, cartes arrondies,
boutons-pastilles, avatars à initiales et fil de ticket en bulles de conversation.
Portail **client** uniquement : le panneau agent (`scp/`) n'est pas touché.

Ce dossier est la seule source du thème. Il ne modifie ni le cœur d'osTicket ni `deploy/docker/` :
`apply.sh` copie `overlay/` dans `deploy/docker/overlay/`, que l'image Docker superpose à osTicket.
Un seul thème peut être installé à la fois (l'autre proposition développée, « Poste de travail »,
vit dans `deploy/themes/poste-de-travail/`).

## Principe

| Élément | Où | Rôle |
|---|---|---|
| Feuille de style | `overlay/assets/atelier/css/atelier.css` | Remplace `assets/default/css/theme.css` (qui n'est plus chargée) ; écran et impression |
| Polices | `overlay/assets/atelier/fonts/` | Archivo (variable), Bricolage Grotesque 700-800, IBM Plex Mono 500-600, sous-ensemble latin WOFF2, licence SIL OFL 1.1 (`OFL.txt`) |
| Favicon | `overlay/assets/atelier/img/favicon.svg` | Pictogramme Ownalytics (ticket sur prune) |
| Aide PHP | `overlay/include/client/atelier/atelier.inc.php` | Fonction `__a()` (chaînes propres au thème), initiales et couleurs des avatars, nom de marque |
| Traductions du thème | `overlay/include/client/atelier/i18n/fr/LC_MESSAGES/atelier.mo.php` | Catalogue français des 10 chaînes que seul le thème utilise |
| Gabarits | `overlay/include/client/…` | 9 fichiers du cœur remplacés (liste ci-dessous) |

Rien n'est servi depuis l'extérieur : polices, icônes (Font Awesome déjà fourni par osTicket,
pictogramme en SVG en ligne) et scripts sont locaux. La politique CSP d'osTicket et de
`deploy/docker` (`script-src 'self' 'unsafe-inline'; object-src 'none'; frame-ancestors`) n'a
pas besoin de changer : le thème n'ajoute aucun script, et ses seules images en ligne sont un
SVG dans le HTML et une flèche de liste déroulante en `data:` dans la feuille de style.

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
| `include/client/tickets.inc.php` | Tickets en cartes au lieu du tableau, onglets Ouverts/Fermés, recherche et filtre par thème, barre de tri (les colonnes triables du tableau), pastille « Nouvelle réponse » ; requêtes d'origine inchangées |
| `include/client/view.inc.php` | En-tête du ticket (numéro, sujet, statut, date, actions), fil en conversation, formulaire de réponse en carte, colonne « Informations du ticket », « Informations client », champs personnalisés et liste de toutes les pièces jointes |
| `include/client/kb-categories.inc.php` | Accueil de la base de connaissances : grande recherche (absente de cette page à l'origine), filtre par thème, catégories en cartes avec leurs articles |
| `include/client/templates/thread-entry.tmpl.php` | Bulles : messages du client à droite, réponses de l'équipe à gauche, rôle (« Vous », « Équipe support »), avatars à initiales au lieu des images distantes |
| `include/client/templates/thread-event.tmpl.php` | Même balisage ; retire les images d'avatar des événements (osTicket les prend sur Gravatar par défaut) |

Les autres pages gardent leur gabarit d'origine et sont habillées par la feuille de style seule :
accueil (`index.php`), page hors ligne, « Vérifier un ticket » (`accesslink.inc.php`), inscription
et confirmation, mot de passe oublié (3 pages), profil, modification d'un ticket, recherche et
catégorie de la base de connaissances, fiche d'article, page de remerciement (contenu administrable).
Ces fichiers sont listés dans `WATCH.sha256` : s'ils changent, il faut revoir les pages concernées.

## Chaînes et langues

- Toutes les chaînes déjà présentes dans osTicket passent par `__()` et sont traduites par les packs
  de langue officiels (le pack français `fr.phar` se place dans `deploy/docker/overlay/include/i18n/`,
  voir `deploy/docker/overlay/README.md`).
- Les 10 chaînes propres au thème (« Skip to content », « Support », « Main menu », « You »,
  « Support team », « New reply », « Tips », deux conseils, l'accroche du pied de page) passent par
  `__a()`, c'est-à-dire `_dgettext('atelier', …)`, la couche gettext d'osTicket avec un domaine
  dédié. Le catalogue français est fourni ; il ne s'affiche que lorsque le français est une langue
  active du portail (donc avec le pack officiel). Pour une autre langue : copier
  `i18n/fr/LC_MESSAGES/atelier.mo.php` dans `i18n/<code>/LC_MESSAGES/` et traduire les valeurs.
  Le catalogue n'est pas sous `include/i18n/` exprès : un dossier à cet endroit ferait apparaître
  une langue de plus dans osTicket.
- Les accroches de l'accueil, de la page de remerciement et de la page hors ligne sont du **contenu
  administrable** (Admin > Gérer > Pages), traduisible par langue dans osTicket. Contenu proposé
  ci-dessous.

## Installation

Depuis la racine du dépôt :

```sh
deploy/themes/atelier/check-drift.sh          # le thème correspond-il à la version d'osTicket ?
deploy/themes/atelier/apply.sh --dry-run      # ce qui va être copié
deploy/themes/atelier/apply.sh                # copie dans deploy/docker/overlay/ + manifeste
git add deploy/docker/overlay && git commit -m "overlay: thème Atelier"
deploy/docker/scripts/build.sh build          # l'image est construite depuis le commit
cd deploy/docker && docker compose up -d --wait web cron
```

`apply.sh` refuse d'écraser un fichier de l'overlay qui n'appartient pas au thème, refuse de
s'installer si l'autre thème est présent, et note dans
`deploy/docker/overlay/include/client/atelier/installed.manifest` (non servi par Apache) chaque
fichier installé avec son empreinte. Il est idempotent : relancé, il ne copie que ce qui a changé
et retire les fichiers d'une ancienne version du thème. `--force` écrase les fichiers du thème
modifiés à la main dans l'overlay.

Réglages conseillés dans l'administration (une fois) :

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
3. **Logo** : ne rien téléverser pour garder le logotype du thème. Si un logo client est téléversé
   (Admin > Paramètres > Pages), l'en-tête l'affiche à la place du pictogramme et du nom.
4. **Nom de l'entreprise** (Admin > Gérer > Formulaires > Company Information) : affiché dans le
   copyright du pied de page (« Coderise SAS »).

## Désinstallation

```sh
deploy/themes/atelier/remove.sh --dry-run
deploy/themes/atelier/remove.sh
git add -A deploy/docker/overlay && git commit -m "overlay: retrait du thème Atelier"
deploy/docker/scripts/build.sh build && (cd deploy/docker && docker compose up -d --wait web cron)
```

`remove.sh` ne retire que les fichiers listés dans le manifeste et **non modifiés** depuis
l'installation (les autres sont signalés et gardés, le manifeste aussi ; `--force` les retire).
Il ne touche jamais aux plugins, packs de langue ou autre thème présents dans l'overlay.
Le portail retrouve alors le thème d'origine d'osTicket. Aucune donnée n'est modifiée en base ;
les contenus de pages saisis dans l'administration restent valables avec le thème d'origine.

## Basculer vers l'autre thème

```sh
deploy/themes/atelier/remove.sh
deploy/themes/poste-de-travail/apply.sh
git add -A deploy/docker/overlay && git commit -m "overlay: thème Poste de travail"
deploy/docker/scripts/build.sh build && (cd deploy/docker && docker compose up -d --wait web cron)
```

Et inversement. `apply.sh` refuse de s'installer tant que des fichiers de l'autre thème sont présents.

## Mise à jour d'osTicket

Un gabarit remplacé ne suit plus les correctifs du gabarit d'origine : à chaque mise à jour
d'osTicket (fusion d'une nouvelle version dans `develop`), avant de reconstruire l'image :

1. `deploy/themes/atelier/check-drift.sh` (compare au commit courant ; `--rev <tag>` pour un autre
   commit, ou un chemin vers une arborescence osTicket extraite). Il liste :
   - les **fichiers remplacés** qui ont changé (`BASE.sha256`) : reporter le changement dans le
     fichier de `overlay/` (`git diff <commit de base> HEAD -- <fichier>` donne la différence ;
     les correctifs de sécurité sur les gabarits sont la priorité) ;
   - les **fichiers habillés** qui ont changé (`WATCH.sha256`) : vérifier visuellement les pages
     concernées et ajuster `atelier.css` si le balisage a bougé.
2. Quand c'est fait : `deploy/themes/atelier/check-drift.sh --write-base` (réécrit les empreintes
   à partir du nouveau commit), committer, puis `apply.sh` (met à jour l'overlay), committer,
   reconstruire.

Code de sortie de `check-drift.sh` : 0 sans dérive, 1 avec dérive (utilisable en intégration continue).
Commit de base des empreintes actuelles : `51112176` (branche `develop`, osTicket 1.18.x).

## Vérifications faites (22/10/2026)

Sur une vraie instance : image durcie construite par la recette `deploy/docker` (`runtime`,
racine en lecture seule, `www-data`, `open_basedir`) avec le thème appliqué dans l'overlay, base
MariaDB 11.4, données de test (un client, 6 tickets ouverts et fermés avec pièces jointes et
réponses de deux agents, un collaborateur, un ticket d'un autre client, 9 articles en 4 catégories,
un formulaire personnalisé à 6 champs lié à 3 thèmes d'aide), relevé du courrier par un Mailpit
local. Navigateur : Chromium (Playwright). Détail des parcours : `captures/parcours-*.txt`.

- **Parcours réels**, à 1280 px et à 390 px : ouverture d'un ticket sans compte (erreurs, champs
  dynamiques rechargés en AJAX, éditeur riche, pièce jointe, page de remerciement) ; accès par
  e-mail + numéro (lien reçu par e-mail, fil, réponse de l'invité) ; connexion (erreur puis
  succès) ; liste (onglets, recherche, état vide, isolation des tickets d'un autre client) ;
  réponse avec pièce jointe et message de succès ; inscription et confirmation par e-mail ; mot
  de passe oublié jusqu'au nouveau mot de passe ; recherche, fiche d'article et filtre par thème de
  la base de connaissances. 27 contrôles par largeur, tous passés (le contrôle « mot de passe
  changé » attendait un message alors qu'osTicket redirige vers la liste : changement effectif,
  vérifié par la connexion suivante).
- **Console et réseau** : aucune erreur JavaScript, aucune réponse HTTP ≥ 400, aucune requête
  vers un autre hôte, sur toutes les pages et tous les parcours. Journaux Apache/PHP du conteneur :
  aucune erreur ni avertissement PHP ; journal système d'osTicket vide.
- **Débordement horizontal à 390 px** : aucun, sur les 18 pages capturées (et 1280 px).
- **Contrastes** (calcul WCAG sur chaque texte visible) : minimum mesuré 5,37:1, aucun texte sous
  4,5:1 (`captures/audit-*.json`). Bordures de champs `#6E6A66` : 5,3:1 sur blanc.
- **Clavier** : lien d'évitement en premier, qui place le focus sur le contenu ; focus visible de
  3 px (soleil sur prune, prune sur clair) sur tous les éléments parcourus ;
  `prefers-reduced-motion` coupe les transitions.
- **Impression** du suivi de ticket : en-tête, actions, formulaire de réponse et pied de page masqués,
  fil et informations en noir sur blanc (`captures/impression-suivi-ticket.png`).
- **Français** : aucun pack de langue officiel n'est disponible hors ligne. Test fait avec un
  catalogue de test (une soixantaine de chaînes, monté en lecture seule dans le conteneur, non
  livré) : bascule EN/FR de l'en-tête, catalogue du thème et page d'accueil traduite affichés en
  français, mises en page tenues avec les libellés français (`captures/*-fr-*.png`).
- **Scripts** : `check-drift.sh` (aucune dérive sur le commit ; sur une copie modifiée, détection
  d'un gabarit remplacé modifié, d'un supprimé et d'un fichier habillé modifié ; fins de ligne CRLF
  ignorées) ; `apply.sh`/`remove.sh` aller-retour, idempotence, refus d'un fichier étranger, d'un
  fichier du thème modifié et d'un autre thème installé, conservation d'un plugin présent dans
  l'overlay ; `git status` propre ensuite.
- **Apache** : les fichiers du thème sous `include/` (gabarits, catalogue, manifeste) répondent 403 ;
  feuille, polices (`font/woff2`) et favicon répondent 200.

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
- **Suivi de ticket** : pas de frise d'avancement (le modèle d'osTicket n'a pas ces étapes) ni de
  pastille « Support prioritaire » ; le titre de réponse est « Répondre » et non « Répondre à
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

## Points à valider

1. **Logo** : il n'existe pas de logo vectoriel autonome d'Ownalytics ; le thème redessine le
   pictogramme du site (ticket à bord dentelé) suivi du mot « Ownalytics » en Archivo 800 large et
   d'une pastille « Support ». À confirmer, ou fournir un fichier officiel.
2. **Couleurs d'état** hors charte : erreur `#A3202D`, succès `#17603F` sur menthe, avertissement
   sur jaune pâle.
3. Les accroches proposées pour l'accueil, la page de remerciement et la page hors ligne (contenu
   administrable), et les deux conseils du formulaire d'ouverture.
4. Le pack de langue français officiel à ajouter à l'overlay, puis une relecture des écrans en
   français avec les vraies traductions d'osTicket (le test a utilisé un catalogue partiel).
5. Le choix des articles et catégories « mis en avant », qui alimentent l'accueil.

## Contenu du dossier

```
deploy/themes/atelier/
├── README.md            ce fichier
├── apply.sh / remove.sh installation et retrait dans deploy/docker/overlay/
├── check-drift.sh       contrôle de dérive après une mise à jour d'osTicket
├── BASE.sha256          empreintes des fichiers du cœur remplacés (commit de base)
├── WATCH.sha256         empreintes des fichiers du cœur habillés par la feuille de style
├── captures/            captures réelles (bureau 1280 px, mobile 390 px), rapports d'audit et de parcours
└── overlay/             arborescence copiée telle quelle sur osTicket
```
