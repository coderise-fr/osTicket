<?php
/*********************************************************************
    deploy/themes-staff/coderise-agent/tests/test-rail.php

    CLI test of the shell helpers of the coderise-agent staff theme:
    cdr_rail(), cdr_crumbs() and cdr_content_class() (overlay/include/
    staff/coderise/coderise.inc.php). StaffNav/AdminNav, Staff and $ost are
    replaced by stubs; the agent sub-navigation is rendered by the real core
    template include/staff/templates/sub-navigation.tmpl.php.

    Usage (from the repository root, in the demo image):
      php -d open_basedir= -d display_errors=1 -d error_reporting=-1 \
          deploy/themes-staff/coderise-agent/tests/test-rail.php

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
error_reporting(-1);
ini_set('display_errors', '1');
define('INCLUDE_DIR', __DIR__ . '/');
define('ROOT_PATH', '/');
define('STAFFINC_DIR', realpath(__DIR__ . '/../../../../include/staff') . '/');

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

// Any PHP notice or warning raised by the theme's code is a failure. The
// core template sub-navigation.tmpl.php reads $info['class'] on a null
// sub-nav info (every tab but Tickets): that warning is core's own, also
// raised under the core header, and is not counted.
$php_errors = array();
set_error_handler(function ($no, $str, $file, $line) {
    global $php_errors;
    if (basename($file) !== 'sub-navigation.tmpl.php')
        $php_errors[] = basename($file) . ":$line $str";
    return true;
});

// osTicket's translator: the stub returns its argument (no language pack).
function __($msgid) { return $msgid; }

// ---------- stubs ----------
class StubNav {
    public $tabs, $submenus, $activeTab, $activeMenu, $info;
    static $seen = array();
    function __construct($tabs, $submenus, $activeTab, $activeMenu=null, $info=null) {
        $this->tabs = $tabs;
        $this->submenus = $submenus;
        $this->activeTab = $activeTab;
        $this->activeMenu = $activeMenu;
        $this->info = $info;
        if ($activeTab && isset($this->tabs[$activeTab]))
            $this->tabs[$activeTab]['active'] = true;
    }
    function getTabs() { return $this->tabs; }
    function getSubMenus() { return $this->submenus; }
    function getSubMenu($tab=null) {
        $tab = $tab ?: $this->activeTab;
        return isset($this->submenus[$tab]) ? $this->submenus[$tab] : null;
    }
    function getActiveTab() { return $this->activeTab; }
    function getActiveMenu() { return $this->activeMenu; }
    function getSubNavInfo() { return $this->info; }
}
class StubDept {
    public $name;
    function __construct($n) { $this->name = $n; }
    function getName() { return $this->name; }
}
class StubStaff {
    public $name, $admin, $dept;
    function __construct($name, $admin, $dept='Support') {
        $this->name = $name; $this->admin = $admin; $this->dept = $dept;
    }
    function getName() { return $this->name; }
    function isAdmin() { return $this->admin; }
    function getDept() { return $this->dept === null ? null : new StubDept($this->dept); }
    function hasPerm($p, $global=true) { return true; }
}
class StubOst {
    function getLinkToken() { return 'TOKEN123'; }
}
$ost = new StubOst();

/** A sub-menu item with every key the core template reads. */
function item($desc, $href, $extra=array()) {
    return $extra + array('desc' => $desc, 'href' => $href, 'title' => $desc,
        'iconclass' => 'x', 'id' => '', 'urls' => array());
}
function agent_tabs() {
    return array(
        'dashboard' => array('desc' => 'Dashboard', 'href' => 'dashboard.php', 'title' => 'Agent Dashboard', 'class' => 'no-pjax'),
        'users' => array('desc' => 'Users', 'href' => 'users.php', 'title' => 'User Directory'),
        'tasks' => array('desc' => 'Tasks', 'href' => 'tasks.php', 'title' => 'Task Queue'),
        'tickets' => array('desc' => 'Tickets', 'href' => 'tickets.php', 'title' => 'Ticket Queue'),
        'kbase' => array('desc' => 'Knowledgebase', 'href' => 'kb.php', 'title' => 'Knowledgebase'),
        'apps' => array('desc' => 'Applications', 'href' => 'apps.php', 'title' => 'Applications'),
    );
}
function agent_nav() {
    // Type-hinted like a plugin closure may be: it must receive the real nav
    // object, not the theme's CdrNavView wrapper (TypeError otherwise).
    $queue = function (StubNav $nav) {
        StubNav::$seen[] = get_class($nav);
        echo '<li class="top-queue item active"><a href="tickets.php?queue=1" class="Ticket">Open'
            . '<span id="queue-count-bucket" class="hidden">(<span class="queue-count" data-queue-id="1"></span>)</span></a>'
            . '<div class="customQ-dropdown"><ul class="scroll-height"></ul></div></li>';
        return true;
    };
    return new StubNav(agent_tabs(), array(
        'tickets' => array($queue,
            item('New Ticket', 'tickets.php?a=open', array('id' => 'new-ticket', 'iconclass' => 'newTicket'))),
        'dashboard' => array(item('Dashboard', 'dashboard.php'), item('Agent Directory', 'directory.php')),
        'users' => array(item('User Directory', 'users.php'), item('Organizations', 'orgs.php')),
        'tasks' => array(item('Tasks', 'tasks.php', array('droponly' => true))),
    // Real StaffNav::addSubNavInfo() stores the key "classes" (the core
    // template reads "class", so it prints an empty class attribute).
    ), 'tickets', null, array('classes' => ' jb-overflowmenu', 'id' => 'customQ_nav'));
}
function admin_tabs() {
    return array(
        'dashboard' => array('desc' => 'Dashboard', 'href' => 'logs.php', 'title' => 'Admin Dashboard'),
        'settings' => array('desc' => 'Settings', 'href' => 'settings.php', 'title' => 'System Settings'),
        'manage' => array('desc' => 'Manage', 'href' => 'helptopics.php', 'title' => 'Manage Options'),
        'emails' => array('desc' => 'Emails', 'href' => 'emails.php', 'title' => 'Email Settings'),
        'staff' => array('desc' => 'Agents', 'href' => 'staff.php', 'title' => 'Manage Agents'),
    );
}
function admin_nav($activeTab, $activeMenu=null) {
    return new StubNav(admin_tabs(), array(
        'dashboard' => array(item('System Logs', 'logs.php'), item('Information', 'system.php')),
        'settings' => array(item('Company', 'settings.php?t=pages'), item('System', 'settings.php?t=system'),
            item('Tickets', 'settings.php?t=tickets')),
        'manage' => array(item('Help Topics', 'helptopics.php'), item('Hidden', 'x.php', array('droponly' => true))),
        'emails' => array(item('Emails', 'emails.php'), item('Settings', 'emailsettings.php')),
        'staff' => array(item('Agents', 'staff.php'), item('Teams', 'teams.php')),
    ), $activeTab, $activeMenu);
}
/** Attributes of the <a> whose visible text ends with $label (first match). */
function link_of($html, $label) {
    $m = array();
    if (preg_match('#<a ([^>]*)>(?:(?!</a>).)*?' . preg_quote($label, '#') . '\s*</a>#s', $html, $m))
        return $m[1];
    return null;
}
function current_labels($html) {
    $m = array();
    preg_match_all('#<a [^>]*aria-current="page"[^>]*>(?:<svg.*?</svg>)?\s*([^<]*)#s', $html, $m);
    return array_map('trim', $m[1]);
}
function dup_ids($html) {
    $m = array();
    preg_match_all('/\sid="([^"]+)"/', $html, $m);
    $c = array_count_values($m[1]);
    return array_keys(array_filter($c, function ($n) { return $n > 1; }));
}

