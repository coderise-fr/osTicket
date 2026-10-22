<?php
/* Theme "Poste de travail" (Coderise) - overrides
 * include/client/templates/thread-event.tmpl.php. Same markup; the avatar
 * images that ThreadEvent::getDescription() inserts when avatars are enabled
 * (Gravatar by default, an external request) are removed: the portal makes
 * no request to another host. */
$desc = $event->getDescription(ThreadEvent::MODE_CLIENT);
if (!$desc)
    return;
$desc = preg_replace('#<img\b[^>]*\bclass="[^"]*\bavatar\b[^"]*"[^>]*>#i', '', $desc);
?>
<div class="thread-event <?php if ($event->uid) echo 'action'; ?>">
        <span class="type-icon">
          <i class="faded icon-<?php echo $event->getIcon(); ?>"></i>
        </span>
        <span class="faded description"><?php echo $desc; ?></span>
</div>
