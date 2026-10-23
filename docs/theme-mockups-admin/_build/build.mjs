// Génère les pages de la maquette « panneau agent et administration » (HTML statique, sans JavaScript).
// Tout le contenu est fictif. Icônes, typographie française et jeu de données de base viennent des maquettes du portail.
//   node docs/theme-mockups-admin/_build/build.mjs
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { icon as baseIcon, esc, fr } from '../../theme-mockups-coderise/_build/common.mjs';

const OUT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SH = '../theme-mockups-coderise/_shared';

// Icônes propres à l'interface agent (même grille 24, même trait que le jeu du portail).
const EXTRA = {
  gauge: '<path d="M4 18a8 8 0 1 1 16 0"/><path d="m12 18 4-5M7 18h.01M17 18h.01"/>',
  chart: '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
  tasks: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8 12 3 3 5-6"/>',
  building: '<rect x="5" y="3.5" width="14" height="17" rx="1.5"/><path d="M9 8h.01M15 8h.01M9 12h.01M15 12h.01M10 20.500v-4h4v4"/>',
  tag: '<path d="M4 4h8l8 8-8 8-8-8z"/><path d="M8 8h.01"/>',
  merge: '<path d="M7 4v6a5 5 0 0 0 5 5h5M17 15l-3-3M17 15l-3 3M7 20V10"/>',
  trash: '<path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13M10 11v6M14 11v6"/>',
  send: '<path d="m4 12 16-8-6 16-3-6.500z"/><path d="m11 13.500 3-3"/>',
  plug: '<path d="M9 3v5M15 3v5M6 8h12v3a6 6 0 0 1-12 0zM12 17v4"/>',
  userplus: '<circle cx="9" cy="8" r="3.500"/><path d="M2.500 20c0-3.600 2.800-5.500 6.500-5.500s6.500 1.900 6.500 5.500M18 8v6M15 11h6"/>',
  dots: '<path d="M6 12h.01M12 12h.01M18 12h.01"/>',
  sortd: '<path d="M12 5v14m-5-5 5 5 5-5"/>',
  logout: '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M16 8l4 4-4 4M20 12H9"/>',
  cog: '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.500M12 18.500V21M3 12h2.500M18.500 12H21M5.600 5.600l1.800 1.800M16.600 16.600l1.800 1.800M5.600 18.400l1.800-1.800M16.600 7.400l1.800-1.800"/>',
  at: '<circle cx="12" cy="12" r="3.500"/><path d="M15.500 12v1.500a2.500 2.500 0 0 0 5 0V12a8.500 8.500 0 1 0-3.400 6.800"/>',
  swap: '<path d="M4 8h15m-4-4 4 4-4 4M20 16H5m4-4-4 4 4 4"/>',
  copy: '<rect x="8" y="8" width="12" height="12" rx="1.500"/><path d="M16 8V5.500A1.500 1.500 0 0 0 14.500 4h-9A1.500 1.500 0 0 0 4 5.500v9A1.500 1.500 0 0 0 5.500 16H8"/>',
};
const clean = (b) => b.replace(/(\d)\.(\d)00\b/g, '$1.$2').replace(/(\d)\.500\b/g, '$1.5');
const icon = (name, cls = 'ic', size = 18) => EXTRA[name]
  ? `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${clean(EXTRA[name])}</svg>`
  : baseIcon(name, cls, size);

// ───────── Données fictives (mercredi 21/10/2026, 11 h 42) ─────────
const agents = [
  { name: 'Hugo Lefèvre', mail: 'hugo.lefevre@coderise.fr', ini: 'HL', role: 'Ingénieur support', dept: 'Support Priority', open: 9, sla: '97 %', last: 'Aujourd\'hui, 08:12', admin: false },
  { name: 'Nora Benali', mail: 'nora.benali@coderise.fr', ini: 'NB', role: 'Lead développeuse', dept: 'Développement', open: 6, sla: '100 %', last: 'Aujourd\'hui, 09:03', admin: true },
  { name: 'Sacha Roussel', mail: 'sacha.roussel@coderise.fr', ini: 'SR', role: 'Ingénieur DevOps', dept: 'Support Priority', open: 11, sla: '88 %', last: 'Aujourd\'hui, 06:41', admin: true },
  { name: 'Julien Chable', mail: 'julien.chable@coderise.fr', ini: 'JC', role: 'Direction', dept: 'Direction', open: 0, sla: 'n/a', last: 'Hier, 17:26', admin: true },
];
const av = (ini, team = true, sm = false) => `<span class="avatar ${team ? 'av-team' : ''} ${sm ? 'av-sm' : ''}" aria-hidden="true">${ini}</span>`;
const plan = (p) => `<span class="plan plan-${p === 'Priority' ? 'priority' : 'standard'}">${p === 'Priority' ? icon('bolt', 'ic', 12) : ''}${p}</span>`;
const status = (s) => {
  const k = s === 'Ouvert' ? 'open' : s === 'Résolu' ? 'resolved' : 'closed';
  const i = s === 'Ouvert' ? 'inbox' : s === 'Résolu' ? 'checkc' : 'lock';
  return `<span class="status status-${k}">${icon(i, 'ic', 14)}${s}</span>`;
};

// Jauge du premier retour. kind : ok | warn | over | done | none. pct = part des 4 h consommée.
function gauge(kind, pct, text, lg = false) {
  if (kind === 'none') return `<span class="gauge-none">${esc(text)}</span>`;
  const ic = { ok: 'clock', warn: 'clock', over: 'alert', done: 'checkc' }[kind];
  const cls = kind === 'ok' ? '' : ` gauge-${kind}`;
  return `<div class="gauge${cls}${lg ? ' gauge-lg' : ''}" role="img" aria-label="${esc(text)}"><span class="gauge-bar"><i style="width:${pct}%"></i></span><span class="gauge-txt">${icon(ic, 'ic', 13)}${esc(text)}</span></div>`;
}

