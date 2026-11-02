# Configuration d'osTicket (support.coderise.fr)

Réglages faits dans l'administration le 02/11/2026 (via l'interface, avec Julien). Ils vivent dans la base :
ils ne sont **pas** dans le dépôt, donc à refaire (ou à restaurer depuis une sauvegarde) sur une instance neuve.
Décisions de fond : [deploiement-droplet.md](deploiement-droplet.md), [alignement-politiques-support.md](alignement-politiques-support.md).

## Départements

| Département | Rôle |
| --- | --- |
| **Ownalytics** (par défaut) | gamme Ownalytics (Heatlens, Auditlens, Reportlens) ; 2 agents |
| **Coderise** | autres produits Coderise, indépendants de la gamme |

Les anciens départements `Support` (renommé), `Maintenance` (renommé) et `Sales` (supprimé, vide) sont remplacés.
Un département n'a ni SLA ni calendrier propre : les tickets héritent du SLA du ticket (plan Priority via le filtre)
et du calendrier du plan.

## Rubriques d'aide (noms en anglais, visibles par les clients)

| Rubrique | Département | Priorité | Formulaire « Product details » |
| --- | --- | --- | --- |
| Installation | Ownalytics | Normal | oui |
| Usage and configuration | Ownalytics | Normal | oui |
| Bug report | Ownalytics | Normal | oui |
| Matomo upgrade | Ownalytics | Normal | oui |
| Priority Support | Ownalytics | Normal | oui |
| License and billing | Ownalytics | Normal | oui |
| Feature request | Ownalytics | Faible | oui |
| Other Coderise product | Coderise | Normal | non |

Aucune rubrique par défaut d'osTicket n'a été conservée (`General Inquiry`, `Feedback`, `Report a Problem` ont été
renommées ; la sous-rubrique `Access Issue` a été supprimée).

## Formulaire personnalisé « Product details »

| Champ (variable) | Type | Valeurs |
| --- | --- | --- |
| Product (`product`) | Choix, obligatoire pour les utilisateurs | Heatlens, Auditlens, Reportlens, Other product |
| Edition (`edition`) | Choix, obligatoire pour les utilisateurs | Matomo 5, Matomo 6, Other or not sure |
| Matomo version (`matomo_version`) | Réponse courte | libre |
| Plugin version (`plugin_version`) | Réponse courte | libre |

Les deux champs de version reprennent les informations que les politiques de support demandent au client.
Piège à retenir : le rattachement d'un formulaire à une rubrique n'est pris en compte qu'après un délai d'environ deux
secondes entre le choix du formulaire et l'enregistrement de la rubrique.

## SLA et calendriers

- **SLA par défaut du système : aucun.** Le support inclus n'a pas de délai garanti ; aucune échéance ni alerte
  « en retard » n'est donc générée pour les tickets standard. Les plans `Default SLA` (48 h) et leur calendrier
  restent en base, inutilisés.
- **Priority** : plan `Priority 4h` (4 h, calendrier 24/7) appliqué par le filtre « Tickets prioritaires » (organisation
  `Priority`), et plan `Priority 4h - 1ere réponse` désactivé (voulu : le plugin `sla_first_response` y bascule le ticket
  après la première réponse). Voir [contrib/sla-first-response/README.md](../contrib/sla-first-response/README.md).
- **Calendriers** : `24/7` (calendrier système par défaut), `Horaires bureau` (lun-ven 9h-17h, Europe/Paris) et
  `Jours fériés France` (type Congés, rattaché à `Horaires bureau`). Le calendrier américain a été supprimé.
- **Jours fériés France** : 8 fêtes fixes en répétition annuelle (1er janvier, 1er mai, 8 mai, 14 juillet, 15 août,
  1er novembre, 11 novembre, 25 décembre) et 6 fêtes mobiles saisies une par une pour 2027 et 2028 (lundi de Pâques,
  Ascension, lundi de Pentecôte). **À renouveler avant fin 2028**, et à vérifier chaque année.

## Anti-spam

- Adresse bannie : `tralotus@gmail.com` (spam du 28/10/2026, 10 tickets depuis `188.163.4.215`).
- **Compte obligatoire pour ouvrir un ticket** (« Inscription requise » = `clients_only`), inscription publique,
  CAPTCHA actif. Vérifié : `GET /open.php` affiche la connexion et un `POST` anonyme est redirigé sans créer de ticket.
- Conséquence : l'e-mail de confirmation d'inscription dépend du SMTP (Brevo, pas encore opérationnel) ; tant qu'il ne
  fonctionne pas, aucun client ne peut activer un compte.
- Non fait : limitation de débit de `POST /open.php`. Le Caddy standard n'a pas de module de limitation ; options :
  module `caddy-ratelimit` (image Caddy personnalisée), ou `fail2ban` sur les journaux. Moins utile maintenant que la
  création de ticket exige une connexion.

## Pièges rencontrés dans l'interface

- Les noms de champs des fenêtres d'administration sont des hachages ; cibler les champs **dans** la fenêtre
  (`#popup form`), jamais `document.getElementsByName` seul : un champ homonyme de la page serait modifié.
- Quitter une page d'édition modifiée déclenche une boîte « beforeunload » du navigateur.
- Les suppressions demandent une confirmation (« Oui, Faites-le ! » / « Yes, Do it! » selon la langue).
