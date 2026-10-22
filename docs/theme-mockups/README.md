# Thème du portail client aux couleurs d'Ownalytics : cinq maquettes

Livrable de l'issue n° 4 (« Thème du portail client aux couleurs d'Ownalytics »). Ce sont des **propositions de maquettes**, pas un thème : rien n'a été développé dans osTicket. Chaque proposition est un petit site statique (HTML et CSS, sans JavaScript, sans requête réseau) avec six pages : accueil, ouverture de ticket, connexion, suivi d'un ticket, liste « mes tickets », base de connaissances. Le contenu est en français et entièrement fictif (client, tickets, agents, numéros).

- Comparaison côte à côte : [`index.html`](index.html) (ouvrez-la dans un navigateur).
- Maquettes : `01-ticket-de-caisse/`, `02-console/`, `03-atelier/`, `04-poste-de-travail/`, `05-une/`. Chaque dossier contient les six pages, un `theme.css` et un dossier `captures/` (PNG bureau 1280 px et mobile 390 px de chaque page, pleine hauteur).
- `_shared/` : polices et favicon copiés du site Ownalytics, feuille de la page de comparaison.
- `_build/` : générateurs (`build.mjs` pour les pages, `index.mjs` pour la comparaison, `capture.mjs` pour les captures). Les `theme.css` sont écrits à la main ; seul le HTML est généré.

## 1. Charte Ownalytics relevée

Source : `websites/ownalytics` (`assets/css/style.css`, `guides.css`, pages `fr/`), plus les feuilles `websites/heatlens` et `websites/auditlens` pour la famille de marque. Lecture seule, rien n'a été modifié.

**Direction du site : « The receipt »** (le ticket de caisse). Fond prune, contenu imprimé sur du papier à bords déchirés, montants en police à chasse fixe, filets pointillés, surligneur jaune sur les totaux.

| Rôle | Valeur | Remarque |
|---|---|---|
| Prune (fond, marque) | `#3B1E54` | couleur du site et du favicon, `theme-color` |
| Prune profond | `#2A1340` | pied de page, champs sur prune |
| Prune fente | `#1C0B2B` | fente de l'imprimante du bandeau |
| Lilas | `#CDB6E3` | texte secondaire et focus sur prune |
| Papier | `#FBFBF6` | surfaces claires, texte sur prune |
| Encre | `#22201F` | texte sur papier |
| Encre atténuée | `#5F5C59` | texte secondaire sur papier, AA |
| Filet décoratif | `#8A8784` | filets et pointillés, jamais du texte |
| Surligneur | `#F4E04D` | **réservé aux totaux** sur le site |
| Heatlens | menthe `#DFF5EA`, marine `#1D2B53`, rose `#FF4D8D`, mandarine `#FF9F1C`, soleil `#FFD23F` | sous-identité du plugin |
| Auditlens | brume `#E6ECFA`, marine `#1D2B53`, cobalt `#3450A3`, bleu `#6F8BE0` | sous-identité du plugin |

**Typographie** (toutes sous licence SIL OFL 1.1, autohébergées sur le site) :

- **Archivo**, police variable (graisse 100 à 900, largeur 62 à 125 %). Corps à 18 px, interligne 1,55. Titres en **800 et largeur 125 %** (`font-variation-settings: "wdth" 125`), interligne 1,04.
- **IBM Plex Mono** 400, 500, 600 : ticket de caisse, libellés, dates, intitulés de sections des pages légales et des guides.
- **Bricolage Grotesque** 700 à 800 : uniquement la pastille « Heatlens » (la fiche Heatlens utilise aussi Figtree, non reprise ici).

**Logo.** Pas de fichier de logo autonome : le logotype est un pictogramme SVG (un ticket de 20 × 26 px, bas en dents de scie, trois lignes) suivi du mot « Ownalytics » en Archivo 800 largeur 125 %. Le favicon est un carré arrondi prune contenant le même ticket en papier (`_shared/img/favicon.svg`). Les maquettes redessinent le pictogramme tel quel.

**Composants récurrents.** Bouton à angles droits, bordure encre de 2 px, ombre dure de 6 px décalée en lilas, qui « s'enfonce » au survol. Filets en tirets de 2 px (en-tête, sections, pied de page). Ticket papier à bords déchirés (dents de 8 px) avec ombre portée. Lignes à points de conduite (« libellé ........ valeur »). Surligneur penché sur le total. Pastilles de plugin inclinées de 3 degrés. FAQ en `details`. Focus visible de 3 px (lilas sur prune, prune sur papier). Lien d'évitement « Aller au contenu ». Gouttières de 16 puis 32 px, largeur maximale 1200 px, points de rupture 600, 720, 900, 960 px.

