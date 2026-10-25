<?php
/*********************************************************************
    include/staff/footer.inc.php  (coderise-agent staff theme)

    Core's footer; the closing tags follow the theme header: #content,
    .cdr-main, #pjax-container. The copyright line (#footer) is inside
    .cdr-main, in PJAX responses too, so #pjax-container ends at the
    bottom of the page and the sticky rail never scrolls up (the autocron
    image is taken out of the flow for the same reason). The "/" key
    focuses the top bar search, or opens it through the compact search
    button below 920 px (bound once, full page loads only).
    Everything else (autocron image, #overlay, #loading, #popup, #alert,
    scripts, getConfig().resolve(), i18n script) is kept as is.
    Shipped by deploy/themes-staff/coderise-agent (osTicket core untouched).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');
?>
</div><!-- #content -->
    <div id="footer">
        <?php echo __('Copyright &copy;') ?> 2006-<?php echo date('Y'); ?>&nbsp;<?php
        echo Format::htmlchars((string) $ost->company ?: 'osTicket.com'); ?>&nbsp;<?php echo __('All Rights Reserved.'); ?>
    </div>
</div><!-- .cdr-main -->
</div><!-- #pjax-container -->
<?php if (!isset($_SERVER['HTTP_X_PJAX'])) { ?>
<?php
if(is_object($thisstaff) && $thisstaff->isStaff()) { ?>
    <div class="cdr-autocron">
        <!-- Do not remove <img src="autocron.php" alt="" width="1" height="1" border="0" /> or your auto cron will cease to function -->
        <img src="<?php echo ROOT_PATH; ?>scp/autocron.php" alt="" width="1" height="1" border="0" />
        <!-- Do not remove <img src="autocron.php" alt="" width="1" height="1" border="0" /> or your auto cron will cease to function -->
    </div>
<?php
} ?>
</div>
<div id="overlay"></div>
<div id="loading">
    <i class="icon-spinner icon-spin icon-3x pull-left icon-light"></i>
    <h1><?php echo __('Loading ...');?></h1>
</div>
<div class="dialog draggable" style="display:none;" id="popup">
    <div id="popup-loading">
        <h1 style="margin-bottom: 20px;"><i class="icon-spinner icon-spin icon-large"></i>
        <?php echo __('Loading ...');?></h1>
    </div>
    <div class="body"></div>
</div>
<div style="display:none;" class="dialog" id="alert">
    <h3><i class="icon-warning-sign"></i> <span id="title"></span></h3>
    <a class="close" href=""><i class="icon-remove-circle"></i></a>
    <hr/>
    <div id="body" style="min-height: 20px;"></div>
    <hr style="margin-top:3em"/>
    <p class="full-width">
        <span class="buttons pull-right">
            <input type="button" value="<?php echo __('OK');?>" class="close ok">
        </span>
     </p>
    <div class="clear"></div>
</div>

<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery.pjax.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/bootstrap-typeahead.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-sliderAccess.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/scp.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/filedrop.field.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/select2.min.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/tips.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor.min.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-osticket.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-plugins.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/jquery.translatable.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/jquery.dropdown.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/bootstrap-tooltip.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/jb.overflow.menu.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>scp/css/tooltip.css">
<script type="text/javascript">
    getConfig().resolve(<?php
        include INCLUDE_DIR . 'ajax.config.php';
        $api = new ConfigAjaxAPI();
        print $api->scp(false);
    ?>);
</script>
<script type="text/javascript">
// Coderise: "/" focuses the top bar search (the <kbd> hint of header.inc.php),
// unless the key is typed in a field, an editor or under an open dialog.
(function () {
    if (window.cdrSlashBound)
        return;
    window.cdrSlashBound = true;
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented)
            return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)))
            return;
        var o = document.getElementById('overlay');
        if (o && window.getComputedStyle(o).display !== 'none')
            return;
        // the queue's own search when the top bar one is not shown (queue.css)
        var f = document.querySelector('.topbar .tsearch input[type="search"]');
        if (!f || !f.offsetParent)
            f = document.querySelector('#basic_search input.basic-search');
        // below 920 px: the compact search button (coderise.js) reveals and focuses the field
        var b = document.querySelector('.topbar .cdr-search-toggle');
        if ((!f || !f.offsetParent) && b && b.offsetParent) {
            e.preventDefault();
            b.click();
            return;
        }
        if (!f || !f.offsetParent)
            return;
        e.preventDefault();
        f.focus();
        f.select();
    });
})();
</script>
<?php
if ($thisstaff
        && ($lang = $thisstaff->getLanguage())
        && 0 !== strcasecmp($lang, 'en_US')) { ?>
    <script type="text/javascript" src="ajax.php/i18n/<?php
        echo $thisstaff->getLanguage(); ?>/js"></script>
<?php } ?>
</body>
</html>
<?php } # endif X_PJAX ?>
