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
| `include/class.stripesync.store.php` | État et idempotence dans `ost_config` (espace `stripesync`), verrou par client |
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
| `STRIPE_SYNC_PRIORITY_ORG` | Nom de l'organisation | `Priority` |
| `STRIPE_SYNC_ACTIVE_STATUSES` | Statuts d'abonnement qui gardent Priority | `active, trialing, past_due` |
| `STRIPE_SYNC_ACCEPT_TEST_EVENTS` | `1` pour accepter les événements du mode test (recette seulement) | refusés |
| `STRIPE_SYNC_INVITE_RESEND_DELAY` | Secondes minimum entre deux invitations à un même client | `21600` |

4. Dans Stripe, créer une destination webhook vers `https://<support>/api/stripe-sync/webhook`
   avec : `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`,
   `invoice.paid`, `invoice.payment_failed`. Au niveau du proxy, n'autoriser que les
   [adresses IP de Stripe](https://docs.stripe.com/ips) sur cette route.
5. Créer le plan SLA et le filtre de tickets « Organisation / Nom = Priority » (voir le §6 du document de conception).

## Tests

```sh
# Unitaires (PHP 8.2+, aucune dépendance)
php contrib/stripe-sync/tests/run.php

# Suite statique d'osTicket (le plugin est scanné comme le reste du dépôt)
php setup/test/run-tests.php

# Intégration (Docker requis, plugin committé) : MariaDB + Apache + osTicket installé par l'assistant
contrib/stripe-sync/tests/integration/run.sh
```

Sans `php` local, lancer les deux premières commandes dans un conteneur, par exemple :
`docker run --rm --network none -v "$PWD:/app:ro" -w /app php:8.4-cli php contrib/stripe-sync/tests/run.php`.

Sous Windows, `git archive` doit être lancé avec `core.autocrlf=false` (le script le fait) : sinon la
signature du schéma d'installation (`core.sig`) ne correspond plus.

## Limites connues

- Un utilisateur osTicket n'a qu'une organisation : Priority remplace son organisation actuelle,
  qui est restaurée à la fin de l'abonnement.
- Le SLA est fixé à la création du ticket : il n'est pas modifié rétroactivement.
- Le client doit écrire depuis l'adresse de son compte.
- Pas de rattrapage automatique des événements manqués au-delà de ce que Stripe rejoue (3 jours) : prévoir un
  rapprochement périodique avec une clé restreinte en lecture seule si cela devient nécessaire.
