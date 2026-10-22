# Thème « Sommet » du portail client (Coderise)

Mise en œuvre de la proposition 03 des maquettes Coderise (`docs/theme-mockups-coderise/03-sommet/`,
charte Coderise v1.0) : direction éditoriale, en-tête et bandeaux de titre bleu nuit pleine largeur,
grande typographie Montserrat, chevron cyan du symbole posé sur le bas du bandeau d'accueil, filets
francs, listes éditoriales, journal des échanges en deux colonnes, indicateur du délai Priority dans
le bandeau d'un ticket, pastilles « Priority / Standard » du niveau de contrat (si le plugin
`sla_first_response` est actif). Portail **client** uniquement : le panneau agent (`scp/`) n'est pas
touché.

C'est la direction la plus proche du gabarit d'origine d'osTicket : **6 fichiers du cœur seulement**
sont remplacés ; les formulaires, la liste des tickets, la base de connaissances et les pages de
compte gardent leur gabarit d'origine et sont habillés par la feuille de style.

Ce thème est une **alternative** aux thèmes `atelier`, `poste-de-travail` et `parcours`
(`deploy/themes/`) : un seul est installé à la fois. Rien n'est modifié dans le cœur d'osTicket ni
dans `deploy/docker/` : `apply.sh` copie `overlay/` dans `deploy/docker/overlay/`, que l'image Docker
superpose à osTicket au moment de la construction (voir `deploy/docker/README.md`, « Plugins et
personnalisation » et « Personnalisation du thème »).

## Contenu du dossier

| Chemin | Rôle |
|---|---|
| `overlay/` | Arbre qui reflète la racine d'osTicket : ce qui est installé dans l'image |
| `MANIFEST` | Liste exacte des fichiers de `overlay/` (`apply.sh` refuse s'ils ne correspondent pas) |
| `BASE.sha256` | Empreintes SHA-256 des fichiers du cœur **remplacés**, au commit de base |
| `WATCH.sha256` | Empreintes des fichiers du cœur **dont le thème dépend** sans les remplacer |
| `apply.sh`, `remove.sh` | Installation et retrait dans `deploy/docker/overlay/` (idempotents) |
| `check-drift.sh` | Liste les fichiers du cœur qui ont changé depuis le commit de base |
| `captures/` | Captures PNG des pages réelles (instance de test) et rapport d'audit |

## Fichiers du cœur remplacés (6)

Chaque gabarit remplacé garde les mêmes champs, noms de champs, actions de formulaire, jeton CSRF
(`csrf_token()`), contrôles d'accès, signaux, appels `__()` et échappements (`Format::htmlchars`,
`Format::display`…) que l'original ; la logique (requêtes, brouillons, pièces jointes, tickets
fusionnés, images en ligne) est recopiée telle quelle.