const queue = [
  { n: '260402', subj: 'Passerelle de paiement : erreur 502 sur toutes les commandes web', who: 'Camille Roy', org: 'Hélios Logistique', topic: 'Incident en production', plan: 'Priority', g: ['over', 100, 'Dépassé de 47 min'], st: 'Ouvert', ag: 'Sacha Roussel', ai: 'SR', act: 'Sacha, 12 min', sel: true },
  { n: '260429', subj: 'Export des factures : le fichier PDF est vide depuis hier', who: 'Mathieu Garnier', org: 'Atelier Dupré', topic: 'Incident en production', plan: 'Priority', g: ['warn', 84, 'Reste 38 min'], st: 'Ouvert', ag: null, act: 'Client, 3 h 22', sel: true },
  { n: '260433', subj: 'Certificat TLS du sous-domaine recette expire dans 6 jours', who: 'Inès Colas', org: 'Maison Verdier', topic: 'Accès et sécurité', plan: 'Priority', g: ['ok', 14, 'Reste 3 h 27'], st: 'Ouvert', ag: 'Hugo Lefèvre', ai: 'HL', act: 'Client, 33 min' },
  { n: '260418', subj: 'Application mobile : la liste des commandes est vide depuis l\'API v2.8.0', who: 'Léa Marchand', org: 'Maison Verdier', topic: 'Incident en production', plan: 'Priority', g: ['done', 18, 'Tenu en 44 min'], st: 'Ouvert', ag: 'Nora Benali', ai: 'NB', act: 'Nora, 22 min' },
  { n: '260352', subj: 'Agent de tri des réclamations : classer aussi les e-mails en anglais', who: 'Léa Marchand', org: 'Maison Verdier', topic: 'Demande d\'évolution', plan: 'Priority', g: ['done', 31, 'Tenu en 1 h 14'], st: 'Ouvert', ag: 'Sacha Roussel', ai: 'SR', act: 'Sacha, hier' },
  { n: '260397', subj: 'Ajouter un export CSV dans le back-office des magasins', who: 'Léa Marchand', org: 'Maison Verdier', topic: 'Demande d\'évolution', plan: 'Standard', g: ['none', 0, 'Jours ouvrés'], st: 'Ouvert', ag: 'Hugo Lefèvre', ai: 'HL', act: 'Hugo, 16/10' },
  { n: '260421', subj: 'Question sur la rotation des clés d\'API avant la mise en production de vendredi', who: 'Camille Roy', org: 'Hélios Logistique', topic: 'Question technique', plan: 'Standard', g: ['none', 0, 'Jours ouvrés'], st: 'Ouvert', ag: null, act: 'Client, hier' },
  { n: '260388', subj: 'Facture 2026-0151 : ajouter le numéro de bon de commande', who: 'Mathieu Garnier', org: 'Atelier Dupré', topic: 'Facturation et contrat', plan: 'Standard', g: ['none', 0, 'Jours ouvrés'], st: 'Résolu', ag: 'Julien Chable', ai: 'JC', act: 'Julien, 19/10' },
  { n: '260365', subj: 'Notification push en double sur iOS 19', who: 'Inès Colas', org: 'Maison Verdier', topic: 'Incident en production', plan: 'Priority', g: ['done', 9, 'Tenu en 22 min'], st: 'Résolu', ag: 'Nora Benali', ai: 'NB', act: 'Nora, 18/10' },
];

// ───────── Gabarit de page ─────────
function page({ title, body, bodyClass = 'app', crumbsFor }) {
  return fr(`<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)} | Panneau agent Coderise</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#000F26">
<link rel="icon" href="${SH}/img/favicon-64.png" type="image/png">
<link rel="stylesheet" href="${SH}/fonts.css">
<link rel="stylesheet" href="${SH}/tokens.css">
<link rel="stylesheet" href="theme.css">
</head>
<body class="${bodyClass}">
<a class="skip" href="#content">Aller au contenu</a>
${body}
</body>
</html>
`);
}

const logo = `<img class="logo" src="${SH}/img/logo-horizontal-sombre.png" width="2041" height="345" alt="Coderise">`;

