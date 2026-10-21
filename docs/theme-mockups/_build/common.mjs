// Donnees fictives et petits utilitaires partages par les 5 generateurs de maquettes.
// Tout le contenu est fictif (clients, tickets, agents). Aucune donnee reelle.

export const PAGES = [
  { file: 'index.html', key: 'home', label: 'Accueil' },
  { file: 'ouvrir-un-ticket.html', key: 'open', label: 'Ouverture de ticket' },
  { file: 'connexion.html', key: 'login', label: 'Connexion' },
  { file: 'suivi-ticket.html', key: 'ticket', label: 'Suivi d\'un ticket' },
  { file: 'mes-tickets.html', key: 'tickets', label: 'Mes tickets' },
  { file: 'base-de-connaissances.html', key: 'kb', label: 'Base de connaissances' },
];

// Typographie francaise : espace insecable avant ? ! : ; » et apres «, avant €.
export function fr(html) {
  return html
    .replace(/ ([?!;:»€%])/g, '&nbsp;$1')
    .replace(/« /g, '«&nbsp;')
    .replace(/(\d) (Ko|Mo|h|min|jours?)\b/g, '$1&nbsp;$2');
}

export const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const ICONS = {
  clip: '<path d="M14.5 7.5 8 14a2.5 2.5 0 0 0 3.5 3.5l7-7a4.5 4.5 0 0 0-6.4-6.4l-7 7a6.5 6.5 0 0 0 9.2 9.2"/>',
  search: '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
  folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  file: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
  chevron: '<path d="m9 6 6 6-6 6"/>',
  refresh: '<path d="M20 11a8 8 0 0 0-14.5-4M4 4v4h4M4 13a8 8 0 0 0 14.5 4M20 20v-4h-4"/>',
  print: '<path d="M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z"/>',
  edit: '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>',
  clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
  check: '<path d="m5 12.5 4.5 4.5L19 7"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  ticket: '<path d="M4 6h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
  book: '<path d="M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3zM8 20a3 3 0 0 1-3-3"/>',
  home: '<path d="M4 11 12 4l8 7v9h-5v-6H9v6H4z"/>',
  bolt: '<path d="m13 3-8 11h6l-1 7 8-11h-6z"/>',
  lock: '<rect x="5" y="11" width="14" height="9"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
  mail: '<rect x="3" y="5" width="18" height="14"/><path d="m3 7 9 7 9-7"/>',
  sort: '<path d="m8 9 4-4 4 4M8 15l4 4 4-4"/>',
  upload: '<path d="M12 16V4m-5 5 5-5 5 5M4 20h16"/>',
  arrow: '<path d="M4 12h15m-6-6 6 6-6 6"/>',
  bold: '<path d="M7 4h6a3.5 3.5 0 0 1 0 7H7zM7 11h7a3.5 3.5 0 0 1 0 7H7z"/>',
  italic: '<path d="M10 4h8M6 20h8M14 4 10 20"/>',
  list: '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
  link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
  image: '<rect x="3" y="5" width="18" height="14"/><circle cx="9" cy="10" r="1.5"/><path d="m4 18 5-5 4 4 3-3 4 4"/>',
  code: '<path d="m8 8-5 4 5 4m8-8 5 4-5 4M14 5l-4 14"/>',
};
export function icon(name, cls = 'ic', size = 18) {
  return `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false">${ICONS[name] || ''}</svg>`;
}

// Pictogramme du ticket de caisse, identique a celui du site (wordmark).
export function receiptMark(fill = '#FBFBF6', line = '#3B1E54', w = 20, h = 26) {
  return `<svg class="mark" width="${w}" height="${h}" viewBox="0 0 20 26" aria-hidden="true" focusable="false"><path d="M1 1h18v21l-3 3-3-3-3 3-3-3-3 3-3-3z" fill="${fill}"/><path d="M5 7h10M5 11h6M5 15h10" stroke="${line}" stroke-width="2"/></svg>`;
}

export const client = {
  name: 'Camille Roux',
  first: 'Camille',
  email: 'camille.roux@atelier-lumen.fr',
  phone: '04 42 00 00 00',
  org: 'Atelier Lumen',
};

export const agent = { name: 'Inès Moreau', first: 'Inès', initials: 'IM' };

