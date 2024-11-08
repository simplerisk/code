<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/includes/functions.php'));
    require_once(realpath(__DIR__ . '/includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/includes/display.php'));
    require_once(realpath(__DIR__ . '/includes/alerts.php'));
    require_once(realpath(__DIR__ . '/vendor/autoload.php'));

    // Add various security headers
    add_security_headers();

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

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    // Ignoring detections related to language files
    // @phan-suppress-next-line SecurityCheck-PathTraversal
    require_once(language_file());

    // Check if this is a page from reset password email
    if (isset($_GET['token']) && $_GET['token'] && isset($_GET['username'])) {
        $token = $_GET['token'];
        $username = $_GET['username'];
    }

    // Check if a password reset email was requested
    if (isset($_POST['send_reset_email'])) {

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

            } else {
                
                if (isset($_SESSION['alert']) && $_SESSION['alert'] == true) {
                } else {

                    // Display an alert
                    set_alert(true, "bad", $lang['PassworResetRequestFailed']);

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
    <body>
        <div class="preloader">
            <div class="lds-ripple">
                <div class="lds-pos"></div>
                <div class="lds-pos"></div>
            </div>
        </div>
        <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="none" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full" data-function="reset">
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
                            <div class="container reset-form">
    <?php 
    if (!isset($_POST['send_reset_email']) && (!isset($token) || !$token)) { 
    ?>
                                <div class="row">
                                	<div class="col-md-3 col-6"></div>
                						<div class="col-md-6 col-6 offset4">
                							<h3><?= $escaper->escapeHtml($lang['SendPasswordResetEmail']);?></h3>
                                            <div class="card">
                                            	<div class="card-body">
                                            		<form name="send_reset_email" method="post" action="" class="send_reset_email">
                                            			<div class="form-group">
                                                            <label><?= $escaper->escapeHtml($lang['Username']);?></label>
                                                            <input class="input-medium form-control" name="user" id="user" type="text" required />
                                                        </div>
                                                        <div class="form-actions float-end">
                											<input class="btn btn-secondary text-white" value="<?= $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
                											<button type="submit" name="send_reset_email" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Send']); ?></button>
                										</div>
                                            		</form>
                                            	</div>
                							</div>
                						</div>

                			        <div class="col-md-3 col-6"></div>
                                </div>
    <?php
        }

        if (isset($_POST['send_reset_email']) || !empty($token)){
    ?>
                                <div class="row">
                                	<div class="col-md-3 col-6"></div>
                						<div class="col-md-6 col-6 offset4">
                							<h3><?= $escaper->escapeHtml($lang['PasswordReset']);?></h3>
                                            <div class="card">
                                            	<div class="card-body">
                                            		<form name="password_reset" method="post" action="" class="password_reset">
                                            			<div class="form-group">
                			                                <label for="user"><?= $escaper->escapeHtml($lang['Username']) ?></label>
                			                                <input class="form-control" autocomplete="username" name="user" value="<?= isset($username) ? $escaper->escapeHtml($username) : ''?>" id="user" type="text" required <?= isset($username) ? 'readonly tabindex=-1' : ''?>/>
                                                        </div>
                                                        <div class="form-group">
                                                    		<label for="token"><?= $escaper->escapeHtml($lang['ResetToken'])?></label>
                			                                <input class="form-control" autocomplete="one-time-code" value="<?= isset($token) ? $escaper->escapeHtml($token) : '' ?>" name="token" id="token" type="text" maxlength="20" required <?= isset($token) ? 'readonly tabindex=-1' : ''?>/>
                                                        </div>
                                                        <div class="form-group">
                											<label for="password"><?= $escaper->escapeHtml($lang['Password']) ?></label>
                				                            <input class="form-control" name="password" id="password" type="password" autocomplete="current-password" required />
                										</div>
                                                        <div class="form-group">
                				                            <label for="repeat_password" ><?= $escaper->escapeHtml($lang['RepeatPassword']) ?></label>
                				                            <input class="form-control" name="repeat_password" id="repeat_password" type="password" autocomplete="new-password" required />
                                                        </div>
                                                        <div class="form-actions float-end">
                											<input class="btn btn-secondary text-white" value="<?= $escaper->escapeHtml($lang['Reset']); ?>" type="reset">
                											<button type="submit" name="password_reset" class="btn btn-submit <?php if (!empty($redirect_js)) echo "hide";?>"><?= $escaper->escapeHtml($lang['Submit']); ?></button>
                										</div>
                                            		</form>
                                            	</div>
                							</div>
                						</div>
                			        <div class="col-md-3 col-6"></div>
                                </div>
    <?php
        } 
    ?>
                            </div>
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
        		// Fading out the preloader once everything is done rendering
        		$(".preloader").fadeOut();
            });
    	</script>
    </body>
</html>