// Donnees fictives, icones et petits composants partages par les 5 generateurs de maquettes Coderise.
// Tout le contenu est fictif (client, tickets, agents, numeros). Les coordonnees de l'entreprise (pied de page) sont celles de la charte.

export const PAGES = [
  { file: 'index.html', key: 'home', label: 'Accueil', shot: 'accueil' },
  { file: 'ouvrir-un-ticket.html', key: 'open', label: 'Ouverture de ticket (avec erreurs)', shot: 'ouverture-ticket' },
  { file: 'connexion.html', key: 'login', label: 'Connexion (avec erreur)', shot: 'connexion' },
  { file: 'inscription.html', key: 'register', label: 'Inscription', shot: 'inscription' },
  { file: 'inscription-confirmation.html', key: 'registered', label: 'Inscription : confirmation', shot: 'inscription-confirmation' },
  { file: 'suivi-ticket.html', key: 'ticket', label: 'Suivi d\'un ticket', shot: 'suivi-ticket' },
  { file: 'mes-tickets.html', key: 'tickets', label: 'Mes tickets', shot: 'mes-tickets' },
  { file: 'mes-tickets-vide.html', key: 'ticketsEmpty', label: 'Mes tickets : état vide', shot: 'mes-tickets-vide' },
  { file: 'base-de-connaissances.html', key: 'kb', label: 'Base de connaissances', shot: 'base-de-connaissances' },
  { file: 'article.html', key: 'article', label: 'Article', shot: 'article' },
];

// Typographie francaise : espace insecable avant ? ! : ; » et apres «.
export function fr(html) {
  // ne touche pas aux balises ni aux attributs : on traite uniquement le texte entre balises
  return html.replace(/(>)([^<]+)(?=<)/g, (m, g, t) =>
    g + t.replace(/ ([?!;:»€%])/g, '&nbsp;$1').replace(/« /g, '«&nbsp;').replace(/(\d) (Ko|Mo|h|min|jours?|px)\b/g, '$1&nbsp;$2'));
}

