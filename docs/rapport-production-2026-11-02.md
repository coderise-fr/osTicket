# Rapport d'écarts de la production (support.coderise.fr), 02/11/2026

Inspection en **lecture seule** du Droplet, de la base et du site public, comparée au dépôt
(`origin/main` = `50323107`) et à [deploiement-droplet.md](deploiement-droplet.md). Aucune donnée
modifiée. Les sauvegardes et la sonde externe sont suivies par Julien, hors de ce rapport.

## 1. Conforme

| Sujet | Constat |
| --- | --- |
| Version déployée | image `sha-5032310`, étiquette de révision = `origin/main` ; checkout du Droplet = `50323107` ; 3 conteneurs sains |
| Image | PHP 8.3.36, MariaDB 11.4 épinglée par digest, `setup/` absent (404), `include/`, `ost-config.php`, `.git` en 403 |
| TLS | Let's Encrypt (YE2), expire le 21/01/2027, HTTP → HTTPS (308), HSTS, cookies `secure; HttpOnly` |
| En-têtes | CSP `frame-ancestors`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `COOP` |
| Méthodes HTTP | `TRACE` 405 ; `PUT` et `DELETE` hors `ajax.php` : 403 Apache ; `DELETE /scp/ajax.php/…` : atteint osTicket (« Must login ») |
| SSH | root et mots de passe refusés ; UFW 22 (limité), 80, 443 ; sudo de `deploy` en place ; clé CI restreinte à `ci-receive.sh` |
| Plugins | `Stripe sync` 0.2 et `SLA : première réponse` 0.4 présents dans l'image, installés et actifs, une instance chacun |
| SLA | `Priority 4h` (calendrier 24/7, 4 h) et `Priority 4h - 1ere réponse` (plan désactivé voulu), organisation `Priority`, filtre « Tickets prioritaires » |
| Base | `schema_signature` = `5fb92bef…`, identique à `core.sig` du dépôt |
| Divers | fuseau Europe/Paris, `enable_auto_cron=0` (le service `cron` relève), cron en bonne santé, aucune erreur 5xx ni PHP dans les journaux sur 24 h, sauvegarde quotidienne à 03:15, secrets en 600 |

## 2. Écarts, par priorité

### À corriger maintenant

1. **Aucun e-mail ne part.** Le compte SMTP de `support@coderise.fr` est configuré (`tls://smtp-relay.brevo.com`,
   port 2525) mais **inactif** : l'authentification Brevo (535) a échoué. Sans `sendmail` dans l'image, osTicket
   n'a donc aucun moyen d'envoyer : invitations d'agents, réinitialisation de mot de passe, accusés de réception,
   réponses aux clients. *Action* : login SMTP `…@smtp-brevo.com` et clé SMTP dédiée, puis test d'envoi.
2. **Aucune réception d'e-mail.** Compte « Remote Mailbox » vide, `enable_mail_polling=0`. Les MX sont chez
   Microsoft 365 : IMAP en OAuth2 (Entra) ou boîte de relais, pas encore décidé.
3. **Redémarrage du serveur requis** (noyau 6.8.0-142, 4 paquets en attente) ; `Automatic-Reboot` est désactivé.
   *Action* : planifier un redémarrage dans une fenêtre calme (la pile redémarre seule, `restart: unless-stopped`).
4. **Compte agent sans mot de passe** : un second compte administrateur sans mot de passe. Julien avait indiqué
   être seul : à confirmer ; l'invitation ne peut de toute façon pas partir (point 1). Un administrateur
   sans mot de passe défini n'est pas exploitable, mais c'est un compte privilégié de plus à justifier.

### À configurer (rien n'a encore été fait côté métier)

5. **Structure par défaut** : départements `Support`, `Sales`, `Maintenance` ; rubriques `General Inquiry`,
   `Feedback`, `Report a Problem`, `Access Issue` ; organisation `osTicket`. Décidé mais pas créé : départements
   Ownalytics et Coderise, rubriques (Installation, Utilisation, Bug, Mise à jour de Matomo, Priority Support,
   Licence et facturation, Demande de fonctionnalité), champs Produit et Édition.
6. **SLA par défaut** : `Default SLA` 48 h (calendrier `Horaires bureau`), départements sans SLA propre, sauf `Sales`.
   Le calendrier `Monday - Friday 8am - 5pm with U.S. Holidays` est resté en base : à retirer, et les jours fériés
   français sont à vérifier dans `Horaires bureau`.
7. **Anti-spam** : 10 tickets de spam le 28/10 (supprimés depuis, il ne reste que le ticket de bienvenue). La liste
   de bannissement ne contient que `test@example.com`, l'adresse du spammeur n'y est pas. `enable_captcha=1` mais
   `client_registration=public` : le CAPTCHA n'a pas empêché le flux. *Actions* : bannir l'expéditeur, exiger un
   compte pour ouvrir un ticket, limiter le débit de `POST /open.php` dans Caddy, vérifier que le CAPTCHA est bien rendu.
8. **Langue** : `system_language=en_US`, français en langue secondaire. Le pack `fr.phar` est dans l'image ; la
   langue principale reste à décider (formats de date américains `MM/dd/y` en vigueur).
9. **Plugin Stripe** : installé mais jamais éprouvé avec un vrai événement (prototype, voir
   [contrib/stripe-sync/README.md](../contrib/stripe-sync/README.md) §9). Le secret du webhook et la destination Stripe ne sont pas
   configurés ; sans eux, aucun client n'obtient d'accès portail.

### Dérive documentaire et exploitation

10. [deploiement-droplet.md](deploiement-droplet.md) cite l'image `sha-2477da4` et l'état du 23/10 ; en service : `sha-5032310`.
    Le port 2525 et le préfixe `tls://` (blocage SMTP sortant 25/465/587 chez DigitalOcean) ne sont pas encore documentés.
11. `CLAUDE.md` mentionne `php:8.2-apache-bookworm` ; l'image est en PHP 8.3 (ticket #3 : « rester sur bookworm »).
12. **Images Docker** : 13 images, 4,1 Go, dont 2,2 Go récupérables (anciens tags `sha-…`, image `-install`).
    `update.sh` en garde 4 ; l'image d'installation `sha-2477da4-install` peut être supprimée.
13. **Sauvegardes** : 35 fichiers, 12 Mo, toutes sur le Droplet. Julien indique qu'elles sont couvertes ; vérifier
    la restauration (`scripts/test-restore.sh`) et inclure `/opt/osticket/secrets` (non couvert par `backup.sh`).
14. `fail2ban` absent : `ufw limit` freine le SSH par force brute, sans le bloquer. Les connexions sont par clé
    seulement, donc le risque est faible.

## 3. Ordre conseillé

1 → 2 (messagerie) ; 7 (anti-spam, rapide) ; 5 et 6 (structure et SLA) ; 3 (redémarrage) ; 9 (Stripe de bout en bout) ;
puis 10–12 (documentation et nettoyage).
