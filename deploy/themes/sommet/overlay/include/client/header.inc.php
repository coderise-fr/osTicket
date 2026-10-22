<?php
/* Sommet theme (deploy/themes/sommet): replaces include/client/header.inc.php.
 * Same behaviour as the original header (HTTP headers, CSP, language links,
 * scripts, extra headers, system and page messages); new markup, the theme
 * stylesheet instead of assets/default/css/theme.css, and the dark title
 * band of inner pages (see include/client/sommet/sommet.inc.php). */
require_once(INCLUDE_DIR . 'client/sommet/sommet.inc.php');

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
$sommet_script = preg_replace('/[^a-z0-9]+/', '-',
    strtolower(basename($_SERVER['SCRIPT_NAME'], '.php')));
$sommet_in_kb = (strpos($_SERVER['SCRIPT_NAME'], '/kb/') !== false);
$sommet_classes = array('sommet', 'p-' . ($sommet_in_kb ? 'kb-' : '') . $sommet_script);
if ($sommet_in_kb)
    $sommet_classes[] = 'p-kb';
if (isset($inc) && is_string($inc))
    $sommet_classes[] = 'v-' . preg_replace('/[^a-z0-9]+/', '-',
        strtolower(preg_replace('/(\.inc)?\.php$/', '', basename($inc))));
if ($sommet_script == 'open' && isset($ticket) && $ticket)
    $sommet_classes[] = 'v-thanks';
$sommet_signed_in = ($thisclient && is_object($thisclient)
    && $thisclient->isValid() && !$thisclient->isGuest());
if ($sommet_signed_in)
    $sommet_classes[] = 'is-signed-in';
// index.php of the theme shows its own hero instead of the band.
$sommet_hero = isset($sommet_hero) && $sommet_hero;
if ($sommet_hero)
    $sommet_classes[] = 'has-hero';

// Navigation: osTicket's links, "Open a New Ticket" becomes the call to action.
$sommet_links = array();
$sommet_cta = null;
$sommet_active = null;
if ($nav && ($navs = $nav->getNavLinks()) && is_array($navs)) {
    foreach ($navs as $name => $item) {
        if ($item['active'])
            $sommet_active = $name;
        if ($name == 'new')
            $sommet_cta = $item;
        else
            $sommet_links[$name] = $item;
    }
}

