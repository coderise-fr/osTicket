<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');
/* Theme "Poste de travail" (Coderise) - overrides include/client/open.inc.php.
 * Same logic, fields, names, AJAX reload of #dynamic-form, CAPTCHA and buttons
 * as the core template. The form keeps its table rows (they are produced by
 * templates/dynamic-form.tmpl.php, shared with other pages); the stylesheet
 * lays them out with labels on the left. A side column lists featured
 * knowledge base questions. */
$info=array();
if($thisclient && $thisclient->isValid()) {
    $info=array('name'=>$thisclient->getName(),
                'email'=>$thisclient->getEmail(),
                'phone'=>$thisclient->getPhoneNumber());
}

$info=($_POST && $errors)?Format::htmlchars($_POST):$info;

$form = null;
if (!$info['topicId']) {
    if (array_key_exists('topicId',$_GET) && preg_match('/^\d+$/',$_GET['topicId']) && Topic::lookup($_GET['topicId']))
        $info['topicId'] = intval($_GET['topicId']);
    else
        $info['topicId'] = $cfg->getDefaultTopicId();
}

$forms = array();
if ($info['topicId'] && ($topic=Topic::lookup($info['topicId']))) {
    foreach ($topic->getForms() as $F) {
        if (!$F->hasAnyVisibleFields())
            continue;
        if ($_POST) {
            $F = $F->instanciate();
            $F->isValidForClient();
        }
        $forms[] = $F->getForm();
    }
}

?>
<div class="title-row"><div>
    <h1><?php echo __('Open a New Ticket');?></h1>
    <p class="sub"><?php echo __('Please fill in the form below to open a new ticket.');?></p>
</div></div>
<div class="cols cols-form"><div class="col-main">
<form id="ticketForm" class="panel form tform" method="post" action="open.php" enctype="multipart/form-data">
  <?php csrf_token(); ?>
  <input type="hidden" name="a" value="open">
  <table class="ftable" role="presentation">
    <tbody class="f-user">
<?php
        if (!$thisclient) {
            $uform = UserForm::getUserForm()->getForm($_POST);
            if ($_POST) $uform->isValid();
            $uform->render(array('staff' => false, 'mode' => 'create'));
        }
        else { ?>
        <tr class="f-static"><td><?php echo __('Email'); ?></td><td><?php
            echo Format::htmlchars($thisclient->getEmail()); ?></td></tr>
        <tr class="f-static"><td><?php echo __('Client'); ?></td><td><?php
            echo Format::htmlchars($thisclient->getName()); ?></td></tr>
        <?php } ?>
    </tbody>
    <tbody class="f-topic">
    <tr><td colspan="2">
        <div class="form-header"><h3><?php echo __('Help Topic'); ?></h3></div>
    </td></tr>
    <tr>
        <td colspan="2">
            <label for="topicId"><span class="required"><?php echo __('Help Topic'); ?>
                <span class="error">*</span></span>
            <select id="topicId" name="topicId" onchange="javascript:
                    var data = $(':input[name]', '#dynamic-form').serialize();
                    $.ajax(
                      'ajax.php/form/help-topic/' + this.value,
                      {
                        data: data,
                        dataType: 'json',
                        success: function(json) {
                          $('#dynamic-form').empty().append(json.html);
                          $(document.head).append(json.media);
                        }
                      });">
                <option value="" selected="selected">&mdash; <?php echo __('Select a Help Topic');?> &mdash;</option>
                <?php
                if($topics=Topic::getPublicHelpTopics()) {
                    foreach($topics as $id =>$name) {
                        echo sprintf('<option value="%d" %s>%s</option>',
                                $id, ($info['topicId']==$id)?'selected="selected"':'', $name);
                    }
                } ?>
            </select></label>
<?php if ($errors['topicId']) { ?>
            <div class="error"><?php echo $errors['topicId']; ?></div>
<?php } ?>
        </td>
    </tr>
    </tbody>
    <tbody id="dynamic-form">
        <?php
        $options = array('mode' => 'create');
        foreach ($forms as $form) {
            include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
        } ?>
    </tbody>
    <tbody>
    <?php
    if($cfg && $cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) {
        if($_POST && $errors && !$errors['captcha'])
            $errors['captcha']=__('Please re-enter the text again');
        ?>
    <tr class="captchaRow">
        <td colspan="2">
            <label for="captcha"><span class="required"><?php echo __('CAPTCHA Text');?>
                <span class="error">*</span></span>
            <span class="captcha"><img src="captcha.php" alt=""></span>
            <input id="captcha" type="text" name="captcha" size="6" autocomplete="off">
            <em><?php echo __('Enter the text shown on the image.');?></em></label>
<?php if ($errors['captcha']) { ?>
            <div class="error"><?php echo $errors['captcha']; ?></div>
<?php } ?>
        </td>
    </tr>
    <?php
    } ?>
    </tbody>
  </table>
  <p class="buttons form-actions">
        <input type="submit" class="btn btn-primary" value="<?php echo __('Create Ticket');?>">
        <input type="reset" class="btn" name="reset" value="<?php echo __('Reset');?>">
        <input type="button" class="btn btn-link" name="cancel" value="<?php echo __('Cancel'); ?>" onclick="javascript:
            $('.richtext').each(function() {
                var redactor = $(this).data('redactor');
                if (redactor && redactor.opts.draftDelete)
                    redactor.plugin.draft.deleteDraft();
            });
            window.location.href='index.php';">
  </p>
</form>
</div>
<?php
if ($cfg->isKnowledgebaseEnabled()
    && ($faqs = FAQ::getFeatured()->select_related('category')->limit(5))
    && $faqs->all()) { ?>
<aside class="col-side">
    <section class="panel" aria-labelledby="open-faq">
        <div class="panel-h"><h2 id="open-faq"><?php echo __('Featured Questions'); ?></h2></div>
        <ul class="kb-rows">
<?php   foreach ($faqs as $F) { ?>
            <li><a href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php echo urlencode($F->getId());
                ?>" target="_blank"><?php echo ptdt_icon('doc'); ?><span><?php echo $F->getLocalQuestion(); ?></span></a></li>
<?php   } ?>
        </ul>
    </section>
</aside>
<?php
} ?>
</div>
