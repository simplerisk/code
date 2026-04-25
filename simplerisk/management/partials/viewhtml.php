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
$_ai_show_section = false;
$_ai_status = null;
if (artificial_intelligence_extra() && get_setting('extra_ai_risk_suggestions') == 1) {
    $_ai_db = db_open();
    $_ai_stmt = $_ai_db->prepare("SELECT `status` FROM `ai_recommendations_risk` WHERE `risk_id` = :risk_id LIMIT 1");
    $_ai_stmt->execute([':risk_id' => $id]);
    $_ai_row = $_ai_stmt->fetch(PDO::FETCH_ASSOC);
    $_ai_show_section = ($_ai_row !== false);
    $_ai_status = $_ai_row['status'] ?? null;
    db_close($_ai_db);
}
$_ai_badge_map = [
    'pending'     => ['bg-secondary', 'Pending'],
    'in_progress' => ['bg-info',      'Processing'],
    'complete'    => ['bg-success',   'Complete'],
    'failed'      => ['bg-danger',    'Failed'],
];
$_ai_badge_class = $_ai_badge_map[$_ai_status][0] ?? 'bg-secondary';
$_ai_badge_label = $_ai_badge_map[$_ai_status][1] ?? ucfirst($_ai_status ?? '');
?>
<div class="accordion mb-2">
    <?php if ($_ai_show_section): ?>
    <div class="accordion-item">
        <h2 id="ai-analysis-accordion-header" class="accordion-header">
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#ai-analysis-accordion-body'>
                <?= $escaper->escapeHtml($lang['ArtificialIntelligenceAssistant']); ?>
                <span id="ai-analysis-status-badge" class="badge ms-2 <?= $_ai_status ? $escaper->escapeHtmlAttr($_ai_badge_class) : 'd-none'; ?>"><?= $escaper->escapeHtml($_ai_badge_label); ?></span>
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
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#associated-exceptions-accordion-body'><?= $escaper->escapeHtml($lang['AssociatedExceptions']); ?></button>
        </h2>
        <div id="associated-exceptions-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row">
                    <div class="col-12">
                        <div>
                            <nav class="nav nav-tabs">
                                <a data-bs-target="#policy-exceptions" data-bs-toggle="tab" class="nav-link active" data-type="policy"><?php echo $escaper->escapeHtml($lang['PolicyExceptions']); ?> (<span id="policy-exceptions-count">-</span>)</a>
                                <a data-bs-target="#control-exceptions" data-bs-toggle="tab" class="nav-link" data-type="control"><?php echo $escaper->escapeHtml($lang['ControlExceptions']); ?> (<span id="control-exceptions-count">-</span>)</a>
    <?php 
        if (check_permission_exception('approve')) { 
    ?>
                                <a data-bs-target="#unapproved-exceptions" data-bs-toggle="tab" class="nav-link" data-type="unapproved"><?php echo $escaper->escapeHtml($lang['UnapprovedExceptions']); ?> (<span id="unapproved-exceptions-count">-</span>)</a>
    <?php
        } 
    ?>
                            </nav>
                        </div>
                        <div class="tab-content card-body border my-2">
                            <div id="policy-exceptions" class="tab-pane active custom-treegrid-container">
                                <?php get_associated_exception_tabs('policy') ?>
                            </div>
                            <div id="control-exceptions" class="tab-pane custom-treegrid-container">
                                <?php get_associated_exception_tabs('control') ?>
                            </div>
    <?php if (check_permission_exception('approve')) { ?>
                            <div id="unapproved-exceptions" class="tab-pane custom-treegrid-container">
                                <?php get_associated_exception_tabs('unapproved') ?>
                            </div>
    <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>

        // Have to init the treegrid when the tab is first displayed, because it's rendered incorrectly when initialized in the background
        $(document).on('shown.bs.tab', 'nav a[data-bs-toggle=\"tab\"][data-type]', function (e) {
            let type = $(this).data('type');
            $(`#associated-exception-table-${type}`).initAsAssociatedExceptionTreegrid(type);
        });

        function wireActionButtons(tab) {

            //Info + Approve
            $("#"+ tab + "-exceptions span.exception-name > a").click(function(){
                event.preventDefault();
                var exception_id = $(this).data("id");
                var type = $(this).data("type");
                var approval = $(this).hasClass("exception--approve");
                
                $.ajax({
                    url: BASE_URL + '/api/exceptions/info',
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
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#comments-accordion-body'><?= $escaper->escapeHtml($lang['Comments']); ?></button>
        </h2>
        <div id="comments-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="comment-wrapper">
                            <form id="comment" class="comment-form" name="add_comment" method="post" action="/management/comment.php?id=<?php echo $id; ?>">
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
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type='button' class='accordion-button collapsed' data-bs-toggle='collapse' data-bs-target='#audit-trail-accordion-body'><?= $escaper->escapeHtml($lang['AuditTrail']); ?></button>
        </h2>
        <div id="audit-trail-accordion-body" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row">
                    <div class="col-12 audit-trail">
                        <?php get_audit_trail_html($id, 36500, ['risk', 'jira']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="_token_value" value="<?php echo csrf_get_tokens(); ?>">