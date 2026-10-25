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

// osTicket's translator: the stub returns its argument, or the entry of
// $STUB_PACK (a fake language pack) when one is set.
function __($msgid) {
    global $STUB_PACK;
    return (is_array($STUB_PACK) && isset($STUB_PACK[$msgid])) ? $STUB_PACK[$msgid] : $msgid;
}
class Internationalization {
    static $lang = 'en_US';
    static function getCurrentLanguage() { return self::$lang; }
}

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
    public $id, $open = true, $created, $due = null, $sla = null, $level = 'priority', $reopened = null;
    function __construct($id, $created) { $this->id = $id; $this->created = $created; }
    function getId() { return $this->id; }
    function getThreadId() { return 100 + $this->id; }
    function isOpen() { return $this->open; }
    function getCreateDate() { return $this->created; }
    function getReopenDate() { return $this->reopened; }
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
    global $CDR_TEST_LANG;
    $CDR_TEST_LANG = 'fr_FR';
    return cdr__('Log Out');
})(), "D\xC3\xA9connexion");
$GLOBALS['CDR_TEST_LANG'] = 'fr';
// Admin save bar and changed-field labels (header.inc.php data- attributes)
check('cdr__: fr changed flag', cdr__('modified'), "modifi\xC3\xA9");
check('cdr__: fr counter (one)', str_replace('%d', '1', cdr__('%d setting changed')),
    "1 r\xC3\xA9glage modifi\xC3\xA9");
check('cdr__: fr counter (many)', str_replace('%d', '2', cdr__('%d settings changed')),
    "2 r\xC3\xA9glages modifi\xC3\xA9s");

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
    'Sign Out', '%s, administrator', 'Administrator', 'Search tickets by number, subject or client',
    'First reply', 'At risk', 'Not applicable',
    // gauge
    '%s left', 'Over by %s', 'Met in %s', 'Returned %s late',
    // ticket view
    'Priority contract: first reply to the client within %s.', 'Ticket properties',
    // ticket view and queue labels for the JS (header.inc.php data-cdr-*)
    'Properties', 'Client', 'Reply to the client', 'Internal note, not visible to the client',
    'No ticket selected', '%d ticket selected', '%d tickets selected', 'Select all tickets',
    // ticket identity block and labelled action buttons
    'Support contract', '%1$s at %2$s', 'Received on %1$s via %2$s.', 'Received on %s.',
    'Edit ticket', 'Print ticket', 'Mark as resolved',
    // queue: client line under the subject
    '%1$s, %2$s',
    // wave 4: action cluster, reply / note "Options" disclosure, "Log" tab
    'Other ticket actions', 'Sending options', 'Note options', 'To: %s',
    'Visible to the team only. The client receives nothing.', 'Log',
    'No event has been recorded for this ticket yet.',
);
$untranslated = array();
foreach ($labels as $l)
    if (cdr__($l) === $l)
        $untranslated[] = $l;
// A few labels are written the same in French: they are allowed to equal their msgid.
$same = array('Client', 'Agent', 'Agents', 'Tickets', 'Applications', 'SLA', 'API', 'Administration', 'Diagnostic', 'Menu', 'Pages', 'Plugins', '%1$s, %2$s');
check('fr.php: inventory complete', array_values(array_diff($untranslated, $same)), array());
// Every label header.inc.php passes to cdr_data_attrs() ('key' => 'Msgid' pairs of the
// file, read from the source) has a French string (or is written the same in French)
$cdr_hdr = file_get_contents(dirname(__DIR__) . '/overlay/include/staff/header.inc.php');
preg_match_all("#^\s*'([a-z0-9]+(?:-[a-z0-9]+)*)' => '([^']+)',#m", $cdr_hdr, $cdr_m);
$cdr_missing = array();
foreach ($cdr_m[2] as $l)
    if (cdr__($l) === $l && !in_array($l, $same, true))
        $cdr_missing[] = $l;
check('header data attrs: labels found', count($cdr_m[2]) >= 20, true);
check('header data attrs: every label translated', $cdr_missing, array());
check('header: skip link targets #cdr-start, emitted before #content',
    (bool) preg_match('#href="\#cdr-start".*<span id="cdr-start" tabindex="-1"></span>\s*<div id="content"#s', $cdr_hdr), true);
