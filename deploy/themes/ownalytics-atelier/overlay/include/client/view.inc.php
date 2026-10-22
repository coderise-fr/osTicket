<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');
/* Ownalytics Atelier theme (deploy/themes/ownalytics-atelier): replaces include/client/view.inc.php.
 * Same data, same reply form (field names, CSRF token, draft, attachments,
 * reopen notice, merged-ticket guard); the information tables become a side
 * column and the thread becomes a conversation (see thread-entry.tmpl.php). */
require_once(INCLUDE_DIR . 'client/ownalytics-atelier/ownalytics-atelier.inc.php');

$info=($_POST && $errors)?Format::htmlchars($_POST):array();

$type = array('type' => 'viewed');
Signal::send('object.view', $ticket, $type);

$dept = $ticket->getDept();

if ($ticket->isClosed() && !$ticket->isReopenable())
    $warn = sprintf(__('%s is marked as closed and cannot be reopened.'), __('This ticket'));

//Making sure we don't leak out internal dept names
if(!$dept || !$dept->isPublic())
    $dept = $cfg->getDefaultDept();

$S = $ticket->getStatus();
$state = $S ? strtolower($S->getState()) : ($ticket->isClosed() ? 'closed' : 'open');
$subject_field = TicketForm::getInstance()->getField('subject');

if ($thisclient && $thisclient->isGuest()
    && $cfg->isClientRegistrationEnabled()) { ?>

<div id="msg_info" class="alert alert-info">
    <i class="icon-compass icon-2x pull-left" aria-hidden="true"></i>
    <strong><?php echo __('Looking for your other tickets?'); ?></strong><br />
    <a href="<?php echo ROOT_PATH; ?>login.php?e=<?php
        echo urlencode($thisclient->getEmail());
    ?>" style="text-decoration:underline"><?php echo __('Sign In'); ?></a>
    <?php echo sprintf(__('or %s register for an account %s for the best experience on our help desk.'),
        '<a href="account.php?do=create" style="text-decoration:underline">','</a>'); ?>
    </div>

<?php } ?>

<div id="ticketInfo" class="page-head ticket-head">
<?php if (!$thisclient->isGuest()) { ?>
    <a class="back" href="tickets.php"><i class="icon-arrow-left" aria-hidden="true"></i> <?php
        echo __('Show all tickets'); ?></a>
<?php } ?>
    <p class="num"><?php echo sprintf(__('Ticket #%s'), $ticket->getNumber()); ?></p>
    <h1 class="display h-ticket"><?php echo $subject_field->display($ticket->getSubject()); ?></h1>
    <div class="meta">
        <span class="pill pill-<?php echo $state == 'closed' ? 'closed' : 'open'; ?>"><?php
            echo $S ? $S->getLocalName() : ''; ?></span>
        <span class="muted"><i class="icon-calendar" aria-hidden="true"></i>
            <span class="visually-hidden"><?php echo __('Create Date'); ?></span>
            <?php echo Format::datetime($ticket->getCreateDate()); ?></span>
    </div>
    <div class="ticket-actions">
        <a class="action-button" href="tickets.php?id=<?php echo $ticket->getId(); ?>"><i class="icon-refresh" aria-hidden="true"></i> <?php echo __('Reload'); ?></a>
        <a class="action-button" href="tickets.php?a=print&id=<?php
          echo $ticket->getId(); ?>"><i class="icon-print" aria-hidden="true"></i> <?php echo __('Print'); ?></a>
<?php if ($ticket->hasClientEditableFields()
        // Only ticket owners can edit the ticket details (and other forms)
        && $thisclient->getId() == $ticket->getUserId()) { ?>
        <a class="action-button" href="tickets.php?a=edit&id=<?php
             echo $ticket->getId(); ?>"><i class="icon-edit" aria-hidden="true"></i> <?php echo __('Edit'); ?></a>
<?php } ?>
    </div>
</div>

<div class="split ticket-split">
<div class="stack ticket-main">
  <?php
    $email = $thisclient->getUserName();
    $clientId = TicketUser::lookupByEmail($email)->getId();

    $ticket->getThread()->render(array('M', 'R', 'user_id' => $clientId), array(
                    'mode' => Thread::MODE_CLIENT,
                    'html-id' => 'ticketThread')
                );
if ($blockReply = $ticket->isChild() && $ticket->getMergeType() != 'visual')
    $warn = sprintf(__('This Ticket is Merged into another Ticket. Please go to the %s%d%s to reply.'),
        '<a href="tickets.php?id=', $ticket->getPid(), '" style="text-decoration:underline">Parent</a>');
  ?>

