<?php
/* Atelier theme (deploy/themes/atelier): replaces include/client/footer.inc.php.
 * Same closing scripts as the original footer. */
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');
require_once(INCLUDE_DIR . 'class.page.php');
?>
        </main>
    </div>
    <footer id="footer" class="site-footer">
      <div class="wrap foot">
        <div class="foot-brand">
          <a class="brand" href="<?php echo ROOT_PATH; ?>index.php"><svg class="mark" width="20" height="26" viewBox="0 0 20 26" aria-hidden="true" focusable="false"><path d="M1 1h18v21l-3 3-3-3-3 3-3-3-3 3-3-3z" fill="#FBFBF6"/><path d="M5 7h10M5 11h6M5 15h10" stroke="#2A1340" stroke-width="2"/></svg><span class="wm"><?php
            echo Format::htmlchars(ATELIER_BRAND); ?></span><span class="visually-hidden"> &mdash; <?php
            echo __('Support Center'); ?></span></a>
          <p><?php echo __a('A small team of real people answers every ticket.'); ?></p>
        </div>
<?php
// Same list as the "Other Resources" box of the default theme (pages of
// type "other" made active in Admin Panel > Manage > Pages).
$atelier_resources = Page::getActivePages()->filter(array('type'=>'other'));
if ($atelier_resources->all()) { ?>
        <nav aria-label="<?php echo Format::htmlchars(__('Other Resources')); ?>">
        <ul class="legal">
<?php foreach ($atelier_resources as $atelier_page_link) { ?>
          <li><a href="<?php echo ROOT_PATH; ?>pages/<?php echo $atelier_page_link->getNameAsSlug();
            ?>"><?php echo Format::htmlchars($atelier_page_link->getLocalName()); ?></a></li>
<?php } ?>
        </ul>
        </nav>
<?php } ?>
        <p class="small"><?php echo __('Copyright &copy;'); ?> <?php echo date('Y'); ?> <?php
        echo Format::htmlchars((string) $ost->company ?: 'osTicket.com'); ?> - <?php echo __('All rights reserved.'); ?>
        <a id="poweredBy" href="https://osticket.com" target="_blank" rel="noopener"><?php echo __('Helpdesk software - powered by osTicket'); ?></a></p>
      </div>
    </footer>
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
