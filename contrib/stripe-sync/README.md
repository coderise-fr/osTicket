# Stripe sync : plugin osTicket (prototype)

Reçoit les webhooks Stripe, donne un compte portail aux clients qui paient et range dans
l'organisation « Priority » ceux qui ont un abonnement Priority Support actif. Conception,
comparaison des alternatives et analyse du code : [`docs/stripe-integration.md`](../../docs/stripe-integration.md)
(issue #2).

Statut : **prototype**. La logique est testée ; aucun vrai événement Stripe ni l'image Docker
durcie n'ont été utilisés (voir le §9 du document de conception).

## Contenu

| Fichier | Rôle |
|---|---|
| `plugin.php`, `stripe-sync.php` | Manifeste du plugin, formulaire d'administration, route `POST /api/stripe-sync/webhook` (signal `api`) |
| `include/class.stripesync.signature.php` | Vérification de `Stripe-Signature` (HMAC-SHA256, tolérance, rotation) |
| `include/class.stripesync.mapper.php` | Événement Stripe → fait normalisé ; règle « abonnement Priority » |
| `include/class.stripesync.service.php` | Règles métier (idempotence, ordre, restauration de l'organisation) |
| `include/class.stripesync.store.php` | État, idempotence, lignes `sub:` et `priority_org_id` exposées (voir plus bas) dans `ost_config` (espace `stripesync`), verrous |
| `include/class.stripesync.priorityorg.php` | Quelle organisation est « Priority » : identifiant mémorisé, puis nom, puis création (version 0.2) |
| `include/class.stripesync.directory.php` | **Seul** fichier qui touche aux classes d'osTicket |
| `include/class.stripesync.settings.php` | Réglages et secrets (environnement uniquement pour les secrets) |
| `tests/run.php` | Tests unitaires, sans osTicket ni base |
| `tests/integration/` | Test d'intégration Docker contre un vrai osTicket + MariaDB |

Aucun fichier du cœur d'osTicket n'est modifié.

## Installation

1. Copier ce dossier, **sans `tests/`**, vers `include/plugins/stripe-sync/` (avec l'image
   Docker durcie : `deploy/docker/overlay/include/plugins/stripe-sync/`, puis reconstruire).
2. Admin > Gérer > Plugins : installer « Stripe sync », ajouter une instance, l'activer.
3. Définir le secret du webhook dans l'environnement du serveur web (jamais dans le dépôt) :

| Variable | Rôle | Défaut |
|---|---|---|
| `STRIPE_SYNC_WEBHOOK_SECRET` | Secret de signature de la destination webhook (`whsec_…`) | obligatoire |
| `STRIPE_SYNC_WEBHOOK_SECRET_FILE` | Alternative : chemin d'un fichier contenant le secret (hors racine web ; avec l'image durcie, sous `/etc/osticket/`) | |
| `STRIPE_SYNC_PRIORITY_PRICE_IDS` | Ids de prix Priority, séparés par des virgules | formulaire du plugin |
| `STRIPE_SYNC_PRIORITY_PRODUCT_IDS` | Ids de produit Priority (optionnel) | formulaire du plugin |
| `STRIPE_SYNC_PRIORITY_ORG` | Nom de l'organisation, pour la retrouver ou la créer la **première fois** (ensuite, elle est retrouvée par son identifiant mémorisé : voir « Organisation Priority ») | `Priority` |
| `STRIPE_SYNC_ACTIVE_STATUSES` | Statuts d'abonnement qui gardent Priority (`past_due` n'en fait pas partie : voir « Limites connues ») | `active, trialing` |
| `STRIPE_SYNC_ACCEPT_TEST_EVENTS` | `1` pour accepter les événements du mode test (recette seulement) | refusés |
| `STRIPE_SYNC_INVITE_RESEND_DELAY` | Secondes minimum entre deux invitations à un même client | `21600` |

