<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (CSP_ENABLED == "true")
{
    // Add the Content-Security-Policy header
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
}

// Session handler is database
if (USE_DATABASE_FOR_SESSIONS == "true")
{
    session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
}

// Start the session
session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
session_start('SimpleRisk');

// Include the language file
require_once(language_file());

require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
    header("Location: ../index.php");
    exit(0);
}

// Check if the user has access to plan mitigations
if (!isset($_SESSION["plan_mitigations"]) || $_SESSION["plan_mitigations"] != 1)
{
    $plan_mitigations = false;

    // Display an alert
    set_alert(true, "bad", "You do not have permission to plan mitigations.  Any mitigations that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
}
else $plan_mitigations = true;

// Check if a risk ID was sent
if (isset($_GET['id']) || isset($_POST['id']))
{
    if (isset($_GET['id']))
    {
        // Test that the ID is a numeric value
        $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);
    }
    else if (isset($_POST['id']))
    {
        // Test that the ID is a numeric value
        $id = (is_numeric($_POST['id']) ? (int)$_POST['id'] : 0);
    }

    // If team separation is enabled
    if (team_separation_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // If the user does not have access to the risk
        if (!extra_grant_access($_SESSION['uid'], $id))
        {
            // Redirect back to the page the workflow started on
            header("Location: " . $_SESSION["workflow_start"]);
            exit(0);
        }
    }

    // If the classic risk was updated and the user has the ability to modify the risk
    if (isset($_POST['update_classic']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
        $CLASSIC_likelihood = (int)$_POST['likelihood'];
        $CLASSIC_impact = (int)$_POST['impact'];

        // Update the risk scoring
        update_classic_score($id, $CLASSIC_likelihood, $CLASSIC_impact);
    }
    // If the cvss risk was updated and the user has the ability to modify the risk
    else if (isset($_POST['update_cvss']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
        $AccessVector = $_POST['AccessVector'];
        $AccessComplexity = $_POST['AccessComplexity'];
        $Authentication = $_POST['Authentication'];
        $ConfImpact = $_POST['ConfImpact'];
        $IntegImpact = $_POST['IntegImpact'];
        $AvailImpact = $_POST['AvailImpact'];
        $Exploitability = $_POST['Exploitability'];
        $RemediationLevel = $_POST['RemediationLevel'];
        $ReportConfidence = $_POST['ReportConfidence'];
        $CollateralDamagePotential = $_POST['CollateralDamagePotential'];
        $TargetDistribution = $_POST['TargetDistribution'];
        $ConfidentialityRequirement = $_POST['ConfidentialityRequirement'];
        $IntegrityRequirement = $_POST['IntegrityRequirement'];
        $AvailabilityRequirement = $_POST['AvailabilityRequirement'];

        // Update the risk scoring
        update_cvss_score($id, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement);
    }
    // If the dread risk was updated and the user has the ability to modify the risk
    else if (isset($_POST['update_dread']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
        $DREADDamagePotential = (int)$_POST['DamagePotential'];
        $DREADReproducibility = (int)$_POST['Reproducibility'];
        $DREADExploitability = (int)$_POST['Exploitability'];
        $DREADAffectedUsers = (int)$_POST['AffectedUsers'];
        $DREADDiscoverability = (int)$_POST['Discoverability'];

        // Update the risk scoring
        update_dread_score($id, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability);
    }
    // If the owasp risk was updated and the user has the ability to modify the risk
    else if (isset($_POST['update_owasp']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
        $OWASPSkillLevel = (int)$_POST['SkillLevel'];
        $OWASPMotive = (int)$_POST['Motive'];
        $OWASPOpportunity = (int)$_POST['Opportunity'];
        $OWASPSize = (int)$_POST['Size'];
        $OWASPEaseOfDiscovery = (int)$_POST['EaseOfDiscovery'];
        $OWASPEaseOfExploit = (int)$_POST['EaseOfExploit'];
        $OWASPAwareness = (int)$_POST['Awareness'];
        $OWASPIntrusionDetection = (int)$_POST['IntrusionDetection'];
        $OWASPLossOfConfidentiality = (int)$_POST['LossOfConfidentiality'];
        $OWASPLossOfIntegrity = (int)$_POST['LossOfIntegrity'];
        $OWASPLossOfAvailability = (int)$_POST['LossOfAvailability'];
        $OWASPLossOfAccountability = (int)$_POST['LossOfAccountability'];
        $OWASPFinancialDamage = (int)$_POST['FinancialDamage'];
        $OWASPReputationDamage = (int)$_POST['ReputationDamage'];
        $OWASPNonCompliance = (int)$_POST['NonCompliance'];
        $OWASPPrivacyViolation = (int)$_POST['PrivacyViolation'];

        // Update the risk scoring
        update_owasp_score($id, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation);
    }
    // If the custom risk was updated and the user has the ability to modify the risk
    else if (isset($_POST['update_custom']) && isset($_SESSION["modify_risks"]) && $_SESSION["modify_risks"] == 1)
    {
        $custom = (float)$_POST['Custom'];

        // Update the risk scoring
        update_custom_score($id, $custom);
    }

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
        $technology = $risk[0]['technology'];
        $owner = $risk[0]['owner'];
        $manager = $risk[0]['manager'];
        $assessment = $risk[0]['assessment'];
        $notes = $risk[0]['notes'];
        $submission_date = $risk[0]['submission_date'];
        $mitigation_id = $risk[0]['mitigation_id'];
        $mgmt_review = $risk[0]['mgmt_review'];
        $calculated_risk = $risk[0]['calculated_risk'];
        $risk_level = get_risk_level_name($calculated_risk);
        $next_review = $risk[0]['next_review'];
        $color = get_risk_color($id);

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
    }
    // If the risk was not found use null values
    else
    {
        $submitted_by = "";
        $status = "Risk ID Does Not Exist";
        $subject = "N/A";
        $reference_id = "N/A";
        $regulation = "";
        $control_number = "N/A";
        $location = "";
        $source = "";
        $category = "";
        $team = "";
        $technology = "";
        $owner = "";
        $manager = "";
        $assessment = "";
        $notes = "";
        $submission_date = "";
        $mitigation_id = "";
        $mgmt_review = "";
        $calculated_risk = "0.0";

        $scoring_method = "";
        $CLASSIC_likelihood = "";
        $CLASSIC_impact = "";
        $AccessVector = "";
        $AccessComplexity = "";
        $Authentication = "";
        $ConfImpact = "";
        $IntegImpact = "";
        $AvailImpact = "";
        $Exploitability = "";
        $RemediationLevel = "";
        $ReportConfidence = "";
        $CollateralDamagePotential = "";
        $TargetDistribution = "";
        $ConfidentialityRequirement = "";
        $IntegrityRequirement = "";
        $AvailabilityRequirement = "";
    }

    if ($submission_date == "")
    {
        $submission_date = "N/A";
    }
    else $submission_date = date(DATETIME, strtotime($submission_date));

    // Get the mitigation for the risk
    $mitigation = get_mitigation_by_id($id);

    // If no mitigation exists for this risk
    if ($mitigation == false)
    {
        // Set the values to empty
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
    }
    // If a mitigation exists
    else
    {
        // Set the mitigation values
        $mitigation_date = $mitigation[0]['submission_date'];
        $mitigation_date = date(DATETIME, strtotime($mitigation_date));
        $planning_strategy = $mitigation[0]['planning_strategy'];
        $mitigation_effort = $mitigation[0]['mitigation_effort'];
        $mitigation_cost = $mitigation[0]['mitigation_cost'];
        $mitigation_owner = $mitigation[0]['mitigation_owner'];
        $mitigation_team = $mitigation[0]['mitigation_team'];
        $current_solution = $mitigation[0]['current_solution'];
        $security_requirements = $mitigation[0]['security_requirements'];
        $security_recommendations = $mitigation[0]['security_recommendations'];
    }

    // Get the management reviews for the risk
    $mgmt_reviews = get_review_by_id($id);

    // If no management review exists for this risk
    if ($mgmt_reviews == false)
    {
        // Set the values to empty
        $review_date = "N/A";
        $review = "";
        $next_step = "";
        $next_review = "";
        $reviewer = "";
        $comments = "";
    }
    // If a management review exists
    else
    {
        // Set the management review values
        $review_date = $mgmt_reviews[0]['submission_date'];
        $review_date = date(DATETIME, strtotime($review_date));
        $review = $mgmt_reviews[0]['review'];
        $next_step = $mgmt_reviews[0]['next_step'];
        $next_review = next_review($color, $id, $next_review, false);
        $reviewer = $mgmt_reviews[0]['reviewer'];
        $comments = $mgmt_reviews[0]['comments'];
    }
}

// Check if a new risk mitigation was submitted and the user has permissions to plan mitigations
if ((isset($_POST['submit'])) && $plan_mitigations)
{
    $status = "Mitigation Planned";
    $planning_strategy = (int)$_POST['planning_strategy'];
    $mitigation_effort = (int)$_POST['mitigation_effort'];
    $mitigation_cost = (int)$_POST['mitigation_cost'];
    $mitigation_owner = (int)$_POST['mitigation_owner'];
    $mitigation_team = (int)$_POST['mitigation_team'];
    $current_solution = $_POST['current_solution'];
    $security_requirements = $_POST['security_requirements'];
    $security_recommendations = $_POST['security_recommendations'];

    // If a mitigation does not exist
    if ($mitigation == false)
    {
        // Submit a new mitigation
        submit_mitigation($id, $status, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations);
    }
    // Otherwise, a mitigation already exists
    else
    {
        // Update the mitigation
        update_mitigation($id, $planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations);
    }

    // If a file was submitted
    if (!empty($_FILES))
    {
        // Upload any file that is submitted
        $error = upload_file($id-1000, $_FILES['file'], 2);
    }
    // Otherwise, success
    else $error = 1;

    // Display an alert
    set_alert(true, "good", "Mitigation submitted successfully!");

    // Redirect back to the page the workflow started on
    header("Location: mitigate.php" . "?mitigated=true&id=".$_GET['id']."&page_type=1");
}
?>

<html ng-app="simplerisk">
<head>
    <title>Simple Risk</title>
    <!-- build:css vendor/vendor.min.css -->
    <link rel="stylesheet" type="text/css" href="../css/bootstrap.min.css" media="screen" />
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" type="text/css" href="../css/style.css" media="screen" />
    <!-- endbuild -->
    <!-- build:css style.min.css -->

    <!-- endbuild -->
    <script src="../js/angular/angular.min.js"></script>
    <script src="../js/jquery-2.1.3.min.js"></script>
    <script src="../js/jquery-ui.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/angular/angular-resource.min.js"></script>
    <script src="../js/angular/ng-file-upload-shim.min.js"></script>
    <script src="../js/angular/ng-file-upload.min.js"></script>
    <script src="../js/angular/ui-bootstrap-tpls-0.3.0.min.js"></script>
    <script src = "http://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
    <script>
        $( function() {
            $("#tabs").tabs({
                beforeActivate: function (event, ui) {
                    if(ui.newPanel.attr('id') == "tabs1"){
                        $("#action_btns").hide();
                    }
                    else if(ui.newPanel.attr('id') == "tabs2"){
                        $("#action_btns").show();
                    }
                    else if(ui.newPanel.attr('id') == "tabs3"){
                        $("#action_btns").hide();
                    }
                }
            });
            $("#save_btn").hide()
            if($("#page_type").val()== '1'){
                $( "#tabs" ).tabs({ active: 1 });
                $("#edit_btn").show();
                $("#save_btn").hide();
            }else if( $("#page_type").val()== '2'){
                $( "#tabs" ).tabs({ active: 2});
                $("#action_btns").hide();

            }else{
                $( "#tabs" ).tabs({ active: 0});
                $("#action_btns").hide();
            }


            $("#save_btn").click(function () {
                $("#save_mitigation").click();
                $("#edit_btn").show();
                $("#save_btn").hide();
             });
             $("#edit_btn").click(function () {
                 $("#edit_btn").hide();
                 $("#save_btn").show();
              });
            $("#tabs1").click(function () {
                $("#action_btns").hide();
            });


        });
    </script>


</head>
<body ng-controller="ProfileCtrl">
<?php view_top_menu("RiskManagement"); ?>

<div class="tabs">
    <div class="container">
        <div class="tab add" ng-click="newRisk()">
            <span>+</span>
        </div>
        <div class="tab" ng-click="selectRisk($index)" ng-class="{'selected': $index == selected}">
            <div>
                <span title="{{risk.title}}"><strong ng-show="risk.id">ID: {{risk.id}}</strong> {{risk.title}}</span>
            </div>
            <button class="close" aria-label="Close" style="margin-left: 10px;" ng-click="closeRisk($index)">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
</div>
<div class="container">
    <div class="leftbar-menu">
        <!-- <div class="item" data-index="1" ui-sref-active="selected"><a href="/management/index.php">Submit Risk</a></div>
         <div class="item" data-index="2" ui-sref-active="selected"><a href="/management/plan_mitigations.php">Plan Mitigation</a></div>
         <div class="item" data-index="3" ui-sref-active="selected"><a href="/management/review_risks.php" ui-sref="risk.review">Perform Reviews</a></div>
         <div class="item" data-index="4" ui-sref-active="selected"><a ui-sref="risk.project">Plan Projects</a></div>
         <div class="item" data-index="5" ui-sref-active="selected">Review Regurarly</div>-->
        <div class="span3 leftmenu">
            <?php view_risk_management_menu("PlanYourMitigations"); ?>
        </div>
    </div>
    <input type="hidden" id="page_type" value="<?php echo $_GET["page_type"];?>">
    <div class="risk-content">
        <div>
            <div class="risk-session overview">
                <div class="col-sm-2">
                    <div class="score">
                        <span>4.5</span>
                        Moderate
                    </div>
                </div>
                <div class="col-sm-2 info">
                    <label>ID #:</label> {{risk.id}}
                </div>
                <div class="col-sm-3 info">
                    <label>Subject: </label> {{risk.subject}}
                </div>
                <div class="col-sm-3 info">
                    <label>Status: </label> {{risk.status}}
                </div>
                <div class="col-sm-2 info">

                </div>
                <div class="col-sm-12 details risk-test">
                    View Risk Scorring details
                </div>
            </div>
            <div id="tabs" class="risk-details">
                <div class="row">
                    <ul>
                        <li><a href="#tabs1">Details</a></li>
                        <li><a href="#tabs2">Mitigation</a></li>
                        <li><a href="#tabs3">Review</a></li>
                    </ul>
                    <!--<div class="col-sm-1 subtab" id="#tabs1" >
                        Details
                    </div>
                    <div class="col-sm-1 subtab" id="#tabs2" >
                        Mitigation
                    </div>
                    <div class="col-sm-1 subtab" id="#tabs3" >
                        Review
                    </div>-->
                    <div id="action_btns" class="col-sm-8">
                        <button class="btn btn-primary pull-right btn-sm" id="cancel_btn" >Cancel</button>
                        <button class="btn btn-default pull-right btn-sm" id="edit_btn" >Edit Details</button>
                        <button class="btn btn-primary pull-right btn-sm" id="save_btn" >Save Details</button>
                    </div>
                    <div class="col-sm-12">
                        <hr />
                    </div>
                    <div class="col-sm-12">
                        <div id="tabs1">
                            <?php view_risk_details($id, $submission_date, $submitted_by, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes); ?>
                        </div>
                        <div id="tabs2">

                            <?php edit_mitigation_submission($planning_strategy, $mitigation_effort, $mitigation_cost, $mitigation_owner, $mitigation_team, $current_solution, $security_requirements, $security_recommendations); ?>
                        </div>
                        <div id="tabs3">
                            <?php view_review_details($id, $review_date, $reviewer, $review, $next_step, $next_review, $comments); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="risk-session comments" id="accordion" ng-controller="commentCtrl">
                <h3 class="clearfix"><i class="fa fa-caret-right"></i>Comments <a href="#" class="pull-right"><i class="fa fa-plus-circle"></i></a></h3>
                <div>
                    <div class="comment--form">
                        <div ng-show="comment_need">Please fill the required fields</div>
                        <form name="commentform">
                            <div class="form-group">
                                <input type="hidden" ng-init="user = <?php echo ($_SESSION['uid'] !== null)? $_SESSION['uid']:''; ?>" ng-model="user" name="user" value="" />
                                <textarea ng-model="comment" name="comment" id="comment" cols="30" rows="10" class="form-control" ng-required="true" required></textarea>
                            </div>
                            <div class="form-group text-right">
                                <a href="" class="btn btn-primary">Reset</a>
                                <a href="javascript:void(0);" class="btn btn-danger" ng-click="commentSave(commentform);">Submit</a>
                            </div>
                        </form>
                    </div>
                    <ul class="comments-list">
                        <li ng-repeat="comment in comments">
                            <div class="comment">
                                <strong>{{ comment.time }} {{ comment.user }}</strong>
                                <p>{{ comment.comment }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <h3><i class="fa fa-caret-right"></i> Audit Trail</h3>
                <div>
                    <ul class="audit-trail">
                        <li>Audit Trail</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</body>
<script src="../js/angular/app.js"></script>
<script src="../js/angular/utils.js"></script>
<script src="../js/angular/resources.js"></script>
<script src="../js/controller/risk_controller.js"></script>
<script src="../js/controller/mitigation_controller.js"></script>
</html>

