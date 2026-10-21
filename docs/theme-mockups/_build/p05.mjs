// Proposition 05 : « Une » (editorial, contraste fort). Gabarits reecrits (niveau 2).
import { doc, nav, client, thread, ticket, tickets, categories, featured, topics, receiptMark, BACK_LINK, icon, redactor, filedrop, fileChips, esc } from './common.mjs';

function shell(page, key, logged, title) {
  const items = nav(key, logged);
  return doc({
    title: `${title} | Ownalytics Support`,
    css: 'theme.css',
    bodyClass: `p-${key}`,
    body: `${BACK_LINK}
<a class="skip" href="#main">Aller au contenu</a>
<header class="site-header">
  <div class="wrap hd">
    <a class="brand" href="index.html" aria-label="Ownalytics Support, accueil">${receiptMark()}<span class="wm">Ownalytics</span><span class="wm-sub">Support</span></a>
    <nav aria-label="Menu principal"><ul class="menu">${items.map((n) => `<li><a href="${n.href}"${n.active ? ' aria-current="page"' : ''}>${n.label}</a></li>`).join('')}</ul></nav>
    <p class="acct">${logged ? `${client.name} <a href="connexion.html">Se déconnecter</a>` : '<a href="connexion.html">Se connecter</a>'} <span class="lang"><a href="#" aria-current="true">FR</a> / <a href="#" hreflang="en">EN</a></span></p>
  </div>
</header>
<main id="main" tabindex="-1">
${page}
</main>
<footer class="site-footer">
  <div class="wrap ft">
    <p class="ft-big">Ownalytics</p>
    <ul class="legal"><li><a href="#">Mentions légales</a></li><li><a href="#">Conditions</a></li><li><a href="#">Confidentialité</a></li><li><a href="#">Politique de support</a></li></ul>
    <p class="small">Matomo est une marque déposée d'InnoCraft Ltd. Ownalytics est un éditeur indépendant, sans lien avec InnoCraft.</p>
    <p class="small">© 2026 Coderise SAS - Tous droits réservés. <a href="https://osticket.com">Logiciel de support propulsé par osTicket</a></p>
  </div>
</footer>`,
  });
}

const home = () => `
<section class="mast hero" aria-labelledby="h1">
  <div class="wrap hero-grid">
    <div>
      <h1 id="h1" class="mega">Une question ? Une personne vous répond, par écrit.</h1>
      <p class="lede">Le support Ownalytics est inclus avec chaque licence. Nous lisons chaque ticket, nous répondons en français ou en anglais, et vous gardez vos données de visiteurs pour vous.</p>
      <div class="cta-row"><a class="btn btn-marker" href="ouvrir-un-ticket.html">Ouvrir un ticket</a><a class="btn btn-line" href="connexion.html">Suivre un ticket</a></div>
    </div>
    <figure class="stub" aria-label="Prochain numéro de ticket">
      <figcaption>Prochain ticket</figcaption>
      <p class="stub-n">N° 482914</p>
      <p class="stub-t">Le vôtre, peut-être.</p>
      <p class="stub-s">Support inclus : réponse les jours ouvrés.<br>Support prioritaire : 4 h, 24 h/24.</p>
    </figure>
  </div>
</section>

<section class="wrap index" aria-label="Où aller">
  <a class="idx" href="base-de-connaissances.html"><span class="idx-t">Chercher dans les guides</span><span class="idx-d">42 articles : installation, licences, compatibilité.</span>${icon('arrow', 'ic', 28)}</a>
  <a class="idx" href="ouvrir-un-ticket.html"><span class="idx-t">Écrire à l'équipe</span><span class="idx-d">Un sujet par ticket, avec versions et journaux.</span>${icon('arrow', 'ic', 28)}</a>
  <a class="idx" href="connexion.html"><span class="idx-t">Suivre un ticket</span><span class="idx-d">Avec votre compte, ou avec le numéro du ticket.</span>${icon('arrow', 'ic', 28)}</a>
</section>

<section class="wrap lead" aria-labelledby="lead-t">
  <p class="kicker">À lire d'abord</p>
  <div class="lead-grid">
    <div><h2 id="lead-t" class="big">Pourquoi ma heatmap reste vide.</h2>
    <p class="lede dark">La cause la plus fréquente : un archivage interrompu juste après une mise à jour de Matomo. Les données ne sont pas perdues. Voici comment les récupérer en trois commandes.</p>
    <p><a class="textlink" href="base-de-connaissances.html">Lire l'article</a></p></div>
    <ol class="short" aria-label="Autres questions fréquentes">${featured.slice(1).map((q) => `<li><a href="base-de-connaissances.html">${q}</a></li>`).join('')}</ol>
  </div>
</section>

<section class="band" aria-labelledby="cat-t"><div class="wrap">
  <h2 id="cat-t" class="big">Par sujet</h2>
  <div class="cats">${categories.map((c) => `<a class="cat" href="base-de-connaissances.html"><span class="cat-n">${c.name}</span><span class="cat-d">${c.desc}</span><span class="cat-c">${c.count} articles</span></a>`).join('')}</div>
</div></section>`;

