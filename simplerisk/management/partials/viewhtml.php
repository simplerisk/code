<?php

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
{
  header("Location: ../../index.php");
  exit(0);
}
// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

?>
<div class="score-overview-container">
    <div class="overview-container">
    <?php
        include(realpath(__DIR__ . '/overview.php'));
    ?>
    </div>
</div>
<div class="content-container">
    <?php
        if($display_risk == true) {
            include(realpath(__DIR__ . '/details.php'));
        }
    ?>
</div>
<?php
require_once(realpath(__DIR__ . '/../../includes/artificial_intelligence.php'));
$_ai_show_section = false;
$_ai_status = null;
// Core/Extra boundary: ai_recommendations_risk is created by the AI Extra.
// ai_capability_enabled('risk_recommendations') is an extra-tier gate (false
// unless the Extra is active), but pair it with the canonical table_exists()
// guard so Core never touches the Extra table on a Core-only install.
if (ai_capability_enabled('risk_recommendations') && table_exists('ai_recommendations_risk')) {
    $_ai_db = db_open();
    $_ai_stmt = $_ai_db->prepare("SELECT `status` FROM `ai_recommendations_risk` WHERE `risk_id` = :risk_id LIMIT 1");
    $_ai_stmt->execute([':risk_id' => $id]);
    $_ai_row = $_ai_stmt->fetch(PDO::FETCH_ASSOC);
    $_ai_show_section = ($_ai_row !== false);
    $_ai_status = $_ai_row['status'] ?? null;
    db_close($_ai_db);
}
// design-system.md §7 "State -- soft": a workflow status (categorical, not
// severity), mapped by MEANING against that section's own family table --
// 'pending' -> warning ("Pending, Past Due, Awaiting Review"), 'in_progress'
// -> info ("New, In Progress, Reopened, Open"), 'complete' -> success
// ("Mitigated, Reviewed, Approved, Pass"), 'failed' -> danger ("Rejected,
// Fail, Overdue") -- replacing raw Bootstrap .badge.bg-* (an off-palette,
// undocumented 4th badge style next to .sr-state-pill's own family, the
// same issue several other raw .badge/.btn-dark spots on this page already
// had fixed this session).
$_ai_badge_map = [
    'pending'     => ['sr-state-warning', 'Pending'],
    'in_progress' => ['sr-state-info',    'Processing'],
    'complete'    => ['sr-state-success', 'Complete'],
    'failed'      => ['sr-state-danger',  'Failed'],
];
$_ai_badge_class = $_ai_badge_map[$_ai_status][0] ?? 'sr-state-neutral';
$_ai_badge_label = $_ai_badge_map[$_ai_status][1] ?? ucfirst($_ai_status ?? '');
// The one truly ACTIVE state (a background job actually running) gets the
// same fa-spinner fa-spin affordance this same accordion's own Monte Carlo
// placeholder uses below, in place of .sr-state-pill's static ::before dot
// (suppressed via .sr-state-pill-spinning -- see _tabs.scss) -- the other
// three are static states with nothing actively happening to animate.
$_ai_is_processing = ($_ai_status === 'in_progress');
?>
<div class="accordion sr-record-accordion mb-3">
    <?php if ($_ai_show_section): ?>
    <div class="accordion-item">
        <h2 id="ai-analysis-accordion-header" class="accordion-header">
            <button type='button' class='accordion-button collapsed sr-qacc-head' data-bs-toggle='collapse' data-bs-target='#ai-analysis-accordion-body' aria-controls='ai-analysis-accordion-body' aria-expanded='false'>
                <i class="fa fa-chevron-right sr-audit-trail-caret" aria-hidden="true"></i>
                <span class="sr-table-title"><?= $escaper->escapeHtml($lang['ArtificialIntelligenceAssistant']); ?></span>
                <span id="ai-analysis-status-badge" class="sr-state-pill ms-2 <?= $_ai_status ? $escaper->escapeHtmlAttr($_ai_badge_class) : 'd-none'; ?><?= $_ai_is_processing ? ' sr-state-pill-spinning' : ''; ?>">
    <?php if ($_ai_is_processing): ?>
                    <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
    <?php endif; ?>
                    <?= $escaper->escapeHtml($_ai_badge_label); ?>
                </span>
            </button>
        </h2>
        <div id="ai-analysis-accordion-body" class="accordion-collapse collapse" data-risk-id="<?= $escaper->escapeHtml($id); ?>" data-ai-status="<?= $escaper->escapeHtml($_ai_status ?? ''); ?>">
            <div class="accordion-body">
                <div id="tab-content-container" class="tab-data" style="background-color:#fff;padding-top:20px;padding-right:20px;margin-bottom:15px">
                    <div id="ai-analysis-status-banner" class="alert d-none mb-3" role="alert"></div>

                    <div class="row">
                        <div class="col-10 h3">
                            <p><strong><?php echo $escaper->escapeHtml($lang['Details']); ?></strong></p>
                        </div>
                        <div class="ai-recommendations-risk-details"></div>
                    </div>

                    <div class="row">&nbsp;</div>

                    <div class="row">
                        <div class="col-10 h3">
                            <p><strong><?php echo $escaper->escapeHtml($lang['Mitigation']); ?></strong></p>
                        </div>
                        <div class="ai-recommendations-risk-mitigation"></div>
                    </div>

                    <div class="row">&nbsp;</div>

                    <div class="row">
                        <div class="col-10 h3">
                            <p><strong><?php echo $escaper->escapeHtml($lang['FAIRRiskAssessment']); ?></strong></p>
                        </div>
                        <div class="col-10">
                            <p><strong><?php echo $escaper->escapeHtml($lang['RiskScenario']); ?></strong></p>
                        </div>
                        <div class="ai-recommendations-fair-risk-scenario"></div>
                        <div class="col-10">&nbsp;</div>
                        <div class="col-10">
                            <p><strong><?php echo $escaper->escapeHtml($lang['Assumptions']); ?></strong></p>
                        </div>
                        <div class="ai-recommendations-fair-assumptions"></div>
                        <div class="col-10">
                            <p><strong><?php echo $escaper->escapeHtml($lang['MonteCarloSimulation']); ?></strong></p>
                        </div>
                        <div class="container">
                            <div class="accordion" id="hierarchyAccordion">

                                <!-- Annual Loss Exposure -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingAnnualLossExposure">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAnnualLossExposure" aria-expanded="true" aria-controls="collapseAnnualLossExposure">
                                            <?php echo $escaper->escapeHtml($lang['AnnualLossExposure']); ?>
                                        </button>
                                    </h2>
                                    <div id="collapseAnnualLossExposure" class="accordion-collapse collapse show" aria-labelledby="headingAnnualLossExposure">
                                        <div class="accordion-body">
                                            <div class="accordion" id="annualLossExposureAccordion">
                                                <p>Annual Loss Exposure refers to the probable financial loss an organization could expect to incur over the course of a year due to specific risk scenarios. Within the FAIR framework, it is calculated as the product of Loss Event Frequency (LEF) and Loss Magnitude (LM).</p>
                                                <div id="ai-fair-ale-processing" class="d-none text-muted fst-italic my-2">
                                                    <i class="fa fa-spinner fa-spin me-2"></i>Monte Carlo simulation results pending&hellip;
                                                </div>
                                                <table id="ai-fair-ale-table" class="table table-sm table-borderless d-none" style="max-width:480px">
                                                    <tbody>
                                                        <tr><td>10th percentile <span class="text-muted small">(optimistic)</span></td><td class="text-end fw-bold ai-fair-ale-p10"></td></tr>
                                                        <tr><td>25th percentile</td><td class="text-end ai-fair-ale-p25"></td></tr>
                                                        <tr class="table-primary"><td><strong>Median (50th percentile)</strong></td><td class="text-end fw-bold ai-fair-ale-median"></td></tr>
                                                        <tr><td>Mean</td><td class="text-end ai-fair-ale-mean"></td></tr>
                                                        <tr><td>75th percentile</td><td class="text-end ai-fair-ale-p75"></td></tr>
                                                        <tr><td>90th percentile <span class="text-muted small">(pessimistic)</span></td><td class="text-end fw-bold ai-fair-ale-p90"></td></tr>
                                                    </tbody>
                                                </table>
                                                <p class="text-muted small ai-fair-ale-iterations d-none"></p>
                                                <br />
                                                <p>By quantifying Annual Loss Exposure, organizations can prioritize their risk management efforts, allocate resources more effectively, and make informed decisions about risk mitigation strategies.</p>

                                                <!-- Begin Loss Event Frequency Accordion -->
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="headingLossEventFrequency">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLossEventFrequency" aria-expanded="false" aria-controls="collapseLossEventFrequency">
                                                            <?php echo $escaper->escapeHtml($lang['LossEventFrequency']); ?>
                                                        </button>
                                                    </h2>
                                                    <div id="collapseLossEventFrequency" class="accordion-collapse collapse" aria-labelledby="headingLossEventFrequency">
                                                        <div class="accordion-body">
                                                            <div class="accordion" id="lossEventFrequencyAccordion">
                                                                <p>Loss Event Frequency (LEF) is the estimated number of times a specific risk scenario is expected to occur within a given timeframe, typically one year. It represents the likelihood of a loss event materializing and is a key component in determining risk exposure. Within the FAIR framework, LEF is derived from Threat Event Frequency (TEF) and Vulnerability.</p>
                                                                <div class="ai-recommendations-fair-loss-event-frequency-min"></div>
                                                                <div class="ai-recommendations-fair-loss-event-frequency-most-likely"></div>
                                                                <div class="ai-recommendations-fair-loss-event-frequency-max"></div>
                                                                <div class="ai-recommendations-fair-loss-event-frequency-confidence"></div>
                                                                <div class="ai-recommendations-fair-loss-event-frequency-rationale"></div>
                                                                <br />
                                                                <p>By analyzing these factors, LEF provides a quantitative basis for understanding how often an organization may experience a particular type of loss, enabling better prioritization of risk mitigation efforts.</p>

                                                                <!-- Begin Threat Event Frequency Accordion -->
                                                                <div class="accordion-item">
                                                                    <h2 class="accordion-header" id="headingThreatEventFrequency">
                                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThreatEventFrequency" aria-expanded="false" aria-controls="collapseThreatEventFrequency">
                                                                            <?php echo $escaper->escapeHtml($lang['ThreatEventFrequency']); ?>
                                                                        </button>
                                                                    </h2>
                                                                    <div id="collapseThreatEventFrequency" class="accordion-collapse collapse" aria-labelledby="headingThreatEventFrequency">
                                                                        <div class="accordion-body">
                                                                            <div class="accordion" id="threatEventFrequencyAccordion">
                                                                                <p>Threat Event Frequency (TEF) refers to the estimated number of times a specific threat actor is expected to take actions that could lead to a loss event within a defined timeframe, typically one year. It represents the activity level of potential threats and is a key factor in determining the likelihood of a risk scenario. TEF is influenced by Contact Frequency and Probability of Action.</p>
                                                                                <div class="ai-recommendations-fair-threat-event-frequency-min"></div>
                                                                                <div class="ai-recommendations-fair-threat-event-frequency-most-likely"></div>
                                                                                <div class="ai-recommendations-fair-threat-event-frequency-max"></div>
                                                                                <div class="ai-recommendations-fair-threat-event-frequency-confidence"></div>
                                                                                <div class="ai-recommendations-fair-threat-event-frequency-rationale"></div>
                                                                                <br />
                                                                                <p>By quantifying TEF, organizations gain insight into how active and persistent specific threats are, enabling better prioritization of risk management strategies and resources.</p>

                                                                                <!-- Begin Contact Frequency Accordion -->
                                                                                <div class="accordion-item">
                                                                                    <h2 class="accordion-header" id="headingContactFrequency">
                                                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseContactFrequency" aria-expanded="false" aria-controls="collapseContactFrequency">
                                                                                            <?php echo $escaper->escapeHtml($lang['ContactFrequency']); ?>
                                                                                        </button>
                                                                                    </h2>
                                                                                    <div id="collapseContactFrequency" class="accordion-collapse collapse" aria-labelledby="headingContactFrequency">
                                                                                        <div class="accordion-body">
                                                                                            <div class="accordion" id="contactFrequencyAccordion">
                                                                                                <p>Contact Frequency (CF) refers to the estimated number of times a threat actor is expected to interact with or target an asset within a specific timeframe, typically one year. It is a component of Threat Event Frequency (TEF) and provides insight into the level of exposure an asset has to potential threats. Contact Frequency is influenced by factors such as Threat Actor Motive, Accessibility and Environmental Factors.</p>
                                                                                                <div class="ai-recommendations-fair-contact-frequency-min"></div>
                                                                                                <div class="ai-recommendations-fair-contact-frequency-most-likely"></div>
                                                                                                <div class="ai-recommendations-fair-contact-frequency-max"></div>
                                                                                                <div class="ai-recommendations-fair-contact-frequency-confidence"></div>
                                                                                                <div class="ai-recommendations-fair-contact-frequency-rationale"></div>
                                                                                                <br />
                                                                                                <p>By evaluating Contact Frequency, organizations can better understand how often assets are likely to face potential threat interactions, aiding in the identification of high-risk areas.</p>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <!-- End Contact Frequency Accordion -->

                                                                                <!-- Begin Probability of Action Accordion -->
                                                                                <div class="accordion-item">
                                                                                    <h2 class="accordion-header" id="headingProbabilityOfAction">
                                                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProbabilityOfAction" aria-expanded="false" aria-controls="collapseProbabilityOfAction">
                                                                                            <?php echo $escaper->escapeHtml($lang['ProbabilityOfAction']); ?>
                                                                                        </button>
                                                                                    </h2>
                                                                                    <div id="collapseProbabilityOfAction" class="accordion-collapse collapse" aria-labelledby="headingProbabilityOfAction">
                                                                                        <div class="accordion-body">
                                                                                            <div class="accordion" id="probabilityOfAction">
                                                                                                <p>Probability of Action (PoA) refers to the likelihood that a threat actor will take an action during an interaction with an asset that could result in a loss event. It is a critical component of Threat Event Frequency (TEF) and helps quantify the risk posed by specific threat scenarios. Probability of Action is influenced by factors such as Threat Actor Intent, Threat Actor Capability and Environmental Factors.</p>
                                                                                                <div class="ai-recommendations-fair-probability-of-action-min"></div>
                                                                                                <div class="ai-recommendations-fair-probability-of-action-most-likely"></div>
                                                                                                <div class="ai-recommendations-fair-probability-of-action-max"></div>
                                                                                                <div class="ai-recommendations-fair-probability-of-action-confidence"></div>
                                                                                                <div class="ai-recommendations-fair-probability-of-action-rationale"></div>
                                                                                                <br />
                                                                                                <p>By evaluating Contact Frequency, organizations can better understand how often assets are likely to face potential threat interactions, aiding in the identification of high-risk areas.</p>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <!-- End Probability of Action Accordion -->

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <!-- End Threat Event Frequency Accordion -->

                                                                <!-- Begin Vulnerability Accordion -->
                                                                <div class="accordion-item">
                                                                    <h2 class="accordion-header" id="headingVulnerability">
                                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVulnerability" aria-expanded="false" aria-controls="collapseVulnerability">
                                                                            <?php echo $escaper->escapeHtml($lang['Vulnerability']); ?>
                                                                        </button>
                                                                    </h2>
                                                                    <div id="collapseVulnerability" class="accordion-collapse collapse" aria-labelledby="headingVulnerability">
                                                                        <div class="accordion-body">
                                                                            <div class="accordion" id="vulnerabilityAccordion">
                                                                                <p>Vulnerability in FAIR risk assessments refers to the likelihood that a threat actor's action will successfully compromise an asset and result in a loss event. It is a key factor in determining Loss Event Frequency (LEF) and represents the susceptibility of an asset to threats. Vulnerability is influenced by Control Strength and Threat Capability.</p>
                                                                                <div class="ai-recommendations-fair-vulnerability-min"></div>
                                                                                <div class="ai-recommendations-fair-vulnerability-most-likely"></div>
                                                                                <div class="ai-recommendations-fair-vulnerability-max"></div>
                                                                                <div class="ai-recommendations-fair-vulnerability-confidence"></div>
                                                                                <div class="ai-recommendations-fair-vulnerability-rationale"></div>
                                                                                <br />
                                                                                <p>By analyzing vulnerability, organizations can identify weaknesses in their defenses, prioritize investments in security measures, and reduce the likelihood of successful attacks.</p>

                                                                                <!-- Begin Threat Capability Accordion -->
                                                                                <div class="accordion-item">
                                                                                    <h2 class="accordion-header" id="headingThreatCapability">
                                                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThreatCapability" aria-expanded="false" aria-controls="collapseThreatCapability">
                                                                                            <?php echo $escaper->escapeHtml($lang['ThreatCapability']); ?>
                                                                                        </button>
                                                                                    </h2>
                                                                                    <div id="collapseThreatCapability" class="accordion-collapse collapse" aria-labelledby="headingThreatCapability">
                                                                                        <div class="accordion-body">
                                                                                            <div class="accordion" id="threatCapabilityAccordion">
                                                                                                <p>Threat Capability (TCap) refers to the level of skill, resources, and effort that a threat actor can leverage to successfully carry out an attack or exploit a vulnerability. It is a critical factor in assessing Vulnerability, as it determines the likelihood that a threat actor can overcome existing controls to achieve their objective. Threat Capability is influenced by factors such as Technical Skills, Resources and Persistence.</p>
                                                                                                <div class="ai-recommendations-fair-threat-capability-min"></div>
                                                                                                <div class="ai-recommendations-fair-threat-capability-most-likely"></div>
                                                                                                <div class="ai-recommendations-fair-threat-capability-max"></div>
                                                                                                <div class="ai-recommendations-fair-threat-capability-confidence"></div>
                                                                                                <div class="ai-recommendations-fair-threat-capability-rationale"></div>
                                                                                                <br />
                                                                                                <p>By evaluating Threat Capability, organizations can better understand the potential effectiveness of a threat actor against their defenses, enabling them to tailor their risk mitigation strategies accordingly.</p>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <!-- End Threat Capability Accordion -->

                                                                                <!-- Begin Resistance Strength Accordion -->
                                                                                <div class="accordion-item">
                                                                                    <h2 class="accordion-header" id="headingResistanceStrength">
                                                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseResistanceStrength" aria-expanded="false" aria-controls="collapseResistanceStrength">
                                                                                            <?php echo $escaper->escapeHtml($lang['ResistanceStrength']); ?>
                                                                                        </button>
                                                                                    </h2>
                                                                                    <div id="collapseResistanceStrength" class="accordion-collapse collapse" aria-labelledby="headingResistanceStrength">
                                                                                        <div class="accordion-body">
                                                                                            <div class="accordion" id="resistanceStrengthAccordion">
                                                                                                <p>Resistance Strength refers to the effectiveness of an asset's controls in preventing, detecting, or mitigating a threat actor's actions. It measures the ability of these defenses to resist or counteract the capabilities of a threat actor. Resistance Strength is a critical factor in determining Vulnerability, as it influences the likelihood of a successful attack. Key elements contributing to Resistance Strength include Control Design, Control Implementation and Control Coverage.</p>
                                                                                                <div class="ai-recommendations-fair-resistance-strength-min"></div>
                                                                                                <div class="ai-recommendations-fair-resistance-strength-most-likely"></div>
                                                                                                <div class="ai-recommendations-fair-resistance-strength-max"></div>
                                                                                                <div class="ai-recommendations-fair-resistance-strength-confidence"></div>
                                                                                                <div class="ai-recommendations-fair-resistance-strength-rationale"></div>
                                                                                                <br />
                                                                                                <p>By assessing Resistance Strength, organizations can identify gaps or weaknesses in their defenses, prioritize enhancements to existing controls, and reduce their overall risk exposure.</p>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <!-- End Resistance Strength Accordion -->

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <!-- End Vulnerability Accordion -->

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- End Loss Event Frequency Accordion -->

                                                <!-- Begin Loss Magnitude Accordion -->
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="headingLossMagnitude">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLossMagnitude" aria-expanded="false" aria-controls="collapseLossMagnitude">
                                                            <?php echo $escaper->escapeHtml($lang['LossMagnitude']); ?>
                                                        </button>
                                                    </h2>
                                                    <div id="collapseLossMagnitude" class="accordion-collapse collapse" aria-labelledby="headingLossMagnitude">
                                                        <div class="accordion-body">
                                                            <p>Loss Magnitude (LM) represents the total financial or operational impact an organization would experience from a single loss event. It quantifies the severity of the loss and is a critical component of risk measurement in the FAIR framework. Loss Magnitude is typically broken down into Primary Loss and Secondary Loss.</p>
                                                            <div class="ai-recommendations-fair-loss-magnitude-min"></div>
                                                            <div class="ai-recommendations-fair-loss-magnitude-most-likely"></div>
                                                            <div class="ai-recommendations-fair-loss-magnitude-max"></div>
                                                            <br />
                                                            <p>By evaluating both primary and secondary losses, organizations can better understand the potential consequences of specific risks, helping them make informed decisions about mitigation strategies and resource allocation.</p>

                                                            <!-- Begin Primary Loss Accordion -->
                                                            <div class="accordion-item">
                                                                <h2 class="accordion-header" id="headingPrimaryLoss">
                                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePrimaryLoss" aria-expanded="false" aria-controls="collapsePrimaryLoss">
                                                                        <?php echo $escaper->escapeHtml($lang['PrimaryLoss']); ?>
                                                                    </button>
                                                                </h2>
                                                                <div id="collapsePrimaryLoss" class="accordion-collapse collapse" aria-labelledby="headingPrimaryLoss">
                                                                    <div class="accordion-body">
                                                                        <p>Primary Loss refers to the direct and immediate financial or operational impact an organization experiences as a result of a loss event. It is one of the two main components of Loss Magnitude in the FAIR framework. Primary Loss typically includes costs that are incurred directly from the event itself, such as Response Costs, Replacement Costs and Fines and Legal Fees.</p>
                                                                        <div class="ai-recommendations-fair-primary-loss-min"></div>
                                                                        <div class="ai-recommendations-fair-primary-loss-most-likely"></div>
                                                                        <div class="ai-recommendations-fair-primary-loss-max"></div>
                                                                        <div class="ai-recommendations-fair-primary-loss-confidence"></div>
                                                                        <div class="ai-recommendations-fair-primary-loss-rationale"></div>
                                                                        <br />
                                                                        <p>By evaluating Primary Loss, organizations can quantify the immediate consequences of risk scenarios and make informed decisions about resource allocation and risk mitigation strategies.</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- End Primary Loss Accordion -->

                                                            <!-- Begin Secondary Risk Accordion -->
                                                            <div class="accordion-item">
                                                                <h2 class="accordion-header" id="headingSecondaryRisk">
                                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSecondaryRisk" aria-expanded="false" aria-controls="collapseSecondaryRisk">
                                                                        <?php echo $escaper->escapeHtml($lang['SecondaryRisk']); ?>
                                                                    </button>
                                                                </h2>
                                                                <div id="collapseSecondaryRisk" class="accordion-collapse collapse" aria-labelledby="headingSecondaryRisk">
                                                                    <div class="accordion-body">
                                                                        <p>Secondary Risk refers to the additional risks or consequences that arise as a result of a primary loss event. Unlike the direct impact measured in Primary Loss, Secondary Risk involves downstream effects, such as the reactions of external parties or cascading impacts on other systems. These risks are driven by factors such as Stakeholder Reactions, Secondary Losses and Amplification Factors.</p>
                                                                        <div class="ai-recommendations-fair-secondary-risk-min"></div>
                                                                        <div class="ai-recommendations-fair-secondary-risk-most-likely"></div>
                                                                        <div class="ai-recommendations-fair-secondary-risk-max"></div>
                                                                        <br />
                                                                        <p>Understanding Secondary Risk is crucial for identifying the broader implications of a risk scenario, enabling organizations to proactively address potential ripple effects and strengthen their overall risk management posture.</p>

                                                                        <!-- Begin Secondary LEF Accordion -->
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header" id="headingSecondaryLEF">
                                                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSecondaryLEF" aria-expanded="false" aria-controls="collapseSecondaryLEF">
                                                                                    <?php echo $escaper->escapeHtml($lang['SecondaryLossEventFrequency']); ?>
                                                                                </button>
                                                                            </h2>
                                                                            <div id="collapseSecondaryLEF" class="accordion-collapse collapse" aria-labelledby="headingSecondaryLEF">
                                                                                <div class="accordion-body">
                                                                                    <p>Secondary Loss Event Frequency refers to the likelihood that a secondary stakeholder reaction (e.g., legal action, regulatory scrutiny, or reputational fallout) will occur as a consequence of a primary loss event. It is a critical factor in quantifying Secondary Loss, as it helps assess how often these indirect impacts are likely to materialize. Secondary Loss Event Frequency is influenced by factors such as Stakeholder Awareness, Stakeholder Perception and Environmental Factors.</p>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-event-frequency-min"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-event-frequency-most-likely"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-event-frequency-max"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-event-frequency-confidence"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-event-frequency-rationale"></div>
                                                                                    <br />
                                                                                    <p>By understanding Secondary Loss Event Frequency, organizations can better anticipate and prepare for the ripple effects of risk scenarios, helping to minimize overall impact.</p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <!-- End Secondary LEF Accordion -->

                                                                        <!-- Begin Secondary LM Accordion -->
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header" id="headingSecondaryLM">
                                                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSecondaryLM" aria-expanded="false" aria-controls="collapseSecondaryLM">
                                                                                    <?php echo $escaper->escapeHtml($lang['SecondaryLossMagnitude']); ?>
                                                                                </button>
                                                                            </h2>
                                                                            <div id="collapseSecondaryLM" class="accordion-collapse collapse" aria-labelledby="headingSecondaryLM">
                                                                                <div class="accordion-body">
                                                                                    <p>Secondary Loss Magnitude represents the financial or operational impact caused by stakeholder reactions to a primary loss event. These reactions, such as lawsuits, regulatory penalties, or reputational harm, often create additional indirect costs that can significantly amplify the overall impact of a risk scenario. Secondary Loss Magnitude is influenced by factors such as Legal and Regulatory Costs, Reputational Damage and Operational Disruption.</p>
                                                                                    <u><strong><?php echo $escaper->escapeHtml($lang['Productivity']); ?></strong></u>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-productivity-min"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-productivity-most-likely"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-productivity-max"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-productivity-confidence"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-productivity-rationale"></div>
                                                                                    <br />
                                                                                    <u><strong><?php echo $escaper->escapeHtml($lang['Response']); ?></strong></u>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-response-min"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-response-most-likely"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-response-max"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-response-confidence"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-response-rationale"></div>
                                                                                    <br />
                                                                                    <u><strong><?php echo $escaper->escapeHtml($lang['Replacement']); ?></strong></u>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-replacement-min"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-replacement-most-likely"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-replacement-max"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-replacement-confidence"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-replacement-rationale"></div>
                                                                                    <br />
                                                                                    <u><strong><?php echo $escaper->escapeHtml($lang['CompetitiveAdvantage']); ?></strong></u>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-competitive-advantage-min"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-competitive-advantage-most-likely"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-competitive-advantage-max"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-competitive-advantage-confidence"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-competitive-advantage-rationale"></div>
                                                                                    <br />
                                                                                    <u><strong><?php echo $escaper->escapeHtml($lang['FinesAndJudgements']); ?></strong></u>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-fines-and-judgements-min"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-fines-and-judgements-most-likely"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-fines-and-judgements-max"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-fines-and-judgements-confidence"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-fines-and-judgements-rationale"></div>
                                                                                    <br />
                                                                                    <u><strong><?php echo $escaper->escapeHtml($lang['Reputation']); ?></strong></u>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-reputation-min"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-reputation-most-likely"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-reputation-max"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-reputation-confidence"></div>
                                                                                    <div class="ai-recommendations-fair-secondary-loss-magnitude-reputation-rationale"></div>
                                                                                    <br />
                                                                                    <p>By quantifying Secondary Loss Magnitude, organizations can gain a more comprehensive understanding of the full cost of risk scenarios, enabling more effective prioritization and risk mitigation strategies.</p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <!-- End Secondary LM Accordion -->

                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- End Secondary Risk Accordion -->

                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- End Loss Magnitude Accordion -->

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Annual Loss Exposure -->

                            </div>
                        </div>
                    </div>

                    <div class="row">&nbsp;</div>

                    <div class="row">
                        <div class="col-10">
                            <p><strong><?php echo $escaper->escapeHtml($lang['LastUpdated']); ?></strong>&nbsp;&nbsp;<i class="fa fa-sync refresh-recommendations-risk" data-id="<?= $escaper->escapeHtml($id); ?>"></i></p>
                        </div>
                        <div class="ai-recommendations-risk-last-updated"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed sr-qacc-head' data-bs-toggle='collapse' data-bs-target='#associated-exceptions-accordion-body' aria-controls='associated-exceptions-accordion-body' aria-expanded='false'>
                <i class="fa fa-chevron-right sr-audit-trail-caret" aria-hidden="true"></i>
                <span class="sr-table-title"><?= $escaper->escapeHtml($lang['AssociatedExceptions']); ?></span>
            </button>
        </h2>
        <div id="associated-exceptions-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <!-- design-system.md §12's hand-rolled .sr-tabs/.sr-tab
                     component (shipped in _tabs.scss; admin/data_integrity.php
                     is the other live consumer) -- NOT Bootstrap's real
                     data-bs-toggle="tab" plugin. Two bugs traced back to that
                     plugin plus header.php's sitewide 'tabs:logic' script,
                     which assumes exactly ONE tab hierarchy per page:
                     (a) it binds unscoped to every `nav a[data-bs-toggle=
                     "tab"]` and force-scrolls `.content-wrapper` into view on
                     every click -- there's no tab CONTENT to jump away from
                     down here, so it just read as an unwanted scroll-to-top;
                     (b) on EVERY page load (hash or not) it unconditionally
                     runs `$('div.tab-pane.active').removeClass('active')`
                     sitewide, then restores only the ONE hierarchy matching
                     location.hash -- or, with no hash, only the FIRST
                     `nav.nav-tabs` group in DOM order, which is Details/
                     Mitigation/Review's, earlier on the page than this one.
                     A hash pointing at one of THESE sub-tabs (which that
                     same script also writes into the URL on click, e.g.
                     `#control-exceptions`) matched here instead, so Details/
                     Mitigation/Review's own active state was cleared and
                     never restored -- everything under them visibly
                     vanished on reload. But even with NO hash, this second,
                     later `div.tab-pane.active` group was still wiped by
                     the same blanket clear and never restored either way --
                     Policy Exceptions' treegrid silently lost its default-
                     active state on every single load, hash or not.
                     Fixed at the source rather than patching the shared
                     script (which other pages' nested tabs still rely on):
                     dropping data-bs-toggle="tab" removes these buttons from
                     its click selector, and using .sr-tab-pane/.is-active
                     instead of .tab-pane/.active on the three content divs
                     below (and in initAsAssociatedExceptionTreegrid()'s
                     matching selectors, risk.js) keeps them out of its
                     load-time selector too. .sr-tab-pane's own CSS
                     (_tables.scss) mirrors the theme's .tab-content>.active
                     {display:block} rule under our own class name. -->
                <div class="sr-tabs" id="associated-exceptions-tabs">
                    <button type="button" class="sr-tab is-active" data-target="#policy-exceptions" data-type="policy" id="tab_policy-exceptions">
                        <?php echo $escaper->escapeHtml($lang['PolicyExceptions']); ?> <span class="sr-table-count" id="policy-exceptions-count">-</span>
                    </button>
                    <button type="button" class="sr-tab" data-target="#control-exceptions" data-type="control" id="tab_control-exceptions">
                        <?php echo $escaper->escapeHtml($lang['ControlExceptions']); ?> <span class="sr-table-count" id="control-exceptions-count">-</span>
                    </button>
    <?php
        if (check_permission_exception('approve')) {
    ?>
                    <button type="button" class="sr-tab" data-target="#unapproved-exceptions" data-type="unapproved" id="tab_unapproved-exceptions">
                        <?php echo $escaper->escapeHtml($lang['UnapprovedExceptions']); ?> <span class="sr-table-count" id="unapproved-exceptions-count">-</span>
                    </button>
    <?php
        }
    ?>
                </div>
                <div class="tab-content" id="associated-exceptions-tab-content">
                    <div id="policy-exceptions" class="sr-tab-pane is-active custom-treegrid-container">
                        <?php get_associated_exception_tabs('policy') ?>
                    </div>
                    <div id="control-exceptions" class="sr-tab-pane custom-treegrid-container">
                        <?php get_associated_exception_tabs('control') ?>
                    </div>
    <?php if (check_permission_exception('approve')) { ?>
                    <div id="unapproved-exceptions" class="sr-tab-pane custom-treegrid-container">
                        <?php get_associated_exception_tabs('unapproved') ?>
                    </div>
    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <script>

        // Hand-rolled .sr-tabs switch (see the markup comment above for why
        // this isn't Bootstrap's data-bs-toggle="tab" plugin): toggle
        // .is-active on the clicked trigger and its matching .sr-tab-pane,
        // then init/resize that pane's treegrid -- same "have to init the
        // treegrid when the tab is first DISPLAYED, because it renders
        // incorrectly in the background" reasoning the old shown.bs.tab
        // handler had, just driven by this click instead of a Bootstrap
        // event.
        $('#associated-exceptions-tabs').on('click', '.sr-tab', function () {
            var $tab = $(this);
            var type = $tab.data('type');
            $('#associated-exceptions-tabs .sr-tab').removeClass('is-active');
            $tab.addClass('is-active');
            $('#associated-exceptions-tab-content .sr-tab-pane').removeClass('is-active');
            $($tab.data('target')).addClass('is-active');
            $(`#associated-exception-table-${type}`).initAsAssociatedExceptionTreegrid(type);
        });

        // Policy Exceptions is the default-active tab, so it never fires the
        // click handler above -- and its treegrid can't size correctly while
        // the accordion itself is still collapsed (0-width container, same
        // reasoning initAsAssociatedExceptionTreegrid()'s own "all ancestor
        // .sr-tab-pane must be is-active" guard exists for). Init it once
        // the accordion is actually expanded instead; plain .on() rather
        // than .one() so a collapse-then-reexpand safely re-triggers the
        // function's own already-initialized branch (a cheap .treegrid
        // ("resize") rather than a re-init).
        $(document).on('shown.bs.collapse', '#associated-exceptions-accordion-body', function () {
            $('#associated-exception-table-policy').initAsAssociatedExceptionTreegrid('policy');

            // The other two tabs' counts otherwise stay stuck on the "-"
            // placeholder until the user actually clicks through to them
            // (initAsAssociatedExceptionTreegrid() won't init a treegrid
            // that isn't visible -- see its own "all ancestor .sr-tab-pane
            // must be is-active" guard). A plain read-only fetch against the
            // same endpoint the treegrid itself loads gets just the count
            // without needing that pane visible or building the treegrid
            // widget early -- the actual table still only renders lazily on
            // first click, unchanged.
            var $accordionBody = $(this);
            var riskId = $('.risk-id', $accordionBody.closest('.tab-data')).html();
            ['control', 'unapproved'].forEach(function (type) {
                if (!$('#' + type + '-exceptions').length) {
                    return; // unapproved-exceptions is permission-gated
                }
                $.ajax({
                    url: BASE_URL + '/api/v2/associated-exceptions/tree?type=' + type + '&id=' + riskId,
                    type: 'GET',
                    success: function (res) {
                        var rows = (res && res.data) || [];
                        var totalCount = 0;
                        rows.forEach(function (parent) {
                            if (parent.children && parent.children.length) {
                                totalCount += parent.children.length;
                            }
                        });
                        $('#' + type + '-exceptions-count').text(totalCount);
                    }
                });
            });
        });

        function wireActionButtons(tab) {

            //Info + Approve
            $("#"+ tab + "-exceptions span.exception-name > a").click(function(){
                event.preventDefault();
                var exception_id = $(this).data("id");
                var type = $(this).data("type");
                var approval = $(this).hasClass("exception--approve");
                
                $.ajax({
                    url: BASE_URL + '/api/v2/exceptions/info',
                    data: {
                        id: exception_id,
                        type: type,
                        approval: approval
                    },
                    type: 'GET',
                    success : function (res){
                        var data = res.data;

                        $("#exception--view #name").html(data.name);
                        $("#exception--view #type").html(data.type_text);
                        if (data.type == 'policy') {
                            $("#exception--view #policy").html(data.policy_name);
                            $("#exception--view #policy").parent().show();
                            $("#exception--view #framework").parent().hide();
                            $("#exception--view #control").parent().hide();
                        } else {
                            $("#exception--view #framework").html(data.framework_name);
                            $("#exception--view #framework").parent().show();
                            $("#exception--view #control").html(data.control_name);
                            $("#exception--view #control").parent().show();
                            $("#exception--view #policy").parent().hide();
                        }

                        $("#exception--view #document_exceptions_status").html(data.document_exceptions_status);
                        $("#exception--view #owner").html(data.owner);
                        $("#exception--view #additional_stakeholders").html(data.additional_stakeholders);
                        $("#exception--view #associated_risks").html(data.associated_risks);
                        $("#exception--view #creation_date").html(data.creation_date);
                        $("#exception--view #review_frequency").html(data.review_frequency);
                        $("#exception--view #next_review_date").html(data.next_review_date);
                        $("#exception--view #approval_date").html(data.approval_date);
                        $("#exception--view #approver").html(data.approver);
                        $("#exception--view #description").html(data.description);
                        $("#exception--view #justification").html(data.justification);
                        $("#exception--view #file_download").html(data.file_download);

                        if (approval) {
                            $(".approve-footer").show();
                            $(".info-footer").hide();
                            $("#exception-approve-form [name='exception_id']").val(exception_id);
                            $("#exception-approve-form [name='type']").val(type);
                        } else {
                            $(".approve-footer").hide();
                            $(".info-footer").show();
                            $("#exception-approve-form [name='type']").val("");
                        }

                        $("#exception--view").modal('show');
                    }
                });
            });

        }
    </script>
    
    <!-- MODAL WINDOW FOR DISPLAYING AN EXCEPTION -->
    <div id="exception--view" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception--update" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h4 id="name" class="modal-title"></h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['ExceptionType']); ?>:</label>
                    <span id="type" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['PolicyName']); ?>:</label>
                    <span id="policy" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['FrameworkName']); ?>:</label>
                    <span id="framework" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['ControlName']); ?>:</label>
                    <span id="control" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['ExceptionStatus']); ?>:</label>
                    <span id="document_exceptions_status" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['AssociatedRisks']); ?>:</label>
                    <span id="associated_risks" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['ExceptionOwner']); ?>:</label>
                    <span id="owner" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?>:</label>
                    <span id="additional_stakeholders" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['CreationDate']); ?>:</label>
                    <span id="creation_date" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?>:</label>
                    <div>
                        <span id="review_frequency" class="exception-data"></span><span style="margin-left: 5px;" class="white-labels"><?php echo $escaper->escapeHtml($lang['days']); ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?>:</label>
                    <span id="next_review_date" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?>:</label>
                    <span id="approval_date" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['Approver']); ?>:</label>
                    <span id="approver" class="exception-data d-block"></span>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['Description']); ?>:</label>
                    <div id="description" class="exception-data d-block"></div>
                </div>
                <div class="form-group">
                    <label><?php echo $escaper->escapeHtml($lang['Justification']); ?>:</label>
                    <div id="justification" class="exception-data d-block"></div>
                </div>
                <div>
                    <label><?php echo $escaper->escapeHtml($lang['File']); ?>:</label>
                    <div id="file_download" class="exception-data d-block"></div>
                </div>
            </div>
            <div class="modal-footer info-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Close']); ?></button>
            </div>
            <?php if (check_permission_exception('approve')) { ?>
                <div class="modal-footer approve-footer">
                    <form class="" id="exception-approve-form" action="" method="post">
                        <input type="hidden" name="exception_id" value="" />
                        <input type="hidden" name="type" value="" />
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="approve_exception" class="btn btn-submit"><?php echo $escaper->escapeHtml($lang['Approve']); ?></button>
                    </form>
                </div>
            <?php } ?>
        </div>
        </div>
    </div>
    <div class="accordion-item comments--wrapper">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed sr-qacc-head' data-bs-toggle='collapse' data-bs-target='#comments-accordion-body' aria-controls='comments-accordion-body' aria-expanded='false'>
                <i class="fa fa-chevron-right sr-audit-trail-caret" aria-hidden="true"></i>
                <span class="sr-table-title"><?= $escaper->escapeHtml($lang['Comments']); ?></span>
            </button>
        </h2>
        <div id="comments-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="comment-wrapper">
                            <form id="comment" class="comment-form" name="add_comment" method="post">
                                <textarea style="width: 100%; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box;" name="comment" cols="50" rows="3" id="comment-text" class="comment-text form-control"></textarea>
                                <div class="form-actions text-end mt-2" id="comment-div">
                                    <input class="btn btn-dark" id="rest-btn" value="<?php echo $escaper->escapeHtml($lang['Reset']); ?>" type="reset" />
                                    <button id="comment-submit" type="submit" name="submit" class="comment-submit btn btn-submit" ><?php echo $escaper->escapeHtml($lang['Submit']); ?></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="comments--list clearfix">
                        <?php
                            include(realpath(__DIR__ . '/comments-list.php'));
                        ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- AUDIT TRAIL (design-system.md §6/§7): a real .sr-table-card disclosure
     -- Define Exceptions'/Document Program's identical shape and
     js/simplerisk/pages/risk-audit-trail.js (js/simplerisk/pages/governance-
     document-audit-trail.js is the reference implementation) -- rather than
     one more item inside the .sr-record-accordion group above: the spec's
     own §6d shell is "a plain disclosure, not .accordion", with its own
     toggle chrome, so it sits as a sibling section instead of a 4th nested
     accordion-item. No Entity column/filter (entityKey: null in the JS
     config) -- this card is already scoped to THIS risk by the API path,
     unlike the list-page reference implementations. Gated the same way the
     legacy accordion it replaces was: reachable whenever $display_risk is
     true (the surrounding viewhtml.php include's own gate), matching GET
     /api/v2/management/risk/auditLog's own riskmanagement + check_access_
     for_risk() gate. -->
<div class="sr-table-card sr-audit-trail-card sr-risk-audit-trail-card" id="risk-audit-trail">
    <div class="sr-table-toolbar" id="risk-audit-trail-toolbar">
        <button type="button" class="sr-audit-trail-toggle" id="risk-audit-trail-toggle" aria-expanded="false" aria-controls="risk-audit-trail-collapse">
            <i class="fa fa-chevron-right sr-audit-trail-caret" aria-hidden="true"></i>
            <span class="sr-table-title"><?= $escaper->escapeHtml($lang['AuditTrail']); ?> <span class="sr-table-count d-none" id="risk-audit-trail-count"></span></span>
        </button>
        <div class="sr-table-tools">
            <button type="button" class="sr-table-filter" id="risk-audit-trail-refresh" title="<?= $escaper->escapeHtmlAttr($lang['Refresh']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Refresh']); ?>"><i class="fa fa-sync" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="d-none" id="risk-audit-trail-collapse">
        <!-- No Export button and no separate inner-toolbar row: this config's
             searchBesideFilters:true (js/simplerisk/pages/risk-audit-trail.js)
             has relocateSearchIntoTools() (js/simplerisk/sr-audit-trail.js)
             append DataTables' own generated search box directly onto
             #risk-audit-trail-filters (.sr-qf-selects) instead, so it sits
             after the filter selects on the same row and wraps left with
             them at narrow widths rather than staying pinned right on an
             otherwise-empty toolbar row. -->
        <!-- Narrow-width filter sheet (design-system.md §6b) -- inert at
             full width, where .sr-qf-toggle is display:none and the row
             below is simply on screen; below 1100px it collapses behind
             this button instead (_tables.scss's .sr-qf-toggle rules, no
             bespoke CSS needed here). Wired generically in createAuditTrail
             (js/simplerisk/sr-audit-trail.js) rather than per-page, so
             Define Exceptions'/Document Program's own audit trails pick up
             the identical toggle once this markup is added to their pages
             too. -->
        <button type="button" class="sr-qf-toggle" id="risk-audit-trail-filters-toggle" aria-expanded="false" aria-controls="risk-audit-trail-quickfilters">
            <i class="fa fa-filter" aria-hidden="true"></i>
            <span><?= $escaper->escapeHtml($lang['Filters']); ?></span>
            <span class="sr-qf-toggle-count" id="risk-audit-trail-filters-count" hidden></span>
        </button>
        <div class="sr-table-quickfilters" id="risk-audit-trail-quickfilters">
            <div class="sr-qf-selects" id="risk-audit-trail-filters">
                <div class="audit-select-folder">
                    <select name="days" id="risk-audit-trail-range-filter" class="form-select" title="<?= $escaper->escapeHtmlAttr($lang['DateRange']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['DateRange']); ?>">
                        <option value="7" selected><?= $escaper->escapeHtml($lang['PastWeek']); ?></option>
                        <option value="30"><?= $escaper->escapeHtml($lang['PastMonth']); ?></option>
                        <option value="90"><?= $escaper->escapeHtml($lang['PastQuarter']); ?></option>
                        <option value="180"><?= $escaper->escapeHtml($lang['Past6Months']); ?></option>
                        <option value="365"><?= $escaper->escapeHtml($lang['PastYear']); ?></option>
                        <option value="36500"><?= $escaper->escapeHtml($lang['AllTime']); ?></option>
                    </select>
                </div>
                <select id="risk-audit-trail-activity-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['Activity']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['Activity']); ?>"></select>
                <select id="risk-audit-trail-user-filter" class="form-select" multiple title="<?= $escaper->escapeHtmlAttr($lang['User']); ?>" aria-label="<?= $escaper->escapeHtmlAttr($lang['User']); ?>"></select>
                <button type="button" class="btn btn-link btn-sm sr-qf-clear d-none" id="risk-audit-trail-filters-clear"><?= $escaper->escapeHtml($lang['ClearFilters']); ?></button>
            </div>
        </div>
        <div id="risk-audit-trail-body"></div>
    </div>
</div>
<?php
    // The mount above is server-rendered directly (not built by JS the way
    // Details' Cards mount is), but window.RiskAuditTrail.init() -- the
    // click bindings that make its toggle/refresh/filters actually work --
    // only ever runs once, from management/view.php's own $(document).ready.
    // On the AJAX path (Close Risk/Reopen/Change Status all replace THIS
    // WHOLE FILE's markup via getTabHtml() -- risk.js's tabContainer.html())
    // that destroys the card management/view.php's ready handler already
    // bound to, and nothing ever re-binds the fresh replacement: the toggle
    // silently stopped responding to clicks after any one of those actions.
    // Re-invoking init() here is the exact mirror of details.php's own
    // $isAjax-gated re-render script just above it in this same render --
    // sr-audit-trail.js's init() is written to be safely re-callable (resets
    // its own expanded/loaded/filter state and tears down the two
    // $(document)-delegated handlers before re-registering them) precisely
    // so this works.
    if (isset($isAjax) && $isAjax) {
?>
<script>
    $(function () {
        if (window.RiskAuditTrail && $('#risk-audit-trail').length) {
            window.RiskAuditTrail.init();
        }
    });
</script>
<?php
    }
?>
<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">