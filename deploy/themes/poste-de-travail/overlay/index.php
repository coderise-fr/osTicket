<?php
/*********************************************************************
    index.php

    Helpdesk landing page. Please customize it to fit your needs.

    Theme "Poste de travail" (Coderise): dashboard layout. Same data as
    the core landing page (admin landing page content, knowledge base
    search, featured questions and categories, open / check status
    buttons), plus the signed-in client's own open tickets and counts, and
    their contract level when the optional plugin contrib/sla-first-response
    provides it (ptdt_contract()). Only data that osTicket really has is shown.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require('client.inc.php');

require_once INCLUDE_DIR . 'class.page.php';

$section = 'home';
require(CLIENTINC_DIR.'header.inc.php');

$kb_enabled = ($cfg && $cfg->isKnowledgebaseEnabled());
$can_open = ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired());
$member = ($thisclient && is_object($thisclient) && $thisclient->isValid()
    && !$thisclient->isGuest());
// Contract level of the signed-in client (optional plugin, null if unknown):
// "Your contract" panel and the contract column of the open tickets
$contract = $member ? ptdt_contract() : null;

if ($member) {
    $org_tickets = $thisclient->canSeeOrgTickets();
    $n_open = $thisclient->getNumOpenTickets($org_tickets);
    $n_closed = $thisclient->getNumClosedTickets($org_tickets);

    // Same visibility rules as include/client/tickets.inc.php
    $basic_filter = Ticket::objects()->filter(array('status__state' => 'open'));
    $visibility = $basic_filter->copy()->values_flat('ticket_id')
        ->filter(array('user_id' => $thisclient->getId()));
    if ($cfg->collaboratorTicketsVisibility())
        $visibility = $visibility->union($basic_filter->copy()->values_flat('ticket_id')
            ->filter(array('thread__collaborators__user_id' => $thisclient->getId())), false);
    if ($org_tickets)
        $visibility = $visibility->union($basic_filter->copy()->values_flat('ticket_id')
            ->filter(array('user__org_id' => $thisclient->getOrgId())), false);

    TicketForm::ensureDynamicDataView();
    $subject_field = TicketForm::objects()->one()->getField('subject');
    $mine = Ticket::objects()
        ->filter(array('ticket_id__in' => $visibility))
        ->order_by('-isanswered', '-lastupdate')
        ->values('ticket_id', 'number', 'created', 'lastupdate', 'isanswered',
            'status_id', 'status__name', 'cdata__subject', 'dept_id', 'dept__name',
            'dept__ispublic', 'topic__topic', 'user_id')
        ->limit(8);
    $n_answered = Ticket::objects()
        ->filter(array('ticket_id__in' => $visibility, 'isanswered' => 1))
        ->count();
    // Most recently answered ticket: named in the welcome banner
    $first_answered = null;
    if ($n_answered) {
        foreach (Ticket::objects()
                ->filter(array('ticket_id__in' => $visibility, 'isanswered' => 1))
                ->order_by('-lastupdate')
                ->values('ticket_id', 'number', 'cdata__subject')->limit(1) as $T)
            $first_answered = $T;
    }
}
?>
<div id="landing_page" class="dash">
<div class="welcome on-dark">
    <div>
        <h1><?php
    if ($member)
        echo Format::htmlchars(sprintf(ptdt__("Hello %s, let's take your project up a level."),
            (string) ($thisclient->getName()->getFirst() ?: $thisclient->getName())));
    else
        echo __('Welcome to the Support Center'); ?></h1>
        <p><?php
    if ($member && $first_answered) {
        echo Format::htmlchars(ptdt__('The team answered, your reply is awaited:')); ?>
            <a href="tickets.php?id=<?php echo $first_answered['ticket_id']; ?>">#<?php
            echo $first_answered['number']; ?>, <?php echo $subject_field->display(
                $subject_field->to_php($first_answered['cdata__subject']) ?: $first_answered['cdata__subject']); ?></a><?php
    } elseif ($member) {
        echo Format::htmlchars(ptdt__('No ticket is waiting for your reply.'));
    } else {
        echo Format::htmlchars(ptdt__('Open a ticket, follow it to the end, find answers in the knowledge base.'));
    } ?></p>
    </div>
    <div class="welcome-actions">
<?php if ($can_open) { ?>
        <a class="btn btn-primary" href="open.php"><?php echo ptdt_icon('plus'); ?><span><?php echo __('Open a New Ticket'); ?></span></a>
<?php } ?>
<?php if (!$member) { ?>
        <a class="btn btn-line-dark" href="view.php"><?php echo ptdt_icon('status'); ?><span><?php echo __('Check Ticket Status'); ?></span></a>
<?php } ?>
    </div>
</div>
<?php if ($member) { ?>
<dl class="stats">
    <div><dt><?php echo __('Open Tickets'); ?></dt><dd><a href="tickets.php?a=search&amp;status=open"><?php echo (int) $n_open; ?></a></dd></div>
    <div class="<?php if ($n_answered) echo 'hot'; ?>"><dt><?php echo ptdt__('Awaiting your reply'); ?></dt><dd><?php echo (int) $n_answered; ?></dd></div>
    <div><dt><?php echo __('Closed Tickets'); ?></dt><dd><a href="tickets.php?a=search&amp;status=closed"><?php echo (int) $n_closed; ?></a></dd></div>
</dl>
<?php } ?>
<div class="cols">
<div class="col-main">
<?php if ($member) { ?>
    <section class="panel" aria-labelledby="dash-open">
        <div class="panel-h"><h2 id="dash-open"><?php echo __('Open Tickets'); ?></h2>
            <a class="more" href="tickets.php?a=search&amp;status=open"><?php echo ptdt__('View all'); ?></a></div>
<?php
    $defaultDept = Dept::getDefaultDeptName();
    if ($mine->exists(true)) { ?>
        <table class="data" id="dashTickets">
            <caption class="visually-hidden"><?php echo __('Open Tickets'); ?></caption>
            <thead><tr>
                <th scope="col" class="c-n"><?php echo __('Ticket #'); ?></th>
                <th scope="col"><?php echo __('Subject'); ?></th>
<?php if ($contract) { ?>
                <th scope="col" class="c-plan"><?php echo ptdt__('Contract'); ?></th>
<?php } ?>
                <th scope="col"><?php echo __('Status'); ?></th>
                <th scope="col" class="c-date"><?php echo __('Last Updated'); ?></th>
            </tr></thead>
            <tbody>
<?php   foreach ($mine as $T) {
            $dept = $T['dept__ispublic']
                ? Dept::getLocalById($T['dept_id'], 'name', $T['dept__name'])
                : $defaultDept;
            $subject = $subject_field->display(
                $subject_field->to_php($T['cdata__subject']) ?: $T['cdata__subject']);
            $status = TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']); ?>
                <tr class="<?php if ($T['isanswered']) echo 'answered'; ?>">
                    <td class="c-n" data-label="<?php echo __('Ticket #'); ?>"><a href="tickets.php?id=<?php echo $T['ticket_id']; ?>">#<?php echo $T['number']; ?></a></td>
                    <td class="c-subj"><a href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php echo $subject; ?></a>
                        <small><?php
                        echo Format::htmlchars($T['topic__topic'] ?: $dept); ?></small><?php
                        if ($T['isanswered']) { ?><span class="await"><?php echo ptdt_icon('message', 14); ?><?php
                            echo ptdt__('Awaiting your reply'); ?></span><?php } ?></td>
<?php       if ($contract) { ?>
                    <td class="c-plan" data-label="<?php echo ptdt__('Contract'); ?>"><?php
                        echo ptdt_contract_pill(ptdt_contract_of_row($T)); ?></td>
<?php       } ?>
                    <td data-label="<?php echo __('Status'); ?>"><span class="status status-open"><?php echo ptdt_icon('open', 14); ?><?php echo $status; ?></span></td>
                    <td class="c-date" data-label="<?php echo __('Last Updated'); ?>"><?php echo Format::datetime($T['lastupdate'] ?: $T['created']); ?></td>
                </tr>
<?php   } ?>
            </tbody>
        </table>
<?php
    } else { ?>
        <div class="empty">
            <span class="sommet empty-mark" aria-hidden="true"></span>
            <h3><?php echo ptdt__('You have no open tickets.'); ?></h3>
            <p><?php echo ptdt__('Something is blocking? Tell us in a ticket.'); ?></p>
<?php   if ($can_open) { ?>
            <a class="btn btn-primary" href="open.php"><?php echo ptdt_icon('plus'); ?><span><?php echo __('Open a New Ticket'); ?></span></a>
<?php   } ?>
        </div>
<?php
    } ?>
    </section>
<?php } else { ?>
    <section class="panel" aria-label="<?php echo __('Support Center'); ?>">
        <div class="pad thread-body landing">
<?php
    if($cfg && ($page = $cfg->getLandingPage()))
        echo $page->getBodyWithImages();
    else
        echo '<h2>'.__('Welcome to the Support Center').'</h2>';
?>
        </div>
    </section>
<?php } ?>
<?php
if ($kb_enabled
    && ($faqs = FAQ::getFeatured()->select_related('category')->limit(6))
    && $faqs->all()) { ?>
    <section class="panel" aria-labelledby="dash-faq">
        <div class="panel-h"><h2 id="dash-faq"><?php echo __('Featured Questions'); ?></h2>
            <a class="more" href="kb/index.php"><?php echo __('Knowledgebase'); ?></a></div>
        <ul class="kb-rows">
<?php   foreach ($faqs as $F) {
            $C = $F->getCategory(); ?>
            <li><a href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php echo urlencode($F->getId());
                ?>"><?php echo ptdt_icon('doc'); ?><span><?php echo $F->getLocalQuestion(); ?></span><?php
                if ($C) { ?><small><?php echo Format::htmlchars($C->getLocalName()); ?></small><?php } ?></a></li>
<?php   } ?>
        </ul>
    </section>
<?php
}
if ($kb_enabled && ($cats = Category::getFeatured()) && $cats->all()) { ?>
    <section class="panel featured" aria-labelledby="dash-kb">
        <div class="panel-h"><h2 id="dash-kb"><?php echo __('Featured Knowledge Base Articles'); ?></h2>
            <a class="more" href="kb/index.php"><?php echo ptdt__('Browse all'); ?></a></div>
<?php   foreach ($cats as $C) { ?>
        <div class="featured-category front-page">
            <h3 class="category-name"><?php echo ptdt_icon('folder'); ?><a href="kb/faq.php?cid=<?php
                echo $C->getId(); ?>"><?php echo $C->getName(); ?></a></h3>
<?php       foreach ($C->getTopArticles() as $F) { ?>
            <div class="article-headline">
                <div class="article-title"><a href="<?php echo ROOT_PATH;
                    ?>kb/faq.php?id=<?php echo $F->getId(); ?>"><?php
                    echo $F->getQuestion(); ?></a></div>
                <div class="article-teaser"><?php echo $F->getTeaser(); ?></div>
            </div>
<?php       } ?>
        </div>
<?php   } ?>
    </section>
<?php
} ?>
</div>
<div class="col-side">
<?php if ($contract) {
    // "Your contract": real data only (level, label, organisation of a
    // standard client). The Priority organisation itself is not named: it is
    // the technical group of all Priority clients, not the client's company.
    $priority = ($contract['level'] == 'priority'); ?>
    <section class="panel contract" aria-labelledby="dash-contract">
        <div class="panel-h"><h2 id="dash-contract"><?php echo ptdt__('Your contract'); ?></h2></div>
        <dl class="props">
            <div><dt><?php echo ptdt__('Level'); ?></dt><dd><?php echo ptdt_contract_pill($contract); ?></dd></div>
            <div><dt><?php echo ptdt__('Response times'); ?></dt><dd><?php
                echo $priority ? ptdt__('First response within 4 h, 24 h a day, 7 days a week')
                    : ptdt__('Business days'); ?></dd></div>
<?php if (!$priority && $contract['org_name'] !== null && $contract['org_name'] !== '') { ?>
            <div><dt><?php echo __('Organization'); ?></dt><dd><?php echo Format::htmlchars($contract['org_name']); ?></dd></div>
<?php } ?>
        </dl>
    </section>
<?php } ?>
<?php if ($kb_enabled) { ?>
    <section class="panel" aria-labelledby="dash-search">
        <div class="panel-h"><h2 id="dash-search"><?php echo __('Knowledgebase'); ?></h2></div>
        <form class="pad" role="search" method="get" action="kb/faq.php">
            <input type="hidden" name="a" value="search"/>
            <label class="visually-hidden" for="dash-q"><?php echo __('Search our knowledge base'); ?></label>
            <div class="inline">
                <input type="search" id="dash-q" name="q" class="search" placeholder="<?php echo __('Search our knowledge base'); ?>"/>
                <button type="submit" class="btn btn-line"><?php echo __('Search'); ?></button>
            </div>
        </form>
    </section>
<?php } ?>
    <section class="panel" aria-labelledby="dash-urgent">
        <div class="panel-h"><h2 id="dash-urgent"><?php echo ptdt__('A blocking incident?'); ?></h2></div>
        <div class="pad">
            <p><?php echo ptdt__('Open a ticket, then call us: a person answers.'); ?></p>
            <a class="btn btn-line" href="tel:<?php echo Format::htmlchars(PTDT_CONTACT_PHONE_URI); ?>"><?php
                echo ptdt_icon('phone'); ?><span><?php echo Format::htmlchars(PTDT_CONTACT_PHONE); ?></span></a>
        </div>
    </section>
<?php if ($member) { ?>
    <section class="panel" aria-label="<?php echo __('Support Center'); ?>">
        <div class="pad thread-body landing">
<?php
    if($cfg && ($page = $cfg->getLandingPage()))
        echo $page->getBodyWithImages();
    else
        echo '<h2>'.__('Welcome to the Support Center').'</h2>';
?>
        </div>
    </section>
<?php } ?>
</div>
</div>
</div>

<?php require(CLIENTINC_DIR.'footer.inc.php'); ?>
