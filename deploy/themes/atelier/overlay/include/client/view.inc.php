<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');
/* Atelier theme (deploy/themes/atelier): replaces include/client/view.inc.php.
 * Same data, same reply form (field names, CSRF token, draft, attachments,
 * reopen notice, merged-ticket guard); the information tables become a side
 * column and the thread becomes a conversation (see thread-entry.tmpl.php). */
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');

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

<div id="ticketInfo" class="page-head band ticket-head">
<?php if (!$thisclient->isGuest()) { ?>
    <nav class="crumbs" aria-label="<?php echo Format::htmlchars(__a('Breadcrumb')); ?>"><ol>
        <li><a href="tickets.php"><?php echo __('Tickets'); ?></a></li>
        <li><span aria-current="page"><?php echo sprintf(__('Ticket #%s'), $ticket->getNumber()); ?></span></li>
    </ol></nav>
<?php } else { ?>
    <p class="num"><?php echo sprintf(__('Ticket #%s'), $ticket->getNumber()); ?></p>
<?php } ?>
    <h1 class="display h-ticket"><?php echo $subject_field->display($ticket->getSubject()); ?></h1>
    <div class="meta">
        <span class="status status-<?php echo $state == 'closed' ? 'closed' : 'open'; ?>"><?php
            echo atelier_icon($state == 'closed' ? 'lock' : 'inbox', 14);
            echo $S ? $S->getLocalName() : ''; ?></span>
<?php if (atelier_is_priority($ticket)) { ?>
        <span class="plan plan-priority"><?php echo atelier_icon('bolt', 13); ?>Priority</span>
<?php } ?>
        <span class="when"><span class="visually-hidden"><?php echo __('Create Date'); ?> </span><?php
            echo Format::datetime($ticket->getCreateDate()); ?></span>
    </div>
    <div class="ticket-actions">
        <a class="action-button" href="tickets.php?id=<?php echo $ticket->getId(); ?>"><?php echo atelier_icon('refresh', 16); ?> <?php echo __('Reload'); ?></a>
        <a class="action-button" href="tickets.php?a=print&id=<?php
          echo $ticket->getId(); ?>"><?php echo atelier_icon('print', 16); ?> <?php echo __('Print'); ?></a>
<?php if ($ticket->hasClientEditableFields()
        // Only ticket owners can edit the ticket details (and other forms)
        && $thisclient->getId() == $ticket->getUserId()) { ?>
        <a class="action-button" href="tickets.php?a=edit&id=<?php
             echo $ticket->getId(); ?>"><?php echo atelier_icon('edit', 16); ?> <?php echo __('Edit'); ?></a>
<?php } ?>
    </div>
</div>

<?php
// Progress of the ticket, from real data only (see atelier_progress()).
list($atelier_steps, $atelier_now) = atelier_progress($ticket); ?>
<ol class="ascent-steps" aria-label="<?php echo Format::htmlchars(__a('Ticket progress')); ?>">
<?php foreach ($atelier_steps as $i => $atelier_step) {
    $cls = $atelier_step['done'] ? 'done' : ($i == $atelier_now ? 'now' : 'todo'); ?>
    <li class="<?php echo $cls; ?>"<?php if ($i == $atelier_now) echo ' aria-current="step"'; ?>>
        <?php echo atelier_sommet('mark'); ?>
        <span class="t"><strong><?php echo Format::htmlchars($atelier_step['label']); ?></strong>
        <small><?php
        if ($atelier_step['done'] && $atelier_step['date'])
            echo Format::datetime($atelier_step['date']);
        elseif ($atelier_step['done'])
            echo __a('Done');
        elseif ($i == $atelier_now && $i == 1)
            echo __a('We are reading your request');
        elseif (!empty($atelier_step['wait']))
            echo __a('In progress');
        else
            echo __a('To come'); ?></small></span>
    </li>
<?php } ?>
</ol>

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
    <h2 class="card-title"><span class="avatar av-you sm" aria-hidden="true"><?php
        echo Format::htmlchars(atelier_initials($thisclient->getName())); ?></span> <?php echo __('Post a Reply');?></h2>
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
<?php
// Optional "Priority" indicator (to be validated, see README.md): only for an
// open, unanswered ticket on the active Priority plan, before its due date.
if ($atelier_sla = atelier_priority($ticket)) { ?>
    <div class="card sla" role="group" aria-labelledby="sla-title">
        <p class="sla-title" id="sla-title"><?php echo atelier_icon('clock'); ?><span><?php
            echo __a('Priority support: first reply within 4 h, 24/7'); ?></span></p>
        <div class="sla-bar" role="img" aria-label="<?php echo Format::htmlchars(sprintf(
            __a('%s%% of the time elapsed'), (int) round($atelier_sla['pct']))); ?>"><span class="sla-fill" style="width:<?php
            echo $atelier_sla['pct']; ?>%"></span></div>
        <p class="sla-text"><?php echo sprintf(__a('First reply expected by %s at the latest.'),
            '<strong>' . Format::datetime($atelier_sla['due']) . '</strong>'); ?></p>
    </div>
<?php } ?>
    <div class="card">
        <h2 class="card-title"><?php echo __('Basic Ticket Information'); ?></h2>
        <dl class="kv">
            <div><dt><?php echo __('Ticket Status');?></dt>
                <dd><span class="status status-<?php echo $state == 'closed' ? 'closed' : 'open'; ?>"><?php
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
$atelier_files = Attachment::objects()
    ->filter(array(
        'thread_entry__thread_id' => $ticket->getThreadId(),
        'inline' => 0,
    ))
    ->filter(Q::any(array(
        'thread_entry__type__in' => array('M', 'R'),
        'thread_entry__user_id' => $clientId,
    )))
    ->order_by('-id');
if ($atelier_files->exists(true)) { ?>
    <div class="card files-card">
        <h2 class="card-title"><?php echo __('Attachments'); ?> <span class="count"><?php
            echo $atelier_files->count(); ?></span></h2>
        <ul class="files">
<?php foreach ($atelier_files as $A) { ?>
            <li><?php echo atelier_icon('clip', 15); ?>
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
    <div class="card tip call-card">
        <h2 class="card-title"><?php echo __a('A quick word?'); ?></h2>
        <p><?php echo sprintf(__a('Call us and quote ticket #%s.'), $ticket->getNumber()); ?></p>
        <a class="btn btn-ghost" href="tel:<?php echo ATELIER_PHONE_URI; ?>"><?php
            echo atelier_icon('phone'); ?><?php echo ATELIER_PHONE; ?></a>
    </div>
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
