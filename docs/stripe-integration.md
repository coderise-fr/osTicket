# Intégration Stripe vers osTicket : comptes clients et organisation Priority

Document de conception pour l'issue #2. Il couvre l'analyse du code (osTicket 1.18.4), la
comparaison des modes d'écriture, le choix recommandé, le design détaillé et l'état de
vérification du prototype livré dans `contrib/stripe-sync/`.

Convention de lecture : ce qui est **vérifié** a été exécuté (voir le §9) ; ce qui est
**lu dans le code** n'a pas été exécuté ; ce qui est **supposé** est signalé comme tel.

## 1. Besoin

À l'achat d'une licence (Stripe), le client reçoit un accès au portail osTicket. Un
abonnement Priority Support actif le rattache à l'organisation « Priority », sur laquelle un
filtre de tickets applique le SLA de 4 h en 24/7. À la fin de l'abonnement, il repasse au
support inclus (SLA en jours ouvrés, sans engagement).

## 2. Ce que le code permet

### API HTTP (`api/http.php`, `include/api.tickets.php`, `include/class.api.php`)

- Routes : `POST /tickets.{json,xml,email}` et `POST /tasks/cron`. Rien pour les utilisateurs,
  les organisations ni les comptes.
- Authentification : une clé API **liée à une adresse IP unique** (`requireApiKey()` compare
  `getIPAddr()` à `REMOTE_ADDR`). Stripe envoie ses webhooks depuis une liste d'adresses qui
  évolue : ce modèle ne s'applique pas à un appelant Stripe direct.
- Effet de bord : créer un ticket crée l'utilisateur correspondant à l'e-mail, sans compte
  portail ni organisation. Inutilisable pour notre besoin.
- Sous `php -S` (serveur intégré), `osTicket::is_cli()` renvoie vrai et l'API lit `php://stdin`
  au lieu du corps de la requête : tester l'API derrière Apache ou nginx. Vérifié.

### Utilisateurs et organisations (`class.user.php`, `class.organization.php`)

- `User::fromVars(['email','name'])` retrouve ou crée l'utilisateur. Elle valide l'e-mail avec
  `Validator::is_email()` **sans** contrôle DNS : une adresse `@acme.test` est acceptée
  (vérifié). Elle rattache aussi l'utilisateur à l'organisation dont le champ « domaine »
  correspond (`Organization::forDomain`).
- Un utilisateur n'a **qu'une seule organisation** (colonne `user.org_id`). Être « Priority »
  et appartenir à l'organisation de son entreprise sont donc exclusifs.
- `Organization::fromVars(['name'])` crée ou retrouve par nom. `User::setOrganization()` et
  `Organization::removeUser()` rattachent et retirent.
- Les drapeaux d'une organisation (`COLLAB_ALL_MEMBERS`, `COLLAB_PRIMARY_CONTACT`,
  `SHARE_PRIMARY_CONTACT`, `SHARE_EVERYBODY`) font voir les tickets des membres entre eux.
  `Organization::create()` active `SHARE_PRIMARY_CONTACT` par défaut. Pour une organisation
  qui regroupe des clients sans lien entre eux, c'est une fuite de données potentielle.

### Compte portail (`UserAccount`, `pwreset.php`, `account.php`)

- Le panneau agent (`scp/users.php`, action « Register ») appelle
  `UserAccount::register($user, ['sendemail' => true], $errors)`, ou `sendConfirmEmail()` si le
  compte existe. Le compte est créé sans mot de passe ; le client reçoit le modèle
  `registration-client` avec un lien `pwreset.php?token=…`.
- Ce lien confirme le compte, ouvre la session et force le choix du mot de passe (vérifié de
  bout en bout).
- Le jeton est stocké dans `ost_config` (espace `pwreset`) et **purgé par le cron** une fois
  `pw_reset_window` écoulé (30 minutes par défaut ; `ConfigItem::cleanPwResets`, appelé par
  `class.cron.php`). Un lien d'invitation expire donc vite, et la fonction « mot de passe
  oublié » refuse un compte sans mot de passe : il faut **renvoyer une invitation**.
- Le jeton utilise l'alphabet `[A-Za-z0-9_=]` (`Misc::randCode`). Tout code qui extrait le
  jeton d'un e-mail doit accepter `_` et `=`.