export const thread = [
  {
    type: 'message', who: client.name, initials: 'CR', at: '14/10/2026 09:12', day: '14 oct.', time: '09:12',
    body: [
      'Bonjour,',
      'Depuis la mise à jour de notre Matomo en 5.14.2 hier soir, plus aucune heatmap ne se remplit sur le site de notre client. Le suivi des sessions, lui, continue de fonctionner : les enregistrements apparaissent bien.',
      'Le code de suivi est toujours en place et nous n\'avons rien changé côté Heatlens. Vous trouverez en pièces jointes une capture d\'une heatmap vide et un extrait du journal de Matomo.',
      'Merci d\'avance,<br>Camille',
    ],
    files: [['capture-heatmap-vide.png', '84 Ko'], ['matomo-extrait.log', '12 Ko']],
  },
  {
    type: 'response', who: agent.name, initials: 'IM', at: '14/10/2026 10:03', day: '14 oct.', time: '10:03', first: true,
    body: [
      'Bonjour Camille,',
      'Merci pour les captures, elles nous aident beaucoup. Dans l\'extrait de journal, la tâche d\'archivage des heatmaps s\'arrête avec une erreur de mémoire juste après la mise à jour : c\'est la cause la plus probable.',
      'Pouvez-vous lancer l\'archivage à la main depuis le serveur, puis nous dire ce qu\'il affiche ?',
      '<code>php console core:archive --force-all-websites --url=https://matomo.atelier-lumen.fr</code>',
      'Pensez à anonymiser les adresses IP dans ce que vous nous envoyez.',
      'Inès, support Ownalytics',
    ],
    files: [],
  },
  {
    type: 'message', who: client.name, initials: 'CR', at: '14/10/2026 14:40', day: '14 oct.', time: '14:40',
    body: [
      'Merci pour la piste. J\'ai lancé la commande : elle va jusqu\'au bout cette fois, après avoir passé la limite de mémoire PHP à 512 Mo. Les nouvelles visites remplissent les heatmaps depuis 13 h, mais les données d\'avant la mise à jour restent vides.',
      'La sortie de la commande est en pièce jointe.',
    ],
    files: [['sortie-archivage.txt', '3 Ko']],
  },
  {
    type: 'response', who: agent.name, initials: 'IM', at: '15/10/2026 09:20', day: '15 oct.', time: '09:20',
    body: [
      'Bonne nouvelle, et merci pour ce retour précis. Les données d\'avant la mise à jour ne sont pas perdues : elles sont dans votre base, mais il faut les archiver de nouveau. Le guide joint explique comment relancer l\'archivage sur la période du 1er au 13 octobre.',
      'Dites-nous si les anciennes heatmaps réapparaissent. Sans nouvelle de votre part, nous garderons le ticket ouvert jusqu\'à lundi.',
      'Inès',
    ],
    files: [['guide-reprise-archivage.pdf', '212 Ko']],
  },
];

export const ticket = {
  number: '482913',
  subject: 'Heatlens : les heatmaps restent vides après la mise à jour vers Matomo 5.14.2',
  status: 'Ouvert',
  dept: 'Support Ownalytics',
  created: '14/10/2026 09:12',
  topic: 'Installation et compatibilité',
  custom: [
    ['Plugin concerné', 'Heatlens'],
    ['Version de Matomo', '5.14.2'],
    ['Adresse de votre Matomo', 'https://matomo.atelier-lumen.fr'],
    ['Hébergement', 'Serveur dédié'],
  ],
};

export const tickets = [
  { n: '482913', date: '14/10/2026', status: 'Ouvert', subject: 'Heatlens : les heatmaps restent vides après la mise à jour vers Matomo 5.14.2', dept: 'Support', answered: true, rel: 'Réponse d\'Inès il y a 6 jours', topic: 'Installation et compatibilité' },
  { n: '482755', date: '12/10/2026', status: 'Ouvert', subject: 'Facture 2026-0142 : changer l\'adresse de facturation', dept: 'Facturation', answered: false, rel: 'Envoyé, en attente de réponse', topic: 'Licences et facturation' },
  { n: '481990', date: '06/10/2026', status: 'Ouvert', subject: 'Auditlens : exporter le journal d\'audit en CSV', dept: 'Support', answered: true, rel: 'Réponse de Théo il y a 13 jours', topic: 'Auditlens' },
  { n: '480417', date: '22/09/2026', status: 'Fermé', subject: 'Mise à niveau de licence vers Matomo 6', dept: 'Facturation', answered: false, rel: 'Fermé le 24/09/2026', topic: 'Licences et facturation' },
  { n: '479802', date: '09/09/2026', status: 'Fermé', subject: 'Replay de sessions : masquer les champs de formulaire', dept: 'Support', answered: false, rel: 'Fermé le 10/09/2026', topic: 'Heatlens' },
  { n: '478115', date: '28/08/2026', status: 'Fermé', subject: 'Licence de préproduction : ce qui est inclus', dept: 'Facturation', answered: false, rel: 'Fermé le 28/08/2026', topic: 'Licences et facturation' },
];

