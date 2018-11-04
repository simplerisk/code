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

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

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

// Load CSRF Magic
require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

function csrf_startup() {
    csrf_conf('rewrite-js', $_SESSION['base_url'].'/includes/csrf-magic/csrf-magic.js');
}

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
  set_unauthenticated_redirect();
  header("Location: ../index.php");
  exit(0);
}

// Enforce that the user has access to risk management
enforce_permission_riskmanagement();

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

  // If the classic risk was updated and the user has the ability to modify the risk
  if (isset($_POST['update_classic']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access)
  {
    $CLASSIC_likelihood = (int)$_POST['likelihood'];
    $CLASSIC_impact = (int)$_POST['impact'];

    // Update the risk scoring
    update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);
  }
  // If the cvss risk was updated and the user has the ability to modify the risk
  else if (isset($_POST['update_cvss']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access)
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
  else if (isset($_POST['update_dread']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access)
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
  else if (isset($_POST['update_owasp']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access)
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
  else if (isset($_POST['update_custom']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access)
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
    $source = $risk[0]['source'];
    $category = $risk[0]['category'];
    $team = $risk[0]['team'];
    $additional_stakeholders = $risk[0]['additional_stakeholders'];
    $technology = $risk[0]['technology'];
    $owner = $risk[0]['owner'];
    $manager = $risk[0]['manager'];
    $assessment = $risk[0]['assessment'];
    $notes = $risk[0]['notes'];
    $submission_date = $risk[0]['submission_date'];
    //$submission_date = date( "m/d/Y", strtotime( $sub_date ) );
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
  }
  // If the risk was not found use null values
  else
  {
    $submitted_by = "";
    // If Risk ID exists.
    if(check_risk_by_id($id)){
        $status = $lang["RiskDisplayPermission"];
    }
    // If Risk ID does not exist.
    else{
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
  }

  // If the current scoring method was changed to Classic
  if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 1 && $access)
  {
    // Set the new scoring method
    $scoring_method = change_scoring_method($id, "1");

    // Update the classic score
    $calculated_risk = update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);

    // Display an alert
    set_alert(true, "good", "The scoring method has been successfully changed to Classic.");
  }
  // If the current scoring method was changed to CVSS
  else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 2 && $access)
  {
    // Set the new scoring method
    $scoring_method = change_scoring_method($id, "2");

    // Update the cvss score
    $calculated_risk = update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);

    // Display an alert
    set_alert(true, "good", "The scoring method has been successfully changed to CVSS.");
  }
  // If the current scoring method was changed to DREAD
  else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 3 && $access)
  {
    // Set the new scoring method
    $scoring_method = change_scoring_method($id, "3");

    // Update the dread score
    $calculated_risk = update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);

    // Display an alert
    set_alert(true, "good", "The scoring method has been successfully changed to DREAD.");
  }
  // If the current scoring method was changed to OWASP
  else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 4 && $access)
  {
    // Set the new scoring method
    $scoring_method = change_scoring_method($id, "4");

    // Update the owasp score
    $calculated_risk = update_owasp_score($id, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);

    // Display an alert
    set_alert(true, "good", "The scoring method has been successfully changed to OWASP.");
  }
  // If the current scoring method was changed to Custom
  else if (isset($_GET['scoring_method']) && $_GET['scoring_method'] == 5 && $access)
  {
    // Set the new scoring method
    $scoring_method = change_scoring_method($id, "5");

    // Update the custom score
    $calculated_risk = update_custom_score($id, $custom);

    // Display an alert
    set_alert(true, "good", "The scoring method has been successfully changed to Custom.");
  }

  if ($submission_date == "")
  {
    $submission_date = "N/A";
  }
  else $submission_date = date(get_default_date_format(), strtotime($submission_date));

  // Get the mitigation for the risk
  $mitigation = get_mitigation_by_id($id);

  // If a mitigation exists for the risk and the user is allowed to access
  if ($mitigation == true && $access)
  {
    // Set the mitigation values
    $mitigation_date = $mitigation[0]['submission_date'];
    $mitigation_date = date(get_default_date_format(), strtotime($mitigation_date));
    $planning_strategy = $mitigation[0]['planning_strategy'];
    $mitigation_effort = $mitigation[0]['mitigation_effort'];
    $mitigation_cost = $mitigation[0]['mitigation_cost'];
    $mitigation_owner = $mitigation[0]['mitigation_owner'];
    $mitigation_team = $mitigation[0]['mitigation_team'];
    $current_solution = $mitigation[0]['current_solution'];
    $security_requirements = $mitigation[0]['security_requirements'];
    $security_recommendations = $mitigation[0]['security_recommendations'];
    $planning_date = ($mitigation[0]['planning_date'] && $mitigation[0]['planning_date'] != "0000-00-00") ? date(get_default_date_format(), strtotime($mitigation[0]['planning_date'])) : "";
    $mitigation_percent = (isset($mitigation[0]['mitigation_percent']) && $mitigation[0]['mitigation_percent'] >= 0 && $mitigation[0]['mitigation_percent'] <= 100) ? $mitigation[0]['mitigation_percent'] : 0;
    $mitigation_controls = isset($mitigation[0]['mitigation_controls']) ? $mitigation[0]['mitigation_controls'] : "";
  }
  // Otherwise
  else
  {
    // Set the values to empty
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
  }else
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

