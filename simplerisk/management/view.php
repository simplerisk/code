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
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

// Add the session
$permissions = array(
        "check_access" => true,
        "check_riskmanagement" => true,
);
add_session_check($permissions);

// Include the CSRF Magic library
include_csrf_magic();

// Include the SimpleRisk language file
require_once(language_file());

// Check if a risk ID was sent
if (isset($_GET['id']))
{
    // Test that the ID is a numeric value
    $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        if (!extra_grant_access($_SESSION['uid'], $id))
        {
            // Do not allow the user to update the risk
            $access = false;
        }
        // Otherwise, allow the user to update the risk
        else $access = true;
    }
    // Otherwise, allow the user to update the risk
    else $access = true;

    // Get the details of the risk
    $risk = get_risk_by_id($id);

    // If the risk was found use the values for the risk
    if (count($risk) != 0)
    {
        $submitted_by = $risk[0]['submitted_by'];
        $status = $risk[0]['status'];
        $subject = $risk[0]['subject'];
        $reference_id = $risk[0]['reference_id'];
        $regulation = $risk[0]['regulation'];
        $control_number = $risk[0]['control_number'];
        $location = $risk[0]['location'];
        $source = $risk[0]['source'];
        $category = $risk[0]['category'];
        $team = $risk[0]['team'];
        $additional_stakeholders = $risk[0]['additional_stakeholders'];
        $technology = $risk[0]['technology'];
        $owner = $risk[0]['owner'];
        $manager = $risk[0]['manager'];
        $assessment = $risk[0]['assessment'];
        $notes = $risk[0]['notes'];
        $jira_issue_key = jira_extra() ? $risk[0]['jira_issue_key'] : "";
        $submission_date = $risk[0]['submission_date'];
        $risk_tags = $risk[0]['risk_tags'];
        $mitigation_id = $risk[0]['mitigation_id'];
        $mgmt_review = $risk[0]['mgmt_review'];
        $calculated_risk = $risk[0]['calculated_risk'];
        $residual_risk = $risk[0]['residual_risk'];
        $next_review = $risk[0]['next_review'];
        $color = get_risk_color($calculated_risk);
        $residual_color = get_risk_color($residual_risk);
        $risk_level = get_risk_level_name($calculated_risk);
        $residual_risk_level = get_risk_level_name($residual_risk);
        $scoring_method = $risk[0]['scoring_method'];
        $CLASSIC_likelihood = $risk[0]['CLASSIC_likelihood'];
        $CLASSIC_impact = $risk[0]['CLASSIC_impact'];
        $AccessVector = $risk[0]['CVSS_AccessVector'];
        $AccessComplexity = $risk[0]['CVSS_AccessComplexity'];
        $Authentication = $risk[0]['CVSS_Authentication'];
        $ConfImpact = $risk[0]['CVSS_ConfImpact'];
        $IntegImpact = $risk[0]['CVSS_IntegImpact'];
        $AvailImpact = $risk[0]['CVSS_AvailImpact'];
        $Exploitability = $risk[0]['CVSS_Exploitability'];
        $RemediationLevel = $risk[0]['CVSS_RemediationLevel'];
        $ReportConfidence = $risk[0]['CVSS_ReportConfidence'];
        $CollateralDamagePotential = $risk[0]['CVSS_CollateralDamagePotential'];
        $TargetDistribution = $risk[0]['CVSS_TargetDistribution'];
        $ConfidentialityRequirement = $risk[0]['CVSS_ConfidentialityRequirement'];
        $IntegrityRequirement = $risk[0]['CVSS_IntegrityRequirement'];
        $AvailabilityRequirement = $risk[0]['CVSS_AvailabilityRequirement'];
        $DREADDamagePotential = $risk[0]['DREAD_DamagePotential'];
        $DREADReproducibility = $risk[0]['DREAD_Reproducibility'];
        $DREADExploitability = $risk[0]['DREAD_Exploitability'];
        $DREADAffectedUsers = $risk[0]['DREAD_AffectedUsers'];
        $DREADDiscoverability = $risk[0]['DREAD_Discoverability'];
        $OWASPSkillLevel = $risk[0]['OWASP_SkillLevel'];
        $OWASPMotive = $risk[0]['OWASP_Motive'];
        $OWASPOpportunity = $risk[0]['OWASP_Opportunity'];
        $OWASPSize = $risk[0]['OWASP_Size'];
        $OWASPEaseOfDiscovery = $risk[0]['OWASP_EaseOfDiscovery'];
        $OWASPEaseOfExploit = $risk[0]['OWASP_EaseOfExploit'];
        $OWASPAwareness = $risk[0]['OWASP_Awareness'];
        $OWASPIntrusionDetection = $risk[0]['OWASP_IntrusionDetection'];
        $OWASPLossOfConfidentiality = $risk[0]['OWASP_LossOfConfidentiality'];
        $OWASPLossOfIntegrity = $risk[0]['OWASP_LossOfIntegrity'];
        $OWASPLossOfAvailability = $risk[0]['OWASP_LossOfAvailability'];
        $OWASPLossOfAccountability = $risk[0]['OWASP_LossOfAccountability'];
        $OWASPFinancialDamage = $risk[0]['OWASP_FinancialDamage'];
        $OWASPReputationDamage = $risk[0]['OWASP_ReputationDamage'];
        $OWASPNonCompliance = $risk[0]['OWASP_NonCompliance'];
        $OWASPPrivacyViolation = $risk[0]['OWASP_PrivacyViolation'];
        $custom = $risk[0]['Custom'];
        $risk_catalog_mapping = $risk[0]['risk_catalog_mapping'];
        $threat_catalog_mapping = $risk[0]['threat_catalog_mapping'];
        $template_group_id  = $risk[0]['template_group_id'];

        $ContributingLikelihood = $risk[0]['Contributing_Likelihood'];
        $contributing_risks_impacts = $risk[0]['Contributing_Risks_Impacts'];
        if($contributing_risks_impacts){
            $ContributingImpacts = get_contributing_impacts_by_subjectimpact_values($contributing_risks_impacts);
        }else{
            $ContributingImpacts = [];
        }
        $display_risk = true;

        $submission_date = format_date($submission_date, "N/A");

        // Get the mitigation for the risk
        $mitigation = get_mitigation_by_id($id);

        // If a mitigation exists for the risk and the user is allowed to access
        if ($mitigation == true && $access)
        {
            // Set the mitigation values
            $mitigation_id = $mitigation[0]['mitigation_id'];
            $mitigation_date = $mitigation[0]['submission_date'];
            $mitigation_date = format_date($mitigation_date);
            $planning_strategy = $mitigation[0]['planning_strategy'];
            $mitigation_effort = $mitigation[0]['mitigation_effort'];
            $mitigation_cost = $mitigation[0]['mitigation_cost'];
            $mitigation_owner = $mitigation[0]['mitigation_owner'];
            $mitigation_team = $mitigation[0]['mitigation_team'];
            $current_solution = $mitigation[0]['current_solution'];
            $security_requirements = $mitigation[0]['security_requirements'];
            $security_recommendations = $mitigation[0]['security_recommendations'];
            $planning_date = format_date($mitigation[0]['planning_date']);
            $mitigation_percent = (isset($mitigation[0]['mitigation_percent']) && $mitigation[0]['mitigation_percent'] >= 0 && $mitigation[0]['mitigation_percent'] <= 100) ? $mitigation[0]['mitigation_percent'] : 0;
            $mitigation_controls = isset($mitigation[0]['mitigation_controls']) ? $mitigation[0]['mitigation_controls'] : "";
        }
        // Otherwise
        else
        {
            // Set the values to empty
            $mitigation_id = "";
            $mitigation_date = "N/A";
            $mitigation_date = "";
            $planning_strategy = "";
            $mitigation_effort = "";
            $mitigation_cost = 1;
            $mitigation_owner = $owner;
            $mitigation_team = $team;
            $current_solution = "";
            $security_requirements = "";
            $security_recommendations = "";
            $planning_date = "";
            $mitigation_percent = 0;
            $mitigation_controls = "";
        }

        // Get the management reviews for the risk
        $mgmt_reviews = get_review_by_id($id);
        // If a mitigation exists for this risk and the user is allowed to access
        if ($mgmt_reviews && $access)
        {
            // Set the mitigation values
            $review_date = $mgmt_reviews[0]['submission_date'];
            $review_date = date(get_default_datetime_format("g:i A T"), strtotime($review_date));

            $review = $mgmt_reviews[0]['review'];
            $review_id = $mgmt_reviews[0]['id'];
            $next_step = $mgmt_reviews[0]['next_step'];

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
            {
                $next_review = next_review($residual_risk_level, $id-1000, $next_review, false);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $next_review = next_review($risk_level, $id-1000, $next_review, false);
            }

            $reviewer = $mgmt_reviews[0]['reviewer'];
            $comments = $mgmt_reviews[0]['comments'];
        }
        else
        // Otherwise
        {
            // Set the values to empty
            $review_date = "N/A";
            $review = "";
            $review_id = "";
            $next_step = "";
            $next_review = "";
            $reviewer = "";
            $comments = "";
        }
    }
    // If the risk was not found use null values
    else
    {
        $subject = "N/A";
        $display_risk = false;
    }
} 
// if ID is not set
else
{
    $id = "";
    $subject = "N/A";
    $display_risk = false;
}

	// Record the page the workflow started from as a session variable
	$_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME'];
