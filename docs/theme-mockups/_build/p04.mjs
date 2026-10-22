// Proposition 04 : « Poste de travail » (dense, productivite). Gabarits reecrits (niveau 2).
import { doc, client, thread, ticket, tickets, categories, featured, topics, receiptMark, BACK_LINK, icon, redactor, filedrop, fileChips, esc } from './common.mjs';

const status = (s) => `<span class="st st-${s === 'Ouvert' ? 'open' : 'closed'}">${s}</span>`;
const ini = (n) => n.split(' ').map((x) => x[0]).join('');

function shell(page, key, logged, title, crumbs = '') {
  const L = (k, href, ic, label, badge = '') => `<li><a href="${href}"${key === k ? ' aria-current="page"' : ''}>${icon(ic, 'ic', 17)}<span>${label}</span>${badge ? `<b class="badge">${badge}</b>` : ''}</a></li>`;
  return doc({
    title: `${title} | Ownalytics Support`,
    css: 'theme.css',
    bodyClass: `p-${key}`,
    body: `${BACK_LINK}
<a class="skip" href="#main">Aller au contenu</a>
<header class="topbar">
  <a class="brand" href="index.html" aria-label="Ownalytics Support, accueil">${receiptMark()}<span class="wm">Ownalytics</span><span class="wm-sub">Support</span></a>
  <form class="gsearch" role="search" action="base-de-connaissances.html">${icon('search', 'ic', 16)}<input type="search" name="q" placeholder="Rechercher un ticket ou un article" aria-label="Rechercher un ticket ou un article"><kbd aria-hidden="true">/</kbd></form>
  <div class="user">${logged
    ? `<span class="u-av" aria-hidden="true">CR</span><span class="u-name">${client.name}</span><span class="u-org">${client.org}</span><a href="connexion.html">Se déconnecter</a>`
    : `<a href="connexion.html">Se connecter</a>`}<span class="lang"><a href="#" aria-current="true">FR</a><a href="#" hreflang="en">EN</a></span></div>
</header>
<div class="shell">
  <nav class="rail" aria-label="Menu principal">
    <ul class="r-main">
      ${L('home', 'index.html', 'home', 'Accueil')}
      ${L('tickets', 'mes-tickets.html', 'ticket', 'Mes tickets', '3')}
      ${L('open', 'ouvrir-un-ticket.html', 'plus', 'Ouvrir un ticket')}
      ${L('kb', 'base-de-connaissances.html', 'book', 'Base de connaissances')}
      ${logged ? '' : L('login', 'connexion.html', 'lock', 'Suivre un ticket')}
    </ul>
    ${logged ? `<p class="r-title">Vues</p><ul class="r-views"><li><a href="mes-tickets.html"><span>Réponse reçue</span><b class="badge hot">2</b></a></li><li><a href="mes-tickets.html"><span>En attente de l'équipe</span><b class="badge">1</b></a></li><li><a href="mes-tickets.html"><span>Fermés ce mois-ci</span><b class="badge">3</b></a></li></ul>` : ''}
    <p class="r-title">Aide</p>
    <ul class="r-views small"><li><a href="#"><span>Politique de support</span></a></li><li><a href="#"><span>Conditions de licence</span></a></li><li><a href="#"><span>État du service</span></a></li></ul>
  </nav>
  <main id="main" tabindex="-1">
${crumbs}${page}
  </main>
</div>
<footer class="foot"><span>© 2026 Coderise SAS - Tous droits réservés.</span><a href="#">Mentions légales</a><a href="#">Conditions</a><a href="#">Confidentialité</a><span class="grow">Matomo est une marque déposée d'InnoCraft Ltd. Ownalytics est un éditeur indépendant, sans lien avec InnoCraft.</span><a href="https://osticket.com">Propulsé par osTicket</a></footer>`,
  });
}

const ticketsOpen = tickets.filter((t) => t.status === 'Ouvert');

