<?php
/*********************************************************************
    class.stripesync.mapper.php

    Turns a decoded Stripe event into a small, normalized "fact" the
    service can act on. Pure PHP, no dependency on osTicket nor on the
    Stripe SDK: only the fields documented in the Stripe API reference are
    read, and every field is validated before use (the payload is
    authenticated by its signature, but never trusted blindly).

    Fact kinds:
      paid           - a purchase was paid: customer gets a portal account
      subscription   - state of one subscription changed
      payment_failed - informational (logged), no access change

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class StripeSyncMapper {

    // Statuses after which a subscription can never become active again.
    static $TERMINAL_STATUSES = array('canceled', 'incomplete_expired');

    /**
     * Identifiers that are written in logs and in the key/value store.
     * Stripe ids look like `cus_Nf8x...`, `sub_1M...`, `evt_1O...`.
     */
    static function isId($value, $prefix=null) {
        if (!is_string($value) || strlen($value) > 64)
            return false;
        $re = $prefix
            ? '/^'.preg_quote($prefix, '/').'_[A-Za-z0-9]+$/'
            : '/^[a-z0-9]+_[A-Za-z0-9]+$/';
        return (bool) preg_match($re, $value);
    }

    private static function str($arr, $key) {
        return (is_array($arr) && isset($arr[$key]) && is_string($arr[$key]))
            ? $arr[$key] : null;
    }

    // A reference to an object is an id, or the object itself if expanded.
    private static function ref($value) {
        if (is_array($value) && isset($value['id']))
            $value = $value['id'];
        return is_string($value) ? $value : null;
    }

    /**
     * Returns an array describing what to do, or null for event types (or
     * states) that this integration deliberately ignores.
     */
    static function map(array $event) {
        $type = self::str($event, 'type');
        $obj = (isset($event['data']['object']) && is_array($event['data']['object']))
            ? $event['data']['object'] : null;
        if (!$type || !$obj)
            return null;

        $base = array(
            'event_id' => self::str($event, 'id'),
            'event_type' => $type,
            'created' => isset($event['created']) ? (int) $event['created'] : 0,
        );

        switch ($type) {
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            // Delayed payment methods (SEPA...) complete the session before
            // the money is there: wait for async_payment_succeeded.
            $ps = self::str($obj, 'payment_status');
            if (!in_array($ps, array('paid', 'no_payment_required'), true))
                return null;
            $details = isset($obj['customer_details']) ? $obj['customer_details'] : array();
            return $base + array(
                'kind' => 'paid',
                'customer' => self::ref(isset($obj['customer']) ? $obj['customer'] : null),
                'email' => self::str($details, 'email') ?: self::str($obj, 'customer_email'),
                'name' => self::str($details, 'name'),
            );

        case 'invoice.paid':
            // A paid invoice proves the purchase and carries the e-mail of
            // the customer: used to learn the customer<->e-mail link when
            // the subscription was not created through Checkout.
            return $base + array(
                'kind' => 'paid',
                'customer' => self::ref(isset($obj['customer']) ? $obj['customer'] : null),
                'email' => self::str($obj, 'customer_email'),
                'name' => self::str($obj, 'customer_name'),
            );

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            $prices = array();
            $products = array();
            $items = isset($obj['items']['data']) && is_array($obj['items']['data'])
                ? $obj['items']['data'] : array();
            foreach ($items as $item) {
                $price = isset($item['price']) ? $item['price'] : null;
                if (($id = self::ref($price)))
                    $prices[] = $id;
                if (is_array($price) && ($p = self::ref(isset($price['product']) ? $price['product'] : null)))
                    $products[] = $p;
            }
            $status = self::str($obj, 'status');
            $deleted = ($type == 'customer.subscription.deleted');
            return $base + array(
                'kind' => 'subscription',
                'subscription' => self::str($obj, 'id'),
                'customer' => self::ref(isset($obj['customer']) ? $obj['customer'] : null),
                'status' => $deleted ? 'canceled' : $status,
                'prices' => array_values(array_unique($prices)),
                'products' => array_values(array_unique($products)),
                'terminal' => $deleted || in_array($status, self::$TERMINAL_STATUSES, true),
            );

        case 'invoice.payment_failed':
            return $base + array(
                'kind' => 'payment_failed',
                'customer' => self::ref(isset($obj['customer']) ? $obj['customer'] : null),
                'invoice' => self::str($obj, 'id'),
            );
        }

        return null;
    }

    /**
     * Does this subscription fact grant the Priority support?
     * $settings is a StripeSyncSettings.
     */
    static function grantsPriority(array $fact, $settings) {
        if (!empty($fact['terminal']))
            return false;
        if (!in_array($fact['status'], $settings->getActiveStatuses(), true))
            return false;
        if (array_intersect($fact['prices'], $settings->getPriorityPriceIds()))
            return true;
        if (array_intersect($fact['products'], $settings->getPriorityProductIds()))
            return true;
        return false;
    }
}
