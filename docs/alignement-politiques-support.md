# Alignement des politiques de support sur osTicket (issue #5)

État au 02/11/2026. Les politiques à modifier sont dans le dépôt `matomo-heatlens`
(`plugins/Heatlens/SUPPORT-POLICY.txt`, `plugins/Auditlens/SUPPORT-POLICY.txt`, `websites/*/support.html`, conditions de
vente) : ce document ne les modifie pas. Il dit **ce qui peut être écrit sans mentir, ce qui ne le peut pas encore**, et
propose les formulations. Ce sont des textes contractuels : à valider par Julien et la revue juridique (questions Q13 à Q17,
Q22 du dépôt `matomo-heatlens`) avant toute publication.

## 1. Les quatre engagements actuels, face à la production

| Engagement publié | Ce que livre osTicket aujourd'hui | Verdict |
| --- | --- | --- |
| « Les demandes passent par le portail `support.coderise.fr`, accès **fourni avec la licence** » | Le portail est en ligne. Le plugin `stripe-sync` 0.2 doit créer un compte portail pour chaque client qui paie, mais c'est un **prototype jamais éprouvé avec un vrai événement Stripe** et sans webhook configuré. Aujourd'hui l'inscription au portail est **publique** (`client_registration=public`), sans lien avec une licence. | **Non tenable** tant que Stripe n'a pas été éprouvé de bout en bout |
| « Première réponse sous **4 h, 24 h/24 et 7 j/7** » (Priority) | Plan SLA `Priority 4h` (calendrier 24/7) et plugin `SLA : première réponse` 0.4 actifs : après la première réponse adressée au client, le ticket n'est plus « en retard ». La mesure fiable est en SQL (`contrib/sla-first-response/sql/`). **Mais aucun e-mail ne part** (SMTP inactif) : une première réponse par e-mail n'arriverait pas. Et **personne n'est alerté la nuit** (question ouverte n° 1 du dossier SLA). | **Mesurable, pas encore tenable** : l'alerte de nuit n'existe pas, l'envoi non plus |
| « Délai **enregistré par le portail** » | L'horodatage de création du ticket (`ost_ticket.created`) est enregistré par osTicket ; la première réponse aussi. La formule est celle du plugin et du SQL. | **Tenable** (à reformuler : « enregistré par notre outil de support ») |
| « Plus de 3 retards dans l'année → 1 mois offert » (Auditlens seulement) | Compte par **client** et par **période d'abonnement à date anniversaire** (pas par année civile, pas par organisation) ; « plus de 3 » = **4 ou plus** ; dépassement = strictement plus de 4 h 00 min 00 s ; la période d'abonnement vient de Stripe. | **Mesurable en SQL**, mais Heatlens ne contient aucun remède (décision juridique Q14 ouverte) |
| « Repli sur `contact@coderise.fr` tant que le portail n'est pas disponible » | La réception d'e-mails n'est pas configurée (Microsoft 365, pas d'IMAP). | **À conserver** tant que la réception n'est pas en production |

## 2. Ce qui bloque la réécriture

1. **Messagerie** : envoi (Brevo) et réception (Microsoft 365) non opérationnels. Toute promesse de réponse repose dessus.
2. **Stripe sync** : à éprouver avec le mode test de Stripe (clé de webhook, destination, abonnement Priority de test),
   puis en production. Sans cela, « accès fourni avec la licence » est faux.
3. **Alerte de nuit** : qui est prévenu quand 4 h approchent la nuit, le week-end et les jours fériés ? Décision reportée
   (dossier SLA, question 1). Avec un seul agent, c'est la contrainte principale de l'engagement 24/7.
4. **Remède** (Heatlens et Auditlens) : identique ou non, et son déclencheur exact. Décision juridique.
5. **Fuseau et jours fériés** du support inclus : Europe/Paris est réglé ; les jours fériés français sont à vérifier dans le
   calendrier `Horaires bureau`.

## 3. Formulations proposées (à valider)

Ces textes ne changent aucune promesse chiffrée : ils retirent seulement ce qui n'est pas vérifiable aujourd'hui.

**Accès au portail** (à publier après la recette Stripe)
- FR : « Vous recevez un accès au portail de support dès que votre licence est active. »
- EN : "You receive access to the support portal once your license is active."
- Provisoirement, sans promesse automatique : « Pour ouvrir un compte sur le portail, écrivez à `contact@coderise.fr` depuis
  l'adresse de votre commande. »

**Horloge de 4 h** (remplace « enregistré par le portail »)
- FR : « Le délai de 4 heures court à partir de l'instant où votre ticket est enregistré dans notre outil de support (portail
  ou e-mail), tel qu'horodaté par cet outil. »
- EN: "The 4 hours start when your ticket is recorded in our support tool (portal or email), as time-stamped by that tool."

**Première réponse** (inchangée) : « Une réponse d'une personne ayant lu le ticket ; l'accusé automatique ne compte pas. »

**Remède** (Auditlens, à étendre à Heatlens après décision) : conserver « plus de 3 », à lire comme **4 ou plus** par
**période d'abonnement**, par licence ; exclusions : information insuffisante, force majeure, panne du portail signalée.
Ajouter la phrase : « Le comptage est fait par Coderise à partir des horodatages de l'outil de support. »

**Repli** : conserver le texte actuel (`contact@coderise.fr`, depuis l'adresse de la commande) jusqu'à ce que la réception
par le portail et l'e-mail `support@coderise.fr` soit validée, puis le retirer dans toutes les langues et fiches de vente.

## 4. À faire après décision, dans `matomo-heatlens`

- [ ] Réécrire les politiques Heatlens et Auditlens en EN et FR ainsi que les fiches de vente et conditions de vente.
- [ ] `npm run legal:sync` côté Heatlens, pour resynchroniser les empreintes juridiques.
- [ ] Retirer la mention de repli quand le portail et la messagerie sont en production.
- [ ] Prévoir la politique de Reportlens et des produits suivants.

Aucun de ces changements n'a été fait : ils relèvent du dépôt `matomo-heatlens`, de sa revue juridique et de Julien.
