<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */


// Bootstrap validates config.php presence and the required DB_* defines.
// If the app isn't installed (or the config is incomplete), bootstrap
// renders the installer for HTTP requests, exits the script for CLI, and
// in either case exit()s before returning here.
require_once(realpath(__DIR__ . "/includes/bootstrap.php"));

// Include required functions file
require_once realpath(__DIR__ . "/includes/config_check.php");
require_once realpath(__DIR__ . "/includes/functions.php");
require_once realpath(__DIR__ . "/includes/authenticate.php");
require_once realpath(__DIR__ . "/includes/display.php");
require_once realpath(__DIR__ . "/includes/alerts.php");
require_once realpath(__DIR__ . "/includes/extras.php");
require_once realpath(__DIR__ . "/includes/install.php");
require_once realpath(__DIR__ . "/vendor/autoload.php");

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper("utf-8");
// Add various security headers
add_security_headers();

// Get the number of users in the database
$db = db_open();
$stmt = $db->prepare("SELECT count(value) as count FROM `user`;");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $result["count"];
db_close($db);

if (!isset($_SESSION)) {
    // Session handler is database
    if (use_database_for_sessions()) {
        SimpleRiskSessionHandler::register();
    }

    // Start session
    $parameters = [
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => isset($_SERVER["HTTPS"]),
        "httponly" => true,
        "samesite" => "Strict",
    ];
    session_set_cookie_params($parameters);

    sess_gc(1440);
    session_name('SimpleRisk');
    session_start();
}

