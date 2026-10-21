// Proposition 03 : « Atelier » (chaleureux, centre sur l'humain). Gabarits reecrits (niveau 2), memes champs et memes etats.
import { doc, nav, client, agent, thread, ticket, tickets, categories, featured, topics, receiptMark, BACK_LINK, icon, redactor, filedrop, fileChips, esc } from './common.mjs';

const av = (ini, c = 'plum', size = '') => `<span class="avatar av-${c} ${size}" aria-hidden="true">${ini}</span>`;
const stepper = (cur) => {
  const steps = ['Demande reçue', 'Prise en charge', 'Réponse reçue', 'Résolu'];
  return `<ol class="stepper" aria-label="Avancement du ticket">${steps.map((s, i) => `<li class="${i < cur ? 'done' : i === cur ? 'now' : ''}"${i === cur ? ' aria-current="step"' : ''}><span class="dot">${i < cur ? icon('check', 'ic', 14) : ''}</span><span class="lbl">${s}</span></li>`).join('')}</ol>`;
};

function shell(page, key, logged, title, msg = '') {
  const items = nav(key, logged);
  return doc({
    title: `${title} | Ownalytics Support`,
    css: 'theme.css',
    bodyClass: `p-${key}`,
    body: `${BACK_LINK}
<a class="skip" href="#main">Aller au contenu</a>
<header class="site-header">
  <div class="wrap bar">
    <a class="brand" href="index.html" aria-label="Ownalytics Support, accueil">${receiptMark()}<span class="wm">Ownalytics</span><span class="wm-sub">Support</span></a>
    <nav aria-label="Menu principal"><ul class="menu">${items.map((n) => `<li><a href="${n.href}"${n.active ? ' aria-current="page"' : ''}>${n.label}</a></li>`).join('')}</ul></nav>
    <div class="who">${logged
      ? `<a class="me" href="#" aria-label="Profil de ${client.name}">${av('CR', 'sun')}<span class="me-name">${client.first}</span></a><a class="out" href="connexion.html">Se déconnecter</a>`
      : `<a class="out" href="connexion.html">Se connecter</a>`}
      <span class="lang" role="group" aria-label="Langue"><a href="#" aria-current="true">FR</a><a href="#" hreflang="en">EN</a></span></div>
  </div>
</header>
<main id="main" class="wrap main" tabindex="-1">
${msg}${page}
</main>
<footer class="site-footer">
  <div class="wrap foot">
    <div class="foot-brand"><a class="brand" href="index.html">${receiptMark('#FBFBF6', '#2A1340')}<span class="wm">Ownalytics</span></a>
      <p>Une petite équipe, des vraies personnes, des plugins pour Matomo auto-hébergé.</p></div>
    <ul class="legal"><li><a href="#">Mentions légales</a></li><li><a href="#">Conditions</a></li><li><a href="#">Confidentialité</a></li><li><a href="#">Politique de support</a></li></ul>
    <p class="small">Matomo est une marque déposée d'InnoCraft Ltd. Ownalytics est un éditeur indépendant, sans lien avec InnoCraft.<br>© 2026 Coderise SAS - Tous droits réservés. <a href="https://osticket.com">Logiciel de support propulsé par osTicket</a></p>
  </div>
</footer>`,
  });
}