const rows = (list, withLast = true) => list.map((t) => `<tr class="${t.answered ? 'unread' : ''}">
  <td class="num"><a href="suivi-ticket.html">#${t.n}</a></td>
  <td class="subj"><a href="suivi-ticket.html">${t.answered ? '<span class="dot" title="Nouvelle réponse"></span><span class="visually-hidden">Nouvelle réponse : </span>' : ''}${esc(t.subject)}</a><span class="sub">${t.topic}</span></td>
  <td>${status(t.status)}</td>
  <td class="svc">${t.dept}</td>
  <td class="date">${t.date}</td>
  ${withLast ? `<td class="last">${t.rel}</td>` : ''}
</tr>`).join('');

const home = () => `
<div class="ph"><h1>Bonjour ${client.first}</h1><div class="ph-actions"><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 15)} Ouvrir un ticket</a><a class="btn" href="mes-tickets.html">Mes tickets</a></div></div>
<dl class="kpis" aria-label="Résumé">
  <div><dt>Tickets ouverts</dt><dd>3</dd></div>
  <div class="hot"><dt>Réponse reçue</dt><dd>2</dd></div>
  <div><dt>1re réponse, moyenne</dt><dd>2 h 51<small>objectif 4 h</small></dd></div>
  <div><dt>Fermés en 30 jours</dt><dd>3</dd></div>
</dl>
<div class="cols">
  <section class="panel" aria-labelledby="a-t"><div class="p-head"><h2 id="a-t">Demande votre attention</h2><a href="mes-tickets.html">Tout voir</a></div>
    <table class="grid"><thead><tr><th>N°</th><th>Sujet</th><th>Statut</th><th>Service</th><th>Création</th><th>Activité</th></tr></thead><tbody>${rows(ticketsOpen.filter((t) => t.answered))}</tbody></table></section>
  <aside class="panel" aria-labelledby="s-t"><div class="p-head"><h2 id="s-t">Chercher une réponse</h2></div>
    <form class="p-body" role="search" action="base-de-connaissances.html"><label class="visually-hidden" for="hq">Rechercher dans la base de connaissances</label><div class="inline"><input id="hq" type="search" placeholder="Mot-clé, message d'erreur…"><button class="btn btn-primary" type="submit">Chercher</button></div></form>
    <ul class="faq">${featured.map((q) => `<li><a href="base-de-connaissances.html">${icon('file', 'ic', 14)} ${q}</a></li>`).join('')}</ul></aside>
</div>
<div class="cols">
  <section class="panel" aria-labelledby="f-t"><div class="p-head"><h2 id="f-t">Activité récente</h2></div>
    <ol class="feed">
      <li><time>15/10 09:20</time><span><b>Inès M.</b> a répondu sur <a href="suivi-ticket.html">#482913</a> et joint un guide</span></li>
      <li><time>14/10 14:40</time><span><b>Vous</b> avez répondu sur <a href="suivi-ticket.html">#482913</a></span></li>
      <li><time>14/10 10:03</time><span><b>Inès M.</b> a posé une question sur <a href="suivi-ticket.html">#482913</a> (première réponse en 51 min)</span></li>
      <li><time>12/10 16:02</time><span><b>Vous</b> avez ouvert <a href="suivi-ticket.html">#482755</a></span></li>
      <li><time>06/10 11:30</time><span><b>Théo A.</b> a répondu sur <a href="suivi-ticket.html">#481990</a></span></li>
    </ol></section>
  <section class="panel" aria-labelledby="k-t"><div class="p-head"><h2 id="k-t">Sujets de la base de connaissances</h2><a href="base-de-connaissances.html">Tout parcourir</a></div>
    <table class="grid compact"><tbody>${categories.map((c) => `<tr><td class="subj"><a href="base-de-connaissances.html">${icon('folder', 'ic', 14)} ${c.name}</a></td><td class="date">${c.count} articles</td></tr>`).join('')}</tbody></table></section>
</div>`;

