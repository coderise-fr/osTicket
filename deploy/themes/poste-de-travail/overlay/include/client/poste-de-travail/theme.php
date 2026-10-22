<?php
/*********************************************************************
    include/client/poste-de-travail/theme.php

    Helpers of the "Poste de travail" client portal theme (Ownalytics).
    Not part of osTicket core: shipped by deploy/themes/poste-de-travail.

    - ptdt__()        translation: osTicket's __() first, then the theme's
                      own catalogue for strings that osTicket does not have
    - ptdt_icon()     inline SVG icons (no icon font, no network request)
    - ptdt_initials() initials of a name, used instead of external avatars
    - ptdt_asset()    URL of a theme asset with a cache-busting version

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('PTDT_VERSION')) {
    define('PTDT_VERSION', '1.0.0');

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

    /** Inline SVG icon, decorative (aria-hidden). */
    function ptdt_icon($name, $size=16) {
        static $paths = array(
            'home'    => '<path d="M4 11 12 4l8 7v9h-5v-6H9v6H4z"/>',
            'ticket'  => '<path d="M4 6h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
            'plus'    => '<path d="M12 5v14M5 12h14"/>',
            'book'    => '<path d="M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3zM8 20a3 3 0 0 1-3-3"/>',
            'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
            'status'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2M8.5 11l2 2 3.5-4"/>',
            'doc'     => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
            'folder'  => '<path d="M3 6h7l2 2h9v11H3z"/>',
            'print'   => '<path d="M7 9V3h10v6M7 17H4v-8h16v8h-3"/><path d="M7 14h10v7H7z"/>',
            'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.9M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9M20 20v-4h-4"/>',
            'clip'    => '<path d="m20 11-8.5 8.5a5 5 0 0 1-7-7L13 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L14 7"/>',
            'open'    => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
            'closed'  => '<circle cx="12" cy="12" r="8"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
            'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'link'    => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
            'sort'    => '<path d="m8 9 4-4 4 4M8 15l4 4 4-4"/>',
            'up'      => '<path d="m7 14 5-5 5 5"/>',
            'down'    => '<path d="m7 10 5 5 5-5"/>',
            'back'    => '<path d="m14 6-6 6 6 6"/>',
            'next'    => '<path d="m10 6 6 6-6 6"/>',
            'group'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
            'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7v1"/>',
        );
        if (!isset($paths[$name]))
            return '';
        $s = (int) $size;
        return sprintf('<svg class="ic" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false">%s</svg>',
            $s, $s, $paths[$name]);
    }

    /** Pictogram of the Ownalytics logotype (a receipt), redrawn from the mockups. */
    function ptdt_logo_mark() {
        return '<svg class="mark" width="20" height="26" viewBox="0 0 20 26" aria-hidden="true" focusable="false"><path d="M1 1h18v21l-3 3-3-3-3 3-3-3-3 3-3-3z" fill="#FBFBF6"/><path d="M5 7h10M5 11h6M5 15h10" stroke="#3B1E54" stroke-width="2"/></svg>';
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
}
