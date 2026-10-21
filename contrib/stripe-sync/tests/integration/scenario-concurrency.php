<?php
require '/tmp/boot.php';
$secret = getenv('STRIPE_SYNC_WEBHOOK_SECRET');
$base = 'http://127.0.0.1:8001/api/stripe-sync/webhook';
$fails = 0; $n = 0;
function ok($c, $l) { global $fails, $n; $n++; if (!$c) { $fails++; echo "FAIL: $l\n"; } else echo "ok:   $l\n"; }
function q($sql) { $r = db_query($sql); $o = array(); while ($r && ($row = db_fetch_array($r))) $o[] = $row; return $o; }
function one($sql) { $r = q($sql); return $r ? array_values($r[0])[0] : null; }
function req($url, $body, $secret, $ts=null) {
    $ts = $ts ?? time(); $sig = "t=$ts,v1=" . hash_hmac('sha256', "$ts.$body", $secret);
    $c = curl_init($url);
    curl_setopt_array($c, array(CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => array("Stripe-Signature: $sig"), CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 60));
    return $c;
}
function ev($id, $type, $obj, $created=null) { return json_encode(array('id' => $id, 'type' => $type, 'created' => $created ?? time(), 'livemode' => true, 'data' => array('object' => $obj))); }
$co = function ($cus, $email) { return array('customer' => $cus, 'payment_status' => 'paid', 'customer_details' => array('email' => $email, 'name' => 'Race R')); };
@array_map('unlink', glob('/tmp/mail-*.eml'));

// (a) the same event delivered 8 times in parallel
$body = ev('evt_r001', 'checkout.session.completed', $co('cus_R1', 'race@example.com'));
$mh = curl_multi_init(); $hs = array();
for ($i = 0; $i < 8; $i++) { $h = req($base, $body, $secret); curl_multi_add_handle($mh, $h); $hs[] = $h; }
do { curl_multi_exec($mh, $run); curl_multi_select($mh); } while ($run);
$codes = array(); $texts = array();
foreach ($hs as $h) { $codes[] = curl_getinfo($h, CURLINFO_HTTP_CODE); $texts[] = trim(curl_multi_getcontent($h)); }
echo "parallel results: ".implode(',', $codes)." / ".implode(',', $texts)."\n";
ok(count(array_filter($texts, fn($t) => $t == 'account')) == 1, 'exactly one request processed the event');
ok(count(glob('/tmp/mail-*.eml')) == 1, 'exactly one invitation e-mail');
ok(one("SELECT COUNT(*) FROM ".USER_EMAIL_TABLE." WHERE address='race@example.com'") == 1, 'exactly one user');
ok(!array_diff($codes, array(200, 503)), 'losers got 200 (duplicate) or 503 (retry), never 500');

// (a2) parallel different events for the same customer (subscription + invoice.paid)
$now = time();
$sub = function ($id, $st, $ts) { return ev($id, 'customer.subscription.updated', array('id' => 'sub_R1', 'customer' => 'cus_R1', 'status' => $st, 'start_date' => 1690000000, 'items' => array('data' => array(array('price' => array('id' => 'price_prio', 'product' => 'prod_x'))))), $ts); };
$mh = curl_multi_init(); $hs = array();
foreach (array($sub('evt_r010', 'active', $now), $sub('evt_r011', 'past_due', $now + 1), $sub('evt_r012', 'active', $now + 2)) as $b) { $h = req($base, $b, $secret); curl_multi_add_handle($mh, $h); $hs[] = $h; }
do { curl_multi_exec($mh, $run); curl_multi_select($mh); } while ($run);
$out = array(); foreach ($hs as $h) $out[] = curl_getinfo($h, CURLINFO_HTTP_CODE).':'.trim(curl_multi_getcontent($h));
echo "parallel subscription events: ".implode(' ', $out)."\n";
$org = one("SELECT o.name FROM ".USER_TABLE." u JOIN ".USER_EMAIL_TABLE." e ON e.user_id=u.id LEFT JOIN ".ORGANIZATION_TABLE." o ON o.id=u.org_id WHERE e.address='race@example.com'");
ok($org == 'Priority', 'parallel events for one customer converge to Priority');
$st = json_decode(one("SELECT value FROM ".CONFIG_TABLE." WHERE namespace='stripesync' AND `key`='cus:cus_R1'"), true);
ok($st['subs']['sub_R1']['ts'] == $now + 2, 'latest event wins in the stored state');
$uidR = one("SELECT user_id FROM ".USER_EMAIL_TABLE." WHERE address='race@example.com'");
ok(one("SELECT COUNT(*) FROM ".CONFIG_TABLE." WHERE namespace='stripesync' AND `key` LIKE 'sub:%'") == 1
    && one("SELECT value FROM ".CONFIG_TABLE." WHERE namespace='stripesync' AND `key`='sub:sub_R1'") === "$uidR|1690000000||active",
    'parallel events: a single sub:sub_R1 row, converged to the latest status');

// (b) unconfirmed account whose link expired: renewal re-sends an invitation, but only after the delay
$m = count(glob('/tmp/mail-*.eml'));
req($base, '', $secret); // noop
$c = req($base, ev('evt_r020', 'invoice.paid', array('customer' => 'cus_R1', 'customer_email' => 'race@example.com', 'status' => 'paid')), $secret); curl_exec($c);
ok(count(glob('/tmp/mail-*.eml')) == $m, 'unconfirmed account, invited minutes ago: no new e-mail (throttled)');
$st['invited_at'] = time() - 7 * 3600;
db_query("UPDATE ".CONFIG_TABLE." SET value=".db_input(json_encode($st))." WHERE namespace='stripesync' AND `key`='cus:cus_R1'");
$c = req($base, ev('evt_r021', 'invoice.paid', array('customer' => 'cus_R1', 'customer_email' => 'race@example.com', 'status' => 'paid')), $secret); curl_exec($c);
ok(count(glob('/tmp/mail-*.eml')) == $m + 1, 'after the delay, a fresh confirmation link is sent');

// (c) locked account: never mailed
db_query("UPDATE ".USER_ACCOUNT_TABLE." SET status = status | 2 WHERE user_id=(SELECT user_id FROM ".USER_EMAIL_TABLE." WHERE address='race@example.com')");
$st['invited_at'] = 0; db_query("UPDATE ".CONFIG_TABLE." SET value=".db_input(json_encode($st))." WHERE namespace='stripesync' AND `key`='cus:cus_R1'");
$m = count(glob('/tmp/mail-*.eml'));
$c = req($base, ev('evt_r022', 'invoice.paid', array('customer' => 'cus_R1', 'customer_email' => 'race@example.com', 'status' => 'paid')), $secret); curl_exec($c);
ok(count(glob('/tmp/mail-*.eml')) == $m, 'account locked by an agent: no invitation');

// (d) secret not configured (second server started without the variable)
$c = req('http://127.0.0.1:8002/api/http.php/stripe-sync/webhook', ev('evt_r030', 'x', array('a' => 1)), $secret); curl_exec($c);
ok(curl_getinfo($c, CURLINFO_HTTP_CODE) == 503, 'no STRIPE_SYNC_WEBHOOK_SECRET -> 503, nothing is accepted (got '.curl_getinfo($c, CURLINFO_HTTP_CODE).')');
// (e) body too large
$c = req($base, str_repeat('a', 600000), $secret); curl_exec($c);
ok(curl_getinfo($c, CURLINFO_HTTP_CODE) == 413, 'oversized body -> 413 (got '.curl_getinfo($c, CURLINFO_HTTP_CODE).')');

echo "\n$n checks, $fails failure(s)\n"; exit($fails);