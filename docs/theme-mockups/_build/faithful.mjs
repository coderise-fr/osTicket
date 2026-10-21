// Corps de pages au balisage FIDELE a osTicket (tableaux, #ticketForm, #ticketTable, .thread-entry...).
// Utilise par les propositions 01 et 02 : seul le CSS (et l'en-tete/pied de page) change, ce qui
// demontre ce que le niveau 1 permet. Les noms d'identifiants et de classes sont ceux de include/client/*.
import { client, agent, thread, ticket, tickets, categories, featured, topics, icon, redactor, filedrop, fileChips, esc } from './common.mjs';

const hint = (t) => `<br><em style="color:gray;display:inline-block">${t}</em>`;
const req = '<span class="error">*</span>';

function fieldRow(label, control, { required = false, hintText = '', error = '', id = '' } = {}) {
  return `<tr><td colspan="2" style="padding-top:10px;">
    <label for="${id}"><span class="${required ? 'required' : ''}">${label} ${required ? req : ''}${hintText ? hint(hintText) : ''}</span><br>
    ${control}</label>${error ? `<div class="error">${error}</div>` : ''}
  </td></tr>`;
}

const select = (id, name, opts, selected) =>
  `<select id="${id}" name="${name}"><option value="">&mdash; Sélectionner &mdash;</option>${opts.map((o) => `<option${o === selected ? ' selected' : ''}>${o}</option>`).join('')}</select>`;

export function home(landingHtml) {
  return `<div id="landing_page">
  <div class="sidebar pull-right">
    <div class="front-page-button flush-right">
      <p><a href="ouvrir-un-ticket.html" style="display:block" class="blue button">Ouvrir un nouveau ticket</a></p>
      <p><a href="connexion.html" style="display:block" class="green button">Consulter l'état d'un ticket</a></p>
    </div>
    <div class="content">
      <section><div class="header">Questions fréquentes</div>
        ${featured.map((q) => `<div><a href="base-de-connaissances.html">${q}</a></div>`).join('')}
      </section>
      <section><div class="header">Autres ressources</div>
        <div><a href="#">Politique de support</a></div>
        <div><a href="#">Conditions de licence</a></div>
        <div><a href="#">État du service</a></div>
      </section>
    </div>
  </div>
  <div class="main-content">
    <div class="search-form">
      <form method="get" action="base-de-connaissances.html" role="search">
        <input type="hidden" name="a" value="search">
        <input type="text" name="q" class="search" placeholder="Rechercher dans la base de connaissances" aria-label="Rechercher dans la base de connaissances">
        <button type="submit" class="green button">Rechercher</button>
      </form>
    </div>
    <div class="thread-body">${landingHtml}</div>
  </div>
  <div class="clear"></div>
  <div class="featured">
    <h1>Articles à la une</h1>
    ${categories.slice(0, 3).map((c) => `<div class="featured-category front-page">
      ${icon('folder', 'ic icon-folder-open', 28)}
      <div class="category-name">${c.name}</div>
      ${c.faqs.slice(0, 2).map((f, i) => `<div class="article-headline"><div class="article-title"><a href="base-de-connaissances.html">${f}</a></div><div class="article-teaser">${['Les étapes à suivre, de la vérification du code de suivi à la reprise des données.', 'Ce que le plugin fait, ce qu\'il ne fait pas, et comment le régler dans Matomo.'][i]}</div></div>`).join('')}
    </div>`).join('')}
  </div>
</div>`;
}