const field = (label, control, { id, req = false, help = '', err = '' } = {}) => `<div class="row${err ? ' has-error' : ''}"><label for="${id}">${label}${req ? ' <span class="req" aria-hidden="true">*</span>' : ''}</label><div class="ctl">${control}${help ? `<p class="help" id="${id}-help">${help}</p>` : ''}${err ? `<p class="err" id="${id}-err">${err}</p>` : ''}</div></div>`;

const open = () => `
<div class="ph"><h1>Ouvrir un ticket</h1><p class="ph-sub">Les champs marqués d'un astérisque sont obligatoires. Prévoyez les versions et les journaux.</p></div>
<div class="alert" role="alert"><b>1 erreur.</b> <a href="#subject">Indiquez un sujet</a> pour envoyer votre demande.</div>
<div class="cols-form">
<form id="ticketForm" class="panel form" method="post" action="suivi-ticket.html" novalidate>
  <h2 class="f-sec">Vos coordonnées</h2>
  ${field('E-mail', '<input id="email" type="email" value="camille.roux@atelier-lumen.fr" autocomplete="email">', { id: 'email', req: true })}
  ${field('Nom complet', '<input id="name" type="text" value="Camille Roux" autocomplete="name">', { id: 'name', req: true })}
  ${field('Téléphone', '<input id="phone" type="text" class="short" autocomplete="tel">', { id: 'phone' })}
  <h2 class="f-sec">Demande</h2>
  ${field('Thème d\'aide', `<select id="topicId">${topics.map((t, i) => `<option${i === 0 ? ' selected' : ''}>${t}</option>`).join('')}</select>`, { id: 'topicId', req: true })}
  ${field('Sujet', '<input id="subject" type="text" aria-invalid="true" aria-describedby="subject-help subject-err">', { id: 'subject', req: true, help: 'En une phrase : ce qui se passe, et sur quel plugin.', err: 'Indiquez un sujet pour votre demande.' })}
  ${field('Message', redactor({ id: 'message', label: 'Message', value: '<p>Bonjour,</p><p>Depuis hier soir, l\'archivage des rapports s\'arrête avant la fin…</p>', rows: 6 }), { id: 'message', req: true })}
  ${field('Pièces jointes', filedrop(), { id: 'files' })}
  <h2 class="f-sec">Contexte technique</h2>
  ${field('Plugin', '<select id="plugin" class="mid"><option selected>Heatlens</option><option>Auditlens</option><option>Autre</option></select>', { id: 'plugin', req: true })}
  ${field('Version de Matomo', '<select id="mver" class="mid"><option>5.14.1</option><option selected>5.14.2</option><option>6.0.0-b6</option></select>', { id: 'mver', req: true })}
  ${field('Adresse de Matomo', '<input id="url" type="text" value="https://matomo.atelier-lumen.fr">', { id: 'url', help: 'Aucun accès ne vous sera demandé.' })}
  ${field('Hébergement', '<div class="radios"><label><input type="radio" name="host" checked> Serveur dédié</label><label><input type="radio" name="host"> Mutualisé</label><label><input type="radio" name="host"> Conteneur Docker</label></div>', { id: 'host' })}
  ${field('N° de licence', '<input id="lic" type="text" class="short" placeholder="OWN-…">', { id: 'lic', help: 'Sur votre facture. Donne accès au support prioritaire si vous y avez souscrit.' })}
  ${field('Vérification', '<label class="check"><input id="chk" type="checkbox" checked> J\'ai consulté le journal d\'erreurs de PHP et celui de Matomo.</label>', { id: 'chk' })}
  <div class="f-actions"><button class="btn btn-primary" type="submit">Créer le ticket</button><button class="btn" type="reset">Réinitialiser</button><a class="btn btn-link" href="index.html">Annuler</a></div>
</form>
<aside class="side">
  <section class="panel"><div class="p-head"><h2>Articles suggérés</h2></div><ul class="faq"><li><a href="base-de-connaissances.html">${icon('file', 'ic', 14)} Pourquoi ma heatmap reste vide</a></li><li><a href="base-de-connaissances.html">${icon('file', 'ic', 14)} Mettre à jour un plugin sans perdre de données</a></li><li><a href="base-de-connaissances.html">${icon('file', 'ic', 14)} Version minimale de PHP requise</a></li></ul></section>
  <section class="panel"><div class="p-head"><h2>Délais de réponse</h2></div><dl class="kv"><div><dt>Support inclus</dt><dd>Jours ouvrés</dd></div><div><dt>Prioritaire</dt><dd>1re réponse sous 4 h</dd></div><div><dt>Langues</dt><dd>FR, EN</dd></div></dl></section>
  <section class="panel"><div class="p-head"><h2>Avant d'envoyer</h2></div><ul class="chk"><li>${icon('check', 'ic', 14)} Versions de Matomo et du plugin</li><li>${icon('check', 'ic', 14)} Journal d'erreurs joint</li><li>${icon('check', 'ic', 14)} Aucune donnée de visiteur</li></ul></section>
</aside>
</div>`;

