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
    - oa_atelier_contract(), oa_atelier_contract_badge(),
      oa_atelier_contract_promise(): contract level (Priority / Standard)
      from the optional sla-first-response plugin
      (coderise_contract_level()); nothing at all without the plugin.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('OA_ATELIER_THEME')) {
    define('OA_ATELIER_THEME', 'ownalytics-atelier');
    // Bumped with every change of the stylesheet (cache busting).
    define('OA_ATELIER_VERSION', '1.1.0');
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

    /**
     * Contract level (Priority / Standard) of a client, read from the
     * optional sla-first-response plugin (contrib/sla-first-response,
     * version 0.3+, coderise_contract_level()): array('level' =>
     * 'priority'|'standard', 'label' => ...), or null when no level must be
     * shown:
     * - plugin (or its instance) disabled or absent: the function does not
     *   exist, the theme shows no level at all (never "Standard" by
     *   default) and makes no promise;
     * - source "default": the plugin could not apply its rule (guest, no
     *   Priority organisation configured, unknown user): level unknown.
     * $subject: null (the signed-in client), a User, a Ticket (its OWNER,
     * never a collaborator), or false (unknown user: nothing). It is the
     * CURRENT level of that person, not the one at ticket creation.
     * Computed once per person and per page (the plugin caches too).
     */
    function oa_atelier_contract($subject=null) {
        static $memo = array();
        if (!function_exists('coderise_contract_level') || $subject === false)
            return null;
        if ($subject === null)
            $key = 'me';
        elseif ($subject instanceof Ticket)
            $key = 'u' . (int) $subject->getOwnerId();
        elseif ($subject instanceof User)
            $key = 'u' . (int) $subject->getId();
        else
            return null;
        if (!array_key_exists($key, $memo)) {
            $memo[$key] = null;
            $l = coderise_contract_level($subject);
            if (is_array($l) && isset($l['level'], $l['label'], $l['source'])
                    && $l['source'] != 'default' && trim((string) $l['label']) !== '')
                $memo[$key] = array(
                    'level' => $l['level'] == 'priority' ? 'priority' : 'standard',
                    'label' => (string) $l['label'],
                );
        }
        return $memo[$key];
    }

    /**
     * Pill of a contract level (see oa_atelier_contract()), or '' if none.
     * Priority: accented (sun on plum, bolt); Standard: discreet (outlined,
     * soft ink). The label is set by an administrator in the plugin: escaped.
     */
    function oa_atelier_contract_badge($contract) {
        if (!$contract)
            return '';
        $priority = ($contract['level'] == 'priority');
        return sprintf('<span class="pill pill-contract pill-%s"><span class="visually-hidden">%s </span>%s%s</span>',
            $priority ? 'priority' : 'standard',
            Format::htmlchars(__oa('Contract:')),
            $priority ? '<i class="icon-bolt" aria-hidden="true"></i>' : '',
            Format::htmlchars($contract['label']));
    }

    /**
     * Expected first reply under a contract level, or '' without a level
     * (no plugin, unknown level): no promise is ever made without one.
     * Priority: within 4 h, 24/7; Standard: on business days.
     */
    function oa_atelier_contract_promise($contract) {
        if (!$contract)
            return '';
        return $contract['level'] == 'priority'
            ? __oa('Within 4 h, 24/7')
            : __oa('On business days');
    }
}
