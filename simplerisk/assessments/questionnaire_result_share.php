<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/assessments.php'));
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Add various security headers
add_security_headers();

if (!isset($_SESSION)) {
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true") {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

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

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

// Check for session timeout or renegotiation
session_check();

// If the assessments extra is enabled
if (assessments_extra()) {
    // Include the assessments extra
    require_once(realpath(__DIR__ . '/../extras/assessments/index.php'));

    // If a token wasn't sent or it's not a valid token
    if (!isset($_GET['token']) || !is_valid_questionnaire_result_share_token($_GET['token'])) {

        // Set the alert message
        set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidTokenForQuestionnaire']));

        set_unauthenticated_redirect();
        header("Location: ../index.php");
        exit(0);
    }
} else {
    // Set the alert message
    set_alert(true, "bad", "You need to purchase the Risk Assessment Extra in order to use this functionality.");

    set_unauthenticated_redirect();
    header("Location: ../index.php");
    exit(0);
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

    <link rel="stylesheet" href="../vendor/simplerisk/selectize.js/dist/css/selectize.bootstrap5.css?<?= $current_app_version ?>">

	<script src="../js/simplerisk/pages/assessment.js?<?= $current_app_version ?>" defer></script>

	<script src="../vendor/node_modules/chart.js/dist/chart.umd.js?20240603-001" id="script_chartjs" defer></script>

  	<script type="text/javascript">
        var BASE_URL = '<?= $escaper->escapeHtml($_SESSION['base_url'] ?? get_setting("simplerisk_base_url"))?>';
  	</script>
    </head>
    <body>
        <div class="preloader">
            <div class="lds-ripple">
                <div class="lds-pos"></div>
                <div class="lds-pos"></div>
            </div>
        </div>
        <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="none" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full" data-function="assessment">
            <header class="topbar" data-navbarbg="skin5">
                <nav class="navbar top-navbar navbar-expand-md navbar-dark">
                    <div class="navbar-header">
                        <a class="navbar-brand" href="https://www.simplerisk.com">
                            <img src="../images/logo@2x.png" alt="homepage" class="logo"/>
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
                            <div class="span12 questionnaire-response questionnaire-result-container">
                                <?php display_shared_questionnaire(); ?>
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
