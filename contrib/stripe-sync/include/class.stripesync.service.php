<?php
/*********************************************************************
    class.stripesync.service.php

    Business rules of the Stripe -> osTicket synchronization. This class
    knows nothing about HTTP, SQL or osTicket classes: it talks to a
    "store" (idempotency, per customer state, locks), to a "directory"
    (users, portal accounts, organizations) and to a "logger", all
    injected. That is what makes it unit testable without a database.

    Rules, in short:
      - every event is processed once (idempotency on the event id);
      - a paid purchase gives the customer a portal account (invitation);
      - Priority membership = "at least one subscription of the customer
        is in an active status AND contains a Priority price/product";
      - events can arrive out of order: per subscription, an older event
        never overwrites a newer state, and a canceled subscription never
        comes back;
      - the previous organization of the user is remembered and restored
        when the Priority support ends (osTicket users have ONE
        organization only);
      - we only remove from the Priority organization the users that we
        put there ourselves.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once dirname(__FILE__) . '/class.stripesync.mapper.php';

/** Retry later (Stripe will redeliver): HTTP 5xx. */
class StripeSyncRetryableError extends Exception {}

/** Retrying cannot help (bad data): logged, answered 200. */
class StripeSyncPermanentError extends Exception {}

interface StripeSyncStore {
    /** 'claimed' (go ahead), 'done' (already processed), 'busy' (in progress) */
    function claimEvent($eventId);
    function completeEvent($eventId);
    function releaseEvent($eventId);
    /** array|null - state of one Stripe customer */
    function getCustomer($customerId);
    function putCustomer($customerId, array $state);
    /** Exclusive lock per Stripe customer; false if it cannot be obtained. */
    function lock($customerId);
    function unlock($customerId);
}

interface StripeSyncDirectory {
    /** Existing user for this e-mail or a new one: returns its id. */
    function ensureUser($email, $name);
    function userExists($userId);
    /**
     * Make sure the user can log in to the portal. Sends the invitation
     * (confirmation link) when the account does not exist yet or is not
     * confirmed, unless one was sent less than $minDelay seconds after
     * $lastInvite. Returns 'invited', 'active', 'throttled' or 'locked'.
     */
    function ensureAccess($userId, $lastInvite, $minDelay, $now);
    /** Put the user in the Priority organization. Returns array(changed, previous org id|null). */
    function attachPriority($userId);
    /** Take the user out of the Priority organization (and back to $restoreOrgId). */
    function detachPriority($userId, $restoreOrgId);
}

interface StripeSyncLogger {
    function info($title, $message);
    function warning($title, $message);
    function error($title, $message);
}

class StripeSyncService {

    private $store;
    private $dir;
    private $settings;
    private $log;
    private $clock;

    function __construct(StripeSyncStore $store, StripeSyncDirectory $dir,
            $settings, StripeSyncLogger $log, $clock=null) {
        $this->store = $store;
        $this->dir = $dir;
        $this->settings = $settings;
        $this->log = $log;
        $this->clock = $clock ?: function () { return time(); };
    }

    private function now() {
        return call_user_func($this->clock);
    }

    /**
     * Process one VERIFIED event. Returns a short result string; throws
     * StripeSyncRetryableError when Stripe must redeliver the event.
     */
    function handle(array $event) {
        $fact = StripeSyncMapper::map($event);
        if (!$fact)
            return 'ignored';

        $eid = $fact['event_id'];
        if (!StripeSyncMapper::isId($eid, 'evt'))
            return 'ignored';

        switch ($this->store->claimEvent($eid)) {
        case 'done':
            return 'duplicate';
        case 'busy':
            throw new StripeSyncRetryableError('Event is being processed');
        }

        try {
            $result = $this->dispatch($fact);
            $this->store->completeEvent($eid);
            return $result;
        }
        catch (StripeSyncPermanentError $e) {
            $this->log->error('Stripe sync: event rejected',
                sprintf('%s (%s): %s', $eid, $fact['event_type'], $e->getMessage()));
            $this->store->completeEvent($eid);
            return 'failed';
        }
        catch (Throwable $e) {
            // Let Stripe redeliver: every step below is idempotent.
            $this->store->releaseEvent($eid);
            throw $e;
        }
    }

    private function dispatch(array $fact) {
        switch ($fact['kind']) {
        case 'paid':
            return $this->onPaid($fact);
        case 'subscription':
            return $this->onSubscription($fact);
        case 'payment_failed':
            // The subscription status change (past_due, unpaid, canceled)
            // is what changes access: this is only a heads-up for the admin.
            $this->log->warning('Stripe sync: payment failed',
                sprintf('Invoice %s of customer %s', $this->id($fact, 'invoice'),
                    $this->id($fact, 'customer')));
            return 'logged';
        }
        return 'ignored';
    }

    // Only well formed Stripe ids go to the logs and to the store.
    private function id(array $fact, $key) {
        return (isset($fact[$key]) && StripeSyncMapper::isId($fact[$key]))
            ? $fact[$key] : '-';
    }

