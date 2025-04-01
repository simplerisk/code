<?php
// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1") {
    header("Location: ../../index.php");
    exit(0);
}

// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/artificial_intelligence.php'));

?>
<div class="risk-details mt-2">
    <?php
    // Display the AI Extra icon
    display_artificial_intelligence_icon("risk_tabs", $id);
    ?>
    <nav class="nav nav-tabs">
        <a id="tab_details" data-bs-target="#details" class="nav-link active" data-bs-toggle="tab"><?php echo $escaper->escapeHtml($lang['Details']); ?></a>
        <a id="tab_mitigation" data-bs-target="#mitigation" class="nav-link" data-bs-toggle="tab"><?php echo $escaper->escapeHtml($lang['Mitigation']); ?></a>
        <a id="tab_review" data-bs-target="#review" class="nav-link" data-bs-toggle="tab"><?php echo $escaper->escapeHtml($lang['Review']); ?></a>
    </nav>
    <div class="tab-content position-relative card-body my-2 border">

        <div id="details" class="tab-pane active clearfix">
            <form name="details" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" class="risk_id" value="<?php echo $escaper->escapeHtml($id); ?>">

    <?php if(@$isAjax && has_permission("modify_risks") && (!isset($action) || $action != 'editdetail')): ?>
                <!-- Edit th risk details-->
                <div class="tabs--action position-absolute top-0 end-0">
                    <button type="button" name="edit_details" class="btn btn-dark on-view"><?php echo $escaper->escapeHtml($lang['EditDetails']); ?></button>
                </div>
    <?php endif; ?>

    <?php
        // If the user has selected to edit the risk and has permission to edit the risk
        if ((isset($_POST['edit_details']) || (isset($action) && $action == 'editdetail')) && has_permission("modify_risks")) {
                edit_risk_details($id, $submission_date,$submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes,  $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts, $risk_tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id);
        // Otherwise we are just viewing the risk
        } else {
                view_risk_details($id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $additional_stakeholders, $technology, $owner, $manager, $assessment, $notes,  $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $risk_tags, $jira_issue_key, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id);
        }
    ?>
            </form>
        </div>

        <div id="mitigation" class="tab-pane">
            <form name="mitigation" method="post" action="" enctype="multipart/form-data">

    <?php if(@$isAjax && has_permission("plan_mitigations") && (!isset($action) || $action!="editmitigation")): ?>
                <!-- Edit mitigation -->
                <div class="tabs--action position-absolute top-0 end-0">
                    <button type="button" name="edit_mitigation" class="btn btn-dark"><?php echo $escaper->escapeHtml($lang['EditMitigation']); ?></button>
                </div>
    <?php endif; ?>
    
    <?php
        // If the user has selected to edit the mitigation and they have permission to edit the mitigation
        if ((isset($_POST['edit_mitigation']) || (isset($action) && $action == 'editmitigation')) && has_permission("plan_mitigations")) {
            edit_mitigation_details($id, $mitigation_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $template_group_id);

            // If it's not being included through an AJAX call we have to run the below logic to initialize the controls on the page
            // Conveniently the '$isAjax' variable isset in the API call before including this page
            // so we can track if it's loaded through the API or it's a regular page load
            if (!isset($isAjax) || !$isAjax) {
                ?>
                <script>
					// To be able to only run this for the active tab we have to get a reference of THIS script tag
					// which is why we're using this structure below
                    (function(scriptTag){

                    	// do whatever you want after the DOM is loaded here...
                    	$(function(){
                        	// Get the containing parent of this script tag
                            var tabContainer = $(scriptTag).parents('.tab-data');
                            // Run the logic that's initializing the elements of the included part
                            callbackAfterRefreshTab(tabContainer, 1);
                        });
                     })(document.currentScript);
                </script>
<?php
            }

            // Otherwise we are just viewing the mitigation
        } else {
            view_mitigation_details($id, $mitigation_id, $mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_percent, $mitigation_controls, $template_group_id);
        }
    ?>

            </form>
        </div>
        
        <div id="review" class="tab-pane">
<!--        <form name="review" method="post" action="">-->
    <?php
        // Check the review permissions for this risk id
        $edit = check_review_permission_by_risk_id($id);

        // If the user is trying to perform a review and the user has the right permission
        if (isset($action) && $action == 'editreview' && $edit) {
            $default_next_review = get_next_review_default($id-1000);
            edit_review_submission($id, $review_id, $review, $next_step, $next_review, $comments, $default_next_review, $template_group_id);

            // If it's not being included through an AJAX call we have to run the below logic to initialize the controls on the page
            // Conveniently the '$isAjax' variable isset in the API call before including this page
            // so we can track if it's loaded through the API or it's a regular page load 
            if (!isset($isAjax) || !$isAjax) {
?>
                <script>
					// To be able to only run this for the active tab we have to get a reference of THIS script tag
					// which is why we're using this structure below
                    (function(scriptTag){

                    	// do whatever you want after the DOM is loaded here...
                    	$(function(){
                        	// Get the containing parent of this script tag
                            var tabContainer = $(scriptTag).parents('.tab-data');
                            // Run the logic that's initializing the elements of the included part
                            callbackAfterRefreshTab(tabContainer, 2);
                        });
                     })(document.currentScript);
                </script>
<?php
            }
        } else {
            view_review_details($id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comments, $template_group_id);
        }
    ?>
<!--        </form>-->
        </div>
    </div>
</div>

<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
<input type="hidden" id="_lang_reopen_risk" value="<?php echo $escaper->escapeHtml($lang['ReopenRisk']); ?>">
<input type="hidden" id="_lang_close_risk" value="<?php echo $escaper->escapeHtml($lang['CloseRisk']); ?>">
<input type="hidden" id="_lang_accepting" value="<?php echo $escaper->escapeHtml($lang['Accepting']); ?>">
<input type="hidden" id="_lang_accept_mitigation" value="<?php echo $escaper->escapeHtml($lang['AcceptMitigation']); ?>">
<input type="hidden" id="_lang_rejecting" value="<?php echo $escaper->escapeHtml($lang['Rejecting']); ?>">
<input type="hidden" id="_lang_reject_mitigation" value="<?php echo $escaper->escapeHtml($lang['RejectMitigation']); ?>">