export function open() {
  return `<h1>Ouvrir un nouveau ticket</h1>
<p>Remplissez le formulaire ci-dessous pour ouvrir un nouveau ticket.</p>
<form id="ticketForm" method="post" action="suivi-ticket.html" enctype="multipart/form-data" novalidate>
<table width="800" cellpadding="1" cellspacing="0" border="0">
<tbody>
  <tr><td colspan="2"><div class="form-header" style="margin-bottom:0.5em"><h3>Vos coordonnées</h3><div>Nous vous répondons à cette adresse. Elle ne sert à rien d'autre.</div></div></td></tr>
  ${fieldRow('Adresse e-mail', '<input type="email" id="email" name="email" size="40" value="camille.roux@atelier-lumen.fr" autocomplete="email">', { required: true, id: 'email' })}
  ${fieldRow('Nom complet', '<input type="text" id="name" name="name" size="40" value="Camille Roux" autocomplete="name">', { required: true, id: 'name' })}
  ${fieldRow('Téléphone', '<input type="text" id="phone" name="phone" size="20" autocomplete="tel"> &nbsp;Poste&nbsp;<input type="text" name="ext" size="3">', { id: 'phone' })}
</tbody>
<tbody>
  <tr><td colspan="2"><hr><div class="form-header" style="margin-bottom:0.5em"><b>Thème d'aide</b></div></td></tr>
  <tr><td colspan="2">
    <select id="topicId" name="topicId" aria-label="Thème d'aide"><option value="">&mdash; Choisir un thème &mdash;</option>${topics.map((t, i) => `<option${i === 0 ? ' selected' : ''}>${t}</option>`).join('')}</select>
    <font class="error">*</font>
  </td></tr>
</tbody>
<tbody id="dynamic-form">
  <tr><td colspan="2"><hr><div class="form-header" style="margin-bottom:0.5em"><h3>Détails de votre demande</h3><div>Plus nous avons d'éléments, plus la première réponse est utile.</div></div></td></tr>
  ${fieldRow('Sujet', '<input type="text" id="subject" name="subject" size="60" value="" aria-invalid="true" aria-describedby="subject-err">', { required: true, hintText: 'En une phrase : ce qui se passe, et sur quel plugin.', error: 'Indiquez un sujet pour votre demande.', id: 'subject' }).replace('class="error"', 'class="error" id="subject-err"')}
  ${fieldRow('Message', redactor({ id: 'message', label: 'Message', value: '<p>Bonjour,</p><p>Depuis hier soir, l\'archivage des rapports s\'arrête avant la fin…</p>' }) + filedrop(), { required: true, id: 'message' })}
  ${fieldRow('Plugin concerné', select('plugin', 'plugin', ['Heatlens', 'Auditlens', 'Autre'], 'Heatlens'), { required: true, id: 'plugin' })}
  ${fieldRow('Version de Matomo', select('mver', 'mver', ['5.14.1', '5.14.2', '6.0.0-b6'], '5.14.2'), { required: true, id: 'mver' })}
  ${fieldRow('Adresse de votre Matomo', '<input type="text" id="url" name="url" size="60" value="https://matomo.atelier-lumen.fr">', { hintText: 'Par exemple https://matomo.exemple.fr. Aucun accès ne vous sera demandé.', id: 'url' })}
  ${fieldRow('Hébergement', `<span class="choices"><label class="inline"><input type="radio" name="host" checked> Serveur dédié</label> <label class="inline"><input type="radio" name="host"> Hébergement mutualisé</label> <label class="inline"><input type="radio" name="host"> Conteneur Docker</label></span>`, { id: 'host' })}
  ${fieldRow('Numéro de licence', '<input type="text" id="lic" name="lic" size="30" placeholder="OWN-…">', { hintText: 'Il figure sur votre facture. Il nous permet de retrouver votre niveau de support.', id: 'lic' })}
  ${fieldRow('Vérification', '<label class="inline"><input type="checkbox" id="chk" name="chk" checked> J\'ai consulté le journal d\'erreurs de PHP et celui de Matomo.</label>', { id: 'chk' })}
</tbody>
<tbody><tr><td colspan="2">&nbsp;</td></tr></tbody>
</table>
<hr>
<p class="buttons" style="text-align:center;">
  <input type="submit" value="Créer le ticket"> <input type="reset" name="reset" value="Réinitialiser"> <input type="button" name="cancel" value="Annuler">
</p>
</form>`;
}

export function login() {
  return `<h1>Connexion</h1>
<p>Pour mieux vous répondre, nous vous invitons à créer un compte et à vérifier l'adresse e-mail que nous avons de vous.</p>
<form action="mes-tickets.html" method="post" id="clientLogin">
<div style="display:table-row">
  <div class="login-box">
    <strong></strong>
    <div><label for="username" class="visually-hidden">Adresse e-mail ou identifiant</label><input id="username" placeholder="Adresse e-mail ou identifiant" type="text" name="luser" size="30" value="" class="nowarn" autocomplete="username"></div>
    <div><label for="passwd" class="visually-hidden">Mot de passe</label><input id="passwd" placeholder="Mot de passe" type="password" name="lpasswd" size="30" maxlength="128" value="" class="nowarn" autocomplete="current-password"></div>
    <p><input class="btn" type="submit" value="Se connecter"> <a style="padding-top:4px;display:inline-block;" href="#">Mot de passe oublié</a></p>
  </div>
  <div style="display:table-cell;padding: 15px;vertical-align:top">
    <div style="margin-bottom: 5px">Pas encore inscrit ? <a href="#">Créer un compte</a></div>
    <div><b>Je suis un agent</b> &mdash; <a href="#">connectez-vous ici</a></div>
  </div>
</div>
</form>
<br>
<h2 style="margin-top:2em">Suivre un ticket sans compte</h2>
<p>Indiquez votre adresse e-mail et le numéro du ticket. Un lien d'accès vous sera envoyé par e-mail.</p>
<form action="suivi-ticket.html" method="post" id="clientLogin2" class="clientLogin">
<div style="display:table-row">
  <div class="login-box">
    <div><label for="email">Adresse e-mail :<input id="email" placeholder="par exemple camille@exemple.fr" type="text" name="lemail" size="30" value="" class="nowarn"></label></div>
    <div><label for="ticketno">Numéro de ticket :<input id="ticketno" type="text" name="lticket" size="30" value="" class="nowarn" placeholder="482913"></label></div>
    <p><input class="btn" type="submit" value="Envoyer le lien d'accès"></p>
  </div>
  <div style="display:table-cell;padding: 15px;vertical-align:top">
    <p>Première demande, ou numéro de ticket perdu ? <a href="ouvrir-un-ticket.html">Ouvrez un nouveau ticket</a>.</p>
  </div>
</div>
</form>`;
}

