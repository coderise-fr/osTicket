# Thème « Ticket de caisse » du portail client (Ownalytics)

Implémentation de la proposition 01 des maquettes Ownalytics (`docs/theme-mockups/01-ticket-de-caisse/`,
issue n° 4) : **continuité stricte avec www.ownalytics.eu** (direction « The receipt »). Fond prune
`#3B1E54`, contenu imprimé sur un papier `#FBFBF6` à bords déchirés, filets pointillés, lignes à points
de conduite, boutons à angles droits et ombre dure lilas, Archivo (titres en 800, largeur 125 %) et
IBM Plex Mono (libellés, dates, numéros), surligneur `#F4E04D` réservé à l'élément « courant » (onglet
du menu, filtre actif de la liste). Portail **client** uniquement : le panneau agent (`scp/`) n'est pas touché.

**Niveau 1** : le balisage d'osTicket est conservé (formulaires et fiche ticket en tableaux, neutralisés
par CSS). Seuls l'en-tête, le pied de page et les deux gabarits du fil (pour supprimer Gravatar) sont
remplacés. C'est la proposition la moins exposée aux mises à jour d'osTicket, mais la plus dépendante du
balisage d'origine : voir « Mise à jour d'osTicket » et `WATCH.sha256`.

Ce dossier est la seule source du thème. Il ne modifie ni le cœur d'osTicket ni `deploy/docker/` :
`apply.sh` copie `overlay/` dans `deploy/docker/overlay/`, que l'image Docker superpose à osTicket.
**Un seul thème peut être installé à la fois** (autres thèmes : `deploy/themes/*/`).

## Contenu