const home = () => `
<section class="hero" aria-labelledby="h-title">
  <div class="hero-copy">
    <p class="hello">${av('CR', 'sun', 'sm')} Content de vous revoir, ${client.first}.</p>
    <h1 id="h-title" class="display">Que se passe-t-il de votre côté ?</h1>
    <form class="ask" action="ouvrir-un-ticket.html" role="search">
      <label class="visually-hidden" for="ask">Décrivez votre question en une phrase</label>
      <input id="ask" type="text" placeholder="Par exemple : mes heatmaps restent vides" autocomplete="off">
      <button class="btn btn-primary" type="submit">Écrire à l'équipe</button>
    </form>
    <ul class="reassure"><li>${icon('check', 'ic', 16)} Une personne vous répond</li><li>${icon('check', 'ic', 16)} En français ou en anglais</li><li>${icon('check', 'ic', 16)} Vos données restent chez vous</li></ul>
  </div>
  <aside class="news card" aria-labelledby="news-t">
    <h2 id="news-t" class="card-title">Du nouveau sur vos demandes</h2>
    <a class="bubble-link" href="suivi-ticket.html">
      ${av('IM', 'pink')}
      <span class="bl-body"><span class="bl-meta"><b>Inès</b> vous a répondu, hier à 09:20</span>
      <span class="bl-text">« Bonne nouvelle, et merci pour ce retour précis. Les données d'avant la mise à jour ne sont pas perdues… »</span>
      <span class="bl-sub">Ticket #482913, Heatlens : les heatmaps restent vides</span></span>
    </a>
    <a class="plain-link" href="mes-tickets.html">Voir mes 3 tickets ouverts ${icon('arrow', 'ic', 16)}</a>
  </aside>
</section>

<section class="block" aria-labelledby="q-t">
  <h2 id="q-t" class="h2">Les questions que l'on nous pose le plus</h2>
  <ul class="chips">${featured.map((q) => `<li><a class="chip" href="base-de-connaissances.html">${q}</a></li>`).join('')}</ul>
</section>

<section class="block" aria-labelledby="t-t">
  <h2 id="t-t" class="h2">Parcourir par sujet</h2>
  <div class="topics">${categories.map((c, i) => `<a class="topic t${i % 4}" href="base-de-connaissances.html"><span class="t-ic">${icon(['bolt', 'search', 'file', 'ticket', 'check', 'clock'][i], 'ic', 22)}</span><span class="t-name">${c.name}</span><span class="t-desc">${c.desc}</span><span class="t-count">${c.count} articles</span></a>`).join('')}</div>
</section>

<section class="block team card" aria-labelledby="team-t">
  <div><h2 id="team-t" class="h2">Qui vous répond</h2>
  <p>Votre ticket est lu par une personne de l'équipe, pas par un robot. Les réponses automatiques ne comptent jamais comme une première réponse.</p></div>
  <ul class="people">
    <li>${av('IM', 'pink', 'lg')}<span><b>Inès</b>Support et installation</span></li>
    <li>${av('TM', 'tangerine', 'lg')}<span><b>Théo</b>Auditlens et licences</span></li>
    <li>${av('LB', 'mint', 'lg')}<span><b>Léa</b>Facturation</span></li>
  </ul>
</section>`;

