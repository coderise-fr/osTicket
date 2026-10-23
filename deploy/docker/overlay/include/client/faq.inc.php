<?php
if(!defined('OSTCLIENTINC') || !$faq  || !$faq->isPublished()) die('Access Denied');
/* Theme "Parcours" (Coderise) - overrides include/client/faq.inc.php.
 * Same article, attachments (download links), help topics and search as
 * the core template. Added from real data: the published articles of the
 * same category, in the order of the category page of osTicket (featured
 * first, then by question), as "article N of M" with previous / next links.
 * osTicket has no article order of its own: that order is the one used. */

$category=$faq->getCategory();

$pcrs_list = FAQ::objects()
    ->filter(array('category'=>$category))
    ->exclude(array('ispublished'=>FAQ::VISIBILITY_PRIVATE))
    ->order_by('-ispublished', 'question');
$pcrs_ids = array(); $pcrs_titles = array();
foreach ($pcrs_list as $F) {
    $pcrs_ids[] = $F->getId();
    $pcrs_titles[$F->getId()] = Format::htmlchars($F->getLocalQuestion() ?: $F->getQuestion());
}
$pcrs_pos = array_search($faq->getId(), $pcrs_ids);
$pcrs_prev = ($pcrs_pos !== false && $pcrs_pos > 0) ? $pcrs_ids[$pcrs_pos - 1] : null;
$pcrs_next = ($pcrs_pos !== false && $pcrs_pos < count($pcrs_ids) - 1) ? $pcrs_ids[$pcrs_pos + 1] : null;
?>
<section class="top">
<?php echo pcrs_crumbs(array(array(__('All Categories'), 'index.php'),
    array(Format::htmlchars($category->getFullName()), 'faq.php?cid=' . $category->getId()),
    array(__('Frequently Asked Question'), null))); ?>
  <h1><?php echo $faq->getLocalQuestion() ?></h1>
  <p class="sub meta"><?php echo sprintf(__('Last Updated %s'),
    Format::relativeTime(Misc::db2gmtime($faq->getUpdateDate()))); ?> · <?php
    echo sprintf(pcrs__('%d min read'), pcrs_reading_time($faq->getLocalAnswer()));
    if ($pcrs_pos !== false && count($pcrs_ids) > 1)
        echo ' · ' . sprintf(pcrs__('article %1$d of %2$d'), $pcrs_pos + 1, count($pcrs_ids)); ?></p>
</section>

<div class="art-grid">
  <article class="faq-content">
    <div class="thread-body prose bleed">
<?php echo $faq->getLocalAnswerWithImages(); ?>
    </div>
<?php if ($pcrs_prev || $pcrs_next) { ?>
    <nav class="pager" aria-label="<?php echo Format::htmlchars(pcrs__('In this category')); ?>">
<?php if ($pcrs_prev) { ?>
      <a class="pg-prev" href="faq.php?id=<?php echo $pcrs_prev; ?>"><small><?php echo pcrs_icon('prev', 14); echo pcrs__('Previous'); ?></small><?php echo $pcrs_titles[$pcrs_prev]; ?></a>
<?php } ?>
<?php if ($pcrs_next) { ?>
      <a class="pg-next" href="faq.php?id=<?php echo $pcrs_next; ?>"><small><?php echo pcrs__('Next'); echo pcrs_icon('next', 14); ?></small><?php echo $pcrs_titles[$pcrs_next]; ?></a>
<?php } ?>
    </nav>
<?php } ?>
  </article>

  <aside class="side">
    <form class="kb-search searchbar" method="get" action="faq.php" role="search">
      <input type="hidden" name="a" value="search"/>
      <?php echo pcrs_icon('search'); ?>
      <label class="sr-only" for="faq-q"><?php echo __('Search our knowledge base'); ?></label>
      <input id="faq-q" type="search" name="q" class="search" placeholder="<?php
        echo __('Search our knowledge base'); ?>"/>
      <button class="btn btn-primary" type="submit"><?php echo __('Search'); ?></button>
    </form>
<?php if (count($pcrs_ids) > 1) { ?>
    <section class="card">
      <h2><?php echo Format::htmlchars($category->getLocalName()); ?></h2>
      <ol class="route vert mini">
<?php foreach ($pcrs_ids as $i => $id) {
        $cls = $id == $faq->getId() ? 'current' : ($pcrs_pos !== false && $i < $pcrs_pos ? 'done' : 'todo'); ?>
        <li class="<?php echo $cls; ?>"><span class="node" aria-hidden="true"><?php echo $i + 1; ?></span><span class="txt"><?php
        if ($id == $faq->getId()) { ?><strong aria-current="page"><?php echo $pcrs_titles[$id]; ?></strong><?php }
        else { ?><a href="faq.php?id=<?php echo $id; ?>"><?php echo $pcrs_titles[$id]; ?></a><?php } ?></span></li>
<?php } ?>
      </ol>
    </section>
<?php }
    if ($attachments = $faq->getLocalAttachments()->all()) { ?>
    <section class="card">
      <h2><?php echo __('Attachments');?></h2>
      <ul class="plain">
<?php foreach ($attachments as $att) { ?>
        <li><a href="<?php echo $att->file->getDownloadUrl(['id' => $att->getId()]);
        ?>" class="no-pjax"><?php echo pcrs_icon('clip', 16); ?>
        <?php echo Format::htmlchars($att->getFilename()); ?></a></li>
<?php } ?>
      </ul>
    </section>
<?php }
if ($faq->getHelpTopics()->count()) { ?>
    <section class="card soft">
      <h2><?php echo __('Help Topics'); ?></h2>
      <ul class="plain">
<?php foreach ($faq->getHelpTopics() as $T) { ?>
        <li><?php echo $T->topic->getFullName(); ?></li>
<?php } ?>
      </ul>
    </section>
<?php } ?>
  </aside>
</div>
