<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');
/* Theme "Parcours" (Coderise) - overrides include/client/login.inc.php.
 * Same sign-in form (#clientLogin, luser, lpasswd, CSRF, external
 * authentication buttons, registration and agent links). Added next to it:
 * the "ticket without an account" form of accesslink.inc.php (same fields
 * lemail, lticket, same target login.php). The "forgot my password" link is
 * always shown (the core shows it after a failed attempt only). */

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
// Which of the two forms was posted (both report in $errors['login'])
$pcrs_access_post = isset($_POST['lticket']);
$pcrs_err = Format::htmlchars($errors['login']);
?>
<section class="top">
<?php echo pcrs_crumbs(array(array(pcrs__('Home'), ROOT_PATH . 'index.php'), array(__('Sign In'), null))); ?>
  <h1><?php echo Format::display($title); ?></h1>
  <div class="sub"><?php echo Format::display($body); ?></div>
</section>

<div class="auth-grid">
  <section class="card" aria-labelledby="login-title">
    <h2 id="login-title"><?php echo pcrs__('I have an account'); ?></h2>
    <form action="login.php" method="post" id="clientLogin">
      <?php csrf_token(); ?>
<?php if ($pcrs_err && !$pcrs_access_post) { ?>
      <div class="alert alert-error" role="alert" id="login-error"><?php echo pcrs_icon('alert', 20); ?><div><?php echo $pcrs_err; ?></div></div>
<?php } ?>
      <div class="field">
        <label for="username"><?php echo __('Email or Username'); ?></label>
        <input id="username" type="text" name="luser" size="30" value="<?php echo $email; ?>" class="nowarn" autocomplete="username"<?php
            if ($pcrs_err && !$pcrs_access_post) echo ' aria-describedby="login-error"'; ?>>
      </div>
      <div class="field">
        <label for="passwd"><?php echo __('Password'); ?></label>
        <input id="passwd" type="password" name="lpasswd" size="30" maxlength="128" value="<?php echo $passwd; ?>" class="nowarn" autocomplete="current-password">
      </div>
      <p class="actions">
        <input class="btn" type="submit" value="<?php echo __('Sign In'); ?>">
      </p>
      <p class="links">
        <a href="pwreset.php"><?php echo __('Forgot My Password'); ?></a>
      </p>
    </form>
<?php
$ext_bks = array();
foreach (UserAuthenticationBackend::allRegistered() as $bk)
    if ($bk instanceof ExternalAuthentication)
        $ext_bks[] = $bk;

if (count($ext_bks)) { ?>
    <hr>
<?php
    foreach ($ext_bks as $bk) { ?>
    <div class="external-auth"><?php $bk->renderExternalLink(); ?></div><?php
    }
} ?>
    <p class="links">
      <span><b><?php echo __("I'm an agent"); ?></b> —
      <a href="<?php echo ROOT_PATH; ?>scp/"><?php echo __('sign in here'); ?></a></span>
    </p>
  </section>

  <div class="side-col">
    <section class="card soft" aria-labelledby="access-title">
      <h2 id="access-title"><?php echo pcrs__('Without an account'); ?></h2>
      <p><?php echo __('Please provide your email address and a ticket number.'); ?></p>
      <form action="login.php" method="post" id="clientAccess">
        <?php csrf_token(); ?>
<?php if ($pcrs_err && $pcrs_access_post) { ?>
        <div class="alert alert-error" role="alert"><?php echo pcrs_icon('alert', 20); ?><div><?php echo $pcrs_err; ?></div></div>
<?php } ?>
        <div class="field">
          <label for="access-email"><?php echo __('Email Address'); ?></label>
          <input id="access-email" type="email" name="lemail" size="30" class="nowarn" autocomplete="email" value="<?php
            echo $pcrs_access_post ? Format::input($_POST['lemail']) : ''; ?>">
        </div>
        <div class="field">
          <label for="access-ticket"><?php echo __('Ticket Number'); ?></label>
          <input id="access-ticket" type="text" name="lticket" size="30" class="nowarn" inputmode="numeric" value="<?php
            echo $pcrs_access_post ? Format::input($_POST['lticket']) : ''; ?>">
        </div>
        <p class="actions"><input class="btn" type="submit" value="<?php
            echo $cfg->isClientEmailVerificationRequired() ? __('Email Access Link') : __('View Ticket'); ?>"></p>
      </form>
    </section>
<?php if ($cfg && $cfg->isClientRegistrationEnabled()) { ?>
    <section class="card" aria-labelledby="first-title">
      <h2 id="first-title"><?php echo pcrs__('First visit?'); ?></h2>
      <p><?php echo __('Not yet registered?'); ?></p>
      <a class="btn btn-line" href="account.php?do=create"><?php echo __('Create an account'); ?></a>
    </section>
<?php } ?>
<?php
if ($cfg->getClientRegistrationMode() != 'disabled'
    || !$cfg->isClientLoginRequired()) { ?>
    <p><?php echo sprintf(__('If this is your first time contacting us or you\'ve lost the ticket number, please %s open a new ticket %s'),
        '<a href="open.php">', '</a>'); ?></p>
<?php } ?>
  </div>
</div>
