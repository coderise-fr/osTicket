# osTicket : déploiement Docker de production (Coderise)

Image durcie, pile `web` + `cron` + MariaDB, construite **depuis ce dépôt** (le commit
courant), sans télécharger d'archive de release. Pour un portail de support derrière un
reverse proxy TLS, par exemple `support.coderise.fr`.

## Contenu

| Fichier | Rôle |
|---|---|
| `Dockerfile` | Image `runtime` (production) et `install` (assistant d'installation) |
| `compose.yaml` | Pile de production : `web`, `cron`, `db` |
| `compose.install.yaml` | Surcharge pour la **première installation** seulement (les mises à jour n'en ont pas besoin) |
| `conf/apache2.conf`, `conf/osticket.ini` | Configuration Apache et PHP durcie |
| `docker-entrypoint.sh`, `healthcheck.php` | Démarrage (échec si la configuration n'est pas sûre) et contrôle de santé |
| `scripts/build.sh` | Construit les images depuis `git archive` (commit exact) |
| `scripts/gen-secrets.sh` | Génère les mots de passe de la base |
| `scripts/backup.sh`, `scripts/restore.sh` | Sauvegarde (SQL + volume de configuration) et restauration sur une pile vierge |
| `overlay/` | Thème, packs de langue et plugins superposés à osTicket (voir son `README.md`) |
| `scripts/local-demo.sh`, `local-demo/` | Environnement local de **démonstration** (jamais en production) : voir la section du même nom |
| `plugins.env` (non versionné) | Variables d'environnement des plugins, par exemple le secret du webhook Stripe |

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
| Fichiers du dépôt | `docs/` (maquettes, documentation) et `contrib/` (code source des plugins) sont exclus du contexte de build (`Dockerfile.dockerignore`) et supprimés du stade final : ils ne sont jamais servis par Apache. Les plugins ne passent que par `overlay/` (voir « Plugins et personnalisation ») |
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

Vérifié le 21/10/2026 (tests exécutés, détails dans les sections correspondantes) :

- **Restauration** d'une sauvegarde sur une pile vierge (données, pièce jointe, configuration,
  relève IMAP avec mot de passe chiffré, création de ticket) : voir « Restauration ».
- **Mise à jour** d'osTicket avec migration du schéma (v1.17.8 vers la version du dépôt),
  mise à jour sans migration, retour arrière des deux types, et personnalisation `overlay/`
  (feuille de style, plugin) qui survit aux mises à jour : voir « Mises à jour ».
- **Installation des deux plugins `contrib/`** par `overlay/` sur l'image durcie (chargement,
  activation, accès au secret) : voir « Plugins ». Pas de test fonctionnel Stripe ni SLA.
- **Relève IMAP et envoi SMTP** contre un serveur de messagerie **de test local** (GreenMail) :
  voir « Messagerie ».
- **PHP 8.3** (image construite avec `PHP_IMAGE=php:8.3-apache-bookworm`) : installation par
  l'assistant, 44 pages d'administration, PDF, pièce jointe, extension `imap`. PHP 8.4 : voir
  « Versions supportées ».

### Non vérifié

- La réception et l'envoi d'e-mails de bout en bout avec **votre** serveur de messagerie
  (check-list dans « À tester par vous »), TLS vers le serveur de messagerie.
- Le comportement derrière votre reverse proxy réel (en-têtes, HSTS, cookies `Secure`).
- Les pièces jointes volumineuses (au-delà de 300 Ko testés). Le pack de langue français n'est
  vérifié que sur la démo locale (portail, panneau agent, administration ; pas les e-mails
  envoyés en français) ; aucun autre pack.
- Le fonctionnement réel des plugins (événements Stripe réels, SLA sur de vrais échanges).
- La mise à jour sur App Platform.

### Versions supportées

| Composant | Testé | Remarques |
|---|---|---|
| PHP | **8.3 (image par défaut, décision du 22/10/2026)**, 8.2 testé | On reste en 8.3. **PHP 8.4 et 8.5 échouent à la construction** : les sources de PHP n'embarquent plus l'extension `imap` (le dossier `ext/imap` est vide, sans `config.m4`, constaté le 22/10/2026 sur 8.4 et 8.5) ; il faudrait la compiler depuis PECL. Ne passer à 8.5 que si `imap` y est de nouveau livrée ou si la compilation PECL est validée. PHP 8.2 n'est plus maintenu après le 31/12/2026 (à reverifier sur php.net). |
| MariaDB | 11.4 | Base de données de la pile (LTS). MySQL non testé. |
| osTicket | v1.18.x (tête de `develop`) ; migration depuis v1.17.8 | La version affichée est `1.18-git` (les builds ne portent pas le numéro de release). |
| Debian | bookworm | trixie : `libc-client-dev` n'existe plus, la construction échoue. |

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
- **Page « Information » de l'administration** (Tableau de bord > Information, `scp/system.php`) :
  erreur fatale `Call to undefined function shell_exec()`, car `shell_exec` est désactivée.
  Constaté sur PHP 8.2 et 8.3 ; sans conséquence pour le reste de l'administration.
- **Requêtes `DELETE` refusées (403 d'Apache).** `conf/apache2.conf` (bloc `<Directory "/var/www/html">`,
  `Require method GET POST HEAD`, en place depuis le commit `6e7f290d`) refuse toute requête `DELETE`,
  or osTicket en envoie par AJAX. Fonctions cassées dans cette image :
  - suppression d'un **brouillon** depuis l'éditeur (« Supprimer le brouillon », `js/redactor-osticket.js`
    vers `scp/ajax.php` `draft/<id>` et, côté portail client, `ajax.php` `draft/<id>`) : **constaté** le
    24/10/2026 sur la démo locale (`DELETE /scp/ajax.php/draft/<id>` → 403, le brouillon reste) ;
  - suppression d'une **organisation** (Agents > Organisations > Supprimer :
    `include/staff/templates/org-delete.tmpl.php`, formulaire `method="delete"` envoyé par
    `scp/js/scp.js` vers `scp/ajax.php` `orgs/<id>/delete`) ;
  - suppression d'une **note rapide** d'utilisateur ou d'organisation (`scp/js/scp.js` vers
    `scp/ajax.php` `note/<id>`) ;
  - suppression d'une **réponse de formulaire** (`scp/ajax.php` `form/answer/<entry>/<field>`) ;
  - suppression d'une **recherche enregistrée** (`scp/ajax.php` `tickets/search/<id>`).
  Seule la suppression de brouillon a été constatée ; les autres se déduisent du code (routes
  `url_delete` des deux routeurs AJAX). Correctif possible, **non appliqué ici** et à décider puis
  tester à part : autoriser `DELETE` uniquement sur les routeurs AJAX, par exemple
  `<LocationMatch "^/(scp/)?ajax\.php/">` avec `Require method GET POST HEAD DELETE` (osTicket vérifie
  déjà le jeton CSRF des `DELETE` : `scp/staff.inc.php` et `client.inc.php`). Contournement en
  attendant : supprimer une organisation ou un brouillon reste impossible depuis l'interface.
