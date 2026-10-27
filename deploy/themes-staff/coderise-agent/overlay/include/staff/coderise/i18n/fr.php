<?php
/*********************************************************************
    include/staff/coderise/i18n/fr.php

    French catalogue of the "coderise-agent" staff theme, keyed by the
    English msgid (see cdr__() in ../coderise.inc.php). osTicket's own
    language pack, when installed, wins over this file, except for the
    labels printed through cdr_t() (rail tabs, crumbs: "Tableau de bord").

    Inventory: every label the rail can show (agent and admin tabs and all
    their sub-menus, as defined in include/class.nav.php), the chrome of
    the theme and the gauge labels. Keep it in sync with class.nav.php.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

return array(
    // Agent tabs (StaffNav::getTabs) and their titles
    'Dashboard' => 'Tableau de bord',
    'Agent Dashboard' => 'Tableau de bord agent',
    'Users' => 'Utilisateurs',
    'User Directory' => 'Annuaire des utilisateurs',
    'Tasks' => 'Tâches',
    'Task Queue' => 'File des tâches',
    'Tickets' => 'Tickets',
    'Ticket Queue' => 'File des tickets',
    'Knowledgebase' => 'Base de connaissances',
    'Applications' => 'Applications',

    // Agent sub-menus (StaffNav::getSubMenus)
    'Agent Directory' => 'Annuaire des agents',
    'My Profile' => 'Mon profil',
    'Organizations' => 'Organisations',
    'FAQs' => 'FAQ',
    'Categories' => 'Catégories',
    'Canned Responses' => 'Réponses prédéfinies',

    // Admin tabs (AdminNav::getTabs) and their titles
    'Settings' => 'Paramètres',
    'System Settings' => 'Paramètres système',
    'Admin Dashboard' => 'Tableau de bord administrateur',
    'Manage' => 'Gérer',
    'Manage Options' => 'Options de gestion',
    'Emails' => 'E-mails',
    'Email Settings' => 'Paramètres e-mail',
    'Agents' => 'Agents',
    'Manage Agents' => 'Gérer les agents',

    // Admin sub-menus: Dashboard
    'System Logs' => 'Journaux système',
    'Audit Logs' => "Journaux d'audit",
    'Information' => 'Informations',
    // Settings
    'Company' => 'Entreprise',
    'System' => 'Système',
    // Manage
    'Help Topics' => "Sujets d'aide",
    'Filters' => 'Filtres',
    'Ticket Filters' => 'Filtres de tickets',
    'SLA' => 'SLA',
    'Schedules' => 'Calendriers',
    'API' => 'API',
    'Pages' => 'Pages',
    'Forms' => 'Formulaires',
    'Lists' => 'Listes',
    'Plugins' => 'Plugins',
    // Emails
    'Email Addresses' => 'Adresses e-mail',
    'Banlist' => 'Liste noire',
    'Banned Emails' => 'E-mails bannis',
    'Templates' => 'Modèles',
    'Email Templates' => "Modèles d'e-mail",
    'Diagnostic' => 'Diagnostic',
    'Email Diagnostic' => 'Diagnostic e-mail',
    // Agents
    'Teams' => 'Équipes',
    'Roles' => 'Rôles',
    'Departments' => 'Départements',

    // Chrome of the theme
    'Profile' => 'Profil',
    'Log Out' => 'Déconnexion',
    'Sign Out' => 'Se déconnecter',
    // Rail foot: department, then the role of an administrator
    '%s, administrator' => '%s, administrateur',
    'Administrator' => 'Administrateur',
    'New Ticket' => 'Nouveau ticket',
    'New ticket' => 'Nouveau ticket',
    'Not assigned' => 'Non assigné',
    'Internal note' => 'Note interne',
    'Menu' => 'Menu',
    'Main menu' => 'Menu principal',
    'Administration' => 'Administration',
    'Agent' => 'Agent',
    'Panel' => 'Panneau',
    'Search tickets' => 'Rechercher un ticket',
    // Top bar search: the results are tickets (keywords also match the
    // client's name and e-mail), so the wording names tickets only
    'Search tickets by number, subject or client' => 'Rechercher un ticket : numéro, sujet, client',
    'Skip to content' => 'Aller au contenu',
    'Breadcrumb' => "Fil d'Ariane",
    'Upgrader' => 'Mise à jour',
    'First reply' => 'Premier retour',
    'Not applicable' => 'Sans objet',
    'At risk' => 'À risque',

    // First-reply gauge (sprintf formats)
    '%s left' => 'Reste %s',
    'Over by %s' => 'Dépassé de %s',
    'Met in %s' => 'Tenu en %s',
    'Returned %s late' => 'Rendu avec %s de retard',

    // Ticket view (header.inc.php, coderise.js)
    'Priority contract: first reply to the client within %s.' => 'Contrat Priority : premier retour au client sous %s.',
    'Ticket properties' => 'Propriétés du ticket',
    // Panel titles of the properties column (the pack has the same wording)
    'Properties' => 'Propriétés',
    'Client' => 'Client',
    // Role next to the author of a thread entry (ticket.js). A reply is
    // always visible to the client, but may not be e-mailed ("Do Not Email
    // Reply"): no "envoyée"
    'Reply to the client' => 'Réponse au client',
    'Internal note, not visible to the client' => 'Note interne, invisible du client',

    // Ticket view: identity line and client subtitle (cdr_ticket_idline:
    // "Léa Marchand, Maison Verdier. Reçu le 21/10/2026 à 08:47 via Web.")
    'Support contract' => 'Contrat de support',
    '%1$s at %2$s' => '%1$s à %2$s',
    'Received on %1$s via %2$s.' => 'Reçu le %1$s via %2$s.',
    'Received on %s.' => 'Reçu le %s.',
    // Ticket view: labelled buttons of the action bar (ticket-chrome.js);
    // own msgids, the pack's "Edit" is "Éditer"
    'Edit ticket' => 'Modifier',
    'Print ticket' => 'Imprimer',
    'Mark as resolved' => 'Marquer comme résolu',
    // the cluster of core's other actions (reply, note, status, assign...)
    'Other ticket actions' => 'Autres actions sur le ticket',
    // Reply and note forms: "Options" disclosure and the line above the
    // editor (mockup .compose .to); "modified" (below) marks a folded field
    // that differs from its default
    'Sending options' => "Options d'envoi",
    'Note options' => 'Options de la note',
    'To: %s' => 'À : %s',
    'Visible to the team only. The client receives nothing.' => "Visible de l'équipe uniquement. Le client ne reçoit rien.",
    // "Log" tab of the ticket view (the thread's events only)
    'Log' => 'Journal',
    'No event has been recorded for this ticket yet.' => "Aucun événement n'a encore été enregistré pour ce ticket.",

    // Queue list (queue.js): selection counter, header check box
    'No ticket selected' => 'Aucune sélection', // short: the bar holds 6 actions at 1280
    '%d ticket selected' => '%d ticket sélectionné',
    '%d tickets selected' => '%d tickets sélectionnés',
    'Select all tickets' => 'Tout sélectionner',

    // Login page (login.tpl.php)
    'Presentation' => 'Présentation',
    'Every Priority ticket has its own countdown.' => 'Chaque ticket Priority a son compte à rebours.',
    'Panel reserved for the Coderise team. Customers open and follow their requests on the support portal.' => "Panneau réservé à l'équipe Coderise. Les clients ouvrent et suivent leurs demandes sur le portail de support.",
    'The code that makes your projects %s' => 'Le code qui fait %s vos projets',
    'take off' => 'décoller',
    'Staff Control Panel' => 'Panneau de contrôle des agents',
    'Team sign-in' => "Connexion à l'équipe",
    'Use your Coderise username.' => 'Utilisez votre identifiant Coderise.',
    'Authentication Required' => 'Authentification requise',
    'Check the username and password, then try again.' => "Vérifiez l'identifiant et le mot de passe, puis réessayez.",
    'If the information provided is valid a password reset email will be sent to the email address you have on file. Follow the link in the email to reset your password.' => "Si les informations fournies sont valides, un e-mail de réinitialisation du mot de passe sera envoyé à l'adresse enregistrée dans votre compte. Suivez le lien contenu dans cet e-mail pour réinitialiser votre mot de passe.",
    'Verifying' => 'Vérification',
    'Verify' => 'Vérifier',
    'Email or Username' => 'Identifiant ou adresse e-mail',
    'Password' => 'Mot de passe',
    'Log In' => 'Se connecter',
    'Forgot My Password' => 'Mot de passe oublié',
    'Copyright' => 'Copyright',
    'Powered by' => 'Propulsé par',

    // Dashboard panels (coderise/dashboard.inc.php). "\u{A0}" = non-breaking space
    'From %1$s to %2$s.' => 'Du %1$s au %2$s.',
    '1 Priority ticket is over or close to its %s first reply.' => "1 ticket Priority a dépassé ou risque de dépasser son premier retour de %s.",
    '%1$d Priority tickets are over or close to their %2$s first reply.' => "%1\$d tickets Priority ont dépassé ou risquent de dépasser leur premier retour de %2\$s.",
    'It is already over by %s.' => "Il l'a déjà dépassé de %s.",
    'The oldest is already over by %s.' => "Le plus ancien l'a déjà dépassé de %s.",
    'Its deadline is in %s.' => "Son échéance est dans %s.",
    'The nearest deadline is in %s.' => "L'échéance la plus proche est dans %s.",
    'View the ticket' => 'Voir le ticket',
    'View the %d tickets' => 'Voir les %d tickets',
    'View all (%d)' => 'Tout voir (%d)',
    '%d d' => "%d\u{A0}j",
    '%d h' => "%d\u{A0}h",
    '%d%%' => "%d\u{A0}%%",
    '%+d%% vs the previous %d days' => "%+d\u{A0}%% vs les %d\u{A0}j précédents",
    'Stable against the previous %d days' => "Stable vs les %d\u{A0}j précédents",
    'No ticket in the previous period' => 'Aucun ticket sur la période précédente',
    'No ticket in this period' => 'Aucun ticket sur cette période',
    'Tickets received' => 'Tickets reçus',
    'Open tickets' => 'Tickets ouverts',
    '%d unassigned' => '%d sans agent',
    'All assigned' => 'Tous assignés',
    'Average first reply, Priority' => 'Premier retour moyen, Priority',
    'No Priority ticket answered in this period' => "Aucun ticket Priority n'a reçu de réponse sur cette période",
    'Target of %s met' => 'Objectif de %s tenu',
    'Target of %s missed' => 'Objectif de %s manqué',
    'First replies within the target' => "Premiers retours dans l'objectif",
    'No Priority ticket to measure in this period' => 'Aucun ticket Priority à mesurer sur cette période',
    '%1$d of %2$d Priority tickets' => '%1$d sur %2$d tickets Priority',
    'Tickets closed' => 'Tickets fermés',
    'In this period' => 'Sur cette période',
    'Overdue tickets' => 'Tickets en retard',
    'Open and past their due date' => "Ouverts et au-delà de l'échéance",
    'None overdue' => 'Aucun en retard',
    'First reply at risk' => 'Premier retour à risque',
    'No Priority ticket is waiting past 75 % of its first-reply window.' => "Aucun ticket Priority n'a consommé plus de 75 % de son délai de premier retour.",
    'Priority tickets close to or past the first-reply target' => "Tickets Priority proches ou au-delà de l'objectif de premier retour",
    'By help topic' => "Par sujet d'aide",
    'No ticket with a help topic in this period.' => "Aucun ticket avec un sujet d'aide sur cette période.",
    'Load by agent' => 'Charge par agent',
    'Open tickets by agent' => 'Tickets ouverts par agent',
    'Open tickets and first-reply target by agent' => "Tickets ouverts et respect du premier retour par agent",
    'Open' => 'Ouverts',
    'Load' => 'Charge',
    'First reply met' => 'Premier retour tenu',
    '%1$d tickets, the busiest agent has %2$d' => "%1\$d tickets, l'agent le plus chargé en a %2\$d",
    'To handle' => 'À traiter',

    // Admin forms (changed-field marks, save bar counter)
    'modified' => 'modifié',
    '%d setting changed' => '%d réglage modifié',
    '%d settings changed' => '%d réglages modifiés',
);
