<?php
/* "Ticket de caisse" theme (deploy/themes/ownalytics-ticket-de-caisse):
 * replaces include/client/footer.inc.php.
 * Closes the <main> opened by the theme's header. Footer in the style of
 * www.ownalytics.eu: logotype, the active "Other Resources" pages of the
 * help desk, the Matomo trademark notice of the website, then the original
 * copyright and "powered by osTicket" lines. Same closing scripts as the
 * original footer. */
require_once(INCLUDE_DIR . 'client/ownalytics-ticket-de-caisse/theme.php');
require_once(INCLUDE_DIR . 'class.page.php');
?>
        </main>
    </div>
    <div id="footer">
      <div class="foot-inner">
        <a class="foot-mark" href="<?php echo ROOT_PATH; ?>index.php"><?php
            echo otdc_mark('#2A1340'); ?><span><?php
            echo Format::htmlchars(OTDC_BRAND); ?></span><span class="visually-hidden"> &mdash; <?php
            echo __('Support Center'); ?></span></a>
<?php
// Same list as the "Other Resources" box of the default theme: pages of type
// "other" made active in Admin Panel > Manage > Pages (legal notice, terms...).
$otdc_resources = Page::getActivePages()->filter(array('type'=>'other'));
if ($otdc_resources->all()) { ?>
        <nav aria-label="<?php echo Format::htmlchars(__('Other Resources')); ?>">
        <ul class="legal">
<?php foreach ($otdc_resources as $otdc_page_link) { ?>
          <li><a href="<?php echo ROOT_PATH; ?>pages/<?php echo $otdc_page_link->getNameAsSlug();
            ?>"><?php echo Format::htmlchars($otdc_page_link->getLocalName()); ?></a></li>
<?php } ?>
        </ul>
        </nav>
<?php } ?>
        <p><?php echo otdc__('Matomo is a registered trademark of InnoCraft Ltd. Ownalytics is an independent publisher, not affiliated with InnoCraft.'); ?></p>
        <p><?php echo __('Copyright &copy;'); ?> <?php echo date('Y'); ?> <?php
        echo Format::htmlchars((string) $ost->company ?: 'osTicket.com'); ?> - <?php echo __('All rights reserved.'); ?>
        <a id="poweredBy" href="https://osticket.com" target="_blank" rel="noopener"><?php echo __('Helpdesk software - powered by osTicket'); ?></a></p>
      </div>
    </div>
<div id="overlay"></div>
<div id="loading" role="status">
    <h4><?php echo __('Please Wait!');?></h4>
    <p><?php echo __('Please wait... it will take a second!');?></p>
</div>
<?php
if (($lang = Internationalization::getCurrentLanguage()) && $lang != 'en_US') { ?>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>ajax.php/i18n/<?php
        echo $lang; ?>/js"></script>
<?php } ?>
<script type="text/javascript">
    getConfig().resolve(<?php
        include INCLUDE_DIR . 'ajax.config.php';
        $api = new ConfigAjaxAPI();
        print $api->client(false);
    ?>);
</script>
</body>
</html>
