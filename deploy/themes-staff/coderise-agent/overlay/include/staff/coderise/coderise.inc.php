<?php
/*********************************************************************
    include/staff/coderise/coderise.inc.php

    Helpers of the "coderise-agent" staff panel theme (Coderise).
    Not part of osTicket core: shipped by deploy/themes-staff/coderise-agent.

    1. Translation
       - cdr__()               osTicket's __() first, then the theme's own
                               catalogue (i18n/<lang>.php), else the msgid
    2. Output helpers
       - cdr_h()               HTML escaping
       - cdr_icon()            inline line icons (no icon font, no request)
       - cdr_initials()        initials of a name, instead of remote avatars
       - cdr_asset()           URL of a theme asset, with a cache-busting version
       - cdr_duration()        "44 min", "3 h 27"
    3. First-reply gauge (Priority support contract)
       - cdr_gauge_state()     pure computation of the gauge from timestamps
       - cdr_ticket_gauge()    the gauge of a ticket (reads the thread)
       - cdr_gauge_html()      the .gauge markup

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('CDR_VERSION')) {
    define('CDR_VERSION', '1.0.0');

    /*
     * Length of the Priority first-reply promise, in hours. Override it in
     * ost-config.php: define('CDR_FIRST_RESPONSE_HOURS', 4);
     */
    if (!defined('CDR_FIRST_RESPONSE_HOURS'))
        define('CDR_FIRST_RESPONSE_HOURS', 4);

    /*
     * Name of the SLA plan that carries the Priority first-reply deadline
     * (contrib/sla-first-response: "Priority 4h"). When a ticket is on that
     * active plan, its due date is the gauge deadline; otherwise the
     * deadline is created + CDR_FIRST_RESPONSE_HOURS. Overridable too.
     */
    if (!defined('CDR_SLA_PRIORITY'))
        define('CDR_SLA_PRIORITY', 'Priority 4h');

    /* ------------------------------------------------------------------
     * 1. Translation
     * ---------------------------------------------------------------- */

    /**
     * Translate a theme string. osTicket's language pack wins when it has
     * the msgid; otherwise the theme catalogue of the current language
     * (i18n/<lang>.php, e.g. fr_FR then fr); otherwise the English msgid.
     * $GLOBALS['CDR_TEST_LANG'], when set, replaces the current language
     * and skips __() (tests only).
     */
    function cdr__($msgid) {
        static $catalogs = array();
        $lang = '';
        if (isset($GLOBALS['CDR_TEST_LANG'])) {
            $lang = (string) $GLOBALS['CDR_TEST_LANG'];
        }
        else {
            $text = __($msgid);
            if ($text !== $msgid)
                return $text;
            if (class_exists('Internationalization'))
                $lang = (string) Internationalization::getCurrentLanguage();
        }
        if (!isset($catalogs[$lang])) {
            $catalogs[$lang] = array();
            foreach (array($lang, substr($lang, 0, 2)) as $code) {
                if (!$code || !preg_match('/^[a-z]{2}(_[A-Za-z]{2})?$/', $code))
                    continue;
                $file = __DIR__ . '/i18n/' . $code . '.php';
                if (is_file($file)) {
                    $catalogs[$lang] = (array) include $file;
                    break;
                }
            }
        }
        return isset($catalogs[$lang][$msgid]) ? $catalogs[$lang][$msgid] : $msgid;
    }

    /* ------------------------------------------------------------------
     * 2. Output helpers
     * ---------------------------------------------------------------- */

    /** HTML-escape a value (core's Format is not loaded in the CLI test). */
    function cdr_h($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** Line icon (24 grid, constant 1.75 stroke, rounded ends), decorative. */
    function cdr_icon($name, $size=18) {
        static $paths = array(
            'inbox' => '<path d="m4 13 2-8h12l2 8v6H4z"/><path d="M4 13h5l1 2h4l1-2h5"/>',
            'ticket' => '<path d="M4 7.5A1.5 1.5 0 0 1 5.5 6h13A1.5 1.5 0 0 1 20 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 16.5V14a2 2 0 0 0 0-4z"/><path d="M14.5 6v12"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.8-5.5 6.5-5.5s6.5 1.9 6.5 5.5M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c2.2.7 3.5 2.4 3.5 5.2"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-6.5 8-6.5s8 2.3 8 6.5"/>',
            'book' => '<path d="M5 5a2 2 0 0 1 2-2h12v15H7a2 2 0 0 0-2 2z"/><path d="M5 20a1 1 0 0 0 1 1h13v-3"/>',
            'tasks' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8 12 3 3 5-6"/>',
            'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
            'sliders' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'tag' => '<path d="M4 4h8l8 8-8 8-8-8z"/><path d="M8 8h.01"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
            'filter' => '<path d="M4 5h16l-6 8v6l-4-2v-4z"/>',
            'layers' => '<path d="m12 3.5 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
            'plug' => '<path d="M9 3v5M15 3v5M6 8h12v3a6 6 0 0 1-12 0zM12 17v4"/>',
            'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 7l3 3"/>',
            'building' => '<rect x="5" y="3.5" width="14" height="17" rx="1.5"/><path d="M9 8h.01M15 8h.01M9 12h.01M15 12h.01M10 20.5v-4h4v4"/>',
            'gauge' => '<path d="M4 18a8 8 0 1 1 16 0"/><path d="m12 18 4-5M7 18h.01M17 18h.01"/>',
            'alert' => '<path d="M12 4 3 19.5h18z"/><path d="M12 10v4.5M12 17.2h.01"/>',
            'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
            'checkc' => '<circle cx="12" cy="12" r="8.5"/><path d="m8 12.2 3 3 5-5.7"/>',
            'lock' => '<rect x="5" y="11" width="14" height="9.5" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'bolt' => '<path d="m13 3-8 11h6l-1 7 8-11h-6z"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4M4 4v4h4M4 13a8 8 0 0 0 14.5 4M20 20v-4h-4"/>',
            'star' => '<path d="m12 4 2.4 5 5.4.7-4 3.8 1 5.4-4.8-2.7L7.2 19l1-5.4-4-3.8L9.6 9z"/>',
            'upload' => '<path d="M12 16V4m-5 5 5-5 5 5M4 20h16"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>',
            'message' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H9l-5 4z"/>',
            'clip' => '<path d="M20 11.5 12 19.5a5 5 0 0 1-7-7l8-8a3.4 3.4 0 0 1 4.8 4.8l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/>',
            'swap' => '<path d="M4 8h15m-4-4 4 4-4 4M20 16H5m4-4-4 4 4 4"/>',
            'merge' => '<path d="M7 4v6a5 5 0 0 0 5 5h5M17 15l-3-3M17 15l-3 3M7 20V10"/>',
            'ban' => '<circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>',
            'chevr' => '<path d="m9 6 6 6-6 6"/>',
            'chevd' => '<path d="m6 9 6 6 6-6"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'logout' => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M16 8l4 4-4 4M20 12H9"/>',
            'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        );
        if (!isset($paths[$name]))
            return '';
        $s = (int) $size;
        return sprintf('<svg class="ic ic-%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            $name, $s, $s, $paths[$name]);
    }

    /** One or two initials of a name, not escaped (use cdr_h() to print). */
    function cdr_initials($name) {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words)
            return '?';
        $out = mb_substr($words[0], 0, 1);
        if (count($words) > 1)
            $out .= mb_substr($words[count($words) - 1], 0, 1);
        return mb_strtoupper($out);
    }

    /**
     * URL of a file in scp/coderise/. The version is the file's mtime so a
     * changed stylesheet is never served stale (CDR_VERSION as a fallback).
     */
    function cdr_asset($path) {
        $v = CDR_VERSION;
        if (defined('ROOT_DIR') && ($t = @filemtime(ROOT_DIR . 'scp/coderise/' . $path)))
            $v = $t;
        return ROOT_PATH . 'scp/coderise/' . $path . '?v=' . $v;
    }

    /** "44 min", "3 h 27", "1 h 05": whole minutes (seconds are dropped). */
    function cdr_duration($seconds) {
        $m = (int) floor(max(0, (int) $seconds) / 60);
        if ($m < 60)
            return $m . ' min';
        return sprintf('%d h %02d', intdiv($m, 60), $m % 60);
    }

    /* ------------------------------------------------------------------
     * 3. First-reply gauge
     * ---------------------------------------------------------------- */

    /**
     * State of the first-reply gauge from timestamps (no osTicket needed).
     * Returns array('kind' => ok|warn|over|done, 'ratio' => 0..1,
     * 'label' => string), or null when the window is empty (due <= start).
     */
    function cdr_gauge_state($start, $due, $firstResponse, $now) {
        $start = (int) $start;
        $due = (int) $due;
        $now = (int) $now;
        if ($due <= $start)
            return null;
        $span = $due - $start;
        if ($firstResponse !== null) {
            $first = (int) $firstResponse;
            $ratio = min(1.0, max(0.0, ($first - $start) / $span));
            if ($first <= $due)
                return array('kind' => 'done', 'ratio' => (float) $ratio,
                    'label' => sprintf(cdr__('Met in %s'), cdr_duration($first - $start)));
            return array('kind' => 'over', 'ratio' => (float) $ratio,
                'label' => sprintf(cdr__('Returned %s late'), cdr_duration($first - $due)));
        }
        if ($now > $due)
            return array('kind' => 'over', 'ratio' => 1.0,
                'label' => sprintf(cdr__('Over by %s'), cdr_duration($now - $due)));
        $ratio = min(1.0, max(0.0, ($now - $start) / $span));
        return array('kind' => $ratio >= 0.75 ? 'warn' : 'ok', 'ratio' => (float) $ratio,
            'label' => sprintf(cdr__('%s left'), cdr_duration($due - $now)));
    }

    /**
     * Gauge of a ticket, or null when it does not apply. Rule (contrib/
     * sla-first-response): the owner is on the Priority contract; the ticket
     * is open or already has a qualifying reply. The window starts at the
     * ticket creation (no new window on reopen). The first response is the
     * earliest visible, non-system staff reply (type R, staff_id > 0) that
     * is addressed to the client (a "to" recipient, as the plugin's
     * isClientReply()). The deadline is the Priority SLA plan's due date
     * when the ticket is on that plan, else created + CDR_FIRST_RESPONSE_HOURS.
     * Memoised per ticket for the request.
     */
    function cdr_ticket_gauge($t) {
        static $memo = array();
        if (!is_object($t) || !function_exists('coderise_contract_level')
                || !class_exists('ThreadEntry'))
            return null;
        $key = (int) $t->getId();
        if (array_key_exists($key, $memo))
            return $memo[$key];
        $memo[$key] = null;
        try {
            $l = coderise_contract_level($t);
            if (!is_array($l) || !isset($l['level']) || $l['level'] !== 'priority')
                return null;
            $created = $t->getCreateDate();
            $start = $created ? (int) Misc::db2gmtime($created) : 0;
            if ($start <= 0)
                return null;

            $first = null;
            if ($tid = $t->getThreadId()) {
                $rows = ThreadEntry::objects()
                    ->filter(array('thread_id' => $tid, 'type' => 'R',
                        'staff_id__gt' => 0, 'created__gte' => $created))
                    ->exclude(array('flags__hasbit' => ThreadEntry::FLAG_HIDDEN))
                    ->exclude(array('flags__hasbit' => ThreadEntry::FLAG_SYSTEM))
                    ->exclude(array('poster__startswith' => 'SYSTEM'))
                    ->order_by('created')
                    ->values_flat('created', 'recipients');
                foreach ($rows as $r) {
                    $to = is_string($r[1]) ? json_decode($r[1], true) : null;
                    if (is_array($to) && !empty($to['to'])) {
                        $first = (int) Misc::db2gmtime($r[0]);
                        break;
                    }
                }
            }
            if (!$t->isOpen() && $first === null)
                return null;

            $due = $start + CDR_FIRST_RESPONSE_HOURS * 3600;
            if (CDR_SLA_PRIORITY !== '' && ($sla = $t->getSLA()) && $sla->isActive()
                    && strcasecmp(trim((string) $sla->getName()), CDR_SLA_PRIORITY) === 0
                    && ($d = $t->getSLADueDate()))
                $due = (int) Misc::db2gmtime($d);

            $memo[$key] = cdr_gauge_state($start, $due, $first, time());
        }
        catch (Throwable $e) {
            $memo[$key] = null;
        }
        return $memo[$key];
    }

    /** The .gauge markup of the mockup, '' when there is no gauge. */
    function cdr_gauge_html($g, $large=false) {
        if (!is_array($g))
            return '';
        $kind = isset($g['kind']) ? $g['kind'] : 'ok';
        $icon = array('ok' => 'clock', 'warn' => 'clock', 'over' => 'alert', 'done' => 'checkc');
        $cls = 'gauge' . ($kind != 'ok' && isset($icon[$kind]) ? ' gauge-' . $kind : '')
            . ($large ? ' gauge-lg' : '');
        $pct = (int) round(100 * min(1, max(0, (float) $g['ratio'])));
        $label = cdr_h($g['label']);
        return '<div class="' . $cls . '" role="img" aria-label="' . $label . '">'
            . '<span class="gauge-bar"><i style="width:' . $pct . '%"></i></span>'
            . '<span class="gauge-txt">' . cdr_icon(isset($icon[$kind]) ? $icon[$kind] : 'clock', 13)
            . $label . '</span></div>';
    }
}
