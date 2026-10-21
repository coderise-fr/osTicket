<?php
/*********************************************************************
    phase3-sql.php

    Phase 3, requêtes SQL du dossier sql/ exécutées sur le moteur de la
    base de test (MariaDB ou MySQL, selon run.ps1) avec des tickets
    FACTICES aux dates fixées et des lignes `sub:` factices dans la table
    de configuration (format du plugin contrib/stripe-sync) :
        namespace = stripesync, key = sub:<id>,
        value = <user_id>|<début epoch UTC>|<fin epoch UTC ou vide>|<statut>

    Vérifie : la première réponse (réponse au client uniquement), le
    comptage par client et par période anniversaire (bornes, 29 février,
    abonnement résilié et repris, chevauchement, client sans abonnement,
    ligne illisible, lignes d'un autre espace de noms) et le seuil
    « 4 dépassements ou plus ».
    Instance de test uniquement (voir run.ps1).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require __DIR__ . '/common.php';

$SQL_DIR = '/tmp/sql';
$watch = sla_id('Priority 4h');
$done = sla_id('Priority 4h - 1re reponse faite');

// Exécute un fichier .sql (plusieurs instructions) sur la connexion d'osTicket
// et renvoie la liste des jeux de résultats (les SET n'en produisent pas).
function run_sql_file($path) {
    global $__db;
    $sets = array();
    // Fonctions procédurales de mysqli (le contrôle statique du dépôt ne
    // connaît pas les méthodes de l'objet mysqli).
    if (!mysqli_multi_query($__db, file_get_contents($path))) {
        echo "  ERREUR SQL : " . mysqli_error($__db) . "\n";
        return null;
    }
    do {
        if ($res = mysqli_store_result($__db)) {
            $sets[] = mysqli_fetch_all($res, MYSQLI_ASSOC);
            mysqli_free_result($res);
        }
    } while (mysqli_more_results($__db) && mysqli_next_result($__db));
    if (mysqli_errno($__db)) {
        echo "  ERREUR SQL : " . mysqli_error($__db) . "\n";
        return null;
    }
    return $sets;
}
function epoch($datetime_utc) { return strtotime($datetime_utc . ' UTC'); }
function shift($datetime_utc, $seconds) { return gmdate('Y-m-d H:i:s', epoch($datetime_utc) + $seconds); }

$tk = array();      // clé de fixture => array(id, number, user_id)
$uid = array();     // initiale du client => user_id
// Ticket facticite : créé à $created (UTC), avec des réponses [décalage en secondes, choix « Répondre à »].
function mk($key, $initial, $name, $created, $replies = array()) {
    global $tk, $uid, $watch;
    $email = strtolower($name) . '-sla@example.com';
    $t = client_ticket($email, $name . ' Sla', $watch, "SQL $key");
    $id = $t->getId();
    $uid[$initial] = $t->getUserId();
    set_created($id, $created);
    foreach ($replies as $r) {
        list($offset, $to) = $r;
        $t = reload($id);
        if ($to == 'collabs' && !$t->getNumCollaborators())
            add_collab($t, 'collab-' . strtolower($name) . '-sla@example.com', 'Collab ' . $name);
        $t = reload($id);
        $resp = agent_reply($t, "Reponse $key", $to);
        set_entry_created($resp->getId(), shift($created, $offset));
    }
    $tk[$key] = array('id' => $id, 'number' => $t->getNumber(), 'user_id' => $t->getUserId());
    return $tk[$key];
}
function put_config($ns, $key, $value) {
    db_query('INSERT INTO ' . CONFIG_TABLE . ' (`namespace`, `key`, `value`) VALUES ('
        . db_input($ns) . ',' . db_input($key) . ',' . db_input($value) . ')');
}
function put_sub($sub_id, $user_id, $start_utc, $ended_utc, $status) {
    $ended = $ended_utc === '' ? '' : epoch($ended_utc);
    put_config('stripesync', 'sub:' . $sub_id, $user_id . '|' . (is_int($start_utc) ? $start_utc : epoch($start_utc)) . '|' . $ended . '|' . $status);
}
function numbers_of($keys) {
    global $tk;
    $n = array_map(function ($k) use ($tk) { return $tk[$k]['number']; }, $keys);
    sort($n);
    return $n;
}
function find_rows($rows, $section, $user_id, $abo = null, $rang = null) {
    $out = array();
    foreach ($rows as $r) {
        if (strpos($r['section'], $section) !== 0 || (int) $r['user_id'] !== (int) $user_id)
            continue;
        if ($abo !== null && $r['abonnement'] !== $abo)
            continue;
        if ($rang !== null && (int) $r['periode_n'] !== $rang)
            continue;
        $out[] = $r;
    }
    return $out;
}
function listed($s) { $a = array_filter(array_map('trim', explode(',', (string) $s))); sort($a); return array_values($a); }

echo "== Fuseau de la session : les fixtures supposent une base en UTC\n";
$tz = db_fetch_array(db_query('SELECT @@session.time_zone AS s, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS decal, VERSION() AS v'));
echo "  moteur " . $tz['v'] . ", fuseau de session " . $tz['s'] . "\n";
check('NOW() = UTC_TIMESTAMP() (décalage 0)', (int) $tz['decal'] == 0, json_encode($tz));

echo "== Fixtures : tickets Priority aux dates fixées\n";
// Alice : abonnement depuis le 10/03/2023 14:00:00 UTC, en cours.
mk('A1', 'A', 'Alice', '2023-04-01 10:00:00', array(array(14399, 'user')));   // 3 h 59 min 59 s : respecté
mk('A2', 'A', 'Alice', '2023-05-01 10:00:00', array(array(14400, 'user')));   // 4 h 00 min 00 s : respecté (pas strictement plus)
mk('A3', 'A', 'Alice', '2023-06-01 10:00:00', array(array(14401, 'user')));   // 4 h 00 min 01 s : dépassé
mk('A4', 'A', 'Alice', '2023-07-01 10:00:00', array(array(3600, 'none'), array(18000, 'user'))); // sans e-mail à +1 h, avec e-mail à +5 h : dépassé
mk('A5', 'A', 'Alice', '2023-08-01 10:00:00', array(array(600, 'none')));     // seulement sans e-mail : dépassé
mk('A6', 'A', 'Alice', '2024-03-10 13:59:59');                                // sans réponse, 1 s avant l'anniversaire : période 0, dépassé
mk('A7', 'A', 'Alice', '2024-03-10 14:00:00');                                // sans réponse, à l'anniversaire : période 1, dépassé
mk('A8', 'A', 'Alice', '2024-06-01 09:00:00', array(array(60, 'user')));      // respecté
// Bob : abonnement démarré un 29 février (2024-02-29 12:00:00 UTC), en cours.
mk('B0', 'B', 'Bob', '2024-02-29 12:00:00');                                  // à la seconde du début : période 0, dépassé
mk('B1', 'B', 'Bob', '2025-02-28 11:59:59');                                  // période 0, dépassé
mk('B2', 'B', 'Bob', '2025-02-28 12:00:00');                                  // 1er anniversaire = 28/02 : période 1, dépassé
mk('B3', 'B', 'Bob', '2025-06-01 08:00:00');                                  // période 1, dépassé
mk('B4', 'B', 'Bob', '2025-07-01 08:00:00', array(array(60, 'user')));        // période 1, respecté
mk('B5', 'B', 'Bob', '2028-02-29 11:59:59', array(array(60, 'user')));        // période 3 (fin le 29/02/2028 12:00)
mk('B6', 'B', 'Bob', '2028-02-29 12:00:00', array(array(60, 'user')));        // 4e anniversaire = 29/02/2028 : période 4
// Carol : abonnement résilié, puis nouvel abonnement.
mk('C1', 'C', 'Carol', '2023-02-01 10:00:00');                                // abonnement 1, dépassé
mk('C2', 'C', 'Carol', '2023-09-20 07:59:59', array(array(60, 'user')));      // 1 s avant la fin : abonnement 1, respecté
mk('C3', 'C', 'Carol', '2023-09-20 08:00:00');                                // à la seconde de la fin (exclue) : hors période
mk('C4', 'C', 'Carol', '2023-01-15 07:59:59');                                // avant le début : hors période
mk('C6', 'C', 'Carol', '2023-10-10 10:00:00');                                // entre les deux abonnements : hors période
mk('C5', 'C', 'Carol', '2024-06-01 10:00:00');                                // abonnement 2, dépassé
// Eve : aucune ligne sub:.
mk('E1', 'E', 'Eve', '2024-01-10 10:00:00');                                  // dépassé
mk('E2', 'E', 'Eve', '2024-01-11 10:00:00', array(array(60, 'user')));        // respecté
// Frank : période en cours (abonnement démarré il y a 100 jours).
$now = time();
mk('F1', 'F', 'Frank', gmdate('Y-m-d H:i:s', $now - 3600));                  // sans réponse, dans le délai : PAS un dépassement
mk('F2', 'F', 'Frank', gmdate('Y-m-d H:i:s', $now - 10 * 86400));             // sans réponse, délai écoulé : dépassé
// Gina : deux abonnements qui se chevauchent.
mk('GA', 'G', 'Gina', '2023-03-01 10:00:00');                                 // seulement l'abonnement 1
mk('GB', 'G', 'Gina', '2023-07-01 10:00:00');                                 // dans les deux : le plus récent l'emporte
// Hank : exactement 3 dépassements.
mk('H1', 'H', 'Hank', '2023-02-01 10:00:00');
mk('H2', 'H', 'Hank', '2023-02-02 10:00:00');
mk('H3', 'H', 'Hank', '2023-02-03 10:00:00');
mk('H4', 'H', 'Hank', '2023-02-04 10:00:00', array(array(60, 'user')));
// Ivan : réponse aux seuls collaborateurs : ne compte pas.
mk('I1', 'I', 'Ivan', '2023-02-01 10:00:00', array(array(60, 'collabs')));

put_sub('sub_A', $uid['A'], '2023-03-10 14:00:00', '', 'active');
put_sub('sub_B', $uid['B'], '2024-02-29 12:00:00', '', 'active');
put_sub('sub_C1', $uid['C'], '2023-01-15 08:00:00', '2023-09-20 08:00:00', 'canceled');
put_sub('sub_C2', $uid['C'], '2024-05-01 00:00:00', '', 'active');
put_sub('sub_F', $uid['F'], $now - 100 * 86400, '', 'past_due');
put_sub('sub_G1', $uid['G'], '2023-01-01 00:00:00', '', 'active');
put_sub('sub_G2', $uid['G'], '2023-06-01 00:00:00', '', 'active');
put_sub('sub_H', $uid['H'], '2023-01-01 00:00:00', '', 'active');
put_sub('sub_I', $uid['I'], '2023-01-01 00:00:00', '', 'active');
// Lignes à ignorer ou à signaler
put_config('autre', 'sub:eve', $uid['E'] . '|' . epoch('2023-01-01 00:00:00') . '||active');   // autre espace de noms
put_config('stripesync', 'customer:cus_1', $uid['E'] . '|' . epoch('2023-01-01 00:00:00') . '||active'); // autre type de clé
put_config('stripesync', 'sub:bad', 'pas|un|bon format');                                       // illisible
check('fixtures créées', count($tk) == 32, count($tk));

echo "== Requête premiere-reponse.sql : première réponse AU CLIENT\n";
$sets = run_sql_file("$SQL_DIR/premiere-reponse.sql");
check('premiere-reponse.sql s\'exécute', $sets !== null && count($sets) == 1);
$byNum = array();
foreach ($sets[0] as $r) $byNum[$r['numero']] = $r;
function pr($key) { global $byNum, $tk; return $byNum[$tk[$key]['number']]; }
check('A1 : 239 min, respecté', pr('A1')['minutes_avant_reponse'] == 239 && pr('A1')['statut'] == 'respecte', json_encode(pr('A1')));
check('A2 : exactement 4 h 00 min 00 s = respecté', pr('A2')['minutes_avant_reponse'] == 240 && pr('A2')['statut'] == 'respecte', json_encode(pr('A2')));
check('A3 : 4 h 00 min 01 s = dépassé', pr('A3')['statut'] == 'depasse', json_encode(pr('A3')));
check('A4 : la réponse sans e-mail à +1 h est ignorée, première = +5 h (300 min), dépassé',
    pr('A4')['minutes_avant_reponse'] == 300 && pr('A4')['statut'] == 'depasse', json_encode(pr('A4')));
check('A5 : réponse sans e-mail seule = sans réponse, délai dépassé', pr('A5')['premiere_reponse_le'] === null && pr('A5')['statut'] == 'sans reponse - delai depasse', json_encode(pr('A5')));
check('I1 : réponse aux collaborateurs seuls = sans réponse', pr('I1')['premiere_reponse_le'] === null && pr('I1')['statut'] == 'sans reponse - delai depasse');
check('F1 : sans réponse dans le délai', pr('F1')['statut'] == 'sans reponse - dans le delai');
check('colonnes client (id, nom, e-mail)', pr('A1')['client_id'] == $uid['A'] && pr('A1')['client'] == 'Alice Sla' && pr('A1')['client_email'] == 'alice-sla@example.com', json_encode(pr('A1')));
check('plan : A1 (réponse client) est passé sur le plan « réponse faite », A5 reste sur le plan surveillé',
    pr('A1')['plan_sla'] == 'Priority 4h - 1re reponse faite' && pr('A5')['plan_sla'] == 'Priority 4h', pr('A1')['plan_sla'] . ' / ' . pr('A5')['plan_sla']);

echo "== Cohérence plugin <-> SQL : un ticket est sur le plan « réponse faite » ssi le SQL voit une première réponse\n";
// Les tickets de la phase 1 (plugin NON installé : réponses faites, plan jamais basculé) sont exclus.
$phase1 = array();
$q = db_query('SELECT ticket_id FROM ' . TABLE_PREFIX . "ticket__cdata WHERE subject LIKE 'Test S%'");
while ($q && ($x = db_fetch_row($q))) $phase1[$x[0]] = true;
$bad = array(); $n = 0;
foreach ($sets[0] as $r) {
    if (isset($phase1[$r['ticket_id']])) continue;
    $n++;
    $f = fresh($r['ticket_id']);
    $has = $r['premiere_reponse_le'] !== null;
    if ($has != ($f['sla_id'] == $done)) $bad[] = $r['numero'];
}
check("aucun écart sur $n tickets Priority (hors phase 1)", !$bad && $n > 40, implode(',', $bad));

echo "== Requête depassements-par-client-periode.sql\n";
$sets = run_sql_file("$SQL_DIR/depassements-par-client-periode.sql");
check('le fichier s\'exécute (rapport + requête B)', $sets !== null && count($sets) == 2, $sets === null ? '' : count($sets));
$rows = $sets[0];

$periods = array(
    // client, abonnement, rang, début, fin, tickets, dépassements, prolongation, tickets en dépassement
    array('A', 'sub:sub_A', 0, '2023-03-10 14:00:00', '2024-03-10 14:00:00', 6, 4, 1, array('A3', 'A4', 'A5', 'A6')),
    array('A', 'sub:sub_A', 1, '2024-03-10 14:00:00', '2025-03-10 14:00:00', 2, 1, 0, array('A7')),
    array('B', 'sub:sub_B', 0, '2024-02-29 12:00:00', '2025-02-28 12:00:00', 2, 2, 0, array('B0', 'B1')),
    array('B', 'sub:sub_B', 1, '2025-02-28 12:00:00', '2026-02-28 12:00:00', 3, 2, 0, array('B2', 'B3')),
    array('B', 'sub:sub_B', 3, '2027-02-28 12:00:00', '2028-02-29 12:00:00', 1, 0, 0, array()),
    array('B', 'sub:sub_B', 4, '2028-02-29 12:00:00', '2029-02-28 12:00:00', 1, 0, 0, array()),
    array('C', 'sub:sub_C1', 0, '2023-01-15 08:00:00', '2023-09-20 08:00:00', 2, 1, 0, array('C1')),
    array('C', 'sub:sub_C2', 0, '2024-05-01 00:00:00', '2025-05-01 00:00:00', 1, 1, 0, array('C5')),
    array('G', 'sub:sub_G1', 0, '2023-01-01 00:00:00', '2024-01-01 00:00:00', 1, 1, 0, array('GA')),
    array('G', 'sub:sub_G2', 0, '2023-06-01 00:00:00', '2024-06-01 00:00:00', 1, 1, 0, array('GB')),
    array('H', 'sub:sub_H', 0, '2023-01-01 00:00:00', '2024-01-01 00:00:00', 4, 3, 0, array('H1', 'H2', 'H3')),
    array('I', 'sub:sub_I', 0, '2023-01-01 00:00:00', '2024-01-01 00:00:00', 1, 1, 0, array('I1')),
);
foreach ($periods as $p) {
    list($c, $abo, $n, $deb, $fin, $nt, $nd, $due, $keys) = $p;
    $m = find_rows($rows, '1 ', $uid[$c], $abo, $n);
    $label = "$c $abo période $n [$deb ; " . $fin . "[";
    check($label . ' : une ligne', count($m) == 1, count($m));
    if (count($m) != 1) continue;
    $m = $m[0];
    check('   bornes UTC', $m['periode_debut_utc'] === $deb && $m['periode_fin_utc'] === $fin, $m['periode_debut_utc'] . ' ; ' . $m['periode_fin_utc']);
    check("   $nt tickets, $nd dépassements", (int) $m['tickets_priority'] == $nt && (int) $m['depassements'] == $nd, $m['tickets_priority'] . '/' . $m['depassements']);
    check('   prolongation_due = ' . $due, (int) $m['prolongation_due'] === $due, $m['prolongation_due']);
    check('   numéros des tickets en dépassement', listed($m['tickets_en_depassement']) === numbers_of($keys), $m['tickets_en_depassement']);
}
// Frank : période en cours, relative à maintenant
$m = find_rows($rows, '1 ', $uid['F'], 'sub:sub_F', 0);
check('F : période en cours, 2 tickets, 1 dépassement (F1 dans le délai non compté)',
    count($m) == 1 && $m[0]['periode_en_cours'] == 1 && $m[0]['tickets_priority'] == 2 && $m[0]['depassements'] == 1
    && listed($m[0]['tickets_en_depassement']) === numbers_of(array('F2')) && $m[0]['statut_stripe'] == 'past_due', json_encode($m));
// Abonnement résilié : fin renseignée, statut Stripe repris
$m = find_rows($rows, '1 ', $uid['C'], 'sub:sub_C1', 0);
check('C : fin_abonnement et statut repris de la ligne sub:', $m && $m[0]['fin_abonnement'] === '2023-09-20 08:00:00' && $m[0]['statut_stripe'] == 'canceled' && $m[0]['debut_abonnement'] === '2023-01-15 08:00:00', json_encode($m));
$m = find_rows($rows, '1 ', $uid['A'], 'sub:sub_A', 0);
check('A : abonnement en cours (fin NULL), client et e-mail', $m && $m[0]['fin_abonnement'] === null && $m[0]['client'] == 'Alice Sla' && $m[0]['email'] == 'alice-sla@example.com', json_encode($m));
check('A : période 0 terminée (pas en cours)', $m && $m[0]['periode_en_cours'] == 0);

// « 4 ou plus » par période, jamais en cumulant les périodes (Bob : 2 + 2)
check('Bob (2 + 2 dépassements sur deux périodes) : aucune prolongation', !array_filter(find_rows($rows, '2 ', $uid['B'])));
check('Hank (3 dépassements) : pas de prolongation', !array_filter(find_rows($rows, '2 ', $uid['H'])));
check('Ivan (réponse aux collaborateurs seuls) : dépassement compté', find_rows($rows, '1 ', $uid['I'])[0]['depassements'] == 1);

// Section 2 : ligne dédiée
$s2 = array_filter($rows, function ($r) { return strpos($r['section'], '2 ') === 0; });
check('section 2 : une seule prolongation due (Alice, période 0)', count($s2) == 1 && current($s2)['user_id'] == $uid['A'] && current($s2)['periode_n'] == 0 && current($s2)['depassements'] == 4, json_encode(array_values($s2)));

// Section 3 : sans abonnement connu
$m = find_rows($rows, '3 ', $uid['E']);
check('Eve (aucune ligne sub:, ligne d\'un autre espace de noms ignorée) : section 3, 2 tickets, 1 dépassement',
    count($m) == 1 && $m[0]['tickets_priority'] == 2 && $m[0]['depassements'] == 1 && strpos($m[0]['motif'], 'aucune ligne sub:') === 0
    && listed($m[0]['tickets_en_depassement']) === numbers_of(array('E1')), json_encode($m));
check('Eve : absente de la section 1', !find_rows($rows, '1 ', $uid['E']));
$m = find_rows($rows, '3 ', $uid['C']);
check('Carol : 3 tickets hors de ses périodes (avant début, à la fin exclue, entre deux abonnements), 3 dépassements',
    count($m) == 1 && $m[0]['tickets_priority'] == 3 && $m[0]['depassements'] == 3 && strpos($m[0]['motif'], 'hors des periodes') !== false
    && listed($m[0]['tickets_en_depassement']) === numbers_of(array('C3', 'C4', 'C6')), json_encode($m));

// Section 4 : ligne illisible
$s4 = array_filter($rows, function ($r) { return strpos($r['section'], '4 ') === 0; });
check('section 4 : seule la ligne sub:bad est signalée', count($s4) == 1 && current($s4)['abonnement'] == 'sub:bad' && strpos(current($s4)['motif'], 'pas|un|bon format') !== false, json_encode(array_values($s4)));

// Aucun ticket perdu ni compté deux fois
$nprio = (int) db_result(db_query('SELECT COUNT(*) FROM ' . TICKET_TABLE . ' t JOIN ' . SLA_TABLE . ' s ON s.id=t.sla_id WHERE s.name IN ("Priority 4h","Priority 4h - 1re reponse faite")'));
$sum = 0;
foreach ($rows as $r)
    if (strpos($r['section'], '1 ') === 0 || strpos($r['section'], '3 ') === 0)
        $sum += (int) $r['tickets_priority'];
check('sections 1 + 3 : tous les tickets Priority, chacun une fois (' . $nprio . ')', $sum == $nprio, "$sum / $nprio");

// Requête B
check('requête B (événements overdue par client) : résultat lisible', is_array($sets[1]));

echo "== Ticket ouvert par un agent pour le client (téléphone), réponse jointe à la création\n";
$thisstaff = Staff::lookup(1);
$topic = (int) db_result(db_query('SELECT topic_id FROM ' . TOPIC_TABLE . ' ORDER BY topic_id LIMIT 1'));
$base = array('topicId' => $topic, 'name' => 'Jean Dupont', 'email' => 'jean-sla@example.com', 'message' => 'Appel téléphonique', 'response' => 'Réponse donnée en ligne',
    'slaId' => $watch, 'source' => 'Phone', 'ip' => '127.0.0.1');
$errors = array();
$j1 = Ticket::open($base + array('subject' => 'SQL J1 avec e-mail', 'reply-to' => 'user'), $errors);
check('Ticket::open (réponse envoyée au client)', (bool) $j1, json_encode($errors));
$errors = array();
$j2 = Ticket::open(array('email' => 'jeanne-sla@example.com', 'name' => 'Jeanne Dupont', 'subject' => 'SQL J2 sans e-mail', 'reply-to' => 'none') + $base, $errors);
check('Ticket::open (réponse sans e-mail)', (bool) $j2, json_encode($errors));
$errors = array();
$base3 = $base; $base3['email'] = 'jacques-sla@example.com'; $base3['name'] = 'Jacques Dupont';
$j3 = Ticket::open($base3 + array('subject' => 'SQL J3 sans choix'), $errors);   // choix « Alerter » absent du formulaire (avis de ticket désactivé)
check('Ticket::open (formulaire sans le choix « Répondre à »)', (bool) $j3, json_encode($errors));
if ($j1 && $j2 && $j3) {
    $sets = run_sql_file("$SQL_DIR/premiere-reponse.sql");
    $byId = array();
    foreach ($sets[0] as $r) $byId[$r['ticket_id']] = $r;
    $r1 = $byId[$j1->getId()]; $r2 = $byId[$j2->getId()]; $r3 = $byId[$j3->getId()];
    check('J1 (envoyée au client) : 0 minute, respecté', $r1['minutes_avant_reponse'] !== null && (int) $r1['minutes_avant_reponse'] == 0 && $r1['statut'] == 'respecte', json_encode($r1));
    check('J1 : bascule du plan par le plugin', fresh($j1->getId())['sla_id'] == $done);
    check('J2 (« Ne pas envoyer d\'e-mail ») : pas de première réponse', $r2['premiere_reponse_le'] === null && fresh($j2->getId())['sla_id'] == $watch, json_encode($r2));
    check('J3 (aucun choix de destinataire) : pas de première réponse', $r3['premiere_reponse_le'] === null && fresh($j3->getId())['sla_id'] == $watch, json_encode($r3));
}

echo "\nPhase 3 : " . ($nfail ? $nfail . " échec(s)" : 'tout est conforme aux attentes') . "\n";
