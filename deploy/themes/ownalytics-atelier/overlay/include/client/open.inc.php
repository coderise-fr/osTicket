<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');
/* Ownalytics Atelier theme (deploy/themes/ownalytics-atelier): replaces include/client/open.inc.php.
 * Same form as the original (action, enctype, CSRF token, field names,
 * #topicId reloading #dynamic-form over AJAX, captcha, submit/reset/cancel
 * with draft deletion). The form sections become cards; the fields are still
 * rendered by templates/dynamic-form.tmpl.php (table rows), so the AJAX
 * response of the help-topic selector fits in unchanged. */
require_once(INCLUDE_DIR . 'client/ownalytics-atelier/ownalytics-atelier.inc.php');

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
<div class="page-head">
    <h1 class="display"><?php echo __('Open a New Ticket');?></h1>
    <p class="sub"><?php echo __('Please fill in the form below to open a new ticket.');?></p>
</div>
<div class="split">
<form id="ticketForm" class="stack" method="post" action="open.php" enctype="multipart/form-data">
  <?php csrf_token(); ?>
  <input type="hidden" name="a" value="open">
  <div class="card f-card">
  <table class="form-table" role="presentation">
    <tbody>
<?php
        if (!$thisclient) {
            $uform = UserForm::getUserForm()->getForm($_POST);
            if ($_POST) $uform->isValid();
            $uform->render(array('staff' => false, 'mode' => 'create'));
        }
        else { ?>
        <tr class="client-identity"><td colspan="2">
            <dl class="kv">
                <div><dt><?php echo __('Email'); ?></dt><dd><?php
                    echo Format::htmlchars($thisclient->getEmail()); ?></dd></div>
                <div><dt><?php echo __('Client'); ?></dt><dd><?php
                    echo Format::htmlchars($thisclient->getName()); ?></dd></div>
            </dl>
        </td></tr>
        <?php } ?>
    </tbody>
  </table>
  </div>
  <div class="card f-card">
  <table class="form-table" role="presentation">
    <tbody>
    <tr class="topic-row<?php if ($errors['topicId']) echo ' has-error'; ?>"><td colspan="2">
        <label for="topicId" class="lbl"><?php echo __('Help Topic'); ?>
            <span class="req" aria-hidden="true">*</span></label>
            <select id="topicId" name="topicId"<?php
                if ($errors['topicId']) echo ' aria-invalid="true" aria-describedby="topicId-err"'; ?> onchange="javascript:
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
            </select>
<?php if ($errors['topicId']) { ?>
            <p class="err" id="topicId-err"><?php echo $errors['topicId']; ?></p>
<?php } ?>
    </td></tr>
    </tbody>
    <tbody id="dynamic-form">
        <?php
        $options = array('mode' => 'create');
        foreach ($forms as $form) {
            include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
        } ?>
    </tbody>
  </table>
  </div>
    <?php
    if($cfg && $cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) {
        if($_POST && $errors && !$errors['captcha'])
            $errors['captcha']=__('Please re-enter the text again');
        ?>
  <div class="card f-card captchaRow<?php if ($errors['captcha']) echo ' has-error'; ?>">
        <label class="lbl required" for="captcha"><?php echo __('CAPTCHA Text');?>
            <span class="req" aria-hidden="true">*</span></label>
        <div class="captcha-line">
            <span class="captcha"><img src="captcha.php" alt=""></span>
            <input id="captcha" type="text" name="captcha" size="6" autocomplete="off" aria-describedby="captcha-help<?php
                if ($errors['captcha']) echo ' captcha-err'; ?>">
        </div>
        <p class="help" id="captcha-help"><?php echo __('Enter the text shown on the image.');?></p>
<?php if ($errors['captcha']) { ?>
        <p class="err" id="captcha-err"><?php echo $errors['captcha']; ?></p>
<?php } ?>
  </div>
    <?php
    } ?>
  <p class="buttons actions">
        <input class="btn btn-primary btn-lg" type="submit" value="<?php echo __('Create Ticket');?>">
        <input class="btn btn-ghost" type="reset" name="reset" value="<?php echo __('Reset');?>">
        <input class="btn btn-link" type="button" name="cancel" value="<?php echo __('Cancel'); ?>" onclick="javascript:
            $('.richtext').each(function() {
                var redactor = $(this).data('redactor');
                if (redactor && redactor.opts.draftDelete)
                    redactor.plugin.draft.deleteDraft();
            });
            window.location.href='index.php';">
  </p>
</form>
<aside class="side stack" aria-label="<?php echo Format::htmlchars(__oa('Tips')); ?>">
    <div class="card tip">
        <p class="tip-head"><i class="icon-comments-alt" aria-hidden="true"></i>
            <b><?php echo __oa('A person from the team reads every ticket.'); ?></b></p>
        <p class="small"><?php echo __oa('What helps us most: what you expected, what you see instead, and since when.'); ?></p>
    </div>
<?php
if ($cfg->isKnowledgebaseEnabled()
    && ($faqs = FAQ::getFeatured()->select_related('category')->limit(5))
    && $faqs->all()) { ?>
    <div class="card">
        <h2 class="card-title"><?php echo __('Featured Questions'); ?></h2>
        <ul class="links">
<?php   foreach ($faqs as $F) { ?>
            <li><a href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php
                echo urlencode($F->getId()); ?>"><?php echo $F->getLocalQuestion(); ?></a></li>
<?php   } ?>
        </ul>
    </div>
<?php } ?>
</aside>
</div>
