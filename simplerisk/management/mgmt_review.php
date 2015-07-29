<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
        require_once(realpath(__DIR__ . '/../includes/display.php'));

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
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRisk');

        // Include the language file
        require_once(language_file());

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

	// Default is no alert
	$alert = false;

	// Default is not approved
	$approved = false;

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if a risk ID was sent
        if (isset($_GET['id']) || isset($_POST['id']))
        {
                if (isset($_GET['id']))
                {
                        $id = (int)$_GET['id'];
                }
                else if (isset($_POST['id']))
                {
                        $id = (int)$_POST['id'];
                }

                // If team separation is enabled
                if (team_separation_extra())
                {
                        // Include the team separation extra
			require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

                        // If the user does not have access to the risk
                        if (!extra_grant_access($_SESSION['uid'], $id))
                        {
                                // Redirect back to the page the workflow started on
                                header("Location: " . $_SESSION["workflow_start"]);
                                exit(0);
                        }
                }

                // If the classic risk was updated and the user has the ability to modify the risk
                if (isset($_POST['update_classic']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
                {
                        $CLASSIC_likelihood = (int)$_POST['likelihood'];
                        $CLASSIC_impact = (int)$_POST['impact'];

                        // Update the risk scoring
                        update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);
                }
                // If the cvss risk was updated and the user has the ability to modify the risk
                else if (isset($_POST['update_cvss']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
                {
                        $AccessVector = $_POST['AccessVector'];
                        $AccessComplexity = $_POST['AccessComplexity'];
                        $Authentication = $_POST['Authentication'];
                        $ConfImpact = $_POST['ConfImpact'];
                        $IntegImpact = $_POST['IntegImpact'];
                        $AvailImpact = $_POST['AvailImpact'];
                        $Exploitability = $_POST['Exploitability'];
                        $RemediationLevel = $_POST['RemediationLevel'];
                        $ReportConfidence = $_POST['ReportConfidence'];
                        $CollateralDamagePotential = $_POST['CollateralDamagePotential'];
                        $TargetDistribution = $_POST['TargetDistribution'];
                        $ConfidentialityRequirement = $_POST['ConfidentialityRequirement'];
                        $IntegrityRequirement = $_POST['IntegrityRequirement'];
                        $AvailabilityRequirement = $_POST['AvailabilityRequirement'];

                        // Update the risk scoring
                        update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
                }
                // If the dread risk was updated and the user has the ability to modify the risk
                else if (isset($_POST['update_dread']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
                {
                        $DREADDamagePotential = (int)$_POST['DamagePotential'];
                        $DREADReproducibility = (int)$_POST['Reproducibility'];
                        $DREADExploitability = (int)$_POST['Exploitability'];
                        $DREADAffectedUsers = (int)$_POST['AffectedUsers'];
                        $DREADDiscoverability = (int)$_POST['Discoverability'];

                        // Update the risk scoring
                        update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
                }
                // If the owasp risk was updated and the user has the ability to modify the risk
                else if (isset($_POST['update_owasp']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
                {
                        $OWASPSkillLevel = (int)$_POST['SkillLevel'];
                        $OWASPMotive = (int)$_POST['Motive'];
                        $OWASPOpportunity = (int)$_POST['Opportunity'];
                        $OWASPSize = (int)$_POST['Size'];
                        $OWASPEaseOfDiscovery = (int)$_POST['EaseOfDiscovery'];
                        $OWASPEaseOfExploit = (int)$_POST['EaseOfExploit'];
                        $OWASPAwareness = (int)$_POST['Awareness'];
                        $OWASPIntrusionDetection = (int)$_POST['IntrusionDetection'];
                        $OWASPLossOfConfidentiality = (int)$_POST['LossOfConfidentiality'];
                        $OWASPLossOfIntegrity = (int)$_POST['LossOfIntegrity'];
                        $OWASPLossOfAvailability = (int)$_POST['LossOfAvailability'];
                        $OWASPLossOfAccountability = (int)$_POST['LossOfAccountability'];
                        $OWASPFinancialDamage = (int)$_POST['FinancialDamage'];
                        $OWASPReputationDamage = (int)$_POST['ReputationDamage'];
                        $OWASPNonCompliance = (int)$_POST['NonCompliance'];
                        $OWASPPrivacyViolation = (int)$_POST['PrivacyViolation'];

                        // Update the risk scoring
                        update_owasp_score($id, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
                }
                // If the custom risk was updated and the user has the ability to modify the risk
                else if (isset($_POST['update_custom']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
                {
                        $custom = (float)$_POST['Custom'];

                        // Update the risk scoring
                        update_custom_score($id, $custom);
                }

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
                	$category = $risk[0]['category'];
	                $team = $risk[0]['team'];
        	        $technology = $risk[0]['technology'];
                	$owner = $risk[0]['owner'];
	                $manager = $risk[0]['manager'];
        	        $assessment = $risk[0]['assessment'];
                	$notes = $risk[0]['notes'];
	                $submission_date = $risk[0]['submission_date'];
        	        $mitigation_id = $risk[0]['mitigation_id'];
                	$mgmt_review = $risk[0]['mgmt_review'];
	                $calculated_risk = $risk[0]['calculated_risk'];
			$risk_level = get_risk_level_name($calculated_risk);
			$next_review = next_review_by_score($calculated_risk);

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
		}
                // If the risk was not found use null values
                else
                {
			$submitted_by = "";
                        $status = "Risk ID Does Not Exist";
                        $subject = "N/A";
                        $reference_id = "N/A";
                        $regulation = "";
                        $control_number = "N/A";
                        $location = "";
                        $category = "";
                        $team = "";
                        $technology = "";
                        $owner = "";
                        $manager = "";
                        $assessment = "";
                        $notes = "";
                        $submission_date = "";
                        $mitigation_id = "";
                        $mgmt_review = "";
                        $calculated_risk = "0.0";
			$risk_level = "";
			$next_review = "";

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
                }

                if ($submission_date == "")
                {
                        $submission_date = "N/A";
                }
                else $submission_date = date(DATETIME, strtotime($submission_date));

                // Get the mitigation for the risk
                $mitigation = get_mitigation_by_id($id);

                // If no mitigation exists for this risk
                if ($mitigation == false)
                {
                        // Set the values to empty
                        $mitigation_date = "";
                        $planning_strategy = "";
                        $mitigation_effort = "";
			$mitigation_team = "";
                        $current_solution = "";
                        $security_requirements = "";
			$security_recommendations = "";
                        $mitigation_date = "N/A";
                }
                // If a mitigation exists
                else
                {
                        // Set the mitigation values
                        $mitigation_date = $mitigation[0]['submission_date'];
                        $mitigation_date = date(DATETIME, strtotime($mitigation_date));
                        $planning_strategy = $mitigation[0]['planning_strategy'];
                        $mitigation_effort = $mitigation[0]['mitigation_effort'];
			$mitigation_team = $mitigation[0]['mitigation_team'];
                        $current_solution = $mitigation[0]['current_solution'];
                        $security_requirements = $mitigation[0]['security_requirements'];
                        $security_recommendations = $mitigation[0]['security_recommendations'];
                }

               // Get the management reviews for the risk
                $mgmt_reviews = get_review_by_id($id);

                // If no management review exists for this risk
                if ($mgmt_reviews == false)
                {
                        // Set the values to empty
                        $review_date = "N/A";
                        $review = "";
                        $next_step = "";
                        $reviewer = "";
                        $comments = "";
                }
                // If a management review exists
                else
                {
                        // Set the management review values
                        $review_date = $mgmt_reviews[0]['submission_date'];
                        $review_date = date(DATETIME, strtotime($review_date));
                        $review = $mgmt_reviews[0]['review'];
                        $next_step = $mgmt_reviews[0]['next_step'];
                        $reviewer = $mgmt_reviews[0]['reviewer'];
                        $comments = $mgmt_reviews[0]['comments'];
                }

		// If the risk level is very high and they have permission
		if (($risk_level == "Very High") && ($_SESSION['review_veryhigh'] == 1))
		{
			// Review is approved
			$approved = true;
		}
		// If the risk level is high and they have permission
		else if (($risk_level == "High") && ($_SESSION['review_high'] == 1))
		{
			// Review is approved
			$approved = true;
		}
		// If the risk level is medium and they have permission
		else if (($risk_level == "Medium") && ($_SESSION['review_medium'] == 1))
		{
                        // Review is approved
                        $approved = true;
		}
		// If the risk level is low and they have permission
		else if (($risk_level == "Low") && ($_SESSION['review_low'] == 1))
		{
                        // Review is approved
                        $approved = true;
		}
                // If the risk level is insignificant and they have permission
                else if (($risk_level == "Insignificant") && ($_SESSION['review_insignificant'] == 1))
                {
                        // Review is approved
                        $approved = true;
                }
        }

	// If they are not approved to review the risk
	if (!($approved))
	{
		// There is an alert
		$alert = "bad";
		$alert_message = "You do not have permission to review " . $risk_level . " level risks.  Any reviews that you attempt to submit will not be recorded.  Please contact an administrator if you feel that you have reached this message in error.";
	}

        // Check if a new risk mitigation was submitted
        if (isset($_POST['submit']))
        {
		// If they are approved to review the risk
		if ($approved)
		{
                	$status = "Mgmt Reviewed";
                	$review = (int)$_POST['review'];
			$next_step = (int)$_POST['next_step'];
                	$reviewer = $_SESSION['uid'];
                	$comments = $_POST['comments'];
			$custom_date = $_POST['custom_date'];

			if ($custom_date == "yes")
			{
				$custom_review = $_POST['next_review'];

				// Check the date format
				if (!validate_date($custom_review, 'Y-m-d'))
				{
					$custom_review = "0000-00-00";
				}
			}
			else $custom_review = "0000-00-00";

                	// Submit review
                	submit_management_review($id, $status, $review, $next_step, $reviewer, $comments, $custom_review);

                	// Audit log
                	$risk_id = $id;
                	$message = "A management review was submitted for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                	write_log($risk_id, $_SESSION['uid'], $message);

			// If the reviewer rejected the risk
			if ($review == 2)
			{
                		$status = "Closed";
                		$close_reason = "The risk was rejected by the reviewer.";
                		$note = "Risk was closed automatically when the reviewer rejected the risk.";

                		// Close the risk
                		close_risk($risk_id, $_SESSION['uid'], $status, $close_reason, $note);

                		// Audit log
                		$message = "Risk ID \"" . $risk_id . "\" automatically closed when username \"" . $_SESSION['user'] . "\" rejected the risk.";
                		write_log($risk_id, $_SESSION['uid'], $message);
			}

                        // Redirect back to the page the workflow started on
                        header("Location: " . $_SESSION["workflow_start"] . "?reviewed=true");
		}
		// They do not have permissions to review the risk
		else
		{
                	// There is an alert
                	$alert = "bad";
                	$alert_message = "You do not have permission to review " . $risk_level . " level risks.  The review that you attempted to submit was not recorded.  Please contact an administrator if you feel that you have reached this message in error.";
		}
        }
?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script language="javascript" src="../js/basescript.js" type="text/javascript"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <link rel="stylesheet" href="../css/display.css">
    <script type="text/javascript">
      function showScoreDetails() {
        document.getElementById("scoredetails").style.display = "";
        document.getElementById("hide").style.display = "";
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
      function hideNextReview() {
        document.getElementById("nextreview").style.display = "none";
      }
      function showNextReview() {
        document.getElementById("nextreview").style.display = "";
      }
    </script>
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

<?php
	view_top_menu("RiskManagement");

        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_risk_management_menu("PerformManagementReviews"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="well">
              <?php view_top_table($id, $calculated_risk, $subject, $status, true); ?>
            </div>
            <div id="scoredetails" class="row-fluid" style="display: none;">
              <div class="well">
                  <?php
                        // Scoring method is Classic
                        if ($scoring_method == "1")
                        {
                                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact);
                        }
                        // Scoring method is CVSS
                        else if ($scoring_method == "2")
                        {
                                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
                        }
                        // Scoring method is DREAD
                        else if ($scoring_method == "3")
                        {
                                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
                        }
                        // Scoring method is OWASP
                        else if ($scoring_method == "4")
                        {
                                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation);
                        }
                        // Scoring method is Custom
                        else if ($scoring_method == "5")
                        {
                                custom_scoring_table($id, $custom);
                        }
                  ?>
              </div>
            </div>
            <div id="updatescore" class="row-fluid" style="display: none;">
              <div class="well">
                  <?php
                        // Scoring method is Classic
                        if ($scoring_method == "1")
                        {
                                edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact);
                        }
                        // Scoring method is CVSS
                        else if ($scoring_method == "2")
                        {
                                edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
                        }
                        // Scoring method is DREAD
                        else if ($scoring_method == "3")
                        {
                                edit_dread_score($DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
                        }
                        // Scoring method is OWASP
                        else if ($scoring_method == "4")
                        {
                                edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
                        }
                        // Scoring method is Custom
                        else if ($scoring_method == "5")
                        {
                                edit_custom_score($custom);
                        }
                  ?>
              </div>
            </div>
            <div class="well">
		<?php edit_review_submission($review, $next_step, $next_review, $comments); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span6">
              <div class="well">
		<?php view_risk_details($id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes); ?>
              </div>
            </div>
            <div class="span6">
              <div class="well">
		<?php view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_team, $current_solution, $security_requirements, $security_recommendations); ?>
              </div>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <h4><?php echo $lang['Comments']; ?></h4>
              <?php get_comments($id); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <h4><?php echo $lang['AuditTrail']; ?></h4>
              <?php get_audit_trail($id); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
