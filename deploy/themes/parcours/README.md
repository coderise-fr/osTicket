# Thème « Parcours » du portail client (Coderise)

Mise en œuvre de la proposition 04 des maquettes Coderise (`docs/theme-mockups-coderise/04-parcours/`,
charte Coderise v1.0) : un portail **guidé, orienté progression**. L'accueil propose les sujets d'aide
comme points de départ, l'ouverture de ticket devient un **assistant en quatre étapes**, le suivi d'un
ticket montre une frise d'avancement et « votre prochain pas », la base de connaissances est organisée en
parcours. Portail **client** uniquement : le panneau agent (`scp/`) n'est pas touché.

C'est une des quatre alternatives développées (slugs `atelier`, `poste-de-travail`, `sommet`, `parcours`) :
**un seul thème est installé à la fois**. Rien n'est modifié dans le cœur d'osTicket ni dans
`deploy/docker/` : `apply.sh` copie `overlay/` dans `deploy/docker/overlay/`, que l'image Docker superpose
à osTicket au moment de la construction (voir `deploy/docker/README.md`, « Plugins et personnalisation »
et « Personnalisation du thème »).

## Contenu du dossier

| Chemin | Rôle |
|---|---|
| `overlay/` | Arbre qui reflète la racine d'osTicket : ce qui est installé dans l'image |
| `MANIFEST` | Liste exacte des fichiers de `overlay/` (`apply.sh` refuse si elle diffère) |
| `BASE.sha256` | Empreintes des fichiers du cœur **remplacés**, au commit de base |
| `WATCH.sha256` | Empreintes des fichiers du cœur **dont le thème dépend** sans les remplacer |
| `apply.sh`, `remove.sh` | Installation et retrait dans l'overlay (idempotents, manifeste d'installation) |
| `check-drift.sh` | Liste les fichiers du cœur qui ont changé depuis le commit de base |
| `captures/` | Captures des pages réelles (bureau 1280 px, mobile 390 px, tablette 820 px), rapports d'audit |

### Fichiers ajoutés (sans équivalent dans le cœur)

- `assets/parcours/css/parcours.css` : toute la feuille (écran et impression). Remplace
  `assets/default/css/theme.css`, `print.css` et `flags.css`, qui ne sont plus chargées.
- `assets/parcours/js/parcours.js` : l'assistant d'ouverture et la fermeture des menus au clavier
  (amélioration progressive ; sans dépendance autre que le jQuery déjà chargé par osTicket).
- `assets/parcours/fonts/` : Montserrat 600/700/800 et Inter 400/500/600, sous-ensembles latin et
  latin-ext, WOFF2, copiés de `docs/theme-mockups-coderise/_shared/fonts/`, avec leurs licences SIL OFL 1.1
  (`OFL-montserrat.txt`, `OFL-inter.txt`).
- `assets/parcours/img/logo-horizontal-sombre.png` : logo horizontal détouré des maquettes (aucun SVG
  horizontal officiel), utilisé sur les zones bleu nuit ; `favicon.svg` : copie de
  `deploy/themes/_shared/coderise/symbole-sur-bleu-nuit.svg` (symbole sur carré bleu nuit).
