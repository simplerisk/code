<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/display.php'));
require_once(realpath(__DIR__ . '/../../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));

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
    $parameters = [
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => isset($_SERVER["HTTPS"]),
        "httponly" => true,
        "samesite" => "Strict",
    ];
    session_set_cookie_params($parameters);

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
require_once(language_file());

global $escaper, $lang;

csrf_init();

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "1")
{
    header("Location: ../../index.php");
    exit(0);
}

// Enforce that the user has access to risk management
enforce_permission("riskmanagement");

// Check if $cvss_key is set from parent scope
$key_suffix = isset($cvss_key) && $cvss_key !== '' ? "[{$cvss_key}]" : '';

// Set a global variable for the current app version, so we don't have to call a function every time
$current_app_version = current_version("app");

?>

<!-- CVSS Hidden Fields (these will be submitted with the form) -->
<input type='hidden' name='AccessVector<?php echo $key_suffix; ?>' id='AccessVector_hidden' value='<?php echo $escaper->escapeHtml($AccessVector); ?>' />
<input type='hidden' name='AccessComplexity<?php echo $key_suffix; ?>' id='AccessComplexity_hidden' value='<?php echo $escaper->escapeHtml($AccessComplexity); ?>' />
<input type='hidden' name='Authentication<?php echo $key_suffix; ?>' id='Authentication_hidden' value='<?php echo $escaper->escapeHtml($Authentication); ?>' />
<input type='hidden' name='ConfImpact<?php echo $key_suffix; ?>' id='ConfImpact_hidden' value='<?php echo $escaper->escapeHtml($ConfImpact); ?>' />
<input type='hidden' name='IntegImpact<?php echo $key_suffix; ?>' id='IntegImpact_hidden' value='<?php echo $escaper->escapeHtml($IntegImpact); ?>' />
<input type='hidden' name='AvailImpact<?php echo $key_suffix; ?>' id='AvailImpact_hidden' value='<?php echo $escaper->escapeHtml($AvailImpact); ?>' />
<input type='hidden' name='Exploitability<?php echo $key_suffix; ?>' id='Exploitability_hidden' value='<?php echo $escaper->escapeHtml($Exploitability); ?>' />
<input type='hidden' name='RemediationLevel<?php echo $key_suffix; ?>' id='RemediationLevel_hidden' value='<?php echo $escaper->escapeHtml($RemediationLevel); ?>' />
<input type='hidden' name='ReportConfidence<?php echo $key_suffix; ?>' id='ReportConfidence_hidden' value='<?php echo $escaper->escapeHtml($ReportConfidence); ?>' />
<input type='hidden' name='CollateralDamagePotential<?php echo $key_suffix; ?>' id='CollateralDamagePotential_hidden' value='<?php echo $escaper->escapeHtml($CollateralDamagePotential); ?>' />
<input type='hidden' name='TargetDistribution<?php echo $key_suffix; ?>' id='TargetDistribution_hidden' value='<?php echo $escaper->escapeHtml($TargetDistribution); ?>' />
<input type='hidden' name='ConfidentialityRequirement<?php echo $key_suffix; ?>' id='ConfidentialityRequirement_hidden' value='<?php echo $escaper->escapeHtml($ConfidentialityRequirement); ?>' />
<input type='hidden' name='IntegrityRequirement<?php echo $key_suffix; ?>' id='IntegrityRequirement_hidden' value='<?php echo $escaper->escapeHtml($IntegrityRequirement); ?>' />
<input type='hidden' name='AvailabilityRequirement<?php echo $key_suffix; ?>' id='AvailabilityRequirement_hidden' value='<?php echo $escaper->escapeHtml($AvailabilityRequirement); ?>' />

<!-- CVSS Modal -->
<div class="modal fade" id="cvssModal" tabindex="-1" aria-labelledby="cvssModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cvssModalLabel"><?php echo $escaper->escapeHtml($lang['CVSS2Calculator']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row">

                    <!-- Left Panel: CVSS Score + Help Desk -->
                    <div class="col-6 d-flex flex-column">

                        <!-- CVSS Scores -->
                        <div class="card-body border mb-2 flex-grow-0">
                            <h5><?php echo $escaper->escapeHtml($lang['CVSSScore']); ?></h5>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['BaseScore']); ?>:</label>
                                <div class="score-value form-control text-end" id="BaseScore">0</div>
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['ExploitabilityScore']); ?>:</label>
                                <div class="score-value form-control text-end" id="ExploitabilitySubscore">0</div>
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $escaper->escapeHtml($lang['ImpactScore']); ?>:</label>
                                <div class="score-value form-control text-end" id="ImpactSubscore">0</div>
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['TemporalScore']); ?>:</label>
                                <div class="score-value form-control text-end" id="TemporalScore">0</div>
                            </div>
                            <div class="score-item d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['EnvironmentalScore']); ?>:</label>
                                <div class="score-value form-control text-end" id="EnvironmentalScore">0</div>
                            </div>
                        </div>

                        <!-- Help Desk -->
                        <div class="card-body border flex-grow-1">
                            <h5>Help Desk</h5>
                            <?php view_cvss_help(); ?>
                        </div>

                    </div>

                    <!-- Right Panel: Metrics -->
                    <div class="col-6 d-flex flex-column">

                        <!-- Base Score Metrics -->
                        <div class="card-body border mb-2 flex-grow-0">
                            <h5><?php echo $escaper->escapeHtml($lang['BaseScoreMetrics']); ?></h5>
                            <h6 class="text-decoration-underline"><?php echo $escaper->escapeHtml($lang['ExploitabilityMetrics']); ?></h6>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['AttackVector']); ?>:</label>
                                <?php create_cvss_dropdown("AccessVector", $AccessVector); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('AccessVectorHelp');">
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['AttackComplexity']); ?>:</label>
                                <?php create_cvss_dropdown("AccessComplexity", $AccessComplexity); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('AccessComplexityHelp');">
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['Authentication']); ?>:</label>
                                <?php create_cvss_dropdown("Authentication", $Authentication); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('AuthenticationHelp');">
                            </div>

                            <h6 class="text-decoration-underline"><?php echo $escaper->escapeHtml($lang['ImpactMetrics']); ?></h6>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['ConfidentialityImpact']); ?>:</label>
                                <?php create_cvss_dropdown("ConfImpact", $ConfImpact); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('ConfImpactHelp');">
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['IntegrityImpact']); ?>:</label>
                                <?php create_cvss_dropdown("IntegImpact", $IntegImpact); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('IntegImpactHelp');">
                            </div>
                            <div class="score-item d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['AvailabilityImpact']); ?>:</label>
                                <?php create_cvss_dropdown("AvailImpact", $AvailImpact); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('AvailImpactHelp');">
                            </div>
                        </div>

                        <!-- Temporal Score Metrics -->
                        <div class="card-body border mb-2 flex-grow-0">
                            <h5><?php echo $escaper->escapeHtml($lang['TemporalScoreMetrics']); ?></h5>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label>Exploitability:</label>
                                <?php create_cvss_dropdown("Exploitability", $Exploitability, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('ExploitabilityHelp');">
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label>Remediation Level:</label>
                                <?php create_cvss_dropdown("RemediationLevel", $RemediationLevel, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('RemediationLevelHelp');">
                            </div>
                            <div class="score-item d-flex align-items-center">
                                <label>Report Confidence:</label>
                                <?php create_cvss_dropdown("ReportConfidence", $ReportConfidence, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('ReportConfidenceHelp');">
                            </div>
                        </div>

                        <!-- Environmental Score Metrics -->
                        <div class="card-body border mb-2 flex-grow-0">
                            <h5><?php echo $escaper->escapeHtml($lang['EnvironmentalScoreMetrics']); ?></h5>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['CollateralDamagePotential']); ?>:</label>
                                <?php create_cvss_dropdown("CollateralDamagePotential", $CollateralDamagePotential, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('CollateralDamagePotentialHelp');">
                            </div>
                            <div class="score-item d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['TargetDistribution']); ?>:</label>
                                <?php create_cvss_dropdown("TargetDistribution", $TargetDistribution, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('TargetDistributionHelp');">
                            </div>
                        </div>

                        <!-- Impact Subscore Modifiers -->
                        <div class="card-body border mb-2 flex-grow-0">
                            <h5><?php echo $escaper->escapeHtml($lang['ImpactSubscoreModifiers']); ?></h5>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['ConfidentialityRequirement']); ?>:</label>
                                <?php create_cvss_dropdown("ConfidentialityRequirement", $ConfidentialityRequirement, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('ConfidentialityRequirementHelp');">
                            </div>
                            <div class="score-item mb-2 d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['IntegrityRequirement']); ?>:</label>
                                <?php create_cvss_dropdown("IntegrityRequirement", $IntegrityRequirement, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('IntegrityRequirementHelp');">
                            </div>
                            <div class="score-item d-flex align-items-center">
                                <label><?php echo $escaper->escapeHtml($lang['AvailabilityRequirement']); ?>:</label>
                                <?php create_cvss_dropdown("AvailabilityRequirement", $AvailabilityRequirement, false); ?>
                                <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="showHelp('AvailabilityRequirementHelp');">
                            </div>
                        </div>

                        <div class="card-body border mb-2 flex-grow-1">
                            <button type="button" class="btn btn-primary w-100" id="cvssModalSaveBtn" data-bs-dismiss="modal">
                                <?php echo $escaper->escapeHtml($lang['SaveCVSSScore']); ?>
                            </button>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Optional inline CSS -->
<style>
    .score-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    .score-value {
        width: 80px;
        text-align: right;
        padding: 0.25rem 0.5rem;
        border: 1px solid #ccc;
        border-radius: 0.25rem;
        background-color: #f8f9fa;
    }
    .m-l-15 { margin-left: 0.5rem; }
</style>

<!-- JS to recalc CVSS scores -->
<script>
    /**********************************
     * CVSS v2 SCORE CALCULATION
     **********************************/

        // Key suffix for field names (set by PHP)
    const CVSS_KEY_SUFFIX = '<?php echo $key_suffix; ?>';

    // Base metric numeric values
    const CVSS2_BASE_VALUES = {
        AccessVector: { N: 1.0, A: 0.646, L: 0.395 },
        AccessComplexity: { L: 0.71, M: 0.61, H: 0.35 },
        Authentication: { N: 0.704, S: 0.56, M: 0.45 },
        Impact: { N: 0.0, P: 0.275, C: 0.66 }
    };

    // Temporal metric numeric values
    const CVSS2_EXPLOITABILITY_VALUES = { ND: 1.0, U: 0.85, POC: 0.9, F: 0.95, H: 1.0 };
    const CVSS2_REMEDIATION_LEVEL_VALUES = { ND: 1.0, OF: 0.87, TF: 0.90, W: 0.95, U: 1.0 };
    const CVSS2_REPORT_CONFIDENCE_VALUES = { ND: 1.0, UC: 0.90, UR: 0.95, C: 1.0 };

    // Environmental metric numeric values
    const CVSS2_ENV_VALUES = {
        CDP: { ND: 0.0, N: 0.0, L: 0.1, LM: 0.3, MH: 0.4, H: 0.5 },
        TD: { ND: 1.0, N: 0.0, L: 0.25, M: 0.75, H: 1.0 }
    };

    // Impact requirement modifiers
    const CVSS2_IMPACT_MODIFIER = { ND: 1.0, L: 0.5, M: 1.0, H: 1.51 };

    // Round helper
    function roundTo1Decimal(num) {
        return Math.round(num * 10) / 10;
    }

    // Get base metric numeric values
    function getBaseMetrics() {
        const AV = CVSS2_BASE_VALUES.AccessVector[document.getElementById('AccessVector').value] || 0;
        const AC = CVSS2_BASE_VALUES.AccessComplexity[document.getElementById('AccessComplexity').value] || 0;
        const Au = CVSS2_BASE_VALUES.Authentication[document.getElementById('Authentication').value] || 0;
        const C = CVSS2_BASE_VALUES.Impact[document.getElementById('ConfImpact').value] || 0;
        const I = CVSS2_BASE_VALUES.Impact[document.getElementById('IntegImpact').value] || 0;
        const A = CVSS2_BASE_VALUES.Impact[document.getElementById('AvailImpact').value] || 0;
        return { AV, AC, Au, C, I, A };
    }

    // Calculate base score
    function calculateBaseScore() {
        const { AV, AC, Au, C, I, A } = getBaseMetrics();
        const Impact = 10.41 * (1 - (1 - C) * (1 - I) * (1 - A));
        const Exploitability = 20 * AV * AC * Au;
        const f = Impact === 0 ? 0 : 1.176;
        const BaseScore = roundTo1Decimal(((0.6 * Impact) + (0.4 * Exploitability) - 1.5) * f);

        document.getElementById('ImpactSubscore').textContent = roundTo1Decimal(Impact);
        document.getElementById('ExploitabilitySubscore').textContent = roundTo1Decimal(Exploitability);
        document.getElementById('BaseScore').textContent = BaseScore;

        return { BaseScore, Exploitability, Impact };
    }

    // Calculate temporal score
    function calculateTemporalScore(baseScore) {
        const E  = CVSS2_EXPLOITABILITY_VALUES[document.getElementById('Exploitability').value] || 1;
        const RL = CVSS2_REMEDIATION_LEVEL_VALUES[document.getElementById('RemediationLevel').value] || 1;
        const RC = CVSS2_REPORT_CONFIDENCE_VALUES[document.getElementById('ReportConfidence').value] || 1;

        const TemporalScore = roundTo1Decimal(baseScore * E * RL * RC);
        document.getElementById('TemporalScore').textContent = TemporalScore;
        return TemporalScore;
    }

    // Calculate environmental score
    function calculateEnvironmentalScore() {
        const { AV, AC, Au, C, I, A } = getBaseMetrics();
        const Exploitability = 20 * AV * AC * Au;

        const CR = CVSS2_IMPACT_MODIFIER[document.getElementById('ConfidentialityRequirement').value] || 1;
        const IR = CVSS2_IMPACT_MODIFIER[document.getElementById('IntegrityRequirement').value] || 1;
        const AR = CVSS2_IMPACT_MODIFIER[document.getElementById('AvailabilityRequirement').value] || 1;

        const CDP = CVSS2_ENV_VALUES.CDP[document.getElementById('CollateralDamagePotential').value] || 0;
        const TD  = CVSS2_ENV_VALUES.TD[document.getElementById('TargetDistribution').value] || 1;

        const ModifiedImpact = Math.min(10, 10.41 * (1 - (1 - C * CR) * (1 - I * IR) * (1 - A * AR)));

        const f = ModifiedImpact === 0 ? 0 : 1.176;
        const AdjustedBase = ((0.6 * ModifiedImpact) + (0.4 * Exploitability) - 1.5) * f;

        const E  = CVSS2_EXPLOITABILITY_VALUES[document.getElementById('Exploitability').value] || 1;
        const RL = CVSS2_REMEDIATION_LEVEL_VALUES[document.getElementById('RemediationLevel').value] || 1;
        const RC = CVSS2_REPORT_CONFIDENCE_VALUES[document.getElementById('ReportConfidence').value] || 1;
        const AdjustedTemporal = AdjustedBase * E * RL * RC;

        const EnvironmentalScore = roundTo1Decimal((AdjustedTemporal + (10 - AdjustedTemporal) * CDP) * TD);
        document.getElementById('EnvironmentalScore').textContent = EnvironmentalScore;

        return EnvironmentalScore;
    }

    // Master CVSS calculation
    function calculateCVSS() {
        const { BaseScore } = calculateBaseScore();
        calculateTemporalScore(BaseScore);
        calculateEnvironmentalScore();
    }

    // Load values from hidden fields into modal selects
    function loadCVSSFromHiddenFields() {
        const fields = [
            'AccessVector', 'AccessComplexity', 'Authentication',
            'ConfImpact', 'IntegImpact', 'AvailImpact',
            'Exploitability', 'RemediationLevel', 'ReportConfidence',
            'CollateralDamagePotential', 'TargetDistribution',
            'ConfidentialityRequirement', 'IntegrityRequirement', 'AvailabilityRequirement'
        ];

        fields.forEach(fieldName => {
            const hidden = document.getElementById(fieldName + '_hidden');
            const select = document.getElementById(fieldName);
            if (hidden && select && hidden.value) {
                select.value = hidden.value;
            }
        });
    }

    // Save values from modal selects to hidden fields
    function saveCVSSToHiddenFields() {
        const fields = [
            'AccessVector', 'AccessComplexity', 'Authentication',
            'ConfImpact', 'IntegImpact', 'AvailImpact',
            'Exploitability', 'RemediationLevel', 'ReportConfidence',
            'CollateralDamagePotential', 'TargetDistribution',
            'ConfidentialityRequirement', 'IntegrityRequirement', 'AvailabilityRequirement'
        ];

        fields.forEach(fieldName => {
            const select = document.getElementById(fieldName);
            const hidden = document.getElementById(fieldName + '_hidden');
            if (select && hidden) {
                hidden.value = select.value;
            }
        });
    }

    // Recalculate on any select change
    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('cvssModal');
        if (!modalEl) return;

        // When modal is opened, load existing values
        modalEl.addEventListener('show.bs.modal', function() {
            loadCVSSFromHiddenFields();
        });

        // When modal is shown (fully visible), calculate scores
        modalEl.addEventListener('shown.bs.modal', function() {
            calculateCVSS();
        });

        // Recalculate on any select change within modal
        modalEl.addEventListener('change', function(e) {
            if (e.target.tagName === 'SELECT') {
                calculateCVSS();
            }
        });

        // Save button updates hidden fields
        const saveBtn = document.getElementById('cvssModalSaveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                saveCVSSToHiddenFields();
            });
        }
    });
</script>