check('fr: wave 4 strings', implode('|', array(cdr__('Log'), cdr__('Sending options'), cdr__('To: %s'), cdr__('Note options'))),
    "Journal|Options d'envoi|\xC3\x80 : %s|Options de la note");
check('data attrs: wave 4 escaped', cdr_data_attrs(array('opts-reply' => 'Sending options', 'opts-team' => 'Visible to the team only. The client receives nothing.')),
    " data-cdr-opts-reply=\"Options d&#039;envoi\" data-cdr-opts-team=\"Visible de l&#039;\xC3\xA9quipe uniquement. Le client ne re\xC3\xA7oit rien.\"");
check('fr: sign out wording', cdr__('Sign Out'), "Se d\xC3\xA9connecter");
check('fr: search placeholder names tickets', cdr__('Search tickets by number, subject or client'),
    "Rechercher un ticket : num\xC3\xA9ro, sujet, client");
// data-cdr-* attributes (header.inc.php): translated, escaped, bad keys skipped
check('data attrs: fr', cdr_data_attrs(array('role-note' => 'Internal note, not visible to the client', 'sel-many' => '%d tickets selected')),
    " data-cdr-role-note=\"Note interne, invisible du client\" data-cdr-sel-many=\"%d tickets s\xC3\xA9lectionn\xC3\xA9s\"");
check('data attrs: escaped', cdr_data_attrs(array('x' => '"<a>" & \'b\'')), ' data-cdr-x="&quot;&lt;a&gt;&quot; &amp; &#039;b&#039;"');
check('data attrs: invalid keys skipped', cdr_data_attrs(array('A' => 'x', 'a b' => 'x', 'a"' => 'x', '-a' => 'x', 'a--b' => 'x', 0 => 'x')), ' data-cdr-0="x"');
check('data attrs: empty', cdr_data_attrs(array()), '');
check('fr: selection counter one/zero', str_replace('%d', '1', cdr__('%d ticket selected')) . '|' . cdr__('No ticket selected'),
    "1 ticket s\xC3\xA9lectionn\xC3\xA9|Aucune s\xC3\xA9lection");
check('cdr_t: test language = catalogue', cdr_t('Dashboard'), 'Tableau de bord');
check('cdr_t: unknown msgid', cdr_t('No such msgid zz'), 'No such msgid zz');

// ---------- pack precedence (real path: __() then the catalogue) ----------
unset($GLOBALS['CDR_TEST_LANG']);
$GLOBALS['STUB_PACK'] = array('Dashboard' => 'Tableau de Bord', 'Log Out' => "D\xC3\xA9connexion (pack)",
    'Sign Out' => "Se d\xC3\xA9connecter");
Internationalization::$lang = 'fr';
check('pack: cdr__ prefers the pack', cdr__('Dashboard'), 'Tableau de Bord');
check('pack: cdr_t prefers the theme wording', cdr_t('Dashboard'), 'Tableau de bord');
check('pack: cdr__ falls back to the catalogue', cdr__('Main menu'), 'Menu principal');
check('pack: Sign Out from the pack', cdr__('Sign Out'), "Se d\xC3\xA9connecter");
Internationalization::$lang = 'de';
$GLOBALS['STUB_PACK'] = array('Dashboard' => '&Uuml;bersicht');
check('pack: no theme catalogue, cdr_t uses the pack', cdr_t('Dashboard'), '&Uuml;bersicht');
check('pack: no theme catalogue, no pack entry = msgid', cdr_t('Sign Out'), 'Sign Out');
Internationalization::$lang = 'en_US';
$GLOBALS['STUB_PACK'] = null;
check('pack: English = msgid', cdr_t('Dashboard') . '|' . cdr__('Sign Out'), 'Dashboard|Sign Out');
check('data attrs: English = msgid', cdr_data_attrs(array('role-response' => 'Reply to the client')), ' data-cdr-role-response="Reply to the client"');
// The pack wins for msgids it has (Properties, Client): same wording as fr.php
Internationalization::$lang = 'fr';
$GLOBALS['STUB_PACK'] = array('Properties' => "Propri\xC3\xA9t\xC3\xA9s (pack)");
check('data attrs: pack wins', cdr_data_attrs(array('title-props' => 'Properties', 'role-response' => 'Reply to the client')),
    " data-cdr-title-props=\"Propri\xC3\xA9t\xC3\xA9s (pack)\" data-cdr-role-response=\"R\xC3\xA9ponse au client\"");
