<?php
/* "Ticket de caisse" theme (deploy/themes/ownalytics-ticket-de-caisse):
 * replaces include/client/header.inc.php.
 * Same behaviour as the original header (HTTP headers, CSP, language links,
 * scripts, extra headers, system and page messages, user links). Changes:
 * the theme stylesheet and favicon instead of assets/default/css/theme.css,
 * print.css and flags.css; the Ownalytics logotype (unless a client logo is
 * uploaded); a skip link; the menu in a labelled <nav> with aria-current;
 * language links as text; page classes on <body> for the stylesheet; the
 * content area is a <main> (closed by the theme's footer.inc.php); on the
 * ticket list and on a ticket, the support contract level as a receipt line
 * (only with the optional sla_first_response plugin, see theme.php). */
require_once(INCLUDE_DIR . 'client/ownalytics-ticket-de-caisse/theme.php');

$title=($cfg && is_object($cfg) && $cfg->getTitle())
    ? $cfg->getTitle() : 'osTicket :: '.__('Support Ticket System');
$signin_url = ROOT_PATH . "login.php"
    . ($thisclient ? "?e=".urlencode($thisclient->getEmail()) : "");
$signout_url = ROOT_PATH . "logout.php?auth=".$ost->getLinkToken();

header("Content-Type: text/html; charset=UTF-8");
header("Content-Security-Policy: frame-ancestors ".$cfg->getAllowIframes()."; script-src 'self' 'unsafe-inline'; object-src 'none'");

if (($lang = Internationalization::getCurrentLanguage())) {
    $langs = array_unique(array($lang, $cfg->getPrimaryLanguage()));
    $langs = Internationalization::rfc1766($langs);
    header("Content-Language: ".implode(', ', $langs));
}

// Page identifiers for the stylesheet: script name (p-*, p-kb-* in kb/,
// p-pages-* in pages/), section (p-kb, p-pages), view (v-*).
$otdc_page = preg_replace('/[^a-z0-9]+/', '-',
    strtolower(basename($_SERVER['SCRIPT_NAME'], '.php')));
$otdc_section = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/kb/') !== false)
    $otdc_section = 'kb';
elseif (strpos($_SERVER['SCRIPT_NAME'], '/pages/') !== false)
    $otdc_section = 'pages';
$otdc_classes = array('otdc', 'p-' . ($otdc_section ? $otdc_section . '-' : '') . $otdc_page);
if ($otdc_section)
    $otdc_classes[] = 'p-' . $otdc_section;
if (isset($inc) && is_string($inc))
    $otdc_classes[] = 'v-' . preg_replace('/[^a-z0-9]+/', '-',
        strtolower(preg_replace('/(\.inc)?\.php$/', '', basename($inc))));
// The landing page and the offline page are printed on the plum background.
// Not when another view is shown in their place (client.inc.php includes
// profile.php from any script while a password change is required).
elseif (!$otdc_section && $otdc_page == 'index')
    $otdc_classes[] = 'is-landing';
elseif (!$otdc_section && $otdc_page == 'offline')
    $otdc_classes[] = 'is-offline';
$otdc_signed_in = ($thisclient && is_object($thisclient)
    && $thisclient->isValid() && !$thisclient->isGuest());
if ($otdc_signed_in)
    $otdc_classes[] = 'is-signed-in';
?>
<!DOCTYPE html>
<html<?php
if ($lang
        && ($info = Internationalization::getLanguageInfo($lang))
        && (@$info['direction'] == 'rtl'))
    echo ' dir="rtl" class="rtl"';
if ($lang) {
    echo ' lang="' . Format::htmlchars(Internationalization::rfc1766($lang)) . '"';
}

// Dropped IE Support Warning
if (osTicket::is_ie())
    $ost->setWarning(__('osTicket no longer supports Internet Explorer.'));
?>>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title><?php echo Format::htmlchars($title); ?></title>
    <meta name="description" content="customer support platform">
    <meta name="keywords" content="osTicket, Customer support system, support ticket system">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#3B1E54">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/osticket.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/typeahead.css"
         media="screen" />
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.13.2.custom.min.css"
        rel="stylesheet" media="screen" />
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/jquery-ui-timepicker-addon.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/thread.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css" media="screen">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css">
    <link rel="stylesheet" href="<?php echo otdc_asset('css/theme.css'); ?>" media="all">
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="<?php echo otdc_asset('img/favicon.svg'); ?>">
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-32x32.png" sizes="32x32" />
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-timepicker-addon.js"></script>
    <script src="<?php echo ROOT_PATH; ?>js/osticket.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/filedrop.field.js"></script>
    <script src="<?php echo ROOT_PATH; ?>js/bootstrap-typeahead.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-plugins.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-osticket.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/select2.min.js"></script>
    <?php
    if($ost && ($headers=$ost->getExtraHeaders())) {
        echo "\n\t".implode("\n\t", $headers)."\n";
    }

    // Offer alternate links for search engines
    // @see https://support.google.com/webmasters/answer/189077?hl=en
    if (($all_langs = Internationalization::getConfiguredSystemLanguages())
        && (count($all_langs) > 1)
    ) {
        $langs = Internationalization::rfc1766(array_keys($all_langs));
        $qs = array();
        parse_str($_SERVER['QUERY_STRING'], $qs);
        foreach ($langs as $L) {
            $qs['lang'] = $L; ?>
        <link rel="alternate" href="//<?php echo $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']); ?>?<?php
            echo http_build_query($qs); ?>" hreflang="<?php echo $L; ?>" />
<?php
        } ?>
        <link rel="alternate" href="//<?php echo $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']); ?>"
            hreflang="x-default" />
<?php
    }
    ?>
