# Thème « Atelier » du portail client (Coderise)

Implémentation de la proposition 01 des maquettes Coderise (`docs/theme-mockups-coderise/01-atelier/`,
charte graphique Coderise v1.0) : chaleureux et centré sur les personnes. Bleu nuit `#000F26` pour
l'identité, cyan `#10C9DE` en accent, Montserrat pour les titres, Inter pour le texte, avatars à
initiales, fil de ticket en bulles (équipe en bleu nuit, client en voile cyan), boutons-pastilles,
frise d'avancement et motif « Sommet » (le chevron du symbole) comme seul ornement.
Portail **client** uniquement : le panneau agent (`scp/`) n'est pas touché.

Ce dossier est la seule source du thème. Il ne modifie ni le cœur d'osTicket ni `deploy/docker/` :
`apply.sh` copie `overlay/` dans `deploy/docker/overlay/`, que l'image Docker superpose à osTicket.
Quatre thèmes sont des **alternatives** (un seul installé à la fois) : `atelier`, `poste-de-travail`,
`sommet`, `parcours`.

La première version de ce thème était aux couleurs d'Ownalytics (autre marque) ; elle reste dans
l'historique git. Tout ce qui la caractérisait a été retiré : couleurs, polices (Archivo, Bricolage
Grotesque, IBM Plex Mono), pictogramme « ticket » redessiné, mentions de la marque.

## Principe

