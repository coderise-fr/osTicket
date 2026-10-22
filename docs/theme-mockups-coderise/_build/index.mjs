// Genere index.html (comparaison cote a cote) : node docs/theme-mockups-coderise/_build/index.mjs
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PAGES, fr, icon } from './common.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const P = [
  {
    dir: '01-atelier', n: '01', name: 'Atelier', reco: false,
    line: 'Chaleureux : une personne vous répond. Avatars, bulles de conversation, boutons-pastilles, frise d\'ascension.',
    who: 'Clients non techniques (commerce, direction) à rassurer.', level: 'Niveau 2', days: '5 j',
    risks: ['Bulles et avatars : réécriture du gabarit de fil (thread-entry), les classes d\'osTicket ne suffisent pas.', 'Frise d\'ascension : « pris en charge » et « correctif en test » ne sont pas des états du portail (statuts personnalisés ou dérivés des réponses, à coder).', 'Barre d\'état sur navy et pastilles : beaucoup de bleu nuit dans les bulles de l\'équipe, à vérifier sur de longs fils.'],
  },
  {
    dir: '02-poste-de-travail', n: '02', name: 'Poste de travail', reco: false,
    line: 'Dense : rail bleu nuit, tableaux serrés, panneau de propriétés, articles suggérés pendant la saisie.',
    who: 'Clients qui ouvrent beaucoup de tickets : équipes techniques, revendeurs.', level: 'Niveau 2', days: '6 j',
    risks: ['Rail latéral : le gabarit d\'osTicket est une colonne unique de 840 px, tout le gabarit de page change.', 'Tableau de bord (compteurs, délai moyen, contrat) : données absentes du portail, à coder ou à retirer.', 'Densité : lisibilité sur mobile et en accessibilité à surveiller (corps 14,5 px).'],
  },
  {
    dir: '03-sommet', n: '03', name: 'Sommet', reco: true,
    line: 'Éditorial : le bleu nuit domine, grande typographie, chevron cyan en cadrage, altimètre du délai dans le bandeau.',
    who: 'Décideurs et chefs de projet : marque forte, peu de tickets, lecture confortable.', level: 'Niveau 1 étendu', days: '4 j',
    risks: ['Les bandeaux sombres demandent d\'ouvrir un conteneur dans l\'en-tête pour y placer le titre de page.', 'Altimètre du délai : donnée non native (voir README).', 'Beaucoup de bleu nuit : à tempérer si le public attend un portail « clair » ; le cyan doit rester un signal.'],
  },
  {
    dir: '04-parcours', n: '04', name: 'Parcours', reco: false,
    line: 'Guidé : choisir, décrire, suivre. Étapes numérotées, « prochain pas », altimètre, bibliothèque en parcours.',
    who: 'Clients qui hésitent sur quoi écrire ; équipes qui veulent des tickets mieux remplis.', level: 'Niveau 2', days: '7 j',
    risks: ['Assistant en quatre étapes : osTicket sert un formulaire unique, l\'étapage est visuel et la progression en direct demande du JavaScript.', '« Prochain pas », regroupement « À vous de jouer » et progression 4 étapes : données dérivées à coder.', 'Parcours de la bibliothèque : l\'ordre des articles n\'existe pas dans le modèle.'],
  },
  {
    dir: '05-referentiel', n: '05', name: 'Référentiel', reco: false,
    line: 'Documentation d\'abord : arborescence, recherche en en-tête, fil de ticket façon suivi d\'incident, étiquettes.',
    who: 'Développeurs et DSI côté client, habitués aux outils de suivi.', level: 'Niveau 2', days: '6 j',
    risks: ['Arborescence de la base : sous-catégories limitées, sommaire « sur cette page » à générer.', 'Étiquettes (priorité, environnement, impact) : issues des champs du formulaire, à mapper.', 'Ton plus froid : moins rassurant pour un public non technique.'],
  },
];

const cell = (p, pg, kind = 'bureau') => `<a class="cell" href="${p.dir}/${pg.file}" aria-label="${p.name} : ${pg.label}"><img class="thumb ${kind}" src="${p.dir}/captures/${pg.shot}-${kind}.png" alt="Capture ${kind} : ${p.name}, ${pg.label}" width="${kind === 'bureau' ? 1280 : 390}"></a>`;

