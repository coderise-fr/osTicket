// 04 Parcours : guidé, orienté progression. Chaque demande est un parcours : étapes, prochain pas, altimètre du délai.
import {
  doc, icon, field, errorSummary, redactor, filedrop, fileChips, footerInfo, logoH,
  statusChip, planChip, initialsAvatar, sommet, client, thread, ticket, tickets, categories, featured, topics, products, CONTACT,
} from './common.mjs';
import { article, articleProse } from './article.mjs';

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
    title, bodyClass: `p04 ${bodyClass}`,
    body: `<header class="site-head on-dark"><div class="wrap head-in">
<a class="brand" href="index.html" aria-label="Coderise : accueil du support">${logoH()}</a>
<nav class="nav-desktop" aria-label="Navigation principale"><ul id="nav">${li}</ul></nav>
<a class="btn btn-primary head-cta" href="ouvrir-un-ticket.html">${icon('plus', 'ic', 18)}<span>Ouvrir un ticket</span></a>
${logged ? `<a class="user-chip" href="mes-tickets.html">${initialsAvatar(client.initials)}<span>${client.first}</span></a>` : ''}
<details class="menu-mobile"><summary>${icon('menu', 'ic', 20)}<span>Menu</span></summary><ul>${li}<li><a href="ouvrir-un-ticket.html">Ouvrir un ticket</a></li></ul></details>
</div></header>
<main id="content">${body}</main>
<footer class="site-foot on-dark"><div class="wrap foot-in"><div class="f-brand">${f.brand}</div><div>${f.contact}</div><div>${f.support}</div><div>${f.links}</div></div><div class="wrap foot-legal">${f.legal}</div></footer>`,
  });
}

const route = (steps, { label = 'Avancement', cls = '' } = {}) => `<ol class="route ${cls}" aria-label="${label}">${steps.map(([state, t, sub], i) => `<li class="${state}"${state === 'current' ? ' aria-current="step"' : ''}><span class="node" aria-hidden="true">${state === 'done' ? icon('check', 'ic', 18) : i + 1}</span><span class="txt"><strong>${t}</strong>${sub ? `<small>${sub}</small>` : ''}</span></li>`).join('')}</ol>`;
const crumbs = (items) => `<nav class="crumbs" aria-label="Fil d'Ariane"><ol>${items.map(([h, t]) => `<li>${h ? `<a href="${h}">${t}</a>` : `<span aria-current="page">${t}</span>`}</li>`).join('')}</ol></nav>`;
const topHead = (c, h1, sub, extra = '') => `<section class="top"><div class="wrap">${c || ''}<h1>${h1}</h1>${sub ? `<p class="sub">${sub}</p>` : ''}${extra}</div></section>`;
const progress = (pct, label) => `<div class="progress" role="img" aria-label="${label}"><span style="width:${pct}%"></span></div>`;
const mini = (n) => `<span class="mini-route" role="img" aria-label="Étape ${n} sur 4">${[1, 2, 3, 4].map((i) => `<i class="${i <= n ? 'on' : ''}"></i>`).join('')}</span>`;

function tl(e) {
  if (e.type === 'event') return `<li class="thread-event">${icon('flag', 'ic', 15)}<span>${e.text}</span><time>${e.at}</time></li>`;
  const team = e.type === 'response';
  return `<li class="thread-entry ${e.type}"><span class="dot">${initialsAvatar(e.initials, team ? 'av-team' : '')}</span><div class="card"><header class="header"><strong>${e.who}</strong><span class="role">${team ? 'Équipe Coderise' : 'Vous'}</span><time>${e.at}</time></header>
<div class="thread-body">${e.body.map((p) => (p.startsWith('<pre') ? p : `<p>${p}</p>`)).join('')}</div>${fileChips(e.files)}</div></li>`;
}

