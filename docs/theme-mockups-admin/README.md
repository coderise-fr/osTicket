# Panneau agent et administration d'osTicket : une maquette à la charte Coderise v1.0

Une **proposition** de thème pour le panneau agent (`/scp/`) et l'administration, dérivée du thème « Poste de travail » du portail (`docs/theme-mockups-coderise/02-poste-de-travail/`, `deploy/themes/poste-de-travail/`). Rien n'est développé dans osTicket : ce sont des pages HTML et CSS statiques, sans JavaScript ni requête réseau, avec des données fictives.

- Ouvrir [`index.html`](index.html) dans un navigateur : le hub liste les six pages.
- Pages : `login.html` (connexion), `tickets.html` (file de tickets), `ticket.html` (fiche ticket), `tableau-de-bord.html`, `admin-parametres.html` (Paramètres, Tickets), `admin-agents.html`.
- `theme.css` : la feuille, écrite à la main. Elle réutilise les jetons, polices et logos de `../theme-mockups-coderise/_shared/` (aucune copie).
- `captures/` : PNG du hub (bureau 1280 px) et une capture mobile (390 px).
- `_build/build.mjs` : génère le HTML. Régénérer : `node docs/theme-mockups-admin/_build/build.mjs`.

## Ce qui vient de « Poste de travail »

Rail latéral bleu nuit, barre haute blanche avec recherche, tableaux à en-tête bleu nuit et lignes alternées, panneaux blancs, panneau de propriétés collant, formulaires à libellés à gauche, boutons cyan à texte bleu nuit, avatars en initiales, même jeu d'icônes (trait de 1,75, grille 24).

## Ce qui est propre à l'interface agent

| Choix | Pourquoi |
|---|---|
| Bascule **Agent / Administration** en haut du rail | osTicket sépare les deux espaces mais ne montre le contexte que par un lien de texte. Ici le segment actif est le seul aplat cyan du rail, et chaque espace a sa propre navigation. |
| **Jauge de premier retour** comme colonne qui organise la file | C'est ce que le plugin `sla_first_response` calcule déjà (Priority : 4 h, 24 h/24). L'état se lit dans le texte et l'icône (« Dépassé de 47 min », « Reste 38 min », « Tenu en 44 min »), jamais par la couleur seule. Repères à 25, 50 et 75 %. |
| File « À risque » dans le rail, bandeau d'alerte au tableau de bord | Les tickets qui vont dépasser sont visibles sans filtrer. |
| **Note interne teintée** (fil et zone de saisie) | Une réponse au client et une note interne ne se ressemblent plus : le mode « Note interne » colore toute la zone de saisie et rappelle « Le client ne reçoit rien ». |
| Actions groupées dans une barre bleu nuit | Elle n'apparaît que lorsqu'une ligne est cochée, et sépare les actions de la sélection des filtres. |
| Réglages modifiés repérés, barre d'enregistrement collée en bas | Dans les longs formulaires d'administration, on voit ce qui a changé et les boutons restent à portée. |

## Charte et accessibilité

- Couleurs : bleu nuit `#000F26`, cyan `#10C9DE` (jamais en texte sur blanc), cyan profond `#0A7C8C` pour les liens sur blanc, ardoise `#5B6675`, gris `#E8ECF1`, voile `#E3F8FB`. Couleurs d'état : celles des maquettes du portail (extension à valider).
- Polices : Montserrat (titres) et Inter (texte), locales. Chiffres tabulaires dans les tableaux et les compteurs.
- Texte courant de 13,5 px, plus dense que le portail (14,5 px). Les textes secondaires sont à 12 px, sauf les compteurs (11 px).
- Focus clavier visible (contour de 3 px), lien d'évitement, tableaux avec `caption` et `th scope`, graphique avec titre et description, `prefers-reduced-motion` respecté.
- Sous 920 px le rail devient une barre haute avec menu (`details`) ; sous 760 px les tableaux se replient en lignes.

## Vérifié

Aucun débordement horizontal à 390, 820 et 1280 px sur les sept pages (mesuré dans Chromium via Playwright), et relecture visuelle à 1280 px de chaque page.

## Non vérifié, non fait

- **Contraste mesuré** page par page (comme l'audit des maquettes du portail) : non fait. Les paires utilisées sont celles de la charte déjà mesurées dans `../theme-mockups-coderise/README.md`.
- Lecteur d'écran, navigateurs autres que Chromium, menu mobile au clavier : non testés.
- **Pages non maquettées** : réponses types, recherche avancée, tâches, utilisateurs et organisations, base de connaissances côté agent, profil, tous les autres écrans d'administration (sujets d'aide, plans de SLA, plugins, e-mails…), états vides et d'erreur hors connexion.
- Anglais et packs de langue : non maquettés.
- **Contenus inventés pour l'illustration** : clients, tickets, numéros, agents et leurs rôles, chiffres du tableau de bord, la règle de blocage à la connexion (« 3 essais, blocage de 5 minutes »), les réglages de la page Tickets, le délai d'alerte « 75 % ». À valider avant tout usage.

## Si cette proposition est retenue

Le panneau agent d'osTicket n'a pas de mécanisme de thème (`scp/css/scp.css` et les gabarits de `include/staff/` sont chargés en dur par `include/staff/header.inc.php`). Les thèmes du portail passent par un overlay Docker qui remplace des fichiers du cœur ; la même méthode s'appliquerait ici, avec un thème par espace. Plusieurs éléments demandent du développement : le rail (l'en-tête actuel est un menu horizontal à deux niveaux), la jauge de premier retour (données du plugin `sla_first_response`), les modes de la zone de saisie, la barre d'enregistrement. Estimation à établir après validation du parti pris : l'ampleur dépend surtout du nombre d'écrans d'administration repris.
