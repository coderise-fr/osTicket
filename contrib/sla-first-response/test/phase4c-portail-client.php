<?php
/*********************************************************************
    phase4c-portail-client.php

    Phase 4c : niveau de contrat et événement « première réponse » sur le
    VRAI portail client (requêtes HTTP vers le serveur PHP du conteneur,
    sessions et bootstrap normaux d'osTicket).

    Une sonde est ajoutée au gabarit include/client/footer.inc.php de
    l'instance de TEST (copie jetable, jamais le dépôt) : elle appelle
    coderise_contract_level() depuis un gabarit de include/client/ et
    écrit le résultat dans un commentaire HTML.
    Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

$watch = sla_id('Priority 4h');
$done = sla_id('Priority 4h - 1re reponse faite');
$prio = Organization::lookup(array('name' => 'Priority'));
$acme = Organization::lookup(array('name' => 'Acme SARL'));
$ids = json_decode(file_get_contents('/tmp/phase4-tickets.json'), true);

// Sonde dans le gabarit de pied de page du portail (copie de test)
$footer = CLIENTINC_DIR . 'footer.inc.php';
$src = file_get_contents($footer);
if (strpos($src, 'sonde-niveau-contrat') === false) {
    $probe = "<?php /* sonde-niveau-contrat (test) */\n"
        . "if (function_exists('coderise_contract_level')) {\n"
        . "    echo '<!--CL:' . json_encode(coderise_contract_level()) . '-->';\n"
        . "    if (isset(\$ticket) && \$ticket instanceof Ticket)\n"
        . "        echo '<!--CLT:' . json_encode(coderise_contract_level(\$ticket)) . '-->';\n"
        . "} else echo '<!--CL:none-->'; ?>\n";
    file_put_contents($footer, str_replace('</body>', $probe . '</body>', $src));
}
function no_php_error($html) {
    return !preg_match('/Fatal error|Parse error|Warning:|Notice:|Uncaught/', $html);
}
function login_client($email, $passwd = CLIENT_PASSWD) {
    $c = new PortalClient();
    $c->post('login.php', array('luser' => $email, 'lpasswd' => $passwd));
    return $c;
}

echo "== H1 : visiteur non connecté (index.php)\n";
$c = new PortalClient();
$h = $c->get('index.php');
$p = probe($h);
check('page servie', $c->status == 200 && strpos($h, '</html>') !== false, $c->status);
check('fonction chargée par le bootstrap du plugin sur le portail client', is_array($p), var_export($p, true));
check('non connecté : standard, source default', is_array($p) && $p['level'] === 'standard' && $p['source'] === 'default', json_encode($p));
check('aucune erreur PHP dans la page', no_php_error($h));

echo "== H2 : client Priority connecté\n";
$c = login_client('cl-prio@example.com');
check('connexion du client (cookie de session)', !empty($c->cookies), json_encode(array_keys($c->cookies)));
foreach (array('index.php', 'tickets.php', 'open.php', 'tickets.php?id=' . $ids['prio'], 'view.php?id=' . $ids['prio']) as $page) {
    $h = $c->get($page);
    $p = probe($h);
    check("$page : priority (client connecté)", is_array($p) && $p['level'] === 'priority' && $p['source'] === 'organisation'
        && $p['org_name'] === 'Priority' && $p['label'] === 'Priority', json_encode($p) . ' http=' . $c->status);
    check("$page : aucune erreur PHP", no_php_error($h));
}
$h = $c->get('tickets.php?id=' . $ids['prio']);
$pt = probe($h, 'CLT');
check('suivi de ticket : coderise_contract_level($ticket) dans le gabarit => priority', is_array($pt) && $pt['level'] === 'priority', json_encode($pt));

echo "== H3 : client d'une autre organisation\n";
$c2 = login_client('cl-acme@example.com');
$p = probe($c2->get('index.php'));
check('index.php : standard (Acme SARL)', is_array($p) && $p['level'] === 'standard' && $p['source'] === 'organisation'
    && $p['org_name'] === 'Acme SARL', json_encode($p));
$h = $c2->get('tickets.php?id=' . $ids['acme']);
$pt = probe($h, 'CLT');
check('son ticket (collaborateur Priority) : standard', is_array($pt) && $pt['level'] === 'standard', json_encode($pt));

echo "== H4 : invité (lien d'accès au ticket envoyé par e-mail, sans compte connecté)\n";
$tp = Ticket::lookup($ids['prio']);
$g = new PortalClient();
$h = $g->get('view.php?auth=' . urlencode($tp->getAuthToken($tp->getOwner())));
check('invité : suivi du ticket affiché', strpos($h, $tp->getSubject()) !== false, $g->status);
$p = probe($h);
$pt = probe($h, 'CLT');
check('invité : niveau du visiteur = standard, default', is_array($p) && $p['level'] === 'standard' && $p['source'] === 'default', json_encode($p));
check('invité : niveau du ticket consulté (propriétaire Priority) = priority', is_array($pt) && $pt['level'] === 'priority', json_encode($pt));