4. Dans Stripe, créer une destination webhook vers `https://<support>/api/stripe-sync/webhook`
   avec : `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`,
   `invoice.paid`, `invoice.payment_failed`. Au niveau du proxy, n'autoriser que les
   [adresses IP de Stripe](https://docs.stripe.com/ips) sur cette route.
5. Créer le plan SLA et le filtre de tickets « Organisation / Nom = Priority » (voir le §6 du document de conception).

## Organisation Priority (version 0.2 : par identifiant)

Version 0.1 : l'organisation était cherchée **par nom** à chaque événement ; renommée, elle
n'était plus trouvée et le plugin en créait une autre, sans que le niveau de contrat affiché par
les thèmes (`coderise_contract_level()`, plugin `sla_first_response`, qui la connaît par
identifiant) le reflète. Depuis la version 0.2, son **identifiant** est mémorisé dans
`ost_config` (espace `stripesync`, clé `priority_org_id`) et utilisé en priorité.

Ordre de résolution (`StripeSyncPriorityOrgResolver::resolve()`, à chaque événement qui
rattache ou détache un client) :

1. **identifiant mémorisé** (`priority_org_id`), si l'organisation existe et que le nom configuré
   n'a pas changé depuis sa mémorisation (`priority_org_name`, clé interne) : chemin normal, une
   lecture, aucune écriture. Une organisation **renommée** dans l'administration est donc toujours
   trouvée et aucune autre n'est créée ;
2. sinon le **nom configuré** (`STRIPE_SYNC_PRIORITY_ORG`, puis le champ « Priority
   organization », puis `Priority`) : premier usage, **migration** d'une installation 0.1 (aucun
   identifiant mémorisé), organisation mémorisée **supprimée**, ou nom configuré **modifié** par
   l'administrateur (le plugin suit alors volontairement le nouveau nom) ;
3. sinon **création** de l'organisation avec ce nom (tous les drapeaux de partage à zéro, aucun
   domaine). Jamais lors d'un détachement (fin d'abonnement).

L'identifiant trouvé en 2 ou 3 est mémorisé. Les étapes 2 et 3 sont sérialisées par un verrou
MySQL/MariaDB (`GET_LOCK`) : des premiers abonnements simultanés ne créent qu'**une**
organisation (testé, 6 requêtes en parallèle).

Journal système (identifiants numériques seulement, jamais de nom de client ni d'adresse) :

| Cas | Niveau | Titre |
|---|---|---|
| Identifiant mémorisé pour la première fois (création ou migration) | info | `Stripe sync: Priority organization remembered` |
| Organisation mémorisée **supprimée** : remplacée (retrouvée par nom ou recréée) | **avertissement** | `Stripe sync: Priority organization replaced` (ancien et nouvel identifiant) |
| Nom configuré modifié par l'administrateur | info | `Stripe sync: Priority organization changed` |

Le refus d'une organisation **non sûre** (partage de tickets, collaborateurs automatiques,
domaine mappé : réponse 503, Stripe rejoue l'événement) s'applique quelle que soit la façon dont
l'organisation a été trouvée, **y compris par identifiant** (testé).

Ce qui n'est **pas** traité :

- **Organisation supprimée** : osTicket retire alors l'organisation de ses membres (`org_id = 0`).
  Le plugin ne les déplace pas de lui-même vers la nouvelle organisation ; chacun y est rattaché
  au **prochain événement** de son abonnement (renouvellement, mise à jour), et son organisation
  d'origine, perdue, n'est plus restaurée à la fin de l'abonnement. Pour les rattacher tout de
  suite, rejouer un événement récent de leur abonnement depuis le Dashboard Stripe, ou les
  ranger à la main.
- Le **filtre de tickets** « Organisation / Nom = Priority » compare le **nom** : après un
  renommage (ou une recréation sous un autre nom), mettre à jour la valeur du filtre (testé : le
  filtre ne correspond plus tant qu'il n'est pas mis à jour).
- Le plugin `sla_first_response` garde sa propre configuration (identifiant) : après une
  suppression, son formulaire signale l'écart et l'action à faire (voir son README, §9.2).

Mise à jour depuis la 0.1 : remplacer les fichiers du plugin (y compris le nouveau
`include/class.stripesync.priorityorg.php`). Au premier événement, l'organisation actuelle est
retrouvée par son nom et son identifiant mémorisé (journal : `Priority organization
remembered`, si le niveau de journalisation inclut les informations). **Ne pas renommer
l'organisation avant ce premier événement** (sinon une nouvelle organisation serait créée, comme
en 0.1). Pour forcer la mémorisation sans attendre, rejouer un événement d'abonnement récent
depuis Stripe, ou écrire la ligne à la main :