const html = fr(`<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maquettes du portail Coderise | Comparaison</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#000F26">
<link rel="icon" href="_shared/img/favicon-64.png" type="image/png">
<link rel="stylesheet" href="_shared/fonts.css">
<link rel="stylesheet" href="_shared/tokens.css">
<link rel="stylesheet" href="_shared/compare.css">
</head>
<body class="compare">
<a class="skip" href="#content">Aller au contenu</a>
<header class="c-head on-dark"><div class="c-wrap c-head-in"><img class="logo" src="_shared/img/logo-horizontal-sombre.png" width="2041" height="345" alt="Coderise"><nav aria-label="Sections"><a href="#propositions">Propositions</a><a href="#cote-a-cote">Côte à côte</a><a href="#mobile">Mobile</a><a href="#cout">Coût et risques</a><a href="README.md">README</a></nav></div></header>
<main id="content">
<section class="c-intro"><div class="c-wrap"><h1>Cinq maquettes du portail de support Coderise</h1>
<p class="lead">Charte graphique v1.0 (juin 2026), polices Montserrat et Inter, logos officiels détourés sans retouche. Rien n'est développé dans osTicket : ce sont des pages statiques, en français, avec des données fictives. Chaque maquette montre dix pages, un état vide et un formulaire avec erreurs.</p>
<ul class="legend"><li><strong>Niveau 1</strong> : feuille de style, logo, en-tête, pied de page.</li><li><strong>Niveau 2</strong> : gabarits réécrits.</li><li><strong>Support standard</strong> : jours ouvrés. <strong>Priority</strong> : premier retour sous 4 h, 24 h/24.</li></ul></div></section>

<section class="c-wrap" id="propositions"><h2>Les cinq propositions</h2>
<ol class="props">${P.map((p) => `<li class="prop${p.reco ? ' reco' : ''}"><a class="shot" href="${p.dir}/index.html"><img src="${p.dir}/captures/accueil-bureau.png" alt="Accueil de la proposition ${p.name}" width="1280"></a>
<div class="body"><h3><span class="num">${p.n}</span> ${p.name}${p.reco ? ' <span class="badge">Recommandée en première phase</span>' : ''}</h3><p>${p.line}</p><p class="who"><strong>Pour qui :</strong> ${p.who}</p>
<p class="cost"><span class="pill">${p.level}</span><span class="pill">${p.days} environ</span></p>
<details><summary>Risques</summary><ul>${p.risks.map((r) => `<li>${r}</li>`).join('')}</ul></details>
<p class="links">${PAGES.map((pg) => `<a href="${p.dir}/${pg.file}">${pg.label.replace(/ \(.*\)/, '')}</a>`).join('')}</p></div></li>`).join('')}</ol></section>

<section class="c-wrap" id="cote-a-cote"><h2>Côte à côte, page par page</h2><p class="muted">Bureau 1280 px, haut de page. Cliquez une capture pour ouvrir la page.</p>
<div class="matrix" role="table" aria-label="Comparaison page par page"><div class="mrow mhead" role="row">${P.map((p) => `<div role="columnheader"><strong>${p.n}</strong> ${p.name}</div>`).join('')}</div>
${PAGES.map((pg) => `<h3 class="mtitle">${pg.label}</h3><div class="mrow" role="row">${P.map((p) => `<div role="cell">${cell(p, pg)}</div>`).join('')}</div>`).join('')}</div></section>

<section class="c-wrap" id="mobile"><h2>Mobile, 390 px</h2><p class="muted">Aucun débordement horizontal mesuré. Accueil, ouverture de ticket, suivi d'un ticket.</p>
<div class="matrix">${['home', 'open', 'ticket'].map((k) => { const pg = PAGES.find((x) => x.key === k); return `<h3 class="mtitle">${pg.label}</h3><div class="mrow m390" role="row">${P.map((p) => `<div role="cell">${cell(p, pg, 'mobile')}</div>`).join('')}</div>`; }).join('')}</div></section>

<section class="c-wrap" id="cout"><h2>Coût et risques</h2>
<div class="table-wrap"><table class="data"><caption>Estimation d'ordre de grandeur, hors anglais et recette sur l'instance de test</caption><thead><tr><th scope="col">Proposition</th><th scope="col">Niveau</th><th scope="col">Durée</th><th scope="col">Développement hors cœur requis</th></tr></thead><tbody>
<tr><th scope="row">01 Atelier</th><td>2</td><td>5 j</td><td>Frise d'avancement, bulles</td></tr>
<tr><th scope="row">02 Poste de travail</th><td>2</td><td>6 j</td><td>Rail, tableau de bord, articles suggérés</td></tr>
<tr><th scope="row">03 Sommet</th><td>1 étendu</td><td>4 j</td><td>Bandeaux de titre, altimètre</td></tr>
<tr><th scope="row">04 Parcours</th><td>2</td><td>7 j</td><td>Assistant, prochain pas, groupement, parcours</td></tr>
<tr><th scope="row">05 Référentiel</th><td>2</td><td>6 j</td><td>Arborescence, sommaire, étiquettes</td></tr></tbody></table></div>
<p class="muted">Commun aux cinq : l'indicateur de délai Priority (premier retour sous 4 h) n'est pas affiché par le portail client d'osTicket ; il demande un petit développement (lecture du plan de SLA et de la première réponse de l'équipe). Voir le README.</p></section>
</main>
<footer class="c-foot on-dark"><div class="c-wrap"><p>Maquettes statiques, sans JavaScript ni requête réseau. Coordonnées de l'entreprise : contact@coderise.fr · +33 (0)6 69 93 92 86 · Aix-en-Provence.</p></div></footer>
</body>
</html>
`);

writeFileSync(join(root, 'index.html'), html);
console.log('index.html');
