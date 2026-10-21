<?php
// Integration test harness (test only): signed webhooks sent over HTTP to the
// real osTicket + plugin + MariaDB, state checked with SQL.
require '/tmp/boot.php';
$secret = getenv('STRIPE_SYNC_WEBHOOK_SECRET');
$url = 'http://127.0.0.1:8001/api/stripe-sync/webhook';
$fails = 0; $n = 0;
function ok($c, $l) { global $fails, $n; $n++; if (!$c) { $fails++; echo "FAIL: $l\n"; } else echo "ok:   $l\n"; }
function q($sql) { $r = db_query($sql); $o = array(); while ($r && ($row = db_fetch_array($r))) $o[] = $row; return $o; }
function one($sql) { $r = q($sql); return $r ? array_values($r[0])[0] : null; }
function post($body, $sig='auto', $ts=null) {
    global $secret, $url;
    $ts = $ts ?? time();
    $h = array('Content-Type: application/json');
    if ($sig === 'auto') $sig = "t=$ts,v1=" . hash_hmac('sha256', "$ts.$body", $secret);
    if ($sig !== null) $h[] = "Stripe-Signature: $sig";
    $c = curl_init($url);
    curl_setopt_array($c, array(CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 60));
    $out = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return array($code, trim((string) $out));
}
function ev($id, $type, $obj, $created=null, $live=true) {
    return json_encode(array('id' => $id, 'object' => 'event', 'type' => $type, 'created' => $created ?? time(), 'livemode' => $live, 'data' => array('object' => $obj)));
}
const SUB_START = 1690000000;       // start_date of every test subscription (first start)
function sub($id, $cus, $status, $price='price_prio', $extra=array()) {
    return $extra + array('id' => $id, 'object' => 'subscription', 'customer' => $cus, 'status' => $status,
        'start_date' => SUB_START,
        'items' => array('data' => array(array('price' => array('id' => $price, 'product' => 'prod_x')))));
}
// What the other modules read: the value of the `sub:<id>` row, or null.
function subRow($id) { return one("SELECT `value` FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key`=".db_input("sub:$id")); }
function subUpdated($id) { return one("SELECT `updated` FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key`=".db_input("sub:$id")); }
function userId($email) { return one("SELECT user_id FROM ".USER_EMAIL_TABLE." WHERE address=".db_input($email)); }
function checkout($cus, $email, $name='Bea Buyer') {
    return array('object' => 'checkout.session', 'customer' => $cus, 'payment_status' => 'paid',
        'customer_details' => array('email' => $email, 'name' => $name));
}
function mails() { return glob('/tmp/mail-*.eml'); }
function userOrg($email) { return one("SELECT o.name FROM ".USER_TABLE." u JOIN ".USER_EMAIL_TABLE." e ON e.user_id=u.id LEFT JOIN ".ORGANIZATION_TABLE." o ON o.id=u.org_id WHERE e.address=".db_input($email)); }

$now = time();

// 1. signature checks
list($c) = post(ev('evt_i001', 'customer.created', array('id' => 'cus_I1')), null);
ok($c == 400, "no signature header -> 400 (got $c)");
list($c) = post(ev('evt_i001', 'customer.created', array('id' => 'cus_I1')), 't=1,v1=' . str_repeat('a', 64));
ok($c == 400, "bad signature -> 400 (got $c)");
$b = ev('evt_i001', 'customer.created', array('id' => 'cus_I1'));
list($c) = post($b, 'auto', $now - 3600);
ok($c == 400, "valid HMAC but 1h old timestamp -> 400 (got $c)");
list($c, $t) = post($b);
ok($c == 200 && $t == 'ignored', "signed, unhandled event type -> 200 ignored (got $c $t)");
$r = curl_init('http://127.0.0.1:8001/api/stripe-sync/webhook'); curl_setopt_array($r, array(CURLOPT_RETURNTRANSFER => 1)); curl_exec($r);
ok(curl_getinfo($r, CURLINFO_HTTP_CODE) != 200, 'GET on the webhook route is not served (got '.curl_getinfo($r, CURLINFO_HTTP_CODE).')');
list($c) = post(ev('evt_i002', 'x', array('a' => 1), null, false));
ok($c == 200, "test-mode event accepted here because accept_test=1 ($c)");

