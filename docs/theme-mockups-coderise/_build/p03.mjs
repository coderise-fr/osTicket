// 03 Sommet : éditorial, dominé par le bleu nuit, grande typographie, chevron cyan en cadrage.
import {
  doc, icon, field, errorSummary, redactor, filedrop, fileChips, footerInfo, logoH, signature,
  statusChip, planChip, sommet, client, thread, ticket, tickets, categories, featured, topics, products, CONTACT,
} from './common.mjs';
import { article, articleProse, related } from './article.mjs';

const NAV = (active, logged) => [
  ['home', 'index.html', 'Accueil'],
  ['kb', 'base-de-connaissances.html', 'Base de connaissances'],
  [logged ? 'tickets' : 'login', logged ? 'mes-tickets.html' : 'connexion.html', logged ? 'Mes tickets' : 'Suivre un ticket'],
].map(([k, h, l]) => ({ k, h, l, cur: k === active || (active === 'ticket' && k === 'tickets') || (active === 'article' && k === 'kb') }));

function shell({ title, active, logged = false, body, bodyClass = '' }) {
  const items = NAV(active, logged);
  const li = items.map((n) => `<li><a href="${n.h}"${n.cur ? ' aria-current="page"' : ''}>${n.l}</a></li>`).join('');
  const f = footerInfo();
  return doc({
    title, bodyClass: `p03 ${bodyClass}`,
    body: `<header class="site-head on-dark"><div class="wrap head-in">
<a class="brand" href="index.html" aria-label="Coderise : accueil du support">${logoH()}</a>
<nav class="nav-desktop" aria-label="Navigation principale"><ul id="nav">${li}</ul></nav>
<a class="btn btn-primary head-cta" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Ouvrir un ticket</span></a>
<details class="menu-mobile"><summary>${icon('menu', 'ic', 20)}<span>Menu</span></summary><ul>${li}<li><a href="ouvrir-un-ticket.html">Ouvrir un ticket</a></li></ul></details>
</div></header>
<main id="content">${body}</main>
<footer class="site-foot on-dark"><div class="wrap foot-in"><div class="f-brand">${f.brand}</div><div>${f.contact}</div><div>${f.support}</div><div>${f.links}</div></div>
<div class="wrap foot-legal">${f.legal}</div></footer>`,
  });
}

const bandeau = ({ crumbs = [], h1, sub = '', extra = '', cls = '' }) => `<section class="bandeau on-dark ${cls}"><div class="wrap">
${crumbs.length ? `<nav class="crumbs" aria-label="Fil d'Ariane"><ol>${crumbs.map(([h, t]) => `<li>${h ? `<a href="${h}">${t}</a>` : `<span aria-current="page">${t}</span>`}</li>`).join('')}</ol></nav>` : ''}
<h1>${h1}</h1>${sub ? `<p class="sub">${sub}</p>` : ''}${extra}</div><span class="b-sommet" aria-hidden="true">${sommet()}</span></section>`;

const entry = (e) => {
  if (e.type === 'event') return `<li class="thread-event">${icon('flag', 'ic', 16)}<span>${e.text}</span><time>${e.at}</time></li>`;
  const team = e.type === 'response';
  return `<li class="thread-entry ${e.type}"><div class="meta"><strong>${e.who}</strong><span>${team ? 'Équipe Coderise' : 'Vous'}</span><time>${e.at}</time></div>
<div class="thread-body">${e.body.map((p) => (p.startsWith('<pre') ? p : `<p>${p}</p>`)).join('')}${fileChips(e.files)}</div></li>`;
};