| Élément | Où | Rôle |
|---|---|---|
| Feuille de style | `overlay/assets/atelier/css/atelier.css` | Remplace `assets/default/css/theme.css` (qui n'est plus chargée) ; écran et impression ; jetons de la charte en tête de fichier |
| Polices | `overlay/assets/atelier/fonts/` | Montserrat 600/700/800 et Inter 400/500/600, sous-ensembles latin et latin-ext, WOFF2, licence SIL OFL 1.1 (`OFL-montserrat.txt`, `OFL-inter.txt`) ; copiées de `docs/theme-mockups-coderise/_shared/fonts/` |
| Logo | `overlay/assets/atelier/img/logo-coderise-horizontal.png` | Logo horizontal officiel détouré (PNG des maquettes, 2041 × 345, dérivé de `2.jpg`), affiché à 24 px de haut (142 px de large) dans l'en-tête et 28 px (166 px) dans le pied de page |
| Favicon | `overlay/assets/atelier/img/favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` | Le symbole sur carré bleu nuit : `symbole-sur-bleu-nuit.svg` de `deploy/themes/_shared/coderise/` (copie à l'identique) et les PNG des maquettes |
| Aide PHP | `overlay/include/client/atelier/atelier.inc.php` | `__a()` (chaînes du thème), initiales, icônes en ligne (SVG), motif Sommet, coordonnées de Coderise, frise d'avancement et indicateur Priority calculés sur les données du ticket |
| Traductions du thème | `overlay/include/client/atelier/i18n/fr/LC_MESSAGES/atelier.mo.php` | Catalogue français des 31 chaînes propres au thème |
| Gabarits | `overlay/include/client/…` | 9 fichiers du cœur remplacés (liste ci-dessous) |

Rien n'est servi depuis l'extérieur : polices, logo, icônes (SVG en ligne, Font Awesome local
d'osTicket pour quelques icônes d'origine) et scripts sont locaux ; aucun Gravatar. La politique CSP
d'osTicket et de `deploy/docker` (`script-src 'self' 'unsafe-inline'; object-src 'none'`) n'a pas
besoin de changer : le thème n'ajoute aucun script. Les seules images en ligne sont des SVG dans le
HTML et deux SVG en `data:` dans la feuille (flèche de liste, icône d'article, chevron des pages de
confirmation).

### Charte appliquée

- Couleurs : bleu nuit (en-tête, pied de page, bulles de l'équipe, bandeau de la base de
  connaissances, boutons au survol), cyan (boutons principaux, onglet actif, soulignement du menu,
  barres de la frise, chevron, liens dans les zones sombres), blanc ; support : cyan profond (liens sur
  blanc, focus sur fond clair), ardoise (texte secondaire, jamais sur bleu nuit : le texte secondaire
  des zones sombres est en blanc à 82 %), gris clair (fonds, séparateurs, onglets), voile cyan (bandeaux
  de titre, bulles du client, encadrés). Jamais de texte cyan sur blanc ni de blanc sur cyan : le texte
  sur aplat cyan est bleu nuit. Les liens posés sur un fond teinté (voile, gris clair) sont en bleu nuit
  soulignés de cyan profond (écart de la charte relevé dans `docs/theme-mockups-coderise/README.md`).
- Couleurs d'état **hors charte, extension à valider** (reprises des maquettes) : erreur `#B3261E` sur
  `#FCECEA`, succès `#0F6B4B` sur `#E4F5EC`, alerte `#7A4B00` sur `#FFF3D1`.
- Typographie : titres en Montserrat 600 à 800, texte en Inter 400 à 600, substitut Arial. Le code
  utilise la pile système (`ui-monospace`, Consolas) : pas de police de code dans la charte.
- Logo : fichiers fournis seulement, ni redessinés, ni déformés, ni recolorés (hauteur fixée, largeur
  automatique). Zone de protection X = 24 px (hauteur du logo) : marges verticales de l'en-tête de
  24 px, écart de 24 px avec le menu. Largeur minimale de 120 px respectée (142 et 166 px). Un logo
  téléversé dans l'administration remplace celui du thème dans l'en-tête.
- Signature « Le code qui fait **décoller** vos projets » (Montserrat 600, blanc, « décoller » en
  cyan) sous le logo du pied de page, à 36 px (≥ 1X de 28 px), sans cadre ; en français dans toutes
  les langues (`lang="fr"`).
- Motif « Sommet » : chevron dessiné en CSS (`clip-path`, rapport 409 : 342 conservé) dans la frise,
  les étapes de l'accueil, le bandeau de la base de connaissances, l'état vide et les pages de
  remerciement et hors ligne ; filigrane bleu nuit à 6 % dans la carte d'actions de l'accueil.
- Icônes en ligne (trait 1,75, extrémités arrondies), jeu des maquettes.
- Tableaux des articles : en-têtes bleu nuit, lignes alternées gris clair.

## Fichiers du cœur remplacés

Chaque gabarit remplacé garde les mêmes champs, noms de champs, actions de formulaire, jeton CSRF
(`csrf_token()`), contrôles d'accès, appels `__()` et échappements (`Format::htmlchars`,
`Format::display`…) que l'original ; la logique (requêtes, filtres, brouillons, pièces jointes,
pagination, collaborateurs) est recopiée telle quelle. Empreintes d'origine : `BASE.sha256`. La liste
n'a pas changé avec le passage à Coderise (9 fichiers).

