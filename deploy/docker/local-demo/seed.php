<?php
/*********************************************************************
    seed.php

    Environnement local de DÉMONSTRATION uniquement (jamais en
    production) : configure osTicket et le peuple de données fictives.
    Exécuté par deploy/docker/scripts/local-demo.sh dans un conteneur
    ponctuel de l'image de démo (`php` lit ce fichier sur son entrée
    standard, précédé d'un prélude qui définit $DEMO : phase, URL,
    mots de passe générés). Aucun mot de passe n'est affiché.

    Deux phases, deux processus :
      config   réglages, messagerie (Mailpit), plans de SLA, filtre,
               plugin sla_first_response, organisations, comptes,
               formulaire, rubriques, base de connaissances, pages ;
      tickets  tickets, réponses, pièces jointes (le plugin, activé à la
               phase précédente, est chargé au démarrage de ce
               processus : il bascule le plan de SLA à la première
               réponse adressée au client).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (PHP_SAPI !== 'cli' || !isset($DEMO) || !is_array($DEMO))
    die("seed.php : à lancer par deploy/docker/scripts/local-demo.sh\n");

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
$demo_url = parse_url($DEMO['base_url']);
$_SERVER['HTTP_HOST'] = $demo_url['host'] . (isset($demo_url['port']) ? ':' . $demo_url['port'] : '');
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
chdir('/var/www/html');
require '/var/www/html/main.inc.php';
require_once INCLUDE_DIR . 'class.ticket.php';
require_once INCLUDE_DIR . 'class.sla.php';
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.organization.php';
require_once INCLUDE_DIR . 'class.staff.php';
require_once INCLUDE_DIR . 'class.dept.php';
require_once INCLUDE_DIR . 'class.role.php';
require_once INCLUDE_DIR . 'class.topic.php';
require_once INCLUDE_DIR . 'class.category.php';
require_once INCLUDE_DIR . 'class.faq.php';
require_once INCLUDE_DIR . 'class.page.php';
require_once INCLUDE_DIR . 'class.schedule.php';
require_once INCLUDE_DIR . 'class.dynamic_forms.php';
require_once INCLUDE_DIR . 'class.email.php';

// --- Données de la démo (fictives : domaines réservés .test) -------------

$D_STAFF_DOMAIN = 'coderise-demo.test';
$D_PRIORITY_PLAN = 'Priority 4h';
$D_DONE_PLAN = 'Priority 4h - 1re reponse faite';
$D_FILTER = 'Priority : plan Priority 4h';    // 32 caractères au plus
$D_AGENT = array('username' => 'theo.martin', 'firstname' => 'Théo', 'lastname' => 'Martin',
    'email' => 'theo.martin@' . $D_STAFF_DOMAIN);
$D_CLIENTS = array(
    'lea'  => array('name' => 'Léa Moreau', 'email' => 'lea.moreau@lumen-studio.test', 'org' => 'Priority'),
    'hugo' => array('name' => 'Hugo Bernard', 'email' => 'hugo.bernard@acme.test', 'org' => 'Acme'),
    'nina' => array('name' => 'Nina Petit', 'email' => 'nina.petit@mail-perso.test', 'org' => null),
    'marc' => array('name' => 'Marc Lefèvre', 'email' => 'marc.lefevre@acme.test', 'org' => 'Acme'),
);
// Invitée : pas de compte, accès au portail par e-mail + numéro de ticket.
$D_GUEST = array('name' => 'Gina Roux', 'email' => 'gina.roux@lumen-studio.test', 'org' => 'Priority');
$D_NOTE = 'Compte fictif de l\'environnement local.';

$nfail = 0;
function demo_say($msg) { echo "  $msg\n"; }
function demo_fail($msg) { global $nfail; $nfail++; echo "  [ÉCHEC] $msg\n"; }
function demo_out($key, $value) { echo "OUT $key=$value\n"; }
function demo_one($sql) { return db_result(db_query($sql)); }

function sla_id($name) {
    $row = SLA::objects()->filter(array('name' => $name))->values_flat('id')->first();
    return $row ? (int) $row[0] : 0;
}
function topic_id($name) {
    return (int) Topic::getIdByName($name);
}
function staff_by_username($username) {
    return Staff::lookup(array('username' => $username));
}

if ($DEMO['phase'] == 'config')
    demo_config($DEMO);
elseif ($DEMO['phase'] == 'tickets')
    demo_tickets($DEMO);
else
    demo_fail('phase inconnue');

echo $nfail ? "seed.php : $nfail échec(s)\n" : "seed.php : phase {$DEMO['phase']} terminée\n";
exit($nfail ? 1 : 0);

// =========================================================================
// Phase 1 : configuration
// =========================================================================
function demo_config($DEMO) {
    global $cfg, $ost, $D_STAFF_DOMAIN, $D_PRIORITY_PLAN, $D_DONE_PLAN, $D_FILTER,
        $D_AGENT, $D_CLIENTS, $D_GUEST, $D_NOTE;

    echo "== Réglages\n";
    // Domaines de démo en .test : aucune vérification DNS (MX) des adresses.
    $settings = array(
        'verify_email_addrs' => 0,
        'default_timezone' => 'Europe/Paris',
        'enable_kb' => 1,
        'client_registration' => 'public',
        'ticket_autoresponder' => 1,
        'message_autoresponder' => 1,
        'enable_avatars' => 1,
        'helpdesk_title' => 'Support Coderise',
        'helpdesk_url' => $DEMO['base_url'],
    );
    foreach ($settings as $k => $v)
        $cfg->set($k, (string) $v);
    // Une clé absente de la table (valeur par défaut du code) est créée sans
    // mise à jour de la copie en mémoire : relecture.
    $cfg->load();
    if ($cfg->verifyEmailAddrs())
        demo_fail('vérification MX toujours active');
    demo_say('réglages système : vérification MX désactivée, Europe/Paris, base de connaissances, inscription publique');

    echo "== Messagerie : Mailpit (SMTP sans authentification)\n";
    $first = 0;
    foreach (Email::objects() as $email) {
        $first = $first ?: $email->getId();
        $smtp = $email->getSmtpAccount();
        $errors = array();
        if (!$smtp->update(array('smtp_active' => 1, 'smtp_host' => 'mailpit',
                'smtp_port' => 1025, 'smtp_auth_bk' => 'none'), $errors))
            demo_fail('SMTP de ' . $email->getEmail() . ' : ' . json_encode($errors));
        else
            demo_say('SMTP de ' . $email->getEmail() . ' : mailpit:1025');
    }
    if ($first)
        $cfg->set('default_smtp_id', $first);

    echo "== Plans de SLA\n";
    $sched = Schedule::objects()->filter(array('name' => '24/7'))->first();
    $schedId = $sched ? $sched->getId() : 0;
    if (!$schedId)
        demo_fail('calendrier 24/7 introuvable');
    foreach (array(array($D_PRIORITY_PLAN, 1), array($D_DONE_PLAN, 0)) as $p) {
        list($name, $active) = $p;
        if (sla_id($name))
            continue;
        $sla = SLA::create();
        $errors = array();
        $ok = $sla->update(array('name' => $name, 'grace_period' => 4, 'schedule_id' => $schedId,
            'isactive' => $active, 'notes' => $active
                ? 'Support Priority : premier retour sous 4 h, 24 h/24 et 7 j/7.'
                : 'Posé par le plugin sla_first_response après la première réponse. Doit rester désactivé et non transitoire.'),
            $errors);
        if (!$ok)
            demo_fail("plan $name : " . json_encode($errors));
    }
    // À la création, des drapeaux à 0 ne sont pas écrits (valeur par défaut de la
    // colonne : actif) ; comme dans l'administration, « créer puis désactiver ».
    db_query('UPDATE ' . SLA_TABLE . ' SET flags=0 WHERE name=' . db_input($D_DONE_PLAN));
    $watch = sla_id($D_PRIORITY_PLAN);
    $done = sla_id($D_DONE_PLAN);
    demo_say("plans : « $D_PRIORITY_PLAN » (n° $watch, actif, 24/7), « $D_DONE_PLAN » (n° $done, désactivé)");

    echo "== Organisations, agents et clients\n";
    $orgs = array();
    foreach (array('Priority', 'Acme') as $name) {
        $orgs[$name] = Organization::fromVars(array('name' => $name));
        if (!$orgs[$name])
            demo_fail("organisation $name");
    }
    db_query('UPDATE ' . ORGANIZATION_TABLE . ' SET manager="", domain=""'
        . ' WHERE id IN (' . (int) $orgs['Priority']->getId() . ',' . (int) $orgs['Acme']->getId() . ')');

    $support = Dept::getIdByName('Support');
    $role = Role::objects()->filter(array('name' => 'Expanded Access'))->first();
    if (!$support || !$role)
        demo_fail('département Support ou rôle « Expanded Access » introuvable');
    if (!staff_by_username($D_AGENT['username'])) {
        $agent = Staff::create();
        $errors = array();
        $vars = $D_AGENT + array('dept_id' => $support, 'role_id' => $role->getId(),
            'isvisible' => 1, 'passwd1' => $DEMO['pw']['agent'], 'passwd2' => $DEMO['pw']['agent'],
            'backend' => '', 'notes' => $D_NOTE, 'assign_use_pri_role' => 1,
            'perms' => array('user.create', 'user.edit', 'user.dir', 'org.create', 'org.edit', 'faq.manage'));
        if (!$agent->update($vars, $errors))
            demo_fail('agent : ' . json_encode($errors));
    }
    demo_say('agent : ' . $D_AGENT['username'] . ' (département Support)');

    foreach ($D_CLIENTS as $key => $c) {
        $user = User::fromVars(array('name' => $c['name'], 'email' => $c['email']));
        if (!$user) {
            demo_fail('client ' . $c['email']);
            continue;
        }
        $user->setOrganization($c['org'] ? $orgs[$c['org']] : null);
        if (!$user->getAccount()) {
            $errors = array();
            $acct = UserAccount::register($user, array('passwd1' => $DEMO['pw'][$key],
                'passwd2' => $DEMO['pw'][$key], 'timezone' => 'Europe/Paris', 'backend' => 'client'), $errors);
            if (!$acct)
                demo_fail('compte de ' . $c['email'] . ' : ' . json_encode($errors));
        }
        demo_say(sprintf('client : %s <%s> (%s)', $c['name'], $c['email'], $c['org'] ?: 'sans organisation'));
    }
    $guest = User::fromVars(array('name' => $D_GUEST['name'], 'email' => $D_GUEST['email']));
    if ($guest)
        $guest->setOrganization($orgs[$D_GUEST['org']]);
    demo_say(sprintf('invitée (sans compte) : %s <%s>', $D_GUEST['name'], $D_GUEST['email']));

    echo "== Filtre « Organisation / Nom = Priority » -> plan $D_PRIORITY_PLAN\n";
    $orgField = demo_one('SELECT ff.id FROM ' . FORM_FIELD_TABLE . ' ff JOIN ' . FORM_SEC_TABLE
        . " f ON f.id=ff.form_id WHERE f.type='O' AND ff.name='name'");
    $fid = (int) demo_one('SELECT id FROM ' . FILTER_TABLE . ' WHERE name=' . db_input($D_FILTER));
    if (!$fid) {
        db_query('INSERT INTO ' . FILTER_TABLE . ' (execorder, isactive, flags, status, match_all_rules,'
            . ' stop_onmatch, target, email_id, name, notes, created, updated) VALUES (1, 1, 0, 0, 1, 0,'
            . " 'Any', 0, " . db_input($D_FILTER) . ', ' . db_input('Les clients de'
            . ' l\'organisation Priority reçoivent le plan Priority 4h à la création du ticket.')
            . ', NOW(), NOW())');
        $fid = db_insert_id();
        db_query('INSERT INTO ' . FILTER_RULE_TABLE . ' (filter_id, what, how, val, isactive, notes,'
            . " created, updated) VALUES ($fid, " . db_input("field.$orgField") . ", 'equal', 'Priority',"
            . " 1, '', NOW(), NOW())");
        db_query('INSERT INTO ' . FILTER_ACTION_TABLE . ' (filter_id, sort, type, configuration, updated)'
            . " VALUES ($fid, 1, 'sla', " . db_input(json_encode(array('sla_id' => (string) $watch)))
            . ', NOW())');
    }
    demo_say("filtre n° $fid");

    echo "== Plugin sla_first_response\n";
    $plugin = Plugin::objects()->filter(array('install_path' => 'plugins/sla_first_response'))->first();
    if (!$plugin)
        $plugin = $ost->plugins->install('plugins/sla_first_response');
    if (!$plugin) {
        demo_fail('installation du plugin (dossier include/plugins/sla_first_response absent de l\'image ?)');
    }
    else {
        $plugin = PluginManager::lookup($plugin->getId());
        $plugin->isactive = 1;
        $plugin->save();
        $vars = array('name' => 'SLA premiere reponse', 'isactive' => 1, 'notes' => '',
            'sla_watch' => $watch, 'sla_done' => $done,
            'contract_org' => (string) $orgs['Priority']->getId(),
            'contract_label_priority' => 'Priority', 'contract_label_standard' => 'Standard');
        $inst = PluginInstance::objects()->filter(array('plugin_id' => $plugin->getId()))->first();
        $errors = array();
        // Même chemin que le formulaire d'administration (POST).
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $vars;
        $ok = $inst ? $inst->update($vars, $errors) : $plugin->addInstance($vars, $errors);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = array();
        if (!$ok || $errors)
            demo_fail('instance du plugin : ' . json_encode($errors));
        $inst = PluginInstance::objects()->filter(array('plugin_id' => $plugin->getId()))->first();
        $c = $inst ? new Config($inst->getNamespace()) : null;
        if (!$c || $c->get('sla_watch') != $watch || $c->get('sla_done') != $done
                || $c->get('contract_org') !== (string) $orgs['Priority']->getId())
            demo_fail('configuration du plugin non enregistrée');
        else
            demo_say('plugin actif, instance active : plans ' . $watch . ' / ' . $done
                . ', organisation Priority n° ' . $orgs['Priority']->getId());
    }

    echo "== Formulaire « Contexte technique » et rubriques d'aide\n";
    $form = DynamicForm::objects()->filter(array('title' => 'Contexte technique'))->first();
    if (!$form) {
        $flags = DynamicFormField::FLAG_ENABLED | DynamicFormField::FLAG_CLIENT_VIEW
            | DynamicFormField::FLAG_CLIENT_EDIT | DynamicFormField::FLAG_AGENT_VIEW
            | DynamicFormField::FLAG_AGENT_EDIT;
        $form = DynamicForm::create(array(
            'type' => 'G', 'title' => 'Contexte technique',
            'instructions' => 'Ces informations nous aident à reproduire le problème plus vite.',
            'notes' => 'Formulaire (champs dynamiques).',
            'fields' => array(
                array('type' => 'choices', 'name' => 'environnement', 'label' => 'Environnement concerné',
                    'sort' => 1, 'flags' => $flags | DynamicFormField::FLAG_CLIENT_REQUIRED,
                    'configuration' => array('choices' => "production:Production\nrecette:Recette\ndeveloppement:Développement")),
                array('type' => 'choices', 'name' => 'impact', 'label' => 'Impact', 'sort' => 2, 'flags' => $flags,
                    'configuration' => array('choices' => "bloquant:Bloquant, plus personne ne travaille\nmajeur:Majeur, contournement possible\nmineur:Mineur")),
                array('type' => 'text', 'name' => 'url_concernee', 'label' => 'Adresse (URL) concernée', 'sort' => 3,
                    'flags' => $flags, 'configuration' => array('size' => 40, 'length' => 255)),
                array('type' => 'datetime', 'name' => 'debut_incident', 'label' => 'Début de l\'incident', 'sort' => 4,
                    'flags' => $flags, 'configuration' => array('time' => true)),
                array('type' => 'bool', 'name' => 'reproductible', 'label' => 'Le problème est reproductible',
                    'sort' => 5, 'flags' => $flags),
            ),
        ));
        $form->save(true);
    }
    $ticketForm = TicketForm::getInstance();
    $topics = array(
        array('Incident en production', 1, array($ticketForm->getId(), $form->getId())),
        array('Question sur l\'utilisation', 2, array($ticketForm->getId())),
        array('Demande d\'évolution', 3, array($ticketForm->getId())),
        array('Facturation et abonnement', 2, array($ticketForm->getId())),
    );
    foreach ($topics as $t) {
        list($name, $prio, $forms) = $t;
        if (topic_id($name))
            continue;
        $topic = Topic::create();
        $errors = array();
        if (!$topic->update(array('topic' => $name, 'status' => 'active', 'ispublic' => 1,
                'dept_id' => $support, 'priority_id' => $prio, 'forms' => $forms,
                'notes' => 'Rubrique.'), $errors))
            demo_fail("rubrique $name : " . json_encode($errors));
    }
    // Les rubriques d'origine restent actives mais ne sont plus proposées aux clients.
    $ours = array();
    foreach ($topics as $t)
        $ours[] = topic_id($t[0]);
    // Formulaire du ticket (sujet, message) en tête de chaque rubrique : constaté,
    // Topic::update() ne l'associe pas à une rubrique créée hors de l'interface.
    $tform = (int) demo_one('SELECT id FROM ' . FORM_SEC_TABLE . " WHERE type='T' ORDER BY id LIMIT 1");
    foreach ($ours as $tid) {
        if (!demo_one('SELECT COUNT(*) FROM ' . TOPIC_FORM_TABLE . " WHERE topic_id=$tid AND form_id=$tform"))
            db_query('INSERT INTO ' . TOPIC_FORM_TABLE . " (topic_id, form_id, sort, extra) VALUES ($tid, $tform, 1, '{\"disable\":[]}')");
    }
    db_query('UPDATE ' . TOPIC_TABLE . ' SET ispublic=0 WHERE topic_id NOT IN (' . implode(',', $ours) . ')');
    $cfg->set('default_help_topic', 0);
    demo_say('formulaire n° ' . $form->getId() . ' sur « Incident en production » ; 4 rubriques publiques');

    echo "== Base de connaissances\n";
    $kb = array(
        array('Premiers pas', 'Ouvrir et suivre une demande sur le portail.', Category::VISIBILITY_FEATURED, array(
            array('Comment ouvrir un ticket ?', '<p>Depuis l\'accueil, choisissez <strong>Ouvrir un ticket</strong>, la rubrique qui correspond à votre demande, puis décrivez le problème. Vous recevez un accusé de réception par e-mail avec le numéro du ticket.</p>', FAQ::VISIBILITY_FEATURED),
            array('Suivre un ticket sans créer de compte', '<p>Utilisez <strong>Vérifier l\'état d\'un ticket</strong> : saisissez l\'adresse e-mail utilisée et le numéro du ticket. Un lien d\'accès vous est envoyé par e-mail.</p>', FAQ::VISIBILITY_PUBLIC),
        )),
        array('Compte et facturation', 'Accès au portail, factures, abonnement.', Category::VISIBILITY_PUBLIC, array(
            array('J\'ai oublié mon mot de passe', '<p>Sur la page de connexion, choisissez <strong>Mot de passe oublié</strong> : un lien de réinitialisation est envoyé à votre adresse.</p>', FAQ::VISIBILITY_PUBLIC),
            array('Où trouver mes factures ?', '<p>Les factures sont envoyées chaque mois par e-mail. Pour un duplicata, ouvrez un ticket dans la rubrique <em>Facturation et abonnement</em>.</p>', FAQ::VISIBILITY_PUBLIC),
        )),
        array('Incidents et disponibilité', 'Que faire en cas de panne ou de lenteur.', Category::VISIBILITY_FEATURED, array(
            array('Mon site affiche une erreur 500', '<p>Notez l\'heure, l\'adresse de la page et ce que vous faisiez, puis ouvrez un ticket dans la rubrique <strong>Incident en production</strong>. Joignez une capture d\'écran si possible.</p>', FAQ::VISIBILITY_FEATURED),
            array('Le site est lent : quelles informations fournir ?', '<p>Indiquez les pages concernées, l\'heure du constat et votre connexion (bureau, mobile). Une mesure avec l\'outil de développement du navigateur nous aide beaucoup.</p>', FAQ::VISIBILITY_PUBLIC),
        )),
        array('Support Priority', 'Délais et fonctionnement du contrat Priority.', Category::VISIBILITY_FEATURED, array(
            array('Quel délai de réponse avec le support Priority ?', '<p>Avec le contrat Priority, un membre de l\'équipe vous apporte un premier retour sous <strong>4 heures</strong>, jour et nuit, 7 jours sur 7.</p>', FAQ::VISIBILITY_FEATURED),
            array('Comment savoir si mon compte est Priority ?', '<p>Une fois connecté, votre niveau de contrat (Priority ou Standard) est affiché sur la liste de vos tickets et dans le suivi de chaque ticket.</p>', FAQ::VISIBILITY_PUBLIC),
        )),
        array('Bonnes pratiques', 'Sauvegardes, mises à jour et sécurité.', Category::VISIBILITY_PUBLIC, array(
            array('À quelle fréquence sauvegarder ?', '<p>Une sauvegarde quotidienne, conservée hors du serveur, et un test de restauration chaque trimestre.</p>', FAQ::VISIBILITY_PUBLIC),
            array('Planifier une mise à jour sans interruption', '<p>Prévenez-nous au moins 48 heures avant par un ticket <em>Demande d\'évolution</em> : nous préparons une recette et un retour arrière.</p>', FAQ::VISIBILITY_PUBLIC),
        )),
    );
    $nfaq = 0;
    foreach ($kb as $cat) {
        list($cname, $cdesc, $cvis, $faqs) = $cat;
        $c = Category::lookup(array('name' => $cname));
        if (!$c) {
            $c = Category::create();
            $errors = array();
            if (!$c->update(array('name' => $cname, 'description' => $cdesc, 'ispublic' => $cvis,
                    'notes' => 'Catégorie.'), $errors)) {
                demo_fail("catégorie $cname : " . json_encode($errors));
                continue;
            }
        }
        foreach ($faqs as $f) {
            list($q, $a, $vis) = $f;
            if (FAQ::findIdByQuestion($q)) {
                $nfaq++;
                continue;
            }
            $faq = FAQ::create();
            $errors = array();
            if ($faq->update(array('category_id' => $c->getId(), 'question' => $q, 'answer' => $a,
                    'ispublished' => $vis, 'notes' => '', 'topics' => array()), $errors))
                $nfaq++;
            else
                demo_fail("article $q : " . json_encode($errors));
        }
    }
    demo_say(count($kb) . " catégories, $nfaq articles");

    echo "== Pages (textes proposés par le README du thème Sommet)\n";
    $pages = array(
        'landing' => '<h1>Faisons monter votre projet d’un niveau.</h1>'
            . '<p>Un incident, une question, une évolution : écrivez-nous. Une personne de l’équipe vous répond, avec une vraie réponse.</p>',
        'thank-you' => '<h1>Merci, %{ticket.name.first}.</h1>'
            . '<p>Votre ticket <strong>#%{ticket.number}</strong> est enregistré. Nous vous répondons ici et par e-mail.</p>',
        'offline' => '<h1>Le support est en maintenance.</h1><p>Nous revenons très vite.</p>',
    );
    foreach ($pages as $type => $body) {
        $p = Page::lookupByType($type);
        if (!$p) {
            demo_fail("page $type introuvable");
            continue;
        }
        $p->body = $body;
        $p->save();
    }
    if (!Page::lookup(array('name' => 'Politique de support'))) {
        $p = Page::create(array('type' => 'other', 'name' => 'Politique de support', 'isactive' => 1,
            'notes' => 'Page.', 'updated' => SqlFunction::NOW(),
            'body' => '<h1>Politique de support</h1><p>Standard : réponse les jours ouvrés. Priority : premier retour sous 4 heures, 24 h/24, 7 j/7.</p>'));
        $p->save();
    }
    demo_say('accueil, remerciement, hors ligne, « Politique de support »');
}

// =========================================================================
// Phase 2 : tickets (le plugin est chargé au démarrage de ce processus)
// =========================================================================
function demo_tickets($DEMO) {
    global $thisstaff, $D_PRIORITY_PLAN, $D_DONE_PLAN, $D_AGENT, $D_CLIENTS, $D_GUEST;

    if (!function_exists('coderise_contract_level'))
        demo_fail('plugin sla_first_response non chargé (coderise_contract_level absente)');
    $watch = sla_id($D_PRIORITY_PLAN);
    $done = sla_id($D_DONE_PLAN);
    $admin = Staff::lookup(1);
    $agent = staff_by_username($D_AGENT['username']);
    $closed = TicketStatus::objects()->filter(array('state' => 'closed'))->first();
    $topics = array(
        'incident' => topic_id('Incident en production'),
        'question' => topic_id('Question sur l\'utilisation'),
        'evolution' => topic_id('Demande d\'évolution'),
        'facturation' => topic_id('Facturation et abonnement'),
    );
    if (!$admin || !$agent || !$closed || !$watch || !$done || in_array(0, $topics))
        return demo_fail('configuration incomplète : relancer la phase config');
    if ((int) demo_one('SELECT COUNT(*) FROM ' . TICKET_TABLE) > 3) {
        demo_say('des tickets existent déjà : rien à faire');
        // Numéros utiles au script (credentials.txt, captures), relus en base.
        $guest = User::lookupByEmail($D_GUEST['email']);
        if ($guest)
            demo_out('guest_ticket', demo_one('SELECT number FROM ' . TICKET_TABLE . ' WHERE user_id='
                . (int) $guest->getId() . ' ORDER BY ticket_id LIMIT 1'));
        $lea = User::lookupByEmail($D_CLIENTS['lea']['email']);
        if ($lea)
            demo_out('lea_priority_ticket', demo_one('SELECT number FROM ' . TICKET_TABLE . ' WHERE user_id='
                . (int) $lea->getId() . " AND sla_id=$watch AND est_duedate > NOW() ORDER BY ticket_id LIMIT 1"));
        return;
    }

    mt_srand(2026);
    $now = time();
    $stats = array('tickets' => 0, 'bascules' => 0, 'pj' => 0);

    // Pièces jointes fictives générées ici (aucun fichier externe).
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    $files = array(
        array('name' => 'capture-erreur.png', 'type' => 'image/png', 'data' => $png),
        array('name' => 'journal-apache.txt', 'type' => 'text/plain',
            'data' => "[démo] 2026-10-14 09:12:03 [error] AH01071: Got error 'PHP message: ...'\n[démo] données fictives\n"),
        array('name' => 'export-commandes.csv', 'type' => 'text/csv',
            'data' => "numero;date;montant\nDEMO-1001;2026-10-01;120,00\nDEMO-1002;2026-10-02;89,90\n"),
    );

    // Crée un ticket comme le portail (le filtre décide du plan de SLA).
    $create = function ($who, $subject, $message, $topic, $created, $extra = array())
            use (&$stats, $topics) {
        global $thisstaff;
        // Ticket du client, pas d'un agent : sinon l'événement « créé » et ceux du
        // filtre sont attribués à l'agent de la réponse précédente.
        $thisstaff = null;
        $vars = array('name' => $who['name'], 'email' => $who['email'], 'subject' => $subject,
            'message' => $message, 'topicId' => $topics[$topic], 'source' => 'Web',
            'ip' => '127.0.0.1') + $extra;
        $errors = array();
        $t = Ticket::create($vars, $errors, 'API', false, false);
        if (!$t) {
            demo_fail("ticket « $subject » : " . json_encode($errors));
            return null;
        }
        $stats['tickets']++;
        if (!empty($extra['attachments']))
            $stats['pj']++;
        $dt = date('Y-m-d H:i:s', $created);
        db_query('UPDATE ' . TICKET_TABLE . " SET created='$dt', updated='$dt', lastupdate='$dt'"
            . ' WHERE ticket_id=' . (int) $t->getId());
        db_query('UPDATE ' . THREAD_ENTRY_TABLE . " SET created='$dt', updated='$dt' WHERE thread_id="
            . (int) $t->getThreadId());
        db_query('UPDATE ' . THREAD_TABLE . " SET created='$dt' WHERE id=" . (int) $t->getThreadId());
        db_query('UPDATE ' . THREAD_EVENT_TABLE . " SET timestamp='$dt' WHERE thread_id="
            . (int) $t->getThreadId());
        $t->created = $dt;
        // Échéance recalculée depuis la date de création (calendrier du plan).
        $t->updateEstDueDate();
        return $t;
    };
    // Réponse d'agent adressée au client ($to = 'user') : première réponse = bascule du plan.
    $reply = function ($t, $staff, $text, $at, $to = 'user', $att = null) use (&$stats, $watch, $done) {
        global $thisstaff;
        $thisstaff = $staff;
        $before = $t->getSLAId();
        $errors = array();
        $vars = array('response' => $text, 'reply-to' => $to);
        if ($att)
            $vars['attachments'] = array($att);
        $r = $t->postReply($vars, $errors, false);
        if (!$r)
            return demo_fail('réponse : ' . json_encode($errors));
        if ($att)
            $stats['pj']++;
        $dt = date('Y-m-d H:i:s', $at);
        db_query('UPDATE ' . THREAD_ENTRY_TABLE . " SET created='$dt', updated='$dt' WHERE id=" . (int) $r->getId());
        db_query('UPDATE ' . TICKET_TABLE . " SET lastupdate='$dt' WHERE ticket_id=" . (int) $t->getId());
        $after = (int) demo_one('SELECT sla_id FROM ' . TICKET_TABLE . ' WHERE ticket_id=' . (int) $t->getId());
        if ($before == $watch && $after == $done)
            $stats['bascules']++;
        return $r;
    };
    $message = function ($t, $user, $text, $at) {
        $vars = array('message' => $text, 'userId' => $user->getId(), 'poster' => $user->getName());
        $m = $t->postMessage($vars, 'Web', false);
        if (!$m) {
            // Constaté pour un collaborateur juste après addCollaborator() dans le même
            // processus : le message est alors ajouté directement au fil.
            $errors = array();
            $m = $t->getThread()->addMessage($vars, $errors);
        }
        if (!$m)
            return demo_fail('message du client : ' . json_encode($errors));
        $dt = date('Y-m-d H:i:s', $at);
        db_query('UPDATE ' . THREAD_ENTRY_TABLE . " SET created='$dt', updated='$dt' WHERE id=" . (int) $m->getId());
        return $m;
    };
    $close = function ($t, $staff, $at) use ($closed) {
        global $thisstaff;
        $thisstaff = $staff;
        $errors = array();
        if (!$t->setStatus($closed, '', $errors))
            return demo_fail('fermeture : ' . json_encode($errors));
        $dt = date('Y-m-d H:i:s', $at);
        db_query('UPDATE ' . TICKET_TABLE . " SET closed='$dt', lastupdate='$dt' WHERE ticket_id=" . (int) $t->getId());
    };
    $ctx = function ($env, $impact, $url) {
        return array('environnement' => $env, 'impact' => $impact, 'url_concernee' => $url, 'reproductible' => 1);
    };

    $u = array();
    foreach ($D_CLIENTS as $k => $c)
        $u[$k] = User::lookupByEmail($c['email']);

    echo "== Tickets de Léa (organisation Priority)\n";
    $lea = array('incident' => array(
            'Erreur 500 sur la page de paiement', 'Les e-mails de confirmation de commande n\'arrivent plus',
            'Sauvegarde nocturne en échec depuis lundi', 'Certificat TLS expiré sur le site de recette',
            'Accès SFTP refusé pour le compte de déploiement', 'Webhook de paiement en erreur 400',
            'Page d\'accueil très lente le matin', 'Formulaire de contact : message d\'erreur à l\'envoi',
            'Images produits absentes après la mise en ligne', 'Le cache n\'est plus purgé après publication',
            'Tâche planifiée d\'import bloquée', 'Erreur de connexion à la base après redémarrage',
            'Panier vidé à la connexion du client', 'Le moteur de recherche ne trouve plus les produits'),
        'question' => array(
            'Ajouter un utilisateur administrateur', 'Comment exporter les commandes du mois ?',
            'Différence entre recette et production', 'Configurer la double authentification',
            'Où modifier les mentions légales ?', 'Durée de conservation des journaux',
            'Changer l\'adresse d\'expédition des e-mails', 'Droits d\'un compte « rédacteur »',
            'Planifier une publication à une date donnée', 'Restaurer une page supprimée'),
        'evolution' => array(
            'Filtre par date sur la liste des commandes', 'Ajout du paiement en trois fois',
            'Traduction anglaise du tunnel de commande', 'Export comptable au format FEC',
            'Nouveau gabarit pour les fiches produits', 'Connexion avec le logiciel de caisse',
            'Tableau de bord des ventes par canal', 'Envoi automatique des factures en PDF'),
        'facturation' => array(
            'Question sur la facture de septembre', 'Changement de moyen de paiement',
            'Ajout d\'un bon de commande sur la facture', 'Renouvellement du contrat Priority',
            'Duplicata de la facture d\'août', 'Mise à jour de l\'adresse de facturation',
            'Avoir pour la prestation annulée', 'Échéancier du contrat annuel'),
    );
    $subjects = array();
    foreach ($lea as $topic => $list)
        foreach ($list as $s)
            $subjects[] = array($topic, $s);
    // Ordre stable mais mélangé, 40 tickets du plus ancien au plus récent.
    usort($subjects, function ($a, $b) { return strcmp(md5($a[1]), md5($b[1])); });
    $subjects = array_slice($subjects, 0, 40);
    $leaPriority = $leaOverdue = null;
    foreach ($subjects as $i => $s) {
        list($topic, $subject) = $s;
        $extra = array();
        if ($topic == 'incident')
            $extra += $ctx($i % 3 ? 'production' : 'recette', $i % 4 ? 'majeur' : 'bloquant',
                'https://boutique.lumen-studio.test/');
        if ($i % 5 == 1)
            $extra['attachments'] = array($files[$i % 3]);
        $body = "Bonjour,\n\n$subject. Pouvez-vous regarder ?\n\nMerci,\nLéa";
        if ($i < 37) {
            // Du plus ancien (environ 160 jours) au plus récent (environ 2 jours).
            $created = $now - (160 - $i * 4) * 86400 - mt_rand(0, 8) * 3600 - mt_rand(0, 59) * 60;
        }
        elseif ($i == 37)
            $created = $now - 9 * 3600;            // sans réponse depuis 9 h : en retard
        elseif ($i == 38)
            $created = $now - 110 * 60;             // en attente du premier retour
        else
            $created = $now - 35 * 60;              // en attente du premier retour
        $t = $create($D_CLIENTS['lea'], $subject, $body, $topic, $created, $extra);
        if (!$t)
            continue;
        if ($t->getSLAId() != $watch)
            demo_fail("le filtre n'a pas posé le plan $D_PRIORITY_PLAN sur « $subject »");
        if ($i >= 37) {
            if ($i == 37)
                $leaOverdue = $t;
            else
                $leaPriority = $leaPriority ?: $t;
            continue;
        }
        // Première réponse adressée au client : 20 min à 3 h 30 après la création.
        $first = $created + mt_rand(20, 210) * 60;
        $staff = ($i % 4 == 0) ? $admin : $agent;
        $reply($t, $staff, "Bonjour Léa,\n\nMerci pour votre message, nous regardons tout de suite et revenons vers vous dans la journée.\n\n" . $staff->getFirstName() . ', équipe Coderise',
            $first, 'user', ($i % 7 == 3) ? $files[0] : null);
        if ($i % 3 == 0) {
            $message($t, $u['lea'], "Merci ! Je reste disponible si besoin.\n\nLéa", $first + 3600);
            $reply($t, $agent, "Le correctif est en place, pouvez-vous confirmer de votre côté ?\n\nThéo", $first + 5 * 3600);
        }
        if ($i < 14)
            $close($t, $staff, $first + 2 * 86400);
    }
    if ($leaPriority)
        demo_out('lea_priority_ticket', $leaPriority->getNumber());
    if ($leaOverdue)
        demo_out('lea_overdue_ticket', $leaOverdue->getNumber());

    echo "== Tickets de Hugo (Acme, Standard), de Nina (sans organisation), de Marc\n";
    $hugo = array(
        array('Mise à jour du module de livraison', 'question', 60, true),
        array('Erreur à l\'import du catalogue', 'incident', 40, true),
        array('Accès au back-office pour un stagiaire', 'question', 12, false),
        array('Devis pour une boutique en anglais', 'evolution', 3, false),
    );
    foreach ($hugo as $h) {
        list($subject, $topic, $days, $isClosed) = $h;
        $created = $now - $days * 86400 - mt_rand(1, 6) * 3600;
        $extra = ($topic == 'incident') ? $ctx('production', 'majeur', 'https://shop.acme.test/') : array();
        $t = $create($D_CLIENTS['hugo'], $subject, "Bonjour,\n\n$subject : merci de votre aide.\n\nHugo", $topic, $created, $extra);
        if (!$t)
            continue;
        if ($t->getSLAId() == $watch)
            demo_fail("ticket Standard de Hugo avec le plan $D_PRIORITY_PLAN");
        if ($days > 5)
            $reply($t, $agent, "Bonjour Hugo,\n\nC'est noté, nous vous répondons dès que possible.\n\nThéo, équipe Coderise", $created + 26 * 3600);
        if ($isClosed)
            $close($t, $agent, $created + 5 * 86400);
    }
    // Ticket partagé : Hugo est demandeur, Marc (Acme) et Léa (Priority) collaborateurs.
    $shared = $create($D_CLIENTS['hugo'], 'Accès partagé au tableau de bord des ventes',
        "Bonjour,\n\nPouvez-vous donner accès au tableau de bord à Marc et à Léa (agence) ?\n\nHugo",
        'question', $now - 6 * 86400, array('attachments' => array($files[2])));
    if ($shared) {
        foreach (array('marc', 'lea') as $k) {
            $errors = array();
            if (!$shared->addCollaborator($u[$k], array('isactive' => 1), $errors, false))
                demo_fail("collaborateur $k : " . json_encode($errors));
        }
        $reply($shared, $agent, "Bonjour à tous,\n\nLes accès sont ouverts. Marc et Léa, vous pouvez suivre ce ticket depuis votre espace.\n\nThéo, équipe Coderise", $now - 5 * 86400, 'all');
        $message($shared, $u['marc'], "Merci, l'accès fonctionne.\n\nMarc", $now - 5 * 86400 + 7200);
        demo_out('shared_ticket', $shared->getNumber());
    }
    $t = $create($D_CLIENTS['marc'], 'Export des ventes par magasin',
        "Bonjour,\n\nL'export ne contient pas le magasin de Lyon.\n\nMarc",
        'incident', $now - 2 * 86400, $ctx('production', 'mineur', 'https://shop.acme.test/admin/export'));
    $nina = array(
        array('Impossible de me connecter à mon espace', 'incident', 30, true),
        array('Combien coûte le contrat Priority ?', 'facturation', 4, false),
        array('Ajouter une page « Équipe » sur mon site', 'evolution', 1, false),
    );
    foreach ($nina as $n) {
        list($subject, $topic, $days, $isClosed) = $n;
        $created = $now - $days * 86400 - mt_rand(1, 6) * 3600;
        $extra = ($topic == 'incident') ? $ctx('production', 'bloquant', 'https://nina-petit.test/') : array();
        $t = $create($D_CLIENTS['nina'], $subject, "Bonjour,\n\n$subject\n\nNina", $topic, $created, $extra);
        if (!$t)
            continue;
        if ($days > 2)
            $reply($t, $agent, "Bonjour Nina,\n\nMerci pour votre message, voici notre réponse.\n\nThéo, équipe Coderise", $created + 20 * 3600);
        if ($isClosed)
            $close($t, $agent, $created + 3 * 86400);
    }

    echo "== Ticket de l'invitée (Priority, sans compte)\n";
    $g = $create($D_GUEST, 'Le formulaire de devis ne s\'envoie plus',
        "Bonjour,\n\nDepuis ce matin le formulaire de devis tourne sans fin.\n\nGina",
        'incident', $now - 50 * 60, $ctx('production', 'majeur', 'https://lumen-studio.test/devis'));
    if ($g) {
        demo_out('guest_ticket', $g->getNumber());
        demo_out('guest_email', $D_GUEST['email']);
    }

    demo_say(sprintf('%d tickets, %d bascules du plan par le plugin, %d messages avec pièce jointe',
        $stats['tickets'], $stats['bascules'], $stats['pj']));
    if ($stats['bascules'] < 30)
        demo_fail('trop peu de bascules du plan (plugin inactif ?)');
    demo_out('tickets', $stats['tickets']);
    demo_out('bascules', $stats['bascules']);
    demo_out('lea_tickets', (int) demo_one('SELECT COUNT(*) FROM ' . TICKET_TABLE . ' WHERE user_id=' . (int) $u['lea']->getId()));
}
?>
