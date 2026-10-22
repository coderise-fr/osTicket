<?php
/* Theme "Poste de travail" (Coderise) - overrides include/client/header.inc.php.
 * Same PHP prelude, headers, scripts and messages as the core template; the
 * markup is a full height bleu nuit rail (logo, signature, navigation,
 * contact) + a column with a top bar and <main>, closed by footer.inc.php.
 * Sign in, registration, password reset and ticket access pages of a visitor
 * use a lighter "auth" layout (top bar with the logo, no rail). */
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
$ptdt_signed = ($thisclient && is_object($thisclient) && $thisclient->isValid());
// Page identifier for the stylesheet: p-index, p-tickets, p-kb-faq...
$ptdt_page = (string) $_SERVER['SCRIPT_NAME'];
if (strpos($ptdt_page, ROOT_PATH) === 0)
    $ptdt_page = substr($ptdt_page, strlen(ROOT_PATH));
$ptdt_page = 'p-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(preg_replace('/\.php$/', '', $ptdt_page)));
// Visitor pages drawn with the "auth" layout
$ptdt_auth = $nav && !$ptdt_signed
    && in_array($ptdt_page, array('p-login', 'p-account', 'p-pwreset', 'p-view'));
$ptdt_kb = ($cfg && $cfg->isKnowledgebaseEnabled());
$ptdt_can_open = ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired());

// Navigation of the rail (and of the mobile menu): osTicket's own links
$ptdt_items = array();
$ptdt_crumb = null;
if ($nav) {
    $ptdt_icons = array('home' => 'home', 'kb' => 'book', 'new' => 'plus',
        'tickets' => 'ticket', 'status' => 'status');
    $ptdt_order = array('home', 'tickets', 'status', 'new', 'kb');
    $ptdt_links = ($nav->getNavLinks() ?: array());
    uksort($ptdt_links, function($a, $b) use ($ptdt_order) {
        $x = array_search($a, $ptdt_order); $y = array_search($b, $ptdt_order);
        return ($x === false ? 99 : $x) - ($y === false ? 99 : $y);
    });
    foreach ($ptdt_links as $ptdt_key => $ptdt_link) {
        $label = $ptdt_link['desc'];
        $badge = null;
        if ($ptdt_key == 'home')
            $label = ptdt__('Home');
        elseif ($ptdt_key == 'tickets' && $ptdt_member) {
            $label = __('Tickets');
            $badge = $thisclient->getNumOpenTickets($thisclient->canSeeOrgTickets());
        }
        $ptdt_items[$ptdt_key] = array(
            'href' => ROOT_PATH . $ptdt_link['href'],
            'label' => $label,
            'title' => isset($ptdt_link['title']) ? $ptdt_link['title'] : '',
            'active' => !empty($ptdt_link['active']),
            'icon' => isset($ptdt_icons[$ptdt_key]) ? $ptdt_icons[$ptdt_key] : 'doc',
            'badge' => $badge,
        );
        if (!empty($ptdt_link['active']))
            $ptdt_crumb = $ptdt_items[$ptdt_key];
    }
}
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
    <meta name="theme-color" content="#000F26">
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
    <link rel="icon" type="image/png" href="<?php echo ptdt_asset('img/favicon-32.png'); ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?php echo ptdt_asset('img/favicon-180.png'); ?>">
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
<body class="ptdt <?php echo $ptdt_page;
    echo $ptdt_auth ? ' is-auth' : ($nav ? ' has-rail' : ' no-rail'); ?>">
<a class="skip" href="#content"><?php echo ptdt__('Skip to main content'); ?></a>
<?php
if($ost->getError())
    echo sprintf('<div class="error_bar" role="alert">%s</div>', $ost->getError());
elseif($ost->getWarning())
    echo sprintf('<div class="warning_bar" role="alert">%s</div>', $ost->getWarning());
elseif($ost->getNotice())
    echo sprintf('<div class="notice_bar" role="status">%s</div>', $ost->getNotice());

// Brand link: a logo uploaded in the admin panel wins, otherwise the Coderise logo
ob_start(); ?>
    <a class="brand" id="logo" href="<?php echo ROOT_PATH; ?>index.php"
        title="<?php echo __('Support Center'); ?>"><?php
    if ($ost->getConfig()->getClientLogoId()) { ?><img class="logo" src="<?php echo ROOT_PATH; ?>logo.php" alt="<?php
        echo Format::htmlchars($ost->getConfig()->getTitle()); ?>"><?php
    } else {
        echo ptdt_logo();
    } ?></a>
<?php
$ptdt_brand = ob_get_clean();

// Language links (only when several languages are configured)
$ptdt_langs = '';
if (($all_langs = Internationalization::getConfiguredSystemLanguages())
    && (count($all_langs) > 1)
) {
    $qs = array();
    parse_str($_SERVER['QUERY_STRING'], $qs);
    $current = Internationalization::getCurrentLanguage();
    foreach ($all_langs as $code=>$info) {
        list($l_lang, $l_locale) = explode('_', $code);
        $qs['lang'] = $code;
        $ptdt_langs .= sprintf('<a href="?%s" hreflang="%s" lang="%s" title="%s"%s>%s</a>',
            Format::htmlchars(http_build_query($qs)),
            Format::htmlchars(str_replace('_', '-', $code)),
            Format::htmlchars(str_replace('_', '-', $code)),
            Format::htmlchars(Internationalization::getLanguageDescription($code)),
            $code == $current ? ' aria-current="true"' : '',
            Format::htmlchars(strtoupper($l_lang)));
    }
    $ptdt_langs = '<span class="lang">'.$ptdt_langs.'</span>';
}