const row = (label, control, { id, help = '', err = '' } = {}) => `<div class="fld${err ? ' has-error' : ''}"><label for="${id}">${label}</label>${control}${help ? `<p class="help" id="${id}-help">${help}</p>` : ''}${err ? `<p class="err" id="${id}-err"><span>${err}</span></p>` : ''}</div>`;

const open = () => `
<div class="split">
  <section class="split-l"><div class="sticky">
    <h1 class="mega sm">Dites-nous tout.</h1>
    <p class="lede">Plus votre ticket est précis, plus la première réponse est utile.</p>
    <ul class="rules"><li><b>Ce que vous attendiez</b> et ce que vous voyez à la place.</li><li><b>Vos versions</b> de Matomo et du plugin.</li><li><b>Les journaux d'erreurs</b>, sans données de visiteurs.</li></ul>
    <p class="note">Prioritaire ? Ajoutez votre numéro de licence : la première réponse arrive sous 4 h.</p>
  </div></section>
  <section class="split-r">
    <p class="alert" role="alert"><b>Il manque un sujet.</b> Remplissez-le, puis envoyez à nouveau.</p>
    <form id="ticketForm" method="post" action="suivi-ticket.html" novalidate>
      <fieldset><legend>Vous</legend>
        ${row('Adresse e-mail *', '<input id="email" type="email" value="camille.roux@atelier-lumen.fr" autocomplete="email">', { id: 'email' })}
        <div class="two">${row('Nom complet *', '<input id="name" type="text" value="Camille Roux" autocomplete="name">', { id: 'name' })}${row('Téléphone', '<input id="phone" type="text" autocomplete="tel">', { id: 'phone' })}</div>
      </fieldset>
      <fieldset><legend>Votre demande</legend>
        ${row('Thème d\'aide *', `<select id="topicId">${topics.map((t, i) => `<option${i === 0 ? ' selected' : ''}>${t}</option>`).join('')}</select>`, { id: 'topicId' })}
        ${row('Sujet *', '<input id="subject" type="text" aria-invalid="true" aria-describedby="subject-help subject-err">', { id: 'subject', help: 'En une phrase : ce qui se passe, et sur quel plugin.', err: 'Indiquez un sujet pour votre demande.' })}
        ${row('Message *', redactor({ id: 'message', label: 'Message', value: '<p>Bonjour,</p><p>Depuis hier soir, l\'archivage des rapports s\'arrête avant la fin…</p>', rows: 7 }), { id: 'message' })}
        <div class="fld"><span class="lab">Pièces jointes</span>${filedrop()}</div>
      </fieldset>
      <fieldset><legend>Contexte</legend>
        <div class="two">${row('Plugin concerné *', '<select id="plugin"><option selected>Heatlens</option><option>Auditlens</option><option>Autre</option></select>', { id: 'plugin' })}${row('Version de Matomo *', '<select id="mver"><option>5.14.1</option><option selected>5.14.2</option><option>6.0.0-b6</option></select>', { id: 'mver' })}</div>
        ${row('Adresse de votre Matomo', '<input id="url" type="text" value="https://matomo.atelier-lumen.fr">', { id: 'url', help: 'Aucun accès ne vous sera demandé.' })}
        <fieldset class="fld rad"><legend class="lab">Hébergement</legend><label class="opt"><input type="radio" name="host" checked> Serveur dédié</label><label class="opt"><input type="radio" name="host"> Mutualisé</label><label class="opt"><input type="radio" name="host"> Conteneur Docker</label></fieldset>
        ${row('Numéro de licence', '<input id="lic" type="text" placeholder="OWN-…">', { id: 'lic', help: 'Sur votre facture. Il donne accès au support prioritaire si vous y avez souscrit.' })}
        <label class="opt chk"><input type="checkbox" checked> J'ai consulté le journal d'erreurs de PHP et celui de Matomo.</label>
      </fieldset>
      <div class="cta-row"><button class="btn btn-marker" type="submit">Envoyer le ticket</button><button class="btn btn-line dark" type="reset">Tout effacer</button><a class="textlink" href="index.html">Annuler</a></div>
    </form>
  </section>
</div>`;