- Mode d'inscription par défaut : `closed` (« Private — Only agents can register users »).
  `account.php` est alors inaccessible, mais le flux par jeton fonctionne.
- Restriction de domaine : il n'existe **pas de liste de domaines refusés**. Les formulaires
  (inscription, champ e-mail) utilisent `Validator::is_valid_email`, qui fait une vérification
  DNS (MX/A) si le réglage `verify_email_addrs` est activé : c'est ce qui rejette `.test` au
  portail. La création côté serveur ne passe pas par là.

### Filtres de tickets (`class.filter.php`, `class.ticket.php::filterTicketData`)

- À la création d'un ticket, si l'e-mail correspond à un utilisateur connu, les données de son
  **organisation actuelle** sont ajoutées aux critères (`$org->getFilterData()`). Le critère
  « Organisation / Nom » est donc évalué sur le nom de l'organisation du demandeur.
- Pour un expéditeur inconnu, seule l'organisation du domaine de l'e-mail est considérée.
- L'évaluation a lieu **à la création**. Un ticket ouvert pendant la période Priority garde son
  SLA après la fin de l'abonnement ; un ticket ouvert avant l'abonnement ne le reçoit pas.
- Conséquence : un client n'obtient le SLA Priority que s'il écrit depuis l'adresse de son
  compte (ou une adresse secondaire enregistrée sur son utilisateur).

### Plugins (`class.plugin.php`, `class.osticket.php::start`, `api/http.php`)

- Un plugin est un dossier `include/plugins/<nom>/` avec `plugin.php`, ou un `.phar`. Il est
  installé puis activé depuis l'administration. `PluginManager::bootstrap()` l'initialise à
  chaque requête, API comprise (`osTicket::start`).
- `api/http.php` envoie le signal `api` avec le `Dispatcher` avant le routage : un plugin y
  ajoute ses routes (`url_post(...)`). Précédent dans le cœur : `class.auth.php` ajoute
  `/auth/ext` de cette façon. `api/.htaccess` réécrit `/api/xxx` vers `api/http.php/xxx`.
- `Plugin::getImpl()` n'exige pas de `.phar` : un dossier suffit (donc déployable via
  `deploy/docker/overlay/include/plugins/`).
- La configuration d'un plugin est stockée en base (`ost_config`). Un secret n'y a donc pas sa
  place si on veut le garder hors des sauvegardes de la base.

### CLI (`include/cli/modules/user.php`, `org.php`)

Vérifié par exécution sur 1.18.4 :

| Besoin | Résultat |
|---|---|
| Créer un utilisateur | Pas d'action dédiée. Seul `user import` (CSV) crée. |
| Créer un compte portail | `user activate` plante : `Call to undefined method UserAccount::create()` (`user.php:95`). |
| Rattacher un utilisateur existant à une organisation | Impossible : `user import -O <id>` ne touche pas les utilisateurs existants (vérifié) et n'accepte qu'un id d'organisation existant. |
| Retirer d'une organisation | Aucune action. |
| Créer une organisation | `org import` (CSV, nom seul). |

Lecture du code (non exécutée) : `getQuerySet()` ignore l'option `--email` (aucun `case 'email'`),
donc `user activate -E x@y` viserait **tous** les utilisateurs ; `user lock` appelle
`setFlag()` qui n'existe pas sur `UserAccount`. Les sorties sont du texte libre, sans JSON ni
code d'erreur exploitable.

## 3. Comparaison des modes d'écriture

