<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');
/* Theme "Poste de travail" (Ownalytics) - overrides include/client/view.inc.php.
 * Same data, form, field names and actions as the core template; the ticket
 * information tables become a sticky properties panel next to the thread. */

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
?>
<?php if (!$thisclient->isGuest()) { ?>
<nav class="crumbs" aria-label="<?php echo ptdt__('Breadcrumb'); ?>">
    <a href="tickets.php"><?php echo __('Tickets'); ?></a><span aria-hidden="true">/</span><span aria-current="page">#<?php echo $ticket->getNumber(); ?></span>
</nav>
<?php } ?>
<div class="ph t-head" id="ticketInfo">
    <h1><?php echo $subject_field->display($ticket->getSubject()); ?>
        <small class="visually-hidden">#<?php echo $ticket->getNumber(); ?></small></h1>
    <div class="ph-actions">
        <a class="btn action-button" href="tickets.php?a=print&id=<?php
            echo $ticket->getId(); ?>"><?php echo ptdt_icon('print', 15); ?> <?php echo __('Print'); ?></a>
<?php if ($ticket->hasClientEditableFields()
        // Only ticket owners can edit the ticket details (and other forms)
        && $thisclient->getId() == $ticket->getUserId()) { ?>
        <a class="btn action-button" href="tickets.php?a=edit&id=<?php
            echo $ticket->getId(); ?>"><?php echo ptdt_icon('edit', 15); ?> <?php echo __('Edit'); ?></a>
<?php } ?>
        <a class="btn action-button" href="tickets.php?id=<?php echo $ticket->getId(); ?>"><?php
            echo ptdt_icon('refresh', 15); ?> <?php echo __('Reload'); ?></a>
    </div>
</div>
<div class="cols-ticket">
<div class="main-col">
  <?php
    $email = $thisclient->getUserName();
    $clientId = TicketUser::lookupByEmail($email)->getId();

    $ticket->getThread()->render(array('M', 'R', 'user_id' => $clientId), array(
                    'mode' => Thread::MODE_CLIENT,
                    'html-id' => 'ticketThread')
                );
if ($blockReply = $ticket->isChild() && $ticket->getMergeType() != 'visual')
    $warn = sprintf(__('This Ticket is Merged into another Ticket. Please go to the %s%d%s to reply.'),
        '<a href="tickets.php?id=', $ticket->getPid(), '">Parent</a>');
  ?>

<?php if($errors['err']) { ?>
    <div id="msg_error" class="alert alert-error" role="alert"><?php echo $errors['err']; ?></div>
<?php }elseif($msg) { ?>
    <div id="msg_notice" class="alert alert-notice" role="status"><?php echo $msg; ?></div>
<?php }elseif($warn) { ?>
    <div id="msg_warning" class="alert alert-warning" role="alert"><?php echo $warn; ?></div>
<?php }
if ((!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply) { ?>
<form id="reply" class="panel form reply" action="tickets.php?id=<?php echo $ticket->getId();
?>#reply" name="reply" method="post" enctype="multipart/form-data">
    <?php csrf_token(); ?>
    <h2 class="f-sec"><label for="message"><?php echo __('Post a Reply');?></label></h2>
    <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
    <input type="hidden" name="a" value="reply">
    <div class="f-body">
        <p class="help" id="reply-help"><?php
         echo __('To best assist you, we request that you be specific and detailed'); ?>
        <span class="req" aria-hidden="true">*</span></p>
<?php if ($errors['message']) { ?>
        <p class="err" id="reply-err"><?php echo $errors['message']; ?></p>
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
    <p class="f-actions buttons">
        <input type="submit" class="btn btn-primary" value="<?php echo __('Post Reply');?>">
        <input type="reset" class="btn" value="<?php echo __('Reset');?>">
        <input type="button" class="btn btn-link" value="<?php echo __('Cancel');?>" onClick="history.go(-1)">
    </p>
</form>
<?php
} ?>
</div>
<aside class="side" aria-label="<?php echo __('Basic Ticket Information'); ?>">
    <section class="panel" aria-labelledby="prop-t">
        <div class="p-head"><h2 id="prop-t"><?php echo __('Basic Ticket Information'); ?></h2></div>
        <dl class="kv infoTable">
            <div><dt><?php echo __('Ticket #'); ?></dt><dd class="mono">#<?php echo $ticket->getNumber(); ?></dd></div>
            <div><dt><?php echo __('Ticket Status');?></dt><dd><span class="st st-<?php
                echo $ticket->isOpen() ? 'open' : 'closed'; ?>"><?php
                echo ($S = $ticket->getStatus()) ? $S->getLocalName() : ''; ?></span></dd></div>
            <div><dt><?php echo __('Department');?></dt><dd><?php
                echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></dd></div>
            <div><dt><?php echo __('Create Date');?></dt><dd class="mono"><?php
                echo Format::datetime($ticket->getCreateDate()); ?></dd></div>
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
        <div class="p-head"><h2 id="cd-<?php echo $i; ?>"><?php echo $forms[$i]; ?></h2></div>
        <dl class="kv">
<?php foreach ($answers as $A) {
    list($v, $a) = $A; ?>
            <div><dt><?php echo $a->getField()->get('label'); ?></dt><dd><?php echo $v; ?></dd></div>
<?php } ?>
        </dl>
    </section>
<?php
}

// Files attached to the messages and responses the client can see (no notes, no inline images)
$ptdt_files = Attachment::objects()
    ->filter(array(
        'thread_entry__thread__id' => $ticket->getThreadId(),
        'thread_entry__type__in' => array('M', 'R'),
        'inline' => 0,
    ))
    ->select_related('file');
if (count($ptdt_files)) { ?>
    <section class="panel" aria-labelledby="files-t">
        <div class="p-head"><h2 id="files-t"><?php echo __('Attachments'); ?></h2><span class="muted"><?php echo count($ptdt_files); ?></span></div>
        <ul class="files">
<?php foreach ($ptdt_files as $A) { ?>
            <li><?php echo ptdt_icon('clip', 14); ?><a class="no-pjax" href="<?php
                echo $A->file->getDownloadUrl(['id' => $A->getId()]); ?>" download="<?php
                echo Format::htmlchars($A->getFilename()); ?>"><?php echo Format::htmlchars($A->getFilename()); ?></a><?php
                if ($A->file->size) { ?><small><?php echo Format::file_size($A->file->size); ?></small><?php } ?></li>
<?php } ?>
        </ul>
    </section>
<?php } ?>
    <section class="panel" aria-labelledby="user-t">
        <div class="p-head"><h2 id="user-t"><?php echo __('User Information'); ?></h2></div>
        <dl class="kv infoTable">
            <div><dt><?php echo __('Name');?></dt><dd><?php echo mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE); ?></dd></div>
            <div><dt><?php echo __('Email');?></dt><dd><?php echo Format::htmlchars($ticket->getEmail()); ?></dd></div>
<?php if ($ticket->getPhoneNumber()) { ?>
            <div><dt><?php echo __('Phone');?></dt><dd class="mono"><?php echo $ticket->getPhoneNumber(); ?></dd></div>
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
