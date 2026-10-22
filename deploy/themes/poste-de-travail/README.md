# Thème « Poste de travail » du portail client (Coderise)

Mise en œuvre de la proposition 02 des maquettes Coderise (`docs/theme-mockups-coderise/02-poste-de-travail/`,
charte graphique Coderise v1.0) : portail dense, **rail latéral bleu nuit pleine hauteur** (logo, signature,
navigation, rappel Priority, téléphone), barre haute blanche (fil d'Ariane, recherche, « Ouvrir un ticket »,
compte), tableaux serrés à **en-têtes bleu nuit et lignes alternées gris clair**, panneau de propriétés collant à
côté du fil d'un ticket. Seul le **portail client** est concerné ; le panneau agent (`scp/`) n'est pas touché.

Ce thème est une **alternative** aux thèmes `atelier`, `sommet` et `parcours` (`deploy/themes/<slug>/`) : un seul est
installé à la fois. Rien n'est modifié dans le cœur d'osTicket : le thème vit dans `overlay/`, copié dans
`deploy/docker/overlay/` par `apply.sh`, puis superposé à osTicket au moment de la construction de l'image
(voir `deploy/docker/README.md`, « Plugins et personnalisation » et « Personnalisation du thème »).

La version précédente de ce dossier était aux couleurs d'Ownalytics (proposition 04 de `docs/theme-mockups/`) ;
l'historique git la conserve.

## Contenu

| Chemin | Rôle |
|---|---|
| `overlay/` | Arbre qui reflète la racine d'osTicket : ce qui est installé dans l'image |
| `MANIFEST` | Liste exacte des fichiers de `overlay/` (installés et retirés par les scripts) |
| `BASE.sha256` | Empreintes SHA-256 des fichiers du cœur surchargés, au commit de base |
| `apply.sh`, `remove.sh` | Installation et retrait dans `deploy/docker/overlay/` (idempotents) |
| `check-drift.sh` | Liste les fichiers du cœur surchargés qui ont changé depuis le commit de base |
| `captures/` | Captures PNG des pages réelles (instance de test), bureau 1280 px et mobile 390 px, rapport d'audit |

### Fichiers du cœur surchargés (10, inchangés par rapport à la version précédente)

Un fichier de l'overlay **remplace** celui du cœur : chacun est à revoir à chaque mise à jour d'osTicket
(`check-drift.sh` dit lesquels ont changé).

