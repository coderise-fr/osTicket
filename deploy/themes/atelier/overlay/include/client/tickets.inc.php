<?php
if(!defined('OSTCLIENTINC') || !is_object($thisclient) || !$thisclient->isValid()) die('Access Denied');
/* Atelier theme (deploy/themes/atelier): replaces include/client/tickets.inc.php.
 * The query logic (filters, visibility, search, sort, pagination) is the
 * original one, verbatim; only the rendering changes: tickets become cards
 * (same links, same data) and the column sort becomes a sort bar. */

$settings = &$_SESSION['client:Q'];

// Unpack search, filter, and sort requests
if (isset($_REQUEST['clear']))
    $settings = array();
if (isset($_REQUEST['keywords'])) {
    $settings['keywords'] = $_REQUEST['keywords'];
}
if (isset($_REQUEST['topic_id'])) {
    $settings['topic_id'] = $_REQUEST['topic_id'];
}
if (isset($_REQUEST['status'])) {
    $settings['status'] = $_REQUEST['status'];
}

$org_tickets = $thisclient->canSeeOrgTickets();
if ($settings['keywords']) {
    // Don't show stat counts for searches
    $openTickets = $closedTickets = -1;
}
elseif ($settings['topic_id']) {
    $openTickets = $thisclient->getNumTopicTicketsInState($settings['topic_id'],
        'open', $org_tickets);
    $closedTickets = $thisclient->getNumTopicTicketsInState($settings['topic_id'],
        'closed', $org_tickets);
}
else {
    $openTickets = $thisclient->getNumOpenTickets($org_tickets);
    $closedTickets = $thisclient->getNumClosedTickets($org_tickets);
}

$tickets = Ticket::objects();

$qs = array();
$status=null;

$sortOptions=array('id'=>'number', 'subject'=>'cdata__subject',
                    'status'=>'status__name', 'dept'=>'dept__name','date'=>'created');
$orderWays=array('DESC'=>'-','ASC'=>'');
//Sorting options...
$order_by=$order=null;
$sort=($_REQUEST['sort'] && $sortOptions[strtolower($_REQUEST['sort'])])?strtolower($_REQUEST['sort']):'date';
if($sort && $sortOptions[$sort])
    $order_by =$sortOptions[$sort];

$order_by=$order_by ?: $sortOptions['date'];
if ($_REQUEST['order'] && !is_null($orderWays[strtoupper($_REQUEST['order'])]))
    $order = $orderWays[strtoupper($_REQUEST['order'])];
else
    $order = $orderWays['DESC'];

$x=$sort.'_sort';
$$x=' class="'.strtolower($_REQUEST['order'] ?: 'desc').'" ';

$basic_filter = Ticket::objects();
if ($settings['topic_id']) {
    $basic_filter = $basic_filter->filter(array('topic_id' => $settings['topic_id']));
}

if ($settings['status'])
    $status = strtolower($settings['status']);
    switch ($status) {
    default:
        $status = 'open';
    case 'open':
    case 'closed':
		$results_type = ($status == 'closed') ? __('Closed Tickets') : __('Open Tickets');
        $basic_filter->filter(array('status__state' => $status));
        break;
}

// Add visibility constraints — use a union query to use multiple indexes,
// use UNION without "ALL" (false as second parameter to union()) to imply
// unique values
$visibility = $basic_filter->copy()
    ->values_flat('ticket_id')
    ->filter(array('user_id' => $thisclient->getId()));

// Add visibility of Tickets where the User is a Collaborator if enabled
if ($cfg->collaboratorTicketsVisibility())
    $visibility = $visibility
    ->union($basic_filter->copy()
        ->values_flat('ticket_id')
        ->filter(array('thread__collaborators__user_id' => $thisclient->getId()))
    , false);

if ($thisclient->canSeeOrgTickets()) {
    $visibility = $visibility->union(
        $basic_filter->copy()->values_flat('ticket_id')
            ->filter(array('user__org_id' => $thisclient->getOrgId()))
    , false);
}

// Perform basic search
if ($settings['keywords']) {
    $q = trim($settings['keywords']);
    if (is_numeric($q)) {
        $tickets->filter(array('number__startswith'=>$q));
    } elseif (strlen($q) > 2) { //Deep search!
        // Use the search engine to perform the search
        $tickets = $ost->searcher->find($q, $tickets, true, ['boolean' => false]);
    }
}

$tickets->distinct('ticket_id');

TicketForm::ensureDynamicDataView();

$total=$visibility->count();
$page=($_GET['p'] && is_numeric($_GET['p']))?$_GET['p']:1;
$pageNav=new Pagenate($total, $page, PAGE_LIMIT);
$qstr = '&amp;'. Http::build_query($qs);
$qs += array('sort' => $_REQUEST['sort'], 'order' => $_REQUEST['order']);
$pageNav->setURL('tickets.php', $qs);
$tickets->filter(array('ticket_id__in' => $visibility));
$pageNav->paginate($tickets);

