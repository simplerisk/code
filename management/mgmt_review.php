<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once('../includes/functions.php');
        require_once('../includes/authenticate.php');
        require_once('../includes/display.php');

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

        require_once('../includes/csrf-magic/csrf-magic.php');

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
                        $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8');
                }
                else if (isset($_POST['id']))
                {
                        $id = htmlentities($_POST['id'], ENT_QUOTES, 'UTF-8');
                }

                // If team separation is enabled
                if (team_separation_extra())
                {
                        // Include the team separation extra
			require_once(__DIR__ . "/../extras/separation/index.php");

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

                $status = htmlentities($risk[0]['status'], ENT_QUOTES, 'UTF-8');
                $subject = htmlentities(stripslashes($risk[0]['subject']), ENT_QUOTES, 'UTF-8');
                $reference_id = htmlentities(stripslashes($risk[0]['reference_id']), ENT_QUOTES, 'UTF-8');
		$regulation = htmlentities($risk[0]['regulation'], ENT_QUOTES, 'UTF-8');
		$control_number = htmlentities($risk[0]['control_number'], ENT_QUOTES, 'UTF-8');
                $location = htmlentities($risk[0]['location'], ENT_QUOTES, 'UTF-8');
                $category = htmlentities($risk[0]['category'], ENT_QUOTES, 'UTF-8');
                $team = htmlentities($risk[0]['team'], ENT_QUOTES, 'UTF-8');
                $technology = htmlentities($risk[0]['technology'], ENT_QUOTES, 'UTF-8');
                $owner = htmlentities($risk[0]['owner'], ENT_QUOTES, 'UTF-8');
                $manager = htmlentities($risk[0]['manager'], ENT_QUOTES, 'UTF-8');
                $assessment = htmlentities(stripslashes($risk[0]['assessment']), ENT_QUOTES, 'UTF-8');
                $notes = htmlentities(stripslashes($risk[0]['notes']), ENT_QUOTES, 'UTF-8');
                $submission_date = htmlentities($risk[0]['submission_date'], ENT_QUOTES, 'UTF-8');
                $mitigation_id = htmlentities($risk[0]['mitigation_id'], ENT_QUOTES, 'UTF-8');
                $mgmt_review = htmlentities($risk[0]['mgmt_review'], ENT_QUOTES, 'UTF-8');
                $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES, 'UTF-8');
		$risk_level = htmlentities(get_risk_level_name($calculated_risk), ENT_QUOTES, 'UTF-8');

                $scoring_method = htmlentities($risk[0]['scoring_method'], ENT_QUOTES, 'UTF-8');
                $CLASSIC_likelihood = htmlentities($risk[0]['CLASSIC_likelihood'], ENT_QUOTES, 'UTF-8');
                $CLASSIC_impact = htmlentities($risk[0]['CLASSIC_impact'], ENT_QUOTES, 'UTF-8');
                $AccessVector = htmlentities($risk[0]['CVSS_AccessVector'], ENT_QUOTES, 'UTF-8');
                $AccessComplexity = htmlentities($risk[0]['CVSS_AccessComplexity'], ENT_QUOTES, 'UTF-8');
                $Authentication = htmlentities($risk[0]['CVSS_Authentication'], ENT_QUOTES, 'UTF-8');
                $ConfImpact = htmlentities($risk[0]['CVSS_ConfImpact'], ENT_QUOTES, 'UTF-8');
                $IntegImpact = htmlentities($risk[0]['CVSS_IntegImpact'], ENT_QUOTES, 'UTF-8');
                $AvailImpact = htmlentities($risk[0]['CVSS_AvailImpact'], ENT_QUOTES, 'UTF-8');
                $Exploitability = htmlentities($risk[0]['CVSS_Exploitability'], ENT_QUOTES, 'UTF-8');
                $RemediationLevel = htmlentities($risk[0]['CVSS_RemediationLevel'], ENT_QUOTES, 'UTF-8');
                $ReportConfidence = htmlentities($risk[0]['CVSS_ReportConfidence'], ENT_QUOTES, 'UTF-8');
                $CollateralDamagePotential = htmlentities($risk[0]['CVSS_CollateralDamagePotential'], ENT_QUOTES, 'UTF-8');
                $TargetDistribution = htmlentities($risk[0]['CVSS_TargetDistribution'], ENT_QUOTES, 'UTF-8');
                $ConfidentialityRequirement = htmlentities($risk[0]['CVSS_ConfidentialityRequirement'], ENT_QUOTES, 'UTF-8');
                $IntegrityRequirement = htmlentities($risk[0]['CVSS_IntegrityRequirement'], ENT_QUOTES, 'UTF-8');
                $AvailabilityRequirement = htmlentities($risk[0]['CVSS_AvailabilityRequirement'], ENT_QUOTES, 'UTF-8');
                $DREADDamagePotential = htmlentities($risk[0]['DREAD_DamagePotential'], ENT_QUOTES, 'UTF-8');
                $DREADReproducibility = htmlentities($risk[0]['DREAD_Reproducibility'], ENT_QUOTES, 'UTF-8');
                $DREADExploitability = htmlentities($risk[0]['DREAD_Exploitability'], ENT_QUOTES, 'UTF-8');
                $DREADAffectedUsers = htmlentities($risk[0]['DREAD_AffectedUsers'], ENT_QUOTES, 'UTF-8');
                $DREADDiscoverability = htmlentities($risk[0]['DREAD_Discoverability'], ENT_QUOTES, 'UTF-8');
                $OWASPSkillLevel = htmlentities($risk[0]['OWASP_SkillLevel'], ENT_QUOTES, 'UTF-8');
                $OWASPMotive = htmlentities($risk[0]['OWASP_Motive'], ENT_QUOTES, 'UTF-8');
                $OWASPOpportunity = htmlentities($risk[0]['OWASP_Opportunity'], ENT_QUOTES, 'UTF-8');
                $OWASPSize = htmlentities($risk[0]['OWASP_Size'], ENT_QUOTES, 'UTF-8');
                $OWASPEaseOfDiscovery = htmlentities($risk[0]['OWASP_EaseOfDiscovery'], ENT_QUOTES, 'UTF-8');
                $OWASPEaseOfExploit = htmlentities($risk[0]['OWASP_EaseOfExploit'], ENT_QUOTES, 'UTF-8');
                $OWASPAwareness = htmlentities($risk[0]['OWASP_Awareness'], ENT_QUOTES, 'UTF-8');
                $OWASPIntrusionDetection = htmlentities($risk[0]['OWASP_IntrusionDetection'], ENT_QUOTES, 'UTF-8');
                $OWASPLossOfConfidentiality = htmlentities($risk[0]['OWASP_LossOfConfidentiality'], ENT_QUOTES, 'UTF-8');
                $OWASPLossOfIntegrity = htmlentities($risk[0]['OWASP_LossOfIntegrity'], ENT_QUOTES, 'UTF-8');
                $OWASPLossOfAvailability = htmlentities($risk[0]['OWASP_LossOfAvailability'], ENT_QUOTES, 'UTF-8');
                $OWASPLossOfAccountability = htmlentities($risk[0]['OWASP_LossOfAccountability'], ENT_QUOTES, 'UTF-8');
                $OWASPFinancialDamage = htmlentities($risk[0]['OWASP_FinancialDamage'], ENT_QUOTES, 'UTF-8');
                $OWASPReputationDamage = htmlentities($risk[0]['OWASP_ReputationDamage'], ENT_QUOTES, 'UTF-8');
                $OWASPNonCompliance = htmlentities($risk[0]['OWASP_NonCompliance'], ENT_QUOTES, 'UTF-8');
                $OWASPPrivacyViolation = htmlentities($risk[0]['OWASP_PrivacyViolation'], ENT_QUOTES, 'UTF-8');
                $custom = htmlentities($risk[0]['Custom'], ENT_QUOTES, 'UTF-8');

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
                        $current_solution = "";
                        $security_requirements = "";
			$security_recommendations = "";
                        $mitigation_date = "N/A";
                }
                // If a mitigation exists
                else
                {
                        // Set the mitigation values
                        $mitigation_date = htmlentities($mitigation[0]['submission_date'], ENT_QUOTES, 'UTF-8');
                        $mitigation_date = date(DATETIME, strtotime($mitigation_date));
                        $planning_strategy = htmlentities($mitigation[0]['planning_strategy'], ENT_QUOTES, 'UTF-8');
                        $mitigation_effort = htmlentities($mitigation[0]['mitigation_effort'], ENT_QUOTES, 'UTF-8');
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
                        $review_date = htmlentities($mgmt_reviews[0]['submission_date'], ENT_QUOTES, 'UTF-8');
                        $review_date = date(DATETIME, strtotime($review_date));
                        $review = htmlentities($mgmt_reviews[0]['review'], ENT_QUOTES, 'UTF-8');
                        $next_step = htmlentities($mgmt_reviews[0]['next_step'], ENT_QUOTES, 'UTF-8');
                        $reviewer = htmlentities($mgmt_reviews[0]['reviewer'], ENT_QUOTES, 'UTF-8');
                        $comments = $mgmt_reviews[0]['comments'];
                }

		// If the risk level is high and they have permission
		if (($risk_level == "High") && ($_SESSION['review_high'] == 1))
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
                	$review = (int)addslashes($_POST['review']);
			$next_step = (int)addslashes($_POST['next_step']);
                	$reviewer = $_SESSION['uid'];
                	$comments = addslashes($_POST['comments']);

                	// Submit review
                	submit_management_review($id, $status, $review, $next_step, $reviewer, $comments);

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
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="../index.php"><?php echo $lang['Home']; ?></a> 
              </li>
              <li class="active">
                <a href="index.php"><?php echo $lang['RiskManagement']; ?></a> 
              </li>
              <li>
                <a href="../reports/index.php"><?php echo $lang['Reporting']; ?></a> 
              </li>
