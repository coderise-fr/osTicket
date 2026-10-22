<?php
/*********************************************************************
    index.php

    Helpdesk landing page. Please customize it to fit your needs.

    Sommet theme (deploy/themes/sommet): replaces the landing page of the
    client portal. Same data as the original (landing page content of the
    admin panel, knowledge base search, featured questions and featured
    categories), shown as the Coderise hero followed by editorial lists.
    The help topics of the "open a ticket" form are listed as shortcuts.

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
$sommet_hero = true;
require(CLIENTINC_DIR.'header.inc.php');

$sommet_can_open = ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired());
?>
<div id="landing_page">
<section class="hero" aria-label="<?php echo Format::htmlchars(__('Support Center')); ?>">
<div class="hero-grid">
<div class="hero-copy">
<div class="thread-body landing">
<?php
    if($cfg && ($page = $cfg->getLandingPage()))
        echo $page->getBodyWithImages();
    else
        echo  '<h1>'.__('Welcome to the Support Center').'</h1>';
    ?>
</div>
<div class="actions front-page-button">
<?php if ($sommet_can_open) { ?>
    <a href="open.php" class="btn btn-primary btn-lg"><?php echo sommet_icon('plus', 20);
        ?><span><?php echo __('Open a New Ticket');?></span></a>
<?php } ?>
<?php if ($sommet_signed_in) { ?>
    <a href="tickets.php" class="btn btn-ghost btn-lg"><?php echo __('Tickets'); ?></a>
<?php } else { ?>
    <a href="view.php" class="btn btn-ghost btn-lg"><?php echo __('Check Ticket Status');?></a>
<?php } ?>
</div>
</div>
<div class="hero-peak" aria-hidden="true"><?php echo sommet_peak(); ?></div>
</div>
</section>

<?php
// Help topics of the "open a ticket" form, as shortcuts (open.php?topicId=).
$sommet_topics = $sommet_can_open ? Topic::getPublicHelpTopics() : array();
if ($sommet_topics) { ?>
<section class="intents">
    <h2><?php echo __s('What would you like to entrust us with?'); ?></h2>
    <ul class="intent-list">
<?php foreach ($sommet_topics as $id => $name) { ?>
        <li><a href="open.php?topicId=<?php echo (int) $id; ?>"><span class="t"><?php
            echo Format::htmlchars($name); ?></span><?php echo sommet_icon('arrow', 22); ?></a></li>
<?php } ?>
    </ul>
</section>
<?php } ?>

<section class="statement">
    <p class="big"><?php echo __s('Priority support: a first reply <span class="mark">within 4 hours</span>, day and night.'); ?></p>
    <p class="small"><?php echo __s('Standard support: business days. Office hours (Monday to Friday, 8:30 am to 5:00 pm) are not the hours of the Priority support.'); ?></p>
</section>

<?php
if ($cfg && $cfg->isKnowledgebaseEnabled()) { ?>
<section class="guides">
<div class="search-form">
    <form method="get" action="kb/faq.php" role="search">
    <input type="hidden" name="a" value="search"/>
    <label class="visually-hidden" for="sommet-kb-q"><?php echo __('Search our knowledge base'); ?></label>
    <?php echo sommet_icon('search', 20); ?>
    <input id="sommet-kb-q" type="text" name="q" class="search" placeholder="<?php echo __('Search our knowledge base'); ?>"/>
    <button type="submit" class="btn btn-primary"><?php echo __('Search'); ?></button>
    </form>
</div>
<?php
    $faqs = FAQ::getFeatured()->select_related('category')->limit(5);
    if ($faqs->all()) { ?>
    <h2><?php echo __('Featured Questions'); ?></h2>
    <ul class="guide-list">
<?php   foreach ($faqs as $F) { ?>
        <li><a href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php echo urlencode($F->getId());
            ?>"><span class="t"><?php echo $F->getLocalQuestion(); ?></span><span class="c"><?php
            echo Format::htmlchars($F->getCategory() ? $F->getCategory()->getFullName() : ''); ?></span></a></li>
<?php   } ?>
    </ul>
<?php
    }
    $cats = Category::getFeatured();
    if ($cats->all()) { ?>
    <h2><?php echo __('Featured Knowledge Base Articles'); ?></h2>
<?php
        foreach ($cats as $C) { ?>
    <div class="featured-category front-page">
        <h3 class="category-name"><?php echo sommet_icon('folder', 22); ?><span><?php echo $C->getName(); ?></span></h3>
        <ul class="guide-list">
<?php       foreach ($C->getTopArticles() as $F) { ?>
            <li class="article-headline"><a href="<?php echo ROOT_PATH;
                ?>kb/faq.php?id=<?php echo $F->getId(); ?>"><span class="t article-title"><?php
                echo $F->getQuestion(); ?></span><span class="article-teaser"><?php
                echo $F->getTeaser(); ?></span></a></li>
<?php       } ?>
        </ul>
    </div>
<?php
        }
    } ?>
    <p><a class="more" href="<?php echo ROOT_PATH; ?>kb/index.php"><?php echo __s('The whole knowledge base'); ?></a></p>
</section>
<?php
} ?>
</div>

<?php require(CLIENTINC_DIR.'footer.inc.php'); ?>