```sql
INSERT INTO ost_config (`namespace`, `key`, `value`, `updated`)
VALUES ('stripesync', 'priority_org_id', '<id de l''organisation>', NOW()),
       ('stripesync', 'priority_org_name', 'Priority', NOW());
```

(`priority_org_name` = le nom configuré dans le plugin, pas celui de l'organisation.)

Changer d'organisation volontairement : modifier le nom configuré (champ ou variable) ; le plugin
retrouve ou crée l'organisation de ce nom et mémorise son identifiant (non testé en intégration ;
testé en unitaire). Les clients déjà rattachés à l'ancienne y restent ; à leur prochain événement
d'abonnement, ils sont déplacés vers la nouvelle et l'ancienne devient leur « organisation
d'origine », restaurée à la fin de l'abonnement. À éviter, ou à accompagner d'un déplacement manuel
(et de la mise à jour du filtre et du plugin `sla_first_response`).

## Tests

```sh
# Unitaires (PHP 8.2+, aucune dépendance)
php contrib/stripe-sync/tests/run.php

# Suite statique d'osTicket (le plugin est scanné comme le reste du dépôt)
php setup/test/run-tests.php

# Intégration (Docker requis, plugin committé) : MariaDB + Apache + osTicket installé par l'assistant
contrib/stripe-sync/tests/integration/run.sh
```

`run.sh` enchaîne quatre scénarios sur la même instance (données remises à zéro entre deux) :
`scenario-flow.php` (parcours principal), `scenario-concurrency.php` (livraisons simultanées),
`scenario-org.php` (organisation Priority par identifiant : création, renommage, organisation non
sûre retrouvée par identifiant, suppression, migration depuis la 0.1, premiers abonnements
simultanés) et `scenario-e2e.php`, **test de bout en bout des deux plugins Coderise** (ce plugin et
`contrib/sla-first-response`, installé à côté) : événement signé → organisation Priority →
`coderise_contract_level()` = `priority` ; ticket créé par l'API → plan `Priority 4h` par le filtre ;
renommage → le niveau reste juste ; fin d'abonnement → organisation d'origine → `standard` ;
suppression → avertissement, recréation, écart signalé dans le formulaire du plugin SLA. Le niveau
est lu dans un processus PHP neuf à chaque fois (`probe.php`, comme une requête web).

Sans `php` local, lancer les deux premières commandes dans un conteneur, par exemple :
`docker run --rm --network none -v "$PWD:/app:ro" -w /app php:8.4-cli php contrib/stripe-sync/tests/run.php`.

Sous Windows, `git archive` doit être lancé avec `core.autocrlf=false` (le script le fait) : sinon la
signature du schéma d'installation (`core.sig`) ne correspond plus.

## Données exposées à d'autres modules

Pour chaque abonnement **Priority** (prix ou produit configuré), le plugin tient à jour une ligne
de la table `ost_config` (`CONFIG_TABLE`) que d'autres modules peuvent lire en SQL, par exemple pour
compter les dépassements de SLA par client et par période d'abonnement (date anniversaire).
Ce format est un **contrat d'interface** : ne pas le modifier sans prévenir ses lecteurs.

| Colonne | Valeur |
|---|---|
| `namespace` | `stripesync` |
| `key` | `sub:<subscription_id>` (ex. `sub:sub_1Abc…`) ; si `sub:` + l'id dépasse 64 caractères, `sub:` + `sha1(id)` (même règle que les clés `evt:`) |
| `value` | `<user_id osTicket>\|<start_date>\|<ended>\|<status>` |

- `user_id` : identifiant de l'utilisateur osTicket du client (colonne `ost_user.id`).
- `start_date` : champ `start_date` de l'objet Subscription de Stripe, en secondes epoch UTC. C'est la
  date du **tout premier démarrage**, pas la période en cours : un renouvellement ne la modifie pas
  (la première valeur vue est conservée).
