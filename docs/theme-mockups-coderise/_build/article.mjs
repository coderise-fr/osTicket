// Article de la base de connaissances, partage par les 5 propositions.
import { team } from './common.mjs';

export const article = {
  title: 'Signaler un incident en production : la marche à suivre',
  cat: 'Premiers pas avec le support',
  read: '5 min',
  updated: '12/10/2026',
  author: team[1],
  lead: 'Un incident en production se traite vite quand le ticket contient les bonnes informations dès le premier message. Voici ce qu\'il faut préciser, dans cet ordre, et ce qui se passe ensuite.',
  toc: [['environnement', 'Préciser l\'environnement et l\'impact'], ['joindre', 'Joindre les preuves'], ['delais', 'Savoir à quoi vous attendre'], ['apres', 'Ce qui se passe ensuite']],
};

export function articleProse() {
  return `<p class="lead">${article.lead}</p>
<h2 id="environnement">Préciser l'environnement et l'impact</h2>
<p>Choisissez le sujet d'aide <strong>Incident en production</strong> : les champs utiles apparaissent. Renseignez trois informations, elles font gagner le plus de temps.</p>
<ol class="steps">
<li><strong>L'environnement.</strong> Production, recette ou développement. Sans cette précision, nous ne savons pas où chercher.</li>
<li><strong>L'impact.</strong> Bloquant, dégradé ou mineur, et le nombre d'utilisateurs touchés.</li>
<li><strong>Le début de l'incident.</strong> Date et heure, même approximatives, ainsi que la dernière version livrée.</li>
</ol>
<aside class="callout" role="note"><p><strong>Bloquant ?</strong> Passez la priorité à Urgente. Avec le support Priority, le premier retour arrive sous 4 h, 24 h/24.</p></aside>
<h2 id="joindre">Joindre les preuves</h2>
<p>Une capture d'écran et un extrait de journal valent dix phrases. Retirez les données personnelles avant l'envoi. Pour une API, ajoutez la requête qui échoue :</p>
<pre><code>curl -i https://api.exemple.fr/v2/commandes?magasin=aix \\
  -H "Authorization: Bearer &lt;jeton&gt;"</code></pre>
<h2 id="delais">Savoir à quoi vous attendre</h2>
<table class="data">
<caption>Premier retour selon votre contrat</caption>
<thead><tr><th scope="col">Critère</th><th scope="col">Support standard</th><th scope="col">Support Priority</th></tr></thead>
<tbody>
<tr><th scope="row">Premier retour</th><td>Jours ouvrés, sans délai garanti</td><td>Sous 4 h</td></tr>
<tr><th scope="row">Jours couverts</th><td>Jours ouvrés</td><td>24 h/24, 7 j/7</td></tr>
<tr><th scope="row">Priorité « Urgente »</th><td>Non disponible</td><td>Disponible</td></tr>
</tbody></table>
<p class="note-small">Les horaires de l'entreprise (lundi au vendredi, 8h30 à 17h00) ne sont pas ceux du support Priority.</p>
<h2 id="apres">Ce qui se passe ensuite</h2>
<p>Vous recevez un e-mail de confirmation avec le numéro du ticket. Une personne de l'équipe reproduit l'incident, vous indique la cause probable et propose un contournement ou un correctif. Chaque réponse arrive par e-mail et sur le portail ; vous pouvez répondre aux deux endroits.</p>`;
}

// Articles proposes sous le texte
export const related = [
  'Que mettre dans un ticket pour être aidé plus vite',
  'Support standard ou Priority : lequel choisir',
  'Lire le journal de déploiement d\'une livraison',
];
