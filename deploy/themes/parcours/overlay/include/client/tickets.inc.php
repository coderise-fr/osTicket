<?php
if(!defined('OSTCLIENTINC') || !is_object($thisclient) || !$thisclient->isValid()) die('Access Denied');
/* Theme "Parcours" (Coderise) - overrides include/client/tickets.inc.php.
 * Same search, filters, counts, visibility (own, collaborator, organisation),
 * sorting and pagination as the core template (PHP logic copied unchanged).
 * Display: tickets as rows grouped by "your turn" (open, answered by the
 * team: isanswered) and "with the team" (open, not answered). Grouping is
 * used only for the default view of open tickets (no sort chosen, no
 * search): the list is then ordered by isanswered, then by creation date,
 * so that the groups hold across pages. Any explicit sort shows a flat
 * list in the chosen order. */

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

// Theme: default view of open tickets grouped by "your turn" / "with the team"
$pcrs_grouped = ($status == 'open' && !$_REQUEST['sort'] && !$settings['keywords']);
if ($pcrs_grouped)
    $tickets->order_by('-isanswered', '-created');
else
    $tickets->order_by($order.$order_by);
$tickets->values(
    'ticket_id', 'number', 'created', 'isanswered', 'source', 'status_id',
    'status__state', 'status__name', 'cdata__subject', 'dept_id',
    'dept__name', 'dept__ispublic', 'user__default_email__address', 'user_id'
);

// Real count for the band: own open tickets answered by the team
$pcrs_turn = Ticket::objects()->filter(array('user_id' => $thisclient->getId(),
    'status__state' => 'open', 'isanswered' => 1))->count();
$pcrs_filtered = ($settings['keywords'] || $settings['topic_id'] || $_REQUEST['sort']);
$pcrs_sorts = array('id' => __('Ticket #'), 'date' => __('Create Date'), 'status' => __('Status'),
    'subject' => __('Subject'), 'dept' => __('Department'));
?>
<section class="top">
<?php echo pcrs_crumbs(array(array(pcrs__('Home'), ROOT_PATH . 'index.php'), array(__('Tickets'), null))); ?>
  <h1><a href="<?php echo Http::refresh_url(); ?>" title="<?php echo Format::htmlchars(__('Reload')); ?>"><?php echo __('Tickets'); ?></a></h1>
  <p class="sub"><?php
    if ($pcrs_turn == 0)
        echo pcrs__('No ticket awaits your reply.');
    elseif ($pcrs_turn == 1)
        echo pcrs__('One ticket awaits your reply.');
    else
        echo sprintf(pcrs__('%d tickets await your reply.'), $pcrs_turn); ?></p>
</section>

<div class="list-tools">
  <ul class="tabs" aria-label="<?php echo Format::htmlchars(__('Status')); ?>">
<?php if ($openTickets) { ?>
    <li><a href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'open')); ?>"<?php
        if ($status == 'open') echo ' aria-current="page"'; ?>><?php echo __('Open');
        if ($openTickets > 0) echo ' <span class="count">'.(int) $openTickets.'</span>'; ?></a></li>
<?php }
if ($closedTickets) { ?>
    <li><a href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'closed')); ?>"<?php
        if ($status == 'closed') echo ' aria-current="page"'; ?>><?php echo __('Closed');
        if ($closedTickets > 0) echo ' <span class="count">'.(int) $closedTickets.'</span>'; ?></a></li>
<?php } ?>
  </ul>
  <form action="tickets.php" method="get" id="ticketSearchForm" class="search-row" role="search">
    <input type="hidden" name="a"  value="search">
    <div class="field">
      <label for="ticket-q"><?php echo __('Search'); ?></label>
      <input id="ticket-q" type="search" name="keywords" size="30" value="<?php echo Format::htmlchars($settings['keywords']); ?>">
    </div>
    <div class="field">
      <label for="ticket-topic"><?php echo __('Help Topic'); ?></label>
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
    </div>
    <button class="btn btn-line" type="submit"><?php echo pcrs_icon('search'); echo __('Search');?></button>
  </form>
</div>
<p class="sorter"><span><?php echo __('Sort By'); ?>:</span>
<?php foreach ($pcrs_sorts as $k => $label) {
    $is = ($_REQUEST['sort'] && $sort == $k); ?>
  <a href="tickets.php?sort=<?php echo $k == 'id' ? 'ID' : $k; ?>&order=<?php echo $is ? $negorder : 'DESC'; ?><?php echo $qstr; ?>"<?php
    if ($is) echo ' aria-current="true"'; ?>><?php echo $label; if ($is) echo $order == '-' ? ' ↓' : ' ↑'; ?></a>
<?php } ?>
<?php if ($pcrs_filtered) { ?>
  <a href="?clear"><?php echo pcrs_icon('x', 16); echo __('Clear all filters and sort'); ?></a>
<?php } ?>
</p>

