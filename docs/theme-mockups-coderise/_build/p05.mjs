// 05 Référentiel : documentation d'abord, fil de ticket façon suivi d'incident (usage développeur).
import {
  doc, icon, field, errorSummary, redactor, filedrop, fileChips, footerInfo, logoH,
  statusChip, planChip, initialsAvatar, sommet, client, team, thread, ticket, tickets, categories, featured, topics, products, CONTACT,
} from './common.mjs';
import { article, articleProse, related } from './article.mjs';

const TREE = [
  ['Premiers pas avec le support', ['Support standard ou Priority : lequel choisir', 'Signaler un incident en production : la marche à suivre', 'Que mettre dans un ticket pour être aidé plus vite']],
  ['Applications web', ['Environnements : développement, recette, production', 'Sauvegardes et restauration', 'Mesurer les performances d\'une page']],
  ['Applications mobiles', ['Publier une mise à jour sur les stores', 'Versions minimales prises en charge', 'Notifications push : diagnostiquer un échec']],
  ['Agents IA et automatisations', ['Surveiller un agent autonome : ce qu\'il faut regarder', 'Ajuster les consignes d\'un agent', 'Lire le journal des décisions d\'un agent']],
  ['Mise en production', ['Lire le journal de déploiement d\'une livraison', 'Revenir en arrière après une livraison', 'Calendrier des livraisons']],
  ['Contrats et facturation', ['Les niveaux de support', 'Lire une facture', 'Renouveler un contrat']],
  ['Sécurité et conformité', ['Faire tourner une clé d\'API sans coupure de service', 'Accès et habilitations', 'Données personnelles dans les tickets']],
];
const catIcon = Object.fromEntries(categories.map((c) => [c.name, c.icon]));

const NAV = (active) => [
  ['kb', 'base-de-connaissances.html', 'book', 'Documentation'],
  ['tickets', 'mes-tickets.html', 'ticket', 'Mes tickets'],
  ['open', 'ouvrir-un-ticket.html', 'plus', 'Ouvrir un ticket'],
].map(([k, h, i, l]) => ({ k, h, i, l, cur: k === active || (active === 'ticket' && k === 'tickets') || (active === 'article' && k === 'kb') || (active === 'home' && k === 'kb') }));

function shell({ title, active, logged = true, body, bodyClass = '', crumb = [] }) {
  const items = NAV(active);
  const li = items.map((n) => `<li><a href="${n.h}"${n.cur ? ' aria-current="page"' : ''}>${icon(n.i, 'ic', 18)}<span>${n.l}</span></a></li>`).join('');
  const f = footerInfo();
  return doc({
    title, bodyClass: `p05 ${bodyClass}`,
    body: `<header class="site-head on-dark"><div class="wrap head-in">
<a class="brand" href="index.html" aria-label="Coderise : accueil du support">${logoH()}</a>
<nav class="nav-desktop" aria-label="Navigation principale"><ul id="nav">${li}</ul></nav>
<form class="hsearch" role="search" method="get" action="kb/faq.php"><label class="sr-only" for="q">Rechercher dans la documentation</label>${icon('search', 'ic', 18)}<input id="q" type="search" placeholder="Rechercher un guide"><kbd>/</kbd></form>
${logged ? `<a class="user-chip" href="mes-tickets.html">${initialsAvatar(client.initials)}<span>${client.first}</span></a>` : `<a class="btn btn-line-dark" href="connexion.html">${icon('login', 'ic', 18)}<span>Se connecter</span></a>`}
<details class="menu-mobile"><summary>${icon('menu', 'ic', 20)}<span>Menu</span></summary><ul>${items.map((n) => `<li><a href="${n.h}">${n.l}</a></li>`).join('')}<li><a href="connexion.html">Se connecter</a></li></ul></details>
</div></header>
${crumb.length ? `<div class="subbar"><div class="wrap"><nav class="crumbs" aria-label="Fil d'Ariane"><ol>${crumb.map(([h, t]) => `<li>${h ? `<a href="${h}">${t}</a>` : `<span aria-current="page">${t}</span>`}</li>`).join('')}</ol></nav></div></div>` : ''}
<main id="content">${body}</main>
<footer class="site-foot on-dark"><div class="wrap foot-in"><div class="f-brand">${f.brand}</div><div>${f.contact}</div><div>${f.support}</div><div>${f.links}</div></div><div class="wrap foot-legal">${f.legal}</div></footer>`,
  });
}

