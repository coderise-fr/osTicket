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
function agent_reply($ticket, $text = 'Réponse agent') {
    global $thisstaff;
    $thisstaff = Staff::lookup(1);
    $errors = array();
    $r = $ticket->postReply(array('response' => $text, 'reply-to' => 'none'), $errors, false);
    if (!$r) { echo "reply failed: " . print_r($errors, true) . "\n"; exit(2); }
    return $r;
}
function reload($id) {
    return Ticket::lookup($id);
}
