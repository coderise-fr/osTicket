<?php
/*********************************************************************
    deploy/themes-staff/coderise-agent/tests/test-dashboard.php

    CLI test of the dashboard panels of the coderise-agent staff theme
    (overlay/include/staff/coderise/dashboard.inc.php). Needs no osTicket
    and no database: the database functions and the osTicket classes are
    stubs; the queries are recorded to check how many run and how they
    are scoped.

    Usage (from the repository root, in the demo image):
      php -d open_basedir= -d display_errors=1 -d error_reporting=-1 \
          deploy/themes-staff/coderise-agent/tests/test-dashboard.php

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
error_reporting(-1);
ini_set('display_errors', '1');
define('INCLUDE_DIR', realpath(__DIR__ . '/../overlay/include') . '/');
define('ROOT_PATH', '/');
foreach (array('TICKET_TABLE' => 'ost_ticket', 'TICKET_STATUS_TABLE' => 'ost_ticket_status',
        'THREAD_TABLE' => 'ost_thread', 'THREAD_ENTRY_TABLE' => 'ost_thread_entry',
        'USER_TABLE' => 'ost_user', 'SLA_TABLE' => 'ost_sla', 'STAFF_TABLE' => 'ost_staff',
        'DEPT_TABLE' => 'ost_department', 'TOPIC_TABLE' => 'ost_help_topic',
        'TICKET_CDATA_TABLE' => 'ost_ticket__cdata') as $k => $v)
    define($k, $v);

$fails = 0;
function check($name, $got, $want) {
    global $fails;
    if ($got === $want) {
        echo "ok    $name\n";
        return;
    }
    $fails++;
    echo "FAIL  $name\n      got:  " . var_export($got, true)
        . "\n      want: " . var_export($want, true) . "\n";
}
function check_has($name, $hay, $needle) {
    check($name, strpos((string) $hay, $needle) !== false, true);
}
function check_not($name, $hay, $needle) {
    check($name, strpos((string) $hay, $needle) === false, true);
}

function __($msgid) { return $msgid; }
class Internationalization {
    static function getCurrentLanguage() { return 'en_US'; }
}
class Misc {
    static function db2gmtime($d) { return $d ? strtotime($d . ' UTC') : 0; }
}
class ReportModel { const PERM_AGENTS = 'stats.agents'; }
class ThreadEntry {
    const FLAG_HIDDEN = 0x0004;
    const FLAG_SYSTEM = 0x0080;
}

// ---------- stubbed database: queries are recorded, rows come from $DB_ROWS by a SQL marker ----------
$DB_LOG = array();
$DB_ROWS = array();
function db_query($sql) {
    global $DB_LOG, $DB_ROWS;
    $DB_LOG[] = $sql;
    foreach ($DB_ROWS as $marker => $rows) {
        if ($rows === 'throw' && strpos($sql, $marker) !== false)
            throw new RuntimeException('db down');
        if (strpos($sql, $marker) !== false)
            return new ArrayIterator($rows);
    }
    return new ArrayIterator(array());
}
function db_fetch_array($res) {
    if (!$res->valid())
        return false;
    $r = $res->current();
    $res->next();
    return $r;
}

class StubStaff {
    public $limited = false, $depts = array(1, 2), $teams = array(), $admin = false, $perm = false, $managed = array();
    function isAccessLimited() { return $this->limited; }
    function getDepts() { return $this->depts; }
    function getTeams() { return $this->teams; }
    function getId() { return 7; }
    function isAdmin() { return $this->admin; }
    function hasPerm($p) { return $this->perm; }
    function getManagedDepartments() { return $this->managed; }
}
class StubReport {
    public $a, $b;
    function __construct($a, $b) { $this->a = $a; $this->b = $b; }
    function getDateRange() { return array($this->a, $this->b); }
}

$file = __DIR__ . '/../overlay/include/staff/coderise/dashboard.inc.php';
if (!is_file($file)) {
    echo "FAIL  dashboard.inc.php is missing: $file\n";
    exit(1);
}
require $file;

// ---------- pure helpers ----------
check('int: FROM_UNIXTIME', cdr_dash_int('FROM_UNIXTIME(1761000000)'), 1761000000);
check('int: anything else is null (no SQL injection path)', cdr_dash_int('FROM_UNIXTIME(1); DROP TABLE x'), null);
check('int: null', cdr_dash_int(null), null);
check('pct: 3 of 4', cdr_dash_pct(3, 4), 75);
check('pct: rounds', cdr_dash_pct(2, 3), 67);
check('pct: no denominator', cdr_dash_pct(0, 0), null);
check('delta: up', cdr_dash_delta(108, 100), array('kind' => 'up', 'pct' => 8));
check('delta: down', cdr_dash_delta(90, 100), array('kind' => 'down', 'pct' => -10));
check('delta: flat', cdr_dash_delta(100, 100), array('kind' => 'flat', 'pct' => 0));
check('delta: new', cdr_dash_delta(5, 0), array('kind' => 'new', 'pct' => 0));
check('delta: none', cdr_dash_delta(0, 0), array('kind' => 'none', 'pct' => 0));
check('span: minutes', cdr_dash_span(47 * 60), "47\xC2\xA0min");
check('span: hours', cdr_dash_span(3 * 3600 + 27 * 60), "3\xC2\xA0h\xC2\xA027");
check('span: days from 48 h', cdr_dash_span(98 * 3600), '4 d');
check('span: negative', cdr_dash_span(-5), "0\xC2\xA0min");
check('target: whole hours', cdr_dash_target(4), '4 h');
check('target: fraction', cdr_dash_target(1.5), "1\xC2\xA0h\xC2\xA030");
check('percent', cdr_dash_percent(94), '94%');
check('num', cdr_dash_num(1234), '1,234');

// ---------- window (the gauge rule, from plain data) ----------
$now = strtotime('2026-10-27 12:00:00 UTC');
$w = cdr_dash_window(array('created' => '2026-10-27 08:00:00', 'first' => null), 4);
check('window: created + 4 h', $w['due'] - $w['start'], 4 * 3600);
check('window: no first reply', $w['first'], null);
$w = cdr_dash_window(array('created' => '2026-10-27 08:00:00', 'first' => '2026-10-27 08:44:00'), 4);
check('window: first reply', $w['first'] - $w['start'], 44 * 60);
$w = cdr_dash_window(array('created' => '2026-10-27 08:00:00', 'est_duedate' => '2026-10-27 10:00:00',
    'sla_name' => 'Priority 4h', 'sla_flags' => 1, 'first' => null), 4);
check('window: Priority plan due date', $w['due'] - $w['start'], 2 * 3600);
$w = cdr_dash_window(array('created' => '2026-10-27 08:00:00', 'est_duedate' => '2026-10-27 10:00:00',
    'sla_name' => 'Priority 4h', 'sla_flags' => 1, 'reopened' => '2026-10-27 09:00:00', 'first' => null), 4);
check('window: reopened keeps created + 4 h', $w['due'] - $w['start'], 4 * 3600);
$w = cdr_dash_window(array('created' => '2026-10-27 08:00:00', 'est_duedate' => '2026-10-27 10:00:00',
    'sla_name' => 'Priority 4h', 'sla_flags' => 0, 'first' => null), 4);
check('window: inactive plan ignored', $w['due'] - $w['start'], 4 * 3600);
$w = cdr_dash_window(array('created' => '2026-10-27 08:00:00', 'est_duedate' => '2026-10-27 10:00:00',
    'sla_name' => 'Default SLA', 'sla_flags' => 1, 'first' => null), 4);
check('window: other plan ignored', $w['due'] - $w['start'], 4 * 3600);
check('window: no creation date', cdr_dash_window(array('created' => null), 4), null);

// ---------- Priority summary ----------
function prow($id, $created, $first, $open = true, $staff = 0) {
    $w = cdr_dash_window(array('created' => $created, 'first' => $first), 4);
    return array('id' => $id, 'number' => (string) (1000 + $id), 'open' => $open,
        'staff_id' => 0, 'first_staff' => $staff, 'window' => $w);
}
$from = $now - 14 * 86400;
$rows = array(
    prow(1, '2026-10-27 06:00:00', null),                       // 6 h old, no reply: over by 2 h
    prow(2, '2026-10-27 08:50:00', null),                       // 3 h 10 old: warn (ratio .79), due in 50 min
    prow(3, '2026-10-27 11:00:00', null),                       // 1 h old: ok
    prow(4, '2026-10-20 08:00:00', '2026-10-20 08:30:00', false, 5),  // answered in 30 min
    prow(5, '2026-10-21 08:00:00', '2026-10-21 14:00:00', false, 5),  // answered in 6 h: late
    prow(6, '2026-09-01 08:00:00', '2026-09-01 08:10:00', false, 6),  // before the period
);
$s = cdr_dash_priority_summary($rows, $from, $now, $now);
check('summary: over', $s['over'], 1);
check('summary: warn', $s['warn'], 1);
check('summary: risk count (ok ticket excluded)', count($s['risk']), 2);
check('summary: risk order = nearest deadline first', array($s['risk'][0]['id'], $s['risk'][1]['id']), array(1, 2));
check('summary: oldest over', $s['oldest_over'], 2 * 3600);
check('summary: next due', $s['next_due'], 50 * 60);
check('summary: answered in the period', $s['answered'], 2);
check('summary: average first reply', $s['avg_first'], (30 * 60 + 6 * 3600) / 2);
check('summary: decided (answered + over unanswered)', $s['decided'], 3);
check('summary: met', $s['met'], 1);
check('summary: by agent', $s['by_staff'], array(5 => array('decided' => 2, 'met' => 1)));
$s0 = cdr_dash_priority_summary(array(), $from, $now, $now);
check('summary: empty', array($s0['avg_first'], $s0['decided'], $s0['risk'], $s0['next_due']), array(null, 0, array(), null));
check('summary: closed ticket without a window is skipped', cdr_dash_priority_summary(array(array('id' => 9)), $from, $now, $now)['decided'], 0);

// ---------- alert wording ----------
check('alert: several, over', cdr_dash_alert_text(3, 2, 47 * 60, null, 4),
    array('3 Priority tickets are over or close to their 4 h first reply.', "The oldest is already over by 47\xC2\xA0min."));
check('alert: one, over', cdr_dash_alert_text(1, 1, 3600, null, 4)[1], "It is already over by 1\xC2\xA0h\xC2\xA000.");
check('alert: warn only', cdr_dash_alert_text(2, 0, 0, 1800, 4)[1], "The nearest deadline is in 30\xC2\xA0min.");
check('alert: one, warn', cdr_dash_alert_text(1, 0, 0, 600, 4),
    array('1 Priority ticket is over or close to its 4 h first reply.', "Its deadline is in 10\xC2\xA0min."));

// ---------- scope ----------
$st = new StubStaff();
check('scope: departments', cdr_dash_scope($st), 't.dept_id IN (1,2)');
$st->depts = array();
check('scope: no department = nothing', cdr_dash_scope($st), '0');
$st->limited = true;
$st->teams = array(3, 0);
check('scope: limited access = own and team tickets', cdr_dash_scope($st), '(t.staff_id=7 OR t.team_id IN (3))');
check('scope: no staff', cdr_dash_scope(null), '0');

// ---------- queries ----------
$st = new StubStaff();
$DB_LOG = array();
$DB_ROWS = array('COUNT(CASE' => array(array('received' => '21', 'previous' => '10', 'open_n' => '41',
    'unassigned' => '15', 'overdue' => '3', 'closed_n' => '4')));
$c = cdr_dash_counts(1000000, 2000000, cdr_dash_scope($st));
check('counts: values', $c, array('received' => 21, 'previous' => 10, 'open_n' => 41, 'unassigned' => 15, 'overdue' => 3, 'closed_n' => 4));
check('counts: one query', count($DB_LOG), 1);
check_has('counts: scoped to the departments', $DB_LOG[0], 't.dept_id IN (1,2)');
check_has('counts: previous period starts one length before', $DB_LOG[0], 'FROM_UNIXTIME(0)');
$DB_ROWS = array('COUNT(CASE' => 'throw');
check('counts: failed query = zeros', cdr_dash_counts(1, 2, '1'), array('received' => 0, 'previous' => 0, 'open_n' => 0, 'unassigned' => 0, 'overdue' => 0, 'closed_n' => 0));

$DB_LOG = array();
$DB_ROWS = array('MIN(te.created)' => array(
    array('ticket_id' => '1', 'number' => '111', 'created' => '2026-10-27 06:00:00', 'est_duedate' => null, 'reopened' => null,
        'staff_id' => '0', 'state' => 'open', 'sla_name' => 'Priority 4h', 'sla_flags' => '1', 'first_reply' => null, 'first_staff' => null),
    array('ticket_id' => '2', 'number' => '222', 'created' => '2026-10-20 06:00:00', 'est_duedate' => null, 'reopened' => null,
        'staff_id' => '2', 'state' => 'closed', 'sla_name' => null, 'sla_flags' => null, 'first_reply' => '2026-10-20 06:20:00', 'first_staff' => '2'),
    array('ticket_id' => '3', 'number' => '333', 'created' => '2026-10-20 06:00:00', 'est_duedate' => null, 'reopened' => null,
        'staff_id' => '2', 'state' => 'closed', 'sla_name' => null, 'sla_flags' => null, 'first_reply' => null, 'first_staff' => null),
));
$pr = cdr_dash_priority_rows(2, $from, 't.dept_id IN (1,2)', 4);
check('priority rows: one query for all tickets', count($DB_LOG), 1);
check('priority rows: closed ticket without reply dropped', array_map(function ($r) { return $r['id']; }, $pr), array(1, 2));
check('priority rows: organisation', strpos($DB_LOG[0], 'u.org_id=2') !== false, true);
check_has('priority rows: client reply = "to" recipient', $DB_LOG[0], 'te.recipients LIKE \'%"to":{"%\'');
check_has('priority rows: neither hidden nor system', $DB_LOG[0], '(te.flags & 132)=0');
check_has('priority rows: scoped', $DB_LOG[0], 't.dept_id IN (1,2)');
check('priority rows: answered ticket keeps its first reply and author', array($pr[1]['window']['first'] - $pr[1]['window']['start'], $pr[1]['first_staff']), array(1200, 2));

$DB_LOG = array();
$DB_ROWS = array('FROM ost_staff' => array(array('staff_id' => '5', 'firstname' => 'Théo', 'lastname' => 'Martin', 'dept' => 'Support', 'open_n' => '20')));
$ag = cdr_dash_agents($st, 't.dept_id IN (1,2)');
check('agents: row', $ag, array(array('id' => 5, 'name' => 'Théo Martin', 'dept' => 'Support', 'open' => 20)));
check_has('agents: the viewer only without permission', $DB_LOG[0], 'WHERE st.isactive=1 AND (st.staff_id=7)');
$st->perm = true;
$DB_LOG = array();
cdr_dash_agents($st, 't.dept_id IN (1,2)');
check_has('agents: all departments with the permission', $DB_LOG[0], '(st.staff_id=7 OR st.dept_id IN (1,2))');
$st->perm = false;
$st->managed = array(2);
$DB_LOG = array();
cdr_dash_agents($st, 't.dept_id IN (1,2)');
check_has('agents: managed departments', $DB_LOG[0], '(st.staff_id=7 OR st.dept_id IN (2))');

$DB_ROWS = array('ost_help_topic h' => array(array('topic_id' => '12', 'topic' => 'Incident', 'n' => '8'), array('topic_id' => '13', 'topic' => '', 'n' => '1')));
check('topics: unnamed topic skipped', cdr_dash_topics(1, 2, '1'), array(array('name' => 'Incident', 'n' => 8)));
$DB_ROWS = array('FROM ost_ticket__cdata' => array(array('ticket_id' => '1', 'subject' => 'Paiement')));
check('subjects', cdr_dash_subjects(array(1, 'x', 0)), array(1 => 'Paiement'));
$DB_LOG = array();
check('subjects: no id, no query', array(cdr_dash_subjects(array()), count($DB_LOG)), array(array(), 0));

// ---------- model: no Priority plugin, no report ----------
check('model: without a report nothing is read', cdr_dash_model($st, null)['counts'], null);

// ---------- rendering ----------
$m = array('from' => $now - 30 * 86400, 'to' => $now, 'hours' => 4, 'queue' => '/scp/tickets.php?queue=1',
    'counts' => array('received' => 21, 'previous' => 10, 'open_n' => 41, 'unassigned' => 15, 'overdue' => 3, 'closed_n' => 4),
    'prio' => null, 'agents' => null, 'topics' => array(array('name' => 'Incident <b>', 'n' => 1234)), 'subjects' => array());
$head = cdr_dash_head($m);
check_has('head: title', $head, '<h1>Dashboard</h1>');
check_not('head: no alert without Priority data', $head, 'alert-strip');
check_has('head: delta', $head, '+110% vs the previous 30 days');
check_has('head: fallback card = closed', $head, 'Tickets closed');
check_has('head: fallback card = overdue', $head, 'Overdue tickets');
check_has('head: unassigned', $head, '15 unassigned');
check_not('head: no Priority card without data', $head, 'Priority');
$m['prio'] = $s;
$m['subjects'] = array(1 => 'Paiement <script>');
$head = cdr_dash_head($m);
check_has('head: alert strip', $head, 'class="cdr-dash alert-strip" role="status"');
check_has('head: alert link = the queue for several tickets', $head, 'href="/scp/tickets.php?queue=1"');
check_has('head: alert label', $head, 'View the 2 tickets');
check_has('head: average first reply', $head, "3\xC2\xA0h\xC2\xA015");
check_has('head: target met', $head, 'Target of 4 h met');
check_has('head: rate', $head, '33%');
check_has('head: rate detail', $head, '1 of 3 Priority tickets');
$risk = cdr_dash_risk_panel($m);
check_has('risk: ticket link', $risk, 'href="/scp/tickets.php?id=1">#1001</a>');
check_has('risk: subject escaped', $risk, 'Paiement &lt;script&gt;');
check_has('risk: gauge', $risk, 'class="gauge gauge-over"');
$m2 = $m;
$m2['prio'] = $s0;
check_has('risk: empty state', cdr_dash_risk_panel($m2), 'cdr-dash-empty');
check_not('head: no alert when nothing at risk', cdr_dash_head($m2), 'alert-strip');
check_has('head: no measurable Priority ticket', cdr_dash_head($m2), 'No Priority ticket to measure');
$m['prio'] = cdr_dash_priority_summary(array(prow(1, '2026-10-27 06:00:00', null)), $from, $now, $now);
check_has('head: single ticket links to it', cdr_dash_head($m), 'href="/scp/tickets.php?id=1">View the ticket');
check('risk: not drawn without Priority data', cdr_dash_risk_panel(array('prio' => null)), '');
check_has('topics: escaped, grouped number', cdr_dash_topics_panel($m), '<dt>Incident &lt;b&gt;</dt><dd>1,234</dd>');
check_has('topics: empty', cdr_dash_topics_panel(array('topics' => array())), 'No ticket with a help topic');
check('agents: no panel without data', cdr_dash_agents_panel(array('agents' => null, 'prio' => null)), '');
$m['agents'] = array(array('id' => 5, 'name' => 'Théo Martin', 'dept' => 'Support', 'open' => 20), array('id' => 6, 'name' => 'Léa', 'dept' => '', 'open' => 5));
$ap = cdr_dash_agents_panel($m);
check_has('agents: initials', $ap, '>TM</span>');
check_has('agents: load relative to the busiest', $ap, '<i style="width:25%"></i>');
check_has('agents: first-reply column with Priority data', $ap, 'First reply met');
check_has('agents: no data = dash', $ap, "\xE2\x80\x94");
$m['prio'] = null;
check_not('agents: no first-reply column without Priority data', cdr_dash_agents_panel($m), 'First reply met');

// ---------- injection into core's page ----------
$core = "<script src=\"x\"></script>\n<form method=\"post\" action=\"dashboard.php\">\n<div id=\"basic_search\">bar</div>\n"
    . "<div style=\"margin-bottom:20px; padding-top:5px;\"><h2>Ticket Activity</h2></div>\n<div id=\"line-chart-here\"></div>\n<hr/>\n<h2>Statistics</h2>\n<table></table>\n</form>\n<script>draw();</script>";
$parts = array('head' => '<HEAD/>', 'main' => '<MAIN/>', 'side' => '<SIDE/>');
$out = cdr_dash_inject($core, $parts);
check('inject: head before the form', strpos($out, '<HEAD/>') < strpos($out, '<form'), true);
check('inject: columns open inside the form', strpos($out, '<form') < strpos($out, 'class="cols cdr-dash-cols"'), true);
check('inject: period bar stays above the chart panel', strpos($out, 'id="basic_search"') < strpos($out, 'cdr-dash-chart'), true);
check('inject: chart panel wraps the title and the chart', (bool) preg_match('~<section class="panel cdr-dash-chart"><div style="margin-bottom:20px; padding-top:5px;">.*line-chart-here.*</section><MAIN/></div><aside~s', $out), true);
check('inject: side column', (bool) preg_match('~<aside class="col-side" aria-label="To handle"><SIDE/></aside></div>~', $out), true);
check('inject: statistics panel to the end of the form', (bool) preg_match('~<section class="panel cdr-dash-stats">\s*<h2>Statistics</h2>.*</table>\s*</section></form>~s', $out), true);
check('inject: the rule is replaced', strpos($out, '<hr/>'), false);
check('inject: core text kept', substr_count($out, 'draw();') + substr_count($out, 'Ticket Activity'), 2);
$out2 = cdr_dash_inject(str_replace('padding-top:5px;', 'padding-top:5px', $core), $parts);
check('inject: marker without semicolon (other core version)', strpos($out2, 'cdr-dash-chart') !== false, true);
check('inject: no form = page untouched', cdr_dash_inject('<html>nothing</html>', $parts), '<html>nothing</html>');
$nohr = cdr_dash_inject(str_replace("<hr/>\n", '', $core), $parts);
check('inject: no rule = stacked before the form', array(strpos($nohr, '<SIDE/>') < strpos($nohr, '<form'), strpos($nohr, 'cols cdr-dash-cols')), array(true, false));
check('inject: empty parts still valid', strpos(cdr_dash_inject($core, array()), '<aside') === false, true);

// ---------- French catalogue: every msgid the panels use ----------
$GLOBALS['CDR_TEST_LANG'] = 'fr';
$fr = cdr_catalog();
$src = file_get_contents($file);
preg_match_all("/cdr_t\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $mm);
$missing = array();
foreach (array_unique($mm[1]) as $id) {
    $id = str_replace("\\'", "'", $id);
    if (!isset($fr[$id]))
        $missing[] = $id;
}
check('fr: every msgid of the panels is translated', $missing, array());
check('fr: percent keeps a non-breaking space', cdr_dash_percent(94), "94\xC2\xA0%");
check('fr: target', cdr_dash_target(4), "4\xC2\xA0h");
check('fr: alert', cdr_dash_alert_text(3, 2, 47 * 60, null, 4)[0], "3 tickets Priority ont dépassé ou risquent de dépasser leur premier retour de 4\xC2\xA0h.");
check('fr: days', cdr_dash_span(98 * 3600), "4\xC2\xA0j");
check('fr: head title', strpos(cdr_dash_head($m), '<h1>Tableau de bord</h1>') !== false, true);
// sprintf of every translated format: same placeholders as the msgid (a wrong one is a runtime error)
$bad = array();
foreach ($fr as $id => $tr) {
    if (strpos($id, '%') === false)
        continue;
    preg_match_all('/%(?:\d+\$)?[+]?[ds]/', $id, $a);
    preg_match_all('/%(?:\d+\$)?[+]?[ds]/', $tr, $b);
    if (count($a[0]) !== count($b[0]))
        $bad[] = $id;
}
check('fr: same number of placeholders as the msgid', $bad, array());

echo $fails ? "\n$fails FAILED\n" : "\nall ok\n";
exit($fails ? 1 : 0);
