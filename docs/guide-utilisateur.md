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

Le panneau agent (`/scp/`) n'est pas concerné : les thèmes n'habillent que le portail client.

### Les thèmes disponibles

| Slug | Marque | Idée |
|---|---|---|
| `sommet` | Coderise | Éditorial, bandeaux bleu nuit, chevron cyan. Le plus proche d'osTicket d'origine. **Thème de production actuel.** |
| `atelier` | Coderise | Chaleureux : bulles de conversation, pastilles. |
| `poste-de-travail` | Coderise | Dense : rail latéral, tableaux serrés, tableau de bord. |
| `parcours` | Coderise | Guidé : assistant d'ouverture de ticket en 4 étapes, « prochain pas ». |
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
