<?php
/*********************************************************************
    index.php

    Helpdesk landing page. Please customize it to fit your needs.

    Theme "Parcours" (Coderise, deploy/themes/parcours): "what do you want
    to do?" page. The choices are the public help topics (each opens the
    ticket wizard on that topic), the welcome text is the "Landing" page of
    the admin panel, the reading list is the featured FAQs.

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

$pcrs_can_open = ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired());
$pcrs_topics = $pcrs_can_open ? (Topic::getPublicHelpTopics() ?: array()) : array();
$pcrs_max = 6;
?>
<section class="top hero">
  <div class="hero-text thread-body">
<?php
    if($cfg && ($page = $cfg->getLandingPage()))
        echo $page->getBodyWithImages();
    else
        echo  '<h1>'.__('Welcome to the Support Center').'</h1>';
?>
  </div>
<?php if ($pcrs_topics) { ?>
  <h2 class="sr-only" id="choices-title"><?php echo __('Open a New Ticket'); ?></h2>
  <ul class="choices" aria-labelledby="choices-title">
<?php
    $i = 0;
    foreach ($pcrs_topics as $id => $name) {
        if (++$i > $pcrs_max)
            break; ?>
    <li><a class="choice" href="<?php echo ROOT_PATH; ?>open.php?topicId=<?php echo (int) $id; ?>">
      <span class="ci"><?php echo pcrs_sommet('ci-mark'); ?></span>
      <span class="c-name"><?php echo Format::htmlchars($name); ?></span>
      <span class="go"><?php echo pcrs__('Start'); ?></span>
    </a></li>
<?php
    }
    if (count($pcrs_topics) > $pcrs_max) { ?>
    <li><a class="choice choice-more" href="<?php echo ROOT_PATH; ?>open.php">
      <span class="ci"><?php echo pcrs_icon('plus', 24); ?></span>
      <span class="c-name"><?php echo pcrs__('Another request'); ?></span>
      <span class="go"><?php echo pcrs__('Start'); ?></span>
    </a></li>
<?php
    } ?>
  </ul>
<?php } elseif ($pcrs_can_open) { ?>
  <p class="actions"><a class="btn btn-primary btn-lg" href="<?php echo ROOT_PATH; ?>open.php"><?php
    echo pcrs_icon('plus'); echo __('Open a New Ticket'); ?></a></p>
<?php } ?>
</section>

<?php if ($pcrs_can_open) { ?>
<section class="how" aria-labelledby="how-title">
  <h2 id="how-title"><?php echo pcrs__('Your request, step by step'); ?></h2>
  <ol class="route">
    <li class="current"><span class="node" aria-hidden="true">1</span><span class="txt"><strong><?php
        echo pcrs__('You describe'); ?></strong><small><?php echo pcrs__('A form in four steps'); ?></small></span></li>
    <li class="todo"><span class="node" aria-hidden="true">2</span><span class="txt"><strong><?php
        echo pcrs__('You get a number'); ?></strong><small><?php echo pcrs__('Your ticket is recorded'); ?></small></span></li>
    <li class="todo"><span class="node" aria-hidden="true">3</span><span class="txt"><strong><?php
        echo pcrs__('We reply'); ?></strong><small><?php echo pcrs__('By email and on this portal'); ?></small></span></li>
    <li class="todo"><span class="node" aria-hidden="true">4</span><span class="txt"><strong><?php
        echo pcrs__('You confirm'); ?></strong><small><?php echo pcrs__('The ticket is closed'); ?></small></span></li>
  </ol>
</section>
<?php } ?>

<div class="two-cols">
<?php
if ($pcrs_member) {
    // Real counts: own open tickets, and those answered by the team (isanswered)
    $pcrs_org = $thisclient->canSeeOrgTickets();
    $pcrs_open = $thisclient->getNumOpenTickets($pcrs_org);
    $pcrs_turn = Ticket::objects()->filter(array('user_id' => $thisclient->getId(),
        'status__state' => 'open', 'isanswered' => 1))->count(); ?>
  <section class="track" aria-labelledby="track-title">
    <h2 id="track-title"><?php echo __('Tickets'); ?></h2>
    <ul class="stats">
      <li><strong><?php echo (int) $pcrs_open; ?></strong> <span><?php echo __('Open'); ?></span></li>
      <li><strong><?php echo (int) $pcrs_turn; ?></strong> <span><?php echo pcrs__('Your turn'); ?></span></li>
    </ul>
    <p class="actions"><a class="btn btn-line" href="<?php echo ROOT_PATH; ?>tickets.php"><?php
        echo __('Show all tickets'); ?></a></p>
  </section>
<?php
} else { ?>
  <section class="track" aria-labelledby="track-title">
    <h2 id="track-title"><?php echo pcrs__('Already have a ticket?'); ?></h2>
    <p><?php echo __('Please provide your email address and a ticket number.'); ?></p>
    <form action="<?php echo ROOT_PATH; ?>login.php" method="post">
      <?php csrf_token(); ?>
      <div class="field">
        <label for="home-email"><?php echo __('Email Address'); ?></label>
        <input id="home-email" type="email" name="lemail" autocomplete="email" class="nowarn">
      </div>
      <div class="field">
        <label for="home-ticket"><?php echo __('Ticket Number'); ?></label>
        <input id="home-ticket" type="text" name="lticket" inputmode="numeric" class="nowarn">
      </div>
      <button class="btn btn-line" type="submit"><?php
        echo $cfg->isClientEmailVerificationRequired() ? __('Email Access Link') : __('View Ticket'); ?></button>
    </form>
  </section>
<?php
}

if ($cfg && $cfg->isKnowledgebaseEnabled()) {
    $pcrs_faqs = FAQ::getFeatured()->select_related('category')->limit(5); ?>
  <section class="reads" aria-labelledby="reads-title">
    <h2 id="reads-title"><?php echo pcrs__('Before you write, two minutes'); ?></h2>
    <form class="kb-search" method="get" action="<?php echo ROOT_PATH; ?>kb/faq.php" role="search">
      <input type="hidden" name="a" value="search">
      <label class="sr-only" for="home-q"><?php echo __('Search our knowledge base'); ?></label>
      <?php echo pcrs_icon('search'); ?>
      <input id="home-q" type="search" name="q" placeholder="<?php echo __('Search our knowledge base'); ?>">
      <button class="btn btn-primary" type="submit"><?php echo __('Search'); ?></button>
    </form>
<?php if ($pcrs_faqs->all()) { ?>
    <ul class="kb-list">
<?php foreach ($pcrs_faqs as $F) { ?>
      <li><a href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php echo urlencode($F->getId()); ?>"><?php
        echo pcrs_icon('doc'); ?><span><?php echo $F->getLocalQuestion(); ?></span><small><?php
        echo sprintf(pcrs__('%d min'), pcrs_reading_time($F->getLocalAnswer())); ?></small></a></li>
<?php } ?>
    </ul>
<?php } ?>
  </section>
<?php
} ?>
</div>

<?php require(CLIENTINC_DIR.'footer.inc.php'); ?>
