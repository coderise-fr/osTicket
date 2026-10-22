<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');
/* Theme "Parcours" (Coderise) - overrides include/client/view.inc.php.
 * Same access check, signal, thread rendering, reply form (same fields,
 * names, draft, attachments, CSRF), messages and inline images as the core
 * template. Added from real ticket data only: progress line (created, first
 * reply of an agent, closed), "your next step" (state + isanswered), and
 * the Priority first-reply gauge when the SLA plan allows it
 * (pcrs_sla_gauge(), see include/client/parcours/parcours.inc.php). */

$info=($_POST && $errors)?Format::htmlchars($_POST):array();

$type = array('type' => 'viewed');
Signal::send('object.view', $ticket, $type);

$dept = $ticket->getDept();

if ($ticket->isClosed() && !$ticket->isReopenable())
    $warn = sprintf(__('%s is marked as closed and cannot be reopened.'), __('This ticket'));

//Making sure we don't leak out internal dept names
if(!$dept || !$dept->isPublic())
    $dept = $cfg->getDefaultDept();

if ($blockReply = $ticket->isChild() && $ticket->getMergeType() != 'visual')
    $warn = sprintf(__('This Ticket is Merged into another Ticket. Please go to the %s%d%s to reply.'),
        '<a href="tickets.php?id=', $ticket->getPid(), '" style="text-decoration:underline">Parent</a>');

