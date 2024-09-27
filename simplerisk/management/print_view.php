<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

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
	// Ignoring detections related to language files
	// @phan-suppress-next-line SecurityCheck-PathTraversal
	require_once(language_file());

	// Set a global variable for the current app version, so we don't have to call a function every time
	$current_app_version = current_version("app");

    // Check if a risk ID was sent
    if (isset($_GET['id'])) {

        // Test that the ID is a numeric value
        $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

        // If team separation is enabled
        if (team_separation_extra()) {

            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // If the user should not have access to the risk
            if (!extra_grant_access($_SESSION['uid'], $id)) {

                // Redirect back to the page the workflow started on
                header("Location: " . $_SESSION["workflow_start"]);
                exit(0);
            }
        }

        // Get the details of the risk
        $risk = get_risk_by_id($id);

        // If the risk was found use the values for the risk
        if (count($risk) != 0) {

            $submitted_by = $risk[0]['submitted_by'];
            $status = $risk[0]['status'];
            $subject = $risk[0]['subject'];
            $reference_id = $risk[0]['reference_id'];
            $regulation = $risk[0]['regulation'];
            $control_number = $risk[0]['control_number'];
            $location = $risk[0]['location_names'];
            $source = $risk[0]['source'];
            $category = $risk[0]['category'];
            $team = $risk[0]['team_names'];
            $additional_stakeholders = $risk[0]['additional_stakeholder_names'];
            $technology = $risk[0]['technology_names'];
            $owner = $risk[0]['owner'];
            $manager = $risk[0]['manager'];
            $assessment = $risk[0]['assessment'];
            $notes = $risk[0]['notes'];
            $submission_date = $risk[0]['submission_date'];
            $tags = $risk[0]['risk_tags'];
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
		
		// If the risk was not found use null values
        } else {

            $submitted_by = "";

            // If Risk ID exists.
            if(check_risk_by_id($id)) {

                $status = $lang["RiskDisplayPermission"];
			
			// If Risk ID does not exist.
            } else {

                $status = $lang["RiskIdDoesNotExist"];

            }

            $subject = "N/A";
            $reference_id = "N/A";
            $regulation = "";
            $control_number = "N/A";
            $location = "";
            $source = "";
            $category = "";
            $team = "";
            $additional_stakeholders = "";
            $technology = "";
            $owner = "";
            $manager = "";
            $assessment = "";
            $notes = "";
            $submission_date = "";
            $tags = "";

            $mitigation_id = "";
            $mgmt_review = "";
            $calculated_risk = "0.0";

            $residual_risk = "";
            $next_review = "";
            $color = "";
            $residual_color = "";

            $risk_level = "";
            $residual_risk_level = "";
            $scoring_method = "";
            $CLASSIC_likelihood = "";
            $CLASSIC_impact = "";
            $AccessVector = "";
            $AccessComplexity = "";
            $Authentication = "";

            $ConfImpact = "";
            $IntegImpact = "";
            $AvailImpact = "";
            $Exploitability = "";
            $RemediationLevel = "";
            $ReportConfidence = "";
            $CollateralDamagePotential = "";
            $TargetDistribution = "";
            $ConfidentialityRequirement = "";
            $IntegrityRequirement = "";
            $AvailabilityRequirement = "";
            $DREADDamagePotential = "";
            $DREADReproducibility = "";
            $DREADExploitability = "";
            $DREADAffectedUsers = "";
            $DREADDiscoverability = "";
            $OWASPSkillLevel = "";
            $OWASPMotive = "";
            $OWASPOpportunity = "";
            $OWASPSize = "";
            $OWASPEaseOfDiscovery = "";
            $OWASPEaseOfExploit = "";
            $OWASPAwareness = "";
            $OWASPIntrusionDetection = "";
            $OWASPLossOfConfidentiality = "";
            $OWASPLossOfIntegrity = "";
            $OWASPLossOfAvailability = "";
            $OWASPLossOfAccountability = "";
            $OWASPFinancialDamage = "";
            $OWASPReputationDamage = "";
            $OWASPNonCompliance = "";
            $OWASPPrivacyViolation = "";
            $custom = "";
            $risk_catalog_mapping = "";
            $threat_catalog_mapping = "";
            $template_group_id  = "";
        }


        if ($submission_date == "") {
            $submission_date = "N/A";
        } else {
			$submission_date = date(get_default_datetime_format("g:i A T"), strtotime($submission_date));
		}

        // Get the mitigation for the risk
        $mitigation = get_mitigation_by_id($id);

        // If no mitigation exists for this risk
        if ($mitigation == false) {

            // Set the values to empty
            $mitigation_date = "N/A";
            $mitigation_date = "";
            $planning_strategy = "";
            $mitigation_effort = "";
            $mitigation_cost = 1;
            $mitigation_owner = 0;
            $mitigation_team = 0;
            $mitigation_percent = 0;
            $current_solution = "";
            $security_requirements = "";
            $security_recommendations = "";
            $planning_date = "";
            $mitigation_percent = "";
		
		// If a mitigation exists
        } else {

            // Set the mitigation values
            $mitigation_date = $mitigation[0]['submission_date'];
            $mitigation_date = date(get_default_datetime_format("g:i A T"), strtotime($mitigation_date));
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
        }

        // Get the management reviews for the risk
        $mgmt_reviews = get_review_by_id($id);

        // If no mitigation exists for this risk
        if ($mgmt_reviews == false) {

            // Set the values to empty
            $review_date = "N/A";
            $review = "";
            $review_id = "";
            $next_step = "";
            $reviewer = "";
            $comments = "";
			
		// If a mitigation exists
        } else {

            // Set the mitigation values
            $review_date = $mgmt_reviews[0]['submission_date'];
            $review_date = date(get_default_datetime_format("g:i A T"), strtotime($review_date));
            $review = $mgmt_reviews[0]['review'];
            $review_id = $mgmt_reviews[0]['id'];
            $next_step = $mgmt_reviews[0]['next_step'];

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk") {

                $next_review = next_review($residual_risk_level, $id-1000, $next_review, false);
			
			// If next_review_date_uses setting is Inherent Risk.
            } else {

                $next_review = next_review($risk_level, $id-1000, $next_review, false);
            
			}
            
            $reviewer = $mgmt_reviews[0]['reviewer'];
            $comments = $mgmt_reviews[0]['comments'];
        }
    }
