<?php
/*********************************************************************
    include/staff/coderise/coderise.inc.php

    Helpers of the "coderise-agent" staff panel theme (Coderise).
    Not part of osTicket core: shipped by deploy/themes-staff/coderise-agent.

    1. Translation
       - cdr__()               osTicket's __() first, then the theme's own
                               catalogue (i18n/<lang>.php), else the msgid
       - cdr_t()               the theme's catalogue first, then cdr__()
       - cdr_catalog()         the theme catalogue of the current language
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
       - cdr_ticket_sla_panel() the "First reply" panel of the ticket view
    4. Shell (header.inc.php)
       - cdr_rail()            the <aside class="rail"> (mode switch, menu,
                               queues, profile and log out)
       - cdr_agent_tabs()      agent tabs in the mockup's order
       - cdr_tab_label()       label of a tab, in the theme's wording
       - cdr_staff_line()      the department / role line of the rail foot
       - cdr_find_queue()      a queue of the loaded queue tree, by id
       - cdr_crumbs()          the breadcrumb of the top bar
       - cdr_content_class()   the classes of #content
    5. Ticket identity (ticket view, object.view signal)
       - cdr_ticket_idline()   pure: the identity line and the client
                               subtitle from plain data
       - cdr_ticket_idline_data() that data, read from a Ticket
       - cdr_plan_chip()       the contract chip (Priority / Standard)
    6. Queue (templates/queue-tickets.tmpl.php)
       - cdr_queue_layout()    where the subject, number and client columns
                               are, and where the first-reply cell goes
       - cdr_queue_client()    the "client, organisation" line under the
                               subject (plain name)
       - cdr_queue_client_html() the same line from core's rendered "From"
                               cell (link and annotations kept)
       - cdr_resolve_status_id() the status of "Mark as resolved" (ticket
                               view, header.inc.php)

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

    /*
     * Ticket priorities whose urgency is at or below this value are shown
     * as urgent in the identity line of the ticket view (core priorities:
     * 1 Emergency, 2 High, 3 Normal, 4 Low; the urgency, not the name,
     * decides, so renamed or translated priorities keep working).
     */
    if (!defined('CDR_URGENT_URGENCY'))
        define('CDR_URGENT_URGENCY', 2);

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
        global $CDR_TEST_LANG; // not $GLOBALS: setup/test UnitializedVars flags it
        if (!isset($CDR_TEST_LANG)) {
            $text = __($msgid);
            if ($text !== $msgid)
                return $text;
        }
        $catalog = cdr_catalog();
        return isset($catalog[$msgid]) ? $catalog[$msgid] : $msgid;
    }

    /**
     * Translate with the theme's wording first: the theme catalogue of the
     * current language wins over osTicket's pack, which still serves the
     * languages the theme has no catalogue for (then the msgid). Only for
     * labels whose pack wording differs from the mockup's (the rail tabs and
     * the crumbs: the pack's "Tableau de Bord" vs "Tableau de bord").
     */
    function cdr_t($msgid) {
        $catalog = cdr_catalog();
        return isset($catalog[$msgid]) ? $catalog[$msgid] : cdr__($msgid);
    }

    /**
     * The theme catalogue of the current language (i18n/<lang>.php, e.g.
     * fr_FR then fr), array() when there is none. $GLOBALS['CDR_TEST_LANG'],
     * when set, replaces the current language (tests only).
     */
    function cdr_catalog() {
        global $CDR_TEST_LANG;
        static $catalogs = array();
        $lang = '';
        if (isset($CDR_TEST_LANG))
            $lang = (string) $CDR_TEST_LANG;
        elseif (class_exists('Internationalization'))
            $lang = (string) Internationalization::getCurrentLanguage();
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
        return $catalogs[$lang];
    }

    /* ------------------------------------------------------------------
     * 2. Output helpers
     * ---------------------------------------------------------------- */

    /** HTML-escape a value (core's Format is not loaded in the CLI test). */
    function cdr_h($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Translated labels for the theme's JS, as data-cdr-<key> attributes
     * (leading space included): array('<key>' => '<English msgid>'), each
     * value translated with cdr__() and escaped. Keys outside [a-z0-9-] are
     * skipped.
     */
    function cdr_data_attrs($labels) {
        $out = '';
        foreach ((array) $labels as $key => $msgid) {
            if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $key))
                continue;
            $out .= ' data-cdr-' . $key . '="' . cdr_h(cdr__((string) $msgid)) . '"';
        }
        return $out;
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
     * Is this staff reply addressed to the client? Ruling I-4: the plugin's
     * own criterion, SlaFirstResponsePlugin::isClientReply($entry) (public
     * static; it reads only $entry->recipients), when the class is loaded;
     * otherwise the same rule: the recipients JSON has a non-empty "to".
     * $entry is any object with a `recipients` property (a ThreadEntry or
     * the row object built by cdr_ticket_gauge()).
     */
    function cdr_is_client_reply($entry) {
        if (!is_object($entry))
            return false;
        if (class_exists('SlaFirstResponsePlugin', false)
                && is_callable(array('SlaFirstResponsePlugin', 'isClientReply')))
            return (bool) SlaFirstResponsePlugin::isClientReply($entry);
        $raw = isset($entry->recipients) ? $entry->recipients : null;
        if (!is_string($raw) || $raw === '')
            return false;
        $list = json_decode($raw, true);
        return is_array($list) && !empty($list['to']);
    }

    /**
     * Gauge of a ticket, or null when it does not apply. Rule (contrib/
     * sla-first-response): the owner is on the Priority contract; the ticket
     * is open or already has a qualifying reply. The window starts at the
     * ticket creation (no new window on reopen). The first response is the
     * earliest visible, non-system staff reply (type R, staff_id > 0) that
     * is addressed to the client (a "to" recipient, as the plugin's
     * isClientReply()). The deadline is the Priority SLA plan's due date
     * when the ticket is on that plan and has never been reopened (core moves
     * that due date to the reopen date), else created + CDR_FIRST_RESPONSE_HOURS.
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
                    $entry = (object) array('created' => $r[0], 'recipients' => $r[1]);
                    if (cdr_is_client_reply($entry)) {
                        $first = (int) Misc::db2gmtime($r[0]);
                        break;
                    }
                }
            }
            if (!$t->isOpen() && $first === null)
                return null;

            // A reopened ticket keeps the window of its creation: core
            // recomputes est_duedate from the reopen date (Ticket::setStatus()
            // -> updateEstDueDate() -> getSLADueDate(), which starts from
            // getReopenDate()), so the plan's due date is ignored then.
            $due = $start + CDR_FIRST_RESPONSE_HOURS * 3600;
            if (!$t->getReopenDate()
                    && CDR_SLA_PRIORITY !== '' && ($sla = $t->getSLA()) && $sla->isActive()
                    && strcasecmp(trim((string) $sla->getName()), CDR_SLA_PRIORITY) === 0
                    && ($d = $t->getSLADueDate()))
                $due = (int) Misc::db2gmtime($d);

            $g = cdr_gauge_state($start, $due, $first, time());
            if (is_array($g))
                $g += array('start' => $start, 'due' => $due);
            $memo[$key] = $g;
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

    /**
     * The "First reply" panel of the ticket view (object.view signal, see
     * header.inc.php): the large gauge plus one line on the Priority
     * promise. '' when the ticket has no gauge (Standard owner, plugin
     * absent, closed without a reply).
     */
    function cdr_ticket_sla_panel($t) {
        $g = cdr_ticket_gauge($t);
        $html = cdr_gauge_html($g, true);
        if ($html === '')
            return '';
        $note = '';
        if (isset($g['start'], $g['due']) && $g['due'] > $g['start'])
            $note = '<p class="cdr-sla-note">' . cdr_h(sprintf(
                cdr__('Priority contract: first reply to the client within %s.'),
                cdr_duration($g['due'] - $g['start']))) . '</p>';
        return '<section class="cdr-sla panel" aria-labelledby="cdr-sla-h">'
            . '<div class="panel-h"><h2 id="cdr-sla-h">' . cdr_icon('gauge', 18)
            . cdr_h(cdr__('First reply')) . '</h2></div>'
            . '<div class="pad">' . $html . $note . '</div></section>';
    }

    /* ------------------------------------------------------------------
     * 4. Shell
     * ---------------------------------------------------------------- */

    /** Absolute URL of a navigation href, as navigation.tmpl.php does. */
    function cdr_nav_href($href) {
        $href = (string) $href;
        if ($href === '' || $href[0] != '/')
            $href = ROOT_PATH . 'scp/' . $href;
        return $href;
    }

    /** Icon of an admin sub-menu item, by its href. */
    function cdr_admin_icon($href) {
        static $map = array(
            'logs.php' => 'chart', 'audits.php' => 'search', 'system.php' => 'gauge',
            'settings.php?t=pages' => 'building', 'settings.php?t=system' => 'sliders',
            'settings.php?t=tickets' => 'ticket', 'settings.php?t=tasks' => 'tasks',
            'settings.php?t=agents' => 'user', 'settings.php?t=users' => 'users',
            'settings.php?t=kb' => 'book', 'helptopics.php' => 'tag', 'filters.php' => 'filter',
            'slas.php' => 'clock', 'schedules.php' => 'clock', 'apikeys.php' => 'key',
            'pages.php' => 'book', 'forms.php' => 'layers', 'lists.php' => 'menu',
            'plugins.php' => 'plug', 'emails.php' => 'mail', 'emailsettings.php' => 'sliders',
            'banlist.php' => 'ban', 'templates.php' => 'message', 'emailtest.php' => 'bolt',
            'staff.php' => 'users', 'teams.php' => 'user', 'roles.php' => 'key',
            'departments.php' => 'building',
        );
        $href = (string) $href;
        return isset($map[$href]) ? $map[$href] : 'layers';
    }

    /**
     * True when the admin sub-menu item $item is the current page. The
     * path must be the script itself (no substring test: emailsettings.php
     * is not emails.php) and every query parameter of the item must match
     * the request (settings.php?t=tickets). The item's 'urls' count as well.
     */
    function cdr_item_is_current($item) {
        $script = basename(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '');
        if ($script === '' || !isset($item['href']))
            return false;
        $parts = explode('?', (string) $item['href'], 2);
        if (strcasecmp(basename($parts[0]), $script) !== 0)
            return !empty($item['urls']) && is_array($item['urls'])
                && in_array($script, $item['urls']);
        if (isset($parts[1])) {
            $want = array();
            parse_str($parts[1], $want);
            foreach ($want as $k => $v)
                if (!isset($_GET[$k]) || (string) $_GET[$k] !== (string) $v)
                    return false;
        }
        return true;
    }

    /**
     * Index of the current item among $items, the sub-menu of tab $name, or
     * -1. Shared by the rail and the crumbs. Only the active tab can hold
     * the current item. getActiveMenu() wins when it points at an item;
     * otherwise (it is 0 or -1 on most pages) the exact script-and-query
     * match of cdr_item_is_current(), except on settings.php where every
     * tab is settings.php?t=... and getActiveMenu() is the only source.
     */
    function cdr_current_item($nav, $name, $items) {
        if (!$nav || !is_array($items) || $name === null || $name !== $nav->getActiveTab())
            return -1;
        $am = (int) $nav->getActiveMenu();
        if ($am > 0 && isset($items[$am - 1]) && is_array($items[$am - 1]))
            return $am - 1;
        if (basename(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '') === 'settings.php')
            return -1;
        foreach ($items as $k => $item)
            if (is_array($item) && empty($item['droponly']) && cdr_item_is_current($item))
                return $k;
        return -1;
    }

    /**
     * Read-only view of a StaffNav for core's sub-navigation.tmpl.php, which
     * prints $item['desc'] and $item['title'] raw: array items get them
     * through cdr__() and escaped; callables (the queue menus) are untouched.
     */
    class CdrNavView {
        private $nav;
        function __construct($nav) { $this->nav = $nav; }
        function getSubMenu($tab=null) {
            $sub = $this->nav->getSubMenu($tab);
            if (!is_array($sub))
                return $sub;
            foreach ($sub as $k => $item) {
                // A callable item (queue list, plugin entry) is called by the
                // core template with this wrapper: hand it the real nav
                // instead, a closure may type-hint StaffNav.
                if (is_callable($item)) {
                    $sub[$k] = function () use ($item) { return $item($this->nav); };
                    continue;
                }
                if (!is_array($item))
                    continue;
                foreach (array('desc', 'title') as $f)
                    if (isset($item[$f]))
                        $sub[$k][$f] = cdr_h(cdr__($item[$f]));
            }
            return $sub;
        }
        function getActiveMenu() { return $this->nav->getActiveMenu(); }
        function getActiveTab() { return $this->nav->getActiveTab(); }
        function getSubNavInfo() { return $this->nav->getSubNavInfo(); }
        function getTabs() { return $this->nav->getTabs(); }
    }

    /**
     * Agent tabs in the mockup's order: Dashboard, Tickets, Tasks, Users,
     * Knowledgebase (core: Dashboard, Users, Tasks, Tickets, Knowledgebase),
     * then any other tab (applications, plugins) in core's order. Keys and
     * tab arrays are kept as they are.
     */
    function cdr_agent_tabs($tabs) {
        if (!is_array($tabs))
            return array();
        $out = array();
        foreach (array('dashboard', 'tickets', 'tasks', 'users', 'kbase') as $k)
            if (isset($tabs[$k]))
                $out[$k] = $tabs[$k];
        foreach ($tabs as $k => $tab)
            if (!array_key_exists($k, $out))
                $out[$k] = $tab;
        return $out;
    }

    /**
     * Label of a rail tab (agent or admin), plain text. Core builds the tabs
     * with labels already translated (StaffNav/AdminNav: 'desc' =>
     * __('Dashboard')), so the theme's wording (cdr_t()) can only apply by
     * tab key, and only while the label is still core's own (a plugin that
     * renamed a tab keeps its label). Other labels go through cdr__().
     */
    function cdr_tab_label($name, $desc) {
        static $msgids = array('dashboard' => 'Dashboard', 'users' => 'Users',
            'tasks' => 'Tasks', 'tickets' => 'Tickets', 'kbase' => 'Knowledgebase',
            'apps' => 'Applications', 'settings' => 'Settings', 'manage' => 'Manage',
            'emails' => 'Emails', 'staff' => 'Agents');
        $desc = (string) $desc;
        if (is_string($name) && isset($msgids[$name])
                && ($desc === $msgids[$name] || $desc === __($msgids[$name])))
            return cdr_t($msgids[$name]);
        return cdr__($desc);
    }

    /**
     * The second line of the rail foot, plain text (escape to print): the
     * department, followed by the role for an administrator
     * ("Support, administrateur"), as the mockup's "Support Priority,
     * administratrice". '' when there is nothing to say.
     */
    function cdr_staff_line($staff) {
        if (!is_object($staff))
            return '';
        $dept = ($d = $staff->getDept()) ? trim((string) $d->getName()) : '';
        if (!$staff->isAdmin())
            return $dept;
        return $dept !== '' ? sprintf(cdr__('%s, administrator'), $dept)
            : cdr__('Administrator');
    }

    /**
     * The queue with id $id in core's queue tree (CustomQueue::
     * getHierarchicalQueues(): a list of array($queue, $children)), or null.
     * No query: the tree is the one scp/tickets.php already loaded.
     */
    function cdr_find_queue($tree, $id) {
        if (!is_array($tree) || !is_numeric($id) || (int) $id <= 0)
            return null;
        foreach ($tree as $node) {
            if (!is_array($node) || !isset($node[0]) || !is_object($node[0]))
                continue;
            if ((int) $node[0]->getId() === (int) $id)
                return $node[0];
            if (isset($node[1]) && ($q = cdr_find_queue($node[1], $id)))
                return $q;
        }
        return null;
    }

    /**
     * The rail of the staff panel. $nav (StaffNav or AdminNav) may be null
     * (profile.php); $staff is the logged-in agent. Admin mode is the
     * ADMINPAGE constant set by scp/admin.inc.php. One markup for every
     * width: below 920 px the rail collapses behind the "Menu" button.
     */
    function cdr_rail($nav, $staff) {
        global $ost;
        $admin = defined('ADMINPAGE');
        $isAdmin = is_object($staff) && $staff->isAdmin();
        $tabs = ($nav && is_array($t = $nav->getTabs())) ? $t : array();
        $active = $nav ? $nav->getActiveTab() : null;

        $o = '<aside class="rail on-dark" aria-label="' . cdr_h(cdr__('Menu')) . '">'
            . '<div class="rail-top"><a class="brand no-pjax" href="' . ROOT_PATH . 'scp/index.php">'
            . '<img class="logo" src="' . cdr_h(cdr_asset('img/logo-horizontal-sombre.png'))
            . '" width="2041" height="345" alt="Coderise"></a>'
            . '<button type="button" class="menu-toggle" aria-expanded="false" aria-controls="cdr-rail-menu">'
            . cdr_icon('menu', 18) . cdr_h(cdr__('Menu')) . '</button></div>'
            . '<div class="rail-body" id="cdr-rail-menu">';

        // Mode switch: only an administrator has two spaces.
        if ($isAdmin) {
            $o .= '<div class="mode" role="group" aria-label="' . cdr_h(cdr__('Panel')) . '">'
                . '<a class="no-pjax" href="' . ROOT_PATH . 'scp/index.php"' . ($admin ? '' : ' aria-current="true"') . '>'
                . cdr_icon('inbox', 16) . cdr_h(cdr__('Agent')) . '</a>'
                . '<a class="no-pjax" href="' . ROOT_PATH . 'scp/admin.php"' . ($admin ? ' aria-current="true"' : '') . '>'
                . cdr_icon('sliders', 16) . cdr_h(cdr__('Administration')) . '</a></div>';
        }

        if ($tabs) {
            $o .= '<nav class="menu" aria-label="' . cdr_h(cdr__('Main menu')) . '">';
            if (!$admin) {
                static $icons = array('dashboard' => 'chart', 'users' => 'users',
                    'tasks' => 'tasks', 'tickets' => 'ticket', 'kbase' => 'book');
                $o .= '<div class="group"><ul>';
                foreach (cdr_agent_tabs($tabs) as $name => $tab) {
                    $cur = !empty($tab['active']) || $name === $active;
                    $cls = trim('item ' . (isset($tab['class']) ? preg_replace('/[^a-z0-9_ -]/i', '', $tab['class']) : ''));
                    $o .= '<li><a class="' . $cls . '" href="' . cdr_h(cdr_nav_href($tab['href'])) . '"'
                        . ($cur ? ' aria-current="page"' : '') . '>'
                        . cdr_icon(isset($icons[$name]) ? $icons[$name] : 'layers')
                        . cdr_h(cdr_tab_label($name, $tab['desc'])) . '</a>';
                    if ($cur) {
                        // Core sub-navigation (queues, counts, "add personal
                        // queue"), renamed so jb.overflow.menu.js leaves it be.
                        // A closure keeps the template's variables out of this scope.
                        // CdrNavView translates and escapes the labels of the
                        // array items; closures (queues) pass through as is.
                        $render = function ($nav) {
                            include STAFFINC_DIR . 'templates/sub-navigation.tmpl.php';
                        };
                        ob_start();
                        $render(new CdrNavView($nav));
                        $sub = trim(ob_get_clean());
                        if ($sub !== '') {
                            $sub = str_replace('id="customQ_nav"', 'id="cdr-queues"', $sub);
                            $sub = preg_replace('/\bjb-overflowmenu\b/', '', $sub);
                            $o .= '<div class="queues">' . $sub . '</div>';
                        }
                    }
                    $o .= '</li>';
                }
                $o .= '</ul></div>';
            }
            else {
                foreach ($tabs as $name => $tab) {
                    $items = $nav->getSubMenu($name) ?: array();
                    $current = cdr_current_item($nav, $name, $items);
                    $o .= '<div class="group"><p class="group-title">' . cdr_h(cdr_tab_label($name, $tab['desc'])) . '</p><ul>';
                    foreach ($items as $k => $item) {
                        if (!is_array($item) || !empty($item['droponly']) || !isset($item['href']))
                            continue;
                        $o .= '<li><a class="item" href="' . cdr_h(cdr_nav_href($item['href'])) . '"'
                            . ($k === $current ? ' aria-current="page"' : '') . '>'
                            . cdr_icon(cdr_admin_icon($item['href']))
                            . cdr_h(cdr__($item['desc'])) . '</a></li>';
                    }
                    $o .= '</ul></div>';
                }
            }
            $o .= '</nav>';
        }

        // Foot: who is logged in, profile, log out.
        $name = is_object($staff) ? (string) $staff->getName() : '';
        $line = cdr_staff_line($staff);
        $token = is_object($ost) ? $ost->getLinkToken() : '';
        // "Sign Out" (pack: "Se déconnecter", the mockup's wording) rather
        // than core's "Log Out" ("Déconnexion")
        $o .= '<div class="foot"><div class="me"><span class="avatar" aria-hidden="true">'
            . cdr_h(cdr_initials($name)) . '</span><span><b>' . cdr_h($name) . '</b>'
            . ($line !== '' ? '<small>' . cdr_h($line) . '</small>' : '') . '</span></div>'
            . '<a class="item" href="' . ROOT_PATH . 'scp/profile.php">' . cdr_icon('user')
            . cdr_h(cdr__('My Profile')) . '</a>'
            . '<a class="item no-pjax" href="' . ROOT_PATH . 'scp/logout.php?auth=' . cdr_h($token) . '">'
            . cdr_icon('logout') . cdr_h(cdr__('Sign Out')) . '</a></div>';

        return $o . '</div></aside>';
    }

    /**
     * Breadcrumb of the top bar: [Administration /] active tab / $current
     * (queue name, "Ticket #123"), or the active sub-menu item when
     * $current is null. $parent, array(label, href), is a crumb between the
     * tab and $current (the queue the ticket was opened from). Labels are
     * plain text (escaped here). '' when there is nothing to show.
     */
    function cdr_crumbs($nav, $current=null, $admin=false, $parent=null) {
        $crumbs = array();
        if ($admin)
            $crumbs[] = array(cdr__('Administration'), ROOT_PATH . 'scp/admin.php');
        if ($nav && ($t = $nav->getActiveTab())) {
            $tabs = $nav->getTabs();
            if (is_array($tabs) && isset($tabs[$t]))
                $crumbs[] = array(cdr_tab_label($t, $tabs[$t]['desc']), cdr_nav_href($tabs[$t]['href']));
            if (is_array($parent) && isset($parent[0], $parent[1])
                    && (string) $parent[0] !== '')
                $crumbs[] = array((string) $parent[0], (string) $parent[1]);
            if ($current === null) {
                $sub = $nav->getSubMenu($t);
                $k = cdr_current_item($nav, $t, $sub);
                if ($k >= 0 && isset($sub[$k]['desc']))
                    $current = cdr__($sub[$k]['desc']);
            }
        }
        // Not repeated when it is the previous crumb's label, whatever the
        // case: the tab label is the theme's wording ("Tableau de bord"),
        // the sub-menu item the pack's ("Tableau de Bord").
        if ($current !== null && (string) $current !== ''
                && (!$crumbs || mb_strtolower(trim((string) $current), 'UTF-8')
                    !== mb_strtolower(trim((string) $crumbs[count($crumbs) - 1][0]), 'UTF-8')))
            $crumbs[] = array((string) $current, null);
        if (!$nav || !$crumbs)
            return '';
        $o = '';
        $last = count($crumbs) - 1;
        foreach ($crumbs as $i => $c)
            $o .= '<li>' . ($i === $last
                ? '<span aria-current="page">' . cdr_h($c[0]) . '</span>'
                : '<a href="' . cdr_h($c[1]) . '">' . cdr_h($c[0]) . '</a>') . '</li>';
        return '<nav class="crumbs" aria-label="' . cdr_h(cdr__('Breadcrumb')) . '"><ol>' . $o . '</ol></nav>';
    }

    /** Classes of #content: "cdr cdr-<script>" [+ cdr-ticket] [+ cdr-is-admin]. */
    function cdr_content_class($script, $ticketView=false, $admin=false) {
        $base = preg_replace('/[^a-z0-9_-]/i', '', basename((string) $script, '.php'));
        return 'cdr' . ($base !== '' ? ' cdr-' . $base : '')
            . ($ticketView ? ' cdr-ticket' : '') . ($admin ? ' cdr-is-admin' : '');
    }

    /* ------------------------------------------------------------------
     * 5. Ticket identity
     * ---------------------------------------------------------------- */

    /**
     * The identity block of the ticket view (mockup .idline and .sub):
     * "Ticket #N | state | contract | urgency" and "Client, organisation.
     * Received on <date> at <time> via <source>." Pure: $d is plain data
     * (see cdr_ticket_idline_data()); every value is escaped here, every
     * missing part is left out. Keys: number, href, reload (link title),
     * state (open|closed|archived|deleted), status (status name), plan
     * (null or array(level, label)), urgent (null or the priority name),
     * name, org, date, time, source. '' without a number.
     */
    function cdr_ticket_idline($d) {
        $d = is_array($d) ? $d : array();
        $v = function ($k) use ($d) {
            return isset($d[$k]) && is_scalar($d[$k]) ? trim((string) $d[$k]) : '';
        };
        if ($v('number') === '')
            return '';
        $sep = '<span class="sep" aria-hidden="true"></span>';
        $id = cdr_h(sprintf(cdr__('Ticket #%s'), $v('number')));
        $parts = array($v('href') !== ''
            ? '<a class="cdr-id" href="' . cdr_h($v('href')) . '"'
                . ($v('reload') !== '' ? ' title="' . cdr_h($v('reload')) . '"' : '') . '>' . $id . '</a>'
            : '<span class="cdr-id">' . $id . '</span>');
        if ($v('status') !== '') {
            static $icons = array('open' => 'inbox', 'closed' => 'checkc',
                'archived' => 'layers', 'deleted' => 'ban');
            $state = preg_replace('/[^a-z]/', '', strtolower($v('state')));
            $parts[] = '<span class="status' . ($state !== '' ? ' status-' . $state : '') . '">'
                . cdr_icon(isset($icons[$state]) ? $icons[$state] : 'flag', 14)
                . cdr_h($v('status')) . '</span>';
        }
        $plan = isset($d['plan']) && is_array($d['plan']) ? $d['plan'] : null;
        if ($plan && ($chip = cdr_plan_chip($plan)) !== '')
            $parts[] = $chip;
        if ($v('urgent') !== '')
            $parts[] = '<span class="urg" title="' . cdr_h(cdr__('Priority')) . '">'
                . cdr_icon('alert', 15) . cdr_h($v('urgent')) . '</span>';

        // Subtitle: two sentences, each from its own msgid (word order is
        // the translation's), the parts that are unknown are left out.
        $sub = array();
        if ($v('name') !== '')
            $sub[] = $v('org') !== ''
                ? sprintf(cdr__('%1$s, %2$s.'), $v('name'), $v('org'))
                : sprintf(cdr__('%s.'), $v('name'));
        if ($v('date') !== '') {
            $at = $v('time') !== '' ? sprintf(cdr__('%1$s at %2$s'), $v('date'), $v('time')) : $v('date');
            $sub[] = $v('source') !== ''
                ? sprintf(cdr__('Received on %1$s via %2$s.'), $at, $v('source'))
                : sprintf(cdr__('Received on %s.'), $at);
        }
        return '<section class="cdr-idline"><p class="idline">' . implode($sep, $parts) . '</p>'
            . ($sub ? '<p class="sub cdr-sub">' . cdr_h(implode(' ', $sub)) . '</p>' : '')
            . '</section>';
    }

    /**
     * The data of cdr_ticket_idline() for a staff ticket view: core's own
     * values (status name as the properties show it, the creation date in
     * the agent's date and time formats and time zone, core's source
     * label), the contract level of the plugin (coderise_contract_level(),
     * only when the plugin has a Priority organisation to decide from),
     * urgency from the priority's urgency number (CDR_URGENT_URGENCY).
     * Never throws: what cannot be read is left out.
     */
    function cdr_ticket_idline_data($t) {
        $d = array();
        if (!is_object($t))
            return $d;
        $read = function ($fn) {
            try {
                return $fn();
            }
            catch (Throwable $e) {
                return null;
            }
        };
        $d['number'] = (string) $read(function () use ($t) { return $t->getNumber(); });
        $id = (int) $read(function () use ($t) { return $t->getId(); });
        if ($id > 0)
            $d['href'] = ROOT_PATH . 'scp/tickets.php?id=' . $id;
        $d['reload'] = cdr__('Reload');
        $read(function () use ($t, &$d) {
            if ($s = $t->getStatus()) {
                $d['status'] = (string) $s->getName();
                $d['state'] = (string) $s->getState();
            }
        });
        $read(function () use ($t, &$d) {
            if (($p = $t->getPriority()) && is_object($p)
                    && ($u = (int) $p->getUrgency()) > 0 && $u <= CDR_URGENT_URGENCY)
                $d['urgent'] = (string) $p->getDesc();
        });
        $read(function () use ($t, &$d) {
            if (function_exists('coderise_contract_level')
                    && is_array($l = coderise_contract_level($t))
                    && isset($l['level'], $l['label'])
                    && ($l['source'] ?? '') !== 'default')
                $d['plan'] = array('level' => (string) $l['level'], 'label' => (string) $l['label']);
        });
        $read(function () use ($t, &$d) {
            $d['name'] = (string) $t->getName();
            if (($o = $t->getOwner()) && ($org = $o->getOrganization()))
                $d['org'] = (string) $org->getName();
        });
        $read(function () use ($t, &$d) {
            if (($c = $t->getCreateDate()) && class_exists('Format')) {
                $d['date'] = (string) Format::date($c);
                $d['time'] = (string) Format::time($c);
            }
        });
        $read(function () use ($t, &$d) {
            $d['source'] = (string) $t->getSource();
        });
        return $d;
    }

    /**
     * The contract chip of the mockup (.plan): "Priority" on nuit with a
     * bolt, "Standard" outlined. $plan = array(level, label), as
     * coderise_contract_level() returns it; any level other than
     * "priority" is Standard. '' without a label. Escaped here.
     */
    function cdr_plan_chip($plan) {
        if (!is_array($plan) || !isset($plan['label']) || !is_scalar($plan['label'])
                || trim((string) $plan['label']) === '')
            return '';
        $level = isset($plan['level']) && $plan['level'] === 'priority' ? 'priority' : 'standard';
        return '<span class="plan plan-' . $level . '" title="' . cdr_h(cdr__('Support contract')) . '">'
            . ($level === 'priority' ? cdr_icon('bolt', 12) : '')
            . cdr_h(trim((string) $plan['label'])) . '</span>';
    }

    /* ------------------------------------------------------------------
     * 6. Queue
     * ---------------------------------------------------------------- */

    /**
     * Layout of the queue table from the data paths of its configured
     * columns (QueueColumn::$primary, in display order). The columns are
     * found by what they show, never by their heading (translated, renamed
     * by the admin) or their id (a custom queue has its own columns):
     *   number  'number'                  ticket number (contract chip)
     *   subject 'cdata__subject'          subject (client line, gauge)
     *   user    'user__name'              core's "From" column
     * Each is the index of the first matching column, or null. 'gauge' is
     * the index of the column the first-reply cell follows: the subject
     * (mockup order: Ticket, Subject, First reply, ...), else the last
     * column; -1 when there is no column (the cell comes first).
     */
    function cdr_queue_layout($primaries) {
        $out = array('number' => null, 'subject' => null, 'user' => null, 'gauge' => -1);
        $roles = array('number' => 'number', 'cdata__subject' => 'subject', 'subject' => 'subject',
            'user__name' => 'user');
        $i = -1;
        foreach (array_values((array) $primaries) as $i => $p) {
            $p = is_scalar($p) ? strtolower(trim((string) $p)) : '';
            if (isset($roles[$p]) && $out[$roles[$p]] === null)
                $out[$roles[$p]] = $i;
        }
        $out['gauge'] = $out['subject'] !== null ? $out['subject'] : $i;
        return $out;
    }

    /**
     * The mockup's client line under the subject (.subj small): "Client,
     * organisation" (or the client alone), escaped. '' without a name.
     */
    function cdr_queue_client($name, $org=null) {
        $name = is_scalar($name) ? trim((string) $name) : '';
        $org = is_scalar($org) ? trim((string) $org) : '';
        if ($name === '')
            return '';
        return '<small class="cdr-client">' . cdr_h($org !== ''
            ? sprintf(cdr__('%1$s, %2$s'), $name, $org) : $name) . '</small>';
    }

    /**
     * The same client line from core's own rendering of the queue's "From"
     * column ($C->render($T): the name with the column's link filter and
     * annotations, e.g. the collaborator count), used as is: it is core's
     * output, exactly what core prints in that column. Only the
     * organisation is escaped here. '' when the cell has no text and no
     * markup (nothing to show).
     */
    function cdr_queue_client_html($cellHtml, $org=null) {
        $cellHtml = is_scalar($cellHtml) ? trim((string) $cellHtml) : '';
        $org = is_scalar($org) ? trim((string) $org) : '';
        if ($cellHtml === '' || preg_replace('/[\s\x{00A0}]+/u', '',
                html_entity_decode(strip_tags($cellHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '')
            return '';
        return '<small class="cdr-client">' . ($org !== ''
            ? sprintf(cdr__('%1$s, %2$s'), $cellHtml, cdr_h($org)) : $cellHtml) . '</small>';
    }

    /**
     * The status "Mark as resolved" closes to (ticket view), from the
     * closed statuses of the install, array(id => state): the stock
     * "Resolved" status (id 2) when it exists and is a closed status;
     * otherwise the only closed status when there is exactly one; else 0
     * (no button: the theme does not guess between several).
     */
    function cdr_resolve_status_id($statuses) {
        $closed = array();
        foreach ((array) $statuses as $id => $state)
            if ((int) $id > 0 && $state === 'closed')
                $closed[] = (int) $id;
        if (in_array(2, $closed, true))
            return 2;
        return count($closed) === 1 ? $closed[0] : 0;
    }

    /**
     * cdr_resolve_status_id() for a ticket of the ticket view: 0 unless the
     * ticket is open; the closed statuses come from core's status list
     * (TicketStatusList::getStatuses(), the list of core's status menu).
     * Never throws (0 then: no button).
     */
    function cdr_ticket_resolve_status($t) {
        try {
            if (!is_object($t) || !$t->isOpen() || !class_exists('TicketStatusList'))
                return 0;
            $statuses = array();
            foreach (TicketStatusList::getStatuses(array('states' => array('closed'))) as $s)
                $statuses[(int) $s->getId()] = (string) $s->getState();
            return cdr_resolve_status_id($statuses);
        }
        catch (Throwable $e) {
            return 0;
        }
    }
}
