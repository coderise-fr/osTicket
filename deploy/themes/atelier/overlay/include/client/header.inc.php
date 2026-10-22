<?php
/* Atelier theme (deploy/themes/atelier): replaces include/client/header.inc.php.
 * Same behaviour as the original header (HTTP headers, CSP, language links,
 * scripts, extra headers, system and page messages); new markup and the
 * theme stylesheet instead of assets/default/css/theme.css. */
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');

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

// Page identifiers used by the stylesheet: script name and included view.
$atelier_page = preg_replace('/[^a-z0-9]+/', '-',
    strtolower(basename($_SERVER['SCRIPT_NAME'], '.php')));
$atelier_classes = array('atelier', 'p-' . $atelier_page);
if (strpos($_SERVER['SCRIPT_NAME'], '/kb/') !== false)
    $atelier_classes[] = 'p-kb';
if (isset($inc) && is_string($inc))
    $atelier_classes[] = 'v-' . preg_replace('/[^a-z0-9]+/', '-',
        strtolower(preg_replace('/(\.inc)?\.php$/', '', basename($inc))));
// offline.php is included by client.inc.php under the name of the page asked for
if (is_object($ost) && !$ost->isSystemOnline())
    $atelier_classes[] = 'p-offline';
if ($atelier_page == 'open' && isset($ticket) && $ticket)
    $atelier_classes[] = 'v-thanks';
$atelier_signed_in = ($thisclient && is_object($thisclient)
    && $thisclient->isValid() && !$thisclient->isGuest());
if ($atelier_signed_in)
    $atelier_classes[] = 'is-signed-in';
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
    <meta name="theme-color" content="#000F26">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/osticket.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/typeahead.css"
         media="screen" />
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.13.2.custom.min.css"
        rel="stylesheet" media="screen" />
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/jquery-ui-timepicker-addon.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/thread.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css" media="screen">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/flags.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>assets/atelier/css/atelier.css?v=<?php echo ATELIER_VERSION; ?>" media="all">
    <link rel="icon" type="image/svg+xml" href="<?php echo ROOT_PATH; ?>assets/atelier/img/favicon.svg">
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH; ?>assets/atelier/img/favicon-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="<?php echo ROOT_PATH; ?>assets/atelier/img/apple-touch-icon.png">
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
<body class="<?php echo implode(' ', $atelier_classes); ?>">
    <a class="skip" href="#content"><?php echo __a('Skip to content'); ?></a>
    <div id="container">
        <?php
        if($ost->getError())
            echo sprintf('<div class="error_bar sysbar" role="alert">%s</div>', $ost->getError());
        elseif($ost->getWarning())
            echo sprintf('<div class="warning_bar sysbar" role="alert">%s</div>', $ost->getWarning());
        elseif($ost->getNotice())
            echo sprintf('<div class="notice_bar sysbar" role="status">%s</div>', $ost->getNotice());
        ?>
        <header id="header" class="site-header on-dark">
          <div class="wrap bar">
            <a class="brand" id="logo" href="<?php echo ROOT_PATH; ?>index.php"
                title="<?php echo __('Support Center'); ?>"><?php
            if ($cfg->getClientLogoId()) { ?>
                <img class="brand-logo" src="<?php echo ROOT_PATH; ?>logo.php" alt="<?php
                    echo Format::htmlchars($ost->getConfig()->getTitle()); ?>"><?php
            } else {
                // Official horizontal logo (cut out from the brand files, never
                // redrawn): 24 px high = X, 142 px wide (minimum 120 px).
                ?><img class="logo" src="<?php echo ROOT_PATH; ?>assets/atelier/img/logo-coderise-horizontal.png"
                    width="142" height="24" alt="<?php echo Format::htmlchars(ATELIER_BRAND); ?>"><span class="visually-hidden"> &mdash; <?php
                    echo Format::htmlchars($ost->getConfig()->getTitle()); ?></span><?php
            } ?></a>
