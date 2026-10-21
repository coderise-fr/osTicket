# SLA : mesurer la première réponse (issue #1)

Priority Support promet un **premier retour humain en moins de 4 h, 24 h/24 et 7 j/7**,
et une prolongation d'un mois si le délai est dépassé **plus de 3 fois (soit 4 fois ou plus)
par période d'abonnement**.
Ce dossier contient la réponse à l'issue #1 : analyse du code, requêtes SQL, plugin
et test d'intégration. **Le cœur d'osTicket n'est pas modifié.**

Contenu :

| Fichier | Rôle |
| --- | --- |
| `sql/premiere-reponse.sql` | Première réponse d'un agent **au client** par ticket Priority (minutes, respecté / dépassé). Contient la définition unique de « première réponse » |
| `sql/depassements-par-client-periode.sql` | Comptage des dépassements **par client et par période d'abonnement à date anniversaire**, signalement des prolongations dues, tickets « sans abonnement connu », recoupement avec l'événement `overdue` |
| `plugin/sla_first_response/` | Plugin : après la première réponse adressée au client, le ticket n'est plus jamais « en retard » |
| `test/` | Test d'intégration jetable (Docker), MariaDB et MySQL : `pwsh contrib/sla-first-response/test/run.ps1` |

## 0. Décisions prises (à refléter dans la clause)

| Sujet | Décision |
| --- | --- |
| Tickets ouverts par un agent pour le client (téléphone) | **Comptent.** Une réponse jointe à la création donne 0 minute (si elle est envoyée au client, voir 4.2) |
| « Plus de 3 fois » | **4 dépassements ou plus** sur la période |
| Dépassement | **Strictement plus de 4 h 00 min 00 s** entre la création du ticket et la première réponse (4 h 00 min 00 s exactement = respecté), ou aucune réponse au bout de 4 h |
| Réponse qui arrête le chronomètre | Seule une réponse **effectivement adressée au client** (4.2). Une réponse enregistrée sans e-mail ne l'arrête pas |
| Compte des dépassements | **Par client** (utilisateur osTicket) **et par période d'abonnement à date anniversaire**, jamais par organisation ni par année civile |
| Après réouverture d'un ticket | **Pas de nouveau délai de 4 h** (comportement du plugin : le plan « réponse faite » est sans échéance) |
| Tickets Priority avant l'installation du plugin | **Il n'y en aura pas** : aucun rattrapage à prévoir |
| Qui est alerté la nuit quand 4 h sont dépassées | **Décision reportée** (question ouverte n° 1, section 7) |

## 1. Conclusion

- **La mesure** (première réponse, comptage des dépassements) est fiable **en SQL**, hors
  cœur. C'est la source de vérité pour la clause « 4 dépassements ou plus ».
- **L'alerte « en retard » après la première réponse** : le seul contournement qui tient est
  le **plugin** fourni (signal `threadentry.created` : à la première réponse d'un agent
  adressée au client, le ticket passe sur un plan de SLA *désactivé*, donc sans échéance). Le
  changement de statut et `updateEstDueDate()` **ne fonctionnent pas** (section 3).
