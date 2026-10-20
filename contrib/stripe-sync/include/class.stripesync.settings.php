<?php
/*********************************************************************
    class.stripesync.settings.php

    Settings of the Stripe sync plugin.

    Secrets (the webhook signing secret) are NEVER read from the
    database nor from the repository: only from the process environment
    or from a file outside the web root (Docker/Kubernetes secrets).
    Non secret settings come from the environment first, then from the
    plugin configuration form of the admin panel, then from defaults.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class StripeSyncSettings {

    const ENV_PREFIX = 'STRIPE_SYNC_';

    // Statuses of a Stripe subscription that keep the Priority support.
    // `past_due` is a grace period: Stripe is still retrying the payment.
    static $DEFAULT_ACTIVE_STATUSES = array('active', 'trialing', 'past_due');

    private $values;
    private $env;
    private $fileReader;

    /**
     * $config - array of the plugin configuration (admin form)
     * $env    - callable(string $name): string|false, defaults to getenv()
     * $fileReader - callable(string $path): string|false, defaults to
     *               file_get_contents (injected for tests)
     */
    function __construct(array $config=array(), $env=null, $fileReader=null) {
        $this->values = $config;
        $this->env = $env ?: function ($name) { return getenv($name); };
        $this->fileReader = $fileReader ?: function ($path) {
            return @file_get_contents($path);
        };
    }

    private function env($name) {
        $v = call_user_func($this->env, self::ENV_PREFIX . $name);
        return ($v === false || $v === null) ? null : trim((string) $v);
    }

    private function get($envName, $key, $default=null) {
        $v = $this->env($envName);
        if ($v !== null && $v !== '')
            return $v;
        if (isset($this->values[$key]) && trim((string) $this->values[$key]) !== '')
            return trim((string) $this->values[$key]);
        return $default;
    }

    /**
     * Webhook signing secret (`whsec_...`).
     * Order: STRIPE_SYNC_WEBHOOK_SECRET, then the file named by
     * STRIPE_SYNC_WEBHOOK_SECRET_FILE. Returns null when absent: callers
     * must then refuse every request (fail closed).
     */
    function getWebhookSecret() {
        $secret = $this->env('WEBHOOK_SECRET');
        if ($secret === null || $secret === '') {
            $file = $this->env('WEBHOOK_SECRET_FILE');
            if ($file) {
                $content = call_user_func($this->fileReader, $file);
                $secret = ($content === false) ? null : trim($content);
            }
        }
        if ($secret === null || $secret === '')
            return null;
        // Endpoint secrets are always `whsec_...`; anything else is a
        // configuration mistake (e.g. an API key pasted by error).
        if (strpos($secret, 'whsec_') !== 0)
            return null;
        return $secret;
    }

    /** Name of the osTicket organization used for Priority support. */
    function getOrgName() {
        return $this->get('PRIORITY_ORG', 'org_name', 'Priority');
    }

    private function csv($value) {
        $items = preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($items));
    }

    /** Stripe price ids (`price_...`) that mean "Priority Support". */
    function getPriorityPriceIds() {
        return $this->csv($this->get('PRIORITY_PRICE_IDS', 'price_ids', ''));
    }

    /** Stripe product ids (`prod_...`) that mean "Priority Support". */
    function getPriorityProductIds() {
        return $this->csv($this->get('PRIORITY_PRODUCT_IDS', 'product_ids', ''));
    }

    function hasPriorityCatalog() {
        return $this->getPriorityPriceIds() || $this->getPriorityProductIds();
    }

    function getActiveStatuses() {
        $v = $this->get('ACTIVE_STATUSES', 'active_statuses');
        return $v ? $this->csv($v) : self::$DEFAULT_ACTIVE_STATUSES;
    }

    /**
     * Whether test mode events (livemode=false) are processed. Off by
     * default so that a stray sandbox endpoint cannot grant anything on a
     * production helpdesk.
     */
    function acceptTestEvents() {
        $v = $this->get('ACCEPT_TEST_EVENTS', 'accept_test', '');
        return in_array(strtolower($v), array('1', 'true', 'yes', 'on'), true);
    }

    /** Minimum delay (seconds) between two invitation e-mails to one user. */
    function getInviteResendDelay() {
        return (int) $this->get('INVITE_RESEND_DELAY', 'invite_delay', 6 * 3600);
    }
}
