<?php
// Integration test harness only: one fresh osTicket process (like one web
// request: plugins bootstrapped, empty caches), prints JSON.
//   php probe.php level <e-mail>        coderise_contract_level(User)
//   php probe.php level-ticket <id>     coderise_contract_level(Ticket)
//   php probe.php sla-form              SLA plugin: contract_org hint + stored value + proposal
require '/tmp/boot.php';
require_once INCLUDE_DIR . 'class.ticket.php';

$what = $argv[1] ?? '';
$arg = $argv[2] ?? '';
$out = null;
switch ($what) {
case 'level':
    $out = function_exists('coderise_contract_level')
        ? coderise_contract_level(User::lookupByEmail($arg) ?: false) : 'undefined';
    break;
case 'level-ticket':
    $out = function_exists('coderise_contract_level')
        ? coderise_contract_level(Ticket::lookup((int) $arg) ?: false) : 'undefined';
    break;
case 'sla-form':
    $p = Plugin::objects()->filter(array('install_path' => 'plugins/sla_first_response'))->one();
    $i = PluginInstance::objects()->filter(array('plugin_id' => $p->getId()))->one();
    $impl = $p->getImpl();
    $out = array(
        'hint' => $i->getConfig()->getForm()->getField('contract_org')->get('hint'),
        'stored' => (string) $i->getConfig()->get('contract_org'),
        'proposal' => SlaFirstResponsePlugin::stripeSyncProposal(),
        'new_instance_defaults' => $impl->getNewInstanceDefaults(array()),
    );
    break;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