?>

<!doctype html>
<html>

    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

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
        <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/cve_lookup.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/basescript.js?<?php echo current_version("app"); ?>"></script>

    <?php
        // Use these HighCharts scripts
        $scripts = [
                'highcharts.js',
        ];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

    ?>

        <script src="../vendor/moment/moment/min/moment.min.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/pages/risk.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>

        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
        <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>

        <script type="text/javascript">
            function showScoreDetails() {
                document.getElementById("scoredetails").style.display = "";
                document.getElementById("hide").style.display = "block";
                document.getElementById("show").style.display = "none";
            }

            function hideScoreDetails() {
                document.getElementById("scoredetails").style.display = "none";
                document.getElementById("updatescore").style.display = "none";
                document.getElementById("hide").style.display = "none";
                document.getElementById("show").style.display = "";
            }

            function updateScore() {
                document.getElementById("scoredetails").style.display = "none";
                document.getElementById("updatescore").style.display = "";
                document.getElementById("show").style.display = "none";
            }
          
        </script>
        <style>
            .risk-details .row-fluid{max-width: 1400px;}
            .top-panel .span2, .bottom-panel .span2{max-width: 210px;}
            .top-panel .span8, .bottom-panel .span8{margin-left:15px;}
            @media only screen and (min-width: 768px) {
                .top-panel .span2.text-right, .bottom-panel .span2.text-right{margin-left:10px;}
            }
            @media only screen and (min-width: 1400px) {
                .top-panel .span2.text-right, .bottom-panel .span2.text-right{margin-left:22px;}
            }
        </style>
        
      <?php
          setup_favicon("..");
          setup_alert_requirements("..");
      ?>    
    </head>

    <body>

      <?php
        view_top_menu("RiskManagement");
        // Get any alert messages
        get_alert();
        $active = isset($_GET['active']) ? $_GET['active'] : "ReviewRisksRegularly";
        switch($active){
            default:
            case "ReviewRisksRegularly":
                $list_href = "review_risks.php";
                break;
            case "PerformManagementReviews":
                $list_href = "management_review.php";
                break;
            case "PlanYourMitigations":
                $list_href = "plan_mitigations.php";
                break;
        }
      ?>
      <div class="tabs new-tabs">
        <div class="container-fluid">
          <div class="row-fluid">
            <div class="span3"> </div>
            <div class="span9">
              <div class="tab-append">
                <div class="tab form-tab" id="risk_list"><div><span><a href="<?php echo $list_href;?>">Risk list</a></span></div>
                </div>
                <div class="tab selected form-tab" id="risk_detail"><div><span><strong>ID: <?php echo $id.'</strong>  '.$escaper->escapeHtml(try_decrypt($subject)); ?></span></div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
      <div class="container-fluid">
        <div class="row-fluid">
          <div class="span3">
            <?php 
                view_risk_management_menu($active); 
            ?>
          </div>
          <div class="span9">

            <div class="row-fluid" id="tab-content-container">
                <div class='tab-data hide'></div>
                <div class='tab-data'>
                    <?php
                        
                        $action = isset($_GET['action']) ? $_GET['action'] : "";
                        if($display_risk == true) include(realpath(__DIR__ . '/partials/viewhtml.php'));
                        else echo $lang["RiskIdDoesNotExist"];
                    ?>
                </div>
            </div>
            
          </div>
        </div>
      </div>
        <input type="hidden" id="enable_popup" value="<?php echo $escaper->escapeHtml(get_setting('enable_popup')); ?>">
          <script>
            /*
            * Function to add the css class for textarea title and make it popup.
            * Example usage:
            * focus_add_css_class("#foo", "#bar");
            */
            function focus_add_css_class(id_of_text_head, text_area_id){
                // If enable_popup setting is false, disable popup
                if($("#enable_popup").val() != 1){
                    $("textarea").removeClass("enable-popup");
                    return;
                }else{
                    $("textarea").addClass("enable-popup");
                }
                
                look_for = "textarea" + text_area_id;
                if( !$(look_for).length ){
                    text_area_id = text_area_id.replace('#','');
                    look_for = "textarea[name=" + text_area_id;
                }
                $(look_for).focusin(function() {
                    $(id_of_text_head).addClass("affected-assets-title");
                    $('.ui-autocomplete').addClass("popup-ui-complete")
                });
                $(look_for).focusout(function() {
                    $(id_of_text_head).removeClass("affected-assets-title");
                    $('.ui-autocomplete').removeClass("popup-ui-complete")
                });
            }
            $(document).ready(function() {
                focus_add_css_class("#RiskAssessmentTitle", "#assessment");
                focus_add_css_class("#NotesTitle", "#notes");
                focus_add_css_class("#SecurityRequirementsTitle", "#security_requirements");
                focus_add_css_class("#CurrentSolutionTitle", "#current_solution");
                focus_add_css_class("#SecurityRecommendationsTitle", "#security_recommendations");
                
                setupAssetsAssetGroupsViewWidget($('select.assets-asset-groups-select-disabled'));
                
                /**
                * Change Event of Risk Scoring Method
                * 
                */
                $('body').on('change', '[name=scoring_method]', function(e){
                    e.preventDefault();
                    var formContainer = $(this).parents('form');
                    handleSelection($(this).val(), formContainer);
                })
                
                /**
                * events in clicking soring button of edit details page, muti tabs case
                */
                $('body').on('click', '[name=cvssSubmit]', function(e){
                    e.preventDefault();
                    var form = $(this).parents('form');
                    popupcvss(form);
                })
                
            });
        </script>
    </body>

    <script type="text/javascript">

        $( function() {
           
            $("#comment-submit").attr('disabled','disabled');
            $("#cancel_disable").attr('disabled','disabled');
            $("#rest-btn").attr('disabled','disabled');
            $("#comment-text").click(function(){
                $("#comment-submit").removeAttr('disabled');
                $("#rest-btn").removeAttr('disabled');
            });

            $("#comment-submit").click(function(){
                var submitbutton = document.getElementById("comment-text").value;
                if(submitbutton == ''){
                    $("#comment-submit").attr('disabled','disabled');
                    $("#rest-btn").attr('disabled','disabled');
                }
            });
            $("#rest-btn").click(function(){
                $("#comment-submit").attr('disabled','disabled');
            });
           
            $(".active-textfield").click(function(){
                $("#cancel_disable").removeAttr('disabled');
            });
                
           $("select").change(function changeOption(){
                $("#cancel_disable").removeAttr('disabled');
           });
                 

            $("#tabs").tabs({ active: 0});
            <?php if (isset($_POST['edit_mitigation'])): ?>
            $("#tabs").tabs({ active: 1});

            <?php elseif (!isset($_POST['tab_type']) && (isset($_POST['edit_details']) ||(isset($_GET['type']) && $_GET['type']) =='0')): ?>
            // $("#tabs").tabs({ active: 0});

            <?php elseif ((isset($_POST['tab_type']) || isset($_GET['tab_type'])) || isset($_GET['type']) && $_GET['type']=='1'): ?>
            $("#tabs").tabs({ active: 1});

            <?php else: ?>
            $("#tabs").tabs({ active: 2});
            <?php endif; ?>

            $('.collapsible').hide();


            $("#tabs" ).tabs({
                activate:function(event,ui){
                    if(ui.newPanel.selector== "#tabs1"){
                        $("#tab_details").addClass("tabList");
                        $("#tab_mitigation").removeClass("tabList");
                        $("#tab_review").removeClass("tabList");
                    } else if(ui.newPanel.selector== "#tabs2"){
                        $("#tab_mitigation").addClass("tabList");
                        $("#tab_review").removeClass("tabList");
                        $("#tab_details").removeClass("tabList");
                    }else{
                        $("#tab_review").addClass("tabList");
                        $("#tab_mitigation").removeClass("tabList");
                        $("#tab_details").removeClass("tabList");

                    }

                }
            });
    //      $("#tabs" ).removeClass('ui-tabs')

        });


    </script>
    <?php display_set_default_date_format_script(); ?>
</html>
