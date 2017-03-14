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
		header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
        }

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
                header("Location: ../index.php");
                exit(0);
        }

        // Check if a risk ID was sent
        if (isset($_GET['id']))
        {
                // Test that the ID is a numeric value
                $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

                // Get the details of the risk
                $risk = get_risk_by_id($id);

                $status = $risk[0]['status'];
                $subject = $risk[0]['subject'];
                $calculated_risk = $risk[0]['calculated_risk'];
		$mgmt_review = $risk[0]['mgmt_review'];

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

		// Get the management reviews for the risk
		$mgmt_reviews = get_review_by_id($id);

		$review_date = $mgmt_reviews[0]['submission_date'];
		$review = $mgmt_reviews[0]['review'];
		$reviewer = $mgmt_reviews[0]['reviewer'];
		$next_step = $mgmt_reviews[0]['next_step'];
		$comments = $mgmt_reviews[0]['comments'];

                if ($review_date == "")
                {
                        $review_date = "N/A";
                }
                else $review_date = date(DATETIME, strtotime($review_date));
        }
?>

<!doctype html>
<html>

  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
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

      $(document).ready(function(){
        $('body').on('click', '.show-score-overtime', function(e){
            e.preventDefault();
            var tabContainer = $(this).parents('.risk-session');
            $('.score-overtime-container', tabContainer).show();
            $('.hide-score-overtime', tabContainer).show();
            $('.show-score-overtime', tabContainer).hide();
            return false;
        })

        $('body').on('click', '.hide-score-overtime', function(e){
            e.preventDefault();
            var tabContainer = $(this).parents('.risk-session');
            $('.score-overtime-container', tabContainer).hide();
            $('.hide-score-overtime', tabContainer).hide();
            $('.show-score-overtime', tabContainer).show();
            return false;
        })
      })
      
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

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">

    <?php view_top_menu("RiskManagement"); ?>

    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_risk_management_menu("ReviewRisksRegularly"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
              <?php view_top_table($id, $calculated_risk, $subject, $status, true); ?>
          </div>
          <div id="scoredetails" class="row-fluid" style="display: none;">
            <div class="well">
                  <?php
                        // Scoring method is Classic
                        if ($scoring_method == "1")
                        {
                                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact,$type=2);
                        }
                        // Scoring method is CVSS
                        else if ($scoring_method == "2")
                        {
                                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement,$type=2);
                        }
                        // Scoring method is DREAD
                        else if ($scoring_method == "3")
                        {
                                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability,$type=2);
                        }
                        // Scoring method is OWASP
                        else if ($scoring_method == "4")
                        {
                                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation,$type=2);
                        }
                        // Scoring method is Custom
                        else if ($scoring_method == "5")
                        {
                                custom_scoring_table($id, $custom,$type=2);
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
          
            <?php
                include(realpath(__DIR__ . '/partials/review.php'));
            ?>
          
        </div>
      </div>
    </div>
  </body>

</html>