- `include/client/parcours/parcours.inc.php` : fonctions du thème (`pcrs__()`, icônes, motif
  « Sommet », prochain pas, première réponse, jauge du délai Priority, niveau de contrat, temps de
  lecture, fil d'Ariane).
- `include/client/parcours/i18n/fr.php` : catalogue français des chaînes propres au thème.

Aucune ressource externe : polices, icônes (SVG en ligne), images et script sont servis par osTicket. La
CSP d'osTicket (`script-src 'self' 'unsafe-inline'`) suffit. Les fichiers PHP du thème sont sous
`include/`, que la configuration Apache de l'image refuse (403, vérifié).

## Fichiers du cœur remplacés (12)

Chaque gabarit remplacé garde les champs, noms de champs, actions, jeton CSRF (`csrf_token()`), contrôles
d'accès, appels `__()` et échappements (`Format::htmlchars`, `Format::display`…) de l'original ; la logique
PHP (requêtes, visibilité, tri, pagination, brouillons, pièces jointes) est recopiée telle quelle.

| Fichier | Pourquoi il est remplacé |
|---|---|
| `include/client/header.inc.php` | En-tête bleu nuit (logo, menu avec `aria-current`, bouton « Ouvrir un ticket », menu du compte, langues), menu mobile, lien d'évitement, feuille et script du thème. Même prélude PHP, mêmes en-têtes HTTP (CSP), mêmes scripts, mêmes messages `$errors['err']`/`$msg`/`$warn`. |
| `include/client/footer.inc.php` | Pied de page (logo, signature, contact, délais, pages « Autres ressources ») ; sur la page de remerciement, frise « où en est votre demande » avec le numéro du ticket créé. Mêmes scripts de fin (`getConfig()`, i18n). |
| `index.php` | Accueil « Que souhaitez-vous faire ? » : les sujets d'aide publics comme choix (lien `open.php?topicId=…`), frise du déroulé, accès par e-mail et numéro (visiteur) ou compteurs réels (client), articles mis en avant avec temps de lecture. Fichier prévu pour être adapté (voir son en-tête). |
| `include/client/open.inc.php` | **Assistant en quatre étapes** (voir plus bas). Prélude PHP d'origine inchangé. |
| `include/client/tickets.inc.php` | Liste en lignes groupées « À vous de jouer » / « Chez Coderise », onglets Ouverts/Fermés, recherche, filtre par sujet, tri, pagination, état vide. |
| `include/client/view.inc.php` | Suivi : bandeau, frise d'avancement, prochain pas, jauge du délai Priority, fil en cartes, formulaire de réponse, colonne d'informations. |
| `include/client/templates/thread-entry.tmpl.php` | Message du fil : initiales au lieu des avatars (pas de Gravatar), rôle « Vous » / « Équipe support ». |
| `include/client/templates/thread-event.tmpl.php` | Même balisage ; retire les images d'avatar des événements (Gravatar par défaut). |
| `include/client/login.inc.php` | Connexion en deux colonnes : compte, et accès sans compte (même formulaire que `accesslink.inc.php`, même cible `login.php`). |
| `include/client/accesslink.inc.php` | Accès à un ticket par e-mail et numéro, en bandeau et cartes. |
| `include/client/kb-categories.inc.php` | Base de connaissances « en parcours » : catégories de premier niveau en colonnes, sous-catégories et articles dedans, articles mis en avant. Même requête. |
| `include/client/faq.inc.php` | Article : temps de lecture, « article N sur M » et précédent / suivant dans la catégorie, sommaire de la catégorie. |

Laissés d'origine, habillés par la feuille seule (listés dans `WATCH.sha256`) : inscription et
confirmation, mot de passe oublié (3 pages), profil, modification d'un ticket, résultats de recherche et
catégorie de la base, page hors ligne, page de remerciement (contenu administrable),
`templates/dynamic-form.tmpl.php` (**non surchargé** : partagé par l'ouverture, l'inscription, le profil,
la modification et le rechargement AJAX des champs), `templates/thread-entries.tmpl.php`. `WATCH.sha256`
suit aussi les scripts et contrats dont l'assistant dépend : `open.php`, `tickets.php`, `include/ajax.forms.php`
(rechargement des champs), `include/class.forms.php`, `js/osticket.js`, `js/redactor-osticket.js`,
`js/filedrop.field.js`, `css/thread.css`, `css/redactor.css`, `css/filedrop.css`, `include/class.nav.php`,
`include/class.pagenate.php`.

## L'assistant d'ouverture de ticket

C'est **un seul formulaire osTicket** (`#ticketForm`, `action="open.php"`, `enctype` multipart, jeton CSRF,
`a=open`), posté **une seule fois**, avec les noms de champs d'osTicket. Il est découpé en quatre
`fieldset` :

1. **La demande** : coordonnées (formulaire « Contact Information » pour un visiteur, adresse et nom pour un
   client connecté) et sujet d'aide (`#topicId`).
2. **Le contexte** : les formulaires propres au sujet d'aide.
3. **Votre message** : le formulaire « Ticket Details » (résumé, message avec Redactor et brouillon, pièces
   jointes, et ses autres champs).
4. **L'envoi** : priorité (si elle est visible pour les clients), CAPTCHA, récapitulatif, boutons d'origine
   (Créer, Réinitialiser, Annuler qui supprime le brouillon).

**Sans JavaScript**, le formulaire s'affiche d'un bloc : étape 1, puis tous les champs du sujet dans
l'étape 2 (le `tbody#dynamic-form` d'origine), puis l'envoi. Il fonctionne comme celui d'osTicket (vérifié :
ticket créé avec toutes les valeurs). Comme dans le thème d'origine, le changement de sujet et l'envoi de
pièces jointes demandent JavaScript.

**Avec JavaScript** (`parcours.js`) :

- une seule étape visible à la fois ; navigation par les boutons « Étape précédente / suivante » et par la
  frise (boutons, chaque étape est accessible à tout moment) ; `aria-current="step"`, titre d'étape annoncé
  dans une zone `aria-live`, focus placé sur l'étape ouverte, étapes masquées par `hidden` (rien de
  focalisable caché, pas de piège clavier) ; Entrée dans un champ d'une ligne passe à l'étape suivante au
  lieu d'envoyer ;
