<?php
/* Atelier theme (deploy/themes/atelier): replaces
 * include/client/templates/thread-event.tmpl.php.
 * Same markup; the avatar images that osTicket puts in event descriptions
 * are removed so that the portal never loads a remote avatar (Gravatar is
 * osTicket's default avatar source). */
$desc = $event->getDescription(ThreadEvent::MODE_CLIENT);
if (!$desc)
    return;
$desc = preg_replace('/<img\b[^>]*\bclass="[^"]*\bavatar\b[^"]*"[^>]*>/i', '', $desc);
?>
<div class="thread-event <?php if ($event->uid) echo 'action'; ?>">
        <span class="type-icon">
          <i class="faded icon-<?php echo $event->getIcon(); ?>" aria-hidden="true"></i>
        </span>
        <span class="faded description"><?php echo $desc; ?></span>
</div>
