<?php
/*********************************************************************
    phase2b-plugin-actif.php

    Phase 2b, plugin actif (chargé par le bootstrap normal d'osTicket) :
    la première réponse d'un agent supprime l'échéance, les réponses
    automatiques, messages et notes ne la suppriment pas.
    Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

$watch = sla_id('Priority 4h');
$done = sla_id('Priority 4h - 1re reponse faite');
$mine = array_map(function ($p) { return get_class($p); }, PluginManager::allActive());
check('plugin chargé au bootstrap', in_array('SlaFirstResponsePlugin', $mine), json_encode($mine));

echo "== P1 : réponse agent dans les temps, puis cron à +5 h\n";
$t = new_ticket('P1', $watch); $id = $t->getId();
check('avant réponse : sla=watch, échéance posée', fresh($id)['sla_id'] == $watch && fresh($id)['est_duedate'] !== null);
agent_reply($t);
$f = fresh($id);
check('après réponse : sla=done, échéance null', $f['sla_id'] == $done && $f['est_duedate'] === null, json_encode($f));
check('isanswered = 1 conservé (onResponse)', $f['isanswered'] == 1);
age($id, 5);
Ticket::checkOverdue();
$f = fresh($id);
check('cron à +5 h : PAS en retard', $f['isoverdue'] == 0, json_encode($f));
check('aucun événement overdue', nb_overdue_events($id) == 0);
$ev = db_result(db_query('SELECT COUNT(*) FROM ' . THREAD_EVENT_TABLE . ' e JOIN ' . EVENT_TABLE . ' v ON v.id=e.event_id AND v.name="edited" JOIN ' . THREAD_TABLE . ' th ON th.id=e.thread_id WHERE th.object_id=' . $id . ' AND e.data LIKE \'%sla_id%\''));
check('événement « edited » sla_id journalisé dans l\'historique', $ev == 1, $ev);

echo "== P2 : modification / réouverture / transfert ensuite : l'échéance ne revient pas\n";
$t = reload($id);
$t->updateEstDueDate();
check('updateEstDueDate() => reste null', fresh($id)['est_duedate'] === null);
$thisstaff = Staff::lookup(1);
$closed = TicketStatus::objects()->filter(array('state' => 'closed'))->order_by('id')->first();
$errors = array();
$t = reload($id);
$t->setStatus($closed->getId(), '', $errors, true, true);
$t = reload($id);
$t->reopen();
$f = fresh($id);
check('fermeture puis réouverture => toujours pas d\'échéance, sla=done', $f['est_duedate'] === null && $f['sla_id'] == $done, json_encode($f));
age($id, 9);
Ticket::checkOverdue();
check('cron : toujours pas en retard', fresh($id)['isoverdue'] == 0);

echo "== P3 : pas de réponse, cron à +5 h => en retard (le contrôle reste actif), puis réponse tardive\n";
$t = new_ticket('P3', $watch); $id3 = $t->getId();
age($id3, 5);
Ticket::checkOverdue();
check('sans réponse : en retard + événement', fresh($id3)['isoverdue'] == 1 && nb_overdue_events($id3) == 1);
$t = reload($id3);
agent_reply($t);
$f = fresh($id3);
check('réponse tardive : sla=done, échéance null, flag « en retard » effacé', $f['sla_id'] == $done && $f['est_duedate'] === null && $f['isoverdue'] == 0, json_encode($f));
check('événement overdue historique conservé', nb_overdue_events($id3) == 1);

echo "== P4 : ce qui ne doit PAS arrêter le chronomètre\n";
$thisstaff = null;
$t = new_ticket('P4', $watch); $id4 = $t->getId();
$errors = array();
$t->postReply(array('poster' => 'SYSTEM (Canned Reply)', 'response' => 'Accusé automatique'), $errors, false, false);
check('réponse canned (staff_id=0, FLAG_SYSTEM) : sla inchangé', fresh($id4)['sla_id'] == $watch && fresh($id4)['est_duedate'] !== null);
$errors = array();
$t->postMessage(array('message' => 'Un message du client', 'userId' => $t->getUserId()), 'API', false);
check('message du client : sla inchangé', fresh($id4)['sla_id'] == $watch);
$thisstaff = Staff::lookup(1);
$errors = array();
$t = reload($id4);
$t->postNote(array('note' => 'note interne', 'title' => 'n'), $errors, $thisstaff, false);
check('note interne d\'un agent : sla inchangé', fresh($id4)['sla_id'] == $watch);
// Même cas, canned posté depuis le panneau agent ($thisstaff défini, auteur SYSTEM...)
$t = reload($id4);
$errors = array();
$t->postReply(array('poster' => __('SYSTEM (Canned Reply)'), 'response' => 'Canned créé depuis le panneau agent'), $errors, false, false);
check('canned depuis le panneau agent : sla inchangé (filtré par l\'auteur)', fresh($id4)['sla_id'] == $watch);
age($id4, 5);
Ticket::checkOverdue();
check('=> ce ticket sans vraie réponse est bien en retard', fresh($id4)['isoverdue'] == 1);
$t = reload($id4);
agent_reply($t);
check('puis vraie réponse agent : bascule', fresh($id4)['sla_id'] == $done);

echo "== P5 : autre plan de SLA ou autre ticket : non concernés\n";
$default = sla_id('Default SLA');
$t = new_ticket('P5', $default); $id5 = $t->getId();
agent_reply($t);
check('ticket au Default SLA : plan inchangé', fresh($id5)['sla_id'] == $default && fresh($id5)['est_duedate'] !== null, json_encode(fresh($id5)));

echo "== P6 : réponse avec envoi au client (reply-to=user) : flags REPLY_USER\n";
$t = new_ticket('P6', $watch); $id6 = $t->getId();
$thisstaff = Staff::lookup(1);
$errors = array();
$r = $t->postReply(array('response' => 'Réponse envoyée', 'reply-to' => 'user'), $errors, false);
$e = db_fetch_array(db_query('SELECT flags FROM ' . THREAD_ENTRY_TABLE . ' WHERE id=' . (int) $r->getId()));
check('flags & 0x200 (REPLY_USER) posé', ($e['flags'] & 0x200) != 0, $e['flags']);
check('bascule du plan', fresh($id6)['sla_id'] == $done);
$rec = json_decode($e2 = db_result(db_query('SELECT recipients FROM ' . THREAD_ENTRY_TABLE . ' WHERE id=' . (int) $r->getId())), true);
check('recipients JSON : le client est dans "to"', !empty($rec['to']) && empty($rec['cc']), (string) $e2);

echo "== P7 : réponse SANS e-mail au client (« Ne pas envoyer d'e-mail ») : n'arrête PAS le chronomètre\n";
$t = new_ticket('P7', $watch); $id7 = $t->getId();
$r1 = agent_reply($t, 'Réponse enregistrée sans e-mail', 'none');
$row = entry_row($r1->getId());
check('entrée R d\'un agent, sans destinataires ni indicateur REPLY', $row['type'] == 'R' && $row['staff_id'] > 0
    && $row['recipients'] === null && ($row['flags'] & 0x300) == 0, json_encode($row));
$f = fresh($id7);
check('plan inchangé, échéance conservée', $f['sla_id'] == $watch && $f['est_duedate'] !== null, json_encode($f));
check('(cœur) onResponse a quand même posé isanswered = 1', $f['isanswered'] == 1);
age($id7, 5);
Ticket::checkOverdue();
check('cron à +5 h : le ticket est EN RETARD (réponse sans e-mail non comptée)', fresh($id7)['isoverdue'] == 1 && nb_overdue_events($id7) == 1);
$t = reload($id7);
$r2 = agent_reply($t, 'Cette fois par e-mail', 'user');
$f = fresh($id7);
check('la réponse suivante AVEC e-mail arrête : sla=done, échéance null, retard effacé',
    $f['sla_id'] == $done && $f['est_duedate'] === null && $f['isoverdue'] == 0, json_encode($f));
check('l\'entrée porte le client dans "to"', SlaFirstResponsePlugin::isClientReply(ThreadEntry::lookup($r2->getId())));
check('l\'entrée sans e-mail n\'est pas une réponse client', !SlaFirstResponsePlugin::isClientReply(ThreadEntry::lookup($r1->getId())));

echo "== P8 : « Tous les destinataires actifs » avec un collaborateur : le client est dans \"to\"\n";
$t = new_ticket('P8', $watch); $id8 = $t->getId();
add_collab($t, 'collab-p8@example.com', 'Collab P8');
$t = reload($id8);
$r = agent_reply($t, 'Pour tous', 'all');
$row = entry_row($r->getId());
$rec = json_decode($row['recipients'], true);
check('to = client, cc = collaborateur, flag REPLY_ALL (0x100)', !empty($rec['to']) && !empty($rec['cc']) && ($row['flags'] & 0x100), json_encode($row));
check('bascule du plan', fresh($id8)['sla_id'] == $done);

echo "== P9 : collaborateurs SEULS (choix absent de l'interface) : le client n'est pas prévenu, n'arrête pas\n";
$t = new_ticket('P9', $watch); $id9 = $t->getId();
add_collab($t, 'collab-p9@example.com', 'Collab P9');
$t = reload($id9);
$r = agent_reply($t, 'Aux collaborateurs seuls', 'collabs');
$row = entry_row($r->getId());
$rec = json_decode($row['recipients'], true);
check('cc seul, pas de "to"', empty($rec['to']) && !empty($rec['cc']), json_encode($row));
check('(constat) l\'indicateur REPLY_USER est pourtant posé : il ne suffit pas comme critère', ($row['flags'] & 0x200) != 0, $row['flags']);
check('plan inchangé', fresh($id9)['sla_id'] == $watch && fresh($id9)['est_duedate'] !== null);
$t = reload($id9);
agent_reply($t, 'Au client', 'user');
check('puis réponse au client : bascule', fresh($id9)['sla_id'] == $done);

echo "== P9b : collaborateurs seuls alors qu'il n'y en a aucun : aucune liste, n'arrête pas\n";
$t = new_ticket('P9b', $watch); $id9b = $t->getId();
$r = agent_reply($t, 'Personne', 'collabs');
$row = entry_row($r->getId());
check('aucune liste de destinataires', $row['recipients'] === null && ($row['flags'] & 0x300) == 0, json_encode($row));
check('plan inchangé', fresh($id9b)['sla_id'] == $watch);

echo "== P10 : deux réponses sans e-mail de suite : toujours pas d'arrêt\n";
$t = new_ticket('P10', $watch); $id10 = $t->getId();
agent_reply($t, 'a', 'none');
$t = reload($id10);
agent_reply($t, 'b', 'none');
check('deux réponses sans e-mail : plan inchangé', fresh($id10)['sla_id'] == $watch && fresh($id10)['est_duedate'] !== null);

echo "\nPhase 2b : " . ($nfail ? $nfail . " échec(s)" : 'tout est conforme aux attentes') . "\n";
