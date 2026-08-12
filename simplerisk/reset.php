<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/includes/functions.php'));
    require_once(realpath(__DIR__ . '/includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/includes/display.php'));
    require_once(realpath(__DIR__ . '/includes/alerts.php'));
    require_once(realpath(__DIR__ . '/includes/config_check.php'));
    require_once(realpath(__DIR__ . '/vendor/autoload.php'));

    // Add various security headers
    add_security_headers();

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

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    require_once(language_file());

    // Check if this is a page from reset password email
    $from_email_link = false;
    if (isset($_GET['token']) && $_GET['token'] && isset($_GET['username'])) {
        $token = $_GET['token'];
        $username = $_GET['username'];
        $from_email_link = true;
    }

    // The password reset flow is closed on a shared demo instance. Both halves
    // of it end in the same place — a new password on the account every visitor
    // shares — and this page is reachable without logging in at all, so it is
    // the one door a visitor doesn't even need the demo credentials to knock on.
    //
    // Refused here rather than deeper down because the token half writes the
    // new password with its own UPDATE statement inside
    // password_reset_by_token() instead of calling update_password(), so the
    // chokepoint guard in that function does not cover it. Refusing the email
    // request as well stops a visitor from using the demo as an on-demand way
    // to mail whatever address the shared account is registered under.
    if (demo_mode_blocks_password_reset(
        isset($_POST['send_reset_email']),
        isset($_POST['password_reset']),
        demo_mode()
    )) {

        set_alert(true, "bad", $lang['ActionDisabledOnDemoInstance']);

    // Check if a password reset email was requested
    } elseif (isset($_POST['send_reset_email'])) {

        if (isset($_POST['user']) && $_POST['user'] == "") {
            $message = _lang('FieldRequired', array("field"=>"Username"));
            set_alert(true, "bad", $message);
        } else {
            $server_host = parse_url(get_setting('simplerisk_base_url'), PHP_URL_HOST);

            // This was added to prevent attack by tampered host header
            if (!get_setting('simplerisk_base_url') || (isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER) && ($server_host == $_SERVER['SERVER_NAME']))) {

                $reset_email_username = $_POST['user'];

                // Open the database connection
                $db = db_open();

                // Get any password resets for this user in the past 10 minutes
                $stmt = $db->prepare("
                    SELECT 
                        * 
                    FROM 
                        password_reset 
                    WHERE 
                        username=:username AND timestamp >= NOW() - INTERVAL 10 MINUTE;
                ");
                $stmt->bindParam(":username", $reset_email_username, PDO::PARAM_STR, 200);
                $stmt->execute();

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // If we have password resets in the past 10 minutes
                if (count($results) != 0) {

                    // set_alert(true, "bad", $lang['PasswordResetRequestsExceeded']);
                    // we should display the same message regardless if the username is valid or not
                    // because if an attacker tries to gather the valid usernames through the password reset functionality
                    // then when they try to reset a username the second time in 10 minutes we reveal that the username IS valid by telling them that we DID send a reset email
                    set_alert(true, "good", $lang['PassworResetEmailSent']);
                    // Adding a random wait to increase noise in response time to make it harder for timing attacks on the password reset
                    wait(rand(1000, 3000));
                } else {

                    // Try to generate a password reset token
                    password_reset_by_username($reset_email_username);

                    // Display an alert
                    set_alert(true, "good", $lang['PassworResetEmailSent']);

                }

                // Close the database connection
                db_close($db);

            } else {

                set_alert(true, "bad", $lang['PassworResetRequestFailed']);

            }
        }
        
    // Check if a password reset was requested
    } elseif (isset($_POST['password_reset'])) {

        $username = $_POST['user'];
        $token = $_POST['token'];
        $password = $_POST['password'];
        $repeat_password = $_POST['repeat_password'];
        $fields = array("user"=>"Username","token"=>"Reset Token","password"=>"Password","repeat_password"=>"Repeat Password");
        $chk_require = true;

        // check required fields
        foreach ($fields as $field=>$label) {

            if ($_POST[$field] == "") {
                $message = _lang('FieldRequired', array("field"=>$label));
                set_alert(true, "bad", $message);
                $chk_require = false;
                break;
            }

        }

        if ($chk_require == true) {

            // If a password reset was submitted
            if (password_reset_by_token($username, $token, $password, $repeat_password)) {

                // Display an alert
                set_alert(true, "good", $lang['PassworResetSuccessfulRedirectIn5Secs']);

                // Redirect back to the login page
                $redirect_js = true;

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

<?php 

// If we need to redirect back to the login page
if (!empty($redirect_js)) {
    echo "
            <script>
                $(document).ready(function() {
                    window.setTimeout(function() {
                        location.href = 'index.php';
                    }, 5000);
                });
            </script>
        ";
}

?>
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
    if (!isset($_POST['send_reset_email']) && (!isset($token) || !$token)) {
    ?>
                <div class="sr-auth-col">
                    <div class="sr-auth-card">
                        <div class="sr-auth-card-head">
                            <h2><?= $escaper->escapeHtml($lang['SendPasswordResetEmail']);?></h2>
                            <p><?= $escaper->escapeHtml($lang['WeWillEmailAResetTokenToYourAccount']);?></p>
                        </div>
                        <form name="send_reset_email" method="post" action="" class="send_reset_email">
                            <div class="sr-auth-card-body">
                                <div class="sr-auth-field">
                                    <label for="user"><?= $escaper->escapeHtml($lang['Username']);?></label>
                                    <input class="input-medium form-control" name="user" id="user" type="text" autocomplete="username" required />
                                </div>
                                <div class="sr-auth-actions">
                                    <input class="btn btn-secondary" value="<?= $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
                                    <button type="submit" name="send_reset_email" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Send']); ?></button>
                                </div>
                                <div class="sr-auth-linkrow sr-auth-linkrow--center">
                                    <a class="sr-auth-link" href="index.php"><?= $escaper->escapeHtml($lang['BackToLogin']);?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
    <?php
        }

        if (isset($_POST['send_reset_email']) || !empty($token)){
    ?>
                <div class="sr-auth-col">
                    <div class="sr-auth-card">
                        <div class="sr-auth-card-head">
                            <h2><?= $escaper->escapeHtml($lang['PasswordReset']);?></h2>
                            <p><?= $escaper->escapeHtml($lang['EnterTheResetTokenFromYourEmail']);?></p>
                        </div>
                        <form name="password_reset" method="post" action="" class="password_reset">
                            <div class="sr-auth-card-body">
                                <div class="sr-auth-field">
                                    <label for="user"><?= $escaper->escapeHtml($lang['Username']) ?></label>
                                    <input class="form-control" autocomplete="username" name="user" value="<?= isset($username) ? $escaper->escapeHtml($username) : ''?>" id="user" type="text" required <?= $from_email_link ? 'readonly tabindex=-1' : ''?>/>
                                </div>
                                <div class="sr-auth-field">
                                    <label for="token"><?= $escaper->escapeHtml($lang['ResetToken'])?></label>
                                    <input class="form-control" autocomplete="one-time-code" value="<?= isset($token) ? $escaper->escapeHtml($token) : '' ?>" name="token" id="token" type="text" maxlength="32" required <?= $from_email_link ? 'readonly tabindex=-1' : ''?>/>
                                </div>
                                <div class="sr-auth-field">
                                    <label for="password"><?= $escaper->escapeHtml($lang['Password']) ?></label>
                                    <input class="form-control" name="password" id="password" type="password" autocomplete="current-password" required />
                                </div>
                                <div class="sr-auth-field">
                                    <label for="repeat_password" ><?= $escaper->escapeHtml($lang['RepeatPassword']) ?></label>
                                    <input class="form-control" name="repeat_password" id="repeat_password" type="password" autocomplete="new-password" required />
                                </div>
                                <div class="sr-auth-actions">
                                    <input class="btn btn-secondary" value="<?= $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
                                    <button type="submit" name="password_reset" class="btn btn-submit <?php if (!empty($redirect_js)) echo "hide";?>"><?= $escaper->escapeHtml($lang['Submit']); ?></button>
                                </div>
                                <div class="sr-auth-linkrow sr-auth-linkrow--center">
                                    <a class="sr-auth-link" href="index.php"><?= $escaper->escapeHtml($lang['BackToLogin']);?></a>
                                </div>
                            </div>
                        </form>
                    </div>
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
        		// Fading out the preloader once everything is done rendering
        		$(".preloader").fadeOut();
            });
    	</script>
    </body>
</html>