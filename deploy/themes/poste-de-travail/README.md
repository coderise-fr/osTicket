# Thème « Poste de travail » du portail client (Ownalytics)

Mise en œuvre de la proposition 04 des maquettes (`docs/theme-mockups/04-poste-de-travail/`, issue n° 4) :
portail dense, barre haute prune, rail latéral de navigation, tableaux serrés, panneau de propriétés
collant à côté du fil d'un ticket. Seul le **portail client** est concerné ; le panneau agent (`scp/`) n'est
pas touché.

Ce thème est une **alternative** au thème « Atelier » (`deploy/themes/atelier/`) : un seul des deux est
installé à la fois. Rien n'est modifié dans le cœur d'osTicket : le thème vit dans `overlay/`, copié dans
`deploy/docker/overlay/` par `apply.sh`, puis superposé à osTicket au moment de la construction de l'image
(voir `deploy/docker/README.md`, « Plugins et personnalisation » et « Personnalisation du thème »).

## Contenu

| Chemin | Rôle |
|---|---|
| `overlay/` | Arbre qui reflète la racine d'osTicket : ce qui est installé dans l'image |
| `MANIFEST` | Liste exacte des fichiers de `overlay/` (installés et retirés par les scripts) |
| `BASE.sha256` | Empreintes SHA-256 des fichiers du cœur surchargés, au commit de base |
| `apply.sh`, `remove.sh` | Installation et retrait dans `deploy/docker/overlay/` (idempotents) |
| `check-drift.sh` | Liste les fichiers du cœur surchargés qui ont changé depuis le commit de base |
| `captures/` | Captures PNG des pages réelles (instance de test), bureau 1280 px et mobile 390 px |

### Fichiers du cœur surchargés (10)

Un fichier de l'overlay **remplace** celui du cœur : chacun est à revoir à chaque mise à jour d'osTicket
(`check-drift.sh` dit lesquels ont changé).

