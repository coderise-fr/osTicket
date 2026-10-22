<?php
/*********************************************************************
    include/client/poste-de-travail/theme.php

    Helpers of the "Poste de travail" client portal theme (Coderise).
    Not part of osTicket core: shipped by deploy/themes/poste-de-travail.

    - ptdt__()            translation: osTicket's __() first, then the theme's
                          own catalogue for strings that osTicket does not have
    - ptdt_icon()         inline SVG icons, line style (no icon font, no request)
    - ptdt_logo()         Coderise horizontal logo (file supplied, not redrawn)
    - ptdt_signature()    brand signature, under the logo
    - ptdt_initials()     initials of a name, used instead of external avatars
    - ptdt_asset()        URL of a theme asset with a cache-busting version
    - ptdt_priority_sla() data of the "Support Priority" first response
                          indicator of a ticket, or null

    Settings (define them before this file is loaded to change them, e.g. in
    include/ost-config.php; otherwise the defaults below apply):
    - PTDT_PRIORITY_SLA   name of the SLA plan of Priority tickets that still
                          wait for their first response (contrib/sla-first-response
                          switches the ticket to a disabled plan afterwards)
    - PTDT_CONTACT_*      contact details shown in the rail and the footer

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('PTDT_VERSION')) {
    define('PTDT_VERSION', '2.0.0');

    foreach (array(
        'PTDT_PRIORITY_SLA' => 'Priority 4h',
        'PTDT_CONTACT_EMAIL' => 'contact@coderise.fr',
        'PTDT_CONTACT_PHONE' => '+33 (0)6 69 93 92 86',
        'PTDT_CONTACT_PHONE_URI' => '+33669939286',
        'PTDT_CONTACT_CITY' => 'Aix-en-Provence',
        'PTDT_URL_SITE' => 'https://www.coderise.fr',
        'PTDT_URL_LINKEDIN' => 'https://www.linkedin.com/company/coderise-fr',
        'PTDT_URL_GITHUB' => 'https://github.com/coderise-fr',
    ) as $k => $v) {
        if (!defined($k))
            define($k, $v);
    }

    /**
     * Translate a theme string. osTicket's language pack wins when it has the
     * msgid; otherwise the theme catalogue for the current language
     * (i18n/<lang>.php) is used; otherwise the English msgid is returned.
     */
    function ptdt__($msgid) {
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

    /** Inline SVG icon, decorative (aria-hidden). Line style of the brand:
     *  constant 1.75 stroke, slightly rounded ends, 24 px grid. */
    function ptdt_icon($name, $size=18) {
        static $paths = array(
            'home'    => '<path d="m4 11 8-7 8 7"/><path d="M6 10v10h4v-6h4v6h4V10"/>',
            'ticket'  => '<path d="M4 7.5A1.5 1.5 0 0 1 5.5 6h13A1.5 1.5 0 0 1 20 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 16.5V14a2 2 0 0 0 0-4z"/><path d="M14.5 6v12"/>',
            'plus'    => '<path d="M12 5v14M5 12h14"/>',
            'book'    => '<path d="M5 5a2 2 0 0 1 2-2h12v15H7a2 2 0 0 0-2 2z"/><path d="M5 20a1 1 0 0 0 1 1h13v-3"/>',
            'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
            'status'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2M8.5 11l2 2 3.5-4"/>',
            'doc'     => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
            'folder'  => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'print'   => '<path d="M7 9V3.5h10V9M7 17H4v-7h16v7h-3M7 14h10v6.5H7z"/>',
            'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4M4 4v4h4M4 13a8 8 0 0 0 14.5 4M20 20v-4h-4"/>',
            'clip'    => '<path d="M20 11.5 12 19.5a5 5 0 0 1-7-7l8-8a3.4 3.4 0 0 1 4.8 4.8l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/>',
            'open'    => '<path d="m4 13 2-8h12l2 8v6H4z"/><path d="M4 13h5l1 2h4l1-2h5"/>',
            'closed'  => '<rect x="5" y="11" width="14" height="9.5" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-6.5 8-6.5s8 2.3 8 6.5"/>',
            'group'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.8-5.5 6.5-5.5s6.5 1.9 6.5 5.5M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c2.2.7 3.5 2.4 3.5 5.2"/>',
            'sort'    => '<path d="m8 9 4-4 4 4M8 15l4 4 4-4"/>',
            'up'      => '<path d="m7 14 5-5 5 5"/>',
            'down'    => '<path d="m6 9 6 6 6-6"/>',
            'next'    => '<path d="m9 6 6 6-6 6"/>',
            'info'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8h.01"/>',
            'alert'   => '<path d="M12 4 3 19.5h18z"/><path d="M12 10v4.5M12 17.2h.01"/>',
            'clock'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
            'bolt'    => '<path d="m13 3-8 11h6l-1 7 8-11h-6z"/>',
            'phone'   => '<path d="M5.5 4H9l1.8 4.5-2.3 1.5a11 11 0 0 0 5.5 5.5l1.5-2.3 4.5 1.8v3.5a1.8 1.8 0 0 1-2 1.8A16 16 0 0 1 3.7 6 1.8 1.8 0 0 1 5.5 4z"/>',
            'mail'    => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'pin'     => '<path d="M12 21s7-6 7-11.5a7 7 0 0 0-14 0C5 15 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'globe'   => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c3 3 3 14 0 17M12 3.5c-3 3-3 14 0 17"/>',
            'linkedin'=> '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10.5V16M8 7.8h.01M12 16v-5.5M12 13a2.5 2.5 0 0 1 5 0v3"/>',
            'github'  => '<path d="M9 19c-4 1.2-4-2-6-2.5M15 21v-3.2a3 3 0 0 0-.9-2.4c3-.3 6-1.5 6-6.6a5 5 0 0 0-1.3-3.4 4.6 4.6 0 0 0-.1-3.4s-1.1-.3-3.6 1.4a12.3 12.3 0 0 0-6.2 0C6.4 3.2 5.3 3.5 5.3 3.5a4.6 4.6 0 0 0-.1 3.4A5 5 0 0 0 3.9 10.3c0 5.1 3 6.3 6 6.6a3 3 0 0 0-.9 2.4V21"/>',
            'login'   => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4M10 8l4 4-4 4M14 12H4"/>',
            'logout'  => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M15 8l4 4-4 4M19 12H9"/>',
            'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'message' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H9l-5 4z"/>',
            'help'    => '<circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>',
        );
        if (!isset($paths[$name]))
            return '';
        $s = (int) $size;
        return sprintf('<svg class="ic" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            $s, $s, $paths[$name]);
    }

    /** Coderise horizontal logo for dark (bleu nuit) areas. The file is the
     *  supplied logo, cut out without retouching (docs/theme-mockups-coderise). */
    function ptdt_logo($class='logo') {
        return sprintf('<img class="%s" src="%s" width="2041" height="345" alt="Coderise">',
            $class, ptdt_asset('img/logo-horizontal-sur-bleu-nuit.png'));
    }

    /** Brand signature (charter): "décoller" carries the cyan accent. */
    function ptdt_signature() {
        return sprintf('<p class="signature">%s</p>', sprintf(
            Format::htmlchars(ptdt__('The code that makes your projects %s')),
            '<span class="up">'.Format::htmlchars(ptdt__('take off')).'</span>'));
    }

    /** One or two initials of a person's name, HTML-escaped. */
    function ptdt_initials($name) {
        $name = trim((string) $name);
        if ($name === '')
            return '?';
        $words = preg_split('/[\s\-\.@_]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        foreach ($words as $w) {
            $out .= mb_substr($w, 0, 1);
            if (mb_strlen($out) >= 2)
                break;
        }
        return Format::htmlchars(mb_strtoupper($out));
    }

    /** URL of a file in assets/poste-de-travail/. */
    function ptdt_asset($path) {
        return ROOT_PATH . 'assets/poste-de-travail/' . $path . '?v=' . PTDT_VERSION;
    }

    /**
     * "Support Priority" first response indicator (to be validated). Shown
     * only when every piece of data is real and readable:
     *   - the ticket is open and has no response of the team yet;
     *   - its SLA plan is active and is named PTDT_PRIORITY_SLA;
     *   - its SLA due date (est_duedate) is set.
     * contrib/sla-first-response moves the ticket to a disabled plan at the
     * first response: the plan is no longer active and the due date is
     * cleared, so the indicator disappears by itself.
     * Returns array(start, due, ratio 0..1, overdue bool) or null.
     */
    function ptdt_priority_sla($ticket, $responses) {
        if (!$ticket || !$ticket->isOpen() || $responses > 0)
            return null;
        if (!($sla = $ticket->getSLA()) || !$sla->isActive())
            return null;
        if (strcasecmp(trim((string) $sla->name), PTDT_PRIORITY_SLA) !== 0)
            return null;
        if (!($due = $ticket->est_duedate))
            return null;
        $start = $ticket->getReopenDate() ?: $ticket->getCreateDate();
        $t0 = Misc::db2gmtime($start);
        $t1 = Misc::db2gmtime($due);
        if (!$t0 || !$t1 || $t1 <= $t0)
            return null;
        $ratio = (time() - $t0) / ($t1 - $t0);
        return array($start, $due, max(0, min(1, $ratio)), time() > $t1);
    }
}