Internationalization::$lang = 'en_US';
$GLOBALS['STUB_PACK'] = null;
$GLOBALS['CDR_TEST_LANG'] = 'fr';

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

// ---------- cdr_ticket_idline (pure builder) ----------
$full = array('number' => '260418', 'href' => '/scp/tickets.php?id=7', 'reload' => 'Reload',
    'state' => 'open', 'status' => 'Open', 'plan' => array('level' => 'priority', 'label' => 'Priority'),
    'urgent' => 'Emergency', 'name' => "L\xC3\xA9a Marchand", 'org' => 'Maison Verdier',
    'date' => '21/10/2026', 'time' => '08:47', 'source' => 'Web');
$h = cdr_ticket_idline($full);
check('idline: section and line', strpos($h, '<section class="cdr-idline"><p class="idline">') === 0, true);
check('idline: reload link', strpos($h, '<a class="cdr-id" href="/scp/tickets.php?id=7" title="Reload">Ticket #260418</a>') !== false, true);
check('idline: three separators', substr_count($h, '<span class="sep" aria-hidden="true"></span>'), 3);
check('idline: state chip with icon', (bool) preg_match('#<span class="status status-open"><svg class="ic ic-inbox"[^>]*>.*?</svg>Open</span>#', $h), true);
check('idline: priority plan with bolt', (bool) preg_match('#<span class="plan plan-priority" title="Support contract"><svg class="ic ic-bolt"[^>]*>.*?</svg>Priority</span>#', $h), true);
check('idline: urgency', (bool) preg_match('#<span class="urg" title="Priority"><svg class="ic ic-alert"[^>]*>.*?</svg>Emergency</span>#', $h), true);
check('idline: order', strpos($h, 'cdr-id') < strpos($h, 'status-open') && strpos($h, 'status-open') < strpos($h, 'plan-priority')
    && strpos($h, 'plan-priority') < strpos($h, 'class="urg"'), true);
check('idline: subtitle (en)', strpos($h, "<p class=\"sub cdr-sub\">L\xC3\xA9a Marchand, Maison Verdier. Received on 21/10/2026 at 08:47 via Web.</p></section>") !== false, true);
$GLOBALS['CDR_TEST_LANG'] = 'fr';
check('idline: subtitle (fr)', strpos(cdr_ticket_idline($full),
    "<p class=\"sub cdr-sub\">L\xC3\xA9a Marchand, Maison Verdier. Re\xC3\xA7u le 21/10/2026 \xC3\xA0 08:47 via Web.</p>") !== false, true);
check('idline: plan title (fr)', strpos(cdr_ticket_idline($full), 'title="Contrat de support"') !== false, true);
unset($GLOBALS['CDR_TEST_LANG']);
// Hostile values: every one escaped, none breaks out of its element.
$evil = '<img src=x onerror=alert(1)>"\'&';
$h = cdr_ticket_idline(array('number' => $evil, 'href' => 'javascript:"><b>', 'reload' => $evil, 'state' => 'op"en<',
    'status' => $evil, 'plan' => array('level' => '"><x', 'label' => $evil), 'urgent' => $evil,
    'name' => $evil, 'org' => $evil, 'date' => $evil, 'time' => $evil, 'source' => $evil));
check('idline hostile: no raw markup', strpos($h, '<img') === false && strpos($h, '"><b>') === false && strpos($h, '"><x') === false, true);
check('idline hostile: escaped text', substr_count($h, '&lt;img src=x onerror=alert(1)&gt;&quot;&#039;&amp;') >= 6, true);
check('idline hostile: state class sanitised', strpos($h, 'class="status status-open"') !== false, true);
check('idline hostile: unknown plan level = standard', strpos($h, 'class="plan plan-standard"') !== false, true);
// Missing parts are left out.
$h = cdr_ticket_idline(array('number' => '1', 'name' => 'Ann', 'date' => '01/02/2026'));
check('idline minimal: no link without href', strpos($h, '<span class="cdr-id">Ticket #1</span>') !== false, true);
check('idline minimal: no separator', strpos($h, 'class="sep"'), false);
check('idline minimal: no status, plan, urgency', strpos($h, 'status') === false && strpos($h, 'plan') === false
    && strpos($h, 'urg') === false, true);
