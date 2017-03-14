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
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (CSP_ENABLED == "true")
{
    // Add the Content-Security-Policy header
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
}

// Session handler is database
if (USE_DATABASE_FOR_SESSIONS == "true")
{
    session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
}

// Start the session
session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

if (!isset($_SESSION))
{
    session_name('SimpleRisk');
    session_start();
}

// Include the language file
require_once(language_file());

require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
    header("Location: ../index.php");
    exit(0);
}

// Record the page the workflow started from as a session variable
$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];

// Set the status
if (isset($_POST['status']))
{
    $status = (int)$_POST['status'];
}
else if (isset($_GET['status']))
{
    $status = (int)$_GET['status'];
}
else $status = 0;

// Set the group
if (isset($_POST['group']))
{
    $group = (int)$_POST['group'];
}
else if (isset($_GET['group']))
{
    $group = (int)$_GET['group'];
}
else $group = 0;

// Set the sort
if (isset($_POST['sort']))
{
    $sort = (int)$_POST['sort'];
}
else if (isset($_GET['sort']))
{
    $sort = (int)$_GET['sort'];
}
else $sort = 0;

// Set the columns
(isset($_POST['id']) ? $id = true : $id = false);
(isset($_POST['risk_status']) ? $risk_status = true : $risk_status = false);
(isset($_POST['subject']) ? $subject = true : $subject = false);
(isset($_POST['reference_id']) ? $reference_id = true : $reference_id = false);
(isset($_POST['regulation']) ? $regulation = true : $regulation = false);
(isset($_POST['control_number']) ? $control_number = true : $control_number = false);
(isset($_POST['location']) ? $location = true : $location = false);
(isset($_POST['source']) ? $source = true : $source = false);
(isset($_POST['category']) ? $category = true : $category = false);
(isset($_POST['team']) ? $team = true : $team = false);
(isset($_POST['technology']) ? $technology = true : $technology = false);
(isset($_POST['owner']) ? $owner = true : $owner = false);
(isset($_POST['manager']) ? $manager = true : $manager = false);
(isset($_POST['submitted_by']) ? $submitted_by = true : $submitted_by = false);
(isset($_POST['scoring_method']) ? $scoring_method = true : $scoring_method = false);
(isset($_POST['calculated_risk']) ? $calculated_risk = true : $calculated_risk = false);
(isset($_POST['submission_date']) ? $submission_date = true : $submission_date = false);
(isset($_POST['review_date']) ? $review_date = true : $review_date = false);
(isset($_POST['project']) ? $project = true : $project = false);
(isset($_POST['mitigation_planned']) ? $mitigation_planned = true : $mitigation_planned = false);
(isset($_POST['management_review']) ? $management_review = true : $management_review = false);
(isset($_POST['days_open']) ? $days_open = true : $days_open = false);
(isset($_POST['next_review_date']) ? $next_review_date = true : $next_review_date = false);
(isset($_POST['next_step']) ? $next_step = true : $next_step = false);
(isset($_POST['affected_assets']) ? $affected_assets = true : $affected_assets = false);
(isset($_POST['planning_strategy']) ? $planning_strategy = true : $planning_strategy = false);
(isset($_POST['mitigation_effort']) ? $mitigation_effort = true : $mitigation_effort = false);
(isset($_POST['mitigation_cost']) ? $mitigation_cost = true : $mitigation_cost = false);
(isset($_POST['mitigation_owner']) ? $mitigation_owner = true : $mitigation_owner = false);
(isset($_POST['mitigation_team']) ? $mitigation_team = true : $mitigation_team = false);
(isset($_POST['risk_assessment']) ? $risk_assessment = true : $risk_assessment = false);
(isset($_POST['additional_notes']) ? $additional_notes = true : $additional_notes = false);
(isset($_POST['current_solution']) ? $current_solution = true : $current_solution = false);
(isset($_POST['security_recommendations']) ? $security_recommendations = true : $security_recommendations = false);
(isset($_POST['security_requirements']) ? $security_requirements = true : $security_requirements = false);

// If there was not a POST
if (!isset($_POST['status']))
{
    // Set the default fields to show
    $id = true;
    $subject = true;
    $calculated_risk = true;
    $submission_date = true;
    $mitigation_planned = true;
    $management_review = true;
}

if (isset($_POST['status']) && isset($_GET['option']) && $_GET['option'] == "download"){

    if (is_dir(realpath(__DIR__ . '/../extras/import-export')))
    {
        // Once it has been activated
        if (import_export_extra()){
            
            // Include the Import-Export Extra
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

            download_risks_by_table($status, $group, $sort, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements);
        }
    }
}
?>

<!doctype html>
<html>

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/sorttable.js"></script>
  <script src="../js/obsolete.js"></script>
  <script src="../js/jquery.dataTables.js"></script>
  <script src="../js/dynamic.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/jquery.dataTables.css">
  
  <link rel="stylesheet" href="../css/divshot-canvas.css">

  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">
</head>

<body>

  <?php view_top_menu("Reporting"); ?>

  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_reporting_menu("DynamicRiskReport"); ?>
      </div>
      <div class="span9">
        <?php
            get_alert();
        ?>        
        <div class="row-fluid">
          <div id="selections" class="span12">
            <div class="well">
              <?php view_get_risks_by_selections($status, $group, $sort, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements); ?>
            </div>
          </div>
        </div>
        <div class="row-fluid bottom-offset-10">
            <div class="span6 text-left top-offset-15">
                <button class="expand-all"><?php echo $lang['ExpandAll'] ?></button>
            </div>
            <?php
		    // If the Import-Export Extra is installed
            	    if (is_dir(realpath(__DIR__ . '/../extras/import-export')))
            	    {
			    // And the Extra is activated
                	    if (import_export_extra())
                	    {
            			    // Include the Import-Export Extra
            			    require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
				    // Display the download link
				    display_download_link();
			    }
		    }
            ?>
        </div>

        <div class="row-fluid">
          <div class="span12">
            <div id="risk-table-container">
                <?php get_risks_by_table($status, $group, $sort, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
