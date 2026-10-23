<?php
/*********************************************************************
    deploy/themes-staff/coderise-agent/tests/test-helpers.php

    CLI test of the pure helpers of the coderise-agent staff theme
    (overlay/include/staff/coderise/coderise.inc.php). Needs no osTicket
    and no database: osTicket classes are replaced by small stubs.

    Usage (from the repository root, in the demo image):
      php -d open_basedir= -d display_errors=1 -d error_reporting=-1 \
          deploy/themes-staff/coderise-agent/tests/test-helpers.php

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
error_reporting(-1);
ini_set('display_errors', '1');
define('INCLUDE_DIR', __DIR__ . '/');
define('ROOT_PATH', '/');

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
function check_near($name, $got, $want) {
    check($name, is_float($got) && abs($got - $want) < 1e-9, true);
}

// osTicket's translator: the stub returns its argument (no language pack).
function __($msgid) { return $msgid; }

// ---------- stubs of the osTicket classes the gauge reads ----------
class Misc {
    static function db2gmtime($d) { return $d ? strtotime($d . ' UTC') : 0; }
}
class StubRows implements IteratorAggregate {
    public $rows;
    function __construct($rows) { $this->rows = $rows; }
    function __call($m, $a) { return $this; }
    function getIterator(): Iterator { return new ArrayIterator($this->rows); }
}
class ThreadEntry {
    const FLAG_HIDDEN = 0x0100;
    const FLAG_SYSTEM = 0x0200;
    static $rows = array();
    static function objects() { return new StubRows(self::$rows); }
}
class StubSla {
    function isActive() { return true; }
    function getName() { return 'Priority 4h'; }
}
class Ticket {
    public $id, $open = true, $created, $due = null, $sla = null, $level = 'priority';
    function __construct($id, $created) { $this->id = $id; $this->created = $created; }
    function getId() { return $this->id; }
    function getThreadId() { return 100 + $this->id; }
    function isOpen() { return $this->open; }
    function getCreateDate() { return $this->created; }
    function getReopenDate() { return null; }
    function getSLA() { return $this->sla; }
    function getSLADueDate() { return $this->due; }
}
function db_dt($ts) { return gmdate('Y-m-d H:i:s', $ts); }

// ---------- load ----------
$file = __DIR__ . '/../overlay/include/staff/coderise/coderise.inc.php';
if (!is_file($file)) {
    echo "FAIL  coderise.inc.php is missing: $file\n";
    exit(1);
}
require $file;

// ---------- cdr_initials ----------
check('initials: two words', cdr_initials('Nora Benali'), 'NB');
check('initials: one word', cdr_initials('nora'), 'N');
check('initials: empty', cdr_initials(''), '?');
check('initials: multibyte', cdr_initials("\xC3\x89lo\xC3\xAFse d'Arc"), "\xC3\x89D");
check('initials: three words = first and last', cdr_initials('Jean Pierre Martin'), 'JM');

// ---------- cdr_duration ----------
check('duration: 44 min', cdr_duration(44 * 60), '44 min');
check('duration: 3 h 27', cdr_duration(3 * 3600 + 27 * 60), '3 h 27');
check('duration: 1 h 05', cdr_duration(3600 + 5 * 60), '1 h 05');
check('duration: negative', cdr_duration(-5), '0 min');
check('duration: zero', cdr_duration(0), '0 min');
check('duration: seconds floor to 0 min', cdr_duration(59), '0 min');
check('duration: 2 h 00', cdr_duration(7200), '2 h 00');

// ---------- cdr_asset / cdr_icon ----------
check('asset: url and version', cdr_asset('css/x.css'), '/scp/coderise/css/x.css?v=' . CDR_VERSION);
check('constant: version', CDR_VERSION, '1.0.0');
check('constant: first response hours', CDR_FIRST_RESPONSE_HOURS, 4);
$svg = cdr_icon('inbox', 20);
check('icon: svg markup', strpos($svg, '<svg ') === 0 && strpos($svg, 'stroke-width="1.75"') !== false
    && strpos($svg, 'viewBox="0 0 24 24"') !== false && strpos($svg, 'aria-hidden="true"') !== false
    && strpos($svg, 'width="20"') !== false, true);
check('icon: unknown name', cdr_icon('nope'), '');
check('icon: numbers normalised', strpos(cdr_icon('building'), '.500') === false, true);
$all = explode(' ', 'inbox ticket users user book tasks chart sliders mail tag clock filter layers plug key building gauge alert check checkc lock bolt search plus refresh star upload edit message clip swap merge ban chevr chevd menu logout flag');
$missing = array();
foreach ($all as $n)
    if (cdr_icon($n) === '')
        $missing[] = $n;
check('icon: every required name exists', $missing, array());
check('h: escapes', cdr_h('<b>"x" & \''), '&lt;b&gt;&quot;x&quot; &amp; &#039;');

// ---------- cdr__ and the French catalogue ----------
check('cdr__: no language = msgid', cdr__('Log Out'), 'Log Out');
$GLOBALS['CDR_TEST_LANG'] = 'fr';
check('cdr__: fr known', cdr__('Log Out'), "D\xC3\xA9connexion");
check('cdr__: fr unknown msgid', cdr__('No such msgid zz'), 'No such msgid zz');
check('cdr__: fr_FR falls back to fr', (function () {
    $GLOBALS['CDR_TEST_LANG'] = 'fr_FR';
    return cdr__('Log Out');
})(), "D\xC3\xA9connexion");
$GLOBALS['CDR_TEST_LANG'] = 'fr';

// Inventory of I-10: every rail label has a French string.
$labels = array(
    // agent tabs and sub-menus (include/class.nav.php)
    'Dashboard', 'Users', 'Tasks', 'Tickets', 'Knowledgebase', 'Applications',
    'Agent Dashboard', 'User Directory', 'Task Queue', 'Ticket Queue',
    'Agent Directory', 'My Profile', 'Organizations', 'FAQs', 'Categories', 'Canned Responses',
    // admin tabs and sub-menus
    'Settings', 'Manage', 'Emails', 'Agents',
    'Admin Dashboard', 'System Settings', 'Manage Options', 'Email Settings', 'Manage Agents',
    'System Logs', 'Audit Logs', 'Information',
    'Company', 'System', 'Help Topics', 'Filters', 'Ticket Filters', 'SLA', 'Schedules', 'API',
    'Pages', 'Forms', 'Lists', 'Plugins',
    'Email Addresses', 'Banlist', 'Banned Emails', 'Templates', 'Email Templates', 'Diagnostic',
    'Email Diagnostic', 'Teams', 'Roles', 'Departments',
    // chrome
    'Profile', 'Log Out', 'New Ticket', 'New ticket', 'Not assigned', 'Internal note', 'Menu',
    'Administration', 'Agent', 'Panel', 'Search tickets', 'Skip to content', 'Main menu',
    'First reply', 'At risk', 'Not applicable',
    // gauge
    '%s left', 'Over by %s', 'Met in %s', 'Returned %s late',
    // ticket view
    'Priority contract: first reply to the client within %s.', 'Ticket properties',
);
$untranslated = array();
foreach ($labels as $l)
    if (cdr__($l) === $l)
        $untranslated[] = $l;
// A few labels are written the same in French: they are allowed to equal their msgid.
$same = array('Agent', 'Agents', 'Tickets', 'Applications', 'SLA', 'API', 'Administration', 'Diagnostic', 'Menu', 'Pages', 'Plugins');
check('fr.php: inventory complete', array_values(array_diff($untranslated, $same)), array());

// ---------- cdr_gauge_state (French rendering) ----------
$g = cdr_gauge_state(0, 14400, null, 1980);
check('gauge fr: ok kind', $g['kind'], 'ok');
check('gauge fr: ok label', $g['label'], 'Reste 3 h 27');
check_near('gauge fr: ok ratio', $g['ratio'], 0.1375);
$g = cdr_gauge_state(0, 14400, null, 10800);
check('gauge: 0.75 exactly is warn', $g['kind'], 'warn');
check_near('gauge: warn ratio', $g['ratio'], 0.75);
$g = cdr_gauge_state(0, 14400, null, 10799);
check('gauge: just under 0.75 is ok', $g['kind'], 'ok');
$g = cdr_gauge_state(0, 14400, null, 14400);
check('gauge: now == due is warn', $g['kind'], 'warn');
check_near('gauge: now == due ratio', $g['ratio'], 1.0);
check('gauge fr: now == due label', $g['label'], 'Reste 0 min');
$g = cdr_gauge_state(0, 14400, null, 17220);
check('gauge fr: over kind', $g['kind'], 'over');
check('gauge fr: over label', $g['label'], "D\xC3\xA9pass\xC3\xA9 de 47 min");
check_near('gauge: over ratio', $g['ratio'], 1.0);
$g = cdr_gauge_state(0, 14400, 2640, 20000);
check('gauge fr: done kind', $g['kind'], 'done');
check('gauge fr: done label', $g['label'], 'Tenu en 44 min');
check_near('gauge: done ratio', $g['ratio'], 2640 / 14400);
$g = cdr_gauge_state(0, 14400, 15000, 20000);
check('gauge fr: late reply kind', $g['kind'], 'over');
check('gauge fr: late reply label', $g['label'], 'Rendu avec 10 min de retard');
check_near('gauge: late reply ratio capped', $g['ratio'], 1.0);
$g = cdr_gauge_state(0, 14400, 14400, 20000);
check('gauge: reply exactly at due is done', $g['kind'], 'done');
check('gauge: due == start is null', cdr_gauge_state(100, 100, null, 200), null);
check('gauge: due < start is null', cdr_gauge_state(100, 50, null, 200), null);

// English rendering
unset($GLOBALS['CDR_TEST_LANG']);
check('gauge en: ok', cdr_gauge_state(0, 14400, null, 1980)['label'], '3 h 27 left');
check('gauge en: over', cdr_gauge_state(0, 14400, null, 17220)['label'], 'Over by 47 min');
check('gauge en: done', cdr_gauge_state(0, 14400, 2640, 20000)['label'], 'Met in 44 min');
check('gauge en: late', cdr_gauge_state(0, 14400, 15000, 20000)['label'], 'Returned 10 min late');

// ---------- cdr_gauge_html ----------
check('gauge html: null', cdr_gauge_html(null), '');
$h = cdr_gauge_html(array('kind' => 'ok', 'ratio' => 0.1375, 'label' => 'x'));
check('gauge html: ok has no state class', strpos($h, 'class="gauge"') !== false, true);
check('gauge html: role and label', strpos($h, 'role="img"') !== false && strpos($h, 'aria-label="x"') !== false, true);
check('gauge html: width', strpos($h, 'width:14%') !== false, true);
$h = cdr_gauge_html(array('kind' => 'warn', 'ratio' => 0.8, 'label' => 'x'), true);
check('gauge html: warn large', strpos($h, 'gauge gauge-warn gauge-lg') !== false, true);
$h = cdr_gauge_html(array('kind' => 'over', 'ratio' => 1.0, 'label' => 'x'));
check('gauge html: over class', strpos($h, 'gauge-over') !== false, true);
$h = cdr_gauge_html(array('kind' => 'done', 'ratio' => 0.2, 'label' => 'x'));
check('gauge html: done class', strpos($h, 'gauge-done') !== false, true);
$h = cdr_gauge_html(array('kind' => 'over', 'ratio' => 1.0, 'label' => '<b>"x"'));
check('gauge html: label escaped', strpos($h, '<b>') === false && strpos($h, '&lt;b&gt;&quot;x&quot;') !== false, true);

// ---------- cdr_ticket_gauge (stubs; the real queries are not exercised) ----------
$now = time();
check('ticket gauge: no plugin function', cdr_ticket_gauge(new Ticket(1, db_dt($now - 600))), null);
if (!function_exists('coderise_contract_level')) {
    function coderise_contract_level($t) {
        return array('level' => $t->level, 'source' => 'org', 'label' => '');
    }
}
check('ticket gauge: null input', cdr_ticket_gauge(null), null);
$t = new Ticket(2, db_dt($now - 1980 - 30));
$t->level = 'standard';
check('ticket gauge: standard owner', cdr_ticket_gauge($t), null);
$t = new Ticket(3, db_dt($now - 1980 - 30));
$g = cdr_ticket_gauge($t);
check('ticket gauge: priority open ok', $g['kind'], 'ok');
check('ticket gauge: priority label', $g['label'], '3 h 26 left');
$t = new Ticket(4, null);
check('ticket gauge: created missing', cdr_ticket_gauge($t), null);
$t = new Ticket(5, db_dt($now - 20000));
$t->open = false;
check('ticket gauge: closed without reply', cdr_ticket_gauge($t), null);
ThreadEntry::$rows = array(array(db_dt($now - 20000 + 2640), null));
$t = new Ticket(6, db_dt($now - 20000));
check('ticket gauge: reply without recipient ignored', cdr_ticket_gauge($t)['kind'], 'over');
ThreadEntry::$rows = array(array(db_dt($now - 20000 + 2640), '{"cc":[["a@b.c"]]}'));
$t = new Ticket(7, db_dt($now - 20000));
check('ticket gauge: cc-only reply ignored', cdr_ticket_gauge($t)['kind'], 'over');
ThreadEntry::$rows = array(array(db_dt($now - 20000 + 2640), '{"to":[["a@b.c"]]}'));
$t = new Ticket(8, db_dt($now - 20000));
$t->open = false;
$g = cdr_ticket_gauge($t);
check('ticket gauge: closed with client reply is done', $g['kind'], 'done');
check('ticket gauge: done label', $g['label'], 'Met in 44 min');
ThreadEntry::$rows = array();
$t = new Ticket(9, db_dt($now - 3600 - 30));
$t->sla = new StubSla();
$t->due = db_dt($now - 3630 + 7200);
$g = cdr_ticket_gauge($t);
check('ticket gauge: Priority plan due date used', $g['kind'] . ' ' . $g['label'], 'ok 59 min left');
$t = new Ticket(10, db_dt($now - 1980 - 30));
check('ticket gauge: memoised per ticket', cdr_ticket_gauge($t) === cdr_ticket_gauge($t), true);
check('ticket gauge: window kept', cdr_ticket_gauge($t)['due'] - cdr_ticket_gauge($t)['start'], CDR_FIRST_RESPONSE_HOURS * 3600);

// ---------- cdr_ticket_sla_panel (ticket view, object.view signal) ----------
check('sla panel: no gauge = nothing', cdr_ticket_sla_panel(null), '');
$t2 = new Ticket(101, db_dt($now - 600));
$t2->level = 'standard';
check('sla panel: standard owner = nothing', cdr_ticket_sla_panel($t2), '');
$h = cdr_ticket_sla_panel($t);
check('sla panel: section + large gauge', strpos($h, '<section class="cdr-sla panel"') === 0 && strpos($h, 'gauge-lg') !== false, true);
check('sla panel: window line', strpos($h, 'cdr-sla-note') !== false && strpos($h, '4 h 00') !== false, true);

// ---------- cdr_is_client_reply (I-4): fallback, then the plugin's verdict ----------
check('client reply fallback: "to" present', cdr_is_client_reply((object) array('recipients' => '{"to":[["a@b.c"]]}')), true);
check('client reply fallback: cc only', cdr_is_client_reply((object) array('recipients' => '{"cc":[["a@b.c"]]}')), false);
check('client reply fallback: null', cdr_is_client_reply((object) array('recipients' => null)), false);
check('client reply fallback: no property', cdr_is_client_reply(new stdClass()), false);
check('client reply: not an object', cdr_is_client_reply(null), false);
check('client reply fallback: plugin class absent', class_exists('SlaFirstResponsePlugin', false), false);

// Stub of contrib/sla-first-response's public static isClientReply($entry):
// it records what it receives and returns a scripted verdict, so the tests
// prove its answer wins over the "to" rule.
// Declared conditionally so PHP does not hoist it above the fallback tests.
if (!class_exists('SlaFirstResponsePlugin', false)) {
    class SlaFirstResponsePlugin {
        static $verdict = false;
        static $seen = array();
        static function isClientReply($entry) {
            self::$seen[] = $entry->recipients;
            return self::$verdict;
        }
    }
}
SlaFirstResponsePlugin::$verdict = false;
check('client reply plugin: verdict false wins over "to"', cdr_is_client_reply((object) array('recipients' => '{"to":[["a@b.c"]]}')), false);
SlaFirstResponsePlugin::$verdict = true;
check('client reply plugin: verdict true wins over cc-only', cdr_is_client_reply((object) array('recipients' => '{"cc":[["a@b.c"]]}')), true);
SlaFirstResponsePlugin::$seen = array();
ThreadEntry::$rows = array(array(db_dt($now - 20000 + 2640), '{"cc":[["a@b.c"]]}'));
$t = new Ticket(11, db_dt($now - 20000));
$g = cdr_ticket_gauge($t);
check('ticket gauge plugin: its verdict makes a cc-only reply count', $g['kind'] . ' ' . $g['label'], 'done Met in 44 min');
check('ticket gauge plugin: receives the row recipients', SlaFirstResponsePlugin::$seen, array('{"cc":[["a@b.c"]]}'));
SlaFirstResponsePlugin::$verdict = false;
ThreadEntry::$rows = array(array(db_dt($now - 20000 + 2640), '{"to":[["a@b.c"]]}'));
$t = new Ticket(12, db_dt($now - 20000));
check('ticket gauge plugin: its "no" makes a "to" reply not count', cdr_ticket_gauge($t)['kind'], 'over');
ThreadEntry::$rows = array();

echo $fails ? "\n$fails FAILED\n" : "\nall ok\n";
exit($fails ? 1 : 0);
