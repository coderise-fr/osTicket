<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');
/* Sommet theme (deploy/themes/sommet): replaces include/client/view.inc.php.
 * Same data, checks, signals, reply form (CSRF token, draft, attachments,
 * reopen warning, merged tickets) and inline images script as the original.
 * New layout: title band (subject, status, number, dates, actions, and the
 * "first reply" indicator of the Priority SLA plan when it applies), then
 * the thread and the reply form, with the ticket information on the side.
 * Support contract badge (band and side column): optional, see
 * sommet_contract() in include/client/sommet/sommet.inc.php. */

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
$sommet_state = $ticket->isClosed() ? 'closed' : 'open';
$sommet_sla = sommet_priority_sla($ticket);
// Contract level of the ticket OWNER (never a collaborator), current level;
// null without the sla_first_response plugin (nothing shown). Once per page.
$sommet_contract = sommet_contract($ticket);
?>
<!--sommet:bandeau-->
<h1 id="sommet-title" class="ticket-subject"><?php
    $subject_field = TicketForm::getInstance()->getField('subject');
    echo $subject_field->display($ticket->getSubject()); ?></h1>
<div class="sub">
    <span class="status status-<?php echo $sommet_state; ?>"><?php
        echo sommet_icon($sommet_state == 'closed' ? 'lock' : 'inbox', 14);
        echo $S ? $S->getLocalName() : ''; ?></span>
<?php if ($sommet_contract) echo '    ', sommet_contract_badge($sommet_contract), "\n"; ?>
    <span class="num">#<?php echo $ticket->getNumber(); ?></span>
    <span class="since"><?php echo sommet_icon('clock', 15, __('Create Date'));
        ?> <time datetime="<?php echo date(DateTime::W3C, Misc::db2gmtime($ticket->getCreateDate()));
        ?>"><?php echo Format::datetime($ticket->getCreateDate()); ?></time></span>
    <span class="ticket-actions">
        <a class="action-button" href="tickets.php?id=<?php echo $ticket->getId(); ?>" title="<?php echo __('Reload'); ?>"><?php
            echo sommet_icon('refresh', 16); ?><span class="visually-hidden"><?php echo __('Reload'); ?></span></a>
        <a class="action-button" href="tickets.php?a=print&id=<?php
            echo $ticket->getId(); ?>"><?php echo sommet_icon('print', 16); ?> <?php echo __('Print'); ?></a>
<?php if ($ticket->hasClientEditableFields()
        // Only ticket owners can edit the ticket details (and other forms)
        && $thisclient->getId() == $ticket->getUserId()) { ?>
        <a class="action-button" href="tickets.php?a=edit&id=<?php
             echo $ticket->getId(); ?>"><?php echo sommet_icon('edit', 16); ?> <?php echo __('Edit'); ?></a>
<?php } ?>
    </span>
</div>
<?php if ($sommet_sla) {
    $sommet_left_h = intdiv($sommet_sla['left'], 3600);
    $sommet_left_m = intdiv($sommet_sla['left'] % 3600, 60); ?>
<div class="altimeter<?php if ($sommet_sla['overdue']) echo ' is-overdue'; ?>" role="group"
    aria-label="<?php echo Format::htmlchars(__s('Priority support: time to the first reply')); ?>">
    <div class="alt-head">
        <strong><?php echo sprintf(__s('First reply within %d h'), $sommet_sla['hours']); ?></strong>
        <span><?php
        if ($sommet_sla['overdue'])
            echo __s('Target time passed. Your ticket stays at the top of our list.');
        else
            echo sprintf(__s('Expected before %s (%d h %02d min left)'),
                Format::datetime($sommet_sla['due']), $sommet_left_h, $sommet_left_m); ?></span>
    </div>
    <div class="alt-bar" role="img" aria-label="<?php echo Format::htmlchars(sprintf(
        __s('%s %% of the time elapsed'), $sommet_sla['pct'])); ?>"><span class="fill" style="width:<?php
        echo $sommet_sla['pct']; ?>%"></span><span class="flag" style="left:<?php echo $sommet_sla['pct']; ?>%"></span></div>
    <ol class="alt-scale">
        <li class="start"><?php echo __s('Received'); ?><small><?php echo Format::datetime($sommet_sla['start']); ?></small></li>
        <li class="end"><?php echo __s('Target'); ?><small><?php echo Format::datetime($sommet_sla['due']); ?></small></li>
    </ol>
