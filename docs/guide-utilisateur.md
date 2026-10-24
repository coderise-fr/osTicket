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
La fenêtre ne repart pas à la réouverture d'un ticket : pour un ticket rouvert, l'échéance est
toujours 4 h après la création (osTicket déplace l'échéance du plan à la date de réouverture, la
jauge ne la suit pas).

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
réponse, à trier par « SLA Due Date ».

Cette recette **approche** la règle de la jauge sans la reproduire : le champ « Answered »
d'osTicket passe à « oui » à chaque réponse d'un agent, même non adressée au client
(`Ticket::onResponse()`), et repasse à « non » à **chaque** nouveau message du client
(`Ticket::onMessage()`) ou à la main (« Mark as unanswered »). Un ticket qui a reçu son premier retour
à temps puis une relance du client réapparaît donc dans la file tant que son échéance SLA tombe
dans la fenêtre choisie (et toujours dans la variante sans échéance), alors que la jauge affiche
« Tenu en … ». Pour un ticket rouvert, l'échéance SLA d'osTicket part de la réouverture, celle de
la jauge de la création. Nom conseillé : « À risque » (le nom d'une file est
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

## 3. Interface en français

**Le pack.** Le pack de langue officiel d'osTicket (`fr.phar`, osTicket 1.18.x, build v1.18.2 du
04/02/2025) est dans `deploy/docker/overlay/include/i18n/fr.phar`, donc dans chaque image construite.
Provenance et empreinte SHA-256 : `deploy/docker/overlay/README.md`. Code de langue osTicket : `fr`.

**Le mettre à jour.** À chaque mise à jour **mineure** d'osTicket (1.18 vers 1.19…), télécharger le pack
de la nouvelle version (osticket.com/download, sélecteur des packs de langue), remplacer le fichier,
reporter version, taille et SHA-256 dans `deploy/docker/overlay/README.md`, committer, reconstruire.
Un pack plus ancien que le code laisse seulement les nouveaux textes en anglais.

**Choisir la langue par défaut** (réglage en base, sans reconstruire) : Admin > Paramètres > Système >
« Langue principale » = Français. Sans langue secondaire, tout le monde voit le français. Avec
l'anglais en langue secondaire, le portail suit la langue du navigateur du visiteur (en-tête
`Accept-Language`) et propose un sélecteur ; chaque agent choisit sa langue dans son profil.
Sur la démo locale : `deploy/docker/scripts/local-demo.sh language fr` (ou `en_US`) règle la langue
principale, retire les langues secondaires et règle la langue de tous les agents ; `up` le fait tout seul
en français après la première installation (`OST_DEMO_LANG=en_US` pour garder l'anglais).

**Ce qui reste en anglais.** Les contenus créés **en base** à l'installation restent dans la langue de
l'installation (la démo est installée en anglais, puis passée en français) : noms des files (Open,
Answered, Overdue, My Tickets, Closed et leurs sous-files), en-têtes de colonnes et tris des files,
statuts (Open, Resolved…), priorités (Low, Normal, High, Emergency), « Default SLA », calendriers,
formulaires (Contact Information : Email Address, Full Name, Phone Number ; Company ; Ticket Details),
rôles (All Access, View only), réponses types d'exemple, pages (« Authentication Required » sur la
connexion agent, « Sign in to… » sur la connexion client, accueil, remerciement), modèles d'e-mails
(« Ticket [#…] Access Link »), ticket de bienvenue « osTicket Installed! ». Pour les traduire :
renommer dans l'administration, ou utiliser la traduction par champ d'osTicket (icône de drapeau,
visible quand une langue secondaire est configurée) ; pour les e-mails, Admin > E-mails > Modèles >
ajouter un jeu de modèles en Français (les textes viennent du pack) et le choisir par défaut et sur les
services. osTicket n'a pas d'outil pour réimporter les contenus de départ traduits dans une
installation existante (le chargement initial n'a lieu qu'à l'installation : le relancer créerait des
doublons). Une **nouvelle** installation faite en français (langue choisie dans l'assistant) crée ces
contenus directement en français.

Restent aussi en anglais quelques textes du cœur absents du pack ou écrits en dur : boutons
« Text Color », « Font », « Video », « Table », « Fullscreen » de l'éditeur, « View Email Recipients »,
l'événement « … Filter set SLA to … », « No, Cancel » / « Yes, Do it! » sur quatre pages
d'administration, l'état « Active » des rubriques, « Enabled » des plugins. Le pack garde « Email » et
« Organization » tels quels et traduit « Open » par « Ouverts » partout (bouton de création de ticket
compris). Les thèmes n'ont pas de texte anglais : leur catalogue français ne sert que si le pack ne
traduit pas déjà le texte (le pack l'emporte : « Services » pour les départements, « Début » au lieu de
« Commencer » sur les cartes du thème Parcours).

## 4. Limites à connaître

- Le niveau de contrat affiché est celui **actuel** du propriétaire du ticket, pas celui du jour où il a été créé.
- Un sélecteur de thème dans l'administration demanderait un plugin dédié ou une modification du cœur
  d'osTicket, ce qui compliquerait les mises à jour : non prévu.
