<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

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
        }


        if ($submission_date == "")
        {
            $submission_date = "N/A";
        }
        else $submission_date = date(get_default_datetime_format("g:i A T"), strtotime($submission_date));

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
            $mitigation_date = $mitigation[0]['submission_date'];
            $mitigation_date = date(get_default_datetime_format("g:i A T"), strtotime($mitigation_date));
            $planning_strategy = $mitigation[0]['planning_strategy'];
            $mitigation_effort = $mitigation[0]['mitigation_effort'];
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
            $review_date = $mgmt_reviews[0]['submission_date'];
            $review_date = date(get_default_datetime_format("g:i A T"), strtotime($review_date));
            $review = $mgmt_reviews[0]['review'];
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
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
  </head>

  <body>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span12">
          <div class="row-fluid">
            <br />
            <div class="well">
              <?php view_print_top_table($id, $calculated_risk, $subject, $status, true); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <?php view_print_risk_details($id, $submission_date, $subject, $reference_id, $regulation, $control_number, $location, $category, $team, $technology, $owner, $manager, $assessment, $notes, $tags); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <?php view_print_mitigation_details($id, $mitigation_date, $planning_strategy, $mitigation_effort, $current_solution, $security_requirements, $security_recommendations); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <?php view_print_mitigation_controls($mitigation); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <?php view_print_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments); ?>
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
              <?php get_audit_trail_html($id,36500,'risk'); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
