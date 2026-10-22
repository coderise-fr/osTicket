<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');
/* Theme "Poste de travail" (Coderise) - overrides include/client/login.inc.php.
 * Same form (#clientLogin, luser, lpasswd, CSRF), external backends,
 * registration and agent links. A second panel posts lemail / lticket to
 * login.php, which already handles ticket access (core accesslink form). */

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

?>
<div class="ph">
    <h1><?php echo Format::display($title); ?></h1>
    <div class="ph-sub"><?php echo Format::display($body); ?></div>
</div>
<div class="cols-two">
<form action="login.php" method="post" id="clientLogin" class="panel form compact" aria-labelledby="login-t">
    <?php csrf_token(); ?>
    <h2 class="f-sec" id="login-t"><?php echo __('Sign In'); ?></h2>
<?php if ($errors['login']) { ?>
    <p class="err-box" role="alert"><?php echo ptdt_icon('alert'); ?><strong><?php echo Format::htmlchars($errors['login']); ?></strong></p>
<?php } ?>
    <div class="stackf">
        <label for="username"><?php echo __('Email or Username'); ?></label>
        <input id="username" type="text" name="luser" size="30" value="<?php echo $email; ?>" class="nowarn" autocomplete="username">
    </div>
    <div class="stackf">
        <label for="passwd"><?php echo __('Password'); ?></label>
        <input id="passwd" type="password" name="lpasswd" size="30" maxlength="128" value="<?php echo $passwd; ?>" class="nowarn" autocomplete="current-password">
    </div>
    <p class="f-actions">
        <input class="btn btn-primary" type="submit" value="<?php echo __('Sign In'); ?>">
<?php if ($suggest_pwreset) { ?>
        <a href="pwreset.php"><?php echo __('Forgot My Password'); ?></a>
<?php } ?>
    </p>
<?php

$ext_bks = array();
foreach (UserAuthenticationBackend::allRegistered() as $bk)
    if ($bk instanceof ExternalAuthentication)
        $ext_bks[] = $bk;

if (count($ext_bks)) { ?>
    <div class="ext-auth"><?php
    foreach ($ext_bks as $bk) { ?>
        <div class="external-auth"><?php $bk->renderExternalLink(); ?></div><?php
    } ?>
    </div>
<?php
} ?>
    <div class="foot-note">
<?php
if ($cfg && $cfg->isClientRegistrationEnabled()) { ?>
        <p><?php echo __('Not yet registered?'); ?> <a href="account.php?do=create"><?php echo __('Create an account'); ?></a></p>
<?php } ?>
        <p><?php echo __("I'm an agent"); ?> &mdash;
        <a href="<?php echo ROOT_PATH; ?>scp/"><?php echo __('sign in here'); ?></a></p>
    </div>
</form>
<?php
if ($cfg->getClientRegistrationMode() != 'disabled') {
    $button = $cfg->isClientEmailVerificationRequired()
        ? __("Email Access Link") : __("View Ticket"); ?>
<form action="login.php" method="post" class="panel form compact" aria-labelledby="access-t">
    <?php csrf_token(); ?>
    <h2 class="f-sec" id="access-t"><?php echo __('Check Ticket Status'); ?></h2>
    <p class="help f-intro"><?php echo __('Please provide your email address and a ticket number.');
    if ($cfg->isClientEmailVerificationRequired())
        echo ' '.__('An access link will be emailed to you.');
    else
        echo ' '.__('This will sign you in to view your ticket.'); ?></p>
    <div class="stackf">
        <label for="lemail"><?php echo __('Email Address'); ?></label>
        <input id="lemail" type="email" name="lemail" size="30" class="nowarn" autocomplete="email"
            placeholder="<?php echo __('e.g. john.doe@osticket.com'); ?>">
    </div>
    <div class="stackf">
        <label for="lticket"><?php echo __('Ticket Number'); ?></label>
        <input id="lticket" type="text" name="lticket" size="30" class="nowarn short"
            placeholder="<?php echo __('e.g. 051243'); ?>">
    </div>
    <p class="f-actions"><input class="btn" type="submit" value="<?php echo $button; ?>"></p>
    <p class="foot-note"><?php
        echo sprintf(__('If this is your first time contacting us or you\'ve lost the ticket number, please %s open a new ticket %s'),
            '<a href="open.php">', '</a>'); ?></p>
</form>
<?php
} ?>
</div>