export const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// Icones : style ligne, trait constant, angles nets et coins legerement arrondis, grille carree 24.
const ICONS = {
  ticket: '<path d="M4 7.5A1.5 1.5 0 0 1 5.5 6h13A1.5 1.5 0 0 1 20 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 16.5V14a2 2 0 0 0 0-4z"/><path d="M14.5 6v12"/>',
  book: '<path d="M5 5a2 2 0 0 1 2-2h12v15H7a2 2 0 0 0-2 2z"/><path d="M5 20a1 1 0 0 0 1 1h13v-3"/>',
  home: '<path d="m4 11 8-7 8 7"/><path d="M6 10v10h4v-6h4v6h4V10"/>',
  search: '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  clip: '<path d="M20 11.5 12 19.5a5 5 0 0 1-7-7l8-8a3.4 3.4 0 0 1 4.8 4.8l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/>',
  upload: '<path d="M12 16V4m-5 5 5-5 5 5M4 20h16"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-6.5 8-6.5s8 2.300 8 6.500"/>',
  users: '<circle cx="9" cy="8" r="3.500"/><path d="M2.500 20c0-3.600 2.800-5.500 6.500-5.500s6.500 1.900 6.500 5.500M16 4.800a3.500 3.500 0 0 1 0 6.400M18 14.800c2.200.700 3.500 2.400 3.500 5.200"/>',
  lock: '<rect x="5" y="11" width="14" height="9.500" rx="1.500"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="1.500"/><path d="m3.500 7 8.500 6 8.500-6"/>',
  clock: '<circle cx="12" cy="12" r="8.500"/><path d="M12 7v5l3 2"/>',
  check: '<path d="m5 12.500 4.500 4.500L19 7"/>',
  checkc: '<circle cx="12" cy="12" r="8.500"/><path d="m8 12.200 3 3 5-5.700"/>',
  alert: '<path d="M12 4 3 19.500h18z"/><path d="M12 10v4.500M12 17.200h.01"/>',
  info: '<circle cx="12" cy="12" r="8.500"/><path d="M12 11v5M12 8h.01"/>',
  chevr: '<path d="m9 6 6 6-6 6"/>',
  chevd: '<path d="m6 9 6 6 6-6"/>',
  arrow: '<path d="M4 12h15m-6-6 6 6-6 6"/>',
  file: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
  folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  bolt: '<path d="m13 3-8 11h6l-1 7 8-11h-6z"/>',
  shield: '<path d="M12 3 4.500 6v6c0 4.500 3 7.500 7.500 9 4.500-1.500 7.500-4.500 7.500-9V6z"/><path d="m9 12 2.200 2.200L15.500 10"/>',
  phone: '<path d="M5.500 4h3.500l1.800 4.500-2.300 1.500a11 11 0 0 0 5.500 5.500l1.500-2.300 4.500 1.800v3.500a1.800 1.800 0 0 1-2 1.800A16 16 0 0 1 3.700 6 1.800 1.800 0 0 1 5.500 4z"/>',
  pin: '<path d="M12 21s7-6 7-11.500a7 7 0 0 0-14 0C5 15 12 21 12 21z"/><circle cx="12" cy="9.500" r="2.500"/>',
  bot: '<rect x="5" y="8" width="14" height="10" rx="2"/><path d="M12 4v4M9 13h.01M15 13h.01M3 12v3M21 12v3"/>',
  rocket: '<path d="M12 3c3 2 4.800 5 4.800 8.500L15 15H9l-1.800-3.500C7.200 8 9 5 12 3z"/><path d="m9 15-2 4M15 15l2 4M12 9.500h.01"/>',
  terminal: '<rect x="3" y="4.500" width="18" height="15" rx="1.500"/><path d="m7 10 3 2-3 2M13 15h4"/>',
  filter: '<path d="M4 5h16l-6 8v6l-4-2v-4z"/>',
  print: '<path d="M7 9V3.500h10V9M7 17H4v-7h16v7h-3M7 14h10v6.500H7z"/>',
  edit: '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>',
  flag: '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
  eye: '<path d="M2.500 12S6 5.500 12 5.500 21.500 12 21.500 12 18 18.500 12 18.500 2.500 12 2.500 12z"/><circle cx="12" cy="12" r="2.800"/>',
  list: '<path d="M9 6h11M9 12h11M9 18h11M4.500 6h.01M4.500 12h.01M4.500 18h.01"/>',
  bold: '<path d="M7 4h6a3.500 3.500 0 0 1 0 7H7zM7 11h7a3.500 3.500 0 0 1 0 7H7z"/>',
  italic: '<path d="M10 4h8M6 20h8M14 4 10 20"/>',
  link: '<path d="M10 14a4 4 0 0 0 5.700 0l3-3a4 4 0 0 0-5.700-5.700l-1 1M14 10a4 4 0 0 0-5.700 0l-3 3a4 4 0 0 0 5.700 5.700l1-1"/>',
  image: '<rect x="3" y="5" width="18" height="14" rx="1.500"/><circle cx="9" cy="10" r="1.500"/><path d="m4 18 5-5 4 4 3-3 4 4"/>',
  code: '<path d="m8 8-5 4 5 4m8-8 5 4-5 4M14 5l-4 14"/>',
  refresh: '<path d="M20 11a8 8 0 0 0-14.500-4M4 4v4h4M4 13a8 8 0 0 0 14.500 4M20 20v-4h-4"/>',
  calendar: '<rect x="4" y="5" width="16" height="16" rx="1.500"/><path d="M4 10h16M8 3v4M16 3v4"/>',
  message: '<path d="M4 5.500A1.500 1.500 0 0 1 5.500 4h13A1.500 1.500 0 0 1 20 5.500v9a1.500 1.500 0 0 1-1.500 1.500H9l-5 4z"/>',
  inbox: '<path d="m4 13 2-8h12l2 8v6H4z"/><path d="M4 13h5l1 2h4l1-2h5"/>',
  menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
  login: '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4M10 8l4 4-4 4M14 12H4"/>',
  help: '<circle cx="12" cy="12" r="8.500"/><path d="M9.500 9.500a2.500 2.500 0 1 1 3.500 2.300c-.700.400-1 .900-1 1.700M12 17h.01"/>',
  star: '<path d="m12 4 2.400 5 5.400.7-4 3.800 1 5.400-4.800-2.700L7.200 19l1-5.400-4-3.800L9.600 9z"/>',
  mobile: '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>',
  globe: '<circle cx="12" cy="12" r="8.500"/><path d="M3.500 12h17M12 3.500c3 3 3 14 0 17M12 3.500c-3 3-3 14 0 17"/>',
  server: '<rect x="4" y="4" width="16" height="6.500" rx="1.500"/><rect x="4" y="13.500" width="16" height="6.500" rx="1.500"/><path d="M8 7.250h.01M8 16.750h.01"/>',
  cpu: '<rect x="6" y="6" width="12" height="12" rx="1.500"/><path d="M9.500 9.500h5v5h-5zM9 3v3M15 3v3M9 18v3M15 18v3M3 9h3M3 15h3M18 9h3M18 15h3"/>',
  linkedin: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10.500V16M8 7.800h.01M12 16v-5.500M12 13a2.500 2.500 0 0 1 5 0v3"/>',
  github: '<path d="M9 19c-4 1.200-4-2-6-2.500M15 21v-3.200a3 3 0 0 0-.9-2.400c3-.3 6-1.500 6-6.600a5 5 0 0 0-1.300-3.400 4.600 4.600 0 0 0-.1-3.400s-1.100-.3-3.600 1.400a12.300 12.300 0 0 0-6.200 0C6.400 3.200 5.300 3.500 5.300 3.500a4.600 4.600 0 0 0-.1 3.400A5 5 0 0 0 3.900 10.300c0 5.100 3 6.300 6 6.600a3 3 0 0 0-.9 2.400V21"/>',
  key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 7l3 3"/>',
  x: '<path d="m6 6 12 12M18 6 6 18"/>',
  sliders: '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
  layers: '<path d="m12 3.500 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
  ban: '<circle cx="12" cy="12" r="8.500"/><path d="m6 6 12 12"/>',
};
export function icon(name, cls = 'ic', size = 20) {
  const body = (ICONS[name] || '').replace(/(\d)\.(\d)00\b/g, '$1.$2').replace(/(\d)\.500\b/g, '$1.5');
  return `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${body}</svg>`;
}