check('idline minimal: no org, no time, no source', strpos($h, '<p class="sub cdr-sub">Ann. Received on 01/02/2026.</p>') !== false, true);
check('idline: name only', strpos(cdr_ticket_idline(array('number' => '1', 'name' => 'Ann', 'org' => 'Acme')),
    '<p class="sub cdr-sub">Ann, Acme.</p>') !== false, true);
check('idline: date without name', strpos(cdr_ticket_idline(array('number' => '1', 'date' => 'd', 'time' => 't', 'source' => 'Email')),
    '<p class="sub cdr-sub">Received on d at t via Email.</p>') !== false, true);
check('idline: no subtitle at all', strpos(cdr_ticket_idline(array('number' => '1')), 'cdr-sub'), false);
check('idline: no number = nothing', cdr_ticket_idline(array('name' => 'Ann')), '');
check('idline: not an array', cdr_ticket_idline(null), '');
check('idline: closed state icon', strpos(cdr_ticket_idline(array('number' => '1', 'state' => 'closed', 'status' => 'Resolved')),
    '<span class="status status-closed"><svg class="ic ic-checkc"') !== false, true);
check('idline: standard plan, no bolt', (bool) preg_match('#<span class="plan plan-standard"[^>]*>Standard</span>#',
    cdr_ticket_idline(array('number' => '1', 'plan' => array('level' => 'standard', 'label' => 'Standard')))), true);
check('idline: empty plan label = no chip', strpos(cdr_ticket_idline(array('number' => '1', 'plan' => array('level' => 'priority', 'label' => ' '))), 'plan'), false);
check('idline: no icon is empty', strpos(cdr_ticket_idline($full), '<svg class="ic ic-') !== false
    && cdr_icon('inbox') !== '' && cdr_icon('checkc') !== '' && cdr_icon('layers') !== '' && cdr_icon('ban') !== '', true);

// ---------- cdr_ticket_idline_data (stubs of the Ticket API it reads) ----------
class Format {
    static function date($d) { return 'D(' . $d . ')'; }
    static function time($d) { return 'T(' . $d . ')'; }
}
class StubStatus {
    public $n, $s;
    function __construct($n, $s) { $this->n = $n; $this->s = $s; }
    function getName() { return $this->n; }
    function getState() { return $this->s; }
}
class StubPriority {
    public $d, $u;
    function __construct($d, $u) { $this->d = $d; $this->u = $u; }
    function getDesc() { return $this->d; }
    function getUrgency() { return $this->u; }
}
class StubOrg { function getName() { return 'Maison <Verdier>'; } }
class StubOwner {
    public $org;
    function getOrganization() { return $this->org; }
}
class StubIdTicket {
    public $status, $priority, $owner, $created = '2026-10-21 06:47:00', $source = 'Web', $level = 'priority',
        $planLabel = 'Priority', $planSource = 'organisation', $reopened = null, $boom = false;
    function __construct() {
        $this->status = new StubStatus('Open', 'open');
        $this->priority = new StubPriority('Normal', 3);
        $this->owner = new StubOwner();
        $this->owner->org = new StubOrg();
    }
    function getId() { return 7; }
    function getNumber() { return '260418'; }
    function getStatus() { return $this->status; }
    function getPriority() { if ($this->boom) throw new Exception('db'); return $this->priority; }
    function getName() { return "L\xC3\xA9a <Marchand>"; }
    function getOwner() { return $this->owner; }
    function getCreateDate() { return $this->created; }
    function getReopenDate() { return $this->reopened; }
    function getSource() { return $this->source; }
}
$t = new StubIdTicket();
$d = cdr_ticket_idline_data($t);
check('idline data: no plugin = no plan', array_key_exists('plan', $d), false);
check('idline data: values', array($d['number'], $d['href'], $d['reload'], $d['status'], $d['state'], $d['name'],
    $d['org'], $d['date'], $d['time'], $d['source']),
    array('260418', '/scp/tickets.php?id=7', 'Reload', 'Open', 'open', "L\xC3\xA9a <Marchand>", 'Maison <Verdier>',
        'D(2026-10-21 06:47:00)', 'T(2026-10-21 06:47:00)', 'Web'));
check('idline data: normal priority is not urgent', array_key_exists('urgent', $d), false);
$h = cdr_ticket_idline($d);
check('idline data: hostile names escaped', strpos($h, "L\xC3\xA9a &lt;Marchand&gt;, Maison &lt;Verdier&gt;.") !== false
    && strpos($h, '<Marchand>') === false, true);
