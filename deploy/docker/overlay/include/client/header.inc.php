<?php
/* Theme "Parcours" (Coderise) - overrides include/client/header.inc.php.
 * Same PHP prelude, HTTP headers (CSP), stylesheets and scripts as the core
 * template, except assets/default/css/theme.css, print.css and flags.css,
 * replaced by the theme stylesheet. Markup: dark header (logo, menu, "open a
 * ticket" button, account), then <main>, closed by footer.inc.php. */
require_once INCLUDE_DIR . 'client/parcours/parcours.inc.php';
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
$pcrs_member = ($thisclient && is_object($thisclient) && $thisclient->isValid()
    && !$thisclient->isGuest());
// Page identifier for the stylesheet: p-index, p-open, p-tickets, p-kb-faq...
$pcrs_page = (string) $_SERVER['SCRIPT_NAME'];
if (strpos($pcrs_page, ROOT_PATH) === 0)
    $pcrs_page = substr($pcrs_page, strlen(ROOT_PATH));
$pcrs_page = 'p-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(preg_replace('/\.php$/', '', $pcrs_page)));
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
    <link rel="stylesheet" href="<?php echo pcrs_asset('css/parcours.css'); ?>" media="all">
    <link rel="icon" type="image/svg+xml" href="<?php echo pcrs_asset('img/favicon.svg'); ?>">
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
    <script src="<?php echo pcrs_asset('js/parcours.js'); ?>"></script>
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
<body class="pcrs <?php echo $pcrs_page; echo $pcrs_member ? ' is-member' : ''; ?>">
<a class="skip" href="#content"><?php echo pcrs__('Skip to content'); ?></a>
<?php
if($ost->getError())
    echo sprintf('<div class="error_bar" role="alert">%s</div>', $ost->getError());
elseif($ost->getWarning())
    echo sprintf('<div class="warning_bar" role="alert">%s</div>', $ost->getWarning());
elseif($ost->getNotice())
    echo sprintf('<div class="notice_bar" role="status">%s</div>', $ost->getNotice());

// Menu entries: core links (UserNav), in the order of the mockup
$pcrs_links = array();
$pcrs_new = null;
if ($nav && ($navs = $nav->getNavLinks()) && is_array($navs)) {
    foreach (array('home', 'kb', 'tickets', 'status') as $k) {
        if (!isset($navs[$k]))
            continue;
        $label = $navs[$k]['desc'];
        if ($k == 'home')
            $label = pcrs__('Home');
        elseif ($k == 'tickets' && $pcrs_member)
            $label = __('Tickets');
        $pcrs_links[$k] = array('label' => $label, 'href' => ROOT_PATH . $navs[$k]['href'],
            'active' => !empty($navs[$k]['active']),
            'title' => isset($navs[$k]['title']) ? $navs[$k]['title'] : '');
    }
    if (isset($navs['new']))
        $pcrs_new = array('label' => $navs['new']['desc'], 'href' => ROOT_PATH . $navs['new']['href'],
            'active' => !empty($navs['new']['active']));
}
$pcrs_langs = array();
if (($all_langs = Internationalization::getConfiguredSystemLanguages())
    && (count($all_langs) > 1)
) {
    $qs = array();
    parse_str($_SERVER['QUERY_STRING'], $qs);
    foreach ($all_langs as $code=>$info) {
        list($l_lang, $l_locale) = explode('_', $code);
        $qs['lang'] = $code;
        $pcrs_langs[] = array('code' => $code, 'short' => strtoupper($l_lang),
            'href' => '?' . http_build_query($qs),
            'name' => Internationalization::getLanguageDescription($code),
            'current' => $code == Internationalization::getCurrentLanguage());
    }
}
// Renders the menu list (desktop and mobile share the markup)
$pcrs_menu = function($id) use ($pcrs_links, $pcrs_member, $thisclient) {
    $out = '<ul' . ($id ? ' id="' . $id . '"' : '') . '>';
    foreach ($pcrs_links as $k => $l) {
        $badge = '';
        if ($k == 'tickets' && $pcrs_member)
            $badge = sprintf(' <span class="count">%d</span>',
                $thisclient->getNumTickets($thisclient->canSeeOrgTickets()));
        $out .= sprintf('<li><a class="%s" href="%s"%s%s>%s%s</a></li>',
            Format::htmlchars($k), $l['href'],
            $l['active'] ? ' aria-current="page"' : '',
            $l['title'] ? ' title="' . Format::htmlchars($l['title']) . '"' : '',
            Format::htmlchars($l['label']), $badge);
    }
    return $out . '</ul>';
};
?>
<header class="site-head on-dark" id="header">
  <div class="wrap head-in">
    <a class="brand" id="logo" href="<?php echo ROOT_PATH; ?>index.php"><?php
    // A logo uploaded in the admin panel wins; otherwise the Coderise logo
    if ($ost->getConfig()->getClientLogoId()) { ?><img class="logo logo-custom" src="<?php echo ROOT_PATH; ?>logo.php" alt="<?php
        echo Format::htmlchars($ost->getConfig()->getTitle()); ?>"><?php
    } else { ?><img class="logo" src="<?php echo pcrs_asset('img/logo-horizontal-sombre.png'); ?>" width="2041" height="345" alt="<?php
        echo Format::htmlchars(sprintf(pcrs__('%s: support home'), 'Coderise')); ?>"><?php
    } ?></a>
