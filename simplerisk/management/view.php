<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

$breadcrumb_title_key = "RiskDetails";
$active_sidebar_menu ="RiskManagement";
$active_sidebar_submenu = isset($_GET['active']) ? $_GET['active'] : "ReviewRisksRegularly";
render_header_and_sidebar(['blockUI', 'tabs:logic', 'selectize', 'datatables', 'chart.js', 'WYSIWYG', 'multiselect', 'CUSTOM:common.js', 'CUSTOM:pages/risk.js', 'CUSTOM:cve_lookup.js', 'datetimerangepicker', 'JSLocalization'], ['check_riskmanagement' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu, required_localization_keys: ['MitigationPlanned']);

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
        $jira_issue_key = jira_extra() ? $risk[0]['jira_issue_key'] : "";
        $submission_date = $risk[0]['submission_date'];
        $risk_tags = $risk[0]['risk_tags'];
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
        $risk_catalog_mapping = $risk[0]['risk_catalog_mapping'];
        $threat_catalog_mapping = $risk[0]['threat_catalog_mapping'];
        $template_group_id  = $risk[0]['template_group_id'];

        $ContributingLikelihood = $risk[0]['Contributing_Likelihood'];
        $contributing_risks_impacts = $risk[0]['Contributing_Risks_Impacts'];
        if($contributing_risks_impacts){
            $ContributingImpacts = get_contributing_impacts_by_subjectimpact_values($contributing_risks_impacts);
        }else{
            $ContributingImpacts = [];
        }
        $display_risk = true;

        $submission_date = format_date($submission_date, "N/A");

        // Get the mitigation for the risk
        $mitigation = get_mitigation_by_id($id);

        // If a mitigation exists for the risk and the user is allowed to access
        if ($mitigation == true && $access)
        {
            // Set the mitigation values
            $mitigation_id = $mitigation[0]['mitigation_id'];
            $mitigation_date = $mitigation[0]['submission_date'];
            $mitigation_date = format_date($mitigation_date);
            $planning_strategy = $mitigation[0]['planning_strategy'];
            $mitigation_effort = $mitigation[0]['mitigation_effort'];
            $mitigation_cost = $mitigation[0]['mitigation_cost'];
            $mitigation_owner = $mitigation[0]['mitigation_owner'];
            $mitigation_team = $mitigation[0]['mitigation_team'];
            $current_solution = $mitigation[0]['current_solution'];
            $security_requirements = $mitigation[0]['security_requirements'];
            $security_recommendations = $mitigation[0]['security_recommendations'];
            $planning_date = format_date($mitigation[0]['planning_date']);
            $mitigation_percent = (isset($mitigation[0]['mitigation_percent']) && $mitigation[0]['mitigation_percent'] >= 0 && $mitigation[0]['mitigation_percent'] <= 100) ? $mitigation[0]['mitigation_percent'] : 0;
            $mitigation_controls = isset($mitigation[0]['mitigation_controls']) ? $mitigation[0]['mitigation_controls'] : "";
        }
        // Otherwise
        else
        {
            // Set the values to empty
            $mitigation_id = "";
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
        }
        else
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
    // If the risk was not found use null values
    else
    {
        $subject = "N/A";
        $display_risk = false;
    }
} 
// if ID is not set
else
{
    $id = "";
    $subject = "N/A";
    $display_risk = false;
}

switch($active_sidebar_submenu){
    default:
    case "ReviewRisksRegularly":
        $list_href = "review_risks.php";
        break;
    case "PerformManagementReviews":
        $list_href = "management_review.php";
        break;
    case "PlanYourMitigations":
        $list_href = "plan_mitigations.php";
        break;
}
?>
<div class="row bg-white">
    <div class="col-12">
        <div class='tab-data hide'></div>
        <div class='tab-data'>
    <?php

        $action = isset($_GET['action']) ? $_GET['action'] : "";

        if($display_risk == true)  {
            include(realpath(__DIR__ . '/partials/viewhtml.php'));
        } else {
            echo "
            <div class='card-body my-2 border'><strong>" . $lang["RiskIdDoesNotExist"] . "</strong></div>
            ";
        }
        
    ?>
        </div>
    </div>
