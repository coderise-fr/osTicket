<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');
/* Theme "Poste de travail" (Ownalytics) - overrides include/client/kb-categories.inc.php.
 * Same query and links as the core template (categories, sub-categories, five
 * first articles, browse by topic); rendered as a table plus a search bar. */
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
<div class="ph">
    <h1><?php echo __('Knowledgebase'); ?></h1>
    <p class="ph-sub"><?php echo __('Click on the category to browse FAQs.'); ?></p>
</div>
<div class="kbsearch">
    <form method="get" action="faq.php" role="search" class="inline">
        <input type="hidden" name="a" value="search"/>
        <label class="visually-hidden" for="kb-q"><?php echo __('Search our knowledge base'); ?></label>
        <input type="search" id="kb-q" name="q" class="search" placeholder="<?php
            echo __('Search our knowledge base'); ?>"/>
        <button type="submit" class="btn btn-primary"><?php echo __('Search'); ?></button>
    </form>
<?php if ($topics->exists(true)) { ?>
    <form method="get" action="faq.php" class="searchbar">
        <input type="hidden" name="a" value="search"/>
        <label class="visually-hidden" for="kb-topic"><?php echo __("Browse by Topic"); ?></label>
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
<?php
    if ($categories->exists(true)) { ?>
<section class="panel tbl" aria-labelledby="kb-cats">
    <div class="p-head"><h2 id="kb-cats"><?php echo __('Categories'); ?></h2></div>
    <table class="grid kb" id="kb">
        <thead><tr>
            <th scope="col"><?php echo __('Category'); ?></th>
            <th scope="col" class="arts-h"><?php echo __('Frequently Asked Questions'); ?></th>
            <th scope="col" class="r"><?php echo __('FAQs'); ?></th>
        </tr></thead>
        <tbody>
<?php
        foreach ($categories as $C) {
            // Don't show subcategories with parents.
            if (($p=$C->parent)
                    && ($categories->findFirst(array(
                                'category_id' => $p->getId()))))
                continue;

            $count = $C->faq_count + $C->children_faq_count;
            ?>
            <tr>
                <td class="subj">
                    <h3><a href="faq.php?cid=<?php echo $C->getId(); ?>"><?php echo ptdt_icon('folder', 15); ?>
                        <?php echo Format::htmlchars($C->getLocalName()); ?></a></h3>
                    <div class="sub"><?php echo Format::safe_html($C->getLocalDescriptionWithImages()); ?></div>
<?php
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
                </td>
                <td class="arts">
<?php       foreach ($C->faqs
                    ->exclude(array('ispublished'=>FAQ::VISIBILITY_PRIVATE))
                    ->limit(5) as $F) { ?>
                    <a class="popular-faq" href="faq.php?id=<?php echo $F->getId(); ?>"><?php
                        echo $F->getLocalQuestion() ?: $F->getQuestion(); ?></a>
<?php       } ?>
                </td>
                <td class="num r"><?php echo $count ? (int) $count : ''; ?></td>
            </tr>
<?php   } ?>
        </tbody>
    </table>
</section>
<?php
    } else { ?>
<div class="panel empty"><p><?php echo __('NO FAQs found'); ?></p></div>
<?php
    }
?>