$pcrs_can_reply = ((!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply);
$pcrs_state = $ticket->isClosed() ? 'closed' : 'open';
list($pcrs_k, $pcrs_title, $pcrs_text) = pcrs_next_step($pcrs_state, $ticket->isAnswered(),
    $ticket->isReopenable() && !$blockReply);
$pcrs_first = pcrs_first_response($ticket);
$pcrs_gauge = pcrs_sla_gauge($ticket);
$pcrs_subject_field = TicketForm::getInstance()->getField('subject');
$pcrs_subject = $pcrs_subject_field->display($ticket->getSubject());
// Progress line: received -> first reply -> closed (dates from the ticket)
$pcrs_steps = array(
    array(__('Created'), Format::datetime($ticket->getCreateDate()), 'done'),
    array(pcrs__('First reply'), $pcrs_first ? Format::datetime($pcrs_first) : pcrs__('Waiting for the team'),
        $pcrs_first ? 'done' : ($pcrs_state == 'open' ? 'current' : 'todo')),
    array(__('Closed'), $ticket->isClosed() ? Format::datetime($ticket->getCloseDate()) : pcrs__('When the request is solved'),
        $ticket->isClosed() ? 'done' : ($pcrs_first ? 'current' : 'todo')),
);
?>
<section class="top ticket-head">
<?php echo pcrs_crumbs(array(array(__('Tickets'), $thisclient->isGuest() ? null : ROOT_PATH . 'tickets.php'),
    array('#' . $ticket->getNumber(), null))); ?>
  <h1><?php echo $pcrs_subject; ?></h1>
  <p class="sub meta">
    <span class="status <?php echo $pcrs_state == 'open' ? 'status-open' : 'status-closed'; ?>"><?php
        echo pcrs_icon($pcrs_state == 'open' ? 'inbox' : 'lock', 14);
        echo ($S = $ticket->getStatus()) ? $S->getLocalName() : ''; ?></span>
    <span>#<?php echo $ticket->getNumber(); ?> · <?php echo sprintf(pcrs__('opened %s'), Format::datetime($ticket->getCreateDate())); ?></span>
  </p>
  <p class="ticket-actions">
    <a class="btn btn-line" href="tickets.php?id=<?php echo $ticket->getId(); ?>"><?php echo pcrs_icon('refresh', 16); echo __('Reload'); ?></a>
    <a class="btn btn-line" href="tickets.php?a=print&id=<?php
        echo $ticket->getId(); ?>"><?php echo pcrs_icon('print', 16); echo __('Print'); ?></a>
<?php if ($ticket->hasClientEditableFields()
        // Only ticket owners can edit the ticket details (and other forms)
        && $thisclient->getId() == $ticket->getUserId()) { ?>
    <a class="btn btn-line" href="tickets.php?a=edit&id=<?php
         echo $ticket->getId(); ?>"><?php echo pcrs_icon('edit', 16); echo __('Edit'); ?></a>
<?php } ?>
  </p>
  <ol class="route" aria-label="<?php echo Format::htmlchars(pcrs__('Progress of the ticket')); ?>">
<?php foreach ($pcrs_steps as $i => $st) { ?>
    <li class="<?php echo $st[2]; ?>"<?php if ($st[2] == 'current') echo ' aria-current="step"'; ?>>
      <span class="node" aria-hidden="true"><?php echo $st[2] == 'done' ? pcrs_icon('check') : $i + 1; ?></span>
      <span class="txt"><strong><?php echo $st[0]; ?></strong><small><?php echo $st[1]; ?></small>
      <span class="sr-only"><?php echo $st[2] == 'done' ? pcrs__('done') : ($st[2] == 'current' ? pcrs__('in progress') : pcrs__('to come')); ?></span></span>
    </li>
<?php } ?>
  </ol>
</section>

<?php
if ($thisclient && $thisclient->isGuest()
    && $cfg->isClientRegistrationEnabled()) { ?>
<div id="msg_info">
    <?php echo pcrs_icon('user', 20); ?>
    <div><strong><?php echo __('Looking for your other tickets?'); ?></strong><br />
    <a href="<?php echo ROOT_PATH; ?>login.php?e=<?php
        echo urlencode($thisclient->getEmail());
    ?>" style="text-decoration:underline"><?php echo __('Sign In'); ?></a>
    <?php echo sprintf(__('or %s register for an account %s for the best experience on our help desk.'),
        '<a href="account.php?do=create" style="text-decoration:underline">','</a>'); ?>
    </div>
</div>
<?php } ?>

<div class="ticket-grid">
  <div class="main-col">
    <section class="next-step" aria-labelledby="next-title">
      <span class="ns-ic"><?php echo pcrs_icon($pcrs_k == 'closed' ? 'reopen' : ($pcrs_k == 'you' ? 'reply' : 'flag'), 22); ?></span>
      <div>
        <h2 id="next-title"><?php echo pcrs__('Your next step'); ?> · <?php echo $pcrs_title; ?></h2>
        <p><?php echo $pcrs_text; ?></p>
      </div>
<?php if ($pcrs_can_reply && $pcrs_k != 'us') { ?>
      <a class="btn btn-primary" href="#reply"><?php echo pcrs_icon('reply'); echo __('Post a Reply'); ?></a>
<?php } ?>
    </section>

    <h2 class="sr-only"><?php echo pcrs__('Conversation'); ?></h2>
<?php
    $email = $thisclient->getUserName();
    $clientId = TicketUser::lookupByEmail($email)->getId();

    $ticket->getThread()->render(array('M', 'R', 'user_id' => $clientId), array(
                    'mode' => Thread::MODE_CLIENT,
                    'html-id' => 'ticketThread')
                );
?>

<?php if ($warn) { ?>
    <div id="msg_warning" class="alert alert-warning" role="alert"><?php echo pcrs_icon('alert', 20); ?><div><?php echo $warn; ?></div></div>
<?php }
if ($pcrs_can_reply) { ?>
    <form id="reply" class="card reply-card" action="tickets.php?id=<?php echo $ticket->getId();
        ?>#reply" name="reply" method="post" enctype="multipart/form-data">
        <?php csrf_token(); ?>
        <h2><?php echo __('Post a Reply');?></h2>
        <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
        <input type="hidden" name="a" value="reply">
        <div>
            <label for="message"><?php echo __('Message'); ?> <span class="error" aria-hidden="true">*</span></label>
            <p class="hint" id="reply-hint"><?php
             echo __('To best assist you, we request that you be specific and detailed'); ?></p>
            <textarea name="<?php echo $messageField->getFormName(); ?>" id="message" cols="50" rows="9" wrap="soft"
                aria-describedby="reply-hint<?php if ($errors['message']) echo ' reply-error'; ?>"<?php
                if ($errors['message']) echo ' aria-invalid="true"'; ?>
                class="<?php if ($cfg->isRichTextEnabled()) echo 'richtext';
                    ?> draft" <?php
    list($draft, $attrs) = Draft::getDraftAndDataAttrs('ticket.client', $ticket->getId(), $info['message']);
    echo $attrs; ?>><?php echo $draft ?: $info['message'];
                ?></textarea>
<?php if ($errors['message']) { ?>
            <div class="error" id="reply-error"><?php echo $errors['message']; ?></div>
<?php } ?>
        <?php
        if ($messageField->isAttachmentsEnabled()) {
            print $attachments->render(array('client'=>true));
        } ?>
        </div>
<?php
      if ($ticket->isClosed() && $ticket->isReopenable()) { ?>
        <div class="warning-banner">
            <?php echo pcrs_icon('reopen', 20); ?><span><?php echo __('Ticket will be reopened on message post'); ?></span>
        </div>
<?php } ?>
        <p class="actions">
            <input type="submit" value="<?php echo __('Post Reply');?>">
            <input type="reset" value="<?php echo __('Reset');?>">
            <input type="button" value="<?php echo __('Cancel');?>" onClick="history.go(-1)">
        </p>
    </form>
<?php
} ?>
  </div>

  <aside class="side" aria-label="<?php echo Format::htmlchars(__('Basic Ticket Information')); ?>">
<?php if ($pcrs_gauge) {
    $h = $pcrs_gauge['hours'];
    $pos = round($pcrs_gauge['ratio'] * 100, 1); ?>
    <section class="card gauge-card" aria-labelledby="gauge-title">
      <h2 id="gauge-title"><?php echo pcrs_icon('clock'); echo pcrs__('First reply'); ?></h2>
      <p class="a-sub"><?php echo sprintf(pcrs__('Priority support: first reply within %s h, 24/7.'), $h == (int) $h ? (int) $h : $h); ?></p>
      <div class="gauge" role="img" aria-label="<?php echo Format::htmlchars(sprintf(pcrs__('%1$s elapsed out of %2$s h'),
            pcrs_duration($pcrs_gauge['elapsed']), $h == (int) $h ? (int) $h : $h)); ?>">
        <span class="gf" style="width:<?php echo $pos; ?>%"></span>
        <span class="gm" style="left:<?php echo $pos; ?>%"><b><?php echo pcrs_duration($pcrs_gauge['elapsed']); ?></b></span>
        <ol class="ticks" aria-hidden="true">
