<?php
// End-to-end test of the TWO Coderise plugins together on one real
// osTicket (test only): stripe-sync (webhooks -> Priority organization,
// remembered by id) and sla_first_response (coderise_contract_level(),
// SLA plans). Signed webhooks over HTTP, tickets through the HTTP API,
// contract level read in fresh processes (probe.php = one request).
//
// Prerequisites (run.sh): both plugins copied to include/plugins/, stripe-sync
// installed and configured (setup_plugin.php), `stripesync` namespace empty,
// no organization named "Priority".
require '/tmp/lib.php';
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.sla.php';

function probe($what, $arg='') {
    $o = shell_exec('cd /tmp && php probe.php ' . escapeshellarg($what) . ' ' . escapeshellarg((string) $arg) . ' 2>&1');
    $j = json_decode(trim((string) $o), true);
    if ($j === null) echo "  (probe output: " . trim((string) $o) . ")\n";
    return $j;
}
function level($email) { $r = probe('level', $email); return is_array($r) ? $r['level'] . '/' . $r['source'] : var_export($r, true); }
function levelTicket($id) { $r = probe('level-ticket', $id); return is_array($r) ? $r['level'] . '/' . $r['source'] : var_export($r, true); }
function apiTicket($email, $subject) {
    global $apikey;
    $c = curl_init('http://127.0.0.1:8001/api/tickets.json');
    curl_setopt_array($c, array(CURLOPT_POST => 1, CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HTTPHEADER => array('X-API-Key: ' . $apikey, 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode(array('alert' => false, 'autorespond' => false, 'source' => 'API',
            'name' => 'E2E Client', 'email' => $email, 'subject' => $subject, 'message' => 'hello'))));
    $num = trim((string) curl_exec($c)); $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $row = q("SELECT ticket_id, sla_id FROM " . TICKET_TABLE . " WHERE number=" . db_input($num));
    return array($code, $row ? (int) $row[0]['ticket_id'] : 0, $row ? (int) $row[0]['sla_id'] : 0);
}
function slaId($name) { return (int) one("SELECT id FROM " . SLA_TABLE . " WHERE name=" . db_input($name)); }
// Save the SLA plugin instance as the admin form does (POST, pre_save validation).
function saveSlaInstance($contractOrg) {
    global $watch, $done;
    $p = Plugin::objects()->filter(array('install_path' => 'plugins/sla_first_response'))->one();
    $i = PluginInstance::objects()->filter(array('plugin_id' => $p->getId()))->one();
    $vars = array('name' => 'SLA premiere reponse', 'isactive' => 1, 'notes' => '',
        'sla_watch' => $watch, 'sla_done' => $done, 'contract_org' => (string) $contractOrg,
        'contract_label_priority' => 'Priority', 'contract_label_standard' => 'Standard');
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $vars;
    $errors = array();
    $ok = $i->update($vars, $errors);
    $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = array();
    return array($ok, $errors);
}

$now = time();
$log0 = lastLogId();

echo "== E0: SLA plugin installed next to stripe-sync (plans, filter, API key)\n";
$watch = slaId('Priority 4h');
if (!$watch) {
    db_query("INSERT INTO " . SLA_TABLE . " (flags, grace_period, name, notes, created, updated) VALUES (1, 4, 'Priority 4h', '', NOW(), NOW())");
    $watch = db_insert_id();
}
$done = slaId('Priority 4h - 1re reponse faite');
if (!$done) {
    // Disabled, not transient: required by the SLA plugin.
    db_query("INSERT INTO " . SLA_TABLE . " (flags, grace_period, name, notes, created, updated) VALUES (0, 4, 'Priority 4h - 1re reponse faite', '', NOW(), NOW())");
    $done = db_insert_id();
}
$orgField = one("SELECT ff.id FROM " . FORM_FIELD_TABLE . " ff JOIN " . FORM_SEC_TABLE . " f ON f.id=ff.form_id WHERE f.type='O' AND ff.name='name'");
$fid = (int) one("SELECT id FROM " . FILTER_TABLE . " WHERE name='Priority org'");
if (!$fid) {
    db_query("INSERT INTO " . FILTER_TABLE . " (execorder, isactive, flags, status, match_all_rules, stop_onmatch, target, email_id, name, notes, created, updated) VALUES (1,1,0,0,1,0,'Any',0,'Priority org','',NOW(),NOW())");
    $fid = db_insert_id();
    db_query("INSERT INTO " . FILTER_RULE_TABLE . " (filter_id, what, how, val, isactive, notes, created, updated) VALUES ($fid, " . db_input("field.$orgField") . ", 'equal', 'Priority', 1, '', NOW(), NOW())");
    db_query("INSERT INTO " . FILTER_ACTION_TABLE . " (filter_id, sort, type, configuration, updated) VALUES ($fid, 1, 'sla', " . db_input(json_encode(array('sla_id' => (string) $watch))) . ", NOW())");
}
db_query("UPDATE " . FILTER_RULE_TABLE . " SET val='Priority' WHERE filter_id=$fid");
db_query("UPDATE " . FILTER_ACTION_TABLE . " SET configuration=" . db_input(json_encode(array('sla_id' => (string) $watch))) . " WHERE filter_id=$fid AND type='sla'");
$apikey = bin2hex(random_bytes(16));
db_query("INSERT INTO " . API_KEY_TABLE . " (isactive, can_create_tickets, can_exec_cron, ipaddr, apikey, notes, created, updated) VALUES (1,1,0,'127.0.0.1'," . db_input($apikey) . ",'e2e',NOW(),NOW())");

$sp = $ost->plugins->install('plugins/sla_first_response');
ok((bool) $sp, 'SLA plugin installed');
$sp = PluginManager::lookup($sp->getId());
$sp->isactive = 1; $sp->save();
$errors = array();
$si = $sp->addInstance(array('name' => 'SLA premiere reponse', 'isactive' => 1, 'notes' => '',
    'sla_watch' => $watch, 'sla_done' => $done, 'contract_org' => '',
    'contract_label_priority' => 'Priority', 'contract_label_standard' => 'Standard'), $errors);
ok($si && !$errors, 'SLA plugin instance created, contract_org empty (as before any configuration) ' . json_encode($errors));
$f = probe('sla-form');
ok($f['proposal'] === 'Priority' && strpos($f['hint'], 'Proposition (configuration du plugin Stripe) : « Priority »') !== false,
    'before any Stripe event: SLA plugin proposes the configured name of stripe-sync (no id remembered yet) ' . json_encode($f));

// Eve belongs to her company organization (mapped by e-mail domain).
db_query("INSERT INTO " . ORGANIZATION_TABLE . " (name, manager, status, domain, created, updated) VALUES ('Eve Corp', '', 0, 'evecorp.test', NOW(), NOW())");
$eveCorp = db_insert_id();

echo "== E1: signed subscription event -> Priority organization -> contract level priority\n";
list($c, $t) = post(ev('evt_e001', 'checkout.session.completed', checkout('cus_E1', 'eve@evecorp.test', 'Eve E')));
ok($c == 200 && userOrgId('eve@evecorp.test') === (int) $eveCorp, "purchase: user created in her company organization (got $c $t)");
list($c, $t) = post(ev('evt_e002', 'customer.subscription.created', sub('sub_E1', 'cus_E1', 'active'), $now));
$P = rememberedOrgId();
ok($c == 200 && $P && userOrgId('eve@evecorp.test') === $P && orgName($P) === 'Priority',
    "subscription: user attached to the Priority organization #$P, id remembered (got $c $t)");
$f = probe('sla-form');
ok($f['proposal'] === (string) $P && $f['new_instance_defaults'] == array('contract_org' => (string) $P)
    && strpos($f['hint'], "Proposition (organisation utilisée par le plugin Stripe) : « Priority » (n° $P)") !== false,
    'SLA plugin now proposes the id remembered by stripe-sync ' . json_encode($f));
ok(level('eve@evecorp.test') === 'standard/default', 'SLA plugin not configured yet: standard/default (got ' . level('eve@evecorp.test') . ')');
list($ok, $errors) = saveSlaInstance($f['proposal']);
$f = probe('sla-form');
ok($ok && $f['stored'] === (string) $P && strpos($f['hint'], 'ATTENTION') === false,
    'admin saves the proposal: contract_org = ' . $f['stored'] . ', no warning ' . json_encode($errors));
ok(level('eve@evecorp.test') === 'priority/organisation', 'coderise_contract_level($user) = priority (got ' . level('eve@evecorp.test') . ')');

echo "== E2: ticket created through the API -> Priority 4h through the filter, level of the ticket\n";
list($c, $tid, $sla) = apiTicket('eve@evecorp.test', 'E2E priority ticket');
ok($c == 201 && $sla === $watch, "API ticket of the Priority customer: SLA plan Priority 4h (got $c, sla $sla, expected $watch)");
ok(levelTicket($tid) === 'priority/organisation', 'coderise_contract_level($ticket) = priority (got ' . levelTicket($tid) . ')');
list($c, $tidOther, $slaOther) = apiTicket('walter@example.com', 'E2E other customer');
ok($c == 201 && $slaOther !== $watch && levelTicket($tidOther) === 'standard/organisation', 'another customer: no Priority 4h, level standard');

echo "== E3: Priority organization renamed in the admin panel -> the level stays right\n";
renameOrg($P, 'Priority Clients');
ok(level('eve@evecorp.test') === 'priority/organisation' && levelTicket($tid) === 'priority/organisation',
    'after the rename: user and ticket still priority (got ' . level('eve@evecorp.test') . ', ' . levelTicket($tid) . ')');
list($c, $t) = post(ev('evt_e010', 'checkout.session.completed', checkout('cus_E2', 'fred@example.com', 'Fred F')));
list($c, $t) = post(ev('evt_e011', 'customer.subscription.created', sub('sub_E2', 'cus_E2', 'active'), $now));
ok($c == 200 && userOrgId('fred@example.com') === $P && orgCount('Priority') == 0 && rememberedOrgId() === $P,
    "new subscriber after the rename: same organization #$P, no new \"Priority\" created (got $c $t)");
ok(level('fred@example.com') === 'priority/organisation', 'new subscriber: priority (got ' . level('fred@example.com') . ')');
$f = probe('sla-form');
ok(strpos($f['hint'], 'ATTENTION') === false && strpos($f['hint'], "« Priority Clients » (n° $P)") !== false, 'SLA form: no warning, shows the new name');
// Known limit: the ticket filter matches the organization NAME.
list($c, $tidF, $slaF) = apiTicket('fred@example.com', 'E2E after rename, filter not updated');
ok($c == 201 && $slaF !== $watch, 'LIMIT (documented): the ticket filter "Organization / Name = Priority" no longer matches after the rename');
db_query("UPDATE " . FILTER_RULE_TABLE . " SET val='Priority Clients' WHERE filter_id=$fid");
list($c, $tidF2, $slaF2) = apiTicket('fred@example.com', 'E2E after rename, filter updated');
ok($c == 201 && $slaF2 === $watch, 'once the admin updates the filter value: Priority 4h again');

echo "== E4: end of subscription -> original organization restored -> standard\n";
list($c, $t) = post(ev('evt_e020', 'customer.subscription.deleted', sub('sub_E1', 'cus_E1', 'canceled', 'price_prio', array('ended_at' => $now + 50)), $now + 50));
ok($c == 200 && userOrgId('eve@evecorp.test') === (int) $eveCorp, "end of subscription (renamed organization found by id): company organization restored (got $c $t)");
$r = probe('level', 'eve@evecorp.test');
ok($r['level'] === 'standard' && $r['org_name'] === 'Eve Corp', 'coderise_contract_level($user) = standard, organization Eve Corp ' . json_encode($r));
ok(levelTicket($tid) === 'standard/organisation', 'her ticket now shows standard (current level of the owner, documented)');
ok((int) one("SELECT sla_id FROM " . TICKET_TABLE . " WHERE ticket_id=$tid") === $watch, 'the ticket keeps its Priority 4h plan (set at creation)');

echo "== E5: Priority organization deleted -> warning, recreated, gap reported in the SLA form\n";
$logD = lastLogId();
ok(deleteOrg($P), "organization #$P deleted by the admin");
ok(level('fred@example.com') === 'standard/default', 'members of the deleted organization: standard/default (configured organization gone)');
$f = probe('sla-form');
ok(strpos($f['hint'], 'n\'existe plus') !== false, 'SLA form: "the configured organization no longer exists" ' . $f['hint']);
list($c, $t) = post(ev('evt_e030', 'checkout.session.completed', checkout('cus_E3', 'gina@example.com', 'Gina G')));
list($c, $t) = post(ev('evt_e031', 'customer.subscription.created', sub('sub_E3', 'cus_E3', 'active'), $now));
$P2 = rememberedOrgId();
ok($c == 200 && $P2 && $P2 != $P && userOrgId('gina@example.com') === $P2 && orgName($P2) === 'Priority',
    "next subscriber: organization recreated (#$P2), new id remembered (got $c $t)");
$w = syslogSince($logD, 'Warning');
ok(count($w) == 1 && strpos($w[0]['log'], "#$P") !== false && strpos($w[0]['log'], "#$P2") !== false, 'system log warning with both ids ' . json_encode($w));
$f = probe('sla-form');
ok(strpos($f['hint'], "ATTENTION : le plugin Stripe rattache maintenant les clients Priority à l'organisation « Priority » (n° $P2)") !== false
    && strpos($f['hint'], "saisir $P2 dans ce champ") !== false, 'SLA form: gap reported with the recommended action ' . $f['hint']);
ok($f['stored'] === (string) $P, 'the SLA configuration is not changed automatically');
ok(level('gina@example.com') === 'standard/default', 'until the admin acts, the new subscriber shows standard (this is what the warning is for)');
list($ok, $errors) = saveSlaInstance($P2);
$f = probe('sla-form');
ok($ok && strpos($f['hint'], 'ATTENTION') === false, 'admin follows the recommended action: warning gone');
ok(level('gina@example.com') === 'priority/organisation', 'new subscriber: priority (got ' . level('gina@example.com') . ')');
list($c, $t) = post(ev('evt_e040', 'customer.subscription.updated', sub('sub_E2', 'cus_E2', 'active'), $now + 60));
ok($c == 200 && userOrgId('fred@example.com') === $P2 && level('fred@example.com') === 'priority/organisation',
    'member of the deleted organization: back to priority at his next subscription event');

echo "== E6: system log\n";
$blob = json_encode(syslogSince($log0));
ok(strpos($blob, '@') === false && strpos($blob, 'evecorp') === false && strpos($blob, 'example.') === false, 'no e-mail address in the system log');
foreach (syslogSince($log0) as $l) echo "[{$l['log_type']}] {$l['title']}: " . substr(strip_tags($l['log']), 0, 220) . "\n";

done();