// Motif « Sommet » : le chevron du symbole, isole (clip-path, jamais etire ni incline). Reconstruit d'apres la geometrie du symbole.
export const sommet = (cls = '') => `<span class="sommet ${cls}" aria-hidden="true"></span>`;

// ---------- Donnees fictives ----------
export const client = {
  name: 'Léa Marchand', first: 'Léa', initials: 'LM',
  email: 'lea.marchand@maison-verdier.fr', org: 'Maison Verdier',
};
export const team = [
  { name: 'Hugo Lefèvre', first: 'Hugo', initials: 'HL', role: 'Ingénieur support' },
  { name: 'Nora Benali', first: 'Nora', initials: 'NB', role: 'Lead développeuse' },
  { name: 'Sacha Roussel', first: 'Sacha', initials: 'SR', role: 'Ingénieur DevOps' },
];

export const thread = [
  {
    type: 'message', who: client.name, initials: 'LM', role: 'Vous', at: '21/10/2026 08:47', day: '21 oct.', time: '08:47',
    body: [
      'Bonjour,',
      'Depuis la mise en production d\'hier soir (API v2.8.0), l\'application mobile n\'affiche plus la liste des commandes dans nos magasins : les équipes voient un écran vide. Le reste de l\'application fonctionne.',
      'C\'est bloquant pour les 12 magasins à partir de 9 h. Vous trouverez une capture d\'écran et un extrait du journal en pièces jointes.',
      'Merci,<br>Léa',
    ],
    files: [['ecran-commandes-vide.png', '212 Ko'], ['journal-api-21-10.log', '38 Ko']],
  },
  {
    type: 'response', who: team[0].name, initials: 'HL', role: 'Équipe Coderise', at: '21/10/2026 09:31', day: '21 oct.', time: '09:31', first: true,
    body: [
      'Bonjour Léa, nous avons reproduit l\'incident en 10 minutes. Cause identifiée : la v2.8.0 renvoie le champ <code>statut</code> sous forme de nombre au lieu d\'un texte, et l\'application attend un texte.',
      'Nous préparons un correctif côté API (v2.8.1), sans nouvelle version de l\'application. Vous n\'avez rien à faire pour l\'instant.',
      'Prochain point dans 45 minutes, ou plus tôt si le correctif est prêt.',
      'Hugo, équipe Coderise',
    ],
    files: [],
  },
  {
    type: 'event', text: 'Hugo Lefèvre a passé la priorité à Urgente', at: '21/10/2026 09:33',
  },
  {
    type: 'message', who: client.name, initials: 'LM', role: 'Vous', at: '21/10/2026 10:05', day: '21 oct.', time: '10:05',
    body: ['Merci pour la réactivité. Nous gardons la bascule sur l\'ancienne liste papier jusqu\'à nouvel ordre. Dites-moi quand je peux faire tester une boutique pilote.'],
    files: [],
  },
  {
    type: 'response', who: team[1].name, initials: 'NB', role: 'Équipe Coderise', at: '21/10/2026 11:20', day: '21 oct.', time: '11:20',
    body: [
      'Bonjour Léa, la v2.8.1 est déployée en recette. Pouvez-vous vérifier avec la boutique d\'Aix (compte de test <code>aix-recette</code>) ? Si c\'est bon, nous passons en production à 14 h.',
      '<pre><code>GET /v2/commandes?magasin=aix\n→ 200 OK · "statut": "en_preparation"</code></pre>',
      'Nous restons disponibles sur ce ticket. Pour un point rapide, appelez-nous : le numéro est en pied de page.',
      'Nora, équipe Coderise',
    ],
    files: [['correctif-2-8-1-notes.pdf', '96 Ko']],
  },
];

