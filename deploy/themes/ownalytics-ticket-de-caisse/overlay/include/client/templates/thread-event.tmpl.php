<?php
/* "Ticket de caisse" theme (deploy/themes/ownalytics-ticket-de-caisse):
 * replaces include/client/templates/thread-event.tmpl.php.
 * Same markup; the avatar images that osTicket puts in event descriptions
 * are removed so that the portal never loads a remote avatar (Gravatar is
 * osTicket's default avatar source). */
require_once(INCLUDE_DIR . 'client/ownalytics-ticket-de-caisse/theme.php');
$desc = $event->getDescription(ThreadEvent::MODE_CLIENT);
if (!$desc)
    return;
$desc = otdc_strip_avatars($desc);
?>
<div class="thread-event <?php if ($event->uid) echo 'action'; ?>">
        <span class="type-icon">
          <i class="faded icon-<?php echo $event->getIcon(); ?>" aria-hidden="true"></i>
        </span>
        <span class="faded description"><?php echo $desc; ?></span>
</div>
