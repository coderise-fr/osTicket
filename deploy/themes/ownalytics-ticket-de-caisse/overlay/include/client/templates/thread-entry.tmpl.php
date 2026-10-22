<?php
/* "Ticket de caisse" theme (deploy/themes/ownalytics-ticket-de-caisse):
 * replaces include/client/templates/thread-entry.tmpl.php.
 * Same markup and escaping as the original, two changes:
 * - no avatar image: osTicket takes avatars from Gravatar by default, and
 *   the portal must not send requests to another host;
 * - replies from the team carry the brand after the agent's name
 *   ("Inès Moreau (Ownalytics)"), as in the mockup. */
require_once(INCLUDE_DIR . 'client/ownalytics-ticket-de-caisse/theme.php');
global $cfg;
$entryTypes = ThreadEntry::getTypes();
$user = $entry->getUser() ?: $entry->getStaff();
if ($entry->staff && $cfg->hideStaffName())
    $name = __('Staff');
else
    $name = $user ? $user->getName() : $entry->poster;
$who = Format::htmlchars($name);
if ($entry->staff)
    $who .= ' <span class="role">(' . Format::htmlchars(OTDC_BRAND) . ')</span>';
?>
<?php
 $type = $entryTypes[$entry->type];
 ?>
<div class="thread-entry <?php echo $type; ?>">
    <div class="header">
        <div class="pull-right">
            <span style="vertical-align:middle;" class="textra">
        <?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
                <span class="label label-bare" title="<?php
        echo sprintf(__('Edited on %s by %s'), Format::datetime($entry->updated), 'You');
                ?>"><?php echo __('Edited'); ?></span>
        <?php } ?>
            </span>
        </div>
<?php
            echo sprintf(__('<b>%s</b> posted %s'), $who,
                sprintf('<time datetime="%s" title="%s">%s</time>',
                    date(DateTime::W3C, Misc::db2gmtime($entry->created)),
                    Format::daydatetime($entry->created),
                    Format::datetime($entry->created)
                )
            ); ?>
            <span style="max-width:500px" class="faded title truncate"><?php
                echo Format::htmlchars($entry->title); ?>
            </span>
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
        <i class="icon-paperclip icon-flip-horizontal" aria-hidden="true"></i>
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
