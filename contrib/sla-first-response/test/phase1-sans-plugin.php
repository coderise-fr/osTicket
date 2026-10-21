<?php
/*********************************************************************
    phase1-sans-plugin.php

    Phase 1, plugin NON installé : vérifie les affirmations de l'issue
    (le cœur marque « en retard » un ticket pourtant répondu) et que les
    contournements « updateEstDueDate » et « changement de statut »
    n'y changent rien. Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

echo "== Préparation : plans de SLA\n";
$errors = array();
foreach (array(
    array('name' => 'Priority 4h', 'isactive' => 1, 'grace_period' => 4, 'schedule_id' => 2),
    array('name' => 'Priority 4h - 1re reponse faite', 'isactive' => 0, 'grace_period' => 4, 'schedule_id' => 2),
) as $vars) {
    if (sla_id($vars["name"])) continue;
    $sla = SLA::create();
    $ok = $sla->update($vars, $errors);
    check("SLA '{$vars['name']}' créé", $ok && sla_id($vars['name']) > 0, json_encode($errors));
    $flags = db_result(db_query('SELECT flags FROM ' . SLA_TABLE . ' WHERE name="' . $vars['name'] . '"'));
    if (!$vars['isactive']) {
        // Constat : un plan créé directement « désactivé » est enregistré actif (flags=3, défaut SQL)
        // car l'ORM ne considère pas 0 comme une modification de NULL. Il faut le rééditer.
        check('(constat cœur) plan créé « désactivé » enregistré avec flags=' . $flags . ' (actif)', $flags == 3);
        $sla = SLA::lookup(sla_id($vars['name']));
        $errors = array(); $vars["id"] = $sla->getId(); $ok = $sla->update($vars, $errors);
        $flags = db_result(db_query('SELECT flags FROM ' . SLA_TABLE . ' WHERE name="' . $vars['name'] . '"'));
        check('après une 2e édition : flags=0 (désactivé)', $flags == 0, $flags);
    }
}
$watch = sla_id('Priority 4h');
$done = sla_id('Priority 4h - 1re reponse faite');
echo "  watch=$watch done=$done\n";

echo "== S0 : l'échéance est bien création + 4 h exactes (calendrier 24/7)\n";
$t0 = new_ticket('S0', $watch);
$id0 = $t0->getId();
$r = db_fetch_array(db_query("SELECT created, est_duedate, TIMESTAMPDIFF(MINUTE, created, est_duedate) AS m FROM " . TICKET_TABLE . " WHERE ticket_id=$id0"));
check('est_duedate = created + 240 min', (int) $r['m'] == 240, json_encode($r));
check('sla_id = Priority 4h', fresh($id0)['sla_id'] == $watch);

echo "== S1 : réponse agent à +1 h, puis cron à +5 h => « en retard » quand même (cœur seul)\n";
$t1 = new_ticket('S1', $watch);
$id1 = $t1->getId();
agent_reply($t1);
check('isanswered = 1 après réponse', fresh($id1)['isanswered'] == 1);
check('isoverdue = 0 juste après la réponse', fresh($id1)['isoverdue'] == 0);
age($id1, 5);
Ticket::checkOverdue();
$f = fresh($id1);
check('CONSTAT ISSUE : ticket répondu mais marqué isoverdue = 1', $f['isoverdue'] == 1, json_encode($f));
check('événement overdue journalisé (1)', nb_overdue_events($id1) == 1, nb_overdue_events($id1));

echo "== S2 : contournement updateEstDueDate() après la réponse\n";
$t2 = new_ticket('S2', $watch);
$id2 = $t2->getId();
agent_reply($t2);
age($id2, 5);
$t2 = reload($id2);
$t2->updateEstDueDate();           // valeur par défaut : efface le retard et recalcule
$f = fresh($id2);
check('est_duedate recalculée = encore dans le passé (création + 4 h)', strtotime($f['est_duedate']) < time(), json_encode($f));
Ticket::checkOverdue();
$f = fresh($id2);
check('=> re-marqué en retard au cron suivant (contournement inefficace)', $f['isoverdue'] == 1, json_encode($f));

echo "== S2b : updateEstDueDate() sur un ticket déjà en retard : efface puis re-marque (doublon d'événement)\n";
$t1 = reload($id1);
$t1->updateEstDueDate();
check('flag effacé par updateEstDueDate', fresh($id1)['isoverdue'] == 0);
Ticket::checkOverdue();
check('flag remis par checkOverdue', fresh($id1)['isoverdue'] == 1);
check('2 événements overdue pour le même ticket', nb_overdue_events($id1) == 2, nb_overdue_events($id1));

echo "== S3 : contournement par changement de statut\n";
$st = TicketStatus::__create(array('name' => 'En attente client', 'state' => 'open', 'mode' => 1, 'flags' => 0, 'sort' => 9,
    'properties' => array('description' => 'test')));
check('statut personnalisé state=open créé', $st && $st->getId() > 0);
$t3 = new_ticket('S3', $watch);
$id3 = $t3->getId();
$thisstaff = Staff::lookup(1);
$t3->setStatus($st->getId());
agent_reply($t3);
age($id3, 5);
Ticket::checkOverdue();
check('statut open personnalisé : toujours marqué en retard', fresh($id3)['isoverdue'] == 1, json_encode(fresh($id3)));

$t3b = new_ticket('S3b', $watch);
$id3b = $t3b->getId();
agent_reply($t3b);
age($id3b, 5);
$t3b = reload($id3b);
$thisstaff = Staff::lookup(1);
$closed = TicketStatus::objects()->filter(array('state' => 'closed'))->order_by('id')->first();
$errors = array();
$ok = $t3b->setStatus($closed->getId(), '', $errors, true, true);
$f = fresh($id3b);
check('statut closed : flag effacé (clearOverdue à la fermeture)', $ok && $f['isoverdue'] == 0 && $f['est_duedate'] === null, json_encode($f));
$t3b = reload($id3b);
$t3b->reopen();
$f = fresh($id3b);
check('réouverture : nouvelle échéance reouverture + 4 h (nouvelle fenêtre)', $f['est_duedate'] !== null && strtotime($f['est_duedate']) > time(), json_encode($f));

echo "== S4 : réponse canned de filtre (poster SYSTEM, sans agent)\n";
$thisstaff = null;
$t4 = new_ticket('S4', $watch);
$id4 = $t4->getId();
$errors = array();
$r = $t4->postReply(array('poster' => 'SYSTEM (Canned Reply)', 'response' => 'Accusé automatique'), $errors, false, false);
$e = db_fetch_array(db_query('SELECT staff_id, flags, poster, type FROM ' . THREAD_ENTRY_TABLE . ' WHERE id=' . (int) $r->getId()));
check('entrée R, staff_id=0, FLAG_SYSTEM (0x80) posé', $e['type'] == 'R' && $e['staff_id'] == 0 && ($e['flags'] & 0x80), json_encode($e));
check('onResponse a quand même mis isanswered=1 (le filtre le remet à 0 via markUnAnswered)', fresh($id4)['isanswered'] == 1);

echo "\nPhase 1 : " . ($nfail ? $nfail . " échec(s)" : 'tout est conforme aux attentes') . "\n";