function entry(e) {
  return `<div class="thread-entry ${e.type}">
  <div class="header">
    <div class="pull-right"><span class="textra"></span></div>
    <b>${e.who}</b> a écrit <time datetime="2026-10-14" title="${e.at}">${e.at}</time>
    <span class="faded title truncate">${e.type === 'message' && e === thread[0] ? esc(ticket.subject) : ''}</span>
  </div>
  <div class="thread-body" id="thread-id-${Math.abs(e.at.length * e.time.length)}"><div>${e.body.map((p) => (p.startsWith('<code>') ? `<pre>${p.replace(/<\/?code>/g, '')}</pre>` : `<p>${p}</p>`)).join('')}</div>
    <div class="clear"></div>
    ${fileChips(e.files)}
  </div>
</div>`;
}

export function ticketView() {
  return `<table width="800" cellpadding="1" cellspacing="0" border="0" id="ticketInfo">
<tr><td colspan="2" width="100%">
  <h1><a href="suivi-ticket.html" title="Recharger">${icon('refresh', 'ic refresh', 16)}</a> <b>${esc(ticket.subject)}</b> <small>#${ticket.number}</small>
  <div class="pull-right"><a class="action-button" href="#">${icon('print', 'ic', 15)} Imprimer</a> <a class="action-button" href="#">${icon('edit', 'ic', 15)} Modifier</a></div></h1>
</td></tr>
<tr>
  <td width="50%">
    <table class="infoTable" cellspacing="1" cellpadding="3" width="100%" border="0">
      <thead><tr><td class="headline" colspan="2">Informations sur le ticket</td></tr></thead>
      <tr><th width="100">Statut :</th><td><span class="status-chip open">Ouvert</span></td></tr>
      <tr><th>Service :</th><td>${ticket.dept}</td></tr>
      <tr><th>Création :</th><td>${ticket.created}</td></tr>
      <tr><th>Support :</th><td>Prioritaire, première réponse sous 4 h</td></tr>
    </table>
  </td>
  <td width="50%">
    <table class="infoTable" cellspacing="1" cellpadding="3" width="100%" border="0">
      <thead><tr><td class="headline" colspan="2">Vos coordonnées</td></tr></thead>
      <tr><th width="100">Nom :</th><td>${client.name}</td></tr>
      <tr><th width="100">E-mail :</th><td>${client.email}</td></tr>
      <tr><th>Téléphone :</th><td>${client.phone}</td></tr>
    </table>
  </td>
</tr>
<tr><td colspan="2">
  <table class="custom-data" cellspacing="0" cellpadding="4" width="100%" border="0">
    <tr><td colspan="2" class="headline flush-left">Détails de la demande</td></tr>
    ${ticket.custom.map(([k, v]) => `<tr><th>${k} :</th><td>${v}</td></tr>`).join('')}
  </table>
</td></tr>
</table>
<br>
<div id="ticketThread" class="thread-entries">
${thread.map(entry).join('\n')}
</div>
<div class="clear" style="padding-bottom:10px;"></div>
<form id="reply" action="suivi-ticket.html#reply" name="reply" method="post" enctype="multipart/form-data">
  <h2>Répondre</h2>
  <div>
    <p><em>Pour que nous puissions bien vous aider, soyez précis et détaillé.</em> <font class="error">*</font></p>
    ${redactor({ id: 'reply-message', label: 'Votre réponse', value: '', rows: 7 })}
    ${filedrop()}
  </div>
  <p style="text-align:center">
    <input type="submit" value="Envoyer la réponse"> <input type="reset" value="Réinitialiser"> <input type="button" value="Annuler">
  </p>
</form>`;
}