// If the risk details were updated
if (isset($_POST['update_details']))
{
  // If the user has permission to modify risks
  if (isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access)
  {
    $reference_id = $_POST['reference_id'];
    $regulation = (int)$_POST['regulation'];
    $control_number = $_POST['control_number'];
    $location = (int)$_POST['location'];
    $source = (int)$_POST['source'];
    $category = (int)$_POST['category'];
    $team = (int)$_POST['team'];
    $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
    $technology = (int)$_POST['technology'];
    $owner = (int)$_POST['owner'];
    $manager = (int)$_POST['manager'];
    $assessment = try_encrypt($_POST['assessment']);
    $notes = try_encrypt($_POST['notes']);
    $assets = $_POST['assets'];
    $submission_date = $_POST['submission_date'];
//    $tmp_s_date = date("Y-m-d H:i:s", strtotime($submission_date));
    $tmp_s_date = get_standard_date_from_default_format($submission_date);
    // Update risk
    $error = update_risk($id);
    
    // If customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
        
        // Save custom fields
        save_risk_custom_field_values($id);
    }
    
    // Classic Risk Scoring Inputs
    $scoring_method = (int)$_POST['scoring_method'];
    $CLASSIC_likelihood = (int)$_POST['likelihood'];
    $CLASSIC_impact =(int) $_POST['impact'];
    
//    if($risk[0]['scoring_method'] != $scoring_method || $risk[0]['CLASSIC_likelihood'] != $CLASSIC_likelihood || $risk[0]['CLASSIC_impact'] != $CLASSIC_impact ){
        // Classic Risk Scoring Inputs
//            $CLASSIClikelihood = (int)$_POST['likelihood'];
//            $CLASSICimpact =(int) $_POST['impact'];

        // CVSS Risk Scoring Inputs
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

        // DREAD Risk Scoring Inputs
        $DREADDamagePotential = (int)$_POST['DREADDamage'];
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
        
        update_risk_scoring($id, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);        
        
//    }
    
    if ($error == 1)
    {
      // Display an alert
      set_alert(true, "good", "The risk has been successfully modified.");
      
      header("Location: " .$_SESSION["workflow_start"] . "?id=". $id  );
    }
    else
    {
      // Display an alert
      set_alert(true, "bad", $error);
    }
  }
  // Otherwise, the user did not have permission to modify risks
  else 
  {
    // Display an alert
    set_alert(true, "bad", "You do not have permission to modify risks.  Your attempt to modify the details of this risk was not recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
  }
}

// If the user has selected to edit the risk details and does not have permission
if ((isset($_POST['edit_details'])) && ($_SESSION['modify_risks'] != 1))
{
  // Display an alert
  set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
}

// Check if the user has access to plan mitigations
if ((isset($_POST['update_mitigation']) || isset($_POST['edit_mitigation'])) && (!isset($_SESSION["plan_mitigations"]) || $_SESSION["plan_mitigations"] != 1))
{
    // Display an alert
    set_alert(true, "bad", $lang['MitigationPermissionMessage']);
}
else 
{
    // Check if a mitigation was updated
    if (isset($_POST['update_mitigation']) && $access)
    {
      $planning_strategy = (int)$_POST['planning_strategy'];
      $mitigation_effort = (int)$_POST['mitigation_effort'];
      $mitigation_cost = (int)$_POST['mitigation_cost'];
      $mitigation_owner = (int)$_POST['mitigation_owner'];
      $mitigation_team = (int)$_POST['mitigation_team'];
      $current_solution = try_encrypt($_POST['current_solution']);
      $security_requirements = try_encrypt($_POST['security_requirements']);
      $security_recommendations = try_encrypt($_POST['security_recommendations']);
      $planning_date = $_POST['planning_date'];

      if (!validate_date($planning_date, get_default_date_format()))
      {
        $planning_date = "0000-00-00";
      }
      // Otherwise, set the proper format for submitting to the database
      else
      {
    //    $planning_date = date("Y-m-d", strtotime($planning_date));
        $planning_date = get_standard_date_from_default_format($planning_date);
      }

      // If we don't yet have a mitigation
      if ($mitigation_id == 0)
      {
        $status = "Mitigation Planned";

        // Submit mitigation and get the mitigation date back
        submit_mitigation($id, $status, $_POST);
      }
      else
      {
        // Update mitigation and get the mitigation date back
        update_mitigation($id, $_POST);
      }


      // Display an alert
      set_alert(true, "good", "The risk mitigation has been successfully modified.");

      // Redirect back to the page the workflow started on
      header("Location: " . $_SESSION["workflow_start"] . "?id=". $id . "&type=1");
    }
}

