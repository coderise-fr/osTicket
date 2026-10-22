<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');
/* Theme "Parcours" (Coderise) - overrides include/client/open.inc.php.
 *
 * One real osTicket form (#ticketForm, same fields, same names, same CSRF
 * token, a single POST to open.php), laid out in four fieldsets:
 *   1. your request: contact (guest) or account, help topic
 *   2. context: the forms of the help topic (tbody#dynamic-form)
 *   3. your message: subject, message, attachments (ticket details form)
 *   4. submission: priority, CAPTCHA, summary, buttons
 * Without JavaScript the fieldsets show one after the other and the help
 * topic forms all stay in step 2 (as in the core template). With
 * assets/parcours/js/parcours.js the form becomes a step-by-step wizard: the
 * rows of #dynamic-form are dispatched to steps 2 to 4, and again after
 * each AJAX reload of the help topic forms. Server-side validation is
 * unchanged; after an error the wizard opens the step of the first error.
 * The PHP prelude below is the core one, unchanged. */
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

// Field names of the standard ticket form, for the wizard: these names do
// not depend on the help topic (hash of session, form id and field name).
$pcrs_roles = array();
if (($pcrs_tf = TicketForm::objects()->one()) && ($pcrs_tform = $pcrs_tf->getForm())) {
    foreach (array('subject', 'message', 'priority') as $pcrs_n) {
        if ($pcrs_f = $pcrs_tform->getField($pcrs_n))
            $pcrs_roles[$pcrs_n] = $pcrs_f->getFormName();
    }
}
$pcrs_wizard = array(
    'roles' => $pcrs_roles,
    'posted' => ($_POST && $errors) ? true : false,
    'steps' => array(
        array('title' => pcrs__('Your request'), 'hint' => pcrs__('Contact and help topic')),
        array('title' => pcrs__('Context'), 'hint' => pcrs__('Details for this topic')),
        array('title' => pcrs__('Your message'), 'hint' => pcrs__('Description and files')),
        array('title' => pcrs__('Submission'), 'hint' => pcrs__('Check and send')),
    ),
    'str' => array(
        'stepOf' => pcrs__('Step %1$d of %2$d'),
        'next' => pcrs__('Next step'),
        'prev' => pcrs__('Previous step'),
        'fix1' => pcrs__('1 field to fix'),
        'fixN' => pcrs__('%d fields to fix'),
        'summary1' => pcrs__('1 field to fix before sending'),
        'summaryN' => pcrs__('%d fields to fix before sending'),
        'stepN' => pcrs__('Step %d'),
        'noTopic' => pcrs__('Choose a help topic in step 1 first.'),
        'noContext' => pcrs__('This help topic needs no extra details. Go on to the next step.'),
        'loading' => pcrs__('Loading the fields of this help topic…'),
        'topic' => __('Help Topic'),
        'subject' => pcrs__('Subject'),
        'files' => pcrs__('Attachments'),
        'none' => pcrs__('none'),
        'recap' => pcrs__('Summary'),
        'progress' => pcrs__('Progress'),
        'sep' => pcrs__(': '),
    ),
);
?>
<section class="top">
<?php echo pcrs_crumbs(array(array(pcrs__('Home'), ROOT_PATH . 'index.php'),
    array(__('Open a New Ticket'), null))); ?>
  <h1><?php echo __('Open a New Ticket');?></h1>
  <p class="sub"><?php echo __('Please fill in the form below to open a new ticket.');?></p>
  <div class="overall" id="pcrs-overall" hidden>
    <p id="pcrs-overall-text"></p>
    <div class="progress" aria-hidden="true"><span></span></div>
  </div>
</section>

<div class="wiz" id="pcrs-wizard">
  <nav class="steps-nav" id="pcrs-steps-nav" aria-label="<?php echo Format::htmlchars(pcrs__('Steps of the form')); ?>" hidden>
    <ol class="route vert"></ol>
  </nav>
  <div class="wiz-main">
    <div class="form-errors" id="pcrs-errors" hidden tabindex="-1">
      <h2><?php echo pcrs_icon('alert', 20); ?><span></span></h2>
      <ul></ul>
    </div>
    <p class="sr-only" id="pcrs-live" aria-live="polite"></p>
<form id="ticketForm" class="form" method="post" action="open.php" enctype="multipart/form-data"
    data-wizard="<?php echo Format::htmlchars(JsonDataEncoder::encode($pcrs_wizard)); ?>">
  <?php csrf_token(); ?>
  <input type="hidden" name="a" value="open">

  <fieldset class="step" id="pcrs-step-1" data-step="1">
    <legend><span class="n">1</span><span class="lt"><?php echo pcrs__('Your request'); ?></span></legend>
    <table class="form-table" role="presentation">
    <tbody>