- les lignes de `#dynamic-form` sont réparties entre les étapes 2 à 4. Le formulaire « Ticket Details » est
  reconnu par le nom haché de ses champs `subject` et `message`, calculé côté serveur à partir de
  `TicketForm` (même formule que l'affichage : session, identifiant du formulaire, nom du champ) ; la ligne
  `priority` va à l'étape 4 ; les autres formulaires vont à l'étape 2 ;
- au changement de sujet, même appel AJAX que le cœur (`ajax.php/form/help-topic/<id>`, valeurs saisies
  transmises pour être conservées), puis nouvelle répartition. Si le sujet n'a pas de formulaire propre,
  l'étape 2 le dit ;
- la validation reste celle du serveur, inchangée. Après un envoi refusé, l'assistant **ouvre l'étape de
  la première erreur**, liste toutes les erreurs avec leur étape et un lien vers le champ, met le focus sur
  cette liste, ajoute `aria-invalid` et `aria-describedby` aux champs concernés, et signale le nombre
  d'erreurs dans la frise ;
- `novalidate` est posé sur le formulaire (en mode assistant seulement) pour qu'un champ requis d'une étape
  masquée ne bloque pas l'envoi : c'est le serveur qui contrôle, comme pour les champs dynamiques d'osTicket.

## Données réelles uniquement : ce qui remplace la maquette

| Maquette | Implémentation |
|---|---|
| Trois choix à l'accueil (incident, question, évolution) | Les **sujets d'aide publics** (6 au plus, puis « Une autre demande »), avec le motif « Sommet ». Pas de pastille « Priority : réponse sous 4 h » ni « chiffrage » par choix (aucune donnée par sujet). L'accroche est la page « Landing » de l'administration. |
| Frise « étape 3 en cours » de l'accueil | Frise neutre du déroulé (étape 1 en cours), sans promesse de délai. |
| Assistant : « Étape 2 sur 4 », erreurs par étape | Réel (voir plus haut). Les titres de section internes sont ceux des formulaires d'osTicket (administrables). |
| Frise du ticket « Reçu / Pris en charge / Correctif en test / Résolu » | **Créé** (date de création), **Premier retour** (date de la première réponse d'un agent, entrée visible de type R), **Fermé** (date de fermeture). Pas d'étape « correctif en test » (aucun statut de ce type). |
| « Votre prochain pas » rédigé par l'équipe | Déduit de l'état réel : ouvert sans réponse → « Nous revenons vers vous » ; dernière action = réponse de l'équipe (`isanswered`) → « À vous de jouer » avec un lien vers la réponse ; fermé → « Encore un souci ? Répondez ci-dessous » si le ticket peut être rouvert. |
| Altimètre « Priority : premier retour sous 4 h » | Jauge affichée **seulement** pour un ticket ouvert dont le plan de SLA s'appelle `PARCOURS_SLA_PRIORITY` (défaut `Priority 4h`, le plan surveillé de `contrib/sla-first-response`) **et** est actif, avec une échéance lisible (`est_duedate`). Graduée de 0 au délai de grâce du plan, temps écoulé depuis la création (ou la réouverture), calculé au chargement de la page. Après la première réponse, le plugin bascule le ticket sur un plan désactivé : **la jauge disparaît seule** (vérifié avec ce scénario). **À valider.** |
| Regroupement « À vous de jouer / Chez Coderise / Terminées » | Vue par défaut des tickets ouverts : groupes « À vous de jouer » (`isanswered`) et « Chez Coderise », tri par `isanswered` puis date pour que les groupes tiennent sur plusieurs pages. Les fermés ont leur onglet. Un tri choisi donne une liste à plat dans l'ordre demandé. |
| Mini-frise « Étape 2 sur 4 » par ticket | Retirée (pas de donnée) ; une pastille d'état et le prochain pas la remplacent. |
| Pastille de contrat Standard / Priority par ticket | **Réelle si le plugin `sla_first_response` est actif** (section « Niveau de contrat » ci-dessous) : niveau **actuel** du propriétaire du ticket, sur les tickets **ouverts** seulement. Rien sans le plugin. |
| « Répondre à Nora », réponse « il y a 3 h » | « Répondre » (chaîne d'osTicket) ; la date de création est affichée. |
| Bibliothèque « Démarrer / Livrer / Opérer » | Les **catégories de premier niveau** (et leurs sous-catégories) : le parcours est celui que l'administrateur crée. |
| « Les plus lus » | « Questions à la une » (articles mis en avant) : osTicket n'a pas de statistiques de lecture côté client. |
| « Article 2 sur 4 », précédent / suivant | Articles publiés de la catégorie, dans l'ordre de la page de catégorie d'osTicket (à la une d'abord, puis par question) : osTicket n'a pas d'ordre d'article. |
| Durées de lecture « 4 min » | Calculées : nombre de mots de l'article / 200, au moins 1 min. |
| Liens LinkedIn, GitHub du pied de page | Non repris (non fournis dans la charte) ; `www.coderise.fr` et les pages « Autres ressources » actives. |
| Barre Redactor à 6 boutons, zone de dépôt | Redactor et dépôt de fichiers d'osTicket, restylés. |

## Niveau de contrat (Priority / Standard)

**Dépendance facultative** : la fonction `coderise_contract_level()` du plugin
`contrib/sla-first-response` (version 0.3 ou plus, README du plugin, sections 9 et 10). Le niveau est
l'appartenance du **propriétaire** du ticket à l'organisation « Priority » configurée dans le plugin
(celle qu'alimente le plugin Stripe). Le thème n'appelle la fonction qu'au travers de `pcrs_contract()`
(`parcours.inc.php`), qui :

- vérifie `function_exists('coderise_contract_level')` : plugin ou instance **désactivés**, ou fichiers du
  plugin **absents** de l'image → **aucun niveau, aucune promesse**, aucune erreur ;
- n'affiche rien quand la source est `default` (visiteur non connecté, invité pour son propre niveau,
  organisation Priority non configurée ou supprimée) : on ne promet rien sans niveau connu. Un client
  sans organisation, l'organisation Priority étant configurée, est **Standard** ;
- mémorise le résultat par propriétaire pour la requête (en plus du cache du plugin) ; libellés du plugin
  (configurables) échappés par `Format::htmlchars`.

Où il apparaît :

| Page | Affichage |
|---|---|
| Liste des tickets (groupes « À vous de jouer » / « Chez Coderise », liste triée, page 2…) | Pastille du niveau du **propriétaire** sur chaque ticket **ouvert** : le client connecté pour ses tickets (`coderise_contract_level()` sans argument), sinon le propriétaire (`User::lookup($T['user_id'])`, une recherche par propriétaire distinct). Pas de pastille sur les tickets fermés. |
| Suivi d'un ticket (client, collaborateur, invité) | Pastille du propriétaire (`coderise_contract_level($ticket)`) à côté de l'état ; dans la frise, tant que le premier retour est attendu : « Attendu sous 4 h, 24 h/24, 7 j/7 » (Priority) ou « Attendu les jours ouvrés » (Standard) ; dans « Votre prochain pas » (ticket ouvert) : « Contrat de support » + pastille, avec « Premier retour sous 4 h, 24 h/24, 7 j/7. » tant que le premier retour est attendu (Priority) ou « Nous vous répondons les jours ouvrés. » (Standard). Un ticket d'un collègue vu en collaborateur montre le niveau **du collègue**. |
| Assistant d'ouverture, étape 4 « L'envoi » | Encadré « Votre contrat de support » + pastille : « Votre ticket reçoit un premier retour sous 4 h, 24 h/24, 7 j/7. » (Priority) ou « Nous vous répondons les jours ouvrés. » (Standard). Client connecté seulement ; rien pour un visiteur ou un invité. Hors des champs : aucun nom de champ, aucune donnée postée, pas de `div.error` (l'assistant ne le compte pas comme une erreur) ; sans JavaScript il s'affiche dans le bloc d'envoi. |
| Accueil (client connecté) | Étape 3 « Nous répondons » de la frise : pastille + engagement (maquette : « Premier retour sous 4 h avec Priority »). Visiteur : texte neutre inchangé. |

Engagements affichés : ceux de la charte (« Support standard : jours ouvrés. Support Priority : premier
retour sous 4 h, 24 h/24, 7 j/7 »), textes fixes du thème. Aucun engagement Priority n'est affiché à un
client Standard, et aucun délai n'est promis après le premier retour d'un ticket Priority. La **jauge**
« premier retour sous 4 h » est **inchangée** (plan `PARCOURS_SLA_PRIORITY` actif, ticket ouvert sans
réponse, échéance lisible).

Limites et écarts :

- **Niveau actuel**, pas celui du moment de la création du ticket : un ticket ouvert avant l'abonnement
  Priority s'affiche Priority (sa jauge n'apparaît pas si son plan de SLA n'est pas `Priority 4h`) ; après la
  fin de l'abonnement, un ancien ticket Priority s'affiche Standard. C'est pour cela que la pastille n'est
  pas mise sur les tickets fermés (écart avec la maquette, qui la montre aussi sur les tickets terminés).
- Le « 4 h » des textes est fixe (charte) ; la jauge, elle, lit le délai de grâce du plan.
- Un invité qui consulte un ticket voit le niveau du **propriétaire** de ce ticket (souvent lui-même), pas
  le sien.
- Accueil : pas de pastille « Priority : réponse sous 4 h » par sujet d'aide (aucune donnée par sujet).

## Chaînes et langues

- Les chaînes d'osTicket passent par `__()` (traduites par les packs de langue officiels, à déposer dans
  `deploy/docker/overlay/include/i18n/`, voir `deploy/docker/overlay/README.md`).
- Les 144 utilisations de chaînes propres au thème passent par `pcrs__()` : `__()` d'abord (le pack gagne s'il
  connaît la chaîne), puis le catalogue `include/client/parcours/i18n/<langue>.php` (fourni : `fr.php`),
  sinon l'anglais. Même mécanisme que « Poste de travail ». Le catalogue n'est pas sous `include/i18n/`
  (un dossier là ferait apparaître une langue de plus dans osTicket).
- Les textes Coderise du pied de page (délais, horaires, signature) sont des chaînes du thème ; les
  coordonnées (e-mail, téléphone, ville) ne sont pas traduites.
- Accroches de l'accueil, de la page de remerciement et de la page hors ligne : **contenu administrable**
  (Admin > Gérer > Pages). Proposition (utilisée sur l'instance de test) :
  - Accueil : `<h1>Que souhaitez-vous faire ?</h1><p>Choisissez, nous vous guidons : quelques questions, un message, et votre demande part à la bonne personne. Faisons monter votre projet d'un niveau.</p>`
  - Remerciement : `<h1>Demande envoyée, merci %{ticket.name.first}.</h1><p>Votre ticket <strong>#%{ticket.number}</strong> est enregistré. Nous vous répondons par e-mail à %{ticket.email} et sur ce portail.</p><p>Gardez ce numéro : il vous permet de suivre le ticket sans compte.</p>`
  - Hors ligne : `<h1>Le portail est en maintenance.</h1><p>Nous revenons très vite. Pour une urgence, écrivez à contact@coderise.fr.</p>`

## Installation

Depuis la racine du dépôt :

```sh
deploy/themes/parcours/check-drift.sh           # le thème correspond-il à la version d'osTicket ?
deploy/themes/parcours/apply.sh --dry-run       # ce qui va être copié
deploy/themes/parcours/apply.sh                 # copie dans deploy/docker/overlay/ + manifeste d'installation
git add -A deploy/docker/overlay && git commit -m "overlay: thème Parcours"
deploy/docker/scripts/build.sh                  # l'image est construite depuis le commit
# puis déploiement comme une mise à jour (deploy/docker/README.md, « Mises à jour »)
```

`apply.sh [--dry-run] [--force] [OVERLAY_DIR]` refuse, sans rien copier :

- si `MANIFEST` ne correspond pas à `overlay/` ;
- si un **autre thème** est installé : `atelier`, `poste-de-travail`, `sommet` (et tout autre dossier de
  `deploy/themes/`) est reconnu par son manifeste `include/client/<slug>/installed.manifest` (chemin utilisé
  par les scripts des quatre thèmes), par son dossier `include/client/<slug>/` ou `assets/<slug>/`, ou par
  un de ses fichiers d'overlay présent à l'identique (anciennes installations sans manifeste) ;
- si un fichier à écrire existe avec un autre contenu sans appartenir au thème (« étranger »), ou appartient
  au thème mais a été modifié à la main (« modifié », sauf `--force`).

Il écrit `include/client/parcours/installed.manifest` (fichiers et empreintes ; non servi), ne touche
jamais aux autres fichiers de l'overlay (plugins, packs de langue), est idempotent et retire les fichiers
d'une version précédente du thème qui n'existent plus.

Réglages conseillés dans l'administration : sujets d'aide publics (ce sont les choix de l'accueil, les 6
premiers), formulaires propres à chaque sujet (ils forment l'étape « Le contexte »), visibilité « client »
du champ Priorité si on veut le proposer à l'étape 4, catégories de premier niveau de la base (les
parcours) avec leurs sous-catégories, articles mis en avant, pages « Autres ressources » (pied de page),
plans de SLA de `contrib/sla-first-response` pour la jauge. Un logo client téléversé (Paramètres > Système)
remplace le logo de l'en-tête. **Ne pas** téléverser de logo pour garder celui de la charte.

Nom du plan de SLA de la jauge : constante `PARCOURS_SLA_PRIORITY` (défaut `Priority 4h`), modifiable dans
`ost-config.php` (`define('PARCOURS_SLA_PRIORITY', 'Mon plan');` ; chaîne vide = jauge désactivée).

## Désinstallation, bascule vers un autre thème

```sh
deploy/themes/parcours/remove.sh --dry-run
deploy/themes/parcours/remove.sh                # retire les fichiers du manifeste, non modifiés
deploy/themes/<autre>/apply.sh                  # si l'on bascule
git add -A deploy/docker/overlay && git commit -m "overlay: thème <autre>" && deploy/docker/scripts/build.sh
```

`remove.sh [--dry-run] [--force] [OVERLAY_DIR]` ne retire que les fichiers listés dans le manifeste
d'installation et non modifiés depuis (les autres sont signalés et gardés avec le manifeste, code de
sortie 1 ; `--force` les retire), puis les dossiers devenus vides. Sans thème, osTicket reprend son
apparence d'origine. Aucune donnée n'est modifiée en base.

## Mise à jour d'osTicket

1. `deploy/themes/parcours/check-drift.sh` (commit courant), `--rev <tag ou branche>` (autre révision) ou
   `check-drift.sh /chemin/osticket` (arbre extrait). Il liste les fichiers **remplacés** qui ont changé
   (`BASE.sha256` : reporter le changement dans `overlay/`, `git diff <base> <nouvelle> -- <fichier>`, les
   correctifs de sécurité en priorité) et les fichiers **dont le thème dépend** (`WATCH.sha256` : revoir les
   pages, et l'assistant si `ajax.forms.php`, `class.forms.php`, `dynamic-form.tmpl.php` ou les scripts
   changent). Fins de ligne CRLF ignorées. Code de sortie 0 sans dérive, 1 avec dérive.
2. Quand c'est fait : `check-drift.sh --write-base [REV]`, committer, `apply.sh` (met l'overlay à jour),
   committer, reconstruire.

Commit de base des empreintes actuelles : `2c9292ef` (branche `develop`).

## Vérifications (22/10/2026)

Instance de test : image construite comme en production (`git archive` du commit `c547bb56`, `apply.sh`
sur l'overlay exporté, `Dockerfile` cible `runtime`), conteneur durci (racine en lecture seule,
`cap_drop ALL`, configuration montée en lecture seule), MariaDB 11.4, installation par l'assistant
d'osTicket. Pile Docker, ports et volumes propres au test, supprimés ensuite. Navigateur : Chromium privé
piloté par `playwright-core` (installé hors dépôt). Données : une cliente (Léa Marchand) avec 39 tickets
(ouverts répondus ou non, fermés, avec pièces jointes et réponses de deux agents), un collègue (Paul
Girard) dont un ticket la met en collaboratrice, une cliente n'ayant qu'un ticket fermé (état vide), un
client tiers (isolation), deux plans de SLA « Priority 4h » (actif, calendrier 24/7) et « Priority 4h - 1re
reponse faite » (désactivé), un ticket Priority sans réponse et un ticket Priority basculé après réponse,
le sujet « Incident en production » avec un formulaire « Contexte de l'incident » (2 listes requises, 2
textes), 9 articles en 3 parcours et 5 sous-catégories.

Exécuté :

- **Assistant, avec JavaScript** (1280 et 390 px, 19 contrôles chacun, tous passés) : étape 1 seule
  visible ; « Étape suivante » au clavier (Entrée) ouvre l'étape 2 et y place le focus ; champs du sujet
  rechargés en AJAX dans l'étape 2 ; brouillon du message enregistré (`ajax.php/draft`, 200) ; changement de
  sujet depuis l'étape 3 vers un sujet sans formulaire (message de l'étape 2), puis retour : résumé et
  message conservés, champs revenus ; pièce jointe envoyée ; priorité à l'étape 4 ; récapitulatif (sujet,
  résumé, 1 pièce jointe) ; envoi avec les deux listes requises vides → page ré-affichée par le serveur,
  **étape 2 ouverte**, 2 erreurs listées avec lien, focus sur la liste, `aria-invalid` sur les 2 champs,
  résumé et pièce jointe conservés ; correction puis envoi → page de remerciement. Ticket vérifié en base :
  sujet, priorité choisie (« High »), environnement, impact, version, message et pièce jointe.
