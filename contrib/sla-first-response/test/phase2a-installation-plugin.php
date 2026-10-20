<?php
/*********************************************************************
    phase2a-installation-plugin.php

    Phase 2a : installe et configure le plugin comme depuis l'interface
    d'administration (dont les refus de configuration invalide).
    Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

$watch = sla_id('Priority 4h');
$done = sla_id('Priority 4h - 1re reponse faite');
$active = sla_id('Default SLA');

$pm = new PluginManager();
$plugin = $pm->install('plugins/sla_first_response');
check('plugin installé (registre)', $plugin && $plugin->getId() > 0);
$plugin = Plugin::lookup($plugin->getId())->getImpl();
check('classe du plugin chargée', $plugin instanceof SlaFirstResponsePlugin, get_class($plugin));
check('une seule instance autorisée (isMultiInstance=false)', $plugin->isMultiInstance() === false);

// NB : Plugin::addInstance() crée la ligne d'instance AVANT de valider la
// configuration (comportement du cœur) : on nettoie entre les essais.
function clean_instances() { global $plugin; db_query('DELETE FROM ' . PLUGIN_INSTANCE_TABLE); $plugin->instances->reset(); }

// Configuration invalide : plan « fait » actif
$errors = array();
$i = $plugin->addInstance(array('name' => 'essai-1', 'isactive' => 1, 'sla_watch' => $watch, 'sla_done' => $active), $errors);
check('refus : plan « réponse faite » actif', $errors['sla_done'] != '', json_encode($errors));
clean_instances();

$errors = array();
$i = $plugin->addInstance(array('name' => 'essai-2', 'isactive' => 1, 'sla_watch' => $watch, 'sla_done' => $watch), $errors);
check('refus : deux plans identiques', $errors['sla_done'] != '', json_encode($errors));
clean_instances();

$errors = array();
$i = $plugin->addInstance(array('name' => 'essai-3', 'isactive' => 1, 'sla_watch' => 9999, 'sla_done' => $done), $errors);
check('refus : plan inexistant', $errors['sla_watch'] != '', json_encode($errors));
clean_instances();

// Configuration valide
$errors = array();
$i = $plugin->addInstance(array('name' => 'SLA premiere reponse', 'isactive' => 1, 'sla_watch' => $watch, 'sla_done' => $done), $errors);
check('instance créée avec une configuration valide', (bool) $i && !$errors, json_encode($errors));
$ri = PluginInstance::objects()->one();
check('instance activée et configurée', $ri && $ri->isEnabled() && $ri->getConfig()->get('sla_watch') == $watch && $ri->getConfig()->get('sla_done') == $done);
$plugin->isactive = 1;
$plugin->save(true);
check('plugin actif (relu en base)', db_result(db_query('SELECT isactive FROM ' . PLUGIN_TABLE . ' WHERE id=' . (int) $plugin->getId())) == 1);
echo "  watch=$watch done=$done\n";
echo "\nPhase 2a : " . ($nfail ? $nfail . " échec(s)" : 'ok') . "\n";
