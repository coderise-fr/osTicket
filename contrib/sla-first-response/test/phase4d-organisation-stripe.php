<?php
/*********************************************************************
    phase4d-organisation-stripe.php

    Phase 4d (version 0.4) : organisation mémorisée par identifiant par le
    plugin Stripe (stripe-sync 0.2, ligne ost_config `stripesync` /
    `priority_org_id`).
    - proposition par défaut : identifiant mémorisé d'abord, puis l'ancien
      ordre (variable, champ org_name, « Priority ») ;
    - avertissement d'écart dans le formulaire quand l'organisation
      configurée ici n'est plus celle du plugin Stripe ;
    - la configuration enregistrée n'est jamais modifiée par l'affichage.
    Le plugin Stripe est celui installé (désactivé) en phase 4a ; la ligne
    `priority_org_id` est écrite ici comme il l'écrirait.
    Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

function stripe_remember($id) {
    db_query('DELETE FROM ' . CONFIG_TABLE . " WHERE `namespace`='stripesync' AND `key`='priority_org_id'");
    if ($id)
        db_query('INSERT INTO ' . CONFIG_TABLE . " (`namespace`, `key`, `value`, `updated`) VALUES ('stripesync', 'priority_org_id', "
            . db_input((string) $id) . ', NOW())');
}
function set_contract_org($value) {
    $ri = sla_instance();
    $c = new Config($ri->getNamespace());
    $c->set('contract_org', $value);
}
function contract_hint() {
    $ri = sla_instance();
    $ri->_config = null; $ri->_form = null;
    return $ri->getConfig()->getForm()->getField('contract_org')->get('hint');
}
function stored_contract_org() {
    $c = new Config(sla_instance()->getNamespace());
    return (string) $c->get('contract_org');
}

$slaPlugin = null;
foreach (PluginManager::allInstalled() as $p) if ($p instanceof SlaFirstResponsePlugin) $slaPlugin = $p;
$orgA = org('Prio Stripe A');
$orgB = org('Prio Stripe B');
$saved = stored_contract_org();

echo "== D1 : proposition par défaut\n";
stripe_remember(0);
check('aucun id mémorisé : stripeSyncOrgId() = null', SlaFirstResponsePlugin::stripeSyncOrgId() === null);
check('aucun id mémorisé : proposition = nom configuré du plugin Stripe (ancien ordre)',
    SlaFirstResponsePlugin::stripeSyncProposal() === 'Priority', var_export(SlaFirstResponsePlugin::stripeSyncProposal(), true));
stripe_remember($orgA->getId());
check('id mémorisé lu dans ost_config', SlaFirstResponsePlugin::stripeSyncOrgId() === (int) $orgA->getId());
check('id mémorisé : proposition = cet identifiant', SlaFirstResponsePlugin::stripeSyncProposal() === (string) $orgA->getId(),
    var_export(SlaFirstResponsePlugin::stripeSyncProposal(), true));
$d = $slaPlugin->getNewInstanceDefaults(array());
check('nouvelle instance : contract_org pré-rempli avec l\'identifiant mémorisé', @$d['contract_org'] === (string) $orgA->getId(), json_encode($d));
putenv('STRIPE_SYNC_PRIORITY_ORG=Autre Nom');
check('l\'identifiant mémorisé l\'emporte sur STRIPE_SYNC_PRIORITY_ORG', SlaFirstResponsePlugin::stripeSyncProposal() === (string) $orgA->getId());
putenv('STRIPE_SYNC_PRIORITY_ORG');
stripe_remember(987654);
check('id mémorisé d\'une organisation supprimée : repli sur le nom configuré', SlaFirstResponsePlugin::stripeSyncProposal() === 'Priority');
db_query('UPDATE ' . CONFIG_TABLE . " SET `value`='abc' WHERE `namespace`='stripesync' AND `key`='priority_org_id'");
check('valeur invalide : ignorée', SlaFirstResponsePlugin::stripeSyncOrgId() === null);

echo "== D2 : aide du formulaire, champ vide\n";
set_contract_org('');
stripe_remember($orgA->getId());
$h = contract_hint();
check('aide : proposition de l\'organisation utilisée par le plugin Stripe (nom et n°)',
    strpos($h, 'Proposition (organisation utilisée par le plugin Stripe) : « Prio Stripe A » (n° ' . $orgA->getId() . ')') !== false, $h);
check('champ vide : jamais pré-rempli pour une instance existante', stored_contract_org() === '');

echo "== D3 : avertissement d'écart\n";
set_contract_org((string) $orgA->getId());
$h = contract_hint();
check('même organisation des deux côtés : aucun avertissement', strpos($h, 'ATTENTION') === false
    && strpos($h, 'Actuellement : « Prio Stripe A »') !== false, $h);
// Renommage en base (le cache d'objets de ce processus peut garder l'ancien nom :
// seul l'identifiant compte).
db_query('UPDATE ' . ORGANIZATION_TABLE . " SET name='Prio Stripe A renommee' WHERE id=" . (int) $orgA->getId());
$h = contract_hint();
check('organisation renommée : toujours la même (identifiant), aucun avertissement', strpos($h, 'ATTENTION') === false
    && strpos($h, '(n° ' . $orgA->getId() . ')') !== false, $h);
stripe_remember($orgB->getId());
$h = contract_hint();
check('le plugin Stripe utilise une autre organisation : avertissement avec son nom et son n°',
    strpos($h, 'ATTENTION : le plugin Stripe rattache maintenant les clients Priority à l\'organisation « Prio Stripe B » (n° ' . $orgB->getId() . ')') !== false, $h);
check('avertissement : action recommandée (saisir l\'identifiant, filtre de tickets)',
    strpos($h, 'saisir ' . $orgB->getId() . ' dans ce champ') !== false && strpos($h, 'filtre de tickets') !== false, $h);
check('l\'affichage ne modifie pas la configuration', stored_contract_org() === (string) $orgA->getId());
stripe_remember(987654);
$h = contract_hint();
check('organisation mémorisée par Stripe supprimée : avertissement « il en créera une nouvelle »',
    strpos($h, 'n° 987654') !== false && strpos($h, 'il en créera une nouvelle') !== false, $h);
stripe_remember(0);
$h = contract_hint();
check('plugin Stripe sans id mémorisé (0.1, ou aucun abonnement) : aucun avertissement', strpos($h, 'ATTENTION') === false, $h);

echo "== D4 : enregistrement après l'action recommandée\n";
stripe_remember($orgB->getId());
set_contract_org((string) $orgB->getId());
$h = contract_hint();
check('organisation alignée : plus d\'avertissement', strpos($h, 'ATTENTION') === false && strpos($h, 'Prio Stripe B') !== false, $h);

// Remise en état pour les phases suivantes
stripe_remember(0);
set_contract_org($saved);

echo "\nPhase 4d : " . ($nfail ? $nfail . " échec(s)" : 'ok') . "\n";