| Critère | (a) Plugin osTicket + webhook | (b) Service externe + `manage.php` | (c) SQL direct | (d) API HTTP |
|---|---|---|---|---|
| Crée l'utilisateur | Oui | Seulement via CSV | Oui, à la main | Effet de bord d'un ticket |
| Compte portail + e-mail d'invitation | Oui (appels du panneau agent) | Non (`activate` cassé) | À réimplémenter : table, bits d'état, jeton `pwreset`, modèle d'e-mail | Non |
| Rattacher / retirer de « Priority » | Oui | Non | Oui (`UPDATE user SET org_id`) | Non |
| Signaux, journal d'audit, caches | Oui, identiques à une action d'agent | Partiellement | Contournés | Oui, mais hors sujet |
| Couplage à osTicket | ~10 appels de méthodes, dans un seul fichier | Texte de sortie du CLI + options fragiles | **Schéma** (tables `user`, `user_email`, `user_account`, `organization`, `config`, `form_entry*`) | Format JSON de l'API |
| Risque à une mise à jour mineure | Faible à moyen : une signature de méthode peut changer | Moyen : le CLI est peu entretenu | Élevé : un patch de schéma (`core.sig`) casse en silence | Faible, mais ne couvre pas le besoin |
| Surface d'attaque | Une route publique dans l'application | Un service séparé exposé + accès à l'application | Un service séparé avec **écriture directe en base** | Clé API par IP : inadapté à Stripe |
| Déploiement avec l'image Docker durcie | Un dossier dans l'overlay ; l'application a déjà accès à la base | `exec`/`proc_open` désactivés dans le conteneur web ; il faudrait un conteneur de plus avec le code et la base | Base en réseau `internal` : conteneur de plus avec accès base | Sans objet |
| Testable sans base | La logique métier, oui (fakes) | Non | Non | Non |

### Recommandation : (a), un plugin osTicket

1. C'est le seul mode qui couvre tout le besoin avec les **mêmes appels que le panneau agent**
   (`UserAccount::register`, `sendConfirmEmail`, `setOrganization`, signal `object.edited`) :
   comportement, signaux et journal restent ceux d'une action manuelle.
2. Le couplage est concentré dans `include/class.stripesync.directory.php`. Le reste du
   prototype (signature, mapping des événements, règles métier, magasin) ne dépend d'aucune
   classe osTicket et se teste seul.
3. Aucun changement de schéma : l'état tient dans `ost_config` (espace `stripesync`).
4. Aucun fichier du cœur n'est modifié ; le plugin vit dans un dossier dédié, ce qui est
   compatible avec la règle de ne pas diverger du dépôt amont.
5. Aucune clé API Stripe n'est nécessaire : seul le secret de signature du webhook (`whsec_…`)
   est requis, lu hors du dépôt et hors de la base.

Coûts et risques acceptés :