// Rail. mode : 'agent' | 'admin'.
function rail({ mode, active, queueActive = '' }) {
  const cur = (k) => (active === k ? ' aria-current="page"' : '');
  const qcur = (k) => (queueActive === k ? ' aria-current="page"' : '');
  const modeSwitch = `<div class="mode" role="group" aria-label="Espace de travail">
<a href="tickets.html"${mode === 'agent' ? ' aria-current="true"' : ''}>${icon('inbox', 'ic', 16)}Agent</a>
<a href="admin-parametres.html"${mode === 'admin' ? ' aria-current="true"' : ''}>${icon('sliders', 'ic', 16)}Administration</a>
</div>`;
  const agentNav = `<nav aria-label="Navigation principale">
<div class="group">
<ul>
<li><a class="item" href="tableau-de-bord.html"${cur('dash')}>${icon('chart')}Tableau de bord</a></li>
<li><a class="item" href="tickets.html"${cur('tickets')}>${icon('ticket')}Tickets<span class="badge badge-hot">3</span></a>
<div class="queues" role="group" aria-label="Files de tickets">
<a href="tickets.html"${qcur('open')}>Ouverts<span class="badge">24</span></a>
<a href="tickets.html"${qcur('mine')}>Mes tickets<span class="badge">7</span></a>
<a href="tickets.html"${qcur('unassigned')}>Non assignés<span class="badge">5</span></a>
<a href="tickets.html"${qcur('late')}>À risque<span class="badge badge-hot">3</span></a>
<a href="tickets.html"${qcur('closed')}>Fermés<span class="badge">412</span></a>
</div></li>
<li><a class="item" href="#"${cur('tasks')}>${icon('tasks')}Tâches<span class="badge">4</span></a></li>
<li><a class="item" href="#"${cur('users')}>${icon('users')}Utilisateurs</a></li>
<li><a class="item" href="#"${cur('kb')}>${icon('book')}Base de connaissances</a></li>
</ul></div></nav>`;
  const adminNav = `<nav aria-label="Navigation de l'administration">
<div class="group"><ul>
<li><a class="item" href="#"${cur('adash')}>${icon('chart')}Tableau de bord</a></li>
</ul></div>
<div class="group"><p class="group-title">Réglages</p><ul>
<li><a class="item" href="admin-parametres.html"${cur('settings')}>${icon('sliders')}Paramètres</a></li>
<li><a class="item" href="#"${cur('emails')}>${icon('mail')}Adresses e-mail</a></li>
</ul></div>
<div class="group"><p class="group-title">Gérer</p><ul>
<li><a class="item" href="#"${cur('topics')}>${icon('tag')}Sujets d'aide</a></li>
<li><a class="item" href="#"${cur('sla')}>${icon('clock')}Plans de SLA</a></li>
<li><a class="item" href="#"${cur('filters')}>${icon('filter')}Filtres de tickets</a></li>
<li><a class="item" href="#"${cur('forms')}>${icon('layers')}Formulaires</a></li>
<li><a class="item" href="#"${cur('plugins')}>${icon('plug')}Plugins</a></li>
</ul></div>
<div class="group"><p class="group-title">Équipe</p><ul>
<li><a class="item" href="admin-agents.html"${cur('agents')}>${icon('users')}Agents</a></li>
<li><a class="item" href="#"${cur('teams')}>${icon('user')}Équipes</a></li>
<li><a class="item" href="#"${cur('depts')}>${icon('building')}Départements</a></li>
<li><a class="item" href="#"${cur('roles')}>${icon('key')}Rôles</a></li>
</ul></div></nav>`;
  const mobileItems = mode === 'agent'
    ? [['tableau-de-bord.html', 'chart', 'Tableau de bord', 'dash'], ['tickets.html', 'ticket', 'Tickets', 'tickets'], ['#', 'tasks', 'Tâches', 'tasks'], ['#', 'users', 'Utilisateurs', 'users'], ['#', 'book', 'Base de connaissances', 'kb'], ['admin-parametres.html', 'sliders', 'Passer à l\'administration', '']]
    : [['admin-parametres.html', 'sliders', 'Paramètres', 'settings'], ['admin-agents.html', 'users', 'Agents', 'agents'], ['#', 'tag', 'Sujets d\'aide', ''], ['#', 'clock', 'Plans de SLA', ''], ['tickets.html', 'inbox', 'Retour à l\'espace agent', '']];
  return `<aside class="rail on-dark" aria-label="Menu">
<a class="brand" href="tickets.html">${logo}</a>
${modeSwitch}
<div class="menu-mobile"><details><summary>${icon('menu', 'ic', 18)}Menu</summary><div class="sheet">${mobileItems.map(([h, i, l, k]) => `<a href="${h}"${k && active === k ? ' aria-current="page"' : ''}>${icon(i, 'ic', 18)}${l}</a>`).join('')}</div></details></div>
${mode === 'agent' ? agentNav : adminNav}
<div class="foot">
<a class="me" href="#">${av('NB', false)}<span><b>Nora Benali</b><small>Support Priority, administratrice</small></span></a>
<a class="item" href="#">${icon('user')}Mon profil</a>
<a class="item" href="login.html">${icon('logout')}Se déconnecter</a>
</div>
</aside>`;
}

function topbar({ crumbs, cta = true, search = 'Rechercher un ticket, un client, une organisation' }) {
  return `<header class="topbar">
<nav class="crumbs" aria-label="Fil d'Ariane"><ol>${crumbs.map(([l, h], i) => (i === crumbs.length - 1 ? `<li><span aria-current="page">${l}</span></li>` : `<li><a href="${h}">${l}</a></li>`)).join('')}</ol></nav>
<form class="tsearch" role="search" onsubmit="return false"><label class="sr-only" for="q">${search}</label>${icon('search', 'ic', 18)}<input id="q" type="search" placeholder="${search}"><kbd aria-hidden="true">/</kbd></form>
${cta ? `<a class="btn btn-primary" href="#">${icon('plus', 'ic', 16)}Nouveau ticket</a>` : ''}
</header>`;
}

function shell({ mode, active, queueActive, crumbs, cta, main, extra = '' }) {
  return `<div class="shell">
${rail({ mode, active, queueActive })}
<div class="viewport">
${topbar({ crumbs, cta })}
<main id="content" tabindex="-1">
${main}
</main>
${extra}
</div>
</div>`;
}

// ───────── 1. Connexion ─────────
const login = page({
  title: 'Connexion', bodyClass: 'login', body: `<div class="login-wrap">
<section class="login-art on-dark" aria-label="Présentation">
<div>${logo}</div>
<div>
<h1>Chaque ticket Priority a son compte à rebours.</h1>
<p>Panneau réservé à l'équipe Coderise. Les clients ouvrent et suivent leurs demandes sur le portail de support.</p>
</div>
<p class="signature">Le code qui fait <span class="up">décoller</span> vos projets</p>
<span class="sommet" aria-hidden="true"></span>
</section>
<section class="login-card" aria-labelledby="t">
<h2 id="t">Connexion à l'équipe</h2>
<p class="lead">Utilisez votre identifiant Coderise.</p>
<div class="banner banner-err" role="alert">${icon('alert', 'ic', 20)}<p><b>Identifiant ou mot de passe incorrect.</b> Il reste 3 essais avant un blocage de 5 minutes.</p></div>
<form action="#" onsubmit="return false">
<div class="field has-error"><label for="u">Identifiant</label><div class="ctl"><input id="u" name="userid" autocomplete="username" value="nora.benali" aria-describedby="e1"></div></div>
<div class="field has-error"><label for="p">Mot de passe</label><div class="ctl"><input id="p" name="passwd" type="password" autocomplete="current-password" value="mot-de-passe" aria-describedby="e1"><p class="error" id="e1">${icon('alert', 'ic', 16)}Vérifiez l'identifiant et le mot de passe, puis réessayez.</p></div></div>
<button class="btn btn-primary" type="submit">Se connecter</button>
</form>
<p class="alt"><a href="#">Mot de passe oublié</a><a href="#">Une question sur l'accès ?</a></p>
</section>
</div>`,
});

