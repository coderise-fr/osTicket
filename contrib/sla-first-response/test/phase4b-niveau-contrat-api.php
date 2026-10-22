<?php
/*********************************************************************
    phase4b-niveau-contrat-api.php

    Phase 4b : niveau de contrat, comportement de coderise_contract_level()
    dans un nouveau processus (configuration chargée par le bootstrap
    normal d'osTicket, comme sur le portail).
    Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

$watch = sla_id('Priority 4h');
$prio = Organization::lookup(array('name' => 'Priority'));
$acme = Organization::lookup(array('name' => 'Acme SARL'));
$ancienne = Organization::lookup(array('name' => 'Ancienne Org'));
$U = function ($email) { return User::lookup(uid_by_email($email)); };
$keys = array('level', 'label', 'org_id', 'org_name', 'source');

echo "== A1 : règle de base (objet User)\n";
check('configuration chargée par le bootstrap : organisation Priority', SlaContractLevel::getConfiguredOrgId() == $prio->getId());
$r = coderise_contract_level($U('cl-prio@example.com'));
check('format du retour : exactement level, label, org_id, org_name, source', array_keys($r) === $keys, json_encode(array_keys($r)));
check('propriétaire dans l\'organisation Priority => priority', $r['level'] === 'priority' && $r['source'] === 'organisation'
    && $r['org_id'] === (int) $prio->getId() && $r['org_name'] === 'Priority' && $r['label'] === 'Priority', json_encode($r));
$r = coderise_contract_level($U('cl-acme@example.com'));
check('autre organisation => standard', $r['level'] === 'standard' && $r['source'] === 'organisation'
    && $r['org_id'] === (int) $acme->getId() && $r['org_name'] === 'Acme SARL' && $r['label'] === 'Standard', json_encode($r));
$r = coderise_contract_level($U('cl-sans@example.com'));
check('sans organisation => standard', $r['level'] === 'standard' && $r['source'] === 'organisation'
    && $r['org_id'] === null && $r['org_name'] === null, json_encode($r));

echo "== A2 : ticket => son PROPRIÉTAIRE, jamais un collaborateur\n";
$t = client_ticket('cl-prio@example.com', 'Client Prio', $watch, 'Ticket prio avec collaborateur Acme');
add_collab($t, 'cl-collab@example.com', 'Collegue Acme');
$t = reload($t->getId());
check('(préalable) un collaborateur actif d\'une autre organisation', $t->getNumCollaborators() == 1);
$r = coderise_contract_level($t);
check('propriétaire Priority + collaborateur Acme => priority', $r['level'] === 'priority' && $r['org_id'] === (int) $prio->getId(), json_encode($r));
$t2 = client_ticket('cl-acme@example.com', 'Client Acme', 0, 'Ticket Acme avec collaborateur Priority');
add_collab($t2, 'cl-invite@example.com', 'Client Invite');
$r = coderise_contract_level(reload($t2->getId()));
check('propriétaire Acme + collaborateur Priority => standard', $r['level'] === 'standard' && $r['org_id'] === (int) $acme->getId(), json_encode($r));
file_put_contents('/tmp/phase4-tickets.json', json_encode(array('prio' => $t->getId(), 'acme' => $t2->getId())));

echo "== A3 : client connecté (null) / EndUser / invité\n";
global $thisclient;
$thisclient = null;
$r = coderise_contract_level();
check('null sans client connecté => standard, default', $r['level'] === 'standard' && $r['source'] === 'default' && $r['org_id'] === null, json_encode($r));
$thisclient = new ClientSession(new EndUser($U('cl-prio@example.com')));
$r = coderise_contract_level();
check('null avec client connecté (ClientSession) dans Priority => priority', $r['level'] === 'priority' && $r['source'] === 'organisation', json_encode($r));
$r = coderise_contract_level($thisclient);
check('ClientSession passé explicitement => priority', $r['level'] === 'priority', json_encode($r));
$thisclient = new ClientSession(new EndUser($U('cl-acme@example.com')));
$r = coderise_contract_level();
check('null avec client connecté Acme => standard', $r['level'] === 'standard' && $r['source'] === 'organisation', json_encode($r));
$r = coderise_contract_level(new EndUser($U('cl-prio@example.com')));
check('EndUser Priority => priority', $r['level'] === 'priority', json_encode($r));
// Invité : accès par numéro de ticket (ClientSession sur TicketOwner, flagGuest())
$guest = new ClientSession($t->getOwner());
$guest->flagGuest();
$thisclient = $guest;
$r = coderise_contract_level();
check('invité (accès par numéro de ticket), même propriétaire Priority => standard, default', $r['level'] === 'standard' && $r['source'] === 'default', json_encode($r));
$thisclient = null;

echo "== A4 : sujets non pris en charge : jamais d'exception\n";
$ok = true;
foreach (array(42, 'cl-prio@example.com', new stdClass(), array(1), false, Staff::lookup(1)) as $s) {
    try {
        $r = coderise_contract_level($s);
        $ok = $ok && $r['level'] === 'standard' && $r['source'] === 'default';
    } catch (Throwable $e) { $ok = false; }
}
check('entier, chaîne, objet, tableau, false, agent => standard, default, sans exception', $ok);

echo "== A5 : cache par requête (pas de requête SQL redondante)\n";
SlaContractLevel::reset();
$u = $U('cl-prio@example.com');
$q0 = nb_questions(); $q1 = nb_questions(); $base = $q1 - $q0;  // coût de la lecture du compteur
coderise_contract_level($u);
$q2 = nb_questions(); $first = $q2 - $q1 - $base;
for ($i = 0; $i < 50; $i++) { coderise_contract_level($u); coderise_contract_level(); coderise_contract_level($t); }
$q3 = nb_questions(); $next = $q3 - $q2 - $base;
check('1er appel : au plus 2 requêtes (organisation Priority, organisation du client)', $first <= 2, "first=$first");
check('150 appels suivants (même client, client connecté, ticket) : 0 requête', $next === 0, "next=$next");

echo "== A6 : fin d'abonnement, organisation d'origine restaurée (comme stripe-sync)\n";
SlaContractLevel::reset();
$abo = $U('cl-abo@example.com');
check('avant abonnement : Ancienne Org => standard', coderise_contract_level($abo)['level'] === 'standard');
// Ce que fait StripeSyncOsTicketDirectory::attachPriority() puis detachPriority()
$previous = (int) $abo->getOrgId();
$abo->setOrganization($prio);
SlaContractLevel::reset();
check('abonnement actif (rangé dans Priority) => priority', coderise_contract_level($abo)['level'] === 'priority');
$prio->removeUser($abo);
$abo->setOrganization(Organization::lookup($previous));
SlaContractLevel::reset();
$r = coderise_contract_level(User::lookup($abo->getId()));
check('fin d\'abonnement, organisation d\'origine restaurée => standard', $r['level'] === 'standard'
    && $r['org_id'] === (int) $ancienne->getId(), json_encode($r));
$row = db_fetch_row(db_query('SELECT org_id FROM ' . USER_TABLE . ' WHERE id=' . (int) $abo->getId()));
check('(relu en base) org_id = Ancienne Org', $row[0] == $ancienne->getId(), $row[0]);

echo "== A7 : organisation Priority non configurée ou supprimée => standard sans erreur\n";
SlaContractLevel::configure(0, 'Priority', 'Standard');
$r = coderise_contract_level($U('cl-prio@example.com'));
check('non configurée => standard, default', $r['level'] === 'standard' && $r['source'] === 'default', json_encode($r));
SlaContractLevel::configure(999999, 'Priority', 'Standard');
$r = coderise_contract_level($U('cl-prio@example.com'));
check('configurée sur une organisation qui n\'existe plus => standard, default', $r['level'] === 'standard' && $r['source'] === 'default', json_encode($r));

echo "== A8 : libellés configurables et traduits (__())\n";
SlaContractLevel::configure($prio->getId(), 'Support prioritaire', '');
$r = coderise_contract_level($U('cl-prio@example.com'));
check('libellé Priority configuré', $r['label'] === 'Support prioritaire', $r['label']);
check('libellé Standard vide => « Standard » par défaut', coderise_contract_level($U('cl-acme@example.com'))['label'] === 'Standard');
// Catalogue de langue factice « fr » (fichier compilé comme ceux des packs de langue)
@mkdir(I18N_DIR . 'fr/LC_MESSAGES', 0777, true);
file_put_contents(I18N_DIR . 'fr/LC_MESSAGES/messages.mo.php',
    '<?php return ' . var_export(array('Priority' => 'Prioritaire', 'Standard' => 'Standard (fr)',
        'Support prioritaire' => 'Support prioritaire (fr)'), true) . ';');
SlaContractLevel::configure($prio->getId(), 'Priority', 'Standard');
$en = coderise_contract_level($U('cl-prio@example.com'))['label'];
TextDomain::setLocale(LC_MESSAGES, 'fr_FR');
$fr = coderise_contract_level($U('cl-prio@example.com'))['label'];
$frs = coderise_contract_level($U('cl-acme@example.com'))['label'];
check('en_US : « Priority »', $en === 'Priority', $en);
check('fr : « Prioritaire » / « Standard (fr) » (libellés passés par __())', $fr === 'Prioritaire' && $frs === 'Standard (fr)', "$fr / $frs");
SlaContractLevel::configure($prio->getId(), 'Support prioritaire', 'Standard');
$fr2 = coderise_contract_level($U('cl-prio@example.com'))['label'];
check('libellé personnalisé traduit s\'il figure au catalogue', $fr2 === 'Support prioritaire (fr)', $fr2);
TextDomain::setLocale(LC_MESSAGES, 'en_US');
@unlink(I18N_DIR . 'fr/LC_MESSAGES/messages.mo.php');

echo "\nPhase 4b : " . ($nfail ? $nfail . " échec(s)" : 'ok') . "\n";
