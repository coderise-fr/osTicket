<?php
/*********************************************************************
    include/staff/header.inc.php  (coderise-agent staff theme)

    Replaces core's header: same HTTP headers, same X-PJAX branch, same
    <head> plus the theme stylesheet and script; the #header/#nav/sub_nav
    markup is replaced by the Coderise rail and top bar, emitted inside
    #pjax-container so PJAX navigation refreshes them.
    Shipped by deploy/themes-staff/coderise-agent (osTicket core untouched).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access Denied');
require_once INCLUDE_DIR . 'staff/coderise/coderise.inc.php';

header("Content-Type: text/html; charset=UTF-8");
header("Content-Security-Policy: frame-ancestors ".$cfg->getAllowIframes()."; script-src 'self' 'unsafe-inline' 'unsafe-eval'; object-src 'none'");

$title = ($ost && ($title=$ost->getPageTitle()))
    ? $title : ('osTicket :: '.__('Staff Control Panel'));

if (!isset($_SERVER['HTTP_X_PJAX'])) { ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html<?php
if (($lang = Internationalization::getCurrentLanguage())
        && ($info = Internationalization::getLanguageInfo($lang))
        && (@$info['direction'] == 'rtl'))
    echo ' dir="rtl" class="rtl"';
if ($lang) {
    echo ' lang="' . Internationalization::rfc1766($lang) . '"';
}

// Dropped IE Support Warning
if (osTicket::is_ie())
    $ost->setWarning(__('osTicket no longer supports Internet Explorer.'));
?>>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="cache-control" content="no-cache" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="x-pjax-version" content="<?php echo GIT_VERSION; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000F26">
    <title><?php echo Format::htmlchars($title); ?></title>
    <!--[if IE]>
    <style type="text/css">
        .tip_shadow { display:block !important; }
    </style>
    <![endif]-->
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/thread.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/scp.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/typeahead.css" media="screen">
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.13.2.custom.min.css"
         rel="stylesheet" media="screen" />
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/jquery-ui-timepicker-addon.css" media="all">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <!--[if IE 7]>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome-ie7.min.css">
    <![endif]-->
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/dropdown.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/loadingbar.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/flags.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/translatable.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo cdr_h(cdr_asset('css/coderise.css')); ?>"/>
    <script type="text/javascript" src="<?php echo cdr_h(cdr_asset('js/coderise.js')); ?>"></script>
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="<?php echo cdr_h(cdr_asset('img/favicon.svg')); ?>" />

    <?php
    if($ost && ($headers=$ost->getExtraHeaders())) {
        echo "\n\t".implode("\n\t", $headers)."\n";
    }
    ?>
</head>
<body>
<a class="cdr-skip" href="#content"><?php echo cdr_h(cdr__('Skip to content')); ?></a>
<div id="container">
    <?php
    if($ost->getError())
        echo sprintf('<div id="error_bar">%s</div>', $ost->getError());
    elseif($ost->getWarning())
        echo sprintf('<div id="warning_bar">%s</div>', $ost->getWarning());
    elseif($ost->getNotice())
        echo sprintf('<div id="notice_bar">%s</div>', $ost->getNotice());
    ?>
    <div id="pjax-container" class="<?php if ($_POST) echo 'no-pjax'; ?>">
<?php } else {
    header('X-PJAX-Version: ' . GIT_VERSION);
    if ($pjax = $ost->getExtraPjax()) { ?>
    <script type="text/javascript">
    <?php foreach (array_filter($pjax) as $s) echo $s.";"; ?>
    </script>
    <?php }
    foreach ($ost->getExtraHeaders() as $h) {
        if (strpos($h, '<script ') !== false)
            echo $h;
    } ?>
    <title><?php echo ($ost && ($title=$ost->getPageTitle()))?$title:'osTicket :: '.__('Staff Control Panel'); ?></title><?php
} # endif X_PJAX

// Coderise shell: page kind, from what the controller set before this include
$cdr_script = basename($_SERVER['SCRIPT_NAME']);
$cdr_inc = isset($inc) ? (string) $inc : '';
// scp/index.php only does require('tickets.php'): same page as tickets.php
if ($cdr_script === 'index.php' && $cdr_inc !== '')
    $cdr_script = 'tickets.php';
$cdr_admin = defined('ADMINPAGE');
// Ticket view: tickets.php with an id and no "a" (edit/print are other pages)
$cdr_ticket_view = ($cdr_inc !== '')
    ? ($cdr_inc === 'ticket-view.inc.php')
    : ($cdr_script === 'tickets.php' && isset($_REQUEST['id']) && !isset($_REQUEST['a']));
// Queue list (and search results): the page renders its own #basic_search
$cdr_queue_list = ($cdr_script === 'tickets.php' && $cdr_inc === 'templates/queue-tickets.tmpl.php');
$cdr_current = null;
if ($cdr_queue_list && isset($queue) && is_object($queue))
    $cdr_current = (string) $queue->getName();
elseif ($cdr_ticket_view && isset($ticket) && is_object($ticket))
    $cdr_current = sprintf(cdr__('Ticket #%s'), $ticket->getNumber());

// Ticket view: the "First reply" panel. ticket-view.inc.php sends
// object.view with array('type' => 'viewed') right after this header opened
// #content, so the panel lands at the top of #content; coderise.js moves it
// into the properties column. The same signal is sent for tasks
// (task-view.tmpl.php) and on the client portal: only a staff Ticket view
// prints. Registered once per request (header.inc.php is included once per
// request, PJAX included; the flag guards a second include).
if ($cdr_script === 'tickets.php' && empty($GLOBALS['cdr_sla_connected'])) {
    $GLOBALS['cdr_sla_connected'] = true;
    Signal::connect('object.view', function ($object, $type) {
        if ($object instanceof Ticket && is_array($type)
                && ($type['type'] ?? '') === 'viewed')
            echo cdr_ticket_sla_panel($object);
    });
}
// Labels the ticket view's CSS and JS need (no translation in CSS/JS)
$cdr_content_attrs = '';
if ($cdr_ticket_view)
    $cdr_content_attrs = ' data-cdr-props="' . cdr_h(cdr__('Ticket properties')) . '"'
        . ' style="--cdr-note-label: ' . cdr_h('"' . addcslashes(cdr__('Internal note'), "\"\\\n\r") . '"') . '"';
// Admin forms: labels of the changed-field marks and the save bar counter
// (coderise.js replaces %d with the number of changed settings)
elseif ($cdr_admin)
    $cdr_content_attrs = ' data-cdr-changed-flag="' . cdr_h(cdr__('modified')) . '"'
        . ' data-cdr-changed-one="' . cdr_h(cdr__('%d setting changed')) . '"'
        . ' data-cdr-changed-many="' . cdr_h(cdr__('%d settings changed')) . '"';
?>
    <?php echo cdr_rail(isset($nav) ? $nav : null, $thisstaff); ?>
    <div class="cdr-main">
    <header class="topbar">
        <?php echo cdr_crumbs(isset($nav) ? $nav : null, $cdr_current, $cdr_admin); ?>
        <?php
        if (!$cdr_queue_list) { ?>
        <form class="tsearch" action="<?php echo ROOT_PATH; ?>scp/tickets.php" method="get" role="search">
            <input type="hidden" name="a" value="search">
            <input type="hidden" name="search-type" value="">
            <?php echo cdr_icon('search', 18); ?>
            <input type="search" class="basic-search" data-url="ajax.php/tickets/lookup" name="query"
                placeholder="<?php echo cdr_h(cdr__('Search tickets')); ?>"
                aria-label="<?php echo cdr_h(cdr__('Search tickets')); ?>"
                autocomplete="off" autocorrect="off" autocapitalize="off">
        </form>
        <?php }
        if (!$cdr_admin && is_object($thisstaff) && class_exists('Ticket')
                && $thisstaff->hasPerm(Ticket::PERM_CREATE, false)) { ?>
        <a class="btn btn-primary" href="<?php echo ROOT_PATH; ?>scp/tickets.php?a=open"><?php
            echo cdr_icon('plus', 16) . cdr_h(cdr__('New ticket')); ?></a>
        <?php } ?>
    </header>

        <div id="content" class="<?php echo cdr_h(cdr_content_class($cdr_script, $cdr_ticket_view, $cdr_admin)); ?>"<?php echo $cdr_content_attrs; ?>>
        <?php if(isset($errors['err'])) { ?>
            <div id="msg_error"><?php echo $errors['err']; ?></div>
        <?php }elseif($msg) { ?>
            <div id="msg_notice"><?php echo $msg; ?></div>
        <?php }elseif($warn) { ?>
            <div id="msg_warning"><?php echo $warn; ?></div>
        <?php }
        foreach (Messages::getMessages() as $M) { ?>
            <div class="<?php echo strtolower($M->getLevel()); ?>-banner"><?php
                echo (string) $M; ?></div>
<?php   } ?>