// ───────── 2. File de tickets ─────────
function qrow(t) {
  const sel = t.sel ? ' is-sel' : '';
  const g = gauge(...t.g);
  const who = t.ag
    ? `<span class="who">${av(t.ai, true, true)}<span class="name">${t.ag}</span></span>`
    : `<span class="unassigned">${icon('user', 'ic', 14)}Non assigné</span>`;
  return `<tr class="${sel.trim()}">
<td class="c-chk"><input type="checkbox" aria-label="Sélectionner le ticket ${t.n}"${t.sel ? ' checked' : ''}></td>
<td class="c-n" data-label="Ticket"><a href="ticket.html">#${t.n}</a><span class="stack">${plan(t.plan)}</span></td>
<td class="subj" data-label="Sujet"><a href="ticket.html">${esc(t.subj)}</a><small>${t.who}, ${t.org}</small></td>
<td data-label="Premier retour">${g}</td>
<td data-label="État">${status(t.st)}</td>
<td data-label="Assigné à" class="nw">${who}</td>
<td data-label="Activité" class="muted nw">${t.act}</td>
</tr>`;
}
const tickets = page({
  title: 'Tickets ouverts',
  body: shell({
    mode: 'agent', active: 'tickets', queueActive: 'open', crumbs: [['Tickets', 'tickets.html'], ['Ouverts', '']],
    main: `<div class="title-row"><div><h1>Tickets ouverts</h1><p class="sub">24 tickets, triés par urgence du premier retour. Mis à jour à 11:42.</p></div>
<div class="t-actions"><a class="btn btn-line" href="#">${icon('refresh', 'ic', 16)}Actualiser</a><a class="btn btn-line" href="#">${icon('star', 'ic', 16)}Enregistrer la recherche</a><a class="btn btn-line" href="#">${icon('upload', 'ic', 16)}Exporter en CSV</a></div></div>
<section class="panel" aria-label="Liste des tickets">
<div class="filters">
<div class="seg" role="tablist" aria-label="Contrat"><a role="tab" href="#" aria-selected="true">Tous<span class="count">24</span></a><a role="tab" href="#" aria-selected="false">${icon('bolt', 'ic', 13)}Priority<span class="count">11</span></a><a role="tab" href="#" aria-selected="false">Standard<span class="count">13</span></a></div>
<div class="sel"><label for="f-dept">Département</label><select id="f-dept"><option>Tous</option><option>Support Priority</option><option>Développement</option></select></div>
<div class="sel"><label for="f-ag">Assigné à</label><select id="f-ag"><option>Tout le monde</option><option>Moi</option><option>Non assigné</option></select></div>
<div class="sel"><label for="f-per">Reçus</label><select id="f-per"><option>Sur 30 jours</option><option>Aujourd'hui</option><option>7 derniers jours</option></select></div>
<a class="btn btn-ghost btn-sm" href="#">${icon('x', 'ic', 14)}Réinitialiser</a>
</div>
<div class="bulk" role="region" aria-label="Actions sur la sélection"><b>2 tickets sélectionnés</b>
<a class="btn btn-sm btn-on-dark" href="#">${icon('user', 'ic', 14)}Assigner</a>
<a class="btn btn-sm btn-on-dark" href="#">${icon('swap', 'ic', 14)}Transférer</a>
<a class="btn btn-sm btn-on-dark" href="#">${icon('flag', 'ic', 14)}Changer l'état</a>
<a class="btn btn-sm btn-on-dark" href="#">${icon('merge', 'ic', 14)}Fusionner</a>
<span class="sp"></span><a class="btn btn-sm btn-primary-dark" href="#">${icon('check', 'ic', 14)}Prendre en charge</a></div>
<div class="table-wrap"><table class="data">
<caption class="sr-only">Tickets ouverts, du premier retour le plus urgent au moins urgent</caption>
<thead><tr><th scope="col" class="c-chk"><input type="checkbox" aria-label="Tout sélectionner"></th>
<th scope="col">Ticket et contrat</th><th scope="col">Sujet et client</th>
<th scope="col" aria-sort="ascending"><a href="#">Premier retour${icon('sortd', 'ic', 14)}</a></th>
<th scope="col">État</th><th scope="col">Assigné à</th><th scope="col"><a href="#">Dernière activité</a></th></tr></thead>
<tbody>
${queue.map(qrow).join('\n')}
</tbody></table></div>
<div class="tfoot"><span>1 à 9 sur 24</span><nav class="pager" aria-label="Pagination"><span aria-current="page">1</span><a href="#">2</a><a href="#">3</a><a href="#" aria-label="Page suivante">${icon('chevr', 'ic', 16)}</a></nav></div>
</section>`,
  }),
});