**Ton éditorial.** Vouvoiement, phrases courtes et concrètes, aucune formule marketing (« Vous payez une fois. », « Merci. Gardez-le, il est à vous. »). Espaces insécables avant `: ; ? !`. Français et anglais.

**Ce qui manque dans la charte** (et que je n'ai pas inventé) : pas de guide de marque écrit, pas de logo vectoriel autonome ni de version monochrome, pas de jeu d'icônes, pas de composants de formulaire sur fond clair (le site n'a que des champs sur prune), pas de tableau de données hors guides, **pas de mode sombre ni de bascule clair/sombre** (le site est unique). Les maquettes sont donc en mode clair uniquement, avec le prune comme couleur de marque. Les états d'erreur, de succès et d'avertissement (rouge `#9E1B2E`, vert `#1F6B4A` ou `#17603F`) et les contrôles de formulaire sur papier sont des **extensions proposées**, à valider.

**Contexte de support** utilisé pour la copie : portail `support.coderise.fr` (Coderise SAS), support inclus avec la licence (réponse les jours ouvrés, sans délai garanti), support prioritaire (première réponse sous 4 h, 24 h/24), français et anglais, ne jamais envoyer de données de visiteurs (politique de support Heatlens).

**Polices.** Archivo, IBM Plex Mono et Bricolage Grotesque sont copiées dans `_shared/fonts/` (sous-ensemble latin, WOFF2, avec `OFL.txt`) : la licence OFL l'autorise. Aucune police système de substitution n'a été nécessaire.

## 2. Réalité d'osTicket (ce qui contraint les maquettes)

Relevé dans `include/client/*.inc.php`, `header.inc.php`, `footer.inc.php`, `assets/default/css/theme.css`, `assets/default/less`, `css/thread.css`.

- Un seul emplacement de thème. En-tête : logo (image servie par `logo.php`), liens utilisateur, drapeaux de langue, `ul#nav` (Accueil, Base de connaissances, Ouvrir un ticket, Suivre un ticket ou Tickets). Pied de page : copyright et « propulsé par osTicket ». Conteneur fixe de 840 px dans le thème actuel.
- **Balisage ancien** : formulaires d'ouverture et d'inscription en tableaux (`#ticketForm`, une ligne par champ, `dynamic-form.tmpl.php`) ; fiche ticket en tableaux imbriqués (`#ticketInfo`, `.infoTable`, `.custom-data`) ; liste `#ticketTable`. Le fil (`.thread-entry.message` et `.response`, `.header`, `.thread-body`, `.attachments`) est en `div`, mais `css/thread.css` fixe `font-size: 14px !important` et la couleur des liens avec `!important`.
- **Champs dynamiques** : le thème d'aide (`#topicId`) recharge par AJAX le contenu de `#dynamic-form`. Tout gabarit réécrit doit garder ce conteneur et les noms de champs, sinon le formulaire casse.
- **Redactor** (éditeur riche) et le dépôt de fichiers (`.filedrop`) sont injectés par JavaScript : on peut les habiller (barre d'outils, zone de dépôt), pas les reconstruire.
- Le contenu de l'accueil (`.thread-body` dans `#landing_page`), de la page de remerciement et de la page hors ligne se modifie en HTML dans l'administration : les accroches d'accueil des maquettes y trouvent leur place.
- Les statuts côté client sont Ouvert, Résolu, Fermé. Pas de notion de « prise en charge », de vues enregistrées ni de compteurs de délai côté portail.

Dans les maquettes, **01 et 02 gardent le balisage fidèle d'osTicket** (tableaux compris, mêmes identifiants et classes) pour montrer ce que le CSS seul permet. **03, 04 et 05 utilisent des gabarits réécrits** (niveau 2) avec les mêmes champs, mêmes noms et mêmes états : formulaire avec une erreur, champs dynamiques (plugin, version de Matomo, adresse, hébergement, numéro de licence, case à cocher), éditeur riche simulé, pièces jointes, réponse, fil de discussion avec pièces jointes, tickets ouverts et fermés, catégories et articles.

## 3. Les cinq propositions

Niveau 1 = CSS, logo, en-tête, pied de page. Niveau 2 = gabarits réécrits. Les durées sont des **ordres de grandeur de l'auteur**, à confirmer, hors anglais et recette sur l'instance de test.

### 01, Ticket de caisse : continuité stricte du site vitrine

Le portail prolonge ownalytics.eu à l'identique : fond prune, contenu imprimé sur du papier à bords déchirés, filets pointillés, ombres dures, surligneur jaune. Un client qui arrive du site ne voit aucune couture, et le « ticket » de support redevient un vrai ticket. *Meilleure pour* les clients qui viennent du site et de ses fiches produit, et pour une équipe qui veut la cohérence de marque au plus bas coût. *Coût* : niveau 1, environ 2 jours (une ligne de gabarit pour les pastilles de statut).

### 02, Console : sobre, type documentation et console de support

Une barre haute prune, des panneaux blancs, des filets fins, Archivo en graisse normale : le portail s'efface derrière l'information. IBM Plex Mono ne sert qu'aux numéros, dates et noms de fichier ; la base de connaissances prend un rail latéral. *Meilleure pour* les administrateurs Matomo et équipes techniques qui lisent de longs fils avec journaux et commandes. *Coût* : niveau 1, environ 2 jours (en-tête avec recherche globale).

### 03, Atelier : chaleureux, centré sur les personnes

Le prune d'Ownalytics adouci par les couleurs des plugins (soleil, rose, menthe), Bricolage Grotesque, cartes arrondies, boutons-pastilles, avatars et conversation en bulles : le portail dit « une personne vous répond » avant de dire « ticket ». *Meilleure pour* un public moins technique (agences, indépendants) à rassurer. *Coût* : niveau 2, environ 5 jours.

### 04, Poste de travail : dense, orienté productivité

Rail latéral, tableaux serrés en Archivo resserré (axe de largeur à 92 %), fil compact, panneau de propriétés collant, formulaire à libellés à gauche, articles suggérés pendant la saisie. Le cobalt d'Auditlens porte liens et focus, le prune porte l'action. *Meilleure pour* les clients qui ouvrent beaucoup de tickets (agences, revendeurs, organisations à tickets partagés). *Coût* : niveau 2, environ 6 jours.

### 05, Une : éditorial, contrasté

Le support lu comme la une d'un journal : titres Archivo extra-larges, aplats prune, lilas et jaune, angles droits, filets noirs de 3 px, écrans scindés pour ouvrir un ticket et se connecter, fil présenté comme une « correspondance » datée. Le jaune, réservé aux totaux sur le site, devient la couleur d'action. *Meilleure pour* une marque qui veut du caractère et des décideurs qui ouvrent peu de tickets. *Coût* : niveau 2, environ 6 jours.

### Coût et risque

| Proposition | Niveau | Effort estimé | Suivi de ticket (page la plus coûteuse) | Anciens tableaux | Redactor et champs dynamiques |
|---|---|---|---|---|---|
| 01 Ticket de caisse | 1 | 2 j | Moyen : tableaux d'info neutralisés par CSS, `thread.css` à contrer | Conservés, neutralisés par `display:block` : fragile si un gabarit change | Barre d'outils restylée, comportement intact |
| 02 Console | 1 | 2 j | Moyen : `grid` appliqué à `#ticketInfo > tbody` pour trois colonnes | Idem 01 | Idem 01 |
| 03 Atelier | 2 | 5 j | Élevé : bulles (`thread-entry.tmpl.php`), frise d'avancement absente du modèle | Supprimés | Faible à moyen : `#dynamic-form` et noms de champs à préserver |
| 04 Poste de travail | 2 | 6 j | Élevé : panneau latéral, compteurs et « vues » absents du portail | Supprimés | Faible à moyen : idem, articles suggérés = AJAX en plus |
| 05 Une | 2 | 6 j | Moyen à élevé : fil « correspondance », titres XXL sur sujets longs | Supprimés | Faible à moyen : le contenu AJAX doit se plier à la grille scindée |

Risques propres à chaque proposition : voir les listes dépliables de `index.html`. Risque commun au niveau 2 : l'accueil et la page de remerciement relèvent du contenu administrable ; les éléments dynamiques des maquettes 03 et 04 (frise, compteurs, délai moyen, équipe nommée) ne sont pas des données d'osTicket et doivent être soit retirés, soit alimentés par du code hors cœur.

## 4. Recommandation : la 01, « Ticket de caisse »

Je recommande de **développer la 01 d'abord**, en niveau 1, puis de décider avec des retours réels s'il faut aller plus loin.

1. **C'est la seule qui soit une extension littérale de la marque.** Le ticket de caisse du site est une métaphore de la facture ; un « ticket » de support imprimé sur le même papier est un clin d'œil que le client comprend sans explication. Le prune, le papier, les filets et l'ombre dure sont déjà dans la feuille de style du site : peu d'invention, peu de décisions à justifier.
2. **Coût et risque les plus bas avec la 02.** Niveau 1 : feuille CSS, logo, en-tête, pied de page. On ne réécrit ni la fiche ticket ni le formulaire ; les mises à jour d'osTicket ont moins de chances de casser le thème. C'est le point que l'issue signale comme le plus coûteux (la page de suivi) qui est ici le moins touché.
3. **Le fil reste lisible.** Les réponses de l'équipe sont décalées et teintées, les pièces jointes sont des étiquettes, le code est en bloc sombre : le papier ne sacrifie pas la lecture longue. La police à chasse fixe est limitée aux libellés, dates et numéros.
4. **La 02 est le plan B**, si l'équipe juge le papier trop typé pour des fils techniques très longs, ou si un jour le portail doit ressembler à une documentation. Elle a le même coût.
5. Les niveaux 2 (03, 04, 05) sont de meilleures vitrines mais ajoutent des fonctions que le portail ne fournit pas (frise de progression, compteurs, vues enregistrées, écrans scindés autour d'un formulaire rechargé en AJAX). À réserver à une seconde phase.

À emprunter à la 02 dans une phase 2 de la 01 : le panneau de propriétés compact et les étiquettes de statut avec point (accessibilité : le statut ne repose pas que sur la couleur).

Points à valider avant de coder la 01 : (a) l'usage de `:has()` pour l'accueil sur prune (navigateurs récents) ou un repli en ajoutant une classe de section dans `index.php` hors cœur ; (b) le logo, qui doit être une image téléversée (ou un `header.inc.php` surchargé) ; (c) la version anglaise et les packs de langue ; (d) la décision sur les couleurs d'état (rouge d'erreur, vert de succès), absentes de la charte.

## 5. Accessibilité, responsive, vérifications

- **Contrastes mesurés** (calcul WCAG) sur toutes les paires de texte utilisées : toutes ≥ 4,5:1 (minimum observé 5,2:1 pour l'encre sur le rose des avatars de 03). Bordures de champs ≥ 3:1 (3,6:1 en 02, 3,2:1 en 04, 6,6:1 en 03, noires en 01 et 05). Le rose `#FF4D8D` (décoratif ou gros) n'est jamais utilisé comme texte ; le texte rose est `#B3175A` (6,6:1). Le surligneur jaune ne porte que de l'encre (12:1).
- **Focus visible** sur tous les contrôles (contour de 2 à 3 px, couleur adaptée au fond), lien d'évitement « Aller au contenu », lien discret « Retour à la comparaison des maquettes » visible au focus, libellés associés aux champs, messages d'erreur reliés par `aria-describedby`, `aria-current` sur la navigation, tableaux avec `caption` ou en-têtes, `prefers-reduced-motion` respecté pour les transitions.
- **Responsive** : chaque page a été rendue à 1280 px et 390 px ; aucun débordement horizontal à 390 px. Les tableaux (liste de tickets) se replient (colonnes masquées en 01 et 02, cartes en 03, lignes en 04 et 05).
- **Mode clair uniquement**, car la charte ne prévoit pas de mode sombre.
- **Réseau** : polices locales, aucun CDN, aucun script. Seul lien externe : « propulsé par osTicket ».

Régénérer : `node docs/theme-mockups/_build/build.mjs && node docs/theme-mockups/_build/index.mjs`. Les captures se refont avec `_build/capture.mjs` (nécessite `playwright-core` et un Chromium local ; voir l'en-tête du script).

## 6. Ce qui n'a pas été fait ou vérifié

- **Rien n'est intégré dans osTicket** : pas de gabarit PHP, pas de LESS, pas de test sur l'instance. Le comportement réel de Redactor, du dépôt de fichiers, de pjax et du rechargement AJAX des champs dynamiques n'a pas été testé (ils sont simulés en HTML statique).
- Pages non maquettées : inscription, mot de passe oublié, accès par lien, profil, modification de ticket, fiche d'un article, résultats de recherche, états vides, pagination au-delà d'une page, événements d'état dans le fil (`thread-event`), page d'impression. L'inscription réutilise les champs de la connexion et du formulaire d'ouverture.
- Version anglaise et packs de langue : non maquettés (le texte est français ; les maquettes sont prêtes pour `__()`).
- Tests manuels au clavier, lecteur d'écran, navigateurs anciens, RTL, impression : non faits. Seuls ont été vérifiés le rendu Chromium, l'absence de débordement horizontal et les contrastes calculés.
- Les noms de l'équipe (Inès, Théo, Léa), du client et les numéros de tickets sont fictifs ; les durées d'effort sont des estimations.
- Présentation sur **Claude Design** (canvas d'artefact) : tentée, **refusée par le contrôle de permissions** de la session (publication en ligne). Les maquettes HTML et les captures PNG locales sont donc les seuls livrables ; elles peuvent être publiées plus tard sur autorisation explicite.
