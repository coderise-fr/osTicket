<?php
/* Theme "Poste de travail" (Coderise) - overrides
 * include/client/templates/thread-entry.tmpl.php. Same data; initials instead
 * of avatar images (no external avatar service), compact header with a role
 * label ("You", "Coderise team"). */
global $cfg, $thisclient;
$entryTypes = ThreadEntry::getTypes();
$user = $entry->getUser() ?: $entry->getStaff();
if ($entry->staff && $cfg->hideStaffName())
    $name = __('Staff');
else
    $name = $user ? $user->getName() : $entry->poster;
$type = $entryTypes[$entry->type];
$ptdt_time = sprintf('<time datetime="%s" title="%s">%s</time>',
    date(DateTime::W3C, Misc::db2gmtime($entry->created)),
    Format::daydatetime($entry->created),
    Format::datetime($entry->created));
$ptdt_role = '';
if ($entry->staff)
    $ptdt_role = ptdt__('Coderise team');
elseif ($thisclient && $entry->user_id && $entry->user_id == $thisclient->getId())
    $ptdt_role = ptdt__('You');
?>
<article class="thread-entry entry <?php echo $type; ?>" aria-label="<?php
    echo Format::htmlchars(strip_tags(sprintf('%s, %s', $name, Format::datetime($entry->created)))); ?>">
    <span class="avatar <?php if ($entry->staff) echo 'av-team'; ?>" aria-hidden="true"><?php echo ptdt_initials($name); ?></span>
    <div class="e-main">
    <header class="header">
        <strong><?php echo Format::htmlchars($name); ?></strong>
<?php if ($ptdt_role) { ?>
        <span class="role"><?php echo Format::htmlchars($ptdt_role); ?></span>
<?php } ?>
<?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
        <span class="label label-bare" title="<?php
        echo sprintf(__('Edited on %s by %s'), Format::datetime($entry->updated), 'You');
                ?>"><?php echo __('Edited'); ?></span>
<?php } ?>
        <?php echo $ptdt_time; ?>
<?php if ($entry->title) { ?>
        <span class="faded title"><?php echo Format::htmlchars($entry->title); ?></span>
<?php } ?>
    </header>
    <div class="thread-body e-body" id="thread-id-<?php echo $entry->getId(); ?>">
        <div><?php echo $entry->getBody()->toHtml(); ?></div>
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
        <?php echo ptdt_icon('clip', 15); ?>
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
</article>