</div>

    <!-- MODEL WINDOW FOR AI Recommendations -->
    <div id="modal-ai-recommendations-risk" class="modal hide fade in" tabindex="-1" role="dialog" aria-labelledby="modal-ai-recommendations-risk" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <?php display_artificial_intelligence_icon("modal", null); ?>
                    <h4 class="modal-title"><?= $escaper->escapeHtml($lang['ArtificialIntelligenceAssistant']); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="tab-content-container" class="tab-data" style="background-color:#fff;padding-top:20px;padding-right:20px;margin-bottom:15px">

                        <div class="row">
                            <div class="col-10 h3">
                                <p><strong><?php echo $escaper->escapeHtml($lang['Details']); ?></strong></p>
                            </div>
                            <div class="ai-recommendations-risk-details">

                            </div>
                        </div>

                        <div class="row">
                            &nbsp;
                        </div>

                        <div class="row">
                            <div class="col-10 h3">
                                <p><strong><?php echo $escaper->escapeHtml($lang['Mitigation']); ?></strong></p>
                            </div>
                            <div class="ai-recommendations-risk-mitigation">

                            </div>
                        </div>

                        <div class="row">
                            &nbsp;
                        </div>

                        <div class="row">
                            <div class="col-10 h3">
                                <p><strong><?php echo $escaper->escapeHtml($lang['FAIRRiskAssessment']); ?></strong></p>
                            </div>
                            <div class="col-10">
                                <p><strong><?php echo $escaper->escapeHtml($lang['RiskScenario']); ?></strong></p>
                            </div>
                            <div class="ai-recommendations-fair-risk-scenario">

                            </div>
                            <div class="col-10">
                                &nbsp;
                            </div>
                            <div class="col-10">
                                <p><strong><?php echo $escaper->escapeHtml($lang['Assumptions']); ?></strong></p>
                            </div>
                            <div class="ai-recommendations-fair-assumptions">

                            </div>
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
                                                    <div class="ai-recommendations-fair-annual-loss-exposure-min"></div>
                                                    <div class="ai-recommendations-fair-annual-loss-exposure-average"></div>
                                                    <div class="ai-recommendations-fair-annual-loss-exposure-max"></div>
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

                        <div class="row">
                            &nbsp;
                        </div>

                        <div class="row">
                            <div class="col-10">
                                <p><strong><?php echo $escaper->escapeHtml($lang['LastUpdated']); ?></strong>&nbsp;&nbsp;<i class="fa fa-sync refresh-recommendations-risk" data-id="<?php echo $escaper->escapeHtml($id); ?>"></i></p>
                            </div>
                            <div class="ai-recommendations-risk-last-updated">

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

<script>

    function updateScore() {
        document.getElementById("scoredetails").style.display = "none";
        document.getElementById("updatescore").style.display = "";
        document.getElementById("show").style.display = "none";
    }

    $(document).ready(function() {
        
        setupAssetsAssetGroupsViewWidget($('select.assets-asset-groups-select-disabled'));
        
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
    
    $(document).click(function (event) {
        var $target = $(event.target);
        if (!$target.closest('.multiselect-native-select').find('.btn-group').length && $('.multiselect-native-select').find('.btn-group').hasClass("open")) {
              $('.multiselect-native-select').find('.btn-group').removeClass('open');
        }
    });
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

        $(".datepicker").initAsDatePicker();

        //render multiselects which are not rendered yet after the page is loaded.
        //multiselects which were already rendered contain 'button.multiselect'.
        $(".multiselect:not(button)").multiselect({enableFiltering: true, buttonWidth: '100%', enableCaseInsensitiveFiltering: true,});

    });
</script>
<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>