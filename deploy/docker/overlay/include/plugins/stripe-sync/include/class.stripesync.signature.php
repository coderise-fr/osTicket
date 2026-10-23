<?php
/*********************************************************************
    class.stripesync.signature.php

    Verification of the `Stripe-Signature` header of Stripe webhooks.

    Pure PHP, no dependency on osTicket: can be unit tested alone.
    Reference: https://docs.stripe.com/webhooks#verify-manually

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class StripeSyncSignatureException extends Exception {}

class StripeSyncSignature {

    // Default tolerance, in seconds, between the timestamp signed by
    // Stripe and the local clock (same default as the official libraries).
    const DEFAULT_TOLERANCE = 300;

    /**
     * Parse a header like `t=1492774577,v1=5257...,v0=6ffb...`.
     *
     * Only the scheme `v1` is kept: other schemes (v0 is a fake scheme
     * sent for test events) must be ignored to prevent downgrade attacks.
     *
     * Returns array(int $timestamp, string[] $signatures)
     * Throws StripeSyncSignatureException when the header is unusable.
     */
    static function parseHeader($header) {
        if (!is_string($header) || $header === '')
            throw new StripeSyncSignatureException('Missing signature header');

        $timestamp = null;
        $signatures = array();
        foreach (explode(',', $header) as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) != 2)
                continue;
            list($scheme, $value) = $pair;
            if ($scheme === 't' && $timestamp === null) {
                if (!ctype_digit($value))
                    throw new StripeSyncSignatureException('Malformed timestamp');
                $timestamp = (int) $value;
            }
            elseif ($scheme === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || !$signatures)
            throw new StripeSyncSignatureException('Malformed signature header');

        return array($timestamp, $signatures);
    }

    /**
     * Compute the expected `v1` signature for a payload.
     */
    static function compute($payload, $timestamp, $secret) {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /**
     * Verify that $payload (the RAW request body, byte for byte) was signed
     * by Stripe with $secret (the endpoint secret, `whsec_...`).
     *
     * Several `v1` signatures can be present while a secret is being rolled
     * (Stripe signs once per active secret): one valid match is enough.
     *
     * Returns true; throws StripeSyncSignatureException otherwise.
     */
    static function verify($payload, $header, $secret,
            $tolerance=self::DEFAULT_TOLERANCE, $now=null) {

        // Fail closed: never accept anything without a secret.
        if (!is_string($secret) || $secret === '')
            throw new StripeSyncSignatureException('No signing secret configured');

        list($timestamp, $signatures) = self::parseHeader($header);

        $expected = self::compute($payload, $timestamp, $secret);
        $match = false;
        // No early exit: compare against every signature, in constant time.
        foreach ($signatures as $sig)
            $match = hash_equals($expected, $sig) || $match;

        if (!$match)
            throw new StripeSyncSignatureException('No signature matches the payload');

        $now = $now ?? time();
        if ($tolerance > 0 && abs($now - $timestamp) > $tolerance)
            throw new StripeSyncSignatureException('Timestamp outside the tolerance zone');

        return true;
    }
}