export const categories = [
  { id: 1, name: 'Premiers pas', desc: 'Installer un plugin, activer sa licence, mettre à jour sans perdre de données.', count: 8, faqs: ['Installer Heatlens sur un Matomo auto-hébergé', 'Où trouver mon numéro de licence', 'Mettre à jour un plugin sans perdre de données'] },
  { id: 2, name: 'Heatlens', desc: 'Heatmaps, profondeur de défilement et replay de sessions.', count: 14, faqs: ['Pourquoi ma heatmap reste vide', 'Masquer les champs de formulaire dans les replays', 'Comprendre la profondeur de défilement'] },
  { id: 3, name: 'Auditlens', desc: 'Le journal qui dit qui a modifié quoi dans votre Matomo.', count: 9, faqs: ['Quelles activités Matomo sont journalisées', 'Exporter le journal d\'audit en CSV', 'Durée de conservation du journal'] },
  { id: 4, name: 'Licences et facturation', desc: 'Éditions, mises à niveau, factures et instance de préproduction.', count: 7, faqs: ['Changer l\'adresse de facturation', 'Passer à l\'édition Matomo 6 : tarif de mise à niveau', 'Licence de préproduction : ce qui est inclus'] },
  { id: 5, name: 'Compatibilité Matomo', desc: 'Versions de Matomo, de PHP et de MariaDB prises en charge.', count: 6, faqs: ['Versions de Matomo prises en charge', 'Où en est la compatibilité avec Matomo 6', 'Version minimale de PHP requise'] },
  { id: 6, name: 'Support et délais', desc: 'Ce que couvre le support, et comment obtenir une réponse vite.', count: 4, faqs: ['Support inclus et support prioritaire', 'Qu\'est-ce qu\'une première réponse', 'Que mettre dans un ticket pour aller plus vite'] },
];

export const featured = [
  'Pourquoi ma heatmap reste vide',
  'Où trouver mon numéro de licence',
  'Exporter le journal d\'audit en CSV',
  'Support inclus et support prioritaire',
  'Que mettre dans un ticket pour aller plus vite',
];

export const topics = [
  'Installation et compatibilité', 'Heatlens', 'Auditlens', 'Licences et facturation', 'Autre question',
];

export const nav = (active, logged) => [
  { key: 'home', href: 'index.html', label: 'Accueil', cls: 'home' },
  { key: 'kb', href: 'base-de-connaissances.html', label: 'Base de connaissances', cls: 'kb' },
  { key: 'open', href: 'ouvrir-un-ticket.html', label: 'Ouvrir un nouveau ticket', cls: 'new' },
  logged
    ? { key: 'tickets', href: 'mes-tickets.html', label: 'Tickets (3)', cls: 'tickets' }
    : { key: 'login', href: 'connexion.html', label: 'Suivre un ticket', cls: 'status' },
].map((n) => ({ ...n, active: n.key === active || (active === 'ticket' && n.key === 'tickets') }));

export const BACK_LINK = '<a class="mockup-back" href="../index.html">Retour à la comparaison des maquettes</a>';

export function doc({ title, lang = 'fr', css, body, bodyClass = '', extraHead = '' }) {
  return fr(`<!doctype html>
<html lang="${lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#3B1E54">
<link rel="icon" href="../_shared/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../_shared/fonts.css">
<link rel="stylesheet" href="${css}">
${extraHead}</head>
<body class="${bodyClass}">
${body}
</body>
</html>
`);
}

// Barre d'outils Redactor simulee (memes noms de classes que l'editeur d'osTicket).
export function redactor({ id = 'message', label = 'Message', value = '', rows = 8, tools = ['bold', 'italic', 'list', 'link', 'image', 'code'] } = {}) {
  const names = { bold: 'Gras', italic: 'Italique', list: 'Liste', link: 'Lien', image: 'Image', code: 'Code' };
  return `<div class="redactor-box">
  <div class="redactor-toolbar" role="toolbar" aria-label="Mise en forme">${tools.map((t) => `<button type="button" class="re-button re-${t}" aria-label="${names[t]}">${icon(t, 'ic', 16)}</button>`).join('')}</div>
  <div class="redactor-editor" id="${id}" role="textbox" aria-multiline="true" aria-label="${esc(label)}" contenteditable="true" style="min-height:${rows * 1.6}em">${value}</div>
</div>`;
}

export function filedrop(compact = false) {
  return `<div class="filedrop">
  <div class="dropzone">${icon('upload', 'ic', 18)} <span>Déposez des fichiers ici ou <a href="#" class="manual">choisissez-les</a>.</span></div>
  <p class="file-hint">Captures, journaux, exports. Anonymisez toujours les données de vos visiteurs avant l'envoi. Taille maximale : 20 Mo par fichier.</p>
</div>`;
}

export function fileChips(files) {
  if (!files || !files.length) return '';
  return `<div class="attachments">${files.map(([n, s]) => `<span class="attachment-info">${icon('clip', 'ic', 15)} <a class="filename" href="#">${esc(n)}</a> <small class="filesize faded">${s}</small></span>`).join('')}</div>`;
}
