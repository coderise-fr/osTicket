<?php
/*********************************************************************
    common.php

    Test d'intégration jetable du plugin « SLA : première réponse » :
    démarre osTicket en CLI et fournit quelques utilitaires. À exécuter
    uniquement sur une instance de test (voir run.ps1) : les scripts
    créent des tickets, vieillissent leurs dates et modifient la base.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
chdir('/app');
require '/app/main.inc.php';
require_once INCLUDE_DIR . 'class.ticket.php';
require_once INCLUDE_DIR . 'class.sla.php';
require_once INCLUDE_DIR . 'class.plugin.php';

$nfail = 0;
function check($label, $cond, $detail = '') {
    global $nfail;
    echo ($cond ? '  [OK]   ' : '  [FAIL] ') . $label . ($detail !== '' ? "  ($detail)" : '') . "\n";
    if (!$cond) $nfail++;
}
function sla_id($name) {
    list($id) = SLA::objects()->filter(array('name' => $name))->values_flat('id')->first();
    return (int) $id;
}
function fresh($id) {
    // relecture directe en base (évite le cache d'objets ORM)
    $r = db_query('SELECT sla_id, isoverdue, isanswered, est_duedate, status_id FROM ' . TICKET_TABLE . ' WHERE ticket_id=' . (int) $id);
    return db_fetch_array($r);
}
function nb_overdue_events($ticket_id) {
    $r = db_query('SELECT COUNT(*) FROM ' . THREAD_EVENT_TABLE . ' e JOIN ' . EVENT_TABLE . ' v ON v.id=e.event_id AND v.name="overdue" JOIN ' . THREAD_TABLE . ' th ON th.id=e.thread_id AND th.object_type="T" WHERE th.object_id=' . (int) $ticket_id);
    return (int) db_result($r);
}
// Crée un ticket « Priority » (comme le ferait le filtre : slaId imposé).
function new_ticket($tag, $sla_id) {
    $errors = array();
    $t = Ticket::create(array(
        'name' => "Client $tag", 'email' => strtolower($tag) . '@example.com',
        'subject' => "Test $tag", 'message' => "Bonjour $tag",
        'slaId' => $sla_id, 'ip' => '127.0.0.1', 'source' => 'API',
    ), $errors, 'API', false, false);
    if (!$t) { echo "create failed: " . print_r($errors, true) . "\n"; exit(2); }
    return $t;
}
// Vieillit un ticket de $heures heures (création et échéance SLA).
function age($ticket_id, $heures, $grace = 4) {
    db_query('UPDATE ' . TICKET_TABLE . ' SET created = NOW() - INTERVAL ' . (int) $heures . ' HOUR,'
        . ' est_duedate = IF(est_duedate IS NULL, NULL, NOW() - INTERVAL ' . (int) $heures . ' HOUR + INTERVAL ' . (int) $grace . ' HOUR)'
        . ' WHERE ticket_id=' . (int) $ticket_id);
}
// Réponse d'un agent. $to = valeur du choix « Répondre à » : 'user' (propriétaire),
// 'all' (propriétaire + collaborateurs actifs), 'collabs' (collaborateurs seuls,
// non proposé par l'interface) ou 'none' (« Ne pas envoyer d'e-mail »).
// L'envoi réel est désactivé ($alert = false) : la liste des destinataires est
// quand même calculée et enregistrée par postReply(), c'est ce que le plugin
// et le SQL lisent.
function agent_reply($ticket, $text = 'Réponse agent', $to = 'user') {
    global $thisstaff;
    $thisstaff = Staff::lookup(1);
    $errors = array();
    $r = $ticket->postReply(array('response' => $text, 'reply-to' => $to), $errors, false);
    if (!$r) { echo "reply failed: " . print_r($errors, true) . "\n"; exit(2); }
    return $r;
}
// Ligne brute d'une entrée du fil (flags, recipients) relue en base.
function entry_row($entry_id) {
    return db_fetch_array(db_query('SELECT id, type, staff_id, flags, poster, recipients, created FROM '
        . THREAD_ENTRY_TABLE . ' WHERE id=' . (int) $entry_id));
}
// Ajoute un collaborateur actif au ticket.
function add_collab($ticket, $email, $name = 'Collab') {
    $user = User::fromVars(array('name' => $name, 'email' => $email));
    $errors = array();
    $c = $ticket->addCollaborator($user, array('isactive' => 1), $errors, false);
    if (!$c) { echo "collab failed: " . print_r($errors, true) . "\n"; exit(2); }
    return $c;
}
// Ticket d'un client identifié par son e-mail (même e-mail = même utilisateur).
function client_ticket($email, $name, $sla_id, $subject) {
    $errors = array();
    $t = Ticket::create(array(
        'name' => $name, 'email' => $email,
        'subject' => $subject, 'message' => "Bonjour $name",
        'slaId' => $sla_id, 'ip' => '127.0.0.1', 'source' => 'API',
    ), $errors, 'API', false, false);
    if (!$t) { echo "create failed: " . print_r($errors, true) . "\n"; exit(2); }
    return $t;
}
// Fixe la date de création du ticket (et de son message initial).
function set_created($ticket_id, $datetime) {
    db_query('UPDATE ' . TICKET_TABLE . " SET created='$datetime' WHERE ticket_id=" . (int) $ticket_id);
    db_query('UPDATE ' . THREAD_ENTRY_TABLE . " SET created='$datetime' WHERE type='M' AND thread_id="
        . '(SELECT id FROM ' . THREAD_TABLE . " WHERE object_type='T' AND object_id=" . (int) $ticket_id . ')');
}
// Fixe la date d'une entrée du fil.
function set_entry_created($entry_id, $datetime) {
    db_query('UPDATE ' . THREAD_ENTRY_TABLE . " SET created='$datetime' WHERE id=" . (int) $entry_id);
}
function reload($id) {
    return Ticket::lookup($id);
}

// --- Niveau de contrat (phases 4) ---------------------------------------

define('CLIENT_PASSWD', 'Cl1ent!Pass#2026');

// Instance du plugin SLA (le plugin Stripe a aussi la sienne à partir de la phase 4a).
function sla_instance() {
    $p = Plugin::objects()->filter(array('install_path' => 'plugins/sla_first_response'))->one();
    return PluginInstance::objects()->filter(array('plugin_id' => $p->getId()))->one();
}

// Organisation (créée si absente).
function org($name) {
    require_once INCLUDE_DIR . 'class.organization.php';
    $org = Organization::fromVars(array('name' => $name));
    if (!$org) { echo "org failed: $name\n"; exit(2); }
    return $org;
}
// Client avec compte portail (mot de passe CLIENT_PASSWD) rangé dans $org (ou aucune).
function client_user($email, $name, $org = null) {
    $user = User::fromVars(array('name' => $name, 'email' => $email));
    if (!$user) { echo "user failed: $email\n"; exit(2); }
    $user->setOrganization($org);
    if (!$user->getAccount()) {
        $errors = array();
        $acct = UserAccount::register($user, array('passwd1' => CLIENT_PASSWD, 'passwd2' => CLIENT_PASSWD,
            'timezone' => 'UTC', 'backend' => 'client'), $errors);
        if (!$acct) { echo "account failed: " . json_encode($errors) . "\n"; exit(2); }
    }
    return $user;
}
function uid_by_email($email) {
    return (int) User::lookupByEmail($email)->getId();
}
// Nombre de requêtes envoyées par cette connexion (compteur MySQL/MariaDB
// « Questions », la lecture du compteur comptant elle-même pour une).
function nb_questions() {
    $r = db_fetch_row(db_query("SHOW SESSION STATUS LIKE 'Questions'"));
    return (int) $r[1];
}

// Client HTTP minimal vers le serveur `php -S` du conteneur (cookies, CSRF).
class PortalClient {
    var $base = 'http://127.0.0.1:8000/';
    var $cookies = array();
    var $status = 0;
    var $location = null;

    function request($path, $post = null) {
        $headers = '';
        if ($this->cookies) {
            $c = array();
            foreach ($this->cookies as $k => $v) $c[] = "$k=$v";
            $headers .= 'Cookie: ' . implode('; ', $c) . "\r\n";
        }
        $opts = array('method' => $post === null ? 'GET' : 'POST',
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 60);
        if ($post !== null) {
            $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $opts['content'] = http_build_query($post);
        }
        $opts['header'] = $headers;
        $body = file_get_contents($this->base . ltrim($path, '/'), false,
            stream_context_create(array('http' => $opts)));
        $this->status = 0; $this->location = null;
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $this->status = (int) $m[1];
            elseif (stripos($h, 'Set-Cookie:') === 0) {
                list($kv) = explode(';', trim(substr($h, 11)));
                list($k, $v) = array_pad(explode('=', $kv, 2), 2, '');
                $this->cookies[$k] = $v;
            }
            elseif (stripos($h, 'Location:') === 0) $this->location = trim(substr($h, 9));
        }
        return $body;
    }
    // GET en suivant les redirections (au plus 5).
    function get($path) {
        $body = $this->request($path);
        for ($i = 0; $i < 5 && $this->location; $i++) {
            $loc = preg_replace('#^https?://[^/]+/#', '', $this->location);
            $body = $this->request($loc);
        }
        return $body;
    }
    function post($path, $data, $formPath = null) {
        $page = $this->get($formPath ?: $path);
        if (preg_match('/name="__CSRFToken__" value="([^"]+)"/', $page, $m))
            $data['__CSRFToken__'] = $m[1];
        $body = $this->request($path, $data);
        if ($this->location) {
            $loc = preg_replace('#^https?://[^/]+/#', '', $this->location);
            $body = $this->get($loc);
        }
        return $body;
    }
}
// Valeur sondée par le gabarit de test (voir phase4c) : <!--CL:{json}-->
function probe($html, $tag = 'CL') {
    if (!preg_match('/<!--' . $tag . ':(.*?)-->/s', $html, $m)) return null;
    return $m[1] === 'none' ? 'none' : json_decode($m[1], true);
}