// 2. purchase -> account + invitation
$before = count(mails());
list($c, $t) = post(ev('evt_i010', 'checkout.session.completed', checkout('cus_I1', 'bea.buyer@example.com')));
ok($c == 200 && $t == 'account', "checkout.session.completed -> 200 account (got $c $t)");
ok(one("SELECT COUNT(*) FROM ".USER_TABLE." u JOIN ".USER_EMAIL_TABLE." e ON e.user_id=u.id WHERE e.address='bea.buyer@example.com'") == 1, 'osTicket user created');
$acct = q("SELECT a.* FROM ".USER_ACCOUNT_TABLE." a JOIN ".USER_EMAIL_TABLE." e ON e.user_id=a.user_id WHERE e.address='bea.buyer@example.com'");
ok(count($acct) == 1 && ($acct[0]['status'] & 1) == 0 && !$acct[0]['passwd'], 'portal account exists, not confirmed, no password');
ok(count(mails()) == $before + 1, 'one invitation e-mail sent');
$eml = file_get_contents(mails()[count(mails()) - 1]);
ok(preg_match('~pwreset\.php\?token=([A-Za-z0-9_=]+)~', $eml, $m) === 1, 'e-mail contains the confirmation link');
$token = $m[1] ?? '';
ok(strpos($eml, 'bea.buyer@example.com') !== false, 'e-mail addressed to the customer');

// 3. replay
list($c, $t) = post(ev('evt_i010', 'checkout.session.completed', checkout('cus_I1', 'bea.buyer@example.com')));
ok($c == 200 && $t == 'duplicate' && count(mails()) == $before + 1, "same event id again -> duplicate, no second e-mail (got $t)");

// 4. confirmation link works (portal access)
$ch = curl_init("http://127.0.0.1:8001/pwreset.php?token=$token");
curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_HEADER => 1, CURLOPT_COOKIEJAR => '/tmp/cj-client', CURLOPT_COOKIEFILE => '/tmp/cj-client'));
$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
preg_match("~^Location: (.*)$~mi", $resp, $lm); ok($code == 302 && stripos($resp, 'account.php?confirmed') !== false, "confirmation link logs the customer in and redirects to account.php?confirmed (got $code ".trim($lm[1] ?? '').")");
ok((one("SELECT a.status FROM ".USER_ACCOUNT_TABLE." a JOIN ".USER_EMAIL_TABLE." e ON e.user_id=a.user_id WHERE e.address='bea.buyer@example.com'") & 1) == 1, 'account is now confirmed');

// 5. Priority
list($c, $t) = post(ev('evt_i020', 'customer.subscription.created', sub('sub_I1', 'cus_I1', 'active'), $now));
ok($c == 200 && $t == 'ok', "subscription.created (Priority price) -> ok (got $c $t)");
ok(userOrg('bea.buyer@example.com') == 'Priority', 'user is in the Priority organization');
ok(one("SELECT status FROM ".ORGANIZATION_TABLE." WHERE name='Priority'") == 0, 'Priority org created with sharing disabled (status 0)');
$beaId = userId('bea.buyer@example.com');
ok(subRow('sub_I1') === "$beaId|".SUB_START."||active", 'row sub:sub_I1 = <user_id>|<start_date>||active (got '.var_export(subRow('sub_I1'), true).')');
ok(one("SELECT COUNT(*) FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key` LIKE 'sub:%'") == 1, 'exactly one sub: row so far');

