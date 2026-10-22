<?php
// Integration test harness (test only): the Priority organization is
// remembered by id (stripe-sync 0.2). Signed webhooks over HTTP to the real
// osTicket + plugin; state checked with SQL. Starts from an empty
// `stripesync` namespace and no organization named "Priority".
require '/tmp/lib.php';

$now = time();
$log0 = lastLogId();
function subscribe($n, $email, $ts) {
    post(ev("evt_o{$n}a", 'checkout.session.completed', checkout("cus_O$n", $email)));
    return post(ev("evt_o{$n}b", 'customer.subscription.created', sub("sub_O$n", "cus_O$n", 'active'), $ts));
}

// 1. creation -> id remembered
ok(rememberedOrgId() === null && orgCount('Priority') == 0, '(start) no id remembered, no Priority organization');
list($c, $t) = subscribe(1, 'olga@example.com', $now);
$p1 = (int) one("SELECT id FROM ".ORGANIZATION_TABLE." WHERE name='Priority'");
ok($c == 200 && $t == 'ok' && $p1 > 0 && userOrgId('olga@example.com') === $p1, "first Priority subscription: organization created, user attached (got $c $t)");
ok(rememberedOrgId() === $p1 && rememberedOrgName() === 'Priority', 'priority_org_id = id of the created organization (priority_org_name = configured name)');
ok(one("SELECT status FROM ".ORGANIZATION_TABLE." WHERE id=$p1") == 0, 'created with sharing disabled (status 0)');

// 2. renamed in the admin panel -> same organization, nothing created
renameOrg($p1, 'Priority Support (renamed)');
list($c, $t) = subscribe(2, 'oscar@example.com', $now);
ok($c == 200 && userOrgId('oscar@example.com') === $p1, "renamed organization: next customer attached to the same organization #$p1 (got $c $t)");
ok(orgCount('Priority') == 0 && rememberedOrgId() === $p1, 'no new "Priority" organization created, remembered id unchanged');
list($c, $t) = post(ev('evt_o1c', 'customer.subscription.deleted', sub('sub_O1', 'cus_O1', 'canceled'), $now + 10));
ok($c == 200 && userOrgId('olga@example.com') === 0, 'end of subscription with the renamed organization: user taken out (found by id)');
ok(!syslogSince($log0, 'Warning'), 'no warning so far');

// 3. unsafe organization found by id -> refused (503), then accepted once fixed
db_query("UPDATE ".ORGANIZATION_TABLE." SET status = 8 WHERE id=$p1");
list($c, $t) = subscribe(3, 'otto@example.com', $now);
ok($c == 503 && !userOrgId('otto@example.com'), "organization found by id with ticket sharing -> 503, nobody added (got $c)");
db_query("UPDATE ".ORGANIZATION_TABLE." SET status = 0, domain = 'example.net' WHERE id=$p1");
list($c, $t) = post(ev('evt_o3b', 'customer.subscription.created', sub('sub_O3', 'cus_O3', 'active'), $now));
ok($c == 503 && !userOrgId('otto@example.com'), "organization found by id with a domain mapping -> 503 (got $c)");
db_query("UPDATE ".ORGANIZATION_TABLE." SET domain = '' WHERE id=$p1");
list($c, $t) = post(ev('evt_o3b', 'customer.subscription.created', sub('sub_O3', 'cus_O3', 'active'), $now));
ok($c == 200 && userOrgId('otto@example.com') === $p1 && orgCount('Priority') == 0, "fixed by the admin: the redelivered event succeeds, same organization (got $c $t)");

// 4. deleted -> recreated, warning in the system log, new id remembered
$logD = lastLogId();
ok(deleteOrg($p1), "organization #$p1 deleted by the admin");
ok(userOrgId('oscar@example.com') === 0, '(osTicket) members of the deleted organization have no organization any more');
list($c, $t) = subscribe(4, 'oria@example.com', $now);
$p2 = rememberedOrgId();
ok($c == 200 && $p2 && $p2 != $p1 && orgName($p2) === 'Priority' && userOrgId('oria@example.com') === $p2,
    "deleted organization: recreated with the configured name, new id #$p2 remembered (got $c $t)");
