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
        <title>SimpleRisk DREAD Calculator</title>
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
        <script language="javascript" src="../js/simplerisk/dread_scoring.js?<?= $current_app_version ?>" type="text/javascript" defer></script>
        
        <script type="text/javascript" language="JavaScript">

            var parent_window = window.opener;
            
            $(document).ready(function(){
                // Initialize values for elements

                $("#DamagePotential").val(parent_window.$("#DREADDamage", parent_window.parentOfScores).val());
                $("#Reproducibility").val(parent_window.$("#DREADReproducibility", parent_window.parentOfScores).val());
                $("#Exploitability").val(parent_window.$("#DREADExploitability", parent_window.parentOfScores).val());
                $("#AffectedUsers").val(parent_window.$("#DREADAffectedUsers", parent_window.parentOfScores).val());
                $("#Discoverability").val(parent_window.$("#DREADDiscoverability", parent_window.parentOfScores).val());
                updateScore();
            })

            function dreadSubmit() {
                if (parent_window && !parent_window.closed) {
                    parent_window.$("#DREADDamage", parent_window.parentOfScores).val( $("#DamagePotential").val() )
                    parent_window.$("#DREADReproducibility", parent_window.parentOfScores).val( $("#Reproducibility").val() )
                    parent_window.$("#DREADExploitability", parent_window.parentOfScores).val( $("#Exploitability").val() )
                    parent_window.$("#DREADAffectedUsers", parent_window.parentOfScores).val( $("#AffectedUsers").val() )
                    parent_window.$("#DREADDiscoverability", parent_window.parentOfScores).val( $("#Discoverability").val() )
                }
            }

            function closeWindow() {
                window.opener.closepopup();
            }

            function submitandclose() {
                dreadSubmit();
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
                    <h4>SimpleRisk DREAD Calculator</h4>
                </div>
                <div class="score-method-page-body">
                    <div class="card-body mt-2 border">
                        <p class="mb-0">This page provides a calculator for creating <a href="http://en.wikipedia.org/wiki/DREAD:_Risk_assessment_model" target="_blank" class="text-info">DREAD</a> vulnerability severity scores.  DREAD is a classification scheme for quantifying, comparing and prioritizing the amount of risk presented by each evaluated threat. The DREAD acronym is formed from the first letter of each category below.  DREAD modeling influences the thinking behind setting the risk rating, and is also used directly to sort the risks. The DREAD algorithm, shown below, is used to compute a risk value, which is an average of all five categories.</p>
                    </div>
                    <div class="row">
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-0">
                                <h5>DREAD Score</h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Damage Potential:</label>
                                    <div class="score-value form-control text-end" id="DamagePotentialScore">0</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Reproducibility:</label>
                                    <div class="score-value form-control text-end" id="ReproducibilityScore">0</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Exploitability:</label>
                                    <div class="score-value form-control text-end" id="ExploitabilityScore">0</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Affected Users:</label>
                                    <div class="score-value form-control text-end" id="AffectedUsersScore">0</div>
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Discoverability:</label>
                                    <div class="score-value form-control text-end" id="DiscoverabilityScore">0</div>
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Overall DREAD Score:</label>
                                    <div class="score-value form-control text-end" id="OverallScore">0</div>
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-1">
                                <h5>Help Desk</h5>
                                <?php view_dread_help(); ?>
                            </div> 
                        </div>
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-0">
                                <h5>Categories</h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Damage Potential:</label>
                                    <?php create_numeric_dropdown("DamagePotential", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('DamagePotentialHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Reproducibility:</label>
                                    <?php create_numeric_dropdown("Reproducibility", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('ReproducibilityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Exploitability:</label>
                                    <?php create_numeric_dropdown("Exploitability", 10, false)?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('ExploitabilityHelp');">
                                </div>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label>Affected Users:</label>
                                    <?php create_numeric_dropdown("AffectedUsers", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('AffectedUsersHelp');">
                                </div>
                                <div class="score-item d-flex align-items-center">
                                    <label>Discoverability:</label>
                                    <?php create_numeric_dropdown("Discoverability", 10, false) ?>
                                    <img class="m-l-15" src="../images/helpicon.jpg" width="25" height="18" align="absmiddle" onclick="javascript:showHelp('DiscoverabilityHelp');">
                                </div>
                            </div>
                            <div class="card-body border mb-2 flex-grow-1">
                                <input class="btn btn-submit" type="button" name="dreadSubmit" id="dreadSubmit" value="Submit" onclick="javascript: submitandclose();" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </body>
</html>