// 02 Poste de travail : dense, rail latéral, tableaux serrés, panneau de propriétés.
import {
  doc, icon, esc, field, errorSummary, redactor, filedrop, fileChips, footerInfo, logoH, signature,
  slaBlock, statusChip, planChip, initialsAvatar, sommet, client, thread, ticket, tickets, categories, featured, topics, products, CONTACT,
} from './common.mjs';
import { article, articleProse, related } from './article.mjs';

const NAV = (active) => [
  ['home', 'index.html', 'home', 'Accueil', ''],
  ['tickets', 'mes-tickets.html', 'ticket', 'Mes tickets', '<span class="badge">3</span>'],
  ['open', 'ouvrir-un-ticket.html', 'plus', 'Ouvrir un ticket', ''],
  ['kb', 'base-de-connaissances.html', 'book', 'Base de connaissances', ''],
].map(([k, h, i, l, b]) => ({ k, h, i, l, b, cur: k === active || (active === 'ticket' && k === 'tickets') || (active === 'article' && k === 'kb') }));

function app({ title, active, body, bodyClass = '', crumb = [] }) {
  const items = NAV(active);
  const rail = items.map((n) => `<li><a href="${n.h}"${n.cur ? ' aria-current="page"' : ''}>${icon(n.i, 'ic', 20)}<span>${n.l}</span>${n.b}</a></li>`).join('');
  const mob = items.map((n) => `<li><a href="${n.h}"${n.cur ? ' aria-current="page"' : ''}>${n.l}</a></li>`).join('');
  const f = footerInfo({ logo: false });
  return doc({
    title, bodyClass: `p02 ${bodyClass}`,
    body: `<div class="app">
<aside class="rail on-dark"><a class="brand" href="index.html" aria-label="Coderise : accueil du support">${logoH()}</a>
${signature()}
<nav class="rail-nav" aria-label="Navigation principale"><ul id="nav">${rail}</ul></nav>
<div class="rail-foot"><p class="plan-box"><span class="k">${icon('bolt', 'ic', 16)}Support Priority</span>Premier retour sous 4 h, 24 h/24</p>
<a class="rail-link" href="tel:${CONTACT.phoneHref}">${icon('phone', 'ic', 18)}${CONTACT.phone}</a>
<a class="rail-link" href="connexion.html">${icon('login', 'ic', 18)}Se déconnecter</a></div>
<details class="menu-mobile"><summary>${icon('menu', 'ic', 20)}<span>Menu</span></summary><ul>${mob}<li><a href="connexion.html">Se déconnecter</a></li></ul></details>
</aside>
<div class="main-col">
<header class="topbar"><nav class="crumbs" aria-label="Fil d'Ariane"><ol>${crumb.map(([h, t]) => `<li>${h ? `<a href="${h}">${t}</a>` : `<span aria-current="page">${t}</span>`}</li>`).join('')}</ol></nav>
<form class="tsearch" role="search" method="get" action="kb/faq.php"><label class="sr-only" for="q">Rechercher un ticket ou un article</label>${icon('search', 'ic', 18)}<input id="q" type="search" placeholder="Rechercher un ticket ou un article"><kbd>/</kbd></form>
<a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Nouveau ticket</span></a>
<a class="who" href="mes-tickets.html" aria-label="Compte de ${client.name}">${initialsAvatar(client.initials)}<span>${client.first}<small>${client.org}</small></span></a></header>
<main id="content">${body}</main>
<footer class="foot"><div class="foot-in"><div>${f.contact}</div><div>${f.support}</div><div>${f.links}</div></div><div class="foot-legal">${f.legal}</div></footer>
</div></div>`,
  });
}

function authShell({ title, body }) {
  return doc({
    title, bodyClass: 'p02 auth',
    body: `<header class="auth-bar on-dark"><a class="brand" href="index.html" aria-label="Coderise : accueil du support">${logoH()}</a><a class="btn btn-line-dark" href="base-de-connaissances.html">${icon('book', 'ic', 18)}<span>Base de connaissances</span></a></header>
<main id="content" class="auth-main">${body}</main>
<footer class="auth-foot"><p class="small"><a href="mailto:${CONTACT.email}">${CONTACT.email}</a> · ${CONTACT.phone} · Support standard : jours ouvrés ; Priority : 24 h/24. © 2026 Coderise, propulsé par <a href="https://osticket.com">osTicket</a>.</p></footer>`,
  });
}