// Generate a CSRF token for the login form if not already set
if (!isset($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

// Whether to honor the ?user=/?pass= login prefill-and-autosubmit below.
//
// That feature exists for one reason: to let the public demo instance hand a
// visitor a single link that drops them straight into the application. It is
// not a login convenience worth having anywhere else — credentials in a query
// string are written to web-server and proxy access logs, kept in browser
// history, and forwarded in the Referer header of the next request. So it is
// gated to DEMO_MODE, and on every ordinary install the parameters are ignored
// and the login form renders empty.
//
// Resolved once, unconditionally, because both the prefill (inside the
// not-yet-authenticated branch) and the autosubmit script (after it) read it.
$demo_login_prefill = demo_mode();

// Include the language file
require_once(language_file());

// If the database has been installed but there are no users
if ($count == 0) {
    // Create the default admin account
    create_default_admin_account();

    // Don't display the rest of the page
    exit();
}
// Otherwise go about the standard login process
else {
    // Checking for the SAML logout status
    if (custom_authentication_extra() && isset($_REQUEST["LogoutState"])) {
        global $lang;
        // Parse the logout state
        $state = \SimpleSAML\Auth\State::loadState((string) $_REQUEST["LogoutState"], "MyLogoutState");
        // @phan-suppress-next-line PhanTypeArraySuspiciousNullable -- loadState returns array for valid logout states; null is handled by the SubCode check below
        $ls = $state["saml:sp:LogoutStatus"]; /* Only works for SAML SP */
        if ($ls["Code"] === "urn:oasis:names:tc:SAML:2.0:status:Success" && !isset($ls["SubCode"])) {
            /* Successful logout. */
            set_alert(true, "good", $lang["SAMLLogoutSuccessful"]);
        } else {
            /* Logout failed. Tell the user to close the browser. */
            set_alert(true, "bad", $lang["SAMLLogoutFailed"]);
        }
    }
    // If the login form was posted
    $user='';
    if (isset($_POST["submit"])) {
        // Validate CSRF token
        if (empty($_POST['csrf_token']) || !isset($_SESSION['login_csrf_token']) ||
            !hash_equals($_SESSION['login_csrf_token'], $_POST['csrf_token'])) {
            header("location: index.php");
            exit();
        }
        // Regenerate token to prevent reuse
        $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));

        $user = !empty($_POST["user"]) ? trim($_POST["user"]) : '';
        $pass = !empty($_POST["pass"]) ? $_POST["pass"] : '';

        // check if the username is empty
        if (empty($user)) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang["UsernameCannotBeEmpty"]));

            // Redirect to the login page
            header("location: index.php");
            exit();

        }

        // check if the password is empty
        if (empty(trim($pass))) {

            // Display an alert
            set_alert(true, "bad", $escaper->escapeHtml($lang["PasswordCannotBeEmptyOrContainOnlySpaces"]));

            // Redirect to the login page
            header("location: index.php");
            exit();
            
        }

        // Check for expired lockouts
        check_expired_lockouts();

        // If the user is valid
        if (is_valid_user($user, $pass)) {
            $uid = get_id_by_user($user);
            $array = get_user_by_id($uid);
            // @phan-suppress-next-line PhanTypeInvalidDimOffset -- get_user_by_id() returns the user row including 'username' when valid
            $_SESSION['user'] = $array['username'];

            // If the user needs to change their password upon login
            //
            // Skipped entirely on a demo instance, and it has to be. DEMO_MODE
            // refuses every password change at the update_password()
            // chokepoint, so an account carrying change_password=1 would be
            // redirected to reset_password.php, refused there, and redirected
            // straight back here on the next attempt — permanently unable to
            // reach an authenticated session, with no way for a visitor or the
            // operator to clear the flag through the UI. The demo account's
            // password cannot change anyway, so the forced change has nothing
            // left to accomplish.
            //
            // Both DimOffset variants are suppressed: $array is `row|false` and
            // 'change_password' is an optional key in the inferred shape, so
            // Phan reports the plain issue for the bare fetch and the Possibly
            // variant once the fetch is one operand of a compound condition.
            // Neither is reachable — is_valid_user() has just passed, so
            // get_user_by_id() returned the row. Same reasoning as the
            // suppression on the $array['username'] fetch above.
            // @phan-suppress-next-line PhanTypeInvalidDimOffset,PhanTypePossiblyInvalidDimOffset
            if($array['change_password'] && !demo_mode())
            {
                $_SESSION['first_login_uid'] = $uid;

                if (encryption_extra()) {
                    // Load the extra
                    require_once realpath(
                        __DIR__ . "/extras/encryption/index.php"
                    );

                    // Get the current password encrypted with the temp key
                    check_user_enc($user, $pass);
                }

                // Put the posted password in the session before redirecting them to the reset page
                $_SESSION["first_login_pass"] = $pass;

                header("location: reset_password.php");
                exit();
            }

            // Create the SimpleRisk instance ID if it doesn't already exist
            create_simplerisk_instance_id();

            // Set the user permissions
            set_user_permissions($user);

            // Do a license check
            simplerisk_license_check();

            // Get base url
            $_SESSION["base_url"] = get_base_url();

            // Set login status
            login($user, $pass);
        }
        // If the user is not a valid user
        else {
            // In case the login attempt fails we're checking the cause.
            // If it's because the user 'Does Not Exist' we're doing a dummy
            // validation to make sure we're using the same time on a non-existant
            // user as we'd use on an existing
            if (get_user_type($user, false) === "DNE") {
                fake_simplerisk_user_validity_check();
            }

            $_SESSION["access"] = "denied";

            // If case sensitive usernames are enabled
            if (get_setting("strict_user_validation") != 0)
            {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPasswordCaseSensitive"]));
            }
            else set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPassword"]));

            // If the password attempt lockout is enabled
            if(get_setting("pass_policy_attempt_lockout") != 0) {
                // Add the login attempt and block if necessary
                add_login_attempt_and_block($user);
            }
        }
    }

    if (isset($_SESSION["access"]) && ($_SESSION["access"] == "1"))
    {
        // Select where to redirect the user next
        select_redirect();
    }

    // If the user has already authorized and we are authorizing with multi factor
    if (isset($_SESSION["access"]) && ($_SESSION["access"] == "mfa"))
    {
        // If a response has been posted
        if (isset($_POST['authenticate']))
        {
            // Validate CSRF token
            if (empty($_POST['csrf_token']) || !isset($_SESSION['login_csrf_token']) ||
                !hash_equals($_SESSION['login_csrf_token'], $_POST['csrf_token'])) {
                header("location: index.php");
                exit();
            }
            // Regenerate token to prevent reuse
            $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));

            // If the mfa token matches
            if (does_mfa_token_match()) {

                // still have to check if the user is locked out as failing MFA can now lock out the user
                if (!is_user_locked_out($_SESSION['uid'])) {

                    // If the encryption extra is enabled
                    if (encryption_extra())
                    {
                        // Load the extra
                        require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

                        // Check user enc
                        check_user_enc($user, $pass);
                    }

                    // Grant the user access
                    grant_access();

                    // Select where to redirect the user next
                    select_redirect();
                } else {
                    // if the user failed the MFA too many times and got locked out
                    // will still be unable to get in even if finally gets the code right

                    // Destroy the session
                    session_destroy();

                    // get back to the login screen
                    header("Location: index.php");
                }
            } elseif(get_setting("pass_policy_attempt_lockout") != 0) {
                // Add the login attempt and block if necessary
                // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset -- $_SESSION['user'] populated upstream when login flow reaches this branch
                add_login_attempt_and_block($_SESSION['user']);
            }
        }
    }

    // If the user has already been authorized and we need to verify their mfa
    if (isset($_SESSION["access"]) && $_SESSION["access"] == "mfa_verify")
    {
        // If a response has ben posted
        if (isset($_POST['verify']))
        {
            // Validate CSRF token
            if (empty($_POST['csrf_token']) || !isset($_SESSION['login_csrf_token']) ||
                !hash_equals($_SESSION['login_csrf_token'], $_POST['csrf_token'])) {
                header("location: index.php");
                exit();
            }
            // Regenerate token to prevent reuse
            $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));

            // If the MFA verification process worked
            if (process_mfa_verify())
            {
                // Convert the user to use the core MFA going forward
                enable_mfa_for_uid();

                // If the encryption extra is enabled
                if (encryption_extra())
                {
                    // Load the extra
                    require_once(realpath(__DIR__ . '/extras/encryption/index.php'));

                    // Check user enc
                    check_user_enc($user, $pass);
                }

                // Grant the user access
                grant_access();

                // Select where to redirect the user next
                select_redirect();
            }
        }
    }
}

