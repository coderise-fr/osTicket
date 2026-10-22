// 01 Atelier : chaleureux, centre sur les personnes (avatars, bulles de conversation, boutons-pastilles).
import {
  doc, icon, esc, field, errorSummary, redactor, filedrop, fileChips, footerInfo, logoH, logoStackedDark, symbolDark,
  slaBlock, statusChip, planChip, initialsAvatar, sommet, signature, client, team, thread, ticket, tickets, categories, featured, topics, products, CONTACT,
} from './common.mjs';
import { article, articleProse, related } from './article.mjs';

const NAV = (active, logged) => [
  ['home', 'index.html', 'Accueil'],
  ['kb', 'base-de-connaissances.html', 'Base de connaissances'],
  ['open', 'ouvrir-un-ticket.html', 'Ouvrir un ticket'],
  [logged ? 'tickets' : 'login', logged ? 'mes-tickets.html' : 'connexion.html', logged ? 'Mes tickets' : 'Suivre un ticket'],
].map(([k, h, l]) => ({ k, h, l, cur: k === active || (active === 'ticket' && k === 'tickets') || (active === 'article' && k === 'kb') }));

function shell({ title, active, logged = false, body, bodyClass = '' }) {
  const items = NAV(active, logged);
  const li = items.map((n) => `<li><a href="${n.h}"${n.cur ? ' aria-current="page"' : ''}>${n.l}</a></li>`).join('');
  const f = footerInfo();
  return doc({
    title, bodyClass: `p01 ${bodyClass}`,
    body: `<header class="site-head on-dark"><div class="wrap head-in">
<a class="brand" href="index.html" aria-label="Coderise : accueil du support">${logoH()}</a>
<nav class="nav-desktop" aria-label="Navigation principale"><ul id="nav">${li}</ul></nav>
<div class="head-user">${logged
      ? `<a class="user-chip" href="mes-tickets.html">${initialsAvatar(client.initials)}<span>${client.first}</span></a>`
      : `<a class="btn btn-line-dark" href="connexion.html">${icon('login', 'ic', 18)}Se connecter</a>`}</div>
<details class="menu-mobile"><summary>${icon('menu', 'ic', 20)}<span>Menu</span></summary><ul>${li}</ul></details>
</div></header>
<main id="content">${body}</main>
<footer class="site-foot on-dark"><div class="wrap foot-in">
<div class="f-brand">${f.brand}</div><div>${f.contact}</div><div>${f.support}</div><div>${f.links}</div>
</div><div class="wrap foot-legal">${f.legal}</div></footer>`,
  });
}

const crumbs = (items) => `<nav class="crumbs" aria-label="Fil d'Ariane"><ol>${items.map(([h, t], i) => `<li>${h ? `<a href="${h}">${t}</a>` : `<span aria-current="page">${t}</span>`}</li>`).join('')}</ol></nav>`;
const head = (c, h1, sub) => `<div class="page-head"><div class="wrap">${c || ''}<h1>${h1}</h1>${sub ? `<p class="sub">${sub}</p>` : ''}</div></div>`;

function bubbles(list) {
  return list.map((e) => {
    if (e.type === 'event') return `<p class="thread-event">${icon('flag', 'ic', 15)}<span>${e.text}</span><time>${e.at}</time></p>`;
    const team_ = e.type === 'response';
    return `<article class="thread-entry ${e.type} ${team_ ? 'from-team' : 'from-you'}" aria-label="${esc(e.who)}, ${e.at}">
${initialsAvatar(e.initials, team_ ? 'av-team' : 'av-you')}
<div class="bubble"><header class="header"><strong>${e.who}</strong><span class="role">${e.role}</span><time>${e.at}</time></header>
<div class="thread-body">${e.body.map((p) => (p.startsWith('<pre') ? p : `<p>${p}</p>`)).join('')}</div>${fileChips(e.files)}</div></article>`;
  }).join('');
}

const stepper = `<ol class="ascent-steps" aria-label="Avancement du ticket" data-needs-dev="progress">
<li class="done"><span class="mark">${sommet()}</span><span class="t"><strong>Reçu</strong><small>21/10 à 08:47</small></span></li>
<li class="done"><span class="mark">${sommet()}</span><span class="t"><strong>Pris en charge</strong><small>Hugo, 21/10 à 09:31</small></span></li>
<li class="now" aria-current="step"><span class="mark">${sommet()}</span><span class="t"><strong>Correctif en cours</strong><small>Test en recette demandé</small></span></li>
<li><span class="mark">${sommet()}</span><span class="t"><strong>Résolu</strong><small>À venir</small></span></li></ol>`;