export const ticket = {
  number: '260418',
  subject: 'Application mobile : la liste des commandes est vide depuis l\'API v2.8.0',
  status: 'Ouvert',
  priority: 'Urgente',
  plan: 'Priority',
  dept: 'Support Priority',
  created: '21/10/2026 08:47',
  last: '21/10/2026 11:20',
  topic: 'Incident en production',
  product: 'Verdier Commandes (application mobile)',
  firstResponse: '44 min',
  custom: [
    ['Produit', 'Verdier Commandes (application mobile)'],
    ['Environnement', 'Production'],
    ['Impact', 'Bloquant'],
    ['Version concernée', 'API v2.8.0'],
    ['Début de l\'incident', '20/10/2026 21:30'],
  ],
};

export const tickets = [
  { n: '260418', date: '21/10/2026', status: 'Ouvert', plan: 'Priority', subject: 'Application mobile : la liste des commandes est vide depuis l\'API v2.8.0', product: 'Verdier Commandes', topic: 'Incident en production', last: 'Réponse de Nora, il y a 3 h', from: 'team' },
  { n: '260397', date: '16/10/2026', status: 'Ouvert', plan: 'Standard', subject: 'Ajouter un export CSV dans le back-office des magasins', product: 'Back-office web', topic: 'Demande d\'évolution', last: 'En attente de votre réponse', from: 'you' },
  { n: '260352', date: '08/10/2026', status: 'Ouvert', plan: 'Priority', subject: 'Agent de tri des réclamations : classer aussi les e-mails en anglais', product: 'Agent de tri des réclamations', topic: 'Demande d\'évolution', last: 'Réponse de Sacha, hier', from: 'team' },
  { n: '260201', date: '22/09/2026', status: 'Fermé', plan: 'Standard', subject: 'Facture 2026-0142 : changer l\'adresse de facturation', product: 'Contrat', topic: 'Facturation et contrat', last: 'Fermé le 23/09/2026', from: 'none' },
  { n: '260144', date: '09/09/2026', status: 'Fermé', plan: 'Priority', subject: 'Notification push en double sur iOS 19', product: 'Verdier Commandes', topic: 'Incident en production', last: 'Fermé le 10/09/2026', from: 'none' },
  { n: '259876', date: '28/08/2026', status: 'Fermé', plan: 'Standard', subject: 'Accès SSH au serveur de recette pour un prestataire', product: 'Hébergement', topic: 'Accès et sécurité', last: 'Fermé le 29/08/2026', from: 'none' },
];