| Fichier | Pourquoi il est remplacé |
|---|---|
| `include/client/header.inc.php` | Logo Coderise, menu souligné de cyan avec `aria-current`, pastille du client (initiales), bouton « Se connecter », langues en texte (EN, FR), menu mobile en `<details>` (sans script), lien d'évitement, feuille et favicons du thème, classes de page sur `<body>` (`p-<script>`, `v-<vue>`, `p-offline`) |
| `include/client/footer.inc.php` | Pied de page bleu nuit : logo et signature, coordonnées (contact@coderise.fr, +33 (0)6 69 93 92 86, Aix-en-Provence, horaires de l'entreprise), délais de réponse (standard : jours ouvrés ; Priority : sous 4 h, 24 h/24), pages « Autres ressources » actives et www.coderise.fr, copyright ; mêmes scripts de fin que l'original |
| `include/client/open.inc.php` | Formulaire en cartes, colonne de conseils et questions à la une ; `#topicId` recharge toujours `#dynamic-form` en AJAX |
| `include/client/login.inc.php` | Deux cartes : connexion et accès par e-mail + numéro de ticket (même formulaire et même cible `login.php` que `accesslink.inc.php`) |
| `include/client/tickets.inc.php` | Tickets en cartes, onglets Ouverts/Fermés, recherche, filtre par sujet, barre de tri, pastilles de statut et « Priority », dernière étape connue (« Nouvelle réponse », « Réponse de l'équipe à venir », « Fermé le … ») ; requête d'origine plus deux colonnes lues (`sla__name`, `closed`) |
| `include/client/view.inc.php` | Bandeau du ticket (fil d'Ariane, sujet, statut, Priority, date, actions), frise d'avancement, fil en bulles, réponse en carte, colonne : indicateur Priority, informations, coordonnées, champs personnalisés, pièces jointes, « Un point rapide ? » |
| `include/client/kb-categories.inc.php` | Bandeau bleu nuit avec grande recherche et chevron cyan, filtre par sujet, catégories en cartes |
| `include/client/templates/thread-entry.tmpl.php` | Bulles : client à droite (voile cyan), équipe à gauche (bleu nuit, avatar cerclé de cyan), rôle « Vous » / « Équipe Coderise », initiales au lieu des images distantes |
| `include/client/templates/thread-event.tmpl.php` | Même balisage ; retire les images d'avatar des événements (Gravatar par défaut dans osTicket) |

Les autres pages gardent leur gabarit d'origine et sont habillées par la feuille de style seule
(accueil `index.php`, hors ligne, « Vérifier un ticket », inscription et confirmation, mot de passe
oublié, profil, modification d'un ticket, recherche, catégorie et fiche d'article, remerciement).
Elles sont listées dans `WATCH.sha256`. `dynamic-form.tmpl.php` n'est pas écrasé.

## Données réelles uniquement

| Élément de la maquette | Implémentation |
|---|---|
| Frise d'ascension du ticket (4 étapes) | **3 étapes** tirées du ticket : « Reçu » (date de création), « Première réponse » (date de la première réponse de l'équipe dans le fil ; à défaut `isanswered`), « Résolu » ou le nom du statut fermé (date de fermeture). L'étape courante est la première non faite ; sous-titres « Nous lisons votre demande », « En cours », « À venir ». Pas d'étape « Correctif en cours » : osTicket n'a pas cet état |
| Pastille « Priority » | Affichée si le nom du plan de SLA du ticket commence par `ATELIER_PRIORITY_PREFIX` (« Priority »), dans la liste et sur le ticket. **À valider** : le client voit ainsi son niveau de contrat |
| Indicateur « premier retour sous 4 h » | Facultatif, **à valider**. Affiché seulement si le plan du ticket est **actif** et s'appelle exactement `ATELIER_PRIORITY_SLA` (« Priority 4h », le plan surveillé du plugin `contrib/sla-first-response`), que le ticket est ouvert, sans réponse (`isanswered = 0`) et que son échéance (`est_duedate`) est à venir. Barre du temps écoulé depuis la création (ou réouverture) jusqu'à l'échéance, et « Premier retour attendu au plus tard le … ». Disparaît seul à la première réponse (`isanswered`), et quand le plugin bascule le ticket sur son plan désactivé. Non affiché après l'échéance (afficher un dépassement au client est une décision ouverte). `define('ATELIER_PRIORITY_SLA', '')` le désactive |
| « Apporté en 44 min, objectif tenu » (maquette) | Non fait : le délai réel n'est pas exposé au portail ; voir `contrib/sla-first-response/sql/` |
| Bulle d'exemple de l'accueil (Hugo, Nora, Sacha) | Omise (équipe et échange inventés) ; la colonne droite est la carte d'actions d'osTicket (ouvrir, suivre, questions à la une) |
| « Comment ça se passe » (3 étapes) | Contenu **administrable** de la page d'accueil (liste `<ol>` mise en forme en marches montantes), voir « Réglages » |
| « Les articles les plus lus », durées de lecture | Remplacés par les articles des catégories mises en avant (osTicket n'a pas de statistiques de lecture ni de durée) |
| Carte « Besoin d'un retour sous 4 h ? » | Omise : texte commercial à placer, si voulu, dans le contenu de l'accueil |
| Compteurs, avatar et nom de l'agent dans la liste | Omis (non exposés au client) ; icône d'état et dernière étape connue à la place |
| Liens LinkedIn et GitHub du pied de page | Omis : adresses non confirmées ; seul www.coderise.fr est mis. À ajouter dans `footer.inc.php` si validés |

## Chaînes et langues

- Les chaînes d'osTicket passent par `__()` et sont traduites par les packs de langue officiels (le
  pack français `fr.phar` se place dans `deploy/docker/overlay/include/i18n/`).
- Les 31 chaînes propres au thème passent par `__a()` (`_dgettext('atelier', …)`) avec un catalogue
  français fourni. Pour une autre langue : copier `i18n/fr/LC_MESSAGES/atelier.mo.php` dans
  `i18n/<code>/LC_MESSAGES/` et traduire. Le catalogue n'est pas sous `include/i18n/` exprès (sinon une
  langue de plus apparaîtrait dans osTicket).
- Ne passent pas par la traduction : la marque, la signature (française par nature), « Priority »
  (nom du contrat), les coordonnées.
- Ton : voix active, phrases courtes (« Nous lisons votre demande », « Un point rapide ? »).

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

`apply.sh` refuse de s'installer si l'un des trois autres thèmes (`poste-de-travail`, `sommet`,
`parcours`, ou tout autre dossier de `deploy/themes/`) est présent dans l'overlay (manifeste
`include/client/<slug>/installed.manifest` ou fichiers identiques à ceux de son `overlay/`), refuse
d'écraser un fichier de l'overlay qui n'appartient pas au thème, et note dans
`deploy/docker/overlay/include/client/atelier/installed.manifest` (non servi par Apache) chaque
fichier installé avec son empreinte. Il est idempotent : relancé, il ne copie que ce qui a changé et
retire les fichiers d'une ancienne version du thème (les polices Ownalytics d'une installation
antérieure partent ainsi). `--force` écrase les fichiers du thème modifiés à la main.

