<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
        require_once(realpath(__DIR__ . '/../includes/display.php'));
        include_once(realpath(__DIR__ . '/includes.php'));

        
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

        if(isset($_GET['page']) && ($_GET['page'] == '1' || $_GET['page'] == '2' || $_GET['page'] == '3')){
          // Record the page the workflow started from as a session variable
          $_SESSION["workflow_start"] = $_SERVER['SCRIPT_NAME']."?module=1&page=".$_GET['page'];
        }




  if(!isset($_GET['page'])) {
    	// Check if the user has access to submit risks
    	if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1)
    	{
    		$submit_risks = false;
    		$alert = "bad";
    		$alert_message = "You do not have permission to submit new risks.  Any risks that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
    	}
    	else $submit_risks = true;

            // Check if a new risk was submitted and the user has permissions to submit new risks
            if ((isset($_POST['submit'])) && $submit_risks)
            {
                    $status = "New";
                    $subject = addslashes($_POST['subject']);
    		$reference_id = addslashes($_POST['reference_id']);
    		$regulation = (int)$_POST['regulation'];
    		$control_number = addslashes($_POST['control_number']);
    		$location = addslashes($_POST['location']);
                    $category = (int)$_POST['category'];
                    $team = (int)$_POST['team'];
                    $technology = (int)$_POST['technology'];
                    $owner = (int)$_POST['owner'];
                    $manager = (int)$_POST['manager'];
                    $assessment = addslashes($_POST['assessment']);
                    $notes = addslashes($_POST['notes']);

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
    		$custom = $_POST['Custom'];

                    // Submit risk and get back the id
                    $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);

    		// Submit risk scoring
    		submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);

    		// If the notification extra is enabled
            	if (notification_extra())
            	{
                    	// Include the team separation extra
                    	require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

                    	// Send the notification
                    	notify_new_risk($last_insert_id, $subject);
            	}

    		// Audit log
    		$risk_id = $last_insert_id + 1000;
    		$message = "A new risk ID \"" . $risk_id . "\" was submitted by username \"" . $_SESSION['user'] . "\".";
    		write_log($risk_id, $_SESSION['uid'], $message);

    		// There is an alert message
    		$alert = "good";
    		$alert_message = "Risk submitted successfully!";
            }
    }

    else if($_GET['page'] == '1'){
        // If mitigated was passed back to the page as a GET parameter
        if (isset($_GET['mitigated']))
        {
          // If its true
          if ($_GET['mitigated'] == true)
          {
            $alert = "good";
            $alert_message = "Mitigation submitted successfully!";
          }
        }

    }

    else if($_GET['page'] == '2'){
        // If reviewed is passed via GET
        if (isset($_GET['reviewed']))
        {
                // If it's true
                if ($_GET['reviewed'] == true)
                {
                        $alert = "good";
                        $alert_message = "Management review submitted successfully!";
                }       
        }

    }

    else if($_GET['page'] == '3'){
        // If the risks were saved to projects
          if (isset($_POST['update_projects']))
          {
            foreach ($_POST['ids'] as $risk_id)
                        {
                                $project_id = $_POST['risk_' . $risk_id];
                                update_risk_project($project_id, $risk_id);
                        }

            // There is an alert message
            $alert = "good";
            $alert_message = "The risks were saved successfully to the projects.";
                }


          // If the order was updated
          if (isset($_POST['update_order']))
          {
            foreach ($_POST['ids'] as $id)
            {
              $order = $_POST['order_' . $id];
              update_project_order($order, $id);
            }

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "The project order was updated successfully.";
          }

          // If the projects were saved to status
          if (isset($_POST['update_project_status']))
          {
            foreach ($_POST['projects'] as $project_id)
            {
              $status_id = $_POST['project_' . $project_id];
              update_project_status($status_id, $project_id);
            }

                        // There is an alert message
                        $alert = "good";
                        $alert_message = "The project statuses were successfully updated.";
          }

                // Check if a new project was submitted
                if (isset($_POST['add_project']))
                {
                        $name = $_POST['new_project'];

                        // Insert a new project up to 100 chars
                        add_name("projects", $name, 100);

                        // Audit log
                        $risk_id = 1000;
                        $message = "A new project was added by the \"" . $_SESSION['user'] . "\" user.";
                        write_log($risk_id, $_SESSION['uid'], $message);

            // There is an alert message
                        $alert = "good";
                        $alert_message = "A new project was added successfully.";
                }

                // Check if a project was deleted
                if (isset($_POST['delete_project']))
                {
                        $value = (int)$_POST['projects'];

                        // Verify value is an integer
                        if (is_int($value))
                        {
              // If the project ID is 0 (ie. Unassigned Risks)
              if ($value == 0)
              {
                // There is an alert message
                $alert = "bad";
                $alert_message = "You cannot delete the Unassigned Risks project or we will have no place to put unassigned risks.  Sorry.";
              }
              // If the project has risks associated with it
              else if (project_has_risks($value))
              {
                // There is an alert message
                $alert = "bad";
                $alert_message = "You cannot delete a project that has risks assigned to it.  Drag the risks back to the Unassigned Risks tab, save it, and try again.";
              }
              else
              {
                                  delete_value("projects", $value);

                                  // Audit log
                                  $risk_id = 1000;
                                  $message = "An existing project was removed by the \"" . $_SESSION['user'] . "\" user.";
                                  write_log($risk_id, $_SESSION['uid'], $message);

                // There is an alert message
                $alert = "good";
                            $alert_message = "An existing project was deleted successfully.";
              }
                        }
            // We should never get here as we bound the variable as an int
            else
            {
              // There is an alert message
              $alert = "bad";
              $alert_message = "The project ID was not a valid value.  Please try again.";
            }
                }

    }

    else if($_GET['page'] == '4') {
      // If reviewed is passed via GET
        if (isset($_GET['reviewed']))
        {
                // If it's true
                if ($_GET['reviewed'] == true)
                {
                        $alert = "good";
                        $alert_message = "Risk review submitted successfully!";
                }
        }
    }

    else if($_GET['page'] == '5') {
        // Check if a risk ID was sent
        if (isset($_GET['id']))
        { 
                $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8', false);

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
                  $status = htmlentities($risk[0]['status'], ENT_QUOTES, 'UTF-8', false);
                  $subject = htmlentities(stripslashes($risk[0]['subject']), ENT_QUOTES, 'UTF-8', false);
      $reference_id = htmlentities(stripslashes($risk[0]['reference_id']), ENT_QUOTES, 'UTF-8', false);
      $regulation = htmlentities($risk[0]['regulation'], ENT_QUOTES, 'UTF-8', false);
      $control_number = htmlentities($risk[0]['control_number'], ENT_QUOTES, 'UTF-8', false);
      $location = htmlentities($risk[0]['location'], ENT_QUOTES, 'UTF-8', false);
                  $category = htmlentities($risk[0]['category'], ENT_QUOTES, 'UTF-8', false);
                  $team = htmlentities($risk[0]['team'], ENT_QUOTES, 'UTF-8', false);
                  $technology = htmlentities($risk[0]['technology'], ENT_QUOTES, 'UTF-8', false);
                  $owner = htmlentities($risk[0]['owner'], ENT_QUOTES, 'UTF-8', false);
                  $manager = htmlentities($risk[0]['manager'], ENT_QUOTES, 'UTF-8', false);
                  $assessment = htmlentities(stripslashes($risk[0]['assessment']), ENT_QUOTES, 'UTF-8', false);
                  $notes = htmlentities(stripslashes($risk[0]['notes']), ENT_QUOTES, 'UTF-8', false);
      $submission_date = htmlentities($risk[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
      $mitigation_id = htmlentities($risk[0]['mitigation_id'], ENT_QUOTES, 'UTF-8', false);
      $mgmt_review = htmlentities($risk[0]['mgmt_review'], ENT_QUOTES, 'UTF-8', false);
      $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
      $next_review = htmlentities($risk[0]['next_review'], ENT_QUOTES, 'UTF-8', false);
      $color = get_risk_color($id);

      $scoring_method = htmlentities($risk[0]['scoring_method'], ENT_QUOTES, 'UTF-8', false);
      $CLASSIC_likelihood = htmlentities($risk[0]['CLASSIC_likelihood'], ENT_QUOTES, 'UTF-8', false);
      $CLASSIC_impact = htmlentities($risk[0]['CLASSIC_impact'], ENT_QUOTES, 'UTF-8', false);
      $AccessVector = htmlentities($risk[0]['CVSS_AccessVector'], ENT_QUOTES, 'UTF-8', false);
      $AccessComplexity = htmlentities($risk[0]['CVSS_AccessComplexity'], ENT_QUOTES, 'UTF-8', false);
      $Authentication = htmlentities($risk[0]['CVSS_Authentication'], ENT_QUOTES, 'UTF-8', false);
      $ConfImpact = htmlentities($risk[0]['CVSS_ConfImpact'], ENT_QUOTES, 'UTF-8', false);
      $IntegImpact = htmlentities($risk[0]['CVSS_IntegImpact'], ENT_QUOTES, 'UTF-8', false);
      $AvailImpact = htmlentities($risk[0]['CVSS_AvailImpact'], ENT_QUOTES, 'UTF-8', false);
      $Exploitability = htmlentities($risk[0]['CVSS_Exploitability'], ENT_QUOTES, 'UTF-8', false);
      $RemediationLevel = htmlentities($risk[0]['CVSS_RemediationLevel'], ENT_QUOTES, 'UTF-8', false);
      $ReportConfidence = htmlentities($risk[0]['CVSS_ReportConfidence'], ENT_QUOTES, 'UTF-8', false);
      $CollateralDamagePotential = htmlentities($risk[0]['CVSS_CollateralDamagePotential'], ENT_QUOTES, 'UTF-8', false);
      $TargetDistribution = htmlentities($risk[0]['CVSS_TargetDistribution'], ENT_QUOTES, 'UTF-8', false);
      $ConfidentialityRequirement = htmlentities($risk[0]['CVSS_ConfidentialityRequirement'], ENT_QUOTES, 'UTF-8', false);
      $IntegrityRequirement = htmlentities($risk[0]['CVSS_IntegrityRequirement'], ENT_QUOTES, 'UTF-8', false);
      $AvailabilityRequirement = htmlentities($risk[0]['CVSS_AvailabilityRequirement'], ENT_QUOTES, 'UTF-8',  false);
                  $DREADDamagePotential = htmlentities($risk[0]['DREAD_DamagePotential'], ENT_QUOTES, 'UTF-8', false);
      $DREADReproducibility = htmlentities($risk[0]['DREAD_Reproducibility'], ENT_QUOTES, 'UTF-8', false);
      $DREADExploitability = htmlentities($risk[0]['DREAD_Exploitability'], ENT_QUOTES, 'UTF-8', false);
      $DREADAffectedUsers = htmlentities($risk[0]['DREAD_AffectedUsers'], ENT_QUOTES, 'UTF-8', false);
      $DREADDiscoverability = htmlentities($risk[0]['DREAD_Discoverability'], ENT_QUOTES, 'UTF-8', false);
      $OWASPSkillLevel = htmlentities($risk[0]['OWASP_SkillLevel'], ENT_QUOTES, 'UTF-8', false);
      $OWASPMotive = htmlentities($risk[0]['OWASP_Motive'], ENT_QUOTES, 'UTF-8', false);
      $OWASPOpportunity = htmlentities($risk[0]['OWASP_Opportunity'], ENT_QUOTES, 'UTF-8', false);
      $OWASPSize = htmlentities($risk[0]['OWASP_Size'], ENT_QUOTES, 'UTF-8', false);
      $OWASPEaseOfDiscovery = htmlentities($risk[0]['OWASP_EaseOfDiscovery'], ENT_QUOTES, 'UTF-8', false);
      $OWASPEaseOfExploit = htmlentities($risk[0]['OWASP_EaseOfExploit'], ENT_QUOTES, 'UTF-8', false);
      $OWASPAwareness = htmlentities($risk[0]['OWASP_Awareness'], ENT_QUOTES, 'UTF-8', false);
      $OWASPIntrusionDetection = htmlentities($risk[0]['OWASP_IntrusionDetection'], ENT_QUOTES, 'UTF-8', false);
      $OWASPLossOfConfidentiality = htmlentities($risk[0]['OWASP_LossOfConfidentiality'], ENT_QUOTES, 'UTF-8', false);
      $OWASPLossOfIntegrity = htmlentities($risk[0]['OWASP_LossOfIntegrity'], ENT_QUOTES, 'UTF-8', false);
      $OWASPLossOfAvailability = htmlentities($risk[0]['OWASP_LossOfAvailability'], ENT_QUOTES, 'UTF-8', false);
      $OWASPLossOfAccountability = htmlentities($risk[0]['OWASP_LossOfAccountability'], ENT_QUOTES, 'UTF-8', false);
      $OWASPFinancialDamage = htmlentities($risk[0]['OWASP_FinancialDamage'], ENT_QUOTES, 'UTF-8', false);
      $OWASPReputationDamage = htmlentities($risk[0]['OWASP_ReputationDamage'], ENT_QUOTES, 'UTF-8', false);
      $OWASPNonCompliance = htmlentities($risk[0]['OWASP_NonCompliance'], ENT_QUOTES, 'UTF-8', false);
      $OWASPPrivacyViolation = htmlentities($risk[0]['OWASP_PrivacyViolation'], ENT_QUOTES, 'UTF-8', false);
      $custom = htmlentities($risk[0]['Custom'], ENT_QUOTES, 'UTF-8', false);
    }
    // If the risk was not found use null values
    else
    {
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
    if (isset($_GET['scoring_method']) && htmlentities($_GET['scoring_method'], ENT_QUOTES, 'UTF-8', false) == 1)
    {
      // Set the new scoring method
      $scoring_method = change_scoring_method($id, "1");

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to Classic.";
    }
                // If the current scoring method was changed to CVSS
                else if (isset($_GET['scoring_method']) && htmlentities($_GET['scoring_method'], ENT_QUOTES, 'UTF-8', false) == 2)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "2");

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to CVSS.";
                }
                // If the current scoring method was changed to DREAD
                else if (isset($_GET['scoring_method']) && htmlentities($_GET['scoring_method'], ENT_QUOTES, 'UTF-8', false) == 3)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "3");

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to DREAD.";
                }
                // If the current scoring method was changed to OWASP
                else if (isset($_GET['scoring_method']) && htmlentities($_GET['scoring_method'], ENT_QUOTES, 'UTF-8', false) == 4)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "4");

                        // Audit log
                        $risk_id = $id;
                        $message = "Scoring method was changed for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                        write_log($risk_id, $_SESSION['uid'], $message);

                        $alert = "good";
                        $alert_message = "The scoring method has been successfully changed to OWASP.";
                }
                // If the current scoring method was changed to Custom
                else if (isset($_GET['scoring_method']) && htmlentities($_GET['scoring_method'], ENT_QUOTES, 'UTF-8', false) == 5)
                {
                        // Set the new scoring method
                        $scoring_method = change_scoring_method($id, "5");

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
      $current_solution = "";
      $security_requirements = "";
      $security_recommendations = "";
    }
    // If a mitigation exists
    else
    {
      // Set the mitigation values
      $mitigation_date = htmlentities($mitigation[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
      $mitigation_date = date(DATETIME, strtotime($mitigation_date));
      $planning_strategy = htmlentities($mitigation[0]['planning_strategy'], ENT_QUOTES, 'UTF-8', false);
      $mitigation_effort = htmlentities($mitigation[0]['mitigation_effort'], ENT_QUOTES, 'UTF-8', false);
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
      $reviewer = "";
      $comments = "";
                }
                // If a mitigation exists
                else
                {
                        // Set the mitigation values
      $review_date = htmlentities($mgmt_reviews[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
      $review_date = date(DATETIME, strtotime($review_date));
      $review = htmlentities($mgmt_reviews[0]['review'], ENT_QUOTES, 'UTF-8', false);
      $next_step = htmlentities($mgmt_reviews[0]['next_step'], ENT_QUOTES, 'UTF-8', false);
      $next_review = htmlentities(next_review($color, $id, $next_review, false), ENT_QUOTES, 'UTF-8', false);
      $reviewer = htmlentities($mgmt_reviews[0]['reviewer'], ENT_QUOTES, 'UTF-8', false);
      $comments = $mgmt_reviews[0]['comments'];
    }
        }

  // If the risk details were updated
        if (isset($_POST['update_details']))
        {
    // If the user has permission to modify risks
    if (isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
                  $subject = addslashes($_POST['subject']);
      $reference_id = addslashes($_POST['reference_id']);
      $regulation = (int)$_POST['regulation'];
      $control_number = addslashes($_POST['control_number']);
      $location = (int)$_POST['location'];
                  $category = (int)$_POST['category'];
                  $team = (int)$_POST['team'];
                  $technology = (int)$_POST['technology'];
                  $owner = (int)$_POST['owner'];
                  $manager = (int)$_POST['manager'];
                  $assessment = addslashes($_POST['assessment']);
                  $notes = addslashes($_POST['notes']);

      // Update risk
      update_risk($id, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes);

                  // Audit log
                  $risk_id = $id;
                  $message = "Risk details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                  write_log($risk_id, $_SESSION['uid'], $message);

      $alert = "good";
      $alert_message = "The risk has been successfully modified.";
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
                $current_solution = addslashes($_POST['current_solution']);
                $security_requirements = addslashes($_POST['security_requirements']);
                $security_recommendations = addslashes($_POST['security_recommendations']);

    // If we don't yet have a mitigation
    if ($mitigation_id == 0)
    {
                  $status = "Mitigation Planned";

                  // Submit mitigation and get the mitigation date back
                  $mitigation_date = submit_mitigation($id, $status, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);
      $mitigation_date = date(DATETIME, strtotime($mitigation_date));
    }
    else
    {
      // Update mitigation and get the mitigation date back
      $mitigation_date = update_mitigation($id, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);
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

    }

    else if($_GET['page'] == '6'){
       // Check if a risk ID was sent
        if (isset($_GET['id']) || isset($_POST['id']))
        {
                if (isset($_GET['id']))
                {
                        $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8', false);
                }
                else if (isset($_POST['id']))
                {
                        $id = htmlentities($_POST['id'], ENT_QUOTES, 'UTF-8', false);
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

                $status = htmlentities($risk[0]['status'], ENT_QUOTES, 'UTF-8', false);
                $subject = htmlentities(stripslashes($risk[0]['subject']), ENT_QUOTES, 'UTF-8', false);
                $reference_id = htmlentities(stripslashes($risk[0]['reference_id']), ENT_QUOTES, 'UTF-8', false);
    $regulation = htmlentities($risk[0]['regulation'], ENT_QUOTES, 'UTF-8', false);
    $control_number = htmlentities($risk[0]['control_number'], ENT_QUOTES, 'UTF-8', false);
                $location = htmlentities($risk[0]['location'], ENT_QUOTES, 'UTF-8', false);
                $category = htmlentities($risk[0]['category'], ENT_QUOTES, 'UTF-8', false);
                $team = htmlentities($risk[0]['team'], ENT_QUOTES, 'UTF-8', false);
                $technology = htmlentities($risk[0]['technology'], ENT_QUOTES, 'UTF-8', false);
                $owner = htmlentities($risk[0]['owner'], ENT_QUOTES, 'UTF-8', false);
                $manager = htmlentities($risk[0]['manager'], ENT_QUOTES, 'UTF-8', false);
                $assessment = htmlentities(stripslashes($risk[0]['assessment']), ENT_QUOTES, 'UTF-8', false);
                $notes = htmlentities(stripslashes($risk[0]['notes']), ENT_QUOTES, 'UTF-8', false);
                $submission_date = htmlentities($risk[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
                $mitigation_id = htmlentities($risk[0]['mitigation_id'], ENT_QUOTES, 'UTF-8', false);
                $mgmt_review = htmlentities($risk[0]['mgmt_review'], ENT_QUOTES, 'UTF-8', false);
                $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
    $risk_level = htmlentities(get_risk_level_name($calculated_risk), ENT_QUOTES, 'UTF-8', false);
    $next_review = next_review_by_score($calculated_risk);

                $scoring_method = htmlentities($risk[0]['scoring_method'], ENT_QUOTES, 'UTF-8', false);
                $CLASSIC_likelihood = htmlentities($risk[0]['CLASSIC_likelihood'], ENT_QUOTES, 'UTF-8', false);
                $CLASSIC_impact = htmlentities($risk[0]['CLASSIC_impact'], ENT_QUOTES, 'UTF-8', false);
                $AccessVector = htmlentities($risk[0]['CVSS_AccessVector'], ENT_QUOTES, 'UTF-8', false);
                $AccessComplexity = htmlentities($risk[0]['CVSS_AccessComplexity'], ENT_QUOTES, 'UTF-8', false);
                $Authentication = htmlentities($risk[0]['CVSS_Authentication'], ENT_QUOTES, 'UTF-8', false);
                $ConfImpact = htmlentities($risk[0]['CVSS_ConfImpact'], ENT_QUOTES, 'UTF-8', false);
                $IntegImpact = htmlentities($risk[0]['CVSS_IntegImpact'], ENT_QUOTES, 'UTF-8', false);
                $AvailImpact = htmlentities($risk[0]['CVSS_AvailImpact'], ENT_QUOTES, 'UTF-8', false);
                $Exploitability = htmlentities($risk[0]['CVSS_Exploitability'], ENT_QUOTES, 'UTF-8', false);
                $RemediationLevel = htmlentities($risk[0]['CVSS_RemediationLevel'], ENT_QUOTES, 'UTF-8', false);
                $ReportConfidence = htmlentities($risk[0]['CVSS_ReportConfidence'], ENT_QUOTES, 'UTF-8', false);
                $CollateralDamagePotential = htmlentities($risk[0]['CVSS_CollateralDamagePotential'], ENT_QUOTES, 'UTF-8', false);
                $TargetDistribution = htmlentities($risk[0]['CVSS_TargetDistribution'], ENT_QUOTES, 'UTF-8', false);
                $ConfidentialityRequirement = htmlentities($risk[0]['CVSS_ConfidentialityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $IntegrityRequirement = htmlentities($risk[0]['CVSS_IntegrityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $AvailabilityRequirement = htmlentities($risk[0]['CVSS_AvailabilityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $DREADDamagePotential = htmlentities($risk[0]['DREAD_DamagePotential'], ENT_QUOTES, 'UTF-8', false);
                $DREADReproducibility = htmlentities($risk[0]['DREAD_Reproducibility'], ENT_QUOTES, 'UTF-8', false);
                $DREADExploitability = htmlentities($risk[0]['DREAD_Exploitability'], ENT_QUOTES, 'UTF-8', false);
                $DREADAffectedUsers = htmlentities($risk[0]['DREAD_AffectedUsers'], ENT_QUOTES, 'UTF-8', false);
                $DREADDiscoverability = htmlentities($risk[0]['DREAD_Discoverability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPSkillLevel = htmlentities($risk[0]['OWASP_SkillLevel'], ENT_QUOTES, 'UTF-8', false);
                $OWASPMotive = htmlentities($risk[0]['OWASP_Motive'], ENT_QUOTES, 'UTF-8', false);
                $OWASPOpportunity = htmlentities($risk[0]['OWASP_Opportunity'], ENT_QUOTES, 'UTF-8', false);
                $OWASPSize = htmlentities($risk[0]['OWASP_Size'], ENT_QUOTES, 'UTF-8', false);
                $OWASPEaseOfDiscovery = htmlentities($risk[0]['OWASP_EaseOfDiscovery'], ENT_QUOTES, 'UTF-8', false);
                $OWASPEaseOfExploit = htmlentities($risk[0]['OWASP_EaseOfExploit'], ENT_QUOTES, 'UTF-8', false);
                $OWASPAwareness = htmlentities($risk[0]['OWASP_Awareness'], ENT_QUOTES, 'UTF-8', false);
                $OWASPIntrusionDetection = htmlentities($risk[0]['OWASP_IntrusionDetection'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfConfidentiality = htmlentities($risk[0]['OWASP_LossOfConfidentiality'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfIntegrity = htmlentities($risk[0]['OWASP_LossOfIntegrity'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfAvailability = htmlentities($risk[0]['OWASP_LossOfAvailability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfAccountability = htmlentities($risk[0]['OWASP_LossOfAccountability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPFinancialDamage = htmlentities($risk[0]['OWASP_FinancialDamage'], ENT_QUOTES, 'UTF-8', false);
                $OWASPReputationDamage = htmlentities($risk[0]['OWASP_ReputationDamage'], ENT_QUOTES, 'UTF-8', false);
                $OWASPNonCompliance = htmlentities($risk[0]['OWASP_NonCompliance'], ENT_QUOTES, 'UTF-8', false);
                $OWASPPrivacyViolation = htmlentities($risk[0]['OWASP_PrivacyViolation'], ENT_QUOTES, 'UTF-8', false);
                $custom = htmlentities($risk[0]['Custom'], ENT_QUOTES, 'UTF-8', false);

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
                        $mitigation_date = htmlentities($mitigation[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
                        $mitigation_date = date(DATETIME, strtotime($mitigation_date));
                        $planning_strategy = htmlentities($mitigation[0]['planning_strategy'], ENT_QUOTES, 'UTF-8', false);
                        $mitigation_effort = htmlentities($mitigation[0]['mitigation_effort'], ENT_QUOTES, 'UTF-8', false);
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
                        $review_date = htmlentities($mgmt_reviews[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
                        $review_date = date(DATETIME, strtotime($review_date));
                        $review = htmlentities($mgmt_reviews[0]['review'], ENT_QUOTES, 'UTF-8', false);
                        $next_step = htmlentities($mgmt_reviews[0]['next_step'], ENT_QUOTES, 'UTF-8', false);
                        $reviewer = htmlentities($mgmt_reviews[0]['reviewer'], ENT_QUOTES, 'UTF-8', false);
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
      $custom_date = $_POST['custom_date'];

      if ($custom_date == "yes")
      {
        $custom_review = $_POST['next_review'];

        // Check the date format
        if (!preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $custom_review))
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
                        header("Location: " . $_SESSION["workflow_start"] . "&reviewed=true");
    }
    // They do not have permissions to review the risk
    else
    {
                  // There is an alert
                  $alert = "bad";
                  $alert_message = "You do not have permission to review " . $risk_level . " level risks.  The review that you attempted to submit was not recorded.  Please contact an administrator if you feel that you have reached this message in error.";
    }
        }

    }

    else if($_GET['page'] == '7'){
        // Check if the user has access to plan mitigations
  if (!isset($_SESSION["plan_mitigations"]) || $_SESSION["plan_mitigations"] != 1)
  {
    $plan_mitigations = false;
    $alert = "bad";
    $alert_message = "You do not have permission to plan mitigations.  Any mitigations that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
  }
  else $plan_mitigations = true;

        // Check if a risk ID was sent
        if (isset($_GET['id']) || isset($_POST['id']))
        {
                if (isset($_GET['id']))
                {
                        $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8', false);
                }
                else if (isset($_POST['id']))
                {
                        $id = htmlentities($_POST['id'], ENT_QUOTES, 'UTF-8', false);
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

                $status = htmlentities($risk[0]['status'], ENT_QUOTES, 'UTF-8', false);
                $subject = htmlentities(stripslashes($risk[0]['subject']), ENT_QUOTES, 'UTF-8', false);
                $reference_id = htmlentities(stripslashes($risk[0]['reference_id']), ENT_QUOTES, 'UTF-8', false);
    $regulation = htmlentities(stripslashes($risk[0]['regulation']), ENT_QUOTES, 'UTF-8', false);
    $control_number = htmlentities(stripslashes($risk[0]['control_number']), ENT_QUOTES, 'UTF-8', false);
                $location = htmlentities($risk[0]['location'], ENT_QUOTES, 'UTF-8', false);
                $category = htmlentities($risk[0]['category'], ENT_QUOTES, 'UTF-8', false);
                $team = htmlentities($risk[0]['team'], ENT_QUOTES, 'UTF-8', false);
                $technology = htmlentities($risk[0]['technology'], ENT_QUOTES, 'UTF-8', false);
                $owner = htmlentities($risk[0]['owner'], ENT_QUOTES, 'UTF-8', false);
                $manager = htmlentities($risk[0]['manager'], ENT_QUOTES, 'UTF-8', false);
                $assessment = htmlentities(stripslashes($risk[0]['assessment']), ENT_QUOTES, 'UTF-8', false);
                $notes = htmlentities(stripslashes($risk[0]['notes']), ENT_QUOTES, 'UTF-8', false);
                $submission_date = htmlentities($risk[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
                $mitigation_id = htmlentities($risk[0]['mitigation_id'], ENT_QUOTES, 'UTF-8', false);
                $mgmt_review = htmlentities($risk[0]['mgmt_review'], ENT_QUOTES, 'UTF-8', false);
                $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
                $risk_level = htmlentities(get_risk_level_name($calculated_risk), ENT_QUOTES, 'UTF-8', false);
    $next_review = htmlentities($risk[0]['next_review'], ENT_QUOTES, 'UTF-8', false);
    $color = get_risk_color($id);

                $scoring_method = htmlentities($risk[0]['scoring_method'], ENT_QUOTES, 'UTF-8', false);
                $CLASSIC_likelihood = htmlentities($risk[0]['CLASSIC_likelihood'], ENT_QUOTES, 'UTF-8', false);
                $CLASSIC_impact = htmlentities($risk[0]['CLASSIC_impact'], ENT_QUOTES, 'UTF-8', false);
                $AccessVector = htmlentities($risk[0]['CVSS_AccessVector'], ENT_QUOTES, 'UTF-8', false);
                $AccessComplexity = htmlentities($risk[0]['CVSS_AccessComplexity'], ENT_QUOTES, 'UTF-8', false);
                $Authentication = htmlentities($risk[0]['CVSS_Authentication'], ENT_QUOTES, 'UTF-8', false);
                $ConfImpact = htmlentities($risk[0]['CVSS_ConfImpact'], ENT_QUOTES, 'UTF-8', false);
                $IntegImpact = htmlentities($risk[0]['CVSS_IntegImpact'], ENT_QUOTES, 'UTF-8', false);
                $AvailImpact = htmlentities($risk[0]['CVSS_AvailImpact'], ENT_QUOTES, 'UTF-8', false);
                $Exploitability = htmlentities($risk[0]['CVSS_Exploitability'], ENT_QUOTES, 'UTF-8', false);
                $RemediationLevel = htmlentities($risk[0]['CVSS_RemediationLevel'], ENT_QUOTES, 'UTF-8', false);
                $ReportConfidence = htmlentities($risk[0]['CVSS_ReportConfidence'], ENT_QUOTES, 'UTF-8', false);
                $CollateralDamagePotential = htmlentities($risk[0]['CVSS_CollateralDamagePotential'], ENT_QUOTES, 'UTF-8', false);
                $TargetDistribution = htmlentities($risk[0]['CVSS_TargetDistribution'], ENT_QUOTES, 'UTF-8', false);
                $ConfidentialityRequirement = htmlentities($risk[0]['CVSS_ConfidentialityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $IntegrityRequirement = htmlentities($risk[0]['CVSS_IntegrityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $AvailabilityRequirement = htmlentities($risk[0]['CVSS_AvailabilityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $DREADDamagePotential = htmlentities($risk[0]['DREAD_DamagePotential'], ENT_QUOTES, 'UTF-8', false);
                $DREADReproducibility = htmlentities($risk[0]['DREAD_Reproducibility'], ENT_QUOTES, 'UTF-8', false);
                $DREADExploitability = htmlentities($risk[0]['DREAD_Exploitability'], ENT_QUOTES, 'UTF-8', false);
                $DREADAffectedUsers = htmlentities($risk[0]['DREAD_AffectedUsers'], ENT_QUOTES, 'UTF-8', false);
                $DREADDiscoverability = htmlentities($risk[0]['DREAD_Discoverability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPSkillLevel = htmlentities($risk[0]['OWASP_SkillLevel'], ENT_QUOTES, 'UTF-8', false);
                $OWASPMotive = htmlentities($risk[0]['OWASP_Motive'], ENT_QUOTES, 'UTF-8', false);
                $OWASPOpportunity = htmlentities($risk[0]['OWASP_Opportunity'], ENT_QUOTES, 'UTF-8', false);
                $OWASPSize = htmlentities($risk[0]['OWASP_Size'], ENT_QUOTES, 'UTF-8', false);
                $OWASPEaseOfDiscovery = htmlentities($risk[0]['OWASP_EaseOfDiscovery'], ENT_QUOTES, 'UTF-8', false);
                $OWASPEaseOfExploit = htmlentities($risk[0]['OWASP_EaseOfExploit'], ENT_QUOTES, 'UTF-8', false);
                $OWASPAwareness = htmlentities($risk[0]['OWASP_Awareness'], ENT_QUOTES, 'UTF-8', false);
                $OWASPIntrusionDetection = htmlentities($risk[0]['OWASP_IntrusionDetection'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfConfidentiality = htmlentities($risk[0]['OWASP_LossOfConfidentiality'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfIntegrity = htmlentities($risk[0]['OWASP_LossOfIntegrity'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfAvailability = htmlentities($risk[0]['OWASP_LossOfAvailability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfAccountability = htmlentities($risk[0]['OWASP_LossOfAccountability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPFinancialDamage = htmlentities($risk[0]['OWASP_FinancialDamage'], ENT_QUOTES, 'UTF-8', false);
                $OWASPReputationDamage = htmlentities($risk[0]['OWASP_ReputationDamage'], ENT_QUOTES, 'UTF-8', false);
                $OWASPNonCompliance = htmlentities($risk[0]['OWASP_NonCompliance'], ENT_QUOTES, 'UTF-8', false);
                $OWASPPrivacyViolation = htmlentities($risk[0]['OWASP_PrivacyViolation'], ENT_QUOTES, 'UTF-8', false);
                $custom = htmlentities($risk[0]['Custom'], ENT_QUOTES, 'UTF-8', false);

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
                        $current_solution = "";
                        $security_requirements = "";
                        $security_recommendations = "";
                }
                // If a mitigation exists
                else
                {
                        // Set the mitigation values
                        $mitigation_date = htmlentities($mitigation[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
                        $mitigation_date = date(DATETIME, strtotime($mitigation_date));
                        $planning_strategy = htmlentities($mitigation[0]['planning_strategy'], ENT_QUOTES, 'UTF-8', false);
                        $mitigation_effort = htmlentities($mitigation[0]['mitigation_effort'], ENT_QUOTES, 'UTF-8', false);
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
                        $review_date = htmlentities($mgmt_reviews[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
                        $review_date = date(DATETIME, strtotime($review_date));
                        $review = htmlentities($mgmt_reviews[0]['review'], ENT_QUOTES, 'UTF-8', false);
                        $next_step = htmlentities($mgmt_reviews[0]['next_step'], ENT_QUOTES, 'UTF-8', false);
      $next_review = htmlentities(next_review($color, $id, $next_review, false), ENT_QUOTES, 'UTF-8', false);
                        $reviewer = htmlentities($mgmt_reviews[0]['reviewer'], ENT_QUOTES, 'UTF-8', false);
                        $comments = $mgmt_reviews[0]['comments'];
                }
        }

        // Check if a new risk mitigation was submitted and the user has permissions to plan mitigations
        if ((isset($_POST['submit'])) && $plan_mitigations)
        {
                $status = "Mitigation Planned";
                $planning_strategy = (int)addslashes($_POST['planning_strategy']);
    $mitigation_effort = (int)addslashes($_POST['mitigation_effort']);
                $current_solution = addslashes($_POST['current_solution']);
                $security_requirements = addslashes($_POST['security_requirements']);
                $security_recommendations = addslashes($_POST['security_recommendations']);

                // Submit mitigation
                submit_mitigation($id, $status, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations);

                // Audit log
                $risk_id = $id;
                $message = "A mitigation was submitted for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                write_log($risk_id, $_SESSION['uid'], $message);

                // Redirect back to the page the workflow started on
                header("Location: " . $_SESSION["workflow_start"] . "&mitigated=true");
        }

    }

    else if($_GET['page'] == '8') {
      // Check if the user has access to close risks
        if (!isset($_SESSION["close_risks"]) || $_SESSION["close_risks"] != 1)
        {
                $close_risks = false;
                $alert = "bad";
                $alert_message = "You do not have permission to close risks.  Any attempts to close risks will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
        }
        else $close_risks = true;

        // Check if a risk ID was sent
        if (isset($_GET['id']) || isset($_POST['id']))
        {
                if (isset($_GET['id']))
    {
      $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8', false);
    }
    else if (isset($_POST['id']))
    {
      $id = htmlentities($_POST['id'], ENT_QUOTES, 'UTF-8', false);
    }

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

                // Get the details of the risk
                $risk = get_risk_by_id($id);

                // If the risk was found use the values for the risk
                if (count($risk) != 0)
                {
                        $status = htmlentities($risk[0]['status'], ENT_QUOTES, 'UTF-8', false);
                        $subject = htmlentities($risk[0]['subject'], ENT_QUOTES, 'UTF-8', false);
                        $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
                }
                // If the risk was not found use null values
                else
                {
                        $status = "Risk ID Does Not Exist";
                        $subject = "N/A";
                        $calculated_risk = "0.0";
                }
        }

        // Check if a risk closure was submitted and the user has permissions to close risks
        if ((isset($_POST['submit'])) && $close_risks)
        {
                $status = "Closed";
                $close_reason = addslashes($_POST['close_reason']);
                $note = addslashes($_POST['note']);

                // Close the risk
                close_risk($id, $_SESSION['uid'], $status, $close_reason, $note);

                // Audit log
                $risk_id = $id;
                $message = "Risk ID \"" . $risk_id . "\" was marked as closed by username \"" . $_SESSION['user'] . "\".";
                write_log($risk_id, $_SESSION['uid'], $message);

                // Check that the id is a numeric value
                if (is_numeric($id))
                {
                        // Create the redirection location
      $url = "index.php?module=1&page=5&id=" . $id . "&closed=true";

                        // Redirect to plan mitigations page
                        header("Location: " . $url); 
                }
        }
    }

    else if($_GET['page'] == '9') {
        // Check if a risk ID was sent
        if (isset($_GET['id']) || isset($_POST['id']))
        {
                if (isset($_GET['id']))
    {
      $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8', false);
    }
    else if (isset($_POST['id']))
    {
      $id = htmlentities($_POST['id'], ENT_QUOTES, 'UTF-8', false);
    }

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

    // Reopen the risk
    reopen_risk($id);

                // Audit log
                $risk_id = $id;
                $message = "Risk ID \"" . $risk_id . "\" was reopened by username \"" . $_SESSION['user'] . "\".";
                write_log($risk_id, $_SESSION['uid'], $message);

                // Check that the id is a numeric value
                if (is_numeric($id))
                {
                        // Create the redirection location
      $url = "index.php?module=1&page=5&id=" . $id . "&reopened=true";

                        // Redirect to plan mitigations page
                        header("Location: " . $url);
                }
        }
  else header('Location: index.php?module=2&page=11');
    }

    else if($_GET['page'] == '10') {
      // Check if a risk ID was sent
        if (isset($_GET['id']) || isset($_POST['id']))
        {
                if (isset($_GET['id']))
    {
      $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8', false);
    }
    else if (isset($_POST['id']))
    {
      $id = htmlentities($_POST['id'], ENT_QUOTES, 'UTF-8', false);
    }

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

                // Get the details of the risk
                $risk = get_risk_by_id($id);

                // If the risk was found use the values for the risk
                if (count($risk) != 0)
                {
                        $status = htmlentities($risk[0]['status'], ENT_QUOTES, 'UTF-8', false);
                        $subject = htmlentities($risk[0]['subject'], ENT_QUOTES, 'UTF-8', false);
                        $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
                }
                // If the risk was not found use null values
                else
                {
                        $status = "Risk ID Does Not Exist";
                        $subject = "N/A";
                        $calculated_risk = "0.0";
                }
        }

        // Check if a new risk mitigation was submitted
        if (isset($_POST['submit']))
        {
                $comment = addslashes($_POST['comment']);

                // Add the comment
                add_comment($id, $_SESSION['uid'], $comment);

                // Audit log
                $risk_id = $id;
                $message = "A comment was added to risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
                write_log($risk_id, $_SESSION['uid'], $message);

    // Check that the id is a numeric value
    if (is_numeric($id))
    {
                  // Create the redirection location
                  $url = "index.php?module=1&page=5&id=" . $id . "&comment=true";

                  // Redirect to plan mitigations page
                  header("Location: " . $url); 
    }
        }
    }

    else if($_GET['page'] == '11') {

        // Check if a risk ID was sent
        if (isset($_GET['id']))
        {
                $id = htmlentities($_GET['id'], ENT_QUOTES, 'UTF-8', false);

                // Get the details of the risk
                $risk = get_risk_by_id($id);

                $status = htmlentities($risk[0]['status'], ENT_QUOTES, 'UTF-8', false);
                $subject = htmlentities(stripslashes($risk[0]['subject']), ENT_QUOTES, 'UTF-8', false);
                $calculated_risk = htmlentities($risk[0]['calculated_risk'], ENT_QUOTES, 'UTF-8', false);
    $mgmt_review = htmlentities($risk[0]['mgmt_review'], ENT_QUOTES, 'UTF-8', false);

                $scoring_method = htmlentities($risk[0]['scoring_method'], ENT_QUOTES, 'UTF-8', false);
                $CLASSIC_likelihood = htmlentities($risk[0]['CLASSIC_likelihood'], ENT_QUOTES, 'UTF-8', false);
                $CLASSIC_impact = htmlentities($risk[0]['CLASSIC_impact'], ENT_QUOTES, 'UTF-8', false);
                $AccessVector = htmlentities($risk[0]['CVSS_AccessVector'], ENT_QUOTES, 'UTF-8', false);
                $AccessComplexity = htmlentities($risk[0]['CVSS_AccessComplexity'], ENT_QUOTES, 'UTF-8', false);
                $Authentication = htmlentities($risk[0]['CVSS_Authentication'], ENT_QUOTES, 'UTF-8', false);
                $ConfImpact = htmlentities($risk[0]['CVSS_ConfImpact'], ENT_QUOTES, 'UTF-8', false);
                $IntegImpact = htmlentities($risk[0]['CVSS_IntegImpact'], ENT_QUOTES, 'UTF-8', false);
                $AvailImpact = htmlentities($risk[0]['CVSS_AvailImpact'], ENT_QUOTES, 'UTF-8', false);
                $Exploitability = htmlentities($risk[0]['CVSS_Exploitability'], ENT_QUOTES, 'UTF-8', false);
                $RemediationLevel = htmlentities($risk[0]['CVSS_RemediationLevel'], ENT_QUOTES, 'UTF-8', false);
                $ReportConfidence = htmlentities($risk[0]['CVSS_ReportConfidence'], ENT_QUOTES, 'UTF-8', false);
                $CollateralDamagePotential = htmlentities($risk[0]['CVSS_CollateralDamagePotential'], ENT_QUOTES, 'UTF-8', false);
                $TargetDistribution = htmlentities($risk[0]['CVSS_TargetDistribution'], ENT_QUOTES, 'UTF-8', false);
                $ConfidentialityRequirement = htmlentities($risk[0]['CVSS_ConfidentialityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $IntegrityRequirement = htmlentities($risk[0]['CVSS_IntegrityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $AvailabilityRequirement = htmlentities($risk[0]['CVSS_AvailabilityRequirement'], ENT_QUOTES, 'UTF-8', false);
                $DREADDamagePotential = htmlentities($risk[0]['DREAD_DamagePotential'], ENT_QUOTES, 'UTF-8', false);
                $DREADReproducibility = htmlentities($risk[0]['DREAD_Reproducibility'], ENT_QUOTES, 'UTF-8', false);
                $DREADExploitability = htmlentities($risk[0]['DREAD_Exploitability'], ENT_QUOTES, 'UTF-8', false);
                $DREADAffectedUsers = htmlentities($risk[0]['DREAD_AffectedUsers'], ENT_QUOTES, 'UTF-8', false);
                $DREADDiscoverability = htmlentities($risk[0]['DREAD_Discoverability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPSkillLevel = htmlentities($risk[0]['OWASP_SkillLevel'], ENT_QUOTES, 'UTF-8', false);
                $OWASPMotive = htmlentities($risk[0]['OWASP_Motive'], ENT_QUOTES, 'UTF-8', false);
                $OWASPOpportunity = htmlentities($risk[0]['OWASP_Opportunity'], ENT_QUOTES, 'UTF-8', false);
                $OWASPSize = htmlentities($risk[0]['OWASP_Size'], ENT_QUOTES, 'UTF-8', false);
                $OWASPEaseOfDiscovery = htmlentities($risk[0]['OWASP_EaseOfDiscovery'], ENT_QUOTES, 'UTF-8', false);
                $OWASPEaseOfExploit = htmlentities($risk[0]['OWASP_EaseOfExploit'], ENT_QUOTES, 'UTF-8', false);
                $OWASPAwareness = htmlentities($risk[0]['OWASP_Awareness'], ENT_QUOTES, 'UTF-8', false);
                $OWASPIntrusionDetection = htmlentities($risk[0]['OWASP_IntrusionDetection'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfConfidentiality = htmlentities($risk[0]['OWASP_LossOfConfidentiality'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfIntegrity = htmlentities($risk[0]['OWASP_LossOfIntegrity'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfAvailability = htmlentities($risk[0]['OWASP_LossOfAvailability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPLossOfAccountability = htmlentities($risk[0]['OWASP_LossOfAccountability'], ENT_QUOTES, 'UTF-8', false);
                $OWASPFinancialDamage = htmlentities($risk[0]['OWASP_FinancialDamage'], ENT_QUOTES, 'UTF-8', false);
                $OWASPReputationDamage = htmlentities($risk[0]['OWASP_ReputationDamage'], ENT_QUOTES, 'UTF-8', false);
                $OWASPNonCompliance = htmlentities($risk[0]['OWASP_NonCompliance'], ENT_QUOTES, 'UTF-8', false);
                $OWASPPrivacyViolation = htmlentities($risk[0]['OWASP_PrivacyViolation'], ENT_QUOTES, 'UTF-8', false);
                $custom = htmlentities($risk[0]['Custom'], ENT_QUOTES, 'UTF-8', false);

    // Get the management reviews for the risk
    $mgmt_reviews = get_review_by_id($id);

    $review_date = htmlentities($mgmt_reviews[0]['submission_date'], ENT_QUOTES, 'UTF-8', false);
    $review = htmlentities($mgmt_reviews[0]['review'], ENT_QUOTES, 'UTF-8', false);
    $reviewer = htmlentities($mgmt_reviews[0]['reviewer'], ENT_QUOTES, 'UTF-8', false);
    $next_step = htmlentities(stripslashes($mgmt_reviews[0]['next_step']), ENT_QUOTES, 'UTF-8', false);
    $comments = htmlentities(stripslashes($mgmt_reviews[0]['comments']), ENT_QUOTES, 'UTF-8', false);

                if ($review_date == "")
                {
                        $review_date = "N/A";
                }
                else $review_date = date(DATETIME, strtotime($review_date));
        }
    }

?>

<!doctype html>
<html>
  
  <head>
    <script src="js/jquery-1.10.1.min.js"></script>
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.css">
    <link rel="stylesheet" href="css/jquery-ui.min.css"> 
    <link rel="stylesheet" href="css/divshot-util.css">
    <link rel="stylesheet" href="css/divshot-canvas.css">
    <link rel="stylesheet" href="css/display.css">
    <script type="text/javascript">
      function popupcvss()
      {
        my_window = window.open('management/cvss_rating.php','popupwindow','width=850,height=680,menu=0,status=0');
      }

      function popupdread()
      {
        my_window = window.open('management/dread_rating.php','popupwindow','width=660,height=500,menu=0,status=0');
      }

      function popupowasp()
      {
        my_window = window.open('management/owasp_rating.php','popupwindow','width=665,height=570,menu=0,status=0');
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
    <?php 
      if($_GET['page'] == '3'){
    ?>
        <style>
        <?php
          // Get the projects
          $projects = get_projects();

          // Get the total number of projects
          $count = count($projects);

          // Initialize the counter
          $counter = 1;

          // For each project created
          foreach ($projects as $project)
          {
                  // Get the project ID
                  $id = $project['value'];

                  echo "#sortable-" . $id . " li";

                  // If it's not the last one
                  if ($counter != $count)
                  {
                          echo ", ";
                          $counter++;
                  }
          }

          echo ", #statussortable-1 li, #statussortable-2 li, #statussortable-3 li, #statussortable-4 li";
          echo " { margin: 0 5px 5px 5px; padding: 5px; font-size: 0.75em; width: 120px; }\n";
        ?>
        </style>
        <script>
              $(function() {
        <?php
          echo "$( \"";

          // Initialize the counter
          $counter = 1;

          // For each project created
          foreach ($projects as $project)
          {
                  // Get the project ID
                  $id = $project['value'];

                  echo "#sortable-" . $id;

                  // If it's not the last one
                  if ($counter != $count)
                  {
                          echo ", ";
                          $counter++;
                  }
          }

          echo ", #statussortable-1, #statussortable-2, #statussortable-3, #statussortable-4";
          echo "\" ).sortable().disableSelection();\n";
        ?>
                var $tabs = $( "#tabs" ).tabs();
                var $tab_items = $( "ul:first li", $tabs ).droppable({
                  accept: ".connectedSortable li",
                  hoverClass: "ui-state-hover",
                  drop: function( event, ui ) {
                    var $item = $( this );
                    var $list = $( $item.find( "a" ).attr( "href" ) )
                    .find( ".connectedSortable" );
                    ui.draggable.hide( "slow", function() {
                      $tabs.tabs( "option", "active", $tab_items.index( $item ) );
                      $( this ).appendTo( $list ).show( "slow" );
                    });
                    $list.each(function() {
                      // Get the project ID that was just dropped into
                      var id = $(this).attr("id");
                      var part = id.split("-");
                      var project_id = part[1];

                      // Get the risk ID that was just dropped
                      var dragged_risk_id = $(ui.draggable).attr("id");

                      // Risk name to update
                      var risk_name = "risk_" + dragged_risk_id;

                      // Update the risk input with the proper value
                      document.getElementsByName(risk_name)[0].value = project_id;
                    });
                  }
                });

                var $statustabs = $( "#statustabs" ).tabs();
                var $status_tab_items = $( "ul:first li", $statustabs ).droppable({
                  accept: ".connectedSortable li",
                  hoverClass: "ui-state-hover",
                  drop: function( event, ui ) {
                    var $item = $( this );
                    var $list = $( $item.find( "a" ).attr( "href" ) )
                    .find( ".connectedSortable" );
                    ui.draggable.hide( "slow", function() {
                      $statustabs.tabs( "option", "active", $status_tab_items.index( $item ) );
                      $( this ).appendTo( $list ).show( "slow" );
                    });
                    $list.each(function() {
                      // Get the status ID that was just dropped into
                      var id = $(this).attr("id");
                      var part = id.split("-");
                      var project_id = part[1];

                      // Get the project ID that was just dropped
                      var dragged_project_id = $(ui.draggable).attr("id");

                      // Project name to update
                      var project_name = "project_" + dragged_project_id;

                      // Update the risk input with the proper value
                      document.getElementsByName(project_name)[0].value = project_id;
                    });
                  }
                });

              });
            </script>
            <script>
              $(function() {
                $( "#prioritize" ).sortable({
                  update: function(event, ui)
                  {
                    // Create an array with the new order
                    var order = $( "#prioritize" ).sortable('toArray');

                    for(var key in order) {
                      var val = order[key];
                      var part = val.split("_");

                      // Update each hidden field used to store the list item position
                      document.getElementById("order"+part[1]).value = key;
                    }
                  }
                });

                $( "#prioritize" ).disableSelection();
              });
            </script>
    <?php
      }
      if($_GET['page'] == '5' || $_GET['page'] == '6' || $_GET['page'] == '7' || $_GET['page'] == '11'){
    ?>
          <script>
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
     <?php
      }
    ?>
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.css">
    <link rel="stylesheet" href="css/divshot-util.css">
    <link rel="stylesheet" href="css/divshot-canvas.css">
    <link rel="stylesheet" href="css/display.css">
    <link rel="stylesheet" href="css/prioritize.css">
    <link rel="stylesheet" href="css/jquery-ui.min.css">
    <div class="navbar">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="http://www.simplerisk.org/">SimpleRisk</a>
          <div class="navbar-content">
            <ul class="nav">
              <li>
                <a href="index.php?module=0"><?php echo $lang['Home']; ?></a> 
              </li>
              <li class="active">
                <a href="index.php?module=1"><?php echo $lang['RiskManagement']; ?></a> 
              </li>
              <li>
                <a href="index.php?module=2"><?php echo $lang['Reporting']; ?></a> 
              </li>
<?php
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == "1")
{
          echo "<li>\n";
          echo "<a href=\"index.php?module=3\">". $lang['Configure'] ."</a>\n";
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
          echo "<a href=\"index.php?module=4\">". $lang['MyProfile'] ."</a>\n";
          echo "</li>\n";
          echo "<li>\n";
          echo "<a href=\"logout.php\">". $lang['Logout'] ."</a>\n";
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
            <li <?php if(!isset($_GET['page'])) { ?>class="active"<? } ?>>
              <a href="index.php?module=1">I. <?php echo $lang['SubmitYourRisks']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '1' || $_GET['page'] == '7') { ?>class="active"<? } ?>>
              <a href="index.php?module=1&page=1">II. <?php echo $lang['PlanYourMitigations']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '2' || $_GET['page'] == '6') { ?>class="active"<? } ?>>
              <a href="index.php?module=1&page=2">III. <?php echo $lang['PerformManagementReviews']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '3') { ?>class="active"<? } ?>>
              <a href="index.php?module=1&page=3">IV. <?php echo $lang['PrioritizeForProjectPlanning']; ?></a> 
            </li>
            <li <?php if($_GET['page'] == '4' || $_GET['page'] == '5' || $_GET['page'] == '8' || $_GET['page'] == '10' || $_GET['page'] == '11') { ?>class="active"<? } ?>>
              <a href="index.php?module=1&page=4">V. <?php echo $lang['ReviewRisksRegularly']; ?></a> 
            </li>
          </ul>
        </div>
        <div class="span9">
          <?php 
            if(!isset($_GET['page'])) {
          ?>
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <h4><?php echo $lang['DocumentANewRisk']; ?></h4>
                <p><?php echo $lang['UseThisFormHelp']; ?>.</p>
                <form name="submit_risk" method="post" action="">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="200px"><?php echo $lang['Subject']; ?>:</td>
                  <td><input maxlength="100" name="subject" id="subject" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['ExternalReferenceId']; ?>:</td>
                  <td><input maxlength="20" size="20" name="reference_id" id="reference_id" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['ControlRegulation']; ?>:</td>
                  <td><?php create_dropdown("regulation"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['ControlNumber']; ?>:</td>
                  <td><input maxlength="20" name="control_number" id="control_number" class="input-medium" type="text"></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['SiteLocation']; ?>:</td>
                  <td><?php create_dropdown("location"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Category']; ?>:</td>
                  <td><?php create_dropdown("category"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Team']; ?>:</td>
                  <td><?php create_dropdown("team"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Technology']; ?>:</td>
                  <td><?php create_dropdown("technology"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['Owner']; ?>:</td>
                  <td><?php create_dropdown("user", NULL, "owner"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['OwnersManager']; ?>:</td>
                  <td><?php create_dropdown("user", NULL, "manager"); ?></td>
                </tr>
                <tr>
                  <td width="200px"><?php echo $lang['RiskScoringMethod']; ?>:</td>
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
                        <td width="197px"><?php echo $lang['CurrentLikelihood']; ?>:</td>
                        <td><?php create_dropdown("likelihood"); ?></td>
                      </tr>
                      <tr>
                        <td width="197px"><?php echo $lang['CurrentImpact']; ?>:</td>
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
                        <td width="197px"><?php echo $lang['CustomValue']; ?>:</td>
                        <td><input type="text" name="Custom" id="Custom" value="" /> (Must be a numeric value between 0 and 10)</td>
                      </tr>
                    </table>
		  </div>
                  <tr>
                    <td width="200px"><?php echo $lang['RiskAssessment']; ?></td>
                    <td><textarea name="assessment" cols="50" rows="3" id="assessment"></textarea></td>
                  </tr>
                  <tr>
                    <td width="200px"><?php echo $lang['AdditionalNotes']; ?></td>
                    <td><textarea name="notes" cols="50" rows="3" id="notes"></textarea></td>
                  </tr>
                </table>
                <div class="form-actions">
                  <button type="submit" name="submit" class="btn btn-primary"><?php echo $lang['Submit']; ?></button>
                  <input class="btn" value="<?php echo $lang['Reset']; ?>" type="reset"> 
                </div>
                </form>
              </div>
            </div>
          </div>
          <?php
            } else {
                switch ($_GET['page']) {
                    case 1:
                      get_plan_mitigations(); 
                      break;
                    case 2: 
                      get_management_review(); 
                      break;
                    case 3:
                      get_prioritize_planning();
                      break;
                    case 4:
                      get_review_risks();
                      break;
                    case 5:
                      get_view($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, 
        $team, $technology, $owner, $manager, $assessment, $notes, $mitigation_date, $planning_strategy, $mitigation_effort, 
        $current_solution, $security_requirements, $security_recommendations, $review_date, $reviewer, $review, 
        $next_step, $next_review, $comments);
                      break;
                    case 6:
                      get_mgmt_review($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, 
        $team, $technology, $owner, $manager, $assessment, $notes, $mitigation_date, $planning_strategy, $mitigation_effort, 
        $current_solution, $security_requirements, $security_recommendations, $review_date, $reviewer, $review, 
        $next_step, $next_review, $comments);
                      break;
                    case 7:
                      get_mitigate($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, 
        $team, $technology, $owner, $manager, $assessment, $notes, $mitigation_date, $planning_strategy, $mitigation_effort, 
        $current_solution, $security_requirements, $security_recommendations, $review_date, $reviewer, $review, 
        $next_step, $next_review, $comments);
                      break;
                    case 8:
                      get_close($id, $calculated_risk, $subject, $status);
                      break;
                    case 9:
                      break;
                    case 10:
                      get_comment($id, $calculated_risk, $subject, $status);
                      break;
                    case 11:
                      get_allreviews($id, $calculated_risk, $subject, $status, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, 
        $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, 
        $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, 
        $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, 
        $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, 
        $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, 
        $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, 
        $OWASPPrivacyViolation, $custom);
                      break;
                    default:
                      break;
                }
            }
          ?>
        </div>
      </div>
    </div>
  </body>

</html>
