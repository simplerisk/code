<?php
    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../../includes/display.php'));
    require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
    require_once(realpath(__DIR__ . '/../../includes/permissions.php'));

    // Include Zend Escaper for HTML Output Encoding
    require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
    $escaper = new Zend\Escaper\Escaper('utf-8');

    // Add various security headers
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");

    // If we want to enable the Content Security Policy (CSP) - This may break Chrome
    if (csp_enabled())
    {
      // Add the Content-Security-Policy header
      header("Content-Security-Policy: default-src 'self' 'unsafe-inline' *.highcharts.com *.googleapis.com *.gstatic.com *.jquery.com;");
    }

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
    global $lang;

    require_once(realpath(__DIR__ . '/../../includes/csrf-magic/csrf-magic.php'));

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        header("Location: ../../index.php");
        exit(0);
    }

    // Enforce that the user has access to risk management
    enforce_permission_riskmanagement();

?>

<div class="row-fluid details risk-test">
    <a href="#" id="show" class='show-score' onclick="javascript: showScoreDetails();"> <i class="fa fa-caret-right"></i>&nbsp; <?php echo $escaper->escapeHtml($lang['ShowRiskScoringDetails']); ?></a>
    <a href="#" id="hide" class='hide-score' style="display: none;" onclick="javascript: hideScoreDetails();"> <i class="fa fa-caret-down"></i> &nbsp; <?php echo $escaper->escapeHtml($lang['HideRiskScoringDetails']); ?> </a>
</div>

<div class="row-fluid score-container">
    <div id="scoredetails" class="scoredetails" class="row-fluid" style="display: none;">
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
          // Scoring method is Custom
          else if ($scoring_method == "6")
          {
            contributing_risk_scoring_table($id, $calculated_risk, $ContributingLikelihood, $ContributingImpacts);
          }
          ?>
        </div>
    </div>
    <div id="updatescore" class="updatescore" class="row-fluid" style="display: none;">
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
          // Scoring method is Contributing Risk
          else if ($scoring_method == "6")
          {
            edit_contributing_risk_score($ContributingLikelihood, $ContributingImpacts);
          }
          ?>
        </div>
    </div>

            <input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
            <input type="hidden" id="_lang_reopen_risk" value="<?php echo $lang['ReopenRisk']; ?>">
            <input type="hidden" id="_lang_close_risk" value="<?php echo $lang['CloseRisk']; ?>">
</div>