| Fichier | Pourquoi il est surchargé |
|---|---|
| `include/client/header.inc.php` | Rail bleu nuit (logo, signature, navigation, vues ouverts/fermés, pages « Autres ressources », rappel Priority, téléphone, connexion/déconnexion), menu mobile (`<details>`, sans JavaScript), barre haute (fil d'Ariane, recherche, bouton, compte, langues) ; mise en page « authentification » (bandeau bleu nuit, sans rail) pour la connexion, l'inscription, le mot de passe oublié et l'accès par numéro d'un visiteur. Ne charge plus `assets/default/css/theme.css`, `print.css` ni `flags.css`. Même prélude PHP, mêmes en-têtes (CSP), mêmes scripts, mêmes messages. |
| `include/client/footer.inc.php` | Pied de page Coderise (contact, délais de réponse, liens) ; mêmes scripts (`getConfig()`, i18n JS), même `#loading`. |
| `index.php` | Accueil en tableau de bord (fichier prévu pour être adapté, d'après son en-tête). |
| `include/client/tickets.inc.php` | Liste « mes tickets » : filtres segmentés, tri, pagination, cartes sur mobile. Logique PHP copiée telle quelle ; seul ajout : `topic__topic` dans `values()` (sujet d'aide sous le titre). |
| `include/client/view.inc.php` | Suivi d'un ticket : fil + panneau de propriétés (infos, champs personnalisés, pièces jointes, coordonnées), indicateur Priority facultatif. |
| `include/client/templates/thread-entry.tmpl.php` | Message du fil : initiales au lieu des avatars (pas de Gravatar), rôle « Vous » / « Équipe Coderise ». |
| `include/client/open.inc.php` | Ouverture de ticket en panneau avec colonne « Questions fréquentes ». Garde le tableau et `#dynamic-form`. |
| `include/client/login.inc.php` | Connexion en deux panneaux (compte, suivi d'un ticket sans compte). |
| `include/client/accesslink.inc.php` | Accès à un ticket par e-mail et numéro. |
| `include/client/kb-categories.inc.php` | Base de connaissances : questions fréquentes, catégories en panneaux, liste des thèmes avec compteurs, recherche. |

Laissés tels quels, habillés par la feuille de style seulement : `register.inc.php`, `profile.inc.php`,
`edit.inc.php`, `pwreset.*.php`, `register.confirm*.inc.php`, `kb-search.inc.php`, `faq-category.inc.php`,
`faq.inc.php`, `knowledgebase.inc.php`, `templates/dynamic-form.tmpl.php` (partagé par l'ouverture,
l'inscription, le profil, la modification et le rechargement AJAX des champs ; `Form::render()` le prend aussi
par défaut quand un appel ne précise pas `staff` : ne pas le surcharger), `templates/thread-entries.tmpl.php`,
`templates/thread-event.tmpl.php`, la page de remerciement (`open.php`) et la page hors ligne (`offline.php`).

### Fichiers ajoutés (aucun équivalent dans le cœur)

- `assets/poste-de-travail/css/theme.css` : toute la feuille (écran et impression), jetons de la charte en tête.
- `assets/poste-de-travail/fonts/` : Montserrat 600/700/800 et Inter 400/500/600, sous-ensembles latin et
  latin-ext, WOFF2, copiés de `docs/theme-mockups-coderise/_shared/fonts/`, avec `OFL-montserrat.txt` et
  `OFL-inter.txt` (SIL Open Font License 1.1). Les polices Ownalytics (Archivo, IBM Plex Mono) sont retirées.
- `assets/poste-de-travail/img/logo-horizontal-sur-bleu-nuit.png` : logo horizontal détouré des maquettes
  (`docs/theme-mockups-coderise/_shared/img/logo-horizontal-sombre.png`, dérivé du fichier fourni, sans retouche),
  faute de logo horizontal officiel en SVG.
- `assets/poste-de-travail/img/favicon.svg` (copie de `deploy/themes/_shared/coderise/symbole-sur-bleu-nuit.svg` :
  le symbole sur carré bleu nuit), `favicon-32.png` et `favicon-180.png` (maquettes, même composition).
- `assets/poste-de-travail/js/theme.js` (raccourci `/` vers la recherche).
- `include/client/poste-de-travail/theme.php` (fonctions `ptdt__()`, `ptdt_icon()`, `ptdt_logo()`,
  `ptdt_signature()`, `ptdt_initials()`, `ptdt_asset()`, `ptdt_priority_sla()` ; constantes de réglage) et
  `include/client/poste-de-travail/i18n/fr.php` (voir « Traductions »).

Aucune ressource externe au chargement : polices, logo, icônes (SVG en ligne, trait de 1,75 comme les maquettes)
et script sont servis par osTicket. La CSP envoyée par osTicket (`script-src 'self' 'unsafe-inline'`) suffit.
Les liens LinkedIn, GitHub et coderise.fr du pied de page sont de simples liens (aucune requête au chargement).

## Charte appliquée

- **Couleurs** : bleu nuit `#000F26` (rail, bandeau d'accueil, en-têtes de tableaux, texte), cyan `#10C9DE`
  (boutons, état actif, pastilles, soulignés, motif « Sommet »), blanc ; cyan profond `#0A7C8C` pour les liens
  sur blanc uniquement ; ardoise `#5B6675` en texte secondaire sur blanc, gris clair et voile ; gris clair
  `#E8ECF1` (fond de page, lignes alternées) ; voile cyan `#E3F8FB` (messages du client dans le fil, survol).
  Jamais de texte cyan sur blanc ni de blanc sur cyan : texte bleu nuit sur les aplats cyan. Les liens posés sur
  un fond teinté (lignes grises, voile) sont **bleu nuit soulignés de cyan profond** (écart de la charte relevé
  dans les maquettes : cyan profond sur voile 4,46:1, sur gris clair 4,14:1). Pas d'ardoise sur bleu nuit : blanc
  à 80-88 % dans le rail.
- **Couleurs d'état** (erreur `#B3261E`, succès `#0F6B4B`, alerte `#7A4B00` et leurs fonds) : celles des
  maquettes, **extension de la charte à valider**.
- **Typographie** : Montserrat (titres, boutons, en-têtes de tableaux), Inter (texte), Arial en substitut.
  Code : pile système (`ui-monospace`, Consolas), extension à valider comme dans les maquettes.
- **Logo** : logo horizontal sur bleu nuit, 28 px de haut (≈ 166 px de large, minimum charte 120 px), zone de
  protection de 1X (28 px) dans le rail, signature « Le code qui fait **décoller** vos projets » sous le logo à
  1X. Un logo téléversé dans l'administration (Paramètres > Système > Logos, portail client) le remplace.
  Favicon : symbole sur carré bleu nuit.
- **Motif « Sommet »** : chevron du symbole en filigrane cyan dans le bandeau d'accueil et en cyan plein sur les
  états vides (`clip-path`, rapport 409/342 conservé, jamais étiré).
- **Ton** : chaînes du thème courtes, voix active, vouvoiement (« Aucun ticket ouvert. Tout roule. »,
  « Un incident bloquant ? Ouvrez un ticket, puis appelez-nous : une personne vous répond. »).
- **Contact** (pied de page et rail) : contact@coderise.fr, +33 (0)6 69 93 92 86, Aix-en-Provence ; délais :
  « Support standard : jours ouvrés. Support Priority : premier retour sous 4 h, 24 h/24, 7 j/7. Les horaires de
  l'entreprise (lundi au vendredi, 8h30 à 17h00) ne sont pas ceux du support Priority. » Ces valeurs sont des
  constantes de `theme.php` (`PTDT_CONTACT_EMAIL`, `PTDT_CONTACT_PHONE`, `PTDT_CONTACT_PHONE_URI`,
  `PTDT_CONTACT_CITY`, `PTDT_URL_SITE`, `PTDT_URL_LINKEDIN`, `PTDT_URL_GITHUB`), modifiables en les définissant
  avant (par exemple dans `ost-config.php`).

## Indicateur « Support Priority – premier retour sous 4 h » (à valider)

Facultatif, affiché dans la colonne du suivi d'un ticket **seulement si toutes les données sont réelles et
lisibles** (`ptdt_priority_sla()` dans `theme.php`) :

- le ticket est ouvert et n'a encore **aucune réponse de l'équipe** (aucune entrée de type `R` dans le fil) ;
- son plan de SLA est **actif** et porte le nom de la constante `PTDT_PRIORITY_SLA` (par défaut `Priority 4h`,
  le nom proposé par `contrib/sla-first-response/README.md`) ;
- son échéance de SLA (`est_duedate`) est renseignée.

Il affiche « Premier retour sous 4 h (Priority, 24 h/24) », une barre du temps écoulé entre l'ouverture (ou la
réouverture) et l'échéance, et « Attendu avant le <échéance>. L'équipe n'a pas encore répondu. ». Il **disparaît
seul** à la première réponse : le plugin `contrib/sla-first-response` bascule alors le ticket sur un plan
désactivé (plus d'échéance), et le thème vérifie aussi l'absence de réponse. Un dépassement n'est pas affiché
comme tel (la barre reste pleine) : la décision « montrer un dépassement au client » reste à prendre.

Ce qui reste à valider : le libellé, l'affichage d'un dépassement, et le nom du plan (`PTDT_PRIORITY_SLA`) si un
autre nom est retenu. Le niveau de contrat (Standard / Priority) n'est pas affiché ailleurs : osTicket ne le
connaît pas comme donnée du client (voir les écarts).

## Installation

Depuis la racine du dépôt :

```sh
deploy/themes/poste-de-travail/check-drift.sh     # le thème correspond-il à la version d'osTicket ?
deploy/themes/poste-de-travail/apply.sh -n        # vérification seule
deploy/themes/poste-de-travail/apply.sh           # copie dans deploy/docker/overlay/ + manifeste
git add deploy/docker/overlay
git commit -m "overlay: thème poste-de-travail"
deploy/docker/scripts/build.sh                    # l'image est construite depuis HEAD
# puis déploiement comme une mise à jour (deploy/docker/README.md, « Mises à jour »)
```

`apply.sh` écrit `deploy/docker/overlay/include/client/poste-de-travail/installed.manifest` (sous `include/`,
jamais servi par Apache) : la liste des fichiers installés avec leur SHA-256. Il refuse, sans rien écrire :

- si **un autre thème est installé** : `include/client/<slug>/installed.manifest` existe pour `atelier`,
  `sommet`, `parcours` (ou tout autre dossier de `deploy/themes/`), ou l'un des fichiers de son `overlay/` est
  présent à l'identique (installation sans manifeste) ;
- si un fichier qu'il écrirait existe déjà avec un autre contenu et n'appartient pas, inchangé, à une
  installation précédente de ce thème (personnalisation locale, autre thème) ;
- si `MANIFEST` ne correspond pas à `overlay/`.

Relancé sur une installation existante, il met à jour les fichiers du thème et retire ceux qui ont quitté le
thème (s'ils n'ont pas été modifiés). Il ne touche jamais aux autres fichiers de l'overlay (plugins, packs de
langue). Un chemin d'overlay peut être donné en argument (`apply.sh /chemin/vers/overlay`).

Dans l'administration, rien n'est obligatoire. Recommandé : un **titre du centre d'aide** (Paramètres >
Système, titre des onglets du navigateur), le **nom de l'entreprise** (copyright du pied de page), les **pages
d'accueil, de remerciement et hors ligne** (Gérer > Pages ; l'accueil s'affiche dans un panneau du tableau de
bord), les pages « Autres ressources » (type *Other*, affichées dans le rail), les **articles et catégories mis en
avant** de la base de connaissances (ils alimentent l'accueil, l'ouverture de ticket et la page de la base).

## Désinstallation, bascule vers un autre thème

```sh
deploy/themes/poste-de-travail/remove.sh          # retire les fichiers du thème, et eux seuls
deploy/themes/<autre>/apply.sh                    # si l'on bascule vers un autre thème
git add -A deploy/docker/overlay && git commit -m "overlay: thème <autre>" && deploy/docker/scripts/build.sh
```

`remove.sh` retire les fichiers listés dans `installed.manifest` qui ont encore leur empreinte d'installation,
puis le manifeste et les dossiers devenus vides (jamais `deploy/docker/overlay/` lui-même). Un fichier modifié
depuis l'installation est conservé et signalé (code de sortie 1, manifeste conservé) ; `-f` le retire quand
même ; `-n` simule. Sans manifeste (installation par une ancienne version du script), il retire les fichiers
de `MANIFEST` identiques à la copie du thème. Sans thème, osTicket reprend son apparence d'origine.

## Mise à jour d'osTicket

1. Avant de fusionner la nouvelle version : `deploy/themes/poste-de-travail/check-drift.sh --rev <révision>`
   (par exemple `upstream/develop` ou l'étiquette de la release) ; après la fusion :
   `deploy/themes/poste-de-travail/check-drift.sh` (arbre courant ; CRLF ramenés à LF avant le calcul).
2. Pour chaque fichier `CHANGED` : `git diff <commit de base> <nouvelle révision> -- <fichier>` (le commit de
   base est en tête de `BASE.sha256`), reporter les changements dans la copie de `overlay/` (corrections de
   sécurité en priorité : échappement, CSRF, contrôles d'accès). Un fichier `MISSING` a été renommé ou supprimé
   en amont : adapter le thème.
3. Régénérer `BASE.sha256` pour le nouveau commit de base (pour chaque fichier listé :
   `git show <commit>:<fichier> | sha256sum`, en gardant le format `empreinte␣␣chemin` et la ligne
   `# base commit:`), tester, puis `apply.sh` (met l'overlay à jour), committer, reconstruire.

## Traductions

Toutes les chaînes d'osTicket passent par `__()` et sont traduites par les packs de langue. Les chaînes propres
au thème (signature, pied de page, accueil, indicateur Priority, rôles du fil…) passent par `ptdt__()`, qui
appelle `__()` et, seulement si le pack de langue n'a pas la chaîne, cherche dans
`include/client/poste-de-travail/i18n/<langue>.php` (fourni : `fr.php`, avec espaces insécables). Les msgids
sont en anglais. Elles ne sont pas extraites par `manage.php i18n make-pot` (fonction différente de `__()`).

Le portail n'est en français qu'avec le **pack de langue français** d'osTicket (`fr.phar` dans
`overlay/include/i18n/`, voir `deploy/docker/overlay/README.md`) : sans lui, tout est en anglais, thème compris.
Les libellés des formulaires (« Adresse e-mail », « Résumé »…), les sujets d'aide et les pages sont du contenu
de l'administration, à saisir en français.

ECARTS_ET_VERIFICATIONS
