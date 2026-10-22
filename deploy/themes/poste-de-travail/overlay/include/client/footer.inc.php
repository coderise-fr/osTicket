    </main>
<?php
/* Theme "Poste de travail" (Coderise) - overrides include/client/footer.inc.php.
 * Coderise contact details (constants of poste-de-travail/theme.php), response
 * times of the two support levels, copyright and "powered by osTicket"; same
 * scripts as the core template (i18n JS, getConfig()) and same #loading. */
$ptdt_company = Format::htmlchars((string) $ost->company ?: 'Coderise');
if (!empty($ptdt_auth)) { ?>
    <footer class="foot foot-slim" id="footer">
        <p><a href="mailto:<?php echo Format::htmlchars(PTDT_CONTACT_EMAIL); ?>"><?php echo Format::htmlchars(PTDT_CONTACT_EMAIL); ?></a>
            · <a href="tel:<?php echo Format::htmlchars(PTDT_CONTACT_PHONE_URI); ?>"><?php echo Format::htmlchars(PTDT_CONTACT_PHONE); ?></a>
            · <?php echo ptdt__('Standard support: business days. Priority: 24/7.'); ?>
            <?php echo __('Copyright &copy;'); ?> <?php echo date('Y'); ?> <?php echo $ptdt_company; ?>.
            <a id="poweredBy" href="https://osticket.com" target="_blank" rel="noopener"><?php echo __('Helpdesk software - powered by osTicket'); ?></a></p>
    </footer>
</div>
<?php
} else { ?>
    <footer class="foot" id="footer">
        <div class="foot-in">
            <div>
                <h2 class="f-title"><?php echo ptdt__('Contact us'); ?></h2>
                <ul class="f-list">
                    <li><?php echo ptdt_icon('mail'); ?><a href="mailto:<?php echo Format::htmlchars(PTDT_CONTACT_EMAIL); ?>"><?php echo Format::htmlchars(PTDT_CONTACT_EMAIL); ?></a></li>
                    <li><?php echo ptdt_icon('phone'); ?><a href="tel:<?php echo Format::htmlchars(PTDT_CONTACT_PHONE_URI); ?>"><?php echo Format::htmlchars(PTDT_CONTACT_PHONE); ?></a></li>
                    <li><?php echo ptdt_icon('pin'); ?><span><?php echo Format::htmlchars(PTDT_CONTACT_CITY); ?></span></li>
                </ul>
            </div>
            <div>
                <h2 class="f-title"><?php echo ptdt__('Response times'); ?></h2>
                <ul class="f-plain">
                    <li><strong><?php echo ptdt__('Standard support:'); ?></strong> <?php echo ptdt__('business days.'); ?></li>
                    <li><strong><?php echo ptdt__('Priority support:'); ?></strong> <?php echo ptdt__('first response within 4 h, 24/7.'); ?></li>
                    <li class="f-note"><?php echo ptdt__('Company hours (Monday to Friday, 8:30 am to 5:00 pm) are not those of Priority support.'); ?></li>
                </ul>
            </div>
            <div>
                <h2 class="f-title"><?php echo ptdt__('Follow Coderise'); ?></h2>
                <ul class="f-list">
                    <li><?php echo ptdt_icon('linkedin'); ?><a href="<?php echo Format::htmlchars(PTDT_URL_LINKEDIN); ?>" rel="noopener">LinkedIn</a></li>
                    <li><?php echo ptdt_icon('github'); ?><a href="<?php echo Format::htmlchars(PTDT_URL_GITHUB); ?>" rel="noopener">GitHub</a></li>
                    <li><?php echo ptdt_icon('globe'); ?><a href="<?php echo Format::htmlchars(PTDT_URL_SITE); ?>" rel="noopener"><?php
                        echo Format::htmlchars(preg_replace('#^https?://#', '', PTDT_URL_SITE)); ?></a></li>
                </ul>
            </div>
        </div>
        <div class="foot-legal">
            <p><?php echo __('Copyright &copy;'); ?> <?php echo date('Y'); ?> <?php echo $ptdt_company; ?> - <?php echo __('All rights reserved.'); ?>
                <a id="poweredBy" href="https://osticket.com" target="_blank" rel="noopener"><?php echo __('Helpdesk software - powered by osTicket'); ?></a></p>
        </div>
    </footer>
</div>
</div>
<?php
} ?>
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
