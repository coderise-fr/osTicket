<?php
/*********************************************************************
    install-http.php

    Pilote l'installeur web d'osTicket (serveur `php -S`) : étapes
    prereq, config puis install, sur une base de test vide.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
$base = 'http://127.0.0.1:8000/setup/install.php';
$jar = '';
function post($url, $data, &$jar) {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . ($jar ? "Cookie: $jar\r\n" : ''),
        'content' => http_build_query($data),
        'ignore_errors' => true,
        'follow_location' => 0,
    ]]);
    $r = file_get_contents($url, false, $ctx);
    foreach ($http_response_header as $h)
        if (stripos($h, 'Set-Cookie:') === 0)
            $jar = trim(explode(';', substr($h, 11))[0]);
    return $r;
}
$r = file_get_contents($base, false, stream_context_create(['http' => ['ignore_errors' => true]]));
foreach ($http_response_header as $h)
    if (stripos($h, 'Set-Cookie:') === 0)
        $jar = trim(explode(';', substr($h, 11))[0]);
echo "GET ok, cookie=$jar\n";
$r = post($base, ['s' => 'prereq'], $jar);
echo "prereq: ", (strpos($r, 'Minimum requirements not met') !== false ? 'FAIL' : 'ok'), "\n";
$r = post($base, ['s' => 'config'], $jar);
echo "config: ", (strpos($r, 'Write access required') !== false || strpos($r, 'does NOT exist') !== false ? 'FAIL' : 'ok'), "\n";
$r = post($base, [
    's' => 'install', 'name' => 'Test Desk', 'email' => 'support@example.test',
    'fname' => 'Ada', 'lname' => 'Admin', 'admin_email' => 'ada@example.test',
    'username' => 'ada_admin', 'passwd' => 'Zx9!kLm2#qWe7vTb', 'passwd2' => 'Zx9!kLm2#qWe7vTb',
    'prefix' => 'ost_', 'dbhost' => 'ostsla-db', 'dbname' => 'ost',
    'dbuser' => 'root', 'dbpass' => 'rootpw', 'lang_id' => 'en_US', 'timezone' => 'UTC',
], $jar);
if (preg_match('/class="error[^"]*"[^>]*>(.*?)<\/|errors?[^<]*<[^>]*>([^<]+)/s', $r, $m)) echo "match: ", trim(strip_tags($m[0])), "\n";
echo (strpos($r, 'Congratulations') !== false || strpos($r, 'install-done') !== false || strpos($r, 'Installation Completed') !== false) ? "INSTALL OK\n" : "INSTALL ?\n";
echo substr(strip_tags($r), 0, 1500), "\n";