$t->priority = new StubPriority('High', 2);
check('idline data: urgency 2 is urgent', cdr_ticket_idline_data($t)['urgent'] ?? null, 'High');
$t->priority = new StubPriority('Emergency', 1);
check('idline data: urgency 1 is urgent', cdr_ticket_idline_data($t)['urgent'] ?? null, 'Emergency');
$t->priority = new StubPriority('Urgente', 0);
check('idline data: urgency 0 (unset) is not urgent', array_key_exists('urgent', cdr_ticket_idline_data($t)), false);
$t->priority = null;
check('idline data: no priority', array_key_exists('urgent', cdr_ticket_idline_data($t)), false);
$t->boom = true;
$d = cdr_ticket_idline_data($t);
check('idline data: a throwing getter only drops its part', array_key_exists('urgent', $d) === false && $d['number'] === '260418'
    && $d['source'] === 'Web', true);
$t->boom = false;
$t->owner->org = null;
$t->source = '';
check('idline data: missing org and source', strpos(cdr_ticket_idline(cdr_ticket_idline_data($t)),
    "L\xC3\xA9a &lt;Marchand&gt;. Received on D(2026-10-21 06:47:00) at T(2026-10-21 06:47:00).</p>") !== false, true);
// Reopened: the subtitle keeps the creation date, the state is the current status.
$t = new StubIdTicket();
$t->reopened = '2026-10-23 10:00:00';
$t->status = new StubStatus('Open', 'open');
$d = cdr_ticket_idline_data($t);
check('idline data: reopened keeps the creation date', $d['date'] . '|' . $d['state'], 'D(2026-10-21 06:47:00)|open');
check('idline data: not an object', cdr_ticket_idline_data(null), array());
check('idline data: unrelated object', cdr_ticket_idline(cdr_ticket_idline_data(new stdClass())), '');