| Fichier | Pourquoi il est remplacé |
|---|---|
| `include/client/header.inc.php` | En-tête bleu nuit (logo Coderise, navigation d'osTicket, « Ouvrir un ticket » en bouton d'action, utilisateur, langues en texte, menu mobile `details`), lien d'évitement, feuille du thème à la place de `theme.css`/`print.css`/`flags.css`, classes de page sur `<body>`, **bandeau de titre** (fil d'Ariane, titre, recherche de la base de connaissances, **niveau de contrat** du client sur la liste des tickets). Même prélude PHP, mêmes en-têtes HTTP (CSP), mêmes scripts, mêmes messages `$errors`/`$msg`/`$warn`. |
| `include/client/footer.inc.php` | Pied de page Coderise (logo, signature, contact, délais, pages « Autres ressources », liens) ; copyright et « propulsé par osTicket » d'origine ; mêmes scripts de fin (`#loading`, i18n JS, `getConfig()`). Remplit le bandeau de titre (voir plus bas). |
| `index.php` | Accueil : bandeau héros avec le contenu administrable de la page d'accueil et le grand chevron cyan, raccourcis vers les rubriques d'aide, **encart des délais selon le niveau de contrat**, recherche et articles mis en avant. Mêmes données que l'original (fichier prévu pour être adapté, d'après son en-tête). |
| `include/client/view.inc.php` | Suivi d'un ticket : bandeau (sujet, statut, **pastille du niveau de contrat**, numéro, date, actualiser/imprimer/modifier, **indicateur Priority**), journal des échanges, formulaire de réponse, colonne d'informations (mêmes données que l'original, en `dl`, plus le contrat du demandeur). |
| `include/client/templates/thread-entry.tmpl.php` | Journal en deux colonnes (auteur, rôle « Vous » ou « Équipe Coderise », date ; message) ; **pas d'image d'avatar** (osTicket les prend sur Gravatar par défaut : aucune requête externe). |
| `include/client/templates/thread-event.tmpl.php` | Même balisage ; retire les images d'avatar des événements ; ne montre pas au client les changements de plan de SLA (voir « Changements de plan de SLA masqués au client »). |

Laissés d'origine et habillés par la feuille de style (listés dans `WATCH.sha256`) : ouverture de
ticket (`open.inc.php`, `templates/dynamic-form.tmpl.php` : **jamais surchargé**, il sert aussi au
rechargement AJAX des champs et à quelques gabarits du panneau agent), connexion, accès par e-mail et
numéro, inscription et confirmation, mot de passe oublié (3 pages), profil, modification d'un
ticket, liste des tickets, base de connaissances (accueil, catégorie, article, recherche),
`thread-entries.tmpl.php`, page de remerciement (`open.php`) et page hors ligne (`offline.php`).

### Le bandeau de titre (sans surcharger les pages)

La maquette met le titre de chaque page dans un bandeau bleu nuit. Pour ne pas réécrire les dix
gabarits concernés, l'en-tête imprime un bandeau vide et met la page en mémoire tampon
(`ob_start`) ; le pied de page déplace dans le bandeau le **premier `<h1>`** de la page (ou, pour le
suivi d'un ticket, le bloc balisé `<!--sommet:bandeau-->…<!--/sommet:bandeau-->` de `view.inc.php`).
Le titre n'existe donc qu'une fois dans la page. Une page sans `<h1>` (accueil de la base de
connaissances, remerciement sans titre administrable) reçoit un titre de repli (« Base de
connaissances », « Ticket #… », ou la dernière étape du fil d'Ariane). Si le tampon n'est pas celui
du thème au moment du pied de page (sortie anticipée, tampon d'un autre code), rien n'est déplacé :
le titre reste simplement dans la page. Code : `include/client/sommet/sommet.inc.php`.
`check-drift.sh` surveille les gabarits dont le premier `<h1>` est ainsi déplacé.

### Fichiers ajoutés (aucun équivalent dans le cœur)

- `assets/sommet/css/sommet.css` : toute la feuille (écran et impression).
- `assets/sommet/fonts/` : Montserrat 600/700/800 et Inter 400/500/600, latin et latin-ext, WOFF2,
  copiés de `docs/theme-mockups-coderise/_shared/fonts/`, avec `OFL-montserrat.txt` et `OFL-inter.txt`
  (SIL Open Font License 1.1).
- `assets/sommet/img/logo-coderise-horizontal.png` : logo horizontal détouré des maquettes (faute de
  SVG horizontal officiel, voir `deploy/themes/_shared/coderise/README.md`) ;
  `favicon.svg` = `deploy/themes/_shared/coderise/symbole-sur-bleu-nuit.svg` tel quel ;
  `favicon-32.png`, `favicon-180.png` : favicons des maquettes (repli des navigateurs sans SVG).
- `include/client/sommet/sommet.inc.php` : `__s()` (chaînes du thème), icônes en ligne, motif
  « Sommet », bandeau de titre, données de l'indicateur Priority, niveau de contrat
  (`sommet_contract()`, `sommet_contract_badge()`).
- `include/client/sommet/i18n/fr/LC_MESSAGES/sommet.mo.php` : catalogue français du thème.

Le **motif « Sommet »** (`sommet_peak()`) est le tracé officiel du chevron du symbole
(`symbole-*.svg` partagé) : même chemin, même épaisseur, même onglet, pieds coupés à plat là où le
logo d'origine les coupe ; jamais étiré (rapport fixé par la boîte de vue), couleur pleine cyan à
l'accueil, filigrane blanc à 7 % dans les bandeaux.

Aucune ressource externe au chargement : polices, icônes (SVG en ligne), logo et favicon sont servis
par osTicket. La CSP d'osTicket et de `deploy/docker` n'a pas besoin de changer (aucun script ajouté ;
seuls attributs `style` en ligne : la position de l'indicateur Priority).

## Indicateur « Support Priority : premier retour sous 4 h » (à valider)

Affiché dans le bandeau d'un ticket **uniquement** quand toutes ces conditions sont réunies, avec des
données qu'osTicket stocke réellement :

1. le ticket est ouvert ;
2. son plan de SLA est **actif** et s'appelle `SOMMET_PRIORITY_SLA` (par défaut `Priority 4h`) ;
3. il a une échéance de SLA calculée par osTicket (`est_duedate`, qui suit le calendrier du plan) ;
4. l'échéance n'est pas dépassée (sauf si `SOMMET_SLA_SHOW_OVERDUE` vaut `true`).

Il montre « Reçu » (création ou réouverture), « Objectif » (échéance), le temps restant et la part
du délai écoulée, **calculés au chargement de la page** (pas de compte à rebours en direct). Il
**disparaît seul à la première réponse** : le plugin `contrib/sla-first-response` bascule alors le
ticket sur un plan désactivé (condition 2 fausse). Testé de bout en bout (voir « Vérifications »).

Réglage, sans toucher au thème : définir les constantes avant le chargement du thème, par exemple
dans `ost-config.php` (volume de configuration) :

```php
define('SOMMET_PRIORITY_SLA', 'Priority 4h');   // nom exact du plan surveillé par le plugin
define('SOMMET_SLA_SHOW_OVERDUE', false);       // afficher l'indicateur une fois l'échéance passée ?
```

À valider : le nom du plan ; montrer ou non un dépassement au client (masqué par défaut) ; le fait
que le calcul suit le calendrier du plan (24 h/24 pour Priority) et du département (voir le README du
plugin, « le calendrier du département l'emporte »). Le thème ne montre pas « premier retour apporté
en 44 min » (maquette) : une fois la réponse faite, l'indicateur n'a plus lieu d'être.

L'indicateur ne dépend **pas** du niveau de contrat ci-dessous : il suit le plan de SLA du ticket
(posé à la création), le niveau suit l'organisation actuelle du client.

## Niveau de contrat « Priority / Standard » (dépendance facultative)

Le niveau vient de la fonction `coderise_contract_level()` du plugin `contrib/sla-first-response`
(version 0.3 et plus, README du plugin, sections 9 et 10) : `priority` si le client est membre de
l'organisation « Priority » configurée dans le plugin (celle qu'alimente le plugin `stripe-sync`),
sinon `standard`. Le thème ne lit rien d'autre.

| Où | Ce qui est affiché | Sujet passé à la fonction |
|---|---|---|
| Suivi d'un ticket, bandeau | Pastille à côté du statut (« Contrat de support : » pour les lecteurs d'écran) | Le ticket : niveau de son **demandeur** (propriétaire), jamais d'un collaborateur |
| Suivi d'un ticket, colonne d'informations | « Contrat de support (actuel) » dans « Informations sur l'utilisateur » | Idem (même calcul, une fois par page) |
| Liste des tickets, bandeau | « Votre contrat de support : » + pastille, sous les onglets Ouverts / Fermés | `null` : le client connecté |
| Accueil, encart des délais | **Priority** : « Votre support Priority : un premier retour sous 4 heures, jour et nuit » ; **Standard** : « nous vous répondons les jours ouvrés », « sans délai de réponse garanti », sans promesse 24/7 ; **sinon** (visiteur non connecté, invité, niveau inconnu, plugin absent) : présentation neutre des deux offres (« Support Priority (sur abonnement) : … ») qui ne dit pas que le lecteur y a droit | `null` : le client connecté (jamais appelée pour un visiteur ou un invité) |

Pastilles (charte v1.0, maquette 03) : sur fond clair, Priority = aplat bleu nuit, texte blanc,
éclair cyan ; Standard = filet et texte ardoise. Dans le bandeau bleu nuit, Priority = aplat cyan,
texte bleu nuit (jamais de blanc sur cyan) ; Standard = filet et texte blancs. Impression : noir sur
blanc, filet noir.

**Repli** (`sommet_contract()` dans `include/client/sommet/sommet.inc.php`) :

- la fonction n'existe pas (plugin absent de l'image, plugin ou instance désactivés, mise à jour
  d'osTicket en attente) : **aucun niveau** nulle part, encart neutre à l'accueil, aucune erreur ;
- la fonction répond avec la source `default` (organisation Priority non configurée ou supprimée,
  invité pour `null`, erreur) : même repli, rien n'est affiché (le niveau ne serait qu'une valeur par
  défaut) ; seule la source `organisation` est affichée ;
- toute exception est absorbée (la fonction n'en lève pas, le thème garde un `try` par prudence) ;
- le libellé (saisi par l'administrateur dans le plugin) est toujours échappé (`Format::htmlchars`).

**Limites.**

- C'est le niveau **actuel** du demandeur, pas celui du moment où le ticket a été ouvert : un ancien
  ticket d'un client devenu Priority porte la pastille Priority (et l'inverse après la fin de
  l'abonnement). D'où « (actuel) » dans la colonne d'informations. Le délai réellement appliqué à un
  ticket reste celui de son plan de SLA (indicateur ci-dessus).
- Ticket d'un collègue vu en collaborateur : la pastille est celle du demandeur (par exemple
  Standard pour une cliente Priority collaboratrice d'un ticket Standard).
- Invité (lien d'accès reçu par e-mail) : le suivi montre le niveau du demandeur du ticket ;
  l'accueil reste neutre.
- **Pas de pastille par ligne dans la liste des tickets**, volontairement, et donc pas de surcharge
  de `tickets.inc.php` (il resterait le seul gabarit de liste à maintenir à chaque mise à jour) : le
  niveau étant celui **actuel** du demandeur, il est identique sur toutes les lignes des propres
  tickets du client (redondant, et trompeur sur les anciens tickets) ; les tickets de l'organisation
  ont des demandeurs de la même organisation, donc le même niveau ; seuls les tickets où le client
  est collaborateur d'un demandeur d'une autre organisation diffèrent, et leur suivi l'affiche. Le
  niveau du client est donc affiché **une fois** dans le bandeau de la liste.
- Coût mesuré (journal général de MariaDB, page HTML seule, client Priority, 3 essais) : plugin
  actif contre plugin désactivé ou absent, +3 à +5 requêtes par page (amorçage et configuration du
  plugin, lecture de l'organisation Priority ; organisation du client servie par le cache), **identique
  pour la page 1 (25 lignes) et la page 2 de la liste de 42 tickets** : le coût ne dépend pas du
  nombre de lignes. Détail : `captures/audit-niveau-contrat.json`.
- Le pied de page présente toujours les deux offres (« Délais de réponse ») : texte de présentation,
  identique pour tous, sans dire au lecteur qu'il y a droit.

## Changements de plan de SLA masqués au client

`templates/thread-event.tmpl.php` ne montre pas au client :

- les événements `edited` qui ne portent **que** `sla_id` : changement de plan fait **à la main** par
  un agent (édition du ticket, champ « SLA Plan »), et bascules écrites par les versions 0.1 et 0.2 du
  plugin (les noms de plans sont internes) ; un événement `edited` qui porte aussi d'autres champs
  reste affiché, comme dans le gabarit d'origine ;
- les événements `sla_first_response` (plugin 0.3 et plus) : le portail d'origine les écarte déjà
  (liste blanche de types dans `thread-entries.tmpl.php`) et le plugin leur donne une description
  vide côté client ; le thème les écarte aussi explicitement, au cas où l'un des deux changerait
  (plugin désactivé : description générique « sla_first_response by … »).

Le panneau agent les montre tous. Vérifié sur l'instance de test (voir « Vérifications »).

## Chaînes et langues

- Toutes les chaînes déjà présentes dans osTicket passent par `__()` et sont traduites par les packs
  de langue officiels (le pack français `fr.phar` se place dans `deploy/docker/overlay/include/i18n/`,
  voir `deploy/docker/overlay/README.md`).
- Les 38 chaînes propres au thème passent par `__s()`, c'est-à-dire `_dgettext('sommet', …)`, la couche
  gettext d'osTicket avec un domaine dédié (même mécanisme que les autres thèmes). Le catalogue
  français est fourni (`include/client/sommet/i18n/fr/LC_MESSAGES/sommet.mo.php`) ; il ne s'affiche que
  lorsque le français est une langue active du portail (donc avec le pack officiel). Pour une autre
  langue : copier le fichier dans `i18n/<code>/LC_MESSAGES/` et traduire les valeurs. Le catalogue
  n'est pas sous `include/i18n/` exprès : un dossier à cet endroit ferait apparaître une langue de plus.
- Les accroches de l'accueil, de la page de remerciement et de la page hors ligne sont du **contenu
  administrable** (Admin > Gérer > Pages), traduisible par langue. Contenu proposé ci-dessous.

## Installation

Depuis la racine du dépôt :

```sh
deploy/themes/sommet/check-drift.sh             # le thème correspond-il à la version d'osTicket ?
deploy/themes/sommet/apply.sh --dry-run         # ce qui va être copié
deploy/themes/sommet/apply.sh                   # copie dans deploy/docker/overlay/ + manifeste
git add -A deploy/docker/overlay && git commit -m "overlay: thème Sommet"
deploy/docker/scripts/build.sh                  # l'image est construite depuis le commit
# puis déploiement comme une mise à jour (deploy/docker/README.md, « Mises à jour »)
```

`apply.sh` refuse, sans rien copier : si un autre thème est installé (manifeste
`include/client/<atelier|poste-de-travail|parcours>/installed.manifest`, ou fichiers identiques à
ceux d'un autre thème de `deploy/themes/`) ; s'il écraserait un fichier de l'overlay qui n'appartient
pas au thème ; si un fichier du thème a été modifié à la main dans l'overlay (`--force` l'écrase) ; si
`MANIFEST` ne correspond pas à `overlay/`. Il note dans
`deploy/docker/overlay/include/client/sommet/installed.manifest` (non servi par Apache) chaque fichier
installé avec son empreinte, ne touche jamais aux autres fichiers de l'overlay (plugins, packs de
langue), et retire les fichiers d'une version précédente du thème. Un autre dossier d'overlay peut être
donné en argument (`apply.sh /chemin/vers/overlay`).

Réglages conseillés dans l'administration (une fois) :

1. **Pages** (Admin > Gérer > Pages), contenu proposé :
   - Accueil (« Landing ») — le `<h1>` devient le grand titre du héros, le paragraphe son chapeau :
     ```html
     <h1>Faisons monter votre projet d’un niveau.</h1>
     <p>Un incident, une question, une évolution : écrivez-nous. Une personne de l’équipe vous répond, avec une vraie réponse.</p>
     ```
   - Remerciement (« Thank You ») — le `<h1>` passe dans le bandeau :
     ```html
     <h1>Merci, %{ticket.name.first}.</h1>
     <p>Votre ticket <strong>#%{ticket.number}</strong> est enregistré. Nous vous répondons ici et par e-mail.</p>
     ```
   - Hors ligne (« Offline ») : `<h1>Le support est en maintenance.</h1><p>Nous revenons très vite.</p>`
   - Pages « Autres ressources » (type *Other*) : affichées dans le pied de page (politique de support,
     conditions…).
2. **Rubriques d'aide** publiques : elles deviennent les raccourcis « Que voulez-vous nous confier ? »
   de l'accueil (lien vers le formulaire avec la rubrique choisie).
3. **Base de connaissances** : activer, mettre en avant (« Featured ») les articles et catégories à
   montrer sur l'accueil.
4. **Logo** : ne rien téléverser pour garder le logo Coderise du thème (un logo téléversé pour le
   portail le remplace).
5. **Nom de l'entreprise** (Admin > Gérer > Formulaires > Company Information) : affiché dans le
   copyright (sinon le titre du centre d'aide).
6. Plans de SLA et plugin `sla_first_response` : voir « Indicateur Priority ». Pour les pastilles
   et l'encart de l'accueil : plugin en version 0.3 ou plus, actif, instance active avec le champ
   « Organisation Priority » renseigné (sans ce champ, aucun niveau n'est affiché).

## Désinstallation, bascule vers un autre thème

```sh
deploy/themes/sommet/remove.sh --dry-run
deploy/themes/sommet/remove.sh                  # retire les 27 fichiers du thème, et eux seuls
deploy/themes/<autre>/apply.sh                  # si l'on bascule vers un autre thème
git add -A deploy/docker/overlay && git commit -m "overlay: thème <autre>" && deploy/docker/scripts/build.sh
```

`remove.sh` ne retire que les fichiers listés dans le manifeste et **non modifiés** depuis
l'installation (les autres sont signalés et gardés, avec le manifeste, code de sortie 1 ; `--force`
les retire). Les dossiers devenus vides sont retirés, jamais l'overlay lui-même ; plugins et packs de
langue ne sont jamais touchés. Sans thème, le portail retrouve le thème d'origine d'osTicket ; aucune
donnée n'est modifiée en base.

## Mise à jour d'osTicket

Un gabarit remplacé ne suit plus les correctifs du gabarit d'origine. À chaque mise à jour
d'osTicket, avant de reconstruire l'image :

1. `deploy/themes/sommet/check-drift.sh` (compare au commit courant ; `--rev <tag>` pour un autre
   commit ; ou un chemin vers une arborescence osTicket extraite ; les fins de ligne CRLF sont
   ignorées). Il liste :
   - les **fichiers remplacés** qui ont changé (`BASE.sha256`) : reporter le changement dans
     `overlay/` (`git diff <commit de base> <révision> -- <fichier>` ; correctifs de sécurité
     d'abord : échappement, CSRF, contrôles d'accès) ;
   - les **fichiers dont le thème dépend** (`WATCH.sha256`) : revoir les pages concernées (le
     premier `<h1>` est-il toujours le titre ? le balisage habillé a-t-il bougé ?).
2. Quand c'est fait : `check-drift.sh --write-base [<révision>]`, committer, `apply.sh`, committer,
   reconstruire.

Code de sortie : 0 sans dérive, 1 avec dérive, 2 erreur d'usage. Commit de base des empreintes
actuelles : `2c9292ef` (branche `develop`, osTicket 1.18.x).

## Écarts assumés avec la maquette 03

| Maquette | Implémentation |
|---|---|
| Libellés en français | Le portail est en anglais tant que le pack de langue français officiel n'est pas installé (les captures sont donc en anglais ; les chaînes du thème ont leur traduction française). |
| Trois intentions rédigées (incident, question, évolution) avec texte | Les **rubriques d'aide publiques** réelles, en liste éditoriale, chacune vers le formulaire pré-rempli. Pas de texte descriptif (osTicket n'en a pas de public pour une rubrique). |
| « À lire avant d'écrire » avec temps de lecture | « Questions à la une » (FAQ mises en avant) avec leur catégorie, puis les catégories mises en avant avec leurs articles. **Pas de temps de lecture** (donnée absente). |
| Altimètre « apporté en 44 min : objectif tenu » | Indicateur affiché seulement **en attente** du premier retour (voir plus haut) ; disparaît à la première réponse. |
| Pastilles « Priority / Standard » sur la liste et le ticket | Sur le ticket (bandeau et colonne), niveau **actuel** du demandeur, avec le plugin `sla_first_response` seulement ; sur la liste, **une** pastille (le niveau du client) dans le bandeau au lieu d'une par ligne (voir « Niveau de contrat »). |
| Priorité « Urgente » sur la liste et le ticket | Non affichée : la priorité n'est pas montrée par le gabarit d'origine. |
| Encart « Support Priority : sous 4 heures » de l'accueil, identique pour tous | Adapté au niveau du client connecté ; présentation neutre des deux offres pour un visiteur, un invité ou sans niveau connu. |
| Liste « Mes tickets » en lignes éditoriales (gros numéros, statut, contrat, dernière réponse) | Tableau d'origine habillé à la charte (en-têtes bleu nuit, lignes alternées gris clair, gros numéros), cartes empilées sur mobile ; onglets Ouverts / Fermés et compteurs réels dans le bandeau ; tri, recherche, filtre par rubrique et pagination d'origine. Pas de « réponse de Nora, il y a 3 h » (non exposé au client). |
| État vide « Aucun ticket ouvert. Tout roule. » | osTicket affiche le formulaire d'ouverture à un client sans ticket (capture `tickets-vide`) et « Your query did not match any records » pour un filtre vide. |
| Connexion en deux panneaux (accroche sombre et chevron / formulaire + « Sans compte ») | Bandeau de titre puis le gabarit d'origine : formulaire, encadré « Pas encore inscrit / Je suis un agent ». L'accès par e-mail et numéro reste sur sa page (« Check Ticket Status »). |
| Formulaire d'ouverture avec colonne « Avant d'écrire » et récapitulatif des erreurs | Gabarit d'origine en une colonne (champs dynamiques en deux colonnes quand c'est possible) ; le formulaire de la rubrique est en encadré voile cyan à filet cyan ; message d'erreur global d'osTicket au-dessus. |
| Fiche d'ouverture : « Votre adresse e-mail » en lecture seule | Lignes « Email » / « Client » d'osTicket. |
| Article : sommaire « Sur cette page », « Cet article vous a aidé ? » | Non faits (pas de données ni de mécanisme dans osTicket) ; la recherche passe dans le bandeau. |
| Titre de l'article dans le bandeau | Le bandeau porte le titre du gabarit (« Frequently Asked Question ») ; la question est le grand titre de la page. Le fil d'Ariane du bandeau remplace celui du gabarit. |
| Bloc « Un point rapide ? » sur le suivi | Repris avec le numéro de téléphone de l'entreprise (à valider : ce numéro est-il celui à appeler pour un ticket ?). |
| Pied de page : LinkedIn, GitHub, coderise.fr | Repris (adresses relevées sur coderise.fr) ; plus les horaires et les pages « Autres ressources ». |
| Rôle « Équipe Coderise » | Réponses de l'équipe : « Coderise team » / « Équipe Coderise » (chaîne du thème) ; le nom de l'agent est celui d'osTicket (« Staff » si l'option « masquer le nom des agents » est active). |
| Changement de plan de SLA visible dans le fil | Masqué au client (voir « Changements de plan de SLA masqués au client »). |

## Vérifications (22/10/2026)

Instance de test construite comme en production : `git archive` du commit, `apply.sh` sur l'overlay
exporté (avec le plugin `sla_first_response`, non committé), `Dockerfile` cibles `install` puis
`runtime`, pile `compose.yaml` durcie (racine en lecture seule, `cap_drop ALL`, `www-data`,
configuration en lecture seule), MariaDB 11.4, **installation par l'assistant** (HTTP), noms de
projet, d'images et de volumes propres au test, port local libre. Données : 4 clients dont une
organisation, 40 tickets pour la cliente principale (ouverts, fermés, réponses d'agent, pièces jointes,
un collaborateur, ticket d'un collègue, 30 ouverts pour la pagination à 25), un ticket d'un autre
client, formulaire « Contexte technique » (listes, texte, date, case) sur la rubrique « Incident en
production », 9 articles en 5 catégories, plans de SLA « Priority 4h » (24/7) et « réponse faite »
(désactivé), plugin installé et actif. Navigateur : Chromium privé (`playwright-core` hors dépôt).

Résultats détaillés : `captures/audit-report.json` ; voir la liste des captures dans `captures/`.

- **Parcours réels** (tous passés) : ouverture d'un ticket par un visiteur (champs rechargés en AJAX au
  changement de rubrique, 6 erreurs de validation affichées, pièce jointe déposée puis enregistrée, page
  de remerciement) ; accès par e-mail + numéro (erreur, puis « lien envoyé ») ; inscription puis
  confirmation par le lien (jeton lu en base, pas de SMTP) ; mot de passe oublié ; connexion (erreur
  puis succès) ; liste (ouverts, fermés, page 2, recherche) ; suivi (fil, pièces jointes, ticket fermé,
  ticket d'un collègue vu en collaborateur, modification) ; réponse avec pièce jointe (message de
  succès, pièce dans le fil) ; profil ; base de connaissances (accueil, catégorie, article, recherche,
  recherche vide) ; ticket inconnu (erreur) ; page hors ligne ; impression du suivi.
- **Indicateur Priority** : affiché sur le ticket en attente (plan « Priority 4h » actif) ; après une
  réponse d'agent adressée au client, le plugin bascule le ticket sur le plan désactivé et
  l'indicateur disparaît ; l'événement « SLA changé » n'apparaît pas dans le fil du client.
- **Console du navigateur** : aucune erreur ni avertissement ; **aucune réponse HTTP ≥ 400** ; **aucune
  requête vers un autre hôte** (avatars activés exprès, source Gravatar par défaut) ; journaux
  Apache/PHP : aucune erreur PHP (seul message : `sendmail: not found`, attendu sans messagerie).
- **Débordement horizontal** : aucun à 390, 820 et 1280 px (63 rendus audités, plus 5 pages de
  parcours capturées à 390 px : erreurs et remerciement de l'ouverture, accès refusé, mot de passe
  envoyé, inscription envoyée ; menu mobile ouvert).
- **Captures** (`captures/`) : `<page>-bureau.png` (1280 px), `<page>-mobile.png` (390 px),
  `accueil-tablette.png` et `suivi-tablette.png` (820 px), `impression-suivi.png` (média print),
  `clavier-*.png` (focus), `menu-mobile-ouvert.png`, `suivi-priority-*.png` (indicateur, puis après la
  première réponse) ; prises sur l'image construite depuis le commit, après installation par
  l'assistant. Libellés en anglais (pas de pack de langue).
- **Contrastes** (WCAG, chaque texte visible, fonds composés) : **minimum 4,91:1**, aucun texte sous le
  seuil sur les 63 rendus.
- **Clavier** : lien d'évitement en premier ; contour de focus d'au moins 2 px (3 px : cyan profond sur
  clair, cyan sur bleu nuit) sur chacun des 25 premiers éléments atteints à l'accueil.
- **Scripts** (21 contrôles passés) : `apply.sh`/`remove.sh` aller-retour sur `deploy/docker/overlay/`,
  `git status` propre ensuite ; idempotence ; refus si `atelier`, `poste-de-travail` ou `parcours` est
  installé (manifeste) et si les fichiers de `poste-de-travail` (installé par son script actuel) sont
  présents ; refus d'un fichier étranger et d'un fichier du thème modifié ; plugin présent conservé ;
  `MANIFEST` désynchronisé refusé. `check-drift.sh` : aucune dérive au commit ; sur une copie
  modifiée : modifié et supprimé détectés, CRLF ignoré (code 1) ; `--rev origin/1.15.x` : 2 fichiers
  remplacés et 8 fichiers surveillés signalés.
- **Apache** : les fichiers PHP du thème (`include/client/sommet/*.php`, catalogue, gabarits) et le
  manifeste répondent **403** en accès direct ; feuille, polices et images répondent 200.
- **Français** : aucun pack officiel hors ligne, rien n'a été téléchargé. Vérifié : les 29 chaînes
  `__s()` du thème ont une traduction dans le catalogue, chargé par la couche gettext d'osTicket
  (`TextDomain`) dans le conteneur ; toutes les autres chaînes passent par `__()`. **Non vérifié** :
  le rendu du portail en français (mises en page avec les libellés français réels).
- **Non vérifié** : envoi réel des e-mails, lecteur d'écran, Firefox et Safari, connexion par
  fournisseur externe (gabarit conservé), CAPTCHA (gabarit conservé, non activé), RTL.

### Niveau de contrat (22/10/2026, seconde instance)

Instance construite de la même façon depuis le commit du thème (`git archive`, `apply.sh` sur
l'overlay exporté, plugin `sla_first_response` 0.3 copié dans `include/plugins/`, non committé),
cibles `install` puis `runtime`, installation par l'assistant (HTTP), MariaDB 11.4. Données : plans
« Priority 4h » (actif, 24/7) et « réponse faite » (désactivé) ; plugin installé, actif, instance
réglée sur l'organisation « Priority » ; clients : Léa (organisation Priority, 42 tickets dont 30+
ouverts, réponses d'agent, 10 fermés), Sam (organisation Acme, standard), Nina (sans organisation),
Marc (Acme ; Léa collaboratrice de son ticket), Gina (organisation Priority, sans compte : accès
invité par le lien du ticket). Navigateur : Chromium privé (`playwright-core` hors dépôt), 1280 et
390 px. Résultats : `captures/audit-niveau-contrat.json` (58 rendus).

- **Plugin actif** : accueil visiteur et invité **neutres** (aucune pastille) ; Léa : encart
  Priority, pastille Priority dans le bandeau de la liste, du suivi et la colonne ; Sam et Nina :
  encart Standard (aucune mention de 4 h, 24 h/24 ni « jour et nuit », vérifié sur le texte), pastilles
  Standard ; ticket de Marc vu par Léa en collaboratrice : **Standard** (niveau du demandeur) ;
  invité : pastille du demandeur (Priority) au suivi, accueil neutre. Indicateur « premier retour
  sous 4 h » toujours affiché sur le ticket en attente, absent après la première réponse.
- **Plugin désactivé** (en base) puis **absent** (image reconstruite sans le dossier du plugin,
  toujours « installé » en base) : aucune pastille, aucune ligne de contrat, encart neutre, aucune
  erreur ; l'indicateur Priority reste affiché sur le ticket en attente (il ne dépend que du plan).
- **Événements** : bascule du plugin à la première réponse (`sla_first_response`) et changement de
  plan fait à la main par un agent dans le panneau agent (édition du champ « SLA Plan » : événement
  `edited` `{"sla_id":[1,2]}`, visible côté agent) : **aucun** n'apparaît dans le fil du client,
  plugin actif, désactivé ou absent.
- **Console** : aucune erreur ni avertissement ; aucune réponse HTTP ≥ 400 ; aucune requête vers un
  autre hôte ; aucune erreur PHP dans les pages ni dans les journaux du conteneur.
- **Débordement horizontal** : aucun à 390 et 1280 px.
- **Contrastes** : minimum 4,91:1 sur les 58 rendus ; pastilles : Priority 9,52:1 au minimum (bleu
  nuit sur cyan dans le bandeau, blanc sur bleu nuit ailleurs), Standard 4,91:1 au minimum (ardoise
  sur gris de l'encart).
- **Requêtes SQL** : voir « Niveau de contrat », Limites.
- **Scripts** : `check-drift.sh` sans dérive ; `apply.sh` / `remove.sh` aller-retour sur
  `deploy/docker/overlay/` (27 fichiers, idempotent, `git status` propre ensuite). Suite statique du
  dépôt (`php setup/test/run-tests.php`, php:8.3-cli) : 0 échec. Catalogue français : les 38 chaînes
  `__s()` traduites, aucune inutilisée.
- **Captures** remplacées ou ajoutées (`-bureau` 1280 px, `-mobile` 390 px) : `accueil`,
  `accueil-connecte` (Priority), `accueil-standard`, `accueil-sans-organisation`, `tickets`,
  `tickets-standard`, `suivi`, `suivi-priority`, `suivi-priority-apres-reponse`,
  `suivi-changement-plan-agent`, `suivi-collaborateur`, `suivi-standard`, `suivi-sans-organisation`,
  `suivi-invite`, `accueil-connecte-sans-plugin`, `tickets-sans-plugin`, `suivi-priority-sans-plugin`.
  Les autres captures (tablette, impression, ticket fermé, page 2…) datent de la première recette,
  sans niveau de contrat configuré : elles ne montrent pas les pastilles.
- **Non vérifié** : rendu en français réel (pas de pack de langue), niveau qui change en cours
  d'abonnement Stripe (le plugin `stripe-sync` n'était pas installé : l'appartenance à
  l'organisation a été posée directement), lecteur d'écran (le préfixe « Contrat de support : » est
  en texte masqué visuellement).

## Limites connues

- Le titre déplacé dans le bandeau dépend du premier `<h1>` de chaque gabarit d'origine (surveillé par
  `check-drift.sh`). Une page qui n'a pas de `<h1>` reçoit un titre de repli.
- La page de suivi affiche deux fois un message d'erreur ou de succès (en haut et au-dessus du
  formulaire de réponse) : comportement du gabarit d'origine, conservé.
- `:has()` sert aux champs en erreur et à la mise en deux colonnes des champs du formulaire
  (navigateurs de 2023 et plus) ; sans lui, les champs restent en une colonne.
- osticket.js colore en rouge le texte du bouton d'envoi quand le formulaire change (illisible sur
  cyan) : le texte reste bleu nuit ; l'avertissement « quitter la page ? » est conservé.
- Les champs de connexion d'origine n'ont qu'un texte indicatif (pas de libellé visible).
- Le PDF d'impression d'osTicket (`tickets.php?a=print`) garde sa mise en page ; seule l'impression du
  navigateur est habillée (`captures/impression-suivi.png`).
- Mode clair uniquement (la charte n'a pas de mode sombre).

## Points à valider

1. **Logo** : PNG horizontal détouré des maquettes, faute de SVG horizontal officiel (hauteur 28 px,
   soit 166 px de large ; 32 px au pied de page). Favicon : symbole officiel sur carré bleu nuit.
2. **Couleurs d'état** (hors charte, reprises des maquettes) : erreur `#B3261E` sur `#FCECEA`, succès
   `#0F6B4B` sur `#E4F5EC`, alerte `#7A4B00` sur `#FFF3D1`.
3. **Indicateur Priority** : nom du plan, affichage d'un dépassement, calendrier (voir plus haut).
4. Les textes proposés pour l'accueil, le remerciement et la page hors ligne (contenu administrable),
   et les chaînes du thème (encart des délais, « Un point rapide ? » avec le numéro de l'entreprise).
5. Liens sur fonds teintés en bleu nuit soulignés de cyan profond (écart de la charte relevé dans le
   README des maquettes).
6. Le pack de langue français officiel à ajouter à l'overlay, puis une relecture du portail en français.
7. Police de code : pile système (`ui-monospace`, Consolas), la charte n'en prévoit pas.
8. **Niveau de contrat** : textes de l'encart de l'accueil (« sur abonnement », « sans délai de
   réponse garanti », « écrivez-nous à toute heure ») ; afficher le niveau **actuel** sur les anciens
   tickets (mention « (actuel) ») ; montrer le niveau du demandeur à un collaborateur ou à un invité ;
   une seule pastille dans le bandeau de la liste plutôt qu'une par ligne.
