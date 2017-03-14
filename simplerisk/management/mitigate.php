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

if (!isset($_SESSION))
{
        session_name('SimpleRisk');
        session_start();
}

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
    $sub_date = $risk[0]['submission_date'];
    $submission_date = date( "m/d/Y", strtotime( $sub_date ) );
    $mitigation_id = $risk[0]['mitigation_id'];
    $mgmt_review = $risk[0]['mgmt_review'];
    $calculated_risk = $risk[0]['calculated_risk'];
    $risk_level = get_risk_level_name($calculated_risk);
    $next_review = $risk[0]['next_review'];
    $color = get_risk_color($calculated_risk);

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
  else $submission_date = date("m/d/Y", strtotime($submission_date));

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
    $planning_date = "";
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
    $planning_date = ($mitigation[0]['planning_date'] && $mitigation[0]['planning_date'] != "0000-00-00") ? date('m/d/Y', strtotime($mitigation[0]['planning_date'])) : "";
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
    $next_review = next_review($risk_level, $id, $next_review, false);
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

  $planning_date = $_POST['planning_date'];

  if (!validate_date($planning_date, 'm/d/Y'))
  {
    $planning_date = "0000-00-00";
  }
  // Otherwise, set the proper format for submitting to the database
  else
  {
    $planning_date = date("Y-m-d", strtotime($planning_date));
  }

  // If a mitigation does not exist
  if ($mitigation == false)
  {
    // Submit a new mitigation
    submit_mitigation($id, $status, $_POST);
  }
  // Otherwise, a mitigation already exists
  else
  {
    // Update the mitigation
    update_mitigation($id, $_POST);
  }

    $unique_names = empty($_POST['unique_names']) ? "" : $_POST['unique_names'];
    refresh_files_for_risk($unique_names, $id-1000, 2);
  
//    refresh_files_for_risk($_POST['unique_names'], $id-1000, 2);

    $error = 1;
    // If a file was submitted
    if (!empty($_FILES))
    {
      // Upload any file that is submitted
        for($i=0; $i<count($_FILES['file']['name']); $i++){
            if($_FILES['file']['error'][$i] || $i==0){
               continue; 
            }
            $file = array(
                'name' => $_FILES['file']['name'][$i],
                'type' => $_FILES['file']['type'][$i],
                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                'size' => $_FILES['file']['size'][$i],
                'error' => $_FILES['file']['error'][$i],
            );
            // Upload any file that is submitted
            $error = upload_file($id-1000, $file, 2);
            if($error != 1){
                /**
                * If error, stop uploading files;
                */
                break;
            }
        }
      
//      $error = upload_file($id-1000, $_FILES['file'], 2);
    }

  // Otherwise, success
  else $error = 1;

  // Display an alert
  set_alert(true, "good", "Mitigation submitted successfully!");

  // Redirect back to the page the workflow started on
  header("Location: " . $_SESSION["workflow_start"] . "?id=". $id . "&type=1");
}
?>

<!doctype html>
<html>

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/jquery-ui.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script language="javascript" src="../js/basescript.js" type="text/javascript"></script>
  <script src="../js/highcharts/code/highcharts.js"></script>
  <script src="../js/common.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/display.css">
  <script type="text/javascript">
      function showScoreDetails() {
        document.getElementById("scoredetails").style.display = "";
        document.getElementById("hide").style.display = "";
        document.getElementById("show").style.display = "none";
      }

      function hideScoreDetails() {
        document.getElementById("scoredetails").style.display = "none";
        document.getElementById("updatescore").style.display = "none";
        document.getElementById("hide").style.display = "none";
        document.getElementById("show").style.display = "";
      }

      function updateScore() {
        document.getElementById("scoredetails").style.display = "none";
        document.getElementById("updatescore").style.display = "";
        document.getElementById("show").style.display = "none";
      }
      
      $(document).ready(function(){
        $('body').on('click', '.show-score-overtime', function(e){
            e.preventDefault();
            var tabContainer = $(this).parents('.risk-session');
            $('.score-overtime-container', tabContainer).show();
            $('.hide-score-overtime', tabContainer).show();
            $('.show-score-overtime', tabContainer).hide();
            return false;
        })

        $('body').on('click', '.hide-score-overtime', function(e){
            e.preventDefault();
            var tabContainer = $(this).parents('.risk-session');
            $('.score-overtime-container', tabContainer).hide();
            $('.hide-score-overtime', tabContainer).hide();
            $('.show-score-overtime', tabContainer).show();
            return false;
        })
      })
  </script>
