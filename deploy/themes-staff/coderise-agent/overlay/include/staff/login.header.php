<?php
/*********************************************************************
    include/staff/login.header.php  (coderise-agent staff theme)

    Replaces core's login head (also used by pwreset.php, pwreset.sent.php
    and pwreset.login.php): same CSP (no 'unsafe-eval', so no script is
    added here), same <head>, plus the theme stylesheet after login.css and
    the cdr-login class on <body>.
    Shipped by deploy/themes-staff/coderise-agent (osTicket core untouched).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
defined('OSTSCPINC') or die('Invalid path');
require_once INCLUDE_DIR . 'staff/coderise/coderise.inc.php';

header("Content-Security-Policy: frame-ancestors ".$cfg->getAllowIframes()."; script-src 'self' 'unsafe-inline'; object-src 'none'");

$cdr_html = '';
if (($cdr_lang = Internationalization::getCurrentLanguage())) {
    if (($cdr_info = Internationalization::getLanguageInfo($cdr_lang))
            && (@$cdr_info['direction'] == 'rtl'))
        $cdr_html .= ' dir="rtl" class="rtl"';
    $cdr_html .= ' lang="' . Format::htmlchars(Internationalization::rfc1766($cdr_lang)) . '"';
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"<?php echo $cdr_html; ?>>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="refresh" content="7200" />
    <title>osTicket :: <?php echo __('Agent Login'); ?></title>
    <link rel="stylesheet" href="css/login.css" type="text/css" />
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo Format::htmlchars(cdr_asset('css/coderise.css')); ?>" type="text/css" />
    <meta name="robots" content="noindex" />
    <meta http-equiv="cache-control" content="no-cache" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000F26">
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-16x16.png" sizes="16x16" />
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $("input:not(.dp):visible:enabled:first").focus();
         });
    </script>
</head>
<body id="loginBody" class="cdr-login">
