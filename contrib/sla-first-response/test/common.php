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
