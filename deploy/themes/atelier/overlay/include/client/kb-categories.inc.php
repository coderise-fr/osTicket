<?php
/* Atelier theme (deploy/themes/atelier): replaces include/client/kb-categories.inc.php.
 * Same category query as the original (public categories, FAQ counts,
 * sub-categories, five articles per category, topic filter); adds the
 * knowledge base search form at the top (same form as on the other KB
 * pages: GET kb/faq.php?a=search&q=) and shows categories as cards. */
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');
?>
<section class="kb-hero on-dark">
    <h1 class="display"><?php echo __('Frequently Asked Questions'); ?></h1>
    <form class="ask" method="get" action="faq.php" role="search">
        <input type="hidden" name="a" value="search"/>
        <label class="visually-hidden" for="kb-q"><?php echo __('Search our knowledge base'); ?></label>
        <input id="kb-q" type="search" name="q" class="search" placeholder="<?php
            echo __('Search our knowledge base'); ?>"/>
        <input class="btn btn-primary" type="submit" value="<?php echo __('Search'); ?>"/>
    </form>
<?php
$topics = Topic::objects()
    ->annotate(array('has_faqs'=>SqlAggregate::COUNT('faqs')))
    ->filter(array('has_faqs__gt'=>0));
if ($topics->exists(true)) { ?>
    <form class="topic-filter" method="get" action="faq.php">
        <input type="hidden" name="a" value="search"/>
        <label for="kb-topic" class="small"><?php echo __("Browse by Topic"); ?></label>
        <select id="kb-topic" name="topicId" onchange="javascript:this.form.submit();">
            <option value="">—<?php echo __("Browse by Topic"); ?>—</option>
<?php foreach ($topics as $T) { ?>
            <option value="<?php echo $T->getId(); ?>"><?php echo $T->getFullName();
                ?></option>
<?php } ?>
        </select>
        <noscript><input class="btn btn-ghost btn-sm" type="submit" value="<?php echo __('Search'); ?>"></noscript>
    </form>
<?php } ?>
</section>
<?php
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

       // ->filter(array('faq_count__gt' => 0));
    if ($categories->exists(true)) { ?>
<section class="block" aria-labelledby="kb-cats">
    <h2 class="h2" id="kb-cats"><?php echo __('Categories'); ?></h2>
    <p class="muted"><?php echo __('Click on the category to browse FAQs.'); ?></p>
    <ul id="kb" class="topics big">
<?php
        $atelier_i = 0;
        foreach ($categories as $C) {
            // Don't show subcategories with parents.
            if (($p=$C->parent)
                    && ($categories->findFirst(array(
                                'category_id' => $p->getId()))))
                continue;

            $count = $C->faq_count + $C->children_faq_count;
            ?>
        <li class="topic t<?php echo ($atelier_i++ % 4); ?>">
            <span class="t-ic" aria-hidden="true"><?php echo atelier_icon('folder', 22); ?></span>
            <h3 class="t-name"><?php echo sprintf('<a href="faq.php?cid=%d">%s</a>',
                $C->getId(), Format::htmlchars($C->getLocalName())); ?>
<?php if ($count) { ?>
                <span class="count"><?php echo (int) $count; ?></span>
<?php } ?>
            </h3>
            <div class="t-desc">
                <?php echo Format::safe_html($C->getLocalDescriptionWithImages()); ?>
            </div>
<?php
            if (($subs=$C->getPublicSubCategories())) {
                echo '<ul class="subs">';
                foreach ($subs as $c) {
                    echo sprintf('<li>' . atelier_icon('folder', 16) . '
                            <a href="faq.php?cid=%d">%s (%d)</a></li>',
                            $c->getId(),
                            $c->getLocalName(),
                            $c->faq_count
                            );
                }
                echo '</ul>';
            }

            $atelier_faqs = $C->faqs
                    ->exclude(array('ispublished'=>FAQ::VISIBILITY_PRIVATE))
                    ->limit(5);
            if ($atelier_faqs->exists(true)) { ?>
            <ul class="links">
<?php       foreach ($atelier_faqs as $F) { ?>
                <li class="popular-faq"><a href="faq.php?id=<?php echo $F->getId(); ?>"><?php
                    echo $F->getLocalQuestion() ?: $F->getQuestion(); ?></a></li>
<?php       } ?>
            </ul>
<?php       } ?>
        </li>
<?php   } ?>
    </ul>
</section>
<?php
    } else { ?>
    <div class="card empty"><p><?php echo __('NO FAQs found'); ?></p></div>
<?php
    }
?>