// Set a global variable for the current app version, so we don't have to call a function every time
$current_app_version = current_version("app");

?>
<!DOCTYPE html>
<html dir="ltr" lang="en" xml:lang="en">
<head>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    
    <!-- Favicon icon -->
    <?php setup_favicon();?>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/style.min.css?<?= $current_app_version ?>" />

    <!-- jQuery CSS -->
    <link rel="stylesheet" href="vendor/node_modules/jquery-ui/dist/themes/base/jquery-ui.min.css?<?= $current_app_version ?>">

    <!-- extra css -->
    <link rel="stylesheet" href="vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

    <!-- jQuery Javascript -->
    <script src="vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
    <script src="vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>

    <!-- Bootstrap tether Core JavaScript -->
    <script src="vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

</head>
<body class="sr-auth-page">
    <div class="preloader">
        <div class="lds-ripple">
            <div class="lds-pos"></div>
            <div class="lds-pos"></div>
        </div>
    </div>
    <div class="sr-auth">
<?php display_auth_brand_panel(); ?>
        <main class="sr-auth-main">
<?php
// If the user has authenticated and now we need to authenticate with mfa
if (isset($_SESSION["access"]) && $_SESSION["access"] == "mfa") {
?>
            <div class="sr-auth-col">
                <div class="sr-auth-card">
                    <div class="sr-auth-card-head">
                        <h2><?= $escaper->escapeHtml($lang['YourSimpleRiskAccountIsProtected']);?></h2>
                        <p><?= $escaper->escapeHtml($lang['VerifyItsYou']);?></p>
                    </div>
                    <form name='mfa' method='post' action=''>
                        <input type="hidden" name="csrf_token" value="<?= $escaper->escapeHtmlAttr($_SESSION['login_csrf_token']) ?>">
                        <div class="sr-auth-card-body">
<?php
                            display_mfa_authentication_page();
?>
                        </div>
                    </form>
                </div>
            </div>
<?php
// If the user needs to verify the new MFA
} else if(isset($_SESSION["access"]) && $_SESSION["access"] == "mfa_verify") {
?>
            <!-- Wider column: the enrolment step puts a QR code beside its input.
                 display_mfa_verification_page() is shared with account/mfa.php, so
                 its markup is hosted as-is and only restyled via .sr-auth-mfa. -->
            <div class="sr-auth-col sr-auth-col--wide">
                <div class="sr-auth-card">
                    <form name='mfa' method='post' action=''>
                        <input type="hidden" name="csrf_token" value="<?= $escaper->escapeHtmlAttr($_SESSION['login_csrf_token']) ?>">
                        <div class="sr-auth-card-body sr-auth-mfa">
<?php
                            // Display the MFA verification page
                            display_mfa_verification_page();
?>
                        </div>
                    </form>
                </div>
            </div>
<?php
// If the user has not authenticated
} else if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1") {
?>
            <div class="sr-auth-col">
                <div class="sr-auth-card">
                    <div class="sr-auth-card-head">
                        <h2><?= $escaper->escapeHtml($lang['LogInHere']);?></h2>
                        <p><?= $escaper->escapeHtml($lang['EnterTheCredentialsForYourAccount']);?></p>
                    </div>
                    <form class="loginForm" action="" method="post" name="authenticate">
                        <input type="hidden" name="csrf_token" value="<?= $escaper->escapeHtmlAttr($_SESSION['login_csrf_token']) ?>">
                        <div class="sr-auth-card-body">
                            <div class="sr-auth-field">
                                <label for="user"><?= $escaper->escapeHtml($lang['Username']);?></label>
                                <input type="text" class="form-control user" id="user" name="user" autocomplete="username" value="<?= $demo_login_prefill && isset($_GET['user']) ? $escaper->escapeHtmlAttr($_GET['user']) : '' ?>" required />
                            </div>
                            <div class="sr-auth-field">
                                <label for="pass"><?= $escaper->escapeHtml($lang['Password']);?></label>
                                <div class="sr-auth-pass">
                                    <input type="password" class="form-control pass" id="pass" name="pass" autocomplete="current-password" value="<?= $demo_login_prefill && isset($_GET['pass']) ? $escaper->escapeHtmlAttr($_GET['pass']) : '' ?>" required />
                                    <span id="eye-icon"><i class="fa fa-eye"></i></span>
                                </div>
                            </div>
                            <div class="sr-auth-linkrow">
                                <a class="sr-auth-link" href="reset.php"><?= $escaper->escapeHtml($lang['ForgotYourPassword']);?></a>
                            </div>
                            <div class="sr-auth-actions">
                                <button type="reset" class="btn btn-dark"><?= $escaper->escapeHtml($lang['Reset']);?></button>
                                <button type="submit" class="btn btn-submit" name="submit" value="submit"><?= $escaper->escapeHtml($lang['Login']);?></button>
                            </div>
