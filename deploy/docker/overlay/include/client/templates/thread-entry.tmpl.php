<?php
/* Theme "Parcours" (Coderise) - overrides
 * include/client/templates/thread-entry.tmpl.php.
 * Same data, escaping, attachments (download links) and inline-image data
 * as the core template. Changes: initials instead of avatar images (the
 * core may load them from Gravatar, an external request), role label
 * ("You", "Support team"), entry as a card on the progress line. */
global $cfg, $thisclient;
if (!function_exists('pcrs__'))
    require_once INCLUDE_DIR . 'client/parcours/parcours.inc.php';
$entryTypes = ThreadEntry::getTypes();
$user = $entry->getUser() ?: $entry->getStaff();
if ($entry->staff && $cfg->hideStaffName())
    $name = __('Staff');
else
    $name = $user ? $user->getName() : $entry->poster;
$type = $entryTypes[$entry->type];
$is_staff = ($entry->type == 'R');
if ($is_staff)
    $role = pcrs__('Support team');
elseif ($thisclient && $entry->user_id && $entry->user_id == $thisclient->getId())
    $role = pcrs__('You');
else
    $role = '';
?>
<div class="thread-entry <?php echo $type; ?>">
    <span class="dot avatar<?php if ($is_staff) echo ' av-team'; ?>" aria-hidden="true"><?php echo pcrs_initials((string) $name); ?></span>
    <div class="card">
    <div class="header">
        <strong><?php echo Format::htmlchars($name); ?></strong>
<?php if ($role) { ?>
        <span class="role"><?php echo $role; ?></span>
<?php } ?>
<?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
        <span class="label label-bare" title="<?php
        echo sprintf(__('Edited on %s by %s'), Format::datetime($entry->updated), 'You');
                ?>"><?php echo __('Edited'); ?></span>
<?php } ?>
        <?php echo sprintf('<time datetime="%s" title="%s">%s</time>',
                    date(DateTime::W3C, Misc::db2gmtime($entry->created)),
                    Format::daydatetime($entry->created),
                    Format::datetime($entry->created)); ?>
<?php if ($entry->title) { ?>
        <span class="e-title"><?php echo Format::htmlchars($entry->title); ?></span>
<?php } ?>
    </div>
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
                $size = sprintf('<small class="filesize">%s</small>', Format::file_size($A->file->size));
?>
        <span class="attachment-info">
        <?php echo pcrs_icon('clip', 16); ?>
        <a  class="no-pjax filename"
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