export default {
  dir: '01-atelier',
  name: 'Atelier',
  pages: {
    home: () => shell({
      title: 'Accueil', active: 'home', bodyClass: 'home',
      body: `<section class="hero"><div class="wrap hero-in">
<div class="hero-copy"><h1>Faisons monter votre projet d'un niveau.</h1>
<p class="lead">Un incident, une question, une évolution : écrivez-nous. Une personne de l'équipe vous répond, avec une vraie réponse.</p>
<div class="actions"><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 20)}Ouvrir un ticket</a><a class="btn btn-line" href="connexion.html">Suivre un ticket</a></div>
<p class="smallprint">Support standard : jours ouvrés. Support Priority : premier retour sous 4 h, 24 h/24.</p></div>
<div class="hero-chat" aria-label="Exemple d'échange">
<p class="chat-cap">${initialsAvatar('HL', 'av-team')}${initialsAvatar('NB', 'av-team')}${initialsAvatar('SR', 'av-team')}<span>Hugo, Nora, Sacha et l'équipe vous lisent</span></p>
<div class="mini you"><p>L'app n'affiche plus les commandes depuis hier soir.</p><small>Léa, 08:47</small></div>
<div class="mini team"><p>Reproduit en 10 minutes. La cause est identifiée, le correctif arrive.</p><small>Hugo, 09:31</small></div>
</div></div></section>
<section class="band"><div class="wrap"><h2>Comment ça se passe</h2>
<ol class="ascent">
<li><span class="mk">${sommet()}</span><h3>Vous décrivez</h3><p>Choisissez le sujet, ajoutez une capture : le formulaire s'adapte à votre demande.</p></li>
<li><span class="mk">${sommet()}</span><h3>Nous répondons</h3><p>Une personne de l'équipe vous lit, reproduit le problème et vous dit où nous en sommes.</p></li>
<li><span class="mk">${sommet()}</span><h3>Vous suivez</h3><p>Chaque réponse arrive par e-mail et sur ce portail. Vous répondez où vous voulez.</p></li>
</ol></div></section>
<section class="wrap split"><div><h2>Les articles les plus lus</h2>
<ul class="kb-list">${featured.slice(0, 4).map((a) => `<li><a href="article.html">${icon('file', 'ic', 20)}<span>${a[0]}</span><small>${a[2]}</small></a></li>`).join('')}</ul>
<p><a class="more" href="base-de-connaissances.html">Parcourir toute la base de connaissances</a></p></div>
<aside class="priority-card"><h2>Besoin d'un retour sous 4 h ?</h2>
<p>Avec le support Priority, votre premier retour arrive en moins de 4 h, jour et nuit, week-ends compris. Votre contrat figure dans l'e-mail de bienvenue.</p>
<a class="btn btn-dark" href="article.html">Comprendre les niveaux de support</a></aside></section>`,
    }),

    open: () => {
      const errs = [['subject', 'Indiquez un sujet en une phrase.'], ['environnement', 'Choisissez l\'environnement concerné.'], ['message', 'Décrivez ce que vous observez (20 caractères au minimum).']];
      return shell({
        title: 'Ouvrir un ticket', active: 'open', logged: true, bodyClass: 'form-page',
        body: `${head(crumbs([['index.html', 'Accueil'], [null, 'Ouvrir un ticket']]), 'Dites-nous ce qui se passe', 'Plus votre message est précis, plus la réponse sera utile. Les champs marqués d\'une étoile sont obligatoires.')}
<div class="wrap form-grid"><form id="ticketForm" class="card form" method="post" action="open.php" enctype="multipart/form-data" novalidate>
${errorSummary(errs)}
<div class="two">${field({ id: 'email', label: 'Votre adresse e-mail', type: 'email', value: client.email, readonly: true })}${field({ id: 'name', label: 'Votre nom', value: client.name, readonly: true })}</div>
${field({ id: 'topicId', label: 'Sujet d\'aide', required: true, options: topics, value: topics[0], hint: 'Le formulaire s\'adapte à votre choix.' })}
${field({ id: 'product', label: 'Produit ou licence concerné', required: true, options: products, value: products[0] })}
<div id="dynamic-form" class="dyn"><h2>Précisions sur l'incident</h2>
<div class="two">${field({ id: 'environnement', label: 'Environnement', required: true, dynamic: true, error: 'Choisissez l\'environnement concerné.', options: [['', 'Choisir…'], 'Production', 'Recette', 'Développement'] })}${field({ id: 'impact', label: 'Impact', required: true, dynamic: true, options: ['Bloquant', 'Dégradé', 'Mineur'], value: 'Bloquant' })}</div>
<div class="two">${field({ id: 'since', label: 'Début de l\'incident', type: 'text', value: '20/10/2026 21:30', dynamic: true, hint: 'Date et heure, même approximatives.' })}${field({ id: 'version', label: 'Version concernée', value: 'API v2.8.0', dynamic: true })}</div>
${field({ id: 'rgpd', type: 'checkbox', label: 'Mes pièces jointes ne contiennent pas de données personnelles', checked: true })}</div>
${field({ id: 'priority', label: 'Priorité', options: ['Normale', 'Haute', 'Urgente'], value: 'Urgente', hint: 'Urgente : réservée aux incidents bloquants. Avec le support Priority, premier retour sous 4 h.' })}
${field({ id: 'subject', label: 'Sujet', required: true, error: 'Indiquez un sujet en une phrase.', placeholder: 'Ex. : la liste des commandes est vide' })}
<div class="field has-error"><label for="message">Description <span class="req" aria-hidden="true">*</span></label><div class="control">${redactor({ id: 'message', label: 'Description', value: 'Depuis hier…', rows: 7, error: 'x' })}<p class="error" id="message-err">${icon('alert', 'ic', 16)}<span>Décrivez ce que vous observez (20 caractères au minimum).</span></p></div></div>
<div class="field"><span class="lbl">Pièces jointes</span><div class="control">${filedrop()}</div></div>
<div class="actions"><button class="btn btn-primary" type="submit">${icon('arrow', 'ic', 20)}Envoyer mon ticket</button><a class="btn btn-line" href="mes-tickets.html">Annuler</a></div>
</form>
<aside class="side"><div class="card tip"><p class="who">${initialsAvatar('HL', 'av-team')}${initialsAvatar('NB', 'av-team')}<span>Une personne vous lira</span></p><p>Pas un robot : un ingénieur de l'équipe, qui connaît votre projet.</p></div>
<div class="card tip"><h2>Peut-être déjà répondu</h2><ul class="kb-list">${featured.slice(1, 4).map((a) => `<li><a href="article.html">${icon('file', 'ic', 18)}<span>${a[0]}</span></a></li>`).join('')}</ul></div></aside></div>`,
      });
    },

    login: () => shell({
      title: 'Connexion', active: 'login', bodyClass: 'auth',
      body: `${head(crumbs([['index.html', 'Accueil'], [null, 'Connexion']]), 'Retrouvez vos échanges', 'Connectez-vous pour suivre vos tickets, ou utilisez votre numéro de ticket sans compte.')}
<div class="wrap auth-grid"><section class="card"><h2>J'ai un compte</h2>
<div class="form-errors" role="alert"><h2>${icon('alert', 'ic', 20)} Connexion impossible</h2><p>Adresse e-mail ou mot de passe incorrect. Vérifiez-les, ou demandez un lien de connexion par e-mail.</p></div>
<form id="loginForm" method="post" action="login.php" novalidate>
${field({ id: 'luser', name: 'luser', label: 'Adresse e-mail', type: 'email', required: true, value: 'lea.marchand@maison-verdier.fr' })}
${field({ id: 'lpasswd', name: 'lpasswd', label: 'Mot de passe', type: 'password', required: true, error: 'Le mot de passe est incorrect.' })}
<div class="actions"><button class="btn btn-primary" type="submit">Me connecter</button></div>
<p class="links"><a href="#">Mot de passe oublié ?</a> <a href="#">Recevoir un lien de connexion</a></p></form>
<p class="after">Pas encore de compte ? <a href="inscription.html">Créez-le en une minute</a>.</p></section>
<section class="card soft"><h2>Je n'ai pas de compte</h2><p>Retrouvez un ticket avec votre e-mail et son numéro, indiqué dans l'e-mail de confirmation.</p>
<form method="post" action="login.php" novalidate>${field({ id: 'lemail', label: 'Adresse e-mail', type: 'email', required: true })}${field({ id: 'lticket', label: 'Numéro de ticket', required: true, placeholder: 'Ex. : 260418' })}
<div class="actions"><button class="btn btn-line" type="submit">Voir mon ticket</button></div></form></section></div>`,
    }),

    register: () => shell({
      title: 'Créer un compte', active: 'login', bodyClass: 'auth',
      body: `${head(crumbs([['index.html', 'Accueil'], [null, 'Créer un compte']]), 'Créez votre espace en une minute', 'Un compte rassemble tous vos tickets et ceux de votre équipe.')}
<div class="wrap auth-grid single"><form class="card" method="post" action="account.php?do=create" novalidate>
<div class="two">${field({ id: 'rname', label: 'Votre nom', required: true, value: 'Léa Marchand' })}${field({ id: 'rorg', label: 'Société', value: 'Maison Verdier' })}</div>
<div class="two">${field({ id: 'remail', label: 'Adresse e-mail professionnelle', type: 'email', required: true, value: 'lea.marchand@maison-verdier.fr', hint: 'Nous y envoyons le lien de confirmation.' })}${field({ id: 'rphone', label: 'Téléphone', type: 'tel', hint: 'Facultatif, utile pour un incident bloquant.' })}</div>
<div class="two">${field({ id: 'rpass', label: 'Mot de passe', type: 'password', required: true, hint: '12 caractères au minimum.' })}${field({ id: 'rpass2', label: 'Confirmer le mot de passe', type: 'password', required: true })}</div>
${field({ id: 'rcgu', type: 'checkbox', label: 'J\'accepte que Coderise traite mes données pour répondre à mes demandes (<a href="#">politique de confidentialité</a>)', checked: true })}
<div class="actions"><button class="btn btn-primary" type="submit">Créer mon compte</button><a class="btn btn-line" href="connexion.html">J'ai déjà un compte</a></div></form></div>`,
    }),

    registered: () => shell({
      title: 'Compte créé', active: 'login', bodyClass: 'auth',
      body: `<div class="wrap done-wrap"><div class="card done"><span class="done-ic">${icon('mail', 'ic', 40)}</span>
<h1>Vérifiez votre boîte mail</h1><p class="lead">Nous venons d'envoyer un lien de confirmation à <strong>${client.email}</strong>. Cliquez dessus pour activer votre compte : il est valable 24 h.</p>
<ol class="next"><li>Ouvrez l'e-mail « Confirmez votre compte Coderise ».</li><li>Cliquez sur le bouton de confirmation.</li><li>Ouvrez votre premier ticket.</li></ol>
<p class="hint">Rien reçu après quelques minutes ? Regardez dans vos courriers indésirables, ou <a href="#">renvoyez l'e-mail</a>.</p>
<div class="actions"><a class="btn btn-primary" href="connexion.html">Aller à la connexion</a></div></div></div>`,
    }),

    ticket: () => shell({
      title: `Ticket #${ticket.number}`, active: 'ticket', logged: true, bodyClass: 'ticket-page',
      body: `${head(crumbs([['mes-tickets.html', 'Mes tickets'], [null, `Ticket #${ticket.number}`]]), ticket.subject, `${statusChip('Ouvert')} ${planChip('Priority')} <span class="meta">Ouvert le ${ticket.created}</span>`)}
<div class="wrap"><div class="step-wrap">${stepper}</div>
<div class="ticket-grid"><section class="convo" aria-label="Échanges">${bubbles(thread)}
<form class="card reply" id="reply" method="post" action="tickets.php?id=${ticket.number}"><h2>${initialsAvatar(client.initials, 'av-you')}Votre réponse</h2>
${redactor({ id: 'response', label: 'Votre réponse', value: '', rows: 5 })}
<div class="field"><span class="lbl">Pièces jointes</span>${filedrop().replace(/<ul class="files"[\s\S]*?<\/ul>/, '')}</div>
<div class="actions"><button class="btn btn-primary" type="submit">Envoyer ma réponse</button><button class="btn btn-line" type="reset">Effacer</button></div></form></section>
<aside class="side"><div class="card">${slaBlock()}</div>
<div class="card"><h2>Informations</h2><dl class="props"><div><dt>Numéro</dt><dd>#${ticket.number}</dd></div><div><dt>Statut</dt><dd>${statusChip('Ouvert')}</dd></div><div><dt>Priorité</dt><dd>${ticket.priority}</dd></div><div><dt>Sujet d'aide</dt><dd>${ticket.topic}</dd></div>${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}<div><dt>Dernière activité</dt><dd>${ticket.last}</dd></div></dl></div>
<div class="card tip"><h2>Un point rapide ?</h2><p>Appelez-nous, en citant le numéro #${ticket.number}.</p><a class="btn btn-line" href="tel:${CONTACT.phoneHref}">${icon('phone', 'ic', 18)}${CONTACT.phone}</a></div></aside></div></div>`,
    }),

    tickets: () => shell({
      title: 'Mes tickets', active: 'tickets', logged: true, bodyClass: 'list-page',
      body: `${head(crumbs([['index.html', 'Accueil'], [null, 'Mes tickets']]), 'Vos tickets', 'Trois sont ouverts : un attend votre réponse.')}
<div class="wrap"><div class="toolbar"><div class="tabs" role="tablist"><a role="tab" aria-selected="true" href="#">Ouverts <span class="count">3</span></a><a role="tab" aria-selected="false" href="#">Fermés <span class="count">3</span></a></div>
<form class="search" role="search" method="get"><label class="sr-only" for="q">Rechercher dans mes tickets</label>${icon('search', 'ic', 18)}<input id="q" type="search" placeholder="Rechercher un ticket"></form></div>
<ul class="tlist" id="ticketTable">${tickets.map((t) => `<li class="${t.status === 'Fermé' ? 'closed' : ''}"><a class="trow" href="suivi-ticket.html">
${t.from === 'team' ? initialsAvatar(t.last.includes('Nora') ? 'NB' : 'SR', 'av-team') : t.from === 'you' ? initialsAvatar(client.initials, 'av-you') : `<span class="avatar av-none" aria-hidden="true">${icon('lock', 'ic', 18)}</span>`}
<span class="tmain"><strong class="subject">${t.subject}</strong><span class="meta">#${t.n} · ${t.product} · ouvert le ${t.date}</span></span>
<span class="tstate">${statusChip(t.status)}${planChip(t.plan)}<small class="last">${t.last}</small></span></a></li>`).join('')}</ul>
<p class="pager">6 tickets · <a href="#">Voir les suivants</a></p></div>`,
    }),

    ticketsEmpty: () => shell({
      title: 'Mes tickets', active: 'tickets', logged: true, bodyClass: 'list-page',
      body: `${head(crumbs([['index.html', 'Accueil'], [null, 'Mes tickets']]), 'Vos tickets', '')}
<div class="wrap"><div class="empty card"><span class="empty-mark">${sommet()}</span><h2>Aucun ticket ouvert. Tout roule.</h2>
<p>Quand vous aurez une question ou un incident à nous confier, il apparaîtra ici avec toutes les réponses de l'équipe.</p>
<div class="actions"><a class="btn btn-primary" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 20)}Ouvrir un ticket</a><a class="btn btn-line" href="base-de-connaissances.html">Chercher dans les guides</a></div></div></div>`,
    }),

    kb: () => shell({
      title: 'Base de connaissances', active: 'kb', bodyClass: 'kb-page',
      body: `<section class="kb-hero"><div class="wrap"><h1>Trouvez la réponse, ou la bonne personne.</h1>
<form class="bigsearch" role="search" method="get" action="kb/faq.php"><label class="sr-only" for="kq">Rechercher dans la base de connaissances</label>${icon('search', 'ic', 22)}<input id="kq" type="search" placeholder="Ex. : clé d'API, déploiement, notification push"><button class="btn btn-primary" type="submit">Rechercher</button></form></div></section>
<section class="wrap"><h2>Parcourir par thème</h2><ul class="cats">${categories.map((c) => `<li><a class="cat" href="article.html"><span class="cic">${icon(c.icon, 'ic', 26)}</span><h3>${c.name}</h3><p>${c.desc}</p><small>${c.count} articles</small></a></li>`).join('')}</ul></section>
<section class="band"><div class="wrap"><h2>Les plus lus</h2><ul class="kb-list wide">${featured.map((a) => `<li><a href="article.html">${icon('file', 'ic', 20)}<span>${a[0]}<small class="cat-name">${a[1]}</small></span><small>${a[2]}</small></a></li>`).join('')}</ul></div></section>`,
    }),

    article: () => shell({
      title: article.title, active: 'kb', bodyClass: 'article-page',
      body: `${head(crumbs([['index.html', 'Accueil'], ['base-de-connaissances.html', 'Base de connaissances'], [null, article.cat]]), article.title, '')}
<div class="wrap article-grid"><article class="prose card"><p class="by">${initialsAvatar(article.author.initials, 'av-team')}<span><strong>${article.author.name}</strong>, ${article.author.role}<br><small>Mis à jour le ${article.updated} · ${article.read} de lecture</small></span></p>${articleProse()}
<div class="helpful" role="group" aria-label="Cet article vous a-t-il aidé ?"><p>Cet article vous a-t-il aidé ?</p><button class="btn btn-line" type="button">${icon('check', 'ic', 18)}Oui</button><button class="btn btn-line" type="button">Pas vraiment</button></div></article>
<aside class="side"><div class="card"><h2>Toujours bloqué ?</h2><p>Une personne de l'équipe vous répond.</p><a class="btn btn-primary" href="ouvrir-un-ticket.html">Ouvrir un ticket</a></div>
<div class="card"><h2>À lire aussi</h2><ul class="kb-list">${related.map((r) => `<li><a href="article.html">${icon('file', 'ic', 18)}<span>${r}</span></a></li>`).join('')}</ul></div></aside></div>`,
    }),
  },
};
