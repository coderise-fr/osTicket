# osTicket : déploiement Docker de production (Coderise)

Image durcie, pile `web` + `cron` + MariaDB, construite **depuis ce dépôt** (le commit
courant), sans télécharger d'archive de release. Pour un portail de support derrière un
reverse proxy TLS, par exemple `support.coderise.fr`.

## Contenu

| Fichier | Rôle |
|---|---|
| `Dockerfile` | Image `runtime` (production) et `install` (assistant d'installation) |
| `compose.yaml` | Pile de production : `web`, `cron`, `db` |
| `compose.install.yaml` | Surcharge pour la première installation et les mises à jour |
| `conf/apache2.conf`, `conf/osticket.ini` | Configuration Apache et PHP durcie |
| `docker-entrypoint.sh`, `healthcheck.php` | Démarrage (échec si la configuration n'est pas sûre) et contrôle de santé |
| `scripts/build.sh` | Construit les images depuis `git archive` (commit exact) |
| `scripts/gen-secrets.sh` | Génère les mots de passe de la base |
| `overlay/` | Thème, packs de langue et plugins superposés à osTicket (voir son `README.md`) |

## Prérequis

- Docker Engine avec Compose v2 et BuildKit.
- Un reverse proxy qui termine TLS (Caddy, Nginx, Traefik) devant `127.0.0.1:8080`.
- Un compte de messagerie IMAP/SMTP pour recevoir et envoyer les e-mails du support.

## Premier déploiement

Depuis `deploy/docker/` :

```sh
cp .env.example .env                 # adapter OST_PORT, OST_PROJECT, etc.
./scripts/gen-secrets.sh             # crée secrets/db_password.txt et db_root_password.txt

# 1. Construire les images à partir du commit (jamais de l'arbre de travail)
./scripts/build.sh
./scripts/build.sh -f compose.yaml -f compose.install.yaml build web

# 2. Installer : assistant sur http://127.0.0.1:8080/setup/
docker compose -f compose.yaml -f compose.install.yaml up -d db web
#    Base : hôte `db`, nom `osticket`, utilisateur `osticket`,
#    mot de passe = contenu de secrets/db_password.txt, préfixe `ost_`.
#    Choisir un identifiant administrateur autre que `admin` (refusé par l'assistant).

# 3. Passer en production : arrêter l'assistant, démarrer web (config en lecture seule) et cron
docker compose -f compose.yaml -f compose.install.yaml stop web
docker compose -f compose.yaml -f compose.install.yaml rm -f web
docker compose up -d
```

Ensuite, dans l'interface d'administration : configurer la boîte e-mail (IMAP/SMTP), activer
**« Email Fetching »** (réglages e-mail), créer les SLA, filtres et rubriques d'aide.
Le service `cron` exécute `api/cron.php` chaque minute (relevé des e-mails, tickets en retard,
purges) : sans lui, aucun e-mail n'est relevé.

L'adresse publiée par Docker est `127.0.0.1` : ne l'ouvrez pas sur Internet. Exemple de
reverse proxy (Caddy, **non testé ici**) :

```caddyfile
support.coderise.fr {
    reverse_proxy 127.0.0.1:8080
}
```

Il transmet `X-Forwarded-For` et `X-Forwarded-Proto`, que l'image prend en compte
uniquement depuis les réseaux de `OSTICKET_TRUSTED_PROXIES`.

## Ce qui est durci

| Domaine | Mesure |
|---|---|
| Image | Image de base épinglée par digest, paquets système mis à jour au build, outils de compilation retirés, aucun binaire `setuid` |
| Contenu | Construit depuis `git archive` ; le build échoue si la signature du schéma SQL ne correspond pas à `core.sig` (fins de ligne CRLF) |
| Installeur | `setup/` n'existe dans **aucune couche** de l'image `runtime` ; il n'est présent que dans l'image `install` |
| Processus | Utilisateur `www-data` (uid 33), port 8080, aucune capability Linux, `no-new-privileges` |
| Système de fichiers | Racine en lecture seule, code appartenant à `root` ; seuls `/tmp` (tmpfs, `noexec`) et `/run/apache2` sont inscriptibles |
| Configuration | `ost-config.php` (mot de passe de la base, `SECRET_SALT`) vit dans un volume dédié, monté **en lecture seule** en production ; le conteneur refuse de démarrer s'il est inscriptible |
| Apache | Liste blanche de 13 modules, pas de `.htaccess` hors 4 dossiers de routage, `include/` et fichiers `*.inc.php`, `bootstrap.php`, `manage.php`, `.git`, `.htaccess` interdits, méthodes limitées à GET/POST/HEAD, `TRACE` refusé, limites de requête, en-têtes de sécurité, HSTS conditionnel |
| PHP | Réglages de production, `display_errors` coupé, `disable_functions` (`exec`, `system`, `shell_exec`, `passthru`, `popen`, `proc_open`, ...), `open_basedir`, cookies de session `HttpOnly`, `SameSite=Lax` et `Secure`, OPcache sans revalidation |
| Base de données | Aucun port publié, réseau `internal` sans accès Internet, mots de passe en secrets Docker, `--local-infile=0`, racine en lecture seule |
| Exploitation | Limites mémoire, CPU et processus, journaux Docker avec rotation, contrôles de santé (web, cron, base) |

### Vérifié sur une instance de test

Construction des deux cibles, installation complète par l'assistant, 17 pages d'administration
chargées sous `open_basedir` et `disable_functions`, génération d'un PDF (mPDF), échec propre
d'une connexion IMAP, poignée de main TLS/IMAP réelle vers l'extérieur, `cron` en santé,
refus des chemins sensibles et des méthodes interdites, base sans accès sortant, tentatives
d'écriture dans l'image refusées, refus de démarrage sans configuration ou avec un volume
de configuration inscriptible, sauvegarde SQL.

### Non vérifié

- La réception et l'envoi d'e-mails de bout en bout avec **votre** serveur de messagerie.
- La procédure de **mise à jour** d'osTicket et la **restauration** d'une sauvegarde.
- Le comportement derrière votre reverse proxy réel (en-têtes, HSTS, cookies `Secure`).
- Les pièces jointes volumineuses, les plugins et les packs de langue (voir `overlay/`).

## Limites connues

- **Vulnérabilités du système de base.** Un scan (Docker Scout, 20/10/2026) remonte 26
  vulnérabilités critiques ou élevées (dont 15 dans le paquet `apache2` de Debian bookworm),
  **identiques à celles de l'image PHP officielle de départ**, sans correctif publié à cette date.
  Refaire le scan à chaque reconstruction et reconstruire dès qu'un correctif sort. L'exposition
  est réduite par la liste blanche de modules, l'absence de privilèges et le système de fichiers
  en lecture seule, mais pas supprimée. L'image MariaDB officielle en remonte aussi
  (34, dans un seul paquet).
- **Pas de CSP stricte.** osTicket s'appuie sur des scripts en ligne et envoie déjà sa propre
  politique (`script-src 'self' 'unsafe-inline'`). L'image y ajoute `frame-ancestors 'self'`.
- **Secrets dans la configuration.** L'assistant écrit le mot de passe de la base et
  `SECRET_SALT` dans `ost-config.php`. Les mots de passe des boîtes e-mail sont stockés
  chiffrés en base avec ce sel : **sauvegardez la base et le volume de configuration ensemble**.
- **Image immuable.** Les plugins et packs de langue ne s'installent pas depuis l'interface :
  ajoutez-les dans `overlay/` et reconstruisez.
- **Fins de ligne Windows.** Ne construisez jamais depuis un arbre de travail Windows
  (`core.autocrlf`) : utilisez `scripts/build.sh`.

## Mises à jour (procédure prévue, non testée)

1. Sauvegarder (voir ci-dessous).
2. Mettre à jour le dépôt sur le nouveau tag (par exemple `v1.18.5`) et valider `OST_VERSION` dans `.env`.
3. Reconstruire les deux images avec `scripts/build.sh`, rafraîchir le digest de `PHP_IMAGE`
   et refaire le scan de vulnérabilités.
4. Relancer l'image `install` (même commande qu'au premier déploiement), ouvrir `/setup/`
   pour exécuter la mise à niveau de la base, puis repasser en production (étape 3).

## Sauvegarde

```sh
# Base de données (utilise le secret à l'intérieur du conteneur)
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$(cat /run/secrets/db_root_password)" --single-transaction --routines --triggers osticket' > osticket-$(date +%F).sql

# Configuration (ost-config.php), à conserver avec la base
docker compose run --rm --no-deps --entrypoint tar web -C /etc/osticket -cf - . > osticket-config-$(date +%F).tar
```

Conservez ces fichiers et le dossier `secrets/` hors du serveur, chiffrés.

## Variables (`.env`)

| Variable | Défaut | Rôle |
|---|---|---|
| `OST_PROJECT` | `osticket` | Nom du projet Compose (préfixe des conteneurs et volumes) |
| `OST_VERSION` | `1.18.4` | Tag de l'image |
| `OST_BIND`, `OST_PORT` | `127.0.0.1`, `8080` | Adresse et port publiés |
| `OSTICKET_COOKIE_SECURE` | `1` | `0` uniquement pour un test en HTTP local |
| `OSTICKET_TRUSTED_PROXIES` | réseaux privés | Proxys autorisés à fournir `X-Forwarded-For` |
| `OST_SECRETS_DIR` | `./secrets` | Dossier des secrets de la base |

## App Platform (DigitalOcean)

No volumes there, so `OSTICKET_CONFIG_FROM_ENV=1` makes the entrypoint render `ost-config.php`
from `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS/DB_PREFIX/DB_SSL/DB_SSL_CA/SECRET_SALT/ADMIN_EMAIL`
at start (the `/etc/osticket` read-only check does not apply in that mode). The default (last) Dockerfile
stage is `production` = `runtime`, because App Platform cannot select a target. See `app-spec.yaml`.

1. Create a MySQL cluster (not PostgreSQL). 2. Run the `install` target locally against it (allow your IP in
the cluster's trusted sources) and keep the generated `SECRET_SALT`. 3. Set `SECRET_SALT` as an encrypted env
var and deploy. Never change the salt afterwards. Not verified on App Platform: TLS to the managed database
(`DB_SSL=1`, mysqlnd without a CA file does not verify the server).
