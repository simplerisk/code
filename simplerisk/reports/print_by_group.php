<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/reporting.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
add_session_check();

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];

$custom_display_settings = $_SESSION['custom_display_settings'];
if(!is_array($custom_display_settings)){
	$custom_display_settings = array(
        'id',
        'subject',
        'calculated_risk',
        'submission_date',
        'mitigation_planned',
        'management_review'
	);
}

$status = isset($_GET["status"])?$_GET["status"]:0;
$group = isset($_GET["group"])?$_GET["group"]:"";
$sort = isset($_GET["sort"])?$_GET["sort"]:0;
$group_value = isset($_GET["group_value"])?$_GET["group_value"]:"";

// Once it has been activated
if (import_export_extra()){
?>
<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/obsolete.js"></script>
  <script src="../js/common.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  
  <link rel="stylesheet" href="../css/divshot-canvas.css">
  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/side-navigation.css">

  <?php
      setup_favicon("..");
      setup_alert_requirements("..");
  ?>  
</head>
<body>
<style>
    #risk-table-container{overflow: auto;}
</style>
    <div class="container-fluid">
        <div class="row-fluid top-offset-15">
            <div class="span12">
                <div id="risk-table-container">
                    <?php get_risks_by_group($status, $group, $sort, $group_value, $custom_display_settings); ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php } else {
    echo $escaper->escapeHtml($lang['ImportExportIsDeactivated']);
}?>