// ---------- load ----------
$file = __DIR__ . '/../overlay/include/staff/coderise/coderise.inc.php';
if (!is_file($file)) {
    echo "FAIL  coderise.inc.php is missing: $file\n";
    exit(1);
}
require $file;
foreach (array('cdr_rail', 'cdr_crumbs', 'cdr_content_class') as $fn)
    if (!function_exists($fn)) {
        echo "FAIL  $fn() is not defined\n";
        exit(1);
    }

$admin = new StubStaff('Nora Benali', true);
$agent = new StubStaff('Theo Martin', false);
$_SERVER['SCRIPT_NAME'] = '/scp/tickets.php';
$_GET = $_REQUEST = array();

// ---------- agent mode ----------
$h = cdr_rail(agent_nav(), $admin);
check('agent: aside.rail', strpos($h, '<aside class="rail') === 0, true);
check('agent: nav closure receives the real nav (type-hinted)', StubNav::$seen, array('StubNav'));
preg_match_all('#<a class="item[^"]*" href="([^"]+)"[^>]*>(<svg class="ic ic-([a-z]+))?#', $h, $m);
check('agent: items, order and hrefs', $m[1], array('/scp/dashboard.php', '/scp/users.php', '/scp/tasks.php',
    '/scp/tickets.php', '/scp/kb.php', '/scp/apps.php', '/scp/profile.php', '/scp/logout.php?auth=TOKEN123'));
