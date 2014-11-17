<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
// Include required functions file
require_once(realpath(__DIR__ . '/../includes/libs.php'));

require_once(realpath(__DIR__ . '/../includes/display.php'));

require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

// Check for session timeout or renegotiation
session_check();

// Default is no alert
$alert = false;

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted") {
    header("Location: ../index.php");
    exit(0);
}

// Check if the user has access to submit risks
if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1) {
    $submit_risks = false;
    $alert = "bad";
    $alert_message = "You do not have permission to submit new risks.  Any risks that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.";
} else $submit_risks = true;

// Check if a new risk was submitted and the user has permissions to submit new risks
if ((isset($_POST['submit'])) && $submit_risks) {
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

    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$_POST['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIClikelihood = (int)$_POST['likelihood'];
    $CLASSICimpact = (int)$_POST['impact'];

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

    // Upload any file that is submitted
    upload_file($last_insert_id, $_FILES['file']);

    // If the notification extra is enabled
    if (notification_extra()) {
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

$localvars = array();

if($alert == "good" || $alert == "bad") {

    $localvars['alert'] = true;
    $localvars['alert_type'] = $alert;
    $localvars['alert_message'] = $alert_message;
}

$template = $twig->loadTemplate('management_index.html.twig');

$template->display(array_merge($base_twigvars, $localvars));



