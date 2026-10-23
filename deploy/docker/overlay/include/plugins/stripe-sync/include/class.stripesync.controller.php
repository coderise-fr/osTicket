<?php
/*********************************************************************
    class.stripesync.controller.php

    HTTP side of the plugin: POST /api/stripe-sync/webhook

    Order of operations (cheapest and most restrictive first):
      1. size limit on the body;
      2. signature check on the RAW body (HMAC-SHA256, 5 min tolerance);
      3. JSON decoding and basic shape check;
      4. test/live mode check;
      5. hand over to StripeSyncService.

    Answers: 200 processed/ignored/duplicate, 400 bad signature or body,
    413 too large, 500/503 transient failure (Stripe redelivers for 3 days).
    Error details are logged, never sent back to the caller.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once dirname(__FILE__) . '/class.stripesync.signature.php';
require_once dirname(__FILE__) . '/class.stripesync.service.php';

/** Writes to the osTicket system log (Admin Panel > Dashboard > System Logs). */
class StripeSyncOstLogger implements StripeSyncLogger {
    // Messages only contain Stripe ids and numeric ids, never names or
    // e-mail addresses: the system log must not become a copy of customer
    // data and cannot carry markup.
    function info($title, $message) {
        global $ost;
        $ost->logInfo($title, $message, false);
    }
    function warning($title, $message) {
        global $ost;
        $ost->logWarning($title, $message, false);
    }
    function error($title, $message) {
        global $ost;
        $ost->logError($title, $message, true);
    }
}

class StripeSyncController {

    // A Stripe event is a few KB; a subscription with many items stays far
    // below this.
    const MAX_BODY = 524288;

    private $settings;
    private $service;

    function __construct($settings, StripeSyncService $service) {
        $this->settings = $settings;
        $this->service = $service;
    }

    // Not Http::response(): it only knows a handful of status codes and
    // turns 413/503 into 500.
    private function reply($code, $text) {
        http_response_code($code);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        print $text;
        exit;
    }

    private function logError($message) {
        global $ost;
        if ($ost)
            $ost->logError('Stripe sync', $message, false);
    }

    /**
     * Entry point registered on the `api` dispatcher.
     */
    function webhook() {
        $secret = $this->settings->getWebhookSecret();
        if (!$secret) {
            $this->logError('STRIPE_SYNC_WEBHOOK_SECRET (or ..._FILE) is not set or is not a whsec_ secret');
            return $this->reply(503, 'Not configured');
        }

        // php://input, not $_POST: the signature covers the exact bytes.
        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);
        if ($raw === false || $raw === '')
            return $this->reply(400, 'Empty body');
        if (strlen($raw) > self::MAX_BODY)
            return $this->reply(413, 'Body too large');

        $header = isset($_SERVER['HTTP_STRIPE_SIGNATURE'])
            ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        try {
            StripeSyncSignature::verify($raw, $header, $secret);
        }
        catch (StripeSyncSignatureException $e) {
            // Not logged at warning level on purpose: anybody can send junk
            // to a public URL and flood the system log.
            return $this->reply(400, 'Invalid signature');
        }

        $event = json_decode($raw, true);
        if (!is_array($event) || !isset($event['id'], $event['type'], $event['data']['object']))
            return $this->reply(400, 'Invalid event');

        if (empty($event['livemode']) && !$this->settings->acceptTestEvents())
            return $this->reply(200, 'Test event ignored');

        try {
            $result = $this->service->handle($event);
        }
        catch (StripeSyncRetryableError $e) {
            $this->logError($e->getMessage());
            return $this->reply(503, 'Try again later');
        }
        catch (Throwable $e) {
            $this->logError(sprintf('%s: %s', get_class($e), $e->getMessage()));
            return $this->reply(500, 'Internal error');
        }

        return $this->reply(200, $result);
    }
}