const open = () => `
<div class="page-head"><a class="back" href="index.html">${icon('arrow', 'ic flip', 16)} Retour à l'accueil</a>
<h1 class="display">Écrivez-nous, on regarde ça ensemble.</h1>
<p class="sub">Ouvrez un ticket. Plus vous nous donnez de détails, plus la première réponse sera utile.</p></div>
<div class="alert alert-error" role="alert"><b>Il manque une information.</b> Indiquez un sujet pour votre demande, puis envoyez à nouveau.</div>
<div class="split">
<form id="ticketForm" class="stack" method="post" action="suivi-ticket.html" novalidate>
  <fieldset class="card f-card"><legend class="card-title">Qui êtes-vous ?</legend>
    <div class="grid2">
      <div class="field"><label for="email">Adresse e-mail <span class="req" aria-hidden="true">*</span></label><input id="email" type="email" value="camille.roux@atelier-lumen.fr" autocomplete="email"><p class="help">Nous vous répondons à cette adresse, et nulle part ailleurs.</p></div>
      <div class="field"><label for="name">Nom complet <span class="req" aria-hidden="true">*</span></label><input id="name" type="text" value="Camille Roux" autocomplete="name"></div>
      <div class="field"><label for="phone">Téléphone <span class="opt">(facultatif)</span></label><input id="phone" type="text" autocomplete="tel"></div>
    </div>
  </fieldset>
  <fieldset class="card f-card"><legend class="card-title">Que se passe-t-il ?</legend>
    <div class="field has-error"><label for="subject">Sujet <span class="req" aria-hidden="true">*</span></label><input id="subject" type="text" aria-invalid="true" aria-describedby="subject-err subject-help"><p class="help" id="subject-help">En une phrase : ce qui se passe, et sur quel plugin.</p><p class="err" id="subject-err">Indiquez un sujet pour votre demande.</p></div>
    <div class="field"><label for="message">Racontez-nous <span class="req" aria-hidden="true">*</span></label>${redactor({ id: 'message', label: 'Votre message', value: '<p>Bonjour,</p><p>Depuis hier soir, l\'archivage des rapports s\'arrête avant la fin…</p>', rows: 7 })}</div>
    <div class="field"><span class="lbl">Captures, journaux, exports</span>${filedrop()}</div>
  </fieldset>
  <fieldset class="card f-card"><legend class="card-title">Pour nous aider à aller vite</legend>
    <div class="grid2">
      <div class="field"><label for="topicId">Thème d'aide <span class="req" aria-hidden="true">*</span></label><select id="topicId">${topics.map((t, i) => `<option${i === 0 ? ' selected' : ''}>${t}</option>`).join('')}</select></div>
      <div class="field"><label for="plugin">Plugin concerné <span class="req" aria-hidden="true">*</span></label><select id="plugin"><option selected>Heatlens</option><option>Auditlens</option><option>Autre</option></select></div>
      <div class="field"><label for="mver">Version de Matomo <span class="req" aria-hidden="true">*</span></label><select id="mver"><option>5.14.1</option><option selected>5.14.2</option><option>6.0.0-b6</option></select></div>
      <div class="field"><label for="lic">Numéro de licence <span class="opt">(facultatif)</span></label><input id="lic" type="text" placeholder="OWN-…"><p class="help">Sur votre facture. Il nous donne votre niveau de support.</p></div>
    </div>
    <div class="field"><label for="url">Adresse de votre Matomo <span class="opt">(facultatif)</span></label><input id="url" type="text" value="https://matomo.atelier-lumen.fr"><p class="help">Aucun accès ne vous sera demandé.</p></div>
    <fieldset class="field radios"><legend class="lbl">Hébergement</legend>
      <label class="radio"><input type="radio" name="host" checked> Serveur dédié</label><label class="radio"><input type="radio" name="host"> Hébergement mutualisé</label><label class="radio"><input type="radio" name="host"> Conteneur Docker</label></fieldset>
    <label class="check"><input type="checkbox" checked> J'ai consulté le journal d'erreurs de PHP et celui de Matomo.</label>
  </fieldset>
  <div class="actions"><button class="btn btn-primary btn-lg" type="submit">Envoyer à l'équipe</button><button class="btn btn-ghost" type="reset">Tout effacer</button><a class="plain-link" href="index.html">Annuler</a></div>
</form>
<aside class="side stack" aria-label="Conseils">
  <div class="card tip"><div class="tip-head">${av('IM', 'pink')}<p><b>Inès</b> lira votre message.</p></div>
    <p class="small">Ce qui nous aide le plus : ce que vous attendiez, ce que vous voyez à la place, et depuis quand.</p></div>
  <div class="card"><h2 class="card-title">Avant d'envoyer</h2>
    <ul class="ticks"><li>${icon('check', 'ic', 16)} Joignez le journal d'erreurs, sans données de visiteurs.</li><li>${icon('check', 'ic', 16)} Indiquez les versions de Matomo et du plugin.</li><li>${icon('check', 'ic', 16)} Un seul sujet par ticket.</li></ul></div>
  <div class="card"><h2 class="card-title">Peut-être déjà répondu</h2>
    <ul class="links"><li><a href="base-de-connaissances.html">Pourquoi ma heatmap reste vide</a></li><li><a href="base-de-connaissances.html">Mettre à jour un plugin sans perdre de données</a></li></ul></div>
</aside>
</div>`;