### Réglages conseillés dans l'administration (une fois)

1. **Pages** (Admin > Gérer > Pages) : contenu de « Landing » (accueil), « Thank You » et « Offline ».
   Accueil, français (onglet de langue de la page), le premier `<p>` est l'accroche, un `<p><small>`
   la mention des délais, `<h2>` + `<ol>` les trois marches :
   ```html
   <h1>Faisons monter votre projet d’un niveau.</h1>
   <p>Un incident, une question, une évolution&nbsp;: écrivez-nous. Une personne de l’équipe lit votre ticket et vous répond, avec une vraie réponse.</p>
   <p><small>Support standard&nbsp;: jours ouvrés. Support Priority&nbsp;: premier retour sous 4&nbsp;h, 24&nbsp;h/24.</small></p>
   <h2>Comment ça se passe</h2>
   <ol><li><strong>Vous décrivez</strong> Choisissez le sujet, ajoutez une capture&nbsp;: le formulaire s’adapte à votre demande.</li>
   <li><strong>Nous répondons</strong> Une personne de l’équipe vous lit, reproduit le problème et vous dit où nous en sommes.</li>
   <li><strong>Vous suivez</strong> Chaque réponse arrive par e-mail et sur ce portail. Vous répondez où vous voulez.</li></ol>
   ```
   Remerciement : `<h1>Merci, %{ticket.name.first}.</h1><p>Votre ticket <strong>n° %{ticket.number}</strong> est ouvert. Une personne de l’équipe le lit et vous répond par e-mail.</p>`.
   Hors ligne : `<h1>Le portail est en maintenance.</h1><p>Nous revenons vite. Pour une urgence : +33 (0)6 69 93 92 86 ou contact@coderise.fr.</p>`.