?>

<!doctype html>
<html>
	<head>
		<title>SimpleRisk: Enterprise Risk Management Simplified</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
		
		<!-- Favicon icon -->
		<?php setup_favicon("..");?>
        
		<!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>" />
        
		<!-- extra css -->
		<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

		<!-- jQuery Javascript -->
		<script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>

		<!-- Bootstrap tether Core JavaScript -->
		<script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

		<script language="javascript" src="../js/basescript.js?<?= $current_app_version ?>" type="text/javascript" defer></script>
	</head>
	<body>
		<div class="preloader">
            <div class="lds-ripple">
                <div class="lds-pos"></div>
                <div class="lds-pos"></div>
            </div>
        </div>
		<div id="main-wrapper">
            <!-- Page wrapper  -->
            <div class="page-wrapper" style="top: 0px;">
            	<div class="scroll-content">
            		<div class="content-wrapper">
						<div class='page-breadcrumb'>
							<div class='row'>
								<div class='col-12 d-flex no-block align-items-center'>
									<h4 class='page-title'>
										<?= $escaper->escapeHtml($lang['RiskDetails']) ?>
									</h4>
								</div>
							</div>
						</div>
						<!-- container - It's the direct container of all the -->
						<div class='content container-fluid'>
							<div class='row'>
								<div class='col-12'>
									<div class='card-body border my-2'>
	<?php 
										view_print_top_table($id, $calculated_risk, $subject, $status, true); 
	?>
									</div>
									<div class='card-body border my-2'>
	<?php 
										view_print_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $additional_stakeholders, $owner, $manager, $assessment, $notes, $tags, $submitted_by, $source, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id); 
	?>
									</div>
									<div class='card-body border my-2'>
	<?php 
										view_print_mitigation_details($id, $mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations, $planning_date, $mitigation_cost, $mitigation_owner, $mitigation_team, $mitigation_percent, $template_group_id); 
	?>
									</div>
									<div class='mitigation-controls-container card-body border my-2'>
	<?php 
										view_print_mitigation_controls($mitigation); 
	?>
									</div>
									<div class='card-body border my-2'>
	<?php 
										view_print_review_details($id, $review_id, $review_date, $reviewer, $review, $next_step, $next_review, $comments, $template_group_id); 
	?>
									</div>
									<div class='comments-container card-body border my-2'>
										<h4><?= $lang['Comments']; ?></h4>
										<?php get_comments($id); ?>
									</div>
									<div class='audit-trail-container card-body border my-2'>
										<h4><?= $lang['AuditTrail']; ?></h4>
										<?php get_audit_trail_html($id,36500,'risk'); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
                	<!-- End of content-wrapper -->
        		</div>
        		<!-- End of scroll-content -->
          	</div>
          <!-- End Page wrapper  -->
        </div>
        <!-- End Wrapper -->
		<script>
			$(function() {
				// Fading out the preloader once everything is done rendering
				$(".preloader").fadeOut();
			});
		</script>
	</body>
</html>