const labelChips = `<span class="label l-prio">${icon('bolt', 'ic', 13)}Priority</span><span class="label">Production</span><span class="label l-block">Bloquant</span>`;

function entry(e, i) {
  if (e.type === 'event') return `<li class="thread-event"><span class="eic">${icon('flag', 'ic', 14)}</span><span class="etxt">${e.text}</span><time>${e.at}</time></li>`;
  const team_ = e.type === 'response';
  return `<li class="thread-entry ${e.type}"><span class="av">${initialsAvatar(e.initials, team_ ? 'av-team' : '')}</span><div class="box"><header class="header"><strong>${e.who}</strong><span class="who ${team_ ? 'w-team' : ''}">${team_ ? 'Équipe Coderise' : 'Vous'}</span>${i === 0 ? '<span class="who">auteur du ticket</span>' : ''}<time>${e.at}</time></header>
<div class="thread-body">${e.body.map((p) => (p.startsWith('<pre') ? p : `<p>${p}</p>`)).join('')}${fileChips(e.files)}</div></div></li>`;
}

const tree = (open = 0, current = -1) => `<nav class="tree" aria-label="Documentation"><h2>Documentation</h2><ul>${TREE.map(([c, arts], i) => `<li><details${i === open ? ' open' : ''}><summary>${icon(catIcon[c] || 'folder', 'ic', 18)}<span>${c}</span></summary><ul>${arts.map((a, j) => `<li><a href="article.html"${i === open && j === current ? ' aria-current="page"' : ''}>${a}</a></li>`).join('')}</ul></details></li>`).join('')}</ul></nav>`;

