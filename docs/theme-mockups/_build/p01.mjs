// Proposition 01 : « Ticket de caisse » (continuite stricte du site vitrine). Balisage osTicket fidele.
import { doc, nav, client, receiptMark, BACK_LINK, icon } from './common.mjs';
import * as F from './faithful.mjs';

const landing = `<h1 class="display">Une question sur Heatlens ou Auditlens ? Écrivez-nous, une personne vous répond.</h1>
<p class="lede">Le support est inclus avec chaque licence. Vos tickets restent entre vous et nous, et les données de vos visiteurs n'ont rien à y faire.</p>
<ul class="r-list">
  <li class="r-row"><span>Support inclus</span><span class="lead" aria-hidden="true"></span><span class="val">jours ouvrés</span></li>
  <li class="r-row"><span>Support prioritaire</span><span class="lead" aria-hidden="true"></span><span class="val">4 h, 24 h/24</span></li>
  <li class="r-row"><span>Langues</span><span class="lead" aria-hidden="true"></span><span class="val">français, anglais</span></li>
</ul>`;

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
    <a id="logo" href="index.html" aria-label="Ownalytics Support, accueil">${receiptMark()}<span class="wm">Ownalytics</span><span class="wm-sub">Support</span></a>
    <div class="pull-right flush-right">
      <p>${logged
        ? `${client.name} | <a href="#">Profil</a> | <a href="mes-tickets.html">Tickets <b>(3)</b></a> - <a href="connexion.html">Se déconnecter</a>`
        : `Visiteur | <a href="connexion.html">Se connecter</a>`}</p>
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
    <a class="foot-mark" href="index.html" aria-label="Ownalytics, accueil">${receiptMark('#FBFBF6', '#2A1340')}<span>Ownalytics</span></a>
    <ul class="legal"><li><a href="#">Mentions légales</a></li><li><a href="#">Conditions</a></li><li><a href="#">Confidentialité</a></li><li><a href="#">Politique de support</a></li></ul>
    <p>Matomo est une marque déposée d'InnoCraft Ltd. Ownalytics est un éditeur indépendant, sans lien avec InnoCraft.</p>
    <p>© 2026 Coderise SAS - Tous droits réservés. <a id="poweredBy" href="https://osticket.com">Logiciel de support propulsé par osTicket</a></p>
  </div>
</div>`,
  });
}

export default {
  dir: '01-ticket-de-caisse',
  pages: {
    home: () => shell(F.home(landing), 'home', false, 'Centre d\'aide'),
    open: () => shell(F.open(), 'open', false, 'Ouvrir un nouveau ticket', '<div id="msg_error">Veuillez corriger les erreurs ci-dessous et réessayer.</div>'),
    login: () => shell(F.login(), 'login', false, 'Connexion'),
    ticket: () => shell(F.ticketView(), 'ticket', true, `Ticket #482913`),
    tickets: () => shell(F.ticketsList(), 'tickets', true, 'Mes tickets'),
    kb: () => shell(F.kb(), 'kb', false, 'Base de connaissances'),
  },
};
