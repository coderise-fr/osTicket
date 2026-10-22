<?php
/* Theme "Poste de travail" (Ownalytics) - overrides include/client/header.inc.php.
 * Same PHP prelude, headers, scripts and messages as the core template; the
 * markup is a top bar + side rail + <main>, closed by footer.inc.php. */
require_once INCLUDE_DIR . 'client/poste-de-travail/theme.php';
require_once INCLUDE_DIR . 'class.page.php';

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

// Signed-in client with an account (not a guest from a ticket access link)
$ptdt_member = ($thisclient && is_object($thisclient) && $thisclient->isValid()
    && !$thisclient->isGuest());
// Page identifier for the stylesheet: p-index, p-tickets, p-kb-faq...
$ptdt_page = (string) $_SERVER['SCRIPT_NAME'];
if (strpos($ptdt_page, ROOT_PATH) === 0)
    $ptdt_page = substr($ptdt_page, strlen(ROOT_PATH));
$ptdt_page = 'p-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(preg_replace('/\.php$/', '', $ptdt_page)));
?>
<!DOCTYPE html>
<html<?php
if ($lang
        && ($info = Internationalization::getLanguageInfo($lang))
        && (@$info['direction'] == 'rtl'))
    echo ' dir="rtl" class="rtl"';
if ($lang) {
    echo ' lang="' . Format::htmlchars(str_replace('_', '-', $lang)) . '"';
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
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/typeahead.css" media="screen">
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.13.2.custom.min.css"
        rel="stylesheet" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/jquery-ui-timepicker-addon.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/thread.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css" media="screen">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css">
    <link rel="stylesheet" href="<?php echo ptdt_asset('css/theme.css'); ?>" media="all">
    <link rel="icon" type="image/svg+xml" href="<?php echo ptdt_asset('img/favicon.svg'); ?>">
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-32x32.png" sizes="32x32">
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
    <script src="<?php echo ptdt_asset('js/theme.js'); ?>" defer></script>
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
<body class="ptdt <?php echo $ptdt_page; echo $nav ? ' has-rail' : ' no-rail'; ?>">
<a class="skip" href="#content"><?php echo ptdt__('Skip to main content'); ?></a>
<?php
if($ost->getError())
    echo sprintf('<div class="error_bar" role="alert">%s</div>', $ost->getError());
elseif($ost->getWarning())
    echo sprintf('<div class="warning_bar" role="alert">%s</div>', $ost->getWarning());
elseif($ost->getNotice())
    echo sprintf('<div class="notice_bar" role="status">%s</div>', $ost->getNotice());
?>
<header class="topbar" id="header">
    <a class="brand" id="logo" href="<?php echo ROOT_PATH; ?>index.php"
        title="<?php echo __('Support Center'); ?>"><?php
    // A logo uploaded in the admin panel wins; otherwise pictogram + helpdesk title
    if ($ost->getConfig()->getClientLogoId()) { ?><img src="<?php echo ROOT_PATH; ?>logo.php" alt="<?php
        echo Format::htmlchars($ost->getConfig()->getTitle()); ?>"><?php
    } else {
        echo ptdt_logo_mark(); ?><span class="wm"><?php
        echo Format::htmlchars($ost->getConfig()->getTitle()); ?></span><?php
    } ?></a>
<?php
if ($nav && $cfg && $cfg->isKnowledgebaseEnabled()) { ?>
    <form class="gsearch" role="search" method="get" action="<?php echo ROOT_PATH; ?>kb/faq.php">
        <input type="hidden" name="a" value="search">
        <?php echo ptdt_icon('search'); ?>
        <input type="search" name="q" id="gsearch-q"<?php
            // No empty value attribute: js/osticket.js focuses the first input[value=""]
            if (!empty($_REQUEST['q']) && is_string($_REQUEST['q']))
                echo ' value="'.Format::htmlchars($_REQUEST['q']).'"'; ?>
            placeholder="<?php echo __('Search our knowledge base'); ?>"
            aria-label="<?php echo __('Search our knowledge base'); ?>">
        <kbd aria-hidden="true">/</kbd>
    </form>
<?php
} ?>
    <div class="user">
<?php
if ($ptdt_member) {
    $ptdt_org = method_exists($thisclient, 'getOrganization') ? $thisclient->getOrganization() : null; ?>
        <span class="u-av" aria-hidden="true"><?php echo ptdt_initials($thisclient->getName()); ?></span>
        <span class="u-name"><?php echo Format::htmlchars($thisclient->getName()); ?></span>
<?php if ($ptdt_org) { ?>
        <span class="u-org"><?php echo Format::htmlchars($ptdt_org->getName()); ?></span>
<?php } ?>
        <a href="<?php echo ROOT_PATH; ?>profile.php"><?php echo __('Profile'); ?></a>
        <a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a>
<?php
} elseif ($nav) {
    if ($cfg->getClientRegistrationMode() == 'public') { ?>
        <span class="u-guest"><?php echo __('Guest User'); ?></span>
<?php
    }
    if ($thisclient && $thisclient->isValid() && $thisclient->isGuest()) { ?>
        <a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a>
<?php
    } elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
        <a href="<?php echo $signin_url; ?>"><?php echo __('Sign In'); ?></a>
<?php
    }
}
if (($all_langs = Internationalization::getConfiguredSystemLanguages())
    && (count($all_langs) > 1)
) {
    $qs = array();
    parse_str($_SERVER['QUERY_STRING'], $qs);
    $current = Internationalization::getCurrentLanguage(); ?>
        <span class="lang"><?php
    foreach ($all_langs as $code=>$info) {
        list($l_lang, $l_locale) = explode('_', $code);
        $qs['lang'] = $code; ?><a href="?<?php echo Format::htmlchars(http_build_query($qs)); ?>"
            hreflang="<?php echo Format::htmlchars(str_replace('_', '-', $code)); ?>"
            lang="<?php echo Format::htmlchars(str_replace('_', '-', $code)); ?>"
            title="<?php echo Format::htmlchars(Internationalization::getLanguageDescription($code)); ?>"<?php
            if ($code == $current) echo ' aria-current="true"'; ?>><?php
            echo Format::htmlchars(strtoupper($l_lang)); ?></a><?php
    } ?></span>
<?php
} ?>
    </div>