- La route est publique dans l'application. Mitigations : signature HMAC vérifiée avant tout
  traitement, corps limité à 512 Ko, tolérance d'horloge de 5 minutes, échec fermé si le secret
  manque, aucun détail d'erreur dans les réponses. À compléter côté proxy par la liste d'IP de
  Stripe (https://docs.stripe.com/ips) et une limite de débit.
- Chaque mise à jour d'osTicket doit être suivie de `contrib/stripe-sync/tests/run.php` et du
  test d'intégration (voir §8). Les symboles à surveiller sont listés au §8.
- Le traitement est synchrone, y compris l'envoi du courriel d'invitation. Le volume d'une
  boutique de licences le permet ; au-delà, il faudra une file d'attente (hors périmètre).

Alternative à garder en tête : un petit service externe qui reçoit Stripe puis appelle une route
du plugin. Il n'apporte rien tant qu'osTicket est le seul destinataire, et ajoute un composant
à exploiter.

## 4. Architecture

```text
Stripe ──POST──▶ reverse proxy (TLS, liste d'IP Stripe, limite de débit)
                    └▶ /api/stripe-sync/webhook      (api/.htaccess → api/http.php)
                         signal `api` ─▶ StripeSyncController
                              1. taille ≤ 512 Ko
                              2. signature Stripe-Signature (HMAC-SHA256, ±5 min)
                              3. JSON valide, mode test/live
                              4. StripeSyncService
                                    ├─ StripeSyncMapper     événement → fait normalisé
                                    ├─ StripeSyncDbStore    idempotence, état client, verrou (ost_config)
                                    └─ StripeSyncOsTicketDirectory   User / UserAccount / Organization
```

## 5. Design détaillé

### 5.1 Événements Stripe

À cocher sur la destination webhook (événements « snapshot », avec la charge complète) :

| Événement | Traitement |
|---|---|
| `checkout.session.completed` | Si `payment_status` vaut `paid` ou `no_payment_required` : crée l'utilisateur, le compte portail et envoie l'invitation. Sinon ignoré. |
| `checkout.session.async_payment_succeeded` | Idem, pour les moyens de paiement différés (SEPA…) où la session se termine avant l'encaissement. |
| `customer.subscription.created` / `updated` / `deleted` | Met à jour l'état de l'abonnement ; recalcule l'appartenance à Priority. |
| `invoice.paid` | Apprend le lien client Stripe ↔ e-mail (`customer_email`) quand l'abonnement ne vient pas de Checkout ; peut renvoyer une invitation (voir §5.5). |
| `invoice.payment_failed` | Écrit un avertissement dans le journal système. Aucun changement d'accès : c'est le statut de l'abonnement (`past_due`, `unpaid`, `canceled`) qui décide. |

Ignorés volontairement (réponse 200) : tous les autres types, dont `customer.created/updated`
(un changement d'e-mail chez Stripe ne déplace pas le compte osTicket), `customer.subscription.paused/resumed`
(`updated` porte déjà le statut) et les remboursements/litiges (décision commerciale, pas un état d'accès).

Choix « snapshot » plutôt que « thin » : Stripe recommande les événements fins pour les
nouvelles intégrations, mais ils obligent à rappeler l'API Stripe pour lire l'objet, donc à
stocker une clé API (même restreinte) dans osTicket. Ici, la charge contient tout ce qu'il
faut et osTicket ne détient aucune clé. Le prix : la charge peut être périmée ; c'est traité
par l'ordre des événements (§5.3).

### 5.2 Vérification de la signature

Implémentée à la main dans `class.stripesync.signature.php`, d'après la documentation
Stripe (aucune dépendance, aucun SDK à vendre dans osTicket) :

1. Lire le corps **brut** (`php://input`), jamais `$_POST` ni du JSON ré-encodé.
2. Extraire `t` et chaque `v1` de l'en-tête `Stripe-Signature`. Les autres schémas (`v0`, factice,
   envoyé en test) sont ignorés pour éviter une attaque par rétrogradation.
3. Calculer `HMAC-SHA256(secret, "<t>.<corps>")` et comparer avec **tous** les `v1` via
   `hash_equals` (pendant une rotation de secret, Stripe signe une fois par secret actif).
4. Refuser si `|now − t| > 300 s` (anti-rejeu).
5. Secret absent, vide ou ne commençant pas par `whsec_` : refus (503), jamais d'acceptation par défaut.

Le vecteur de test de l'HMAC a été calculé avec OpenSSL, indépendamment du code.

### 5.3 Idempotence, ordre, concurrence

- **Idempotence** : `INSERT IGNORE` de `evt:<id>` dans `ost_config` : une seule requête
  « gagne » la réservation. Le marqueur `done` est posé après succès et supprimé si le
  traitement échoue (Stripe réessaie jusqu'à 3 jours ; chaque étape est rejouable). Une
  réservation abandonnée par un plantage est reprise après 5 minutes (compare-and-swap). Les
  marqueurs sont purgés au fil de l'eau après 30 jours.
- **Doublons à identifiant différent** : couverts par l'état par abonnement ci-dessous.
- **Désordre** : l'état d'un client contient, par abonnement, `{priority, ts, terminal}`.
  Un événement plus ancien que `ts` est ignoré ; un abonnement `canceled` / `incomplete_expired`
  ou `deleted` est **définitif**. Limite : `created` a une précision d'une seconde ; deux
  événements de la même seconde sont appliqués dans l'ordre d'arrivée, d'où le caractère définitif de l'annulation.
- **Concurrence** : verrou `GET_LOCK('ss:<md5(cus_…)>', 10)` par client Stripe ; libéré
  automatiquement si la connexion tombe. Les requêtes concurrentes pour le même événement
  reçoivent 503 (Stripe réessaie) ; vérifié avec 8 requêtes simultanées.
- **Codes de réponse** : 200 traité, ignoré ou doublon ; 400 signature ou corps invalide ;
  413 corps trop gros ; 503 erreur transitoire ou configuration à corriger ; 500 erreur
  inattendue. Une donnée définitivement inexploitable (par exemple un paiement sans e-mail)
  est journalisée en erreur (avec alerte) et répond 200 pour ne pas boucler pendant 3 jours.

### 5.4 Correspondance client Stripe ↔ osTicket

- Clé naturelle : l'**adresse e-mail** (celle de l'utilisateur osTicket, unique).
- Table de correspondance : `cus:<cus_id>` dans `ost_config`, en JSON :
  `{user_id, email, prev_org_id, attached, invited_at, subs:{sub_id:{priority, ts, terminal}}}`.
- Un événement d'abonnement reçu **avant** qu'on connaisse l'e-mail du client est enregistré et
  répond « différé » ; il est appliqué dès qu'un événement `checkout.session.completed` ou
  `invoice.paid` apporte l'e-mail (Stripe ne garantit pas l'ordre de livraison). Vérifié.
- Paiement sans objet client Stripe (Checkout en mode `payment` sans création de client) : compte
  portail seulement, pas d'état. Pour les licences, configurer `customer_creation: 'always'`
  est préférable.
- Le client change d'e-mail (nouvel achat avec une autre adresse) : l'ancien utilisateur est
  retiré de Priority et le nouveau le reçoit.
- Utilisateur supprimé par un agent alors que l'abonnement vit : recréé sans invitation.
- L'e-mail saisi à l'achat n'est **pas vérifié** par Stripe. Ce n'est pas un problème d'accès :
  le lien de confirmation part vers l'adresse saisie, donc seul son titulaire entre. Le risque
  résiduel est l'envoi d'invitations à des tiers ; l'envoi est limité à une fois par 6 h et par client.

### 5.5 Accès au portail

1. Premier achat : `User::fromVars` puis `UserAccount::register(…, ['sendemail' => true])`.
   Le client reçoit le modèle `registration-client` et choisit son mot de passe depuis le lien.
2. Compte déjà confirmé : rien n'est envoyé. Compte verrouillé par un agent : rien non plus.
3. Compte jamais confirmé (lien expiré) : un nouvel événement payé (`invoice.paid` de la
   période suivante, ou nouvel achat) renvoie un lien, au plus une fois par 6 h
   (`STRIPE_SYNC_INVITE_RESEND_DELAY`). Vérifié avec une date d'invitation falsifiée.
4. Le mode d'inscription peut rester sur « Private » ; `.test` et autres domaines que le
   portail refuserait ne bloquent pas la création.

À décider côté exploitation : le lien expire après `pw_reset_window` (30 min). Soit on
l'augmente (Admin > Paramètres > Agents, « Reset Token Expiration », en minutes ; le réglage
vaut aussi pour les réinitialisations de mot de passe des agents et des clients), soit le support
renvoie le lien (action « Register » de la liste des utilisateurs, `scp/users.php`). Le texte du modèle `registration-client` doit être adapté
(« Merci pour votre achat… »).

