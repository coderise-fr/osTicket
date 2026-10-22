<?php
/*********************************************************************
    plugin.php

    Manifest read by osTicket's plugin manager (include/plugins/<dir>/).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.
**********************************************************************/

return array(
    'id' =>             'coderise:stripe-sync',
    'version' =>        '0.2',
    'name' =>           'Stripe sync (portal accounts and Priority support)',
    'author' =>         'Coderise',
    'description' =>    'Receives Stripe webhooks, gives paying customers a portal account and keeps the Priority organization in line with their subscription.',
    'url' =>            'https://github.com/coderise-fr/osTicket',
    'ost_version' =>    '1.18',
    'plugin' =>         'stripe-sync.php:StripeSyncPlugin',
);
