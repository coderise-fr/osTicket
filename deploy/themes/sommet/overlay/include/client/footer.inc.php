<?php
/* Sommet theme (deploy/themes/sommet): replaces include/client/footer.inc.php.
 * Coderise footer (logo, signature, contact, response times, links), the
 * "Other Resources" pages of the admin panel, copyright and "powered by
 * osTicket"; same closing scripts as the original (#loading, i18n JS,
 * getConfig()). Fills the title band first (sommet_banner_close()). */
?>
        </main>
    </div>
<?php
if (function_exists('sommet_banner_close'))
    sommet_banner_close();
?>
    <footer id="footer" class="site-foot">
        <div class="wrap foot-in">
            <div class="f-brand">
                <img class="logo" src="<?php echo sommet_asset('img/logo-coderise-horizontal.png'); ?>"
                    width="2041" height="345" alt="Coderise">
                <p class="signature"><?php echo __s('The code that makes your projects <span class="up">take off</span>'); ?></p>
            </div>
            <div>
                <h2 class="f-title"><?php echo __s('Contact us'); ?></h2>
                <ul class="f-list">
                    <li><?php echo sommet_icon('mail'); ?><a href="mailto:contact@coderise.fr">contact@coderise.fr</a></li>
                    <li><?php echo sommet_icon('phone'); ?><a href="tel:+33669939286">+33 (0)6 69 93 92 86</a></li>
                    <li><?php echo sommet_icon('pin'); ?><span>Aix-en-Provence</span></li>
                    <li><?php echo sommet_icon('clock'); ?><span><?php echo __s('Monday to Friday, 8:30 am to 5:00 pm'); ?></span></li>
                </ul>
            </div>
            <div>
                <h2 class="f-title"><?php echo __s('Response times'); ?></h2>
                <ul class="f-plain">
                    <li><?php echo __s('<strong>Standard support:</strong> business days.'); ?></li>
                    <li><?php echo __s('<strong>Priority support:</strong> first reply within 4 hours, 24/7.'); ?></li>
                    <li class="f-note"><?php echo __s('Office hours are not the hours of the Priority support.'); ?></li>
                </ul>
            </div>
            <div>
<?php
$sommet_pages = Page::getActivePages()->filter(array('type'=>'other'));
if ($sommet_pages->all()) { ?>
                <h2 class="f-title"><?php echo __('Other Resources'); ?></h2>
                <ul class="f-plain f-pages">
<?php   foreach ($sommet_pages as $P) { ?>
                    <li><a href="<?php echo ROOT_PATH; ?>pages/<?php echo $P->getNameAsSlug();
                        ?>"><?php echo Format::htmlchars($P->getLocalName()); ?></a></li>
<?php   } ?>
                </ul>
<?php
} ?>
                <h2 class="f-title"><?php echo __s('Follow Coderise'); ?></h2>
                <ul class="f-list">
                    <li><?php echo sommet_icon('linkedin'); ?><a href="https://www.linkedin.com/company/coderise-fr">LinkedIn</a></li>
                    <li><?php echo sommet_icon('github'); ?><a href="https://github.com/coderise-fr">GitHub</a></li>
                    <li><?php echo sommet_icon('globe'); ?><a href="https://www.coderise.fr">www.coderise.fr</a></li>
                </ul>
            </div>
        </div>
        <div class="wrap foot-legal">
            <p><?php echo __('Copyright &copy;'); ?> <?php echo date('Y'); ?> <?php
            echo Format::htmlchars((string) $ost->company ?: 'Coderise'); ?> - <?php echo __('All rights reserved.'); ?>
            <a id="poweredBy" href="https://osticket.com" target="_blank" rel="noopener"><?php echo __('Helpdesk software - powered by osTicket'); ?></a></p>
        </div>
    </footer>
<div id="overlay"></div>
<div id="loading">
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
