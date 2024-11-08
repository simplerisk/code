<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

$breadcrumb_title_key = "RiskDetails";
$active_sidebar_menu ="RiskManagement";
$active_sidebar_submenu = isset($_GET['active']) ? $_GET['active'] : "ReviewRisksRegularly";
render_header_and_sidebar(['blockUI', 'tabs:logic', 'selectize', 'datatables', 'chart.js', 'WYSIWYG', 'multiselect', 'CUSTOM:common.js', 'CUSTOM:pages/risk.js', 'CUSTOM:cve_lookup.js', 'datetimerangepicker', 'JSLocalization'], ['check_riskmanagement' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, required_localization_keys: ['MitigationPlanned']);

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

switch($active_sidebar_submenu){
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
<div class="row bg-white">
    <div class="col-12">
        <div class='tab-data hide'></div>
        <div class='tab-data'>
    <?php

        $action = isset($_GET['action']) ? $_GET['action'] : "";

        if($display_risk == true)  {
            include(realpath(__DIR__ . '/partials/viewhtml.php'));
        } else {
            echo "
            <div class='card-body my-2 border'><strong>" . $lang["RiskIdDoesNotExist"] . "</strong></div>
            ";
        }
        
    ?>
        </div>
    </div>
</div>
<script>

    function updateScore() {
        document.getElementById("scoredetails").style.display = "none";
        document.getElementById("updatescore").style.display = "";
        document.getElementById("show").style.display = "none";
    }

    $(document).ready(function() {
        
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
    
    $(document).click(function (event) {
        var $target = $(event.target);
        if (!$target.closest('.multiselect-native-select').find('.btn-group').length && $('.multiselect-native-select').find('.btn-group').hasClass("open")) {
              $('.multiselect-native-select').find('.btn-group').removeClass('open');
        }
    });
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

        $(".datepicker").initAsDatePicker();

        //render multiselects which are not rendered yet after the page is loaded.
        //multiselects which were already rendered contain 'button.multiselect'.
        $(".multiselect:not(button)").multiselect({enableFiltering: true, buttonWidth: '100%', enableCaseInsensitiveFiltering: true,});

    });
</script>
<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>