export function ticketsList() {
  return `<div class="search well">
  <div class="flush-left">
    <form action="mes-tickets.html" method="get" id="ticketSearchForm" role="search">
      <input type="hidden" name="a" value="search">
      <input type="text" name="keywords" size="30" value="" aria-label="Rechercher dans mes tickets">
      <input type="submit" value="Rechercher">
      <div class="pull-right">Thème d'aide :
        <select name="topic_id" class="nowarn" aria-label="Thème d'aide"><option>&mdash; Tous les thèmes &mdash;</option><option>Installation et compatibilité (1)</option><option>Heatlens (1)</option><option>Auditlens (1)</option><option>Licences et facturation (3)</option></select>
      </div>
    </form>
  </div>
</div>
<h1 style="margin:10px 0">
  <a href="mes-tickets.html">${icon('refresh', 'ic refresh', 16)} Tickets</a>
  <div class="pull-right states"><small>
    <a class="state active" href="#">${icon('file', 'ic', 14)} Ouverts (3)</a> &nbsp;<span style="color:lightgray">|</span>&nbsp;
    <a class="state" href="#">${icon('file', 'ic', 14)} Fermés (3)</a>
  </small></div>
</h1>
<table id="ticketTable" width="800" border="0" cellspacing="0" cellpadding="0">
  <caption>Affichage de 1 à 3 sur 3 tickets ouverts</caption>
  <thead><tr>
    <th nowrap><a href="#" title="Trier par numéro">N° de ticket ${icon('sort', 'ic', 12)}</a></th>
    <th width="120"><a href="#" title="Trier par date">Création ${icon('sort', 'ic', 12)}</a></th>
    <th width="100"><a href="#" title="Trier par statut">Statut ${icon('sort', 'ic', 12)}</a></th>
    <th width="320"><a href="#" title="Trier par sujet">Sujet ${icon('sort', 'ic', 12)}</a></th>
    <th width="120"><a href="#" title="Trier par service">Service ${icon('sort', 'ic', 12)}</a></th>
  </tr></thead>
  <tbody>
  ${tickets.filter((t) => t.status === 'Ouvert').map((t) => `<tr id="${t.n}">
    <td><a class="Icon webTicket" title="${client.email}" href="suivi-ticket.html">${t.answered ? `<b>${t.n}</b>` : t.n}</a></td>
    <td>${t.date}</td>
    <td><span class="status-chip open">${t.status}</span></td>
    <td><div class="link truncate"${''}>${t.answered ? `<b>${esc(t.subject)}</b>` : esc(t.subject)}</div></td>
    <td><span class="truncate">${t.dept}</span></td>
  </tr>`).join('')}
  </tbody>
</table>
<div>&nbsp;Page : <span id="pagination"><b>1</b></span>&nbsp;</div>`;
}

export function kb() {
  return `<div class="row">
<div class="span8">
  <div>Cliquez sur une catégorie pour parcourir ses questions.</div>
  <ul id="kb">
  ${categories.map((c) => `<li><i>${icon('folder', 'ic', 26)}</i>
    <div style="margin-left:45px">
      <h4><a href="base-de-connaissances.html">${c.name} (${c.count})</a></h4>
      <div class="faded" style="margin:10px 0">${c.desc}</div>
      ${c.faqs.map((f) => `<div class="popular-faq">${icon('file', 'ic icon-file-alt', 14)} <a href="base-de-connaissances.html">${f}</a></div>`).join('')}
    </div></li>`).join('')}
  </ul>
</div>
<div class="span4">
  <div class="sidebar">
    <div class="searchbar">
      <form method="get" action="base-de-connaissances.html" role="search">
        <input type="hidden" name="a" value="search">
        <input type="text" name="q" class="search" placeholder="Rechercher dans la base de connaissances" aria-label="Rechercher dans la base de connaissances">
        <select name="topicId" style="width:100%;max-width:100%" aria-label="Parcourir par thème"><option value="">&mdash; Parcourir par thème &mdash;</option>${topics.map((t) => `<option>${t}</option>`).join('')}</select>
      </form>
    </div>
    <br>
    <div class="content"><section><div class="header">Autres ressources</div>
      <div><a href="#">Politique de support</a></div><div><a href="#">Conditions de licence</a></div><div><a href="#">État du service</a></div>
    </section></div>
  </div>
</div>
</div>`;
}