// ---------- cdr_ticket_gauge (stubs; the real queries are not exercised) ----------
$now = time();
check('ticket gauge: no plugin function', cdr_ticket_gauge(new Ticket(1, db_dt($now - 600))), null);
if (!function_exists('coderise_contract_level')) {
    function coderise_contract_level($t) {
        return array('level' => $t->level, 'source' => isset($t->planSource) ? $t->planSource : 'org',
            'label' => isset($t->planLabel) ? $t->planLabel : '');
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
// Reopened: core moved est_duedate to reopen + grace; the window stays created + 4 h.
$t = new Ticket(13, db_dt($now - 5 * 3600));
$t->sla = new StubSla();
$t->reopened = db_dt($now - 600);
$t->due = db_dt($now - 600 + 4 * 3600);
$g = cdr_ticket_gauge($t);
check('ticket gauge: reopened ignores the plan due date', $g['due'] - $g['start'], CDR_FIRST_RESPONSE_HOURS * 3600);
check('ticket gauge: reopened past created + 4 h is over', $g['kind'] . ' ' . $g['label'], 'over Over by 1 h 00');
$t = new Ticket(14, db_dt($now - 5 * 3600));
$t->sla = new StubSla();
// 30 s of margin: the gauge reads the clock again, 3630 s or 3629 s left both floor to 60 min
$t->due = db_dt($now + 3630);
$g = cdr_ticket_gauge($t);
check('ticket gauge: not reopened keeps the plan due date', $g['kind'] . ' ' . $g['label'], 'warn 1 h 00 left');
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

// ---------- cdr_ticket_idline_data with the plugin (stub above) ----------
$t = new StubIdTicket();
check('idline data plugin: priority plan', cdr_ticket_idline_data($t)['plan'] ?? null, array('level' => 'priority', 'label' => 'Priority'));
$t->level = 'standard';
$t->planLabel = 'Standard';
$h = cdr_ticket_idline(cdr_ticket_idline_data($t));
check('idline data plugin: standard chip', (bool) preg_match('#<span class="plan plan-standard"[^>]*>Standard</span>#', $h), true);
$t->planSource = 'default';
check('idline data plugin: no Priority organisation configured = no chip', array_key_exists('plan', cdr_ticket_idline_data($t)), false);

// ---------- cdr_plan_chip (queue and identity line) ----------
check('plan chip: priority', cdr_plan_chip(array('level' => 'priority', 'label' => 'Priority')),
    '<span class="plan plan-priority" title="Support contract">' . cdr_icon('bolt', 12) . 'Priority</span>');
check('plan chip: standard, no icon', cdr_plan_chip(array('level' => 'standard', 'label' => ' Standard ')),
    '<span class="plan plan-standard" title="Support contract">Standard</span>');
check('plan chip: unknown level = standard', strpos(cdr_plan_chip(array('level' => '"><x', 'label' => 'S')), 'class="plan plan-standard"') !== false, true);
check('plan chip: no label', cdr_plan_chip(array('level' => 'priority', 'label' => ' ')), '');
check('plan chip: label not scalar', cdr_plan_chip(array('level' => 'priority', 'label' => array('x'))), '');
check('plan chip: not an array', cdr_plan_chip(null), '');
check('plan chip: hostile label escaped', cdr_plan_chip(array('level' => 'priority', 'label' => $evil)),
    '<span class="plan plan-priority" title="Support contract">' . cdr_icon('bolt', 12)
    . '&lt;img src=x onerror=alert(1)&gt;&quot;&#039;&amp;</span>');
$GLOBALS['CDR_TEST_LANG'] = 'fr';
check('plan chip: title (fr)', strpos(cdr_plan_chip(array('level' => 'standard', 'label' => 'Standard')), 'title="Contrat de support"') !== false, true);
unset($GLOBALS['CDR_TEST_LANG']);
check('plan chip: same markup as the identity line', strpos(cdr_ticket_idline(array('number' => '1',
    'plan' => array('level' => 'priority', 'label' => 'Priority'))),
    cdr_plan_chip(array('level' => 'priority', 'label' => 'Priority'))) !== false, true);

// ---------- cdr_queue_layout (columns found by data path) ----------
// Seeded "Open" queue: Ticket, Last Updated, Subject, From, Priority, Assigned To
check('queue layout: seeded open queue', cdr_queue_layout(array('number', 'lastupdate', 'cdata__subject', 'user__name', 'cdata__priority', 'assignee')),
    array('number' => 0, 'subject' => 2, 'user' => 3, 'gauge' => 2));
check('queue layout: mockup order', cdr_queue_layout(array('number', 'cdata__subject', 'status__id', 'assignee', 'lastupdate')),
    array('number' => 0, 'subject' => 1, 'user' => null, 'gauge' => 1));
check('queue layout: no subject = gauge last', cdr_queue_layout(array('number', 'created', 'assignee')),
    array('number' => 0, 'subject' => null, 'user' => null, 'gauge' => 2));
check('queue layout: no column = gauge first', cdr_queue_layout(array()),
    array('number' => null, 'subject' => null, 'user' => null, 'gauge' => -1));
check('queue layout: first match wins, case and spaces ignored', cdr_queue_layout(array('assignee', ' CDATA__Subject', 'cdata__subject', 'user__name', 'user__name')),
    array('number' => null, 'subject' => 1, 'user' => 3, 'gauge' => 1));
check('queue layout: subject-like paths are not the subject', cdr_queue_layout(array('cdata__subject_extra', 'thread__lastmessage', 'user__email')),
    array('number' => null, 'subject' => null, 'user' => null, 'gauge' => 2));
check('queue layout: non-scalar paths skipped', cdr_queue_layout(array(null, array('x'), 'subject')),
    array('number' => null, 'subject' => 2, 'user' => null, 'gauge' => 2));
check('queue layout: associative input', cdr_queue_layout(array('a' => 'number', 'b' => 'user__name')),
    array('number' => 0, 'subject' => null, 'user' => 1, 'gauge' => 1));

// ---------- cdr_queue_client (client line under the subject) ----------
check('queue client: name and org', cdr_queue_client("L\xC3\xA9a Marchand", 'Maison Verdier'),
    "<small class=\"cdr-client\">L\xC3\xA9a Marchand, Maison Verdier</small>");
check('queue client: name only', cdr_queue_client('Hugo Bernard', null), '<small class="cdr-client">Hugo Bernard</small>');
check('queue client: blank org', cdr_queue_client('Hugo Bernard', '  '), '<small class="cdr-client">Hugo Bernard</small>');
check('queue client: no name', cdr_queue_client('', 'Org'), '');
check('queue client: null name', cdr_queue_client(null, 'Org'), '');
check('queue client: hostile values escaped', cdr_queue_client($evil, '</small><b>'),
    '<small class="cdr-client">&lt;img src=x onerror=alert(1)&gt;&quot;&#039;&amp;, &lt;/small&gt;&lt;b&gt;</small>');
$GLOBALS['CDR_TEST_LANG'] = 'fr';
check('queue client: fr wording', cdr_queue_client('Camille Roy', "H\xC3\xA9lios Logistique"),
    "<small class=\"cdr-client\">Camille Roy, H\xC3\xA9lios Logistique</small>");
unset($GLOBALS['CDR_TEST_LANG']);

// ---------- cdr_queue_client_html (core's rendered "From" cell under the subject) ----------
$cell = '<span class="pull-right"><span class="pull-right faded-more" data-toggle="tooltip" title="2"><i class="icon-group"></i></span></span>L&eacute;a Moreau';
check('queue client html: core cell kept as is, org escaped', cdr_queue_client_html($cell, 'A & <B>'),
    '<small class="cdr-client">' . $cell . ', A &amp; &lt;B&gt;</small>');
check('queue client html: no org', cdr_queue_client_html('<a href="users.php?id=2">Nina Petit</a>', null),
    '<small class="cdr-client"><a href="users.php?id=2">Nina Petit</a></small>');
check('queue client html: empty cell', cdr_queue_client_html('', 'Org'), '');
check('queue client html: markup without text', cdr_queue_client_html('<span class="pull-right"></span> ', 'Org'), '');
check('queue client html: entity-only blank', cdr_queue_client_html('&nbsp;', 'Org'), '');
check('queue client html: not scalar', cdr_queue_client_html(array('x'), 'Org'), '');

// ---------- cdr_resolve_status_id / cdr_ticket_resolve_status ("Mark as resolved") ----------
check('resolve: stock statuses = Resolved (2)', cdr_resolve_status_id(array(2 => 'closed', 3 => 'closed')), 2);
check('resolve: Resolved first even when Closed comes first', cdr_resolve_status_id(array(3 => 'closed', 2 => 'closed')), 2);
check('resolve: id 2 not closed is not Resolved', cdr_resolve_status_id(array(2 => 'archived', 3 => 'closed')), 3);
check('resolve: Resolved deleted, one closed left', cdr_resolve_status_id(array(3 => 'closed')), 3);
check('resolve: Resolved deleted, several closed = no button', cdr_resolve_status_id(array(3 => 'closed', 7 => 'closed')), 0);
check('resolve: none closed', cdr_resolve_status_id(array(1 => 'open', 4 => 'archived')), 0);
check('resolve: empty / not an array', cdr_resolve_status_id(null), 0);
check('resolve: bad ids ignored', cdr_resolve_status_id(array(0 => 'closed', -1 => 'closed', 9 => 'closed')), 9);
if (!class_exists('TicketStatusList', false)) {
    class StubListStatus {
        public $id, $state;
        function __construct($id, $state) { $this->id = $id; $this->state = $state; }
        function getId() { return $this->id; }
        function getState() { return $this->state; }
    }
    class TicketStatusList {
        static $items = array();
        static $seen = null;
        static function getStatuses($criteria=array()) {
            self::$seen = $criteria;
            if (self::$items === 'throw')
                throw new Exception('db');
            return self::$items;
        }
    }
}
TicketStatusList::$items = array(new StubListStatus(2, 'closed'), new StubListStatus(3, 'closed'));
$t = new Ticket(201, db_dt($now));
check('ticket resolve: open ticket, stock statuses', cdr_ticket_resolve_status($t), 2);
check('ticket resolve: asks core for the closed statuses', TicketStatusList::$seen, array('states' => array('closed')));
$t->open = false;
check('ticket resolve: closed ticket = no button', cdr_ticket_resolve_status($t), 0);
$t->open = true;
TicketStatusList::$items = array(new StubListStatus(3, 'closed'), new StubListStatus(8, 'closed'));
check('ticket resolve: no Resolved, two closed = no button', cdr_ticket_resolve_status($t), 0);
TicketStatusList::$items = 'throw';
check('ticket resolve: core error = no button', cdr_ticket_resolve_status($t), 0);
check('ticket resolve: no ticket', cdr_ticket_resolve_status(null), 0);

echo $fails ? "\n$fails FAILED\n" : "\nall ok\n";
exit($fails ? 1 : 0);