const login = () => `
<div class="ph"><h1>Connexion</h1><p class="ph-sub">Connectez-vous à votre compte, ou suivez un ticket avec son numéro.</p></div>
<div class="cols-two">
<form class="panel form compact" action="mes-tickets.html" method="post" aria-labelledby="l1"><h2 id="l1" class="f-sec">Compte</h2>
  <div class="stackf"><label for="username">E-mail ou identifiant</label><input id="username" type="text" autocomplete="username"></div>
  <div class="stackf"><label for="passwd">Mot de passe</label><input id="passwd" type="password" autocomplete="current-password"></div>
  <div class="f-actions"><button class="btn btn-primary" type="submit">Se connecter</button><a href="#">Mot de passe oublié</a></div>
  <p class="foot-note">Pas encore de compte ? <a href="#">Créer un compte</a>. Agent ? <a href="#">Connexion agents</a>.</p></form>
<form class="panel form compact" action="suivi-ticket.html" method="post" aria-labelledby="l2"><h2 id="l2" class="f-sec">Suivre un ticket sans compte</h2>
  <div class="stackf"><label for="email">E-mail</label><input id="email" type="text" placeholder="camille@exemple.fr"></div>
  <div class="stackf"><label for="ticketno">N° de ticket</label><input id="ticketno" type="text" class="short" placeholder="482913"></div>
  <div class="f-actions"><button class="btn" type="submit">Envoyer le lien d'accès</button></div>
  <p class="foot-note">Première demande, ou numéro perdu ? <a href="ouvrir-un-ticket.html">Ouvrir un ticket</a>.</p></form>
</div>`;

const entry = (e, i) => `<article class="entry ${e.type}" aria-label="${e.who}, ${e.at}">
  <span class="e-av ${e.type}" aria-hidden="true">${e.initials}</span>
  <div class="e-main"><header><b>${e.who}</b>${e.type === 'response' ? '<span class="tag">Équipe</span>' : ''}<time datetime="2026-10-14">${e.at}</time><span class="e-n">#${i + 1}</span></header>
    <div class="e-body">${e.body.map((p) => (p.startsWith('<code>') ? `<pre>${p.replace(/<\/?code>/g, '')}</pre>` : `<p>${p}</p>`)).join('')}${fileChips(e.files)}</div></div></article>`;