| Chemin | Rôle |
|---|---|
| `overlay/` | Arbre qui reflète la racine d'osTicket : ce qui est installé dans l'image |
| `MANIFEST` | Liste exacte des fichiers de `overlay/` (apply.sh refuse s'ils divergent) |
| `BASE.sha256` | Empreintes des fichiers du cœur **remplacés**, au commit de base |
| `WATCH.sha256` | Empreintes des fichiers du cœur **habillés** par la feuille de style sans être remplacés |
| `apply.sh`, `remove.sh` | Installation et retrait dans `deploy/docker/overlay/` (idempotents, avec manifeste d'installation) |
| `check-drift.sh` | Liste les fichiers du cœur remplacés ou habillés qui ont changé depuis le commit de base |
| `captures/` | Captures réelles (bureau 1280 px, mobile 390 px), rapports de parcours et d'audit |

### Fichiers du cœur remplacés (4)

Chaque fichier garde les mêmes en-têtes HTTP (CSP), scripts, messages (`$errors['err']`, `$msg`, `$warn`),
appels `__()` et échappements (`Format::htmlchars`…) que l'original.

| Fichier | Pourquoi |
|---|---|
| `include/client/header.inc.php` | Logotype Ownalytics (pictogramme + « Ownalytics » + « Support ») à la place de l'image `logo.php` (sauf si un logo client est téléversé) ; feuille du thème et favicon à la place de `assets/default/css/theme.css`, `print.css` et `flags.css` ; lien d'évitement ; menu dans un `<nav>` étiqueté avec `aria-current` ; langues en texte (EN, FR) au lieu des drapeaux ; classes de page sur `<body>` (`p-*`, `v-*`, `is-landing`, `is-offline`) qui remplacent le recours à `:has()` prévu par la maquette ; `#content` devient `<main>` ; **ligne « niveau de contrat »** en tête du papier sur la liste des tickets et le suivi (voir « Niveau de contrat ») |
| `include/client/footer.inc.php` | Pied de page du site (logotype, pages « Autres ressources » actives, mention Matomo/InnoCraft du site, copyright et « propulsé par osTicket » d'origine) ; ferme le `<main>` ; mêmes scripts de fin |
| `include/client/templates/thread-entry.tmpl.php` | **Pas d'avatar** : osTicket prend les avatars sur Gravatar par défaut (requête externe interdite) ; « (Ownalytics) » après le nom de l'agent dans les réponses (la maquette l'ajoutait en CSS) |
| `include/client/templates/thread-event.tmpl.php` | Même balisage ; retire les images d'avatar des descriptions d'événements (Gravatar) |

Laissés d'origine et habillés par la feuille seule (listés dans `WATCH.sha256`) : accueil (`index.php`,
`templates/sidebar.tmpl.php`), ouverture et remerciement (`open.php`, `open.inc.php`), **`dynamic-form.tmpl.php`
(non surchargé : il sert aussi au rechargement AJAX des champs et à certains écrans agent)**, connexion,
accès par numéro, inscription et confirmations, mot de passe oublié, profil, modification, liste, suivi,
fil (`thread-entries.tmpl.php`), base de connaissances (catégories, catégorie, article, recherche), page
hors ligne. Plus `tickets.php` (l'en-tête lit les variables `$inc` et `$ticket` qu'il pose), `css/thread.css`, `css/redactor.css`, `css/filedrop.css`, `css/osticket.css`,
`js/osticket.js`, `js/redactor-osticket.js` et `include/class.forms.php`, dont la feuille contre les règles.

### Fichiers ajoutés

- `assets/ownalytics-ticket-de-caisse/css/theme.css` : toute la feuille (écran et impression).
- `assets/ownalytics-ticket-de-caisse/fonts/` : Archivo (variable) et IBM Plex Mono 400/500/600, sous-ensemble
  latin, WOFF2, copiés de `docs/theme-mockups/_shared/fonts/`, avec `OFL.txt` (SIL Open Font License 1.1).
  Bricolage Grotesque n'est pas reprise (la maquette 01 ne l'utilise pas).
- `assets/ownalytics-ticket-de-caisse/img/favicon.svg` : favicon du site (ticket sur prune).
- `include/client/ownalytics-ticket-de-caisse/theme.php` : `otdc__()` (traduction), `otdc_asset()`,
  `otdc_mark()` (pictogramme SVG en ligne), `otdc_strip_avatars()`, `otdc_contract()` et
  `otdc_contract_line()` (niveau de contrat, plugin facultatif).
- `include/client/ownalytics-ticket-de-caisse/i18n/fr.php` : catalogue français de repli (voir « Traductions »).

Aucune ressource externe au chargement : polices, pictogramme (SVG en ligne), icônes (Font Awesome
fourni par osTicket) et feuille sont servis par osTicket ; la flèche des listes déroulantes est une
image `data:`. Le thème n'ajoute aucun script. La CSP d'osTicket (`script-src 'self' 'unsafe-inline'`)
n'a pas à changer. Le seul lien externe est « propulsé par osTicket », d'origine.

## Installation

Depuis la racine du dépôt :

```sh
deploy/themes/ownalytics-ticket-de-caisse/check-drift.sh        # le thème correspond-il à cette version d'osTicket ?
deploy/themes/ownalytics-ticket-de-caisse/apply.sh --dry-run    # ce qui va être copié
deploy/themes/ownalytics-ticket-de-caisse/apply.sh              # copie dans deploy/docker/overlay/ + manifeste
git add -A deploy/docker/overlay && git commit -m "overlay: thème Ticket de caisse"
deploy/docker/scripts/build.sh                                  # l'image est construite depuis le commit
# puis déploiement comme une mise à jour (deploy/docker/README.md, « Mises à jour »)
```

`apply.sh [--dry-run] [--force] [OVERLAY_DIR]` écrit dans
`deploy/docker/overlay/include/client/ownalytics-ticket-de-caisse/installed.manifest` (sous `include/`,
jamais servi par Apache) chaque fichier installé avec son empreinte. Il refuse, sans rien copier :

- **si un autre thème est installé**, de façon générique : n'importe quel `installed.manifest` (ou
  `*.manifest`) présent dans l'overlay ailleurs que dans le dossier de ce thème, quel que soit le nom du
  thème (`atelier`, `sommet`, `parcours`, `ownalytics-atelier`…, aucune liste codée en dur) ; ou des
  fichiers identiques à ceux d'un autre thème de `deploy/themes/` (thèmes qui n'écrivent pas de manifeste,
  comme `poste-de-travail`) ;