// Breadcrumbs of the band: real sections only.
$sommet_crumbs = array(array(Format::htmlchars(__s('Home')), ROOT_PATH . 'index.php'));
if ($sommet_in_kb) {
    $sommet_crumbs[] = array(Format::htmlchars(__('Knowledgebase')), ROOT_PATH . 'kb/index.php');
    $sommet_cat = null;
    if (isset($faq) && is_object($faq))
        $sommet_cat = $faq->getCategory();
    elseif (isset($category) && is_object($category))
        $sommet_cat = $category;
    if ($sommet_cat)
        $sommet_crumbs[] = array(Format::htmlchars($sommet_cat->getFullName()),
            ROOT_PATH . 'kb/faq.php?cid=' . $sommet_cat->getId());
    if (isset($faq) && is_object($faq))
        $sommet_crumbs[] = array(Format::htmlchars($faq->getLocalQuestion()), null);
    elseif (isset($_REQUEST['q']) || isset($_REQUEST['topicId']))
        $sommet_crumbs[] = array(Format::htmlchars(__('Search Results')), null);
}
elseif ($sommet_script == 'tickets' && isset($inc) && $inc == 'open.inc.php' && $sommet_cta) {
    // No ticket yet: osTicket shows the "open a ticket" form instead of the list.
    $sommet_crumbs[] = array($sommet_cta['desc'], null);
}
elseif ($sommet_script == 'tickets' && $sommet_signed_in) {
    $sommet_crumbs[] = array(Format::htmlchars(__('Tickets')), ROOT_PATH . 'tickets.php');
    if (isset($ticket) && is_object($ticket))
        $sommet_crumbs[] = array('#' . Format::htmlchars($ticket->getNumber()), null);
}
elseif (isset($ticket) && is_object($ticket) && $sommet_script != 'open') {
    $sommet_crumbs[] = array('#' . Format::htmlchars($ticket->getNumber()), null);
}
elseif ($sommet_script == 'profile') {
    $sommet_crumbs[] = array(Format::htmlchars(__('Profile')), null);
}
elseif (isset($inc) && is_string($inc) && in_array(basename($inc), array(
        'login.inc.php', 'accesslink.inc.php', 'register.inc.php', 'register.confirm.inc.php',
        'register.confirmed.inc.php', 'pwreset.request.php', 'pwreset.sent.php', 'pwreset.login.php'))) {
    switch (basename($inc)) {
    case 'login.inc.php': $sommet_label = __('Sign In'); break;
    case 'accesslink.inc.php': $sommet_label = __('Check Ticket Status'); break;
    case 'pwreset.request.php': case 'pwreset.sent.php': case 'pwreset.login.php':
        $sommet_label = __('Forgot My Password'); break;
    default: $sommet_label = __('Account Registration');
    }
    $sommet_crumbs[] = array(Format::htmlchars($sommet_label), null);
}
elseif ($sommet_active && isset($navs[$sommet_active]) && $sommet_active != 'home') {
    $sommet_crumbs[] = array(Format::htmlchars($navs[$sommet_active]['desc']), null);
}
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
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css">
    <link rel="stylesheet" href="<?php echo sommet_asset('css/sommet.css'); ?>" media="all">
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="<?php echo sommet_asset('img/favicon.svg'); ?>">
    <link rel="icon" type="image/png" href="<?php echo sommet_asset('img/favicon-32.png'); ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?php echo sommet_asset('img/favicon-180.png'); ?>">
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
<body class="<?php echo implode(' ', $sommet_classes); ?>">
<a class="skip" href="#content"><?php echo __s('Skip to content'); ?></a>
    <div id="container">
        <?php
        if($ost->getError())
            echo sprintf('<div class="error_bar sysbar">%s</div>', $ost->getError());
        elseif($ost->getWarning())
            echo sprintf('<div class="warning_bar sysbar">%s</div>', $ost->getWarning());
        elseif($ost->getNotice())
            echo sprintf('<div class="notice_bar sysbar">%s</div>', $ost->getNotice());
        ?>
        <header id="header" class="site-head">
        <div class="wrap head-in">
            <a class="brand" id="logo" href="<?php echo ROOT_PATH; ?>index.php"
                title="<?php echo __('Support Center'); ?>"><?php
            if ($cfg->getClientLogo()) { ?>
                <img class="logo custom-logo" src="<?php echo ROOT_PATH; ?>logo.php" alt="<?php
                    echo Format::htmlchars($ost->getConfig()->getTitle()); ?>"><?php
            } else { ?>
                <img class="logo" src="<?php echo sommet_asset('img/logo-coderise-horizontal.png'); ?>"
                    width="2041" height="345" alt="Coderise"><?php
            } ?></a>
<?php
        if ($sommet_links) { ?>
            <nav class="nav-desktop" aria-label="<?php echo Format::htmlchars(__s('Main menu')); ?>">
            <ul id="nav">
<?php
            foreach ($sommet_links as $name => $item) {
                echo sprintf('<li><a class="%s %s" href="%s"%s>%s</a></li>%s',
                    $item['active'] ? 'active' : '', $name, ROOT_PATH . $item['href'],
                    $item['active'] ? ' aria-current="page"' : '', $item['desc'], "\n");
            } ?>
            </ul>
            </nav>
<?php
        } ?>
            <div class="who">
<?php
        if ($sommet_signed_in) { ?>
                <a class="me" href="<?php echo ROOT_PATH; ?>profile.php"<?php
                    if ($sommet_script == 'profile') echo ' aria-current="page"'; ?>><?php
                    echo sommet_icon('user', 16); ?><span class="me-name"><?php
                    echo Format::htmlchars($thisclient->getName()); ?></span><span class="visually-hidden"> (<?php
                    echo __('Profile'); ?>)</span></a>
                <a class="out" href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a>
<?php
        } elseif ($nav) {
            if ($thisclient && $thisclient->isValid() && $thisclient->isGuest()) { ?>
                <a class="out" href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a>
<?php
            } elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
                <a class="in" href="<?php echo $signin_url; ?>"><?php echo __('Sign In'); ?></a>
<?php
            }
        }