<?php
// As in the original view: messages repeated next to the reply form (the
// form posts back to #reply, so this is where the reader lands).
if($errors['err']) { ?>
    <div class="alert alert-error" role="alert"><?php echo $errors['err']; ?></div>
<?php }elseif($msg) { ?>
    <div class="alert alert-notice" role="status"><?php echo $msg; ?></div>
<?php }elseif($warn) { ?>
    <div class="alert alert-warning" role="alert"><?php echo $warn; ?></div>
<?php }
if ((!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply) { ?>
<form id="reply" class="card reply" action="tickets.php?id=<?php echo $ticket->getId();
?>#reply" name="reply" method="post" enctype="multipart/form-data">
    <?php csrf_token(); ?>
    <h2 class="card-title"><span class="avatar av-sun sm" aria-hidden="true"><?php
        echo Format::htmlchars(oa_atelier_initials($thisclient->getName())); ?></span> <?php echo __('Post a Reply');?></h2>
    <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
    <input type="hidden" name="a" value="reply">
    <div class="field<?php if ($errors['message']) echo ' has-error'; ?>">
        <label class="lbl" for="message" id="message-help"><?php
         echo __('To best assist you, we request that you be specific and detailed'); ?>
            <span class="req" aria-hidden="true">*</span></label>
<?php if ($errors['message']) { ?>
        <p class="err" id="message-err"><?php echo $errors['message']; ?></p>
<?php } ?>
        <textarea name="<?php echo $messageField->getFormName(); ?>" id="message" cols="50" rows="9" wrap="soft"
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
    <p class="buttons actions">
        <input class="btn btn-primary" type="submit" value="<?php echo __('Post Reply');?>">
        <input class="btn btn-ghost" type="reset" value="<?php echo __('Reset');?>">
        <input class="btn btn-link" type="button" value="<?php echo __('Cancel');?>" onClick="history.go(-1)">
    </p>
</form>
<?php
} ?>
</div>

<aside class="side stack ticket-side" aria-label="<?php echo Format::htmlchars(__('Basic Ticket Information')); ?>">
    <div class="card">
        <h2 class="card-title"><?php echo __('Basic Ticket Information'); ?></h2>
        <dl class="kv">
            <div><dt><?php echo __('Ticket Status');?></dt>
                <dd><span class="pill pill-<?php echo $state == 'closed' ? 'closed' : 'open'; ?>"><?php
                    echo $S ? $S->getLocalName() : ''; ?></span></dd></div>
            <div><dt><?php echo __('Department');?></dt>
                <dd><?php echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></dd></div>
            <div><dt><?php echo __('Create Date');?></dt>
                <dd><?php echo Format::datetime($ticket->getCreateDate()); ?></dd></div>
<?php if ($ticket->getLastResponseDate()) { ?>
            <div><dt><?php echo __('Last Response');?></dt>
                <dd><?php echo Format::datetime($ticket->getLastResponseDate()); ?></dd></div>
<?php } ?>
        </dl>
    </div>
    <div class="card">
        <h2 class="card-title"><?php echo __('User Information'); ?></h2>
        <dl class="kv">
            <div><dt><?php echo __('Name');?></dt>
                <dd><?php echo mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE); ?></dd></div>
            <div><dt><?php echo __('Email');?></dt>
                <dd><?php echo Format::htmlchars($ticket->getEmail()); ?></dd></div>
<?php if ($ticket->getPhoneNumber()) { ?>
            <div><dt><?php echo __('Phone');?></dt>
                <dd><?php echo $ticket->getPhoneNumber(); ?></dd></div>
<?php } ?>
        </dl>
    </div>
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
    <div class="card custom-data">
        <h2 class="card-title"><?php echo $forms[$i]; ?></h2>
        <dl class="kv">
<?php foreach ($answers as $A) {
    list($v, $a) = $A; ?>
            <div><dt><?php
echo $a->getField()->get('label');
            ?></dt>
            <dd><?php
echo $v;
            ?></dd></div>
<?php } ?>
        </dl>
    </div>
    <?php
}

// Every file attached to the visible part of the thread (same visibility
// rule as the thread above: messages and responses, or entries posted by
// this user), most recent first.
$oa_atelier_files = Attachment::objects()
    ->filter(array(
        'thread_entry__thread_id' => $ticket->getThreadId(),
        'inline' => 0,
    ))
    ->filter(Q::any(array(
        'thread_entry__type__in' => array('M', 'R'),
        'thread_entry__user_id' => $clientId,
    )))
    ->order_by('-id');
if ($oa_atelier_files->exists(true)) { ?>
    <div class="card files-card">
        <h2 class="card-title"><?php echo __('Attachments'); ?> <span class="count"><?php
            echo $oa_atelier_files->count(); ?></span></h2>
        <ul class="files">
<?php foreach ($oa_atelier_files as $A) { ?>
            <li><i class="icon-paperclip icon-flip-horizontal" aria-hidden="true"></i>
                <a class="no-pjax filename" href="<?php echo $A->file->getDownloadUrl(['id' => $A->getId()]);
                    ?>" download="<?php echo Format::htmlchars($A->getFilename()); ?>" target="_blank"><?php
                    echo Format::htmlchars($A->getFilename()); ?></a>
<?php   if ($A->file->size) { ?>
                <small><?php echo Format::file_size($A->file->size); ?></small>
<?php   } ?>
            </li>
<?php } ?>
        </ul>
    </div>
<?php } ?>
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