export default {
  dir: '05-referentiel',
  name: 'Référentiel',
  pages: {
    home: () => shell({
      title: 'Accueil', active: 'home', logged: false, bodyClass: 'home',
      body: `<section class="hero"><div class="wrap"><h1>Trouvez la réponse. Sinon, écrivez-nous.</h1><p class="lead">La documentation de vos livraisons, et une personne de l'équipe quand elle ne suffit pas. Faisons monter votre projet d'un niveau.</p>
<form class="bigsearch" role="search" method="get" action="kb/faq.php"><label class="sr-only" for="kq">Rechercher dans la documentation</label>${icon('search', 'ic', 22)}<input id="kq" type="search" placeholder="Clé d'API, déploiement, notification push…"><button class="btn btn-primary" type="submit">Rechercher</button></form>
<p class="try">Essayez : <a href="article.html">clé d'API</a> <a href="article.html">rollback</a> <a href="article.html">notification push</a> <a href="article.html">journal d'agent</a></p></div></section>
<div class="wrap home-grid"><section><h2>Guides par thème</h2><ul class="cat-grid">${categories.map((c) => `<li><a href="article.html">${icon(c.icon, 'ic', 24)}<span><strong>${c.name}</strong><small>${c.desc}</small></span><em>${c.count}</em></a></li>`).join('')}</ul>
<h2 class="mt">Mis à jour récemment</h2><div class="table-wrap"><table class="data"><caption class="sr-only">Articles mis à jour récemment</caption><thead><tr><th scope="col">Article</th><th scope="col">Thème</th><th scope="col">Mis à jour</th></tr></thead><tbody>${featured.slice(0, 5).map((a, i) => `<tr><td><a href="article.html">${a[0]}</a></td><td>${a[1]}</td><td>${['12/10', '09/10', '30/09', '25/09', '18/09'][i]}/2026</td></tr>`).join('')}</tbody></table></div></section>
<aside class="contact"><h2>Il vous faut une personne ?</h2><ul>
<li><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Ouvrir un ticket</span></a></li>
<li><a class="btn btn-line" href="connexion.html">${icon('ticket', 'ic', 18)}<span>Suivre un ticket</span></a></li></ul>
<dl class="delays"><div><dt>Support standard</dt><dd>Jours ouvrés</dd></div><div><dt>Support Priority</dt><dd>Premier retour sous 4 h, 24 h/24</dd></div></dl>
<p class="small">Incident bloquant avec Priority : ouvrez le ticket en priorité Urgente, puis appelez le <a href="tel:${CONTACT.phoneHref}">${CONTACT.phone}</a>.</p></aside></div>`,
    }),

    open: () => {
      const errs = [['subject', 'Sujet : indiquez un sujet en une phrase.'], ['environnement', 'Environnement : choisissez une valeur.'], ['message', 'Description : 20 caractères au minimum.']];
      return shell({
        title: 'Nouveau ticket', active: 'open', crumb: [['index.html', 'Support'], [null, 'Nouveau ticket']], bodyClass: 'form-page',
        body: `<div class="wrap two"><div><h1>Nouveau ticket</h1><p class="sub">Les champs marqués d'une étoile sont obligatoires.</p>
<form id="ticketForm" class="form" method="post" action="open.php" enctype="multipart/form-data" novalidate>
${errorSummary(errs)}
<div class="two-f">${field({ id: 'topicId', label: 'Sujet d\'aide', required: true, options: topics, value: topics[0] })}${field({ id: 'priority', label: 'Priorité', options: ['Normale', 'Haute', 'Urgente'], value: 'Urgente' })}</div>
${field({ id: 'product', label: 'Produit ou licence', required: true, options: products, value: products[0] })}
<div id="dynamic-form" class="dyn"><h2>${icon('terminal', 'ic', 18)} Contexte de l'incident</h2><div class="two-f">${field({ id: 'environnement', label: 'Environnement', required: true, dynamic: true, error: 'Choisissez l\'environnement concerné.', options: [['', 'Choisir…'], 'Production', 'Recette', 'Développement'] })}${field({ id: 'impact', label: 'Impact', required: true, dynamic: true, options: ['Bloquant', 'Dégradé', 'Mineur'], value: 'Bloquant' })}</div>
<div class="two-f">${field({ id: 'since', label: 'Début de l\'incident', value: '20/10/2026 21:30', dynamic: true })}${field({ id: 'version', label: 'Version concernée', value: 'API v2.8.0', dynamic: true })}</div>
${field({ id: 'rgpd', type: 'checkbox', label: 'Mes pièces jointes ne contiennent pas de données personnelles', checked: true })}</div>
${field({ id: 'subject', label: 'Sujet', required: true, error: 'Indiquez un sujet en une phrase.' })}
<div class="field has-error"><label for="message">Description <span class="req" aria-hidden="true">*</span></label><div class="control"><div class="ed-tabs" role="tablist"><a role="tab" aria-selected="true" href="#">Écrire</a><a role="tab" aria-selected="false" href="#">Aperçu</a></div>${redactor({ id: 'message', label: 'Description', value: 'Depuis hier…', rows: 8, error: 'x' })}<p class="error" id="message-err">${icon('alert', 'ic', 16)}<span>Décrivez ce que vous observez (20 caractères au minimum).</span></p></div></div>
<div class="field"><span class="lbl">Pièces jointes</span><div class="control">${filedrop()}</div></div>
<div class="actions"><button class="btn btn-primary" type="submit">Envoyer le ticket</button><a class="btn btn-line" href="mes-tickets.html">Annuler</a></div></form></div>
<aside class="help"><section><h2>Un bon ticket contient</h2><ul class="checks"><li>${icon('check', 'ic', 18)}<span>l'environnement : production, recette, développement</span></li><li>${icon('check', 'ic', 18)}<span>l'impact et le nombre d'utilisateurs touchés</span></li><li>${icon('check', 'ic', 18)}<span>la requête ou l'écran qui échoue, avec un extrait de journal</span></li><li>${icon('check', 'ic', 18)}<span>aucune donnée personnelle</span></li></ul></section>
<section data-needs-dev="suggest"><h2>Articles suggérés</h2><ul class="links-list">${featured.slice(1, 4).map((a) => `<li><a href="article.html">${a[0]}</a></li>`).join('')}</ul></section>
<section><h2>Délais</h2><dl class="delays"><div><dt>Standard</dt><dd>Jours ouvrés</dd></div><div><dt>Priority</dt><dd>Sous 4 h, 24 h/24</dd></div></dl></section></aside></div>`,
      });
    },

    login: () => shell({
      title: 'Connexion', active: 'login', logged: false, bodyClass: 'auth',
      body: `<div class="auth-wrap"><h1>Se connecter au support</h1>
<section class="box-card"><div class="form-errors" role="alert"><h2>${icon('alert', 'ic', 20)} Connexion impossible</h2><p>Adresse e-mail ou mot de passe incorrect. Vérifiez-les ou demandez un lien de connexion.</p></div>
<form id="loginForm" method="post" action="login.php" novalidate>${field({ id: 'luser', name: 'luser', label: 'Adresse e-mail', type: 'email', required: true, value: client.email })}${field({ id: 'lpasswd', name: 'lpasswd', label: 'Mot de passe', type: 'password', required: true, error: 'Le mot de passe est incorrect.' })}
<button class="btn btn-primary block" type="submit">Se connecter</button></form><p class="links"><a href="#">Mot de passe oublié ?</a> · <a href="#">Recevoir un lien de connexion</a></p></section>
<section class="box-card alt"><h2>Suivre un ticket sans compte</h2><form method="post" action="login.php" novalidate>${field({ id: 'lemail', label: 'Adresse e-mail', type: 'email', required: true })}${field({ id: 'lticket', label: 'Numéro de ticket', required: true, placeholder: '260418' })}<button class="btn btn-line block" type="submit">Voir le ticket</button></form></section>
<p class="new">Première visite ? <a href="inscription.html">Créer un compte</a></p></div>`,
    }),

    register: () => shell({
      title: 'Créer un compte', active: 'login', logged: false, bodyClass: 'auth',
      body: `<div class="auth-wrap wide"><h1>Créer un compte</h1><section class="box-card"><form method="post" action="account.php?do=create" novalidate>
<div class="two-f">${field({ id: 'rname', label: 'Nom', required: true, value: 'Léa Marchand' })}${field({ id: 'rorg', label: 'Société', value: 'Maison Verdier' })}</div>
<div class="two-f">${field({ id: 'remail', label: 'Adresse e-mail professionnelle', type: 'email', required: true, value: client.email, hint: 'Le lien de confirmation y sera envoyé.' })}${field({ id: 'rphone', label: 'Téléphone', type: 'tel', hint: 'Facultatif, utile pour un incident bloquant.' })}</div>
<div class="two-f">${field({ id: 'rpass', label: 'Mot de passe', type: 'password', required: true, hint: '12 caractères minimum.' })}${field({ id: 'rpass2', label: 'Confirmation', type: 'password', required: true })}</div>
${field({ id: 'rcgu', type: 'checkbox', label: 'J\'accepte que Coderise traite mes données pour répondre à mes demandes (<a href="#">politique de confidentialité</a>)', checked: true })}
<div class="actions"><button class="btn btn-primary" type="submit">Créer le compte</button><a class="btn btn-line" href="connexion.html">J'ai déjà un compte</a></div></form></section></div>`,
    }),

    registered: () => shell({
      title: 'Compte créé', active: 'login', logged: false, bodyClass: 'auth',
      body: `<div class="auth-wrap wide"><section class="box-card done"><span class="done-ic">${icon('mail', 'ic', 30)}</span><h1>Vérifiez votre boîte mail</h1><p>Un lien de confirmation a été envoyé à <strong>${client.email}</strong>. Il est valable 24 h.</p>
<ol class="next"><li>Ouvrez « Confirmez votre compte Coderise ».</li><li>Cliquez sur le bouton de confirmation.</li><li>Ouvrez votre premier ticket.</li></ol><p class="hint">Rien reçu ? Regardez dans les courriers indésirables ou <a href="#">renvoyez l'e-mail</a>.</p><a class="btn btn-primary" href="connexion.html">Aller à la connexion</a></section></div>`,
    }),

    ticket: () => shell({
      title: `Ticket #${ticket.number}`, active: 'ticket', crumb: [['mes-tickets.html', 'Mes tickets'], [null, `#${ticket.number}`]], bodyClass: 'ticket-page',
      body: `<div class="wrap"><header class="issue-h"><h1>${ticket.subject} <span class="num">#${ticket.number}</span></h1>
<p class="issue-sub">${statusChip('Ouvert')} <span><strong>Léa Marchand</strong> a ouvert ce ticket le ${ticket.created} · 5 messages</span><span class="actions-r"><a class="btn btn-line sm" href="#">${icon('print', 'ic', 16)}<span>Imprimer</span></a><a class="btn btn-line sm" href="#">${icon('edit', 'ic', 16)}<span>Modifier</span></a></span></p></header>
<div class="issue-grid"><div class="main-col"><ol class="thread">${thread.map(entry).join('')}</ol>
<form id="reply" class="box reply" method="post" action="tickets.php?id=${ticket.number}"><header class="header"><strong>Répondre</strong></header><div class="pad"><div class="ed-tabs" role="tablist"><a role="tab" aria-selected="true" href="#">Écrire</a><a role="tab" aria-selected="false" href="#">Aperçu</a></div>${redactor({ id: 'response', label: 'Votre réponse', rows: 5 })}<div class="field"><span class="lbl">Pièces jointes</span>${filedrop().replace(/<ul class="files"[\s\S]*?<\/ul>/, '')}</div>
<div class="actions"><button class="btn btn-primary" type="submit">Envoyer la réponse</button><button class="btn btn-line" type="reset">Effacer</button></div></div></form></div>
<aside class="meta-col"><section><h2>Statut</h2><p>${statusChip('Ouvert')}</p></section>
<section><h2>Étiquettes</h2><p class="labels">${labelChips}</p></section>
<section data-needs-dev="sla"><h2>Premier retour (Priority)</h2><div class="sla" role="group" aria-label="Délai de premier retour"><div class="sla-bar" role="img" aria-label="Premier retour apporté en 44 minutes sur 4 heures"><span class="sla-fill" style="width:18.3%"></span></div><p class="sla-text"><strong>44 min</strong> sur 4 h : objectif tenu.<br><small>Reçu 08:47 · premier retour 09:31</small></p></div></section>
<section><h2>Détails</h2><dl class="props"><div><dt>Priorité</dt><dd>${ticket.priority}</dd></div><div><dt>Département</dt><dd>${ticket.dept}</dd></div><div><dt>Sujet d'aide</dt><dd>${ticket.topic}</dd></div>${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}<div><dt>Dernière activité</dt><dd>${ticket.last}</dd></div></dl></section>
<section><h2>Pièces jointes</h2><ul class="links-list files-l">${thread.flatMap((e) => e.files || []).map(([n, s]) => `<li><a href="#">${icon('clip', 'ic', 15)}<span>${n}</span></a><small>${s}</small></li>`).join('')}</ul></section>
<section><h2>Participants</h2><p class="people">${initialsAvatar('LM')}${initialsAvatar('HL', 'av-team')}${initialsAvatar('NB', 'av-team')}</p></section>
<section><h2>Un point rapide ?</h2><p><a href="tel:${CONTACT.phoneHref}">${CONTACT.phone}</a>, en citant #${ticket.number}.</p></section></aside></div></div>`,
    }),

    tickets: () => shell({
      title: 'Mes tickets', active: 'tickets', crumb: [['index.html', 'Support'], [null, 'Mes tickets']], bodyClass: 'list-page',
      body: `<div class="wrap"><div class="title-row"><h1>Mes tickets</h1><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Nouveau ticket</span></a></div>
<section class="box"><header class="list-h"><div class="tabs" role="tablist"><a role="tab" aria-selected="true" href="#">${icon('inbox', 'ic', 18)}3 ouverts</a><a role="tab" aria-selected="false" href="#">${icon('lock', 'ic', 18)}3 fermés</a></div>
<form class="filters" method="get"><div class="sel"><label for="fprod">Produit</label><select id="fprod"><option>Tous</option><option>Verdier Commandes</option></select></div><div class="sel"><label for="fplan">Contrat</label><select id="fplan"><option>Tous</option><option>Priority</option><option>Standard</option></select></div></form></header>
<ul class="tlist" id="ticketTable">${tickets.map((t) => `<li class="${t.status === 'Fermé' ? 'closed' : ''}"><span class="st">${t.status === 'Fermé' ? icon('lock', 'ic', 20) : icon('inbox', 'ic', 20)}<span class="sr-only">${t.status}</span></span><div class="tmain"><a class="subject" href="suivi-ticket.html">${t.subject}</a> <span class="labels">${t.plan === 'Priority' ? '<span class="label l-prio">' + icon('bolt', 'ic', 13) + 'Priority</span>' : '<span class="label">Standard</span>'}<span class="label">${t.topic}</span></span><p class="meta"><span class="mono">#${t.n}</span> · ${t.product} · ouvert le ${t.date} · ${t.last}</p></div><span class="cnt">${icon('message', 'ic', 16)}${t.status === 'Fermé' ? 4 : 5}</span></li>`).join('')}</ul>
<footer class="list-f">1 à 6 sur 6 tickets</footer></section></div>`,
    }),

    ticketsEmpty: () => shell({
      title: 'Mes tickets', active: 'tickets', crumb: [['index.html', 'Support'], [null, 'Mes tickets']], bodyClass: 'list-page',
      body: `<div class="wrap"><div class="title-row"><h1>Mes tickets</h1><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Nouveau ticket</span></a></div>
<section class="box"><header class="list-h"><div class="tabs" role="tablist"><a role="tab" aria-selected="true" href="#">${icon('inbox', 'ic', 18)}0 ouvert</a><a role="tab" aria-selected="false" href="#">${icon('lock', 'ic', 18)}0 fermé</a></div></header>
<div class="empty"><span class="e-mark" aria-hidden="true">${sommet()}</span><h2>Aucun ticket ouvert. Tout roule.</h2><p>Quand vous aurez un incident, une question ou une évolution à nous confier, le ticket apparaîtra ici.</p><div class="actions"><a class="btn btn-primary" href="ouvrir-un-ticket.html">Ouvrir un ticket</a><a class="btn btn-line" href="base-de-connaissances.html">Parcourir la documentation</a></div></div></section></div>`,
    }),

    kb: () => shell({
      title: 'Documentation', active: 'kb', crumb: [['index.html', 'Support'], [null, 'Documentation']], bodyClass: 'kb-page',
      body: `<div class="wrap docs-grid"><aside class="side-tree">${tree(0)}</aside>
<div class="docs-main"><h1>Documentation</h1><p class="sub">54 articles en 7 thèmes. Tapez <kbd class="k">/</kbd> pour rechercher.</p>
${TREE.slice(0, 4).map(([c, arts], i) => `<section class="doc-sec"><h2>${icon(catIcon[c] || 'folder', 'ic', 22)}${c}<small>${categories[i].count} articles</small></h2><p class="d">${categories[i].desc}</p><ul class="links-list">${arts.map((a, j) => `<li><a href="article.html">${a}</a><small class="mono">KB-${String(140 + i * 10 + j).padStart(4, '0')}</small></li>`).join('')}</ul><p><a class="more" href="article.html">Voir les ${categories[i].count} articles</a></p></section>`).join('')}</div></div>`,
    }),

    article: () => shell({
      title: article.title, active: 'article', crumb: [['index.html', 'Support'], ['base-de-connaissances.html', 'Documentation'], [null, article.cat]], bodyClass: 'article-page',
      body: `<div class="wrap docs-grid art"><aside class="side-tree">${tree(0, 1)}</aside>
<article class="prose"><h1>${article.title}</h1><p class="byline">${initialsAvatar(article.author.initials, 'av-team')}<span>${article.author.name}, ${article.author.role} · mis à jour le ${article.updated} · ${article.read} de lecture · <span class="mono">KB-0141</span></span></p>${articleProse()}
<div class="helpful" role="group" aria-label="Cet article vous a-t-il aidé ?"><span>Cet article vous a-t-il aidé ?</span><button class="btn btn-line sm" type="button">Oui</button><button class="btn btn-line sm" type="button">Non</button></div>
<nav class="pager" aria-label="Articles voisins"><a href="article.html"><small>Précédent</small><span>Support standard ou Priority : lequel choisir</span></a><a class="next" href="article.html"><small>Suivant</small><span>Que mettre dans un ticket pour être aidé plus vite</span></a></nav></article>
<aside class="toc-col"><h2>Sur cette page</h2><ol class="toc">${article.toc.map(([id, t], i) => `<li><a href="#${id}"${i === 0 ? ' aria-current="true"' : ''}>${t}</a></li>`).join('')}</ol><div class="stuck"><h2>Toujours bloqué ?</h2><p>Une personne vous répond.</p><a class="btn btn-primary sm" href="ouvrir-un-ticket.html">Ouvrir un ticket</a></div></aside></div>`,
    }),
  },
};