- **Le critère « réponse adressée au client »** est la liste des destinataires que
  `Ticket::postReply()` enregistre dans `thread_entry.recipients` (section 4.2). Le plugin et
  le SQL utilisent exactement la même définition.
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
5. **Une réponse « à personne »** (« Répondre à : Ne pas envoyer d'e-mail ») est enregistrée
   dans le fil sans e-mail (`postReply`, `getRecipients` renvoie `null`,
   `class.ticket.php:960-980`). **Décision : elle n'arrête pas le chronomètre** ; le critère
   et ses limites sont au 4.2. *Testé.*
6. **Création d'un plan de SLA directement « Désactivé »** : l'ORM n'enregistre pas `flags=0`
   sur un objet neuf (`null != 0` est faux, `include/class.orm.php:488`), la base garde le
   défaut `flags=3` (actif). *Testé.* Créer le plan, puis le rééditer pour le désactiver et
   vérifier « Disabled » dans la liste. Le plugin refuse un plan « réponse faite » encore actif.
7. **Les événements `overdue` ne conviennent pas comme compteur** : écrits seulement si le cron
   est passé avant la réponse (une réponse tardive mais avant le cron n'en laisse aucun) ;
   répétés pour un même ticket après `updateEstDueDate()`, réouverture ou modification
   (*testé*, S2b : 2 événements pour 1 ticket) ; déclenchés aussi par une « Due Date »
   manuelle. La requête B du fichier SQL les met en regard du comptage par première réponse.

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
| **Plugin sur `threadentry.created`** : à la première réponse adressée au client, bascule sur un plan de SLA désactivé | **Marche** (testé) | `ThreadEntry::create()` envoie le signal (`include/class.thread.php:1821`) à chaque entrée créée. Un plan inactif n'a pas d'échéance : `getSLADueDate()` renvoie `null` (`class.ticket.php:552`), `est_duedate` devient `null` et `checkOverdue()` ne retient plus le ticket. Il reste `null` après `updateEstDueDate()`, fermeture/réouverture, modification, transfert (le plan n'est pas transitoire, `:2690`, `:3793`). |

Signaux réellement émis sur le chemin d'une réponse d'agent (`Ticket::postReply()`) :

| Signal | Où | Utilisable ? |
| --- | --- | --- |
| `threadentry.created` (objet : `ThreadEntry`) | `class.thread.php:1821`, avant `onResponse()` | **Oui, retenu** : on connaît le type (`R`), l'agent, `FLAG_SYSTEM`, la liste des destinataires (`recipients`), le fil et le ticket. Absent de `setup/doc/signals.md` (non documenté). |
| `model.created` / `model.updated` / `object.edited` | `include/class.orm.php:680-688` | Trop bavard, sans type d'entrée. |
| `object.created` avec `type=message` | `class.ticket.php:3389` | Après la réponse, mais identique à `postMessage` (`:3267`) à la clé `uid` près : ne distingue pas réponse et message. |
| `ticket.created`, `ticket.create.before`, `ticket.create.validated` | `class.ticket.php:4089, 4259, 4585` | Création uniquement. |

Il n'existe **aucun** signal dédié « réponse d'agent » ni « ticket en retard ».

### Risques lors des mises à jour d'osTicket

- Le plugin dépend du signal **non documenté** `threadentry.created`, de quelques méthodes
  (`setSLAId`, `updateEstDueDate`, `logEvent`, `isSystem`, `ThreadEntry::FLAG_*`) et du format
  JSON de `thread_entry.recipients` (clés `to` / `cc`). Rejouer `test/run.ps1` après chaque
  mise à jour : il échoue si l'un d'eux change.
- Le plan « réponse faite » doit rester **désactivé et non transitoire** : sinon osTicket le
  remplace ou recalcule une échéance. Supprimer ce plan renvoie ses tickets sur le plan par
  défaut (`class.sla.php:232-234`), qui peut avoir une échéance de 18 h.
- Les requêtes SQL dépendent des tables `ticket`, `thread`, `thread_entry`, `thread_event`,
  `event`, `sla`, `user`, `user_email`, `organization`, `config` : à revérifier si une
  migration touche ces tables.
- Image Docker immuable (branche `feature/docker-production`) : le plugin ne s'installe pas
  depuis l'interface ; le placer dans `deploy/docker/overlay/include/plugins/sla_first_response/`
  et reconstruire. **Non vérifié** (je n'ai pas construit cette image).

## 4. Réponses qui arrêtent le chronomètre

### 4.1 Réponses automatiques : décision proposée

**Elles n'arrêtent pas le chronomètre.** Seule compte la réponse publiée par un **agent**
(entrée de type `R`, `staff_id > 0`) dans le fil du ticket, et adressée au client (4.2).

Ce que le code fait réellement :

- L'accusé de réception à la création (`Ticket::onNewTicket`, `class.ticket.php:1646-1790`) et
  la réponse automatique à un nouveau message (`onMessage`, `:1889`) sont **uniquement des
  e-mails** : aucune entrée dans le fil, aucun effet sur `isanswered`, l'échéance ou le SLA. Ils
  ne peuvent donc jamais être pris pour une première réponse.
- La réponse « canned » posée par un **filtre** (`postCannedReply`, `:3272`) est une entrée de
  type `R`, signée `SYSTEM (Canned Reply)`, sans agent (`staff_id=0`), marquée `FLAG_SYSTEM`
  (`class.thread.php:1702`) et sans liste de destinataires. Le cœur la remet à « sans réponse »
  (`markUnAnswered`, `:4555`). *Testé.* Le plugin et le SQL l'écartent. Créée depuis le panneau
  agent, elle porte l'identifiant de l'agent ; elle est alors écartée par son auteur `SYSTEM…`
  (comparaison avec le texte traduit côté plugin ; `LIKE 'SYSTEM%'` côté SQL, valable tant que
  ce texte n'est pas traduit autrement) et, de toute façon, par l'absence de destinataire.

### 4.2 Critère « réponse effectivement adressée au client »

**Définition retenue** (identique dans `plugin/.../sla_first_response.php`, méthode
`isClientReply()`, et dans les deux fichiers SQL) : une entrée du fil est la première réponse si

| Condition | Colonne / test |
| --- | --- |
| c'est une réponse | `type = 'R'` |
| d'un agent | `staff_id > 0` |
| ni système ni masquée | `(flags & (0x80 \| 0x04)) = 0`, auteur ne commençant pas par `SYSTEM` |
| **le client est destinataire** | `recipients LIKE '%"to":%'` en SQL ; `json_decode(recipients)['to']` non vide dans le plugin |

**Pourquoi ce critère** (lecture de `Ticket::postReply`, `ThreadEntry::create`,
`Ticket::getRecipients`, `MailingList::getEmailAddresses`) :

- Avant d'enregistrer la réponse, `postReply()` calcule la liste des destinataires avec
  `getRecipients($vars['reply-to'], $vars['ccs'])` (`class.ticket.php:3361-3363`). Selon le
  choix « Répondre à » de l'agent : `user` = le propriétaire seul dans `to` ; `all` = le
  propriétaire dans `to`, les collaborateurs actifs dans `cc` ; `collabs` = collaborateurs dans
  `cc` seulement ; `none` ou toute autre valeur = `null`, pas de liste, pas d'e-mail
  (`class.ticket.php:960-982`).
- `ThreadEntry::create()` (`class.thread.php:1680-1692`) stocke cette liste en JSON dans
  `thread_entry.recipients` (`{"to":{"<id>":"Nom <adresse>"},"cc":{...}}`) **et** pose
  `FLAG_REPLY_USER` (un destinataire) ou `FLAG_REPLY_ALL` (plusieurs). Sans liste : colonne
  `NULL`, aucun des deux indicateurs.
- Le propriétaire du ticket (le client) n'est jamais ailleurs que dans `to` ; les
  collaborateurs sont toujours dans `cc`. Donc « `to` non vide » équivaut à « le client est
  destinataire ».

**Critères écartés** :

- Les **indicateurs** `FLAG_REPLY_USER` / `FLAG_REPLY_ALL` (variante stricte de la version
  précédente) : posés aussi quand seuls des collaborateurs sont destinataires (`collabs` ou un
  `ccs` sans propriétaire). *Testé* : indicateur `0x200` présent pour une réponse aux
  collaborateurs seuls. Ils ne suffisent donc pas.
- **`thread_entry_email`** : osTicket n'y enregistre que les e-mails **entrants**
  (`ThreadEntry::saveEmailInfo`, qui porte « TODO: Refactor it to include outgoing emails on
  responses », `class.thread.php:1308-1322`). Aucune ligne n'existe pour une réponse d'agent.
- **Le journal d'envoi** n'existe pas : osTicket ne garde aucune trace de remise.

**Comportement par cas** (tous *testés* sauf mention) :

| Cas | Arrête le chronomètre ? |
| --- | --- |
| « Propriétaire » (`user`) ou « Tous les destinataires actifs » (`all`) | **Oui** |
| « Ne pas envoyer d'e-mail » (`none`) | Non. La réponse suivante, avec e-mail, l'arrête (le délai court alors depuis la création) |
| Collaborateurs seuls (`collabs`) avec collaborateurs actifs | **Non** (choix prudent, voir ci-dessous) |
| `collabs` sans collaborateur actif | Non (aucune liste) |
| Ticket ouvert par un agent, réponse jointe, choix « Alerter » = tous / utilisateur | **Oui, 0 minute** |
| Ticket ouvert par un agent, choix = « Ne pas envoyer d'alerte » | Non |
| Ticket ouvert par un agent alors que « Nouveau ticket par un agent » n'est pas coché dans Admin > Paramètres > Alertes et avis | **Non** : le formulaire n'a alors pas le choix « Répondre à », `postReply()` ne reçoit pas de destinataire (`class.ticket.php:3361`, `include/staff/ticket-open.inc.php:181`). **Cocher cette option** pour que les tickets ouverts par téléphone comptent avec 0 minute |
| Réponse « canned » de filtre, note interne, message du client | Non |

**Cas où le critère n'est pas fiable, et choix retenu :**

1. **Collaborateurs seuls** : l'interface agent ne propose pas ce choix (liste : tous / propriétaire /
   aucun, `include/staff/ticket-view.inc.php:975-990`), seul un script ou une requête forgée
   peut le produire. Choix prudent : **ne pas arrêter** le chronomètre, le client n'est pas
   prévenu. Cela peut au pire ajouter un dépassement en faveur du client, jamais en
   supprimer un.
2. **Remise réelle de l'e-mail** : la liste enregistrée est l'**intention** d'osTicket au
   moment de la réponse. Elle ne prouve pas la remise : échec du serveur SMTP, modèle de
   réponse ou adresse d'expédition du département manquants (`class.ticket.php:3432-3436`,
   l'e-mail n'est alors pas envoyé sans erreur visible), adresse invalide ou rejetée. Une
   réponse enregistrée avec le client dans `to` compte même si le message n'est pas arrivé.
   Surveiller les journaux de messagerie ; cette limite est à connaître dans la clause (« publiée
   dans la demande et adressée au client »).
3. **Adresse du propriétaire modifiée après coup** : le JSON garde l'adresse d'origine ;
   sans effet sur le critère (on teste la présence de `to`, pas l'adresse).
4. **Anciens tickets** : les entrées créées avant l'ajout de la colonne `recipients`
   (versions d'osTicket antérieures) n'ont pas de liste. Sans objet ici (pas de ticket Priority
   avant l'installation du plugin, décision 0).
5. **Test par `LIKE`** plutôt que `JSON_*` : volontaire, une valeur illisible ferait échouer
   les fonctions JSON de MySQL pour toute la requête. Le motif `"to":` ne peut pas apparaître à
   l'intérieur d'un nom (les guillemets y sont échappés).

### 4.3 Texte de clause proposé (à faire valider)

> **Délai de première réponse (Priority Support).** Pour toute demande enregistrée par le
> portail de support au titre de l'offre Priority Support, y compris une demande saisie par un
> membre de l'équipe pour le compte du client (par exemple à la suite d'un appel téléphonique),
> Coderise s'engage à apporter un premier retour d'un membre de son équipe en moins de quatre
> (4) heures, vingt-quatre heures sur vingt-quatre et sept jours sur sept.
>
> Le délai court de l'enregistrement de la demande par le portail jusqu'à la publication, dans
> la demande, de la première réponse d'un membre de l'équipe adressée au client. Les messages
> automatiques (accusé de réception, réponse automatique, notifications), les notes internes et
> les réponses enregistrées sans envoi au client ne mettent pas fin au délai. Le délai est
> dépassé lorsque cette première réponse intervient plus de quatre (4) heures, soit plus de
> 4 h 00 min 00 s, après l'enregistrement de la demande, ou n'est pas intervenue à l'issue de
> ce délai. Il concerne le premier retour, pas la résolution de la demande. La réouverture
> d'une demande ne fait pas courir un nouveau délai.
>
> Si le délai est dépassé à quatre (4) reprises ou plus (c'est-à-dire plus de trois fois) au
> cours d'une même période d'abonnement de douze mois, comptée à partir de la date de
> démarrage de l'abonnement et de chacune de ses dates anniversaires, l'abonnement est prolongé
> d'un (1) mois, sans frais. Les dépassements sont comptés pour chaque client et pour chaque
> période, d'après les données du portail de support ; le client peut en demander le relevé.

Points laissés à votre arbitrage : la demande n'est « enregistrée » qu'une fois créée dans
osTicket (voir 2.3) ; le 29 février (section 5, « Calcul », point 2) ; la prolongation d'un abonnement déjà résilié.

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
5. **Alertes et avis** (Admin > Paramètres > Alertes et avis) : cocher « Nouveau ticket par
   un agent : propriétaire du ticket », sinon la réponse jointe à un ticket ouvert par un agent
   n'est pas adressée au client et ne compte pas (4.2).
6. **Plugin** : copier `plugin/sla_first_response/` dans `include/plugins/sla_first_response/`,
   puis Admin > Manage > Plugins > Add New Plugin > Install, activer, ajouter l'instance avec
   les deux identifiants de plan (visibles dans l'URL `slas.php?id=…`). La configuration
   refuse deux plans identiques, un plan inexistant, un plan « réponse faite » actif ou
   transitoire.
7. **SQL** : adapter le préfixe `ost_` et les noms de plans (un seul endroit par requête), puis
   les exécuter en lecture seule sur la base (testé sur MariaDB 10.11 et MySQL 8.0). Exécuter
   avec le même fuseau de session que osTicket (voir « Fuseau » ci-dessous).

### Comptage des dépassements : `depassements-par-client-periode.sql`

Pourquoi **par client** : tous les clients Priority sont dans une seule organisation
(« Priority »), compter par organisation les mélangerait. Le compte se fait par utilisateur
osTicket (`ticket.user_id`) et par période d'abonnement.

**Entrée (contrat avec `contrib/stripe-sync`)** : une ligne par abonnement Priority dans la
table `ost_config` :

| Colonne | Valeur |
| --- | --- |
| `namespace` | `stripesync` |
| `key` | `sub:<subscription_id>` |
| `value` | `<user_id osTicket>\|<start_date>\|<ended>\|<status>` |

`start_date` = epoch UTC (secondes) du tout premier démarrage de l'abonnement ; `ended` =
epoch UTC de la fin, ou chaîne vide si en cours ; `status` = statut Stripe. Pas de JSON,
séparateur `\|`. La requête découpe la valeur avec `SUBSTRING_INDEX` ; une ligne qui ne
respecte pas ce format (`^[0-9]+\|[0-9]+\|[0-9]*\|[^\|]*$`) est ignorée pour le calcul et
listée en section 4 du résultat.

**Calcul** :

1. Pour chaque abonnement, périodes `[début + n ans ; début + (n+1) ans[`, `n` = 0, 1, 2…,
   calculées en **UTC à la seconde près** avec `DATE_ADD(début, INTERVAL n YEAR)` (les bornes
   viennent toujours de la date de début, jamais d'une période à la suivante : une période
   finit exactement où la suivante commence). Si l'abonnement est terminé, la dernière période
   est coupée à la date de fin (exclue) ; il n'y a pas de période après.
2. **29 février** : pour un abonnement démarré un 29 février, l'anniversaire est le **28
   février** les années non bissextiles (comportement de `DATE_ADD` de MySQL et MariaDB) et le
   29 les années bissextiles. Exemple (testé) : début le 29/02/2024 12:00 UTC, périodes
   29/02/2024 - 28/02/2025, 28/02/2025 - 28/02/2026, …, 28/02/2027 - 29/02/2028, 29/02/2028 -
   28/02/2029. Un ticket du 28/02/2025 à 11:59:59 est dans la période 0, à 12:00:00 dans la
   période 1. À arbitrer si le contrat doit dire autre chose (1er mars, par exemple).
3. Chaque ticket Priority (reconnu par son plan de SLA, comme `premiere-reponse.sql`) est
   rattaché au client `ticket.user_id` et à la période **de ce client** qui contient sa date
   de création. Deux abonnements du même client qui se chevauchent : le plus récent l'emporte
   (aucun ticket n'est compté deux fois).
4. Un ticket est un **dépassement** si sa première réponse (définition 4.2) arrive **strictement
   plus de 4 h 00 min 00 s** après sa création, ou s'il n'a pas de réponse et que 4 h sont
   écoulées.
5. Par client et par période : nombre de tickets, dépassements, numéros des tickets en
   dépassement, `prolongation_due` = 1 si **4 dépassements ou plus**. Les dépassements ne se
   cumulent jamais d'une période à l'autre (2 + 2 sur deux périodes = aucune prolongation).
6. Le résultat est un seul tableau avec une colonne `section` : `1` par client et période,
   `2` prolongations dues (ligne dédiée), `3` **sans abonnement connu** (client sans ligne
   `sub:`, ou ticket créé avant le début ou à partir de la fin de ses abonnements : jamais
   perdu), `4` lignes `sub:` illisibles. La requête B (événements `overdue`) est à titre
   d'information.

**Fuseau** : les dates d'osTicket sont dans le fuseau de la base. La conversion en UTC
(`UNIX_TIMESTAMP`) se fait avec le fuseau de la **session** qui exécute la requête : utiliser
le même que osTicket (par défaut celui du serveur). Les dates du résultat sont en UTC.

**Limites** :

- Un ticket est attribué à l'utilisateur osTicket qui en est propriétaire. Un collègue du
  client qui écrit depuis une autre adresse est un **autre utilisateur** : son ticket va en
  section 3 (« aucune ligne `sub:` pour ce client »), pas sur l'abonnement du payeur. Le plugin
  Stripe demande au client d'écrire depuis l'adresse de son compte ; à confirmer pour les
  sociétés avec plusieurs interlocuteurs.
- Le statut Stripe n'est pas utilisé pour borner les périodes : seule la date de fin
  (`ended`) le fait. Un abonnement `past_due` ou en résiliation programmée dont `ended` est
  vide reste en cours.
- Une période **en cours** (`periode_en_cours` = 1) peut encore évoluer. Un abonnement
  terminé peut atteindre le seuil : prolonger un abonnement résilié est à décider à la main.
- Vingt-neuf ans d'anniversaires au maximum par abonnement (table `nombres`).

## 6. Vérifié / non vérifié

Vérifié par exécution (instance osTicket du dépôt, schéma d'installation, PHP 8.2 CLI en
conteneur ; `test/run.ps1`, 164 contrôles par moteur) sur **MariaDB 10.11** et sur **MySQL 8.0**
(`-MariaDbImage mysql:8.0`), tous passés :

- constats de l'issue (S0, S1) et échec des contournements par statut et `updateEstDueDate`
  (S2, S2b, S3) ;
- plugin : bascule du plan à la première réponse adressée au client, pas d'échéance ni de
  retard ensuite (cron à +5 h, fermeture/réouverture, `updateEstDueDate`), réponse tardive (le
  retard déjà marqué est effacé, l'événement historique reste), pas de bascule pour réponse
  « canned » de filtre, message client, note interne, plan hors périmètre ;
- **critère de réponse au client** : réponse avec e-mail (propriétaire, tous les destinataires)
  arrête ; réponse sans e-mail, deux de suite, ou collaborateurs seuls (avec ou sans
  collaborateur) n'arrêtent pas, le ticket passe « en retard » au cron suivant ; la réponse
  suivante avec e-mail arrête et efface le retard ; contenu de `recipients` et des indicateurs
  relu en base ; ticket ouvert par un agent (`Ticket::open`) avec réponse jointe envoyée au
  client = 0 minute et bascule du plan, avec « Ne pas envoyer d'alerte » ou sans choix de
  destinataire = pas de première réponse ;
- configuration du plugin : refus d'une configuration invalide ;
- SQL : 32 tickets Priority factices aux dates fixées et 9 lignes `sub:` factices valides, plus une illisible et deux hors périmètre (dates,
  abonnement résilié puis repris, deux périodes, client sans abonnement, démarrage un 29
  février, chevauchement, ligne illisible, lignes d'un autre espace de noms) : bornes à la
  seconde près (3 h 59 min 59 s et 4 h 00 min 00 s respectés, 4 h 00 min 01 s dépassé),
  périodes, seuil (3 = non, 4 = oui, 2 + 2 sur deux périodes = non), sections 1 à 4, aucun
  ticket perdu ni compté deux fois, cohérence plugin <-> SQL sur les 41 tickets de la phase 2
  et 3 ;
- test de sensibilité : en neutralisant le critère (plugin et SQL), 14 contrôles échouent ;
- suite de tests statiques du dépôt (`php setup/test/run-tests.php`, PHP 8.2 en conteneur) avec
  ce dossier inclus : code de sortie 0. Les contrôles `jslint` ne tournent pas (binaire `jsl`
  absent du conteneur) ; ils ne concernent pas du JavaScript de ce dossier.

**Non vérifié** :

- aucun essai avec un vrai flux (relève POP/IMAP, e-mail sortant réellement remis, filtre
  configuré dans l'interface, réponse depuis l'interface web) : les réponses ont été postées par
  appel direct à `Ticket::postReply()` / `Ticket::open()` en CLI, l'« envoi » étant désactivé
  pour `postReply` ; la lecture de `scp/tickets.php` et de `ticket-view.inc.php` sur les valeurs
  de « Répondre à » est faite dans le code, pas dans un navigateur ;
- la remise effective des e-mails (4.2, cas 2) ;
- aucun vrai flux Stripe : les lignes `sub:` sont des fixtures conformes au format annoncé ; le
  plugin `contrib/stripe-sync` n'a pas été lancé avec cette requête ;
- le test n'a pas été rejoué sur l'image Docker de production, ni sur MySQL < 8 / MariaDB
  autre que 10.11 ; ni avec un fuseau de base différent d'UTC (les fixtures supposent
  `NOW() = UTC_TIMESTAMP()`, vérifié au début du test) ;
- aucun essai en conditions de charge ni de concurrence (deux réponses simultanées) ;
- le comportement de la relève des messages (`created` vs date d'envoi) est lu dans le code,
  pas mesuré ;
- l'interface d'administration (écrans plugin et plans de SLA) n'a pas été ouverte dans un
  navigateur : l'installation du plugin a été faite par les mêmes appels que l'interface.

## 7. Questions ouvertes

1. Qui est **alerté** la nuit quand un ticket Priority dépasse 4 h (alerte e-mail du
   département, `class.ticket.php:2117`) ? Le cœur n'alerte qu'**après** le dépassement.
   *Décision reportée.*
2. Le 29 février : l'anniversaire d'un abonnement démarré un 29 février tombe le 28 février
   les années non bissextiles. Acceptable, ou 1er mars ?
3. Un collègue du client (autre adresse e-mail) qui ouvre un ticket : doit-il compter pour
   l'abonnement du payeur ? Aujourd'hui non (limites, section 5).
4. Prolongation d'un abonnement **déjà résilié** qui atteint 4 dépassements : due ou sans objet ?
5. Faut-il surveiller la remise des e-mails de réponse (journal du serveur SMTP) pour
   que « adressée au client » soit aussi « remise » ?

Questions tranchées (section 0) : tickets ouverts par un agent, « plus de 3 fois » = 4 ou
plus, dépassement strict, réponse sans e-mail, nouveau délai après réouverture (non), tickets
Priority avant le plugin (aucun, pas de rattrapage), compte par client et par date anniversaire.

## 8. Relancer les tests

```bash
# Intégration : une fois par moteur (Docker ; noms de conteneurs uniques, supprimés à la fin)
pwsh contrib/sla-first-response/test/run.ps1                          # MariaDB 10.11
pwsh contrib/sla-first-response/test/run.ps1 -MariaDbImage mysql:8.0   # MySQL 8.0

# Suite statique du dépôt
php setup/test/run-tests.php
```

(`php` n'est pas dans le PATH de la machine de développement : j'ai utilisé l'image
`php:8.2-cli`. Les tests `jslint` échouent à trouver `jsl` hors de leur environnement ; sans
rapport avec ce dossier. Ils n'influencent pas le code de sortie.)