// ───────── 3. Fiche ticket ─────────
const ticketPage = page({
  title: 'Ticket #260418',
  body: shell({
    mode: 'agent', active: 'tickets', queueActive: 'mine', crumbs: [['Tickets', 'tickets.html'], ['Mes tickets', 'tickets.html'], ['#260418', '']],
    main: `<div class="title-row"><div>
<p class="idline"><span>Ticket #260418</span><span class="sep"></span>${status('Ouvert')}<span class="sep"></span>${plan('Priority')}<span class="sep"></span><span class="urg">${icon('alert', 'ic', 15)}Urgente</span></p>
<h1>Application mobile : la liste des commandes est vide depuis l'API v2.8.0</h1>
<p class="sub">Léa Marchand, Maison Verdier. Reçu le 21/10/2026 à 08:47 via le portail.</p></div>
<div class="t-actions"><a class="btn btn-line" href="#">${icon('edit', 'ic', 16)}Modifier</a><a class="btn btn-line" href="#">${icon('print', 'ic', 16)}Imprimer</a><a class="btn btn-primary" href="#">${icon('checkc', 'ic', 16)}Marquer comme résolu</a></div></div>
<div class="cols">
<div class="col-main">
<section class="panel" aria-label="Conversation">
<div class="tabs" role="tablist"><a role="tab" href="#" aria-selected="true">${icon('message', 'ic', 16)}Conversation<span class="count">6</span></a><a role="tab" href="#" aria-selected="false">${icon('tasks', 'ic', 16)}Tâches<span class="count">1</span></a><a role="tab" href="#" aria-selected="false">${icon('list', 'ic', 16)}Journal</a></div>
<ol class="thread">
<li class="entry from-client">${av('LM', false)}<div><div class="head"><strong>Léa Marchand</strong><span class="role">Cliente</span><time datetime="2026-10-21T08:47">21/10/2026 08:47</time></div>
<div class="body"><p>Bonjour, depuis la mise à jour de l'API en v2.8.0, la liste des commandes de l'application mobile est vide pour tous nos magasins. Le problème a commencé hier soir vers 21 h 30. C'est bloquant pour les équipes de retrait.</p><p>Environnement : production. Version de l'application : 4.12.1.</p></div></div></li>
<li class="event">${icon('flag', 'ic', 14)}<span>Règle « contrat Priority » : ticket assigné à <b>Hugo Lefèvre</b>, département Support Priority.</span><time datetime="2026-10-21T08:52">08:52</time></li>
<li class="entry">${av('HL')}<div><div class="head"><strong>Hugo Lefèvre</strong><span class="role">Réponse envoyée au client</span><time datetime="2026-10-21T09:31">09:31</time></div>
<div class="body"><p>Bonjour Léa, nous avons reproduit le problème. Nous cherchons la cause côté API et revenons vers vous avant midi avec un correctif ou une solution de contournement.</p></div></div></li>
<li class="entry note">${av('HL')}<div><div class="head"><strong>Hugo Lefèvre</strong><span class="note-flag">${icon('lock', 'ic', 14)}Note interne, invisible du client</span><time datetime="2026-10-21T09:48">09:48</time></div>
<div class="body"><p>Reproduit en recette : <code>GET /v2/orders</code> renvoie 200 avec un tableau vide quand le paramètre <code>status</code> est absent. Suspect : le commit <code>3f9c1a2</code> (filtre par défaut). Je passe à Nora pour le correctif.</p></div></div></li>
<li class="event">${icon('swap', 'ic', 14)}<span><b>Hugo Lefèvre</b> a réassigné le ticket à <b>Nora Benali</b>.</span><time datetime="2026-10-21T10:02">10:02</time></li>
<li class="entry latest">${av('NB')}<div><div class="head"><strong>Nora Benali</strong><span class="role">Réponse envoyée au client</span><time datetime="2026-10-21T11:20">11:20</time></div>
<div class="body"><p>Le filtre par défaut était bien en cause. Le correctif (v2.8.1) est en test sur la recette ; nous le livrons en production à 12 h 30 si la recette est validée.</p>
<pre><code>GET /v2/orders          -&gt; 200 [ ...commandes... ]
GET /v2/orders?status=* -&gt; 200 [ ...commandes... ]</code></pre>
<div class="attachments"><span class="attachment">${icon('clip', 'ic', 14)}<a href="#">correctif-2-8-1-notes.pdf</a><small>96 Ko</small></span></div></div></div></li>
</ol>
</section>
<section class="panel compose is-note" aria-label="Répondre">
<div class="modes" role="tablist"><a role="tab" href="#" aria-selected="false">${icon('send', 'ic', 16)}Répondre au client</a><a class="m-note" role="tab" href="#" aria-selected="true">${icon('lock', 'ic', 16)}Note interne</a></div>
<div class="to">${icon('lock', 'ic', 16)}<span><b>Visible de l'équipe uniquement.</b> Le client ne reçoit rien.</span></div>
<div class="area">
<div class="editor"><div class="editor-bar" role="toolbar" aria-label="Mise en forme"><button type="button" aria-label="Gras">${icon('bold', 'ic', 16)}</button><button type="button" aria-label="Italique">${icon('italic', 'ic', 16)}</button><button type="button" aria-label="Liste">${icon('list', 'ic', 16)}</button><button type="button" aria-label="Lien">${icon('link', 'ic', 16)}</button><button type="button" aria-label="Code">${icon('code', 'ic', 16)}</button><button type="button" aria-label="Image">${icon('image', 'ic', 16)}</button><span class="sepv"></span><button type="button" aria-label="Mentionner un collègue">${icon('at', 'ic', 16)}</button><a class="btn btn-ghost btn-sm canned" href="#">${icon('copy', 'ic', 14)}Réponses types</a></div>
<div class="editor-body" contenteditable="false"><p>Livraison prévue 12 h 30. Prévenir <b>@Sacha</b> pour la fenêtre de déploiement.</p></div></div>
<div class="compose-foot"><a class="btn btn-line btn-sm" href="#">${icon('clip', 'ic', 14)}Joindre un fichier</a><span class="sp"></span>
<span class="inline-field"><label for="st">Passer l'état à</label><select id="st"><option>Ouvert (inchangé)</option><option>Résolu</option></select></span>
<button class="btn btn-primary" type="button">Publier la note</button></div>
</div></section>
</div>
<aside class="col-side sticky" aria-label="Propriétés du ticket">
<section class="panel"><div class="panel-h"><h2>${icon('gauge', 'ic', 18)}Premier retour</h2></div>
<div class="pad">${gauge('done', 18, 'Tenu en 44 min sur 4 h', true)}<p class="muted" style="margin:8px 0 0">Réponse apportée par Hugo à 09:31. Contrat Priority, 24 h/24, 7 j/7.</p></div></section>
<section class="panel"><div class="panel-h"><h2>${icon('sliders', 'ic', 18)}Propriétés</h2></div>
<dl class="props">
<div><dt>État</dt><dd><select aria-label="État"><option>Ouvert</option><option>Résolu</option><option>Fermé</option></select></dd></div>
<div><dt>Assigné à</dt><dd><select aria-label="Assigné à"><option>Nora Benali</option><option>Hugo Lefèvre</option><option>Sacha Roussel</option></select></dd></div>
<div><dt>Priorité</dt><dd><select aria-label="Priorité"><option>Urgente</option><option>Haute</option><option>Normale</option></select></dd></div>
<div><dt>Département</dt><dd>Support Priority</dd></div>
<div><dt>Sujet d'aide</dt><dd>Incident en production</dd></div>
<div><dt>Contrat</dt><dd>${plan('Priority')}</dd></div>
<div><dt>Produit</dt><dd>Verdier Commandes (application mobile)</dd></div>
<div><dt>Environnement</dt><dd>Production, bloquant</dd></div>
<div><dt>Dernière activité</dt><dd>21/10/2026 11:20</dd></div>
</dl></section>
<section class="panel"><div class="panel-h"><h2>${icon('user', 'ic', 18)}Client</h2></div>
<dl class="props"><div><dt>Nom</dt><dd><a href="#">Léa Marchand</a></dd></div><div><dt>Organisation</dt><dd><a href="#">Maison Verdier</a></dd></div><div><dt>E-mail</dt><dd>lea.marchand@maison-verdier.fr</dd></div><div><dt>Tickets ouverts</dt><dd>3 (dont 2 Priority)</dd></div></dl></section>
<section class="panel"><ul class="actions-list">
<li><a href="#">${icon('swap')}Transférer à un autre département</a></li>
<li><a href="#">${icon('merge')}Fusionner avec un autre ticket</a></li>
<li><a href="#">${icon('users')}Ajouter un collaborateur</a></li>
<li><a class="danger" href="#">${icon('ban')}Marquer comme indésirable</a></li></ul></section>
</aside>
</div>`,
  }),
});