    private function newState() {
        return array(
            'v' => 1, 'user_id' => null, 'email' => null,
            'prev_org_id' => null, 'attached' => false,
            'invited_at' => 0, 'subs' => array(),
        );
    }

    // --- paid purchase ---------------------------------------------------

    private function onPaid(array $fact) {
        $cus = $fact['customer'];

        if (!$cus) {
            // Guest payment without a Stripe customer: portal account only.
            if (!$fact['email'])
                throw new StripeSyncPermanentError('Paid session without e-mail');
            $uid = $this->dir->ensureUser($fact['email'], $fact['name']);
            $this->dir->ensureAccess($uid, 0, 0, $this->now());
            return 'account';
        }

        if (!StripeSyncMapper::isId($cus, 'cus'))
            throw new StripeSyncPermanentError('Invalid customer id');

        return $this->withCustomer($cus, function ($state) use ($fact) {
            $email = $fact['email'] ?: $state['email'];
            if (!$email)
                throw new StripeSyncPermanentError('Paid event without e-mail');

            $uid = $this->dir->ensureUser($email, $fact['name']);
            if ($state['user_id'] && $state['user_id'] != $uid)
                $this->release($state);     // the customer changed of e-mail
            $state['user_id'] = $uid;
            $state['email'] = $email;

            $r = $this->dir->ensureAccess($uid, $state['invited_at'],
                $this->settings->getInviteResendDelay(), $this->now());
            if ($r == 'invited')
                $state['invited_at'] = $this->now();

            $this->reconcile($state);
            return array($state, 'account');
        });
    }

    // --- subscription state change ---------------------------------------

    private function onSubscription(array $fact) {
        $cus = $fact['customer'];
        $sub = $fact['subscription'];
        if (!StripeSyncMapper::isId($cus, 'cus') || !StripeSyncMapper::isId($sub, 'sub'))
            throw new StripeSyncPermanentError('Invalid customer or subscription id');
        if (!$this->settings->hasPriorityCatalog())
            throw new StripeSyncRetryableError(
                'No Priority price/product configured (STRIPE_SYNC_PRIORITY_PRICE_IDS)');

        return $this->withCustomer($cus, function ($state) use ($fact, $sub) {
            $cur = isset($state['subs'][$sub]) ? $state['subs'][$sub] : null;
            if ($cur && $cur['terminal'])
                return array($state, 'stale');          // ended for good
            if ($cur && $fact['created'] < $cur['ts'])
                return array($state, 'stale');          // older than what we know

            $state['subs'][$sub] = array(
                'priority' => StripeSyncMapper::grantsPriority($fact, $this->settings),
                'ts' => $fact['created'],
                'terminal' => (bool) $fact['terminal'],
            );

            if (!$state['user_id'])
                return array($state, 'deferred');       // waiting for a paid event

            $this->reconcile($state);
            return array($state, 'ok');
        });
    }

    // --- shared ------------------------------------------------------------

    private function withCustomer($cus, $fn) {
        if (!$this->store->lock($cus))
            throw new StripeSyncRetryableError('Customer is locked');
        try {
            $state = $this->store->getCustomer($cus) ?: $this->newState();
            list($state, $result) = call_user_func($fn, $state);
            $this->store->putCustomer($cus, $state);
            return $result;
        }
        finally {
            $this->store->unlock($cus);
        }
    }

    /**
     * Bring the user in line with the subscriptions known for the customer.
     * $state is modified in place.
     */
    private function reconcile(array &$state) {
        $want = false;
        foreach ($state['subs'] as $s)
            $want = $want || ($s['priority'] && !$s['terminal']);

        if (!$state['user_id'])
            return;

        // The user may have been deleted by an agent: recreate it quietly.
        if (!$this->dir->userExists($state['user_id'])) {
            if (!$state['email'])
                throw new StripeSyncPermanentError('User vanished and e-mail unknown');
            $state['user_id'] = $this->dir->ensureUser($state['email'], null);
            $state['attached'] = false;
            $state['prev_org_id'] = null;
        }

        if ($want) {
            list($changed, $previous) = $this->dir->attachPriority($state['user_id']);
            if ($changed) {
                $state['prev_org_id'] = $previous;
                $state['attached'] = true;
                $this->log->info('Stripe sync: Priority granted',
                    sprintf('User #%d', $state['user_id']));
            }
        }
        elseif ($state['attached']) {
            $this->release($state);
        }
    }

    // Take the user out of the Priority organization, if we put it there.
    private function release(array &$state) {
        if ($state['attached'] && $state['user_id']) {
            if ($this->dir->detachPriority($state['user_id'], $state['prev_org_id']))
                $this->log->info('Stripe sync: Priority ended',
                    sprintf('User #%d', $state['user_id']));
        }
        $state['attached'] = false;
        $state['prev_org_id'] = null;
    }
}