</head>

<body>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/divshot-util.css">
  <link rel="stylesheet" href="../css/divshot-canvas.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="../css/theme.css">

  <?php
      view_top_menu("RiskManagement");

  ?>
  <div class="tabs new-tabs">
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3"> </div>
        <div class="span9">
          <div class="tab-append">
            <div class="tab selected form-tab tab-show" id="tab"><div><span><a href="plan_mitigations.php">Risk list</a></span></div>
            </div>
            <div class="tab selected form-tab tab-show" id="tab"><div><span><strong>ID: <?php echo $id.'</strong> '.$escaper->escapeHtml(try_decrypt($subject)); ?></span></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
  <div class="container-fluid">
    <div class="row-fluid">
      <div class="span3">
        <?php view_risk_management_menu("PlanYourMitigations"); ?>
      </div>
      <div class="span9">
        <div id="show-alert"></div>
        <div class="row-fluid" id="tab-content-container">
            <div class='tab-data' id="tab-container">
                <?php
                    $action = "editmitigation";
                    include(realpath(__DIR__ . '/partials/viewhtml.php'));
                ?>
            </div>
        </div>
      </div>
    </div>
  </body>
  <script type="text/javascript">
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
            $("#active-textfield").click(function(){
                
                $("#cancel_disable").removeAttr('disabled');
            });

            $("#active-button").click(function(){
                $("#cancel_disable").removeAttr('disabled');
            });
              $("select").change(function changeOption(){
                   $("#cancel_disable").removeAttr('disabled');
             });

      $("#tabs" ).tabs({
          activate:function(event,ui){
              if(ui.newPanel.selector== "#tabs1"){
                  $("#tab_details").addClass("tabList");
                  $("#tab_mitigation").removeClass("tabList");
                  $("#tab_review").removeClass("tabList");
              } else if(ui.newPanel.selector== "#tabs2"){
                  $("#tab_mitigation").addClass("tabList");
                  $("#tab_review").removeClass("tabList");
                  $("#tab_details").removeClass("tabList");
              }else{
                  $("#tab_review").addClass("tabList");
                  $("#tab_mitigation").removeClass("tabList");
                  $("#tab_details").removeClass("tabList");
              }

          }
      });
      $("#tabs").tabs({ active: 1});

      $('.collapsible--toggle span').click(function(event) {
        event.preventDefault();
        $(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
        $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
      });

      $('.add-comments').click(function(event) {
        event.preventDefault();
        $(this).parents('.collapsible--toggle').next('.collapsible').slideDown('400');
        $(this).toggleClass('rotate');
        $('#comment').fadeToggle('100');
        $(this).parent().find('span i').removeClass('fa-caret-right');
        $(this).parent().find('span i').addClass('fa-caret-down');
      });

        $('.collapsible').hide();

      $(".add-comment-menu").click(function(event){
        event.preventDefault();
        $commentsContainer = $("#comment").parents('.well');
        $commentsContainer.find(".collapsible--toggle").next('.collapsible').slideDown('400');
        $commentsContainer.find(".add-comments").addClass('rotate');
        $('#comment').show();
        $commentsContainer.find(".add-comments").parent().find('span i').removeClass('fa-caret-right');
        $commentsContainer.find(".add-comments").parent().find('span i').addClass('fa-caret-down');
        $("#comment-text").focus();
      })



  });
  </script>
  <script>
  /*
  * Function to add the css class for textarea title and make it popup.
  * Example usage:
  * focus_add_css_class("#foo", "#bar");
  */
  function focus_add_css_class(id_of_text_head, text_area_id){
      look_for = "textarea" + text_area_id;
      console.log(look_for);
      if( !$(look_for).length ){
          text_area_id = text_area_id.replace('#','');
          look_for = "textarea[name=" + text_area_id;
      }
      $(look_for).focusin(function() {
          $(id_of_text_head).addClass("affected-assets-title");
          $('.ui-autocomplete').addClass("popup-ui-complete")
      });
      $(look_for).focusout(function() {
          $(id_of_text_head).removeClass("affected-assets-title");
          $('.ui-autocomplete').removeClass("popup-ui-complete")
      });
  }
  $(document).ready(function() {
      focus_add_css_class("#SecurityRequirementsTitle", "#security_requirements");
      focus_add_css_class("#CurrentSolutionTitle", "#current_solution");
      focus_add_css_class("#SecurityRecommendationsTitle", "#security_recommendations");
      $( ".datepicker" ).datepicker();
  });
</script>
  </html>
