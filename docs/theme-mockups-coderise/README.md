# Portail de support Coderise : cinq maquettes à la charte v1.0

Ces cinq maquettes remplacent, pour la marque **Coderise**, la première série faite à tort aux couleurs d'Ownalytics (`docs/theme-mockups/`, non modifiée, utilisée seulement comme base technique : pages, balisage osTicket, captures). Ce sont des **propositions** : rien n'est développé dans osTicket. Chaque proposition est un petit site statique (HTML et CSS, sans JavaScript ni requête réseau), en français, avec des données fictives, à la charte graphique Coderise v1.0 (juin 2026).

- Comparaison côte à côte : [`index.html`](index.html) (à ouvrir dans un navigateur).
- Propositions : `01-atelier/`, `02-poste-de-travail/`, `03-sommet/`, `04-parcours/`, `05-referentiel/`. Chacune contient 10 pages, un `theme.css` et un dossier `captures/` (PNG pleine hauteur : bureau 1280 px et mobile 390 px de chaque page ; tablette 820 px pour l'accueil et le suivi de ticket).
- Pages de chaque proposition : accueil, ouverture de ticket (avec erreurs de formulaire), connexion (avec erreur), inscription, inscription : confirmation, suivi d'un ticket, mes tickets, mes tickets : état vide, base de connaissances (accueil), article.
- `_shared/` : logos détourés (`img/`), polices (`fonts/`, licences OFL), `tokens.css` (jetons de la charte), `fonts.css`, `compare.css`.
- `_build/` : générateurs (`build.mjs`, `index.mjs`), composants et données fictives (`common.mjs`, `article.mjs`), une page par proposition (`p01.mjs` à `p05.mjs`), traitement des logos (`logos.py`), captures (`capture.mjs`) et contrôles (`audit.mjs`, `focus.mjs`, `audit-report.json`). Les `theme.css` sont écrits à la main ; seul le HTML est généré.

## 1. Charte appliquée

| Élément | Application |
|---|---|
| Couleurs | Bleu nuit `#000F26` (fonds sombres, titres, texte), cyan `#10C9DE` (accent : boutons, état actif, soulignages, aplats, chevron), blanc. Support : cyan profond `#0A7C8C` (liens sur blanc uniquement), ardoise `#5B6675` (texte secondaire), gris clair `#E8ECF1` (fonds, lignes alternées), voile cyan `#E3F8FB` (encadrés). Jetons dans `_shared/tokens.css`. |
| Hiérarchie bleu nuit / cyan | Le cyan ne porte jamais de texte sur blanc ; un bouton cyan porte du bleu nuit (9,5:1) ; le cyan sur bleu nuit est réservé aux accents et aux liens des zones sombres. Mesure de la surface des captures bureau : bleu nuit de 18 % (02) à 43 % (03), cyan de 0,6 % à 1,3 %. Le cyan reste donc un signal ; la répartition « 60 / 30 / 10 » de la charte vaut pour les supports de marque, pas pour une interface où le blanc domine. |
| Typographie | Montserrat 600, 700, 800 (titres) et Inter 400, 500, 600 (texte), WOFF2 latin et latin-ext, locales (`_shared/fonts/`), aucune requête réseau. Substitut Arial dans les piles. Aucune autre police, sauf le code (voir « Décisions »). |
| Logo | Fichiers fournis uniquement, détourés sans retouche (section 2). Logo horizontal sur toutes les zones bleu nuit (en-tête, rail, pied de page), 142 à 189 px de large (minimum charte 120 px). Zone de protection : X = hauteur du logo, vérifiée par script (section 7). |
| Signature | « Le code qui fait **décoller** vos projets » (Montserrat 600, « décoller » en cyan), sous le logo à 1X au moins, jamais dans un cadre, uniquement là où le logo est à côté (pied de page, rail de 02). |
| Motif « Sommet » | Le chevron du symbole, isolé : cyan plein (accueil et connexion de 03, bandeau de recherche de 01), filigrane clair (bandeaux, bulle de l'accueil de 01), marqueurs d'étapes (01, 04), état vide. Jamais étiré ni incliné : rapport 409 / 342 conservé (`clip-path`). |
| Icônes | Style ligne, trait constant de 1,75, angles nets et extrémités légèrement arrondies, grille 24 carrée, bleu nuit par défaut, cyan (ou cyan profond) pour l'état actif ou l'action. Jeu dessiné pour les maquettes (`common.mjs`). |
| Tableaux | En-têtes bleu nuit, lignes alternées gris clair, mises en avant cyan (ligne sélectionnée, dernière colonne). |
| Ton | Vouvoiement, voix active, phrases courtes (« Faisons monter votre projet d'un niveau. », « Livré en production, pas pour la démo. », « Aucun ticket ouvert. Tout roule. »). Les états vides et les erreurs disent quoi faire. |
| Modes | Une seule version claire (la charte ne définit pas de mode sombre). La version du logo sur bleu nuit sert dans les zones sombres. |
| Délais | « Support standard : jours ouvrés. Support Priority : premier retour sous 4 h, 24 h/24, 7 j/7. » Les horaires de l'entreprise (lundi au vendredi, 8h30 à 17h00) ne sont donnés qu'au pied de page, avec la mention qu'ils ne sont pas ceux du support Priority. |

### Contrastes de la charte (mesurés, WCAG)

| Paire | Ratio | Usage |
|---|---|---|
| Bleu nuit sur blanc / gris clair / voile | 19,2 / 16,1 / 17,4 | texte courant |
| Bleu nuit sur cyan | 9,5 | boutons, pastilles |
| Cyan sur bleu nuit | 9,5 | accents en zone sombre |
| **Cyan sur blanc** | **2,0** | jamais en texte |
| Cyan profond sur blanc | 4,9 | liens sur fond blanc |
| **Cyan profond sur voile cyan** | **4,46, insuffisant** | voir ci-dessous |
| **Cyan profond sur gris clair** | **4,14, insuffisant** | voir ci-dessous |
| Ardoise sur blanc / voile / gris clair | 5,8 / 5,3 / 4,9 | texte secondaire |
| **Ardoise sur bleu nuit** | **3,3, insuffisant** | en zone sombre : blanc à 78-85 % |
| Extension : erreur `#B3261E` sur blanc / `#FCECEA` | 6,5 / 5,7 | à valider |
| Extension : succès `#0F6B4B` sur blanc / `#E4F5EC` | 6,5 / 5,8 | à valider |
| Extension : alerte `#7A4B00` sur blanc / `#FFF3D1` | 7,4 / 6,7 | à valider (définie, non utilisée) |

**Écart à signaler à l'auteur de la charte** : « liens cyan profond sur blanc » est exact, mais le même lien sur un fond teinté de la charte (voile cyan, gris clair) passe sous 4,5:1. Dans les maquettes, les liens posés sur un fond teinté sont en bleu nuit soulignés de cyan profond. La charte devrait le dire.

## 2. Logos : traitement

Les trois JPG fournis sont raster, sans transparence. Le script `_build/logos.py` retrouve l'opacité de chaque pixel (anti-crénelage compris) par rapport au fond connu, puis l'applique à la couleur de charte exacte (cyan `#10C9DE`, blanc, bleu nuit `#000F26`). Le dessin n'est ni redessiné, ni déformé, ni recoloré ; les images sont recadrées au plus juste, la zone de protection est appliquée en mise en page.

| Fichier produit (`_shared/img/`) | Source | Usage |
|---|---|---|
| `logo-horizontal-sombre.png` (2041 × 345) | `2.jpg`, fond noir détouré | en-têtes, rails, pieds de page : tous les usages de ces maquettes |
| `logo-complet-sombre.png` (737 × 592), `symbole-sombre.png` | `3.jpg` détouré du fond bleu nuit | disponibles, non utilisés dans les maquettes |
| `logo-complet-clair.png` (704 × 566), `symbole-clair.png` | `1.jpg` détouré du fond blanc | disponibles, non utilisés : aucune maquette n'a de zone claire portant le logo |
| `favicon-32/64/180/512.png` | `symbole-sombre.png` centré sur un carré bleu nuit | favicon et avatar (composition, pas de retouche du dessin) |

À savoir sur `2.jpg` : le fichier contient deux formes bleu nuit (une barre au-dessus de la barre oblique, une barre sous le symbole) à peine visibles sur son fond noir. Elles ont la couleur d'une zone bleu nuit : le détourage les fait disparaître, ce qui donne exactement le rendu voulu sur un fond bleu nuit (même dessin que `3.jpg`). Le rendu est contrôlé à l'œil sur fond bleu nuit, sans halo.

### Ce qui manque

- **Le logo en vectoriel (SVG)**, et le **symbole seul en SVG**. Les PNG détourés sont une solution de repli : l'en-tête d'osTicket devra recevoir une image téléversée. Aucun SVG officiel n'existe dans `E:\Sources\public-website` (seulement `public/logo_coderise.png`, symbole et mot en blanc sur transparent, non utilisé ici).
- **Un logo horizontal sur fond clair.** Il n'existe que le logo carré (`1.jpg`) pour les fonds clairs. C'est pourquoi toutes les propositions ont un en-tête bleu nuit, ce qui correspond d'ailleurs à coderise.fr.
- **Les versions monochromes** (bleu nuit, blanc) : non fournies, non recréées (la charte interdit de recolorer).
- **Un favicon officiel.** Le carré bleu nuit avec le symbole est une composition proposée. À 16 et 32 px, le symbole sous 56 px de large est une exception à la règle de taille minimale de la charte, qui vise le logo sur écran ; à valider.
- **Le motif « Sommet » en SVG officiel.** Le chevron des maquettes est reconstruit d'après la géométrie du symbole (mesurée sur `1.jpg`) ; à remplacer par le fichier officiel.
- **Les couleurs d'état** (erreur, succès, alerte) : proposées, marquées « extension à valider » dans `tokens.css`.
- **Une police de code.** Les extraits de code utilisent la pile système (`ui-monospace`, Consolas) : extension à valider.
- Les avatars de l'équipe sont des **initiales** sur rond bleu nuit ; la charte ne dit rien des avatars.

## 3. Le site coderise.fr et la charte

Relevé en lecture seule dans `E:\Sources\public-website` (`tailwind.config.ts`, `src/index.css`, `index.html`, `Header.tsx`, `HeroSection.tsx`, `Footer.tsx`, `ServicesSection.tsx`). **La charte prime** ; voici les écarts observés.

| Sujet | Site actuel | Charte v1.0 | Retenu |
|---|---|---|---|
| Bleu de marque | `#1E2A47` (« navy »), fond de page proche de `#000F26` | `#000F26` | charte |
| Cyan | `#00E1E5` (« turquoise »), lueurs et halo du curseur | `#10C9DE` | charte |
| Gris | `#475569` / `#8B9DC3`, pied de page `gray-900` | ardoise `#5B6675`, bleu nuit | charte |
| Polices | Montserrat seule, chargée depuis Google Fonts (CDN) | Montserrat et Inter | locales, les deux |
| Logo | PNG du symbole à 32 px **plus** « CODERISE » recomposé en Montserrat Bold | fichiers fournis, logotype jamais recréé | fichiers fournis |
| Signature | « Le code qui fait décoller vos projets » en cyan sur fond sombre | Montserrat, bleu nuit ou blanc, « décoller » en cyan | charte |
| Icônes | lucide-react (ligne) | ligne, trait constant | cohérent, jeu propre aux maquettes |
| Boutons, cartes | boutons cyan à texte bleu nuit, coins `rounded-md`, cartes sur fond blanc | éléments interactifs cyan | repris (texte bleu nuit sur cyan) |
| Effets | dégradés, flous, curseur lumineux, réseau d'agents animé | motif « Sommet » plein ou filigrane | aucun effet repris |

À corriger sur le site si la charte fait foi : les deux couleurs de marque, le logo recomposé en texte, la police Inter absente et l'appel au CDN.

## 4. Ce que le portail d'osTicket permet réellement

Rappel de la première série (`docs/theme-mockups/README.md`) : colonne unique de 840 px, formulaires et fiche de ticket en tableaux imbriqués, champs dynamiques rechargés en AJAX par `#topicId` (garder `#dynamic-form` et les noms de champs), Redactor et dépôt de fichiers injectés par JavaScript, statuts côté client Ouvert, Résolu, Fermé, contenu d'accueil modifiable dans l'administration.

Les maquettes gardent les noms et identifiants d'osTicket (`#ticketForm`, `#dynamic-form`, `#ticketTable`, `.thread-entry.message` et `.response`, `.thread-body`, `.attachments`, `#loginForm`, `.redactor-box`, `.filedrop`, `topicId`, `luser`, `lpasswd`). Les éléments qui ne sont pas des données du portail portent l'attribut `data-needs-dev` dans le HTML.

### L'indicateur de délai Priority (« premier retour sous 4 h »)

Toutes les propositions l'affichent (barre, altimètre ou jauge). **Le portail client ne l'affiche pas.** Ce qui existe : les plans de SLA (délai de grâce, calendrier 24 h/24 possible) et l'échéance d'un ticket, côté équipe. Ce qu'il faut développer, hors cœur, dans le gabarit de suivi : lire le plan de SLA du ticket, retrouver l'horodatage de la première réponse de l'équipe, calculer le temps écoulé en continu (24 h/24 pour Priority, jours ouvrés pour le standard, donc deux calendriers) et décider « objectif tenu » ou « dépassé ». Ordre de grandeur : 1 à 2 jours. Il faut aussi décider si le client doit voir un dépassement.

### Autres éléments non natifs (repérés par `data-needs-dev`)

| Élément | Où | Nature du travail |
|---|---|---|
| Contrat « Standard / Priority » sur chaque ticket | toutes | champ de formulaire ou lien produit-contrat à créer |
| Avancement en étapes (« pris en charge », « correctif en test ») | 01, 03, 04 | statuts personnalisés (ils s'affichent côté client) ou étapes dérivées des réponses ; aucune frise native |
| « Votre prochain pas » | 04 | texte et action dérivés de la dernière réponse, à coder |
| Regroupement « À vous de jouer / Chez Coderise » | 04 | requête sur l'auteur de la dernière réponse |
| Statistiques de l'accueil (premier retour moyen, tickets résolus) | 02 | requêtes à coder ; ou à retirer |
| Articles suggérés pendant la saisie | 02, 05 | comportement du formulaire d'ouverture à vérifier, sinon AJAX à coder |
| Parcours de la base de connaissances (ordre, « article 2 sur 4 ») | 04 | l'ordre des articles n'existe pas dans le modèle |
| Sommaire « sur cette page », arborescence | 03, 05 | génération depuis les titres ; sous-catégories limitées |
| Menu mobile | toutes | `details` dans les maquettes ; l'en-tête d'osTicket est une liste simple, à surcharger |
| Assistant en étapes | 04 | visuel seulement ici ; la progression en direct demande du JavaScript |

## 5. Les cinq propositions

Niveau 1 : feuille de style, logo, en-tête, pied de page. Niveau 2 : gabarits réécrits. Les durées sont des **ordres de grandeur de l'auteur**, à confirmer, hors anglais et recette sur l'instance de test.

### 01, Atelier : chaleureux, centré sur les personnes (transposition de « Atelier »)

*Intention.* Dire « une personne vous répond » avant de dire « ticket » : avatars (initiales, contour cyan pour l'équipe), bulles de conversation (équipe en bleu nuit, client en voile cyan), boutons-pastilles, frise d'ascension de la demande (marqueurs en chevron qui montent), bulle de conversation en accueil. *Pour qui.* Clients non techniques (commerce, direction) qu'il faut rassurer. *Coût.* Niveau 2, environ 5 jours.
*Risques.* Bulles et avatars : le gabarit du fil doit être réécrit (`thread-entry`), les classes d'osTicket ne suffisent pas. Frise : états non natifs. Longs fils techniques : beaucoup de bleu nuit dans les bulles de l'équipe, à vérifier. Cartes arrondies : risque de ressembler à un SaaS générique si l'illustration d'accueil disparaît.

### 02, Poste de travail : dense, orienté productivité (transposition de « Poste de travail »)

*Intention.* Un outil de travail : rail latéral bleu nuit (logo, signature, navigation, contrat), barre haute avec recherche, tableaux serrés (en-têtes bleu nuit, lignes alternées), fil compact, panneau de propriétés collant, formulaire à libellés à gauche, articles suggérés. *Pour qui.* Clients qui ouvrent beaucoup de tickets : équipes techniques, revendeurs. *Coût.* Niveau 2, environ 6 jours.
*Risques.* Toute la page change de structure (rail, colonne principale). Le tableau de bord (compteurs, délai moyen, contrat) n'existe pas dans le portail. Densité : corps de 14,5 px, à surveiller en accessibilité et sur mobile (le rail devient une barre haute, les tableaux se replient en lignes).

### 03, Sommet : éditorial, dominé par le bleu nuit (nouvelle direction)

*Intention.* La marque d'abord : bandeaux bleu nuit pleine largeur, très grande typographie Montserrat, le **chevron cyan** posé sur le bas du bandeau d'accueil (un seul grand geste), filets francs, listes éditoriales à la place des cartes, altimètre du délai dans le bandeau du ticket, journal des échanges en deux colonnes. C'est la plus proche de la présentation de coderise.fr. *Pour qui.* Décideurs et chefs de projet : peu de tickets, lecture confortable, marque forte. *Coût.* Niveau 1 étendu : couleurs, typographie, en-tête, pied de page, accueil (contenu administrable), formulaires et listes se traitent en feuille de style ; niveau 2 pour les bandeaux de titre et la fiche de ticket. Environ 4 jours.
*Risques.* Les bandeaux demandent d'ouvrir un conteneur dans l'en-tête pour y placer le titre de page. Altimètre : non natif. Beaucoup de bleu nuit : à tempérer si le public attend un portail « clair » ; la discipline « le cyan reste un signal » doit tenir quand les pages seront enrichies.

### 04, Parcours : guidé, orienté progression (nouvelle direction)

*Intention.* Chaque demande est un parcours : trois choix à l'accueil (incident, question, évolution), formulaire en quatre étapes numérotées avec une étape « courante » en cyan et une erreur signalée, frise d'avancement, « prochain pas », **altimètre** gradué de 0 à 4 h, liste groupée par « à votre tour », bibliothèque organisée en parcours (Démarrer, Livrer, Opérer) avec précédent et suivant. *Pour qui.* Clients qui hésitent sur quoi écrire ; équipes qui veulent des tickets mieux remplis. *Coût.* Niveau 2, environ 7 jours, le plus élevé.
*Risques.* osTicket sert un formulaire unique : l'étapage est visuel, et la progression en direct demande du JavaScript. Plusieurs données dérivées à coder (prochain pas, regroupement, étapes). L'ordre des articles n'existe pas. Plus de contenu rédactionnel à maintenir.

### 05, Référentiel : documentation d'abord (nouvelle direction)

*Intention.* Un site de documentation avec un support attaché : recherche dans l'en-tête, arborescence de la base, sommaire « sur cette page », code en bloc, fil de ticket façon suivi d'incident (étiquettes Priority, Production, Bloquant ; événements de la conversation ; participants), éditeur à onglets Écrire et Aperçu. *Pour qui.* Développeurs et DSI côté client, habitués à ce type d'outil. *Coût.* Niveau 2, environ 6 jours.
*Risques.* Arborescence et sommaire à produire ; étiquettes à relier aux champs du formulaire ; ton plus froid, moins rassurant pour un public non technique ; proche de 02 par la densité, mais sans tableau de bord ni rail.

## 6. Tableau comparatif

| | 01 Atelier | 02 Poste de travail | 03 Sommet | 04 Parcours | 05 Référentiel |
|---|---|---|---|---|---|
| Ambiance | chaleureuse | outil dense | marque forte | guidée | technique |
| Surface bleu nuit / cyan (mesurée) | 31 % / 0,6 % | 18 % / 0,7 % | **43 %** / 1,3 % | 27 % / 0,9 % | 29 % / 0,6 % |
| Niveau, durée | 2, 5 j | 2, 6 j | **1 étendu, 4 j** | 2, 7 j | 2, 6 j |
| Rupture avec le gabarit osTicket | moyenne | **forte** | **faible** | forte | moyenne |
| Données non natives affichées | frise | compteurs, contrat | altimètre | **nombreuses** | étiquettes |
| Lisibilité des longs fils | bonne | **très bonne** | **très bonne** | bonne | très bonne |
| Mobile (390 px) | cartes empilées | rail en barre, tableaux repliés | colonnes empilées | frise verticale | arborescence sous l'article |
| Fidélité à coderise.fr | moyenne | faible | **forte** | moyenne | moyenne |
| Public visé | non technique | utilisateurs fréquents | décideurs | clients hésitants | développeurs |

## 7. Recommandation : commencer par 03, « Sommet »

Je recommande de **développer 03 en première phase**, puis de décider avec des retours réels s'il faut aller plus loin.

1. **C'est la plus fidèle à la marque et à son site.** Fond bleu nuit dominant, chevron cyan, titres Montserrat très lisibles : un client qui passe de coderise.fr au support ne voit pas de couture. Elle applique « le bleu nuit porte l'identité, le cyan en signe l'accent » sans effort : 43 % de bleu nuit, 1,3 % de cyan.
2. **C'est la moins coûteuse et la moins risquée** (environ 4 jours, niveau 1 étendu). Elle ne change ni la structure du formulaire ni celle de la liste ; les mises à jour d'osTicket la casseront moins que 02 (rail) ou 04 (assistant).
3. **Elle porte le délai Priority de la façon la plus visible**, dans le bandeau du ticket, sans dépendre d'avatars ni de frise.
4. **Elle laisse la porte ouverte.** L'altimètre de 04 et les avatars de 01 s'y greffent en seconde phase, une fois le développement du délai Priority fait.

Pourquoi pas 01 ou 02, vos deux préférées de la première série ? **01 Atelier** est la meilleure si la priorité est la relation (clients peu techniques) : choisissez-la si le portail est surtout lu par des non-développeurs. Elle coûte un jour de plus et demande une frise qui n'existe pas dans le modèle. **02 Poste de travail** est la meilleure si vos clients ouvrent beaucoup de tickets ; sa rupture avec le gabarit est la plus forte, et son tableau de bord montre des données que le portail n'a pas. **04** a la meilleure idée pour « orienté progression » mais coûte le plus ; **05** est utile surtout si la documentation devient le premier canal.

Points à valider avant de coder : (a) le fichier vectoriel du logo ; (b) les couleurs d'état ; (c) la règle de lien sur fond teinté (section 1) ; (d) la décision sur le délai Priority (affichage du dépassement, calendriers) ; (e) la version anglaise (non maquettée).

## 8. Vérifications effectuées

Script `_build/audit.mjs` (Chromium, Playwright) sur les 50 pages et `index.html`, à 390, 820 et 1280 px (153 rendus) :

- **Débordement horizontal** : aucun à 390, 820 et 1280 px (un défaut de grille trouvé et corrigé en cours de route dans 04 ; un autre dans 05 et dans `index.html`).
- **Contraste texte / fond** : chaque nœud de texte est mesuré avec sa couleur calculée et son fond réel (couches de transparence composées), seuil 4,5:1 (3:1 pour le gros texte), y compris les marques de substitution (placeholders). **Minimum mesuré : 4,91:1, aucune paire sous le seuil.** Les contrôles ont une sensibilité vérifiée par injection de couleurs fautives. Un champ de recherche blanc sur blanc dans l'en-tête de 05 a été détecté ainsi et corrigé.
- **Bords des champs** : chaque champ est délimité à 3:1 au moins (bordure de 1 à 2 px en ardoise ou bleu nuit sur blanc, 5,8:1 ou plus).
- **Logo** : largeur minimale de 120 px respectée partout (142 à 189 px) ; zone de protection de 1X mesurée dans les en-têtes, rails et pieds de page à 390 et 1280 px (le plus proche voisin est à X ou plus du logo ; les marges latérales sont de X ou plus à tous les points de rupture). La signature est à 1X au moins sous le logo.
- **Focus clavier** (`_build/focus.mjs`) : tous les éléments atteints par Tab sur 25 pages portent un contour d'au moins 2 px (3 px en pratique : cyan profond sur fond clair, cyan sur bleu nuit).
- **Accessibilité de base** : lien d'évitement et lien « Retour à la comparaison » visibles au focus, libellés associés aux champs, erreurs reliées par `aria-describedby` et récapitulées dans un bloc `role="alert"`, `aria-current` sur la navigation, tableaux avec `caption` et `th scope`, état jamais porté par la couleur seule (icône et texte), `prefers-reduced-motion` respecté.
- **Réseau** : aucune requête externe ; seuls liens sortants : osTicket, LinkedIn, GitHub, www.coderise.fr.

Régénérer : `node docs/theme-mockups-coderise/_build/build.mjs && node docs/theme-mockups-coderise/_build/index.mjs`. Captures et contrôles : `capture.mjs`, `audit.mjs`, `focus.mjs` (nécessitent `playwright-core`, installé hors dépôt avec `npm i playwright-core --ignore-scripts`, variable `PW_CORE` vers son `index.mjs`, et un Chromium local). Logos : `uv run --with pillow --with numpy python -I _build/logos.py <dossier des JPG> _shared/img`.

## 9. Ce qui n'a pas été fait ou vérifié

- **Rien n'est intégré dans osTicket** : pas de gabarit PHP ni de LESS, pas de test sur une instance. Le comportement réel de Redactor, du dépôt de fichiers, de pjax et du rechargement AJAX des champs dynamiques est simulé en HTML statique.
- **Version anglaise** et packs de langue : non maquettés.
- **Pages non maquettées** : mot de passe oublié, accès par lien, profil, modification d'un ticket, résultats de recherche, pagination au-delà d'une page, page d'impression, page hors ligne.
- Pas de test au lecteur d'écran, navigateurs autres que Chromium, RTL, impression, ni de test au clavier du menu mobile (`details`).
- Les captures sont prises avec le menu mobile fermé.
- **Contenus fictifs ou inventés pour l'illustration, à valider avant usage** : toute l'équipe (Hugo, Nora, Sacha), la cliente (Léa Marchand, Maison Verdier), les tickets et numéros, les produits ; et quelques règles : priorité « Urgente » réservée à Priority, « chiffrage sous quelques jours », « premier retour moyen 52 min », lien de confirmation valable 24 h, mot de passe de 12 caractères, pièces jointes de 20 Mo. Seuls sont issus de la charte ou de vos consignes : les coordonnées de l'entreprise, la signature, les trois exemples de phrases, les délais « standard : jours ouvrés » et « Priority : sous 4 h, 24 h/24 ».
- Présentation sur Claude Design ou publication en ligne : non faite, comme demandé.