2. **Base de connaissances** : mettre en avant les catégories et articles à montrer à l'accueil.
3. **Logo** : ne rien téléverser pour garder le logo du thème.
4. **Nom de l'entreprise** (Admin > Gérer > Formulaires > Company Information) : affiché dans le
   copyright (« Coderise » sinon).
5. **Plans de SLA** (indicateur Priority) : plan actif « Priority 4h » (délai 4 h, calendrier 24/7) et
   plan désactivé, non transitoire, « Priority 4h - 1re reponse faite » pour le plugin
   `sla_first_response`. Les noms se changent dans `atelier.inc.php` (`ATELIER_PRIORITY_PREFIX`,
   `ATELIER_PRIORITY_SLA`).

## Désinstallation

```sh
deploy/themes/atelier/remove.sh --dry-run
deploy/themes/atelier/remove.sh
git add -A deploy/docker/overlay && git commit -m "overlay: retrait du thème Atelier"
deploy/docker/scripts/build.sh build && (cd deploy/docker && docker compose up -d --wait web cron)
```

`remove.sh` ne retire que les fichiers listés dans le manifeste et **non modifiés** depuis
l'installation (les autres sont signalés et gardés, le manifeste aussi ; `--force` les retire). Il ne
touche jamais aux plugins, packs de langue ou autre thème présents dans l'overlay. Aucune donnée n'est
modifiée en base.

## Basculer vers un autre thème

```sh
deploy/themes/atelier/remove.sh
deploy/themes/<poste-de-travail|sommet|parcours>/apply.sh
git add -A deploy/docker/overlay && git commit -m "overlay: changement de thème"
deploy/docker/scripts/build.sh build && (cd deploy/docker && docker compose up -d --wait web cron)
```

## Mise à jour d'osTicket

Un gabarit remplacé ne suit plus les correctifs du gabarit d'origine : à chaque mise à jour
d'osTicket, avant de reconstruire l'image :

