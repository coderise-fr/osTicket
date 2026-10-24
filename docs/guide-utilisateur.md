# Guide utilisateur : portail de support Coderise (osTicket)

Ce guide décrit ce qu'un administrateur du portail peut faire. Les procédures de déploiement
et d'exploitation sont dans [`deploy/docker/README.md`](../deploy/docker/README.md) et
[`docs/deploiement-droplet.md`](deploiement-droplet.md).

## 1. Changer de thème

### Principe

osTicket n'a **pas de sélecteur de thème** dans l'administration. Nos thèmes sont des fichiers
(feuille de style et gabarits PHP du portail client) qui remplacent ceux d'osTicket au moment de la
construction de l'image Docker, par le dossier `deploy/docker/overlay/`. Changer de thème, c'est donc
installer un autre jeu de fichiers puis reconstruire et redéployer l'image. Un seul thème est actif à
la fois : les scripts refusent d'en installer deux.

Ces thèmes n'habillent que le portail client. Le panneau agent (`/scp/`) a ses propres thèmes,
indépendants : voir « Thème du panneau agent » plus bas.

### Les thèmes disponibles

| Slug | Marque | Idée |
|---|---|---|
| `sommet` | Coderise | Éditorial, bandeaux bleu nuit, chevron cyan. Le plus proche d'osTicket d'origine. |
| `atelier` | Coderise | Chaleureux : bulles de conversation, pastilles. |
| `poste-de-travail` | Coderise | Dense : rail latéral, tableaux serrés, tableau de bord. |
| `parcours` | Coderise | Guidé : assistant d'ouverture de ticket en 4 étapes, « prochain pas ». **Thème installé dans l'overlay de production.** |
| `ownalytics-atelier` | Ownalytics | Atelier aux couleurs d'Ownalytics. |
| `ownalytics-ticket-de-caisse` | Ownalytics | Papier à bords déchirés sur fond prune. |

Chaque thème a son README (`deploy/themes/<slug>/README.md`) : fichiers du cœur surchargés, écarts avec
la maquette, limites, captures.

### Sur l'environnement local de démonstration

Depuis Git Bash, dans `deploy/docker/` :

```sh
./scripts/local-demo.sh theme parcours     # un slug du tableau ci-dessus
./scripts/local-demo.sh status
```

La commande applique le thème, reconstruit l'image et redémarre `web` et `cron`. Les comptes, tickets et
articles sont conservés. Comptez quelques minutes, puis rechargez le portail. Les adresses et les
identifiants de la démo sont dans `.local-demo/credentials.txt` (fichier local, jamais committé).

### En production

1. Retirer le thème actuel : `deploy/themes/<ancien>/remove.sh`.
2. Installer le nouveau : `deploy/themes/<nouveau>/apply.sh` (`--dry-run` pour simuler).
3. Committer `deploy/docker/overlay/`.
4. Construire l'image (`deploy/docker/scripts/build.sh build`, ou le workflow `docker-image`), puis
   la déployer avec `deploy/docker/droplet/update.sh sha-xxxxxxx` (sauvegarde et retour arrière
   automatique inclus).
5. Vérifier le portail (accueil, ouverture d'un ticket, connexion, suivi d'un ticket).

Retour arrière : réinstaller l'ancien thème de la même façon, ou remettre l'ancien tag d'image
(voir « Retour arrière » dans le README Docker).

### Après une mise à jour d'osTicket

Chaque thème remplace des fichiers du cœur. Lancer `deploy/themes/<slug>/check-drift.sh` : il liste les
fichiers du cœur modifiés depuis la version de référence du thème, c'est-à-dire les gabarits à rebaser.

### Thème du panneau agent

Le panneau agent et l'administration (`/scp/`) ont leur propre thème, `coderise-agent`
(`deploy/themes-staff/coderise-agent/`, README détaillé dans ce dossier) : rail latéral bleu nuit,
file de tickets avec jauge de premier retour, fiche ticket à colonne de propriétés, formulaires
d'administration repérant les réglages modifiés, page de connexion en carte. Il **cohabite** avec
le thème client : les deux s'installent ensemble dans `deploy/docker/overlay/` (un thème client et
un thème agent au plus). Il est installé dans l'overlay de production avec le thème client `parcours`.