<?php
        $atelier_navs = ($nav && ($navs = $nav->getNavLinks()) && is_array($navs)) ? $navs : array();
        $atelier_nav_html = '';
        foreach ($atelier_navs as $atelier_nav_name => $atelier_nav_item) {
            $atelier_nav_html .= sprintf('<li><a class="%s %s" href="%s"%s>%s</a></li>%s',
                $atelier_nav_item['active'] ? 'active' : '', Format::htmlchars($atelier_nav_name),
                ROOT_PATH . $atelier_nav_item['href'],
                $atelier_nav_item['active'] ? ' aria-current="page"' : '',
                $atelier_nav_item['desc'], "\n");
        }
        if ($atelier_navs) { ?>
            <nav class="site-nav" aria-label="<?php echo Format::htmlchars(__a('Main menu')); ?>">
            <ul id="nav" class="menu">
<?php echo $atelier_nav_html; ?>
            </ul>
            </nav>
<?php   } ?>
            <div class="who">
<?php
            if ($atelier_signed_in) { ?>
                <a class="me" href="<?php echo ROOT_PATH; ?>profile.php" title="<?php echo __('Profile'); ?>"><span class="avatar sm" aria-hidden="true"><?php
                    echo Format::htmlchars(atelier_initials($thisclient->getName())); ?></span><span class="me-name"><?php
                    echo Format::htmlchars($thisclient->getName()); ?></span><span class="visually-hidden"> (<?php
                    echo __('Profile'); ?>)</span></a>
                <a class="out" href="<?php echo $signout_url; ?>"><?php echo atelier_icon('logout', 16); ?><span><?php echo __('Sign Out'); ?></span></a>
<?php
            } elseif ($nav) {
                if ($cfg->getClientRegistrationMode() == 'public') { ?>
                <span class="guest"><?php echo __('Guest User'); ?></span>
<?php           }
                if ($thisclient && $thisclient->isValid() && $thisclient->isGuest()) { ?>
                <a class="out" href="<?php echo $signout_url; ?>"><?php echo atelier_icon('logout', 16); ?><span><?php echo __('Sign Out'); ?></span></a>
<?php           }
                elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
                <a class="signin" href="<?php echo $signin_url; ?>"><?php echo atelier_icon('login', 18); ?><span><?php echo __('Sign In'); ?></span></a>
<?php           }
            }

if (($all_langs = Internationalization::getConfiguredSystemLanguages())
    && (count($all_langs) > 1)
) {
    $atelier_lang_current = strtolower((string) Internationalization::getCurrentLanguage());
    $qs = array();
    parse_str($_SERVER['QUERY_STRING'], $qs); ?>
                <span class="lang" role="group" aria-label="<?php echo Format::htmlchars(__('Language')); ?>">
<?php
    foreach ($all_langs as $code=>$info) {
        list($lang, $locale) = explode('_', $code);
        $qs['lang'] = $code;
        $atelier_is_current = ($atelier_lang_current == strtolower($code)
            || $atelier_lang_current == strtolower($lang)); ?>
                    <a class="flag-<?php echo strtolower($info['flag'] ?: $locale ?: $lang); ?>"
                        href="?<?php echo Format::htmlchars(http_build_query($qs)); ?>"
                        hreflang="<?php echo Format::htmlchars(Internationalization::rfc1766($code)); ?>"
                        title="<?php echo Format::htmlchars(Internationalization::getLanguageDescription($code)); ?>"<?php
                        if ($atelier_is_current) echo ' aria-current="true"'; ?>><?php
                        echo Format::htmlchars(strtoupper($lang)); ?></a>
<?php } ?>
                </span>
<?php } ?>
            </div>
<?php if ($atelier_navs) {
            // Small screens: the same links in a disclosure (no script).
            // The desktop list above is hidden there, this one on desktop. ?>
            <details class="menu-mobile">
                <summary><?php echo atelier_icon('menu', 20); ?><span><?php echo __a('Menu'); ?></span></summary>
                <nav aria-label="<?php echo Format::htmlchars(__a('Main menu')); ?>"><ul>
<?php echo $atelier_nav_html; ?>
                </ul></nav>
            </details>
<?php } ?>
          </div>
        </header>
        <main id="content" class="wrap main" tabindex="-1">

         <?php if($errors['err']) { ?>
            <div id="msg_error" class="alert alert-error" role="alert"><?php echo $errors['err']; ?></div>
         <?php }elseif($msg) { ?>
            <div id="msg_notice" class="alert alert-notice" role="status"><?php echo $msg; ?></div>
         <?php }elseif($warn) { ?>
            <div id="msg_warning" class="alert alert-warning" role="alert"><?php echo $warn; ?></div>
         <?php } ?>
