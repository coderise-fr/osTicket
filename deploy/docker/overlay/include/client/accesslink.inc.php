<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');
/* Theme "Parcours" (Coderise) - overrides include/client/accesslink.inc.php.
 * Same form (#clientLogin, lemail, lticket, CSRF, target login.php), same
 * texts and links as the core template, laid out as a band and two cards. */

$email=Format::input($_POST['lemail']?$_POST['lemail']:$_GET['e']);
$ticketid=Format::input($_POST['lticket']?$_POST['lticket']:$_GET['t']);

if ($cfg->isClientEmailVerificationRequired())
    $button = __("Email Access Link");
else
    $button = __("View Ticket");
?>
<section class="top">
<?php echo pcrs_crumbs(array(array(pcrs__('Home'), ROOT_PATH . 'index.php'), array(__('Check Ticket Status'), null))); ?>
  <h1><?php echo __('Check Ticket Status'); ?></h1>
  <p class="sub"><?php
echo __('Please provide your email address and a ticket number.');
if ($cfg->isClientEmailVerificationRequired())
    echo ' '.__('An access link will be emailed to you.');
else
    echo ' '.__('This will sign you in to view your ticket.');
?></p>
</section>

<div class="auth-grid">
  <section class="card" aria-labelledby="access-title">
    <h2 id="access-title"><?php echo pcrs__('Without an account'); ?></h2>
    <form action="login.php" method="post" id="clientLogin">
      <?php csrf_token(); ?>
<?php if ($errors['login']) { ?>
      <div class="alert alert-error" role="alert"><?php echo pcrs_icon('alert', 20); ?><div><?php echo Format::htmlchars($errors['login']); ?></div></div>
<?php } ?>
      <div class="field">
        <label for="email"><?php echo __('Email Address'); ?></label>
        <input id="email" placeholder="<?php echo __('e.g. john.doe@osticket.com'); ?>" type="text"
            name="lemail" size="30" value="<?php echo $email; ?>" class="nowarn" autocomplete="email">
      </div>
      <div class="field">
        <label for="ticketno"><?php echo __('Ticket Number'); ?></label>
        <input id="ticketno" type="text" name="lticket" placeholder="<?php echo __('e.g. 051243'); ?>"
            size="30" value="<?php echo $ticketid; ?>" class="nowarn" inputmode="numeric">
      </div>
      <p class="actions">
        <input class="btn" type="submit" value="<?php echo $button; ?>">
      </p>
    </form>
  </section>
  <div class="side-col">
<?php if ($cfg && $cfg->getClientRegistrationMode() !== 'disabled') { ?>
    <section class="card soft instructions">
      <h2><?php echo pcrs__('I have an account'); ?></h2>
      <p><?php echo __('Have an account with us?'); ?>
        <a href="login.php"><?php echo __('Sign In'); ?></a> <?php
    if ($cfg->isClientRegistrationEnabled()) { ?>
<?php echo sprintf(__('or %s register for an account %s to access all your tickets.'),
    '<a href="account.php?do=create">','</a>');
    } ?></p>
    </section>
<?php } ?>
<?php
if ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired()) { ?>
    <p><?php echo sprintf(
    __("If this is your first time contacting us or you've lost the ticket number, please %s open a new ticket %s"),
        '<a href="open.php">','</a>'); ?></p>
<?php } ?>
  </div>
</div>
