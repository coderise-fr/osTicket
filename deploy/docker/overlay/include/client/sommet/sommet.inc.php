<?php
/*********************************************************************
    sommet.inc.php

    Helpers of the "Sommet" client portal theme (Coderise brand).
    Lives outside osTicket's core: it is added by the Docker overlay
    (deploy/themes/sommet) and only used by the theme's templates.

    - __s(): translation of the strings that only exist in this theme,
      through a dedicated gettext domain ("sommet") whose catalogues live
      in include/client/sommet/i18n/<lang>/LC_MESSAGES/sommet.mo.php.
      Strings that osTicket already has keep using __() so that the
      official language packs translate them.
    - sommet_icon(), sommet_peak(): inline SVG (line icons, the "Sommet"
      chevron drawn from the official Coderise symbol).
    - sommet_banner_*(): the dark title band ("bandeau"). The header
      prints an empty band, the page is buffered, and the footer moves
      the page title (first <h1>, or a block between
      <!--sommet:bandeau--> and <!--/sommet:bandeau-->) into the band.
      Without that step (unexpected output buffering), the title simply
      stays in the page.
    - sommet_priority_sla(): data of the "first reply" indicator of a
      ticket (Priority SLA plan), see view.inc.php.
    - sommet_contract(), sommet_contract_badge(): support contract level
      ("Priority" / "Standard") given by the OPTIONAL function
      coderise_contract_level() of the sla_first_response plugin
      (contrib/sla-first-response, README section 9). Without the plugin
      (absent or disabled), no level is ever shown.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');

if (!defined('SOMMET_THEME')) {
    define('SOMMET_THEME', 'sommet');
    // Bumped with every change of the stylesheet (cache busting).
    define('SOMMET_VERSION', '1.1.0');

    // Name of the SLA plan of the Priority support (first reply within
    // 4 hours). Can be defined beforehand, e.g. in ost-config.php. The
    // indicator is shown only while a ticket is on an ACTIVE plan of
    // this name; the sla_first_response plugin moves the ticket to a
    // disabled plan at the first reply, which hides it.
    if (!defined('SOMMET_PRIORITY_SLA'))
        define('SOMMET_PRIORITY_SLA', 'Priority 4h');
    // Show the indicator once the due time has passed (false: hidden).
    if (!defined('SOMMET_SLA_SHOW_OVERDUE'))
        define('SOMMET_SLA_SHOW_OVERDUE', false);

    TextDomain::lookup('sommet')->setPath(dirname(__FILE__) . '/i18n/');

    /**
     * Translate a string that belongs to the theme (gettext domain
     * "sommet"). Falls back to the English msgid, like __().
     */
    function __s($msgid) {
        return _dgettext('sommet', $msgid);
    }

    function sommet_asset($path) {
        return ROOT_PATH . 'assets/sommet/' . $path . '?v=' . SOMMET_VERSION;
    }

    /**
     * Line icon (stroke 1.75, 24 px grid), decorative unless $label is given.
     */
    function sommet_icon($name, $size=18, $label='') {
        static $paths = array(
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'phone' => '<path d="M5.5 4h3.5l1.8 4.5-2.3 1.5a11 11 0 0 0 5.5 5.5l1.5-2.3 4.5 1.8v3.5a1.8 1.8 0 0 1-2 1.8A16 16 0 0 1 3.7 6 1.8 1.8 0 0 1 5.5 4z"/>',
            'pin' => '<path d="M12 21s7-6 7-11.5a7 7 0 0 0-14 0C5 15 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'linkedin' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10.5V16M8 7.8h.01M12 16v-5.5M12 13a2.5 2.5 0 0 1 5 0v3"/>',
            'github' => '<path d="M9 19c-4 1.2-4-2-6-2.5M15 21v-3.2a3 3 0 0 0-.9-2.4c3-.3 6-1.5 6-6.6a5 5 0 0 0-1.3-3.4 4.6 4.6 0 0 0-.1-3.4s-1.1-.3-3.6 1.4a12.3 12.3 0 0 0-6.2 0C6.4 3.2 5.3 3.5 5.3 3.5a4.6 4.6 0 0 0-.1 3.4A5 5 0 0 0 3.9 10.3c0 5.1 3 6.3 6 6.6a3 3 0 0 0-.9 2.4V21"/>',
            'globe' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c3 3 3 14 0 17M12 3.5c-3 3-3 14 0 17"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
            'inbox' => '<path d="m4 13 2-8h12l2 8v6H4z"/><path d="M4 13h5l1 2h4l1-2h5"/>',
            'lock' => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'user' => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
            'logout' => '<path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15M10 16l-4-4 4-4M6 12h10"/>',
            'print' => '<path d="M7 9V4h10v5M7 17H5a1.5 1.5 0 0 1-1.5-1.5v-5A1.5 1.5 0 0 1 5 9h14a1.5 1.5 0 0 1 1.5 1.5v5A1.5 1.5 0 0 1 19 17h-2"/><path d="M7 14h10v6H7z"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
            'refresh' => '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 4.5v4h-4"/>',
            'clip' => '<path d="M20 11.5 12 19.5a5 5 0 0 1-7-7l8-8a3.4 3.4 0 0 1 4.8 4.8l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/>',
            'alert' => '<path d="M12 4 3 19.5h18z"/><path d="M12 10v4.5M12 17.2h.01"/>',
            'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
            'book' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H11v16H5.5A1.5 1.5 0 0 1 4 18.5zM20 5.5A1.5 1.5 0 0 0 18.5 4H13v16h5.5a1.5 1.5 0 0 0 1.5-1.5z"/>',
            'folder' => '<path d="M3.5 6.5A1.5 1.5 0 0 1 5 5h4l2 2.5h8A1.5 1.5 0 0 1 20.5 9v8.5A1.5 1.5 0 0 1 19 19H5a1.5 1.5 0 0 1-1.5-1.5z"/>',
            'file' => '<path d="M6 3.5h8l4 4v13H6z"/><path d="M14 3.5v4h4M9 12h6M9 15.5h6"/>',
            'group' => '<circle cx="9" cy="9" r="3.2"/><path d="M3 19a6 6 0 0 1 12 0M16 6.2a3.2 3.2 0 0 1 0 6.1M18 19a6 6 0 0 0-2.5-4.9"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'bolt' => '<path d="m13 3-8 11h6l-1 7 8-11h-6z"/>',
        );
        if (!isset($paths[$name]))
            return '';
        $aria = $label
            ? 'role="img" aria-label="' . Format::htmlchars($label) . '"'
            : 'aria-hidden="true"';
        return sprintf('<svg class="ic ic-%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false" %s>%s</svg>',
            $name, $size, $size, $aria, $paths[$name]);
    }

    /**
     * The "Sommet" pattern: the chevron of the official Coderise symbol
     * (deploy/themes/_shared/coderise/symbole-*.svg), same path, same stroke,
     * same miter, its feet cut flat by the view box exactly where the
     * original logo cuts them. Never stretched (the ratio comes from the
     * view box). Colour = currentColor.
     */
    function sommet_peak($class='') {
        return '<svg class="sommet-peak ' . $class . '" viewBox="38.05 15.11 34.41 28.67" '
            . 'preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false">'
            . '<path d="m 40.255924,44.480112 15,-25 15,25" fill="none" stroke="currentColor" '
            . 'stroke-width="4.5" stroke-linecap="round" stroke-linejoin="miter"/></svg>';
    }

    /**
     * Start of the band: starts buffering the page, then prints the band
     * (breadcrumbs and an empty slot for the title). The footer calls
     * sommet_banner_close() to fill the slot.
     */
    function sommet_banner_open($crumbs, $fallback='', $extra='') {
        global $sommet_banner_level, $sommet_banner_fallback;
        ob_start();
        $sommet_banner_level = ob_get_level();
        $sommet_banner_fallback = $fallback;
        echo '<section class="bandeau"><div class="wrap bandeau-in">';
        if (count($crumbs) > 1) {
            echo '<nav class="crumbs" aria-label="' . Format::htmlchars(__s('Breadcrumb')) . '"><ol>';
            $last = count($crumbs) - 1;
            foreach ($crumbs as $i => $c) {
                list($label, $href) = $c;
                if ($href && $i < $last)
                    echo '<li><a href="' . $href . '">' . $label . '</a></li>';
                else
                    echo '<li><span' . ($i == $last ? ' aria-current="page"' : '') . '>' . $label . '</span></li>';
            }
            echo '</ol></nav>';
        }
        echo '<!--sommet:slot-->' . $extra . '</div>' . sommet_peak('b-sommet') . "</section>\n";
    }

    /**
     * Called by the footer: moves the page title (an explicit block of the
     * theme's templates, or else the first <h1> of the page) into the band.
     * Does nothing if the buffer opened by sommet_banner_open() is not the
     * current one (the page is then printed as is).
     */
    function sommet_banner_close() {
        global $sommet_banner_level, $sommet_banner_fallback;
        if (!$sommet_banner_level || ob_get_level() != $sommet_banner_level)
            return;
        $sommet_banner_level = null;
        $html = ob_get_clean();
        $slot = '<!--sommet:slot-->';
        $at = strpos($html, $slot);
        if ($at === false) {
            echo $html;
            return;
        }
        $head = substr($html, 0, $at);
        $page = substr($html, $at + strlen($slot));
        $title = '';
        $m = array();
        if (preg_match('#<!--sommet:bandeau-->(.*?)<!--/sommet:bandeau-->#s', $page, $m, PREG_OFFSET_CAPTURE)) {
            $title = $m[1][0];
            $page = substr_replace($page, '', $m[0][1], strlen($m[0][0]));
        }
        elseif (preg_match('#<h1\b([^>]*)>(.*?)</h1>#is', $page, $m, PREG_OFFSET_CAPTURE)) {
            // Inline styles of the original templates (margins) do not
            // belong in the band.
            $attrs = preg_replace('/\sstyle\s*=\s*"[^"]*"/i', '', $m[1][0]);
            $title = '<h1' . $attrs . '>' . $m[2][0] . '</h1>';
            $page = substr_replace($page, '', $m[0][1], strlen($m[0][0]));
        }
        elseif ($sommet_banner_fallback) {
            // Page without a title of its own (knowledge base home, thank-you
            // page without <h1>): already escaped by the caller.
            $title = '<h1>' . $sommet_banner_fallback . '</h1>';
        }
        echo $head, $title, $page;
    }

    /**
     * "First reply" indicator of a ticket on the Priority SLA plan.
     * Returns null (nothing to show) unless the ticket is open, its plan is
     * ACTIVE and named SOMMET_PRIORITY_SLA, and it has an SLA due date.
     * Only data osTicket stores: plan, creation (or reopening) date and the
     * SLA due date computed by osTicket (est_duedate, which follows the
     * plan's schedule). The position is computed when the page is loaded.
     */
    function sommet_priority_sla($ticket) {
        if (!$ticket || !$ticket->isOpen())
            return null;
        $sla = $ticket->getSLA();
        if (!$sla || !$sla->isActive())
            return null;
        if (strcasecmp(trim((string) $sla->name), SOMMET_PRIORITY_SLA) !== 0
                && strcasecmp(trim((string) $sla->getName()), SOMMET_PRIORITY_SLA) !== 0)
            return null;
        $due = $ticket->est_duedate;
        $start = $ticket->getReopenDate() ?: $ticket->getCreateDate();
        if (!$due || !$start)
            return null;
        $t0 = Misc::db2gmtime($start);
        $t1 = Misc::db2gmtime($due);
        $now = time();
        if ($t1 <= $t0)
            return null;
        $overdue = $now > $t1;
        if ($overdue && !SOMMET_SLA_SHOW_OVERDUE)
            return null;
        $pct = max(0, min(100, ($now - $t0) * 100 / ($t1 - $t0)));
        return array(
            'start' => $start,
            'due' => $due,
            'pct' => round($pct, 1),
            'overdue' => $overdue,
            'left' => max(0, $t1 - $now),
            'hours' => (int) $sla->getGracePeriod(),
        );
    }

    /**
     * Support contract level of a client, or null when nothing reliable can
     * be shown. $subject: null (signed-in client), User or Ticket (its
     * OWNER, never a collaborator), as coderise_contract_level().
     *
     * The function belongs to the sla_first_response plugin and only exists
     * while the plugin and its instance are enabled: without it, null (the
     * theme then shows no level and no Priority commitment). A level that is
     * only the plugin's default (no signed-in client, guest, Priority
     * organisation not configured or deleted, error) is not shown either:
     * only source "organisation" is. It is the CURRENT level of the client,
     * not the one at the time the ticket was opened. The plugin caches the
     * result for the request; callers still compute it once per page.
     */
    function sommet_contract($subject=null) {
        if (!function_exists('coderise_contract_level'))
            return null;
        try {
            $level = coderise_contract_level($subject);
        }
        catch (Throwable $t) {
            return null;
        }
        if (!is_array($level) || !isset($level['level'], $level['source'])
                || $level['source'] !== 'organisation'
                || !in_array($level['level'], array('priority', 'standard'), true))
            return null;
        $label = isset($level['label']) ? trim((string) $level['label']) : '';
        if ($label === '')
            $label = $level['level'] == 'priority' ? 'Priority' : 'Standard';
        return array('level' => $level['level'], 'label' => $label);
    }

    /**
     * Badge of a contract level (result of sommet_contract()). The label is
     * typed by an administrator in the plugin: always escaped. $prefix adds
     * "Support contract:" for screen readers (visually hidden) when the
     * badge has no visible caption.
     */
    function sommet_contract_badge($contract, $prefix=true) {
        if (!$contract)
            return '';
        $priority = ($contract['level'] == 'priority');
        return sprintf('<span class="plan plan-%s">%s%s%s</span>',
            $priority ? 'priority' : 'standard',
            $priority ? sommet_icon('bolt', 13) : '',
            $prefix ? '<span class="visually-hidden">'
                . Format::htmlchars(__s('Support contract:')) . ' </span>' : '',
            Format::htmlchars($contract['label']));
    }
}
