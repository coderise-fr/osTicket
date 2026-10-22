<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');
/* Theme "Poste de travail" (Coderise) - overrides include/client/view.inc.php.
 * Same data, form, field names and actions as the core template; the ticket
 * information tables become a sticky properties panel next to the thread.
 * Additions, all from real data: message / attachment counts, the list of
 * the attachments, and the optional "Support Priority" first response
 * indicator (ptdt_priority_sla(), to be validated). */

$info=($_POST && $errors)?Format::htmlchars($_POST):array();

$type = array('type' => 'viewed');
Signal::send('object.view', $ticket, $type);

$dept = $ticket->getDept();

if ($ticket->isClosed() && !$ticket->isReopenable())
    $warn = sprintf(__('%s is marked as closed and cannot be reopened.'), __('This ticket'));

//Making sure we don't leak out internal dept names
if(!$dept || !$dept->isPublic())
    $dept = $cfg->getDefaultDept();

if ($thisclient && $thisclient->isGuest()
    && $cfg->isClientRegistrationEnabled()) { ?>

<div id="msg_info" class="alert alert-info">
    <strong><?php echo __('Looking for your other tickets?'); ?></strong>
    <a href="<?php echo ROOT_PATH; ?>login.php?e=<?php
        echo urlencode($thisclient->getEmail());
    ?>"><?php echo __('Sign In'); ?></a>
    <?php echo sprintf(__('or %s register for an account %s for the best experience on our help desk.'),
        '<a href="account.php?do=create">','</a>'); ?>
</div>

<?php }

$subject_field = TicketForm::getInstance()->getField('subject');

// Counts shown in the thread header (entries the client can see: messages, responses)
$ptdt_entries = ThreadEntry::objects()->filter(array(
    'thread_id' => $ticket->getThreadId(), 'type__in' => array('M', 'R')));
$ptdt_n_entries = $ptdt_entries->count();
$ptdt_n_responses = ThreadEntry::objects()->filter(array(
    'thread_id' => $ticket->getThreadId(), 'type' => 'R'))->count();
// Files attached to the messages and responses the client can see (no notes, no inline images)
$ptdt_files = Attachment::objects()
    ->filter(array(
        'thread_entry__thread__id' => $ticket->getThreadId(),
        'thread_entry__type__in' => array('M', 'R'),
        'inline' => 0,
    ))
    ->select_related('file');