export const categories = [
  { id: 1, name: 'Premiers pas avec le support', icon: 'rocket', desc: 'Choisir son niveau de support, écrire un bon ticket, savoir à quoi vous attendre.', count: 7 },
  { id: 2, name: 'Applications web', icon: 'globe', desc: 'Déploiements, environnements, performances et sauvegardes de vos applications.', count: 12 },
  { id: 3, name: 'Applications mobiles', icon: 'mobile', desc: 'Publication sur les stores, mises à jour, notifications et versions minimales.', count: 9 },
  { id: 4, name: 'Agents IA et automatisations', icon: 'bot', desc: 'Surveiller un agent, ajuster ses consignes, comprendre ses décisions.', count: 8 },
  { id: 5, name: 'Mise en production', icon: 'server', desc: 'Calendrier de livraison, retours arrière, journaux et sauvegardes.', count: 6 },
  { id: 6, name: 'Contrats et facturation', icon: 'file', desc: 'Support standard et Priority, factures, renouvellements.', count: 5 },
  { id: 7, name: 'Sécurité et conformité', icon: 'shield', desc: 'Accès, clés d\'API, RGPD, journalisation et sous-traitants.', count: 7 },
];

export const featured = [
  ['Support standard ou Priority : lequel choisir', 'Premiers pas avec le support', '4 min'],
  ['Signaler un incident en production : la marche à suivre', 'Premiers pas avec le support', '5 min'],
  ['Que mettre dans un ticket pour être aidé plus vite', 'Premiers pas avec le support', '3 min'],
  ['Faire tourner une clé d\'API sans coupure de service', 'Sécurité et conformité', '6 min'],
  ['Lire le journal de déploiement d\'une livraison', 'Mise en production', '5 min'],
  ['Surveiller un agent autonome : ce qu\'il faut regarder', 'Agents IA et automatisations', '7 min'],
];

export const topics = ['Incident en production', 'Question technique', 'Demande d\'évolution', 'Facturation et contrat', 'Accès et sécurité'];
export const products = [
  'Verdier Commandes (app mobile), Priority',
  'Back-office web des magasins, Standard',
  'Agent de tri des réclamations (IA), Priority',
  'Plugin Heatlens, licence HL-2026-0831',
];

// ---------- Composants ----------
export const CONTACT = {
  email: 'contact@coderise.fr', phone: '+33 (0)6 69 93 92 86', phoneHref: '+33669939286', city: 'Aix-en-Provence',
  hours: 'lundi au vendredi, 8h30 à 17h00', linkedin: 'linkedin.com/company/coderise-fr', github: 'github.com/coderise-fr',
};

export const signature = (cls = '') => `<p class="signature ${cls}">Le code qui fait <span class="up">décoller</span> vos projets</p>`;

export const logoH = (cls = 'logo') => `<img class="${cls}" src="../_shared/img/logo-horizontal-sombre.png" width="2041" height="345" alt="Coderise">`;
export const logoStackedLight = (cls = 'logo-stack') => `<img class="${cls}" src="../_shared/img/logo-complet-clair.png" width="704" height="566" alt="Coderise">`;
export const logoStackedDark = (cls = 'logo-stack') => `<img class="${cls}" src="../_shared/img/logo-complet-sombre.png" width="737" height="592" alt="Coderise">`;
export const symbolDark = (cls = 'symbol') => `<img class="${cls}" src="../_shared/img/symbole-sombre.png" width="643" height="359" alt="">`;
export const symbolLight = (cls = 'symbol') => `<img class="${cls}" src="../_shared/img/symbole-clair.png" width="615" height="345" alt="">`;

export const BACK_LINK = '<a class="mockup-back" href="../index.html">Retour à la comparaison des maquettes</a>';

