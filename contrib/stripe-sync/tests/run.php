<?php
/*********************************************************************
    tests/run.php

    Standalone unit tests (no osTicket, no database, no network, no
    Stripe account): signature verification, settings, event mapping and
    the business rules of the service, against in-memory fakes.

        php contrib/stripe-sync/tests/run.php

    Exit code = number of failures.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

$inc = dirname(__FILE__) . '/../include/';
require_once $inc . 'class.stripesync.signature.php';
require_once $inc . 'class.stripesync.settings.php';
require_once $inc . 'class.stripesync.mapper.php';
require_once $inc . 'class.stripesync.service.php';

class Counter {
    static $fail = 0;
    static $count = 0;
}

function check($cond, $label) {
    Counter::$count++;
    if ($cond)
        return;
    Counter::$fail++;
    echo "FAIL: $label\n";
}

function throws($class, $fn, $label) {
    try { $fn(); }
    catch (Throwable $e) {
        check($e instanceof $class, "$label (got ".get_class($e).": ".$e->getMessage().")");
        return;
    }
    check(false, "$label (nothing thrown)");
}

// ---------------------------------------------------------------- signature

// Test vector computed independently of this code base (OpenSSL):
//   printf '%s' '1700000000.{"id":"evt_1"}' | openssl dgst -sha256 -hmac whsec_test_secret
$secret = 'whsec_test_secret';
$body = '{"id":"evt_1"}';
$ts = 1700000000;
$sig = '248a374f50f943a28b0f6ab50faf9a7e7e29b710fa26df9fb1618b9bf8ea9c9a';
check(StripeSyncSignature::compute($body, $ts, $secret) === $sig, 'HMAC matches the OpenSSL reference vector');
$hdr = "t=$ts,v1=$sig,v0=" . str_repeat('0', 64);

check(StripeSyncSignature::verify($body, $hdr, $secret, 300, $ts + 10), 'valid signature accepted');
check(StripeSyncSignature::verify($body, "v1=ffff, t=$ts , v1=$sig", $secret, 300, $ts), 'several v1, any order, spaces');
throws('StripeSyncSignatureException', function () use ($body, $hdr) {
    StripeSyncSignature::verify($body, $hdr, 'whsec_other', 300, 1700000000); }, 'wrong secret rejected');
throws('StripeSyncSignatureException', function () use ($hdr, $secret) {
    StripeSyncSignature::verify('{"id":"evt_2"}', $hdr, $secret, 300, 1700000000); }, 'tampered body rejected');
throws('StripeSyncSignatureException', function () use ($body, $hdr, $secret) {
    StripeSyncSignature::verify($body, $hdr, $secret, 300, 1700000000 + 301); }, 'old timestamp rejected (replay)');
throws('StripeSyncSignatureException', function () use ($body, $hdr, $secret) {
    StripeSyncSignature::verify($body, $hdr, $secret, 300, 1700000000 - 301); }, 'future timestamp rejected');
throws('StripeSyncSignatureException', function () use ($body, $ts, $secret) {
    StripeSyncSignature::verify($body, "t=$ts,v0=" . hash_hmac('sha256', "$ts.$body", $secret), $secret, 300, $ts); },
    'v0 scheme only is not accepted (downgrade)');
throws('StripeSyncSignatureException', function () use ($body, $hdr) {
    StripeSyncSignature::verify($body, $hdr, '', 300, 1700000000); }, 'empty secret fails closed');
throws('StripeSyncSignatureException', function () use ($body, $secret) {
    StripeSyncSignature::verify($body, '', $secret); }, 'empty header');
throws('StripeSyncSignatureException', function () use ($body, $secret) {
    StripeSyncSignature::verify($body, 't=abc,v1=00', $secret); }, 'non numeric timestamp');
throws('StripeSyncSignatureException', function () use ($body, $secret) {
    StripeSyncSignature::verify($body, 'garbage', $secret); }, 'garbage header');

// ----------------------------------------------------------------- settings

$none = function ($n) { return false; };
$envOf = function (array $vars) { return function ($n) use ($vars) { return isset($vars[$n]) ? $vars[$n] : false; }; };

$s = new StripeSyncSettings(array(), $none);
check($s->getWebhookSecret() === null, 'no secret -> null');
check($s->getOrgName() === 'Priority', 'default org name');
check($s->getActiveStatuses() == array('active', 'trialing'), 'default statuses: past_due does not keep Priority');
check(!in_array('past_due', StripeSyncSettings::$DEFAULT_ACTIVE_STATUSES, true), 'past_due is not an active status by default');
$s2 = new StripeSyncSettings(array('active_statuses' => 'active, trialing, past_due'), $none);
check(in_array('past_due', $s2->getActiveStatuses(), true), 'past_due can still be re-enabled explicitly');
check(!$s->hasPriorityCatalog(), 'no catalog by default');
check(!$s->acceptTestEvents(), 'test events refused by default');

$s = new StripeSyncSettings(array('org_name' => 'Gold', 'price_ids' => 'price_a, price_b;price_a'),
    $envOf(array('STRIPE_SYNC_WEBHOOK_SECRET' => ' whsec_abc ', 'STRIPE_SYNC_PRIORITY_ORG' => 'Env Org')));
check($s->getWebhookSecret() === 'whsec_abc', 'secret from env, trimmed');
check($s->getOrgName() === 'Env Org', 'env overrides form');
check($s->getPriorityPriceIds() == array('price_a', 'price_b'), 'csv parsing + dedup');

$s = new StripeSyncSettings(array(), $envOf(array('STRIPE_SYNC_WEBHOOK_SECRET' => 'sk_live_oops')));
check($s->getWebhookSecret() === null, 'an API key is not accepted as webhook secret');

$s = new StripeSyncSettings(array(), $envOf(array('STRIPE_SYNC_WEBHOOK_SECRET_FILE' => '/run/secrets/x')),
    function ($p) { return $p === '/run/secrets/x' ? "whsec_file\n" : false; });
check($s->getWebhookSecret() === 'whsec_file', 'secret from file');
$s = new StripeSyncSettings(array('webhook_secret' => 'whsec_db'), $none);
check($s->getWebhookSecret() === null, 'the secret is never read from the form/database');

// ------------------------------------------------------------------- mapper

// $extra: more fields of the Subscription object (start_date, ended_at, ...).
// start_date defaults to 1690000000 (the very first start, constant across renewals).
function sub_event($id, $type, $created, $status, $price, $cus='cus_A', $sub='sub_1', $product='prod_P', $extra=array()) {
    return array('id' => $id, 'type' => $type, 'created' => $created, 'livemode' => true,
        'data' => array('object' => $extra + array('id' => $sub, 'customer' => $cus, 'status' => $status,
            'start_date' => 1690000000,
            'items' => array('data' => array(array('price' => array('id' => $price, 'product' => $product)))))));
}
function paid_event($id, $cus='cus_A', $email='client@example.com', $ps='paid') {
    return array('id' => $id, 'type' => 'checkout.session.completed', 'created' => 1700000000, 'livemode' => true,
        'data' => array('object' => array('customer' => $cus, 'payment_status' => $ps,
            'customer_details' => array('email' => $email, 'name' => 'Ada Client'))));
}

$f = StripeSyncMapper::map(paid_event('evt_1'));
check($f && $f['kind'] == 'paid' && $f['customer'] == 'cus_A' && $f['email'] == 'client@example.com', 'checkout paid mapped');
check(StripeSyncMapper::map(paid_event('evt_1', 'cus_A', 'a@b.c', 'unpaid')) === null, 'unpaid session ignored');
$e = paid_event('evt_1'); $e['type'] = 'checkout.session.async_payment_succeeded';
check(StripeSyncMapper::map($e)['kind'] == 'paid', 'async payment succeeded mapped');
$e = paid_event('evt_1'); $e['data']['object']['customer'] = array('id' => 'cus_X');
check(StripeSyncMapper::map($e)['customer'] == 'cus_X', 'expanded customer object');
$e = paid_event('evt_1'); unset($e['data']['object']['customer_details']); $e['data']['object']['customer_email'] = 'z@z.zz';
check(StripeSyncMapper::map($e)['email'] == 'z@z.zz', 'customer_email fallback');
check(StripeSyncMapper::map(array('id' => 'evt_1', 'type' => 'customer.created', 'data' => array('object' => array('id' => 'cus_A')))) === null, 'unhandled type ignored');
check(StripeSyncMapper::map(array('type' => 'x')) === null, 'malformed event ignored');

$cfg = new StripeSyncSettings(array('price_ids' => 'price_prio', 'product_ids' => 'prod_prio'), $none);
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.created', 10, 'active', 'price_prio'));
check($f['kind'] == 'subscription' && StripeSyncMapper::grantsPriority($f, $cfg), 'active priority subscription grants');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 10, 'active', 'price_other'));
check(!StripeSyncMapper::grantsPriority($f, $cfg), 'other price does not grant');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 10, 'active', 'price_other', 'cus_A', 'sub_1', 'prod_prio'));
check(StripeSyncMapper::grantsPriority($f, $cfg), 'matching product grants');
foreach (array('past_due', 'canceled', 'unpaid', 'incomplete', 'incomplete_expired', 'paused') as $st) {
    $f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 10, $st, 'price_prio'));
    check(!StripeSyncMapper::grantsPriority($f, $cfg), "status $st does not grant");
    check(StripeSyncMapper::isPrioritySubscription($f, $cfg), "status $st: still a Priority subscription (record kept)");
}
foreach (array('active', 'trialing') as $st) {
    $f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 10, $st, 'price_prio'));
    check(StripeSyncMapper::grantsPriority($f, $cfg), "status $st grants");
}
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 10, 'active', 'price_other'));
check(!StripeSyncMapper::isPrioritySubscription($f, $cfg), 'other price: not a Priority subscription');

// dates of the subscription
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 500, 'active', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('current_period_start' => 1750000000, 'canceled_at' => 1750000100, 'cancel_at_period_end' => true)));
check($f['start_date'] === 1690000000 && $f['ended'] === null, 'start_date read as is; a pending cancel_at_period_end is not an end');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.deleted', 500, 'canceled', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('ended_at' => 1760000000, 'canceled_at' => 1750000100)));
check($f['ended'] === 1760000000, 'ended = ended_at');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.deleted', 500, 'canceled', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('canceled_at' => 1750000100)));
check($f['ended'] === 1750000100, 'ended falls back to canceled_at');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.deleted', 500, 'canceled', 'price_prio'));
check($f['ended'] === 500, 'ended falls back to the event time');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 500, 'active', 'price_prio', 'cus_A', 'sub_1', 'prod_P', array('start_date' => '1690000000')));
check($f['start_date'] === 1690000000, 'numeric string start_date accepted');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.updated', 500, 'active', 'price_prio', 'cus_A', 'sub_1', 'prod_P', array('start_date' => 'x; DROP')));
check($f['start_date'] === null, 'garbage start_date rejected');
$f = StripeSyncMapper::map(sub_event('evt_1', 'customer.subscription.deleted', 10, 'active', 'price_prio'));
check($f['terminal'] && !StripeSyncMapper::grantsPriority($f, $cfg), 'deleted is terminal even if payload says active');
check(StripeSyncMapper::isId('cus_Nf8xABC123', 'cus') && !StripeSyncMapper::isId('cus_<script>', 'cus')
    && !StripeSyncMapper::isId('sub_1', 'cus'), 'id validation');

// ------------------------------------------------------------------ service

class FakeStore implements StripeSyncStore {
    public $events = array(); public $customers = array(); public $locked = array(); public $failLock = false;
    public $subs = array(); public $subWrites = 0;      // published sub:<id> records
    function putSubscription($id, $value) {
        if (!isset($this->subs[$id]) || $this->subs[$id] !== $value) $this->subWrites++;
        $this->subs[$id] = $value; return true;
    }
    function claimEvent($id) {
        if (!isset($this->events[$id])) { $this->events[$id] = 'p'; return 'claimed'; }
        return $this->events[$id] == 'done' ? 'done' : 'busy';
    }
    function completeEvent($id) { $this->events[$id] = 'done'; }
    function releaseEvent($id) { unset($this->events[$id]); }
    function getCustomer($c) { return isset($this->customers[$c]) ? $this->customers[$c] : null; }
    function putCustomer($c, array $s) { $this->customers[$c] = $s; return true; }
    function lock($c) { if ($this->failLock) return false; $this->locked[$c] = true; return true; }
    function unlock($c) { unset($this->locked[$c]); }
}

class FakeDirectory implements StripeSyncDirectory {
    public $users = array();      // id => array(email, org, account => none|pending|active|locked)
    public $next = 1; public $mails = 0; public $failEnsureUser = null; public $calls = array();
    public $domainOrg = array();  // email domain => org id
    const PRIORITY = 99;
    function ensureUser($email, $name) {
        if ($this->failEnsureUser) throw $this->failEnsureUser;
        foreach ($this->users as $id => $u) if ($u['email'] == $email) return $id;
        $id = $this->next++;
        $dom = substr($email, strpos($email, '@') + 1);
        $this->users[$id] = array('email' => $email, 'org' => isset($this->domainOrg[$dom]) ? $this->domainOrg[$dom] : null, 'account' => 'none');
        return $id;
    }
    function userExists($id) { return isset($this->users[$id]); }
    function ensureAccess($id, $last, $delay, $now) {
        $a = $this->users[$id]['account'];
        if ($a == 'locked') return 'locked';
        if ($a == 'active') return 'active';
        if ($a == 'pending' && $last && ($now - $last) < $delay) return 'throttled';
        $this->users[$id]['account'] = 'pending'; $this->mails++;
        return 'invited';
    }
    function attachPriority($id) {
        $this->calls[] = "attach:$id";
        if ($this->users[$id]['org'] === self::PRIORITY) return array(false, null);
        $prev = $this->users[$id]['org']; $this->users[$id]['org'] = self::PRIORITY;
        return array(true, $prev);
    }
    function detachPriority($id, $restore) {
        $this->calls[] = "detach:$id";
        if (!isset($this->users[$id]) || $this->users[$id]['org'] !== self::PRIORITY) return false;
        $this->users[$id]['org'] = $restore; return true;
    }
}

class FakeLogger implements StripeSyncLogger {
    public $lines = array();
    function info($t, $m) { $this->lines[] = "info $t $m"; }
    function warning($t, $m) { $this->lines[] = "warning $t $m"; }
    function error($t, $m) { $this->lines[] = "error $t $m"; }
}

function make($settings=null) {
    $st = new FakeStore(); $dir = new FakeDirectory(); $log = new FakeLogger();
    $settings = $settings ?: new StripeSyncSettings(array('price_ids' => 'price_prio'), function ($n) { return false; });
    $now = 1700000000;
    $svc = new StripeSyncService($st, $dir, $settings, $log, function () use (&$now) { return $now; });
    return array($svc, $st, $dir, $log);
}

// 1. Included support only: account + invitation, no Priority
list($svc, $st, $dir, $log) = make();
check($svc->handle(paid_event('evt_a')) == 'account', 'purchase -> account');
check($dir->mails == 1 && $dir->users[1]['account'] == 'pending' && $dir->users[1]['org'] === null, 'invited, not Priority');
check($svc->handle(paid_event('evt_a')) == 'duplicate' && $dir->mails == 1, 'same event id is idempotent');
check(!$st->locked, 'locks released');

// 2. Priority subscription after purchase, then cancellation restores the previous org
list($svc, $st, $dir, $log) = make();
$dir->domainOrg['example.com'] = 7;                       // org auto-assigned by e-mail domain
$svc->handle(paid_event('evt_a'));
check($dir->users[1]['org'] === 7, 'user starts in his company organization');
check($svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio')) == 'ok', 'subscription created');
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'user is now Priority');
check($st->customers['cus_A']['prev_org_id'] === 7, 'previous organization remembered');
$svc->handle(sub_event('evt_c', 'customer.subscription.updated', 200, 'active', 'price_prio'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'renewal keeps Priority');
$svc->handle(sub_event('evt_d', 'customer.subscription.updated', 300, 'past_due', 'price_prio'));
check($dir->users[1]['org'] === 7, 'past_due: Priority removed, company organization restored');
check($st->customers['cus_A']['attached'] === false && $st->customers['cus_A']['prev_org_id'] === null, 'past_due: state detached');
$svc->handle(sub_event('evt_d2', 'customer.subscription.updated', 350, 'past_due', 'price_prio'));
check($dir->users[1]['org'] === 7, 'a second past_due event changes nothing');
$svc->handle(sub_event('evt_d3', 'customer.subscription.updated', 360, 'active', 'price_prio'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'back to active after the payment: Priority granted again');
check($st->customers['cus_A']['attached'] === true && $st->customers['cus_A']['prev_org_id'] === 7, 'state: re-attached, company organization remembered again');
$svc->handle(sub_event('evt_e', 'customer.subscription.deleted', 400, 'canceled', 'price_prio'));
check($dir->users[1]['org'] === 7, 'end of subscription restores the previous organization');
check($st->customers['cus_A']['attached'] === false, 'state: detached');

// 2b. past_due then unpaid then canceled, and a user without company organization
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
$svc->handle(sub_event('evt_c', 'customer.subscription.updated', 200, 'past_due', 'price_prio'));
check($dir->users[1]['org'] === null, 'past_due, no company organization: back to none');
$svc->handle(sub_event('evt_d', 'customer.subscription.updated', 300, 'active', 'price_prio'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'active again: Priority again');
$svc->handle(sub_event('evt_e', 'customer.subscription.updated', 400, 'past_due', 'price_prio'));
$svc->handle(sub_event('evt_f', 'customer.subscription.updated', 500, 'unpaid', 'price_prio'));
check($dir->users[1]['org'] === null, 'unpaid: still no Priority');
$svc->handle(sub_event('evt_g', 'customer.subscription.updated', 450, 'active', 'price_prio'));
check($dir->users[1]['org'] === null, 'late "active" (older than unpaid) is ignored');

// 2c. past_due on one subscription does not remove Priority granted by another
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio', 'cus_A', 'sub_1'));
$svc->handle(sub_event('evt_c', 'customer.subscription.created', 110, 'active', 'price_prio', 'cus_A', 'sub_2'));
$svc->handle(sub_event('evt_d', 'customer.subscription.updated', 200, 'past_due', 'price_prio', 'cus_A', 'sub_1'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'one subscription past_due, another active: still Priority');
$svc->handle(sub_event('evt_e', 'customer.subscription.updated', 210, 'past_due', 'price_prio', 'cus_A', 'sub_2'));
check($dir->users[1]['org'] === null, 'all subscriptions past_due: Priority removed');

// 2d. the setting can bring the old behaviour back
list($svc, $st, $dir, $log) = make(new StripeSyncSettings(array('price_ids' => 'price_prio', 'active_statuses' => 'active, trialing, past_due'), function ($n) { return false; }));
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.updated', 100, 'past_due', 'price_prio'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'explicit active_statuses with past_due keeps Priority');

// 3. Out of order: stale event ignored, canceled is final
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.updated', 200, 'canceled', 'price_prio'));
check($svc->handle(sub_event('evt_c', 'customer.subscription.created', 100, 'active', 'price_prio')) == 'stale', 'older event ignored');
check($dir->users[1]['org'] === null, 'late "created" does not resurrect a canceled subscription');
check($svc->handle(sub_event('evt_d', 'customer.subscription.updated', 300, 'active', 'price_prio')) == 'stale', 'canceled subscription is final');

// 4. Subscription events before the purchase event: deferred, then applied
list($svc, $st, $dir, $log) = make();
check($svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio')) == 'deferred', 'no user yet -> deferred');
check(!$dir->users, 'no user created from a subscription event alone');
$svc->handle(paid_event('evt_a'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'deferred state applied when the customer is known');

// 5. Not a Priority price: nothing happens
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_license'));
check($dir->users[1]['org'] === null, 'non Priority subscription does not change the organization');

// 6. Two subscriptions: Priority stays while one remains
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio', 'cus_A', 'sub_1'));
$svc->handle(sub_event('evt_c', 'customer.subscription.created', 110, 'active', 'price_prio', 'cus_A', 'sub_2'));
$svc->handle(sub_event('evt_d', 'customer.subscription.deleted', 200, 'canceled', 'price_prio', 'cus_A', 'sub_1'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'one subscription left -> still Priority');
$svc->handle(sub_event('evt_e', 'customer.subscription.deleted', 210, 'canceled', 'price_prio', 'cus_A', 'sub_2'));
check($dir->users[1]['org'] === null, 'last subscription gone -> Priority removed');

// 7. Manual membership is never removed by the integration
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$dir->users[1]['org'] = FakeDirectory::PRIORITY;            // an agent did it by hand
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_license'));
$svc->handle(sub_event('evt_c', 'customer.subscription.deleted', 200, 'canceled', 'price_license'));
check($dir->users[1]['org'] === FakeDirectory::PRIORITY, 'manual Priority membership untouched');

// 8. Agent moved the user elsewhere meanwhile: respected at the end
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
$dir->users[1]['org'] = 5;
$svc->handle(sub_event('evt_c', 'customer.subscription.deleted', 200, 'canceled', 'price_prio'));
check($dir->users[1]['org'] === 5, 'user moved by an agent is left alone');

// 9. Invitation re-sent for an unconfirmed account, but throttled
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(paid_event('evt_b'));
check($dir->mails == 1, 'second purchase within the delay does not mail again');
$dir->users[1]['account'] = 'active';
$svc->handle(paid_event('evt_c'));
check($dir->mails == 1, 'confirmed account is not re-invited');
$dir->users[1]['account'] = 'locked';
check($svc->handle(paid_event('evt_d')) == 'account' && $dir->mails == 1, 'locked account is respected');

// 10. Customer changed e-mail: old user released, new one gets Priority
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a', 'cus_A', 'old@example.com'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
$svc->handle(paid_event('evt_c', 'cus_A', 'new@example.com'));
check($dir->users[1]['org'] === null && $dir->users[2]['org'] === FakeDirectory::PRIORITY, 'Priority follows the new e-mail');

// 11. User deleted by an agent while subscribed: recreated without invitation
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$mails = $dir->mails;
unset($dir->users[1]);
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
check(isset($dir->users[2]) && $dir->users[2]['org'] === FakeDirectory::PRIORITY && $dir->mails == $mails, 'deleted user recreated silently');

// 12. Guest payment (no Stripe customer)
list($svc, $st, $dir, $log) = make();
$e = paid_event('evt_a'); $e['data']['object']['customer'] = null;
check($svc->handle($e) == 'account' && $dir->mails == 1 && !$st->customers, 'guest payment: account, no state');

// 13. Errors: permanent -> logged + completed; retryable -> claim released
list($svc, $st, $dir, $log) = make();
$e = paid_event('evt_a'); unset($e['data']['object']['customer_details']);
check($svc->handle($e) == 'failed' && $st->events['evt_a'] == 'done' && strpos(implode("\n", $log->lines), 'error') === 0, 'no e-mail -> permanent failure logged');
list($svc, $st, $dir, $log) = make();
$dir->failEnsureUser = new StripeSyncRetryableError('db down');
throws('StripeSyncRetryableError', function () use ($svc) { $svc->handle(paid_event('evt_a')); }, 'transient error propagates');
check(!isset($st->events['evt_a']), 'claim released so Stripe retry is processed');
$dir->failEnsureUser = null;
check($svc->handle(paid_event('evt_a')) == 'account', 'retry succeeds');
list($svc, $st, $dir, $log) = make();
$st->failLock = true;
throws('StripeSyncRetryableError', function () use ($svc) { $svc->handle(paid_event('evt_a')); }, 'lock not obtained -> retry later');
list($svc, $st, $dir, $log) = make(new StripeSyncSettings(array(), function ($n) { return false; }));
throws('StripeSyncRetryableError', function () use ($svc) {
    $svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio')); },
    'catalog not configured -> fail visibly (retry) instead of silently ignoring');
list($svc, $st, $dir, $log) = make();
$st->events['evt_a'] = 'p';
throws('StripeSyncRetryableError', function () use ($svc) { $svc->handle(paid_event('evt_a')); }, 'event in progress elsewhere -> retry later');

// 14. payment_failed is informational
list($svc, $st, $dir, $log) = make();
$r = $svc->handle(array('id' => 'evt_f', 'type' => 'invoice.payment_failed', 'created' => 5, 'data' => array('object' => array('id' => 'in_1', 'customer' => 'cus_A'))));
check($r == 'logged' && count($log->lines) == 1 && !$dir->users, 'payment_failed only logged');

// 15. Hostile ids never reach logs/store
list($svc, $st, $dir, $log) = make();
$r = $svc->handle(array('id' => 'evt_f', 'type' => 'invoice.payment_failed', 'created' => 5, 'data' => array('object' => array('id' => '<script>', 'customer' => '"; DROP'))));
check(strpos(implode("\n", $log->lines), '<script>') === false && strpos(implode("\n", $log->lines), 'DROP') === false, 'ids are sanitized in logs');

// 16. Records exposed to other modules: sub:<id> = user|start_date|ended|status
$START = 1690000000;
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
check($st->subs === array(), 'no subscription, no record');
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
check($st->subs === array('sub_1' => "1|$START||active"), 'exact format: user|start_date||status while running');
check(preg_match('/^\d+\|\d+\|\d*\|[a-z_]+$/', $st->subs['sub_1']) === 1 && strpos($st->subs['sub_1'], ' ') === false, 'no space, no JSON');
$w = $st->subWrites;
$svc->handle(sub_event('evt_c', 'customer.subscription.updated', 200, 'active', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('current_period_start' => 1692000000, 'current_period_end' => 1694600000)));
check($st->subs['sub_1'] === "1|$START||active" && $st->subWrites == $w, 'renewal: start_date unchanged, nothing rewritten');
$svc->handle(sub_event('evt_c2', 'customer.subscription.updated', 210, 'active', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('start_date' => $START + 99999)));
check($st->subs['sub_1'] === "1|$START||active", 'the first start_date seen is kept');
$svc->handle(sub_event('evt_d', 'customer.subscription.updated', 300, 'past_due', 'price_prio'));
check($st->subs['sub_1'] === "1|$START||past_due", 'past_due: record updated, not ended');
$svc->handle(sub_event('evt_d2', 'customer.subscription.updated', 310, 'active', 'price_prio'));
check($st->subs['sub_1'] === "1|$START||active", 'back to active: same start_date');
$svc->handle(sub_event('evt_d3', 'customer.subscription.updated', 320, 'active', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('cancel_at_period_end' => true, 'canceled_at' => 1700000000)));
check($st->subs['sub_1'] === "1|$START||active", 'cancel_at_period_end pending: still running (ended empty)');
$w = $st->subWrites;
$svc->handle(sub_event('evt_e', 'customer.subscription.deleted', 400, 'canceled', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('ended_at' => 1760000000, 'canceled_at' => 1700000000)));
check($st->subs['sub_1'] === "1|$START|1760000000|canceled", 'cancellation: ended filled (epoch UTC), status canceled');
check($svc->handle(sub_event('evt_e', 'customer.subscription.deleted', 400, 'canceled', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('ended_at' => 1760000000))) == 'duplicate' && $st->subWrites == $w + 1, 'replayed event: duplicate, no further write');
$svc->handle(sub_event('evt_e2', 'customer.subscription.deleted', 401, 'canceled', 'price_prio', 'cus_A', 'sub_1', 'prod_P',
    array('ended_at' => 1760000000)));
$svc->handle(paid_event('evt_p2'));
check($st->subs['sub_1'] === "1|$START|1760000000|canceled" && $st->subWrites == $w + 1 && count($st->subs) == 1,
    'same content under a new event id: idempotent (no new write, no new row)');
$svc->handle(sub_event('evt_e3', 'customer.subscription.updated', 500, 'active', 'price_prio'));
check($st->subs['sub_1'] === "1|$START|1760000000|canceled", 'a canceled subscription never comes back in the record either');

// not a Priority subscription: no record at all
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_license'));
$svc->handle(sub_event('evt_c', 'customer.subscription.deleted', 200, 'canceled', 'price_license'));
check($st->subs === array(), 'non Priority subscription: nothing written');

// subscription event before the purchase: written once the user is known (checkout.session.completed)
list($svc, $st, $dir, $log) = make();
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
check($st->subs === array(), 'unknown user yet: nothing written');
$svc->handle(paid_event('evt_a'));
check($st->subs === array('sub_1' => "1|$START||active"), 'written when the checkout event reveals the user');

// matching by product, several subscriptions, unknown start_date
list($svc, $st, $dir, $log) = make(new StripeSyncSettings(array('product_ids' => 'prod_prio'), function ($n) { return false; }));
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'trialing', 'price_x', 'cus_A', 'sub_1', 'prod_prio'));
$svc->handle(sub_event('evt_c', 'customer.subscription.created', 101, 'active', 'price_x', 'cus_A', 'sub_2', 'prod_prio', array('start_date' => 1600000000)));
$svc->handle(sub_event('evt_d', 'customer.subscription.created', 102, 'active', 'price_x', 'cus_A', 'sub_3', 'prod_other'));
check($st->subs === array('sub_1' => "1|$START||trialing", 'sub_2' => '1|1600000000||active'), 'one record per Priority subscription (by product), none for the others');
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$e = sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'); unset($e['data']['object']['start_date']);
$svc->handle($e);
check($st->subs === array() && strpos(implode("\n", $log->lines), 'without start date') !== false && $dir->users[1]['org'] === FakeDirectory::PRIORITY,
    'no start_date: no record (warning logged) but Priority still granted');

// customer changes e-mail: the record follows the new user
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a', 'cus_A', 'old@example.com'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
$svc->handle(paid_event('evt_c', 'cus_A', 'new@example.com'));
check($st->subs['sub_1'] === "2|$START||active", 'record points to the new user after an e-mail change');

// subscription moved away from the Priority price: the Priority period ends at that event
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$svc->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio'));
$svc->handle(sub_event('evt_c', 'customer.subscription.updated', 250, 'active', 'price_license'));
check($st->subs['sub_1'] === "1|$START|250|active" && $dir->users[1]['org'] === null, 'downgrade to a non Priority price: ended at the event time, Priority removed');
$svc->handle(sub_event('evt_d', 'customer.subscription.updated', 260, 'active', 'price_license'));
check($st->subs['sub_1'] === "1|$START|250|active", 'the end of the Priority period is not moved by later events');

// a failing write is retried by Stripe: the event is released
list($svc, $st, $dir, $log) = make();
$svc->handle(paid_event('evt_a'));
$st2 = new class extends FakeStore { function putSubscription($i, $v) { throw new StripeSyncRetryableError('db down'); } };
$st2->customers = $st->customers;
$svc2 = new StripeSyncService($st2, $dir, new StripeSyncSettings(array('price_ids' => 'price_prio'), function ($n) { return false; }), $log);
throws('StripeSyncRetryableError', function () use ($svc2) {
    $svc2->handle(sub_event('evt_b', 'customer.subscription.created', 100, 'active', 'price_prio')); }, 'record write failure -> retry later');
check(!isset($st2->events['evt_b']) && !isset($st2->customers['cus_A']['subs']['sub_1']), 'failed write: event released, state not saved');

printf("%d checks, %d failure(s)\n", Counter::$count, Counter::$fail);
exit(Counter::$fail);
