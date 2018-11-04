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
  set_unauthenticated_redirect();
  header("Location: ../index.php");
  exit(0);
}


// Get page info
$currentpage = isset($_GET['currentpage']) ? $_GET['currentpage'] : "1";
// Get teams submitted by user
$teams = isset($_GET['teams']) ? $_GET['teams'] : "";
// Get owners submitted by user
$owners = isset($_GET['owners']) ? $_GET['owners'] : "";
// Get owner's managers submitted by user
$ownersmanagers = isset($_GET['ownersmanagers']) ? $_GET['ownersmanagers'] : "";

$teamOptions = get_teams_by_login_user();
array_unshift($teamOptions, array(
    'value' => "0",
    'name' => $lang['Unassigned'],
));

$ownerOptions = $ownersManagerOptions = get_table_ordered_by_name("user");
array_unshift($ownerOptions, array(
    'value' => "0",
    'name' => $lang['NoOwner'],
));
array_unshift($ownersManagerOptions, array(
    'value' => "0",
    'name' => $lang['NoOwnersManager'],
));

// Set the columns
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
    'risk_assessment',
    'additional_notes',
    'current_solution',
    'security_recommendations',
    'security_requirements',
);

$custom_values = [];

foreach($columns as $column){
    ${$column} = isset($_GET[$column]) ? true : false;
}

foreach($_GET as $key => $column){
    if(stripos($key, "custom_field_") === 0){
        $custom_values[$key] = 1;
    }
}

    // Set the default fields to show
//    $id = true;
//    $subject = true;
//    $calculated_risk = true;
//    $submission_date = true;
//    $mitigation_planned = true;
//    $management_review = true;

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
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">

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

            // Team dropdown
            $("#teams").multiselect({
                allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllTeams']); ?>',
                includeSelectAllOption: true,
                onChange: function(element, checked){
                    var brands = $('#teams option:selected');
                    var selected = [];
                    $(brands).each(function(index, brand){
                        selected.push($(this).val());
                    });
                    
                    $("#team_options").val(selected.join(","));

                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(submit_form, doneInterval);
                }
            });
            
            // Owner dropdown
            $("#owners").multiselect({
                allSelectedText: '<?php echo $escaper->escapeHtml($lang['AllOwners']); ?>',
                includeSelectAllOption: true,
                onChange: function(element, checked){
                    var brands = $('#owners option:selected');
                    var selected = [];
                    $(brands).each(function(index, brand){
                        selected.push($(this).val());
                    });
                    
                    $("#owner_options").val(selected.join(","));

                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(submit_form, doneInterval);
                }
            });
            
            // Owner's dropdown
            $("#ownersmanagers").multiselect({
                allSelectedText: "<?php echo $lang['AllOwnersManagers']; ?>",
                includeSelectAllOption: true,
                onChange: function(element, checked){
                    var brands = $('#ownersmanagers option:selected');
                    var selected = [];
                    $(brands).each(function(index, brand){
                        selected.push($(this).val());
                    });
                    
                    $("#ownersmanager_options").val(selected.join(","));

                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(submit_form, doneInterval);
                }
            });
            
            $(".pagination ul > li > a").click(function(e){
                e.preventDefault();
                $("#currentpage").val($(this).text().trim().toLowerCase());

                clearTimeout(typingTimer);
                typingTimer = setTimeout(submit_form, doneInterval);
            })
            
            $(".colums-select-container input[type=checkbox]").click(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(submit_form, doneInterval);
            })
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
                <div class="row-fluid">
                    <div class="span4">
                        <u><?php echo $escaper->escapeHtml($lang['Teams']); ?></u>: &nbsp;
                        <?php create_multiple_dropdown("teams", ":".implode(":", explode(",", $teams)).":" , NULL, $teamOptions); ?>
                    </div>
                    <div class="span4">
                        <u><?php echo $escaper->escapeHtml($lang['Owner']); ?></u>: &nbsp;
                        <?php create_multiple_dropdown("owners", ":".implode(":", explode(",", $owners)).":" , NULL, $ownerOptions); ?>
                    </div>
                    <div class="span4">
                        <u><?php echo $escaper->escapeHtml($lang['OwnersManager']); ?></u>: &nbsp;
                        <?php create_multiple_dropdown("ownersmanagers", ":".implode(":", explode(",", $ownersmanagers)).":" , NULL, $ownersManagerOptions); ?>
                    </div>
                </div>
                
                <div class="row-fluid" style="margin-top: 14px;">
                    <div class="well">
                        <form id="risks_by_teams_form" method="GET">
                            <input type="hidden" value="<?php echo (int)$currentpage; ?>" name="currentpage" id="currentpage" >
                            <input type="hidden" value="<?php echo $escaper->escapeHtml($teams); ?>" name="teams" id="team_options">
                            <input type="hidden" value="<?php echo $escaper->escapeHtml($owners); ?>" name="owners" id="owner_options">
                            <input type="hidden" value="<?php echo $escaper->escapeHtml($ownersmanagers); ?>" name="ownersmanagers" id="ownersmanager_options">
                            <div class="colums-select-container">
                                <?php echo display_risk_columns($id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $custom_values) ?>
                            </div>
                        </form>

                    </div>
                </div>
                
                <!--<div class="row-fluid" id="risks-open-by-team-container">
                    <?php // get_risk_table(22); ?>
                </div>-->

                <div class="row-fluid" id="risks-open-by-team-container">
                    <?php risk_table_open_by_team($teams, $owners, $ownersmanagers, $currentpage, $id, $risk_status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $submitted_by, $scoring_method, $calculated_risk, $residual_risk, $submission_date, $review_date, $project, $mitigation_planned, $management_review, $days_open, $next_review_date, $next_step, $affected_assets, $planning_strategy, $planning_date, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_date, $risk_assessment, $additional_notes, $current_solution, $security_recommendations, $security_requirements, $custom_values); ?>
                </div>
            </div>
        </div>
    </div>
    <?php display_set_default_date_format_script(); ?>
</body>

</html>