if (($all_langs = Internationalization::getConfiguredSystemLanguages())
    && (count($all_langs) > 1)
) {
    $qs = array();
    parse_str($_SERVER['QUERY_STRING'], $qs);
    $current = Internationalization::getCurrentLanguage(); ?>
                <span class="lang" role="group" aria-label="<?php echo Format::htmlchars(__('Language')); ?>"><?php
    foreach ($all_langs as $code=>$info) {
        list($lang, $locale) = explode('_', $code);
        $qs['lang'] = $code; ?>
                    <a href="?<?php echo Format::htmlchars(http_build_query($qs));
                    ?>" lang="<?php echo Format::htmlchars($lang); ?>" title="<?php
                    echo Format::htmlchars(Internationalization::getLanguageDescription($code)); ?>"<?php
                    if ($code == $current) echo ' aria-current="true"'; ?>><?php
                    echo Format::htmlchars(strtoupper($lang)); ?></a><?php
    } ?>
                </span>
<?php
} ?>
            </div>
<?php
        if ($sommet_cta) { ?>
            <a class="btn btn-primary head-cta<?php if ($sommet_cta['active']) echo ' active'; ?>"
                href="<?php echo ROOT_PATH . $sommet_cta['href']; ?>"<?php
                if ($sommet_cta['active']) echo ' aria-current="page"'; ?>><?php
                echo sommet_icon('plus', 18); ?><span><?php echo $sommet_cta['desc']; ?></span></a>
<?php
        }
        if ($sommet_links || $sommet_cta) { ?>
            <details class="menu-mobile">
                <summary><?php echo sommet_icon('menu', 20); ?><span><?php echo __s('Menu'); ?></span></summary>
                <ul>
<?php
            foreach ($sommet_links as $name => $item) {
                echo sprintf('<li><a class="%s" href="%s"%s>%s</a></li>%s',
                    $name, ROOT_PATH . $item['href'],
                    $item['active'] ? ' aria-current="page"' : '', $item['desc'], "\n");
            }
            if ($sommet_cta) {
                echo sprintf('<li><a class="new" href="%s"%s>%s</a></li>%s',
                    ROOT_PATH . $sommet_cta['href'],
                    $sommet_cta['active'] ? ' aria-current="page"' : '', $sommet_cta['desc'], "\n");
            } ?>
                </ul>
            </details>
<?php
        } ?>
        </div>
        </header>
<?php
        if (!$sommet_hero) {
            // Title used only when the page has no <h1> of its own.
            $sommet_fallback = '';
            if ($sommet_in_kb)
                $sommet_fallback = Format::htmlchars(__('Knowledgebase'));
            elseif (isset($ticket) && is_object($ticket))
                $sommet_fallback = Format::htmlchars(sprintf('%s #%s', __('Ticket'), $ticket->getNumber()));
            elseif (count($sommet_crumbs) > 1)
                $sommet_fallback = $sommet_crumbs[count($sommet_crumbs) - 1][0];
            // Knowledge base: search form in the band (same target as the
            // search of the home page, kb/faq.php?a=search&q=).
            $sommet_extra = '';
            if ($sommet_in_kb && $cfg->isKnowledgebaseEnabled()) {
                $sommet_extra = '<form class="bigsearch" method="get" action="' . ROOT_PATH . 'kb/faq.php" role="search">'
                    . '<input type="hidden" name="a" value="search"/>'
                    . sommet_icon('search', 20)
                    . '<label class="visually-hidden" for="sommet-kb-search">' . __('Search our knowledge base') . '</label>'
                    . '<input id="sommet-kb-search" type="text" name="q" value="'
                    . Format::htmlchars(isset($_REQUEST['q']) ? (string) $_REQUEST['q'] : '') . '" placeholder="'
                    . Format::htmlchars(__('Search our knowledge base')) . '"/>'
                    . '<button type="submit" class="btn btn-primary">' . __('Search') . '</button></form>';
            }
            sommet_banner_open($sommet_crumbs, $sommet_fallback, $sommet_extra);
        }
?>
        <main id="content" class="wrap" tabindex="-1">

         <?php if($errors['err']) { ?>
            <div id="msg_error" role="alert"><?php echo $errors['err']; ?></div>
         <?php }elseif($msg) { ?>
            <div id="msg_notice" role="status"><?php echo $msg; ?></div>
         <?php }elseif($warn) { ?>
            <div id="msg_warning" role="status"><?php echo $warn; ?></div>
         <?php } ?>
