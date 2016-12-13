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

// Load CSRF Magic
require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

// Include the language file
require_once(language_file());

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
  header("Location: ../index.php");
  exit(0);
}

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
  set_alert(true, "bad", "The subject of a risk cannot be empty.");
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
  $team = (int)$_POST['team'];
  $technology = (int)$_POST['technology'];
  $owner = (int)$_POST['owner'];
  $manager = (int)$_POST['manager'];
  $assessment = $_POST['assessment'];
  $notes = $_POST['notes'];
  $assets = $_POST['assets'];

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

  // Submit risk and get back the id
  $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes);

  // Submit risk scoring
  submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);

  // Tag assets to risk
  tag_assets_to_risk($last_insert_id, $assets);

  // If a file was submitted
  if (!empty($_FILES))
  {
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
        upload_file($last_insert_id, $file, 1);
    }
  }

  // If the notification extra is enabled
  if (notification_extra())
  {
    // Include the team separation extra
    require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

    // Send the notification
    notify_new_risk($last_insert_id, $subject);
  }

  // There is an alert message
  $risk_id = $last_insert_id + 1000;

  // Display an alert
  set_alert(true, "good", "Risk ID " . $risk_id . " submitted successfully!");
}
?>

<!doctype html>
<html>

<head>
  <script src="../js/jquery.min.js"></script>
  <script src="../js/jquery-ui.min.js"></script>
  <script src="../js/bootstrap.min.js"></script>
  <script src="../js/cve_lookup.js"></script>
  <script src="../js/common.js"></script>
  <title>SimpleRisk: Enterprise Risk Management Simplified</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/bootstrap-responsive.css">
  <link rel="stylesheet" href="../css/jquery-ui.min.css">
  <script type="text/javascript">
  function popupcvss()
  {
    var cve_id = document.getElementById('reference_id').value;
    var pattern = /cve\-\d{4}-\d{4}/i;

    // If the field is a CVE ID
    if (cve_id.match(pattern))
    {
      my_window = window.open('cvss_rating.php?cve_id='+cve_id,'popupwindow','width=850,height=680,menu=0,status=0');
    }
    else my_window = window.open('cvss_rating.php','popupwindow','width=850,height=680,menu=0,status=0');
  }

  function popupdread()
  {
    my_window = window.open('dread_rating.php','popupwindow','width=660,height=500,menu=0,status=0');
  }

  function popupowasp()
  {
    my_window = window.open('owasp_rating.php','popupwindow','width=665,height=570,menu=0,status=0');
  }

  function closepopup()
  {
    if(false == my_window.closed)
    {
      my_window.close ();
    }
    else
    {
      alert('Window already closed!');
    }
  }

  function handleSelection(choice) {
    if (choice=="1") {
      document.getElementById("classic").style.display = "";
      document.getElementById("cvss").style.display = "none";
      document.getElementById("dread").style.display = "none";
      document.getElementById("owasp").style.display = "none";
      document.getElementById("custom").style.display = "none";
    }
    if (choice=="2") {
      document.getElementById("classic").style.display = "none";
      document.getElementById("cvss").style.display = "";
      document.getElementById("dread").style.display = "none";
      document.getElementById("owasp").style.display = "none";
      document.getElementById("custom").style.display = "none";
    }
    if (choice=="3") {
      document.getElementById("classic").style.display = "none";
      document.getElementById("cvss").style.display = "none";
      document.getElementById("dread").style.display = "";
      document.getElementById("owasp").style.display = "none";
      document.getElementById("custom").style.display = "none";
    }
    if (choice=="4") {
      document.getElementById("classic").style.display = "none";
      document.getElementById("cvss").style.display = "none";
      document.getElementById("dread").style.display = "none";
      document.getElementById("owasp").style.display = "";
      document.getElementById("custom").style.display = "none";
    }
    if (choice=="5") {
      document.getElementById("classic").style.display = "none";
      document.getElementById("cvss").style.display = "none";
      document.getElementById("dread").style.display = "none";
      document.getElementById("owasp").style.display = "none";
      document.getElementById("custom").style.display = "";
    }
  }
  </script>
  <?php display_asset_autocomplete_script(get_entered_assets()); ?>
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

  // Get any alert messages
  get_alert();
  ?>
  <div id="risk_hid_id" style="display: none"  > <?php if (isset($risk_id)) echo $escaper->escapeHtml($risk_id);?></div>
  <div class="tabs new-tabs">
    <div class="container-fluid">

      <div class="row-fluid">

        <div class="span3"> </div>
        <div class="span9">

          <div class="tab add" id='add-tab'>
            <span>+</span>
          </div>
          <div class="tab-append">
            <div class="tab selected form-tab tab-show new" id="tab"><div><span>New Risk (1)</span></div>
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

      <div id="show-alert"></div>

      <div class="row-fluid" id="tab-content-container">
        <div class='tab-data' id="tab-container">
          <div class="row-fluid">

            <form name="submit_risk" method="post" action="" enctype="multipart/form-data" id="risk-submit-form">

              <div class="row-fluid padded-bottom subject-field">
                <div class="span2 text-right"><?php echo $escaper->escapeHtml($lang['Subject']); ?>:</div>
                <div class="span8"><input maxlength="90" name="subject" id="subject" class="form-control" type="text"></div>
              </div>

              <div class="row-fluid">
                <!-- first coulmn -->
                <div class="span5">
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Category']); ?>:</div>
                    <div class="span7"><?php create_dropdown("category"); ?></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['SiteLocation']); ?>:</div>
                    <div class="span7"><?php create_dropdown("location"); ?></div>
                  </div>
                  <div class="row-fluid">
                    <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['ExternalReferenceId']); ?>:</div>
                    <div class="span7"><input maxlength="20" size="20" name="reference_id" id="reference_id" class="form-control" type="text" onkeyup="javascript: check_cve_id('reference_id');"></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['ControlRegulation']); ?>:</div>
                    <div class="span7"><?php create_dropdown("regulation"); ?></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['ControlNumber']); ?>:</div>
                    <div class="span7"><input maxlength="20" name="control_number" id="control_number" class="form-control" type="text"></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right" id="AffectedAssetsTitle"><?php echo $escaper->escapeHtml($lang['AffectedAssets']); ?>:</div>
                    <div class="span7"><div class="ui-widget"><textarea type="text" id="assets" name="assets" class="assets" class="form-control" tabindex="1"></textarea></div></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Technology']); ?>:</div>
                    <div class="span7"><?php create_dropdown("technology"); ?></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Team']); ?>:</div>
                    <div class="span7"><?php create_dropdown("team"); ?></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Owner']); ?>:</div>
                    <div class="span7"><?php create_dropdown("user", NULL, "owner"); ?></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['OwnersManager']); ?>:</div>
                    <div class="span7"><?php create_dropdown("user", NULL, "manager"); ?></div>
                  </div>
                </div>
                <!-- first coulmn end -->
                <!-- second coulmn -->
                <div class="span5">

                  <div class="row-fluid">
                    <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['RiskSource']); ?>:</div>
                    <div class="span7"><?php create_dropdown("source"); ?></div>
                  </div>

                  <div class="row-fluid">
                    <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['RiskScoringMethod']); ?>:</div>
                    <div class="span7">
                      <select class="form-control" name="scoring_method" id="select" onChange="handleSelection(value)">
                        <option selected value="1">Classic</option>
                        <option value="2">CVSS</option>
                        <option value="3">DREAD</option>
                        <option value="4">OWASP</option>
                        <option value="5">Custom</option>
                      </select>
                    </div>
                  </div>
                  <div id="classic">
                    <div class="row-fluid">
                      <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['CurrentLikelihood']); ?>:</div>
                      <div class="span7"><?php create_dropdown("likelihood"); ?></div>
                    </div>
                    <div class="row-fluid">
                      <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['CurrentImpact']); ?>:</div>
                      <div class="span7"><?php create_dropdown("impact"); ?></div>
                    </div>
                  </div>
                  <div id="cvss" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="cvssSubmit" id="cvssSubmit" value="Score Using CVSS" onclick="javascript: popupcvss();" /></p></td>
                      </tr>
                    </table>
                    <input type="hidden" name="AccessVector" id="AccessVector" value="N" />
                    <input type="hidden" name="AccessComplexity" id="AccessComplexity" value="L" />
                    <input type="hidden" name="Authentication" id="Authentication" value="N" />
                    <input type="hidden" name="ConfImpact" id="ConfImpact" value="C" />
                    <input type="hidden" name="IntegImpact" id="IntegImpact" value="C" />
                    <input type="hidden" name="AvailImpact" id="AvailImpact" value="C" />
                    <input type="hidden" name="Exploitability" id="Exploitability" value="ND" />
                    <input type="hidden" name="RemediationLevel" id="RemediationLevel" value="ND" />
                    <input type="hidden" name="ReportConfidence" id="ReportConfidence" value="ND" />
                    <input type="hidden" name="CollateralDamagePotential" id="CollateralDamagePotential" value="ND" />
                    <input type="hidden" name="TargetDistribution" id="TargetDistribution" value="ND" />
                    <input type="hidden" name="ConfidentialityRequirement" id="ConfidentialityRequirement" value="ND" />
                    <input type="hidden" name="IntegrityRequirement" id="IntegrityRequirement" value="ND" />
                    <input type="hidden" name="AvailabilityRequirement" id="AvailabilityRequirement" value="ND" />
                  </div>
                  <div id="dread" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="dreadSubmit" id="dreadSubmit" value="Score Using DREAD" onclick="javascript: popupdread();" /></p></td>
                      </tr>
                    </table>
                    <input type="hidden" name="DREADDamage" id="DREADDamage" value="10" />
                    <input type="hidden" name="DREADReproducibility" id="DREADReproducibility" value="10" />
                    <input type="hidden" name="DREADExploitability" id="DREADExploitability" value="10" />
                    <input type="hidden" name="DREADAffectedUsers" id="DREADAffectedUsers" value="10" />
                    <input type="hidden" name="DREADDiscoverability" id="DREADDiscoverability" value="10" />
                  </div>
                  <div id="owasp" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px">&nbsp;</td>
                        <td><p><input type="button" name="owaspSubmit" id="owaspSubmit" value="Score Using OWASP" onclick="javascript: popupowasp();" /></p></td>
                      </tr>
                    </table>
                    <input type="hidden" name="OWASPSkillLevel" id="OWASPSkillLevel" value="10" />
                    <input type="hidden" name="OWASPMotive" id="OWASPMotive" value="10" />
                    <input type="hidden" name="OWASPOpportunity" id="OWASPOpportunity" value="10" />
                    <input type="hidden" name="OWASPSize" id="OWASPSize" value="10" />
                    <input type="hidden" name="OWASPEaseOfDiscovery" id="OWASPEaseOfDiscovery" value="10" />
                    <input type="hidden" name="OWASPEaseOfExploit" id="OWASPEaseOfExploit" value="10" />
                    <input type="hidden" name="OWASPAwareness" id="OWASPAwareness" value="10" />
                    <input type="hidden" name="OWASPIntrusionDetection" id="OWASPIntrusionDetection" value="10" />
                    <input type="hidden" name="OWASPLossOfConfidentiality" id="OWASPLossOfConfidentiality" value="10" />
                    <input type="hidden" name="OWASPLossOfIntegrity" id="OWASPLossOfIntegrity" value="10" />
                    <input type="hidden" name="OWASPLossOfAvailability" id="OWASPLossOfAvailability" value="10" />
                    <input type="hidden" name="OWASPLossOfAccountability" id="OWASPLossOfAccountability" value="10" />
                    <input type="hidden" name="OWASPFinancialDamage" id="OWASPFinancialDamage" value="10" />
                    <input type="hidden" name="OWASPReputationDamage" id="OWASPReputationDamage" value="10" />
                    <input type="hidden" name="OWASPNonCompliance" id="OWASPNonCompliance" value="10" />
                    <input type="hidden" name="OWASPPrivacyViolation" id="OWASPPrivacyViolation" value="10" />
                  </div>
                  <div id="custom" style="display: none;">
                    <table width="100%">
                      <tr>
                        <td width="197px"><?php echo $escaper->escapeHtml($lang['CustomValue']); ?>:</td>
                        <td><input type="text" name="Custom" id="Custom" value="" /> (Must be a numeric value between 0 and 10)</td>
                      </tr>
                    </table>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right" id="RiskAssessmentTitle"><?php echo $escaper->escapeHtml($lang['RiskAssessment']); ?>:</div>
                    <div class="span7"><textarea name="assessment" cols="50" rows="3" id="assessment" class="form-control" tabindex="1"></textarea></div>
                  </div>
                  <div class="row-fluid">
                    <div class="span5 text-right" id="NotesTitle"><?php echo $escaper->escapeHtml($lang['AdditionalNotes']); ?>:</div>
                    <div class="span7"><textarea name="notes" cols="50" rows="3" id="notes" class="form-control" tabindex="1"></textarea></div>
                  </div>
                  <div class="row-fluid">
                    <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['SupportingDocumentation']); ?>:</div>
                    <div class="span7">

                      <div class="file-uploader">
                        <label for="file-upload" class="btn">Choose File</label><span class="file-count-html"> <span class="file-count">0</span> File Added</span>
                        <ul class="file-list">
                            
                        </ul>
                        <input type="file" id="file-upload" name="file[]" class="hidden-file-upload hide active" />
                      </div>

                    </div>
                  </div>
                </div>
                <!-- second coulmn end -->
              </div>

              <div class="row-fluid">
                <div class="span10">
                  <div class="actions risk-form-actions">
                    <span>Complete the form above to document a risk for consideration in Risk Management Process</span>
                    <button type="button" name="submit" class="btn btn-primary pull-right save-risk-form"><?php echo $escaper->escapeHtml($lang['SubmitRisk']); ?></button>
                    <input class="btn pull-right" value="<?php echo $escaper->escapeHtml($lang['ClearForm']); ?>" type="reset">
                  </div>
                </div>
              </div>

            </form>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- sample form to add as a new form -->