export function doc({ title, css = 'theme.css', body, bodyClass = '', lang = 'fr' }) {
  return fr(`<!doctype html>
<html lang="${lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)} | Support Coderise</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#000F26">
<link rel="icon" href="../_shared/img/favicon-64.png" type="image/png">
<link rel="stylesheet" href="../_shared/fonts.css">
<link rel="stylesheet" href="../_shared/tokens.css">
<link rel="stylesheet" href="${css}">
</head>
<body class="${bodyClass}">
${BACK_LINK}
<a class="skip" href="#content">Aller au contenu</a>
${body}
</body>
</html>
`);
}

// Pied de page : memes informations dans les 5 directions (le style change). Rappel des horaires : ne pas melanger entreprise et support.
export function footerInfo({ logo = true } = {}) {
  return {
    brand: `${logo ? logoH('logo') : ''}${signature()}`,
    contact: `<h2 class="f-title">Nous contacter</h2><ul class="f-list">
<li>${icon('mail', 'ic', 18)}<a href="mailto:${CONTACT.email}">${CONTACT.email}</a></li>
<li>${icon('phone', 'ic', 18)}<a href="tel:${CONTACT.phoneHref}">${CONTACT.phone}</a></li>
<li>${icon('pin', 'ic', 18)}<span>${CONTACT.city}</span></li></ul>`,
    support: `<h2 class="f-title">Délais de réponse</h2><ul class="f-plain">
<li><strong>Support standard :</strong> jours ouvrés.</li>
<li><strong>Support Priority :</strong> premier retour sous 4 h, 24 h/24, 7 j/7.</li>
<li class="f-note">Les horaires de l'entreprise (${CONTACT.hours}) ne sont pas ceux du support Priority.</li></ul>`,
    links: `<h2 class="f-title">Suivre Coderise</h2><ul class="f-list">
<li>${icon('linkedin', 'ic', 18)}<a href="https://${CONTACT.linkedin}">LinkedIn</a></li>
<li>${icon('github', 'ic', 18)}<a href="https://${CONTACT.github}">GitHub</a></li>
<li>${icon('globe', 'ic', 18)}<a href="https://www.coderise.fr">www.coderise.fr</a></li></ul>`,
    legal: `<p>© 2026 Coderise. Portail de support, propulsé par <a href="https://osticket.com">osTicket</a>.</p>`,
  };
}

// Champ de formulaire (balisage commun ; la mise en page est propre a chaque direction).
export function field({ id, name, label, type = 'text', required = false, error = '', hint = '', value = '', options = null, rows = 0, readonly = false, placeholder = '', cls = '', dynamic = false, checked = false }) {
  const describe = [hint ? `${id}-hint` : '', error ? `${id}-err` : ''].filter(Boolean).join(' ');
  const attrs = `id="${id}" name="${name || id}"${required ? ' required aria-required="true"' : ''}${error ? ' aria-invalid="true"' : ''}${describe ? ` aria-describedby="${describe}"` : ''}${readonly ? ' readonly' : ''}${placeholder ? ` placeholder="${esc(placeholder)}"` : ''}`;
  let control;
  if (options) {
    control = `<select ${attrs}>${options.map((o, i) => {
      const [v, t] = Array.isArray(o) ? o : [o, o];
      return `<option value="${esc(v)}"${(value === v || (!value && i === 0 && v === '')) ? ' selected' : ''}>${esc(t)}</option>`;
    }).join('')}</select>`;
  } else if (rows) {
    control = `<textarea ${attrs} rows="${rows}">${esc(value)}</textarea>`;
  } else if (type === 'checkbox') {
    control = `<label class="check"><input type="checkbox" ${attrs}${checked ? ' checked' : ''}><span>${label}</span></label>`;
    return `<div class="field field-check ${cls}">${control}${hint ? `<p class="hint" id="${id}-hint">${hint}</p>` : ''}</div>`;
  } else {
    control = `<input type="${type}" ${attrs} value="${esc(value)}">`;
  }
  return `<div class="field ${error ? 'has-error' : ''} ${cls}"${dynamic ? ' data-dynamic="1"' : ''}>
<label for="${id}">${label}${required ? ' <span class="req" aria-hidden="true">*</span>' : ''}</label>
<div class="control">${control}${hint ? `<p class="hint" id="${id}-hint">${hint}</p>` : ''}${error ? `<p class="error" id="${id}-err">${icon('alert', 'ic', 16)}<span>${error}</span></p>` : ''}</div>
</div>`;
}

