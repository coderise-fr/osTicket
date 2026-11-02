<?php
/*********************************************************************
    include/staff/coderise/dashboard.inc.php

    Theme-made panels of the dashboard (scp/dashboard.php) of the
    "coderise-agent" staff panel theme (Coderise). Not part of osTicket
    core: shipped by deploy/themes-staff/coderise-agent. Required by
    header.inc.php on dashboard.php only.

    The core page renders the period form, the activity chart (Raphael)
    and the statistics tables. The mockup (docs/theme-mockups-admin/
    tableau-de-bord.html) also has a title, an alert strip, four
    indicators, and three panels: the first replies at risk, the load
    per agent and the tickets per help topic. Core has no such data, so
    this file reads it with a few aggregate queries, once per request,
    only on the dashboard. Its own queries (5):

      cdr_dash_counts()          1 query  : tickets received / open / closed / overdue
      cdr_dash_priority_rows()   1 query  : Priority tickets of the window and of the
                                            open ones, with their first client reply
                                            (correlated sub-selects on the indexed
                                            thread_id; no query per ticket), at most
                                            CDR_DASH_PRIORITY_LIMIT rows, open ones first
      cdr_dash_agents()          1 query  : open tickets per agent
      cdr_dash_topics()          1 query  : tickets per help topic
      cdr_dash_subjects()        1 query  : subjects of the (at most 5) listed tickets

    plus those of the core helpers it calls, each once per request at most:
    $staff->getManagedDepartments(), Topic::getHelpTopics() (names in the
    panel language), the Priority organisation (SlaContractLevel::
    priorityOrg(), plugin only) and $staff->getDepts() when core has not
    loaded it yet: 8 to 9 queries for a dashboard page. A failed query is
    not reported through core's db_query() logging (which may mail the
    administrator on every SQL error): the panel shows no data and one line
    goes to the PHP error log (cdr_dash_log_once(), once per session).
    The Priority rows are capped (CDR_DASH_PRIORITY_LIMIT, 3000): when the
    cap is reached the open tickets are still all read (they come first),
    the average and rate cover the most recent closed ones only, a note
    says so under the indicators and one line is logged.

    Everything about the Priority contract (first reply, alert strip,
    risk panel, average and rate) needs the SLA plugin's
    coderise_contract_level() and SlaContractLevel; without it those
    panels are not drawn and the indicators fall back to closed and
    overdue tickets. The scope is the agent's: the departments they may
    access (or, when their access is limited, their own and their teams'
    tickets); the per-agent panel lists other agents only with the
    "stats.agents" permission or for the departments the viewer manages,
    as core's agent statistics. Times: the report's getDateRange()
    gives database times, kept as they are in the SQL (as core's own
    statistics); every comparison with a ticket's dates and the period
    shown ("From ... to ...") use UTC (Misc::db2gmtime(), cdr_dash_period()).
    "View the N tickets" opens core's ad hoc search on exactly the tickets
    at risk (cdr_dash_risk_redirect()). Core's markup is not touched: the panels
    are injected into the page's output buffer around the period form
    and before the rule that precedes "Statistics" (cdr_dash_inject());
    when core's markup is not found as expected the page is left as core
    renders it, or the panels stack above the form.

    1. Pure helpers (no osTicket needed: tested in tests/test-dashboard.php)
       - cdr_dash_int()           a "FROM_UNIXTIME(n)" expression as n
       - cdr_dash_period()        the period in database time (SQL) and in UTC
       - cdr_dash_risk_criteria() core ad hoc search criteria for the tickets at risk
       - cdr_dash_pct()           whole percentage or null
       - cdr_dash_delta()         change against the previous period
       - cdr_dash_span()          "4 h", "52 min", "3 d"
       - cdr_dash_priority_summary() averages, rate and risk list of the Priority tickets
       - cdr_dash_alert_text()    the alert strip's wording
       - cdr_dash_inject()        panels into core's page
    2. Queries
    3. Rendering
    4. Entry point (header.inc.php)

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');
require_once INCLUDE_DIR . 'staff/coderise/coderise.inc.php';

// Cap on the Priority rows read per dashboard page (see the header); tests may define a lower one.
if (!defined('CDR_DASH_PRIORITY_LIMIT'))
    define('CDR_DASH_PRIORITY_LIMIT', 3000);

if (!function_exists('cdr_dash_start')) {

    /* ------------------------------------------------------------------
     * 1. Pure helpers
     * ---------------------------------------------------------------- */

    /** n of "FROM_UNIXTIME(n)" (OverviewReport::getDateRange()), or null. */
    function cdr_dash_int($expr) {
        $m = array();
        return preg_match('/^FROM_UNIXTIME\((\d+)\)$/', (string) $expr, $m) ? (int) $m[1] : null;
    }

    /** A database time (Unix seconds, as Misc::dbtime() gives them) in UTC; unchanged without core. */
    function cdr_dash_gmt($ts) {
        if (class_exists('Misc', false) && is_callable(array('Misc', 'db2gmtime'))) {
            $g = Misc::db2gmtime((int) $ts);
            if (is_int($g) || (is_string($g) && ctype_digit($g)))
                return (int) $g;
        }
        return (int) $ts;
    }

    /**
     * The report period from OverviewReport::getDateRange(): array(db_from,
     * db_to, from, to), or null. db_* are database times (Misc::dbtime()),
     * used as they are in the SQL like core's statistics; from/to are UTC,
     * the time of every value read from a ticket (Misc::db2gmtime()) and of
     * Format::date(): comparisons and the subtitle use them.
     */
    function cdr_dash_period($range) {
        if (!is_array($range) || count($range) < 2)
            return null;
        $a = cdr_dash_int($range[0]);
        $b = cdr_dash_int($range[1]);
        if ($a === null || $b === null || $b < $a)
            return null;
        return array('db_from' => $a, 'db_to' => $b, 'from' => cdr_dash_gmt($a), 'to' => cdr_dash_gmt($b));
    }

    /**
     * Criteria of core's ad hoc ticket search (scp/tickets.php?queue=adhoc,<key>,
     * class.search.php AdhocSearch) matching exactly the tickets at risk: their
     * numbers, as one anchored pattern of the "Ticket Number matches" filter.
     * null when there is none or a number has a character outside letters,
     * digits and "-" (the pattern would need escaping: the caller falls back
     * to the queue).
     */
    function cdr_dash_risk_criteria($risk) {
        $nums = array();
        foreach ((array) $risk as $r) {
            $n = (string) ($r['number'] ?? '');
            if ($n === '' || !preg_match('/^[A-Za-z0-9-]+$/', $n))
                return null;
            $nums[$n] = true;
        }
        if (!$nums)
            return null;
        return array(array('number', 'match', '^(' . implode('|', array_keys($nums)) . ')$'));
    }

    /**
     * Writes $msg to the PHP error log once per session (once per request
     * without a session) for $key: a broken query or the row cap would
     * otherwise log on every dashboard page.
     */
    function cdr_dash_log_once($key, $msg) {
        static $done = array();
        if (isset($done[$key]))
            return false;
        $done[$key] = true;
        if (isset($_SESSION) && is_array($_SESSION)) {
            if (!empty($_SESSION['cdr_dash_logged'][$key]))
                return false;
            $_SESSION['cdr_dash_logged'][$key] = 1;
        }
        error_log('coderise-agent dashboard: ' . $msg);
        return true;
    }

    /** Whole percentage of $num in $den, null when $den is 0. */
    function cdr_dash_pct($num, $den) {
        return (int) $den > 0 ? (int) round(100 * (int) $num / (int) $den) : null;
    }

    /**
     * Change of $cur against $prev: array('kind' => none|new|flat|up|down,
     * 'pct' => int). none = nothing in either period, new = nothing before.
     */
    function cdr_dash_delta($cur, $prev) {
        $cur = (int) $cur;
        $prev = (int) $prev;
        if ($prev <= 0)
            return array('kind' => $cur > 0 ? 'new' : 'none', 'pct' => 0);
        $pct = (int) round(100 * ($cur - $prev) / $prev);
        return array('kind' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'), 'pct' => $pct);
    }

    /**
     * A duration for the strips: "52 min", "3 h 27", then "3 d" from 48 h
     * (cdr_duration() would print "98 h 03"). Non-breaking space before
     * the unit.
     */
    function cdr_dash_span($seconds) {
        $seconds = max(0, (int) $seconds);
        if ($seconds >= 48 * 3600)
            return sprintf(cdr_t('%d d'), intdiv($seconds, 86400));
        return str_replace(' ', "\xC2\xA0", cdr_duration($seconds));
    }

    /** The promise length, "4 h" (whole hours) or as cdr_dash_span(). */
    function cdr_dash_target($hours) {
        $hours = (float) $hours;
        if ($hours > 0 && $hours == floor($hours))
            return sprintf(cdr_t('%d h'), (int) $hours);
        return cdr_dash_span((int) round($hours * 3600));
    }

    /** A count with the language's digit grouping. */
    function cdr_dash_num($n) {
        if (class_exists('Format', false) && is_callable(array('Format', 'number')))
            return (string) Format::number((int) $n);
        return number_format((int) $n);
    }

    /** "94 %" (French: non-breaking space), the msgid keeps the sign attached in English. */
    function cdr_dash_percent($p) {
        return sprintf(cdr_t('%d%%'), (int) $p);
    }

    /**
     * Gauge of a Priority ticket row from plain data (the gauge helper's
     * rule, cdr_ticket_gauge(), without a Ticket object): the window starts
     * at the creation, the deadline is created + CDR_FIRST_RESPONSE_HOURS,
     * or the Priority plan's due date when the ticket is on that active plan
     * and has never been reopened. $row: created, est_duedate, reopened
     * (db dates, text), sla_name, sla_flags, first (db date or null).
     * Returns array(start, due, first) in Unix seconds, or null.
     */
    function cdr_dash_window($row, $hours) {
        $start = !empty($row['created']) ? (int) Misc::db2gmtime($row['created']) : 0;
        if ($start <= 0)
            return null;
        $due = $start + (int) round($hours * 3600);
        if (empty($row['reopened']) && CDR_SLA_PRIORITY !== ''
                && isset($row['sla_name']) && ((int) ($row['sla_flags'] ?? 0)) & 1
                && strcasecmp(trim((string) $row['sla_name']), CDR_SLA_PRIORITY) === 0
                && !empty($row['est_duedate']))
            $due = (int) Misc::db2gmtime($row['est_duedate']);
        $first = !empty($row['first']) ? (int) Misc::db2gmtime($row['first']) : null;
        return array('start' => $start, 'due' => $due, 'first' => $first);
    }

    /**
     * What the Priority panels need, from the rows of cdr_dash_priority_rows()
     * (each: id, number, open (bool), staff_id, first_staff, window =
     * cdr_dash_window()). $from..$to is the report period (tickets created
     * in it are counted), $now the current time.
     *
     *   avg_first   average seconds to the first client reply (null: none)
     *   answered    tickets of the period with a first reply
     *   decided     tickets of the period answered or already past their deadline
     *   met         of those, answered within the deadline
     *   risk        open tickets with no reply, over or past 75 % of the window,
     *               most urgent first: array(id, number, gauge)
     *   over, warn  their count by state
     *   oldest_over seconds the longest-waiting over ticket is late (0: none)
     *   next_due    seconds to the nearest deadline among the "warn" ones (null: none)
     *   by_staff    staff_id => array(decided, met) of the tickets whose first
     *               reply that agent wrote
     */
    function cdr_dash_priority_summary($rows, $from, $to, $now) {
        $sum = 0;
        $out = array('avg_first' => null, 'answered' => 0, 'decided' => 0, 'met' => 0,
            'risk' => array(), 'over' => 0, 'warn' => 0, 'oldest_over' => 0,
            'next_due' => null, 'by_staff' => array());
        foreach ((array) $rows as $r) {
            $w = isset($r['window']) ? $r['window'] : null;
            if (!is_array($w))
                continue;
            $inPeriod = $w['start'] >= $from && $w['start'] <= $to;
            if ($inPeriod) {
                if ($w['first'] !== null) {
                    $out['answered']++;
                    $sum += max(0, $w['first'] - $w['start']);
                    $out['decided']++;
                    $ok = $w['first'] <= $w['due'];
                    if ($ok)
                        $out['met']++;
                    $sid = (int) ($r['first_staff'] ?? 0);
                    if ($sid > 0) {
                        if (!isset($out['by_staff'][$sid]))
                            $out['by_staff'][$sid] = array('decided' => 0, 'met' => 0);
                        $out['by_staff'][$sid]['decided']++;
                        if ($ok)
                            $out['by_staff'][$sid]['met']++;
                    }
                }
                elseif ($now > $w['due'])
                    $out['decided']++;
            }
            if ($w['first'] === null && !empty($r['open'])) {
                $g = cdr_gauge_state($w['start'], $w['due'], null, $now);
                if (is_array($g) && ($g['kind'] === 'over' || $g['kind'] === 'warn')) {
                    $out['risk'][] = array('id' => (int) $r['id'], 'number' => (string) $r['number'],
                        'gauge' => $g, 'due' => $w['due']);
                    if ($g['kind'] === 'over') {
                        $out['over']++;
                        $out['oldest_over'] = max($out['oldest_over'], $now - $w['due']);
                    }
                    else {
                        $out['warn']++;
                        $left = $w['due'] - $now;
                        $out['next_due'] = $out['next_due'] === null ? $left : min($out['next_due'], $left);
                    }
                }
            }
        }
        if ($out['answered'] > 0)
            $out['avg_first'] = (int) round($sum / $out['answered']);
        // the deadline already past longest first, then the nearest deadlines
        usort($out['risk'], function ($a, $b) {
            return $a['due'] === $b['due'] ? $a['id'] - $b['id'] : $a['due'] - $b['due'];
        });
        return $out;
    }

    /**
     * Alert strip wording: array(strong, rest). $total tickets over or about
     * to be, $over of them over; $oldest = seconds the longest is late,
     * $next = seconds to the nearest deadline (warn only), $hours = promise.
     */
    function cdr_dash_alert_text($total, $over, $oldest, $next, $hours) {
        $t = cdr_dash_target($hours);
        $strong = $total === 1
            ? sprintf(cdr_t('1 Priority ticket is over or close to its %s first reply.'), $t)
            : sprintf(cdr_t('%1$d Priority tickets are over or close to their %2$s first reply.'), $total, $t);
        if ($over > 0)
            $rest = $total === 1
                ? sprintf(cdr_t('It is already over by %s.'), cdr_dash_span($oldest))
                : sprintf(cdr_t('The oldest is already over by %s.'), cdr_dash_span($oldest));
        else
            $rest = $total === 1
                ? sprintf(cdr_t('Its deadline is in %s.'), cdr_dash_span((int) $next))
                : sprintf(cdr_t('The nearest deadline is in %s.'), cdr_dash_span((int) $next));
        return array($strong, $rest);
    }

    /**
     * Puts the panels into core's dashboard output. $parts: head (title,
     * alert strip, indicators: before the form), main (after the chart
     * block, in the main column: the per-agent panel), side (the aside
     * column). Core's markers: the opening tag of the form and the first
     * rule after it (the one between the chart and "Statistics"). Without
     * the form the page is returned as it came; without the rule everything
     * is stacked before the form.
     */
    function cdr_dash_inject($html, $parts) {
        $form = '<form method="post" action="dashboard.php">';
        $at = strpos($html, $form);
        if ($at === false)
            return $html;
        $head = (string) ($parts['head'] ?? '');
        $main = (string) ($parts['main'] ?? '');
        $side = (string) ($parts['side'] ?? '');
        $bodyAt = $at + strlen($form);
        $hr = strpos($html, '<hr/>', $bodyAt);
        if ($hr === false)
            return substr($html, 0, $at) . $head
                . ($side !== '' ? '<div class="cdr-dash-stack">' . $side . '</div>' : '')
                . $main . substr($html, $at);
        // between the form and the rule: the period form, then "Ticket Activity" and the chart
        $middle = substr($html, $bodyAt, $hr - $bodyAt);
        $closeChart = '';
        $mt = array();
        // (the semicolon before the closing quote is optional: it differs between core versions)
        if (preg_match('/<div style="margin-bottom:20px; ?padding-top:5px;?">/', $middle, $mt, PREG_OFFSET_CAPTURE)) {
            $c = $mt[0][1];
            $middle = substr($middle, 0, $c) . '<section class="panel cdr-dash-chart">' . substr($middle, $c);
            $closeChart = '</section>';
        }
        $cols = '<div class="cols cdr-dash-cols"><div class="col-main">';
        $end = $closeChart . $main . '</div>'
            . ($side !== '' ? '<aside class="col-side" aria-label="' . cdr_h(cdr_t('To handle')) . '">' . $side . '</aside>' : '')
            . '</div>';
        // after the rule: "Statistics" and its tables, up to the end of the form, as one panel
        $rest = substr($html, $hr + 5);
        if (($f = strpos($rest, '</form>')) !== false)
            $rest = '<section class="panel cdr-dash-stats">' . substr($rest, 0, $f) . '</section>' . substr($rest, $f);
        else
            $rest = '<hr/>' . $rest;
        return substr($html, 0, $at) . $head . $form . $cols . $middle . $end . $rest;
    }

    /* ------------------------------------------------------------------
     * 2. Queries
     * ---------------------------------------------------------------- */

    /** SQL condition of the tickets (alias $a) the agent's dashboard covers. */
    function cdr_dash_scope($staff, $a = 't') {
        if (!is_object($staff))
            return '0';
        if ($staff->isAccessLimited()) {
            $or = array($a . '.staff_id=' . (int) $staff->getId());
            if ($teams = array_filter(array_map('intval', (array) $staff->getTeams())))
                $or[] = $a . '.team_id IN (' . implode(',', $teams) . ')';
            return '(' . implode(' OR ', $or) . ')';
        }
        $depts = array_filter(array_map('intval', (array) $staff->getDepts()));
        return $depts ? $a . '.dept_id IN (' . implode(',', $depts) . ')' : '0';
    }

    /**
     * Rows of a query as arrays; array() on a failed query (never throws).
     * Core's logging is off for these queries (db_query($sql, false)): with
     * "alert on SQL error" on, core mails the administrator for each failed
     * query, which would be one mail per dashboard page; one line goes to
     * the PHP error log instead (once per session).
     */
    function cdr_dash_fetch($sql) {
        $rows = array();
        try {
            $res = db_query($sql, false);
            if (!$res) {
                cdr_dash_log_once('query', 'a panel query failed'
                    . (function_exists('db_error') ? ' (' . db_error() . ')' : '')
                    . '; the panel shows no data. Query: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 300));
                return array();
            }
            while (($row = db_fetch_array($res)))
                $rows[] = $row;
        }
        catch (Throwable $e) {
            cdr_dash_log_once('query', 'a panel query failed (' . $e->getMessage() . '); the panel shows no data.');
            return array();
        }
        return $rows;
    }

    /** Counts of the tickets in scope: received (period and the one before), open, unassigned, overdue, closed. */
    function cdr_dash_counts($from, $to, $scope) {
        $len = max(1, $to - $from);
        $pf = $from - $len;
        $f = 'FROM_UNIXTIME(' . (int) $from . ')';
        $e = 'FROM_UNIXTIME(' . (int) $to . ')';
        $p = 'FROM_UNIXTIME(' . (int) $pf . ')';
        $sql = 'SELECT'
            . ' COUNT(CASE WHEN t.created BETWEEN ' . $f . ' AND ' . $e . ' THEN 1 END) AS received,'
            . ' COUNT(CASE WHEN t.created >= ' . $p . ' AND t.created < ' . $f . ' THEN 1 END) AS previous,'
            . ' COUNT(CASE WHEN s.state=\'open\' THEN 1 END) AS open_n,'
            . ' COUNT(CASE WHEN s.state=\'open\' AND t.staff_id=0 AND t.team_id=0 THEN 1 END) AS unassigned,'
            . ' COUNT(CASE WHEN s.state=\'open\' AND t.isoverdue=1 THEN 1 END) AS overdue,'
            . ' COUNT(CASE WHEN t.closed BETWEEN ' . $f . ' AND ' . $e . ' THEN 1 END) AS closed_n'
            . ' FROM ' . TICKET_TABLE . ' t JOIN ' . TICKET_STATUS_TABLE . ' s ON s.id=t.status_id'
            . ' WHERE (t.created >= ' . $p . ' OR t.closed >= ' . $f . ' OR s.state=\'open\') AND ' . $scope;
        $rows = cdr_dash_fetch($sql);
        $r = $rows ? $rows[0] : array();
        $out = array();
        foreach (array('received', 'previous', 'open_n', 'unassigned', 'overdue', 'closed_n') as $k)
            $out[$k] = (int) ($r[$k] ?? 0);
        return $out;
    }

    /**
     * Priority tickets (owner in the Priority organisation) created since
     * $from or still open, with the date and author of their first reply to
     * the client (the plugin's definition, contrib/sla-first-response/sql/
     * premiere-reponse.sql: type R, agent, neither system nor hidden, not a
     * canned SYSTEM reply, a "to" recipient). One query; the sub-selects use
     * the thread_id index. Closed tickets with no such reply are dropped
     * (no gauge, as cdr_ticket_gauge()). At most $limit rows, the open
     * tickets first (the risk list and alert need every one of them), then
     * the most recent; $truncated is set to true when the cap is reached.
     */
    function cdr_dash_priority_rows($orgId, $from, $scope, $hours, $limit = CDR_DASH_PRIORITY_LIMIT, &$truncated = null) {
        $mask = (int) ((defined('ThreadEntry::FLAG_HIDDEN') ? ThreadEntry::FLAG_HIDDEN : 0x04)
            | (defined('ThreadEntry::FLAG_SYSTEM') ? ThreadEntry::FLAG_SYSTEM : 0x80));
        $reply = 'te.thread_id=th.id AND te.type=\'R\' AND te.staff_id>0 AND (te.flags & ' . $mask . ')=0'
            . ' AND te.poster NOT LIKE \'SYSTEM%\' AND te.recipients LIKE \'%"to":{"%\' AND te.created>=t.created';
        $sql = 'SELECT t.ticket_id, t.number, t.created, t.est_duedate, t.reopened, t.staff_id, s.state,'
            . ' sl.name AS sla_name, sl.flags AS sla_flags,'
            . ' (SELECT MIN(te.created) FROM ' . THREAD_ENTRY_TABLE . ' te WHERE ' . $reply . ') AS first_reply,'
            . ' (SELECT te.staff_id FROM ' . THREAD_ENTRY_TABLE . ' te WHERE ' . $reply . ' ORDER BY te.created, te.id LIMIT 1) AS first_staff'
            . ' FROM ' . TICKET_TABLE . ' t'
            . ' JOIN ' . TICKET_STATUS_TABLE . ' s ON s.id=t.status_id'
            . ' JOIN ' . USER_TABLE . ' u ON u.id=t.user_id AND u.org_id=' . (int) $orgId
            . ' LEFT JOIN ' . SLA_TABLE . ' sl ON sl.id=t.sla_id'
            . ' LEFT JOIN ' . THREAD_TABLE . ' th ON th.object_type=\'T\' AND th.object_id=t.ticket_id'
            . ' WHERE (s.state=\'open\' OR t.created >= FROM_UNIXTIME(' . (int) $from . ')) AND ' . $scope
            . ' ORDER BY (s.state=\'open\') DESC, t.created DESC LIMIT ' . (int) $limit;
        $rows = array();
        $all = cdr_dash_fetch($sql);
        $truncated = count($all) >= (int) $limit;
        if ($truncated)
            cdr_dash_log_once('limit', sprintf('%d Priority tickets or more in scope: the average and rate'
                . ' of the first replies cover the %d most recent (open ones first).', (int) $limit, (int) $limit));
        foreach ($all as $r) {
            $open = ($r['state'] ?? '') === 'open';
            if (!$open && empty($r['first_reply']))
                continue;
            $w = cdr_dash_window(array(
                'created' => $r['created'] ?? null, 'est_duedate' => $r['est_duedate'] ?? null,
                'reopened' => $r['reopened'] ?? null, 'sla_name' => $r['sla_name'] ?? null,
                'sla_flags' => $r['sla_flags'] ?? 0, 'first' => $r['first_reply'] ?? null), $hours);
            if ($w === null || $w['due'] <= $w['start'])
                continue;
            $rows[] = array('id' => (int) $r['ticket_id'], 'number' => (string) $r['number'],
                'open' => $open, 'staff_id' => (int) $r['staff_id'],
                'first_staff' => (int) ($r['first_staff'] ?? 0), 'window' => $w);
        }
        return $rows;
    }

    /** Subjects of tickets (id => subject), one query on the cdata table; array() when it has no subject. */
    function cdr_dash_subjects($ids) {
        $ids = array_filter(array_map('intval', (array) $ids));
        if (!$ids)
            return array();
        $out = array();
        foreach (cdr_dash_fetch('SELECT ticket_id, subject FROM ' . TICKET_CDATA_TABLE
                . ' WHERE ticket_id IN (' . implode(',', $ids) . ')') as $r)
            $out[(int) $r['ticket_id']] = (string) $r['subject'];
        return $out;
    }

    /**
     * Open tickets per active agent, busiest first. The departments are those
     * of core's own agent statistics (class.report.php, getTabularData('staff')):
     * the viewer, plus the departments they manage, plus all their departments
     * with "stats.agents" (being an administrator is not enough, as in core).
     * Core picks the agents by the department of their activity in the period;
     * this panel, a current load, picks them by membership: primary department
     * or extended access (staff_dept_access) in one of those departments.
     */
    function cdr_dash_agents($staff, $scope, $limit = 12) {
        $depts = array_map('intval', (array) $staff->getManagedDepartments());
        if (class_exists('ReportModel', false) && $staff->hasPerm(ReportModel::PERM_AGENTS))
            $depts = array_merge($depts, array_map('intval', (array) $staff->getDepts()));
        $depts = array_values(array_unique(array_filter($depts)));
        $in = implode(',', $depts);
        $who = 'st.staff_id=' . (int) $staff->getId() . ($depts ? ' OR st.dept_id IN (' . $in . ')'
            . ' OR st.staff_id IN (SELECT da.staff_id FROM ' . STAFF_DEPT_TABLE . ' da WHERE da.dept_id IN (' . $in . '))' : '');
        $sql = 'SELECT st.staff_id, st.firstname, st.lastname, d.name AS dept, COUNT(t.ticket_id) AS open_n'
            . ' FROM ' . STAFF_TABLE . ' st'
            . ' LEFT JOIN ' . DEPT_TABLE . ' d ON d.id=st.dept_id'
            . ' LEFT JOIN ' . TICKET_TABLE . ' t ON t.staff_id=st.staff_id AND ' . $scope
            . ' AND t.status_id IN (SELECT id FROM ' . TICKET_STATUS_TABLE . ' WHERE state=\'open\')'
            . ' WHERE st.isactive=1 AND (' . $who . ')'
            . ' GROUP BY st.staff_id, st.firstname, st.lastname, d.name'
            . ' ORDER BY open_n DESC, st.lastname, st.firstname LIMIT ' . (int) $limit;
        $out = array();
        foreach (cdr_dash_fetch($sql) as $r)
            $out[] = array('id' => (int) $r['staff_id'],
                'name' => trim($r['firstname'] . ' ' . $r['lastname']),
                'dept' => (string) ($r['dept'] ?? ''), 'open' => (int) $r['open_n']);
        return $out;
    }

    /** The help topics with the most tickets received in the period, with their count. */
    function cdr_dash_topics($from, $to, $scope, $limit = 5) {
        $sql = 'SELECT t.topic_id, h.topic, COUNT(*) AS n FROM ' . TICKET_TABLE . ' t'
            . ' LEFT JOIN ' . TOPIC_TABLE . ' h ON h.topic_id=t.topic_id'
            . ' WHERE t.created BETWEEN FROM_UNIXTIME(' . (int) $from . ') AND FROM_UNIXTIME(' . (int) $to . ')'
            . ' AND t.topic_id>0 AND ' . $scope
            . ' GROUP BY t.topic_id, h.topic ORDER BY n DESC, h.topic LIMIT ' . (int) $limit;
        $names = null;
        $out = array();
        foreach (cdr_dash_fetch($sql) as $r) {
            $name = (string) ($r['topic'] ?? '');
            if (class_exists('Topic', false) && is_callable(array('Topic', 'getHelpTopics'))) {
                try {
                    if ($names === null)
                        $names = (array) Topic::getHelpTopics(false, true);
                    if (isset($names[(int) $r['topic_id']]))
                        $name = (string) $names[(int) $r['topic_id']];
                }
                catch (Throwable $e) {
                    $names = array();
                }
            }
            if ($name !== '')
                $out[] = array('name' => $name, 'n' => (int) $r['n']);
        }
        return $out;
    }

    /**
     * Everything the panels show, memoised for the request. $report: the
     * OverviewReport core builds from the same POST fields (period).
     */
    function cdr_dash_model($staff, $report) {
        static $memo = null;
        if ($memo !== null)
            return $memo;
        $memo = array('from' => 0, 'to' => 0, 'counts' => null, 'prio' => null, 'agents' => null,
            'topics' => array(), 'hours' => CDR_FIRST_RESPONSE_HOURS, 'queue' => '', 'subjects' => array(),
            'risk_url' => '', 'truncated' => 0);
        if (!is_object($staff) || !is_object($report))
            return $memo;
        $period = cdr_dash_period($report->getDateRange());
        if ($period === null)
            return $memo;
        // database times in the SQL (as core's statistics), UTC everywhere else
        $from = $period['db_from'];
        $to = $period['db_to'];
        $memo['from'] = $period['from'];
        $memo['to'] = $period['to'];
        $scope = cdr_dash_scope($staff);
        $memo['counts'] = cdr_dash_counts($from, $to, $scope);
        $memo['topics'] = cdr_dash_topics($from, $to, $scope);
        $memo['agents'] = cdr_dash_agents($staff, $scope);
        $org = (function_exists('coderise_contract_level') && class_exists('SlaContractLevel', false))
            ? SlaContractLevel::priorityOrg() : null;
        if (is_object($org) && ($orgId = (int) $org->getId()) > 0) {
            $len = max(1, $to - $from);
            $truncated = false;
            $rows = cdr_dash_priority_rows($orgId, $from - $len, $scope, CDR_FIRST_RESPONSE_HOURS,
                CDR_DASH_PRIORITY_LIMIT, $truncated);
            $memo['truncated'] = $truncated ? CDR_DASH_PRIORITY_LIMIT : 0;
            $memo['prio'] = cdr_dash_priority_summary($rows, $period['from'], $period['to'], time());
            $ids = array();
            foreach (array_slice($memo['prio']['risk'], 0, 5) as $r)
                $ids[] = $r['id'];
            $memo['subjects'] = cdr_dash_subjects($ids);
        }
        // "Open" queue: the default one when it is configured
        global $cfg;
        $qid = (is_object($cfg) && is_callable(array($cfg, 'getDefaultTicketQueueId')))
            ? (int) $cfg->getDefaultTicketQueueId() : 0;
        $memo['queue'] = ROOT_PATH . 'scp/tickets.php' . ($qid ? '?queue=' . $qid : '');
        // "View the N tickets": the dashboard itself, which stores core's ad hoc search of
        // those tickets and redirects to it (cdr_dash_risk_redirect()); the queue when the
        // numbers cannot be expressed as a search
        $memo['risk_url'] = (is_array($memo['prio']) && cdr_dash_risk_criteria($memo['prio']['risk']))
            ? ROOT_PATH . 'scp/dashboard.php?cdr_risk=1' : $memo['queue'];
        return $memo;
    }

    /**
     * scp/dashboard.php?cdr_risk=1 (header.inc.php, before any output): reads the
     * tickets at risk now, in the agent's scope, stores them as core's ad hoc search
     * "cdr-risk" ($_SESSION['advsearch'], as core's own ?orgid= and ?uid= links do)
     * and redirects to that list (tickets.php?queue=adhoc,cdr-risk), whose rows are
     * also limited by core to the tickets the agent may see. Without a ticket at risk
     * (or a number the search cannot express), the default queue. Never returns.
     */
    function cdr_dash_risk_redirect($staff) {
        $url = ROOT_PATH . 'scp/tickets.php';
        try {
            if (class_exists('OverviewReport', false)) {
                $m = cdr_dash_model($staff, new OverviewReport(null, 'now'));
                $url = $m['queue'] ?: $url;
                $crit = is_array($m['prio']) ? cdr_dash_risk_criteria($m['prio']['risk']) : null;
                if ($crit && isset($_SESSION) && is_array($_SESSION)) {
                    $_SESSION['advsearch']['cdr-risk'] = $crit;
                    $url = ROOT_PATH . 'scp/tickets.php?queue=adhoc,cdr-risk';
                }
            }
        }
        catch (Throwable $e) {
            cdr_dash_log_once('risk', 'the list of the tickets at risk could not be built (' . $e->getMessage() . ').');
        }
        if (class_exists('Http', false) && is_callable(array('Http', 'redirect')))
            Http::redirect($url);
        header('Location: ' . $url);
        exit;
    }

    /* ------------------------------------------------------------------
     * 3. Rendering
     * ---------------------------------------------------------------- */

    /** A date of the period, in the panel language ("27 septembre 2026"). */
    function cdr_dash_date($ts) {
        if (class_exists('Format', false) && is_callable(array('Format', 'date'))) {
            $d = extension_loaded('intl') ? Format::date($ts, false, 'd MMMM y') : '';
            return $d !== '' ? $d : (string) Format::date($ts, false);
        }
        return gmdate('j M Y', (int) $ts);
    }

    /** Target of "View the N tickets" / "View all (N)": the list of the tickets at risk, else the queue. */
    function cdr_dash_risk_link($m) {
        return !empty($m['risk_url']) ? $m['risk_url'] : (string) ($m['queue'] ?? '');
    }

    /** Title, alert strip and indicators. */
    function cdr_dash_head($m) {
        $h = '<div class="cdr-dash cdr-dash-head"><div class="cdr-dash-title"><h1>' . cdr_h(cdr_t('Dashboard')) . '</h1>';
        if ($m['to'] > 0)
            $h .= '<p class="sub">' . cdr_h(sprintf(cdr_t('From %1$s to %2$s.'),
                cdr_dash_date($m['from']), cdr_dash_date($m['to']))) . '</p>';
        $h .= '</div></div>';
        $p = $m['prio'];
        if (is_array($p) && ($total = $p['over'] + $p['warn']) > 0) {
            list($strong, $rest) = cdr_dash_alert_text($total, $p['over'], $p['oldest_over'], $p['next_due'], $m['hours']);
            $one = $total === 1 ? $p['risk'][0] : null;
            // several: the list of exactly those tickets (cdr_dash_risk_redirect(), full page load)
            $link = $one ? ROOT_PATH . 'scp/tickets.php?id=' . (int) $one['id'] : cdr_dash_risk_link($m);
            $label = $one ? cdr_t('View the ticket') : sprintf(cdr_t('View the %d tickets'), $total);
            $h .= '<div class="cdr-dash alert-strip" role="status">' . cdr_icon('alert', 22)
                . '<p><strong>' . cdr_h($strong) . '</strong> ' . cdr_h($rest) . '</p>'
                . '<a class="btn btn-primary-dark' . ($one ? '' : ' no-pjax') . '" href="' . cdr_h($link) . '">' . cdr_h($label) . '</a></div>';
        }
        $c = $m['counts'];
        if (!is_array($c))
            return $h;
        $cards = array();
        $d = cdr_dash_delta($c['received'], $c['previous']);
        $days = max(1, (int) round(($m['to'] - $m['from']) / 86400));
        $sub = array('up' => sprintf(cdr_t('%+d%% vs the previous %d days'), $d['pct'], $days),
            'down' => sprintf(cdr_t('%+d%% vs the previous %d days'), $d['pct'], $days),
            'flat' => sprintf(cdr_t('Stable against the previous %d days'), $days),
            'new' => cdr_t('No ticket in the previous period'),
            'none' => cdr_t('No ticket in this period'));
        $cards[] = array(cdr_dash_num($c['received']), cdr_t('Tickets received'), $sub[$d['kind']], 'flat');
        $cards[] = array(cdr_dash_num($c['open_n']), cdr_t('Open tickets'),
            $c['unassigned'] > 0 ? sprintf(cdr_t('%d unassigned'), $c['unassigned']) : cdr_t('All assigned'),
            $c['unassigned'] > 0 ? 'warn' : 'up');
        if (is_array($p)) {
            $target = $m['hours'] * 3600;
            if ($p['avg_first'] === null)
                $cards[] = array("\xE2\x80\x94", cdr_t('Average first reply, Priority'), cdr_t('No Priority ticket answered in this period'), 'flat');
            else
                $cards[] = array(cdr_dash_span($p['avg_first']), cdr_t('Average first reply, Priority'),
                    sprintf($p['avg_first'] <= $target ? cdr_t('Target of %s met') : cdr_t('Target of %s missed'), cdr_dash_target($m['hours'])),
                    $p['avg_first'] <= $target ? 'up' : 'down');
            $pct = cdr_dash_pct($p['met'], $p['decided']);
            if ($pct === null)
                $cards[] = array("\xE2\x80\x94", cdr_t('First replies within the target'), cdr_t('No Priority ticket to measure in this period'), 'flat');
            else
                $cards[] = array(cdr_dash_percent($pct), cdr_t('First replies within the target'),
                    sprintf(cdr_t('%1$d of %2$d Priority tickets'), $p['met'], $p['decided']),
                    $pct >= 90 ? 'up' : ($pct >= 70 ? 'flat' : 'down'));
        }
        else {
            $cards[] = array(cdr_dash_num($c['closed_n']), cdr_t('Tickets closed'), cdr_t('In this period'), 'flat');
            $cards[] = array(cdr_dash_num($c['overdue']), cdr_t('Overdue tickets'),
                $c['overdue'] > 0 ? cdr_t('Open and past their due date') : cdr_t('None overdue'),
                $c['overdue'] > 0 ? 'down' : 'up');
        }
        $h .= '<div class="cdr-dash stats">';
        foreach ($cards as $k) {
            $ic = $k[3] === 'down' ? cdr_icon('alert', 12) : '';
            $h .= '<div><b>' . cdr_h($k[0]) . '</b><span>' . cdr_h($k[1]) . '</span>'
                . '<span class="delta delta-' . ($k[3] === 'warn' ? 'down' : $k[3]) . '">' . $ic . cdr_h($k[2]) . '</span></div>';
        }
        $h .= '</div>';
        // the Priority rows were capped (CDR_DASH_PRIORITY_LIMIT): the average and rate are partial
        if (is_array($p) && !empty($m['truncated']))
            $h .= '<p class="cdr-dash cdr-dash-note">' . cdr_h(sprintf(cdr_t('Average and rate computed on the %s most recent Priority tickets (open ones first).'),
                cdr_dash_num($m['truncated']))) . '</p>';
        return $h;
    }

    /** "First reply at risk" panel (Priority tickets over or close to the deadline). */
    function cdr_dash_risk_panel($m) {
        $p = $m['prio'];
        if (!is_array($p))
            return '';
        $h = '<section class="panel cdr-dash"><div class="panel-h"><h2>' . cdr_icon('gauge', 18)
            . cdr_h(cdr_t('First reply at risk')) . '</h2>';
        $total = count($p['risk']);
        if ($total > 5)
            $h .= '<a class="more no-pjax" href="' . cdr_h(cdr_dash_risk_link($m)) . '">' . cdr_h(sprintf(cdr_t('View all (%d)'), $total)) . '</a>';
        $h .= '</div>';
        if ($total === 0)
            return $h . '<p class="cdr-dash-empty">' . cdr_icon('checkc', 16)
                . cdr_h(cdr_t('No Priority ticket is waiting past 75 % of its first-reply window.')) . '</p></section>';
        $h .= '<div class="table-wrap"><table class="data"><caption class="sr-only">'
            . cdr_h(cdr_t('Priority tickets close to or past the first-reply target')) . '</caption><tbody>';
        foreach (array_slice($p['risk'], 0, 5) as $r) {
            $subject = $m['subjects'][$r['id']] ?? '';
            $h .= '<tr><td><a href="' . cdr_h(ROOT_PATH . 'scp/tickets.php?id=' . $r['id']) . '">#' . cdr_h($r['number']) . '</a>'
                . ($subject !== '' ? '<small class="muted">' . cdr_h($subject) . '</small>' : '')
                . '</td><td>' . cdr_gauge_html($r['gauge']) . '</td></tr>';
        }
        return $h . '</tbody></table></div></section>';
    }

    /** "By help topic" panel. */
    function cdr_dash_topics_panel($m) {
        $h = '<section class="panel cdr-dash"><div class="panel-h"><h2>' . cdr_icon('tag', 18)
            . cdr_h(cdr_t('By help topic')) . '</h2></div>';
        if (!$m['topics'])
            return $h . '<p class="cdr-dash-empty">' . cdr_h(cdr_t('No ticket with a help topic in this period.')) . '</p></section>';
        $h .= '<dl class="props">';
        foreach ($m['topics'] as $t)
            $h .= '<div><dt>' . cdr_h($t['name']) . '</dt><dd>' . cdr_h(cdr_dash_num($t['n'])) . '</dd></div>';
        return $h . '</dl></section>';
    }

    /** "Load by agent" panel; '' without data (no permission, no agent). */
    function cdr_dash_agents_panel($m) {
        if (empty($m['agents']))
            return '';
        $max = 1;
        foreach ($m['agents'] as $a)
            $max = max($max, $a['open']);
        $p = $m['prio'];
        $h = '<section class="panel cdr-dash"><div class="panel-h"><h2>' . cdr_icon('users', 18)
            . cdr_h(cdr_t('Load by agent')) . '</h2></div>'
            . '<div class="table-wrap"><table class="data"><caption class="sr-only">'
            . cdr_h(is_array($p) ? cdr_t('Open tickets and first-reply target by agent') : cdr_t('Open tickets by agent'))
            . '</caption><thead><tr><th scope="col">' . cdr_h(cdr_t('Agent')) . '</th><th scope="col">'
            . cdr_h(cdr_t('Open')) . '</th><th scope="col">' . cdr_h(cdr_t('Load')) . '</th>'
            . (is_array($p) ? '<th scope="col">' . cdr_h(cdr_t('First reply met')) . '</th>' : '')
            . '</tr></thead><tbody>';
        foreach ($m['agents'] as $a) {
            $w = (int) round(100 * $a['open'] / $max);
            $h .= '<tr><th scope="row"><span class="who"><span class="avatar av-team av-sm" aria-hidden="true">'
                . cdr_h(cdr_initials($a['name'])) . '</span><span class="name">' . cdr_h($a['name'])
                . ($a['dept'] !== '' ? '<small>' . cdr_h($a['dept']) . '</small>' : '') . '</span></span></th>'
                . '<td data-label="' . cdr_h(cdr_t('Open')) . '"><strong>' . cdr_h(cdr_dash_num($a['open'])) . '</strong></td>'
                . '<td data-label="' . cdr_h(cdr_t('Load')) . '"><div class="load" role="img" aria-label="'
                . cdr_h(sprintf(cdr_t('%1$d tickets, the busiest agent has %2$d'), $a['open'], $max)) . '"><i style="width:' . $w . '%"></i></div></td>';
            if (is_array($p)) {
                $s = $p['by_staff'][$a['id']] ?? null;
                $pct = $s ? cdr_dash_pct($s['met'], $s['decided']) : null;
                $h .= '<td data-label="' . cdr_h(cdr_t('First reply met')) . '">'
                    . ($pct === null ? '<span class="muted">' . "\xE2\x80\x94" . '</span>' : cdr_h(cdr_dash_percent($pct))) . '</td>';
            }
            $h .= '</tr>';
        }
        return $h . '</tbody></table></div></section>';
    }

    /** The three blocks cdr_dash_inject() places: head, main (agents) and side. */
    function cdr_dash_render($m) {
        return array('head' => cdr_dash_head($m), 'main' => cdr_dash_agents_panel($m),
            'side' => cdr_dash_risk_panel($m) . cdr_dash_topics_panel($m));
    }

    /* ------------------------------------------------------------------
     * 4. Entry point
     * ---------------------------------------------------------------- */

    /**
     * Called by header.inc.php on dashboard.php, before #content is
     * printed: reads the data now (the database is open, the page is not
     * flushed yet) and puts the panels into the page when it is flushed.
     * Any failure leaves the page as core renders it.
     */
    function cdr_dash_start($staff) {
        try {
            if (!class_exists('OverviewReport', false))
                return false;
            $report = new OverviewReport($_POST['start'] ?? null, $_POST['period'] ?? 'now');
            $parts = cdr_dash_render(cdr_dash_model($staff, $report));
            return ob_start(function ($html) use ($parts) {
                try {
                    return cdr_dash_inject($html, $parts);
                }
                catch (Throwable $e) {
                    return $html;
                }
            });
        }
        catch (Throwable $e) {
            return false;
        }
    }
}