<?php
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
{
          echo "<li>\n";
          echo "<a href=\"../admin/index.php\">". $lang['Configure'] ."</a>\n";
          echo "</li>\n";
}
          echo "</ul>\n";
          echo "</div>\n";

if (isset($_SESSION["access"]) && $_SESSION["access"] == "granted")
{
          echo "<div class=\"btn-group pull-right\">\n";
          echo "<a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">".$_SESSION['name']."<span class=\"caret\"></span></a>\n";
          echo "<ul class=\"dropdown-menu\">\n";
          echo "<li>\n";
          echo "<a href=\"../account/profile.php\">". $lang['MyProfile'] ."</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"../logout.php\">". $lang['Logout'] ."</a>\n";
          echo "</li>\n";
          echo "</ul>\n";
          echo "</div>\n";
}
?>
        </div>
      </div>
    </div>
<?php
        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $alert_message . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <ul class="nav  nav-pills nav-stacked">
            <li>
              <a href="index.php">I. <?php echo $lang['SubmitYourRisks']; ?></a> 
            </li>
            <li>
              <a href="plan_mitigations.php">II. <?php echo $lang['PlanYourMitigations']; ?></a> 
            </li>
            <li class="active">
              <a href="management_review.php">III. <?php echo $lang['PerformManagementReviews']; ?></a> 
            </li>
            <li>
              <a href="prioritize_planning.php">IV. <?php echo $lang['PrioritizeForProjectPlanning']; ?></a> 
            </li>
            <li>
              <a href="review_risks.php">V. <?php echo $lang['ReviewRisksRegularly']; ?></a>
            </li>
          </ul>
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
		<?php edit_review_submission($review, $next_step, $comments); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="span6">
              <div class="well">
		<?php view_risk_details($submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes); ?>
              </div>
            </div>
            <div class="span6">
              <div class="well">
		<?php view_mitigation_details($mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations); ?>
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
