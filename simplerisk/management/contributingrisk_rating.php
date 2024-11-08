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
        <title>SimpleRisk Contributing Risk Calculator</title>
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
        <script language="javascript" src="../js/simplerisk/contributingrisk_scoring.js?<?= $current_app_version ?>" type="text/javascript"></script>

        <script type="text/javascript" language="JavaScript">

            var parent_window = window.opener;
            
            $(document).ready(function(){
                // Initialize values for elements

                $("#contributing_likelihood").val(parent_window.$("#contributing_likelihood", parent_window.parentOfScores).val());

                parent_window.$(".contributing-impact", parent_window.parentOfScores).each(function(){
                    $("#" + $(this).attr("id")).val($(this).val())
                })
                
                updateScore();
                
                $("#contributing_likelihood, .contributing_impact select").change(function(){
                    updateScore();
                })
            })

            function contributingRiskSubmit() {
                if (parent_window && !parent_window.closed) {
                    parent_window.$("#contributing_likelihood", parent_window.parentOfScores).val( $("#contributing_likelihood").val() );
                    
                    $("div.contributing-risk-table select").each(function(){
                        parent_window.$("#"+$(this).attr("id"), parent_window.parentOfScores).val( $(this).val() )
                    })
                }
            }

            function closeWindow() {
                window.opener.closepopup();
            }

            function submitandclose() {
                contributingRiskSubmit();
                closeWindow();
            }

        </script>
    </head>

    <body topmargin="0" bottommargin="4" leftmargin="0" rightmargin="0" >
        <form name="frmCalc" method="post" action="" >
            <div class="score-method-page">
                <div class="score-method-page-header">
                    <h4><?= $escaper->escapeHtml($lang["SimpleriskContributingRiskCalculator"]); ?></h4>
                </div>
                <div class="score-method-page-body">
                    <div class="card-body mt-2 border">
                        <p class="mb-0"><?= $escaper->escapeHtml($lang["ContributingRiskCalendarDescription"]) ?></p>
                    </div>
                    <div class="row">
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-1">
                                <h5><?= $escaper->escapeHtml($lang["ContributingRiskScore"]) ?></h5>
                                <div class="score-item mb-2 d-flex align-items-center">
                                    <label><?= $escaper->escapeHtml($lang["OverallContributingRiskScore"]) ?>:</label>
                                    <div class="score-value form-control text-end" id="OverallScore">10</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 d-flex flex-column">
                            <div class="card-body border my-2 flex-grow-0">
                                <h5><?= $escaper->escapeHtml($lang['Likelihood']) ?></h5>
                                <?php create_dropdown("contributing_risks_likelihood", NULL, "contributing_likelihood", false); ?>
                            </div>
                            <div class="card-body border my-2 flex-grow-0 contributing-risk-table">
                                <h5><?= $escaper->escapeHtml($lang["ContributingRisk"]) ?></h5>
                                <?php display_contributing_risk_from_calculator(); ?>
                            </div>
                            <div class="card-body border my-2 flex-grow-1">
                                <input type="button" class="btn btn-submit" name="contributingRiskSubmit" id="contributingRiskSubmit" value="Submit" onclick="javascript: submitandclose();" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </body>
</html>