### 5.6 Appartenance à l'organisation Priority

- Règle : *au moins un abonnement du client est dans un statut actif ET contient un prix ou un
  produit Priority*. Statuts actifs par défaut : `active`, `trialing`, `past_due`
  (`past_due` = délai de grâce pendant les relances de Stripe). `unpaid`, `incomplete`, `paused`,
  `canceled` retirent Priority. `cancel_at_period_end` ne change rien avant la fin de période :
  Stripe envoie `customer.subscription.deleted` à l'échéance.
- Identification de l'abonnement Priority : ids de prix (`price_…`) et/ou de produit
  (`prod_…`) configurés. Sans configuration, les événements d'abonnement répondent 503 plutôt
  que d'être ignorés en silence.
- Organisation : retrouvée par nom (« Priority » par défaut) ; créée si absente avec **tous les
  drapeaux de partage à zéro**. Si elle existe avec un drapeau de partage/collaboration, ou un
  domaine mappé, le plugin **refuse** de l'utiliser (503 + erreur au journal système) : des
  clients sans lien ne doivent pas voir les tickets les uns des autres, ni s'inscrire
  automatiquement via un domaine. L'événement est rejoué une fois l'organisation corrigée (vérifié).
- Organisation précédente : l'utilisateur n'a qu'une organisation. Son organisation d'origine
  (par exemple celle de son entreprise, attribuée par domaine) est mémorisée et **restaurée à la fin**.
- Le plugin ne retire que ce qu'il a ajouté : un utilisateur placé dans Priority à la main, ou
  déplacé ailleurs par un agent entre-temps, n'est pas touché.
- Non rétroactif : voir §2 (filtres). Aucun ticket existant n'est modifié.

### 5.7 Fin d'abonnement