- si un fichier à écrire existe déjà sans appartenir au thème (autre personnalisation), ou appartient au
  thème mais a été modifié à la main (`--force` pour l'écraser) ;
- si `MANIFEST` ne correspond pas à `overlay/`.

Il ne touche jamais aux autres fichiers de l'overlay (plugins, packs de langue). Relancé, il ne copie que
ce qui a changé et retire les fichiers d'une ancienne version du thème.

### Réglages conseillés dans l'administration

Rien n'est obligatoire. Pour retrouver la maquette :

1. **Pages** (Admin > Gérer > Pages) : l'accroche de l'accueil est du contenu administrable (« Landing »).
   Le `<h1>` devient le titre géant sur prune, le premier paragraphe le chapeau lilas, et **une liste
   `<li>Libellé <strong>valeur</strong></li>` devient un relevé à points de conduite** comme sur le site :
   ```html
   <h1>Une question sur Heatlens ou Auditlens ? Écrivez-nous, une personne vous répond.</h1>
   <p>Le support est inclus avec chaque licence. Vos tickets restent entre vous et nous, et les données de vos visiteurs n'ont rien à y faire.</p>
   <ul><li>Support inclus <strong>jours ouvrés</strong></li><li>Support prioritaire <strong>4 h, 24 h/24</strong></li><li>Langues <strong>français, anglais</strong></li></ul>
   ```
   Ces engagements sont ceux décrits dans `docs/theme-mockups/README.md` (« Contexte de support ») : **à
   valider** avant publication. Même principe pour « Thank You » (page de remerciement) et « Offline ».
2. **Autres ressources** : les pages de type *Other* actives (politique de support, conditions, mentions
   légales…) apparaissent dans le bloc latéral de l'accueil et dans le pied de page. Le thème n'écrit aucun
   lien juridique en dur.
3. **Base de connaissances** : mettre en avant (« Featured ») catégories et articles pour l'accueil.
4. **Logo** : ne rien téléverser pour garder le logotype du thème ; un logo client téléversé le remplace.
5. **Nom de l'entreprise** (Admin > Gérer > Formulaires > Company Information) : affiché dans le copyright.

## Niveau de contrat « Priority / Standard » (dépendance facultative)

Le support Ownalytics (Heatlens, Auditlens) passe par le même portail osTicket que Coderise. Le niveau
de contrat vient de la fonction `coderise_contract_level()` du plugin `contrib/sla-first-response`
(version 0.3 et plus, README du plugin, sections 9 et 10) : `priority` si le client est membre de
l'organisation « Priority » configurée dans le plugin (celle qu'alimente le plugin `stripe-sync`), sinon
`standard`. Le thème ne lit rien d'autre (ni le plan de SLA, ni le nom de l'organisation).

**Rendu** : une **ligne de relevé** en tête du ticket de papier, comme l'en-tête d'un ticket de caisse,
au-dessus d'un filet pointillé : « Votre contrat de support ……… PRIORITY », puis l'engagement du niveau
sur la ligne suivante, aligné à droite. **Priority** est accentué en **tampon** (texte prune en capitales
sur lilas, cadre et ombre dure prune, légèrement incliné) suivi de « Premier retour sous 4 h, 24 h/24 »
en encre ; **Standard** est imprimé simplement, en encre atténuée, suivi de « Réponse les jours ouvrés »
(aucune mention de 4 h ni de 24 h/24). Le surligneur jaune n'est pas utilisé (réservé). Balisage `<dl>`
(intitulé `dt`, niveau et engagement `dd`) : lu dans l'ordre par un lecteur d'écran. Impression : noir
sur blanc, cadre noir, sans inclinaison.

| Où | Intitulé | Sujet passé à la fonction |
|---|---|---|
| Liste « Tickets » (`tickets.php`) | « Votre contrat de support » | `null` : le client connecté (une fois, pas une pastille par ligne) |
| Suivi d'un ticket, propriétaire (y compris invité arrivé par le lien d'accès) | « Votre contrat de support actuel » | Le ticket : niveau de son **propriétaire** |
| Suivi d'un ticket vu en **collaborateur** | « Contrat de support actuel du demandeur » | Le ticket : niveau du **propriétaire**, jamais du collaborateur |

Rien n'est affiché ailleurs (accueil, ouverture, base de connaissances, profil) : l'accueil reste le
contenu administrable « Landing » (relevé de présentation des deux offres, voir « Réglages conseillés »).