export function errorSummary(list) {
  return `<div class="form-errors" role="alert"><h2>${icon('alert', 'ic', 20)} ${list.length} champs à corriger avant l'envoi</h2><ul>${list.map(([id, t]) => `<li><a href="#${id}">${t}</a></li>`).join('')}</ul></div>`;
}

// Editeur riche simule (memes classes que Redactor).
export function redactor({ id = 'message', label = 'Description', value = '', rows = 8, error = '', tools = ['bold', 'italic', 'list', 'link', 'image', 'code'] } = {}) {
  const names = { bold: 'Gras', italic: 'Italique', list: 'Liste', link: 'Lien', image: 'Image', code: 'Code' };
  return `<div class="redactor-box${error ? ' has-error' : ''}">
<div class="redactor-toolbar" role="toolbar" aria-label="Mise en forme">${tools.map((t) => `<button type="button" class="re-button re-${t}" aria-label="${names[t]}">${icon(t, 'ic', 16)}</button>`).join('')}</div>
<div class="redactor-editor" id="${id}" role="textbox" aria-multiline="true" aria-label="${esc(label)}"${error ? ` aria-invalid="true" aria-describedby="${id}-err"` : ''} tabindex="0" style="min-height:${rows * 1.55}em">${value}</div>
</div>`;
}

export function filedrop() {
  return `<div class="filedrop">
<div class="dropzone">${icon('upload', 'ic', 22)}<span>Déposez vos fichiers ici ou <a href="#" class="manual">parcourez votre ordinateur</a></span></div>
<p class="hint">Captures d'écran, journaux, exports. Retirez les données personnelles avant l'envoi. 20 Mo maximum par fichier.</p>
<ul class="files" aria-label="Fichiers ajoutés"><li>${icon('clip', 'ic', 16)}<span class="filename">ecran-commandes-vide.png</span> <small>212 Ko</small><button type="button" class="rm" aria-label="Retirer ecran-commandes-vide.png">${icon('x', 'ic', 14)}</button></li></ul>
</div>`;
}

export function fileChips(files) {
  if (!files || !files.length) return '';
  return `<div class="attachments">${files.map(([n, s]) => `<span class="attachment-info">${icon('clip', 'ic', 15)}<a class="filename" href="#">${esc(n)}</a><small class="filesize">${s}</small></span>`).join('')}</div>`;
}

// Indicateur SLA Priority. 24/7 : le delai court en continu. Donnee non native du portail (voir README).
export function slaBlock({ variant = 'bar' } = {}) {
  return `<div class="sla" data-needs-dev="sla" role="group" aria-label="Délai de premier retour, support Priority">
<p class="sla-title">${icon('clock', 'ic', 18)}<span>Premier retour sous 4 h (Priority, 24 h/24)</span></p>
<div class="sla-bar" role="img" aria-label="Premier retour apporté en 44 minutes sur 4 heures"><span class="sla-fill" style="width:18.3%"></span><span class="sla-mark" style="left:18.3%"></span></div>
<p class="sla-text"><strong>Apporté en 44 min</strong> le 21/10/2026 à 09:31. Objectif tenu.</p>
</div>`;
}

export const statusChip = (s) => {
  const k = s === 'Ouvert' ? 'open' : s === 'Résolu' ? 'resolved' : 'closed';
  const ic = s === 'Ouvert' ? 'inbox' : s === 'Résolu' ? 'checkc' : 'lock';
  return `<span class="status status-${k}">${icon(ic, 'ic', 14)}${s}</span>`;
};
export const planChip = (p) => `<span class="plan plan-${p === 'Priority' ? 'priority' : 'standard'}">${p === 'Priority' ? icon('bolt', 'ic', 13) : ''}${p}</span>`;
export const initialsAvatar = (ini, cls = '') => `<span class="avatar ${cls}" aria-hidden="true">${ini}</span>`;