check('agent: icons by tab key', array_slice($m[3], 0, 6), array('chart', 'users', 'tasks', 'ticket', 'book', 'layers'));
check('agent: Dashboard item is no-pjax', strpos(link_of($h, 'Dashboard'), 'no-pjax') !== false, true);
check('agent: Tickets item is not no-pjax', strpos(link_of($h, 'Tickets'), 'no-pjax'), false);
check('agent: active tab marked', current_labels($h), array('Tickets'));
check('agent: one .queues block', substr_count($h, 'class="queues"'), 1);
check('agent: .queues under the active tab', strpos($h, 'class="queues"') > strpos($h, '/scp/tickets.php')
    && strpos($h, 'class="queues"') < strpos($h, '/scp/kb.php'), true);
check('agent: customQ_nav renamed', strpos($h, 'customQ_nav'), false);
check('agent: cdr-queues id', substr_count($h, 'id="cdr-queues"'), 1);
check('agent: no jb-overflowmenu class', strpos($h, 'jb-overflowmenu'), false);
check('agent: queue callable rendered', strpos($h, 'data-queue-id="1"') !== false, true);
check('agent: #new-ticket kept', strpos($h, 'id="new-ticket"') !== false, true);
check('agent: mode switch present for an admin', strpos($h, 'class="mode"') !== false, true);
check('agent: Agent segment current', (bool) preg_match('#<a [^>]*href="/scp/index.php"[^>]*aria-current="true"#', $h), true);
check('agent: Administration segment not current', (bool) preg_match('#<a [^>]*href="/scp/admin.php"[^>]*aria-current#', $h), false);
check('agent: mode links are no-pjax', preg_match_all('#<a class="no-pjax" href="/scp/(index|admin)\.php"#', $h), 2);
check('agent: logout no-pjax with token', strpos(link_of($h, 'Log Out'), 'no-pjax') !== false
    && strpos($h, 'href="/scp/logout.php?auth=TOKEN123"') !== false, true);
check('agent: foot initials and name', strpos($h, '>NB</span>') !== false && strpos($h, 'Nora Benali') !== false, true);
check('agent: mobile toggle', (bool) preg_match('#<button type="button" class="menu-toggle" aria-expanded="false" aria-controls="cdr-rail-menu"#', $h), true);
check('agent: no duplicate ids', dup_ids($h), array());

// Dashboard tab active: its sub-menu in .queues, droponly elsewhere ignored.
$nav = agent_nav();
$nav = new StubNav(agent_tabs(), $nav->submenus, 'dashboard', 1);
$_SERVER['SCRIPT_NAME'] = '/scp/dashboard.php';
$h = cdr_rail($nav, $admin);
check('agent dashboard: active tab', current_labels($h), array('Dashboard'));
check('agent dashboard: sub-menu shown', strpos($h, 'directory.php') !== false, true);
check('agent dashboard: no queue', strpos($h, 'data-queue-id'), false);

// Non-admin: no Administration segment at all.
$_SERVER['SCRIPT_NAME'] = '/scp/tickets.php';
$h = cdr_rail(agent_nav(), $agent);
check('non-admin: no mode switch', strpos($h, 'class="mode"'), false);
check('non-admin: no Administration', strpos($h, 'Administration'), false);
check('non-admin: no admin.php link', strpos($h, 'admin.php'), false);

// $nav null (profile.php guards "if ($nav)").
$h = cdr_rail(null, $agent);
check('nav null: still a rail with its foot', strpos($h, '<aside class="rail') === 0 && strpos($h, 'logout.php') !== false, true);
check('nav null: no item but the foot', preg_match_all('#<a class="item#', $h), 2);
check('crumbs: nav null', cdr_crumbs(null), '');

// Hostile labels and names.
$tabs = agent_tabs();
$tabs['tickets']['desc'] = '<script>x</script>';
$evil = new StubStaff("D'Arc \"Jo\" <b>", true, '<i>Dept</i>');
$h = cdr_rail(new StubNav($tabs, array(), 'tickets'), $evil);
check('hostile: tab label escaped', strpos($h, '<script>'), false);
check('hostile: tab label shown escaped', strpos($h, '&lt;script&gt;x&lt;/script&gt;') !== false, true);
check('hostile: staff name escaped', strpos($h, 'D&#039;Arc &quot;Jo&quot; &lt;b&gt;') !== false && strpos($h, "D'Arc \"Jo\" <b>") === false, true);
check('hostile: dept escaped', strpos($h, '&lt;i&gt;Dept&lt;/i&gt;') !== false, true);
check('hostile: initials', strpos($h, '>D&amp;</span>') !== false || strpos($h, '>DB</span>') !== false
    || strpos($h, '>D&lt;</span>') !== false, true);