const login = () => `
<div class="page-head center"><h1 class="display">Content de vous revoir.</h1><p class="sub">Connectez-vous pour retrouver vos tickets, ou suivez-en un avec son numéro.</p></div>
<div class="duo">
  <form class="card login" action="mes-tickets.html" method="post" aria-labelledby="l1">
    <h2 id="l1" class="card-title">${icon('user', 'ic', 20)} J'ai un compte</h2>
    <div class="field"><label for="username">Adresse e-mail ou identifiant</label><input id="username" type="text" autocomplete="username"></div>
    <div class="field"><label for="passwd">Mot de passe</label><input id="passwd" type="password" autocomplete="current-password"></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Se connecter</button><a class="plain-link" href="#">Mot de passe oublié</a></div>
    <p class="small sep">Pas encore de compte ? <a href="#">Créer un compte</a>. Vous êtes un agent ? <a href="#">Connectez-vous ici</a>.</p>
  </form>
  <form class="card login alt" action="suivi-ticket.html" method="post" aria-labelledby="l2">
    <h2 id="l2" class="card-title">${icon('ticket', 'ic', 20)} Je suis un ticket sans compte</h2>
    <p class="small">Indiquez votre adresse e-mail et le numéro du ticket. Nous vous envoyons un lien d'accès.</p>
    <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="text" placeholder="par exemple camille@exemple.fr"></div>
    <div class="field"><label for="ticketno">Numéro de ticket</label><input id="ticketno" type="text" placeholder="482913"></div>
    <div class="actions"><button class="btn btn-ghost" type="submit">M'envoyer le lien</button></div>
    <p class="small sep">Première demande, ou numéro perdu ? <a href="ouvrir-un-ticket.html">Ouvrez un ticket</a>.</p>
  </form>
</div>`;

const bubble = (e) => `<li class="msg ${e.type === 'response' ? 'from-team' : 'from-me'}">
  ${av(e.initials, e.type === 'response' ? 'pink' : 'sun')}
  <div class="bubble"><div class="b-head"><b>${e.who}</b>${e.type === 'response' ? '<span class="role">Équipe Ownalytics</span>' : '<span class="role me">Vous</span>'}<time>${e.day} à ${e.time}</time></div>
    <div class="b-body">${e.body.map((p) => (p.startsWith('<code>') ? `<pre>${p.replace(/<\/?code>/g, '')}</pre>` : `<p>${p}</p>`)).join('')}</div>${fileChips(e.files)}</div></li>`;

const ticketPage = () => `
<div class="page-head"><a class="back" href="mes-tickets.html">${icon('arrow', 'ic flip', 16)} Mes tickets</a>
  <p class="num">Ticket #${ticket.number}</p>
  <h1 class="display h-ticket">${esc(ticket.subject)}</h1>
  <div class="meta"><span class="pill pill-open">Ouvert</span><span class="pill pill-prio">Support prioritaire</span><span class="muted">Ouvert le 14 octobre, dernière réponse le 15 octobre</span></div>
  ${stepper(2)}</div>
<div class="split ticket-split">
  <div class="stack">
    <ol class="convo" aria-label="Conversation">${thread.map(bubble).join('')}</ol>
    <form id="reply" class="card reply" action="suivi-ticket.html#reply" method="post">
      <h2 class="card-title">${av('CR', 'sun', 'sm')} Répondre à Inès</h2>
      <p class="help">Soyez précis et détaillé : cela nous fait gagner un échange.</p>
      ${redactor({ id: 'reply-message', label: 'Votre réponse', rows: 6 })}
      ${filedrop()}
      <div class="actions"><button class="btn btn-primary" type="submit">Envoyer la réponse</button><button class="btn btn-ghost" type="reset">Effacer</button></div>
    </form>
  </div>
  <aside class="side stack" aria-label="Détails du ticket">
    <div class="card"><h2 class="card-title">Votre demande</h2>
      <dl class="kv"><div><dt>Statut</dt><dd><span class="pill pill-open">Ouvert</span></dd></div><div><dt>Service</dt><dd>${ticket.dept}</dd></div><div><dt>Créé le</dt><dd>${ticket.created}</dd></div>
      ${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}</dl></div>
    <div class="card"><h2 class="card-title">Pièces jointes <span class="count">4</span></h2>
      <ul class="files"><li>${icon('clip', 'ic', 15)} <a href="#">capture-heatmap-vide.png</a> <small>84 Ko</small></li><li>${icon('clip', 'ic', 15)} <a href="#">matomo-extrait.log</a> <small>12 Ko</small></li><li>${icon('clip', 'ic', 15)} <a href="#">sortie-archivage.txt</a> <small>3 Ko</small></li><li>${icon('clip', 'ic', 15)} <a href="#">guide-reprise-archivage.pdf</a> <small>212 Ko</small></li></ul>
      <p class="small"><a href="#" class="plain-link">${icon('print', 'ic', 15)} Imprimer le ticket</a></p></div>
  </aside>
</div>`;

