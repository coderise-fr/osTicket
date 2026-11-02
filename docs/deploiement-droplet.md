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
7. **Durcissement** : `provision.sh` donne à `deploy` un `sudo` sans mot de passe (`/etc/sudoers.d/deploy`).
   Après avoir vérifié `ssh deploy@<ip>`, lancer `sudo SUPPORT_DOMAIN=support.coderise.fr sh provision.sh --harden-ssh`
   (le script refuse sans ce `sudo`). Une fois root fermé en SSH, il n'est plus joignable que par la console de récupération.
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
- **Jetons** : le jeton DigitalOcean de la registry abandonnée a été retiré du Droplet ; le révoquer côté
  DigitalOcean et supprimer le secret GitHub `DIGITALOCEAN_ACCESS_TOKEN` s'ils ne le sont pas déjà.

## 7. État au 02/11/2026

| Étape | État |
| --- | --- |
| Serveur préparé (système, UFW, swap, Docker, Caddy) | fait |
| Installation d'osTicket, production, TLS (Let's Encrypt, expire le 21/01/2027), HSTS, cookies `secure` | fait |
| CI : build et envoi de l'image par SSH ; image en service `sha-5032310` (`origin/main`), `sha-40c46cd` gardée pour le retour arrière | fait |
| Durcissement SSH (root et mots de passe refusés), `sudo` sans mot de passe pour `deploy` | fait |
| Plugins `sla_first_response` 0.4 et `stripe-sync` 0.2 dans l'image, installés et actifs | fait (Stripe jamais éprouvé, webhook non configuré) |
| Sauvegardes quotidiennes locales, instantané DigitalOcean, sonde externe | fait (par Julien) |
| Nettoyage des images Docker (2 Go récupérés, 02/11/2026) | fait |
| Envoi d'e-mails (Brevo) | **à faire** : compte SMTP inactif, authentification refusée |
| Réception d'e-mails (Microsoft 365) | **à faire** : OAuth2 ou relais IMAP à décider |
| Structure osTicket (départements, rubriques, formulaire, SLA, calendrier, anti-spam) | fait le 02/11/2026, voir [configuration-osticket.md](configuration-osticket.md) |
| Redémarrage du serveur (noyau mis à jour) | **à faire** |

Détail des écarts constatés : [rapport-production-2026-11-02.md](rapport-production-2026-11-02.md).

## 8. Messagerie : pièges constatés

- **DigitalOcean bloque le SMTP sortant** sur les ports 25, 465 et 587 des Droplets ; 2525 et 443 passent (testé
  depuis l'hôte et depuis le conteneur). Brevo accepte le port 2525.
- osTicket ne déduit le chiffrement que pour les ports 465, 993, 995 (SSL) et 587 (STARTTLS) : sur 2525, préfixer l'hôte
  par `tls://` (`tls://smtp-relay.brevo.com`, port `2525`), sinon les identifiants partiraient sans chiffrement
  (`include/class.mail.php`).
- Le champ « Hôte » ne doit contenir que le nom du serveur (pas de login `@…`, pas de `:port`, pas d'espace) : sinon
  « The input … cannot match against hostname schema for TLD 'COM' ».
- Le login SMTP de Brevo (`…@smtp-brevo.com`) n'est pas l'e-mail du compte, et la clé SMTP (`xsmtpsib-…`) n'est pas une clé d'API.
- Les MX de `coderise.fr` sont chez Microsoft 365 : IMAP en mot de passe simple refusé ; OAuth2 demande le plugin
  OAuth2 d'osTicket (`.phar`, à placer dans `deploy/docker/overlay/include/plugins/` avec l'exception `.gitignore` et
  `Dockerfile.dockerignore`) et une inscription d'application Entra.
