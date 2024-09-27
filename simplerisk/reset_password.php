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

    // Include the CSRF Magic library
    include_csrf_magic();

    // Include the language file
    // Ignoring detections related to language files
    // @phan-suppress-next-line SecurityCheck-PathTraversal
    require_once(language_file());
	
    // Set a global variable for the current app version, so we don't have to call a function every time
	$current_app_version = current_version("app");

    if(empty($_SESSION['first_login_uid'])) {

        header('Location: index.php');
        exit;

    }

    // Check if a password reset was requested
    if (isset($_POST['password_reset'])) {

        $user_id            = $_SESSION['first_login_uid'];
        $current_password   = $_SESSION['first_login_pass'];
        $new_password       = $_POST['new_password'];
        $confirm_password    = $_POST['confirm_password'];

        // Remove the current password from the session
        unset($_SESSION['first_login_pass']);

        // If a password reset was submitted
        reset_password($user_id, $current_password, $new_password, $confirm_password);

    }

?>
<!doctype html>
<html>
    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
        
        <!-- Favicon icon -->
        <?php setup_favicon();?>

        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="css/style.min.css?<?= $current_app_version ?>" />

        <!-- extra css -->
        <link rel="stylesheet" href="vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

        <!-- jQuery Javascript -->
        <script src="vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>

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
        <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="none" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full" data-function="reset_password">
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
                            <div class="container reset-password-form">
                                <div class="row">
                                    <div class="col-md-3"></div>
                                    <div class="col-md-6">
                                        <h3>Enterprise Risk Management Simplified...</h3>
                                        <div class="card">
                                            <form name="password_reset" method="post" autocomplete="off" action="" class="password_reset">
                                                <div class="card-body">
                                                    <h4 class="card-title"><?= $escaper->escapeHtml($lang['PasswordChangeRequired']);?></h4>
    <?php
        $resetRequestMessages = getPasswordReqeustMessages();
        if(count($resetRequestMessages)) {
            echo "
                                                    <p class='mb-2'><b>Password should have the following requirements.</b></p>
                                                    <ul>
            ";
            foreach($resetRequestMessages as $resetRequestMessage) {
                echo "
                                                        <li>{$resetRequestMessage}</li>
                ";
            }
            echo "
                                                    </ul>
            ";
        }
    ?>
                                                    <div class="form-group">
                                                        <label><?= $escaper->escapeHtml($lang['NewPassword']);?></label>
                                                        <input class="form-control" name="new_password" id="new_password" type="password" maxlength="50" autocomplete="off" />
                                                    </div>
                                                    <div class="form-group">
                                                        <label><?= $escaper->escapeHtml($lang['ConfirmPassword']);?></label>
                                                        <input class="form-control" name="confirm_password" id="confirm_password" type="password" maxlength="50" autocomplete="off" />
                                                    </div>
                                                    <div class="form-group justify-content-end">
                                                        <div>
                                                            <button type="reset" class="btn btn-dark"><?= $escaper->escapeHtml($lang['Reset']);?></button>
                                                            <button type="submit" class="btn btn-submit" name="password_reset"><?= $escaper->escapeHtml($lang['Submit']);?></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-md-3"></div>
                                </div>
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