// Labels through cdr__ (French catalogue).
$GLOBALS['CDR_TEST_LANG'] = 'fr';
$h = cdr_rail(agent_nav(), $admin);
check('fr: tab label', link_of($h, 'Tableau de bord') !== null, true);
check('fr: log out', link_of($h, "D\xC3\xA9connexion") !== null, true);
check('fr: profile', link_of($h, 'Mon profil') !== null, true);
check('fr: menu button', strpos($h, '>Menu</button>') !== false || strpos($h, 'Menu</span></button>') !== false, true);
// Agent sub-menu items are rendered by the core template: labels still go through cdr__.
$_SERVER['SCRIPT_NAME'] = '/scp/directory.php';
$h = cdr_rail(new StubNav(agent_tabs(), agent_nav()->submenus, 'dashboard', 2), $admin);
check('fr: agent sub-menu label translated', strpos($h, '>Annuaire des agents</a>') !== false, true);
check('fr: agent sub-menu msgid gone', strpos($h, 'Agent Directory'), false);
check('fr: queue closures untouched', strpos(cdr_rail(agent_nav(), $admin), 'data-queue-id="1"') !== false, true);
$_SERVER['SCRIPT_NAME'] = '/scp/orgs.php';
check('fr: crumbs sub-item translated', cdr_crumbs(new StubNav(agent_tabs(), agent_nav()->submenus, 'users', -1)),
    '<nav class="crumbs" aria-label="Fil d&#039;Ariane"><ol><li><a href="/scp/users.php">Utilisateurs</a></li>'
    . '<li><span aria-current="page">Organisations</span></li></ol></nav>');
unset($GLOBALS['CDR_TEST_LANG']);
$_SERVER['SCRIPT_NAME'] = '/scp/tickets.php';

// Hostile agent sub-menu label (plugin app): escaped once rendered by the core template.
$sub = agent_nav()->submenus;
$sub['dashboard'][1]['desc'] = '<img src=x onerror=alert(1)>';
$h = cdr_rail(new StubNav(agent_tabs(), $sub, 'dashboard', 1), $admin);
check('hostile: agent sub-menu label escaped', strpos($h, '<img src=x') === false
    && strpos($h, '&lt;img src=x onerror=alert(1)&gt;') !== false, true);

// Landmarks: the aside and the nav have distinct labels.
$m = array();
preg_match_all('#<(?:aside|nav) [^>]*aria-label="([^"]*)"#', cdr_rail(agent_nav(), $admin), $m);
check('landmarks: distinct labels', count($m[1]) === count(array_unique($m[1])), true);

// ---------- crumbs and #content classes (agent) ----------
$nav = agent_nav();
check('crumbs: queue name', cdr_crumbs($nav, 'Open <b>'),
    '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="/scp/tickets.php">Tickets</a></li>'
    . '<li><span aria-current="page">Open &lt;b&gt;</span></li></ol></nav>');
check('crumbs: no current = active sub-item', cdr_crumbs(new StubNav(agent_tabs(), $nav->submenus, 'dashboard', 2)),
    '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="/scp/dashboard.php">Dashboard</a></li>'
    . '<li><span aria-current="page">Agent Directory</span></li></ol></nav>');
check('crumbs: same label not repeated', cdr_crumbs(new StubNav(agent_tabs(), $nav->submenus, 'dashboard', 1)),
    '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><span aria-current="page">Dashboard</span></li></ol></nav>');
check('content class: queue list', cdr_content_class('/scp/tickets.php', false, false), 'cdr cdr-tickets');
check('content class: ticket view', cdr_content_class('/scp/tickets.php', true, false), 'cdr cdr-tickets cdr-ticket');
check('content class: sanitised', cdr_content_class('/scp/a"b<c>.php', false, true), 'cdr cdr-abc cdr-is-admin');

