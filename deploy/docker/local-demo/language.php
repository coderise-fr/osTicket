<?php
/*********************************************************************
    language.php

    Environnement local de DÉMONSTRATION uniquement (jamais en
    production) : règle la langue de l'interface d'une démo installée.
    Exécuté par deploy/docker/scripts/local-demo.sh (commande
    `language <code>`, et après le premier peuplement avec
    OST_DEMO_LANG, `fr` par défaut) dans un conteneur ponctuel de
    l'image : `php` lit ce fichier sur son entrée standard, précédé d'un
    prélude qui définit $DEMO (code de langue, URL).

    Effet (en base, idempotent) :
      - langue principale du système (`system_language`) = le code ;
      - aucune langue secondaire (`secondary_langs` vide) : tout le
        monde voit la langue principale, quelle que soit la langue du
        navigateur (avec une langue secondaire, osTicket suit
        l'en-tête Accept-Language des visiteurs) ;
      - langue de chaque agent (`ost_staff.lang`) = le code.
    Les contenus déjà en base (rubriques, départements, statuts,
    réponses types, pages, modèles d'e-mails) ne sont pas modifiés.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (PHP_SAPI !== 'cli' || !isset($DEMO) || !is_array($DEMO) || !isset($DEMO['lang']))
    die("language.php : à lancer par deploy/docker/scripts/local-demo.sh\n");

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
$demo_url = parse_url($DEMO['base_url']);
$_SERVER['HTTP_HOST'] = $demo_url['host'] . (isset($demo_url['port']) ? ':' . $demo_url['port'] : '');
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
chdir('/var/www/html');
require '/var/www/html/main.inc.php';
require_once INCLUDE_DIR . 'class.staff.php';

$lang = (string) $DEMO['lang'];
$info = Internationalization::getLanguageInfo($lang);
if (!$info) {
    fwrite(STDERR, sprintf("langue non installée dans l'image : %s (installées : %s)\n", $lang,
        implode(', ', array_map(function ($l) { return $l['code']; },
            Internationalization::availableLanguages()))));
    exit(1);
}
$lang = $info['code'];    // casse du fichier (fr, en_US)

$cfg->set('system_language', $lang);
$cfg->set('secondary_langs', '');

$n = 0;
foreach (Staff::objects() as $staff) {
    if ($staff->lang !== $lang) {
        $staff->lang = $lang;
        $staff->save();
    }
    $n++;
}
printf("langue principale : %s ; langues secondaires : aucune ; agents réglés : %d\n", $lang, $n);