export default {
  dir: '04-parcours',
  name: 'Parcours',
  pages: {
    home: () => shell({
      title: 'Accueil', active: 'home', bodyClass: 'home',
      body: `<section class="hero"><div class="wrap"><h1>Que souhaitez-vous faire&nbsp;?</h1><p class="lead">Choisissez, nous vous guidons : quelques questions, un message, et votre demande part à la bonne personne. Faisons monter votre projet d'un niveau.</p>
<ul class="choices">
<li><a class="choice" href="ouvrir-un-ticket.html"><span class="ci">${icon('alert', 'ic', 28)}</span><h2>Signaler un incident</h2><p>Quelque chose ne fonctionne plus en production.</p><span class="tag">${icon('bolt', 'ic', 14)}Priority : réponse sous 4 h</span><span class="go">Commencer</span></a></li>
<li><a class="choice" href="ouvrir-un-ticket.html"><span class="ci">${icon('help', 'ic', 28)}</span><h2>Poser une question</h2><p>Une livraison, une configuration, un comportement à comprendre.</p><span class="tag plain">Support standard : jours ouvrés</span><span class="go">Commencer</span></a></li>
<li><a class="choice" href="ouvrir-un-ticket.html"><span class="ci">${icon('rocket', 'ic', 28)}</span><h2>Demander une évolution</h2><p>Une fonction à ajouter, un agent à ajuster, un chantier à lancer.</p><span class="tag plain">Chiffrage sous quelques jours</span><span class="go">Commencer</span></a></li></ul></div></section>
<section class="how"><div class="wrap"><h2>Votre demande, étape par étape</h2>${route([['done', 'Vous décrivez', 'Un formulaire adapté'], ['done', 'Nous accusons réception', 'Par e-mail, avec un numéro'], ['current', 'Nous répondons', 'Premier retour sous 4 h avec Priority'], ['todo', 'Vous validez', 'Le ticket est résolu']], { cls: 'big', label: 'Étapes d\'une demande' })}</div></section>
<section class="wrap two-cols"><div class="track"><h2>Vous avez déjà un ticket ?</h2><p>Retrouvez-le avec votre e-mail et son numéro, sans compte.</p><form class="inline" method="post" action="login.php" novalidate>${field({ id: 'hemail', label: 'Adresse e-mail', type: 'email', required: true })}${field({ id: 'hticket', label: 'Numéro de ticket', required: true, placeholder: '260418' })}<button class="btn btn-line" type="submit">Suivre mon ticket</button></form></div>
<div class="reads"><h2>Avant d'écrire, deux minutes</h2><ul class="kb-list">${featured.slice(0, 4).map((a) => `<li><a href="article.html">${icon('file', 'ic', 20)}<span>${a[0]}</span><small>${a[2]}</small></a></li>`).join('')}</ul></div></section>`,
    }),

    open: () => {
      const errs = [['environnement', 'Étape 2 : choisissez l\'environnement concerné.'], ['subject', 'Étape 3 : indiquez un sujet en une phrase.'], ['message', 'Étape 3 : décrivez ce que vous observez (20 caractères au minimum).']];
      return shell({
        title: 'Ouvrir un ticket', active: 'open', logged: true, bodyClass: 'form-page',
        body: `${topHead(crumbs([['index.html', 'Accueil'], [null, 'Ouvrir un ticket']]), 'Signaler un incident', 'Quatre étapes, environ deux minutes. Les champs marqués d\'une étoile sont obligatoires.', `<div class="overall"><p><strong>Étape 2 sur 4</strong> : le contexte</p>${progress(50, 'Deux étapes sur quatre complétées')}</div>`)}
<div class="wrap wiz"><nav class="steps-nav" aria-label="Étapes du formulaire">${route([['done', 'La demande', 'Sujet et produit'], ['current', 'Le contexte', '1 champ à corriger'], ['todo', 'Votre message', 'Description et fichiers'], ['todo', 'L\'envoi', 'Priorité et confirmation']], { cls: 'vert', label: 'Étapes' })}</nav>
<form id="ticketForm" class="form" method="post" action="open.php" enctype="multipart/form-data" novalidate>
${errorSummary(errs)}
<fieldset class="step done"><legend><span class="n">${icon('check', 'ic', 18)}</span>La demande</legend>
${field({ id: 'topicId', label: 'Sujet d\'aide', required: true, options: topics, value: topics[0], hint: 'Le formulaire s\'adapte à votre choix.' })}
${field({ id: 'product', label: 'Produit ou licence concerné', required: true, options: products, value: products[0] })}
${field({ id: 'email', label: 'Votre adresse e-mail', type: 'email', value: client.email, readonly: true })}</fieldset>
<fieldset class="step current" id="dynamic-form"><legend><span class="n">2</span>Le contexte de l'incident</legend>
<div class="two">${field({ id: 'environnement', label: 'Environnement', required: true, dynamic: true, error: 'Choisissez l\'environnement concerné.', options: [['', 'Choisir…'], 'Production', 'Recette', 'Développement'] })}${field({ id: 'impact', label: 'Impact', required: true, dynamic: true, options: ['Bloquant', 'Dégradé', 'Mineur'], value: 'Bloquant' })}</div>
<div class="two">${field({ id: 'since', label: 'Début de l\'incident', value: '20/10/2026 21:30', dynamic: true, hint: 'Date et heure, même approximatives.' })}${field({ id: 'version', label: 'Version concernée', value: 'API v2.8.0', dynamic: true })}</div>
${field({ id: 'rgpd', type: 'checkbox', label: 'Mes pièces jointes ne contiennent pas de données personnelles', checked: true })}</fieldset>
<fieldset class="step"><legend><span class="n">3</span>Votre message</legend>
${field({ id: 'subject', label: 'Sujet', required: true, error: 'Indiquez un sujet en une phrase.', placeholder: 'Ex. : la liste des commandes est vide' })}
<div class="field has-error"><label for="message">Description <span class="req" aria-hidden="true">*</span></label><div class="control">${redactor({ id: 'message', label: 'Description', value: 'Depuis hier…', rows: 7, error: 'x' })}<p class="error" id="message-err">${icon('alert', 'ic', 16)}<span>Décrivez ce que vous observez (20 caractères au minimum).</span></p></div></div>
<div class="field"><span class="lbl">Pièces jointes</span><div class="control">${filedrop()}</div></div></fieldset>
<fieldset class="step"><legend><span class="n">4</span>L'envoi</legend>
${field({ id: 'priority', label: 'Priorité', options: ['Normale', 'Haute', 'Urgente'], value: 'Urgente', hint: 'Urgente : incidents bloquants. Avec le support Priority, premier retour sous 4 h, 24 h/24.' })}
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">${icon('arrow', 'ic', 20)}<span>Envoyer ma demande</span></button><a class="btn btn-line btn-lg" href="mes-tickets.html">Annuler</a></div></fieldset></form></div>`,
      });
    },

    login: () => shell({
      title: 'Connexion', active: 'login', bodyClass: 'auth',
      body: `${topHead(crumbs([['index.html', 'Accueil'], [null, 'Connexion']]), 'Retrouvez vos demandes', 'Connectez-vous pour reprendre là où vous en étiez.')}
<div class="wrap auth-grid"><section class="card"><h2>J'ai un compte</h2>
<div class="form-errors" role="alert"><h2>${icon('alert', 'ic', 20)} Connexion impossible</h2><p>Adresse e-mail ou mot de passe incorrect. Vérifiez-les ou demandez un lien de connexion.</p></div>
<form id="loginForm" method="post" action="login.php" novalidate>${field({ id: 'luser', name: 'luser', label: 'Adresse e-mail', type: 'email', required: true, value: client.email })}${field({ id: 'lpasswd', name: 'lpasswd', label: 'Mot de passe', type: 'password', required: true, error: 'Le mot de passe est incorrect.' })}
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">Me connecter</button></div><p class="links"><a href="#">Mot de passe oublié ?</a> <a href="#">Recevoir un lien de connexion</a></p></form></section>
<div class="side-col"><section class="card soft"><h2>Sans compte</h2><p>Retrouvez un ticket avec votre e-mail et son numéro.</p><form method="post" action="login.php" novalidate>${field({ id: 'lemail', label: 'Adresse e-mail', type: 'email', required: true })}${field({ id: 'lticket', label: 'Numéro de ticket', required: true, placeholder: '260418' })}<button class="btn btn-line" type="submit">Voir le ticket</button></form></section>
<section class="card"><h2>Première visite ?</h2><p>Un compte réunit tous vos tickets, et ceux de votre équipe.</p><a class="btn btn-line" href="inscription.html">Créer un compte</a></section></div></div>`,
    }),

    register: () => shell({
      title: 'Créer un compte', active: 'login', bodyClass: 'auth',
      body: `${topHead(crumbs([['index.html', 'Accueil'], [null, 'Créer un compte']]), 'Créez votre compte', 'Deux étapes : vos informations, puis la confirmation par e-mail.', route([['current', 'Vos informations', ''], ['todo', 'Confirmation par e-mail', '']], { cls: 'inline-route', label: 'Étapes de l\'inscription' }))}
<div class="wrap narrow"><form class="card" method="post" action="account.php?do=create" novalidate>
<div class="two">${field({ id: 'rname', label: 'Votre nom', required: true, value: 'Léa Marchand' })}${field({ id: 'rorg', label: 'Société', value: 'Maison Verdier' })}</div>
${field({ id: 'remail', label: 'Adresse e-mail professionnelle', type: 'email', required: true, value: client.email, hint: 'Nous y envoyons le lien de confirmation.' })}
${field({ id: 'rphone', label: 'Téléphone', type: 'tel', hint: 'Facultatif, utile pour un incident bloquant.' })}
<div class="two">${field({ id: 'rpass', label: 'Mot de passe', type: 'password', required: true, hint: '12 caractères minimum.' })}${field({ id: 'rpass2', label: 'Confirmation', type: 'password', required: true })}</div>
${field({ id: 'rcgu', type: 'checkbox', label: 'J\'accepte que Coderise traite mes données pour répondre à mes demandes (<a href="#">politique de confidentialité</a>)', checked: true })}
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">Continuer</button><a class="btn btn-line btn-lg" href="connexion.html">J'ai déjà un compte</a></div></form></div>`,
    }),

    registered: () => shell({
      title: 'Compte créé', active: 'login', bodyClass: 'auth',
      body: `${topHead('', 'Il ne reste qu\'une étape.', `Un lien de confirmation vient d'être envoyé à <strong>${client.email}</strong>. Il est valable 24 h.`, route([['done', 'Vos informations', ''], ['current', 'Confirmation par e-mail', '']], { cls: 'inline-route', label: 'Étapes de l\'inscription' }))}