$showing =$total ? $pageNav->showing() : "";
if(!$results_type)
{
	$results_type=ucfirst($status).' '.__('Tickets');
}
$showing.=($status)?(' '.$results_type):' '.__('All Tickets');
if($search)
    $showing=__('Search Results').": $showing";

$negorder=$order=='-'?'ASC':'DESC'; //Negate the sorting

$tickets->order_by($order.$order_by);
$tickets->values(
    'ticket_id', 'number', 'created', 'isanswered', 'source', 'status_id',
    'status__state', 'status__name', 'cdata__subject', 'dept_id',
    'dept__name', 'dept__ispublic', 'user__default_email__address', 'user_id',
    // Atelier theme: plan name (Priority badge) and close date.
    'sla__name', 'closed'
);

// ---- Atelier theme: everything above is the original query logic, verbatim
// (except the two extra columns of values() marked above).
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');
$atelier_sorts = array(
    'id' => array(__('Ticket #'), __('Ticket ID')),
    'date' => array(__('Create Date'), __('Date')),
    'status' => array(__('Status'), __('Status')),
    'subject' => array(__('Subject'), __('Subject')),
    'dept' => array(__('Department'), __('Department')),
);
?>
<div class="page-head band list-head">
    <h1 class="display"><a href="<?php echo Http::refresh_url(); ?>" title="<?php
        echo Format::htmlchars(__('Reload')); ?>"><?php echo __('Tickets'); ?></a></h1>
    <p class="sub" id="ticket-count"><?php echo $showing; ?></p>
</div>

<div class="toolbar search well">
<?php if ($openTickets || $closedTickets) { ?>
    <nav class="tabs states" aria-label="<?php echo Format::htmlchars(__('Ticket Status')); ?>">
<?php if ($openTickets) { ?>
        <a class="tab state <?php if ($status == 'open') echo 'active on'; ?>"
            <?php if ($status == 'open') echo 'aria-current="true"'; ?>
            href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'open')); ?>">
            <?php echo __('Open'); if ($openTickets > 0) { ?> <span class="count"><?php echo (int) $openTickets; ?></span><?php } ?>
        </a>
<?php }
if ($closedTickets) { ?>
        <a class="tab state <?php if ($status == 'closed') echo 'active on'; ?>"
            <?php if ($status == 'closed') echo 'aria-current="true"'; ?>
            href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'closed')); ?>">
            <?php echo __('Closed'); if ($closedTickets > 0) { ?> <span class="count"><?php echo (int) $closedTickets; ?></span><?php } ?>
        </a>
<?php } ?>
    </nav>
<?php } ?>
<form action="tickets.php" method="get" id="ticketSearchForm" class="tsearch-form" role="search">
    <input type="hidden" name="a"  value="search">
    <div class="tsearch">
        <?php echo atelier_icon('search'); ?>
        <label class="visually-hidden" for="ticket-keywords"><?php echo __('Search'); ?></label>
        <input id="ticket-keywords" type="search" name="keywords" size="30" value="<?php echo Format::htmlchars($settings['keywords']); ?>">
        <input class="btn btn-ghost btn-sm" type="submit" value="<?php echo __('Search');?>">
    </div>
    <label class="visually-hidden" for="ticket-topic"><?php echo __('Help Topic'); ?></label>
    <select id="ticket-topic" name="topic_id" class="nowarn" onchange="javascript: this.form.submit(); ">
        <option value="">&mdash; <?php echo __('All Help Topics');?> &mdash;</option>
<?php
foreach (Topic::getHelpTopics(true) as $id=>$name) {
        $count = $thisclient->getNumTopicTickets($id, $org_tickets);
        if ($count == 0)
            continue;
?>
        <option value="<?php echo $id; ?>"
            <?php if ($settings['topic_id'] == $id) echo 'selected="selected"'; ?>
            ><?php echo sprintf('%s (%d)', Format::htmlchars($name),
                $thisclient->getNumTopicTickets($id)); ?></option>
<?php } ?>
    </select>
</form>
</div>

<div class="sortbar">
    <span class="muted"><?php echo __('Sort By'); ?></span>
