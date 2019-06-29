<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/assets.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

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
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
  set_unauthenticated_redirect();
  header("Location: ../index.php");
  exit(0);
}

// Include the CSRF-magic library
// Make sure it's called after the session is properly setup
include_csrf_magic();

// Include the language file
require_once(language_file());

// Enforce that the user has access to risk management
enforce_permission_riskmanagement();

// Check if the user has access to submit risks
if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1)
{
  $submit_risks = false;

  // Display an alert
  set_alert(true, "bad", "You do not have permission to submit new risks.  Any risks that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
}
else $submit_risks = true;

// Check if the subject is null
if (isset($_POST['subject']) && $_POST['subject'] == "")
{
  $submit_risks = false;

  // Display an alert
  ob_end_clean();
  $data = array("error" => true, "message" => $escaper->escapeHtml("The subject of a risk cannot be empty."));
  header('Content-type:application/json;charset=utf-8');
  echo json_encode($data);
  return;  
}

// Check if a new risk was submitted and the user has permissions to submit new risks
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && $submit_risks)
{
  $status = "New";
  $subject = $_POST['subject'];
  $reference_id = $_POST['reference_id'];
  $regulation = (int)$_POST['regulation'];
  $control_number = $_POST['control_number'];
  $location = (int)$_POST['location'];
  $source = (int)$_POST['source'];
  $category = (int)$_POST['category'];
  $team = (empty($_POST['team'])) ? "" : implode(",", $_POST['team']);
  $technology = (empty($_POST['technology'])) ? "" : implode(",", $_POST['technology']);
  $owner = (int)$_POST['owner'];
  $manager = (int)$_POST['manager'];
  $assessment = $_POST['assessment'];
  $notes = $_POST['notes'];
  $assets_asset_groups = is_array($_POST['assets_asset_groups']) ? $_POST['assets_asset_groups'] : [];
  $additional_stakeholders = empty($_POST['additional_stakeholders']) ? "" : implode(",", $_POST['additional_stakeholders']);
  $risk_tags = empty($_POST['tags']) ? array() : explode(",", $_POST['tags']);

  // Risk scoring method
  // 1 = Classic
  // 2 = CVSS
  // 3 = DREAD
  // 4 = OWASP
  // 5 = Custom
  $scoring_method = (int)$_POST['scoring_method'];

  // Classic Risk Scoring Inputs
  $CLASSIClikelihood = (int)$_POST['likelihood'];
  $CLASSICimpact =(int) $_POST['impact'];

  // CVSS Risk Scoring Inputs
  $CVSSAccessVector = $_POST['AccessVector'];
  $CVSSAccessComplexity = $_POST['AccessComplexity'];
  $CVSSAuthentication = $_POST['Authentication'];
  $CVSSConfImpact = $_POST['ConfImpact'];
  $CVSSIntegImpact = $_POST['IntegImpact'];
  $CVSSAvailImpact = $_POST['AvailImpact'];
  $CVSSExploitability = $_POST['Exploitability'];
  $CVSSRemediationLevel = $_POST['RemediationLevel'];
  $CVSSReportConfidence = $_POST['ReportConfidence'];
  $CVSSCollateralDamagePotential = $_POST['CollateralDamagePotential'];
  $CVSSTargetDistribution = $_POST['TargetDistribution'];
  $CVSSConfidentialityRequirement = $_POST['ConfidentialityRequirement'];
  $CVSSIntegrityRequirement = $_POST['IntegrityRequirement'];
  $CVSSAvailabilityRequirement = $_POST['AvailabilityRequirement'];

  // DREAD Risk Scoring Inputs
  $DREADDamage = (int)$_POST['DREADDamage'];
  $DREADReproducibility = (int)$_POST['DREADReproducibility'];
  $DREADExploitability = (int)$_POST['DREADExploitability'];
  $DREADAffectedUsers = (int)$_POST['DREADAffectedUsers'];
  $DREADDiscoverability = (int)$_POST['DREADDiscoverability'];

  // OWASP Risk Scoring Inputs
  $OWASPSkillLevel = (int)$_POST['OWASPSkillLevel'];
  $OWASPMotive = (int)$_POST['OWASPMotive'];
  $OWASPOpportunity = (int)$_POST['OWASPOpportunity'];
  $OWASPSize = (int)$_POST['OWASPSize'];
  $OWASPEaseOfDiscovery = (int)$_POST['OWASPEaseOfDiscovery'];
  $OWASPEaseOfExploit = (int)$_POST['OWASPEaseOfExploit'];
  $OWASPAwareness = (int)$_POST['OWASPAwareness'];
  $OWASPIntrusionDetection = (int)$_POST['OWASPIntrusionDetection'];
  $OWASPLossOfConfidentiality = (int)$_POST['OWASPLossOfConfidentiality'];
  $OWASPLossOfIntegrity = (int)$_POST['OWASPLossOfIntegrity'];
  $OWASPLossOfAvailability = (int)$_POST['OWASPLossOfAvailability'];
  $OWASPLossOfAccountability = (int)$_POST['OWASPLossOfAccountability'];
  $OWASPFinancialDamage = (int)$_POST['OWASPFinancialDamage'];
  $OWASPReputationDamage = (int)$_POST['OWASPReputationDamage'];
  $OWASPNonCompliance = (int)$_POST['OWASPNonCompliance'];
  $OWASPPrivacyViolation = (int)$_POST['OWASPPrivacyViolation'];

  // Custom Risk Scoring
  $custom = (float)$_POST['Custom'];
  
  // Contributing Risk Scoring
  $ContributingLikelihood = (int)$_POST["ContributingLikelihood"];
  $ContributingImpacts = $_POST["ContributingImpacts"];

  // Submit risk and get back the id
  $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes, 0, 0, false, $additional_stakeholders);

    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

        create_subject_order($_SESSION['encrypted_pass']);
    }

  // Submit risk scoring
  submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);

  // Process the data from the Affected Assets widget
  if (!empty($assets_asset_groups))
    process_selected_assets_asset_groups_of_type($last_insert_id, $assets_asset_groups, 'risk');

  //Add tags
  updateTagsOfType($last_insert_id, 'risk', $risk_tags);

  $error = 1;
  // If a file was submitted
  if (!empty($_FILES))
  {
    for($i=0; $i<count($_FILES['file']['name']); $i++){
        if($_FILES['file']['error'][$i] || $i==0){
           continue; 
        } 
        $file = array(
            'name'      => $_FILES['file']['name'][$i],
            'type'      => $_FILES['file']['type'][$i],
            'tmp_name'  => $_FILES['file']['tmp_name'][$i],
            'size'      => $_FILES['file']['size'][$i],
            'error'     => $_FILES['file']['error'][$i],
        );
        // Upload any file that is submitted
        $error = upload_file($last_insert_id, $file, 1);
        if($error != 1){
            /**
            * If error, stop uploading files;
            */
            break;
        }
        
    }
  }
  // Otherwise, success
  else $error = 1;
  
  // If there was an error in submitting.
  if($error != 1)
  {
      // Delete risk
      delete_risk($last_insert_id);

      // Display an alert
      ob_end_clean();
      $data = array("error" => true, "message" => $escaper->escapeHtml($error));
      header('Content-type:application/json;charset=utf-8');
      echo json_encode($data);
      return;
  }
  else 
  {
      // If the notification extra is enabled
      if (notification_extra())
      {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

        // Send the notification
        notify_new_risk($last_insert_id, $subject);
      }

      // There is an alert message
      $risk_id = (int)$last_insert_id + 1000;

      echo "<script> var global_risk_id = " . $risk_id . ";</script>";
      
      // Display an alert   
      $RiskSubmitSuccess = _lang("RiskSubmitSuccess", ["subject" => $escaper->escapeHtml($subject)]);
      ob_end_clean();
      $data = array("risk_id" => $risk_id, "error" => false, "message" => $escaper->escapeHtml($RiskSubmitSuccess));
      header('Content-type:application/json;charset=utf-8');
      echo json_encode($data);
      return;
  }

}