<div class="wrap narrow"><div class="card"><h2>Pour terminer</h2><ol class="next"><li>Ouvrez l'e-mail « Confirmez votre compte Coderise ».</li><li>Cliquez sur le bouton de confirmation.</li><li>Ouvrez votre premier ticket.</li></ol>
<p class="hint">Rien reçu après quelques minutes ? Regardez dans vos courriers indésirables, ou <a href="#">renvoyez l'e-mail</a>.</p><div class="actions"><a class="btn btn-primary btn-lg" href="connexion.html">Aller à la connexion</a></div></div></div>`,
    }),

    ticket: () => shell({
      title: `Ticket #${ticket.number}`, active: 'ticket', logged: true, bodyClass: 'ticket-page',
      body: `${topHead(crumbs([['mes-tickets.html', 'Mes tickets'], [null, `Ticket #${ticket.number}`]]), ticket.subject, `${statusChip('Ouvert')} ${planChip('Priority')} <span class="meta">#${ticket.number} · ouvert le ${ticket.created}</span>`, `<div data-needs-dev="progress">${route([['done', 'Reçu', '21/10 à 08:47'], ['done', 'Pris en charge', 'Hugo, à 09:31'], ['current', 'Correctif en test', 'En recette'], ['todo', 'Résolu', 'À valider avec vous']], { cls: 'big', label: 'Avancement du ticket' })}</div>`)}
<div class="wrap ticket-grid"><div class="main-col">
<aside class="next-step" data-needs-dev="next-step"><span class="ns-ic">${icon('flag', 'ic', 24)}</span><div><h2>Votre prochain pas</h2><p>Testez le correctif sur la boutique d'Aix avec le compte <code>aix-recette</code>, puis dites-nous si les commandes s'affichent. Passage en production prévu à 14 h.</p></div><a class="btn btn-primary" href="#reply">Répondre à Nora</a></aside>
<ol class="thread">${thread.map(tl).join('')}</ol>
<form id="reply" class="card form" method="post" action="tickets.php?id=${ticket.number}"><h2>Votre réponse</h2>${redactor({ id: 'response', label: 'Votre réponse', rows: 5 })}<div class="field"><span class="lbl">Pièces jointes</span>${filedrop().replace(/<ul class="files"[\s\S]*?<\/ul>/, '')}</div>
<div class="actions"><button class="btn btn-primary btn-lg" type="submit">Envoyer ma réponse</button><button class="btn btn-line btn-lg" type="reset">Effacer</button></div></form></div>
<aside class="side"><section class="card alti" data-needs-dev="sla" aria-label="Délai de premier retour, support Priority"><h2>${icon('clock', 'ic', 20)}Altimètre du délai</h2><p class="a-sub">Priority : premier retour sous 4 h, 24 h/24.</p>
<div class="gauge" role="img" aria-label="Premier retour apporté en 44 minutes sur 4 heures"><span class="gf" style="width:18.3%"></span><span class="gm" style="left:18.3%"><b>44 min</b></span><ol class="ticks"><li style="left:0">0 h</li><li style="left:25%">1 h</li><li style="left:50%">2 h</li><li style="left:75%">3 h</li><li style="left:100%">4 h</li></ol></div>
<p class="a-text"><strong>Objectif tenu.</strong> Premier retour à 09:31, 44 minutes après la réception.</p></section>
<section class="card"><h2>Le ticket</h2><dl class="props"><div><dt>Statut</dt><dd>${statusChip('Ouvert')}</dd></div><div><dt>Priorité</dt><dd>${ticket.priority}</dd></div><div><dt>Département</dt><dd>${ticket.dept}</dd></div><div><dt>Sujet d'aide</dt><dd>${ticket.topic}</dd></div>${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}<div><dt>Dernière activité</dt><dd>${ticket.last}</dd></div></dl></section>
<section class="card soft"><h2>Un point rapide ?</h2><p>Appelez-nous en citant #${ticket.number}.</p><a class="btn btn-line" href="tel:${CONTACT.phoneHref}">${icon('phone', 'ic', 18)}<span>${CONTACT.phone}</span></a></section></aside></div>`,
    }),

    tickets: () => {
      const g = (title, n, list, cls = '') => `<section class="group ${cls}"><h2>${title} <span class="count">${n}</span></h2><ul class="tlist">${list.map(([t, step]) => `<li><a class="trow" href="suivi-ticket.html"><span class="tmain"><strong class="subject">${t.subject}</strong><span class="meta">#${t.n} · ${t.product} · ${t.last}</span></span><span class="tprog" data-needs-dev="progress">${mini(step)}<small>Étape ${step} sur 4</small></span><span class="tstate">${statusChip(t.status)}${planChip(t.plan)}</span></a></li>`).join('')}</ul></section>`;
      return shell({
        title: 'Mes tickets', active: 'tickets', logged: true, bodyClass: 'list-page',
        body: `${topHead(crumbs([['index.html', 'Accueil'], [null, 'Mes tickets']]), 'Vos demandes', 'Un ticket attend votre réponse. Deux avancent chez Coderise.')}
<div class="wrap">${g('À vous de jouer', 1, [[tickets[1], 2]], 'turn')}${g('Chez Coderise', 2, [[tickets[0], 3], [tickets[2], 2]])}${g('Terminées', 3, [[tickets[3], 4], [tickets[4], 4], [tickets[5], 4]], 'done')}</div>`,
      });
    },

    ticketsEmpty: () => shell({
      title: 'Mes tickets', active: 'tickets', logged: true, bodyClass: 'list-page',
      body: `${topHead(crumbs([['index.html', 'Accueil'], [null, 'Mes tickets']]), 'Vos demandes', '')}
<div class="wrap"><div class="empty card"><span class="e-mark" aria-hidden="true">${sommet()}</span><h2>Aucun ticket ouvert. Tout roule.</h2><p>Quand vous aurez une demande, elle apparaîtra ici avec ses étapes. Par où commencer ?</p>
<ul class="mini-choices"><li><a href="ouvrir-un-ticket.html">${icon('alert', 'ic', 20)}Signaler un incident</a></li><li><a href="ouvrir-un-ticket.html">${icon('help', 'ic', 20)}Poser une question</a></li><li><a href="ouvrir-un-ticket.html">${icon('rocket', 'ic', 20)}Demander une évolution</a></li></ul></div></div>`,
    }),

    kb: () => {
      const col = (h, d, cats) => `<section class="path"><h2><span class="pm" aria-hidden="true">${sommet()}</span>${h}</h2><p>${d}</p><ul>${cats.map((c) => `<li><a href="article.html">${icon(c.icon, 'ic', 22)}<span><strong>${c.name}</strong><small>${c.count} articles</small></span></a></li>`).join('')}</ul></section>`;
      return shell({
        title: 'Base de connaissances', active: 'kb', bodyClass: 'kb-page',
        body: `${topHead(crumbs([['index.html', 'Accueil'], [null, 'Base de connaissances']]), 'Trouvez la réponse, étape par étape', 'Cherchez, ou suivez un parcours du début à la fin.', `<form class="bigsearch" role="search" method="get" action="kb/faq.php"><label class="sr-only" for="kq">Rechercher dans la base de connaissances</label>${icon('search', 'ic', 22)}<input id="kq" type="search" placeholder="Clé d'API, déploiement, notification push…"><button class="btn btn-primary btn-lg" type="submit">Rechercher</button></form>`)}
<div class="wrap"><div class="paths">${col('Démarrer', 'Choisir son niveau de support et savoir bien écrire.', [categories[0], categories[5]])}${col('Livrer', 'Applications, mises en production et versions.', [categories[1], categories[2], categories[4]])}${col('Opérer', 'Surveiller, sécuriser, tenir dans la durée.', [categories[3], categories[6]])}</div>
<h2 class="sec">Les plus lus</h2><ul class="kb-list wide">${featured.map((a) => `<li><a href="article.html">${icon('file', 'ic', 20)}<span>${a[0]}<small class="cat-name">${a[1]}</small></span><small>${a[2]}</small></a></li>`).join('')}</ul></div>`,
      });
    },

    article: () => shell({
      title: article.title, active: 'article', bodyClass: 'article-page',
      body: `${topHead(crumbs([['index.html', 'Accueil'], ['base-de-connaissances.html', 'Base de connaissances'], [null, article.cat]]), article.title, `${article.author.name}, ${article.author.role} · mis à jour le ${article.updated} · ${article.read} de lecture`, `<div class="overall"><p><strong>Parcours « Premiers pas avec le support »</strong> : article 2 sur 4</p>${progress(50, 'Article 2 sur 4')}</div>`)}
<div class="wrap art-grid"><article class="prose">${articleProse()}
<nav class="pager" aria-label="Dans le parcours"><a class="prev" href="article.html"><small>Précédent</small><span>Support standard ou Priority : lequel choisir</span></a><a class="next" href="article.html"><small>Suivant</small><span>Que mettre dans un ticket pour être aidé plus vite</span></a></nav>
<div class="helpful" role="group" aria-label="Cet article vous a-t-il aidé ?"><p>Cet article vous a-t-il aidé ?</p><button class="btn btn-line" type="button">Oui</button><button class="btn btn-line" type="button">Pas vraiment</button></div></article>
<aside class="side"><section class="card"><h2>Dans cet article</h2><ol class="toc">${article.toc.map(([id, t]) => `<li><a href="#${id}">${t}</a></li>`).join('')}</ol></section>
<section class="card soft"><h2>Toujours bloqué ?</h2><p>Une personne de l'équipe vous répond.</p><a class="btn btn-primary" href="ouvrir-un-ticket.html">Ouvrir un ticket</a></section></aside></div>`,
    }),
  },
};