const ticketPage = () => `
<nav class="crumbs" aria-label="Fil d'Ariane"><a href="mes-tickets.html">Mes tickets</a><span aria-hidden="true">/</span><span aria-current="page">#${ticket.number}</span></nav>
<div class="ph t-head"><h1>${esc(ticket.subject)}</h1>
  <div class="ph-actions"><a class="btn" href="#">${icon('print', 'ic', 15)} Imprimer</a><a class="btn" href="#">${icon('edit', 'ic', 15)} Modifier</a><a class="btn" href="suivi-ticket.html">${icon('refresh', 'ic', 15)} Actualiser</a></div></div>
<div class="cols-ticket">
  <div class="main-col">
    <div class="thread" role="feed" aria-label="Échanges">${thread.map(entry).join('')}</div>
    <form id="reply" class="panel form reply" action="suivi-ticket.html#reply" method="post"><h2 class="f-sec">Répondre</h2>
      <p class="help">Soyez précis et détaillé. Joignez journaux et captures sans données de visiteurs.</p>
      ${redactor({ id: 'reply-message', label: 'Votre réponse', rows: 5 })}
      ${filedrop()}
      <div class="f-actions"><button class="btn btn-primary" type="submit">Envoyer la réponse</button><button class="btn" type="reset">Effacer</button><span class="grow"></span><span class="hint">Entrée pour un saut de ligne, <kbd>Ctrl</kbd> + <kbd>Entrée</kbd> pour envoyer</span></div></form>
  </div>
  <aside class="side">
    <section class="panel"><div class="p-head"><h2>Propriétés</h2></div>
      <dl class="kv"><div><dt>N°</dt><dd class="mono">#${ticket.number}</dd></div><div><dt>Statut</dt><dd>${status('Ouvert')}</dd></div><div><dt>Support</dt><dd><span class="tag prio">Prioritaire</span></dd></div><div><dt>Service</dt><dd>${ticket.dept}</dd></div><div><dt>Création</dt><dd class="mono">${ticket.created}</dd></div>
      ${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}</dl></section>
    <section class="panel"><div class="p-head"><h2>Première réponse</h2></div>
      <div class="p-body"><div class="meter" role="img" aria-label="51 minutes sur 4 heures"><span style="width:21%"></span></div><p class="meter-t"><b>51 min</b> sur 4 h : objectif tenu</p></div></section>
    <section class="panel"><div class="p-head"><h2>Pièces jointes</h2><span class="muted">4</span></div>
      <ul class="files"><li>${icon('clip', 'ic', 14)}<a href="#">capture-heatmap-vide.png</a><small>84 Ko</small></li><li>${icon('clip', 'ic', 14)}<a href="#">matomo-extrait.log</a><small>12 Ko</small></li><li>${icon('clip', 'ic', 14)}<a href="#">sortie-archivage.txt</a><small>3 Ko</small></li><li>${icon('clip', 'ic', 14)}<a href="#">guide-reprise-archivage.pdf</a><small>212 Ko</small></li></ul></section>
    <section class="panel"><div class="p-head"><h2>Vos coordonnées</h2></div>
      <dl class="kv"><div><dt>Nom</dt><dd>${client.name}</dd></div><div><dt>E-mail</dt><dd>${client.email}</dd></div><div><dt>Téléphone</dt><dd class="mono">${client.phone}</dd></div></dl></section>
  </aside>
</div>`;