const login = () => `
<div class="split rev">
  <section class="split-r">
    <h1 class="mega sm dark">Connexion.</h1>
    <form class="lg" action="mes-tickets.html" method="post" aria-label="Connexion à mon compte">
      ${row('E-mail ou identifiant', '<input id="username" type="text" autocomplete="username">', { id: 'username' })}
      ${row('Mot de passe', '<input id="passwd" type="password" autocomplete="current-password">', { id: 'passwd' })}
      <div class="cta-row"><button class="btn btn-plum" type="submit">Se connecter</button><a class="textlink" href="#">Mot de passe oublié</a></div>
    </form>
    <p class="note">Pas encore de compte ? <a href="#">Créer un compte</a>. Vous êtes agent ? <a href="#">Connexion agents</a>.</p>
  </section>
  <section class="split-l mk"><div class="sticky">
    <h2 class="big dark">Pas de compte ? Suivez votre ticket par son numéro.</h2>
    <p class="lede dark">Nous vous envoyons un lien d'accès à l'adresse e-mail du ticket.</p>
    <form class="lg" action="suivi-ticket.html" method="post" aria-label="Suivre un ticket sans compte">
      ${row('Adresse e-mail', '<input id="email" type="text" placeholder="camille@exemple.fr">', { id: 'email' })}
      ${row('Numéro de ticket', '<input id="ticketno" type="text" placeholder="482913">', { id: 'ticketno' })}
      <div class="cta-row"><button class="btn btn-plum" type="submit">Envoyer le lien d'accès</button></div>
    </form>
    <p class="note dark">Première demande, ou numéro perdu ? <a href="ouvrir-un-ticket.html">Ouvrez un ticket</a>.</p>
  </div></section>
</div>`;

const entry = (e) => `<article class="ent ${e.type}" aria-label="${e.who}, ${e.at}">
  <div class="when"><span class="d">${e.day.split(' ')[0]}</span><span class="m">${e.day.split(' ')[1]}</span><time>${e.time}</time></div>
  <div class="what"><p class="who"><b>${e.who}</b>${e.type === 'response' ? ' <span class="tag">Équipe Ownalytics</span>' : ' <span class="tag you">Vous</span>'}</p>
    ${e.body.map((p) => (p.startsWith('<code>') ? `<pre>${p.replace(/<\/?code>/g, '')}</pre>` : `<p>${p}</p>`)).join('')}${fileChips(e.files)}</div></article>`;

const ticketPage = () => `
<section class="mast" aria-labelledby="t1"><div class="wrap">
  <p class="back"><a href="mes-tickets.html">Mes tickets</a></p>
  <p class="tnum">#${ticket.number}</p>
  <h1 id="t1" class="headline">${esc(ticket.subject)}</h1>
  <dl class="strip"><div><dt>Statut</dt><dd>Ouvert</dd></div><div><dt>Support</dt><dd>Prioritaire, 4 h</dd></div><div><dt>Service</dt><dd>${ticket.dept}</dd></div><div><dt>Création</dt><dd class="mono">${ticket.created}</dd></div></dl>
</div></section>
<div class="wrap t-grid">
  <div>
    <h2 class="sec">Correspondance</h2>
    <div class="corr">${thread.map(entry).join('')}</div>
    <form id="reply" class="reply" action="suivi-ticket.html#reply" method="post">
      <h2 class="big dark">Répondre.</h2>
      <p class="help">Soyez précis et détaillé. Joignez journaux et captures, sans données de visiteurs.</p>
      ${redactor({ id: 'reply-message', label: 'Votre réponse', rows: 6 })}
      ${filedrop()}
      <div class="cta-row"><button class="btn btn-plum" type="submit">Envoyer la réponse</button><button class="btn btn-line dark" type="reset">Effacer</button></div>
    </form>
  </div>
  <aside class="facts" aria-label="Détails du ticket">
    <h2 class="sec">Détails</h2>
    <dl>${ticket.custom.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}<div><dt>Nom</dt><dd>${client.name}</dd></div><div><dt>E-mail</dt><dd>${client.email}</dd></div><div><dt>Téléphone</dt><dd class="mono">${client.phone}</dd></div></dl>
    <h2 class="sec">Pièces jointes</h2>
    <ul class="files"><li><a href="#">capture-heatmap-vide.png</a><small>84 Ko</small></li><li><a href="#">matomo-extrait.log</a><small>12 Ko</small></li><li><a href="#">sortie-archivage.txt</a><small>3 Ko</small></li><li><a href="#">guide-reprise-archivage.pdf</a><small>212 Ko</small></li></ul>
    <p class="acts"><a class="textlink" href="#">Imprimer</a> <a class="textlink" href="#">Modifier</a></p>
  </aside>
</div>`;