</head>
<body class="<?php echo implode(' ', $otdc_classes); ?>">
    <a class="skip" href="#content"><?php echo otdc__('Skip to content'); ?></a>
    <div id="container">
        <?php
        if($ost->getError())
            echo sprintf('<div class="error_bar" role="alert">%s</div>', $ost->getError());
        elseif($ost->getWarning())
            echo sprintf('<div class="warning_bar" role="alert">%s</div>', $ost->getWarning());
        elseif($ost->getNotice())
            echo sprintf('<div class="notice_bar" role="status">%s</div>', $ost->getNotice());
        ?>
        <div id="header">
            <a id="logo" href="<?php echo ROOT_PATH; ?>index.php"
            title="<?php echo __('Support Center'); ?>"><?php
            if ($cfg->getClientLogoId()) { ?>
                <img src="<?php echo ROOT_PATH; ?>logo.php" border=0 alt="<?php
                echo Format::htmlchars($ost->getConfig()->getTitle()); ?>"><?php
            } else {
                echo otdc_mark('#3B1E54'); ?><span class="wm"><?php
                    echo Format::htmlchars(OTDC_BRAND); ?></span><span class="wm-sub"><?php
                    echo otdc__('Support'); ?></span><span class="visually-hidden"> &mdash; <?php
                    echo Format::htmlchars($ost->getConfig()->getTitle()); ?></span><?php
            } ?></a>
            <div class="pull-right flush-right">
            <p class="who">
             <?php
                if ($otdc_signed_in) {
                 echo Format::htmlchars($thisclient->getName()).'&nbsp;|';
                 ?>
                <a href="<?php echo ROOT_PATH; ?>profile.php"><?php echo __('Profile'); ?></a> |
                <a href="<?php echo ROOT_PATH; ?>tickets.php"><?php echo sprintf(__('Tickets <b>(%d)</b>'), $thisclient->getNumTickets()); ?></a> -
                <a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a>
            <?php
            } elseif($nav) {
                if ($cfg->getClientRegistrationMode() == 'public') { ?>
                    <?php echo __('Guest User'); ?> | <?php
                }
                if ($thisclient && $thisclient->isValid() && $thisclient->isGuest()) { ?>
                    <a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a><?php
                }
                elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
                    <a href="<?php echo $signin_url; ?>"><?php echo __('Sign In'); ?></a>
<?php
                }
            } ?>
            </p>
<?php
if (($all_langs = Internationalization::getConfiguredSystemLanguages())
    && (count($all_langs) > 1)
) {
    $otdc_lang_current = strtolower((string) Internationalization::getCurrentLanguage());
    $qs = array();
    parse_str($_SERVER['QUERY_STRING'], $qs); ?>
            <p class="langs" role="group" aria-label="<?php echo Format::htmlchars(__('Language')); ?>">
<?php
    foreach ($all_langs as $code=>$info) {
        list($lang, $locale) = explode('_', $code);
        $qs['lang'] = $code;
        $otdc_is_current = ($otdc_lang_current == strtolower($code)
            || $otdc_lang_current == strtolower($lang)); ?>
                <a href="?<?php echo Format::htmlchars(http_build_query($qs)); ?>"
                    hreflang="<?php echo Format::htmlchars(Internationalization::rfc1766($code)); ?>"
                    lang="<?php echo Format::htmlchars(Internationalization::rfc1766($code)); ?>"
                    title="<?php echo Format::htmlchars(Internationalization::getLanguageDescription($code)); ?>"<?php
                    if ($otdc_is_current) echo ' aria-current="true"'; ?>><?php
                    echo Format::htmlchars(strtoupper($lang)); ?></a>
<?php } ?>
            </p>
<?php } ?>
            </div>
        </div>
        <div class="clear"></div>
        <?php
        if($nav){ ?>
        <nav aria-label="<?php echo Format::htmlchars(otdc__('Main menu')); ?>">
        <ul id="nav" class="flush-left">
            <?php
            if($nav && ($navs=$nav->getNavLinks()) && is_array($navs)){
                foreach($navs as $name =>$nav) {
                    echo sprintf('<li><a class="%s %s" href="%s"%s>%s</a></li>%s',
                        $nav['active']?'active':'', Format::htmlchars($name),
                        (ROOT_PATH.$nav['href']),
                        $nav['active'] ? ' aria-current="page"' : '',
                        $nav['desc'], "\n");
                }
            } ?>
        </ul>
        </nav>
        <?php
        }else{ ?>
         <hr class="no-nav">
        <?php
        } ?>
        <main id="content" tabindex="-1">

         <?php if($errors['err']) { ?>
            <div id="msg_error" role="alert"><?php echo $errors['err']; ?></div>
         <?php }elseif($msg) { ?>
            <div id="msg_notice" role="status"><?php echo $msg; ?></div>
         <?php }elseif($warn) { ?>
            <div id="msg_warning" role="alert"><?php echo $warn; ?></div>
         <?php }
// Support contract level, printed once at the top of the receipt (optional
// sla_first_response plugin, see otdc_contract()): on the ticket list, the
// signed-in client's level (not one per row); on a ticket, the level of its
// OWNER (requester), also for a collaborator or a guest. Variables set by
// tickets.php before it includes this header.
if (isset($inc) && $inc === 'tickets.inc.php' && $otdc_signed_in) {
    echo otdc_contract_line(otdc_contract(), otdc__('Your support contract'));
}
elseif (isset($inc, $ticket) && $inc === 'view.inc.php' && $ticket instanceof Ticket
        && $thisclient && $thisclient->isValid()) {
    echo otdc_contract_line(otdc_contract($ticket),
        ((int) $ticket->getOwnerId() === (int) $thisclient->getId())
            ? otdc__('Your current support contract')
            : otdc__("Requester's current support contract"));
} ?>