$ptdt_n_files = count($ptdt_files);
$ptdt_sla = ptdt_priority_sla($ticket, $ptdt_n_responses);
$blockReply = $ticket->isChild() && $ticket->getMergeType() != 'visual';
$ptdt_can_reply = (!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply;
$ptdt_is_open = $ticket->isOpen();
?>
<div class="t-head" id="ticketInfo">
    <p class="idline"><span>#<?php echo $ticket->getNumber(); ?></span>
        <span class="status status-<?php echo $ptdt_is_open ? 'open' : 'closed'; ?>"><?php
            echo ptdt_icon($ptdt_is_open ? 'open' : 'closed', 14); ?><?php
            echo ($S = $ticket->getStatus()) ? $S->getLocalName() : ''; ?></span>
    </p>
    <h1><?php echo $subject_field->display($ticket->getSubject()); ?></h1>
    <div class="t-actions">
<?php if ($ptdt_can_reply) { ?>
        <a class="btn btn-primary" href="#reply"><?php echo ptdt_icon('message'); ?><span><?php echo __('Post a Reply'); ?></span></a>
<?php } ?>
        <a class="btn btn-line action-button" href="tickets.php?a=print&id=<?php
            echo $ticket->getId(); ?>"><?php echo ptdt_icon('print'); ?><span><?php echo __('Print'); ?></span></a>
<?php if ($ticket->hasClientEditableFields()
        // Only ticket owners can edit the ticket details (and other forms)
        && $thisclient->getId() == $ticket->getUserId()) { ?>
        <a class="btn btn-line action-button" href="tickets.php?a=edit&id=<?php
            echo $ticket->getId(); ?>"><?php echo ptdt_icon('edit'); ?><span><?php echo __('Edit'); ?></span></a>
<?php } ?>
        <a class="btn btn-line action-button" href="tickets.php?id=<?php echo $ticket->getId(); ?>"><?php
            echo ptdt_icon('refresh'); ?><span><?php echo __('Reload'); ?></span></a>
    </div>
</div>
<div class="cols cols-ticket">
<div class="col-main">
<section class="panel thread-panel" aria-labelledby="thread-t">
    <div class="panel-h"><h2 id="thread-t"><?php echo __('Ticket Thread'); ?></h2>
        <span class="muted"><?php echo Format::htmlchars(sprintf(ptdt__('%1$d messages · %2$d attachments'),
            $ptdt_n_entries, $ptdt_n_files)); ?></span></div>
  <?php
    $email = $thisclient->getUserName();
    $clientId = TicketUser::lookupByEmail($email)->getId();

    $ticket->getThread()->render(array('M', 'R', 'user_id' => $clientId), array(
                    'mode' => Thread::MODE_CLIENT,
                    'html-id' => 'ticketThread')
                );
if ($blockReply)
    $warn = sprintf(__('This Ticket is Merged into another Ticket. Please go to the %s%d%s to reply.'),
        '<a href="tickets.php?id=', $ticket->getPid(), '">Parent</a>');
  ?>
</section>

<?php if($errors['err']) { ?>
    <div id="msg_error" class="alert alert-error" role="alert"><?php echo $errors['err']; ?></div>
<?php }elseif($msg) { ?>
    <div id="msg_notice" class="alert alert-notice" role="status"><?php echo $msg; ?></div>
<?php }elseif($warn) { ?>
    <div id="msg_warning" class="alert alert-warning" role="alert"><?php echo $warn; ?></div>
<?php }
if ($ptdt_can_reply) { ?>
<form id="reply" class="panel form reply" action="tickets.php?id=<?php echo $ticket->getId();
?>#reply" name="reply" method="post" enctype="multipart/form-data">
    <?php csrf_token(); ?>
    <div class="panel-h"><h2><label for="message"><?php echo __('Post a Reply');?></label></h2></div>
    <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
    <input type="hidden" name="a" value="reply">
    <div class="f-body">
        <p class="hint" id="reply-help"><?php
         echo __('To best assist you, we request that you be specific and detailed'); ?>
        <span class="req" aria-hidden="true">*</span></p>
<?php if ($errors['message']) { ?>
        <p class="error" id="reply-err"><?php echo ptdt_icon('alert', 16); ?><span><?php echo $errors['message']; ?></span></p>
<?php } ?>
        <textarea name="<?php echo $messageField->getFormName(); ?>" id="message" cols="50" rows="9" wrap="soft"
            aria-describedby="reply-help<?php if ($errors['message']) echo ' reply-err'; ?>"
            class="<?php if ($cfg->isRichTextEnabled()) echo 'richtext';
                ?> draft" <?php
list($draft, $attrs) = Draft::getDraftAndDataAttrs('ticket.client', $ticket->getId(), $info['message']);
echo $attrs; ?>><?php echo $draft ?: $info['message'];
            ?></textarea>
    <?php
    if ($messageField->isAttachmentsEnabled()) {
        print $attachments->render(array('client'=>true));
    } ?>
    </div>
<?php
  if ($ticket->isClosed() && $ticket->isReopenable()) { ?>
    <div class="warning-banner">
        <?php echo __('Ticket will be reopened on message post'); ?>
    </div>
<?php } ?>
    <p class="form-actions buttons">
        <input type="submit" class="btn btn-primary" value="<?php echo __('Post Reply');?>">
        <input type="reset" class="btn btn-line" value="<?php echo __('Reset');?>">
        <input type="button" class="btn btn-link" value="<?php echo __('Cancel');?>" onClick="history.go(-1)">
    </p>
</form>
<?php
} ?>
</div>
<aside class="col-side sticky" aria-label="<?php echo __('Basic Ticket Information'); ?>">
<?php if ($ptdt_sla) {
    list($ptdt_start, $ptdt_due, $ptdt_ratio, $ptdt_late) = $ptdt_sla; ?>
    <section class="panel sla" aria-labelledby="sla-t">
        <div class="panel-h"><h2 id="sla-t"><?php echo ptdt__('Priority first response'); ?></h2></div>
        <div class="pad">
            <p class="sla-title"><?php echo ptdt_icon('clock'); ?><span><?php echo ptdt__('First response within 4 h (Priority, 24/7)'); ?></span></p>
            <div class="sla-bar" role="img" aria-label="<?php echo Format::htmlchars(sprintf(ptdt__('%d%% of the time elapsed'), round($ptdt_ratio * 100))); ?>"><span class="sla-fill" style="width:<?php
                echo round($ptdt_ratio * 100, 1); ?>%"></span></div>
            <p class="sla-text"><strong><?php echo ptdt__('Expected by'); ?></strong> <?php echo Format::datetime($ptdt_due); ?>.
                <?php echo ptdt__('The team has not answered yet.'); ?></p>
        </div>
    </section>
<?php } ?>
    <section class="panel" aria-labelledby="prop-t">
        <div class="panel-h"><h2 id="prop-t"><?php echo __('Basic Ticket Information'); ?></h2></div>
        <dl class="props infoTable">
            <div><dt><?php echo __('Ticket #'); ?></dt><dd>#<?php echo $ticket->getNumber(); ?></dd></div>
            <div><dt><?php echo __('Ticket Status');?></dt><dd><?php
                echo ($S = $ticket->getStatus()) ? $S->getLocalName() : ''; ?></dd></div>
            <div><dt><?php echo __('Department');?></dt><dd><?php
                echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></dd></div>
<?php if ($ptdt_topic = $ticket->getHelpTopic()) { ?>
            <div><dt><?php echo __('Help Topic');?></dt><dd><?php echo Format::htmlchars($ptdt_topic); ?></dd></div>
<?php } ?>
            <div><dt><?php echo __('Create Date');?></dt><dd><?php
                echo Format::datetime($ticket->getCreateDate()); ?></dd></div>
<?php if ($ticket->getEffectiveDate()) { ?>
            <div><dt><?php echo __('Last Updated');?></dt><dd><?php
                echo Format::datetime($ticket->getEffectiveDate()); ?></dd></div>
<?php } ?>
        </dl>
    </section>
<!-- Custom Data -->
<?php
$sections = $forms = array();
foreach (DynamicFormEntry::forTicket($ticket->getId()) as $i=>$form) {
    // Skip core fields shown earlier in the ticket view
    $answers = $form->getAnswers()->exclude(Q::any(array(
        'field__flags__hasbit' => DynamicFormField::FLAG_EXT_STORED,
        'field__name__in' => array('subject', 'priority'),
        Q::not(array('field__flags__hasbit' => DynamicFormField::FLAG_CLIENT_VIEW)),
    )));
    // Skip display of forms without any answers
    foreach ($answers as $j=>$a) {
        if ($v = $a->display())
            $sections[$i][$j] = array($v, $a);
    }
    // Set form titles
    $forms[$i] = $form->getTitle();
}
foreach ($sections as $i=>$answers) {
    ?>
    <section class="panel custom-data" aria-labelledby="cd-<?php echo $i; ?>">
        <div class="panel-h"><h2 id="cd-<?php echo $i; ?>"><?php echo $forms[$i]; ?></h2></div>
        <dl class="props">
<?php foreach ($answers as $A) {
    list($v, $a) = $A; ?>
            <div><dt><?php echo $a->getField()->get('label'); ?></dt><dd><?php echo $v; ?></dd></div>
<?php } ?>
        </dl>
    </section>
<?php
}

if ($ptdt_n_files) { ?>
    <section class="panel" aria-labelledby="files-t">
        <div class="panel-h"><h2 id="files-t"><?php echo __('Attachments'); ?></h2><span class="muted"><?php echo $ptdt_n_files; ?></span></div>
        <ul class="files-list">
<?php foreach ($ptdt_files as $A) { ?>
            <li><?php echo ptdt_icon('clip', 16); ?><a class="no-pjax" href="<?php
                echo $A->file->getDownloadUrl(['id' => $A->getId()]); ?>" download="<?php
                echo Format::htmlchars($A->getFilename()); ?>"><?php echo Format::htmlchars($A->getFilename()); ?></a><?php
                if ($A->file->size) { ?><small><?php echo Format::file_size($A->file->size); ?></small><?php } ?></li>
<?php } ?>
        </ul>
    </section>
<?php } ?>
    <section class="panel" aria-labelledby="user-t">
        <div class="panel-h"><h2 id="user-t"><?php echo __('User Information'); ?></h2></div>
        <dl class="props infoTable">
            <div><dt><?php echo __('Name');?></dt><dd><?php echo mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE); ?></dd></div>
            <div><dt><?php echo __('Email');?></dt><dd><?php echo Format::htmlchars($ticket->getEmail()); ?></dd></div>
<?php if ($ticket->getPhoneNumber()) { ?>
            <div><dt><?php echo __('Phone');?></dt><dd><?php echo $ticket->getPhoneNumber(); ?></dd></div>
<?php } ?>
        </dl>
    </section>
</aside>
</div>
<script type="text/javascript">
<?php
// Hover support for all inline images
$urls = array();
foreach (AttachmentFile::objects()->filter(array(
    'attachments__thread_entry__thread__id' => $ticket->getThreadId(),
    'attachments__inline' => true,
)) as $file) {
    $urls[strtolower($file->getKey())] = array(
        'download_url' => $file->getDownloadUrl(['type' => 'H']),
        'filename' => $file->name,
    );
} ?>
showImagesInline(<?php echo JsonDataEncoder::encode($urls); ?>);
</script>
