<?php
    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1") {
        header("Location: ../../index.php");
        exit(0);
    }

    // Enforce that the user has access to risk management
    enforce_permission("riskmanagement");
?>
<style>
    .cal-head{
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid #d1d3d4
    }
</style>
<div class="accordion-item">
    <h2 class="accordion-header">
        <button id="show" type='button' class='accordion-button collapsed show-score' data-bs-toggle='collapse' data-bs-target='#score-container-accordion-body'><?= $escaper->escapeHtml($lang['ShowRiskScoringDetails']); ?></button>
        <button id="hide" type='button' style="display: none;" class='accordion-button hide-score' data-bs-toggle='collapse' data-bs-target='#score-container-accordion-body'><?= $escaper->escapeHtml($lang['HideRiskScoringDetails']); ?></button>
    </h2>
    <div id="score-container-accordion-body" class="accordion-collapse collapse">
        <div class="score-container accordion-body">
            <div id="scoredetails" class="scoredetails" style="display: none;">
    <?php
        // Scoring method is Classic
        if ($scoring_method == "1") {
                classic_scoring_table($id, $calculated_risk, $CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2") {
                cvss_scoring_table($id, $calculated_risk, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3") {
                dread_scoring_table($id, $calculated_risk, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4") {
                owasp_scoring_table($id, $calculated_risk, $OWASPSkillLevel, $OWASPEaseOfDiscovery, $OWASPLossOfConfidentiality, $OWASPFinancialDamage, $OWASPMotive, $OWASPEaseOfExploit, $OWASPLossOfIntegrity, $OWASPReputationDamage, $OWASPOpportunity, $OWASPAwareness, $OWASPLossOfAvailability, $OWASPNonCompliance, $OWASPSize, $OWASPIntrusionDetection, $OWASPLossOfAccountability, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5") {
                custom_scoring_table($id, $custom);
        }
        // Scoring method is Custom
        else if ($scoring_method == "6") {
                contributing_risk_scoring_table($id, $calculated_risk, $ContributingLikelihood, $ContributingImpacts);
        }
    ?>
            </div>
            <div id="updatescore" class="updatescore" style="display: none;">
    <?php
        // Scoring method is Classic
        if ($scoring_method == "1") {
                edit_classic_score($CLASSIC_likelihood, $CLASSIC_impact);
        }
        // Scoring method is CVSS
        else if ($scoring_method == "2") {
                edit_cvss_score($AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
        }
        // Scoring method is DREAD
        else if ($scoring_method == "3") {
                edit_dread_score($DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
        }
        // Scoring method is OWASP
        else if ($scoring_method == "4") {
                edit_owasp_score($OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
        }
        // Scoring method is Custom
        else if ($scoring_method == "5") {
                edit_custom_score($custom);
        }
        // Scoring method is Contributing Risk
        else if ($scoring_method == "6") {
                edit_contributing_risk_score($ContributingLikelihood, $ContributingImpacts);
        }
    ?>
            </div>
            <input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">
            <input type="hidden" id="_lang_reopen_risk" value="<?php echo $escaper->escapeHtml($lang['ReopenRisk']); ?>">
            <input type="hidden" id="_lang_close_risk" value="<?php echo $escaper->escapeHtml($lang['CloseRisk']); ?>">
        </div>
    </div>
</div>