- `ended` : secondes epoch UTC de la fin (`ended_at`, sinon `canceled_at`, sinon l'heure de
  l'événement), ou **chaîne vide** tant que l'abonnement est en cours. Une annulation programmée en
  fin de période (`cancel_at_period_end`) n'est pas une fin. Si l'abonnement passe sur un prix non
  Priority, `ended` est l'heure de cet événement.
- `status` : statut Stripe (`active`, `trialing`, `past_due`, `unpaid`, `canceled`, `incomplete_expired`…).
- Pas de JSON, séparateur `|`, aucun espace. Exemples : `42|1690000000||active`,
  `42|1690000000|1760000000|canceled`.

Règles d'écriture :

- La ligne est écrite sur `customer.subscription.created/updated/deleted`, sous le verrou par client,
  une fois l'utilisateur osTicket connu. Un abonnement reçu avant que l'on connaisse l'e-mail du client
  est publié dès que `checkout.session.completed` (ou `invoice.paid`) l'apporte : l'objet Checkout Session
  ne contient ni prix, ni `start_date`, ni statut, donc c'est l'événement d'abonnement qui fournit les données.
- L'écriture est idempotente : un rejeu, ou un événement de même contenu, laisse la ligne intacte.
- Les abonnements non Priority n'écrivent **rien**. La ligne d'un abonnement qui a été Priority n'est jamais
  supprimée (historique) ; elle n'est pas purgée avec les marqueurs `evt:`.
- Si le client change d'adresse e-mail, `user_id` est mis à jour. Un abonnement sans `start_date` dans
  l'événement n'écrit pas de ligne (avertissement au journal système).
Lecture :

```sql
SELECT `key`, `value` FROM ost_config
 WHERE `namespace` = 'stripesync' AND `key` LIKE 'sub:%';
```

### Identifiant de l'organisation Priority (`priority_org_id`, version 0.2)

Second **contrat d'interface** : l'organisation que le plugin utilise pour Priority.

| Colonne | Valeur |
|---|---|
| `namespace` | `stripesync` |
| `key` | `priority_org_id` |
| `value` | identifiant de l'organisation osTicket (`ost_organization.id`), entier décimal, sans espace |

- Absente tant que le plugin n'a rattaché ni détaché personne (ou en version 0.1).
- Écrite à la première résolution (création ou organisation trouvée par nom), puis réécrite
  uniquement si l'organisation mémorisée a été supprimée ou si le nom configuré a changé.
- Elle peut désigner une organisation **supprimée** entre-temps (le plugin ne la remplace qu'au
  prochain événement) : le lecteur vérifie que l'organisation existe.
- La clé voisine `priority_org_name` est **interne** (nom configuré au moment de la mémorisation) :
  ne pas la lire.
- Ne charger aucune classe du plugin pour la lire : une requête suffit (c'est ce que fait
  `sla_first_response`, voir `SlaFirstResponsePlugin::stripeSyncOrgId()`). Dans le plugin lui-même :
  `StripeSyncDbStore::readPriorityOrgId()` (entier ou `null`).

```sql
SELECT `value` FROM ost_config
 WHERE `namespace` = 'stripesync' AND `key` = 'priority_org_id';
```

## Limites connues

- Un abonnement en `past_due` (paiement échoué, relances Stripe en cours) **perd** l'organisation
  Priority aussitôt (décision du 21/10/2026) ; l'organisation d'origine est restaurée. Quand le paiement
  aboutit et que Stripe repasse l'abonnement en `active`, Priority est de nouveau accordé. Une instance du
  plugin enregistrée avant ce changement garde peut-être l'ancienne valeur dans son champ « statuts » : la
  vérifier dans l'administration (ou la vider pour retrouver le défaut `active, trialing`).
- Un utilisateur osTicket n'a qu'une organisation : Priority remplace son organisation actuelle,
  qui est restaurée à la fin de l'abonnement.
- Organisation Priority supprimée : ses anciens membres ne sont pas déplacés d'office vers la
  nouvelle (voir « Organisation Priority ») ; le filtre de tickets compare le nom de
  l'organisation et doit être mis à jour après un renommage.
- Le SLA est fixé à la création du ticket : il n'est pas modifié rétroactivement.
- Le client doit écrire depuis l'adresse de son compte.
- Pas de rattrapage automatique des événements manqués au-delà de ce que Stripe rejoue (3 jours) : prévoir un
  rapprochement périodique avec une clé restreinte en lecture seule si cela devient nécessaire.
