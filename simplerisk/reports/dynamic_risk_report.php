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

if (isset($_POST['affected_assets_filter']))
{
    $affected_assets_filter = empty($_POST['affected_assets_filter']) ? [] : $_POST['affected_assets_filter'];
}
else if (isset($_GET['affected_assets_filter']))
{
    $affected_assets_filter = empty($_GET['affected_assets_filter']) ? [] : $_GET['affected_assets_filter'];
}
else $affected_assets_filter = [];

$processed_affected_assets_filter = [];

if (!empty($affected_assets_filter)) {
    $processed_affected_assets_filter = array('group'=>[], 'asset'=>[]);
    foreach($affected_assets_filter as $asset_filter) {
        if (preg_match('/^([\d]+)_(group|asset)$/', $asset_filter, $matches)) {
            list(, $id, $type) = $matches;
            
            array_push($processed_affected_assets_filter[$type], (int)$id);
        }
    }
}

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

// Set the Tags
if (isset($_POST['tags_filter']))
{
    $tags_filter = empty($_POST['tags_filter']) ? [] : array_map('intval', $_POST['tags_filter']);
}
else if (isset($_GET['tags_filter']))
{
    $tags_filter = empty($_GET['tags_filter']) ? [] : array_map('intval', $_GET['tags_filter']);
}
else $tags_filter = [];

// Names list of Risk columns
$columns = array(
    'id',
    'risk_status',
    'subject',
    'reference_id',
    'regulation',
    'control_number',
    'location',
    'source',
    'category',
    'team',
    'additional_stakeholders',
    'technology',
    'owner',
    'manager',
    'submitted_by',
    'scoring_method',
    'calculated_risk',
    'residual_risk',
    'submission_date',
    'review_date',
    'project',
    'mitigation_planned',
    'management_review',
    'days_open',
    'next_review_date',
    'next_step',
    'affected_assets',
    'planning_strategy',
    'planning_date',
    'mitigation_effort',
    'mitigation_cost',
    'mitigation_owner',
    'mitigation_team',
    'mitigation_date',
    'mitigation_controls',
    'risk_assessment',
    'additional_notes',
    'current_solution',
    'security_recommendations',
    'security_requirements',
    'risk_tags',
);

$custom_values = [];

if(is_array($custom_display_settings = $_SESSION['custom_display_settings']) && !isset($_POST['status'])){
    foreach($columns as $column){
        ${$column} = in_array($column, $custom_display_settings) ? true : false;
    }
    foreach($custom_display_settings as $custom_display_setting){
        if(stripos($custom_display_setting, "custom_field_") === 0){
            $custom_values[$custom_display_setting] = 1;
        }
    }
}elseif(isset($_POST['status'])){
    foreach($columns as $column){
        ${$column} = isset($_POST[$column]) ? true : false;
    }
    foreach($_POST as $key=>$val){
        if(stripos($key, "custom_field_") === 0){
            $custom_values[$key] = 1;
        }
    }
}else{
    $id = true;
    $subject = true;
    $calculated_risk = true;
    $residual_risk = true;
    $submission_date = true;
    $mitigation_planned = true;
    $management_review = true;
}

// If there was not a POST
//if (!isset($_POST['status']))
//{
    // Set the default fields to show
//    $id = true;
//    $subject = true;
//    $calculated_risk = true;
//    $submission_date = true;
//    $mitigation_planned = true;
//    $management_review = true;
//}

// Once it has been activated
if (import_export_extra()){
    // Include the Import-Export Extra
    require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
    
    // if download request, download all risks
    if (isset($_POST['status']) && isset($_GET['option']) && $_GET['option'] == "download")
    {
        download_risks_by_table($status, $group, $sort, $processed_affected_assets_filter, $tags_filter, NULL, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $mitigation_controls, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $risk_tags, $custom_values);
    }

    // if group download request, download risks by the group
    if(isset($_GET['option']) && $_GET['option'] == "download-by-group")
    {
        $group_value = $_GET['group_value'];
        download_risks_by_table($status, $group, $sort, $processed_affected_assets_filter, $tags_filter, $group_value, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $mitigation_controls, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $risk_tags, $custom_values);
    }
}


?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/sorttable.js"></script>
  <script src="../js/obsolete.js"></script>
  <script src="../js/jquery.dataTables.js"></script>
  <script src="../js/dynamic.js"></script>
  <script src="../js/bootstrap-multiselect.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/jquery.dataTables.css">
  
  <link rel="stylesheet" href="../css/divshot-canvas.css">
  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">

  <?php
      setup_alert_requirements("..");
  ?>  
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
              <?php view_get_risks_by_selections($status, $group, $sort, $affected_assets_filter, $tags_filter, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $mitigation_controls, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $risk_tags, $custom_values); ?>
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
                <?php get_risks_by_table($status, $group, $sort, $processed_affected_assets_filter, $tags_filter, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $mitigation_controls, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $risk_tags, $custom_values); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
