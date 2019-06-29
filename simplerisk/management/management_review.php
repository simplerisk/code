<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
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

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
  set_unauthenticated_redirect();
  header("Location: ../index.php");
  exit(0);
}

// Include the CSRF-magic library
// Make sure it's called after the session is properly setup
include_csrf_magic();

// Enforce that the user has access to risk management
enforce_permission_riskmanagement();

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];

// If reviewed is passed via GET
if (isset($_GET['reviewed']))
{
  // If it's true
  if ($_GET['reviewed'] == true)
  {
    // Display an alert
    set_alert(true, "good", "Management review submitted successfully!");
  }
}

// If mitigated was passed back to the page as a GET parameter
if (isset($_GET['mitigated']))
{
  // If its true
  if ($_GET['mitigated'] == true)
  {
    // Display an alert
    set_alert(true, "good", "Mitigation submitted successfully!");
  }
}
?>

<!doctype html>
<html>

<head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.dataTables.js"></script>
    <script src="../js/cve_lookup.js"></script>
    <script src="../js/sorttable.js"></script>
    <script src="../js/common.js"></script>
    <script src="../js/pages/risk.js"></script>
    <script src="../js/highcharts/code/highcharts.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <script src="../js/jquery.blockUI.min.js"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.css">
    
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/style.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">

    <link rel="stylesheet" href="../css/selectize.bootstrap3.css">
    <script src="../js/selectize.min.js"></script>

    <?php
        setup_alert_requirements("..");
    ?>    

</head>

<body>
    <?php
        view_top_menu("RiskManagement");

    ?>
    <?php  
    // Get any alert messages
    get_alert();
    ?>
    
    <div class="tabs new-tabs">
        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3"> </div>
                <div class="span9">
                    <div class="tab-append">
                        <div class="tab selected form-tab tab-show new" >
                            <div>
                                <span>
                                    <!--<a href="management_review.php"><?php echo $escaper->escapeHtml($lang['RiskList']); ?></a>-->
                                    <?php echo $escaper->escapeHtml($lang['RiskList']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_risk_management_menu("PerformManagementReviews"); ?>
            </div>
            <div class="span9">
                <div id="tab-content-container" class="row-fluid">
                    <div id="tab-container" class="tab-data">
                        <div class="row-fluid">
                            <div class="span12 ">
                                <p><?php echo $escaper->escapeHtml($lang['ManagementReviewHelp']); ?>.</p>
                                <?php // get_risk_table(2,$activecol="management"); ?>
                                
                                <?php display_management_review(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="_delete_tab_alert" value="<?php echo $escaper->escapeHtml($lang['Are you sure you want to close the risk? All changes will be lost!']); ?>">
    <input type="hidden" id="enable_popup" value="<?php echo get_setting('enable_popup'); ?>">

    <?php display_set_default_date_format_script(); ?>
</body>

</html>
