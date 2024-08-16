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

<html dir="ltr" lang="en" xml:lang="en">
    <head>
        <title>SimpleRisk CVSS Calculator</title>
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

        <script language="javascript" src="../js/basescript.js?<?= $current_app_version ?>" type="text/javascript" defer></script>
        <script language="javascript" src="../js/simplerisk/cvss_scoring.js?<?= $current_app_version ?>" type="text/javascript" defer></script>

        <script type="text/javascript" language="JavaScript">

            var parent_window = window.opener;
            $(document).ready(function(){
                getCVE();
            })
            // Get the CVE information

            //    var AccessVector = parent_window.$("#AccessVector", parent_window.parentOfScores).val();
            //    var AccessComplexity = parent_window.$("#AccessComplexity", parent_window.parentOfScores).val();

            function cvssSubmit() {
                if (parent_window && !parent_window.closed) {
                    parent_window.$("#AccessVector", parent_window.parentOfScores).val( $("#AccessVector").val() )
                    parent_window.$("#AccessComplexity", parent_window.parentOfScores).val( $("#AccessComplexity").val() )
                    parent_window.$("#Authentication", parent_window.parentOfScores).val( $("#Authentication").val() )
                    parent_window.$("#ConfImpact", parent_window.parentOfScores).val( $("#ConfImpact").val() )
                    parent_window.$("#IntegImpact", parent_window.parentOfScores).val( $("#IntegImpact").val() )
                    parent_window.$("#AvailImpact", parent_window.parentOfScores).val( $("#AvailImpact").val() )
                    parent_window.$("#Exploitability", parent_window.parentOfScores).val( $("#Exploitability").val() )
                    parent_window.$("#RemediationLevel", parent_window.parentOfScores).val( $("#RemediationLevel").val() )
                    parent_window.$("#ReportConfidence", parent_window.parentOfScores).val( $("#ReportConfidence").val() )
                    parent_window.$("#CollateralDamagePotential", parent_window.parentOfScores).val( $("#CollateralDamagePotential").val() )
                    parent_window.$("#TargetDistribution", parent_window.parentOfScores).val( $("#TargetDistribution").val() )
                    parent_window.$("#ConfidentialityRequirement", parent_window.parentOfScores).val( $("#ConfidentialityRequirement").val() )
                    parent_window.$("#IntegrityRequirement", parent_window.parentOfScores).val( $("#IntegrityRequirement").val() )
                    parent_window.$("#AvailabilityRequirement", parent_window.parentOfScores).val( $("#AvailabilityRequirement").val() )
                }
            }

            function closeWindow() {
                window.opener.closepopup();
            }

            function submitandclose() {
                cvssSubmit();
                closeWindow();
            }

            function showHelp(divId) {
                $("#divHelp").html($("#"+divId).html());
            };

        </script>

    </head>

    <body topmargin="0" bottommargin="4" leftmargin="0" rightmargin="0" >
        <form name="frmCalc" method="post" action="" class="mb-0">
            <div class="score-method-page">
                <div class="score-method-page-header">
                    <h4>SimpleRisk CVSS V2.0 Calculator</h4>
                </div>
                <div class="score-method-page-body">
                    <div class="card-body mt-2 border">
                        <p class="mb-0">This page provides a calculator for creating <a href="http://www.first.org/cvss/" target="_blank" class='text-info'>CVSS</a> vulnerability severity scores.  The scores are computed in sequence such that the Base Score is used to calculate the Temporal Score and the Temporal Score is used to calculate the Environmental Score.</p>
                    </div>
                    <div class="row">
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-0">
                                <h5>CVSS Score</h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>CVSS Base Score:</label>
                                    <div class="score-value form-control text-end" id="BaseScore">0</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Subscore:</label>
                                    <div class="score-value form-control text-end" id="ImpactSubscore">0</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Exploitability Subscore:</label>
                                    <div class="score-value form-control text-end" id="ExploitabilitySubscore">0</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>CVSS Temporal Score:</label>
                                    <div class="score-value form-control text-end" id="TemporalScore">0</div>
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>CVSS Environmental Score:</label>
                                    <div class="score-value form-control text-end" id="EnvironmentalScore">0</div>
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-1">
                                <h5>Help Desk</h5>
                                <?php view_cvss_help(); ?>
                            </div>
                        </div>
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-0">
                                <h5>Base Score Metrics</h5>
                                <h6 class="text-decoration-underline">Exploitability Metrics</h6>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Attack Vector:</label>
                                    <?php create_cvss_dropdown("AccessVector") ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('AccessVectorHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Attack Complexity:</label>
                                    <?php create_cvss_dropdown("AccessComplexity") ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('AccessComplexityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Authentication:</label>
                                    <?php create_cvss_dropdown("Authentication") ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('AuthenticationHelp');">
                                </div>
                                <h6 class="text-decoration-underline">Impact Metrics</h6>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Confidentiality Impact:</label>
                                    <?php create_cvss_dropdown("ConfImpact") ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('ConfImpactHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Integrity Impact:</label>
                                    <?php create_cvss_dropdown("IntegImpact") ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('IntegImpactHelp');">
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Availability Impact:</label>
                                    <?php create_cvss_dropdown("AvailImpact") ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('AvailImpactHelp');">
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-0">
                                <h5>Temporal Score Metrics</h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Exploitability:</label>
                                    <?php create_cvss_dropdown("Exploitability", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('ExploitabilityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Remediation Level:</label>
                                    <?php create_cvss_dropdown("RemediationLevel", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('RemediationLevelHelp');">
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Report Confidence:</label>
                                    <?php create_cvss_dropdown("ReportConfidence", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('ReportConfidenceHelp');">
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-0">
                                <h5>Environmental Score Metrics</h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Collateral Damage Potential:</label>
                                    <?php create_cvss_dropdown("CollateralDamagePotential", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('CollateralDamagePotentialHelp');">
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Target Distribution:</label>
                                    <?php create_cvss_dropdown("TargetDistribution", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('TargetDistributionHelp');">
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-0">
                                <h5>Impact Subscore Modifiers</h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Confidentiality Requirement:</label>
                                    <?php create_cvss_dropdown("ConfidentialityRequirement", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('ConfidentialityRequirementHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Integrity Requirement:</label>
                                    <?php create_cvss_dropdown("IntegrityRequirement", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('IntegrityRequirementHelp');">
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Availability Requirement:</label>
                                    <?php create_cvss_dropdown("AvailabilityRequirement", NULL, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('AvailabilityRequirementHelp');">
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-1">
                                <input class="btn btn-submit" type="button" name="cvssSubmit" id="cvssSubmit" value="Submit" onclick="javascript: submitandclose();" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </body>
</html>