// ───────── 4. Tableau de bord ─────────
function bars() {
  const days = ['8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21'];
  const rec = [18, 22, 25, 9, 3, 21, 24, 27, 19, 23, 6, 4, 26, 22];
  const clo = [15, 20, 22, 12, 4, 19, 23, 21, 22, 20, 8, 5, 21, 18];
  const W = 640, H = 220, L = 34, B = 26, T = 8, gw = (W - L) / days.length, bw = gw * 0.34;
  const y = (v) => T + (H - T - B) * (1 - v / 30);
  let s = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-labelledby="ct cd"><title id="ct">Tickets reçus et fermés par jour, du 8 au 21 octobre 2026</title><desc id="cd">Pic de 27 tickets reçus le 15 octobre. Creux les week-ends des 11, 12, 18 et 19 octobre.</desc>`;
  for (const v of [0, 10, 20, 30]) s += `<line x1="${L}" x2="${W}" y1="${y(v)}" y2="${y(v)}" stroke="#000F26" stroke-opacity="${v === 0 ? 0.5 : 0.12}"/><text x="${L - 6}" y="${y(v) + 4}" text-anchor="end" font-size="11" fill="#5B6675" font-family="Inter,Arial">${v}</text>`;
  days.forEach((d, i) => {
    const x = L + i * gw + gw * 0.14;
    s += `<rect x="${x}" y="${y(rec[i])}" width="${bw}" height="${y(0) - y(rec[i])}" fill="#000F26" rx="1.5"/>`;
    s += `<rect x="${x + bw + 2}" y="${y(clo[i])}" width="${bw}" height="${y(0) - y(clo[i])}" fill="#10C9DE" rx="1.5"/>`;
    s += `<text x="${x + bw}" y="${H - 8}" text-anchor="middle" font-size="11" fill="#5B6675" font-family="Inter,Arial">${d}</text>`;
  });
  return s + '</svg>';
}
const dash = page({
  title: 'Tableau de bord',
  body: shell({
    mode: 'agent', active: 'dash', crumbs: [['Tableau de bord', '']],
    main: `<div class="title-row"><div><h1>Tableau de bord</h1><p class="sub">Les 14 derniers jours, du 8 au 21 octobre 2026.</p></div>
<div class="t-actions"><div class="sel"><label class="sr-only" for="per">Période</label><select id="per"><option>14 derniers jours</option><option>30 derniers jours</option><option>Ce trimestre</option></select></div><a class="btn btn-line" href="#">${icon('upload', 'ic', 16)}Exporter</a></div></div>
<div class="alert-strip" role="status">${icon('alert', 'ic', 22)}<p><strong>3 tickets Priority risquent de dépasser leur premier retour de 4 h.</strong> Le plus ancien l'a déjà dépassé de 47 minutes.</p><a class="btn btn-primary-dark" href="tickets.html">Voir les 3 tickets</a></div>
<div class="stats">
<div><b>287</b><span>Tickets reçus</span><span class="delta delta-up">+8 % sur 14 jours</span></div>
<div><b>24</b><span>Tickets ouverts</span><span class="delta delta-flat">Stable</span></div>
<div><b>52 min</b><span>Premier retour moyen, Priority</span><span class="delta delta-up">Objectif 4 h tenu</span></div>
<div><b>94 %</b><span>Premiers retours dans l'objectif</span><span class="delta delta-down">${icon('alert', 'ic', 12)}2 points de moins</span></div>
</div>
<div class="cols">
<div class="col-main">
<section class="panel"><div class="panel-h"><h2>${icon('chart', 'ic', 18)}Activité par jour</h2><a class="more" href="#">Voir les données</a></div>
<div class="chart">${bars()}</div>
<div class="legend"><span><i style="background:#000F26"></i>Reçus</span><span><i style="background:#10C9DE"></i>Fermés</span></div></section>
<section class="panel"><div class="panel-h"><h2>${icon('users', 'ic', 18)}Charge par agent</h2></div>
<div class="table-wrap"><table class="data"><caption class="sr-only">Tickets ouverts et respect du premier retour par agent</caption>
<thead><tr><th scope="col">Agent</th><th scope="col">Ouverts</th><th scope="col">Charge</th><th scope="col">Premier retour tenu</th></tr></thead><tbody>
${agents.filter((a) => a.open).map((a) => `<tr><th scope="row"><span class="who">${av(a.ini, true, true)}<span class="name">${a.name}<small>${a.role}</small></span></span></th><td data-label="Ouverts"><strong>${a.open}</strong></td><td data-label="Charge"><div class="load" role="img" aria-label="${a.open} tickets sur 12 de capacité"><i style="width:${Math.round((a.open / 12) * 100)}%"></i></div></td><td data-label="Premier retour tenu">${a.sla}</td></tr>`).join('')}
</tbody></table></div></section>
</div>
<aside class="col-side" aria-label="À traiter">
<section class="panel"><div class="panel-h"><h2>${icon('gauge', 'ic', 18)}Premier retour à risque</h2></div>
<div class="table-wrap"><table class="data"><caption class="sr-only">Tickets Priority proches ou au-delà de l'objectif</caption><tbody>
<tr><td data-label="Ticket"><a href="ticket.html">#260402</a><small class="muted" style="display:block">Passerelle de paiement</small></td><td data-label="Délai">${gauge('over', 100, 'Dépassé de 47 min')}</td></tr>
<tr><td data-label="Ticket"><a href="ticket.html">#260429</a><small class="muted" style="display:block">Export des factures</small></td><td data-label="Délai">${gauge('warn', 84, 'Reste 38 min')}</td></tr>
<tr><td data-label="Ticket"><a href="ticket.html">#260433</a><small class="muted" style="display:block">Certificat TLS recette</small></td><td data-label="Délai">${gauge('ok', 14, 'Reste 3 h 27')}</td></tr>
</tbody></table></div></section>
<section class="panel"><div class="panel-h"><h2>${icon('tag', 'ic', 18)}Par sujet d'aide</h2></div>
<dl class="props"><div><dt>Incident en production</dt><dd>112</dd></div><div><dt>Question technique</dt><dd>84</dd></div><div><dt>Demande d'évolution</dt><dd>51</dd></div><div><dt>Facturation et contrat</dt><dd>24</dd></div><div><dt>Accès et sécurité</dt><dd>16</dd></div></dl></section>
</aside>
</div>`,
  }),
});

// ───────── 5. Administration : paramètres des tickets ─────────
const field = (id, label, ctl, { hint = '', short = false, changed = false, req = false, row = false } = {}) =>
  `<div class="field${changed ? ' is-changed' : ''}"><label for="${id}">${label}${req ? ' <span class="req" aria-hidden="true">*</span>' : ''}${changed ? `<span class="changed-flag">${icon('edit', 'ic', 12)}modifié</span>` : ''}</label><div class="ctl${short ? ' short' : ''}${row ? ' row' : ''}">${ctl}${hint ? `<p class="hint">${hint}</p>` : ''}</div></div>`;
const check = (id, label, checked, hint = '') =>
  `<div class="field"><span class="lbl"></span><div class="ctl"><div class="check"><input type="checkbox" id="${id}"${checked ? ' checked' : ''}><label for="${id}">${label}${hint ? `<span class="hint" style="display:block;margin-top:2px">${hint}</span>` : ''}</label></div></div></div>`;
const settings = page({
  title: 'Paramètres des tickets',
  body: shell({
    mode: 'admin', active: 'settings', cta: false, crumbs: [['Administration', 'admin-parametres.html'], ['Paramètres', 'admin-parametres.html'], ['Tickets', '']],
    main: `<nav class="subnav" aria-label="Paramètres">
<a href="#">Entreprise</a><a href="#">Système</a><a href="admin-parametres.html" aria-current="page">Tickets</a><a href="#">Tâches</a><a href="#">Agents</a><a href="#">Utilisateurs</a><a href="#">Base de connaissances</a><a href="#">Réponses automatiques</a><a href="#">Alertes et avis</a></nav>
<div class="title-row"><div><h1>Paramètres des tickets</h1><p class="sub">Ce qui s'applique à tous les tickets, quel que soit le département.</p></div></div>
<form class="panel" action="#" onsubmit="return false" aria-label="Paramètres des tickets">
<div class="form">
<fieldset><legend>Numérotation et modèle</legend>
${field('num', 'Numéros de ticket', '<select id="num"><option>Séquentiels : 260418, 260419, 260420</option><option>Aléatoires</option></select>', { hint: 'Un numéro séquentiel permet à un client de citer son ticket au téléphone.' })}
${field('seq', 'Prochain numéro', '<input id="seq" value="260434" inputmode="numeric">', { short: true })}
${field('lock', 'Verrouillage d\'un ticket', '<select id="lock"><option>Verrouiller à l\'ouverture d\'une réponse</option><option>Ne pas verrouiller</option></select>', { hint: 'Évite que deux agents répondent en même temps.', changed: true })}
</fieldset>
<fieldset><legend>Délais et plans de service</legend>
${field('sla', 'Plan de SLA par défaut', '<select id="sla"><option>Standard, jours ouvrés</option><option>Priority, premier retour sous 4 h, 24 h/24</option></select>', { hint: 'Les clients de l\'organisation Priority reçoivent le plan Priority, quel que soit ce réglage.' })}
${field('grace', 'Délai de grâce', '<input id="grace" value="4" inputmode="numeric"> <span>heures</span>', { row: true })}
${field('rep', 'Alerte de premier retour à risque', '<select id="rep"><option>Au bout de 75 % du délai (3 h)</option><option>Au bout de 50 % du délai (2 h)</option></select>', { hint: 'Fait passer le ticket dans la file « Premier retour à risque ».', changed: true })}
</fieldset>
<fieldset><legend>Réponses et pièces jointes</legend>
${field('att', 'Taille maximale d\'un fichier', '<select id="att"><option>20 Mo</option><option>10 Mo</option><option>50 Mo</option></select>')}
${field('ext', 'Types de fichiers acceptés', '<input id="ext" value=".pdf, .png, .jpg, .log, .txt, .zip, .csv">', { hint: 'Séparez les extensions par une virgule. Les fichiers exécutables sont toujours refusés.' })}
${check('c1', 'Répondre avec la dernière réponse de l\'équipe en copie', true, 'Le client voit le fil complet dans l\'e-mail.')}
${check('c2', 'Afficher les notes internes au client', false, 'Désactivé et verrouillé pour le contrat Priority.')}
${check('c3', 'Assigner automatiquement à l\'agent qui répond', true)}
</fieldset>
<fieldset><legend>Clôture</legend>
${field('close', 'Fermer les tickets résolus après', '<input id="close" value="5" inputmode="numeric"> <span>jours sans réponse</span>', { row: true, hint: 'Un message prévient le client 48 h avant.', changed: true })}
</fieldset>
</div>
</form>`,
    extra: `<div class="savebar" role="region" aria-label="Enregistrement"><p>${icon('edit', 'ic', 18)}<span><strong>2 réglages modifiés.</strong> Rien n'est appliqué tant que vous n'avez pas enregistré.</span></p>
<div class="btns"><button class="btn btn-sm btn-on-dark" type="button">Annuler les modifications</button><button class="btn btn-sm btn-primary-dark" type="button">Enregistrer les modifications</button></div></div>`,
  }),
});

// ───────── 6. Administration : agents ─────────
const agentsPage = page({
  title: 'Agents',
  body: shell({
    mode: 'admin', active: 'agents', cta: false, crumbs: [['Administration', 'admin-parametres.html'], ['Équipe', ''], ['Agents', '']],
    main: `<div class="title-row"><div><h1>Agents</h1><p class="sub">4 agents actifs. Les rôles décident de ce que chacun peut faire dans les tickets.</p></div>
<div class="t-actions"><a class="btn btn-primary" href="#">${icon('userplus', 'ic', 16)}Ajouter un agent</a></div></div>
<section class="panel" aria-label="Liste des agents">
<div class="filters"><div class="sel" style="min-width:260px"><label for="fa">Rechercher un agent</label><input id="fa" type="search" placeholder="Nom, identifiant ou e-mail"></div>
<div class="sel"><label for="fd">Département</label><select id="fd"><option>Tous</option><option>Support Priority</option><option>Développement</option><option>Direction</option></select></div>
<div class="sel"><label for="fs">Statut</label><select id="fs"><option>Actifs</option><option>Verrouillés</option><option>Tous</option></select></div></div>
<div class="table-wrap"><table class="data"><caption class="sr-only">Agents de l'équipe Coderise</caption>
<thead><tr><th scope="col" class="c-chk"><input type="checkbox" aria-label="Tout sélectionner"></th><th scope="col">Agent</th><th scope="col">Département</th><th scope="col">Rôle</th><th scope="col">Statut</th><th scope="col">Dernière connexion</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
${agents.map((a, i) => `<tr><td class="c-chk"><input type="checkbox" aria-label="Sélectionner ${a.name}"></td>
<td data-label="Agent" class="subj"><span class="who">${av(a.ini, true, true)}<span class="name"><a href="#">${a.name}</a><small>${a.mail}</small></span></span></td>
<td data-label="Département">${a.dept}</td>
<td data-label="Rôle">${a.admin ? '<span class="tag">' + icon('key', 'ic', 12) + 'Administrateur</span>' : '<span class="tag">Agent</span>'}</td>
<td data-label="Statut"><span class="status status-resolved">${icon('checkc', 'ic', 14)}Actif</span></td>
<td data-label="Dernière connexion" class="muted">${a.last}</td>
<td data-label="Actions"><a class="btn btn-ghost btn-sm icon-btn" href="#" aria-label="Actions pour ${a.name}">${icon('dots', 'ic', 18)}</a></td></tr>`).join('\n')}
</tbody></table></div>
<div class="tfoot"><span>4 agents</span><span>Les accès se retirent en verrouillant le compte, jamais en le supprimant : l'historique des tickets est conservé.</span></div>
</section>`,
  }),
});

// ───────── Hub ─────────
const hubItems = [
  ['connexion', 'login.html', 'Connexion', 'Écran partagé : le compte à rebours Priority en accroche, formulaire et erreur à droite.'],
  ['file-de-tickets', 'tickets.html', 'File de tickets', 'Tri par urgence du premier retour, jauge à repères, actions groupées sur la sélection.'],
  ['fiche-ticket', 'ticket.html', 'Fiche ticket', 'Conversation, note interne teintée, réponse en deux modes, panneau de propriétés collant.'],
  ['tableau-de-bord', 'tableau-de-bord.html', 'Tableau de bord', 'Bandeau « à risque », chiffres du jour, activité sur 14 jours, charge par agent.'],
  ['admin-parametres', 'admin-parametres.html', 'Administration : paramètres', 'Réglages en libellés à gauche, champs modifiés repérés, barre d\'enregistrement collée.'],
  ['admin-agents', 'admin-agents.html', 'Administration : agents', 'Liste serrée avec rôle et statut, retrait d\'accès par verrouillage.'],
];
const hub = fr(`<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maquette du panneau agent et de l'administration | Coderise</title>
<meta name="robots" content="noindex">
<link rel="icon" href="${SH}/img/favicon-64.png" type="image/png">
<link rel="stylesheet" href="${SH}/fonts.css">
<link rel="stylesheet" href="${SH}/tokens.css">
<link rel="stylesheet" href="theme.css">
</head>
<body class="hub">
<header class="hub-head on-dark"><div class="in">${logo}
<h1>Panneau agent et administration d'osTicket, à la charte Coderise</h1>
<p>Proposition de maquette, dérivée du thème « Poste de travail » du portail : même rail bleu nuit, mêmes tableaux serrés, même panneau de propriétés. Rien n'est encore développé dans osTicket. Données fictives.</p></div></header>
<main class="hub-main" id="content">
<ul class="hub-grid">
${hubItems.map(([shot, href, t, d]) => `<li><a class="hub-card" href="${href}"><img src="captures/${shot}.png" alt="" width="1280" height="800" loading="lazy"><span class="t"><h2>${t}</h2><p>${d}</p></span></a></li>`).join('\n')}
</ul>
<section class="hub-note"><h2>Ce que cette maquette change par rapport au poste de travail du portail</h2>
<ul>
<li>L'espace de travail (Agent ou Administration) est choisi en haut du rail, toujours visible : on sait où l'on est avant d'agir.</li>
<li>Le délai de premier retour du contrat Priority devient la colonne qui organise la file, avec une jauge dont l'état se lit aussi dans le texte.</li>
<li>La note interne a sa propre couleur, dans le fil et dans la zone de saisie, pour qu'on ne l'envoie pas au client par erreur.</li>
<li>Les réglages modifiés sont repérés dans le formulaire, et une barre collée en bas garde les boutons d'enregistrement à portée.</li>
</ul></section>
</main>
</body>
</html>
`);

const files = { 'index.html': hub, 'login.html': login, 'tickets.html': tickets, 'ticket.html': ticketPage, 'tableau-de-bord.html': dash, 'admin-parametres.html': settings, 'admin-agents.html': agentsPage };
for (const [f, html] of Object.entries(files)) writeFileSync(join(OUT, f), html);
console.log('Pages écrites :', Object.keys(files).join(', '));