1. `deploy/themes/atelier/check-drift.sh` (compare au commit courant ; `--rev <tag>` pour un autre
   commit, ou un chemin vers une arborescence osTicket). Il liste les **fichiers remplacés** qui ont
   changé (`BASE.sha256` : reporter le changement dans `overlay/`, les correctifs de sécurité d'abord)
   et les **fichiers habillés** qui ont changé (`WATCH.sha256` : vérifier les pages, ajuster la feuille).
2. Ensuite : `check-drift.sh --write-base`, committer, `apply.sh`, committer, reconstruire.

Code de sortie : 0 sans dérive, 1 avec dérive. Commit de base des empreintes : `51112176`
(`develop`, osTicket 1.18.x) ; aucune dérive au commit de cette version.

## Vérifications faites (22/10/2026)

Sur une vraie instance : image durcie construite par la recette `deploy/docker` (`git archive` du
commit, thème appliqué par `apply.sh` dans l'overlay de cette copie, cibles `install` puis `runtime`,
racine en lecture seule, `www-data`), installation par l'assistant web, MariaDB 11.4. Projet Compose,
images, réseaux et volumes suffixés, port hôte aléatoire. Données : une cliente (Léa Marchand) avec
38 tickets (31 ouverts, 7 fermés : pagination), réponses de deux agents, pièces jointes des deux côtés,
un collaborateur, un ticket Priority sans réponse, un ticket d'un autre utilisateur, un formulaire
personnalisé à 6 champs lié à 2 sujets, 7 articles en 3 catégories. Navigateur : Chromium privé
piloté par `playwright-core` installé hors dépôt. Détail : `captures/parcours.txt`, `captures/audit.json`.

- **Parcours réels**, à 1280 px et à 390 px, 52 contrôles, tous passés : base de connaissances
  (accueil, catégorie, article, recherche, recherche vide) ; ouverture sans compte (erreurs, champs
  dynamiques rechargés en AJAX, éditeur riche, pièce jointe, remerciement) ; accès par e-mail +
  numéro (lien envoyé, et accès direct avec réponse de l'invité) ; connexion (erreur puis succès) ;
  liste (pagination, fermés, état vide, ticket d'un autre client invisible, pastille Priority) ;
  suivi (frise, indicateur Priority, ticket fermé, réponse avec pièce jointe et message de succès,
  réponse vide refusée) ; modification, profil, refus d'accès au ticket d'un autre client, article
  introuvable ; inscription jusqu'à la confirmation ; mot de passe oublié jusqu'au nouveau mot de
  passe (jetons lus en base, pas de serveur de courrier) ; page hors ligne. Captures : `captures/`.
- **Indicateur Priority** (`captures/indicateur-priority.txt`) : affiché sur plan actif sans réponse
  avant échéance ; masqué après réponse, après bascule sur le plan désactivé (ce que fait le plugin ;
  la pastille reste), après l'échéance, et si le plan est désactivé. Le plugin lui-même n'était pas
  installé sur l'instance : sa bascule a été simulée en base.
- **Console et réseau** : aucune erreur JavaScript, aucune réponse HTTP ≥ 400 (hors les 403 voulus),
  aucune requête vers un autre hôte. Journaux du conteneur : aucune erreur ni avertissement PHP ;
  journal d'osTicket : seulement « Mailer Error » (pas de serveur de courrier sur l'instance de test).
- **Débordement horizontal** : aucun, sur toutes les pages, à 390 et 1280 px.
- **Contrastes** (WCAG, chaque texte visible, fond réel composé) : minimum **4,91:1** (ardoise sur gris
  clair), aucun texte sous 4,5:1 sur 76 rendus.
- **Clavier** : lien d'évitement en premier ; focus visible (3 px cyan profond sur fond clair, cyan sur
  bleu nuit) sur tous les éléments parcourus de l'accueil, de la liste et du suivi ; menu mobile
  ouvrable (`captures/menu-mobile-ouvert-mobile.png`) ; `prefers-reduced-motion` respecté.
- **Impression** du suivi : en-tête, actions, réponse, pied de page masqués ; fil et informations en
  noir sur blanc (`captures/impression-suivi-ticket-*.png`).
- **Français** : aucun pack officiel n'est disponible hors ligne. Test avec un catalogue minimal
  d'osTicket monté en lecture seule (non livré, une soixantaine de chaînes) : catalogue du thème
  affiché, mises en page tenues avec les libellés français, en-tête sur une ligne à 1280 px
  (`captures/*-fr-*.png`, `captures/parcours-fr.txt`). Contrôle du code : toutes les chaînes affichées
  passent par `__()` ou `__a()`, sauf la marque, la signature, « Priority » et les coordonnées. Avec ce
  catalogue minimal, `ajax.php/i18n/fr/js` ne renvoie pas de JavaScript (erreur console) : propre au
  catalogue de test, le pack officiel contient ces traductions.
- **Scripts** (`captures/scripts.txt`) : `check-drift.sh` sans dérive sur le commit ; sur une copie
  modifiée, détection d'un gabarit remplacé modifié et d'un supprimé, d'un fichier habillé modifié,
  fins de ligne CRLF ignorées. `apply.sh`/`remove.sh` : simulation, installation, idempotence, refus
  d'un fichier du thème modifié (puis `--force`), retrait qui garde le fichier modifié, refus d'un
  fichier étranger, refus quand `poste-de-travail`, `sommet` ou `parcours` est installé, plugin
  conservé ; `git status` propre après l'aller-retour.
- **Apache** : les fichiers du thème sous `include/` (aide, gabarit, catalogue, manifeste) répondent
  403 ; feuille, polices (`font/woff2`), favicon et logo répondent 200.

Non vérifié : lecteur d'écran, navigateurs autres que Chromium, tablette 820 px en parcours complet
(le point de rupture du menu est à 1240 px), RTL, envoi réel des e-mails, plugin `sla_first_response`
installé (bascule simulée), pack de langue officiel.

## Écarts avec la maquette Coderise

- **Accueil** : gabarit d'origine conservé ; l'accroche et les trois marches viennent du contenu
  administrable ; la colonne droite est la carte d'actions d'osTicket (voile cyan, chevron en
  filigrane) au lieu de la bulle d'exemple ; pas de carte « Besoin d'un retour sous 4 h ? » ; les
  articles sont ceux des catégories mises en avant ; la recherche cherche dans la base de connaissances.
- **En-tête** : entre 1241 et 1440 px, la pastille du client ne montre que les initiales (nom lisible
  au lecteur d'écran) ; sous 1240 px, menu repliable « Menu » et ligne client sous le logo. Les
  libellés du menu sont ceux d'osTicket (« Support Center Home », « Check Ticket Status »…).
- **Suivi** : frise en 3 étapes au lieu de 4 ; pas de « Apporté en 44 min » ; actions Actualiser /
  Imprimer / Modifier conservées ; colonne plus complète (coordonnées, champs, pièces jointes) ; le
  titre du message (souvent le sujet) est affiché sous l'auteur, comme à l'origine.
- **Liste** : pas d'avatar ni de nom de l'agent, pas de « il y a 3 h » ; pastille « Priority » d'après le
  plan de SLA ; barre de tri et filtre par sujet ajoutés ; pagination « [1] 2 » d'osTicket.
- **Connexion** : titres d'osTicket (« Se connecter », « Vérifier le statut d'un ticket ») ; le lien
  « Mot de passe oublié » est toujours affiché.
- **Base de connaissances** : icône de dossier commune ; pas de « Les plus lus ».
- **Pied de page** : « Ressources » (pages administrables + www.coderise.fr) au lieu de « Suivre
  Coderise » (LinkedIn et GitHub non confirmés).
- **Formulaires** : bordures de 1,5 px ardoise comme la maquette ; la mise en rouge du bouton d'envoi
  par osticket.js est neutralisée (rouge sur cyan illisible).

## Limites connues

- `:has()`, `:is()`, `:where()` (Chrome/Edge 105+, Firefox 121+, Safari 15.4+) : sur un navigateur plus
  ancien, les champs passent en une colonne et certaines cartes perdent leur cadre. L'accueil utilise
  `display: contents` (même support).
- Le logo est un PNG détouré (pas de logo horizontal en SVG fourni) : net jusqu'à environ 14× la taille
  affichée, mais pas vectoriel.
- Les messages d'erreur des champs dynamiques viennent de `dynamic-form.tmpl.php` (non remplacé) :
  pas d'`aria-describedby`, et un même message peut apparaître plusieurs fois.
- Le PDF d'impression d'osTicket (`tickets.php?a=print`) garde sa mise en page.
- Sur la page de suivi en mobile, l'indicateur Priority est dans la colonne, donc sous le fil.
- RTL : règles de base, non testées.

## Points à valider

1. **Couleurs d'état** (extension de la charte) et la règle des liens sur fond teinté.
2. **Indicateur Priority** : l'afficher au client, les noms de plans, et ce qu'il faut montrer après
   l'échéance (rien aujourd'hui). **Pastille Priority** visible du client.
3. **Logo** : un SVG horizontal officiel remplacerait le PNG ; le favicon (symbole sous 56 px) est une
   exception à la taille minimale, comme dans les maquettes.
4. Les textes administrables proposés (accueil, remerciement, hors ligne) et les chaînes du thème.
5. Les liens LinkedIn et GitHub du pied de page (omis).
6. Le pack de langue français officiel à ajouter à l'overlay, puis relecture des écrans en français.

## Contenu du dossier

```
deploy/themes/atelier/
├── README.md            ce fichier
├── apply.sh / remove.sh installation et retrait dans deploy/docker/overlay/
├── check-drift.sh       contrôle de dérive après une mise à jour d'osTicket
├── BASE.sha256          empreintes des fichiers du cœur remplacés (commit de base)
├── WATCH.sha256         empreintes des fichiers du cœur habillés par la feuille de style
├── captures/            captures réelles (bureau 1280 px, mobile 390 px), audit et journaux des parcours
└── overlay/             arborescence copiée telle quelle sur osTicket
```