<div class="row-fluid" id="tab-append-div" style="display:none;">
    <div class="row-fluid">


      <form name="submit_risk" method="post" action="" enctype="multipart/form-data" id="risk-submit-form">

        <div class="row-fluid padded-bottom subject-field">
          <div class="span2 text-right"><?php echo $escaper->escapeHtml($lang['Subject']); ?>:</div>
          <div class="span8"><input maxlength="90" name="subject" id="subject" class="form-control" type="text"></div>
        </div>

        <div class="row-fluid">
          <!-- first coulmn -->
          <div class="span5">

            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Category']); ?>:</div>
              <div class="span7"><?php create_dropdown("category"); ?></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['SiteLocation']); ?>:</div>
              <div class="span7"><?php create_dropdown("location"); ?></div>
            </div>
            <div class="row-fluid">
              <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['ExternalReferenceId']); ?>:</div>
              <div class="span7"><input maxlength="20" size="20" name="reference_id" id="reference_id" class="form-control" type="text" onkeyup="javascript: check_cve_id('reference_id');"></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['ControlRegulation']); ?>:</div>
              <div class="span7"><?php create_dropdown("regulation"); ?></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['ControlNumber']); ?>:</div>
              <div class="span7"><input maxlength="20" name="control_number" id="control_number" class="form-control" type="text"></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['AffectedAssets']); ?>:</div>
              <div class="span7"><div class="ui-widget"><textarea type="text" id="assets" name="assets" class="assets" class="form-control" tabindex="1"></textarea></div></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Technology']); ?>:</div>
              <div class="span7"><?php create_dropdown("technology"); ?></div>
            </div>

            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Team']); ?>:</div>
              <div class="span7"><?php create_dropdown("team"); ?></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['Owner']); ?>:</div>
              <div class="span7"><?php create_dropdown("user", NULL, "owner"); ?></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['OwnersManager']); ?>:</div>
              <div class="span7"><?php create_dropdown("user", NULL, "manager"); ?></div>
            </div>
          </div>
          <!-- first coulmn end -->
          <!-- second coulmn -->
          <div class="span5">

            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['RiskSource']); ?>:</div>
              <div class="span7"><?php create_dropdown("source"); ?></div>
            </div>

            <div class="row-fluid">
              <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['RiskScoringMethod']); ?>:</div>
              <div class="span7">
                <select class="form-control" name="scoring_method" id="select" onChange="handleSelection(value)">
                  <option selected value="1">Classic</option>
                  <option value="2">CVSS</option>
                  <option value="3">DREAD</option>
                  <option value="4">OWASP</option>
                  <option value="5">Custom</option>
                </select>
              </div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['CurrentLikelihood']); ?>:</div>
              <div class="span7"><?php create_dropdown("likelihood"); ?></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['CurrentImpact']); ?>:</div>
              <div class="span7"><?php create_dropdown("impact"); ?></div>
            </div>
            <div id="cvss" style="display: none;">
              <table width="100%">
                <tr>
                  <td width="197px">&nbsp;</td>
                  <td><p><input type="button" name="cvssSubmit" id="cvssSubmit" value="Score Using CVSS" onclick="javascript: popupcvss();" /></p></td>
                </tr>
              </table>
              <input type="hidden" name="AccessVector" id="AccessVector" value="N" />
              <input type="hidden" name="AccessComplexity" id="AccessComplexity" value="L" />
              <input type="hidden" name="Authentication" id="Authentication" value="N" />
              <input type="hidden" name="ConfImpact" id="ConfImpact" value="C" />
              <input type="hidden" name="IntegImpact" id="IntegImpact" value="C" />
              <input type="hidden" name="AvailImpact" id="AvailImpact" value="C" />
              <input type="hidden" name="Exploitability" id="Exploitability" value="ND" />
              <input type="hidden" name="RemediationLevel" id="RemediationLevel" value="ND" />
              <input type="hidden" name="ReportConfidence" id="ReportConfidence" value="ND" />
              <input type="hidden" name="CollateralDamagePotential" id="CollateralDamagePotential" value="ND" />
              <input type="hidden" name="TargetDistribution" id="TargetDistribution" value="ND" />
              <input type="hidden" name="ConfidentialityRequirement" id="ConfidentialityRequirement" value="ND" />
              <input type="hidden" name="IntegrityRequirement" id="IntegrityRequirement" value="ND" />
              <input type="hidden" name="AvailabilityRequirement" id="AvailabilityRequirement" value="ND" />
            </div>
            <div id="dread" style="display: none;">
              <table width="100%">
                <tr>
                  <td width="197px">&nbsp;</td>
                  <td><p><input type="button" name="dreadSubmit" id="dreadSubmit" value="Score Using DREAD" onclick="javascript: popupdread();" /></p></td>
                </tr>
              </table>
              <input type="hidden" name="DREADDamage" id="DREADDamage" value="10" />
              <input type="hidden" name="DREADReproducibility" id="DREADReproducibility" value="10" />
              <input type="hidden" name="DREADExploitability" id="DREADExploitability" value="10" />
              <input type="hidden" name="DREADAffectedUsers" id="DREADAffectedUsers" value="10" />
              <input type="hidden" name="DREADDiscoverability" id="DREADDiscoverability" value="10" />
            </div>
            <div id="owasp" style="display: none;">
              <table width="100%">
                <tr>
                  <td width="197px">&nbsp;</td>
                  <td><p><input type="button" name="owaspSubmit" id="owaspSubmit" value="Score Using OWASP" onclick="javascript: popupowasp();" /></p></td>
                </tr>
              </table>
              <input type="hidden" name="OWASPSkillLevel" id="OWASPSkillLevel" value="10" />
              <input type="hidden" name="OWASPMotive" id="OWASPMotive" value="10" />
              <input type="hidden" name="OWASPOpportunity" id="OWASPOpportunity" value="10" />
              <input type="hidden" name="OWASPSize" id="OWASPSize" value="10" />
              <input type="hidden" name="OWASPEaseOfDiscovery" id="OWASPEaseOfDiscovery" value="10" />
              <input type="hidden" name="OWASPEaseOfExploit" id="OWASPEaseOfExploit" value="10" />
              <input type="hidden" name="OWASPAwareness" id="OWASPAwareness" value="10" />
              <input type="hidden" name="OWASPIntrusionDetection" id="OWASPIntrusionDetection" value="10" />
              <input type="hidden" name="OWASPLossOfConfidentiality" id="OWASPLossOfConfidentiality" value="10" />
              <input type="hidden" name="OWASPLossOfIntegrity" id="OWASPLossOfIntegrity" value="10" />
              <input type="hidden" name="OWASPLossOfAvailability" id="OWASPLossOfAvailability" value="10" />
              <input type="hidden" name="OWASPLossOfAccountability" id="OWASPLossOfAccountability" value="10" />
              <input type="hidden" name="OWASPFinancialDamage" id="OWASPFinancialDamage" value="10" />
              <input type="hidden" name="OWASPReputationDamage" id="OWASPReputationDamage" value="10" />
              <input type="hidden" name="OWASPNonCompliance" id="OWASPNonCompliance" value="10" />
              <input type="hidden" name="OWASPPrivacyViolation" id="OWASPPrivacyViolation" value="10" />
            </div>
            <div id="custom" style="display: none;">
              <table width="100%">
                <tr>
                  <td width="197px"><?php echo $escaper->escapeHtml($lang['CustomValue']); ?>:</td>
                  <td><input type="text" name="Custom" id="Custom" value="" /> (Must be a numeric value between 0 and 10)</td>
                </tr>
              </table>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['RiskAssessment']); ?>:</div>
              <div class="span7"><textarea name="assessment" cols="50" rows="3" id="assessment" class="form-control" tabindex="1"></textarea></div>
            </div>
            <div class="row-fluid">
              <div class="span5 text-right"><?php echo $escaper->escapeHtml($lang['AdditionalNotes']); ?>:</div>
              <div class="span7"><textarea name="notes" cols="50" rows="3" id="notes" class="form-control" tabindex="1"></textarea></div>
            </div>
            <div class="row-fluid">
              <div class="wrap-text span5 text-right"><?php echo $escaper->escapeHtml($lang['SupportingDocumentation']); ?>:</div>
              <div class="span7">

                  <div class="file-uploader">
                    <label for="file-upload" class="btn">Choose File</label> <span class="file-count-html"> <span class="file-count">0</span> File Added</span>
                    <ul class="file-list">

                    </ul>
                    <input type="file" name="file[]" id="file-upload" class="hidden-file-upload hide active" />
                  </div>

              </div>
            </div>
          </div>
          <!-- second coulmn end -->
        </div>

        <div class="row-fluid">
          <div class="span10">
            <div class="actions risk-form-actions">
              <span>Complete the form above to document a risk for consideration in Risk Management Process</span>
              <button type="button" name="submit" class="btn btn-primary pull-right save-risk-form"><?php echo $escaper->escapeHtml($lang['SubmitRisk']); ?></button>
              <input class="btn pull-right" value="<?php echo $escaper->escapeHtml($lang['ClearForm']); ?>" type="reset">
            </div>
          </div>
        </div>

      </form>


    </div>
  </div>
