<?php
// Integration test harness only: helpers shared by scenario-org.php and
// scenario-e2e.php (signed webhooks over HTTP, SQL checks).
require '/tmp/boot.php';
require_once INCLUDE_DIR . 'class.organization.php';

$secret = getenv('STRIPE_SYNC_WEBHOOK_SECRET');
$url = 'http://127.0.0.1:8001/api/stripe-sync/webhook';
$fails = 0; $n = 0;
function ok($c, $l) { global $fails, $n; $n++; if (!$c) { $fails++; echo "FAIL: $l\n"; } else echo "ok:   $l\n"; }
function q($sql) { $r = db_query($sql); $o = array(); while ($r && ($row = db_fetch_array($r))) $o[] = $row; return $o; }
function one($sql) { $r = q($sql); return $r ? array_values($r[0])[0] : null; }
function post($body) {
    global $secret, $url;
    $ts = time();
    $c = curl_init($url);
    curl_setopt_array($c, array(CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json', "Stripe-Signature: t=$ts,v1=" . hash_hmac('sha256', "$ts.$body", $secret))));
    $out = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return array($code, trim((string) $out));
}
// Same, several bodies at once (curl_multi): array of "code:text".
function postParallel(array $bodies) {
    global $secret, $url;
    $mh = curl_multi_init(); $hs = array();
    foreach ($bodies as $body) {
        $ts = time();
        $h = curl_init($url);
        curl_setopt_array($h, array(CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array("Stripe-Signature: t=$ts,v1=" . hash_hmac('sha256', "$ts.$body", $secret))));
        curl_multi_add_handle($mh, $h); $hs[] = $h;
    }
    $run = 0;
    do { curl_multi_exec($mh, $run); curl_multi_select($mh); } while ($run);
    $out = array();
    foreach ($hs as $h) $out[] = curl_getinfo($h, CURLINFO_HTTP_CODE) . ':' . trim(curl_multi_getcontent($h));
    return $out;
}
function ev($id, $type, $obj, $created=null) {
    return json_encode(array('id' => $id, 'object' => 'event', 'type' => $type, 'created' => $created ?? time(), 'livemode' => true, 'data' => array('object' => $obj)));
}
function sub($id, $cus, $status, $price='price_prio', $extra=array()) {
    return $extra + array('id' => $id, 'object' => 'subscription', 'customer' => $cus, 'status' => $status,
        'start_date' => 1690000000,
        'items' => array('data' => array(array('price' => array('id' => $price, 'product' => 'prod_x')))));
}
function checkout($cus, $email, $name='Test Client') {
    return array('object' => 'checkout.session', 'customer' => $cus, 'payment_status' => 'paid',
        'customer_details' => array('email' => $email, 'name' => $name));
}
function userId($email) { return one("SELECT user_id FROM ".USER_EMAIL_TABLE." WHERE address=".db_input($email)); }
function userOrgId($email) {
    $v = one("SELECT u.org_id FROM ".USER_TABLE." u JOIN ".USER_EMAIL_TABLE." e ON e.user_id=u.id WHERE e.address=".db_input($email));
    return $v === null ? null : (int) $v;
}
function orgName($id) { return one("SELECT name FROM ".ORGANIZATION_TABLE." WHERE id=".(int) $id); }
function orgCount($name) { return (int) one("SELECT COUNT(*) FROM ".ORGANIZATION_TABLE." WHERE name=".db_input($name)); }
// The published row: id of the Priority organization remembered by stripe-sync.
function rememberedOrgId() {
    $v = one("SELECT `value` FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key`='priority_org_id'");
    return $v === null ? null : (int) $v;
}
function rememberedOrgName() { return one("SELECT `value` FROM ".CONFIG_TABLE." WHERE `namespace`='stripesync' AND `key`='priority_org_name'"); }
// Rename / delete as the admin panel does (Organization model, signals).
function renameOrg($id, $name) { $o = Organization::lookup((int) $id); $o->name = $name; return $o->save(); }
function deleteOrg($id) { $o = Organization::lookup((int) $id); return $o && $o->delete(); }
function syslogSince($logId, $type=null) {
    return q("SELECT log_id, log_type, title, log FROM ".SYSLOG_TABLE." WHERE log_id > ".(int) $logId
        . ($type ? " AND log_type=".db_input($type) : '')." ORDER BY log_id");
}
function lastLogId() { return (int) one("SELECT COALESCE(MAX(log_id), 0) FROM ".SYSLOG_TABLE); }
function done() { global $n, $fails; echo "\n$n checks, $fails failure(s)\n"; exit($fails); }