$w = syslogSince($logD, 'Warning');
ok(count($w) == 1 && strpos($w[0]['title'], 'Priority organization replaced') !== false
    && strpos($w[0]['log'], "#$p1") !== false && strpos($w[0]['log'], "#$p2") !== false,
    'one warning in the system log with the old and the new id: '.json_encode($w));
list($c, $t) = subscribe(5, 'omar@example.com', $now);
ok($c == 200 && userOrgId('omar@example.com') === $p2 && count(syslogSince($logD, 'Warning')) == 1, 'next customers: same new organization, no further warning');
ok(userOrgId('oscar@example.com') === 0, 'users of the deleted organization are not moved by the plugin on their own');
list($c, $t) = post(ev('evt_o2c', 'customer.subscription.updated', sub('sub_O2', 'cus_O2', 'active'), $now + 20));
ok($c == 200 && userOrgId('oscar@example.com') === $p2, 'their next subscription event attaches them to the new organization');

// 5. installation of version 0.1 (no id remembered): found by name, id remembered
db_query("DELETE FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key` IN ('priority_org_id', 'priority_org_name')");
list($c, $t) = subscribe(6, 'opal@example.com', $now);
ok($c == 200 && userOrgId('opal@example.com') === $p2 && rememberedOrgId() === $p2 && orgCount('Priority') == 1,
    "migration: existing organization found by name and its id remembered, nothing created (got $c $t)");
renameOrg($p2, 'Priority (after migration)');
list($c, $t) = subscribe(7, 'oleg@example.com', $now);
ok($c == 200 && userOrgId('oleg@example.com') === $p2 && orgCount('Priority') == 0, 'after the migration, a rename does not lose it');
list($c, $t) = post(ev('evt_o7c', 'customer.subscription.deleted', sub('sub_O7', 'cus_O7', 'canceled'), $now + 30));
ok($c == 200 && userOrgId('oleg@example.com') === 0, 'and the end of subscription still finds it');

// 6. concurrency: no id, no organization, 6 first subscriptions at the same time -> ONE organization
db_query("DELETE FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key` IN ('priority_org_id', 'priority_org_name')");
deleteOrg($p2);
$bodies = array();
for ($i = 1; $i <= 6; $i++) {
    post(ev("evt_oc{$i}a", 'checkout.session.completed', checkout("cus_OC$i", "race$i@example.com")));
    $bodies[] = ev("evt_oc{$i}b", 'customer.subscription.created', sub("sub_OC$i", "cus_OC$i", 'active'), $now);
}
$res = postParallel($bodies);
echo "parallel first subscriptions: ".implode(' ', $res)."\n";
// Losers of a lock answer 503 and Stripe redelivers: replay those.
foreach ($res as $i => $r) if (strpos($r, '200:') !== 0) post($bodies[$i]);
$p3 = rememberedOrgId();
$orgs = array();
for ($i = 1; $i <= 6; $i++) $orgs[] = userOrgId("race$i@example.com");
ok(orgCount('Priority') == 1 && $p3 && array_unique($orgs) === array($p3), 'concurrent first subscriptions: a single organization created, all 6 users in it, its id remembered ('.json_encode($orgs).')');

// 7. nothing personal in the system log
$blob = json_encode(syslogSince($log0));
ok(strpos($blob, '@') === false && strpos($blob, 'example.') === false, 'system log: no e-mail address');
echo "\n--- system log since the start of this scenario ---\n";
foreach (syslogSince($log0) as $l) echo "[{$l['log_type']}] {$l['title']}: ".substr(strip_tags($l['log']), 0, 220)."\n";

done();