**Pourquoi dans l'en-tête et pas dans la fiche ticket** (la maquette mettait « Support : Prioritaire,
première réponse sous 4 h » dans « Informations sur le ticket ») : la fiche et la liste sont des
gabarits du cœur **non surchargés** (`view.inc.php`, `tickets.inc.php`, niveau 1). Les surcharger pour
une ligne ajouterait deux fichiers à rebaser à chaque mise à jour d'osTicket ; l'insérer en JavaScript
ajouterait un script au thème (aucun aujourd'hui). L'en-tête, déjà remplacé, connaît la vue (`$inc`) et
le ticket (`$ticket`) posés par `tickets.php` avant de l'inclure : la ligne est imprimée au début de
`<main>`, après les messages. **Aucune nouvelle surcharge** ; `tickets.php` est ajouté à `WATCH.sha256`
(si ces variables changent, `check-drift.sh` le signale). `dynamic-form.tmpl.php` n'est pas touché.

**Repli** (`otdc_contract()` dans `include/client/ownalytics-ticket-de-caisse/theme.php`) :

- la fonction n'existe pas (plugin absent de l'image, plugin ou instance désactivés, mise à jour
  d'osTicket en attente) : **aucune ligne**, aucune promesse 24/7, aucune erreur ;
- la fonction répond avec la source `default` (organisation Priority non configurée ou supprimée,
  invité pour `null`, utilisateur inconnu, erreur) : rien non plus ; seule la source `organisation` est
  affichée ;
- toute exception est absorbée (la fonction n'en lève pas, le thème garde un `try` par prudence) ;
- le libellé (saisi par l'administrateur dans le plugin) est toujours échappé (`Format::htmlchars`) ;
  libellé vide : « Priority » / « Standard » ;
- une valeur par personne et par page (mémo du thème, en plus du cache du plugin).

**Limites.**

- C'est le niveau **actuel** du propriétaire, pas celui du moment où le ticket a été ouvert : un ancien
  ticket d'un client devenu Priority affiche Priority (et l'inverse après la fin de l'abonnement). D'où
  « actuel » dans l'intitulé du suivi. Le délai réellement appliqué à un ticket reste celui de son plan
  de SLA (posé par le filtre à la création).
- **Pas de niveau par ligne dans la liste**, volontairement : il serait identique sur toutes les lignes
  des tickets du client (niveau actuel), et trompeur sur les anciens tickets ; seuls les tickets où il
  est collaborateur d'un demandeur d'une autre organisation diffèrent, et leur suivi l'affiche.
- Invité (lien d'accès) : le suivi montre le niveau du propriétaire du ticket (un invité collaborateur
  voit donc « Contrat de support actuel du demandeur »).
- Les engagements affichés (« Premier retour sous 4 h, 24 h/24 », « Réponse les jours ouvrés ») sont
  écrits dans le thème (`otdc__()`), pas lus dans le plugin : à modifier dans `theme.php` et `i18n/fr.php`
  si l'offre change.
- Le relevé de l'accueil proposé dans « Réglages conseillés » (« Support prioritaire 4 h, 24 h/24 ») est du
  contenu administrable, identique pour tous : il présente les offres sans dire au lecteur qu'il y a droit,
  et il reste affiché sans le plugin. Le retirer de la page « Landing » si on ne le souhaite pas.
- Coût mesuré (journal général de MariaDB, page HTML seule, cliente Priority avec 41 tickets dans la
  liste) : **34-35 requêtes** plugin actif, **30-31** plugin désactivé, **32** plugin absent ;
  **identique pour la page 1 (25 lignes) et la page 2 (16 lignes)** : le coût ne dépend pas du nombre
  de lignes (amorçage du plugin et lecture de l'organisation Priority, une fois).

## Désinstallation, bascule vers un autre thème

```sh
deploy/themes/ownalytics-ticket-de-caisse/remove.sh --dry-run
deploy/themes/ownalytics-ticket-de-caisse/remove.sh
deploy/themes/<autre thème>/apply.sh                # si l'on bascule
git add -A deploy/docker/overlay && git commit -m "overlay: …" && deploy/docker/scripts/build.sh
```

`remove.sh [--dry-run] [--force] [OVERLAY_DIR]` ne retire que les fichiers du manifeste **non modifiés**
depuis l'installation (les autres sont signalés et gardés avec le manifeste, code 1 ; `--force` les
retire), puis les dossiers devenus vides. Sans thème, le portail retrouve l'apparence d'origine. Aucune
donnée n'est modifiée en base.

## Mise à jour d'osTicket

Un fichier remplacé ne suit plus les correctifs du cœur, et la feuille dépend du balisage d'origine
(niveau 1). À chaque mise à jour d'osTicket, avant de reconstruire l'image :

1. `deploy/themes/ownalytics-ticket-de-caisse/check-drift.sh` (arbre `HEAD` ; `--rev <tag>` pour une autre
   révision ; ou un chemin vers une arborescence osTicket extraite ; fins de ligne CRLF ignorées). Code de
   sortie 0 sans dérive, 1 avec dérive.
   - **Fichiers remplacés** (`BASE.sha256`) qui ont changé : reporter le changement dans `overlay/`
     (`git diff <commit de base> <nouvelle révision> -- <fichier>` ; correctifs de sécurité en priorité).
   - **Fichiers habillés** (`WATCH.sha256`) qui ont changé : revoir visuellement les pages concernées. Les
     points fragiles sont connus : tableaux neutralisés en `display:block` (`#ticketForm`, `#ticketInfo`,
     `.infoTable`, `.custom-data`, `table.padded`), pastille de statut (première ligne du premier tableau
     d'information du ticket), pagination (`#ticketTable + div`), séparateurs en ligne de la liste,
     règles contre `css/thread.css` (taille de police fixée à 14 px) et contre le rouge posé par
     `js/osticket.js` sur le bouton d'envoi.
2. Puis `check-drift.sh --write-base` (réécrit les deux fichiers d'empreintes), `apply.sh`, committer,
   reconstruire. Commit de base actuel : `2c9292ef` (branche `develop`, osTicket 1.18.x).

## Traductions

Toutes les chaînes d'osTicket passent par `__()` et sont traduites par les packs officiels (`fr.phar` dans
`deploy/docker/overlay/include/i18n/`, voir `deploy/docker/overlay/README.md`). Les **9 chaînes propres au
thème** (« Skip to content », « Main menu », « Support », la mention Matomo/InnoCraft reprise du site, et
les 5 chaînes du niveau de contrat : intitulés « Your support contract », « Your current support
contract », « Requester's current support contract », engagements « First reply within 4 hours, 24/7 »
et « Reply on business days ») passent
par `otdc__()` : `__()` d'abord (si un pack connaît la chaîne), puis le catalogue du thème
`include/client/ownalytics-ticket-de-caisse/i18n/<langue>.php` (fourni : `fr.php`), sinon l'anglais.
Le catalogue n'est pas sous `include/i18n/` exprès (un dossier à cet endroit ferait apparaître une langue
de plus). « Ownalytics » est un nom propre, jamais traduit. Ces chaînes ne sont pas extraites par
`manage.php i18n make-pot` (fonction différente de `__()`).

## Vérifications faites (22/10/2026)

Instance de test construite comme en production : `git archive` du commit, `apply.sh` sur l'overlay
exporté, cibles `install` puis `runtime` du `Dockerfile` (racine en lecture seule, `www-data`,
`open_basedir`, configuration en lecture seule), MariaDB 11.4, **installation par l'assistant**, courrier
relevé par un Mailpit local (compte SMTP sans authentification). Données réalistes : une cliente avec
compte (Camille Roux), 40 tickets dont 33 ouverts (pagination sur 2 pages) et 7 fermés, réponses de deux
agents, pièces jointes, un collaborateur sur un ticket et un ticket d'un collègue où elle est
collaboratrice, un ticket d'un autre client (isolation), un formulaire personnalisé « Your Matomo » à 6
champs lié à 3 thèmes d'aide, 10 articles en 4 catégories (3 mises en avant), 2 pages « Autres
ressources ». Navigateur : Chromium privé (`playwright-core`), jamais le navigateur partagé. Réglage de
test : vérification MX des adresses e-mail désactivée (domaines `.test`). Détail : `captures/parcours-*.txt`.

- **Parcours réels**, à 1280, 820 et 390 px, 32 contrôles chacun, **tous passés** : ouverture d'un ticket
  sans compte (erreurs, champs dynamiques rechargés en AJAX au choix du thème d'aide, éditeur riche, pièce
  jointe, page de remerciement avec numéro) ; connexion (erreur puis succès) ; accès par e-mail + numéro
  (e-mail reçu, lien, fil, réponse de l'invité) ; inscription et confirmation par e-mail ; mot de passe
  oublié jusqu'au changement imposé et à la reconnexion ; liste (onglets ouverts/fermés, recherche,
  recherche sans résultat, pagination page 2, isolation, accès direct refusé au ticket d'un autre client) ;
  réponse avec pièce jointe et message de succès ; ticket fermé ; ticket d'un collègue en collaborateur ;
  modification ; profil ; base de connaissances (catégories, catégorie, article, recherche depuis l'accueil,
  recherche vide, filtre par thème) ; page « Autres ressources » ; page hors ligne. Un client sans aucun
  ticket voit le formulaire d'ouverture (comportement d'osTicket), capturé (`mes-tickets-aucun-*`).
- **Console et réseau** : aucune erreur ni avertissement JavaScript, aucune réponse HTTP ≥ 400, **aucune
  requête vers un autre hôte** (Gravatar compris), sur tous les parcours et toutes les largeurs.
- **Journaux** : aucune erreur, aucun avertissement PHP dans les journaux Apache/PHP du conteneur après un
  parcours complet ; journal système d'osTicket vide (seule l'entrée d'installation).
- **Débordement horizontal** : aucun, à 390, 820 et 1280 px, sur toutes les pages.
- **Contrastes** (calcul WCAG sur chaque nœud de texte visible) : minimum mesuré **5,77:1**, aucun texte
  sous 4,5:1, aux trois largeurs. Paires de la charte : encre sur papier 15,6:1, papier sur prune 13,5:1,
  lilas sur prune 7,6:1 (9,0:1 dans le pied de page), encre atténuée sur papier 6,4:1 (5,77:1 sur le papier teinté des réponses), erreur 7,9:1 et succès 7,6:1 sur blanc.
- **Clavier** : lien d'évitement en premier (« Aller au contenu »), qui place le focus sur `<main>` ;
  contour de focus de 3 px (lilas sur prune, prune sur papier) ; `prefers-reduced-motion` respecté.
  Les boutons de la barre Redactor ont `tabindex=-1` dans osTicket (inchangé).
- **Impression** du suivi : en-tête, menu, actions, formulaire de réponse, messages et pied de page
  masqués, fil en noir sur blanc (`captures/impression-suivi-ticket.png`).
- **Français** : aucun pack officiel n'est disponible hors ligne, rien n'a été téléchargé. Test fait avec
  un catalogue partiel de test (une cinquantaine de chaînes d’osTicket, monté en lecture seule, non livré) : bascule EN/FR,
  `lang="fr"`, catalogue du thème (« Aller au contenu », mention InnoCraft) affiché en français, mises en
  page tenues (`captures/*-fr-*.png`). Toutes les chaînes des fichiers du thème passent par `__()` ou
  `otdc__()`.
- **Scripts** (`deploy/docker/overlay` du dépôt) : 42 contrôles passés — `apply.sh`/`remove.sh` aller-retour,
  `--dry-run` sans écriture, idempotence, refus d'un fichier étranger, d'un fichier du thème modifié
  (conservé par `remove.sh`), d'un `MANIFEST` désynchronisé ; **refus générique** quand
  `include/client/sommet/installed.manifest` ou `include/client/ownalytics-atelier/installed.manifest`
  existe, quand `atelier` (avec manifeste) ou `poste-de-travail` (sans manifeste) est installé par son
  propre script ; refus réciproque de ces deux thèmes quand celui-ci est installé ; plugin présent dans
  l'overlay conservé ; `git status` propre ensuite. `check-drift.sh` : aucune dérive sur `HEAD` ni sur un
  arbre extrait ; détection d'un fichier remplacé modifié, d'un supprimé et d'un fichier habillé modifié
  (code 1) ; CRLF ignoré ; `--rev origin/1.15.x` : 2 fichiers remplacés et 8 habillés signalés.
- **Apache** : les fichiers PHP et le manifeste du thème sous `include/` répondent **403** en accès direct ;
  feuille (`text/css`), polices (`font/woff2`) et favicon (`image/svg+xml`) répondent 200. `php -l` sans
  erreur sur les 6 fichiers PHP.

### Niveau de contrat (22/10/2026, seconde instance)

Instance construite de la même façon depuis le commit du thème (`git archive`, `apply.sh` sur l'overlay
exporté, plugin `sla_first_response` 0.3 copié dans `include/plugins/` de l'overlay exporté, **non
committé**), cibles `install` puis `runtime`, **installation par l'assistant** (HTTP), MariaDB 11.4. Données :
plans « Priority 4h » (actif) et « réponse faite » (désactivé) ; plugin installé, actif, une instance
réglée sur l'organisation « Priority » ; clients : Léa (organisation Priority, 40 tickets, plus le ticket de
Marc où elle est collaboratrice : 41 lignes, 2 pages), Sam (organisation Acme, standard), Nina (sans
organisation), Marc (Acme), Gina (organisation Priority, sans compte : accès invité par le lien du
ticket). Chromium privé (`playwright-core` hors dépôt), 1280 et 390 px, 66 rendus, **tous conformes**.
Détail : `captures/audit-niveau-contrat.json`.

- **Plugin actif** : Léa : « Votre contrat de support … PRIORITY » + « Premier retour sous 4 h, 24 h/24 »
  sur la liste (pages 1 et 2) et sur son ticket ; Sam et Nina : Standard + « Réponse les jours ouvrés »,
  **sans** 4 h ni 24 h/24 (vérifié sur le texte de la page) ; ticket de Marc vu par Léa en collaboratrice :
  « Contrat de support actuel du demandeur … Standard » ; invitée Gina (lien d'accès) : Priority (niveau du
  propriétaire). Une seule ligne par page. Accueil (visiteur et cliente connectée) : rien.
- **Plugin désactivé** (en base) puis **absent** (image reconstruite sans le dossier du plugin, toujours
  « installé » et actif en base) : aucune ligne, aucune mention 24/7, aucune erreur, sur les 11 scénarios.
- **Console** : aucune erreur ni avertissement ; aucune réponse HTTP ≥ 400 ; aucune requête vers un autre
  hôte ; aucune erreur PHP dans les pages, dans les journaux du conteneur ni dans le journal système
  d'osTicket.
- **Débordement horizontal** : aucun, à 390 et 1280 px.
- **Contrastes** (chaque nœud de texte visible) : minimum 6,4:1 sur les 66 rendus. Ligne de contrat :
  tampon Priority prune sur lilas **7,6:1**, engagement Priority encre sur papier 15,6:1, intitulé,
  niveau Standard et engagement Standard encre atténuée sur papier 6,4:1.
- **Requêtes SQL** : voir « Niveau de contrat », Limites (pas de requête par ligne).
- **Scripts** : `apply.sh` (`--dry-run`, installation, relance idempotente) puis `remove.sh` sur
  `deploy/docker/overlay/` du dépôt, `git status` propre ensuite ; `check-drift.sh` sans dérive sur `HEAD`
  et sur l'arbre exporté (`tickets.php` compris). Correction : `check-drift.sh` lisait mal ses propres
  fichiers d'empreintes extraits en CRLF (copie de travail Windows) et signalait à tort les 4 fichiers
  remplacés « supprimés » ; le retour chariot est maintenant retiré. Suite statique du dépôt
  (`php setup/test/run-tests.php`, php:8.3-cli, sur l'arbre exporté) : 0 échec.
- **Captures** ajoutées (`-bureau` 1280 px, `-mobile` 390 px) : `contrat-liste-priority`,
  `contrat-suivi-priority`, `contrat-liste-standard`, `contrat-suivi-standard`,
  `contrat-suivi-sans-organisation`, `contrat-suivi-collaborateur`, `contrat-suivi-invite`,
  `contrat-liste-sans-plugin`, `contrat-suivi-sans-plugin`. Les captures de la première recette
  (`mes-tickets-*`, `suivi-ticket-*`…) restent valables : elles ont été faites sans le plugin, et le
  rendu sans plugin n'a pas changé.

### Non vérifié

Lecteur d'écran, Firefox et Safari (Chromium seulement), RTL (règles de base seulement), CAPTCHA (non
activé), connexion par fournisseur externe, pack de langue français officiel, envoi réel des e-mails
(Mailpit local), affichage avec un logo client téléversé. Niveau de contrat : rendu en français (pas de
pack de langue ; le catalogue du thème contient les 5 chaînes), impression de la ligne (règles écrites,
non capturées), changement de niveau en cours d'abonnement Stripe (plugin `stripe-sync` non installé :
l'appartenance à l'organisation a été posée directement).

## Écarts avec la maquette 01

La maquette montre des données ou des éléments qu'osTicket n'a pas ; ils sont remplacés par ce que le
portail fournit réellement, ou omis.

| Maquette | Implémentation |
|---|---|
| Textes en français | Interface en anglais tant que le pack de langue français n'est pas ajouté (les chaînes sont celles d'osTicket) |
| Accueil : titre, chapeau et relevé « Support inclus … jours ouvrés » | Contenu administrable de la page « Landing » (exemple ci-dessus) ; la feuille transforme toute liste de cette page en relevé à points de conduite |
| Liens juridiques du pied de page (Mentions légales, Conditions, Confidentialité) | Pages « Autres ressources » actives d'osTicket (rien n'est codé en dur) ; la mention Matomo/InnoCraft du site est reprise telle quelle |
| « Articles à la une » avec descriptions inventées | Catégories et articles mis en avant d'osTicket, avec leur extrait réel |
| Titre de connexion « Connexion » et bloc « Suivre un ticket sans compte » sur la même page | Gabarits d'origine : la connexion (titre de la page administrable « banner-client ») et l'accès par numéro (« Check Ticket Status ») restent deux pages |
| Pastilles de statut colorées dans la liste | Statut en police à chasse fixe (pas de surcharge de `tickets.inc.php`) ; sur la fiche ticket, une pastille prune neutre (le texte porte l'état ; pas de couleur par état sans modifier le gabarit) |
| « Support : Prioritaire, première réponse sous 4 h » dans la fiche | Ligne de relevé en tête du papier, sur le suivi et la liste, avec le plugin `sla_first_response` seulement (voir « Niveau de contrat ») ; pas dans le tableau de la fiche (gabarit non surchargé) |
| « Fichiers : 20 Mo par fichier, anonymisez vos données… » sous la zone de dépôt | Absent : texte non fourni par osTicket (la limite réelle est un réglage d'osTicket) |
| Titres de sections « Vos coordonnées », « Détails de votre demande » | Titres des formulaires d'osTicket (administrables) |
| Barre Redactor à 6 boutons | Barre d'origine d'osTicket (même nombre de boutons qu'osTicket), restylée ; comportement intact |
| Pagination « Page : 1 » sur une seule page | Pagination réelle d'osTicket (« Page: [1] 2 »), en cases |
| Menu « Suivre un ticket » actif sur la connexion | Comportement du cœur : la page de connexion active « Accueil » |
| Avatars | Aucun (Gravatar supprimé) ; le rôle « (Ownalytics) » suit le nom de l'agent |
| `:has()` pour passer l'accueil sur prune | Classe `is-landing` posée par l'en-tête (l'accueil n'est sur prune que s'il n'affiche pas une autre vue, par exemple le profil quand un changement de mot de passe est imposé) |

Le surligneur jaune, « réservé aux totaux » sur le site, ne souligne ici que l'élément courant (menu, filtre
actif) et l'ombre du bouton d'envoi quand un formulaire a des modifications (signal d'osTicket, voir ci-dessous).

## Limites connues

- **Fragilité du niveau 1** : la mise en page dépend de la structure des gabarits d'origine (tableaux
  neutralisés, ordre des lignes). Un changement de gabarit en amont peut décaler une page sans erreur :
  `check-drift.sh` le signale, il faut alors revoir les captures.
- Le bouton d'envoi reçoit une ombre jaune quand le formulaire a des modifications non enregistrées : c'est le
  texte rouge que `js/osticket.js` pose en style en ligne, rendu lisible. Il apparaît parfois dès le
  chargement (Redactor ou l'autoremplissage déclenchent l'événement), comme dans le thème d'origine.
- Sur mobile, l'accueil montre d'abord le bloc d'actions (ordre du code, donc ordre du clavier), puis la
  recherche et l'accroche.
- Sur mobile, le champ « Ext. » du téléphone passe à la ligne.
- Polices en sous-ensemble latin : un nom en alphabet non latin s'affiche avec la police système.
- Le PDF d'impression d'osTicket (`tickets.php?a=print`) garde sa mise en page ; seule l'impression du
  navigateur est habillée. L'impression masque aussi les messages (succès, avertissement).
- Mode clair uniquement (la charte n'a pas de mode sombre). RTL : règles de base, non testé.
- Couleurs d'état (rouge `#9E1B2E`, vert `#17603F`, jaune pâle d'avertissement) : extensions de la charte.

## Points à valider

1. **Logo** : il n'existe pas de logo vectoriel autonome d'Ownalytics ; le pictogramme du site (ticket à bord
   dentelé) est redessiné en SVG, suivi de « Ownalytics » en Archivo 800 largeur 125 % et de « Support » en
   IBM Plex Mono. À confirmer, ou fournir un fichier officiel.
2. **Couleurs d'état** hors charte (erreur, succès, avertissement).
3. **Contenu de l'accueil** (accroche, engagements « 4 h, 24 h/24 », langues) et des pages de remerciement
   et hors ligne : contenu administrable proposé, à valider avant publication.
4. La reprise dans le pied de page de la mention « Matomo est une marque déposée d'InnoCraft Ltd… » du site.
5. Le pack de langue français officiel à ajouter à l'overlay, puis une relecture des écrans en français.
6. Les pages « Autres ressources » à créer (politique de support, conditions, mentions légales).
7. **Niveau de contrat** : textes des engagements (« Premier retour sous 4 h, 24 h/24 », « Réponse les
   jours ouvrés »), place de la ligne (en tête du papier plutôt que dans la fiche, pour ne pas surcharger
   `view.inc.php`), tampon incliné pour Priority, et affichage du niveau **actuel** (et non celui de la
   création du ticket) sur les anciens tickets.