// 6. ticket filter routes on the organization (API ticket creation as the customer)
$orgField = one("SELECT ff.id FROM ".FORM_FIELD_TABLE." ff JOIN ".FORM_SEC_TABLE." f ON f.id=ff.form_id WHERE f.type='O' AND ff.name='name'");
db_query("INSERT INTO ".SLA_TABLE." (flags, grace_period, name, notes, created, updated) VALUES (1, 4, 'Priority 4h', '', NOW(), NOW())");
$sla = db_insert_id();
db_query("INSERT INTO ".FILTER_TABLE." (execorder, isactive, flags, status, match_all_rules, stop_onmatch, target, email_id, name, notes, created, updated) VALUES (1,1,0,0,1,0,'Any',0,'Priority org','',NOW(),NOW())");
$fid = db_insert_id();
db_query("INSERT INTO ".FILTER_RULE_TABLE." (filter_id, what, how, val, isactive, notes, created, updated) VALUES ($fid, ".db_input("field.$orgField").", 'equal', 'Priority', 1, '', NOW(), NOW())");
db_query("INSERT INTO ".FILTER_ACTION_TABLE." (filter_id, sort, type, configuration, updated) VALUES ($fid, 1, 'sla', ".db_input(json_encode(array('sla_id' => (string) $sla))).", NOW())");
$key = bin2hex(random_bytes(16));
db_query("INSERT INTO ".API_KEY_TABLE." (isactive, can_create_tickets, can_exec_cron, ipaddr, apikey, notes, created, updated) VALUES (1,1,0,'127.0.0.1',".db_input($key).",'int',NOW(),NOW())");
function apiTicket($email, $subject) {
    global $key;
    $c = curl_init('http://127.0.0.1:8001/api/tickets.json');
    curl_setopt_array($c, array(CURLOPT_POST => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => array('X-API-Key: '.$key, 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode(array('alert' => false, 'autorespond' => false, 'source' => 'API', 'name' => 'Bea Buyer', 'email' => $email, 'subject' => $subject, 'message' => 'hello'))));
    $out = curl_exec($c); return array(curl_getinfo($c, CURLINFO_HTTP_CODE), trim($out));
}
list($c, $num) = apiTicket('bea.buyer@example.com', 'Priority customer ticket');
ok($c == 201, "ticket created through the API as the Priority customer (got $c $num)");
ok(one("SELECT sla_id FROM ".TICKET_TABLE." WHERE number=".db_input($num)) == $sla, 'filter on "Organization / Name = Priority" assigned the 4h SLA');
list($c, $num2) = apiTicket('someone.else@example.com', 'Non priority ticket');
ok($c == 201 && one("SELECT sla_id FROM ".TICKET_TABLE." WHERE number=".db_input($num2)) != $sla, 'other customer does not get the Priority SLA');

// 7. renewal keeps Priority and the start date; past_due removes Priority, active gives it back;
//    payment_failed only logs
sleep(1);
$upd = subUpdated('sub_I1');
list($c, $t) = post(ev('evt_i029', 'customer.subscription.updated',
    sub('sub_I1', 'cus_I1', 'active', 'price_prio', array('current_period_start' => SUB_START + 2592000, 'current_period_end' => SUB_START + 5184000)), $now + 5));
ok($c == 200 && $t == 'ok' && userOrg('bea.buyer@example.com') == 'Priority', 'renewal keeps Priority');
ok(subRow('sub_I1') === "$beaId|".SUB_START."||active", 'renewal: start_date unchanged in the sub: row');
ok(subUpdated('sub_I1') === $upd, 'renewal: row not rewritten (same value)');
list($c, $t) = post(ev('evt_i029', 'customer.subscription.updated', sub('sub_I1', 'cus_I1', 'active'), $now + 5));
ok($t == 'duplicate' && subRow('sub_I1') === "$beaId|".SUB_START."||active", 'replayed event id: duplicate, row unchanged');
list($c, $t) = post(ev('evt_i029b', 'customer.subscription.updated', sub('sub_I1', 'cus_I1', 'active'), $now + 6));
ok($c == 200 && subRow('sub_I1') === "$beaId|".SUB_START."||active" && subUpdated('sub_I1') === $upd
    && one("SELECT COUNT(*) FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key` LIKE 'sub:%'") == 1,
    'same content under another event id: idempotent (same value, same row, untouched)');

list($c, $t) = post(ev('evt_i030', 'customer.subscription.updated', sub('sub_I1', 'cus_I1', 'past_due'), $now + 10));
ok($c == 200 && $t == 'ok', "subscription.updated past_due -> ok (got $c $t)");
ok(userOrg('bea.buyer@example.com') === null, 'past_due removes Priority (back to no organization)');
ok(subRow('sub_I1') === "$beaId|".SUB_START."||past_due", 'past_due: row updated, not ended, same start_date');
list($c, $numPd) = apiTicket('bea.buyer@example.com', 'Ticket while past_due');
ok($c == 201 && one("SELECT sla_id FROM ".TICKET_TABLE." WHERE number=".db_input($numPd)) != $sla, 'ticket opened while past_due: no Priority SLA');
list($c, $t) = post(ev('evt_i031', 'invoice.payment_failed', array('id' => 'in_I1', 'customer' => 'cus_I1'), $now + 11));
ok($c == 200 && $t == 'logged', "invoice.payment_failed -> logged ($t)");
list($c, $t) = post(ev('evt_i033', 'customer.subscription.updated', sub('sub_I1', 'cus_I1', 'active'), $now + 20));
ok($c == 200 && $t == 'ok' && userOrg('bea.buyer@example.com') == 'Priority', 'payment received, subscription active again: Priority granted again');
ok(subRow('sub_I1') === "$beaId|".SUB_START."||active", 'active again: row back to active, same start_date');
list($c, $numBack) = apiTicket('bea.buyer@example.com', 'Ticket after payment');
ok($c == 201 && one("SELECT sla_id FROM ".TICKET_TABLE." WHERE number=".db_input($numBack)) == $sla, 'ticket opened after the payment: Priority SLA again');

// 8. out of order: an older 'created' delivered late is ignored
list($c, $t) = post(ev('evt_i032', 'customer.subscription.updated', sub('sub_I1', 'cus_I1', 'trialing'), $now - 500));
ok($t == 'stale', "event older than the known state ignored ($t)");

// 9. end of subscription -> back to included support
list($c, $t) = post(ev('evt_i040', 'customer.subscription.deleted',
    sub('sub_I1', 'cus_I1', 'canceled', 'price_prio', array('canceled_at' => $now + 90, 'ended_at' => $now + 100)), $now + 100));
ok($c == 200 && $t == 'ok', "subscription.deleted -> ok ($t)");
ok(subRow('sub_I1') === "$beaId|".SUB_START."|".($now + 100)."|canceled", 'cancellation: row = <user_id>|<start_date>|<ended_at>|canceled (got '.var_export(subRow('sub_I1'), true).')');
ok(userOrg('bea.buyer@example.com') === null, 'user left the Priority organization');
list($c, $num3) = apiTicket('bea.buyer@example.com', 'After cancellation');
ok($c == 201 && one("SELECT sla_id FROM ".TICKET_TABLE." WHERE number=".db_input($num3)) != $sla, 'new ticket after cancellation: no Priority SLA');
ok(one("SELECT sla_id FROM ".TICKET_TABLE." WHERE number=".db_input($num)) == $sla, 'ticket opened while Priority keeps its SLA');
list($c, $t) = post(ev('evt_i041', 'customer.subscription.updated', sub('sub_I1', 'cus_I1', 'active'), $now + 200));
ok($t == 'stale' && userOrg('bea.buyer@example.com') === null, 'canceled subscription never comes back');
ok(subRow('sub_I1') === "$beaId|".SUB_START."|".($now + 100)."|canceled", 'canceled subscription: row unchanged by a late event');

// 9b. a subscription that is not Priority writes nothing (neither created nor deleted)
list($c, $t) = post(ev('evt_i045', 'customer.subscription.created', sub('sub_NP1', 'cus_I1', 'active', 'price_license'), $now + 300));
ok($c == 200 && $t == 'ok' && subRow('sub_NP1') === null, 'non Priority subscription: no sub: row');
list($c, $t) = post(ev('evt_i046', 'customer.subscription.deleted', sub('sub_NP1', 'cus_I1', 'canceled', 'price_license', array('ended_at' => $now + 400)), $now + 400));
ok($c == 200 && subRow('sub_NP1') === null && userOrg('bea.buyer@example.com') === null, 'non Priority subscription ended: still no row, no organization change');

// 10. previous org restored (company org by e-mail domain)
db_query("INSERT INTO ".ORGANIZATION_TABLE." (name, manager, status, domain, created, updated) VALUES ('Acme Corp', '', 8, 'acme.test', NOW(), NOW())");
$acme = db_insert_id();
list($c, $t) = post(ev('evt_i050', 'checkout.session.completed', checkout('cus_I2', 'carl@acme.test', 'Carl C')));
ok($c == 200 && userOrg('carl@acme.test') == 'Acme Corp', 'user created from a ".test" address; company org assigned by e-mail domain (bypasses the portal domain check)');
post(ev('evt_i051', 'customer.subscription.created', sub('sub_I2', 'cus_I2', 'active'), $now));
ok(userOrg('carl@acme.test') == 'Priority', 'moved to Priority');
post(ev('evt_i052', 'customer.subscription.deleted', sub('sub_I2', 'cus_I2', 'canceled'), $now + 5));
ok(userOrg('carl@acme.test') == 'Acme Corp', 'company organization restored at the end');

// 11. subscription before the purchase event: deferred then applied
list($c, $t) = post(ev('evt_i060', 'customer.subscription.created', sub('sub_I3', 'cus_I3', 'active'), $now));
ok($t == 'deferred', "subscription for an unknown customer -> deferred ($t)");
ok(subRow('sub_I3') === null, 'deferred subscription: no row yet (user unknown)');
post(ev('evt_i061', 'invoice.paid', array('customer' => 'cus_I3', 'customer_email' => 'dora@example.com', 'customer_name' => 'Dora D', 'status' => 'paid')));
ok(userOrg('dora@example.com') == 'Priority', 'deferred state applied once the customer e-mail is known (invoice.paid)');
$doraId = userId('dora@example.com');
ok(subRow('sub_I3') === "$doraId|".SUB_START."||active", 'deferred subscription: row written once the user is known');

// 11b. a very long subscription id: the key is hashed like the event keys (`key` is 64 characters max)
$long = 'sub_' . str_repeat('L', 58);
list($c, $t) = post(ev('evt_i062', 'customer.subscription.created', sub($long, 'cus_I3', 'active'), $now + 1));
$lk = 'sub:' . sha1($long);
ok($c == 200 && strlen('sub:' . $long) > 64 && one("SELECT `value` FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key`=".db_input($lk)) === "$doraId|".SUB_START."||active",
    'id too long for `key`: row stored under sub:<sha1(id)>');

// 12. unsafe Priority org configuration is refused (retry), then recovers
db_query("UPDATE ".ORGANIZATION_TABLE." SET status = 8 WHERE name='Priority'");
list($c, $t) = post(ev('evt_i070', 'checkout.session.completed', checkout('cus_I4', 'erin@example.com')));
list($c2, $t2) = post(ev('evt_i071', 'customer.subscription.created', sub('sub_I4', 'cus_I4', 'active'), $now));
ok($c2 == 503, "Priority org with ticket sharing enabled -> refused with 503 (got $c2)");
ok(userOrg('erin@example.com') === null, 'nobody was added to the unsafe organization');
db_query("UPDATE ".ORGANIZATION_TABLE." SET status = 0 WHERE name='Priority'");
list($c3, $t3) = post(ev('evt_i071', 'customer.subscription.created', sub('sub_I4', 'cus_I4', 'active'), $now));
ok($c3 == 200 && userOrg('erin@example.com') == 'Priority', 'after the admin fixes the org, the redelivered event succeeds');

// 13. a locked account is respected; no re-invitation spam
$m0 = count(mails());
post(ev('evt_i080', 'checkout.session.completed', checkout('cus_I1', 'bea.buyer@example.com')));
ok(count(mails()) == $m0, 'confirmed customer buying again: no new invitation');

// 14. nothing sensitive in the system log
$logs = q("SELECT log_type, title, log FROM ".SYSLOG_TABLE." ORDER BY log_id");
$blob = json_encode($logs);
ok(strpos($blob, 'example.com') === false && strpos($blob, 'acme.test') === false, 'system log contains no e-mail addresses');
echo "\n--- system log (".count($logs)." entries) ---\n";
foreach ($logs as $l) echo "[{$l['log_type']}] {$l['title']}: ".substr(strip_tags($l['log']), 0, 160)."\n";

echo "\n$n checks, $fails failure(s)\n";
exit($fails);