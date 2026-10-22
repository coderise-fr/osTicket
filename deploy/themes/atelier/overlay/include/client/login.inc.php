<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');
/* Atelier theme (deploy/themes/atelier): replaces include/client/login.inc.php.
 * Two cards side by side: the account sign-in form (unchanged fields:
 * luser, lpasswd, CSRF token, external backends, registration and agent
 * links) and the ticket access form of accesslink.inc.php (lemail, lticket),
 * which posts to login.php exactly as the "Check Ticket Status" page does. */
require_once(INCLUDE_DIR . 'client/atelier/atelier.inc.php');

$email=Format::input($_POST['luser']?:$_GET['e']);
$passwd=Format::input($_POST['lpasswd']?:$_GET['t']);

$content = Page::lookupByType('banner-client');

if ($content) {
    list($title, $body) = $ost->replaceTemplateVariables(
        array($content->getLocalName(), $content->getLocalBody()));
} else {
    $title = __('Sign In');
    $body = __('To better serve you, we encourage our clients to register for an account and verify the email address we have on record.');
}

if ($cfg->isClientEmailVerificationRequired())
    $atelier_access_button = __("Email Access Link");
else
    $atelier_access_button = __("View Ticket");
?>
<div class="page-head center">
    <h1 class="display"><?php echo Format::display($title); ?></h1>
    <div class="sub"><?php echo Format::display($body); ?></div>
</div>
<div class="duo">
<form action="login.php" method="post" id="clientLogin" class="card login" aria-labelledby="login-title">
    <?php csrf_token(); ?>
    <h2 class="card-title" id="login-title"><i class="icon-user" aria-hidden="true"></i> <?php echo __('Sign In'); ?></h2>
<?php if ($errors['login']) { ?>
    <p class="err" role="alert"><?php echo Format::htmlchars($errors['login']); ?></p>
<?php } ?>
    <div class="field">
        <label for="username"><?php echo __('Email or Username'); ?></label>
        <input id="username" type="text" name="luser" size="30" value="<?php echo $email; ?>" class="nowarn" autocomplete="username">
    </div>
    <div class="field">
        <label for="passwd"><?php echo __('Password'); ?></label>
        <input id="passwd" type="password" name="lpasswd" size="30" maxlength="128" value="<?php echo $passwd; ?>" class="nowarn" autocomplete="current-password">
    </div>
    <p class="actions">
        <input class="btn btn-primary" type="submit" value="<?php echo __('Sign In'); ?>">
        <a class="plain-link<?php if ($suggest_pwreset) echo ' suggest'; ?>" href="pwreset.php"><?php echo __('Forgot My Password'); ?></a>
    </p>
<?php

$ext_bks = array();
foreach (UserAuthenticationBackend::allRegistered() as $bk)
    if ($bk instanceof ExternalAuthentication)
        $ext_bks[] = $bk;

if (count($ext_bks)) {
    foreach ($ext_bks as $bk) { ?>
    <div class="external-auth"><?php $bk->renderExternalLink(); ?></div><?php
    }
} ?>
    <div class="small sep">
<?php if ($cfg && $cfg->isClientRegistrationEnabled()) { ?>
        <p><?php echo __('Not yet registered?'); ?> <a href="account.php?do=create"><?php echo __('Create an account'); ?></a></p>
<?php } ?>
        <p><b><?php echo __("I'm an agent"); ?></b> —
        <a href="<?php echo ROOT_PATH; ?>scp/"><?php echo __('sign in here'); ?></a></p>
    </div>
</form>

<form action="login.php" method="post" id="clientAccess" class="card login alt" aria-labelledby="access-title">
    <?php csrf_token(); ?>
    <h2 class="card-title" id="access-title"><i class="icon-ticket" aria-hidden="true"></i> <?php echo __('Check Ticket Status'); ?></h2>
    <p class="small"><?php
echo __('Please provide your email address and a ticket number.');
if ($cfg->isClientEmailVerificationRequired())
    echo ' '.__('An access link will be emailed to you.');
else
    echo ' '.__('This will sign you in to view your ticket.');
?></p>
    <div class="field">
        <label for="email"><?php echo __('Email Address'); ?></label>
        <input id="email" placeholder="<?php echo __('e.g. john.doe@osticket.com'); ?>" type="text"
            name="lemail" size="30" value="" class="nowarn" autocomplete="email">
    </div>
    <div class="field">
        <label for="ticketno"><?php echo __('Ticket Number'); ?></label>
        <input id="ticketno" type="text" name="lticket" placeholder="<?php echo __('e.g. 051243'); ?>"
            size="30" value="" class="nowarn">
    </div>
    <p class="actions">
        <input class="btn btn-ghost" type="submit" value="<?php echo $atelier_access_button; ?>">
    </p>
<?php
if ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired()) { ?>
    <p class="small sep"><?php
    echo sprintf(__('If this is your first time contacting us or you\'ve lost the ticket number, please %s open a new ticket %s'),
        '<a href="open.php">', '</a>'); ?></p>
<?php } ?>
</form>
</div>
