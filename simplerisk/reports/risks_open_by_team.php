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

// Get page info
$currentpage = isset($_GET['currentpage']) ? $_GET['currentpage'] : "1";
// Get teams submitted by user
$teams = isset($_GET['teams']) ? $_GET['teams'] : [];
// Get owners submitted by user
$owners = isset($_GET['owners']) ? $_GET['owners'] : [];
// Get owner's managers submitted by user
$ownersmanagers = isset($_GET['ownersmanagers']) ? $_GET['ownersmanagers'] : [];

$teamOptions = get_teams_by_login_user();
array_unshift($teamOptions, array(
    'value' => "0",
    'name' => $lang['Unassigned'],
));

$ownerOptions = $ownersManagerOptions = get_options_from_table("enabled_users");
array_unshift($ownerOptions, array(
    'value' => "0",
    'name' => $lang['NoOwner'],
));
array_unshift($ownersManagerOptions, array(
    'value' => "0",
    'name' => $lang['NoOwnersManager'],
));

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
    'risk_tags'
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

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">

<head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>
    <script src="../js/sorttable.js"></script>
    <script src="../js/obsolete.js"></script>
    <script src="../js/jquery.dataTables.js"></script>
    <script src="../js/dynamic.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.css">

    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <script type="text/javascript">
        $(function(){
            // timer identifier
            var typingTimer;                
            // time in ms (1 second)
            var doneInterval = 2000;  
            function submit_form(){
                $("#risks_by_teams_form").submit();
            }

            function throttledFormSubmit() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(submit_form, doneInterval);
            }            
            
            // Team dropdown
            $("#teams").multiselect({
                allSelectedText: '<?php echo $escaper->escapeJs($lang['AllTeams']); ?>',
                includeSelectAllOption: true,
                onChange: throttledFormSubmit,
                onSelectAll: throttledFormSubmit,
                onDeselectAll: throttledFormSubmit
            });
            
            // Owner dropdown
            $("#owners").multiselect({
                allSelectedText: '<?php echo $escaper->escapeJs($lang['AllOwners']); ?>',
                includeSelectAllOption: true,
                onChange: throttledFormSubmit,
                onSelectAll: throttledFormSubmit,
                onDeselectAll: throttledFormSubmit
            });
            
            // Owner's dropdown
            $("#ownersmanagers").multiselect({
                allSelectedText: "<?php echo $escaper->escapeJs($lang['AllOwnersManagers']); ?>",
                includeSelectAllOption: true,
                onChange: throttledFormSubmit,
                onSelectAll: throttledFormSubmit,
                onDeselectAll: throttledFormSubmit
            });
        });
    </script>

</head>

<body>

    <?php view_top_menu("Reporting"); ?>

    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span3">
                <?php view_reporting_menu("AllOpenRisksByTeam"); ?>
            </div>
            <div class="span9">
                <form id="risks_by_teams_form" name="get_risks_by" method="GET">
                    <div class="row-fluid">
                        <div class="span4">
                            <u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u>: &nbsp;
                            <?php create_multiple_dropdown("teams", $teams, NULL, $teamOptions); ?>
                        </div>
                        <div class="span4">
                            <u><?php echo $escaper->escapeHtml($lang['Owner']); ?></u>: &nbsp;
                            <?php create_multiple_dropdown("owners", $owners , NULL, $ownerOptions); ?>
                        </div>
                        <div class="span4">
                            <u><?php echo $escaper->escapeHtml($lang['OwnersManager']); ?></u>: &nbsp;
                            <?php create_multiple_dropdown("ownersmanagers", $ownersmanagers, NULL, $ownersManagerOptions); ?>
                        </div>
                    </div>
                    
                    <div class="row-fluid" style="margin-top: 14px;">
                        <div class="well">
                            
                                <input type="hidden" value="<?php echo (int)$currentpage; ?>" name="currentpage" id="currentpage" >
                                <div class="colums-select-container">
                                    <?php echo display_risk_columns($id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $mitigation_controls, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $risk_tags, $custom_values) ?>
                                </div>
                            

                        </div>
                    </div>
                </form>
                <!--<div class="row-fluid" id="risks-open-by-team-container">
                    <?php // get_risk_table(22); ?>
                </div>-->

                <div class="row-fluid" id="risks-open-by-team-container">
                    <?php risk_table_open_by_team($id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $mitigation_controls, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $risk_tags, $custom_values); ?>
                </div>
            </div>
        </div>
    </div>
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
