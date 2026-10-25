<?php
/*********************************************************************
    include/staff/templates/queue-tickets.tmpl.php  (coderise-agent theme)

    Core's queue template plus, in the mockup's order: a contract chip
    under the ticket number, the client and organisation under the subject
    (core's "From" column is then hidden by CSS), and a "First reply"
    column right after the subject (the Priority contract's first-reply
    gauge; chip and gauge need the plugin sla_first_response). Everything
    else is core's markup, unchanged: sorting links, tids[] checkboxes,
    $C->render($T) cells, agent actions, pagination, export, quick search.
    Shipped by deploy/themes-staff/coderise-agent (osTicket core untouched).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');
require_once INCLUDE_DIR . 'staff/coderise/coderise.inc.php';

// Calling convention (assumed global scope):
// $tickets - <QuerySet> with all columns and annotations necessary to
//      render the full page


// Impose visibility constraints
// ------------------------------------------------------------
//filter if limited visibility or if unlimited visibility and in a queue
$ignoreVisibility = $queue->ignoreVisibilityConstraints($thisstaff);
if (!$ignoreVisibility || //limited visibility
   ($ignoreVisibility && ($queue->isAQueue() || $queue->isASubQueue())) //unlimited visibility + not a search
)
    $tickets->filter($thisstaff->getTicketsVisibility());

// do not show children tickets unless agent is doing a search
if ($queue->isAQueue() || $queue->isASubQueue())
    $tickets->filter(Q::any(
            array('ticket_pid' => null, 'flags__hasbit' => TICKET::FLAG_LINKED)));

// Make sure the cdata materialized view is available
TicketForm::ensureDynamicDataView();

// Identify columns of output
$columns = $queue->getColumns();

// Figure out REFRESH url — which might not be accurate after posting a
// response
// Remove some variables from query string.
$qsFilter = ['id'];
if (isset($_REQUEST['a']) && ($_REQUEST['a'] !== 'search'))
    $qsFilter[] = 'a';
$refresh_url = Http::refresh_url($qsFilter);

// Establish the selected or default sorting mechanism
if (isset($_GET['sort']) && is_numeric($_GET['sort'])) {
    $sort = $_SESSION['sort'][$queue->getId()] = array(
        'col' => (int) $_GET['sort'],
        'dir' => (int) $_GET['dir'],
    );
}
elseif (isset($_GET['sort'])
    // Drop the leading `qs-`
    && (strpos($_GET['sort'], 'qs-') === 0)
    && ($sort_id = substr($_GET['sort'], 3))
    && is_numeric($sort_id)
    && ($sort = QueueSort::lookup($sort_id))
) {
    $sort = $_SESSION['sort'][$queue->getId()] = array(
        'queuesort' => $sort,
        'dir' => (int) $_GET['dir'],
    );
}
elseif (isset($_SESSION['sort'][$queue->getId()])) {
    $sort = $_SESSION['sort'][$queue->getId()];
}
elseif ($queue_sort = $queue->getDefaultSort()) {
    $sort = $_SESSION['sort'][$queue->getId()] = array(
        'queuesort' => $queue_sort,
        'dir' => (int) $_GET['dir'] ?? 0,
    );
}

// Handle current sorting preferences

$sorted = false;
foreach ($columns as $C) {
    // Sort by this column ?
    if (isset($sort['col']) && $sort['col'] == $C->id) {
        $tickets = $C->applySort($tickets, $sort['dir']);
        $sorted = true;
    }
}

// Apply queue sort if it's not already sorted by a column
if (!$sorted) {
    // Apply queue sort-dropdown selected preference
    if (isset($sort['queuesort']))
        $sort['queuesort']->applySort($tickets, $sort['dir']);
    else // otherwise sort by created DESC
        $tickets->order_by('-created');
}

// Apply pagination

$page = (isset($_GET['p']) && is_numeric($_GET['p']))?$_GET['p']:1;
$pageNav = new Pagenate(PHP_INT_MAX, $page, PAGE_LIMIT);
$tickets = $pageNav->paginateSimple($tickets);

if (isset($tickets->extra['tables'])) {
    // Creative twist here. Create a new query copying the query criteria, sort, limit,
    // and offset. Then join this new query to the $tickets query and clear the
    // criteria, sort, limit, and offset from the outer query.
    $criteria = clone $tickets;
    $criteria->limit(500);
    $criteria->annotations = $criteria->related = $criteria->aggregated =
        $criteria->annotations = $criteria->ordering = [];
    $tickets->constraints = $tickets->extra = [];
    $criteria->extra(array('select' => array('relevance' => 'Z1.relevance')));
    $tickets = $tickets->filter(['ticket_id__in' =>
            $criteria->values_flat('ticket_id')]);
    $tickets->order_by(new SqlCode('relevance'), QuerySet::DESC);
    # Index hint should be used on the $criteria query only
    $tickets->clearOption(QuerySet::OPT_INDEX_HINT);
}

$tickets->distinct('ticket_id');
$Q = $queue->getBasicQuery();

if ($Q->constraints) {
    if (count($Q->constraints) > 1) {
        foreach ($Q->constraints as $value) {
            if (!$value->constraints)
                $empty = true;
        }
    }
}

if (($Q->extra && isset($Q->extra['tables'])) || !$Q->constraints || $empty) {
    $skipCount = true;
    $count = '-';
}

$count = $count ?? $queue->getCount($thisstaff);
$pageNav->setTotal($count, true);
$pageNav->setURL('tickets.php', $args);
?>

<!-- SEARCH FORM START -->
<div id='basic_search'>
  <div class="pull-right" style="height:25px">
    <span class="valign-helper"></span>
    <?php
    require 'queue-quickfilter.tmpl.php';
    if ($queue->getSortOptions())
        require 'queue-sort.tmpl.php';
    ?>
  </div>
    <form action="tickets.php" method="get" onsubmit="javascript:
  $.pjax({
    url:$(this).attr('action') + '?' + $(this).serialize(),
    container:'#pjax-container',
    timeout: 2000
  });
return false;">
    <input type="hidden" name="a" value="search">
    <input type="hidden" name="search-type" value=""/>
    <div class="attached input">
      <input type="text" class="basic-search" data-url="ajax.php/tickets/lookup" name="query"
        autofocus size="30" value="<?php echo Format::htmlchars($_REQUEST['query'] ?? null, true); ?>"
        autocomplete="off" autocorrect="off" autocapitalize="off">
      <button type="submit" class="attached button"><i class="icon-search"></i>
      </button>
    </div>
    <a href="#" onclick="javascript:
        $.dialog('ajax.php/tickets/search', 201);"
        >[<?php echo __('advanced'); ?>]</a>
        <i class="help-tip icon-question-sign" href="#advanced"></i>
    </form>
</div>
<!-- SEARCH FORM END -->

<div class="clear"></div>
<div style="margin-bottom:20px; padding-top:5px;">
    <div class="sticky bar opaque">
        <div class="content">
            <div class="pull-left flush-left">
                <h2><a href="<?php echo $refresh_url; ?>"
                    title="<?php echo __('Refresh'); ?>"><i class="icon-refresh"></i> <?php echo
                    $queue->getName(); ?></a>
                    <?php
                    if (($crit=$queue->getSupplementalCriteria()))
                        echo sprintf('<i class="icon-filter"
                                data-placement="bottom" data-toggle="tooltip"
                                title="%s"></i>&nbsp;',
                                Format::htmlchars($queue->describeCriteria($crit)));
                    ?>
                </h2>
            </div>
            <div class="configureQ">
                <i class="icon-cog"></i>
                <div class="noclick-dropdown anchor-left">
                    <ul>
                        <li>
                            <a class="no-pjax" href="#"
                              data-dialog="ajax.php/tickets/search/<?php echo
                              urlencode($queue->getId()); ?>"><i
                            class="icon-fixed-width icon-pencil"></i>
                            <?php echo __('Edit'); ?></a>
                        </li>
                        <li>
                            <a class="no-pjax" href="#"
                              data-dialog="ajax.php/tickets/search/create?pid=<?php
                              echo $queue->getId(); ?>"><i
                            class="icon-fixed-width icon-plus-sign"></i>
                            <?php echo __('Add Sub Queue'); ?></a>
                        </li>
<?php

if ($queue->id > 0 && $queue->isOwner($thisstaff)) { ?>
                        <li class="danger">
                            <a class="no-pjax confirm-action" href="#"
                                data-dialog="ajax.php/queue/<?php
                                echo $queue->id; ?>/delete"><i
                            class="icon-fixed-width icon-trash"></i>
                            <?php echo __('Delete'); ?></a>
                        </li>
<?php } ?>
                    </ul>
                </div>
            </div>

          <div class="pull-right flush-right">
            <?php
            // TODO: Respect queue root and corresponding actions
            if ($count) {
                Ticket::agentActions($thisstaff, array('status' => $status ?? null));
            }?>
            </div>
        </div>
    </div>
</div>
<div class="clear"></div>

<?php
// Coderise: per-row data of the theme, read before the table in one query
// (owner, owner's name and organisation, state of every row of the page):
//  - the client line under the subject ("Client, organisation", mockup
//    "Sujet et client"), always. When the queue has core's "From" column
//    (user__name), the line is core's own rendering of that cell (its link
//    filter and annotations, e.g. the collaborator count, kept) followed
//    by the escaped organisation; otherwise the owner's name, escaped;
//  - with the plugin sla_first_response only: the contract chip under the
//    ticket number and the first-reply gauge. The contract level is
//    resolved once per owner; only open tickets of a Priority owner are
//    looked up (one Ticket::lookup, one thread query and the SLA each,
//    bounded by the page size). Closed tickets and Standard owners show a dash ("Not
//    applicable"). No chip when the plugin has no Priority organisation
//    to decide from (source "default"), as in the ticket view.
// The rows of $tickets are cached by the QuerySet, so the table below does
// not query them again. The columns are found by their data path
// (cdr_queue_layout()): the gauge cell follows the subject column, the
// chip goes under the number; core's "From" column (user__name) gets the
// class cdr-col-from once the client is printed under the subject (a
// subject column exists and the query succeeded): queue.css hides it, it
// stays in the DOM at its place (its cells and no-CSS reading are core's)
// and its sort link is repeated in the subject heading ("Subject / From").
$cdrGauge = function_exists('coderise_contract_level');
$cdrGauges = $cdrPlans = $cdrClients = $cdrOrgs = array();
$cdrPrimaries = array();
foreach ($columns as $C)
    $cdrPrimaries[] = (string) $C->primary;
$cdrLayout = cdr_queue_layout($cdrPrimaries);
$cdrStacked = $cdrLayout['subject'] !== null;
$cdrIds = array();
foreach ($tickets as $cdrT) {
    if (isset($cdrT['ticket_id']))
        $cdrIds[] = (int) $cdrT['ticket_id'];
}
if ($cdrIds) {
    $cdrOwners = array();
    try {
        $cdrRows = Ticket::objects()
            ->filter(array('ticket_id__in' => $cdrIds))
            ->values_flat('ticket_id', 'user_id', 'status__state',
                'user__name', 'user__org__name');
        foreach ($cdrRows as $cdrR) {
            list($cdrTid, $cdrUid, $cdrState, $cdrName, $cdrOrg) = $cdrR;
            $cdrTid = (int) $cdrTid;
            $cdrUid = (int) $cdrUid;
            $cdrOrgs[$cdrTid] = $cdrOrg;
            if ($cdrName !== null && $cdrName !== '')
                $cdrClients[$cdrTid] = cdr_queue_client(class_exists('UsersName')
                    ? (string) new UsersName($cdrName) : $cdrName, $cdrOrg);
            if (!$cdrGauge)
                continue;
            if (!array_key_exists($cdrUid, $cdrOwners)) {
                $cdrUser = $cdrUid ? User::lookup($cdrUid) : null;
                $cdrL = $cdrUser ? coderise_contract_level($cdrUser) : null;
                $cdrOwners[$cdrUid] = is_array($cdrL) ? $cdrL : null;
            }
            $cdrL = $cdrOwners[$cdrUid];
            if ($cdrL && ($cdrL['source'] ?? '') !== 'default')
                $cdrPlans[$cdrTid] = cdr_plan_chip($cdrL);
            if ($cdrL && ($cdrL['level'] ?? '') === 'priority' && $cdrState === 'open'
                    && ($cdrTicket = Ticket::lookup($cdrTid)))
                $cdrGauges[$cdrTid] = cdr_gauge_html(cdr_ticket_gauge($cdrTicket));
        }
    }
    catch (Throwable $e) {
        // Every row falls back to core's cells and the dash; leave a trace
        // for the admin.
        error_log('coderise-agent: queue rows skipped (client, contract, gauge): '
            . get_class($e) . ': ' . $e->getMessage());
        $cdrGauges = $cdrPlans = $cdrClients = $cdrOrgs = array();
        $cdrStacked = false;
    }
}
if ($cdrGauge) {
    $cdrNone = '<span class="gauge-none" title="' . cdr_h(cdr__('Not applicable'))
        . '"><span aria-hidden="true">&mdash;</span><span class="sr-only">'
        . cdr_h(cdr__('Not applicable')) . '</span></span>';
}
?>
<form action="?" method="POST" name='tickets' id="tickets">
<?php csrf_token(); ?>
 <input type="hidden" name="a" value="mass_process" >
 <input type="hidden" name="do" id="action" value="" >

<table class="list queue tickets" border="0" cellspacing="1" cellpadding="2" width="940">
  <thead>
    <tr>
<?php
$canManageTickets = $thisstaff->canManageTickets();
if ($canManageTickets) { ?>
        <th style="width:12px"></th>
<?php
}

// Coderise: the first-reply heading follows the subject column (else the
// last one); core's "From" column is tagged when the client is stacked
// under the subject (hidden by queue.css), and its heading (sort link
// included) is repeated after the subject's: "Subject / From".
$cdrGaugeTh = $cdrGauge
    ? '<th class="cdr-gauge-col">' . cdr_h(cdr__('First reply')) . '</th>' : '';
$cdrHeads = array();
foreach ($columns as $C) {
    $heading = Format::htmlchars($C->getLocalHeading());
    if ($C->isSortable()) {
        $args = $_GET;
        $dir = $sort['col'] != $C->id ?: ($sort['dir'] ? 'desc' : 'asc');
        $args['dir'] = $sort['col'] != $C->id ?: (int) !$sort['dir'];
        $args['sort'] = $C->id;
        $heading = sprintf('<a href="?%s" class="%s">%s</a>',
            Http::build_query($args), $dir, $heading);
    }
    $cdrHeads[] = array($C, $heading);
}
if ($cdrLayout['gauge'] < 0)
    echo $cdrGaugeTh;
foreach ($cdrHeads as $cdrI => $cdrH) {
    list($C, $heading) = $cdrH;
    if ($cdrStacked && $cdrI === $cdrLayout['subject'] && $cdrLayout['user'] !== null
            && isset($cdrHeads[$cdrLayout['user']]))
        $heading .= '<span class="cdr-th-sep" aria-hidden="true"> / </span>'
            . $cdrHeads[$cdrLayout['user']][1];
    echo sprintf('<th width="%s" data-id="%d"%s>%s</th>',
        $C->getWidth(), $C->id,
        ($cdrStacked && $cdrI === $cdrLayout['user']) ? ' class="cdr-col-from"' : '',
        $heading);
    if ($cdrI === $cdrLayout['gauge'])
        echo $cdrGaugeTh;
}
?>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($tickets as $T) {
    echo '<tr>';
    if ($canManageTickets) { ?>
        <td><input type="checkbox" class="ckb" name="tids[]"
            value="<?php echo $T['ticket_id']; ?>" /></td>
<?php
    }
    // Coderise: core's cells as is (all rendered first, so that the "From"
    // cell can be repeated under the subject), followed inside the same
    // cell by the contract chip under the number and the client under the
    // subject; the first-reply cell after the subject cell.
    $cdrTid = isset($T['ticket_id']) ? (int) $T['ticket_id'] : 0;
    $cdrGaugeTd = $cdrGauge ? '<td class="cdr-gauge-col">'
        . (($cdrTid && !empty($cdrGauges[$cdrTid])) ? $cdrGauges[$cdrTid] : $cdrNone)
        . '</td>' : '';
    if ($cdrLayout['gauge'] < 0)
        echo $cdrGaugeTd;
    $cdrCells = array();
    foreach ($columns as $C)
        $cdrCells[] = $C->render($T);
    $cdrClient = '';
    if ($cdrStacked && $cdrLayout['user'] !== null && isset($cdrCells[$cdrLayout['user']]))
        $cdrClient = cdr_queue_client_html((string) $cdrCells[$cdrLayout['user']][0],
            $cdrOrgs[$cdrTid] ?? null);
    elseif (!empty($cdrClients[$cdrTid]))
        $cdrClient = $cdrClients[$cdrTid];
    foreach ($cdrCells as $cdrI => $cdrCell) {
        list($contents, $styles) = $cdrCell;
        $cdrAfter = '';
        if ($cdrI === $cdrLayout['number'] && !empty($cdrPlans[$cdrTid]))
            $cdrAfter .= '<span class="stack">' . $cdrPlans[$cdrTid] . '</span>';
        if ($cdrI === $cdrLayout['subject'])
            $cdrAfter .= $cdrClient;
        $cdrTd = ($cdrStacked && $cdrI === $cdrLayout['user']) ? ' class="cdr-col-from"' : '';
        if ($style = $styles ? 'style="'.$styles.'"' : '') {
            echo "<td$cdrTd $style><div $style>$contents</div>$cdrAfter</td>";
        }
        else {
            echo "<td$cdrTd>$contents$cdrAfter</td>";
        }
        if ($cdrI === $cdrLayout['gauge'])
            echo $cdrGaugeTd;
    }
    echo '</tr>';
}
?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="<?php echo count($columns)+1+($cdrGauge ? 1 : 0); ?>">
        <?php if ($count && $canManageTickets) {
        echo __('Select');?>:&nbsp;
        <a id="selectAll" href="#ckb"><?php echo __('All');?></a>&nbsp;&nbsp;
        <a id="selectNone" href="#ckb"><?php echo __('None');?></a>&nbsp;&nbsp;
        <a id="selectToggle" href="#ckb"><?php echo __('Toggle');?></a>&nbsp;&nbsp;
        <?php }else{
            echo '<i>';
            echo $ferror?Format::htmlchars($ferror):__('Query returned 0 results.');
            echo '</i>';
        } ?>
      </td>
    </tr>
  </tfoot>
</table>

<?php
    if ($count > 0 || $skipCount) { //if we actually had any tickets returned.
?>  <div>
      <span class="faded pull-right"><?php echo $pageNav->showing(); ?></span>
<?php
        echo __('Page').':'.$pageNav->getPageLinks().'&nbsp;';
        ?>
        <a href="#tickets/export/<?php echo $queue->getId(); ?>"
        id="queue-export" class="no-pjax export"
            ><?php echo __('Export'); ?></a>
        <i class="help-tip icon-question-sign" href="#export"></i>
    </div>
<?php
    } ?>
</form>
