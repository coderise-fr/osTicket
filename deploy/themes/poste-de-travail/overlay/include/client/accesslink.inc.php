<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');
/* Theme "Poste de travail" (Coderise) - overrides include/client/accesslink.inc.php.
 * Same form (#clientLogin, lemail, lticket, CSRF) and links as the core template. */

$email=Format::input($_POST['lemail']?$_POST['lemail']:$_GET['e']);
$ticketid=Format::input($_POST['lticket']?$_POST['lticket']:$_GET['t']);

if ($cfg->isClientEmailVerificationRequired())
    $button = __("Email Access Link");
else
    $button = __("View Ticket");
?>
<div class="ph">
    <h1><?php echo __('Check Ticket Status'); ?></h1>
    <p class="ph-sub"><?php
echo __('Please provide your email address and a ticket number.');
if ($cfg->isClientEmailVerificationRequired())
    echo ' '.__('An access link will be emailed to you.');
else
    echo ' '.__('This will sign you in to view your ticket.');
?></p>
</div>
<div class="cols-two">
<form action="login.php" method="post" id="clientLogin" class="panel form compact" aria-labelledby="access-t">
    <?php csrf_token(); ?>
    <h2 class="f-sec" id="access-t"><?php echo __('Ticket Number'); ?></h2>
<?php if ($errors['login']) { ?>
    <p class="err-box" role="alert"><?php echo ptdt_icon('alert'); ?><strong><?php echo Format::htmlchars($errors['login']); ?></strong></p>
<?php } ?>
    <div class="stackf">
        <label for="email"><?php echo __('Email Address'); ?></label>
        <input id="email" placeholder="<?php echo __('e.g. john.doe@osticket.com'); ?>" type="text"
            name="lemail" size="30" value="<?php echo $email; ?>" class="nowarn" autocomplete="email">
    </div>
    <div class="stackf">
        <label for="ticketno"><?php echo __('Ticket Number'); ?></label>
        <input id="ticketno" type="text" name="lticket" placeholder="<?php echo __('e.g. 051243'); ?>"
            size="30" value="<?php echo $ticketid; ?>" class="nowarn short">
    </div>
    <p class="f-actions">
        <input class="btn btn-primary" type="submit" value="<?php echo $button; ?>">
    </p>
</form>
<div class="panel instructions">
    <div class="p-body">
<?php if ($cfg && $cfg->getClientRegistrationMode() !== 'disabled') { ?>
        <p><?php echo __('Have an account with us?'); ?>
        <a href="login.php"><?php echo __('Sign In'); ?></a> <?php
    if ($cfg->isClientRegistrationEnabled()) { ?>
<?php echo sprintf(__('or %s register for an account %s to access all your tickets.'),
    '<a href="account.php?do=create">','</a>');
    } ?></p>
<?php
}
if ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired()) { ?>
        <p><?php echo sprintf(
    __("If this is your first time contacting us or you've lost the ticket number, please %s open a new ticket %s"),
        '<a href="open.php">','</a>'); ?></p>
<?php } ?>
    </div>
</div>
</div>
