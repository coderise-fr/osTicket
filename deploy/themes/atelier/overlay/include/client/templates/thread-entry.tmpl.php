<?php
/* Atelier theme (deploy/themes/atelier): replaces
 * include/client/templates/thread-entry.tmpl.php.
 * Conversation bubbles: the client's own messages on the right, the
 * support team's replies on the left, initials instead of remote avatars.
 * Same data, same escaping, same hooks for osticket.js (.thread-entry,
 * .header .textra, .thread-body#thread-id-N, .attachments). */
global $cfg, $thisclient;
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');

$entryTypes = ThreadEntry::getTypes();
$user = $entry->getUser() ?: $entry->getStaff();
if ($entry->staff && $cfg->hideStaffName())
    $name = __('Staff');
else
    $name = $user ? $user->getName() : $entry->poster;

$type = $entryTypes[$entry->type];
$is_team = ($entry->type == 'R' || $entry->staff_id);
$is_me = (!$is_team && $thisclient && $entry->user_id
    && $entry->user_id == $thisclient->getId());
$side = $is_team ? 'from-team' : ($is_me ? 'from-me' : 'from-other');
$tone = $is_me ? 'you'
    : ($is_team ? 'team'
        : 'other');
?>
<div class="thread-entry <?php echo $type; ?> <?php echo $side; ?>">
    <span class="avatar av-<?php echo $tone; ?>" aria-hidden="true"><?php
        echo Format::htmlchars(atelier_initials((string) $name)); ?></span>
    <div class="bubble">
    <div class="header b-head">
        <b class="b-name"><?php echo Format::htmlchars((string) $name); ?></b>
<?php if ($is_me) { ?>
        <span class="role me"><?php echo __a('You'); ?></span>
<?php } elseif ($is_team) { ?>
        <span class="role"><?php echo sprintf(__a('%s team'), ATELIER_BRAND); ?></span>
<?php } ?>
        <span class="textra">
<?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
            <span class="label label-bare" title="<?php
    echo sprintf(__('Edited on %s by %s'), Format::datetime($entry->updated), 'You');
            ?>"><?php echo __('Edited'); ?></span>
<?php } ?>
        </span>
        <?php echo sprintf('<time datetime="%s" title="%s">%s</time>',
            date(DateTime::W3C, Misc::db2gmtime($entry->created)),
            Format::daydatetime($entry->created),
            Format::datetime($entry->created)); ?>
    </div>
<?php if ($entry->title) { ?>
    <p class="b-title"><?php echo Format::htmlchars($entry->title); ?></p>
<?php } ?>
    <div class="thread-body" id="thread-id-<?php echo $entry->getId(); ?>">
        <div><?php echo $entry->getBody()->toHtml(); ?></div>
        <div class="clear"></div>
<?php
    if ($entry->has_attachments) { ?>
    <div class="attachments"><?php
        foreach ($entry->attachments as $A) {
            if ($A->inline)
                continue;
            $size = '';
            if ($A->file->size)
                $size = sprintf('<small class="filesize faded">%s</small>', Format::file_size($A->file->size));
?>
        <span class="attachment-info">
        <?php echo atelier_icon('clip', 15); ?>
        <a  class="no-pjax truncate filename"
            href="<?php echo $A->file->getDownloadUrl(['id' => $A->getId()]);
            ?>" download="<?php echo Format::htmlchars($A->getFilename()); ?>"
            target="_blank"><?php echo Format::htmlchars($A->getFilename());
        ?></a><?php echo $size;?>
        </span>
<?php   }  ?>
    </div>
<?php } ?>
    </div>
    </div>
<?php
    if ($urls = $entry->getAttachmentUrls()) { ?>
        <script type="text/javascript">
            $('#thread-id-<?php echo $entry->getId(); ?>')
                .data('urls', <?php
                    echo JsonDataEncoder::encode($urls); ?>)
                .data('id', <?php echo $entry->getId(); ?>);
        </script>
<?php
    } ?>
</div>
