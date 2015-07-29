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

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if a risk ID was sent
        if (isset($_GET['id']))
        {
                $id = (int)$_GET['id'];

        	// If team separation is enabled
        	if (team_separation_extra())
        	{
                	//Include the team separation extra
                	require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

			// If the user should not have access to the risk
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
			$next_review = $risk[0]['next_review'];
			$color = get_risk_color($id);

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

		// If the current scoring method was changed to Classic
		if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 1)
		{
			// Set the new scoring method
			$scoring_method = change_scoring_method($id, "1");

			// Update the classic score
			$calculated_risk = update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to Classic.";
		}
                // If the current scoring method was changed to CVSS
                else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 2)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "2");

			// Update the cvss score
			$calculated_risk = update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to CVSS.";
                }
                // If the current scoring method was changed to DREAD
                else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 3)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "3");

			// Update the dread score
			$calculated_risk = update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to DREAD.";
                }
                // If the current scoring method was changed to OWASP
                else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 4)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "4");

			// Update the owasp score
			$calculated_risk = update_owasp_score($id, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to OWASP.";
                }
                // If the current scoring method was changed to Custom
                else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 5)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "5");

			// Update the custom score
			$calculated_risk = update_custom_score($id, $custom);

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to Custom.";
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
			$mitigation_date = "N/A";
			$mitigation_date = "";
			$planning_strategy = "";
			$mitigation_effort = "";
			$mitigation_team = $team;
			$current_solution = "";
			$security_requirements = "";
			$security_recommendations = "";
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

                // If no mitigation exists for this risk
                if ($mgmt_reviews == false)
                {
                        // Set the values to empty
			$review_date = "N/A";
			$review = "";
			$next_step = "";
			$next_review = "";
			$reviewer = "";
			$comments = "";
                }
                // If a mitigation exists
                else
                {
                        // Set the mitigation values
			$review_date = $mgmt_reviews[0]['submission_date'];
			$review_date = date(DATETIME, strtotime($review_date));
			$review = $mgmt_reviews[0]['review'];
			$next_step = $mgmt_reviews[0]['next_step'];
			$next_review = next_review($color, $id, $next_review, false);
			$reviewer = $mgmt_reviews[0]['reviewer'];
			$comments = $mgmt_reviews[0]['comments'];
		}
        }

	// If the risk details were updated
        if (isset($_POST['update_details']))
        {
		// If the user has permission to modify risks
		if (isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
		{
                	$subject = $_POST['subject'];
			$reference_id = $_POST['reference_id'];
			$regulation = (int)$_POST['regulation'];
			$control_number = $_POST['control_number'];
			$location = (int)$_POST['location'];
                	$category = (int)$_POST['category'];
                	$team = (int)$_POST['team'];
                	$technology = (int)$_POST['technology'];
                	$owner = (int)$_POST['owner'];
                	$manager = (int)$_POST['manager'];
                	$assessment = $_POST['assessment'];
                	$notes = $_POST['notes'];
			$assets = $_POST['assets'];

			// Update risk
			update_risk($id, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);

			// Tag the assets to the risk id
			tag_assets_to_risk($id-1000, $assets);

			// If the user checked the delete button
			if (isset($_POST['delete']) && $_POST['delete'] == "YES")
			{
				// Delete the file
				$error = delete_file($id-1000);
			}
			// Otherwise
			else
			{
                		// Upload any file that is submitted
                		$error = upload_file($id-1000, $_FILES['file']);
			}

                	// Audit log
                	$risk_id = $id;
                	$message = "Risk details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                	write_log($risk_id, $_SESSION['uid'], $message);

			if ($error == 1)
			{
				$alert = "good";
				$alert_message = "The risk has been successfully modified.";
			}
			else
			{
				$alert = "bad";
				$alert_message = $error;
			}
		}
		// Otherwise, the user did not have permission to modify risks
		else
		{
			$alert = "bad";
                	$alert_message = "You do not have permission to modify risks.  Your attempt to modify the details of this risk was not recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
		}
        }

	// If the user has selected to edit the risk details and does not have permission
	if ((isset($_POST['edit_details'])) && ($_SESSION['modify_risks'] != 1))
	{
        	$alert = "bad";
                $alert_message = "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
	}

	// Check if a mitigation was updated
	if (isset($_POST['update_mitigation']))
	{
                $planning_strategy = (int)$_POST['planning_strategy'];
		$mitigation_effort = (int)$_POST['mitigation_effort'];
		$mitigation_team = (int)$_POST['mitigation_team'];
                $current_solution = $_POST['current_solution'];
                $security_requirements = $_POST['security_requirements'];
                $security_recommendations = $_POST['security_recommendations'];

		// If we don't yet have a mitigation
		if ($mitigation_id == 0)
		{
	                $status = "Mitigation Planned";

                	// Submit mitigation and get the mitigation date back
                	$mitigation_date = submit_mitigation($id, $status, $planning_strategy, $mitigation_effort, $mitigation_team, $current_solution, $security_requirements, $security_recommendations);
			$mitigation_date = date(DATETIME, strtotime($mitigation_date));
		}
		else
		{
			// Update mitigation and get the mitigation date back
			$mitigation_date = update_mitigation($id, $planning_strategy, $mitigation_effort, $mitigation_team, $current_solution, $security_requirements, $security_recommendations);
			$mitigation_date = date(DATETIME, strtotime($mitigation_date));
		}

                // Audit log
                $risk_id = $id;
                $message = "Risk mitigation details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                write_log($risk_id, $_SESSION['uid'], $message);

		$alert = "good";
		$alert_message = "The risk mitigation has been successfully modified.";
	}

	// If comment is passed via GET
	if (isset($_GET['comment']))
	{
		// If it's true
		if ($_GET['comment'] == true)
		{
			$alert = "good";
			$alert_message = "Your comment has been successfully added to the risk.";
		}
	}

        // If closed is passed via GET
        if (isset($_GET['closed']))
        {       
                // If it's true
                if ($_GET['closed'] == true)
                {
                        $alert = "good";
                        $alert_message = "Your risk has now been marked as closed.";
                }
        }

        // If reopened is passed via GET
        if (isset($_GET['reopened']))
        {       
                // If it's true
                if ($_GET['reopened'] == true)
                {       
                        $alert = "good"; 
                        $alert_message = "Your risk has now been reopened.";      
                }
        }
?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script language="javascript" src="../js/basescript.js" type="text/javascript"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">
    <link rel="stylesheet" href="../css/jquery-ui.min.css">
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
    </script>
    <?php display_asset_autocomplete_script(get_entered_assets()); ?>
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">

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
          <?php view_risk_management_menu("ReviewRisksRegularly"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="well">
              <?php view_top_table($id, $calculated_risk, $subject, $status, true); ?>
            </div>
          </div>
          <div class="row-fluid">
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
          </div>
          <div class="row-fluid">
            <div class="span4">
              <div class="well">
                <form name="details" method="post" action="" enctype="multipart/form-data">
		<?php
			// If the user has selected to edit the risk
			if (isset($_POST['edit_details']))
			{
				edit_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $assessment, $notes);
			}
			// Otherwise we are just viewing the risk
			else
			{
				view_risk_details($id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);
			}
		?>
                </form>
              </div>
            </div>
            <div class="span4">
              <div class="well">
                <form name="mitigation" method="post" action="">
		<?php
			// If the user has selected to edit the mitigation
			if (isset($_POST['edit_mitigation']))
			{ 
				edit_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_team, $current_solution, $security_requirements, $security_recommendations);
			}
			// Otherwise we are just viewing the mitigation
			else
			{
				view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $mitigation_team, $current_solution, $security_requirements, $security_recommendations);
			}
		?>
                </form>
              </div>
            </div>
            <div class="span4">
              <div class="well">
                <form name="review" method="post" action="">
		<?php
			view_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments);
		?>
                </form>
              </div>
            </div>
            </form>
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
