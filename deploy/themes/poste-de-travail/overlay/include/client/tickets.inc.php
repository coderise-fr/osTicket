<?php
if(!defined('OSTCLIENTINC') || !is_object($thisclient) || !$thisclient->isValid()) die('Access Denied');

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
    'topic__topic' // theme: help topic shown under the subject
);

?>
<?php
/* Theme "Poste de travail" (Coderise): markup below this line replaces the
 * core list (same form, field names, filters, sort links and pagination).
 * The only change above is 'topic__topic' in values() (help topic shown
 * under the subject). */
$ptdt_sort = function($key, $label, $title, $class='') use ($sort, $negorder, $qstr, $order) {
    $active = ($sort == $key);
    $dir = $order == '-' ? 'descending' : 'ascending';
    return sprintf('<th scope="col"%s%s><a href="tickets.php?sort=%s&order=%s%s" title="%s" class="%s">%s %s</a></th>',
        $class ? ' class="'.$class.'"' : '',
        $active ? ' aria-sort="'.$dir.'"' : '',
        $key == 'id' ? 'ID' : $key, $negorder, $qstr,
        Format::htmlchars(sprintf('%s %s', __('Sort By'), $title)),
        $active ? 'on' : '',
        $label,
        ptdt_icon($active ? ($order == '-' ? 'down' : 'up') : 'sort', 13));
};
?>
<div class="title-row">
    <div>
        <h1><a href="<?php echo Http::refresh_url(); ?>" title="<?php echo __('Reload'); ?>"><?php echo __('Tickets'); ?></a></h1>
<?php if ($openTickets >= 0 && $closedTickets >= 0) { ?>
        <p class="sub"><?php echo Format::htmlchars(sprintf(ptdt__('%1$d open, %2$d closed'), $openTickets, $closedTickets)); ?></p>
<?php } ?>
    </div>
<?php if ($cfg->getClientRegistrationMode() != 'disabled' || !$cfg->isClientLoginRequired()) { ?>
    <div class="t-actions">
        <a class="btn btn-primary" href="open.php"><?php echo ptdt_icon('plus'); ?><span><?php echo __('Open a New Ticket'); ?></span></a>
    </div>
<?php } ?>
</div>
<div class="panel">
<div class="search filters">
    <div class="seg states" role="group" aria-label="<?php echo __('Status'); ?>">
        <a class="state <?php if ($status == 'open') echo 'active'; ?>"<?php if ($status == 'open') echo ' aria-current="true"'; ?>
            href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'open')); ?>"><?php
            echo __('Open'); ?><?php if ($openTickets >= 0) echo ' <b class="count">'.(int) $openTickets.'</b>'; ?></a>
        <a class="state <?php if ($status == 'closed') echo 'active'; ?>"<?php if ($status == 'closed') echo ' aria-current="true"'; ?>
            href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'closed')); ?>"><?php
            echo __('Closed'); ?><?php if ($closedTickets >= 0) echo ' <b class="count">'.(int) $closedTickets.'</b>'; ?></a>
    </div>
    <form action="tickets.php" method="get" id="ticketSearchForm" role="search">
        <input type="hidden" name="a" value="search">
        <div class="fsearch">
            <?php echo ptdt_icon('search', 16); ?>
            <label class="visually-hidden" for="ticket-keywords"><?php echo __('Search'); ?></label>
            <input type="search" id="ticket-keywords" name="keywords" size="30" placeholder="<?php echo ptdt__('Ticket number or subject'); ?>" value="<?php echo Format::htmlchars($settings['keywords']); ?>">
        </div>
        <input type="submit" class="btn btn-line" value="<?php echo __('Search');?>">
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
<?php if ($settings['keywords'] || $settings['topic_id'] || $_REQUEST['sort']) { ?>
        <a class="reset" href="?clear"><?php echo __('Clear all filters and sort'); ?></a>
<?php } ?>
    </form>
