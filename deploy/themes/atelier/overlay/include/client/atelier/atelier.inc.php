<?php
/*********************************************************************
    atelier.inc.php

    Helpers of the "Atelier" client portal theme (Coderise brand, charte
    graphique v1.0). Lives outside osTicket's core: it is added by the
    Docker overlay (deploy/themes/atelier) and only used by the theme's
    templates.

    - __a(): translation of the strings that only exist in this theme,
      through a dedicated gettext domain ("atelier") whose catalogues live
      in include/client/atelier/i18n/<lang>/LC_MESSAGES/atelier.mo.php
      (same compiled format as osTicket's language packs). Strings that
      osTicket already has keep using __() so that the official language
      packs translate them.
    - atelier_initials(): local avatars made of initials (no Gravatar, no
      external request).
    - atelier_icon(): line icons (inline SVG, constant stroke), the set of
      the Coderise mock-ups.
    - atelier_progress(), atelier_priority(): ticket progress and the
      optional "Priority" first-response indicator, computed only from data
      the ticket really has (dates, isanswered, state, SLA plan).
    - atelier_contract(), atelier_contract_badge(): contract level badge
      (Priority / Standard) from the optional sla-first-response plugin
      (coderise_contract_level()); nothing at all without the plugin.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('ATELIER_THEME')) {
    define('ATELIER_THEME', 'atelier');
    // Bumped with every change of the stylesheet (cache busting).
    define('ATELIER_VERSION', '2.1.0');
    // Brand (proper noun: not translated).
    define('ATELIER_BRAND', 'Coderise');

    // Contact details shown in the footer (Coderise, charte v1.0). The
    // office hours are the company's, not those of the Priority support.
    define('ATELIER_EMAIL', 'contact@coderise.fr');
    define('ATELIER_PHONE', '+33 (0)6 69 93 92 86');
    define('ATELIER_PHONE_URI', '+33669939286');
    define('ATELIER_CITY', 'Aix-en-Provence');
    define('ATELIER_WEBSITE', 'https://www.coderise.fr');

    // Support Priority (to be validated, see README.md):
    // - the contract level badge (Priority / Standard) comes from
    //   coderise_contract_level(), provided by the sla-first-response
    //   plugin (contrib/sla-first-response, version 0.3+): current level of
    //   the ticket's owner (membership of the "Priority" organisation). No
    //   plugin, no badge (see atelier_contract());
    // - a ticket whose ACTIVE SLA plan is exactly ATELIER_PRIORITY_SLA, not
    //   answered yet, open and with a due date in the future shows the
    //   "first reply within 4 h" indicator. The sla-first-response plugin
    //   moves the ticket to a disabled plan on the first reply, so the
    //   indicator disappears by itself.
    // Set ATELIER_PRIORITY_SLA to '' to turn the indicator off.
    define('ATELIER_PRIORITY_SLA', 'Priority 4h');

    TextDomain::lookup('atelier')->setPath(dirname(__FILE__) . '/i18n/');

    /**
     * Translate a string that belongs to the theme (gettext domain
     * "atelier"). Falls back to the English msgid, like __().
     */
    function __a($msgid) {
        return _dgettext('atelier', $msgid);
    }

    /**
     * One or two capital letters from a person's name, for an avatar.
     */
    function atelier_initials($name) {
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
     * Line icon (24 px grid, stroke 1.75, rounded caps), decorative.
     */
    function atelier_icon($name, $size=18) {
        static $paths = array(
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
            'clip' => '<path d="M20 11.5 12 19.5a5 5 0 0 1-7-7l8-8a3.4 3.4 0 0 1 4.8 4.8l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/>',
            'lock' => '<rect x="5" y="11" width="14" height="9.5" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
            'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
            'alert' => '<path d="M12 4 3 19.5h18z"/><path d="M12 10v4.5M12 17.2h.01"/>',
            'chevr' => '<path d="m9 6 6 6-6 6"/>',
            'arrowl' => '<path d="M20 12H5m6-6-6 6 6 6"/>',
            'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
            'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'bolt' => '<path d="m13 3-8 11h6l-1 7 8-11h-6z"/>',
            'phone' => '<path d="M5.5 4h3.5l1.8 4.5-2.3 1.5a11 11 0 0 0 5.5 5.5l1.5-2.3 4.5 1.8v3.5a1.8 1.8 0 0 1-2 1.8A16 16 0 0 1 3.7 6 1.8 1.8 0 0 1 5.5 4z"/>',
            'pin' => '<path d="M12 21s7-6 7-11.5a7 7 0 0 0-14 0C5 15 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'print' => '<path d="M7 9V3.5h10V9M7 17H4v-7h16v7h-3M7 14h10v6.5H7z"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4M4 4v4h4M4 13a8 8 0 0 0 14.5 4M20 20v-4h-4"/>',
            'calendar' => '<rect x="4" y="5" width="16" height="16" rx="1.5"/><path d="M4 10h16M8 3v4M16 3v4"/>',
            'message' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H9l-5 4z"/>',
            'inbox' => '<path d="m4 13 2-8h12l2 8v6H4z"/><path d="M4 13h5l1 2h4l1-2h5"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'login' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4M10 8l4 4-4 4M14 12H4"/>',
            'logout' => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M15 8l4 4-4 4M19 12H9"/>',
            'globe' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c3 3 3 14 0 17M12 3.5c-3 3-3 14 0 17"/>',
            'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 7l3 3"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-6.5 8-6.5s8 2.3 8 6.5"/>',
            'help' => '<circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>',
        );
        if (!isset($paths[$name]))
            return '';
        return sprintf('<svg class="ic" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
            (int) $size, $paths[$name]);
    }

    /**
     * The "Sommet" motif: the chevron of the Coderise symbol (CSS shape,
     * ratio 409:342 kept, never stretched nor tilted).
     */
    function atelier_sommet($class='') {
        return '<span class="sommet ' . $class . '" aria-hidden="true"></span>';
    }

    /**
     * Contract level (Priority / Standard) of a client, from the
     * sla-first-response plugin: array('level' => 'priority'|'standard',
     * 'label' => ...), or null when no level must be shown:
     * - plugin (or its instance) disabled: coderise_contract_level() does
     *   not exist, the theme shows no level at all (never "Standard" by
     *   default);
     * - source "default": the plugin could not apply its rule (guest, no
     *   Priority organisation configured, unknown user), so the level is
     *   not known either.
     * $subject: null (the signed-in client), a User, a Ticket (its OWNER,
     * never a collaborator), or false (unknown user: nothing). It is the
     * CURRENT level of that person, not the one at ticket creation.
     * Computed once per person and per page (the plugin caches too).
     */
    function atelier_contract($subject=null) {
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
                    && $l['source'] != 'default' && trim($l['label']) !== '')
                $memo[$key] = array(
                    'level' => $l['level'] == 'priority' ? 'priority' : 'standard',
                    'label' => (string) $l['label'],
                );
        }
        return $memo[$key];
    }

    /**
     * Badge of a contract level (see atelier_contract()), or '' if none.
     * Priority: accented (bleu nuit, cyan bolt); Standard: discreet (gris
     * clair, ardoise text). The label is set by an administrator: escaped.
     */
    function atelier_contract_badge($contract) {
        if (!$contract)
            return '';
        $priority = ($contract['level'] == 'priority');
        return sprintf('<span class="plan plan-%s"><span class="visually-hidden">%s </span>%s%s</span>',
            $priority ? 'priority' : 'standard',
            Format::htmlchars(__a('Contract:')),
            $priority ? atelier_icon('bolt', 13) : '',
            Format::htmlchars($contract['label']));
    }

    /**
     * Data of the "first reply within 4 h" indicator, or null when it must
     * not be shown: wrong or inactive plan, ticket answered or closed, no
     * due date, or due date already passed (showing a missed target to the
     * client is a decision left open, see README.md).
     */
    function atelier_priority($ticket) {
        if (!$ticket || ATELIER_PRIORITY_SLA === '')
            return null;
        $sla = $ticket->getSLA();
        if (!$sla || !is_object($sla) || !$sla->isActive()
                || strcasecmp((string) $sla->getName(), ATELIER_PRIORITY_SLA) !== 0)
            return null;
        if ($ticket->isAnswered() || $ticket->isClosed())
            return null;
        $due = $ticket->getSLADueDate();
        $start = $ticket->getReopenDate() ?: $ticket->getCreateDate();
        if (!$due || !$start)
            return null;
        $t0 = Misc::db2gmtime($start);
        $t1 = Misc::db2gmtime($due);
        $now = time();
        if ($t1 <= $t0 || $now >= $t1)
            return null;
        $pct = max(0, min(100, round(100 * ($now - $t0) / ($t1 - $t0), 1)));
        return array('start' => $start, 'due' => $due, 'pct' => $pct,
            'left' => (int) ceil(($t1 - $now) / 60));
    }

    /**
     * Progress of a ticket, from real data only: received (creation date),
     * first reply of the team (date of the first response in the thread),
     * closed or resolved (state of the status and close date).
     */
    function atelier_progress($ticket) {
        $closed = $ticket->isClosed();
        $first = null;
        if (($thread = $ticket->getThread()) && $thread->entries) {
            $first = $thread->entries
                ->filter(array('type' => 'R'))
                ->order_by('created')
                ->values_flat('created')
                ->first();
            $first = $first ? $first[0] : null;
        }
        $answered = (bool) ($first || $ticket->isAnswered());
        $S = $ticket->getStatus();
        $steps = array(
            array('label' => __a('Received'),
                'date' => $ticket->getCreateDate(), 'done' => true),
            array('label' => __a('First reply'),
                'date' => $first, 'done' => $answered),
            array('label' => $closed && $S ? $S->getLocalName() : __a('Resolved'),
                'date' => $closed ? $ticket->getCloseDate() : null,
                'done' => $closed),
        );
        // The current step is the first one that is not done.
        $now = -1;
        foreach ($steps as $i => $s) {
            if (!$s['done']) { $now = $i; break; }
        }
        if ($now > 0 && !$closed && $answered)
            $steps[$now]['wait'] = true;
        return array($steps, $now);
    }
}