// If the user updated the subject and they have the permission to modify the risk
if (isset($_POST['update_subject']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1 && $access)
{
  $id = $_POST['riskid'];
  $new_subject = $_POST['subject'];
  if ($new_subject != '')
  {
    $subject = try_encrypt($new_subject);
    update_risk_subject($id, $subject); 
    set_alert(true, "good", "The subject has been successfully modified."); 
  } else
  {
    set_alert(true, "bad", "The subject of a risk cannot be empty.");
  }
    
}
// Otherwise, if the user updated the subject and does not have the permission to modify the risk
else if (isset($_POST['update_subject']) && (!isset($_SESSION["modify_risks"]) || $_SESSION["modify_risks"] != 1))
{
  // Display an alert
  set_alert(true, "bad", "You do not have permission to modify risks.  Any risks that you attempt to modify will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
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

    <script src="../js/jquery.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery.dataTables.js"></script>
    <script src="../js/cve_lookup.js"></script>
    <script src="../js/basescript.js"></script>
    <script src="../js/highcharts/code/highcharts.js"></script>
    <script src="../js/common.js"></script>
    <script src="../js/pages/risk.js"></script>
    <script src="../js/bootstrap-multiselect.js"></script>

    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/jquery.dataTables.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css">

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
  <?php display_asset_autocomplete_script(get_entered_assets()); ?>
</head>

<body>

  <?php
    view_top_menu("RiskManagement");
    // Get any alert messages
    get_alert();
  ?>
  <div class="tabs new-tabs">
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3"> </div>
        <div class="span9">
          <div class="tab-append">
            <div class="tab selected form-tab tab-show" id="tab"><div><span><a href="plan_mitigations.php">Risk list</a></span></div>
            </div>
            <div class="tab selected form-tab tab-show" id="tab"><div><span><strong>ID: <?php echo $id.'</strong>  '.$escaper->escapeHtml(try_decrypt($subject)); ?></span></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_risk_management_menu("ReviewRisksRegularly"); ?>
      </div>
      <div class="span9">

<!--        <div id="show-alert"></div>-->
        <div class="row-fluid" id="tab-content-container">
            <div class='tab-data' id="tab-container">
                <?php
                    
                    $action = isset($_GET['action']) ? $_GET['action'] : "";
                    include(realpath(__DIR__ . '/partials/viewhtml.php'));
                ?>
            </div>
        </div>
        
      </div>
    </div>
  </div>
    <input type="hidden" id="enable_popup" value="<?php echo get_setting('enable_popup'); ?>">
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
            focus_add_css_class("#AffectedAssetsTitle", "#assets");
            focus_add_css_class("#RiskAssessmentTitle", "#assessment");
            focus_add_css_class("#NotesTitle", "#notes");
            focus_add_css_class("#SecurityRequirementsTitle", "#security_requirements");
            focus_add_css_class("#CurrentSolutionTitle", "#current_solution");
            focus_add_css_class("#SecurityRecommendationsTitle", "#security_recommendations");
            
            
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

          $('.collapsible--toggle span').click(function(event) {
            event.preventDefault();
            $(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
            $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
          });

          $('.add-comments').click(function(event) {
            event.preventDefault();
            $(this).parents('.collapsible--toggle').next('.collapsible').slideDown('400');
            $(this).toggleClass('rotate');
            $('#comment').fadeToggle('100');
            $(this).parent().find('span i').removeClass('fa-caret-right');
            $(this).parent().find('span i').addClass('fa-caret-down');
          });


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

          $('#edit-subject').click(function (){
            $('.edit-subject').show();
            $('#static-subject').hide();
          });
          
          $(".add-comment-menu").click(function(event){
            event.preventDefault();
            $commentsContainer = $("#comment").parents('.well');
            $commentsContainer.find(".collapsible--toggle").next('.collapsible').slideDown('400');
            $commentsContainer.find(".add-comments").addClass('rotate');
            $('#comment').show();
            $commentsContainer.find(".add-comments").parent().find('span i').removeClass('fa-caret-right');
            $commentsContainer.find(".add-comments").parent().find('span i').addClass('fa-caret-down');
            $("#comment-text").focus();
          })
          $( ".datepicker" ).datepicker();
        });

        $('body').on('click', '[name=view_all_reviews], .view-all-reviews', function(e){
            e.preventDefault();
            var tabContainer = $(this).parents('.tab-data');
            if($('.current_review', tabContainer).is(":visible")){
                $('.all_reviews', tabContainer).show();
                $('.current_review', tabContainer).hide();
	        $('.all_reviews_btn', tabContainer).html("<?php echo $escaper->escapeHtml($lang['LastReview']); ?>");
            }else{
                $('.all_reviews', tabContainer).hide();
                $('.current_review', tabContainer).show();
	        $('.all_reviews_btn', tabContainer).html("<?php echo $escaper->escapeHtml($lang['ViewAllReviews']); ?>");
            }
        });

    </script>
    <?php display_set_default_date_format_script(); ?>
</html>
