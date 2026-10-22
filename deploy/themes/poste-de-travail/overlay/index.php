<?php
/*********************************************************************
    index.php

    Helpdesk landing page. Please customize it to fit your needs.

    Theme "Poste de travail" (Ownalytics): dashboard layout. Same data as
    the core landing page (admin landing page content, knowledge base
    search, featured questions and categories, open / check status
    buttons), plus the signed-in client's own open tickets and counts.

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
?>
<div id="landing_page" class="dash">
<div class="ph">
    <h1><?php
    if ($member)
        echo sprintf(ptdt__('Hello %s'), Format::htmlchars((string) ($thisclient->getName()->getFirst()
            ?: $thisclient->getName())));
    else
        echo __('Support Center'); ?></h1>
    <div class="ph-actions">
<?php if ($can_open) { ?>
        <a class="btn btn-primary" href="open.php"><?php echo ptdt_icon('plus', 15); ?> <?php echo __('Open a New Ticket'); ?></a>
<?php } ?>
<?php if ($member) { ?>
        <a class="btn" href="tickets.php"><?php echo __('Tickets'); ?></a>
<?php } else { ?>
        <a class="btn" href="view.php"><?php echo __('Check Ticket Status'); ?></a>
<?php } ?>
    </div>
</div>
<?php
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
?>
<dl class="kpis" aria-label="<?php echo __('Tickets'); ?>">
    <div><dt><?php echo __('Open Tickets'); ?></dt><dd><a href="tickets.php?a=search&amp;status=open"><?php echo (int) $n_open; ?></a></dd></div>
    <div class="<?php if ($n_answered) echo 'hot'; ?>"><dt><?php echo ptdt__('Answered, awaiting your reply'); ?></dt><dd><?php echo (int) $n_answered; ?></dd></div>
    <div><dt><?php echo __('Closed Tickets'); ?></dt><dd><a href="tickets.php?a=search&amp;status=closed"><?php echo (int) $n_closed; ?></a></dd></div>
</dl>
<?php
} ?>
<div class="cols">
<?php if ($member) { ?>
    <section class="panel tbl" aria-labelledby="dash-open">
        <div class="p-head"><h2 id="dash-open"><?php echo __('Open Tickets'); ?></h2>
            <a href="tickets.php?a=search&amp;status=open"><?php echo ptdt__('View all'); ?></a></div>
<?php
    $subject_field = TicketForm::objects()->one()->getField('subject');
    $defaultDept = Dept::getDefaultDeptName();
    if ($mine->exists(true)) { ?>
        <table class="grid">
            <thead><tr>
                <th scope="col"><?php echo __('Ticket #'); ?></th>
                <th scope="col"><?php echo __('Subject'); ?></th>
                <th scope="col"><?php echo __('Status'); ?></th>
                <th scope="col"><?php echo __('Department'); ?></th>
                <th scope="col"><?php echo __('Last Updated'); ?></th>
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
                    <td class="num"><a href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php echo $T['number']; ?></a></td>
                    <td class="subj"><a href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php
                        if ($T['isanswered']) { ?><span class="dot" aria-hidden="true"></span><span class="visually-hidden"><?php
                            echo ptdt__('Answered, awaiting your reply'); ?>: </span><?php } ?><?php echo $subject; ?></a><?php
                        if ($T['topic__topic']) { ?><span class="sub"><?php echo Format::htmlchars($T['topic__topic']); ?></span><?php } ?></td>
                    <td class="stc"><span class="st st-open"><?php echo $status; ?></span></td>
                    <td class="svc"><?php echo Format::htmlchars($dept); ?></td>
                    <td class="date"><?php echo Format::datetime($T['lastupdate'] ?: $T['created']); ?></td>
                </tr>
<?php   } ?>
            </tbody>
        </table>
<?php
    } else { ?>
        <div class="empty">
            <p><?php echo ptdt__('You have no open tickets.'); ?></p>
<?php   if ($can_open) { ?>
            <a class="btn btn-primary" href="open.php"><?php echo __('Open a New Ticket'); ?></a>
<?php   } ?>
        </div>
<?php
    } ?>
    </section>
<?php } else { ?>
    <section class="panel" aria-label="<?php echo __('Support Center'); ?>">
        <div class="p-body thread-body landing">
<?php
    if($cfg && ($page = $cfg->getLandingPage()))
        echo $page->getBodyWithImages();
    else
        echo '<h2>'.__('Welcome to the Support Center').'</h2>';
?>
        </div>
    </section>
<?php } ?>
    <div class="side">
<?php if ($kb_enabled) { ?>
    <section class="panel search-form" aria-labelledby="dash-search">
        <div class="p-head"><h2 id="dash-search"><?php echo __('Knowledgebase'); ?></h2></div>
        <form class="p-body" role="search" method="get" action="kb/faq.php">
            <input type="hidden" name="a" value="search"/>
            <label class="visually-hidden" for="dash-q"><?php echo __('Search our knowledge base'); ?></label>
            <div class="inline">
                <input type="search" id="dash-q" name="q" class="search" placeholder="<?php echo __('Search our knowledge base'); ?>"/>
                <button type="submit" class="btn btn-primary"><?php echo __('Search'); ?></button>
            </div>
        </form>
<?php
    if (($faqs = FAQ::getFeatured()->select_related('category')->limit(5))
        && $faqs->all()) { ?>
        <h3 class="p-sub"><?php echo __('Featured Questions'); ?></h3>
        <ul class="faq">
<?php   foreach ($faqs as $F) { ?>
            <li><a href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php echo urlencode($F->getId());
                ?>"><?php echo ptdt_icon('doc', 14); ?> <span><?php echo $F->getLocalQuestion(); ?></span></a></li>
<?php   } ?>
        </ul>
<?php } ?>
    </section>
<?php } ?>
    </div>
</div>
<?php
if ($member || $kb_enabled) { ?>
<div class="cols">
<?php
    if ($member) { ?>
    <section class="panel" aria-label="<?php echo __('Support Center'); ?>">
        <div class="p-body thread-body landing">
<?php
        if($cfg && ($page = $cfg->getLandingPage()))
            echo $page->getBodyWithImages();
        else
            echo '<h2>'.__('Welcome to the Support Center').'</h2>';
?>
        </div>
    </section>
<?php
    }
    if ($kb_enabled) {
        $cats = Category::getFeatured();
        if ($cats->all()) { ?>
    <section class="panel featured" aria-labelledby="dash-kb">
        <div class="p-head"><h2 id="dash-kb"><?php echo __('Featured Knowledge Base Articles'); ?></h2>
            <a href="kb/index.php"><?php echo ptdt__('Browse all'); ?></a></div>
<?php       foreach ($cats as $C) { ?>
        <div class="featured-category front-page">
            <h3 class="category-name"><?php echo ptdt_icon('folder', 15); ?> <a href="kb/faq.php?cid=<?php
                echo $C->getId(); ?>"><?php echo $C->getName(); ?></a></h3>
<?php           foreach ($C->getTopArticles() as $F) { ?>
            <div class="article-headline">
                <div class="article-title"><a href="<?php echo ROOT_PATH;
                    ?>kb/faq.php?id=<?php echo $F->getId(); ?>"><?php
                    echo $F->getQuestion(); ?></a></div>
                <div class="article-teaser"><?php echo $F->getTeaser(); ?></div>
            </div>
<?php           } ?>
        </div>
<?php       } ?>
    </section>
<?php
        }
    } ?>
</div>
<?php
} ?>
</div>

<?php require(CLIENTINC_DIR.'footer.inc.php'); ?>