`customer.subscription.deleted` (ou un statut non actif) retire l'utilisateur de Priority et
restaure son organisation d'origine ; il garde son compte portail et repasse au traitement par
défaut du filtre (support inclus). Rien n'est supprimé.

### 5.8 Secrets

- **Dans le dépôt : rien.** Pas de clé, pas de secret d'exemple au format réel.
- **Secret de signature** (`whsec_…`) : variable d'environnement `STRIPE_SYNC_WEBHOOK_SECRET`,
  ou `STRIPE_SYNC_WEBHOOK_SECRET_FILE` pointant vers un fichier hors de la racine web. Jamais en
  base ni dans le formulaire d'administration. Avec l'image durcie, `open_basedir` n'autorise
  que `/var/www/html`, `/etc/osticket`, `/tmp`… : un secret Docker monté dans `/run/secrets` ne serait pas lisible ;
  utiliser la variable d'environnement, ou monter le fichier sous `/etc/osticket/`
  (non testé avec cette image).
- Une clé API Stripe (`sk_`/`rk_`) n'est **pas** acceptée comme secret de webhook (refus si le
  préfixe n'est pas `whsec_`) et n'est jamais utilisée par le prototype.
- Paramètres non secrets (organisation, ids de prix) : formulaire du plugin, ou variables
  `STRIPE_SYNC_*` qui l'emportent.
- Le journal système ne reçoit que des ids Stripe validés (`cus_`, `sub_`, `evt_`) et des ids numériques osTicket, jamais
  de nom ni d'e-mail (vérifié par le test d'intégration).

### 5.9 Exploitation

- Journal système (Admin > Tableau de bord > Journaux) : erreurs de configuration, paiements
  échoués, événements rejetés. Niveau « info » (octroi/fin de Priority) si le niveau de journalisation l'inclut.
- Rejeu : le Dashboard Stripe permet de renvoyer un événement ; l'idempotence le rend sûr.
- Sauvegarde/restauration de la base : l'état (`cus:…`, `evt:…`) suit la base. Après une restauration, rejouer les
  événements récents depuis Stripe.
- Désinstaller le plugin laisse les lignes `stripesync` dans `ost_config` (inoffensif).

## 6. Mise en place

