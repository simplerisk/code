<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/config.php'));
    require_once(realpath(__DIR__ . '/../includes/upgrade.php'));
    require_once(realpath(__DIR__ . '/../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION)) {
        // Start the session
        $parameters = [
            "lifetime" => 0,
            "path" => "/",
            "domain" => "",
            "secure" => isset($_SERVER["HTTPS"]),
            "httponly" => true,
            "samesite" => "Strict",
        ];
        session_set_cookie_params($parameters);

        session_name('SimpleRiskDBUpgrade');
        session_start();
    }

    // Include the language file
    // Ignoring detections related to language files
    // @phan-suppress-next-line SecurityCheck-PathTraversal
    require_once(language_file());
    
    // Set a global variable for the current app version, so we don't have to call a function every time
    $current_app_version = current_version("app");

    csrf_init();

    // Check for session timeout or renegotiation
    session_check();

    // If the user requested a logout
    if (isset($_GET['logout']) && $_GET['logout'] == "true") {
        // Log the user out
        upgrade_logout();
    }

    // If the login form was posted
    if (isset($_POST['submit'])) {
        $user = $_POST['user'];
        $pass = $_POST['pass'];
        // If the user is valid
        if (is_valid_user($user, $pass, true)) {
            // Set the user permissions
            set_user_permissions($user, true);
            
            // Check if the user is an admin
            if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1") {
                // Grant access
                $_SESSION["access"] = "1";
            
            // The user is not an admin
            } else {
                // Display an alert
                set_alert(true, "bad", "You need to log in as an administrative user in order to upgrade the database.");
                // Deny access
                $_SESSION["access"] = "denied";
            }

        // The user was not valid
        } else {
            // If case sensitive usernames are enabled
            if (get_setting("strict_user_validation") != 0) {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPasswordCaseSensitive"]));
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang["InvalidUsernameOrPassword"]));
            }
            
            // Deny access
            $_SESSION["access"] = "denied";
        }
    }
    // If an API key is set and is valid
    if (isset($_GET['key']) && check_valid_key($_GET['key'])) {
        // Grant access
        $_SESSION["access"] = "1";
        // API key is admin
        $_SESSION["admin"] = "1";
    }
?>
<!DOCTYPE html>
<html dir="ltr" lang="en" xml:lang="en">
    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
          
        <!-- Favicon icon -->
        <?php setup_favicon("..");?>
        
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>" />

        <!-- jQuery CSS -->
        <link rel="stylesheet" href="../vendor/node_modules/jquery-ui/dist/themes/base/jquery-ui.min.css?<?= $current_app_version ?>">

        <!-- extra css -->

        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

        <!-- jQuery Javascript -->
        <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
        <script src="../vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>

        <!-- Bootstrap tether Core JavaScript -->
        <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

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
                <nav class="navbar top-navbar navbar-expand-md navbar-dark justify-content-between">
                    <div class="navbar-header">
                        <a class="navbar-brand" href="https://www.simplerisk.com">
                            <img src="../images/logo@2x.png" alt="homepage" class="logo"/>
                        </a>
                    </div>
                    <div class="navbar-content">
                        <ul class="nav"> 
                            <li>
                                <a class="text-white" href="upgrade.php">Database Upgrade Script</a>
                            </li>
                            <li>
                                <a class="text-white mx-4" href="upgrade.php?logout=true"><i class="fa fa-power-off m-r-5"></i>Logout</a>
                            </li>
                        </ul>
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
                            <div class="container login-form">
                                <div class="row">
                                    <div class="col-md-3"></div>
                                    <div class="col-md-6">

<?php
    // If access was not granted display the login form
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1") {
    
                                        // Display the login form
                                        display_login_form();
    
    // Otherwise access was granted so check if the user is an admin
    } else if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1") {

        // If CONTINUE was not pressed
        if (!isset($_POST['upgrade_database'])) {
            
                                        // Display the upgrade information
                                        display_upgrade_info();

        // Otherwise, CONTINUE was pressed
        } else {
            
            echo "
                                        <div class='card' style='margin-top: 43.8px; '>
                                            <div class='card-body'>
            ";

                                                // Upgrade the database
                                                upgrade_database();

                                                // Convert tables to InnoDB
                                                convert_tables_to_innodb();

                                                // Convert tables to utf8_general_ci
                                                convert_tables_to_utf8();

                                                // Display the clear cache warning
                                                display_cache_clear_warning();

            echo "
                                                <span class='d-block m-t-25'>!-- " . $escaper->escapeHtml($lang['UPGRADECOMPLETED']) . " --!</span>
                                            </div>
                                        </div>
            ";
        }
    }
?>
                                    </div>
                                    <div class="col-md-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
        // Get any alert messages
        get_alert();
        setup_alert_requirements("..");
?>
    	<script>
        	$(function() {
        		// Fading out the preloader once everything is done rendering
        		$(".preloader").fadeOut();
            });
    	</script>
    </body>
</html>