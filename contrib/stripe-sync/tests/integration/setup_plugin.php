<?php
// Test harness only: installs and configures the plugin like the admin panel
// does (PluginManager::install, then Plugin::addInstance with the form values).
require '/tmp/boot.php';

$p = $ost->plugins->install('plugins/stripe-sync');
if (!$p) {
    echo "plugin install failed\n";
    exit(1);
}
$p = PluginManager::lookup($p->getId());
$p->isactive = 1;
$p->save();

$errors = array();
$i = $p->addInstance(array(
    'name' => 'Stripe sync', 'isactive' => 1, 'notes' => '',
    'org_name' => 'Priority', 'price_ids' => 'price_prio', 'product_ids' => '',
    'active_statuses' => 'active, trialing, past_due', 'accept_test' => '1',
), $errors);
if (!$i || $errors) {
    echo "plugin instance failed: " . json_encode($errors) . "\n";
    exit(1);
}
echo "plugin installed and enabled\n";