<?php
        if (!$thisclient) {
            $uform = UserForm::getUserForm()->getForm($_POST);
            if ($_POST) $uform->isValid();
            $uform->render(array('staff' => false, 'mode' => 'create'));
        }
        else { ?>
        <tr class="account-row"><td colspan="2">
          <dl class="props">
            <div><dt><?php echo __('Email'); ?></dt><dd><?php
            echo Format::htmlchars($thisclient->getEmail()); ?></dd></div>
            <div><dt><?php echo __('Client'); ?></dt><dd><?php
            echo Format::htmlchars($thisclient->getName()); ?></dd></div>
          </dl>
        </td></tr>
        <?php } ?>
    </tbody>
    <tbody>
    <tr class="topic-row<?php if ($errors['topicId']) echo ' has-error'; ?>">
        <td colspan="2">
            <label for="topicId" class="lbl"><span class="required"><?php echo __('Help Topic'); ?>
                <span class="error" aria-hidden="true">*</span></span></label>
            <select id="topicId" name="topicId"<?php
                if ($errors['topicId']) echo ' aria-invalid="true" aria-describedby="topicId-error"'; ?> onchange="javascript:
                    if (window.pcrsTopicChange) return window.pcrsTopicChange(this);
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
            <p class="hint"><?php echo pcrs__('The form adapts to your choice.'); ?></p>
<?php if ($errors['topicId']) { ?>
            <div class="error" id="topicId-error"><?php echo $errors['topicId']; ?></div>
<?php } ?>
        </td>
    </tr>
    </tbody>
    </table>
  </fieldset>

  <fieldset class="step" id="pcrs-step-2" data-step="2">
    <legend><span class="n">2</span><span class="lt" data-js-label="<?php
        echo Format::htmlchars(pcrs__('Context')); ?>"><?php echo pcrs__('Details of your request'); ?></span></legend>
    <p class="step-note" hidden></p>
    <table class="form-table" role="presentation">
    <tbody class="pcrs-rows"></tbody>
    <tbody id="dynamic-form">
        <?php
        $options = array('mode' => 'create');
        foreach ($forms as $form) {
            include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
        } ?>
    </tbody>
    </table>
  </fieldset>

  <fieldset class="step" id="pcrs-step-3" data-step="3" hidden>
    <legend><span class="n">3</span><span class="lt"><?php echo pcrs__('Your message'); ?></span></legend>
    <p class="step-note" hidden></p>
    <table class="form-table" role="presentation">
    <tbody class="pcrs-rows"></tbody>
    </table>
  </fieldset>

  <fieldset class="step" id="pcrs-step-4" data-step="4">
    <legend><span class="n">3</span><span class="lt"><?php echo pcrs__('Submission'); ?></span></legend>
    <table class="form-table" role="presentation">
    <tbody class="pcrs-rows"></tbody>
    <tbody>
    <?php
    if($cfg && $cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) {
        if($_POST && $errors && !$errors['captcha'])
            $errors['captcha']=__('Please re-enter the text again');
        ?>
    <tr class="captchaRow<?php if ($errors['captcha']) echo ' has-error'; ?>">
        <td colspan="2">
            <label for="captcha" class="lbl required"><?php echo __('CAPTCHA Text');?> <span class="error" aria-hidden="true">*</span></label>
            <span class="captcha"><img src="captcha.php" alt="<?php echo Format::htmlchars(__('CAPTCHA Text')); ?>"></span>
            <input id="captcha" type="text" name="captcha" size="6" autocomplete="off" aria-describedby="captcha-hint<?php
                if ($errors['captcha']) echo ' captcha-error'; ?>">
            <p class="hint" id="captcha-hint"><?php echo __('Enter the text shown on the image.');?></p>
<?php if ($errors['captcha']) { ?>
            <div class="error" id="captcha-error"><?php echo $errors['captcha']; ?></div>
<?php } ?>
        </td>
    </tr>
    <?php
    } ?>
    </tbody>
    </table>
    <div class="recap" id="pcrs-recap" hidden></div>
    <p class="buttons actions">
        <button class="btn btn-primary btn-lg" type="submit"><?php echo pcrs_icon('arrow', 20);
            ?><span><?php echo __('Create Ticket');?></span></button>
        <input class="btn btn-line" type="reset" name="reset" value="<?php echo __('Reset');?>">
        <input class="btn btn-line" type="button" name="cancel" value="<?php echo __('Cancel'); ?>" onclick="javascript:
            $('.richtext').each(function() {
                var redactor = $(this).data('redactor');
                if (redactor && redactor.opts.draftDelete)
                    redactor.plugin.draft.deleteDraft();
            });
            window.location.href='index.php';">
    </p>
  </fieldset>
</form>
  </div>
</div>
