<?php
/*********************************************************************
    phase4a-niveau-contrat-config.php

    Phase 4a : niveau de contrat (API des thèmes), configuration.
    - fonction définie au bootstrap, niveau standard tant que rien n'est
      configuré ;
    - organisations et clients de test ;
    - proposition de l'organisation du plugin Stripe (installé ici pour
      le test, sans être modifié), puis plugin SLA qui fonctionne seul ;
    - validation à l'enregistrement : organisation inexistante refusée,
      nom résolu en identifiant.
    Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

$watch = sla_id('Priority 4h');
$done = sla_id('Priority 4h - 1re reponse faite');

echo "== C0 : avant configuration du niveau de contrat\n";
check('coderise_contract_level() définie par le bootstrap du plugin', function_exists('coderise_contract_level'));
check('classe SlaContractLevel chargée', class_exists('SlaContractLevel', false));
$r = coderise_contract_level();
check('aucun client connecté : standard, source default', $r['level'] === 'standard' && $r['source'] === 'default'
    && $r['org_id'] === null && $r['org_name'] === null && $r['label'] === 'Standard', json_encode($r));

echo "== Préparation : organisations et clients\n";
$prio = org('Priority');
$acme = org('Acme SARL');
$ancienne = org('Ancienne Org');
client_user('cl-prio@example.com', 'Client Prio', $prio);
client_user('cl-acme@example.com', 'Client Acme', $acme);
client_user('cl-sans@example.com', 'Client Sans Org', null);
client_user('cl-collab@example.com', 'Collegue Acme', $acme);
client_user('cl-abo@example.com', 'Client Abonne', $ancienne);
client_user('cl-invite@example.com', 'Client Invite', $prio);
check('organisations et clients créés', $prio->getId() && $acme->getId() && uid_by_email('cl-prio@example.com') > 0);
echo "  priority_org=" . $prio->getId() . " acme=" . $acme->getId() . " ancienne=" . $ancienne->getId() . "\n";

$u = User::lookup(uid_by_email('cl-prio@example.com'));
$r = coderise_contract_level($u);
check('client de l\'org « Priority » mais organisation non configurée : standard, source default',
    $r['level'] === 'standard' && $r['source'] === 'default' && $r['org_id'] == $prio->getId(), json_encode($r));

echo "== C1 : proposition de l'organisation du plugin Stripe\n";
$sla = PluginManager::allInstalled();
$slaPlugin = null;
foreach ($sla as $p) if ($p instanceof SlaFirstResponsePlugin) $slaPlugin = $p;
check('plugin SLA trouvé', (bool) $slaPlugin);
check('stripe-sync non installé : aucune proposition (le plugin SLA fonctionne seul)',
    SlaFirstResponsePlugin::stripeSyncOrgName() === null && $slaPlugin->getNewInstanceDefaults(array()) === array());

// Le plugin Stripe tel qu'il est dans le dépôt (copié par run.ps1, non modifié)
$sp = $ost->plugins->install('plugins/stripe-sync');
check('plugin stripe-sync installé (instance de test)', (bool) $sp, $sp ? '' : 'install failed');
if ($sp) {
    $sp = PluginManager::lookup($sp->getId());
    $errors = array();
    $si = $sp->addInstance(array(
        'name' => 'Stripe sync', 'isactive' => 1, 'notes' => '',
        'org_name' => 'Priority', 'price_ids' => 'price_prio', 'product_ids' => '',
        'active_statuses' => 'active, trialing', 'accept_test' => '',
    ), $errors);
    check('instance stripe-sync configurée (org_name = Priority)', $si && !$errors, json_encode($errors));
    // Contrôles dans un nouveau processus (registre des plugins relu au
    // démarrage, comme lors de l'affichage du formulaire d'administration).
    $code = <<<'PHP'
<?php
require '/tmp/common.php';
foreach (PluginManager::allInstalled() as $p) if ($p instanceof SlaFirstResponsePlugin) $slaPlugin = $p;
check('proposition = organisation de la configuration Stripe', SlaFirstResponsePlugin::stripeSyncOrgName() === 'Priority',
    var_export(SlaFirstResponsePlugin::stripeSyncOrgName(), true));
$d = $slaPlugin->getNewInstanceDefaults(array());
check('nouvelle instance : contract_org pré-rempli avec « Priority »', @$d['contract_org'] === 'Priority', json_encode($d));
putenv('STRIPE_SYNC_PRIORITY_ORG=Priority Env');
check('variable STRIPE_SYNC_PRIORITY_ORG prioritaire (même ordre que stripe-sync)',
    SlaFirstResponsePlugin::stripeSyncOrgName() === 'Priority Env');
putenv('STRIPE_SYNC_PRIORITY_ORG');
// Formulaire de l'instance existante, organisation pas encore saisie : proposition en aide
$f = sla_instance()->getConfig()->getForm()->getField('contract_org');
check('formulaire : l\'aide propose l\'organisation Stripe', strpos($f->get('hint'), 'Proposition (configuration du plugin Stripe) : « Priority »') !== false, $f->get('hint'));
check('formulaire : aide « même organisation que celle utilisée par le plugin Stripe »',
    strpos($f->get('hint'), 'même organisation que celle utilisée par le plugin Stripe') !== false);
check('le formulaire ne pré-remplit pas une instance existante (organisation non configurée)', sla_instance()->getConfig()->get('contract_org') == '');
PHP;
    file_put_contents('/tmp/phase4a-proposition.php', $code);
    $out = shell_exec('php -d date.timezone=UTC /tmp/phase4a-proposition.php 2>&1');
    echo $out;
    $nfail += substr_count($out, '[FAIL]');
    check('contrôles de la proposition exécutés', substr_count($out, '[OK]') >= 6, substr_count($out, '[OK]') . ' OK');
    // Le plugin Stripe reste installé mais désactivé (il n'intervient plus dans les tests).
    $sp->isactive = 0;
    $sp->save();
}

echo "== C2 : validation à l'enregistrement\n";
// Mise à jour de l'instance comme le formulaire d'administration (POST).
function update_contract($vars) {
    global $watch, $done;
    $ri = sla_instance();
    $ri->_config = null; $ri->_form = null;
    $all = array_merge(array('name' => 'SLA premiere reponse', 'isactive' => 1, 'notes' => '',
        'sla_watch' => $watch, 'sla_done' => $done,
        'contract_org' => '', 'contract_label_priority' => 'Priority', 'contract_label_standard' => 'Standard'), $vars);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $all;
    $errors = array();
    $ok = $ri->update($all, $errors);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = array();
    $c = new Config($ri->getNamespace());
    return array($ok, $errors, $c->get('contract_org'));
}
list($ok, $errors, $stored) = update_contract(array('contract_org' => 'Organisation Inexistante'));
check('refus : organisation inexistante (nom)', !$ok && @$errors['contract_org'] != '', json_encode($errors));
list($ok, $errors, $stored) = update_contract(array('contract_org' => '999999'));
check('refus : organisation inexistante (identifiant)', !$ok && @$errors['contract_org'] != '', json_encode($errors));
check('rien d\'enregistré après un refus', (string) $stored === '', var_export($stored, true));
list($ok, $errors, $stored) = update_contract(array('contract_org' => 'Priority'));
check('accepté : nom « Priority », enregistré sous forme d\'identifiant', $ok && !$errors && $stored === (string) $prio->getId(),
    json_encode(array($errors, $stored)));
list($ok, $errors, $stored) = update_contract(array('contract_org' => (string) $prio->getId()));
check('accepté : identifiant', $ok && !$errors && $stored === (string) $prio->getId(), json_encode(array($errors, $stored)));
$c = new Config(sla_instance()->getNamespace());
check('plans de SLA conservés', $c->get('sla_watch') == $watch && $c->get('sla_done') == $done);
check('libellés par défaut enregistrés', $c->get('contract_label_priority') === 'Priority' && $c->get('contract_label_standard') === 'Standard');
$ri = sla_instance();
$ri->_config = null; $ri->_form = null;
$f = $ri->getConfig()->getForm()->getField('contract_org');
check('formulaire : aide « Actuellement : « Priority » (n° …) »', strpos($f->get('hint'), 'Actuellement : « Priority »') !== false, $f->get('hint'));
// Le type d'événement n'est pas encore créé ici s'il n'y a eu aucune bascule ; la phase 2b en a fait.
check('type d\'événement « sla_first_response » présent dans la table event (créé à la 1re bascule)',
    db_result(db_query('SELECT COUNT(*) FROM ' . EVENT_TABLE . ' WHERE name="sla_first_response"')) == 1);

echo "\nPhase 4a : " . ($nfail ? $nfail . " échec(s)" : 'ok') . "\n";
