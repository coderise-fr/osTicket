<?php
/* Sommet theme (deploy/themes/sommet): replaces
 * include/client/templates/thread-event.tmpl.php.
 * Same markup; the avatar images that osTicket puts in event descriptions
 * are removed so that the portal never loads a remote avatar (Gravatar is
 * osTicket's default avatar source).
 * SLA plan changes are not shown to the client (plan names are internal):
 * - "edited" events that only carry sla_id: a plan changed BY HAND by an
 *   agent (ticket edit, "Change SLA Plan"), and the plan switch written by
 *   versions 0.1-0.2 of the sla_first_response plugin;
 * - "sla_first_response" events (plugin 0.3 and later): the client portal
 *   already leaves them out (whitelist of event types in
 *   thread-entries.tmpl.php) and the plugin gives them an empty client
 *   description; also skipped here in case either changes, e.g. plugin
 *   disabled (generic description "sla_first_response by ...").
 * The agent panel still shows all of them. */
if ($event->state == 'sla_first_response')
    return;
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