**Stripe** (sandbox d'abord) : créer une destination webhook vers
`https://<support>/api/stripe-sync/webhook` avec les événements du §5.1 ; copier le secret
`whsec_…` dans l'environnement du conteneur web ; noter les ids de prix Priority.

**osTicket** :

1. Copier `contrib/stripe-sync/` (sans `tests/`) vers `include/plugins/stripe-sync/` (ou
   `deploy/docker/overlay/include/plugins/stripe-sync/` avec l'image durcie), puis
   Admin > Gérer > Plugins : installer, ajouter une instance, activer.
2. Renseigner les ids de prix. Laisser « Accepter les événements de test » décoché en production.
3. Créer l'organisation « Priority » : le plugin la crée au premier besoin ; si on la crée à la main,
   décocher partage des tickets et collaborateurs automatiques, et ne mettre aucun domaine.
4. Plan SLA 4 h / planning 24/7, puis filtre de tickets : « Organisation / Nom » égal à
   « Priority » → action « Set SLA Plan ». (Le plan SLA lui-même dépend de l'issue sur le SLA de première réponse.)
5. Adapter le modèle d'e-mail `registration-client`.

## 7. Menaces considérées

| Menace | Réponse |
|---|---|
| Faux webhook (octroi de Priority, création de comptes) | Signature HMAC sur le corps brut ; échec fermé ; liste d'IP au proxy |
| Rejeu d'un vrai webhook | Tolérance 5 min + idempotence par `event.id` |
| Événements dans le désordre / doublons | État par abonnement, annulation définitive, verrou par client |
| Fuite de tickets entre clients Priority | Refus d'une organisation avec partage ou collaborateurs automatiques |
| Auto-inscription par domaine dans Priority | Refus d'une organisation avec domaine |
| Invitations envoyées à des tiers | Lien envoyé à l'adresse saisie seulement ; une fois par 6 h par client |
| Secret dans Git, la base ou les journaux | Variable d'environnement/fichier ; préfixe `whsec_` exigé ; ids seuls dans les journaux |
| Corps géant / déni de service | Limite 512 Ko ; HMAC peu coûteux ; échecs de signature non journalisés |
| Mauvais environnement | Événements `livemode=false` ignorés par défaut |

## 8. Maintenance à chaque mise à jour d'osTicket

Le plugin appelle uniquement : `User::lookupByEmail`, `User::fromVars`, `User::lookup`,
`User::getAccount/getOrgId/setOrganization`, `UserAccount::register`, `ClientAccount::isLocked/isConfirmed/sendConfirmEmail`,
`Organization::lookup/fromVars/removeUser`, `Signal::send('object.edited')`, `Validator::is_email`,
`$cfg->getDefaultEmail()`, le signal `api`, `url_post()`, `db_query/db_input/db_affected_rows`,
`PluginConfig`, `CONFIG_TABLE`.

Après chaque mise à jour : `php contrib/stripe-sync/tests/run.php`, puis le scénario d'intégration (voir le
README du dossier), puis relire le changelog pour ces symboles.

## 9. Ce qui est vérifié, ce qui ne l'est pas

**Exécuté** (conteneurs Docker, sans réseau externe) :

- 81 tests unitaires (`contrib/stripe-sync/tests/run.php`, PHP 8.4) : signature, réglages, mapping, règles
  métier avec doublures. Une mutation volontaire du code (comparaison de signature, garde d'ordre) fait échouer les tests concernés.
- La suite statique d'osTicket (`php setup/test/run-tests.php`, PHP 8.4) ne signale aucun échec sur les nouveaux fichiers
  (le binaire `jsl` du test JavaScript est absent du conteneur).
- Intégration : osTicket 1.18.4 installé par l'assistant sur PHP 8.2 + Apache + MariaDB 11.4,
  plugin installé via `PluginManager::install` et `addInstance`. 39 contrôles (répétés 10 fois) +
  11 contrôles de concurrence : signature (absente, fausse, vieille), achat → utilisateur +
  compte non confirmé + e-mail avec lien, rejeu, lien de confirmation → session et compte confirmé,
  abonnement → organisation, **création d'un ticket par l'API et filtre « Organisation / Nom = Priority » → plan SLA 4 h**,
  fin d'abonnement → retour au support inclus, restauration de l'organisation d'origine, événements hors ordre, différés,
  organisation non sûre refusée puis rejeu, 8 requêtes simultanées, relance d'invitation limitée, compte verrouillé,
  secret absent (503), corps trop gros (413), aucune adresse e-mail dans le journal système.

**Non vérifié** :

- Aucun vrai événement Stripe : les charges sont construites d'après la documentation
  (`stripe listen` / `stripe trigger` dans un sandbox sont à faire avant la mise en production).
- Pas de test avec l'image Docker durcie (`open_basedir`, `disable_functions`, système de fichiers en lecture seule,
  proxy inverse), ni avec un vrai SMTP (les e-mails sont capturés par un faux `sendmail`).
- Le formulaire de configuration du plugin n'a pas été affiché dans un navigateur (l'instance a été
  créée par `Plugin::addInstance`).
- Pas de test de charge, ni de montée de version d'osTicket.
- Les messages d'e-mail en français et le comportement d'un client de messagerie réel.

## 10. Questions ouvertes

1. Quels ids de prix/produit désignent Priority Support ? Les licences sont-elles des paiements uniques ou des abonnements ?
2. Tout achat payé donne-t-il un accès au portail, ou seulement certains produits (licences) ?
3. `past_due` garde-t-il Priority (hypothèse actuelle : oui, pendant les relances Stripe) ?
4. Les clients sont-ils déjà rangés dans des organisations d'entreprise utiles (partage de tickets) ? Une seule organisation par utilisateur impose le retour à l'organisation d'origine ; une alternative est un champ personnalisé « Support » sur l'utilisateur, au prix d'un couplage plus fort.
5. Durée de validité du lien d'invitation : garder 30 min avec renvoi, ou passer `pw_reset_window` à 24 h ou plus ?
6. Reprise des clients existants (avant la mise en service) : script de rattrapage à partir d'un export Stripe ?
7. Tickets envoyés depuis une autre adresse que celle du compte : acceptable, ou faut-il enregistrer des adresses secondaires ?
8. Qui expose le webhook (proxy) et peut appliquer la liste d'IP de Stripe ?
