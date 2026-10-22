<?php
/* Sommet theme (deploy/themes/sommet): replaces
 * include/client/templates/thread-entry.tmpl.php.
 * Same data and escaping as the original; laid out as a journal entry
 * (author, role, date on the left, message on the right). No avatar image:
 * osTicket takes client avatars from Gravatar by default, and the portal
 * must not load any remote resource. */
global $cfg, $thisclient;
$entryTypes = ThreadEntry::getTypes();
$user = $entry->getUser() ?: $entry->getStaff();
if ($entry->staff && $cfg->hideStaffName())
    $name = __('Staff');
else
    $name = $user ? $user->getName() : $entry->poster;
$type = $entryTypes[$entry->type];
if ($entry->type == 'R')
    $role = __s('Coderise team');
elseif ($thisclient && $entry->user_id
        && $entry->user_id == $thisclient->getId())
    $role = __s('You');
else
    $role = '';
?>
<div class="thread-entry <?php echo $type; ?>">
    <div class="header meta">
        <strong class="author"><?php echo Format::htmlchars($name); ?></strong>
<?php if ($role) { ?>
        <span class="role"><?php echo $role; ?></span>
<?php } ?>
        <?php echo sprintf('<time datetime="%s" title="%s">%s</time>',
                    date(DateTime::W3C, Misc::db2gmtime($entry->created)),
                    Format::daydatetime($entry->created),
                    Format::datetime($entry->created)
                ); ?>
        <?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
        <span class="textra"><span class="label label-bare" title="<?php
        echo sprintf(__('Edited on %s by %s'), Format::datetime($entry->updated), 'You');
                ?>"><?php echo __('Edited'); ?></span></span>
        <?php } ?>
<?php if (trim((string) $entry->title) !== '') { ?>
        <span class="title"><?php echo Format::htmlchars($entry->title); ?></span>
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
                $size = sprintf('<small class="filesize faded">%s</small>', Format::file_size($A->file->size));
?>
        <span class="attachment-info">
        <?php echo sommet_icon('clip', 15); ?>
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