Sur l'environnement local, depuis `deploy/docker/` (l'image est construite depuis le dernier
commit : committer d'abord toute modification du thème) :

```sh
./scripts/local-demo.sh staff-theme coderise-agent   # ou none pour le panneau d'origine
./scripts/local-demo.sh status                       # affiche le thème client et le thème agent
```

En production :

1. Installer : `sh deploy/themes-staff/coderise-agent/apply.sh` (`--dry-run` pour simuler) ;
   retirer : `remove.sh` du même dossier. Le thème client n'est pas touché.
2. Committer `deploy/docker/overlay/`.
3. Construire avec `deploy/docker/scripts/build.sh build`, puis déployer avec
   `deploy/docker/droplet/update.sh sha-xxxxxxx`.
4. Vérifier : connexion agent, file de tickets, une fiche ticket, une page d'administration.

Après une mise à jour d'osTicket : `deploy/themes-staff/coderise-agent/check-drift.sh` (fichiers du
cœur remplacés ou dont le thème dépend, modifiés depuis la référence du thème).

**Jauge de premier retour.** La colonne « Premier retour » de la file et le panneau de la fiche
ticket n'apparaissent que si le plugin `sla_first_response` est installé et actif (sinon « — »), et
seulement pour les tickets ouverts d'un client Priority. L'échéance est celle du plan SLA
« Priority 4h » quand le ticket est sur ce plan, sinon 4 h après la création ; le premier retour est
la première réponse d'un agent adressée au client (règle de `contrib/sla-first-response/README.md`).
La fenêtre ne repart pas à la réouverture d'un ticket.

**File « À risque ».** Le thème ne crée pas de file : un administrateur l'ajoute comme file
enregistrée (Admin > Paramètres > Tickets, onglet « Queues », « Add New Queue »), visible ensuite
dans le rail de chaque agent. Critères disponibles dans l'éditeur de files (vérifié sur la démo) :

| Critère | Valeur |
|---|---|
| Status / State | Open |
| SLA Plan | Priority 4h |
| Answered | No |
| SLA Due Date | « in the next n days » : 1, unité « hours » (échéance dans l'heure) |

Les critères s'additionnent (ET) : les tickets déjà en dépassement n'y figurent pas, ils sont dans
la file « Open / Overdue » du cœur. Variante sans le dernier critère : tous les tickets Priority sans
réponse, à trier par « SLA Due Date ». Le champ « Answered » d'osTicket passe à « oui » dès la
première réponse d'un agent, même non adressée au client : la file approche la jauge sans la
reproduire exactement. Nom conseillé : « À risque » (le nom d'une file est
affiché tel quel, il n'est pas traduit).

## 2. Ce qui se change dans l'administration, sans reconstruire

| Quoi | Où |
|---|---|
| Logo du portail | Admin > Paramètres > Système |
| Textes d'accueil, de remerciement, hors ligne | Admin > Gérer > Pages |
| Liens du pied de page | Pages « Autres ressources » (Admin > Gérer > Pages) |
| Organisation Priority, libellés Priority/Standard | Admin > Gérer > Plugins > « SLA : première réponse » |
| Plans de SLA, filtres de tickets | Admin > Gérer > Plans de SLA, Filtres |

Le niveau de contrat (Priority / Standard) affiché par les thèmes vient du plugin `sla_first_response` :
il faut qu'il soit installé dans l'overlay, activé et configuré sur l'organisation Priority, sinon les thèmes
n'affichent aucun niveau. Détails : `contrib/sla-first-response/README.md`.

## 3. Limites à connaître

- Le portail est en anglais tant que le pack de langue français officiel n'est pas ajouté à l'overlay
  (voir « À tester par vous » dans le README Docker).
- Le niveau de contrat affiché est celui **actuel** du propriétaire du ticket, pas celui du jour où il a été créé.
- Un sélecteur de thème dans l'administration demanderait un plugin dédié ou une modification du cœur
  d'osTicket, ce qui compliquerait les mises à jour : non prévu.
