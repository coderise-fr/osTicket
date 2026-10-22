<?php
/*********************************************************************
    class.stripesync.store.php

    Persistence of the Stripe sync plugin, built on the generic
    key/value table of osTicket (CONFIG_TABLE: namespace, key, value,
    updated). No schema change, nothing to migrate when osTicket is
    upgraded, and the rows are removed by Config::destroy() semantics
    (namespace `stripesync`).

      evt:<event id>   idempotency marker ('p:<time>' in progress, 'done')
      cus:<cus id>     JSON state of a Stripe customer
      sub:<sub id>     record of a Priority subscription, read by OTHER modules
      priority_org_id  id of the Priority organization, read by OTHER modules
                       (since 0.2; see StripeSyncPriorityOrgResolver)
      priority_org_name  configured name when that id was remembered (internal)

    The `sub:` rows are a published interface (documented in
    docs/stripe-integration.md and in the README, "Donnees exposees a
    d'autres modules"): namespace `stripesync`, key `sub:<subscription id>`
    (sha1 of the id if the key would exceed 64 characters), value

        <osTicket user id>|<start_date>|<ended>|<status>

    start_date = `start_date` of the Stripe subscription (epoch UTC,
    seconds, first start, not the current period), ended = epoch UTC of
    the end or empty while it runs, status = Stripe status. No JSON, no
    space. Do not change this format without updating its readers.

    Per customer exclusion uses a MySQL/MariaDB advisory lock
    (GET_LOCK), released automatically if the connection drops.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once dirname(__FILE__) . '/class.stripesync.service.php';
require_once dirname(__FILE__) . '/class.stripesync.priorityorg.php';

class StripeSyncDbStore implements StripeSyncStore, StripeSyncOrgMemory {

    const NS = 'stripesync';
    // A claim older than this is considered abandoned (crashed request).
    const CLAIM_TTL = 300;
    // Idempotency markers are kept this long (Stripe retries for 3 days).
    const EVENT_RETENTION_DAYS = 30;
    // Published: id of the Priority organization (decimal integer).
    const PRIORITY_ORG_KEY = 'priority_org_id';
    // Internal: configured name when that id was remembered (a changed
    // setting is followed, a renamed organization is not lost).
    const PRIORITY_ORG_NAME_KEY = 'priority_org_name';
    // Lock name (lock() hashes it like a customer id; never a `cus_` id).
    const PRIORITY_ORG_LOCK = '~priority-org';

    private $clock;

    function __construct($clock=null) {
        $this->clock = $clock ?: function () { return time(); };
    }

    private function now() {
        return call_user_func($this->clock);
    }

    // `key` is limited to 64 characters in the table.
    private function evKey($eventId) {
        $k = 'evt:' . $eventId;
        return strlen($k) <= 64 ? $k : 'evt:' . sha1($eventId);
    }

    private function subKey($subscriptionId) {
        $k = 'sub:' . $subscriptionId;
        return strlen($k) <= 64 ? $k : 'sub:' . sha1($subscriptionId);
    }

    private function row($key) {
        $res = db_query(sprintf(
            'SELECT `value` FROM %s WHERE `namespace`=%s AND `key`=%s',
            CONFIG_TABLE, db_input(self::NS), db_input($key)));
        $row = $res ? db_fetch_row($res) : null;
        return $row ? $row[0] : null;
    }

    private function put($key, $value) {
        // Upsert: the unique key is (namespace, key).
        return db_query(sprintf(
            'INSERT INTO %1$s (`namespace`, `key`, `value`, `updated`)'
            .' VALUES (%2$s, %3$s, %4$s, NOW())'
            .' ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `updated`=NOW()',
            CONFIG_TABLE, db_input(self::NS), db_input($key), db_input($value)));
    }

    // --- idempotency ------------------------------------------------------

    function claimEvent($eventId) {
        $key = $this->evKey($eventId);
        $mine = 'p:' . $this->now();

        // Atomic: only one request can insert the row.
        db_query(sprintf(
            'INSERT IGNORE INTO %s (`namespace`, `key`, `value`, `updated`)'
            .' VALUES (%s, %s, %s, NOW())',
            CONFIG_TABLE, db_input(self::NS), db_input($key), db_input($mine)));
        if (db_affected_rows() == 1) {
            $this->purgeOldEvents();
            return 'claimed';
        }

        $cur = $this->row($key);
        if ($cur === 'done')
            return 'done';

        // In progress somewhere else, unless that claim is abandoned.
        $age = strncmp((string) $cur, 'p:', 2) === 0
            ? $this->now() - (int) substr($cur, 2) : self::CLAIM_TTL + 1;
        if ($age > self::CLAIM_TTL) {
            // Compare-and-swap so that two takeovers cannot both win.
            db_query(sprintf(
                'UPDATE %s SET `value`=%s, `updated`=NOW()'
                .' WHERE `namespace`=%s AND `key`=%s AND `value`=%s',
                CONFIG_TABLE, db_input($mine), db_input(self::NS),
                db_input($key), db_input((string) $cur)));
            if (db_affected_rows() == 1)
                return 'claimed';
        }
        return 'busy';
    }

    function completeEvent($eventId) {
        return $this->put($this->evKey($eventId), 'done');
    }

    function releaseEvent($eventId) {
        return db_query(sprintf(
            'DELETE FROM %s WHERE `namespace`=%s AND `key`=%s',
            CONFIG_TABLE, db_input(self::NS), db_input($this->evKey($eventId))));
    }

    // Housekeeping, run now and then (about 1 claim out of 50).
    private function purgeOldEvents() {
        if (random_int(1, 50) != 1)
            return;
        db_query(sprintf(
            'DELETE FROM %s WHERE `namespace`=%s AND `key` LIKE %s'
            .' AND `updated` < (NOW() - INTERVAL %d DAY)',
            CONFIG_TABLE, db_input(self::NS), db_input('evt:%'),
            self::EVENT_RETENTION_DAYS));
    }

    // --- customers ---------------------------------------------------------

    function getCustomer($customerId) {
        $json = $this->row('cus:' . $customerId);
        $state = $json ? json_decode($json, true) : null;
        return is_array($state) ? $state : null;
    }

    function putCustomer($customerId, array $state) {
        if (!$this->put('cus:' . $customerId, json_encode($state)))
            throw new StripeSyncRetryableError('Unable to save the customer state');
        return true;
    }

    // --- subscriptions (published to other modules) ------------------------

    function putSubscription($subscriptionId, $value) {
        $key = $this->subKey($subscriptionId);
        // Same value: leave the row (and its `updated`) untouched.
        if ($this->row($key) === (string) $value)
            return true;
        if (!$this->put($key, (string) $value))
            throw new StripeSyncRetryableError('Unable to save the subscription record');
        return true;
    }

    // --- Priority organization (published to other modules) ---------------

    /**
     * Id of the Priority organization remembered by the plugin, or null
     * (plugin never used yet). Same as reading the row directly:
     *
     *   SELECT `value` FROM ost_config
     *    WHERE `namespace`='stripesync' AND `key`='priority_org_id'
     *
     * The organization may have been deleted since: check that it exists.
     */
    static function readPriorityOrgId() {
        $res = db_query(sprintf(
            'SELECT `value` FROM %s WHERE `namespace`=%s AND `key`=%s',
            CONFIG_TABLE, db_input(self::NS), db_input(self::PRIORITY_ORG_KEY)));
        $row = $res ? db_fetch_row($res) : null;
        $id = $row ? (int) $row[0] : 0;
        return $id > 0 ? $id : null;
    }

    function getPriorityOrg() {
        $id = (int) $this->row(self::PRIORITY_ORG_KEY);
        $name = $this->row(self::PRIORITY_ORG_NAME_KEY);
        return array($id > 0 ? $id : null, $name);
    }

    function putPriorityOrg($id, $configuredName) {
        if (!$this->put(self::PRIORITY_ORG_KEY, (string) (int) $id)
                || !$this->put(self::PRIORITY_ORG_NAME_KEY, (string) $configuredName))
            throw new StripeSyncRetryableError('Unable to remember the Priority organization');
        return true;
    }

    function lockPriorityOrg() {
        return $this->lock(self::PRIORITY_ORG_LOCK);
    }

    function unlockPriorityOrg() {
        $this->unlock(self::PRIORITY_ORG_LOCK);
    }

    // --- locks ---------------------------------------------------------------

    private function lockName($customerId) {
        return 'ss:' . md5($customerId);        // 64 characters max
    }

    function lock($customerId) {
        $res = db_query(sprintf('SELECT GET_LOCK(%s, 10)',
            db_input($this->lockName($customerId))));
        $row = $res ? db_fetch_row($res) : null;
        return $row && $row[0] == 1;
    }

    function unlock($customerId) {
        db_query(sprintf('SELECT RELEASE_LOCK(%s)',
            db_input($this->lockName($customerId))));
    }
}
