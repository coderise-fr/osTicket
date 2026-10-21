// Description des 5 propositions, partagee par index.html (genere) et le README (ecrit a la main, meme contenu).
export const proposals = [
  {
    id: '01', dir: '01-ticket-de-caisse', name: 'Ticket de caisse', tagline: 'Continuité stricte du site vitrine',
    swatches: ['#3B1E54', '#FBFBF6', '#CDB6E3', '#F4E04D', '#22201F'],
    intention: 'Le portail prolonge ownalytics.eu à l\'identique : fond prune, contenu imprimé sur du papier à bords déchirés, filets pointillés, ombres dures, surligneur jaune. Un client qui arrive depuis le site ne voit aucune couture, et le « ticket » de support redevient un vrai ticket.',
    pour: 'Clients qui viennent du site et de ses fiches produit ; équipe qui veut la cohérence de marque au plus bas coût.',
    niveau: 1, cout: 'Niveau 1 : CSS, logo, en-tête, pied de page (environ 2 jours). Une ligne de gabarit pour les pastilles de statut.',
    risques: ['Balisage en tableaux conservé : formulaires et fiche ticket neutralisés par CSS (display:block, grid sur table), fragile si un gabarit change.', 'Bords déchirés et accueil sur fond prune reposent sur ::before/::after et :has() (navigateurs récents).', 'thread.css d\'osTicket impose taille et couleur des liens du fil avec !important : à contrer.', 'Redactor : seule la barre d\'outils est restylée, l\'éditeur garde son comportement.'],
    tier: 'bas',
  },
  {
    id: '02', dir: '02-console', name: 'Console', tagline: 'Sobre, type documentation et console de support',
    swatches: ['#3B1E54', '#F3F1EA', '#FFFFFF', '#EFE9F5', '#3450A3'],
    intention: 'Une barre haute prune, des panneaux blancs, des filets fins et Archivo en graisse normale : le portail disparaît derrière l\'information. IBM Plex Mono ne sert qu\'aux numéros, dates et noms de fichier, et la base de connaissances prend un rail latéral de documentation.',
    pour: 'Administrateurs Matomo et équipes techniques qui lisent de longs fils avec journaux, commandes et pièces jointes.',
    niveau: 1, cout: 'Niveau 1 : CSS, en-tête (recherche globale), pied de page (environ 2 jours).',
    risques: ['Même dette de balisage que 01 : la fiche ticket en trois colonnes repose sur un grid appliqué à #ticketInfo > tbody.', 'La barre latérale « rail » de la base de connaissances est un simple réordonnancement CSS de .span4 / .span8.', 'L\'identité est la plus discrète : moins de personnalité, davantage de « thème générique ».', 'Pastilles de statut : une classe à ajouter dans tickets.inc.php et view.inc.php.'],
    tier: 'bas',
  },
  {
    id: '03', dir: '03-atelier', name: 'Atelier', tagline: 'Chaleureux, centré sur les personnes',
    swatches: ['#3B1E54', '#F6F1FB', '#FFD23F', '#FF4D8D', '#DFF5EA'],
    intention: 'Le prune d\'Ownalytics, adouci par les couleurs des plugins (soleil, rose, menthe), Bricolage Grotesque, cartes de 22 px, boutons-pastilles, avatars et conversation en bulles. Le portail dit « une personne vous répond » avant de dire « ticket ».',
    pour: 'Clients moins techniques, agences et indépendants, public qu\'il faut rassurer au moment de demander de l\'aide.',
    niveau: 2, cout: 'Niveau 2 : six gabarits, thread-entry, dynamic-form, en-tête et pied de page (environ 5 jours).',
    risques: ['La frise « Demande reçue, Prise en charge, Réponse reçue, Résolu » n\'est pas une donnée d\'osTicket (statuts Ouvert, Résolu, Fermé) : à simplifier ou à relier à une règle.', 'Prénoms et avatars d\'agents : osTicket peut masquer les noms (hideStaffName), l\'équipe affichée sur l\'accueil est une décision éditoriale.', 'Le formulaire d\'ouverture est recomposé en cartes : le conteneur #dynamic-form et les noms de champs doivent rester ceux de osTicket (rechargement AJAX par thème d\'aide).', 'Bulles : thread-entry.tmpl.php à réécrire ; les scripts qui s\'accrochent aux classes du fil (images en ligne) sont à retester.'],
    tier: 'haut',
  },
  {
    id: '04', dir: '04-poste-de-travail', name: 'Poste de travail', tagline: 'Dense, orienté productivité',
    swatches: ['#3B1E54', '#EEF1F8', '#FFFFFF', '#3450A3', '#FFD23F'],
    intention: 'Un rail latéral, des tableaux serrés en Archivo resserré (axe de largeur à 92 %), un fil de discussion compact et un panneau de propriétés collant à droite. Cobalt d\'Auditlens pour les liens et les focus, prune pour l\'action. Tout est à un clic, rien n\'est décoratif.',
    pour: 'Clients qui ouvrent beaucoup de tickets (agences, revendeurs, organisations avec tickets partagés) et qui reviennent souvent.',
    niveau: 2, cout: 'Niveau 2 : six gabarits, rail de navigation, tableaux responsives (environ 6 jours).',
    risques: ['Le tableau de bord (compteurs, délai moyen de première réponse, activité) demande des requêtes que le portail n\'a pas et touche index.php, hors gabarits ; à défaut, accueil statique.', 'Les « vues » du rail n\'existent pas côté client : osTicket ne garde que les filtres de session.', 'Les articles suggérés pendant la saisie demandent un appel AJAX supplémentaire ; sans JavaScript, liste fixe.', 'Tableaux repliés en cartes sur mobile : à valider avec de vrais titres très longs.'],
    tier: 'haut',
  },
  {
    id: '05', dir: '05-une', name: 'Une', tagline: 'Éditorial, contrasté',
    swatches: ['#3B1E54', '#F4E04D', '#CDB6E3', '#FBFBF6', '#1C0B2B'],
    intention: 'Le support lu comme la une d\'un journal : titres Archivo extra-larges, aplats prune, lilas et jaune, angles droits, filets noirs de 3 px, écrans scindés pour ouvrir un ticket et se connecter. Le jaune, réservé aux totaux sur le site, devient la couleur d\'action.',
    pour: 'Une marque qui veut du caractère et des décideurs qui ouvrent peu de tickets ; moins adaptée aux listes longues.',
    niveau: 2, cout: 'Niveau 2 : six gabarits, en-tête, pied de page ; mise en page la plus éloignée de celle d\'osTicket (environ 6 jours).',
    risques: ['Sujets de ticket très longs : les titres XXL doivent rester lisibles (retours à la ligne et tailles à tester).', 'Le fil « correspondance » avec grande date impose de réécrire thread-entry.tmpl.php et d\'ajouter des classes de présentation.', 'Écran scindé du formulaire : le contenu injecté par AJAX dans #dynamic-form doit se plier à la même grille.', 'Le pied de page géant est décoratif : à retirer si la place manque sur mobile.'],
    tier: 'haut',
  },
];

export const pages = [
  ['index.html', 'Accueil', 'accueil'],
  ['ouvrir-un-ticket.html', 'Ouverture de ticket', 'ouverture-ticket'],
  ['connexion.html', 'Connexion', 'connexion'],
  ['suivi-ticket.html', 'Suivi d\'un ticket', 'suivi-ticket'],
  ['mes-tickets.html', 'Mes tickets', 'mes-tickets'],
  ['base-de-connaissances.html', 'Base de connaissances', 'base-de-connaissances'],
];