</header>
<div class="shell">
<?php
if ($nav) {
    $ptdt_icons = array('home' => 'home', 'kb' => 'book', 'new' => 'plus',
        'tickets' => 'ticket', 'status' => 'status');
    // Display order of the rail: home, tickets, new ticket, knowledge base
    $ptdt_order = array('home', 'tickets', 'status', 'new', 'kb');
    $ptdt_links = ($nav->getNavLinks() ?: array());
    uksort($ptdt_links, function($a, $b) use ($ptdt_order) {
        $x = array_search($a, $ptdt_order); $y = array_search($b, $ptdt_order);
        return ($x === false ? 99 : $x) - ($y === false ? 99 : $y);
    }); ?>
    <nav class="rail" aria-label="<?php echo ptdt__('Main menu'); ?>">
        <ul class="r-main" id="nav">
<?php
    foreach ($ptdt_links as $ptdt_key => $ptdt_link) {
        $label = $ptdt_link['desc'];
        $badge = '';
        if ($ptdt_key == 'home')
            $label = ptdt__('Home');
        elseif ($ptdt_key == 'tickets' && $ptdt_member) {
            $label = __('Tickets');
            $badge = sprintf('<b class="badge">%d</b>',
                $thisclient->getNumTickets($thisclient->canSeeOrgTickets()));
        }
        printf('<li><a class="%s" href="%s"%s%s>%s<span>%s</span>%s</a></li>'."\n",
            Format::htmlchars($ptdt_key),
            ROOT_PATH . $ptdt_link['href'],
            $ptdt_link['active'] ? ' aria-current="page"' : '',
            !empty($ptdt_link['title']) ? ' title="'.Format::htmlchars($ptdt_link['title']).'"' : '',
            ptdt_icon(isset($ptdt_icons[$ptdt_key]) ? $ptdt_icons[$ptdt_key] : 'doc', 17),
            $label, $badge);
    } ?>
        </ul>
<?php
    if ($ptdt_member) {
        $ptdt_org_t = $thisclient->canSeeOrgTickets();
        $ptdt_open = $thisclient->getNumOpenTickets($ptdt_org_t);
        $ptdt_closed = $thisclient->getNumClosedTickets($ptdt_org_t); ?>
        <p class="r-title" id="r-views"><?php echo __('Tickets'); ?></p>
        <ul class="r-views" aria-labelledby="r-views">
            <li><a href="<?php echo ROOT_PATH; ?>tickets.php?a=search&amp;status=open"><span><?php
                echo __('Open'); ?></span><b class="badge"><?php echo (int) $ptdt_open; ?></b></a></li>
            <li><a href="<?php echo ROOT_PATH; ?>tickets.php?a=search&amp;status=closed"><span><?php
                echo __('Closed'); ?></span><b class="badge"><?php echo (int) $ptdt_closed; ?></b></a></li>
        </ul>
<?php
    }
    $ptdt_pages = Page::getActivePages()->filter(array('type'=>'other'));
    if ($ptdt_pages->all()) { ?>
        <p class="r-title" id="r-help"><?php echo __('Other Resources'); ?></p>
        <ul class="r-views small" aria-labelledby="r-help">
<?php   foreach ($ptdt_pages as $ptdt_p) { ?>
            <li><a href="<?php echo ROOT_PATH; ?>pages/<?php echo $ptdt_p->getNameAsSlug();
                ?>"><span><?php echo Format::htmlchars($ptdt_p->getLocalName()); ?></span></a></li>
<?php   } ?>
        </ul>
<?php
    } ?>
    </nav>
<?php
} ?>
    <main id="content" tabindex="-1">
<?php if($errors['err']) { ?>
        <div id="msg_error" class="alert alert-error" role="alert"><?php echo $errors['err']; ?></div>
<?php }elseif($msg) { ?>
        <div id="msg_notice" class="alert alert-notice" role="status"><?php echo $msg; ?></div>
<?php }elseif($warn) { ?>
        <div id="msg_warning" class="alert alert-warning" role="alert"><?php echo $warn; ?></div>
<?php } ?>