echo "== H5 : événement de bascule invisible pour le client, visible pour l'agent\n";
$t = Ticket::lookup($ids['prio']);
check('(préalable) ticket au plan surveillé', fresh($t->getId())['sla_id'] == $watch);
agent_reply($t, 'Bonjour, voici notre premiere reponse.', 'user');
check('bascule faite par le plugin', fresh($t->getId())['sla_id'] == $done);
// Témoin : un événement « edited » sla_id comme en version 0.2, sur un autre ticket
$tc = client_ticket('cl-prio@example.com', 'Client Prio', $watch, 'Ticket temoin 0.2');
$thisstaff = Staff::lookup(1);
$tc->logEvent('edited', array('sla_id' => array($watch, $done)));
$h = $c->get('tickets.php?id=' . $t->getId());
check('client : la réponse de l\'agent est bien affichée (fil rendu)', strpos($h, 'voici notre premiere reponse') !== false);
check('client : aucune mention du plan « 1re reponse faite » ni de la bascule', strpos($h, '1re reponse faite') === false
    && strpos($h, 'remplacé par') === false && strpos($h, 'première réponse adressée') === false);
$h = $c->get('tickets.php?id=' . $tc->getId());
check('(témoin 0.2) un événement « edited » sla_id EST affiché au client par le gabarit d\'origine',
    strpos($h, '1re reponse faite') !== false);
$s = new PortalClient();
$s->post('scp/login.php', array('userid' => 'ada_admin', 'passwd' => 'Zx9!kLm2#qWe7vTb'));
$h = $s->get('scp/tickets.php?id=' . $t->getId());
check('agent : l\'événement est affiché dans le fil', strpos($h, 'première réponse adressée au client') !== false
    && strpos($h, '1re reponse faite') !== false, 'http=' . $s->status);
check('agent : aucune erreur PHP', no_php_error($h));
// Conversion facultative des événements écrits par la version 0.2 (sql/masquer-evenements-0.2.sql)
$sql = preg_replace('/^--.*$/m', '', file_get_contents('/tmp/sql/masquer-evenements-0.2.sql'));
$sql = str_replace('ost_', TABLE_PREFIX, $sql);
$sql = preg_replace('/CONCAT\(\'\{"sla_id":\[\', \d+, \',\', \d+, \'\]\}\'\)/',
    "CONCAT('{\"sla_id\":[', $watch, ',', $done, ']}')", $sql);
$before = db_result(db_query('SELECT COUNT(*) FROM ' . THREAD_EVENT_TABLE . ' e JOIN ' . EVENT_TABLE . ' v ON v.id=e.event_id AND v.name="edited" WHERE e.data LIKE \'{"sla_id":%\''));
check('script de conversion exécuté', (bool) db_query(trim($sql)), db_error());
$after = db_result(db_query('SELECT COUNT(*) FROM ' . THREAD_EVENT_TABLE . ' e JOIN ' . EVENT_TABLE . ' v ON v.id=e.event_id AND v.name="edited" WHERE e.data LIKE \'{"sla_id":%\''));
check('anciens événements « edited » sla_id convertis', $before >= 1 && $after == 0, "$before -> $after");
$h = $c->get('tickets.php?id=' . $tc->getId());
check('(témoin 0.2 converti) le client ne voit plus la bascule', strpos($h, '1re reponse faite') === false);
$h = $s->get('scp/tickets.php?id=' . $tc->getId());
check('(témoin 0.2 converti) l\'agent la voit toujours', strpos($h, 'première réponse adressée au client') !== false
    && strpos($h, '1re reponse faite') !== false);

echo "== H6 : fin d'abonnement sur l'instance réelle (une requête = un cache neuf)\n";
$ca = login_client('cl-abo@example.com');
$abo = User::lookup(uid_by_email('cl-abo@example.com'));
$previous = Organization::lookup((int) $abo->getOrgId());
check('avant : standard', probe($ca->get('index.php'))['level'] === 'standard');
$abo->setOrganization($prio);
check('rangé dans Priority (attachPriority) : priority à la requête suivante', probe($ca->get('index.php'))['level'] === 'priority');
$prio->removeUser($abo);
$abo->setOrganization($previous);
$p = probe($ca->get('index.php'));
check('organisation d\'origine restaurée (detachPriority) : standard', $p['level'] === 'standard' && $p['org_name'] === $previous->getName(), json_encode($p));

echo "== H7 : organisation Priority supprimée\n";
$prio->delete();
$h = $c->get('index.php');
$p = probe($h);
check('client (ex-)Priority : standard, default, sans erreur', is_array($p) && $p['level'] === 'standard' && $p['source'] === 'default', json_encode($p));
check('aucune erreur PHP', no_php_error($h));
$ri = sla_instance();
$f = $ri->getConfig()->getForm()->getField('contract_org');
check('formulaire : avertissement « l\'organisation enregistrée n\'existe plus »', strpos($f->get('hint'), 'n\'existe plus') !== false, $f->get('hint'));

echo "\nPhase 4c : " . ($nfail ? $nfail . " échec(s)" : 'ok') . "\n";
