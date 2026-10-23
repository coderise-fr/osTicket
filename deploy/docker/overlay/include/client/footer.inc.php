<?php
/* Theme "Parcours" (Coderise) - overrides include/client/footer.inc.php.
 * Closes the <main> opened by header.inc.php, then the dark footer (logo,
 * signature, contact, response times, "Other resources" pages). Same end of
 * page as the core template: #overlay, #loading, i18n script, getConfig(). */
if (!function_exists('pcrs__'))
    require_once INCLUDE_DIR . 'client/parcours/parcours.inc.php';

// Thank-you page (open.php after a successful POST, $ticket set by open.php):
// where the new ticket stands, from real data (number, account access)
if (basename($_SERVER['SCRIPT_NAME']) == 'open.php' && isset($ticket) && $ticket instanceof Ticket) { ?>
<section class="how done-route" aria-labelledby="done-title">
  <h2 id="done-title"><?php echo pcrs__('Your request, step by step'); ?></h2>
  <ol class="route">
    <li class="done"><span class="node" aria-hidden="true"><?php echo pcrs_icon('check'); ?></span><span class="txt"><strong><?php
        echo pcrs__('You describe'); ?></strong><small><?php echo pcrs__('done'); ?></small></span></li>
    <li class="done"><span class="node" aria-hidden="true"><?php echo pcrs_icon('check'); ?></span><span class="txt"><strong><?php
        echo pcrs__('You get a number'); ?></strong><small>#<?php echo $ticket->getNumber(); ?></small></span></li>
    <li class="current" aria-current="step"><span class="node" aria-hidden="true">3</span><span class="txt"><strong><?php
        echo pcrs__('We reply'); ?></strong><small><?php echo pcrs__('By email and on this portal'); ?></small></span></li>
    <li class="todo"><span class="node" aria-hidden="true">4</span><span class="txt"><strong><?php
        echo pcrs__('You confirm'); ?></strong><small><?php echo pcrs__('The ticket is closed'); ?></small></span></li>
  </ol>
<?php if ($thisclient && $thisclient->isValid() && !$thisclient->isGuest()) { ?>
  <p class="actions"><a class="btn btn-primary" href="<?php echo ROOT_PATH; ?>tickets.php?id=<?php echo $ticket->getId(); ?>"><?php
    echo pcrs__('Follow this ticket'); ?></a></p>
<?php } ?>
</section>
<?php
} ?>
</div>
</main>
<footer class="site-foot on-dark" id="footer">
  <div class="wrap foot-in">
    <div class="f-brand">
      <img class="logo" src="<?php echo pcrs_asset('img/logo-horizontal-sombre.png'); ?>" width="2041" height="345" alt="Coderise">
      <p class="signature"><?php
        // "Le code qui fait décoller vos projets": the word "décoller" in cyan
        echo sprintf(Format::htmlchars(pcrs__('The code that makes your projects %s')),
            '<span class="up">' . Format::htmlchars(pcrs__('take off')) . '</span>'); ?></p>
    </div>
    <div>
      <h2 class="f-title"><?php echo pcrs__('Contact us'); ?></h2>
      <ul class="f-list">
        <li><?php echo pcrs_icon('mail'); ?><a href="mailto:contact@coderise.fr">contact@coderise.fr</a></li>
        <li><?php echo pcrs_icon('phone'); ?><a href="tel:+33669939286">+33 (0)6 69 93 92 86</a></li>
        <li><?php echo pcrs_icon('pin'); ?><span>Aix-en-Provence</span></li>
      </ul>
    </div>
    <div>
      <h2 class="f-title"><?php echo pcrs__('Response times'); ?></h2>
      <ul class="f-plain">
        <li><?php echo pcrs__('<strong>Standard support:</strong> business days.'); ?></li>
        <li><?php echo pcrs__('<strong>Priority support:</strong> first reply within 4 h, 24/7.'); ?></li>
        <li class="f-note"><?php echo pcrs__('Office hours (Monday to Friday, 8:30 am to 5 pm) are not those of the Priority support.'); ?></li>
      </ul>
    </div>
    <div>
      <h2 class="f-title"><?php echo pcrs__('Resources'); ?></h2>
      <ul class="f-list">
<?php
$pcrs_res = Page::getActivePages()->filter(array('type'=>'other'));
foreach ($pcrs_res as $pcrs_p) { ?>
        <li><?php echo pcrs_icon('doc'); ?><a href="<?php echo ROOT_PATH; ?>pages/<?php echo $pcrs_p->getNameAsSlug();
            ?>"><?php echo Format::htmlchars($pcrs_p->getLocalName()); ?></a></li>
<?php } ?>
        <li><?php echo pcrs_icon('globe'); ?><a href="https://www.coderise.fr">www.coderise.fr</a></li>
      </ul>
    </div>
  </div>
  <div class="wrap foot-legal">
    <p><?php echo __('Copyright &copy;'); ?> <?php echo date('Y'); ?> <?php
        echo Format::htmlchars((string) $ost->company ?: 'osTicket.com'); ?> - <?php echo __('All rights reserved.'); ?>
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
