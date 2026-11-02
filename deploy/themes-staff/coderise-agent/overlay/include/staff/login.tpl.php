<?php
/*********************************************************************
    include/staff/login.tpl.php  (coderise-agent staff theme)

    Replaces core's agent login page. Same form, ids, names, CSRF token,
    2FA branch, external authentication backends, #loading dialog and
    attemptLoginAjax(); the markup is wrapped in the split layout of the
    Coderise charter (presentation on the left, #loginBox card on the
    right). #login-message is the error banner itself (role="alert"):
    attemptLoginAjax() rewrites it with .text(), CSS hides it when empty.
    Shipped by deploy/themes-staff/coderise-agent (osTicket core untouched).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
include_once(INCLUDE_DIR.'staff/login.header.php');
$info = ($_POST && $errors)?Format::htmlchars($_POST):array();
// A failed attempt (non-AJAX POST): the card shows its error state; attemptLoginAjax() sets the same class on a 401
$cdr_err = ($_POST && $errors);

if ($thisstaff && $thisstaff->is2FAPending())
    $msg = "2FA Pending";
// Core's first-visit notice, in the panel language (core's pack has no entry for it)
if ($msg === 'Authentication Required')
    $msg = cdr__('Authentication Required');

?>
<div id="brickwall"></div>
<div class="login-wrap">
<section class="login-art on-dark" aria-label="<?php echo Format::htmlchars(cdr__('Presentation')); ?>">
    <div><img class="logo" src="<?php echo Format::htmlchars(cdr_asset('img/logo-horizontal-sombre.png')); ?>"
        width="2041" height="345" alt="Coderise"></div>
    <div>
        <p class="hook"><?php echo Format::htmlchars(cdr__('Every Priority ticket has its own countdown.')); ?></p>
        <p><?php echo Format::htmlchars(cdr__('Panel reserved for the Coderise team. Customers open and follow their requests on the support portal.')); ?></p>
    </div>
    <p class="signature"><?php echo sprintf(
        Format::htmlchars(cdr__('The code that makes your projects %s')),
        '<span class="up">'.Format::htmlchars(cdr__('take off')).'</span>'); ?></p>
    <span class="sommet" aria-hidden="true"></span>
</section>
<div id="loginBox" class="login-card<?php echo $cdr_err ? ' is-err' : ''; ?>">
    <div id="blur">
        <div id="background"></div>
    </div>
    <h1 id="logo" class="sr-only"><a href="index.php" tabindex="-1">
        <span class="valign-helper"></span>
        <img src="logo.php?login" alt="osTicket :: <?php echo Format::htmlchars(cdr__('Staff Control Panel')); ?>" />
    </a></h1>
    <h2 class="card-title"><?php echo Format::htmlchars(cdr__('Team sign-in')); ?></h2>
    <p class="lead"><?php echo Format::htmlchars(cdr__('Use your Coderise username.')); ?></p>
    <p id="login-message" class="banner-err" role="alert"><?php echo Format::htmlchars($msg); ?></p>
    <div class="banner"><small><?php echo ($content) ? Format::display($content->getLocalBody()) : ''; ?></small></div>
    <div id="loading" style="display:none;" class="dialog">
        <h1><i class="icon-spinner icon-spin icon-large"></i>
        <?php echo Format::htmlchars(cdr__('Verifying')); ?></h1>
    </div>
    <form action="login.php" method="post" id="login" onsubmit="attemptLoginAjax(event)">
        <?php csrf_token();
        if ($thisstaff
                &&  $thisstaff->is2FAPending()
                && ($bk=$thisstaff->get2FABackend())
                && ($form=$bk->getInputForm($_POST))) {
            // Render 2FA input form
            include STAFFINC_DIR . 'templates/dynamic-form-simple.tmpl.php';
            ?>
            <fieldset style="padding-top:10px;">
            <input type="hidden" name="do" value="2fa">
            <button class="submit button btn btn-primary pull-center" type="submit"
                name="submit"><i class="icon-signin"></i>
                <?php echo Format::htmlchars(cdr__('Verify')); ?>
            </button>
             </fieldset>
        <?php
        } else { ?>
            <input type="hidden" name="do" value="scplogin">
            <fieldset>
            <label for="name"><?php echo Format::htmlchars(cdr_t('Email or Username')); ?></label>
            <input type="text" name="userid" id="name" value="<?php
                echo $info['userid'] ?? null; ?>"
                autofocus autocorrect="off" autocapitalize="off" autocomplete="username"<?php
                echo $cdr_err ? ' aria-describedby="login-hint"' : ''; ?>>
            <label for="pass"><?php echo Format::htmlchars(cdr__('Password')); ?></label>
            <input type="password" name="passwd" id="pass" maxlength="128"
                autocorrect="off" autocapitalize="off" autocomplete="current-password"<?php
                echo $cdr_err ? ' aria-describedby="login-hint"' : ''; ?>>
            <p class="field-error" id="login-hint"><?php
                echo Format::htmlchars(cdr__('Check the username and password, then try again.')); ?></p>
                <button class="submit button btn btn-primary" type="submit"
                    name="submit"><i class="icon-signin"></i>
                    <?php echo Format::htmlchars(cdr_t('Log In')); ?>
                </button>
                <p class="alt"><a id="reset-link" class="<?php
                    if (!$show_reset || !$cfg->allowPasswordReset()) echo 'hidden';
                    ?>" href="pwreset.php"><?php echo Format::htmlchars(cdr_t('Forgot My Password')); ?></a></p>
            </fieldset>
        <?php
        } ?>
    </form>
<?php
if (($bks=StaffAuthenticationBackend::getExternal())) { ?>
<div class="or">
    <hr/>
</div><?php
    foreach ($bks as $bk) { ?>
<div class="external-auth"><?php $bk->renderExternalLink(); ?></div><br/><?php
    }
} ?>

    <div id="company">
        <div class="content">
            <?php echo Format::htmlchars(cdr__('Copyright')); ?> &copy; <?php echo Format::htmlchars($ost->company) ?: date('Y'); ?>
        </div>
    </div>
</div>
</div>
<div id="poweredBy"><?php echo Format::htmlchars(cdr__('Powered by')); ?>
    <a href="http://www.osticket.com" target="_blank">
        <img alt="osTicket" src="images/osticket-grey.png" class="osticket-logo">
    </a>
</div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (undefined === window.getComputedStyle(document.documentElement).backgroundBlendMode) {
            document.getElementById('loginBox').style.backgroundColor = 'white';
        }
    });

    function attemptLoginAjax(e) {
        $('#loading').show();
        var objectifyForm = function(formArray) { //serialize data function
            var returnArray = {};
            for (var i = 0; i < formArray.length; i++) {
                returnArray[formArray[i]['name']] = formArray[i]['value'];
            }
            return returnArray;
        };
        if ($.fn.effect) {
            // For some reason, JQuery-UI shake does not considere an element's
            // padding when shaking. Looks like it might be fixed in 1.12.
            // Thanks, https://stackoverflow.com/a/22302374
            var oldEffect = $.fn.effect;
            $.fn.effect = function (effectName) {
                if (effectName === "shake") {
                    $('#loading').hide();
                    var old = $.effects.createWrapper;
                    $.effects.createWrapper = function (element) {
                        var result;
                        var oldCSS = $.fn.css;

                        $.fn.css = function (size) {
                            var _element = this;
                            var hasOwn = Object.prototype.hasOwnProperty;
                            return _element === element && hasOwn.call(size, "width") && hasOwn.call(size, "height") && _element || oldCSS.apply(this, arguments);
                        };

                        result = old.apply(this, arguments);

                        $.fn.css = oldCSS;
                        return result;
                    };
                }
                return oldEffect.apply(this, arguments);
            };
        }
        var form = $(e.target),
            data = objectifyForm(form.serializeArray())
        data.ajax = 1;
        $('button[type=submit]', form).attr('disabled', 'disabled');
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data,
            cache: false,
            success: function(json) {
                 $('button[type=submit]', form).removeAttr('disabled');
                if (!typeof(json) === 'object' || !json.status)
                    return;
                switch (json.status) {
                case 401:
                    if (json && json.redirect)
                        document.location.href = json.redirect;
                    if (json && json.message)
                        $('#login-message').text(json.message)
                    // error state of the card: red banner, field borders and hint; the fields point to the hint
                    $('#loginBox').addClass('is-err')
                    $('#name, #pass').attr('aria-describedby', 'login-hint')
                    if (json && json.show_reset)
                        $('#reset-link').show()
                    if ($.fn.effect) {
                        $('#loginBox').effect('shake')
                    }
                    // Clear the password field
                    $('#pass').val('').focus();
                    break
                case 302:
                    if (json && json.redirect)
                        document.location.href = json.redirect;
                    break
                }
            },
        });
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return false;
    }
    </script>
    <!--[if IE]>
    <style>
        #loginBox:after { background-color: white !important; }
    </style>
    <![endif]-->
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
</body>
</html>
