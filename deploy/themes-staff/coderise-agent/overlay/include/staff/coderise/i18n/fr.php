<?php
/*********************************************************************
    include/staff/coderise/i18n/fr.php

    French catalogue of the "coderise-agent" staff theme, keyed by the
    English msgid (see cdr__() in ../coderise.inc.php). osTicket's own
    language pack, when installed, wins over this file.

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
    'Skip to content' => 'Aller au contenu',
    'Breadcrumb' => "Fil d'Ariane",
    'Ticket #%s' => 'Ticket n° %s',
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

    // Login page (login.tpl.php)
    'Presentation' => 'Présentation',
    'Every Priority ticket has its own countdown.' => 'Chaque ticket Priority a son compte à rebours.',
    'Panel reserved for the Coderise team. Customers open and follow their requests on the support portal.' => "Panneau réservé à l'équipe Coderise. Les clients ouvrent et suivent leurs demandes sur le portail de support.",
    'The code that makes your projects %s' => 'Le code qui fait %s vos projets',
    'take off' => 'décoller',
    'Staff Control Panel' => 'Panneau de contrôle des agents',
    'Team sign-in' => "Connexion à l'équipe",
    'Use your Coderise username.' => 'Utilisez votre identifiant Coderise.',
    'Verifying' => 'Vérification',
    'Verify' => 'Vérifier',
    'Email or Username' => 'Identifiant ou adresse e-mail',
    'Password' => 'Mot de passe',
    'Log In' => 'Se connecter',
    'Forgot My Password' => 'Mot de passe oublié',
    'Copyright' => 'Copyright',
    'Powered by' => 'Propulsé par',

    // Admin forms (changed-field marks, save bar counter)
    'modified' => 'modifié',
    '%d setting changed' => '%d réglage modifié',
    '%d settings changed' => '%d réglages modifiés',
);