// ---------- admin mode ----------
define('ADMINPAGE', true);
$_SERVER['SCRIPT_NAME'] = '/scp/settings.php';
$_GET = $_REQUEST = array('t' => 'tickets');
$h = cdr_rail(admin_nav('settings', 3), $admin);
check('admin: Administration segment current', (bool) preg_match('#<a [^>]*href="/scp/admin.php"[^>]*aria-current="true"#', $h), true);
check('admin: Agent segment not current', (bool) preg_match('#<a [^>]*href="/scp/index.php"[^>]*aria-current#', $h), false);
check('admin: one group per tab', substr_count($h, 'class="group"'), 5);
preg_match_all('#<p class="group-title">([^<]*)</p>#', $h, $m);
check('admin: group titles', $m[1], array('Dashboard', 'Settings', 'Manage', 'Emails', 'Agents'));
check('admin: hrefs prefixed', strpos($h, 'href="/scp/settings.php?t=pages"') !== false
    && strpos($h, 'href="/scp/emailsettings.php"') !== false, true);
check('admin: droponly skipped', strpos($h, '/scp/x.php'), false);
check('admin: no .queues', strpos($h, 'class="queues"'), false);
check('admin: settings.php?t=tickets marks only that item', current_labels($h), array('Tickets'));

$_GET = $_REQUEST = array('t' => 'pages');
$h = cdr_rail(admin_nav('settings', 1), $admin);
check('admin: settings.php?t=pages', current_labels($h), array('Company'));

$_GET = $_REQUEST = array('t' => 'tickets');
$h = cdr_rail(admin_nav('settings'), $admin);
check('admin: settings.php relies on getActiveMenu() only', current_labels($h), array());

$_SERVER['SCRIPT_NAME'] = '/scp/emailsettings.php';
$_GET = $_REQUEST = array();
$h = cdr_rail(admin_nav('emails'), $admin);
check('admin: emailsettings.php is not emails.php', current_labels($h), array('Settings'));

$_SERVER['SCRIPT_NAME'] = '/scp/emails.php';
$_GET = $_REQUEST = array('id' => '2');
$h = cdr_rail(admin_nav('emails'), $admin);
check('admin: emails.php?id=2 marks Emails only', current_labels($h), array('Emails'));

$_SERVER['SCRIPT_NAME'] = '/scp/staff.php';
$_GET = $_REQUEST = array();
$h = cdr_rail(admin_nav('staff'), $admin);
check('admin: other groups never marked', current_labels($h), array('Agents'));
check('admin: no duplicate ids', dup_ids($h), array());

$h = cdr_rail(admin_nav('emails'), $agent);
check('admin page, non-admin staff: no mode switch', strpos($h, 'class="mode"'), false);

check('crumbs: admin settings', cdr_crumbs(admin_nav('settings', 3), null, true),
    '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="/scp/admin.php">Administration</a></li>'
    . '<li><a href="/scp/settings.php">Settings</a></li><li><span aria-current="page">Tickets</span></li></ol></nav>');

// Real AdminNav on teams.php: getActiveMenu() is 0 (or -1); the item is found by
// script match, and the rail and the crumbs agree (one shared resolver).
$_SERVER['SCRIPT_NAME'] = '/scp/teams.php';
$_GET = $_REQUEST = array();
foreach (array(0, -1, null) as $am) {
    $nav = admin_nav('staff', $am);
    check("teams.php (activeMenu " . var_export($am, true) . "): rail", current_labels(cdr_rail($nav, $admin)), array('Teams'));
    check("teams.php (activeMenu " . var_export($am, true) . "): crumbs", cdr_crumbs($nav, null, true),
        '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="/scp/admin.php">Administration</a></li>'
        . '<li><a href="/scp/staff.php">Agents</a></li><li><span aria-current="page">Teams</span></li></ol></nav>');
}
// settings.php keeps relying on getActiveMenu() only, in the crumbs too.
$_SERVER['SCRIPT_NAME'] = '/scp/settings.php';
$_GET = $_REQUEST = array('t' => 'tickets');
check('crumbs: settings.php without active menu', cdr_crumbs(admin_nav('settings'), null, true),
    '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="/scp/admin.php">Administration</a></li>'
    . '<li><span aria-current="page">Settings</span></li></ol></nav>');
// Real StaffNav on orgs.php (users tab, no active menu): "Users / Organizations".
$_SERVER['SCRIPT_NAME'] = '/scp/orgs.php';
$_GET = $_REQUEST = array();
check('crumbs: orgs.php by script match', cdr_crumbs(new StubNav(agent_tabs(), agent_nav()->submenus, 'users', 0)),
    '<nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="/scp/users.php">Users</a></li>'
    . '<li><span aria-current="page">Organizations</span></li></ol></nav>');

check('no PHP notice or warning', $php_errors, array());

echo $fails ? "\n$fails FAILED\n" : "\nall ok\n";
exit($fails ? 1 : 0);