- **Pas de `sendmail`** dans l'image : osTicket n'envoie des e-mails que par le SMTP configuré
  dans l'administration (Emails > adresse > SMTP). Sans SMTP, le journal système affiche
  « Unable to email via Sendmail ».
- **Fins de ligne Windows.** Ne construisez jamais depuis un arbre de travail Windows
  (`core.autocrlf`) : utilisez `scripts/build.sh`.

## Mises à jour (procédure testée le 21/10/2026)

**Principe.** L'image est immuable : mettre à jour = construire une nouvelle image (nouveau
commit) et la déployer. La base n'est migrée que si le schéma a changé, depuis l'administration
(`/scp/upgrade.php`), **avec l'image de production**. L'image `install` et l'assistant `/setup/`
ne servent plus (`setup/upgrade.php` redirige d'ailleurs vers `/scp/upgrade.php`) : le volume de
configuration reste en lecture seule pendant toute la mise à jour. Le schéma a changé si et
seulement si `include/upgrader/streams/core.sig` diffère entre l'ancienne et la nouvelle version.

Depuis `deploy/docker/`, dans cet ordre :

1. **Sauvegarder** et noter l'image en service :
   `./scripts/backup.sh /chemin/hors/serveur` ; `docker compose images` (le tag, par exemple `1.18.4`).
   Vérifier que la sauvegarde existe avant d'aller plus loin : le retour arrière en dépend.
2. **Intégrer la nouvelle version dans le dépôt de la recette.** Les tags amont d'osTicket ne
   contiennent pas `deploy/docker/` : fusionner le tag (`git merge v1.18.5`) dans la branche du
   fork qui porte `deploy/docker/` et `overlay/`, **committer** (l'image est construite depuis le
   commit `HEAD`, pas depuis l'arbre de travail), puis comparer les signatures :
   `git diff <ancien>..HEAD --stat -- include/upgrader/streams/core.sig` (vide = pas de migration).
3. **Choisir un nouveau tag d'image** dans `.env` : `OST_VERSION=1.18.5` (ou `1.18.4-20261105` pour
   une reconstruction sans changement d'osTicket). Ne jamais réutiliser l'ancien tag : l'ancienne
   image doit rester disponible pour le retour arrière (ne pas lancer `docker image prune` avant
   d'avoir validé la mise à jour).
4. **Construire** : `./scripts/build.sh build --pull`. Pour appliquer aussi les correctifs
   Debian parus depuis la dernière construction, ajouter `--no-cache` : sinon la couche
   `apt-get upgrade` est réutilisée du cache et **aucun correctif système n'est pris**
   (constaté : la construction suivante est quasi instantanée). Puis scanner
   (`docker scout cves --only-severity critical,high local://coderise/osticket:<tag>`).
5. **Déployer** : `docker compose up -d --wait`. Compose recrée `web` et `cron` avec la nouvelle
   image et laisse la base en place (une reconstruction sous le même tag est aussi détectée, mais
   voir l'étape 3). Dès cet instant, si le schéma a changé, le portail client affiche
   « Support Ticket System Offline » et le relevé des e-mails est suspendu jusqu'à l'étape 6.
6. **Migrer la base** (seulement si la signature a changé) : se connecter à `/scp/` en
   administrateur ; le bandeau « System upgrade is pending » mène à `/scp/upgrade.php` ;
   « Start Upgrade Now », puis « Upgrade Now » ; attendre « Upgrade Completed! » (une dizaine de
   secondes pour 1.17.8 vers 1.18). Le portail repasse en ligne ; osTicket crée un ticket
   « osTicket Upgraded! ». Sans migration, cette page répond « Nothing to do! ».
7. **Vérifier** : page de connexion client et agent, un ticket avec sa pièce jointe, Admin > Emails
   (boîte active, pas d'erreur), le journal système (Admin > Tableau de bord > Journaux),
   `docker compose ps` (trois conteneurs `healthy`), plugins actifs (voir « Plugins »).
8. Quand tout est validé : conserver la sauvegarde de l'étape 1 quelques jours, puis supprimer
   les anciennes images.

### Retour arrière

- **Aucune migration appliquée** (même signature de schéma) : remettre l'ancien `OST_VERSION` dans
  `.env` et `docker compose up -d --wait`. Testé : retour sur l'image précédente, données intactes.
- **Migration appliquée** : l'ancienne image ne sait pas lire le nouveau schéma ; il faut revenir à
  la sauvegarde de l'étape 1 sur une pile vierge, avec l'ancien tag :

  ```sh
  # .env : OST_VERSION=<ancien tag>
  docker compose down -v          # DÉTRUIT la base et le volume de configuration actuels
  ./scripts/restore.sh /chemin/osticket-<horodatage>.sql /chemin/osticket-config-<horodatage>.tar
  ```

  Testé (v1.17.8 / schéma `83a22ba2`, retour depuis la version migrée) : l'empreinte SQL de la
  base est identique à celle d'avant la mise à jour. **Les tickets créés depuis la sauvegarde sont
  perdus** : si c'est un problème, exporter d'abord la base actuelle avec `backup.sh`.
- Un plugin retiré de `overlay/` alors qu'il est installé reste listé « defunct — missing » :
  sans danger, à désinstaller depuis l'administration.

### Ce qui a été testé

Pile complète (web, cron, MariaDB 11.4, serveur de messagerie de test) avec un ticket et sa pièce
jointe de 300 000 octets, une boîte IMAP/SMTP configurée (mot de passe chiffré avec `SECRET_SALT`),
une clé d'API, des tickets ouverts par e-mail et par l'API :

| Scénario | Résultat |
|---|---|
| v1.17.8 vers la tête de `develop` (schéma `83a22ba2` vers `5fb92bef`, patch « v1.18: Modify Plugin / Instance name to varchar(255) ») | Migration par `/scp/upgrade.php` avec l'image `runtime`, assistant d'installation absent. Données identiques (empreinte : seuls le ticket « osTicket Upgraded! », la signature du schéma et la configuration changent). Pièce jointe identique octet pour octet. La relève IMAP (mot de passe chiffré conservé), l'API et la création de ticket fonctionnent après la migration. |
| Nouvelle image sans changement de schéma | `up -d` suffit ; `/scp/upgrade.php` répond « Nothing to do! » |
| Reconstruction sous le même tag | `up -d` recrée les conteneurs (le changement d'identifiant d'image est détecté) |
| Retour arrière sans migration | Ancienne image, données intactes |
| Retour arrière après migration | Restauration de la sauvegarde sur pile vierge : empreinte identique à celle d'avant |
| Migration rejouée avec une session neuve, par requêtes HTTP | Réussie (la connexion agent reste possible pendant que la migration est en attente) |
| `overlay/` : feuille de style `assets/default/css/theme.css` et plugin | Servis après la mise à jour ; l'installation du plugin (en base) survit à la migration et aux reconstructions suivantes |

Non testé : la mise à jour sur App Platform (même principe : redéployer, puis `/scp/upgrade.php`
sur l'application déployée ; la sauvegarde y est celle de la base gérée), une mise à jour de
MariaDB (changer le digest de `mariadb` dans `compose.yaml` : rester dans la série 11.4, sauvegarder
avant ; `MARIADB_AUTO_UPGRADE=1` migre les tables au démarrage).

## Sauvegarde

```sh
./scripts/backup.sh [dossier]      # défaut : ./backups (ignoré par Git)
```

Produit `osticket-<horodatage>.sql` (export cohérent `--single-transaction`, la pile peut tourner) et
`osticket-config-<horodatage>.tar` (volume `/etc/osticket` : `ost-config.php` avec le mot de passe de la
base et `SECRET_SALT`, plus les fichiers secrets de plugins). Le script vérifie que l'export est complet
(`Dump completed`) et que l'archive contient `ost-config.php`.

- **Les pièces jointes sont dans la base** (stockage « Base de données » d'osTicket, par défaut) :
  l'export SQL les contient. Si vous passez à un stockage par plugin (fichiers, S3), sauvegardez aussi ce stockage.
- **Base et configuration vont ensemble** : les mots de passe des boîtes e-mail sont chiffrés avec
  `SECRET_SALT`, et les liens de téléchargement des pièces jointes sont signés avec lui.
  Une base restaurée avec une autre configuration ne peut plus relire ces mots de passe (à ressaisir).
- Il faut aussi `secrets/db_password.txt` (le mot de passe de la base est écrit dans `ost-config.php`) :
  ne pas régénérer les secrets entre la sauvegarde et la restauration.
- Les commandes équivalentes à la main (depuis `deploy/docker/`) sont dans le script. Avec Git Bash sous
  Windows, préfixer la commande `tar` de `MSYS_NO_PATHCONV=1` (sinon `/etc/osticket` est réécrit en chemin Windows).
- Conserver ces fichiers et le dossier `secrets/` hors du serveur, chiffrés. Ils contiennent les tickets,
  les hachages de mots de passe et les secrets : mêmes précautions que la production.

## Restauration

```sh
# Sur une pile VIERGE : mêmes secrets/, image du même tag (ou plus récente, puis migration), aucun volume
docker compose down -v                       # seulement pour repartir d'une pile existante
./scripts/restore.sh osticket-<horodatage>.sql osticket-config-<horodatage>.tar
```

Le script refuse de s'exécuter si le projet Compose a déjà des conteneurs ou des volumes. Il démarre la
base seule, charge l'export SQL, crée le volume de configuration et y décompresse l'archive (conteneur
ponctuel : `docker compose run -v` ne peut pas surcharger le montage en lecture seule du service), puis
démarre `web` et `cron`.

Testé le 21/10/2026 (pile détruite avec `down -v`, puis restaurée) :

- empreinte SQL identique avant et après (tickets, utilisateurs, fils, fichiers, blocs de fichier, clés
  d'API, configuration, signature du schéma) ;
- `ost-config.php` identique (SHA-256), propriétaire `33:33`, droits `0640`, volume monté en lecture seule ;
- pièce jointe de 300 000 octets retéléchargée via son lien signé : SHA-256 identique ;
- connexion agent, 7 pages d'administration, `web`/`cron`/`db` en `healthy` ;
- relève IMAP d'un nouveau message par `cron` avec le mot de passe de la boîte restauré : le ticket
  est créé (donc `SECRET_SALT` restauré correctement) ;
- création d'un ticket par l'API après restauration.

Ce test est rejouable à tout moment (pile jetable, nom et port aléatoires, tout est supprimé à la fin) :
`./scripts/test-restore.sh` (HEAD doit être committé ; quelques minutes, plus longues si la construction n'est pas en cache). À rejouer avant chaque
changement de la recette et, par exemple, une fois par trimestre (voir « Veille »).

Constaté en l'écrivant : une **clé d'API est liée à l'adresse source** de l'appelant ; sur un réseau Docker
recréé, la passerelle (adresse vue par `web` pour les requêtes venues de l'hôte) peut changer de sous-réseau.
Après une restauration, vérifier l'adresse des clés (Admin > Gérer > API) si des appelants passent par là.
Une ligne de la configuration (`mysqlsearch/reindex`) change toute seule après démarrage : normal.

Détails constatés : le volume de configuration restauré n'existe qu'après `docker compose create`
(le script s'en charge) ; sous Git Bash, `/etc/osticket` est réécrit en chemin Windows si
`MSYS_NO_PATHCONV=1` n'est pas positionné pour la commande `tar` (les scripts le font).

## Plugins et personnalisation (`overlay/`)

Testé le 21/10/2026 sur l'image durcie (utilisateur `www-data`, racine en lecture seule,
`open_basedir`, `disable_functions`) avec les deux plugins de `contrib/` (issues #1 et #2, en
attente) et un plugin factice. **Installation et chargement seulement** : aucun événement Stripe réel,
aucun SLA évalué sur de vrais échanges.

### Emplacement exact

| Plugin | Source | Dossier dans l'overlay |
|---|---|---|
| Stripe sync | `contrib/stripe-sync/` | `overlay/include/plugins/stripe-sync/` : `plugin.php`, `stripe-sync.php`, `include/` ; **sans** `tests/`, `README.md`, `.gitattributes` |
| SLA : première réponse | `contrib/sla-first-response/plugin/sla_first_response/` | `overlay/include/plugins/sla_first_response/` : `plugin.php`, `sla_first_response.php` |

```sh
# depuis la racine du dépôt
O=deploy/docker/overlay/include/plugins
mkdir -p "$O"
cp -r contrib/stripe-sync "$O/stripe-sync"
rm -rf "$O/stripe-sync/tests" "$O/stripe-sync/README.md" "$O/stripe-sync/.gitattributes"
cp -r contrib/sla-first-response/plugin/sla_first_response "$O/"
git add deploy/docker/overlay && git commit -m "overlay: plugins stripe-sync et sla_first_response"
./deploy/docker/scripts/build.sh build        # puis déploiement : voir « Mises à jour »
```

L'image est construite depuis le commit : un fichier non committé n'y est pas. Les `.phar` et les
`.htaccess` sont ignorés par le `.gitignore` du dépôt (les plugins sous forme de dossier ne le sont pas).
Un plugin ou un pack de langue au format `.phar` demande une exception étroite pour **ce seul chemin**
dans `.gitignore` et dans `Dockerfile.dockerignore` (sans retirer les lignes `*.phar` / `**/*.phar`),
comme pour le pack français (voir `overlay/README.md`).

### Pack de langue français

Le pack officiel `fr.phar` (osTicket 1.18.x, build v1.18.2 du 04/02/2025, SHA-256 et provenance dans
`overlay/README.md`) est committé dans `overlay/include/i18n/fr.phar` et donc présent dans l'image.
Code de langue osTicket : **`fr`**. Activation (en base, une fois par installation) : Admin >
Paramètres > Système > « Langue principale » = Français ; garder l'anglais en langue secondaire
si l'on veut le proposer aux clients ; la langue d'un agent se règle dans son profil. Les contenus
déjà en base (rubriques, départements, statuts, réponses types, pages, modèles d'e-mails) restent dans
la langue de l'installation : voir `docs/guide-utilisateur.md`, « Interface en français ».
**À chaque mise à jour mineure d'osTicket**, télécharger le pack de la nouvelle version, remplacer le
fichier, mettre à jour son SHA-256 dans `overlay/README.md`, committer et reconstruire.

**Passage en production.** L'overlay committé livre le thème du panneau agent `coderise-agent` et
`fr.phar`. Le pack reste inactif tant qu'un administrateur n'a pas réglé la langue principale (et la
langue des agents dans leur profil). Avant la mise en service, lire « Requêtes `DELETE` refusées »
dans « Limites connues » : la suppression des brouillons, des organisations, des notes rapides, des
réponses de formulaire et des recherches enregistrées ne fonctionne pas avec la configuration Apache
actuelle (correctif non appliqué, à décider et tester à part).

### Permissions et exécution (constatées)

- Les fichiers du plugin appartiennent à `root` (dossiers `0755`, fichiers `0644`) et sont
  **en lecture seule** pour `www-data` : suffisant, les deux plugins n'écrivent jamais dans l'arbre
  (leur état est en base : tables `ost_plugin*`, `ost_config`). `open_basedir` couvre
  `/var/www/html` : aucun obstacle.
- Une fois les dossiers dans l'image, la page Admin > Gérer > Plugins > Ajouter les propose (« Install ») :
  elle détecte les dossiers sous `include/plugins/` et affiche `ost_version` (1.18 pour Stripe sync : accepté).
- Un plugin retiré de l'overlay alors qu'il est installé apparaît « defunct — missing ».

### Activation (administration, une seule fois par base)

1. Admin > Gérer > Plugins > **Ajouter un plugin** > « Install » en face de chaque plugin.
2. Ouvrir le plugin installé, régler **Statut = Actif** (il est « Désactivé » après l'installation),
   enregistrer.
3. **Ajouter une instance** (lien « Add New Instance ») : un nom, statut **Activé**, réglages.
   C'est l'instance activée qui fait démarrer le plugin (comportement d'osTicket).
   - Stripe sync : les valeurs par défaut suffisent à charger le plugin ; renseigner les
     identifiants de prix Priority plus tard.
   - SLA première réponse : demande **deux plans de SLA existants** (créer d'abord le plan surveillé
     et le plan « réponse faite », ce dernier **désactivé** et **non transitoire** ; le plugin
     refuse l'instance sinon, d'après son code, non essayé). Testé avec le plan « Default SLA » (n° 1)
     et un second plan désactivé, non transitoire (n° 2).
   - Un plugin sans classe de configuration (`$config_class`) fait échouer ce formulaire (erreur
     fatale) : les deux plugins de `contrib/` en ont une.
4. Contrôle : `POST /api/stripe-sync/webhook` répond `503 Not configured` sans secret, `400 Invalid
   signature` avec un secret mais sans signature valide (preuve que le plugin est chargé et lit son
   secret). Le routeur `api/` fonctionne sous Apache durci (`api/.htaccess`, `AllowOverride FileInfo`).

### Accès au secret du webhook Stripe

Trois emplacements essayés. `open_basedir` n'autorise que `/var/www/html`, `/etc/osticket`, `/tmp`
et quelques bibliothèques : un fichier ailleurs est refusé (« open_basedir restriction in effect »).

| Méthode | Résultat | Remarques |
|---|---|---|
| Fichier sous `/etc/osticket/` + `STRIPE_SYNC_WEBHOOK_SECRET_FILE` | **Fonctionne** (recommandé) | Absent de `docker inspect` et de l'environnement du processus ; sauvegardé avec le volume de configuration ; lecture seule pour `www-data` |
| Variable `STRIPE_SYNC_WEBHOOK_SECRET` dans `plugins.env` | **Fonctionne** | Visible par `docker inspect` et par quiconque accède au démon Docker ; `plugins.env` : droits `0600`, jamais versionné |
| Secret Docker monté dans `/run/secrets/` | **Refusé par `open_basedir`** | Il faudrait ajouter `/run/secrets` à `open_basedir` (`conf/osticket.ini`) : non fait, par prudence |

Écrire le fichier secret dans le volume de configuration (monté en lecture seule dans `web`, donc via
un conteneur ponctuel ; remplacer `<tag>` par `OST_VERSION` et `osticket_ost-config` par
`<OST_PROJECT>_ost-config` ; le secret `whsec_…` vient du tableau de bord Stripe) :

```sh
printf '%s' 'whsec_...' | MSYS_NO_PATHCONV=1 docker run --rm -i --network none --cap-drop ALL \
  --security-opt no-new-privileges --user 33:33 -v osticket_ost-config:/etc/osticket \
  --entrypoint sh coderise/osticket:<tag> -c 'umask 0227 && cat > /etc/osticket/stripe-sync-webhook-secret'
echo 'STRIPE_SYNC_WEBHOOK_SECRET_FILE=/etc/osticket/stripe-sync-webhook-secret' > plugins.env
docker compose up -d --wait web        # recrée web avec la variable
```

Le fichier est créé avec les droits `0440` (propriétaire `33`). `compose.yaml` lit `plugins.env` s'il
existe (`env_file` facultatif) pour `web` seulement ; `cron` n'en a pas besoin (le webhook est servi par
`web`). Les autres variables du plugin (`STRIPE_SYNC_PRIORITY_PRICE_IDS`, etc.) se mettent dans le même fichier.

Pour App Platform, les variables d'environnement du plugin se déclarent dans `app-spec.yaml`
(chiffrées) : la méthode fichier n'y existe pas, il n'y a pas de volume.

### Autres obstacles et précautions

- Le proxy inverse doit transmettre le corps de la requête `POST` tel quel (la signature Stripe porte
  sur les octets exacts) et peut limiter la route aux adresses IP de Stripe.
- `opcache.validate_timestamps=0` : modifier un fichier de plugin ne prend effet qu'avec une nouvelle
  image, ce qui est le cas ici (image immuable).
- Mettre à jour un plugin = modifier son dossier dans l'overlay, committer, reconstruire, déployer.
  Une instance déjà configurée garde ses réglages (en base).
- Le fichier `plugins.env` et `backups/` sont exclus du contexte de construction.

### Personnalisation du thème

Testé : une version modifiée de `assets/default/css/theme.css` placée dans l'overlay est servie après
construction, et survit à une mise à jour. Copier le fichier d'origine puis le modifier, plutôt que
d'écrire un fichier partiel : l'overlay **remplace** le fichier du même chemin.

## Messagerie (IMAP/SMTP)

Aucun identifiant réel n'a été utilisé. Test réalisé contre **GreenMail** (serveur de test, conteneur
jetable sur le réseau `egress` de la pile, sans chiffrement) :

```sh
docker run -d --name <projet>-greenmail --network <projet>_egress --network-alias greenmail \
  -e GREENMAIL_OPTS='-Dgreenmail.setup.test.smtp -Dgreenmail.setup.test.imap -Dgreenmail.hostname=0.0.0.0 -Dgreenmail.users=helpdesk:motdepasse@example.org' \
  greenmail/standalone:2.1.8
```

Dans l'administration : Emails > l'adresse > onglet **Remote Mailbox** (hôte `greenmail`, port `3143`,
protocole IMAP, authentification « Basic Authentication (Legacy) », relève toutes les 1 min, action
« Delete emails »), onglet **Outgoing (SMTP)** (hôte `greenmail`, port `3025`, authentification « None »),
et **Emails > Settings : cocher « Email Fetching »** (`scp/emailsettings.php`). Résultats :

- relève IMAP par le service `cron` : un message déposé dans la boîte devient un ticket en moins d'une
  minute (testé avant sauvegarde, après restauration et après mise à jour) ;
- envoi SMTP : les alertes « New Ticket Alert » arrivent dans la boîte de l'administrateur de test ;

Pièges constatés : (1) **à la première configuration d'une boîte, ouvrir « Config » (identifiants)
deux fois** : la première fois les identifiants sont enregistrés sous un compte provisoire et
l'enregistrement du formulaire échoue avec « Configure Authentication » ; la seconde fois, ils
sont attachés au compte et l'enregistrement réussit. (2) Sans l'option « Email Fetching » les boîtes
ne sont jamais relevées, même avec `cron` en santé. (3) Il n'y a pas de `sendmail` dans l'image
(voir « Limites connues »). (4) Le test ne couvre ni TLS (IMAPS/STARTTLS, SMTP 587/465) ni
l'authentification OAuth2 (non disponible ici : seule l'authentification de base est proposée).

### À tester par vous (check-list, avec `contact@coderise.fr`)

Rien de ceci n'a été exécuté. À faire sur une instance de test, pas sur la production :

1. [ ] Identifiants IMAP/SMTP du compte, chiffrement (SSL/TLS ou STARTTLS), port ; l'enregistrement
   de la boîte dans l'administration réussit (test de connexion intégré).
2. [ ] Envoyer un e-mail depuis une adresse externe vers `contact@coderise.fr` : un ticket apparaît en
   moins de 2 minutes ; la pièce jointe s'ouvre ; la réponse de l'agent arrive chez le client, dans le
   même fil, sans passer en courrier indésirable.
3. [ ] SPF, DKIM et DMARC du domaine alignés avec le serveur d'envoi ; l'adresse d'expédition correspond.
4. [ ] Aucune boucle de courrier (réponses automatiques entre deux systèmes) ; le filtre de ticket
   ignore les adresses `no-reply` et les rebonds.
5. [ ] TLS et reverse proxy de production : certificat valide pour `support.coderise.fr`,
   redirection HTTP vers HTTPS, `OSTICKET_COOKIE_SECURE=1`, cookies `Secure` + `HttpOnly` dans les
   outils du navigateur, HSTS présent, adresse IP réelle du client visible dans les tickets
   (`OSTICKET_TRUSTED_PROXIES` contient l'adresse du proxy), téléversement de pièce jointe par le proxy
   (limite de taille alignée sur 10 Mo).
6. [ ] Domaine : enregistrement DNS A/AAAA, URL du helpdesk (Admin > Paramètres > Système) réglée sur
   `https://support.coderise.fr/`, e-mail du système.
7. [ ] Pack de langue français : il est dans l'image (`overlay/include/i18n/fr.phar`, voir « Pack de
   langue français ») ; l'activer en production (Admin > Paramètres > Système > Langue principale =
   Français) ; vérifier le portail, les e-mails et les modèles (les modèles déjà en base ne sont pas
   traduits automatiquement). Vérifié sur la démo locale seulement (portail, panneau agent,
   administration).
8. [ ] Après chaque mise à jour : rejouer 2 et 5.

## Veille de sécurité et supervision (proposition à valider)

> **Proposition à valider.** Rien de ce qui suit n'est décidé ni mis en place : responsables, rythmes et
> outils sont à choisir par Coderise. Les valeurs sont des points de départ réalistes pour un support
> annoncé 24 h/24 et 7 j/7.

### Veille et reconstruction (proposition)

| Quoi | Fréquence proposée | Qui (à décider) | Comment |
|---|---|---|---|
| Scan de l'image en service et d'une image reconstruite à neuf | Chaque semaine, et à chaque annonce de sécurité Apache/PHP/openssl | Un référent technique + un suppléant | `./scripts/build.sh build --pull --no-cache` sous un tag candidat, puis `docker scout cves --only-severity critical,high local://coderise/osticket:<tag>` ; comparer au scan de l'image en service |
| Reconstruction et déploiement de routine | Chaque mois au minimum, même sans alerte | idem | Procédure « Mises à jour » (sans migration : `up -d`) |
| Reconstruction d'urgence | Sous 48 h pour une faille critique corrigée et exploitable (Apache, PHP, osTicket) ; sous 7 jours pour une faille élevée corrigée | idem | idem, avec validation de l'étape 7 |
| Rafraîchir les digests `PHP_IMAGE` (Dockerfile) et `mariadb` (compose.yaml) | À chaque reconstruction de routine | idem | `docker buildx imagetools inspect php:8.3-apache-bookworm` (le digest amont a déjà changé depuis l'épinglage du 20/10/2026) |
| Suivre les versions d'osTicket | Chaque semaine | idem | Liste des versions du dépôt amont (releases GitHub d'osTicket) et avis de sécurité ; fusionner les tags dans la branche du fork |
| Fin de vie PHP | Décidé le 22/10/2026 : rester en 8.3 | Responsable technique | Surveiller `imap` pour 8.4/8.5 (voir « Versions supportées ») ; ne pas prendre la dernière version de PHP sans raison |
| Test de restauration | Chaque trimestre | idem | `restore.sh` sur une pile jetable, comparer l'empreinte et ouvrir un ticket avec pièce jointe |

À savoir : (a) sans `--no-cache`, la reconstruction réutilise la couche `apt-get upgrade` du cache et ne
prend aucun correctif système ; (b) le 21/10/2026, la reconstruction à neuf donnait toujours les 26 failles
critiques ou élevées signalées le 20/10 (3 critiques, 23 élevées : `apache2` 15, `util-linux` 6, et
`cyrus-sasl2`, `expat`, `openssl`, `perl`, `zlib` une chacune), toutes marquées « not fixed » : il n'y avait
rien à corriger ce jour-là ; (c) la décision d'accepter un risque résiduel (correctif absent) revient à
Coderise et doit être consignée.

### Supervision et alerte externe pour un engagement 24/7 (proposition)

Principes : l'alerte doit venir **de l'extérieur** du serveur et **ne pas passer par osTicket** (un
helpdesk en panne ne peut pas envoyer l'alerte de sa propre panne), prévenir une personne d'astreinte par
un canal à accusé de réception (appel/SMS/notification poussée), puis escalader.

| Contrôle | Sonde externe | Seuil d'alerte proposé | Gravité |
|---|---|---|---|
| Portail joignable | Requête HTTPS toutes les minutes sur `https://support.coderise.fr/login.php`, avec test d'un mot attendu dans la page | 2 échecs consécutifs (2 min) | Critique : astreinte immédiate |
| Base joignable | La même page échoue (le contrôle de santé de `web` dépend de la base) ; en plus, `docker compose ps` signale un conteneur `unhealthy` | 3 contrôles Docker (≈ 1,5 min) | Critique |
| Certificat TLS | Date d'expiration lue par la sonde | 21 jours, puis 7 jours | Majeur, puis critique |
| Relève des e-mails | Requête SQL ou script : `last_activity` de `ost_email_account` plus vieux que 10 min, ou `num_errors` > 0, ou plus de ticket créé depuis N heures en journée | 10 min | Majeur (le support de la messagerie est la porte d'entrée) |
| Planificateur `cron` | Conteneur `cron` `unhealthy` (aucun passage réussi depuis 3 min) ; option : « battement de cœur » vers un service externe à chaque passage réussi (non implémenté : demande une sortie réseau et un changement de l'entrypoint) | 3 min | Majeur |
| Sauvegarde | Signal « dead man's switch » envoyé par la tâche de sauvegarde à un service externe ; alerte s'il manque | pas de signal sous 26 h | Majeur |
| Espace disque, mémoire, redémarrages | Agent hôte (disque du volume Docker, RAM, nombre de redémarrages de conteneur) | disque 80 % puis 90 % ; redémarrages > 3 en 10 min | Majeur |
| Nouvelle vulnérabilité | Le scan hebdomadaire ci-dessus | critique corrigée | Selon la veille |

**Modèle d'alerte** (même gabarit pour tous les canaux) :

```
[CRITIQUE] support.coderise.fr : portail indisponible
Depuis : 2026-11-05 03:12 UTC (2 échecs consécutifs)     Sonde : <nom, lieu>
Symptôme : HTTP 502 / délai dépassé / mot attendu absent
Premiers gestes : (1) ssh serveur ; docker compose ps ; docker compose logs --tail 100 web db
                  (2) base saine ? docker compose exec db healthcheck.sh --connect --innodb_initialized
                  (3) disque plein ? df -h ; (4) redémarrer : docker compose up -d --wait
                  (5) si échec : suivre « Retour arrière » du README (image précédente) ou « Restauration »
Escalade : astreinte N1 -> N2 après 15 min sans accusé -> responsable après 30 min
Communication client : page de statut / message sur contact@ si l'indisponibilité dépasse 30 min
```

À décider : l'outil de sonde (service gratuit ou payant, ou sonde auto-hébergée sur un autre
fournisseur), les noms de l'astreinte et les numéros, le canal de secours (deuxième fournisseur), la
page de statut, et le délai de réaction contractuel que les seuils ci-dessus doivent permettre de tenir.

## Variables (`.env`)

| Variable | Défaut | Rôle |
|---|---|---|
| `OST_PROJECT` | `osticket` | Nom du projet Compose (préfixe des conteneurs et volumes) |
| `OST_IMAGE`, `OST_VERSION` | `coderise/osticket`, `1.18.4` | Dépôt et tag de l'image (Droplet : `coderise/osticket`, `sha-xxxxxxx`) |
| `OSTICKET_MAX_WORKERS` | `40` | Processus Apache (8 sur le Droplet 1 Go) |
| `OST_BIND`, `OST_PORT` | `127.0.0.1`, `8080` | Adresse et port publiés |
| `OSTICKET_COOKIE_SECURE` | `1` | `0` uniquement pour un test en HTTP local |
| `OSTICKET_TRUSTED_PROXIES` | réseaux privés | Proxys autorisés à fournir `X-Forwarded-For` |
| `OST_SECRETS_DIR` | `./secrets` | Dossier des secrets de la base |

`plugins.env` (même dossier, facultatif, non versionné) : variables passées à `web` seulement, une
`NOM=valeur` par ligne, par exemple `STRIPE_SYNC_WEBHOOK_SECRET_FILE=/etc/osticket/stripe-sync-webhook-secret`
(voir « Plugins »).

## Environnement local de démonstration

Pour **voir** les thèmes et le portail en fonctionnement sur son poste, avec des données fictives.
Jamais pour la production : HTTP, cookies non `Secure`, identifiants de démonstration.

```sh
deploy/docker/scripts/local-demo.sh up [--theme <slug>] [--rebuild]
deploy/docker/scripts/local-demo.sh theme <slug>    # change de thème, données conservées
deploy/docker/scripts/local-demo.sh language <code> # langue de l'interface (fr, en_US), données conservées
deploy/docker/scripts/local-demo.sh status          # conteneurs, adresses, thème, commit
deploy/docker/scripts/local-demo.sh logs [-f] [web] # journaux
deploy/docker/scripts/local-demo.sh down            # arrête, garde les volumes
deploy/docker/scripts/local-demo.sh reset [--yes]   # supprime tout (confirmation « oui »)
```

Thèmes : `atelier`, `poste-de-travail`, `sommet` (défaut), `parcours`, `ownalytics-atelier`,
`ownalytics-ticket-de-caisse`. Shell POSIX : Git Bash sous Windows ou Linux ; il faut Docker
(Compose v2), `git` et `curl`.

**Ce que fait `up`** (la première fois ; ensuite il redémarre simplement la pile, et reconstruit
l'image si le commit `HEAD` ou le thème a changé) :

1. choisit deux ports libres sur `127.0.0.1` (portail à partir de 8480, Mailpit à partir de 8425 ;
   jamais 80, 443, 3306, 8025, 8026, 8080 ni un port déjà publié par un autre conteneur) et les fixe
   dans le dossier d'état : ils restent les mêmes entre `down` et `up` ;
2. construit l'image depuis le **commit** (`git archive`, comme `build.sh`), cibles `install` et
   `runtime` : sur la copie exportée, retire le thème committé dans `overlay/`, applique
   `deploy/themes/<slug>/apply.sh` et ajoute le plugin `contrib/sla-first-response/plugin/sla_first_response/`
   dans `include/plugins/` (copie seulement : rien n'est écrit dans `deploy/docker/overlay/`) ;
3. installe osTicket par l'**assistant web** (requêtes HTTP, image `install`), puis repasse sur l'image
   de production (configuration en lecture seule) ;
4. démarre `web`, `cron`, la base et **Mailpit**, puis peuple la base (`local-demo/seed.php`, exécuté
   dans un conteneur ponctuel de l'image) et passe l'interface en français (`local-demo/language.php` :
   langue principale `fr`, aucune langue secondaire, langue de chaque agent ; `OST_DEMO_LANG=en_US`
   garde l'anglais) ;
5. affiche les adresses et le **chemin** du fichier d'identifiants (jamais un mot de passe).

**Isolation.** Projet Compose fixe `ostdemo` (conteneurs `ostdemo-*`, volumes `ostdemo_ost-db`,
`ostdemo_ost-config`, `ostdemo_mail-data`), images `ostdemo/osticket:demo` et `:demo-install`. Les
fichiers de production (`compose.yaml`, `compose.install.yaml`) sont **copiés tels quels** dans le
dossier d'état, avec la surcouche `local-demo/compose.demo.yaml` (Mailpit et un réseau interne `mail`) ;
les ports, le tag d'image et `OSTICKET_COOKIE_SECURE=0` sont passés par `state.env`. Rien de la démo
(port publié, Mailpit, mot de passe) n'est dans les fichiers de production, et `deploy/` n'est jamais
copié dans l'image.

**Dossier d'état** (`OST_DEMO_DIR`, par défaut `<dépôt principal>/.local-demo/`, même lancé depuis
un worktree ; ignoré par Git) : `state.env` (ports, thème, commit, sans secret), copies des fichiers
Compose, `secrets/` (base), `credentials.txt` (droits `600`, ACL réservée à l'utilisateur sous Windows),
journaux du peuplement (`seed-*.log`). `down`, `status`, `logs` et `theme` n'utilisent que ce dossier
pour la pile ; `theme` et `up` ont besoin du dépôt pour reconstruire l'image.

**`credentials.txt`** : adresses du portail client, du panneau agent (`/scp/`), de l'administration
et de Mailpit, puis, pour chaque compte, rôle, identifiant et mot de passe **générés aléatoirement**
à la création de l'environnement (jamais de mot de passe par défaut). Comptes (adresses fictives en
`.test`) :

| Compte | Rôle |
|---|---|
| `camille.durand` | Administrateur (panneau agent et administration) |
| `theo.martin` | Agent, département Support |
| `lea.moreau@lumen-studio.test` | Cliente de l'organisation **Priority** (environ 40 tickets) |
| `hugo.bernard@acme.test` | Client de l'organisation **Acme** (Standard), demandeur du ticket partagé |
| `nina.petit@mail-perso.test` | Cliente sans organisation (Standard) |
| `marc.lefevre@acme.test` | Client Acme, collaborateur du ticket partagé |
| `gina.roux@lumen-studio.test` | Invitée sans compte (organisation Priority) : « Vérifier le statut d'un ticket » (« Check Ticket Status » en anglais) avec l'e-mail et le numéro noté dans le fichier |

**Données** (toutes fictives) : vérification MX des adresses désactivée, fuseau Europe/Paris,
installation en anglais (`en_US` : contenus de départ en anglais) puis interface en **français** (pack
`overlay/include/i18n/fr.phar`, commande `language`), inscription des clients ouverte, accusés de
réception activés ; SMTP de toutes les adresses système vers Mailpit (aucun e-mail ne sort de la
machine) ; plans de SLA « Priority 4h » (actif, calendrier 24/7) et « Priority 4h - 1re reponse faite »
(désactivé, non transitoire) ; filtre « Organisation / Nom = Priority » qui pose le plan ; plugin
`sla_first_response` installé, actif, instance réglée sur ces deux plans et sur l'organisation Priority ;
rubriques « Incident en production » (avec le formulaire « Contexte technique » : listes, texte, date,
case à cocher), « Question sur l'utilisation », « Demande d'évolution », « Facturation et abonnement » ;
10 articles en 5 catégories ; pages d'accueil, de remerciement et hors ligne avec les textes proposés
par le README du thème Sommet. Tickets : 40 pour Léa (14 fermés, réponses d'agent qui ont fait basculer
le plan par le plugin, messages de suivi, pièces jointes, deux tickets en attente du premier retour
pour l'indicateur Priority, un en retard), tickets de Hugo, Nina et Marc, un ticket partagé, un ticket
de l'invitée. Le plugin `stripe-sync` n'est pas installé.

**Vérifié le 22/10/2026** (Windows 11, Git Bash, Docker 29, Compose 5.1 ; Chromium privé piloté par
`playwright-core` installé hors du dépôt) : construction depuis le commit, installation par
l'assistant, peuplement (50 tickets, 37 bascules du plan par le plugin) ; connexion de Léa, Hugo,
Nina et Marc au portail avec la pastille de contrat attendue (Priority pour Léa, Standard pour les
autres), encart Priority de l'accueil de Léa, indicateur « premier retour sous 4 h » sur son ticket en
attente ; connexion de l'agent et de l'administrateur (`/scp/`, `settings.php`, plugin actif) ;
ouverture d'un ticket depuis le portail et accusé de réception reçu dans Mailpit ; `theme parcours`
puis `theme sommet` (feuille du thème servie, nombre de tickets, d'entrées, d'utilisateurs et
d'articles inchangé) ; `down` puis `up` (données et e-mails conservés) ; `docker compose restart` de la
pile (retour en `healthy`, portail et Mailpit répondent) ; empreintes des 16 binaires du thème dans
l'image identiques aux fichiers committés ; aucun montage ni label des conteneurs ne pointe hors du
dossier d'état. **Non vérifié** : `reset` jusqu'au bout (seul le refus sans confirmation l'a été),
une installation complète sous Linux, le changement vers `atelier`, `poste-de-travail` et les deux
thèmes `ownalytics-*`, l'accès invité par e-mail et numéro.

Constaté en l'écrivant : Docker ne publie pas le port d'un conteneur relié seulement à des réseaux
`internal` (d'où le réseau `mail-ui` de Mailpit) ; `Topic::update()` n'associe pas le formulaire du
ticket à une rubrique créée par script (le peuplement l'ajoute) ; le nom d'un filtre est limité à
32 caractères ; un plan de SLA créé avec des drapeaux à 0 reçoit la valeur par défaut de la colonne
(actif) : le plan « réponse faite » est désactivé après coup, comme dans l'administration.
Au `docker compose restart`, le premier passage de `cron` peut échouer pendant que la base redémarre
(`Call to undefined function osTicket\Mail\_S()` dans `class.mailer.php`, chemin d'erreur d'osTicket
quand la base est injoignable) ; les passages suivants réussissent et `cron` reste `healthy`.

**Limites.** HTTP seulement (`OSTICKET_COOKIE_SECURE=0`) ; les échéances de SLA continuent de courir :
les tickets en attente passent « en retard » quelques heures après le peuplement (`cron` envoie alors
les alertes, visibles dans Mailpit) ; changer de commit puis relancer `up` reconstruit l'image mais ne
migre pas la base (`reset` puis `up` pour repartir de zéro) ; les images de démonstration occupent
environ 1 Go (supprimées par `reset`).

## Droplet DigitalOcean

Déploiement sur un Droplet (Caddy sur l'hôte, image construite par GitHub Actions et envoyée par SSH,
sauvegardes locales, UFW, réglages 1 Go de RAM) : voir [droplet/README.md](droplet/README.md).