const listPage = () => `
<div class="page-head"><h1 class="display">Vos demandes, ${client.first}.</h1><p class="sub">Trois tickets en cours. Une réponse de l'équipe vous attend sur le premier.</p></div>
<div class="toolbar"><div class="tabs" role="tablist" aria-label="Statut"><a href="#" class="tab on" aria-current="true">Ouverts <span class="count">3</span></a><a href="#" class="tab">Fermés <span class="count">3</span></a></div>
  <form class="tsearch" role="search"><label class="visually-hidden" for="ts">Rechercher dans mes tickets</label>${icon('search', 'ic', 18)}<input id="ts" type="search" placeholder="Rechercher un sujet ou un numéro"></form>
  <label class="visually-hidden" for="tt">Thème d'aide</label><select id="tt"><option>Tous les thèmes</option>${topics.map((t) => `<option>${t}</option>`).join('')}</select></div>
<ul class="tlist">${tickets.filter((t) => t.status === 'Ouvert').map((t, i) => `<li><a class="tcard${t.answered ? ' unread' : ''}" href="suivi-ticket.html">
  ${av(['IM', 'LB', 'TM'][i], ['pink', 'mint', 'tangerine'][i])}
  <span class="t-main"><span class="t-subj">${esc(t.subject)}</span><span class="t-sub">#${t.n} · ${t.topic} · ouvert le ${t.date}</span><span class="t-last">${t.rel}</span></span>
  <span class="t-side">${t.answered ? '<span class="pill pill-new">Nouvelle réponse</span>' : '<span class="pill pill-wait">En attente de l\'équipe</span>'}<span class="chev">${icon('chevron', 'ic', 18)}</span></span></a></li>`).join('')}</ul>
<p class="pager" aria-label="Pagination">Page 1 sur 1 · <a href="#">Voir aussi mes 3 tickets fermés</a></p>`;

const kbPage = () => `
<section class="kb-hero"><h1 class="display">Une réponse vous attend peut-être déjà.</h1>
  <form class="ask" role="search" action="base-de-connaissances.html"><label class="visually-hidden" for="q">Rechercher dans la base de connaissances</label><input id="q" type="search" placeholder="Rechercher : heatmap vide, licence, export CSV…"><button class="btn btn-primary" type="submit">Rechercher</button></form>
  <p class="small">Pas trouvé ? <a href="ouvrir-un-ticket.html">Écrivez-nous</a>, une personne vous répond.</p></section>
<section class="block"><h2 class="h2">Tous les sujets</h2>
  <div class="topics big">${categories.map((c, i) => `<article class="topic t${i % 4}"><span class="t-ic">${icon(['bolt', 'search', 'file', 'ticket', 'check', 'clock'][i], 'ic', 22)}</span><h3 class="t-name"><a href="base-de-connaissances.html">${c.name}</a> <span class="t-count">${c.count}</span></h3><p class="t-desc">${c.desc}</p>
    <ul class="links">${c.faqs.map((f) => `<li><a href="base-de-connaissances.html">${f}</a></li>`).join('')}</ul></article>`).join('')}</div></section>
<section class="block card pop"><h2 class="h2">Les plus lus cette semaine</h2><ol class="poplist">${featured.map((q, i) => `<li><a href="base-de-connaissances.html">${q}</a><span class="muted">${[412, 301, 255, 198, 164][i]} lectures</span></li>`).join('')}</ol></section>`;

export default {
  dir: '03-atelier',
  pages: {
    home: () => shell(home(), 'home', true, 'Centre d\'aide'),
    open: () => shell(open(), 'open', false, 'Ouvrir un nouveau ticket'),
    login: () => shell(login(), 'login', false, 'Connexion'),
    ticket: () => shell(ticketPage(), 'ticket', true, 'Ticket #482913'),
    tickets: () => shell(listPage(), 'tickets', true, 'Mes tickets'),
    kb: () => shell(kbPage(), 'kb', false, 'Base de connaissances'),
  },
};