<?php   $n = max(1, (int) ceil($h));
        for ($i = 0; $i <= $n; $i++) { ?>
          <li style="left:<?php echo round($i / $n * 100, 2); ?>%"><?php echo $i; ?> h</li>
<?php   } ?>
        </ol>
      </div>
      <p class="a-text<?php if ($pcrs_gauge['late']) echo ' late'; ?>"><?php
        echo $pcrs_gauge['late']
            ? sprintf(pcrs__('The first reply was due %s.'), Format::datetime($pcrs_gauge['due']))
            : sprintf(pcrs__('Due %s at the latest.'), Format::datetime($pcrs_gauge['due'])); ?></p>
    </section>
<?php } ?>
    <section class="card" aria-labelledby="info-title">
      <h2 id="info-title"><?php echo __('Basic Ticket Information'); ?></h2>
      <dl class="props">
        <div><dt><?php echo __('Ticket Status');?></dt><dd><?php echo ($S = $ticket->getStatus()) ? $S->getLocalName() : ''; ?></dd></div>
        <div><dt><?php echo __('Department');?></dt><dd><?php echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></dd></div>
        <div><dt><?php echo __('Create Date');?></dt><dd><?php echo Format::datetime($ticket->getCreateDate()); ?></dd></div>
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
    <section class="card custom-data">
      <h2><?php echo $forms[$i]; ?></h2>
      <dl class="props">
<?php foreach ($answers as $A) {
    list($v, $a) = $A; ?>
        <div><dt><?php
echo $a->getField()->get('label');
            ?></dt><dd><?php
echo $v;
            ?></dd></div>
<?php } ?>
      </dl>
    </section>
<?php
} ?>
    <section class="card" aria-labelledby="user-title">
      <h2 id="user-title"><?php echo __('User Information'); ?></h2>
      <dl class="props">
        <div><dt><?php echo __('Name');?></dt><dd><?php echo mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE); ?></dd></div>
        <div><dt><?php echo __('Email');?></dt><dd><?php echo Format::htmlchars($ticket->getEmail()); ?></dd></div>
<?php if ($ticket->getPhoneNumber()) { ?>
        <div><dt><?php echo __('Phone');?></dt><dd><?php echo $ticket->getPhoneNumber(); ?></dd></div>
<?php } ?>
      </dl>
    </section>
    <section class="card soft" aria-labelledby="call-title">
      <h2 id="call-title"><?php echo pcrs__('A quick word?'); ?></h2>
      <p><?php echo sprintf(pcrs__('Call us and quote #%s.'), $ticket->getNumber()); ?></p>
      <a class="btn btn-line" href="tel:+33669939286"><?php echo pcrs_icon('phone', 16); ?>+33 (0)6 69 93 92 86</a>
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