if ($ptdt_auth) { ?>
<div class="app-auth">
<header class="auth-bar on-dark" id="header">
<?php echo $ptdt_brand; ?>
    <div class="auth-links">
<?php if ($ptdt_kb) { ?>
        <a class="btn btn-line-dark" href="<?php echo ROOT_PATH; ?>kb/index.php"><?php
            echo ptdt_icon('book'); ?><span><?php echo __('Knowledgebase'); ?></span></a>
<?php } ?>
<?php if ($ptdt_can_open) { ?>
        <a class="btn btn-primary" href="<?php echo ROOT_PATH; ?>open.php"><?php
            echo ptdt_icon('plus'); ?><span><?php echo __('Open a New Ticket'); ?></span></a>
<?php } ?>
        <?php echo $ptdt_langs; ?>
    </div>
</header>
    <main id="content" class="auth-main" tabindex="-1">
<?php
} else { ?>
<div class="app">
<?php
if ($nav) {
    // Rail: logo, signature, navigation, contact; a <details> menu on mobile
    ob_start();
    foreach ($ptdt_items as $ptdt_key => $I) {
        printf('<li><a class="%s" href="%s"%s%s>%s<span>%s</span>%s</a></li>'."\n",
            Format::htmlchars($ptdt_key), $I['href'],
            $I['active'] ? ' aria-current="page"' : '',
            $I['title'] ? ' title="'.Format::htmlchars($I['title']).'"' : '',
            ptdt_icon($I['icon'], 20), $I['label'],
            $I['badge'] !== null ? sprintf('<b class="badge" title="%s">%d</b>',
                Format::htmlchars(__('Open Tickets')), $I['badge']) : '');
    }
    $ptdt_list = ob_get_clean(); ?>
    <aside class="rail on-dark" id="header">
<?php echo $ptdt_brand; ?>
        <?php echo ptdt_signature(); ?>
        <nav class="rail-nav" aria-label="<?php echo ptdt__('Main menu'); ?>">
            <ul id="nav">
<?php echo $ptdt_list; ?>
            </ul>
<?php
    if ($ptdt_member) {
        $ptdt_org_t = $thisclient->canSeeOrgTickets(); ?>
            <p class="r-title" id="r-views"><?php echo __('Tickets'); ?></p>
            <ul class="r-views" aria-labelledby="r-views">
                <li><a href="<?php echo ROOT_PATH; ?>tickets.php?a=search&amp;status=open"><span><?php
                    echo __('Open'); ?></span><b class="count"><?php echo (int) $thisclient->getNumOpenTickets($ptdt_org_t); ?></b></a></li>
                <li><a href="<?php echo ROOT_PATH; ?>tickets.php?a=search&amp;status=closed"><span><?php
                    echo __('Closed'); ?></span><b class="count"><?php echo (int) $thisclient->getNumClosedTickets($ptdt_org_t); ?></b></a></li>
            </ul>
<?php
    }
    $ptdt_pages = Page::getActivePages()->filter(array('type'=>'other'));
    if ($ptdt_pages->all()) { ?>
            <p class="r-title" id="r-help"><?php echo __('Other Resources'); ?></p>
            <ul class="r-views" aria-labelledby="r-help">
<?php   foreach ($ptdt_pages as $ptdt_p) { ?>
                <li><a href="<?php echo ROOT_PATH; ?>pages/<?php echo $ptdt_p->getNameAsSlug();
                    ?>"><span><?php echo Format::htmlchars($ptdt_p->getLocalName()); ?></span></a></li>
<?php   } ?>
            </ul>
<?php
    } ?>
        </nav>
        <div class="rail-foot">
            <p class="plan-box"><span class="k"><?php echo ptdt_icon('bolt', 16); ?><?php
                echo ptdt__('Support Priority'); ?></span><?php echo ptdt__('First response within 4 h, 24/7'); ?></p>
            <a class="rail-link" href="tel:<?php echo Format::htmlchars(PTDT_CONTACT_PHONE_URI); ?>"><?php
                echo ptdt_icon('phone'); ?><span><?php echo Format::htmlchars(PTDT_CONTACT_PHONE); ?></span></a>
<?php if ($ptdt_signed) { ?>
            <a class="rail-link" href="<?php echo $signout_url; ?>"><?php echo ptdt_icon('logout'); ?><span><?php
                echo __('Sign Out'); ?></span></a>
<?php } elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
            <a class="rail-link" href="<?php echo $signin_url; ?>"><?php echo ptdt_icon('login'); ?><span><?php
                echo __('Sign In'); ?></span></a>
<?php } ?>
        </div>
        <details class="menu-mobile">
            <summary><?php echo ptdt_icon('menu', 20); ?><span><?php echo ptdt__('Menu'); ?></span></summary>
            <ul>
<?php echo $ptdt_list; ?>
<?php if ($ptdt_member) { ?>
                <li><a href="<?php echo ROOT_PATH; ?>profile.php"><?php echo ptdt_icon('user', 20); ?><span><?php echo __('Profile'); ?></span></a></li>
<?php } ?>
<?php if ($ptdt_signed) { ?>
                <li><a href="<?php echo $signout_url; ?>"><?php echo ptdt_icon('logout', 20); ?><span><?php echo __('Sign Out'); ?></span></a></li>
<?php } elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
                <li><a href="<?php echo $signin_url; ?>"><?php echo ptdt_icon('login', 20); ?><span><?php echo __('Sign In'); ?></span></a></li>
<?php } ?>
            </ul>
        </details>
    </aside>
