<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

    // Add various security headers
    add_security_headers();

    // Add the session
    $permissions = array(
        "check_access" => true,
        "check_riskmanagement" => true,
    );
    add_session_check($permissions);

    // Include the CSRF Magic library
    include_csrf_magic();

    // Include the SimpleRisk language file
    // Ignoring detections related to language files
    // @phan-suppress-next-line SecurityCheck-PathTraversal
    require_once(language_file());

    // Set a global variable for the current app version, so we don't have to call a function every time
    $current_app_version = current_version("app");
?>
<!doctype html>
<html dir="ltr" lang="en" xml:lang="en">
    <head>
        <title>SimpleRisk OWASP Calculator</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">        

        <!-- Favicon icon -->
        <?php setup_favicon("..");?>

        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>" />

        <!-- jQuery CSS -->
        <link rel="stylesheet" href="../vendor/node_modules/jquery-ui/dist/themes/base/jquery-ui.min.css?<?= $current_app_version ?>">

        <!-- extra css -->

        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

        <!-- jQuery Javascript -->
        <script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>
        <script src="../vendor/node_modules/jquery-ui/dist/jquery-ui.min.js?<?= $current_app_version ?>" id="script_jqueryui"></script>
        
        <!-- Bootstrap tether Core JavaScript -->
        <script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

        <script language="javascript" src="../js/basescript.js?<?= $current_app_version ?>" type="text/javascript"></script>
        <script language="javascript" src="../js/simplerisk/owasp_scoring.js?<?= $current_app_version ?>" type="text/javascript"></script>

        <script type="text/javascript" language="JavaScript">

        var parent_window = window.opener;

        $(document).ready(function(){
            // Initialize values for elements
            $("#SkillLevel").val(parent_window.$("#OWASPSkillLevel", parent_window.parentOfScores).val());
            $("#Motive").val(parent_window.$("#OWASPMotive", parent_window.parentOfScores).val());
            $("#Opportunity").val(parent_window.$("#OWASPOpportunity", parent_window.parentOfScores).val());
            $("#Size").val(parent_window.$("#OWASPSize", parent_window.parentOfScores).val());
            $("#EaseOfDiscovery").val(parent_window.$("#OWASPEaseOfDiscovery", parent_window.parentOfScores).val());
            $("#EaseOfExploit").val(parent_window.$("#OWASPEaseOfExploit", parent_window.parentOfScores).val());
            $("#Awareness").val(parent_window.$("#OWASPAwareness", parent_window.parentOfScores).val());
            $("#IntrusionDetection").val(parent_window.$("#OWASPIntrusionDetection", parent_window.parentOfScores).val());
            $("#LossOfConfidentiality").val(parent_window.$("#OWASPLossOfConfidentiality", parent_window.parentOfScores).val());
            $("#LossOfIntegrity").val(parent_window.$("#OWASPLossOfIntegrity", parent_window.parentOfScores).val());
            $("#LossOfAvailability").val(parent_window.$("#OWASPLossOfAvailability", parent_window.parentOfScores).val());
            $("#LossOfAccountability").val(parent_window.$("#OWASPLossOfAccountability", parent_window.parentOfScores).val());
            $("#FinancialDamage").val(parent_window.$("#OWASPFinancialDamage", parent_window.parentOfScores).val());
            $("#ReputationDamage").val(parent_window.$("#OWASPReputationDamage", parent_window.parentOfScores).val());
            $("#NonCompliance").val(parent_window.$("#OWASPNonCompliance", parent_window.parentOfScores).val());
            $("#PrivacyViolation").val(parent_window.$("#OWASPPrivacyViolation", parent_window.parentOfScores).val());
            updateScore();
        });

        function owaspSubmit() {
            if (parent_window && !parent_window.closed) {
                parent_window.$("#OWASPSkillLevel", parent_window.parentOfScores).val( $("#SkillLevel").val() )
                parent_window.$("#OWASPMotive", parent_window.parentOfScores).val( $("#Motive").val() )
                parent_window.$("#OWASPOpportunity", parent_window.parentOfScores).val( $("#Opportunity").val() )
                parent_window.$("#OWASPSize", parent_window.parentOfScores).val( $("#Size").val() )
                parent_window.$("#OWASPEaseOfDiscovery", parent_window.parentOfScores).val( $("#EaseOfDiscovery").val() )
                parent_window.$("#OWASPEaseOfExploit", parent_window.parentOfScores).val( $("#EaseOfExploit").val() )
                parent_window.$("#OWASPAwareness", parent_window.parentOfScores).val( $("#Awareness").val() )
                parent_window.$("#OWASPIntrusionDetection", parent_window.parentOfScores).val( $("#IntrusionDetection").val() )
                parent_window.$("#OWASPLossOfConfidentiality", parent_window.parentOfScores).val( $("#LossOfConfidentiality").val() )
                parent_window.$("#OWASPLossOfIntegrity", parent_window.parentOfScores).val( $("#LossOfIntegrity").val() )
                parent_window.$("#OWASPLossOfAvailability", parent_window.parentOfScores).val( $("#LossOfAvailability").val() )
                parent_window.$("#OWASPLossOfAccountability", parent_window.parentOfScores).val( $("#LossOfAccountability").val() )
                parent_window.$("#OWASPFinancialDamage", parent_window.parentOfScores).val( $("#FinancialDamage").val() )
                parent_window.$("#OWASPReputationDamage", parent_window.parentOfScores).val( $("#ReputationDamage").val() )
                parent_window.$("#OWASPNonCompliance", parent_window.parentOfScores).val( $("#NonCompliance").val() )
                parent_window.$("#OWASPPrivacyViolation", parent_window.parentOfScores).val( $("#PrivacyViolation").val() )
            }
        }

        function closeWindow() {
            window.opener.closepopup();
        }

        function submitandclose() {
            owaspSubmit();
            closeWindow();
        }

        function showHelp(divId) {
            $("#divHelp").html($("#"+divId).html());
        };

        </script>
    </head>

    <body topmargin="0" bottommargin="4" leftmargin="0" rightmargin="0" >
        <form name="frmCalc" method="post" action="" >
            <div class="score-method-page">
                <div class="score-method-page-header">
                    <h4>SimpleRisk OWASP Calculator</h4>
                </div>
                <div class="score-method-page-body">
                    <div class="card-body mt-2 border">
                        <p class="mb-0">This page provides a calculator for creating <a href="https://www.owasp.org/index.php/OWASP_Risk_Rating_Methodology" target="_blank" class="text-info">OWASP</a> vulnerability severity scores.  You'll need to gather information about the threat agent involved, the attack they're using, the vulnerability involved, and the impact of a successful exploit on your business.  There may be multiple possible groups of attackers, or even multiple possible business impacts.  In general, it's best to err on the side of caution by using the worst-case option, as that will result in the highest overall risk.</p>
                    </div>
                    <div class="row">
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-0">
                                <h5>OWASP Score</h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Overall Likelihood:</label>
                                    <div class="score-value form-control text-end" id="Likelihood">10</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Threat Agent Factors:</label>
                                    <div class="score-value form-control text-end" id="ThreatAgentScore">10</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Vulnerability Factors:</label>
                                    <div class="score-value form-control text-end" id="VulnerabilityScore">10</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Overall Impact:</label>
                                    <div class="score-value form-control text-end" id="Impact">10</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Technical Impact:</label>
                                    <div class="score-value form-control text-end" id="TechnicalScore">10</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Business Impact:</label>
                                    <div class="score-value form-control text-end" id="BusinessScore">10</div>
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Overall OWASP Score:</label>
                                    <div class="score-value form-control text-end" id="OverallScore">10</div>
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-1">
                                <h5>Help Desk</h5>
                                <?php view_owasp_help(); ?>
                            </div> 
                        </div>
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-0">
                                <h5>Likelihood</h5>
                                <h6 class="text-decoration-underline">Threat Agent Factors</h6>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Skill Level:</label>
                                    <?php create_numeric_dropdown("SkillLevel", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('SkillLevelHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Motive:</label>
                                    <?php create_numeric_dropdown("Motive", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('MotiveHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Opportunity:</label>
                                    <?php create_numeric_dropdown("Opportunity", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('OpportunityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Size:</label>
                                    <?php create_numeric_dropdown("Size", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('SizeHelp');">
                                </div>
                                <h6 class="text-decoration-underline">Vulnerability Factors</h6>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Ease of Discovery:</label>
                                    <?php create_numeric_dropdown("EaseOfDiscovery", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('EaseOfDiscoveryHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Ease of Exploit:</label>
                                    <?php create_numeric_dropdown("EaseOfExploit", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('EaseOfExploitHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Awareness:</label>
                                    <?php create_numeric_dropdown("Awareness", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('AwarenessHelp');">
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Intrusion Detection:</label>
                                    <?php create_numeric_dropdown("IntrusionDetection", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('IntrusionDetectionHelp');">
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-0">
                                <h5>Impact</h5>
                                <h6 class="text-decoration-underline">Technical Impact</h6>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Loss of Confidentiality:</label>
                                    <?php create_numeric_dropdown("LossOfConfidentiality", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('LossOfConfidentialityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Loss of Integrity:</label>
                                    <?php create_numeric_dropdown("LossOfIntegrity", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('LossOfIntegrityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Loss of Availability:</label>
                                    <?php create_numeric_dropdown("LossOfAvailability", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('LossOfAvailabilityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Loss of Accountability:</label>
                                    <?php create_numeric_dropdown("LossOfAccountability", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('LossOfAccountabilityHelp');">
                                </div>
                                <h6 class="text-decoration-underline">Business Impact</h6>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Financial Damage:</label>
                                    <?php create_numeric_dropdown("FinancialDamage", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('FinancialDamageHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Reputation Damage:</label>
                                    <?php create_numeric_dropdown("ReputationDamage", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('ReputationDamageHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Non-Compliance:</label>
                                    <?php create_numeric_dropdown("NonCompliance", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('NonComplianceHelp');">
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Privacy Violation:</label>
                                    <?php create_numeric_dropdown("PrivacyViolation", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('PrivacyViolationHelp');">
                                </div>
                            </div>
                            <div class="card-body border my-2 flex-grow-1">
                                <input class="btn btn-submit" type="button" name="owaspSubmit" id="owaspSubmit" value="Submit" onclick="javascript: submitandclose();" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </body>
</html>