<?php
/*********************************************************************
    stripe-sync.php

    osTicket plugin: Stripe webhooks -> portal accounts + Priority
    organization. See README.md and docs/stripe-integration.md.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.dispatcher.php';
require_once dirname(__FILE__) . '/include/class.stripesync.settings.php';
require_once dirname(__FILE__) . '/include/class.stripesync.store.php';
require_once dirname(__FILE__) . '/include/class.stripesync.directory.php';
require_once dirname(__FILE__) . '/include/class.stripesync.controller.php';

/**
 * Admin form. Only NON secret settings live here (they are stored in the
 * database and visible to administrators). The webhook signing secret is
 * read from the environment: see StripeSyncSettings.
 */
class StripeSyncPluginConfig extends PluginConfig {

    function getOptions() {
        return array(
            'org_name' => new TextboxField(array(
                'label' => 'Priority organization',
                'hint' => 'Name of the osTicket organization given to customers with an active Priority subscription (the ticket filter matches on it). Created if missing.',
                'default' => 'Priority',
                'configuration' => array('size' => 40, 'length' => 128),
            )),
            'price_ids' => new TextboxField(array(
                'label' => 'Priority Stripe prices',
                'hint' => 'Stripe price ids (price_...) of the Priority Support subscription, separated by commas.',
                'configuration' => array('size' => 60, 'length' => 1024),
            )),
            'product_ids' => new TextboxField(array(
                'label' => 'Priority Stripe products',
                'hint' => 'Optional: Stripe product ids (prod_...). A subscription containing one of them also counts.',
                'configuration' => array('size' => 60, 'length' => 1024),
            )),
            'active_statuses' => new TextboxField(array(
                'label' => 'Subscription statuses that keep Priority',
                'hint' => 'Default: active, trialing. A past_due subscription (payment failed) loses Priority until Stripe sets it back to active.',
                'default' => 'active, trialing',
                'configuration' => array('size' => 60, 'length' => 256),
            )),
            'accept_test' => new BooleanField(array(
                'label' => 'Accept test mode events',
                'configuration' => array('desc' => 'Only for a staging helpdesk. Leave off in production.'),
            )),
        );
    }
}

class StripeSyncPlugin extends Plugin {
    var $config_class = 'StripeSyncPluginConfig';

    function bootstrap() {
        $config = $this->getConfig();
        $info = $config ? $config->getInfo() : array();

        // The `api` signal is sent by api/http.php before routing: the
        // route below is therefore served at /api/stripe-sync/webhook
        // (api/.htaccess) or /api/http.php/stripe-sync/webhook.
        Signal::connect('api', function ($dispatcher) use ($info) {
            $settings = new StripeSyncSettings($info);
            $service = new StripeSyncService(
                new StripeSyncDbStore(),
                new StripeSyncOsTicketDirectory($settings),
                $settings,
                new StripeSyncOstLogger());
            $controller = new StripeSyncController($settings, $service);
            $dispatcher->append(
                url_post('^/stripe-sync/webhook$', array($controller, 'webhook')));
        });
    }
}