<?php
} else { ?>
    <header class="rail rail-min on-dark" id="header">
<?php echo $ptdt_brand; ?>
    </header>
<?php
} ?>
<div class="main-col">
<?php if ($nav) { ?>
    <div class="topbar">
        <nav class="crumbs" aria-label="<?php echo ptdt__('Breadcrumb'); ?>"><ol><?php
    if (isset($ticket) && $ticket instanceof Ticket && $ptdt_page == 'p-tickets' && $ptdt_member) { ?>
            <li><a href="<?php echo ROOT_PATH; ?>tickets.php"><?php echo __('Tickets'); ?></a></li>
            <li><span aria-current="page">#<?php echo $ticket->getNumber(); ?></span></li><?php
    } elseif ($ptdt_crumb && $ptdt_page != 'p-index') { ?>
            <li><a href="<?php echo ROOT_PATH; ?>index.php"><?php echo ptdt__('Home'); ?></a></li>
            <li><span aria-current="page"><?php echo Format::htmlchars($ptdt_crumb['label']); ?></span></li><?php
    } else { ?>
            <li><span aria-current="page"><?php echo $ptdt_page == 'p-index' ? ptdt__('Home') : Format::htmlchars($title); ?></span></li><?php
    } ?></ol></nav>
<?php if ($ptdt_kb) { ?>
        <form class="tsearch" role="search" method="get" action="<?php echo ROOT_PATH; ?>kb/faq.php">
            <input type="hidden" name="a" value="search">
            <label class="visually-hidden" for="gsearch-q"><?php echo __('Search our knowledge base'); ?></label>
            <?php echo ptdt_icon('search'); ?>
            <input type="search" name="q" id="gsearch-q"<?php
                // No empty value attribute: js/osticket.js focuses the first input[value=""]
                if (!empty($_REQUEST['q']) && is_string($_REQUEST['q']))
                    echo ' value="'.Format::htmlchars($_REQUEST['q']).'"'; ?>
                placeholder="<?php echo __('Search our knowledge base'); ?>">
            <kbd aria-hidden="true">/</kbd>
        </form>
<?php } ?>
<?php if ($ptdt_can_open && $ptdt_page != 'p-open') { ?>
        <a class="btn btn-primary t-new" href="<?php echo ROOT_PATH; ?>open.php"><?php
            echo ptdt_icon('plus'); ?><span><?php echo __('Open a New Ticket'); ?></span></a>
<?php } ?>
<?php
    if ($ptdt_member) {
        $ptdt_org = method_exists($thisclient, 'getOrganization') ? $thisclient->getOrganization() : null;
        $ptdt_n = $thisclient->getName(); ?>
        <a class="who" href="<?php echo ROOT_PATH; ?>profile.php" title="<?php echo __('Profile'); ?>">
            <span class="avatar" aria-hidden="true"><?php echo ptdt_initials($ptdt_n); ?></span>
            <span class="who-t"><?php echo Format::htmlchars((string) ($ptdt_n->getFirst() ?: $ptdt_n));
                if ($ptdt_org) { ?><small><?php echo Format::htmlchars($ptdt_org->getName()); ?></small><?php }
            ?><span class="visually-hidden">, <?php echo __('Profile'); ?></span></span>
        </a>
<?php
    } elseif ($ptdt_signed) { ?>
        <span class="who"><span class="avatar" aria-hidden="true"><?php echo ptdt_initials($thisclient->getName()); ?></span>
            <span class="who-t"><?php echo Format::htmlchars((string) $thisclient->getName()); ?><small><?php echo __('Guest User'); ?></small></span></span>
<?php
    } ?>
        <?php echo $ptdt_langs; ?>
    </div>
<?php } ?>
    <main id="content" tabindex="-1">
<?php
} ?>
<?php if($errors['err']) { ?>
        <div id="msg_error" class="alert alert-error" role="alert"><?php echo $errors['err']; ?></div>
<?php }elseif($msg) { ?>
        <div id="msg_notice" class="alert alert-notice" role="status"><?php echo $msg; ?></div>
<?php }elseif($warn) { ?>
        <div id="msg_warning" class="alert alert-warning" role="alert"><?php echo $warn; ?></div>
<?php } ?>