<?php foreach ($atelier_sorts as $atelier_key => $atelier_labels) {
    $atelier_on = ($sort == $atelier_key);
    $atelier_dir = $atelier_on ? $negorder : 'DESC'; ?>
    <a class="sort<?php if ($atelier_on) echo ' on'; ?>"
        href="tickets.php?sort=<?php echo $atelier_key; ?>&amp;order=<?php echo $atelier_dir; ?><?php echo $qstr; ?>"
        title="<?php echo Format::htmlchars(sprintf('%s %s', __('Sort By'), $atelier_labels[1])); ?>"<?php
        if ($atelier_on) echo ' aria-current="true"'; ?>><?php echo $atelier_labels[0]; ?><?php
        if ($atelier_on) { ?> <i class="icon-sort-<?php echo ($order == '-') ? 'down' : 'up'; ?>" aria-hidden="true"></i><?php } ?></a>
<?php } ?>
<?php if ($settings['keywords'] || $settings['topic_id'] || $_REQUEST['sort']) { ?>
    <a class="clear-filters" href="?clear"><i class="icon-remove-circle" aria-hidden="true"></i> <?php echo __('Clear all filters and sort'); ?></a>
<?php } ?>
</div>

<div id="ticketTable">
<?php
 $subject_field = TicketForm::objects()->one()->getField('subject');
 $defaultDept=Dept::getDefaultDeptName(); //Default public dept.
 if ($tickets->exists(true)) { ?>
    <ul class="tlist" aria-describedby="ticket-count">
<?php
     foreach ($tickets as $T) {
        $dept = $T['dept__ispublic']
            ? Dept::getLocalById($T['dept_id'], 'name', $T['dept__name'])
            : $defaultDept;
        $subject = $subject_field->display(
            $subject_field->to_php($T['cdata__subject']) ?: $T['cdata__subject']
        );
        $status = TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']);
        $ticketNumber=$T['number'];
        $is_open = !strcasecmp($T['status__state'], 'open');
        // Answered by the team and still open: a reply is waiting for the client.
        $has_reply = ($T['isanswered'] && $is_open);
        $isCollab = ($thisclient->getId() != $T['user_id']);
        ?>
        <li id="<?php echo $T['ticket_id']; ?>">
        <a class="tcard<?php if ($has_reply) echo ' unread'; if (!$is_open) echo ' is-closed'; ?>"
            href="tickets.php?id=<?php echo $T['ticket_id']; ?>"
            title="<?php echo Format::htmlchars($T['user__default_email__address']); ?>">
            <span class="t-ic <?php echo Format::htmlchars(strtolower($T['source'])); ?>Ticket" aria-hidden="true"><?php
                echo atelier_icon($is_open ? ($has_reply ? 'message' : 'inbox') : 'lock', 18); ?></span>
            <span class="t-main">
                <span class="t-subj"><?php if ($isCollab) { ?><i class="icon-group" aria-hidden="true"></i>
                    <span class="visually-hidden"><?php echo __('Collaborator'); ?>:</span> <?php } ?><?php echo $subject; ?></span>
                <span class="t-sub">
                    <span class="t-num">#<?php echo $ticketNumber; ?></span>
                    <span class="t-dept"><?php echo Format::htmlchars($dept); ?></span>
                    <span class="t-date"><span class="visually-hidden"><?php echo __('Create Date'); ?></span> <?php echo Format::date($T['created']); ?></span>
                </span>
            </span>
            <span class="t-side">
                <span class="t-badges">
                <span class="status status-<?php echo $is_open ? 'open' : 'closed'; ?>"><?php
                    echo atelier_icon($is_open ? 'inbox' : 'lock', 14); echo $status; ?></span>
<?php if ($T['sla__name'] && ATELIER_PRIORITY_PREFIX !== ''
        && stripos($T['sla__name'], ATELIER_PRIORITY_PREFIX) === 0) { ?>
                <span class="plan plan-priority"><?php echo atelier_icon('bolt', 13); ?>Priority</span>
<?php } ?>
                </span>
                <span class="t-last"><?php
                if (!$is_open && $T['closed'])
                    echo sprintf(__a('Closed on %s'), Format::date($T['closed']));
                elseif ($has_reply)
                    echo '<span class="pill pill-new">' . __a('New reply') . '</span>';
                elseif ($is_open)
                    echo __a('Waiting for our reply'); ?></span>
            </span>
            <span class="chev"><?php echo atelier_icon('chevr', 20); ?></span>
        </a>
        </li>
<?php
    } ?>
    </ul>
<?php
 } else { ?>
    <div class="card empty">
        <?php echo atelier_sommet('empty-mark'); ?>
        <h2><?php echo __('Your query did not match any records'); ?></h2>
<?php if ($cfg->getClientRegistrationMode() != 'disabled' || !$cfg->isClientLoginRequired()) { ?>
        <p class="actions"><a class="btn btn-primary" href="open.php"><?php echo atelier_icon('plus', 20); ?><?php echo __('Open a New Ticket'); ?></a></p>
<?php } ?>
    </div>
<?php
 } ?>
</div>
<?php
if ($total) {
    echo '<nav class="pager" aria-label="'.Format::htmlchars(__('Page')).'">'.__('Page').': '.$pageNav->getPageLinks().'</nav>';
}
?>
