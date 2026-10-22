<?php
/*********************************************************************
    plugin.php

    Descripteur du plugin « SLA : première réponse ».
    Voir contrib/sla-first-response/README.md

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
return array(
    'id' => 'coderise:sla-first-response',
    'version' => '0.4',
    'name' => 'SLA : première réponse',
    'author' => 'Coderise',
    'description' => 'Fait du plan de SLA un délai de première réponse : '
        . 'à la première réponse d\'un agent adressée au client, le ticket passe sur un plan '
        . 'de SLA désactivé, ce qui supprime l\'échéance et le statut '
        . '« en retard ». Expose aussi le niveau de contrat d\'un client '
        . '(organisation « Priority ») aux thèmes du portail : coderise_contract_level().',
    'url' => 'https://github.com/coderise-fr/osTicket',
    'plugin' => 'sla_first_response.php:SlaFirstResponsePlugin',
);
?>
