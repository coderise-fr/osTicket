<?php
/*********************************************************************
    include/client/parcours/parcours.inc.php

    Helpers of the "Parcours" client portal theme (Coderise).
    Not part of osTicket core: shipped by deploy/themes/parcours.

    - pcrs__()            translation: osTicket's __() first, then the
                          theme's own catalogue (i18n/<lang>.php)
    - pcrs_icon()         inline line icons (no icon font, no request)
    - pcrs_sommet()       the "Sommet" motif: chevron path of the official
                          Coderise symbol (deploy/themes/_shared/coderise)
    - pcrs_initials()     initials of a name, instead of remote avatars
    - pcrs_asset()        URL of a theme asset, with a cache-busting version
    - pcrs_next_step()    "your next step", derived from the ticket state
    - pcrs_first_response() date of the first staff reply of a ticket
    - pcrs_sla_gauge()    first-response gauge of the Priority SLA plan
    - pcrs_contract()     support contract level (Priority / Standard) of
                          a client, from the optional sla_first_response
                          plugin; pcrs_contract_pill(), _promise()
    - pcrs_reading_time() minutes of reading of an article

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('PCRS_VERSION')) {
    define('PCRS_VERSION', '1.0.0');

    /*
     * Name of the SLA plan whose deadline is the "first reply" promise of
     * the Priority support (see contrib/sla-first-response: "Priority 4h").
     * The gauge is shown only for an open ticket on this plan, while the
     * plan is active. After the first reply the sla_first_response plugin
     * moves the ticket to a disabled plan: the gauge disappears by itself.
     * Override it in ost-config.php: define('PARCOURS_SLA_PRIORITY', '...');
     * An empty string disables the gauge.
     */
    if (!defined('PARCOURS_SLA_PRIORITY'))
        define('PARCOURS_SLA_PRIORITY', 'Priority 4h');

    /**
     * Translate a theme string. osTicket's language pack wins when it has
     * the msgid; otherwise the theme catalogue of the current language;
     * otherwise the English msgid.
     */
    function pcrs__($msgid) {
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

    /** Line icon (24 grid, constant 1.75 stroke, rounded ends), decorative. */
    function pcrs_icon($name, $size=18) {
        static $paths = array(
            'plus'    => '<path d="M12 5v14M5 12h14"/>',
            'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'check'   => '<path d="m5 12.5 4.5 4.5L19 7"/>',
            'alert'   => '<path d="M12 4 3 19.5h18z"/><path d="M12 10v4.5M12 17.2h.01"/>',
            'arrow'   => '<path d="M4 12h15m-6-6 6 6-6 6"/>',
            'back'    => '<path d="M20 12H5m6-6-6 6 6 6"/>',
            'doc'     => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 12h5M10 16h5"/>',
            'folder'  => '<path d="M3 6.5h6.5l2 2H21V19H3z"/>',
            'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
            'mail'    => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'phone'   => '<path d="M5.5 4h3.5l1.8 4.5-2.3 1.5a11 11 0 0 0 5.5 5.5l1.5-2.3 4.5 1.8v3.5a1.8 1.8 0 0 1-2 1.8A16 16 0 0 1 3.7 6 1.8 1.8 0 0 1 5.5 4z"/>',
            'pin'     => '<path d="M12 21s7-6 7-11.5a7 7 0 0 0-14 0C5 15 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'clock'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'globe'   => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c3 3 3 14 0 17M12 3.5c-3 3-3 14 0 17"/>',
            'flag'    => '<path d="M6 21V4M6 4h11l-2 4 2 4H6"/>',
            'clip'    => '<path d="M20 11.5 12 19.5a5 5 0 0 1-7-7l8-8a3.4 3.4 0 0 1 4.8 4.8l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/>',
            'print'   => '<path d="M7 9V3h10v6M7 17H4v-8h16v8h-3"/><path d="M7 14h10v7H7z"/>',
            'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.9M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9M20 20v-4h-4"/>',
            'lock'    => '<rect x="5" y="10.5" width="14" height="10" rx="1.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
            'inbox'   => '<path d="M4 13 6.5 5h11L20 13v6H4z"/><path d="M4 13h4.5l1.5 2.5h4l1.5-2.5H20"/>',
            'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'group'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
            'reply'   => '<path d="M9 7 4 12l5 5"/><path d="M4 12h10a6 6 0 0 1 6 6v1"/>',
            'reopen'  => '<path d="M4 12a8 8 0 1 0 2.4-5.7L4 8.6"/><path d="M4 4v4.6h4.6"/>',
            'next'    => '<path d="m9 6 6 6-6 6"/>',
            'prev'    => '<path d="m15 6-6 6 6 6"/>',
            'sort'    => '<path d="m8 9 4-4 4 4M8 15l4 4 4-4"/>',
            'x'       => '<path d="m6 6 12 12M18 6 6 18"/>',
            'bolt'    => '<path d="m13 3-8 11h6l-1 7 8-11h-6z"/>',
            'shield'  => '<path d="M12 3 5 6v5.5c0 4.4 3 8.2 7 9.5 4-1.3 7-5.1 7-9.5V6z"/>',
            'logout'  => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
            'question'=> '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/>',
        );
        if (!isset($paths[$name]))
            return '';
        $s = (int) $size;
        return sprintf('<svg class="ic ic-%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            $name, $s, $s, $paths[$name]);
    }

    /**
     * The "Sommet" motif: the chevron of the official symbol
     * (symbole-sur-fond-clair.svg: same path, same 4.5 stroke, feet cut
     * flat at the line of the original mask). Never stretched: the viewBox
     * keeps its ratio. Colour = currentColor (cyan by default in the CSS).
     */
    function pcrs_sommet($class='sommet') {
        return '<svg class="' . Format::htmlchars($class) . '" viewBox="37.4 14.95 35.75 28.85" aria-hidden="true" focusable="false"><path d="m 40.255924,44.480112 15,-25 15,25" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="miter"/></svg>';
    }

    /** One or two initials of a person's name, HTML-escaped. */
    function pcrs_initials($name) {
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

    /** First name for the header chip (first word of the name). */
    function pcrs_first_name($name) {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        return Format::htmlchars($parts ? $parts[0] : '');
    }

    /** URL of a file in assets/parcours/. */
    function pcrs_asset($path) {
        return ROOT_PATH . 'assets/parcours/' . $path . '?v=' . PCRS_VERSION;
    }

    /**
     * "Your next step", derived only from real ticket data:
     * closed -> reopen if needed (when the ticket can be reopened);
     * open + isanswered (the last action is a staff reply) -> your turn;
     * open + not answered -> we are on it.
     * Returns array(key, title, text).
     */
    function pcrs_next_step($state, $answered, $reopenable=true) {
        if (strcasecmp((string) $state, 'closed') === 0)
            return array('closed', pcrs__('Closed'),
                $reopenable
                    ? pcrs__('Still an issue? Reply below: the ticket opens again.')
                    : pcrs__('This ticket is closed. Open a new ticket if you need us again.'));
        if ($answered)
            return array('you', pcrs__('Your turn'),
                pcrs__('The team has answered. Read the reply and tell us if it works for you.'));
        return array('us', pcrs__('We are on it'),
            pcrs__('The team has your latest message and will get back to you.'));
    }

    /** Short label of the next step, for lists. */
    function pcrs_next_label($state, $answered) {
        if (strcasecmp((string) $state, 'closed') === 0)
            return pcrs__('Closed');
        return $answered ? pcrs__('Your turn') : pcrs__('With the team');
    }

    /**
     * Date (database time) of the first reply of an agent in the ticket
     * thread, or null. Visible entries only, system notes excluded. A
     * separate query: Thread::getEntries() caches its queryset and must
     * not be filtered here.
     */
    function pcrs_first_response($ticket) {
        if (!$ticket || !($tid = $ticket->getThreadId()))
            return null;
        $row = ThreadEntry::objects()
            ->filter(array('thread_id' => $tid, 'type' => 'R', 'staff_id__gt' => 0))
            ->exclude(array('flags__hasbit' => ThreadEntry::FLAG_HIDDEN))
            ->exclude(array('flags__hasbit' => ThreadEntry::FLAG_SYSTEM))
            ->order_by('created')
            ->values_flat('created')
            ->first();
        return $row ? $row[0] : null;
    }

    /**
     * Gauge of the Priority first-reply deadline, or null when it does not
     * apply. Shown only when ALL of these hold (data the portal can read):
     * the ticket is open; its SLA plan is named PARCOURS_SLA_PRIORITY and
     * is active; it has an estimated due date. Values computed at page load.
     */
    function pcrs_sla_gauge($ticket) {
        if (!PARCOURS_SLA_PRIORITY || !$ticket || !$ticket->isOpen())
            return null;
        if (!($sla = $ticket->getSLA()) || !$sla->isActive())
            return null;
        if (strcasecmp(trim((string) $sla->getName()), PARCOURS_SLA_PRIORITY) !== 0)
            return null;
        $hours = (float) $sla->getGracePeriod();
        $due = $ticket->getSLADueDate();
        if ($hours <= 0 || !$due)
            return null;
        $start = Misc::db2gmtime($ticket->getReopenDate() ?: $ticket->getCreateDate());
        $end = Misc::db2gmtime($due);
        if (!$start || !$end || $end <= $start)
            return null;
        $now = time();
        $elapsed = max(0, $now - $start);
        return array(
            'hours' => $hours,
            'elapsed' => $elapsed,
            'ratio' => min(1, $elapsed / ($end - $start)),
            'due' => $due,
            'late' => $now > $end,
        );
    }

    /**
     * Support contract level ("priority" / "standard") of a client, from
     * the sla_first_response plugin (contrib/sla-first-response, README
     * section 9: coderise_contract_level()). $subject: null (the signed-in
     * client), a User or a Ticket (its OWNER, never a collaborator).
     *
     * Returns array('level' => 'priority'|'standard', 'label' => raw text)
     * or null when nothing may be shown: plugin absent or disabled (the
     * function does not exist), or level unknown (source "default":
     * visitor, guest, Priority organisation not configured...). No level,
     * no promise. This is the CURRENT level of the owner, not the level
     * at the creation of the ticket. Memoised per owner for the request.
     */
    function pcrs_contract($subject=null) {
        static $memo = array();
        if (!function_exists('coderise_contract_level'))
            return null;
        if ($subject instanceof Ticket)
            $key = 'u' . (int) $subject->getOwnerId();
        elseif ($subject instanceof User)
            $key = 'u' . (int) $subject->getId();
        elseif ($subject === null)
            $key = 'me';
        else
            return null;
        if (!array_key_exists($key, $memo)) {
            $memo[$key] = null;
            try {
                $l = coderise_contract_level($subject);
                if (is_array($l) && isset($l['level'], $l['source'])
                        && $l['source'] != 'default'
                        && in_array($l['level'], array('priority', 'standard'), true))
                    $memo[$key] = array(
                        'level' => $l['level'],
                        'label' => trim((string) $l['label']) !== ''
                            ? (string) $l['label']
                            : ($l['level'] == 'priority' ? 'Priority' : 'Standard'),
                    );
            }
            catch (Throwable $t) {
                $memo[$key] = null;
            }
        }
        return $memo[$key];
    }

    /** Pill of a contract level (pcrs_contract() result), HTML; '' if none. */
    function pcrs_contract_pill($c) {
        if (!$c)
            return '';
        $p = ($c['level'] == 'priority');
        return sprintf('<span class="plan %s">%s<span class="sr-only">%s</span>%s</span>',
            $p ? 'plan-priority' : 'plan-standard',
            $p ? pcrs_icon('bolt', 13) : '',
            Format::htmlchars(pcrs__('Support contract') . pcrs__(': ')),
            Format::htmlchars($c['label']));
    }

    /**
     * Commitment of a contract level for the first reply (plain text), or
     * '' when the level is unknown. Priority: first reply within 4 h, 24/7;
     * standard: business days. Never a Priority promise without a level.
     */
    function pcrs_contract_promise($c) {
        if (!$c)
            return '';
        return $c['level'] == 'priority'
            ? pcrs__('First reply within 4 h, 24/7.')
            : pcrs__('We reply on business days.');
    }

    /** "44 min", "2 h 05", in the language-neutral short form. */
    function pcrs_duration($seconds) {
        $m = (int) floor($seconds / 60);
        if ($m < 60)
            return sprintf('%d min', $m);
        return sprintf('%d h %02d', floor($m / 60), $m % 60);
    }

    /** Minutes of reading of an HTML text (200 words a minute, at least 1). */
    function pcrs_reading_time($html) {
        $words = str_word_count(strip_tags((string) $html), 0,
            'àâäçéèêëîïôöùûüÿœæÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸŒÆ0123456789');
        return max(1, (int) round($words / 200));
    }

    /** "1 article" / "N articles". */
    function pcrs_articles($n) {
        return ((int) $n == 1) ? pcrs__('1 article') : sprintf(pcrs__('%d articles'), (int) $n);
    }

    /** Breadcrumb trail: array of array(label, href|null). Labels are HTML-safe. */
    function pcrs_crumbs($items) {
        $out = '<nav class="crumbs" aria-label="' . Format::htmlchars(pcrs__('Breadcrumb')) . '"><ol>';
        $last = count($items) - 1;
        foreach ($items as $i => $it) {
            if ($i == $last || !$it[1])
                $out .= '<li><span' . ($i == $last ? ' aria-current="page"' : '') . '>' . $it[0] . '</span></li>';
            else
                $out .= '<li><a href="' . $it[1] . '">' . $it[0] . '</a></li>';
        }
        return $out . '</ol></nav>';
    }
}
