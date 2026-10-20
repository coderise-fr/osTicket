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

    Per customer exclusion uses a MySQL/MariaDB advisory lock
    (GET_LOCK), released automatically if the connection drops.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once dirname(__FILE__) . '/class.stripesync.service.php';

class StripeSyncDbStore implements StripeSyncStore {

    const NS = 'stripesync';
    // A claim older than this is considered abandoned (crashed request).
    const CLAIM_TTL = 300;
    // Idempotency markers are kept this long (Stripe retries for 3 days).
    const EVENT_RETENTION_DAYS = 30;

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