const listPage = () => `
<div class="ph"><h1>Mes tickets</h1><div class="ph-actions"><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 15)} Ouvrir un ticket</a></div></div>
<form class="filters" role="search" action="mes-tickets.html">
  <div class="seg" role="group" aria-label="Statut"><a href="#" aria-current="true">Ouverts <b>3</b></a><a href="#">Fermés <b>3</b></a><a href="#">Tous <b>6</b></a></div>
  <label class="visually-hidden" for="kw">Rechercher</label><div class="fsearch">${icon('search', 'ic', 15)}<input id="kw" type="search" placeholder="Numéro ou mot du sujet"></div>
  <label class="visually-hidden" for="tp">Thème d'aide</label><select id="tp"><option>Tous les thèmes</option>${topics.map((t) => `<option>${t}</option>`).join('')}</select>
  <a class="reset" href="#">Réinitialiser les filtres</a>
</form>
<div class="panel tbl">
<table class="grid" id="ticketTable"><caption class="visually-hidden">Tickets ouverts, 3 résultats</caption>
<thead><tr><th scope="col"><a href="#" aria-label="Trier par numéro">N° ${icon('sort', 'ic', 12)}</a></th><th scope="col"><a href="#">Sujet ${icon('sort', 'ic', 12)}</a></th><th scope="col"><a href="#">Statut ${icon('sort', 'ic', 12)}</a></th><th scope="col"><a href="#">Service ${icon('sort', 'ic', 12)}</a></th><th scope="col"><a href="#" class="on" aria-label="Trié par création, décroissant">Création ${icon('sort', 'ic', 12)}</a></th><th scope="col">Dernière activité</th></tr></thead>
<tbody>${rows(ticketsOpen)}</tbody></table>
<div class="t-foot"><span>1 à 3 sur 3 tickets ouverts</span><span class="grow"></span><label for="pp">Lignes par page</label><select id="pp"><option>25</option><option>50</option></select><span class="pager"><a href="#" aria-label="Page précédente" aria-disabled="true">‹</a><b>1</b><a href="#" aria-label="Page suivante" aria-disabled="true">›</a></span></div>
</div>
<p class="legend"><span class="dot"></span> Nouvelle réponse de l'équipe depuis votre dernière visite.</p>`;

const kbPage = () => `
<div class="ph"><h1>Base de connaissances</h1><p class="ph-sub">42 articles. Mise à jour le 16/10/2026.</p></div>
<form class="kbsearch" role="search" action="base-de-connaissances.html"><label class="visually-hidden" for="q">Rechercher</label><div class="inline"><input id="q" type="search" placeholder="Rechercher un mot-clé ou un message d'erreur"><select aria-label="Parcourir par thème"><option>Tous les thèmes</option>${topics.map((t) => `<option>${t}</option>`).join('')}</select><button class="btn btn-primary" type="submit">Chercher</button></div></form>
<div class="cols">
<section class="panel" aria-labelledby="c-t"><div class="p-head"><h2 id="c-t">Catégories</h2></div>
  <table class="grid kb"><thead><tr><th>Catégorie</th><th>Premiers articles</th><th class="r">Articles</th></tr></thead><tbody>${categories.map((c) => `<tr><td class="subj"><a href="base-de-connaissances.html">${icon('folder', 'ic', 14)} ${c.name}</a><span class="sub">${c.desc}</span></td><td class="arts">${c.faqs.map((f) => `<a href="base-de-connaissances.html">${f}</a>`).join('')}</td><td class="num r">${c.count}</td></tr>`).join('')}</tbody></table></section>
<aside class="side">
  <section class="panel"><div class="p-head"><h2>Les plus lus</h2></div><ol class="rank">${featured.map((q, i) => `<li><a href="base-de-connaissances.html">${q}</a><span class="num">${[412, 301, 255, 198, 164][i]}</span></li>`).join('')}</ol></section>
  <section class="panel"><div class="p-head"><h2>Mis à jour récemment</h2></div><ul class="rank"><li><a href="base-de-connaissances.html">Matomo 6 : état de la compatibilité</a><span class="num">16/10</span></li><li><a href="base-de-connaissances.html">Exporter le journal d'audit en CSV</a><span class="num">09/10</span></li><li><a href="base-de-connaissances.html">Qu'est-ce qu'une première réponse</a><span class="num">02/10</span></li></ul></section>
</aside>
</div>`;

export default {
  dir: '04-poste-de-travail',
  pages: {
    home: () => shell(home(), 'home', true, 'Tableau de bord'),
    open: () => shell(open(), 'open', false, 'Ouvrir un ticket'),
    login: () => shell(login(), 'login', false, 'Connexion'),
    ticket: () => shell(ticketPage(), 'tickets', true, 'Ticket #482913'),
    tickets: () => shell(listPage(), 'tickets', true, 'Mes tickets'),
    kb: () => shell(kbPage(), 'kb', false, 'Base de connaissances'),
  },
};