</div>
<?php } ?>
<!--/sommet:bandeau-->
<?php
if ($thisclient && $thisclient->isGuest()
    && $cfg->isClientRegistrationEnabled()) { ?>

<div id="msg_info">
    <?php echo sommet_icon('user', 22); ?>
    <strong><?php echo __('Looking for your other tickets?'); ?></strong><br />
    <a href="<?php echo ROOT_PATH; ?>login.php?e=<?php
        echo urlencode($thisclient->getEmail());
    ?>" style="text-decoration:underline"><?php echo __('Sign In'); ?></a>
    <?php echo sprintf(__('or %s register for an account %s for the best experience on our help desk.'),
        '<a href="account.php?do=create" style="text-decoration:underline">','</a>'); ?>
    </div>

<?php } ?>

<div class="ticket-grid" id="ticketInfo">
<section class="journal" aria-label="<?php echo Format::htmlchars(__s('Conversation')); ?>">
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

<div class="clear"></div>
<?php if($errors['err']) { ?>
    <div id="msg_error" role="alert"><?php echo $errors['err']; ?></div>
<?php }elseif($msg) { ?>
    <div id="msg_notice" role="status"><?php echo $msg; ?></div>
<?php }elseif($warn) { ?>
    <div id="msg_warning" role="status"><?php echo $warn; ?></div>
<?php }
if ((!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply) { ?>
<form id="reply" class="reply" action="tickets.php?id=<?php echo $ticket->getId();
?>#reply" name="reply" method="post" enctype="multipart/form-data">
    <?php csrf_token(); ?>
    <h2><?php echo __('Post a Reply');?></h2>
    <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
    <input type="hidden" name="a" value="reply">
    <div>
        <p class="hint"><label for="message"><?php
         echo __('To best assist you, we request that you be specific and detailed'); ?></label>
        <span class="error" id="message-error">*&nbsp;<?php echo $errors['message']; ?></span>
        </p>
        <textarea name="<?php echo $messageField->getFormName(); ?>" id="message" cols="50" rows="9" wrap="soft"
            aria-describedby="message-error"
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
    <p class="buttons">
        <input type="submit" value="<?php echo __('Post Reply');?>">
        <input type="reset" value="<?php echo __('Reset');?>">
        <input type="button" value="<?php echo __('Cancel');?>" onClick="history.go(-1)">
    </p>
</form>
<?php
} ?>
</section>

<aside class="side" aria-label="<?php echo Format::htmlchars(__('Basic Ticket Information')); ?>">
    <h2><?php echo __('Basic Ticket Information'); ?></h2>
    <dl class="props infoTable">
        <div><dt><?php echo __('Ticket Status');?></dt>
            <dd><span class="status status-<?php echo $sommet_state; ?>"><?php
                echo sommet_icon($sommet_state == 'closed' ? 'lock' : 'inbox', 14);
                echo $S ? $S->getLocalName() : ''; ?></span></dd></div>
        <div><dt><?php echo __('Department');?></dt>
            <dd><?php echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></dd></div>
        <div><dt><?php echo __('Create Date');?></dt>
            <dd><?php echo Format::datetime($ticket->getCreateDate()); ?></dd></div>
    </dl>
    <h2><?php echo __('User Information'); ?></h2>
    <dl class="props infoTable">
        <div><dt><?php echo __('Name');?></dt>
            <dd><?php echo mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE); ?></dd></div>
        <div><dt><?php echo __('Email');?></dt>
            <dd><?php echo Format::htmlchars($ticket->getEmail()); ?></dd></div>
<?php if ($ticket->getPhoneNumber()) { ?>
        <div><dt><?php echo __('Phone');?></dt>
            <dd><?php echo $ticket->getPhoneNumber(); ?></dd></div>
<?php } ?>
<?php if ($sommet_contract) { ?>
        <div class="contract"><dt><?php echo __s('Support contract (current)'); ?></dt>
            <dd><?php echo sommet_contract_badge($sommet_contract, false); ?></dd></div>
<?php } ?>
    </dl>
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
    <h2><?php echo $forms[$i]; ?></h2>
    <dl class="props custom-data">
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
    <?php
} ?>
    <div class="call">
        <h2><?php echo __s('A quick word?'); ?></h2>
        <p><?php echo sprintf(__s('Call us and quote ticket #%s.'), $ticket->getNumber()); ?></p>
        <a class="btn btn-line" href="tel:+33669939286"><?php echo sommet_icon('phone'); ?><span>+33 (0)6 69 93 92 86</span></a>
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