| Fichier | Pourquoi il est surchargé |
|---|---|
| `include/client/header.inc.php` | Barre haute (logo, recherche, utilisateur, langues) et rail latéral ; ne charge plus `assets/default/css/theme.css`, `print.css` ni `flags.css`, charge la feuille du thème. Même prélude PHP, mêmes en-têtes (CSP), mêmes scripts, mêmes messages. |
| `include/client/footer.inc.php` | Ferme la nouvelle structure ; mêmes scripts (`getConfig()`, i18n JS), même `#loading`. |
| `index.php` | Accueil en tableau de bord (fichier prévu pour être adapté, d'après son en-tête). |
| `include/client/tickets.inc.php` | Liste « mes tickets » : filtres segmentés, tri, pagination, cartes sur mobile. Logique PHP copiée telle quelle. |
| `include/client/view.inc.php` | Suivi d'un ticket : fil + panneau de propriétés (infos, champs personnalisés, pièces jointes, coordonnées). |
| `include/client/templates/thread-entry.tmpl.php` | Message du fil : initiales au lieu des avatars (pas de Gravatar externe), étiquette « Agent ». |
| `include/client/open.inc.php` | Ouverture de ticket en panneau avec colonne « Questions fréquentes ». Garde le tableau et `#dynamic-form`. |
| `include/client/login.inc.php` | Connexion en deux panneaux (compte, suivi d'un ticket sans compte). |
| `include/client/accesslink.inc.php` | Accès à un ticket par e-mail et numéro. |
| `include/client/kb-categories.inc.php` | Catégories de la base de connaissances en tableau, avec recherche. |

Laissés tels quels, habillés par la feuille de style seulement : `register.inc.php`, `profile.inc.php`,
`edit.inc.php`, `pwreset.*.php`, `register.confirm*.inc.php`, `kb-search.inc.php`, `faq-category.inc.php`,
`faq.inc.php`, `knowledgebase.inc.php`, `templates/dynamic-form.tmpl.php` (partagé par l'ouverture,
l'inscription, le profil, la modification et le rechargement AJAX des champs ; `Form::render()` le prend aussi
par défaut quand un appel ne précise pas `staff`, ce que font quelques gabarits du panneau agent : ne pas le
surcharger), `templates/thread-entries.tmpl.php`,
`templates/thread-event.tmpl.php`, la page de remerciement (`open.php`) et la page hors ligne (`offline.php`).

### Fichiers ajoutés (aucun équivalent dans le cœur)

- `assets/poste-de-travail/css/theme.css` : toute la feuille (écran et impression).
- `assets/poste-de-travail/fonts/` : Archivo (variable) et IBM Plex Mono 400/500, sous-ensemble latin, WOFF2,
  copiés de `docs/theme-mockups/_shared/fonts/`, avec `OFL.txt` (SIL Open Font License 1.1).
- `assets/poste-de-travail/img/favicon.svg`, `assets/poste-de-travail/js/theme.js` (raccourci `/` vers la recherche).
- `include/client/poste-de-travail/theme.php` (fonctions `ptdt__()`, `ptdt_icon()`, `ptdt_initials()`, `ptdt_asset()`)
  et `include/client/poste-de-travail/i18n/fr.php` (voir « Traductions »).

Aucune ressource externe au chargement : polices, icônes (SVG en ligne) et script sont servis par osTicket.
La CSP envoyée par osTicket (`script-src 'self' 'unsafe-inline'`) suffit ; rien à changer dans `deploy/docker/`.

## Installation

Depuis la racine du dépôt :

```sh
deploy/themes/poste-de-travail/apply.sh -n        # vérification seule
deploy/themes/poste-de-travail/apply.sh           # copie dans deploy/docker/overlay/
git add deploy/docker/overlay
git commit -m "overlay: thème poste-de-travail"
deploy/docker/scripts/build.sh                    # l'image est construite depuis HEAD
# puis déploiement comme une mise à jour (deploy/docker/README.md, « Mises à jour »)
```

`apply.sh` refuse (sans rien copier) si l'autre thème est installé, si un fichier qu'il écrirait existe déjà
avec un autre contenu (personnalisation locale), ou si `MANIFEST` ne correspond pas à `overlay/`. Il ne
touche jamais aux autres fichiers de l'overlay (plugins, packs de langue). Un chemin d'overlay peut être
donné en argument (`apply.sh /chemin/vers/overlay`).

Dans l'administration, rien n'est obligatoire. Recommandé : un **titre du centre d'aide** court
(Paramètres > Système, il s'affiche à côté du pictogramme), le **nom de l'entreprise** (pied de page), les
**pages d'accueil, de remerciement et hors ligne** (Gérer > Pages) et les pages « Autres ressources »
(type *Other*, affichées dans le rail : politique de support, conditions…). Un logo téléversé pour le
portail client (Paramètres > Système > Logos) remplace le pictogramme et le titre.

## Désinstallation, bascule vers « Atelier »

```sh
deploy/themes/poste-de-travail/remove.sh          # retire les 19 fichiers du thème, et eux seuls
deploy/themes/atelier/apply.sh                    # si l'on bascule vers l'autre thème
git add -A deploy/docker/overlay && git commit -m "overlay: thème atelier" && deploy/docker/scripts/build.sh
```

`remove.sh` ne supprime un fichier que s'il est identique à la copie du thème ; un fichier modifié depuis
l'installation est conservé et signalé (code de sortie 1) ; `remove.sh -f` le supprime quand même. Les
dossiers devenus vides sont retirés, jamais `deploy/docker/overlay/` lui-même. Sans thème, osTicket reprend
son apparence d'origine (`assets/default/css/theme.css`).

## Mise à jour d'osTicket

1. Avant de fusionner la nouvelle version : `deploy/themes/poste-de-travail/check-drift.sh --rev <révision>`
   (par exemple `upstream/develop` ou l'étiquette de la release) ; après la fusion :
   `deploy/themes/poste-de-travail/check-drift.sh` (arbre courant ; les fins de ligne CRLF de Windows sont
   ramenées à LF avant le calcul).
2. Pour chaque fichier `CHANGED` : `git diff <commit de base> <nouvelle révision> -- <fichier>` (le commit de
   base est en tête de `BASE.sha256`), reporter les changements dans la copie de `overlay/` (corrections de
   sécurité en priorité : échappement, CSRF, contrôles d'accès). Un fichier `MISSING` a été renommé ou supprimé
   en amont : adapter le thème.
3. Régénérer `BASE.sha256` pour le nouveau commit de base (pour chaque fichier listé :
   `git show <commit>:<fichier> | sha256sum`, en gardant le format `empreinte␣␣chemin` et la ligne
   `# base commit:`), tester, puis `remove.sh && apply.sh` pour mettre l'overlay à jour, committer, reconstruire.

## Traductions

Toutes les chaînes passent par `__()`. Le thème réutilise autant que possible des chaînes qu'osTicket
connaît déjà (donc traduites par les packs de langue). Les quelques chaînes nouvelles passent par
`ptdt__()`, qui appelle `__()` et, seulement si le pack de langue n'a pas la chaîne, cherche dans
`include/client/poste-de-travail/i18n/<langue>.php` (fourni : `fr.php`). Chaînes propres au thème :
« Skip to main content », « Main menu », « Home », « Hello %s », « Answered, awaiting your reply »,
« View all », « Browse all », « You have no open tickets. », « Breadcrumb », « Ticket number or subject ».
Elles ne sont pas extraites par `manage.php i18n make-pot` (fonction différente de `__()`).

Le portail n'est en français qu'avec le **pack de langue français** d'osTicket (`fr.phar` dans
`overlay/include/i18n/`, voir `deploy/docker/overlay/README.md`) : sans lui, tout est en anglais, thème compris.

## Écarts assumés avec la maquette 04

La maquette montre des données que le portail n'a pas ; elles sont remplacées par ce qu'osTicket fournit :

| Maquette | Implémentation |
|---|---|
| Compteurs « 1re réponse, moyenne », jauge « Première réponse / objectif tenu », fil « Activité récente » | Retirés (aucune donnée côté client). Accueil : tickets ouverts, « réponse reçue » (tickets ouverts avec réponse de l'équipe, champ `isanswered`) et fermés. |
| Vues enregistrées du rail (« Réponse reçue », « En attente de l'équipe », « Fermés ce mois-ci ») | Vues « Ouverts » et « Fermés » avec leurs compteurs réels. La liste ne sait pas filtrer « réponse reçue » (pas de nouveau filtre ajouté au cœur) : le point rose et le gras la signalent. |
| Rubrique « Aide » du rail (politique de support…) | Pages « Autres ressources » de l'administration (vide tant qu'aucune n'est créée). |
| « Articles suggérés » pendant la saisie, « Délais de réponse », « Avant d'envoyer » | Colonne « Questions fréquentes » (FAQ mises en avant). Le reste est du contenu à écrire dans les pages d'osTicket. |
| Recherche globale « ticket ou article » | Recherche dans la base de connaissances (le portail n'a pas de recherche commune) ; la liste des tickets garde sa propre recherche. Raccourci `/` conservé. |
| Libellés à gauche du formulaire, sections | Obtenus en CSS sur les lignes de tableau d'osTicket (`display: contents` sur les libellés) ; le champ « Détails » n'a pas de libellé (champ de type bloc, comme dans osTicket). |
| Colonne « Dernière activité » de la liste | « Date de création », colonne triable d'origine (« Dernière mise à jour » sur l'accueil). |
| Avatars à initiales, « Équipe » | Initiales calculées du nom ; étiquette « Staff » (chaîne d'osTicket, traduite par le pack). |
| Mentions légales, avertissement Matomo dans le pied de page | Non repris (contenu propre à Coderise, pas dans osTicket) : copyright et lien « propulsé par osTicket » d'origine. |
| Barre d'outils Redactor à 6 boutons, zone de dépôt | Redactor et la zone de dépôt d'osTicket, restylés (même nombre de boutons qu'osTicket). |
| Fiche d'article, résultats de recherche, catégorie | Gabarits d'origine habillés en panneaux (non maquettés). |

## Limites connues

- **Logo** : pas de logo vectoriel autonome dans la charte ; le pictogramme « ticket » des maquettes est redessiné
  en SVG, suivi du titre du centre d'aide. **À valider.**
- **Couleurs d'état** (rouge d'erreur, vert de succès, jaune d'avertissement) : extensions de la charte, **à valider**.
- `:has()` est utilisé pour la page de remerciement, la page hors ligne, les champs en erreur et les formulaires
  d'origine (navigateurs de 2023 et plus) ; sans `:has()`, ces pages restent lisibles mais sans panneau blanc.
- Le bouton d'envoi est souligné en rose quand le formulaire a des modifications non enregistrées : c'est le
  signal rouge d'osTicket (`js/osticket.js`), rendu lisible. Il apparaît aussi au chargement de la page de suivi
  (Redactor déclenche l'événement), comme dans le thème d'origine.
- Page « Accueil » active dans le rail sur la page profil et quelques pages sans section (comportement du cœur).
- Le message de confirmation après une réponse est affiché une fois, au-dessus du formulaire (le cœur l'affiche
  deux fois).
- Mode clair uniquement (la charte n'a pas de mode sombre). Pas de RTL testé (`css/rtl.css` reste chargé).