?>

<!doctype html>
<html>

    <head>
        <script>
            var simplerisk = {
                risk: "<?php echo $lang['Risk']; ?>",
	            newrisk: "<?php echo $lang['NewRisk']; ?>"
            }
            
        </script>
        <!--script src="../js/jquery.min.js"></script>
        <script src="../js/jquery-ui.min.js"></script -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js" ></script>

<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <script src="../js/jquery.dataTables.js"></script>
        <script src="../js/cve_lookup.js?<?php echo time() ?>"></script>
        <script src="../js/basescript.js"></script>
        <script src="../js/common.js?<?php echo time() ?>"></script>
        <script src="../js/pages/risk.js?<?php echo time() ?>"></script>
        <script src="../js/highcharts/code/highcharts.js"></script>
        <script src="../js/bootstrap-multiselect.js"></script>
        <script src="../js/jquery.blockUI.min.js"></script>

        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css">
<!--        <link rel="stylesheet" href="../css/jquery-ui.min.css">-->

        <link rel="stylesheet" href="../css/jquery.dataTables.css">
        <link rel="stylesheet" href="../css/divshot-util.css">
        <link rel="stylesheet" href="../css/divshot-canvas.css">
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="../css/bootstrap-multiselect.css">

        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">

        <link rel="stylesheet" href="../css/selectize.bootstrap3.css">
        <script src="../js/selectize.min.js"></script>

        <?php
            setup_alert_requirements("..");
        ?>
    </head>

    <body>

        <?php
            view_top_menu("RiskManagement");

            // Get any alert messages
            get_alert();
        ?>
        
        <div class="tabs new-tabs">
        <div class="container-fluid">

          <div class="row-fluid">

            <div class="span3"> </div>
            <div class="span9">

              <div class="tab add" id='add-tab'>
                <span>+</span>
              </div>
              <div class="tab-append">
                <div class="tab selected form-tab tab-show new" id="tab"><div><span><?php echo $escaper->escapeHtml($lang['NewRisk']); ?> (1)</span></div>
                  <button class="close tab-close" aria-label="Close" data-id=""><i class="fa fa-close"></i></button>
                </div>
              </div>
            </div>

          </div>

        </div>
        </div>
        <div class="container-fluid">
          <div class="row-fluid">
            <div class="span3">
              <?php view_risk_management_menu("SubmitYourRisks"); ?>
            </div>
            <div class="span9">

              <div class="row-fluid" id="tab-content-container">
                <div class='tab-data' id="tab-container">

                    <?php
                        include(realpath(__DIR__ . '/partials/add.php'));
                    ?>
                  
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- sample form to add as a new form -->
        <div class="row-fluid" id="tab-append-div" style="display:none;">
            <?php
                include(realpath(__DIR__ . '/partials/add.php'));
            ?>
        </div>
        <input type="hidden" id="_delete_tab_alert" value="<?php echo $escaper->escapeHtml($lang['Are you sure you want to close the risk? All changes will be lost!']); ?>">
        <input type="hidden" id="enable_popup" value="<?php echo get_setting('enable_popup'); ?>">
        <script>
            $(document).ready(function() {
                
                setupAssetsAssetGroupsWidget($('#tab-container select.assets-asset-groups-select'));
                
                window.onbeforeunload = function() {
                    if ($('#subject:enabled').val() != ''){
                        return "Are you sure you want to procced without saving the risk?";
                    }
                }
                
                var length = $('.tab-close').length;
                if (length == 1){
                    $('.tab-show button').hide();
                }

                $("div#tabs").tabs();
            
                $("div#add-tab").click(function() {

                    $('.tab-show button').show();
                    var num_tabs = $("div.container-fluid div.new").length + 1;
                    var form = $('#tab-append-div').html();

                    $('.tab-show').removeClass('selected');
                    $("div.tab-append").prepend(
                        "<div class='tab new tab-show form-tab selected' id='tab"+num_tabs+"'><div><span><?php echo $escaper->escapeHtml($lang['NewRisk']); ?> ("+num_tabs+")</span></div>"
                        +"<button class='close tab-close' aria-label='Close' data-id='"+num_tabs+"'>"
                        +"<i class='fa fa-close'></i>"
                        +"</button>"
                        +"</div>"
                    );
                    $('.tab-data').css({'display':'none'});
                    $("#tab-content-container").append(
                        "<div class='tab-data' id='tab-container"+num_tabs+"'>"+form+"</div>"
                    );

                    setupAssetsAssetGroupsWidget($('#tab-container'+num_tabs+' select.assets-asset-groups-select'));
                    
                    focus_add_css_class("#RiskAssessmentTitle", "#assessment", $("#tab-container" + num_tabs));
                    focus_add_css_class("#NotesTitle", "#notes", $("#tab-container" + num_tabs));


                    $("#tab-container"+num_tabs)
                        .find('.file-uploader label').attr('for', 'file_upload'+num_tabs);

                    $("#tab-container"+num_tabs)
                        .find('.hidden-file-upload')
                        .attr('id', 'file_upload'+num_tabs)
                        .prev('label').attr('for', 'file_upload'+num_tabs);
                    
                        
                    // Add multiple selctets
                    $('.multiselect', "#tab-container"+num_tabs).multiselect({buttonWidth: '100%'});
                    
                    // Add DatePicker
                    if($('.datepicker', "#tab-container"+num_tabs).length){
                        $('.datepicker', "#tab-container"+num_tabs).datepicker();
                    }
                });

                focus_add_css_class("#RiskAssessmentTitle", "#assessment", $("#tab-container"));
                focus_add_css_class("#NotesTitle", "#notes", $("#tab-container"));
                
            });

        </script>
        <?php display_set_default_date_format_script(); ?>
    </body>
</html>
