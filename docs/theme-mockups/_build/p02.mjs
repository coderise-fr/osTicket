// Proposition 02 : « Console » (sobre, type console de support / documentation). Balisage osTicket fidele.
import { doc, nav, client, receiptMark, BACK_LINK, icon } from './common.mjs';
import * as F from './faithful.mjs';

const landing = `<h1>Comment pouvons-nous vous aider ?</h1>
<p class="lead">Cherchez dans la documentation, ou ouvrez un ticket : une personne de l'équipe vous répond.</p>
<dl class="levels">
  <div><dt>Support inclus</dt><dd>Avec chaque licence. Réponse les jours ouvrés, en français ou en anglais.</dd></div>
  <div><dt>Support prioritaire</dt><dd>Première réponse sous 4 h, 24 h/24, week-ends et jours fériés compris.</dd></div>
  <div><dt>Pour aller plus vite</dt><dd>Indiquez vos versions de Matomo et du plugin, et joignez les journaux. N'envoyez jamais de données de visiteurs.</dd></div>
</dl>`;

function shell(page, key, logged, title, msg = '') {
  const items = nav(key, logged);
  return doc({
    title: `${title} | Ownalytics Support`,
    css: 'theme.css',
    bodyClass: `p-${key}`,
    body: `${BACK_LINK}
<a class="skip" href="#content">Aller au contenu</a>
<div id="container">
  <div id="header">
    <a id="logo" href="index.html" aria-label="Ownalytics Support, accueil">${receiptMark()}<span class="wm">Ownalytics</span><span class="wm-sub">Centre d'aide</span></a>
    <form class="topsearch" role="search" action="base-de-connaissances.html">
      ${icon('search', 'ic', 16)}<input type="search" name="q" placeholder="Rechercher dans la documentation" aria-label="Rechercher dans la documentation"><kbd aria-hidden="true">/</kbd>
    </form>
    <div class="pull-right flush-right userbox">
      <p>${logged
        ? `<span class="who">${icon('user', 'ic', 16)} ${client.name}</span> <a href="#">Profil</a> <a href="mes-tickets.html">Tickets <b>(3)</b></a> <a href="connexion.html">Se déconnecter</a>`
        : `<a href="connexion.html">Se connecter</a> <a class="cta" href="ouvrir-un-ticket.html">Ouvrir un ticket</a>`}</p>
      <p class="langs"><a href="#" aria-current="true">FR</a> <a href="#" hreflang="en">EN</a></p>
    </div>
  </div>
  <div class="clear"></div>
  <nav aria-label="Menu principal"><ul id="nav" class="flush-left">${items.map((n) => `<li><a class="${n.active ? 'active' : ''} ${n.cls}" href="${n.href}"${n.active ? ' aria-current="page"' : ''}>${n.label}</a></li>`).join('')}</ul></nav>
  <main id="content" tabindex="-1">
    ${msg}
    ${page}
  </main>
</div>
<div id="footer">
  <div class="foot-inner">
    <p>© 2026 Coderise SAS - Tous droits réservés.</p>
    <ul class="legal"><li><a href="#">Mentions légales</a></li><li><a href="#">Conditions</a></li><li><a href="#">Confidentialité</a></li><li><a href="#">Politique de support</a></li><li><a href="#">État du service</a></li></ul>
    <p class="small">Matomo est une marque déposée d'InnoCraft Ltd. Ownalytics est un éditeur indépendant, sans lien avec InnoCraft. <a id="poweredBy" href="https://osticket.com">Logiciel de support propulsé par osTicket</a></p>
  </div>
</div>`,
  });
}

export default {
  dir: '02-console',
  pages: {
    home: () => shell(F.home(landing), 'home', false, 'Centre d\'aide'),
    open: () => shell(F.open(), 'open', false, 'Ouvrir un nouveau ticket', '<div id="msg_error">Veuillez corriger les erreurs ci-dessous et réessayer.</div>'),
    login: () => shell(F.login(), 'login', false, 'Connexion'),
    ticket: () => shell(F.ticketView(), 'ticket', true, 'Ticket #482913'),
    tickets: () => shell(F.ticketsList(), 'tickets', true, 'Mes tickets'),
    kb: () => shell(F.kb(), 'kb', false, 'Base de connaissances'),
  },
};
