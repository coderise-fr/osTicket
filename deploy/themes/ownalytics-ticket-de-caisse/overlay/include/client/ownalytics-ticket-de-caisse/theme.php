<?php
/*********************************************************************
    include/client/ownalytics-ticket-de-caisse/theme.php

    Helpers of the "Ticket de caisse" client portal theme (Ownalytics
    colours, proposal 01 of docs/theme-mockups). Not part of osTicket's
    core: shipped by deploy/themes/ownalytics-ticket-de-caisse and only
    used by the theme's header, footer and thread templates.

    - otdc__()          translation of the few strings that only exist in
                        this theme: osTicket's __() first (a language pack
                        may have the msgid), then the theme's catalogue
                        i18n/<lang>.php, then the English msgid.
    - otdc_asset()      URL of a theme asset, with a cache-busting version.
    - otdc_mark()       the Ownalytics pictogram (a receipt), inline SVG.
    - otdc_strip_avatars()  removes avatar images from HTML built by the
                        core (Gravatar is osTicket's default avatar source:
                        the portal must not call it).
    - otdc_contract(), otdc_contract_line()  support contract level
                        (Priority / Standard) read from the optional
                        sla_first_response plugin, printed as a receipt line.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('OTDC_VERSION')) {
    // Bumped with every change of the stylesheet (cache busting).
    define('OTDC_VERSION', '1.1.0');
    define('OTDC_SLUG', 'ownalytics-ticket-de-caisse');
    // Brand shown next to the pictogram when no custom logo is uploaded.
    // A proper noun: never translated.
    define('OTDC_BRAND', 'Ownalytics');

    /**
     * Translate a string of the theme. osTicket's language pack wins when
     * it knows the msgid; otherwise the theme catalogue of the current
     * language is used; otherwise the English msgid is returned (like __()).
     */
    function otdc__($msgid) {
        static $catalog = null;
        $text = __($msgid);
        if ($text !== $msgid)
            return $text;
        if ($catalog === null) {
            $catalog = array();
            $lang = (string) Internationalization::getCurrentLanguage();
            foreach (array($lang, substr($lang, 0, 2)) as $code) {
                if (!$code || !preg_match('/^[a-z]{2}(_[A-Za-z]{2})?$/', $code))
                    continue;
                $file = __DIR__ . '/i18n/' . $code . '.php';
                if (is_file($file)) {
                    $catalog = (array) include $file;
                    break;
                }
            }
        }
        return isset($catalog[$msgid]) ? $catalog[$msgid] : $msgid;
    }

    /** URL of a file in assets/ownalytics-ticket-de-caisse/. */
    function otdc_asset($path) {
        return ROOT_PATH . 'assets/' . OTDC_SLUG . '/' . $path . '?v=' . OTDC_VERSION;
    }

    /**
     * Pictogram of the Ownalytics logotype, redrawn from the website (no
     * standalone vector logo exists). $slot is the colour of the three
     * lines: the background the receipt sits on.
     */
    function otdc_mark($slot='#3B1E54') {
        return '<svg class="mark" width="20" height="26" viewBox="0 0 20 26" aria-hidden="true" focusable="false">'
            . '<path d="M1 1h18v21l-3 3-3-3-3 3-3-3-3 3-3-3z" fill="#FBFBF6"/>'
            . '<path d="M5 7h10M5 11h6M5 15h10" stroke="' . Format::htmlchars($slot) . '" stroke-width="2"/></svg>';
    }

    /** Remove <img class="... avatar ..."> tags from core-built HTML. */
    function otdc_strip_avatars($html) {
        return preg_replace('/<img\b[^>]*\bclass\s*=\s*["\'][^"\']*\bavatar\b[^"\']*["\'][^>]*>/i',
            '', (string) $html);
    }

    /**
     * Support contract level ("priority" / "standard") of a client, or
     * null when nothing reliable can be shown.
     *
     * Read from coderise_contract_level() of the OPTIONAL sla_first_response
     * plugin (contrib/sla-first-response, README sections 9 and 10). The
     * function only exists while the plugin and its instance are enabled:
     * without it, null (no level, no 24/7 commitment anywhere). A level
     * whose source is "default" (guest for null, Priority organisation not
     * configured or deleted, unknown user, error) is not shown either.
     * $subject: null (the signed-in client) or a Ticket (its OWNER, never a
     * collaborator). It is the CURRENT level of that person, not the one at
     * the time the ticket was opened. Memoised per person and per page.
     */
    function otdc_contract($subject=null) {
        static $memo = array();
        if (!function_exists('coderise_contract_level'))
            return null;
        if ($subject === null)
            $key = 'me';
        elseif ($subject instanceof Ticket)
            $key = 'u' . (int) $subject->getOwnerId();
        else
            return null;
        if (!array_key_exists($key, $memo)) {
            $memo[$key] = null;
            try {
                $l = coderise_contract_level($subject);
            }
            catch (Throwable $t) {
                $l = null;
            }
            if (is_array($l) && isset($l['level'], $l['source'])
                    && $l['source'] === 'organisation'
                    && in_array($l['level'], array('priority', 'standard'), true)) {
                $label = isset($l['label']) ? trim((string) $l['label']) : '';
                if ($label === '')
                    $label = $l['level'] == 'priority' ? 'Priority' : 'Standard';
                $memo[$key] = array('level' => $l['level'], 'label' => $label);
            }
        }
        return $memo[$key];
    }

    /**
     * Receipt line of a contract level (result of otdc_contract()), or ''.
     * "Caption ........ LEVEL" with dot leaders, then the commitment of the
     * level. Priority is stamped (plum on lilac, plum frame); Standard is
     * printed plainly. The label is typed by an administrator: escaped.
     */
    function otdc_contract_line($contract, $caption) {
        if (!$contract)
            return '';
        $priority = ($contract['level'] == 'priority');
        return sprintf('<dl class="otdc-contract otdc-contract--%s"><div>'
                . '<dt>%s</dt><dd class="otdc-contract__level">%s</dd>'
                . '<dd class="otdc-contract__terms">%s</dd></div></dl>',
            $priority ? 'priority' : 'standard',
            Format::htmlchars($caption),
            Format::htmlchars($contract['label']),
            Format::htmlchars($priority
                ? otdc__('First reply within 4 hours, 24/7')
                : otdc__('Reply on business days')));
    }
}