</div>
<script>
$(document).ready(function() {

    window.onbeforeunload = function() {
        if ($('#subject:enabled').val() != ''){
            return "Are you sure you want to procced without saving the risk?";
        }
    }

   $('#tab-content-container').delegate('input[type="reset"]', 'click', function (){
        var getForm = $(this).parent().parent().parent().parent();
        $('.hidden-file-upload',getForm).prev('label').text('');
        $(getForm).find('.file-count-html').html('<span class="file-count">0</span> File Added');
        $(getForm).find('.file-list').html('');
   })

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
          "<div class='tab new tab-show form-tab selected' id='tab"+num_tabs+"'><div><span>New Risk ("+num_tabs+")</span></div>"
          +"<button class='close tab-close' aria-label='Close' data-id='"+num_tabs+"'>"
          +"<i class='fa fa-close'></i>"
          +"</button>"
          +"</div>"
        );
        $('.tab-data').css({'display':'none'});
        $("#tab-content-container").append(
          "<div class='tab-data' id='tab-container"+num_tabs+"'>"+form+"</div>"
        );

        $("#tab-container"+num_tabs)
        .find('.file-uploader label').attr('for', 'file_upload'+num_tabs);

            $("#tab-container"+num_tabs)
              .find('.hidden-file-upload')
              .attr('id', 'file_upload'+num_tabs)
              .prev('label').attr('for', 'file_upload'+num_tabs);

        $( "#tab-container"+num_tabs +" .assets" )
          .bind( "keydown", function( event ) {
            if ( event.keyCode === $.ui.keyCode.TAB && $( this ).autocomplete( "instance" ).menu.active ) {
              event.preventDefault();
            }
          })
          .autocomplete({
                minLength: 0,
                source: function( request, response ) {
                // delegate back to autocomplete, but extract the last term
                response( $.ui.autocomplete.filter(
                availableAssets, extractLast( request.term ) ) );
              },
              focus: function() {
                // prevent value inserted on focus
                return false;
              },
              select: function( event, ui ) {
                var terms = split( this.value );
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push( ui.item.value );
                // add placeholder to get the comma-and-space at the end
                terms.push( "" );
                this.value = terms.join( ", " );
                return false;
              }
          });
  
  });


  $('.container-fluid').delegate('.tab-show', 'click', function(){
    $('#show-alert').html('');
    $('.form-tab').removeClass('selected');
    $(this).addClass('selected');
    var index = $('.tab-close', this).attr('data-id');
    $('.tab-data').hide();
    $('#tab-container'+index+'').show();
  });

  $('.container-fluid').delegate('.tab-close', 'click', function(){
    var index = $(this).attr('data-id');
    if ($('.tab-close').length > 1)
    {
      if (confirm("Are you sure you want to delete this?")){
        $('#tab-container'+index+'').remove();
        $(this).parent().remove();
        $('.tab-show').first().addClass('selected');
        $('.tab-data').last().show();
      }
      return false;
    }
  });
  
  function submitRisk($this){
    var getForm = $this.parent().parent().parent().parent();
    var div = getForm.parent().parent();
    var index = parseInt((div).attr('id').replace(/[A-Za-z$-]/g, ""));
    var form = new FormData($(getForm)[0]);
    $.each($("input[type=file]"), function(i, obj) {
        $.each(obj.files,function(j, file){
            form.append('file['+j+']', file);
        })
    });
    console.log(form)
    $('#show-alert').html('');
    $.ajax({
        type: "POST",
        url: "index.php",
        data: form,
        async: true,
        cache: false,
        contentType: false,
        processData: false,
        success: function(data){
            var message = $(data).filter('#alert');
            var risk_id = $(data).filter('#risk_hid_id');

            $('#show-alert').append(message);
            if (message[0].innerText != 'The subject of a risk cannot be empty.'){
                if (isNaN(index)){
                    var subject = $('input[name="subject"]', getForm).val();
                    var subject = subject.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    $('#tab span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+subject);
                    //$('#tab span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+$('input[name="subject"]', getForm).val());
                } else {
                    var subject = $('input[name="subject"]', getForm).val();
                    var subject = subject.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    $('#tab'+index+' span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+subject);
                    //$('#tab'+index+' span:eq(0)').html('<b>ID:'+risk_id[0].innerText+' </b>'+$('input[name="subject"]', getForm).val());
                }
                $('input, select, textarea', getForm).prop('disabled', true);
                $this.prop('disabled', true);
            } else {
                $this.removeAttr('disabled');
            }
        }
    })
    .fail(function(xhr, textStatus){
        var obj = $('<div/>').html(xhr.responseText);
        var token = obj.find('input[name="__csrf_magic"]').val();
        if(token){
            $('input[name="__csrf_magic"]').val(token);
            submitRisk($this);
        }
    })
    ;
      
  }

  $('#tab-content-container').delegate('.save-risk-form', 'click', function (){
    submitRisk($(this));
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
    });
    $(look_for).focusout(function() {
      $(id_of_text_head).removeClass("affected-assets-title");
    });
}
$(document).ready(function() {
    focus_add_css_class("#AffectedAssetsTitle", "#assets");
    focus_add_css_class("#RiskAssessmentTitle", "#assessment");
    focus_add_css_class("#NotesTitle", "#notes");
});

</script>
</body>
</html>