const listPage = () => `
<section class="mast short" aria-labelledby="l1"><div class="wrap">
  <h1 id="l1" class="mega">Vos tickets.</h1>
  <p class="lede">Trois ouverts, dont deux avec une réponse de l'équipe.</p>
</div></section>
<div class="wrap">
  <form class="bar" role="search" action="mes-tickets.html">
    <p class="tabs"><a href="#" aria-current="true">Ouverts <span>3</span></a><a href="#">Fermés <span>3</span></a></p>
    <div class="fld inl"><label class="visually-hidden" for="kw">Rechercher un ticket</label><input id="kw" type="search" placeholder="Rechercher un sujet ou un numéro"></div>
    <div class="fld inl"><label class="visually-hidden" for="tp">Thème d'aide</label><select id="tp"><option>Tous les thèmes</option>${topics.map((t) => `<option>${t}</option>`).join('')}</select></div>
  </form>
  <p class="sortline">Trier par : <a href="#" aria-current="true">création</a> <a href="#">statut</a> <a href="#">sujet</a> <a href="#">service</a></p>
  <ul class="rows" id="ticketTable">${tickets.filter((t) => t.status === 'Ouvert').map((t) => `<li><a class="rw${t.answered ? ' new' : ''}" href="suivi-ticket.html">
    <span class="rn">#${t.n}</span>
    <span class="rs"><span class="rt">${esc(t.subject)}</span><span class="rm">${t.topic} · ${t.dept} · ouvert le ${t.date}</span></span>
    <span class="rst">${t.answered ? 'Réponse reçue' : 'En attente'}</span></a></li>`).join('')}</ul>
  <p class="pg">1 à 3 sur 3 tickets ouverts. Page 1 sur 1.</p>
</div>`;

const kbPage = () => `
<section class="mast short" aria-labelledby="k1"><div class="wrap">
  <h1 id="k1" class="mega">Les guides.</h1>
  <form class="bigsearch" role="search" action="base-de-connaissances.html"><label class="visually-hidden" for="q">Rechercher dans la base de connaissances</label><input id="q" type="search" placeholder="Rechercher : heatmap vide, licence, export…"><button class="btn btn-marker" type="submit">Chercher</button></form>
</div></section>
<div class="wrap kb">
  <div>
    <h2 class="sec">Tous les sujets</h2>
    <div class="klist">${categories.map((c) => `<article class="kc"><h3><a href="base-de-connaissances.html">${c.name}</a><span class="kn">${c.count}</span></h3><p>${c.desc}</p><ul>${c.faqs.map((f) => `<li><a href="base-de-connaissances.html">${f}</a></li>`).join('')}</ul></article>`).join('')}</div>
  </div>
  <aside><h2 class="sec">Les plus lus</h2>
    <ol class="top">${featured.map((q, i) => `<li><a href="base-de-connaissances.html">${q}</a><span class="mono">${[412, 301, 255, 198, 164][i]}</span></li>`).join('')}</ol>
    <div class="ask"><p><b>Pas trouvé ?</b></p><p>Une personne vous répond.</p><a class="btn btn-plum" href="ouvrir-un-ticket.html">Ouvrir un ticket</a></div>
  </aside>
</div>`;

export default {
  dir: '05-une',
  pages: {
    home: () => shell(home(), 'home', false, 'Centre d\'aide'),
    open: () => shell(open(), 'open', false, 'Ouvrir un ticket'),
    login: () => shell(login(), 'login', false, 'Connexion'),
    ticket: () => shell(ticketPage(), 'ticket', true, 'Ticket #482913'),
    tickets: () => shell(listPage(), 'tickets', true, 'Mes tickets'),
    kb: () => shell(kbPage(), 'kb', false, 'Base de connaissances'),
  },
};
