<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');
/* Theme "Poste de travail" (Coderise) - overrides include/client/kb-categories.inc.php.
 * Same query and links as the core template (categories, sub-categories, five
 * first articles, browse by topic); rendered as category panels, plus the
 * featured questions and a side list of the categories with their counts. */
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

// Top level categories only (sub-categories are listed inside their parent)
$ptdt_cats = array();
if ($categories->exists(true)) {
    foreach ($categories as $C) {
        // Don't show subcategories with parents.
        if (($p=$C->parent)
                && ($categories->findFirst(array(
                            'category_id' => $p->getId()))))
            continue;
        $ptdt_cats[] = $C;
    }
}
?>
<div class="title-row">
    <div>
        <h1><?php echo __('Knowledgebase'); ?></h1>
        <p class="sub"><?php echo __('Click on the category to browse FAQs.'); ?></p>
    </div>
</div>
<div class="cols">
<div class="col-main">
<?php
if (($faqs = FAQ::getFeatured()->select_related('category')->limit(6))
    && $faqs->all()) { ?>
    <section class="panel" aria-labelledby="kb-featured">
        <div class="panel-h"><h2 id="kb-featured"><?php echo __('Featured Questions'); ?></h2></div>
        <ul class="kb-rows">
<?php foreach ($faqs as $F) {
        $FC = $F->getCategory(); ?>
            <li><a href="faq.php?id=<?php echo urlencode($F->getId()); ?>"><?php echo ptdt_icon('doc'); ?><span><?php
                echo $F->getLocalQuestion(); ?></span><?php
                if ($FC) { ?><small><?php echo Format::htmlchars($FC->getLocalName()); ?></small><?php } ?></a></li>
<?php } ?>
        </ul>
    </section>
<?php
}
if ($ptdt_cats) { ?>
    <div class="cat-grid" id="kb">
<?php
    foreach ($ptdt_cats as $C) {
        $count = $C->faq_count + $C->children_faq_count; ?>
        <section class="panel cat" aria-labelledby="kbc-<?php echo $C->getId(); ?>">
            <div class="panel-h"><h2 id="kbc-<?php echo $C->getId(); ?>"><?php echo ptdt_icon('folder'); ?><a href="faq.php?cid=<?php
                echo $C->getId(); ?>"><?php echo Format::htmlchars($C->getLocalName()); ?></a></h2><?php
                if ($count) { ?><span class="muted" title="<?php echo __('FAQs'); ?>"><?php echo (int) $count; ?></span><?php } ?></div>
<?php       if (trim(strip_tags($C->getLocalDescriptionWithImages()))) { ?>
            <div class="pad cdesc"><?php echo Format::safe_html($C->getLocalDescriptionWithImages()); ?></div>
<?php       }
            if (($subs=$C->getPublicSubCategories())) { ?>
            <ul class="subcats">
<?php           foreach ($subs as $c) {
                    echo sprintf('<li><a href="faq.php?cid=%d">%s (%d)</a></li>',
                            $c->getId(),
                            $c->getLocalName(),
                            $c->faq_count
                            );
                } ?>
            </ul>
<?php       } ?>
            <ul class="kb-rows">
<?php       foreach ($C->faqs
                    ->exclude(array('ispublished'=>FAQ::VISIBILITY_PRIVATE))
                    ->limit(5) as $F) { ?>
                <li><a class="popular-faq" href="faq.php?id=<?php echo $F->getId(); ?>"><?php echo ptdt_icon('doc'); ?><span><?php
                    echo $F->getLocalQuestion() ?: $F->getQuestion(); ?></span></a></li>
<?php       } ?>
            </ul>
        </section>
<?php
    } ?>
    </div>
<?php
} else { ?>
    <div class="panel"><div class="empty">
        <span class="sommet empty-mark" aria-hidden="true"></span>
        <h2><?php echo __('NO FAQs found'); ?></h2>
    </div></div>
<?php
} ?>
</div>
<div class="col-side">
    <section class="panel" aria-labelledby="kb-search-t">
        <div class="panel-h"><h2 id="kb-search-t"><?php echo __('Search'); ?></h2></div>
        <div class="pad kbsearch">
            <form method="get" action="faq.php" role="search" class="inline">
                <input type="hidden" name="a" value="search"/>
                <label class="visually-hidden" for="kb-q"><?php echo __('Search our knowledge base'); ?></label>
                <input type="search" id="kb-q" name="q" class="search" placeholder="<?php
                    echo __('Search our knowledge base'); ?>"/>
                <button type="submit" class="btn btn-line"><?php echo __('Search'); ?></button>
            </form>
<?php if ($topics->exists(true)) { ?>
            <form method="get" action="faq.php" class="searchbar">
                <input type="hidden" name="a" value="search"/>
                <label class="lbl" for="kb-topic"><?php echo __("Browse by Topic"); ?></label>
                <select name="topicId" id="kb-topic" onchange="javascript:this.form.submit();">
                    <option value="">— <?php echo __("Browse by Topic"); ?> —</option>
<?php foreach ($topics as $T) { ?>
                    <option value="<?php echo $T->getId(); ?>"><?php echo $T->getFullName(); ?></option>
<?php } ?>
                </select>
                <noscript><button type="submit" class="btn"><?php echo __('Search'); ?></button></noscript>
            </form>
<?php } ?>
        </div>
    </section>
<?php if ($ptdt_cats) { ?>
    <section class="panel" aria-labelledby="kb-cats">
        <div class="panel-h"><h2 id="kb-cats"><?php echo __('Categories'); ?></h2></div>
        <ul class="cat-list">
<?php foreach ($ptdt_cats as $C) {
        $count = $C->faq_count + $C->children_faq_count; ?>
            <li><a href="faq.php?cid=<?php echo $C->getId(); ?>"><?php echo ptdt_icon('folder'); ?><span><?php
                echo Format::htmlchars($C->getLocalName()); ?></span><small class="rt"><?php echo $count ? (int) $count : ''; ?></small></a></li>
<?php } ?>
        </ul>
    </section>
<?php } ?>
</div>
</div>
