<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include the SimpleRisk configuration file
require_once realpath(__DIR__ . "/includes/config.php");

// If the database hasn't been installed yet
if (defined("SIMPLERISK_INSTALLED") && SIMPLERISK_INSTALLED == "false") {
    // Include the required installation file
    require_once realpath(__DIR__ . "/includes/install.php");

    // Call the SimpleRisk installation process
    simplerisk_installation();
}
// The SimpleRisk database has been installed
else {

    // Include required functions file
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
        if (USE_DATABASE_FOR_SESSIONS == "true") {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
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

    // Include the language file
    // Ignoring detections related to language files
    // @phan-suppress-next-line SecurityCheck-PathTraversal
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
                $_SESSION['user'] = $array['username'];

                // If the user needs to change their password upon login
                if($array['change_password'])
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
    <body>
        <div class="preloader">
            <div class="lds-ripple">
                <div class="lds-pos"></div>
                <div class="lds-pos"></div>
            </div>
        </div>
        <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="none" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full" data-function="login">
            <header class="topbar" data-navbarbg="skin5">
                <nav class="navbar top-navbar navbar-expand-md navbar-dark">
                    <div class="navbar-header">
                        <a class="navbar-brand" href="https://www.simplerisk.com">
                            <img src="images/logo@2x.png" alt="homepage" class="logo"/>
                        </a>
                    </div>
                </nav>
            </header>
            <!-- ============================================================== -->
            <!-- Page wrapper  -->
            <div class="page-wrapper">
            	<div class="scroll-content">
            		<div class="content-wrapper">
                        <!-- container - It's the direct container of all the -->
                        <div class="content container-fluid">
<?php 
    // If the user has authenticated and now we need to authenticate with mfa
    if (isset($_SESSION["access"]) && $_SESSION["access"] == "mfa") {
?>
                            <div class="container login-form">
                                <div class="row">
                                    <div class="col-md-3"></div>
                                    <div class="col-md-6">
                                	    <form name='mfa' method='post' action=''>
<?php
                                            display_mfa_authentication_page();
?>
                                	    </form> 
                                    </div>
                                    <div class="col-md-3"></div>
                                </div>
                            </div>
<?php
    // If the user needs to verify the new MFA
    } else if(isset($_SESSION["access"]) && $_SESSION["access"] == "mfa_verify") {
?>
                            <div class="container login-form">
                                <div class="row">
                                    <div class="col-md-12">
                            		    <form name='mfa' method='post' action=''>
                                            <div class='card' style='margin-top: 43.8px;'>
                                                <div class='card-body'>
<?php
                                                    // Display the MFA verification page
                                                    display_mfa_verification_page();
?>
                            		            </div>
                                            </div>
                                        </form> 
                                    </div>
                                </div>
                            </div>
<?php
    // If the user has not authenticated
    } else if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1") {
?>
                            <div class="container login-form">
                                <div class="row">
                                    <div class="col-md-3"></div>
                                    <div class="col-md-6">
                                        <h3>Enterprise Risk Management Simplified...</h3>
                                        <div class="card">
                                            <form class="loginForm" action="" method="post" name="authenticate">
                                                <div class="card-body">
                                                    <h4 class="card-title"><?= $escaper->escapeHtml($lang['LogInHere']);?>:</h4>
                                                    <div class="form-group">
                                                        <label><?= $escaper->escapeHtml($lang['Username']);?></label>
                                                        <input type="text" class="form-control user" id="user" name="user" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label><?= $escaper->escapeHtml($lang['Password']);?></label>
                                                        <div class="password-container">
                                                            <input type="password" class="form-control pass" id="pass" name="pass" required />
                                                            <span id="eye-icon"><i class="fa fa-eye"></i></span>
                                                        </div>
                                                    </div>
<?php
        // If the custom authentication extra is enabled
        if (custom_authentication_extra()) {
            // If SSO Login is enabled or not set yet
            if (get_setting("GO_TO_SSO_LOGIN") === false || get_setting("GO_TO_SSO_LOGIN") === "1") {
                                                    // Display the SSO login link
?>                                                
													<p><a href="extras/authentication/login.php"><?= $escaper->escapeHtml($lang["GoToSSOLoginPage"]);?></a></p>
<?php
            }
        }
?>
                                                    <div class="form-group">
                                                        <p class='m-b-0'><a href="reset.php"><?= $escaper->escapeHtml($lang['ForgotYourPassword']);?></a></p>
                                                        <div>
                                                            <button type="reset" class="btn btn-dark"><?= $escaper->escapeHtml($lang['Reset']);?></button>
                                                            <button type="submit" class="btn btn-submit" name="submit" value="submit"><?= $escaper->escapeHtml($lang['Login']);?></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-md-3"></div>
                                </div>
                            </div>

<?php
    }
?>
                        </div>
                        <!-- End of content -->
                	</div>
                	<!-- End of content-wrapper -->
        		</div>
        		<!-- End of scroll-content -->
          	</div>
          <!-- End Page wrapper  -->
        </div>
        <!-- End Wrapper -->
<?php
    get_alert();
    setup_alert_requirements("");
?>
    	<script>
            $(function() {

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
<?php
}
?>