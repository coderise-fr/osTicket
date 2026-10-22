<?php
/* Theme "Poste de travail" (Coderise): French strings that osTicket's language
 * packs do not contain (used by ptdt__() only when __() has no translation).
 * "\u{a0}" is a no-break space (French typography before : ? ! and in "4 h"). */
if (!defined('INCLUDE_DIR')) die('Access Denied');

return array(
    // Layout
    'Skip to main content' => 'Aller au contenu',
    'Main menu' => 'Navigation principale',
    'Menu' => 'Menu',
    'Home' => 'Accueil',
    'Breadcrumb' => "Fil d'Ariane",
    'The code that makes your projects %s' => 'Le code qui fait %s vos projets',
    'take off' => 'décoller',
    'Support Priority' => 'Support Priority',
    'First response within 4 h, 24/7' => "Premier retour sous 4\u{a0}h, 24\u{a0}h/24",
    // Footer
    'Contact us' => 'Nous contacter',
    'Response times' => 'Délais de réponse',
    'Standard support:' => "Support standard\u{a0}:",
    'business days.' => 'jours ouvrés.',
    'Priority support:' => "Support Priority\u{a0}:",
    'first response within 4 h, 24/7.' => "premier retour sous 4\u{a0}h, 24\u{a0}h/24, 7\u{a0}j/7.",
    'Company hours (Monday to Friday, 8:30 am to 5:00 pm) are not those of Priority support.'
        => "Les horaires de l'entreprise (lundi au vendredi, 8h30 à 17h00) ne sont pas ceux du support Priority.",
    'Follow Coderise' => 'Suivre Coderise',
    'Standard support: business days. Priority: 24/7.'
        => "Support standard\u{a0}: jours ouvrés\u{a0}; Priority\u{a0}: 24\u{a0}h/24.",
    // Home
    "Hello %s, let's take your project up a level." => "Bonjour %s, faisons monter votre projet d'un niveau.",
    'The team answered, your reply is awaited:' => "L'équipe a répondu, votre réponse est attendue\u{a0}:",
    'No ticket is waiting for your reply.' => "Aucun ticket n'attend votre réponse.",
    'Open a ticket, follow it to the end, find answers in the knowledge base.'
        => "Ouvrez un ticket, suivez-le jusqu'au bout, trouvez vos réponses dans la base de connaissances.",
    'Awaiting your reply' => 'Attend votre réponse',
    'View all' => 'Tout voir',
    'Browse all' => 'Tout parcourir',
    'You have no open tickets.' => "Aucun ticket ouvert. Tout roule.",
    'Something is blocking? Tell us in a ticket.' => "Un blocage\u{a0}? Décrivez-le dans un ticket.",
    'A blocking incident?' => "Un incident bloquant\u{a0}?",
    'Open a ticket, then call us: a person answers.' => "Ouvrez un ticket, puis appelez-nous\u{a0}: une personne vous répond.",
    // Ticket list
    '%1$d open, %2$d closed' => '%1$d ouverts, %2$d fermés',
    'Ticket number or subject' => 'Numéro ou mot du sujet',
    'No open ticket. All good.' => 'Aucun ticket ouvert. Tout roule.',
    // Ticket view
    'Messages: %1$d · attachments: %2$d' => "Messages\u{a0}: %1\$d · pièces jointes\u{a0}: %2\$d",
    'Priority first response' => 'Délai Priority',
    'First response within 4 h (Priority, 24/7)' => "Premier retour sous 4\u{a0}h (Priority, 24\u{a0}h/24)",
    '%d%% of the time elapsed' => '%d %% du délai écoulé',
    'Expected by' => 'Attendu avant le',
    'The team has not answered yet.' => "L'équipe n'a pas encore répondu.",
    'Coderise team' => 'Équipe Coderise',
    'You' => 'Vous',
    // Contract level (optional plugin contrib/sla-first-response)
    'Support %s' => 'Support %s',
    'Answers on business days' => 'Réponse les jours ouvrés',
    'Your contract' => 'Votre contrat',
    'Level' => 'Niveau',
    'Contract' => 'Contrat',
    'First response within 4 h, 24 h a day, 7 days a week' => "Premier retour sous 4\u{a0}h, 24\u{a0}h/24, 7\u{a0}j/7",
    'Business days' => 'Jours ouvrés',
    "Current contract level of the ticket's requester" => 'Niveau de contrat actuel du demandeur du ticket',
);
