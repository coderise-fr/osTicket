# Déploiement sur un Droplet DigitalOcean (Compose + Caddy)

Cible : 1 vCPU, 1 Go de RAM, Ubuntu 24.04. Pile `web` + `cron` + MariaDB (Compose), Caddy sur
l'hôte pour le TLS, image publiée dans GHCR par GitHub Actions. Le détail du durcissement, des
sauvegardes et des mises à jour est dans [../README.md](../README.md).

```
Internet ─443→ Caddy (hôte) ─127.0.0.1:8080→ web ─réseau interne→ db
                                              cron ─┘
```

## 0. Avant de commencer

- Enregistrement DNS `support.coderise.fr` (A) vers l'IP du Droplet (Caddy en a besoin pour le certificat).
- Pare-feu DigitalOcean : 22, 80, 443 (UFW est aussi configuré sur le Droplet).
- Clé SSH ed25519 ajoutée au Droplet (`ssh-keygen -t ed25519 -f ~/.ssh/coderise_droplet`).
- Une première exécution réussie du workflow **docker-image** (onglet Actions de GitHub, après un push
  sur `main`) : elle publie `ghcr.io/coderise-fr/osticket:sha-<7 caractères>` et `…-install`.
- Un jeton GitHub `read:packages` (PAT classique) pour que le Droplet puisse tirer l'image (les
  paquets GHCR sont privés par défaut ; sinon rendre le paquet public).

## 1. Préparer le serveur (une fois, en root)

```sh
ssh -i ~/.ssh/coderise_droplet root@<ip>
curl -fsSLO https://raw.githubusercontent.com/coderise-fr/osTicket/main/deploy/docker/droplet/provision.sh
SUPPORT_DOMAIN=support.coderise.fr sh provision.sh
```

Le script : mises à jour et correctifs de sécurité automatiques, swap de 2 Go, UFW (22/80/443),
Docker, Caddy (installé mais **arrêté**), utilisateur `deploy`, checkout de `deploy/docker` dans
`/opt/osticket`, `.env`, secrets de la base (`/opt/osticket/secrets`), minuterie de sauvegarde
quotidienne. Dépôt privé : le clone échoue avec un avertissement, clonez-le alors avec une clé de
déploiement ou un jeton.

Ouvrez **une deuxième session** `ssh deploy@<ip>`. Si elle fonctionne, relancez
`sh provision.sh --harden-ssh` (désactive la connexion root et les mots de passe SSH).

## 2. Installer (en `deploy`)

```sh
cd /opt/osticket/deploy/docker
echo "<PAT read:packages>" | docker login ghcr.io -u <votre-compte-github> --password-stdin

# Tag du build GitHub Actions (onglet Actions > résumé du run, ou Packages)
sed -i 's|^OST_VERSION=.*|OST_VERSION=sha-abc1234|' .env

docker compose -f compose.yaml -f compose.droplet.yaml -f compose.install.yaml pull web
docker compose -f compose.yaml -f compose.droplet.yaml -f compose.install.yaml up -d db web
```

L'assistant n'écoute que sur le Droplet : depuis votre poste, ouvrez un tunnel puis
`http://127.0.0.1:8080/setup/`.

```sh
ssh -L 8080:127.0.0.1:8080 deploy@<ip>
```

Base : hôte `db`, nom `osticket`, utilisateur `osticket`, mot de passe = contenu de
`/opt/osticket/secrets/db_password.txt`, préfixe `ost_`. Identifiant administrateur différent de
`admin`. (Si la connexion boucle à cause du cookie `Secure`, mettez temporairement
`OSTICKET_COOKIE_SECURE=0` dans `.env`, puis remettez `1`.)

## 3. Passer en production

```sh
docker compose -f compose.yaml -f compose.droplet.yaml -f compose.install.yaml stop web
docker compose -f compose.yaml -f compose.droplet.yaml -f compose.install.yaml rm -f web
docker compose up -d --no-build --wait          # web + cron + db, config en lecture seule
sudo systemctl enable --now caddy               # TLS : https://support.coderise.fr
docker compose ps                               # trois conteneurs « healthy »
```

Ensuite, réglages dans `/scp/` : URL du helpdesk, e-mails (IMAP/SMTP, voir README principal),
SLA, rubriques. Faites une **première sauvegarde** à la main :
`./droplet/backup-local.sh`.

## 4. Mettre à jour (à chaque nouvelle image)

Un push sur `main` construit `sha-xxxxxxx` dans GitHub Actions, puis sur le Droplet :

```sh
cd /opt/osticket/deploy/docker && git pull --ff-only
./droplet/update.sh sha-xxxxxxx
```

`update.sh` sauvegarde, tire l'image, redémarre et **revient automatiquement au tag précédent** si
les conteneurs ne deviennent pas sains. Si le schéma de la base a changé, connectez-vous ensuite à
`/scp/` (bandeau « System upgrade is pending » > `/scp/upgrade.php`).

## 5. Sauvegardes

- Quotidienne à 03:15 (`osticket-backup.timer`) vers `/var/backups/osticket`, 14 jours conservés :
  `systemctl list-timers osticket-backup.timer`, `journalctl -u osticket-backup`.
- Une copie locale ne protège pas contre la perte du Droplet : copiez régulièrement
  `/var/backups/osticket` **et** `/opt/osticket/secrets` hors du serveur (chiffrés). En attendant
  l'outil prévu, activez les sauvegardes DigitalOcean du Droplet.
- Restauration : section « Restauration » du README principal (`scripts/restore.sh`).

## 6. Réglages pour 1 vCPU / 1 Go (`compose.droplet.yaml`)

| Élément | Valeur |
| --- | --- |
| `web` | 400 Mo, 8 processus Apache (`OSTICKET_MAX_WORKERS`) |
| `cron` | 128 Mo |
| MariaDB | 288 Mo, cache InnoDB 96 Mo, 30 connexions, `performance_schema` désactivé |
| Hôte | swap 2 Go, `vm.swappiness=10` |

Pour agrandir le Droplet : augmenter ces valeurs (`OSTICKET_MAX_WORKERS` dans `.env`, `mem_limit` et
`--innodb-buffer-pool-size` dans `compose.droplet.yaml`).

## Dépannage

- `docker compose pull` : `unauthorized` → refaire `docker login ghcr.io`.
- Caddy n’obtient pas le certificat → DNS non propagé, ou 80/443 fermés (UFW **et** pare-feu DO).
- `web` redémarre → `docker compose logs web` (le point d'entrée refuse de démarrer si la config est
  inscriptible ou absente).
- Mémoire : `free -h`, `docker stats --no-stream`.
