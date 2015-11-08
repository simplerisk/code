<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/assets.php'));

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

	// Check if the user has access to submit risks
	if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1)
	{
		$submit_risks = false;
		$alert = "bad";
		$alert_message = "You do not have permission to submit new risks.  Any risks that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
	}
	else $submit_risks = true;

	// Check if the subject is null
	if (isset($_POST['subject']) && $_POST['subject'] == "")
	{
		$submit_risks = false;
		$alert = "bad";
		$alert_message = "The subject of a risk cannot be empty.";
	}

        // Check if a new risk was submitted and the user has permissions to submit new risks
        if ((isset($_POST['submit'])) && $submit_risks)
        {
                $status = "New";
                $subject = $_POST['subject'];
		$reference_id = $_POST['reference_id'];
		$regulation = (int)$_POST['regulation'];
		$control_number = $_POST['control_number'];
		$location = $_POST['location'];
                $category = (int)$_POST['category'];
                $team = (int)$_POST['team'];
                $technology = (int)$_POST['technology'];
                $owner = (int)$_POST['owner'];
                $manager = (int)$_POST['manager'];
                $assessment = $_POST['assessment'];
                $notes = $_POST['notes'];
		$assets = $_POST['assets'];

		// Risk scoring method
		// 1 = Classic
		// 2 = CVSS
		// 3 = DREAD
		// 4 = OWASP
		// 5 = Custom
		$scoring_method = (int)$_POST['scoring_method'];

		// Classic Risk Scoring Inputs
		$CLASSIClikelihood = (int)$_POST['likelihood'];
                $CLASSICimpact =(int) $_POST['impact'];

		// CVSS Risk Scoring Inputs
		$CVSSAccessVector = $_POST['AccessVector'];
		$CVSSAccessComplexity = $_POST['AccessComplexity'];
		$CVSSAuthentication = $_POST['Authentication'];
		$CVSSConfImpact = $_POST['ConfImpact'];
		$CVSSIntegImpact = $_POST['IntegImpact'];
		$CVSSAvailImpact = $_POST['AvailImpact'];
		$CVSSExploitability = $_POST['Exploitability'];
		$CVSSRemediationLevel = $_POST['RemediationLevel'];
		$CVSSReportConfidence = $_POST['ReportConfidence'];
		$CVSSCollateralDamagePotential = $_POST['CollateralDamagePotential'];
		$CVSSTargetDistribution = $_POST['TargetDistribution'];
		$CVSSConfidentialityRequirement = $_POST['ConfidentialityRequirement'];
		$CVSSIntegrityRequirement = $_POST['IntegrityRequirement'];
		$CVSSAvailabilityRequirement = $_POST['AvailabilityRequirement'];

		// DREAD Risk Scoring Inputs
		$DREADDamage = (int)$_POST['DREADDamage'];
		$DREADReproducibility = (int)$_POST['DREADReproducibility'];
		$DREADExploitability = (int)$_POST['DREADExploitability'];
		$DREADAffectedUsers = (int)$_POST['DREADAffectedUsers'];
		$DREADDiscoverability = (int)$_POST['DREADDiscoverability'];

		// OWASP Risk Scoring Inputs
		$OWASPSkillLevel = (int)$_POST['OWASPSkillLevel'];
		$OWASPMotive = (int)$_POST['OWASPMotive'];
		$OWASPOpportunity = (int)$_POST['OWASPOpportunity'];
		$OWASPSize = (int)$_POST['OWASPSize'];
		$OWASPEaseOfDiscovery = (int)$_POST['OWASPEaseOfDiscovery'];
		$OWASPEaseOfExploit = (int)$_POST['OWASPEaseOfExploit'];
		$OWASPAwareness = (int)$_POST['OWASPAwareness'];
		$OWASPIntrusionDetection = (int)$_POST['OWASPIntrusionDetection'];
		$OWASPLossOfConfidentiality = (int)$_POST['OWASPLossOfConfidentiality'];
		$OWASPLossOfIntegrity = (int)$_POST['OWASPLossOfIntegrity'];
		$OWASPLossOfAvailability = (int)$_POST['OWASPLossOfAvailability'];
		$OWASPLossOfAccountability = (int)$_POST['OWASPLossOfAccountability'];
		$OWASPFinancialDamage = (int)$_POST['OWASPFinancialDamage'];
		$OWASPReputationDamage = (int)$_POST['OWASPReputationDamage'];
		$OWASPNonCompliance = (int)$_POST['OWASPNonCompliance'];
		$OWASPPrivacyViolation = (int)$_POST['OWASPPrivacyViolation'];

		// Custom Risk Scoring
		$custom = (float)$_POST['Custom'];

                // Submit risk and get back the id
                $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);

		// Submit risk scoring
		submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);

		// Tag assets to risk
		tag_assets_to_risk($last_insert_id, $assets);

		// If a file was submitted
		if (!empty($_FILES))
		{
			// Upload any file that is submitted
			upload_file($last_insert_id, $_FILES['file']);
		}

		// If the notification extra is enabled
        	if (notification_extra())
        	{
                	// Include the team separation extra
                	require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

                	// Send the notification
                	notify_new_risk($last_insert_id, $subject);
        	}

		// There is an alert message
		$risk_id = $last_insert_id + 1000;
		$alert = "good";
		$alert_message = "Risk ID " . $risk_id . " submitted successfully!";
        }
