<?php
/* Theme "Poste de travail" (Ownalytics) - overrides
 * include/client/templates/thread-entry.tmpl.php. Same data; initials instead
 * of avatar images (no external avatar service), compact header. */
global $cfg;
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
?>
<article class="thread-entry entry <?php echo $type; ?>" aria-label="<?php
    echo Format::htmlchars(strip_tags(sprintf('%s, %s', $name, Format::datetime($entry->created)))); ?>">
    <span class="e-av <?php echo $type; ?>" aria-hidden="true"><?php echo ptdt_initials($name); ?></span>
    <div class="e-main">
    <header class="header">
        <b><?php echo Format::htmlchars($name); ?></b>
<?php if ($entry->staff) { ?>
        <span class="tag"><?php echo __('Staff'); ?></span>
<?php } ?>
        <?php echo $ptdt_time; ?>
<?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
        <span class="label label-bare" title="<?php
        echo sprintf(__('Edited on %s by %s'), Format::datetime($entry->updated), 'You');
                ?>"><?php echo __('Edited'); ?></span>
<?php } ?>
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
        <?php echo ptdt_icon('clip', 13); ?>
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
