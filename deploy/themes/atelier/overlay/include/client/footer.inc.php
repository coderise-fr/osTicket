<?php
/* Atelier theme (deploy/themes/atelier): replaces include/client/footer.inc.php.
 * Coderise footer: logo and signature, contact details, response times,
 * the active "Other Resources" pages, copyright. Same closing scripts as
 * the original footer. */
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');
require_once(INCLUDE_DIR . 'class.page.php');
?>
        </main>
    </div>
    <footer id="footer" class="site-footer on-dark">
      <div class="wrap foot">
        <div class="foot-brand">
          <a class="brand" href="<?php echo ROOT_PATH; ?>index.php"><img class="logo"
            src="<?php echo ROOT_PATH; ?>assets/atelier/img/logo-coderise-horizontal.png"
            width="166" height="28" alt="<?php echo Format::htmlchars(ATELIER_BRAND); ?>"><span class="visually-hidden"> &mdash; <?php
            echo __('Support Center'); ?></span></a>
<?php
          // Brand signature (charte v1.0): French in every language, never
          // framed, at least 1X under the logo, "décoller" in cyan.
          ?>
          <p class="signature" lang="fr">Le code qui fait <span class="up">décoller</span> vos projets</p>
        </div>
        <div class="foot-col">
          <h2 class="f-title"><?php echo __a('Contact us'); ?></h2>
          <ul class="f-list">
            <li><?php echo atelier_icon('mail'); ?><a href="mailto:<?php echo ATELIER_EMAIL; ?>"><?php echo ATELIER_EMAIL; ?></a></li>
            <li><?php echo atelier_icon('phone'); ?><a href="tel:<?php echo ATELIER_PHONE_URI; ?>"><?php echo ATELIER_PHONE; ?></a></li>
            <li><?php echo atelier_icon('pin'); ?><span><?php echo ATELIER_CITY; ?></span></li>
          </ul>
          <p class="f-note"><?php echo __a('Company office hours: Monday to Friday, 8:30 am to 5:00 pm.'); ?></p>
        </div>
        <div class="foot-col">
          <h2 class="f-title"><?php echo __a('Response times'); ?></h2>
          <ul class="f-plain">
            <li><?php echo __a('<strong>Standard support:</strong> business days.'); ?></li>
            <li><?php echo __a('<strong>Priority support:</strong> first reply within 4 h, 24/7.'); ?></li>
          </ul>
        </div>
        <div class="foot-col">
          <h2 class="f-title"><?php echo __a('Resources'); ?></h2>
          <ul class="f-list">
<?php
// Same list as the "Other Resources" box of the default theme (pages of
// type "other" made active in Admin Panel > Manage > Pages).
foreach (Page::getActivePages()->filter(array('type'=>'other')) as $atelier_page_link) { ?>
            <li><?php echo atelier_icon('file'); ?><a href="<?php echo ROOT_PATH; ?>pages/<?php echo $atelier_page_link->getNameAsSlug();
            ?>"><?php echo Format::htmlchars($atelier_page_link->getLocalName()); ?></a></li>
<?php } ?>
            <li><?php echo atelier_icon('globe'); ?><a href="<?php echo ATELIER_WEBSITE; ?>">www.coderise.fr</a></li>
          </ul>
        </div>
      </div>
      <div class="wrap foot-legal">
        <p><?php echo __('Copyright &copy;'); ?> <?php echo date('Y'); ?> <?php
        echo Format::htmlchars((string) $ost->company ?: ATELIER_BRAND); ?> - <?php echo __('All rights reserved.'); ?>
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
