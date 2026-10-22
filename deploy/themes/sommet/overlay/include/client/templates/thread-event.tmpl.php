<?php
/* Sommet theme (deploy/themes/sommet): replaces
 * include/client/templates/thread-event.tmpl.php.
 * Same markup; the avatar images that osTicket puts in event descriptions
 * are removed so that the portal never loads a remote avatar (Gravatar is
 * osTicket's default avatar source).
 * Changes that only concern the SLA plan are not shown to the client: the
 * plan names are internal, and the sla_first_response plugin switches the
 * plan at the first reply ("Priority 4h" -> "... (Disabled)"). The agent
 * panel still shows them. */
if ($event->state == 'edited' && is_array($sommet_d = $event->getData())
        && $sommet_d && !array_diff(array_keys($sommet_d), array('sla_id')))
    return;
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