<?php
    // If the custom authentication extra is enabled
    if (custom_authentication_extra()) {
        // If SSO Login is enabled or not set yet
        if (get_setting("GO_TO_SSO_LOGIN") === false || get_setting("GO_TO_SSO_LOGIN") === "1") {
                            // Display the SSO login link
?>
                            <div class="sr-auth-divider"><?= $escaper->escapeHtml($lang['Or']);?></div>
                            <a class="sr-auth-sso" href="extras/authentication/login.php"><?= $escaper->escapeHtml($lang["GoToSSOLoginPage"]);?></a>
<?php
        }
    }
?>
                        </div>
                    </form>
                </div>
                <p class="sr-auth-help"><?= $escaper->escapeHtml($lang['TroubleSigningIn']);?></p>
            </div>
<?php
}
?>
        </main>
    </div>
    <!-- End Wrapper -->
<?php
get_alert();
setup_alert_requirements("");
?>
	<script>
        $(function() {

            // Click submit (not form.submit()) so the button's name="submit" reaches $_POST and to avoid named-element shadowing.
            // Demo instances only — see the $demo_login_prefill comment above.
            <?php if ($demo_login_prefill && !empty($_GET['user']) && !empty($_GET['pass'])): ?>
            document.querySelector('.loginForm button[type="submit"]').click();
            <?php endif; ?>

            // Show the password when the eye icon is clicked
            $("#eye-icon").on("mousedown", function() {

                // Change the password input type to text so the password is visible
                $("#pass").attr("type", "text");

                // Change the eye icon to an eye slash icon
                $("#eye-icon i").attr("class", "fa fa-eye-slash");

            });

            // Hide the password when the eye icon is released or the mouse leaves the icon
            $("#eye-icon").on("mouseup mouseleave", function() {

                // Change the password input type back to password so the password is hidden
                $("#pass").attr("type", "password");

                // Change the eye slash icon back to an eye icon
                $("#eye-icon i").attr("class", "fa fa-eye");

            });
            
    		// Fading out the preloader once everything is done rendering
    		$(".preloader").fadeOut();

        });
	</script>
</body>
</html>
