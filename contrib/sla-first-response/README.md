# SLA : mesurer la première réponse (issue #1)

Priority Support promet un **premier retour humain en moins de 4 h, 24 h/24 et 7 j/7**,
et une prolongation d'un mois si le délai est dépassé **plus de 3 fois par an**.
Ce dossier contient la réponse à l'issue #1 : analyse du code, requêtes SQL, plugin
et test d'intégration. **Le cœur d'osTicket n'est pas modifié.**

Contenu :

| Fichier | Rôle |
| --- | --- |
| `sql/premiere-reponse.sql` | Première réponse d'un agent par ticket Priority (minutes, respecté / dépassé) |
| `sql/depassements-par-organisation-annee.sql` | Comptage des dépassements par organisation et par année (ou par période d'abonnement) + recoupement avec l'événement `overdue` |
| `plugin/sla_first_response/` | Plugin : après la première réponse d'un agent, le ticket n'est plus jamais « en retard » |
| `test/` | Test d'intégration jetable (Docker) : `pwsh contrib/sla-first-response/test/run.ps1` |

## 1. Conclusion

- **La mesure** (première réponse, comptage des dépassements) est fiable **en SQL**, hors
  cœur. C'est la source de vérité pour la clause « plus de 3 fois ».
- **L'alerte « en retard » après la première réponse** : le seul contournement qui tient est
  le **plugin** fourni (signal `threadentry.created` : au premier envoi d'un agent, le ticket
  passe sur un plan de SLA *désactivé*, donc sans échéance). Le changement de statut et
  `updateEstDueDate()` **ne fonctionnent pas** (section 3).
- Il n'y a pas de besoin de modifier le cœur. Si un jour le plugin ne suffisait plus, la
  seule modification de cœur envisageable serait dans `Ticket::onResponse()`
  (`include/class.ticket.php:1800`) : à éviter, elle serait écrasée ou en conflit à chaque
  fusion de version.
- Les événements `overdue` **ne doivent pas servir de compteur** pour la clause (section 2).

## 2. Vérification des affirmations de l'issue

Chaque ligne a été vérifiée par lecture du code (fichier:ligne sur cette branche) ; celles
marquées *testé* l'ont aussi été par exécution (section 6).

| Affirmation | Verdict | Preuve |
| --- | --- | --- |
| Le SLA est une échéance de **résolution** : `checkOverdue()` marque « en retard » tout ticket ouvert dont l'échéance est passée | Confirmé, *testé* (S1) | `include/class.ticket.php:4723-4743` : filtre `isoverdue=0`, `status__state='open'`, `est_duedate < NOW()` (ou `duedate` manuelle), 100 tickets par passage |
| `onResponse()` ne fait que `isanswered=1` | Confirmé | `class.ticket.php:1800-1810` ; rien ne touche `est_duedate` ni `isoverdue` |
| Un ticket répondu en 1 h devient « en retard » à 4 h s'il reste ouvert | Confirmé, *testé* (S1) | idem |
| L'événement `overdue` est journalisé dans `thread_event` | Confirmé, *testé* | `class.ticket.php:2427-2445` (`logEvent('overdue')`) ; `ThreadEvents::log`, `include/class.thread.php:2343` |
| L'échéance est à +4 h exactes avec le calendrier 24/7 | Confirmé, *testé* (S0) | `class.ticket.php:546-562`, `include/class.sla.php:47-65` |
| Le délai de grâce est en heures entières, minimum 1 h | Confirmé | `include/class.sla.php:157-158` (0 refusé), colonne `grace_period int unsigned` |

Points que l'issue ne mentionne pas et qui comptent pour la promesse :

1. **Le calendrier du département l'emporte sur celui du plan de SLA**
   (`class.ticket.php:553-557`, `class.sla.php:51-54`), et un plan sans calendrier utilise le
   calendrier système par défaut, qui est « lundi-vendredi 8h-17h » à l'installation
   (`include/i18n/en_US/schedule.yaml:14`, `setup/inc/class.installer.php:258`). Pour du 24/7
   réel : le plan doit avoir le calendrier « 24/7 » **et** le département ne doit pas en avoir.
2. **Le marquage dépend du cron** : `Cron::TicketMonitor()` (`include/class.cron.php:28-34`),
   lancé par `api/cron.php` / `manage.php cron`, ou par l'« autocron » qui ne tourne que si un
   agent est connecté (`scp/autocron.php:54`, 3 minutes minimum). La nuit, sans cron système,
   rien n'est marqué ni alerté. L'horodatage de l'événement `overdue` est l'heure du cron, pas
   l'échéance.
3. **`ticket.created` est la date de création dans osTicket** (`class.ticket.php:4359`), pas la
   date d'envoi du client : pour une boîte relevée en POP/IMAP, le délai écoulé avant la relève
   n'est pas compté (préférer le pipe `api/pipe.php` ou une relève fréquente).
4. **Une réponse envoyée directement depuis la messagerie n'est pas une réponse osTicket** :
   seules `Ticket::postReply()` (`class.ticket.php:3345`, appelée par `scp/tickets.php:220`,
   l'ouverture de ticket par un agent `:4656`, et la réponse « canned » `:3299`) créent une
   entrée de type `R`. Toute réponse doit passer par le panneau agent.
5. **Une réponse « à personne »** (« Répondre à : aucun ») est enregistrée dans le fil sans
   e-mail (`postReply`, `getRecipients` renvoie `null`, `class.ticket.php:960-980`) : elle
   arrêterait le chronomètre sans que le client soit prévenu. Variante stricte en commentaire
   de `premiere-reponse.sql` (filtre sur `FLAG_REPLY_USER|FLAG_REPLY_ALL`).
6. **Création d'un plan de SLA directement « Désactivé »** : l'ORM n'enregistre pas `flags=0`
   sur un objet neuf (`null != 0` est faux, `include/class.orm.php:488`), la base garde le
   défaut `flags=3` (actif). *Testé.* Créer le plan, puis le rééditer pour le désactiver et
   vérifier « Disabled » dans la liste. Le plugin refuse un plan « réponse faite » encore actif.
7. **Les événements `overdue` ne conviennent pas comme compteur** : écrits seulement si le cron
   est passé avant la réponse (une réponse tardive mais avant le cron n'en laisse aucun) ;
   répétés pour un même ticket après `updateEstDueDate()`, réouverture ou modification
   (*testé*, S2b : 2 événements pour 1 ticket) ; déclenchés aussi par une « Due Date »
   manuelle. La requête 3 du fichier SQL les met en regard du comptage par première réponse.

## 3. Contournements « ne plus alerter après la première réponse »

Testés sur le papier **et** par exécution (instance osTicket du dépôt sur MariaDB, voir
section 6).

| Contournement | Résultat | Pourquoi |
| --- | --- | --- |
| **Changement de statut**, statut personnalisé d'état `open` (ex. « En attente client ») | Ne marche pas | `checkOverdue()` ne regarde que l'état (`status__state='open'`, `class.ticket.php:4727`), pas le statut. Testé : toujours « en retard ». Et `reply_status_id` (`:3371`) dépend de l'agent. |
| **Changement de statut** vers un état `closed` | Efface le retard, mais inacceptable | `setStatus()` appelle `clearOverdue(false)` (`:1531`), mais ferme le ticket ; la réponse suivante du client le rouvre et ouvre une **nouvelle** fenêtre de 4 h (`:1563`, base = date de réouverture). |
| **`updateEstDueDate()`** après la réponse | Ne marche pas | Il efface le drapeau puis **recalcule la même échéance** (création + 4 h, déjà passée) (`:564-571`, `:546-562`). Le cron suivant re-marque le ticket et **écrit un 2e événement `overdue`**. Testé (S2, S2b). Idem à chaque modification de ticket (`:3803`, `:3891`). |
| **Annuler/effacer `est_duedate` en base** (SQL, script, déclencheur) | Fragile | Recalculée à chaque `updateEstDueDate()` : création (`:4538`), réouverture (`:1563`), modification du ticket (`:3803`), champ SLA/échéance (`:3891`). Un script périodique laisse une fenêtre de course avec le cron d'osTicket. |
| **Signal `cron`** (script/plugin) | Trop tard | Envoyé à la fin de `Cron::run()` (`class.cron.php:122`), après `TicketMonitor()` (`:30`). |
| **Plugin sur `threadentry.created`** : au premier envoi d'un agent, bascule sur un plan de SLA désactivé | **Marche** (testé) | `ThreadEntry::create()` envoie le signal (`include/class.thread.php:1821`) à chaque entrée créée. Un plan inactif n'a pas d'échéance : `getSLADueDate()` renvoie `null` (`class.ticket.php:552`), `est_duedate` devient `null` et `checkOverdue()` ne retient plus le ticket. Il reste `null` après `updateEstDueDate()`, fermeture/réouverture, modification, transfert (le plan n'est pas transitoire, `:2690`, `:3793`). |

Signaux réellement émis sur le chemin d'une réponse d'agent (`Ticket::postReply()`) :

| Signal | Où | Utilisable ? |
| --- | --- | --- |
| `threadentry.created` (objet : `ThreadEntry`) | `class.thread.php:1821`, avant `onResponse()` | **Oui, retenu** : on connaît le type (`R`), l'agent, `FLAG_SYSTEM`, le fil et le ticket. Absent de `setup/doc/signals.md` (non documenté). |
| `model.created` / `model.updated` / `object.edited` | `include/class.orm.php:680-688` | Trop bavard, sans type d'entrée. |
| `object.created` avec `type=message` | `class.ticket.php:3389` | Après la réponse, mais identique à `postMessage` (`:3267`) à la clé `uid` près : ne distingue pas réponse et message. |
| `ticket.created`, `ticket.create.before`, `ticket.create.validated` | `class.ticket.php:4089, 4259, 4585` | Création uniquement. |

Il n'existe **aucun** signal dédié « réponse d'agent » ni « ticket en retard ».

### Risques lors des mises à jour d'osTicket

- Le plugin dépend du signal **non documenté** `threadentry.created` et de quelques méthodes
  (`setSLAId`, `updateEstDueDate`, `logEvent`, `isSystem`, `ThreadEntry::FLAG_*`). Rejouer
  `test/run.ps1` après chaque mise à jour : il échoue si l'un d'eux change.
- Le plan « réponse faite » doit rester **désactivé et non transitoire** : sinon osTicket le
  remplace ou recalcule une échéance. Supprimer ce plan renvoie ses tickets sur le plan par
  défaut (`class.sla.php:232-234`), qui peut avoir une échéance de 18 h.
- Les requêtes SQL dépendent des tables `ticket`, `thread`, `thread_entry`, `thread_event`,
  `event`, `sla`, `user`, `organization` : à revérifier si une migration touche ces tables.
- Les tickets déjà répondus avant l'installation du plugin ne sont pas rattrapés.
- Image Docker immuable (branche `feature/docker-production`) : le plugin ne s'installe pas
  depuis l'interface ; le placer dans `deploy/docker/overlay/include/plugins/sla_first_response/`
  et reconstruire. **Non vérifié** (je n'ai pas construit cette image).

## 4. Réponses automatiques : décision proposée

**Elles n'arrêtent pas le chronomètre.** Seule compte la réponse publiée par un **agent**
(entrée de type `R`, `staff_id > 0`) dans le fil du ticket.

Ce que le code fait réellement :

- L'accusé de réception à la création (`Ticket::onNewTicket`, `class.ticket.php:1646-1790`) et
  la réponse automatique à un nouveau message (`onMessage`, `:1889`) sont **uniquement des
  e-mails** : aucune entrée dans le fil, aucun effet sur `isanswered`, l'échéance ou le SLA. Ils
  ne peuvent donc jamais être pris pour une première réponse.
- La réponse « canned » posée par un **filtre** (`postCannedReply`, `:3272`) est une entrée de
  type `R`, signée `SYSTEM (Canned Reply)`, sans agent (`staff_id=0`) et marquée `FLAG_SYSTEM`
  (`class.thread.php:1702`). Le cœur la remet à « sans réponse » (`markUnAnswered`, `:4555`).
  *Testé.* Le plugin et le SQL l'écartent. Réserve : créée depuis le panneau agent, elle porte
  l'identifiant de l'agent ; elle est alors écartée par son auteur `SYSTEM…` (comparaison avec
  le texte traduit côté plugin ; `LIKE 'SYSTEM%'` côté SQL, valable tant que ce texte n'est pas
  traduit autrement).

Autres choix à faire dans la politique : voir les questions de la section 7.

### Texte de clause proposé (à faire valider)

> **Délai de première réponse (Priority Support).** Pour toute demande enregistrée par le
> portail de support au titre de l'offre Priority Support, Coderise s'engage à apporter un
> premier retour d'un membre de son équipe en moins de quatre (4) heures, vingt-quatre heures
> sur vingt-quatre et sept jours sur sept.
>
> Le délai court de l'enregistrement de la demande par le portail jusqu'à la publication de la
> première réponse d'un membre de l'équipe dans la demande. Les messages automatiques
> (accusé de réception, réponse automatique, notifications) et les notes internes ne mettent
> pas fin au délai. Le délai concerne le premier retour, pas la résolution de la demande.
>
> Si ce délai est dépassé plus de trois (3) fois au cours d'une période d'abonnement de douze
> mois, l'abonnement est prolongé d'un (1) mois, sans frais. Les dépassements sont ceux
> enregistrés par le portail de support ; le client peut en demander le relevé.

Points laissés à votre arbitrage : « plus de 3 fois » est compté ici comme **4 dépassements ou
plus** ; « moins de 4 h » comme **dépassement au-delà de 4 h 00 min 00 s** ; la demande n'est
« enregistrée » qu'une fois créée dans osTicket (voir 2.3).

## 5. Mise en place

1. **Plans de SLA** (Admin > Manage > SLA Plans) :
   - `Priority 4h` : Actif, délai de grâce 4 h, calendrier **24/7**, non transitoire.
   - `Priority 4h - 1re reponse faite` : mêmes réglages, mais **Désactivé** (créer, puis
     rééditer pour le désactiver, section 2.6), non transitoire.
2. **Départements** concernés : aucun calendrier (section 2.1).
3. **Filtre d'expéditeur** : action « Set SLA Plan » = `Priority 4h` (le cœur applique alors
   `slaId` à la création, `include/class.filter_action.php:416`, `class.ticket.php:4504`).
4. **Cron système** toutes les 5 minutes au plus (`php manage.php cron` ou `api/cron.php`),
   sinon l'alerte « en retard » ne part pas la nuit.
5. **Plugin** : copier `plugin/sla_first_response/` dans `include/plugins/sla_first_response/`,
   puis Admin > Manage > Plugins > Add New Plugin > Install, activer, ajouter l'instance avec
   les deux identifiants de plan (visibles dans l'URL `slas.php?id=…`). La configuration
   refuse deux plans identiques, un plan inexistant, un plan « réponse faite » actif ou
   transitoire.
6. **SQL** : adapter le préfixe `ost_` et les noms de plans (un seul endroit par requête), puis
   l'exécuter en lecture seule sur la base (testé sur MariaDB 10.11 et MySQL 8.0).

## 6. Vérifié / non vérifié

Vérifié par exécution (instance osTicket du dépôt, schéma d'installation, MariaDB 10.11, PHP
8.2 CLI en conteneur ; `test/run.ps1`, une cinquantaine de contrôles) :

- constats de l'issue (S0, S1) et échec des contournements par statut et `updateEstDueDate`
  (S2, S2b, S3) ;
- plugin : bascule du plan à la première réponse d'agent, pas d'échéance ni de retard ensuite
  (cron à +5 h, fermeture/réouverture, `updateEstDueDate`), réponse tardive (le retard déjà
  marqué est effacé, l'événement historique reste), pas de bascule pour réponse « canned » de
  filtre, message client, note interne, plan hors périmètre ;
- configuration du plugin : refus d'une configuration invalide ;
- SQL : les trois requêtes s'exécutent sur MariaDB 10.11 et MySQL 8.0 ; résultats cohérents
  avec les données de test (organisations, années, période d'abonnement).
- Suite de tests statiques du dépôt (`php setup/test/run-tests.php`, PHP 8.2 en conteneur) avec
  ce dossier inclus : 0 échec, code de sortie 0. Deux échecs initiaux, dus à mes fichiers
  (méthode d'instance citée avec `::` dans un commentaire, `$GLOBALS` dans le test), ont été
  corrigés. Les contrôles `jslint` ne tournent pas (binaire `jsl` absent du conteneur) ; ils ne
  concernent pas du JavaScript de ce dossier.

**Non vérifié** :

- aucun essai avec un vrai flux (relève POP/IMAP, e-mail sortant, filtre configuré dans
  l'interface, réponse depuis l'interface web) : les réponses ont été postées par appel direct
  à `Ticket::postReply()` en CLI, l'« envoi » étant désactivé ;
- le test n'a pas été rejoué sur l'image Docker de production, ni sur MySQL < 8 / MariaDB
  autre que 10.11 ;
- aucun essai en conditions de charge ni de concurrence (deux réponses simultanées) ;
- le comportement de la relève des messages (`created` vs date d'envoi) est lu dans le code,
  pas mesuré ;
- l'interface d'administration (écrans plugin et plans de SLA) n'a pas été ouverte dans un
  navigateur : l'installation du plugin a été faite par les mêmes appels que l'interface.

## 7. Questions ouvertes

1. « Abonnement » = **organisation** osTicket ? Sinon, comment rattacher un ticket à un
   abonnement (domaine, utilisateur, champ de formulaire) ?
2. « Par an » = année civile ou **période d'abonnement** (date anniversaire) ? Les dates
   d'abonnement ne sont pas dans osTicket (requête 2 : paramètres à fournir à la main).
3. Les tickets **ouverts par un agent** pour le compte du client (téléphone) comptent-ils ?
   Une réponse jointe à la création donne 0 minute.
4. Une réponse **sans e-mail au client** (« à personne ») arrête-t-elle le chronomètre ?
   (Variante stricte fournie.)
5. Après une **réouverture**, y a-t-il un nouveau délai de 4 h ? Avec le plugin : non (le
   plan « réponse faite » est sans échéance). Le comportement du cœur seul serait : oui.
6. Qui est **alerté** la nuit quand un ticket Priority dépasse 4 h (alerte e-mail du
   département, `class.ticket.php:2117`) ? Le cœur n'alerte qu'**après** le dépassement.
7. Les tickets Priority déjà répondus avant l'installation : faut-il les rattraper ?

## 8. Relancer la suite de tests du dépôt

```bash
php setup/test/run-tests.php
```

(`php` n'est pas dans le PATH de la machine de développement : j'ai utilisé l'image
`php:8.2-cli`. Les tests `jslint` échouent à trouver `jsl` hors de leur environnement ; sans
rapport avec ce dossier.)
