<?php
/*********************************************************************
    ownalytics-atelier.inc.php

    Helpers of the "Ownalytics Atelier" client portal theme.
    Lives outside osTicket's core: it is added by the Docker overlay
    (deploy/themes/ownalytics-atelier) and only used by the theme's templates.

    - __oa(): translation of the strings that only exist in this theme,
      through a dedicated gettext domain ("ownalytics-atelier") whose catalogues live
      in include/client/ownalytics-atelier/i18n/<lang>/LC_MESSAGES/ownalytics-atelier.mo.php
      (same compiled format as osTicket's language packs). Strings that
      osTicket already has keep using __() so that the official language
      packs translate them.
    - oa_atelier_initials(), oa_atelier_avatar_tone(): local avatars made of
      initials (no Gravatar, no external request).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('OA_ATELIER_THEME')) {
    define('OA_ATELIER_THEME', 'ownalytics-atelier');
    // Bumped with every change of the stylesheet (cache busting).
    define('OA_ATELIER_VERSION', '1.0.0');
    // Brand shown next to the pictogram when no custom logo is uploaded.
    // A proper noun: it is not translated.
    define('OA_ATELIER_BRAND', 'Ownalytics');

    TextDomain::lookup('ownalytics-atelier')->setPath(dirname(__FILE__) . '/i18n/');

    /**
     * Translate a string that belongs to the theme (gettext domain
     * "ownalytics-atelier"). Falls back to the English msgid, like __().
     */
    function __oa($msgid) {
        return _dgettext('ownalytics-atelier', $msgid);
    }

    /**
     * One or two capital letters from a person's name, for an avatar.
     */
    function oa_atelier_initials($name) {
        $name = trim((string) $name);
        if ($name === '')
            return '?';
        // "Roux, Camille" => "Camille Roux"
        if (strpos($name, ',') !== false) {
            list($last, $first) = array_map('trim', explode(',', $name, 2));
            $name = trim("$first $last");
        }
        $words = preg_split('/[\s\-_.@]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $letters = '';
        foreach ($words as $w) {
            $letters .= mb_substr($w, 0, 1, 'UTF-8');
            if (mb_strlen($letters, 'UTF-8') >= 2)
                break;
        }
        return mb_strtoupper($letters ?: '?', 'UTF-8');
    }

    /**
     * Stable colour of an agent's avatar (the client is always "sun").
     */
    function oa_atelier_avatar_tone($key) {
        $tones = array('pink', 'tangerine', 'mint', 'plum');
        return $tones[abs(crc32((string) $key)) % count($tones)];
    }
}
