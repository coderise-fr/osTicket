// Genere docs/theme-mockups/index.html (comparaison cote a cote) : node docs/theme-mockups/_build/index.mjs
import { writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { proposals, pages } from './meta.mjs';
import { fr, esc } from './common.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const card = (p) => `<article class="card" aria-labelledby="t${p.id}">
  <header>
    <p class="num">${p.id}</p>
    <div><h2 id="t${p.id}">${p.name}</h2><p class="tag">${p.tagline}</p></div>
  </header>
  <ul class="sw" aria-label="Couleurs dominantes">${p.swatches.map((c) => `<li style="background:${c}" title="${c}"><span class="visually-hidden">${c}</span></li>`).join('')}</ul>
  <a class="hero-shot" href="${p.dir}/index.html"><img src="${p.dir}/captures/accueil-bureau.png" alt="Accueil de la proposition ${p.id}, ${esc(p.name)}, sur ordinateur"></a>
  <div class="thumbs">
    <a href="${p.dir}/suivi-ticket.html"><img src="${p.dir}/captures/suivi-ticket-bureau.png" alt="Suivi d'un ticket, proposition ${p.id}"><span>Suivi</span></a>
    <a href="${p.dir}/mes-tickets.html"><img src="${p.dir}/captures/mes-tickets-bureau.png" alt="Liste des tickets, proposition ${p.id}"><span>Liste</span></a>
    <a class="m" href="${p.dir}/index.html"><img src="${p.dir}/captures/accueil-mobile.png" alt="Accueil sur mobile, proposition ${p.id}"><span>Mobile</span></a>
  </div>
  <p class="intent">${p.intention}</p>
  <dl class="facts">
    <div><dt>Pour qui</dt><dd>${p.pour}</dd></div>
    <div><dt>Coût</dt><dd><span class="lvl lvl${p.niveau}">Niveau ${p.niveau}</span> ${p.cout.replace(/^Niveau \d : /, '')}</dd></div>
  </dl>
  <details><summary>Risques (${p.risques.length})</summary><ul>${p.risques.map((r) => `<li>${r}</li>`).join('')}</ul></details>
  <nav aria-label="Pages de la proposition ${p.id}"><ul class="pg">${pages.map(([f, l]) => `<li><a href="${p.dir}/${f}">${l}</a></li>`).join('')}</ul></nav>
</article>`;

const html = fr(`<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Portail client Ownalytics : 5 maquettes</title>
<meta name="robots" content="noindex">
<link rel="icon" href="_shared/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="_shared/fonts.css">
<link rel="stylesheet" href="_shared/compare.css">
</head>
<body>
<a class="skip" href="#main">Aller au contenu</a>
<header class="top">
  <div class="wrap">
    <p class="brand"><svg width="20" height="26" viewBox="0 0 20 26" aria-hidden="true"><path d="M1 1h18v21l-3 3-3-3-3 3-3-3-3 3-3-3z" fill="#FBFBF6"/><path d="M5 7h10M5 11h6M5 15h10" stroke="#3B1E54" stroke-width="2"/></svg><span>Ownalytics</span></p>
    <h1>Portail client : cinq directions à choisir</h1>
    <p class="lede">Maquettes statiques du portail osTicket aux couleurs d'Ownalytics (issue n° 4). Mêmes pages, mêmes champs, mêmes états dans chaque proposition ; données fictives. Rien n'est encore développé dans osTicket.</p>
    <p class="how">Cliquez une capture pour ouvrir la maquette, réelle et responsive. Niveau 1 : CSS, logo, en-tête, pied de page. Niveau 2 : gabarits réécrits.</p>
  </div>
</header>
<main id="main" class="wrap">
  <section class="cards" aria-label="Les cinq propositions">
${proposals.map(card).join('\n')}
  </section>

  <section class="cmp" aria-labelledby="cmp-t">
    <h2 id="cmp-t">Coût et risque en un coup d'œil</h2>
    <div class="tw"><table>
      <thead><tr><th scope="col">Proposition</th><th scope="col">Niveau</th><th scope="col">Effort estimé</th><th scope="col">Risque sur le suivi de ticket</th><th scope="col">Risque sur les tableaux et Redactor</th></tr></thead>
      <tbody>
        <tr><th scope="row">01 Ticket de caisse</th><td>1</td><td>2 j</td><td>Moyen : tableaux d'info neutralisés par CSS</td><td>Moyen : balisage conservé, thread.css à contrer</td></tr>
        <tr><th scope="row">02 Console</th><td>1</td><td>2 j</td><td>Moyen : grid sur #ticketInfo</td><td>Moyen : balisage conservé</td></tr>
        <tr><th scope="row">03 Atelier</th><td>2</td><td>5 j</td><td>Élevé : bulles, frise d'avancement hors modèle</td><td>Faible à moyen : gabarits réécrits, #dynamic-form à préserver</td></tr>
        <tr><th scope="row">04 Poste de travail</th><td>2</td><td>6 j</td><td>Élevé : panneau latéral, compteurs absents du portail</td><td>Faible à moyen : idem</td></tr>
        <tr><th scope="row">05 Une</th><td>2</td><td>6 j</td><td>Moyen à élevé : fil « correspondance », titres XXL</td><td>Faible à moyen : idem</td></tr>
      </tbody>
    </table></div>
    <p class="note">Les durées sont des ordres de grandeur (hypothèse de l'auteur, à confirmer), hors anglais et tests sur l'instance. Le README détaille la charte relevée, la recommandation et les limites.</p>
  </section>
</main>
<footer class="bot"><div class="wrap"><p>Maquettes de travail, non publiées. Polices Archivo, Bricolage Grotesque et IBM Plex Mono sous licence SIL OFL 1.1, copiées localement.</p></div></footer>
</body>
</html>
`);

writeFileSync(join(root, 'index.html'), html);
console.log('index.html');