?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <link rel="stylesheet" href="../css/jquery-ui.min.css">
    <script type="text/javascript">
      function popupcvss()
      {
        my_window = window.open('cvss_rating.php','popupwindow','width=850,height=680,menu=0,status=0');
      }

      function popupdread()
      {
        my_window = window.open('dread_rating.php','popupwindow','width=660,height=500,menu=0,status=0');
      }

      function popupowasp()
      {
        my_window = window.open('owasp_rating.php','popupwindow','width=665,height=570,menu=0,status=0');
      }

      function closepopup()
      {
        if(false == my_window.closed)
        {
          my_window.close ();
        }
        else
        {
          alert('Window already closed!');
        }
      }

      function handleSelection(choice) {
        if (choice=="1") {
	  document.getElementById("classic").style.display = "";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "none";
	}
        if (choice=="2") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "none";
	}
        if (choice=="3") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "none";
        }
        if (choice=="4") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "";
          document.getElementById("custom").style.display = "none";
        }
        if (choice=="5") {
          document.getElementById("classic").style.display = "none";
          document.getElementById("cvss").style.display = "none";
          document.getElementById("dread").style.display = "none";
          document.getElementById("owasp").style.display = "none";
          document.getElementById("custom").style.display = "";
        }
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
          <?php view_risk_management_menu("SubmitYourRisks"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4><?php echo $escaper->escapeHtml($lang['DocumentANewRisk']); ?></h4>
                <p><?php echo $escaper->escapeHtml($lang['UseThisFormHelp']); ?>.</p>
                <form name="submit_risk" method="post" action="" enctype="multipart/form-data">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['Subject']); ?>:</td>
                  <td><input maxlength="100" name="subject" id="subject" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['ExternalReferenceId']); ?>:</td>
                  <td><input maxlength="20" size="20" name="reference_id" id="reference_id" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['ControlRegulation']); ?>:</td>
                  <td><?php create_dropdown("regulation"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['ControlNumber']); ?>:</td>
                  <td><input maxlength="20" name="control_number" id="control_number" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['SiteLocation']); ?>:</td>
                  <td><?php create_dropdown("location"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['Category']); ?>:</td>
                  <td><?php create_dropdown("category"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['Team']); ?>:</td>
                  <td><?php create_dropdown("team"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['Technology']); ?>:</td>
                  <td><?php create_dropdown("technology"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['Owner']); ?>:</td>
                  <td><?php create_dropdown("user", NULL, "owner"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['OwnersManager']); ?>:</td>
                  <td><?php create_dropdown("user", NULL, "manager"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $escaper->escapeHtml($lang['RiskScoringMethod']); ?>:</td>
                  <td>
		    <select name="scoring_method" id="select" onChange="handleSelection(value)">
		      <option selected value="1">Classic</option>
		      <option value="2">CVSS</option>
		      <option value="3">DREAD</option>
		      <option value="4">OWASP</option>
		      <option value="5">Custom</option>
		    </select>
                  </td>
                </tr>
                <tr><td colspan="2">
		  <div id="classic">
                    <table width="100%">
                      <tr>
                        <td width="197px"><?php echo $escaper->escapeHtml($lang['CurrentLikelihood']); ?>:</td>
                        <td><?php create_dropdown("likelihood"); ?></td>
                      </tr>
                      <tr>
                        <td width="197px"><?php echo $escaper->escapeHtml($lang['CurrentImpact']); ?>:</td>
                        <td><?php create_dropdown("impact"); ?></td>
                      </tr>
                    </table>
		  </div>
		  <div id="cvss" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="cvssSubmit" id="cvssSubmit" value="Score Using CVSS" onclick="javascript: popupcvss();" /></p></td>
                      </tr>
                    </table>
                    <input type="hidden" name="AccessVector" id="AccessVector" value="N" />
                    <input type="hidden" name="AccessComplexity" id="AccessComplexity" value="L" />
                    <input type="hidden" name="Authentication" id="Authentication" value="N" />
                    <input type="hidden" name="ConfImpact" id="ConfImpact" value="C" />
                    <input type="hidden" name="IntegImpact" id="IntegImpact" value="C" />
                    <input type="hidden" name="AvailImpact" id="AvailImpact" value="C" />
                    <input type="hidden" name="Exploitability" id="Exploitability" value="ND" />
                    <input type="hidden" name="RemediationLevel" id="RemediationLevel" value="ND" />
                    <input type="hidden" name="ReportConfidence" id="ReportConfidence" value="ND" />
                    <input type="hidden" name="CollateralDamagePotential" id="CollateralDamagePotential" value="ND" />
                    <input type="hidden" name="TargetDistribution" id="TargetDistribution" value="ND" />
                    <input type="hidden" name="ConfidentialityRequirement" id="ConfidentialityRequirement" value="ND" />
                    <input type="hidden" name="IntegrityRequirement" id="IntegrityRequirement" value="ND" />
                    <input type="hidden" name="AvailabilityRequirement" id="AvailabilityRequirement" value="ND" />
		  </div>
		  <div id="dread" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="dreadSubmit" id="dreadSubmit" value="Score Using DREAD" onclick="javascript: popupdread();" /></p></td>
                      </tr>
                    </table>
		    <input type="hidden" name="DREADDamage" id="DREADDamage" value="10" />
		    <input type="hidden" name="DREADReproducibility" id="DREADReproducibility" value="10" />
                    <input type="hidden" name="DREADExploitability" id="DREADExploitability" value="10" />
                    <input type="hidden" name="DREADAffectedUsers" id="DREADAffectedUsers" value="10" />
                    <input type="hidden" name="DREADDiscoverability" id="DREADDiscoverability" value="10" />
		  </div>
		  <div id="owasp" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="owaspSubmit" id="owaspSubmit" value="Score Using OWASP" onclick="javascript: popupowasp();" /></p></td>
                      </tr>
                    </table>
                    <input type="hidden" name="OWASPSkillLevel" id="OWASPSkillLevel" value="10" />
                    <input type="hidden" name="OWASPMotive" id="OWASPMotive" value="10" />
                    <input type="hidden" name="OWASPOpportunity" id="OWASPOpportunity" value="10" />
                    <input type="hidden" name="OWASPSize" id="OWASPSize" value="10" />
                    <input type="hidden" name="OWASPEaseOfDiscovery" id="OWASPEaseOfDiscovery" value="10" />
                    <input type="hidden" name="OWASPEaseOfExploit" id="OWASPEaseOfExploit" value="10" />
                    <input type="hidden" name="OWASPAwareness" id="OWASPAwareness" value="10" />
                    <input type="hidden" name="OWASPIntrusionDetection" id="OWASPIntrusionDetection" value="10" />
                    <input type="hidden" name="OWASPLossOfConfidentiality" id="OWASPLossOfConfidentiality" value="10" />
		    <input type="hidden" name="OWASPLossOfIntegrity" id="OWASPLossOfIntegrity" value="10" />
                    <input type="hidden" name="OWASPLossOfAvailability" id="OWASPLossOfAvailability" value="10" />
                    <input type="hidden" name="OWASPLossOfAccountability" id="OWASPLossOfAccountability" value="10" />
                    <input type="hidden" name="OWASPFinancialDamage" id="OWASPFinancialDamage" value="10" />
                    <input type="hidden" name="OWASPReputationDamage" id="OWASPReputationDamage" value="10" />
                    <input type="hidden" name="OWASPNonCompliance" id="OWASPNonCompliance" value="10" />
                    <input type="hidden" name="OWASPPrivacyViolation" id="OWASPPrivacyViolation" value="10" />
		  </div>
		  <div id="custom" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px"><?php echo $escaper->escapeHtml($lang['CustomValue']); ?>:</td>
                        <td><input type="text" name="Custom" id="Custom" value="" /> (Must be a numeric value between 0 and 10)</td>
                      </tr>
                    </table>
		  </div>
                  <tr>
                    <td width="200px"><?php echo $escaper->escapeHtml($lang['RiskAssessment']); ?>:</td>
                    <td><textarea name="assessment" cols="50" rows="3" id="assessment"></textarea></td>
                  </tr>
                  <tr>
                    <td width="200px"><?php echo $escaper->escapeHtml($lang['AdditionalNotes']); ?>:</td>
                    <td><textarea name="notes" cols="50" rows="3" id="notes"></textarea></td>
                  </tr>
                  <tr>
                    <td width="200px"><?php echo $escaper->escapeHtml($lang['AffectedAssets']); ?>:</td>
                    <td><div class="ui-widget"><input type="text" id="assets" name="assets" /></div></td>
                  </tr>
                  <tr>
                    <td width="200px"><?php echo $escaper->escapeHtml($lang['SupportingDocumentation']); ?>:</td>
                    <td><input type="file" name="file" /></td>
                  </tr>
                </table>
                <div class="form-actions">
                  <button type="submit" name="submit" class="btn btn-primary"><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
                  <input class="btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset"> 
                </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