- **Assistant, sans JavaScript** (1280 et 390 px) : formulaire d'un bloc, pas de frise, envoi → ticket créé
  avec toutes les valeurs (vérifié en base).
- **Parcours de pages** (81 rendus : 27 pages × 1280, 390, 820 px) : accueil (visiteur, client), connexion
  et erreur, inscription, mot de passe oublié et envoi, accès par numéro, ticket vu en invité (accès par
  e-mail + numéro), base (parcours, catégorie, article, recherche, recherche vide), liste (groupes, page 2,
  fermés, tri, recherche vide, état vide), suivi (répondu avec pièces jointes, Priority avec jauge, fermé,
  collaborateur), modification, profil, ouverture connectée ; page hors ligne (système passé hors ligne puis
  rétabli) ; impression du suivi (`captures/impression-suivi-ticket.png`).
- **Débordement horizontal** : aucun, sur les 81 rendus et les captures de l'assistant (390 px compris).
- **Contrastes** (chaque nœud de texte visible, fond réel composé, seuil 4,5:1, 3:1 en grand texte) :
  minimum **4,91:1**, aucun échec (`captures/audit.json`). Corrigés en cours de route : liens des pièces
  jointes (bleu de `thread.css`, 3,06:1) et liens cyan profond sur voile (4,46:1, l'écart de la charte : ils
  passent en bleu nuit soulignés de cyan profond).
