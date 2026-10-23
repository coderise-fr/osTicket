# Déploiement de support.coderise.fr (Droplet DigitalOcean)

Ce document décrit le déploiement réel du portail de support : ce qui a été choisi, pourquoi, comment
le refaire de zéro et comment l'exploiter. Les détails techniques de l'image (durcissement, plugins,
messagerie, restauration) sont dans [deploy/docker/README.md](../deploy/docker/README.md) ; la procédure pas à pas
du Droplet est dans [deploy/docker/droplet/README.md](../deploy/docker/droplet/README.md). Aucun secret n'est écrit ici.

## 1. Vue d'ensemble

```
GitHub (push sur main)
   │  workflow « docker-image »
   ▼
construit coderise/osticket:sha-xxxxxxx ──ssh (clé restreinte)──► Droplet 161.35.193.138
                                                                  │
                          Internet ─443→ Caddy (hôte, TLS) ─127.0.0.1:8080→ web ──réseau interne──► db (MariaDB)
                                                                           cron ──┘
```

| Élément | Choix |
| --- | --- |
| Serveur | Droplet DigitalOcean, Ubuntu 24.04, 1 vCPU, 1 Go de RAM, 24 Go de disque, région Europe |
| Domaine | `support.coderise.fr` (enregistrement A vers l'IP du Droplet) |
| Pile | Docker Compose : `web` (Apache + PHP 8.3), `cron`, `db` (MariaDB 11.4) |
| TLS | Caddy sur l'hôte, certificat Let's Encrypt automatique |
| Image | construite par GitHub Actions, envoyée par SSH au Droplet (pas de registry) |
| Données | volumes Docker nommés `ost-db` (base) et `ost-config` (`ost-config.php`) |
| Pare-feu | UFW sur le Droplet (22 limité, 80, 443) + pare-feu DigitalOcean (géré séparément) |
| Sauvegardes | locales, quotidiennes (03:15), 14 jours ; copie hors serveur à mettre en place |

### Décisions prises

- **Compose sur un Droplet plutôt qu'App Platform.** App Platform n'a pas de volumes et ne permet pas
  de choisir la cible de build ; la pile Compose (config en lecture seule, réseau `internal` pour la base,
  durcissement) est faite pour un hôte Docker. Un mode « config depuis l'environnement »
  (`OSTICKET_CONFIG_FROM_ENV=1`) reste disponible dans l'entrypoint mais n'est pas utilisé ici.
- **MariaDB/MySQL, pas PostgreSQL.** osTicket n'a qu'un pilote `mysqli` et un schéma MySQL ; aucun port
  PostgreSQL n'existe.
- **Pas de registry.** L'image est construite par la CI et transférée par `docker save | ssh … docker load`.
  Une registry DigitalOcean (`ownanalytics-registry`, puis `coderise-registry`) a été essayée puis abandonnée :
  quota de l'offre gratuite (500 Mo) et gestion de jetons en plus, pour un seul serveur.
- **Caddy sur l'hôte**, pas dans Compose : le conteneur `web` ne publie que `127.0.0.1:8080`.
- **Réglages 1 Go** (`compose.droplet.yaml`) : `web` 400 Mo avec 8 processus Apache, `cron` 128 Mo, MariaDB
  288 Mo (cache InnoDB 96 Mo, 30 connexions), swap de 2 Go, `vm.swappiness=10`.

## 2. Accès et secrets

| Quoi | Où | Remarque |
| --- | --- | --- |
| Accès administrateur | clé SSH ed25519 `~/.ssh/coderise_osticket` (poste de l'administrateur) | clé privée jamais copiée ailleurs |
| Utilisateur d'exploitation | `deploy` (groupe docker), mêmes clés autorisées que root | connexion root désactivable avec `provision.sh --harden-ssh` |
| Clé de la CI | clé ed25519 dédiée, ligne `restrict,command="…/ci-receive.sh"` dans `/home/deploy/.ssh/authorized_keys` | ne peut exécuter que `load` et `deploy sha-xxxxxxx` |
| Secrets GitHub | `DROPLET_HOST`, `DROPLET_SSH_KEY`, `DROPLET_KNOWN_HOSTS` | l'empreinte du serveur est vérifiée à chaque transfert |
| Mots de passe de la base | `/opt/osticket/secrets/db_password.txt`, `db_root_password.txt` (600) | générés par `scripts/gen-secrets.sh`, hors dépôt |
| Configuration osTicket | volume `ost-config` → `/etc/osticket/ost-config.php` | contient `SECRET_SALT` : à sauvegarder avec la base |

Règle de la CI : une clé qui fuit permet de charger une image arbitraire sur le Droplet. La révoquer aussitôt
(retirer la ligne `ci-deploy` de `authorized_keys`, régénérer la paire, mettre à jour le secret `DROPLET_SSH_KEY`).

## 3. Contenu du dépôt lié au déploiement

| Fichier | Rôle |
| --- | --- |
| `deploy/docker/Dockerfile` | cibles `runtime` (production), `install` (assistant) et `production` (dernier stage = runtime) |
| `deploy/docker/compose.yaml`, `compose.install.yaml` | pile, et surcouche d'installation (config inscriptible, assistant) |
| `deploy/docker/compose.droplet.yaml` | limites mémoire et réglages MariaDB pour 1 Go |
| `deploy/docker/droplet/provision.sh` | préparation du serveur (une fois) |
| `deploy/docker/droplet/ci-receive.sh` | commande forcée de la clé CI (`load`, `deploy`) |
| `deploy/docker/droplet/update.sh` | sauvegarde, démarrage sur un tag, retour arrière automatique, nettoyage des vieilles images |
| `deploy/docker/droplet/backup-local.sh` + `osticket-backup.{service,timer}` | sauvegarde quotidienne et rotation |
| `deploy/docker/droplet/Caddyfile`, `env.example` | proxy TLS, modèle du `.env` |
| `.github/workflows/docker-image.yml` | build et envoi de l'image |

## 4. Mise en place depuis zéro

1. **DNS** : A `support.coderise.fr` → IP du Droplet. **Pare-feu DigitalOcean** : 22, 80, 443.
2. **Serveur** (root) : `SUPPORT_DOMAIN=support.coderise.fr sh provision.sh`. Il applique les mises à jour,
   active les correctifs de sécurité automatiques, crée le swap, configure UFW, installe Docker et Caddy
   (Caddy reste **arrêté** pour ne pas exposer l'assistant), crée l'utilisateur `deploy`, clone
   `deploy/docker` dans `/opt/osticket`, génère le `.env` et les secrets de la base, active la minuterie de
   sauvegarde.
3. **Clé et secrets de la CI** : paire dédiée, ligne restreinte dans `authorized_keys`, secrets GitHub (§2).
4. **Images** : push sur `main`, ou lancement manuel du workflow avec `install` coché pour la première
   installation (envoie aussi `…-install`).
5. **Installation** (en `deploy`) : fixer `OST_VERSION=sha-xxxxxxx` dans `.env`, puis
   `docker compose … pull db` (MariaDB) puis `docker compose -f compose.yaml -f compose.droplet.yaml -f compose.install.yaml up -d --no-build --pull never db web`
   (`--pull never` : l'image osticket ne doit jamais être cherchée sur Docker Hub).
   Tunnel `ssh -L 8080:127.0.0.1:8080 deploy@<ip>` et assistant sur `http://127.0.0.1:8080/setup/`
   (base : hôte `db`, nom `osticket`, utilisateur `osticket`, mot de passe du fichier de secrets, préfixe
   `ost_`, identifiant administrateur différent de `admin`).
6. **Production** : arrêter et supprimer `web` d'installation, `docker compose up -d --no-build --pull never --wait`,
   `sudo systemctl enable --now caddy`, vérifier `https://support.coderise.fr`.
7. **Durcissement** : `sh provision.sh --harden-ssh` après avoir vérifié `ssh deploy@<ip>`.
8. **Réglages osTicket** (`/scp/`) : URL du helpdesk, e-mails (IMAP/SMTP), SLA, rubriques ; première
   sauvegarde manuelle (`droplet/backup-local.sh`).

## 5. Exploitation

| Besoin | Commande (en `deploy`, dans `/opt/osticket/deploy/docker`) |
| --- | --- |
| État | `docker compose ps` ; `docker stats --no-stream` ; `free -h` |
| Journaux | `docker compose logs -f web` (ou `cron`, `db`) ; `journalctl -u caddy` |
| Nouvelle version | CI : onglet Actions > docker-image > Run workflow, `deploy` coché (ou variable `AUTO_DEPLOY=true`) ; à la main : `./droplet/update.sh sha-xxxxxxx` |
| Retour arrière | automatique si les conteneurs ne deviennent pas sains ; sinon `./droplet/update.sh <ancien tag>` (4 images conservées) |
| Migration de schéma | après une mise à jour qui change `include/upgrader/streams/core.sig` : `/scp/upgrade.php` en administrateur |
| Sauvegarde | `./droplet/backup-local.sh` ; planifiée par `osticket-backup.timer` (`systemctl list-timers`) |
| Restauration | `scripts/restore.sh` (voir le README principal, section « Restauration ») |
| Rotation du certificat | rien à faire (Caddy) |

## 6. Points d'attention

- **Les sauvegardes locales ne protègent pas contre la perte du Droplet.** Copier régulièrement
  `/var/backups/osticket` et `/opt/osticket/secrets` hors du serveur, chiffrés (outil à choisir), et activer
  les sauvegardes/instantanés DigitalOcean en attendant.
- **Un seul serveur, pas de haute disponibilité.** 1 Go de RAM : surveiller le swap ; pour monter en charge,
  augmenter le Droplet puis `OSTICKET_MAX_WORKERS`, `mem_limit` et le cache InnoDB.
- **Image immuable** : plugins, packs de langue et thème se déploient par `deploy/docker/overlay/` et une
  nouvelle image, jamais depuis l'interface d'administration.
- **CVE de l'image de base** : reconstruire régulièrement (la CI relance `apt-get upgrade` à chaque build sans
  cache) et scanner.
- **Jetons exposés** : le jeton DigitalOcean communiqué en conversation pendant la mise en place de la registry
  est à révoquer s'il ne l'est pas déjà ; le secret GitHub `DIGITALOCEAN_ACCESS_TOKEN` n'est plus utilisé.

## 7. État au 23/10/2026

| Étape | État |
| --- | --- |
| Serveur préparé (système, UFW, swap, Docker, Caddy installé) | fait |
| Dépôt cloné, `.env`, secrets de la base, minuterie de sauvegarde | fait |
| CI : build et envoi de l'image par SSH (testé de bout en bout) | fait |
| Image d'installation sur le Droplet, base et assistant démarrés (`http://127.0.0.1:8080/setup/` en local sur le Droplet) | fait |
| Installation d'osTicket, mise en production, TLS (Let's Encrypt), première sauvegarde | fait (23/10/2026, `https://support.coderise.fr`) |
| Durcissement SSH (`--harden-ssh`) | à faire |
| Copie des sauvegardes hors serveur | à faire |