<?php if ($pcrs_links) { ?>
    <nav class="nav-main" aria-label="<?php echo Format::htmlchars(pcrs__('Main menu')); ?>">
      <?php echo $pcrs_menu('nav'); ?>
    </nav>
<?php } ?>
    <div class="head-end">
<?php if ($pcrs_new) { ?>
      <a class="btn btn-primary head-cta" href="<?php echo $pcrs_new['href']; ?>"<?php
        if ($pcrs_new['active']) echo ' aria-current="page"'; ?>><?php echo pcrs_icon('plus');
        ?><span><?php echo Format::htmlchars($pcrs_new['label']); ?></span></a>
<?php }
if ($pcrs_member) { ?>
      <details class="account">
        <summary><span class="avatar" aria-hidden="true"><?php echo pcrs_initials($thisclient->getName());
            ?></span><span class="who"><?php echo pcrs_first_name($thisclient->getName()); ?></span>
            <span class="sr-only"><?php echo Format::htmlchars(pcrs__('Account menu')); ?></span></summary>
        <ul>
          <li class="acc-name"><?php echo Format::htmlchars($thisclient->getName()); ?></li>
          <li><a href="<?php echo ROOT_PATH; ?>profile.php"><?php echo pcrs_icon('user', 16); echo __('Profile'); ?></a></li>
          <li><a href="<?php echo ROOT_PATH; ?>tickets.php"><?php echo pcrs_icon('inbox', 16); echo __('Tickets'); ?></a></li>
          <li><a href="<?php echo $signout_url; ?>"><?php echo pcrs_icon('logout', 16); echo __('Sign Out'); ?></a></li>
        </ul>
      </details>
<?php
} elseif ($nav) { ?>
      <p class="guest"><?php
    if ($cfg->getClientRegistrationMode() == 'public') { ?><span class="sr-only"><?php echo __('Guest User'); ?></span><?php
    }
    if ($thisclient && $thisclient->isValid() && $thisclient->isGuest()) { ?>
        <a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a><?php
    } elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
        <a href="<?php echo $signin_url; ?>"><?php echo __('Sign In'); ?></a><?php
    } ?></p>
<?php
}
if ($pcrs_langs) { ?>
      <p class="langs"><?php
    foreach ($pcrs_langs as $L) { ?><a href="<?php echo Format::htmlchars($L['href']); ?>"
          hreflang="<?php echo Format::htmlchars(str_replace('_', '-', $L['code'])); ?>"
          lang="<?php echo Format::htmlchars(str_replace('_', '-', $L['code'])); ?>"
          title="<?php echo Format::htmlchars($L['name']); ?>"<?php
          if ($L['current']) echo ' aria-current="true"'; ?>><?php echo Format::htmlchars($L['short']); ?></a><?php
    } ?></p>
<?php
} ?>
    </div>
<?php if ($nav) { ?>
    <details class="menu-mobile">
      <summary><?php echo pcrs_icon('menu', 20); ?><span><?php echo pcrs__('Menu'); ?></span></summary>
      <div class="mm-panel">
        <nav aria-label="<?php echo Format::htmlchars(pcrs__('Main menu')); ?>"><?php echo $pcrs_menu(''); ?></nav>
        <ul class="mm-account">
<?php if ($pcrs_new) { ?>
          <li><a href="<?php echo $pcrs_new['href']; ?>"><?php echo Format::htmlchars($pcrs_new['label']); ?></a></li>
<?php }
    if ($pcrs_member) { ?>
          <li><a href="<?php echo ROOT_PATH; ?>profile.php"><?php echo __('Profile'); ?></a></li>
          <li><a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a></li>
<?php } elseif ($thisclient && $thisclient->isValid() && $thisclient->isGuest()) { ?>
          <li><a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a></li>
<?php } elseif ($cfg->getClientRegistrationMode() != 'disabled') { ?>
          <li><a href="<?php echo $signin_url; ?>"><?php echo __('Sign In'); ?></a></li>
<?php }
    foreach ($pcrs_langs as $L) { ?>
          <li><a href="<?php echo Format::htmlchars($L['href']); ?>" lang="<?php
            echo Format::htmlchars(str_replace('_', '-', $L['code'])); ?>"<?php
            if ($L['current']) echo ' aria-current="true"'; ?>><?php echo Format::htmlchars($L['name']); ?></a></li>
<?php } ?>
        </ul>
      </div>
    </details>
<?php } ?>
  </div>
</header>
<main id="content" tabindex="-1">
<div class="wrap page">
<?php if($errors['err']) { ?>
    <div id="msg_error" class="alert alert-error" role="alert"><?php echo pcrs_icon('alert', 20); ?><div><?php echo $errors['err']; ?></div></div>
<?php }elseif($msg) { ?>
    <div id="msg_notice" class="alert alert-notice" role="status"><?php echo pcrs_icon('check', 20); ?><div><?php echo $msg; ?></div></div>
<?php }elseif($warn) { ?>
    <div id="msg_warning" class="alert alert-warning" role="alert"><?php echo pcrs_icon('alert', 20); ?><div><?php echo $warn; ?></div></div>
<?php } ?>