- **Console et réseau** : aucune erreur ni avertissement JavaScript, aucune réponse HTTP ≥ 400, **aucune
  requête vers un autre hôte**, sur tous ces parcours.
- **Journaux Apache/PHP** pendant la passe finale : aucune erreur ni avertissement PHP, aucune réponse 4xx/5xx.
  Seul message : `sendmail: not found` (osTicket tente d'envoyer les accusés de réception ; pas de messagerie
  sur l'instance de test, sans lien avec le thème).
- **Clavier** (`captures/clavier.json`) : lien d'évitement en premier, contour de focus visible sur tous les
  éléments atteints (accueil, assistant, liste, suivi, base), pied de page atteint (pas de piège), menu du
  compte ouvert par Entrée et fermé par Échap avec retour du focus.
- **Fichiers** : les PHP du thème et le manifeste d'installation répondent **403** ; feuille, script, polices
  (`font/woff2`), logo et favicon répondent 200.
- **Scripts** : `apply.sh`/`remove.sh` aller-retour sur `deploy/docker/overlay/` (`git status` propre
  ensuite) ; sur un overlay de test, 15 contrôles passés : refus si atelier (manifeste), sommet (dossier
  `assets/sommet`) ou poste-de-travail (fichier identique, sans manifeste) est installé, refus d'un fichier
  étranger (rien copié), idempotence, refus d'un fichier du thème modifié puis `--force`, `remove.sh` qui garde
  un fichier modifié (code 1) puis `--force`, plugin présent conservé, dossiers vides retirés.
  `check-drift.sh` : HEAD et `origin/1.18.x` sans dérive ; sur une copie modifiée, un gabarit remplacé
  modifié, un supprimé et deux fichiers suivis modifiés détectés (code 1).
- **Traductions** : sur l'instance, langue forcée à `fr` en session : `pcrs__()` renvoie le catalogue du
  thème (« À vous de jouer », « Étape %1$d sur %2$d »…) ; les chaînes du thème ont une entrée dans `fr.php`
  (144 utilisations, contrôle refait le 22/10/2026 après l'ajout du niveau de contrat).

Non vérifié :

- **Français complet** : aucun pack de langue officiel n'est disponible hors ligne (rien n'a été téléchargé) ;
  les captures sont en anglais pour les chaînes d'osTicket et du thème. Les chaînes sont toutes passées par
  `__()`/`pcrs__()`.
- Envoi réel des e-mails, lien d'accès envoyé par e-mail (instance réglée sans vérification par e-mail),
  lecteur d'écran, Firefox et Safari, CAPTCHA (désactivé), connexion par fournisseur externe, RTL, le plugin
  `sla_first_response` lui-même (le basculement de plan a été simulé par le jeu de données).

### Niveau de contrat (22/10/2026, commit `c8cfdad9`)

Instance : image construite comme en production (`git archive` du commit, `apply.sh` sur l'overlay
exporté, cible `runtime`, conteneur durci), installation par l'assistant d'osTicket, MariaDB 11.4. Plugin
`contrib/sla-first-response/plugin/sla_first_response/` copié dans l'overlay exporté
(`include/plugins/sla_first_response/`, **non committé**), installé, activé, instance configurée avec les
plans « Priority 4h » (actif, 24/7) / « Priority 4h - 1re reponse faite » (désactivé) et l'organisation
« Priority ». Données : Léa (organisation Priority, 40 tickets puis 42, dont 26 ouverts, réponses d'agent :
le plugin a bien basculé les tickets Priority répondus), Paul (Acme, Standard) dont un ticket la met en
collaboratrice, Sam (Acme), Noé (sans organisation), Marc (organisation Priority, sans compte : invité),
sujet « Report a Problem » avec un formulaire (un champ requis). Chromium privé (`playwright-core`, image
`mcr.microsoft.com/playwright`), pages servies en `localhost`.

Exécuté (`captures/contrat.json` : 162 rendus, 1280 / 390 / 820 px, dans les trois modes) :

- **Plugin actif** (201 contrôles, tous passés) : visiteur (accueil neutre, assistant sans encadré ni
  « 4 h » dans le formulaire) ; invité Marc (pastille Priority du propriétaire, jauge présente ; assistant
  sans encadré) ; Léa (accueil : Priority + engagement ; liste : pastille Priority sur chacun de ses
  tickets ouverts, **Standard** sur le ticket de Paul ; onglet Fermés sans pastille ; page 2 ; liste
  triée ; suivi Priority sans réponse : pastille, frise « Expected within 4 h, 24/7 », prochain pas avec
  l'engagement, jauge inchangée ; suivi répondu : pastille sans promesse de délai, pas de jauge ; ticket de
  Paul en collaboratrice : **Standard**, « jours ouvrés », aucun « 4 h » ; étape 4 : encadré Priority) ;
  Sam et Noé : Standard partout, jamais « 4 h » ni « 24/7 ».
- **Plugin désactivé** (fonction absente ; 174 contrôles) et **plugin absent** (image reconstruite sans
  ses fichiers, plugin toujours actif en base ; 174 contrôles) : aucune pastille, aucun encadré, frise et
  accueil d'origine, jauge inchangée, **aucune erreur PHP** dans les journaux, aucune réponse ≥ 400.
- **Assistant, non-régression** (48 contrôles par mode, plugin actif, désactivé et absent, 1280 et 390 px) :
  avec JavaScript (Léa) étape 1 seule, champs du sujet chargés dans l'étape 2, changement de sujet depuis
  l'étape 3 et retour (résumé et message conservés, encadré inchangé, mêmes noms de champs), récapitulatif,
  envoi avec le champ requis vide → **étape 2 ré-ouverte**, 1 erreur listée (l'encadré n'est pas compté),
  focus sur la liste, `aria-invalid`, puis correction → ticket créé ; sans JavaScript (Sam) formulaire
  d'un bloc, encadré Standard visible dans le bloc d'envoi, ticket créé ; visiteur avec JavaScript, étape 4
  sans encadré, ticket créé. Tickets vérifiés en base (sujet, sujet d'aide, champs du formulaire, message).
- **Requêtes SQL** (journal général de MariaDB, une requête `tickets.php` de Léa, 25 lignes dont un ticket
  d'un autre propriétaire) : 37 requêtes plugin actif contre 32 plugin désactivé ; l'écart (5) comprend
  le démarrage du plugin et **une** recherche du propriétaire distinct : coût constant, pas une requête par
  ligne.
- Débordement horizontal : aucun ; contrastes : minimum **4,91:1** (pastille Standard, ardoise sur gris),
  Priority 19,16:1, aucun échec ; console : aucune erreur ni avertissement ; aucune requête vers un autre
  hôte. `apply.sh` / `remove.sh` aller-retour sur `deploy/docker/overlay/` (`git status` propre),
  `check-drift.sh` sans dérive ; test statique du dépôt : 0 échec.

Captures : `accueil-connecte`, `mes-tickets` (et `-page-2`, `-tri`, `-standard`), `suivi-ticket`,
`suivi-ticket-priority`, `suivi-ticket-standard`, `suivi-collaborateur`, `suivi-invite`,
`ouverture-etape-4-priority`, `ouverture-etape-4-standard`, `ouverture-erreur`, `ouverture-sans-js`, et
`sans-plugin-*` (liste, suivi Priority, étape 4). Elles proviennent de ce jeu de données (différent de celui
des autres captures).

Non vérifié : écrans en français (aucun pack de langue ; catalogue du thème contrôlé : 144 utilisations,
toutes traduites), lecteur d'écran, Firefox et Safari, bascule réelle de l'organisation par le plugin Stripe.

## Limites connues

- Changer de sujet d'aide recharge tout `#dynamic-form`, formulaire « Ticket Details » compris : comme dans
  le thème d'origine, le message est retrouvé par le brouillon mais **les pièces jointes déjà déposées sont
  perdues**. Mieux vaut joindre les fichiers après avoir choisi le sujet.
- Sans JavaScript, pas de pièce jointe ni de rechargement des champs au changement de sujet (comportement
  d'osTicket).
- Le champ « message » n'a pas de libellé visible (champ de type bloc, comme dans osTicket) ; ses erreurs
  sont reliées par l'assistant.
- Un même message d'erreur de champ peut apparaître plusieurs fois (validation d'osTicket, vu sur l'adresse
  e-mail) : la liste des erreurs le reprend tel quel.
- La jauge est calculée au chargement de la page (pas de mise à jour en direct) ; elle affiche un dépassement
  (« le premier retour était attendu le… ») : à décider si le client doit le voir.
- Accueil : 6 sujets au plus en cartes (puis « Une autre demande »).
- osTicket marque en rouge le bouton d'envoi d'un formulaire modifié (`js/osticket.js`) : neutralisé (illisible
  sur cyan) ; l'avertissement « quitter la page ? » est conservé. Le message de confirmation après une
  réponse s'affiche une fois en haut de page (le cœur l'affichait aussi près du formulaire) ; l'avertissement
  « ticket fermé, non réouvrable » reste près du formulaire.
- `:has()` sert à l'ordre bandeau / messages (navigateurs 2023+) ; sans lui, le message passe au-dessus du
  bandeau.
- Polices en sous-ensembles latin et latin-ext.
- osTicket déduplique les fichiers joints par contenu : un fichier identique à un fichier déjà connu reprend
  le nom du premier (constaté pendant les tests, comportement du cœur).

## Points à valider

1. **Logo** : PNG horizontal détouré des maquettes, faute de SVG horizontal officiel ; favicon = symbole sur
   carré bleu nuit (composition, exception de taille minimale à 16/32 px).
2. **Couleurs d'état** hors charte : erreur `#B3261E`, succès `#0F6B4B`, alerte `#7A4B00` (extension à valider,
   reprise de la maquette).
3. **Jauge Priority** : nom du plan (`PARCOURS_SLA_PRIORITY`), affichage du dépassement au client.
4. Le libellé des groupes « À vous de jouer » / « Chez Coderise » et des étapes de l'assistant.
5. Les textes Coderise du pied de page (délais, horaires) et les accroches proposées pour les pages
   administrables.
6. Le pack de langue français officiel, puis une relecture des écrans en français.
7. **Niveau de contrat** : afficher le niveau actuel (et non celui de la création) ; pastille absente des
   tickets fermés ; textes d'engagement (« premier retour sous 4 h, 24 h/24, 7 j/7 », « jours ouvrés »)
   et leur absence après le premier retour d'un ticket Priority ; niveau du propriétaire montré à un invité.
