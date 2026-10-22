<?php
/* Theme "Parcours" (Coderise) - overrides include/client/kb-categories.inc.php.
 * Same category query as the core template (public categories with
 * published articles, sub-categories under their parent). Display: a
 * search band, then the top-level categories as "paths" (columns) holding
 * their sub-categories and first articles, then the featured articles
 * (osTicket has no reading statistics: "most read" is not available). */
    $categories = Category::objects()
        ->exclude(Q::any(array(
            'ispublic'=>Category::VISIBILITY_PRIVATE,
            Q::all(array(
                    'faqs__ispublished'=>FAQ::VISIBILITY_PRIVATE,
                    'children__ispublic' => Category::VISIBILITY_PRIVATE,
                    'children__faqs__ispublished'=>FAQ::VISIBILITY_PRIVATE,
                    ))
        )))
        //->annotate(array('faq_count'=>SqlAggregate::COUNT('faqs__ispublished')));
        ->annotate(array('faq_count' => SqlAggregate::COUNT(
                        SqlCase::N()
                        ->when(array(
                                'faqs__ispublished__gt'=> FAQ::VISIBILITY_PRIVATE), 1)
                        ->otherwise(null)
        )))
        ->annotate(array('children_faq_count' => SqlAggregate::COUNT(
                        SqlCase::N()
                        ->when(array(
                                'children__faqs__ispublished__gt'=> FAQ::VISIBILITY_PRIVATE), 1)
                        ->otherwise(null)
        )));
$topics = Topic::objects()
    ->annotate(array('has_faqs'=>SqlAggregate::COUNT('faqs')))
    ->filter(array('has_faqs__gt'=>0));
?>
<section class="top">
<?php echo pcrs_crumbs(array(array(pcrs__('Home'), ROOT_PATH . 'index.php'), array(__('Knowledgebase'), null))); ?>
  <h1><?php echo pcrs__('Find the answer, step by step'); ?></h1>
  <p class="sub"><?php echo pcrs__('Search, or follow a path from start to finish.'); ?></p>
  <form class="bigsearch" method="get" action="faq.php" role="search">
    <input type="hidden" name="a" value="search"/>
    <?php echo pcrs_icon('search', 20); ?>
    <label class="sr-only" for="kb-q"><?php echo __('Search our knowledge base'); ?></label>
    <input id="kb-q" type="search" name="q" placeholder="<?php echo __('Search our knowledge base'); ?>">
    <button class="btn btn-primary" type="submit"><?php echo __('Search'); ?></button>
  </form>
<?php if ($topics->count()) { ?>
  <form class="topic-browse" method="get" action="faq.php">
    <input type="hidden" name="a" value="search"/>
    <label for="kb-topic"><?php echo __("Browse by Topic"); ?></label>
    <select id="kb-topic" name="topicId" onchange="javascript:this.form.submit();">
      <option value="">—<?php echo __("Browse by Topic"); ?>—</option>
<?php foreach ($topics as $T) { ?>
      <option value="<?php echo $T->getId(); ?>"><?php echo $T->getFullName(); ?></option>
<?php } ?>
    </select>
    <noscript><button class="btn btn-line" type="submit"><?php echo __('Search'); ?></button></noscript>
  </form>
<?php } ?>
</section>
<?php
    if ($categories->exists(true)) { ?>
<p class="sr-only"><?php echo __('Click on the category to browse FAQs.'); ?></p>
<div class="paths" id="kb">
<?php
        foreach ($categories as $C) {
            // Don't show subcategories with parents.
            if (($p=$C->parent)
                    && ($categories->findFirst(array(
                                'category_id' => $p->getId()))))
                continue;

            $count = $C->faq_count + $C->children_faq_count;
            ?>
  <section class="path">
    <h2><?php echo pcrs_sommet('pm'); ?><a href="faq.php?cid=<?php echo $C->getId(); ?>"><?php
        echo Format::htmlchars($C->getLocalName()); ?></a></h2>
    <div class="desc"><?php echo Format::safe_html($C->getLocalDescriptionWithImages()); ?></div>
    <ul>
<?php
            if (($subs=$C->getPublicSubCategories())) {
                foreach ($subs as $c) { ?>
      <li><a href="faq.php?cid=<?php echo $c->getId(); ?>"><?php echo pcrs_icon('folder', 20); ?><span><strong><?php
                    echo Format::htmlchars($c->getLocalName()); ?></strong><small><?php
                    echo sprintf(pcrs__('%d articles'), $c->faq_count); ?></small></span></a></li>
<?php           }
            }
            foreach ($C->faqs
                    ->exclude(array('ispublished'=>FAQ::VISIBILITY_PRIVATE))
                    ->limit(5) as $F) { ?>
      <li><a href="faq.php?id=<?php echo $F->getId(); ?>"><?php echo pcrs_icon('doc', 20); ?><span><?php
                echo $F->getLocalQuestion() ?: $F->getQuestion(); ?></span></a></li>
<?php       } ?>
    </ul>
<?php if (!$subs && $count) { ?>
    <p class="meta"><?php echo sprintf(pcrs__('%d articles'), $count); ?></p>
<?php } ?>
  </section>
<?php   } ?>
</div>
<?php
        $featured = FAQ::getFeatured()->select_related('category')->limit(6);
        if ($featured->all()) { ?>
<h2 class="sec"><?php echo __('Featured Questions'); ?></h2>
<ul class="kb-list wide">
<?php       foreach ($featured as $F) { ?>
  <li><a href="faq.php?id=<?php echo urlencode($F->getId()); ?>"><?php echo pcrs_icon('doc'); ?><span><?php
        echo $F->getLocalQuestion(); ?><span class="cat-name"><?php echo Format::htmlchars($F->getCategory()->getLocalName()); ?></span></span><small><?php
        echo sprintf(pcrs__('%d min'), pcrs_reading_time($F->getLocalAnswer())); ?></small></a></li>
<?php       } ?>
</ul>
<?php   }
    } else { ?>
<div class="empty">
  <?php echo pcrs_sommet('e-mark'); ?>
  <h2><?php echo __('NO FAQs found'); ?></h2>
</div>
<?php
    }
?>