export default {
  dir: '03-sommet',
  name: 'Sommet',
  pages: {
    home: () => shell({
      title: 'Accueil', active: 'home', bodyClass: 'home',
      body: `<section class="hero on-dark"><div class="wrap hero-grid"><div class="hero-copy">
<h1>Faisons monter votre projet d'un niveau.</h1>
<p class="lead">Un incident, une question, une évolution : écrivez-nous. Une personne de l'équipe vous répond, avec une vraie réponse. Livré en production, pas pour la démo.</p>
<div class="actions"><a class="btn btn-primary btn-lg" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 20)}<span>Ouvrir un ticket</span></a><a class="btn btn-ghost btn-lg" href="connexion.html">Suivre un ticket</a></div></div>
<div class="hero-peak" aria-hidden="true">${sommet()}</div></div></section>
<section class="intents wrap"><h2>Que voulez-vous nous confier ?</h2>
<ul class="intent-list">
<li>${icon('alert', 'ic', 32)}<h3>Un incident</h3><p>Quelque chose ne fonctionne plus en production. Vous nous donnez l'impact, nous reproduisons, nous corrigeons.</p><a href="ouvrir-un-ticket.html">Signaler un incident</a></li>
<li>${icon('help', 'ic', 32)}<h3>Une question</h3><p>Un doute sur une livraison, une configuration, un comportement. Une réponse claire, pas un renvoi vers la documentation.</p><a href="ouvrir-un-ticket.html">Poser une question</a></li>
<li>${icon('rocket', 'ic', 32)}<h3>Une évolution</h3><p>Une fonction à ajouter, un agent à ajuster, un chantier à planifier. Nous chiffrons et nous vous disons quand.</p><a href="ouvrir-un-ticket.html">Demander une évolution</a></li></ul></section>
<section class="statement"><div class="wrap"><p class="big">Support Priority : un premier retour <span class="mark">sous 4 heures</span>, jour et nuit.</p><p class="small">Support standard : jours ouvrés, sans délai garanti. Les horaires de l'entreprise (${CONTACT.hours}) ne sont pas ceux du support Priority.</p></div></section>
<section class="guides wrap"><h2>À lire avant d'écrire</h2><ul class="guide-list">${featured.slice(0, 5).map((a) => `<li><a href="article.html"><span class="t">${a[0]}</span><span class="c">${a[1]}</span><span class="r">${a[2]} de lecture</span></a></li>`).join('')}</ul><p><a class="more" href="base-de-connaissances.html">Toute la base de connaissances</a></p></section>`,
    }),

    open: () => {
      const errs = [['subject', 'Indiquez un sujet en une phrase.'], ['environnement', 'Choisissez l\'environnement concerné.'], ['message', 'Décrivez ce que vous observez (20 caractères au minimum).']];
      return shell({
        title: 'Ouvrir un ticket', active: 'open', logged: true, bodyClass: 'form-page',
        body: `${bandeau({ crumbs: [['index.html', 'Accueil'], [null, 'Ouvrir un ticket']], h1: 'Dites-nous ce qui se passe.', sub: 'Trois informations suffisent pour commencer : le sujet, l\'impact, ce que vous observez.' })}
<div class="wrap split"><aside class="guide"><h2>Avant d'écrire</h2><ul class="checks"><li>${icon('check', 'ic', 18)}<span>Dites si c'est en production, en recette ou en développement.</span></li><li>${icon('check', 'ic', 18)}<span>Joignez une capture et un extrait de journal.</span></li><li>${icon('check', 'ic', 18)}<span>Retirez les données personnelles.</span></li></ul>
<div class="delay"><h2>Délais</h2><p><strong>Standard :</strong> jours ouvrés.<br><strong>Priority :</strong> premier retour sous 4 h, 24 h/24.</p></div>
<div class="delay"><h2>Une urgence ?</h2><p>Ouvrez le ticket en priorité Urgente, puis appelez le <a href="tel:${CONTACT.phoneHref}">${CONTACT.phone}</a>.</p></div></aside>
<form id="ticketForm" class="form" method="post" action="open.php" enctype="multipart/form-data" novalidate>
${errorSummary(errs)}
${field({ id: 'email', label: 'Votre adresse e-mail', type: 'email', value: client.email, readonly: true })}
<div class="two">${field({ id: 'topicId', label: 'Sujet d\'aide', required: true, options: topics, value: topics[0] })}${field({ id: 'priority', label: 'Priorité', options: ['Normale', 'Haute', 'Urgente'], value: 'Urgente' })}</div>
${field({ id: 'product', label: 'Produit ou licence concerné', required: true, options: products, value: products[0] })}
<div id="dynamic-form" class="dyn"><h2>Contexte de l'incident</h2><div class="two">${field({ id: 'environnement', label: 'Environnement', required: true, dynamic: true, error: 'Choisissez l\'environnement concerné.', options: [['', 'Choisir…'], 'Production', 'Recette', 'Développement'] })}${field({ id: 'impact', label: 'Impact', required: true, dynamic: true, options: ['Bloquant', 'Dégradé', 'Mineur'], value: 'Bloquant' })}</div>
<div class="two">${field({ id: 'since', label: 'Début de l\'incident', value: '20/10/2026 21:30', dynamic: true })}${field({ id: 'version', label: 'Version concernée', value: 'API v2.8.0', dynamic: true })}</div>
${field({ id: 'rgpd', type: 'checkbox', label: 'Mes pièces jointes ne contiennent pas de données personnelles', checked: true })}</div>
${field({ id: 'subject', label: 'Sujet', required: true, error: 'Indiquez un sujet en une phrase.', placeholder: 'Ex. : la liste des commandes est vide' })}
<div class="field has-error"><label for="message">Description <span class="req" aria-hidden="true">*</span></label><div class="control">${redactor({ id: 'message', label: 'Description', value: 'Depuis hier…', rows: 7, error: 'x' })}<p class="error" id="message-err">${icon('alert', 'ic', 16)}<span>Décrivez ce que vous observez (20 caractères au minimum).</span></p></div></div>
<div class="field"><span class="lbl">Pièces jointes</span><div class="control">${filedrop()}</div></div>
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">${icon('arrow', 'ic', 20)}<span>Envoyer le ticket</span></button><a class="btn btn-line btn-lg" href="mes-tickets.html">Annuler</a></div></form></div>`,
      });
    },

    login: () => shell({
      title: 'Connexion', active: 'login', bodyClass: 'auth-page',
      body: `<div class="auth-split"><section class="auth-left on-dark"><div class="auth-left-in"><h1>Retrouvez vos échanges.</h1><p class="lead">Suivez vos tickets, répondez à l'équipe, ajoutez des pièces jointes.</p></div><span class="a-sommet" aria-hidden="true">${sommet()}</span></section>
<section class="auth-right"><div class="auth-box"><h2>Connexion</h2>
<div class="form-errors" role="alert"><h2>${icon('alert', 'ic', 20)} Connexion impossible</h2><p>Adresse e-mail ou mot de passe incorrect. Vérifiez-les ou demandez un lien de connexion.</p></div>
<form id="loginForm" method="post" action="login.php" novalidate>${field({ id: 'luser', name: 'luser', label: 'Adresse e-mail', type: 'email', required: true, value: client.email })}${field({ id: 'lpasswd', name: 'lpasswd', label: 'Mot de passe', type: 'password', required: true, error: 'Le mot de passe est incorrect.' })}
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">Me connecter</button></div><p class="links"><a href="#">Mot de passe oublié ?</a> <a href="#">Recevoir un lien de connexion</a></p></form>
<div class="alt"><h2>Sans compte</h2><p>Retrouvez un ticket avec votre e-mail et son numéro.</p><form method="post" action="login.php" novalidate><div class="two">${field({ id: 'lemail', label: 'E-mail', type: 'email', required: true })}${field({ id: 'lticket', label: 'N° de ticket', required: true, placeholder: '260418' })}</div><button class="btn btn-line" type="submit">Voir le ticket</button></form></div>
<p class="after">Pas encore de compte ? <a href="inscription.html">Créez-le en une minute</a>.</p></div></section></div>`,
    }),

    register: () => shell({
      title: 'Créer un compte', active: 'login', bodyClass: 'auth-page',
      body: `<div class="auth-split"><section class="auth-left on-dark"><div class="auth-left-in"><h1>Un compte pour tout votre projet.</h1><p class="lead">Vos tickets, ceux de votre équipe, et l'historique complet des échanges.</p></div><span class="a-sommet" aria-hidden="true">${sommet()}</span></section>
<section class="auth-right"><div class="auth-box"><h2>Créer un compte</h2><form method="post" action="account.php?do=create" novalidate>
<div class="two">${field({ id: 'rname', label: 'Votre nom', required: true, value: 'Léa Marchand' })}${field({ id: 'rorg', label: 'Société', value: 'Maison Verdier' })}</div>
${field({ id: 'remail', label: 'Adresse e-mail professionnelle', type: 'email', required: true, value: client.email, hint: 'Nous y envoyons le lien de confirmation.' })}
${field({ id: 'rphone', label: 'Téléphone', type: 'tel', hint: 'Facultatif, utile pour un incident bloquant.' })}
<div class="two">${field({ id: 'rpass', label: 'Mot de passe', type: 'password', required: true, hint: '12 caractères minimum.' })}${field({ id: 'rpass2', label: 'Confirmation', type: 'password', required: true })}</div>
${field({ id: 'rcgu', type: 'checkbox', label: 'J\'accepte que Coderise traite mes données pour répondre à mes demandes (<a href="#">politique de confidentialité</a>)', checked: true })}
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">Créer mon compte</button><a class="btn btn-line btn-lg" href="connexion.html">J'ai déjà un compte</a></div></form></div></section></div>`,
    }),

    registered: () => shell({
      title: 'Compte créé', active: 'login', bodyClass: 'done-page',
      body: `${bandeau({ h1: 'Vérifiez votre boîte mail.', sub: `Un lien de confirmation vient d'être envoyé à <strong>${client.email}</strong>. Il est valable 24 h.` })}
<div class="wrap done"><ol class="big-steps"><li><b>Ouvrez</b> l'e-mail « Confirmez votre compte Coderise ».</li><li><b>Cliquez</b> sur le bouton de confirmation.</li><li><b>Ouvrez</b> votre premier ticket.</li></ol>
<p class="hint">Rien reçu après quelques minutes ? Regardez dans vos courriers indésirables, ou <a href="#">renvoyez l'e-mail</a>.</p><div class="actions"><a class="btn btn-primary btn-lg" href="connexion.html">Aller à la connexion</a></div></div>`,
    }),

    ticket: () => shell({
      title: `Ticket #${ticket.number}`, active: 'ticket', logged: true, bodyClass: 'ticket-page',
      body: `${bandeau({
        crumbs: [['mes-tickets.html', 'Mes tickets'], [null, `Ticket #${ticket.number}`]], h1: ticket.subject, cls: 'b-ticket',
        sub: `${statusChip('Ouvert')} ${planChip('Priority')} <span class="urg">Urgente</span> <span class="since">Ouvert le ${ticket.created}</span>`,
        extra: `<div class="altimeter" data-needs-dev="sla" role="group" aria-label="Délai de premier retour, support Priority"><div class="alt-head"><strong>Premier retour sous 4 h</strong><span>Apporté en 44 min : objectif tenu</span></div>
<div class="alt-bar" role="img" aria-label="Premier retour apporté en 44 minutes sur 4 heures"><span class="fill" style="width:18.3%"></span><span class="flag" style="left:18.3%"></span></div>
<ol class="alt-scale"><li style="left:0">Reçu<small>08:47</small></li><li style="left:18.3%">Premier retour<small>09:31</small></li><li class="end">Objectif<small>12:47</small></li></ol></div>`,
      })}
<div class="wrap ticket-grid"><section aria-label="Journal des échanges"><ol class="thread">${thread.map(entry).join('')}</ol>
<form id="reply" class="reply form" method="post" action="tickets.php?id=${ticket.number}"><h2>Votre réponse</h2>${redactor({ id: 'response', label: 'Votre réponse', rows: 5 })}<div class="field"><span class="lbl">Pièces jointes</span>${filedrop().replace(/<ul class="files"[\s\S]*?<\/ul>/, '')}</div>
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">Envoyer ma réponse</button><button class="btn btn-line btn-lg" type="reset">Effacer</button></div></form></section>
<aside class="side"><h2>Le ticket</h2><dl class="props"><div><dt>Numéro</dt><dd>#${ticket.number}</dd></div><div><dt>Statut</dt><dd>${statusChip('Ouvert')}</dd></div><div><dt>Priorité</dt><dd>${ticket.priority}</dd></div><div><dt>Département</dt><dd>${ticket.dept}</dd></div><div><dt>Sujet d'aide</dt><dd>${ticket.topic}</dd></div>${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}<div><dt>Dernière activité</dt><dd>${ticket.last}</dd></div></dl>
<div class="call"><h2>Un point rapide ?</h2><p>Appelez-nous en citant #${ticket.number}.</p><a class="btn btn-line" href="tel:${CONTACT.phoneHref}">${icon('phone', 'ic', 18)}<span>${CONTACT.phone}</span></a></div></aside></div>`,
    }),

    tickets: () => shell({
      title: 'Mes tickets', active: 'tickets', logged: true, bodyClass: 'list-page',
      body: `${bandeau({ crumbs: [['index.html', 'Accueil'], [null, 'Mes tickets']], h1: 'Vos tickets.', sub: 'Trois sont ouverts. Un attend votre réponse.' })}
<div class="wrap"><div class="toolbar"><div class="tabs" role="tablist"><a role="tab" aria-selected="true" href="#">Ouverts <span>3</span></a><a role="tab" aria-selected="false" href="#">Fermés <span>3</span></a></div><form class="search" role="search" method="get"><label class="sr-only" for="q">Rechercher dans mes tickets</label>${icon('search', 'ic', 20)}<input id="q" type="search" placeholder="Rechercher un ticket"></form></div>
<ul class="tlist" id="ticketTable">${tickets.map((t) => `<li class="${t.status === 'Fermé' ? 'closed' : ''}"><a class="trow" href="suivi-ticket.html"><span class="num">#${t.n}</span><span class="tmain"><strong class="subject">${t.subject}</strong><span class="meta">${t.product} · ouvert le ${t.date} · ${t.last}</span></span><span class="tstate">${statusChip(t.status)}${planChip(t.plan)}</span><span class="go">${icon('arrow', 'ic', 22)}</span></a></li>`).join('')}</ul></div>`,
    }),

    ticketsEmpty: () => shell({
      title: 'Mes tickets', active: 'tickets', logged: true, bodyClass: 'list-page',
      body: `${bandeau({ crumbs: [['index.html', 'Accueil'], [null, 'Mes tickets']], h1: 'Vos tickets.', sub: '' })}
<div class="wrap empty"><span class="e-mark" aria-hidden="true">${sommet()}</span><div><h2>Aucun ticket ouvert. Tout roule.</h2><p>Quand vous nous confierez un incident, une question ou une évolution, il apparaîtra ici avec chaque réponse de l'équipe.</p><div class="actions"><a class="btn btn-primary btn-lg" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 20)}<span>Ouvrir un ticket</span></a><a class="btn btn-line btn-lg" href="base-de-connaissances.html">Chercher dans les guides</a></div></div></div>`,
    }),

    kb: () => shell({
      title: 'Base de connaissances', active: 'kb', bodyClass: 'kb-page',
      body: `${bandeau({ crumbs: [['index.html', 'Accueil'], [null, 'Base de connaissances']], h1: 'Trouvez la réponse, ou la bonne personne.', cls: 'b-kb', extra: `<form class="bigsearch" role="search" method="get" action="kb/faq.php"><label class="sr-only" for="kq">Rechercher dans la base de connaissances</label>${icon('search', 'ic', 24)}<input id="kq" type="search" placeholder="Clé d'API, déploiement, notification push…"><button class="btn btn-primary btn-lg" type="submit">Rechercher</button></form>` })}
<div class="wrap kb-wrap"><h2>Par thème</h2><ul class="cat-list">${categories.map((c) => `<li><a href="article.html">${icon(c.icon, 'ic', 30)}<span class="n">${c.name}</span><span class="d">${c.desc}</span><span class="k">${c.count}</span></a></li>`).join('')}</ul>
<h2>Les plus lus</h2><ul class="guide-list">${featured.map((a) => `<li><a href="article.html"><span class="t">${a[0]}</span><span class="c">${a[1]}</span><span class="r">${a[2]}</span></a></li>`).join('')}</ul></div>`,
    }),

    article: () => shell({
      title: article.title, active: 'article', bodyClass: 'article-page',
      body: `${bandeau({ crumbs: [['index.html', 'Accueil'], ['base-de-connaissances.html', 'Base de connaissances'], [null, article.cat]], h1: article.title, sub: `${article.author.name}, ${article.author.role} · mis à jour le ${article.updated} · ${article.read} de lecture` })}
<div class="wrap art-grid"><aside class="toc-col"><h2>Dans cet article</h2><ol class="toc">${article.toc.map(([id, t]) => `<li><a href="#${id}">${t}</a></li>`).join('')}</ol></aside>
<article class="prose">${articleProse()}<div class="helpful" role="group" aria-label="Cet article vous a-t-il aidé ?"><p>Cet article vous a-t-il aidé ?</p><button class="btn btn-line" type="button">Oui</button><button class="btn btn-line" type="button">Pas vraiment</button></div></article>
<aside class="more-col"><div class="help"><h2>Toujours bloqué ?</h2><p>Une personne de l'équipe vous répond.</p><a class="btn btn-primary" href="ouvrir-un-ticket.html">Ouvrir un ticket</a></div><h2>À lire aussi</h2><ul class="rel">${related.map((r) => `<li><a href="article.html">${r}</a></li>`).join('')}</ul></aside></div>`,
    }),
  },
};
