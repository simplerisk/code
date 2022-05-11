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

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

if (!isset($_SESSION)) {
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true") {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
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
?>

<!doctype html>
<html>
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
        <script src="../js/pages/assessment.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/cve_lookup.js?<?php echo current_version("app"); ?>"></script>
        <script src="../extras/assessments/includes/js/questionnaire-result_share.js?<?php echo current_version("app"); ?>"></script>

        <?php
            // Use these HighCharts scripts
            $scripts = [
        		'highcharts.js',
        		'highcharts-more.js',
        		'modules/exporting.js',
        		'modules/export-data.js',
        		'modules/accessibility.js',
        	];

            // Display the highcharts javascript source
            display_highcharts_javascript($scripts);
        ?>

        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/display.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../vendor/components/font-awesome/css/all.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

        <?php
            setup_favicon("..");
            setup_alert_requirements("..");
        ?>
    </head>
    <body>
        <?php
            // Get any alert messages
            get_alert();
        ?>
        <div class="navbar">
            <div class="navbar-inner">
                <div class="container">
                    <a class="brand" href="http://www.simplerisk.com/"><img src='../images/logo@2x.png' alt='SimpleRisk' /></a>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row-fluid">
                <div class="span12 questionnaire-response questionnaire-result-container">
                    <?php display_shared_questionnaire(); ?>
                </div>
            </div>
        </div>
    </body>
</html>