<div id="ticketTable">
<?php
     $subject_field = TicketForm::objects()->one()->getField('subject');
     $defaultDept=Dept::getDefaultDeptName(); //Default public dept.
     if ($tickets->exists(true)) {
         $pcrs_group = null;
         foreach ($tickets as $T) {
            $dept = $T['dept__ispublic']
                ? Dept::getLocalById($T['dept_id'], 'name', $T['dept__name'])
                : $defaultDept;
            $subject = $subject_field->display(
                $subject_field->to_php($T['cdata__subject']) ?: $T['cdata__subject']
            );
            $pcrs_status = TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']);
            $pcrs_open = !strcasecmp($T['status__state'], 'open');
            $pcrs_turn_row = ($T['isanswered'] && $pcrs_open);
            $isCollab = ($thisclient->getId() != $T['user_id']);
            // Group heading
            $g = $pcrs_grouped ? ($pcrs_turn_row ? 'turn' : 'team') : 'all';
            if ($g !== $pcrs_group) {
                if ($pcrs_group !== null) echo "</ul></section>\n";
                $pcrs_group = $g; ?>
  <section class="group <?php echo $g; ?>">
    <h2><?php
                if ($g == 'turn') echo pcrs__('Your turn');
                elseif ($g == 'team') echo pcrs__('With the team');
                else echo Format::htmlchars($showing); ?></h2>
    <ul class="tlist">
<?php       }
            list($pcrs_k) = pcrs_next_step($T['status__state'], $T['isanswered']); ?>
      <li id="<?php echo $T['ticket_id']; ?>"><a class="trow" href="tickets.php?id=<?php echo $T['ticket_id']; ?>">
        <span>
          <span class="subject"><?php if ($isCollab) echo pcrs_icon('group', 16) . '<span class="sr-only">' . Format::htmlchars(pcrs__('Shared with you')) . '</span> '; echo $subject; ?></span>
          <span class="meta">#<?php echo $T['number']; ?> · <?php echo $dept; ?> · <?php
            echo sprintf(pcrs__('opened %s'), Format::date($T['created'])); ?></span>
        </span>
        <span class="tstate">
          <span class="status <?php echo $pcrs_open ? 'status-open' : 'status-closed'; ?>"><?php
            echo pcrs_icon($pcrs_open ? 'inbox' : 'lock', 14); echo $pcrs_status; ?></span>
<?php       if ($pcrs_open) { ?>
          <span class="step-tag <?php echo $pcrs_k; ?>"><?php
            if ($pcrs_k == 'you') echo pcrs_icon('reply', 14);
            echo pcrs_next_label($T['status__state'], $T['isanswered']); ?></span>
<?php       } ?>
        </span>
      </a></li>
<?php
        }
        echo "</ul></section>\n";
     } elseif ($pcrs_filtered) {
         echo '<p class="empty-msg">'.__('Your query did not match any records').'</p>';
     } else { ?>
  <div class="empty">
    <?php echo pcrs_sommet('e-mark'); ?>
    <h2><?php echo $status == 'closed' ? pcrs__('No closed tickets yet.') : pcrs__('No open tickets. All good.'); ?></h2>
    <p><?php echo pcrs__('A question, an incident, an idea? Open a ticket: we guide you in four steps.'); ?></p>
    <p class="actions"><a class="btn btn-primary" href="open.php"><?php echo pcrs_icon('plus'); echo __('Open a New Ticket'); ?></a>
<?php if ($cfg->isKnowledgebaseEnabled()) { ?>
      <a class="btn btn-line" href="kb/index.php"><?php echo __('Knowledgebase'); ?></a>
<?php } ?></p>
  </div>
<?php
     } ?>
</div>
<?php
if ($total) {
    $pcrs_links = $pageNav->getPageLinks();
    $pcrs_links = preg_replace('#<b>\[(\d+)\]</b>#', '<span class="cur" aria-current="page">$1</span>', $pcrs_links);
    $pcrs_links = preg_replace('#<a ([^>]*)><b>(\d+)</b></a>#', '<a $1>$2</a>', $pcrs_links); ?>
<nav class="pager-row" aria-label="<?php echo Format::htmlchars(__('Page')); ?>">
  <span><?php echo $showing; ?></span>
  <span class="pages"><?php echo __('Page'); ?>: <?php echo $pcrs_links; ?></span>
</nav>
<?php
}
?>