</div>
<table id="ticketTable" class="data">
    <caption class="visually-hidden"><?php echo $showing; ?></caption>
    <thead>
        <tr>
            <?php echo $ptdt_sort('id', __('Ticket #'), __('Ticket ID'), 'c-n'); ?>
            <?php echo $ptdt_sort('subject', __('Subject'), __('Subject')); ?>
            <?php echo $ptdt_sort('status', __('Status'), __('Status')); ?>
            <?php echo $ptdt_sort('dept', __('Department'), __('Department')); ?>
            <?php echo $ptdt_sort('date', __('Create Date'), __('Date'), 'c-date'); ?>
        </tr>
    </thead>
    <tbody>
    <?php
     $subject_field = TicketForm::objects()->one()->getField('subject');
     $defaultDept=Dept::getDefaultDeptName(); //Default public dept.
     if ($tickets->exists(true)) {
         foreach ($tickets as $T) {
            $dept = $T['dept__ispublic']
                ? Dept::getLocalById($T['dept_id'], 'name', $T['dept__name'])
                : $defaultDept;
            $subject = $subject_field->display(
                $subject_field->to_php($T['cdata__subject']) ?: $T['cdata__subject']
            );
            $status = TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']);
            $ptdt_open = !strcasecmp($T['status__state'], 'open');
            $answered = ($T['isanswered'] && $ptdt_open);
            $isCollab = ($thisclient->getId() != $T['user_id']);
            ?>
            <tr id="<?php echo $T['ticket_id']; ?>" class="<?php if ($answered) echo 'answered'; if (!$ptdt_open) echo ' is-closed'; ?>">
                <td class="c-n" data-label="<?php echo __('Ticket #'); ?>">
                <a class="Icon <?php echo strtolower($T['source']); ?>Ticket" title="<?php echo Format::htmlchars($T['user__default_email__address']); ?>"
                    href="tickets.php?id=<?php echo $T['ticket_id']; ?>">#<?php echo $T['number']; ?></a>
                </td>
                <td class="c-subj">
                    <a href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php
                    if ($isCollab) { ?><span class="collab" title="<?php echo __('Collaborator'); ?>"><?php
                        echo ptdt_icon('group', 15); ?><span class="visually-hidden"><?php echo __('Collaborator'); ?>: </span></span><?php }
                    echo $subject; ?></a>
<?php           if ($T['topic__topic']) { ?>
                    <small><?php echo Format::htmlchars($T['topic__topic']); ?></small>
<?php           }
                if ($answered) { ?>
                    <span class="await"><?php echo ptdt_icon('message', 14); ?><?php echo ptdt__('Awaiting your reply'); ?></span>
<?php           } ?>
                </td>
                <td data-label="<?php echo __('Status'); ?>"><span class="status status-<?php echo $ptdt_open ? 'open' : 'closed'; ?>"><?php
                    echo ptdt_icon($ptdt_open ? 'open' : 'closed', 14); ?><?php echo $status; ?></span></td>
                <td data-label="<?php echo __('Department'); ?>"><?php echo Format::htmlchars($dept); ?></td>
                <td class="c-date" data-label="<?php echo __('Create Date'); ?>"><?php echo Format::date($T['created']); ?></td>
            </tr>
        <?php
        }

     } else { ?>
            <tr class="empty-row"><td colspan="5">
                <div class="empty">
                    <span class="sommet empty-mark" aria-hidden="true"></span>
                    <h2><?php echo __('Your query did not match any records'); ?></h2>
<?php       if ($status == 'open' && !$settings['keywords'] && !$settings['topic_id']) { ?>
                    <p><?php echo ptdt__('No open ticket. All good.'); ?></p>
<?php       } ?>
                </div>
            </td></tr>
<?php
     }
    ?>
    </tbody>
</table>
<?php
if ($total) { ?>
<div class="tfoot">
    <span><?php echo $showing; ?></span>
    <nav class="pager" aria-label="<?php echo __('Page'); ?>"><?php
        echo preg_replace('#<b>\[(\d+)\]</b>#', '<b aria-current="page">$1</b>', $pageNav->getPageLinks()); ?></nav>
</div>
<?php
} ?>
</div>