const panel = (h, inner, cls = '', actions = '') => `<section class="panel ${cls}"><header class="panel-h"><h2>${h}</h2>${actions}</header>${inner}</section>`;
const titleRow = (h1, sub, actions = '') => `<div class="title-row"><div><h1>${h1}</h1>${sub ? `<p class="sub">${sub}</p>` : ''}</div>${actions ? `<div class="t-actions">${actions}</div>` : ''}</div>`;

function ticketsTable(rows, { sel = -1, compact = false } = {}) {
  const hide = compact ? ' hide-c' : '';
  return `<div class="table-wrap${hide}"><table class="data" id="ticketTable"><caption class="sr-only">Mes tickets</caption><thead><tr><th scope="col" class="c-n">N°</th><th scope="col">Sujet</th><th scope="col" class="c-prod">Produit</th><th scope="col">Contrat</th><th scope="col">Statut</th><th scope="col" class="c-act">Dernière activité</th></tr></thead><tbody>
${rows.map((t, i) => `<tr class="${i === sel ? 'is-sel' : ''}${t.status === 'Fermé' ? ' is-closed' : ''}"><td data-label="N°" class="c-n"><a href="suivi-ticket.html">#${t.n}</a></td><td data-label="Sujet" class="c-subj"><a href="suivi-ticket.html">${t.subject}</a><small>${t.topic} · ouvert le ${t.date}</small></td><td data-label="Produit" class="c-prod">${t.product}</td><td data-label="Contrat">${planChip(t.plan)}</td><td data-label="Statut">${statusChip(t.status)}</td><td data-label="Dernière activité" class="c-act">${t.last}</td></tr>`).join('')}
</tbody></table></div>`;
}

function entry(e) {
  if (e.type === 'event') return `<li class="thread-event">${icon('flag', 'ic', 15)}<span>${e.text}</span><time>${e.at}</time></li>`;
  const team = e.type === 'response';
  return `<li class="thread-entry ${e.type}${e.last ? ' latest' : ''}"><div class="gut">${initialsAvatar(e.initials, team ? 'av-team' : '')}</div>
<div class="body"><header class="header"><strong>${e.who}</strong><span class="role">${team ? 'Équipe Coderise' : 'Vous'}</span><time>${e.at}</time></header>
<div class="thread-body">${e.body.map((p) => (p.startsWith('<pre') ? p : `<p>${p}</p>`)).join('')}</div>${fileChips(e.files)}</div></li>`;
}

export default {
  dir: '02-poste-de-travail',
  name: 'Poste de travail',
  pages: {
    home: () => app({
      title: 'Accueil', active: 'home', bodyClass: 'home', crumb: [[null, 'Accueil']],
      body: `<div class="welcome"><div><h1>Bonjour Léa, faisons monter votre projet d'un niveau.</h1><p>3 tickets ouverts. 1 attend votre réponse : <a href="suivi-ticket.html">#260397, export CSV du back-office</a>.</p></div><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Ouvrir un ticket</span></a></div>
<div class="stats" role="list"><div role="listitem"><b>3</b><span>tickets ouverts</span></div><div role="listitem"><b>1</b><span>attend votre réponse</span></div><div role="listitem" data-needs-dev="stats"><b>52 min</b><span>premier retour moyen, 90 jours</span></div><div role="listitem" data-needs-dev="stats"><b>5</b><span>tickets résolus ce trimestre</span></div></div>
<div class="cols"><div class="col-main">${panel('Tickets récents', ticketsTable(tickets.slice(0, 4), { sel: 0, compact: true }), '', '<a class="more" href="mes-tickets.html">Tous mes tickets</a>')}
${panel('Articles recommandés', `<ul class="kb-rows">${featured.slice(0, 5).map((a) => `<li><a href="article.html">${icon('file', 'ic', 18)}<span>${a[0]}</span><small>${a[1]}</small><small class="rt">${a[2]}</small></a></li>`).join('')}</ul>`, '', '<a class="more" href="base-de-connaissances.html">Base de connaissances</a>')}</div>
<div class="col-side">${panel('Votre contrat', `<dl class="props" data-needs-dev="contract"><div><dt>Niveau</dt><dd>${planChip('Priority')}</dd></div><div><dt>Premier retour</dt><dd>Sous 4 h, 24 h/24, 7 j/7</dd></div><div><dt>Standard</dt><dd>Jours ouvrés</dd></div><div><dt>Produits couverts</dt><dd>Verdier Commandes<br>Agent de tri des réclamations</dd></div></dl>`)}
${panel('Un incident bloquant ?', `<div class="pad"><p>Ouvrez un ticket en priorité Urgente, puis appelez-nous : une personne vous répond.</p><a class="btn btn-line" href="tel:${CONTACT.phoneHref}">${icon('phone', 'ic', 18)}<span>${CONTACT.phone}</span></a></div>`)}</div></div>`,
    }),

    open: () => {
      const errs = [['subject', 'Sujet : indiquez un sujet en une phrase.'], ['environnement', 'Environnement : choisissez une valeur.'], ['message', 'Description : 20 caractères au minimum.']];
      const row = (inner) => inner;
      return app({
        title: 'Ouvrir un ticket', active: 'open', crumb: [['index.html', 'Accueil'], [null, 'Ouvrir un ticket']], bodyClass: 'form-page',
        body: `${titleRow('Ouvrir un ticket', 'Les champs marqués d\'une étoile sont obligatoires.')}
<div class="cols form-cols"><form id="ticketForm" class="panel form" method="post" action="open.php" enctype="multipart/form-data" novalidate>
${errorSummary(errs)}
<fieldset><legend>Demande</legend>
${row(field({ id: 'email', label: 'E-mail', type: 'email', value: client.email, readonly: true }))}
${row(field({ id: 'topicId', label: 'Sujet d\'aide', required: true, options: topics, value: topics[0] }))}
${row(field({ id: 'product', label: 'Produit ou licence', required: true, options: products, value: products[0] }))}
${row(field({ id: 'priority', label: 'Priorité', options: ['Normale', 'Haute', 'Urgente'], value: 'Urgente', hint: 'Urgente : incidents bloquants. Priority : premier retour sous 4 h.' }))}</fieldset>
<fieldset id="dynamic-form"><legend>Contexte de l'incident</legend>
${field({ id: 'environnement', label: 'Environnement', required: true, dynamic: true, error: 'Choisissez l\'environnement concerné.', options: [['', 'Choisir…'], 'Production', 'Recette', 'Développement'] })}
${field({ id: 'impact', label: 'Impact', required: true, dynamic: true, options: ['Bloquant', 'Dégradé', 'Mineur'], value: 'Bloquant' })}
${field({ id: 'since', label: 'Début', value: '20/10/2026 21:30', dynamic: true })}
${field({ id: 'version', label: 'Version concernée', value: 'API v2.8.0', dynamic: true })}
${field({ id: 'rgpd', type: 'checkbox', label: 'Mes pièces jointes ne contiennent pas de données personnelles', checked: true })}</fieldset>
<fieldset><legend>Message</legend>
${field({ id: 'subject', label: 'Sujet', required: true, error: 'Indiquez un sujet en une phrase.' })}
<div class="field has-error"><label for="message">Description <span class="req" aria-hidden="true">*</span></label><div class="control">${redactor({ id: 'message', label: 'Description', value: 'Depuis hier…', rows: 7, error: 'x' })}<p class="error" id="message-err">${icon('alert', 'ic', 16)}<span>Décrivez ce que vous observez (20 caractères au minimum).</span></p></div></div>
<div class="field"><span class="lbl">Pièces jointes</span><div class="control">${filedrop()}</div></div></fieldset>
<div class="form-actions"><button class="btn btn-primary" type="submit">Envoyer le ticket</button><a class="btn btn-line" href="mes-tickets.html">Annuler</a></div>
</form>
<aside class="col-side">${panel('Articles suggérés', `<ul class="kb-rows" data-needs-dev="suggest">${featured.slice(1, 4).map((a) => `<li><a href="article.html">${icon('file', 'ic', 18)}<span>${a[0]}</span></a></li>`).join('')}</ul><p class="pad hint">La liste se met à jour pendant la saisie du sujet.</p>`)}
${panel('Délais de réponse', `<dl class="props"><div><dt>Standard</dt><dd>Jours ouvrés</dd></div><div><dt>Priority</dt><dd>Sous 4 h, 24 h/24</dd></div></dl>`)}</aside></div>`,
      });
    },

    login: () => authShell({
      title: 'Connexion',
      body: `<div class="auth-grid"><section class="panel"><header class="panel-h"><h1>Connexion</h1></header><div class="pad">
<div class="form-errors" role="alert"><h2>${icon('alert', 'ic', 20)} Connexion impossible</h2><p>Adresse e-mail ou mot de passe incorrect. Vérifiez-les ou demandez un lien de connexion.</p></div>
<form id="loginForm" method="post" action="login.php" novalidate>${field({ id: 'luser', name: 'luser', label: 'E-mail', type: 'email', required: true, value: client.email })}${field({ id: 'lpasswd', name: 'lpasswd', label: 'Mot de passe', type: 'password', required: true, error: 'Le mot de passe est incorrect.' })}
<div class="form-actions"><button class="btn btn-primary" type="submit">Se connecter</button><a href="#">Mot de passe oublié ?</a></div></form>
<p class="after">Pas de compte ? <a href="inscription.html">Créer un compte</a></p></div></section>
<section class="panel"><header class="panel-h"><h2>Suivre un ticket sans compte</h2></header><div class="pad"><p>Saisissez votre e-mail et le numéro reçu dans l'e-mail de confirmation.</p><form method="post" action="login.php" novalidate>${field({ id: 'lemail', label: 'E-mail', type: 'email', required: true })}${field({ id: 'lticket', label: 'N° de ticket', required: true, placeholder: '260418' })}<div class="form-actions"><button class="btn btn-line" type="submit">Voir le ticket</button></div></form></div></section></div>`,
    }),

    register: () => authShell({
      title: 'Créer un compte',
      body: `<div class="auth-grid one"><section class="panel"><header class="panel-h"><h1>Créer un compte</h1></header><form class="pad" method="post" action="account.php?do=create" novalidate>
<div class="g2">${field({ id: 'rname', label: 'Nom', required: true, value: 'Léa Marchand' })}${field({ id: 'rorg', label: 'Société', value: 'Maison Verdier' })}</div>
<div class="g2">${field({ id: 'remail', label: 'E-mail professionnel', type: 'email', required: true, value: client.email, hint: 'Le lien de confirmation y sera envoyé.' })}${field({ id: 'rphone', label: 'Téléphone', type: 'tel', hint: 'Facultatif, utile pour un incident bloquant.' })}</div>
<div class="g2">${field({ id: 'rpass', label: 'Mot de passe', type: 'password', required: true, hint: '12 caractères minimum.' })}${field({ id: 'rpass2', label: 'Confirmation', type: 'password', required: true })}</div>
${field({ id: 'rcgu', type: 'checkbox', label: 'J\'accepte que Coderise traite mes données pour répondre à mes demandes (<a href="#">politique de confidentialité</a>)', checked: true })}
<div class="form-actions"><button class="btn btn-primary" type="submit">Créer le compte</button><a class="btn btn-line" href="connexion.html">J'ai déjà un compte</a></div></form></section></div>`,
    }),

    registered: () => authShell({
      title: 'Compte créé',
      body: `<div class="auth-grid one"><section class="panel done"><div class="pad"><span class="done-ic">${icon('mail', 'ic', 32)}</span><h1>Vérifiez votre boîte mail</h1>
<p>Un lien de confirmation a été envoyé à <strong>${client.email}</strong>. Il est valable 24 h.</p>
<ol class="next"><li>Ouvrez « Confirmez votre compte Coderise ».</li><li>Cliquez sur le bouton de confirmation.</li><li>Ouvrez votre premier ticket.</li></ol>
<p class="hint">Rien reçu ? Regardez dans les courriers indésirables ou <a href="#">renvoyez l'e-mail</a>.</p>
<a class="btn btn-primary" href="connexion.html">Aller à la connexion</a></div></section></div>`,
    }),

    ticket: () => app({
      title: `Ticket #${ticket.number}`, active: 'ticket', crumb: [['mes-tickets.html', 'Mes tickets'], [null, `#${ticket.number}`]], bodyClass: 'ticket-page',
      body: `<div class="title-row"><div><p class="idline">#${ticket.number} ${statusChip('Ouvert')} ${planChip('Priority')} <span class="urg">${icon('flag', 'ic', 14)}Urgente</span></p><h1>${ticket.subject}</h1></div>
<div class="t-actions"><a class="btn btn-primary" href="#reply">${icon('message', 'ic', 18)}<span>Répondre</span></a><a class="btn btn-line" href="#">${icon('print', 'ic', 18)}<span>Imprimer</span></a><a class="btn btn-line" href="#">${icon('edit', 'ic', 18)}<span>Modifier</span></a></div></div>
<div class="cols ticket-cols"><div class="col-main"><section class="panel" aria-label="Fil de discussion"><header class="panel-h"><h2>Fil de discussion</h2><span class="muted">5 messages · 2 pièces jointes</span></header>
<ol class="thread">${thread.map((e, i) => entry({ ...e, last: i === thread.length - 1 })).join('')}</ol></section>
<form id="reply" class="panel reply" method="post" action="tickets.php?id=${ticket.number}"><header class="panel-h"><h2>Répondre</h2></header><div class="pad">${redactor({ id: 'response', label: 'Votre réponse', rows: 5 })}
<div class="field"><span class="lbl">Pièces jointes</span>${filedrop().replace(/<ul class="files"[\s\S]*?<\/ul>/, '')}</div>
<div class="form-actions"><button class="btn btn-primary" type="submit">Envoyer la réponse</button><button class="btn btn-line" type="reset">Effacer</button></div></div></form></div>
<aside class="col-side sticky"><section class="panel"><header class="panel-h"><h2>Délai Priority</h2></header><div class="pad">${slaBlock()}</div></section>
${panel('Propriétés', `<dl class="props"><div><dt>Statut</dt><dd>${statusChip('Ouvert')}</dd></div><div><dt>Priorité</dt><dd>${ticket.priority}</dd></div><div><dt>Contrat</dt><dd>${planChip('Priority')}</dd></div><div><dt>Département</dt><dd>${ticket.dept}</dd></div><div><dt>Sujet d'aide</dt><dd>${ticket.topic}</dd></div>${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}<div><dt>Créé le</dt><dd>${ticket.created}</dd></div><div><dt>Dernière activité</dt><dd>${ticket.last}</dd></div></dl>`)}
${panel('Pièces jointes', `<ul class="kb-rows files-list">${thread.flatMap((e) => e.files || []).map(([n, s]) => `<li><a href="#">${icon('clip', 'ic', 16)}<span>${n}</span><small class="rt">${s}</small></a></li>`).join('')}</ul>`)}</aside></div>`,
    }),

    tickets: () => app({
      title: 'Mes tickets', active: 'tickets', crumb: [['index.html', 'Accueil'], [null, 'Mes tickets']], bodyClass: 'list-page',
      body: `${titleRow('Mes tickets', '3 ouverts, 3 fermés', `<a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Nouveau ticket</span></a>`)}
<section class="panel"><div class="filters"><div class="seg" role="tablist"><a role="tab" aria-selected="true" href="#">Ouverts <span class="count">3</span></a><a role="tab" aria-selected="false" href="#">Fermés <span class="count">3</span></a><a role="tab" aria-selected="false" href="#">Tous <span class="count">6</span></a></div>
<div class="sel"><label for="fprod">Produit</label><select id="fprod"><option>Tous</option><option>Verdier Commandes</option></select></div><div class="sel"><label for="fplan">Contrat</label><select id="fplan"><option>Tous</option><option>Priority</option><option>Standard</option></select></div></div>
${ticketsTable(tickets, { sel: 0 })}<footer class="tfoot"><span>1 à 6 sur 6 tickets</span><span class="muted">Trier par : dernière activité</span></footer></section>`,
    }),

    ticketsEmpty: () => app({
      title: 'Mes tickets', active: 'tickets', crumb: [['index.html', 'Accueil'], [null, 'Mes tickets']], bodyClass: 'list-page',
      body: `${titleRow('Mes tickets', 'Aucun ticket pour le moment')}
<section class="panel empty"><span class="empty-mark">${sommet()}</span><h2>Aucun ticket ouvert. Tout roule.</h2><p>Vos futurs tickets apparaîtront ici, avec leur statut et le délai de réponse.</p>
<div class="form-actions"><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Ouvrir un ticket</span></a><a class="btn btn-line" href="base-de-connaissances.html">Chercher dans les guides</a></div></section>`,
    }),

    kb: () => app({
      title: 'Base de connaissances', active: 'kb', crumb: [['index.html', 'Accueil'], [null, 'Base de connaissances']], bodyClass: 'kb-page',
      body: `${titleRow('Base de connaissances', '54 articles. Tapez / pour rechercher.')}
<div class="cols"><div class="col-main">${panel('Les plus lus', `<ul class="kb-rows">${featured.map((a) => `<li><a href="article.html">${icon('file', 'ic', 18)}<span>${a[0]}</span><small>${a[1]}</small><small class="rt">${a[2]}</small></a></li>`).join('')}</ul>`)}
<div class="cat-grid">${categories.slice(0, 4).map((c) => `<section class="panel"><header class="panel-h"><h2>${icon(c.icon, 'ic', 18)} ${c.name}</h2><span class="muted">${c.count}</span></header><p class="pad cdesc">${c.desc}</p><ul class="kb-rows">${featured.slice(0, 2).map((a) => `<li><a href="article.html">${icon('file', 'ic', 16)}<span>${a[0]}</span></a></li>`).join('')}</ul></section>`).join('')}</div></div>
<aside class="col-side">${panel('Thèmes', `<ul class="cat-list">${categories.map((c, i) => `<li><a href="article.html"${i === 0 ? ' aria-current="true"' : ''}>${icon(c.icon, 'ic', 18)}<span>${c.name}</span><small class="rt">${c.count}</small></a></li>`).join('')}</ul>`)}</aside></div>`,
    }),

    article: () => app({
      title: article.title, active: 'article', crumb: [['index.html', 'Accueil'], ['base-de-connaissances.html', 'Base de connaissances'], [null, article.cat]], bodyClass: 'article-page',
      body: `<div class="cols article-cols"><article class="panel prose"><header><p class="idline">${article.cat}</p><h1>${article.title}</h1><p class="by">${initialsAvatar(article.author.initials, 'av-team')}<span>${article.author.name} · mis à jour le ${article.updated} · ${article.read} de lecture</span></p></header>${articleProse()}
<div class="helpful" role="group" aria-label="Cet article vous a-t-il aidé ?"><span>Cet article vous a-t-il aidé ?</span><button class="btn btn-line" type="button">Oui</button><button class="btn btn-line" type="button">Non</button></div></article>
<aside class="col-side sticky">${panel('Sur cette page', `<ol class="toc">${article.toc.map(([id, t], i) => `<li><a href="#${id}"${i === 0 ? ' aria-current="true"' : ''}>${t}</a></li>`).join('')}</ol>`)}
${panel('Toujours bloqué ?', `<div class="pad"><p>Une personne de l'équipe vous répond.</p><a class="btn btn-primary" href="ouvrir-un-ticket.html">Ouvrir un ticket</a></div>`)}
${panel('À lire aussi', `<ul class="kb-rows">${related.map((r) => `<li><a href="article.html">${icon('file', 'ic', 16)}<span>${r}</span></a></li>`).join('')}</ul>`)}</aside></div>`,
    }),
  },
};
