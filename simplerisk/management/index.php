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
require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

// Include Laminas Escaper for HTML Output Encoding
$escaper = new Laminas\Escaper\Escaper('utf-8');

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
require_once(language_file());

// Check if the user has access to submit risks
if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1) {
    $submit_risks = false;

    // Display an alert
    set_alert(true, "bad", "You do not have permission to submit new risks.  Any risks that you attempt to submit will not be recorded.  Please contact an Administrator if you feel that you have reached this message in error.");
}
else $submit_risks = true;

// Check if the subject is null
if (get_param("POST", 'subject', false) !== false && !trim(get_param("POST", 'subject', "")))
{
  $submit_risks = false;
  // Display an alert
  ob_end_clean();
  set_alert(true, "bad", "The subject of a risk cannot be empty.");
  json_response(400, get_alert(true), NULL);
  exit;
}
    
// Check if a new risk was submitted and the user has permissions to submit new risks
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && $submit_risks) {

    $status = "New";
    $subject = get_param("POST", 'subject');
    $risk_catalog_mapping = get_param("POST", 'risk_catalog_mapping', []);
    $threat_catalog_mapping = get_param("POST", 'threat_catalog_mapping', []);
    $reference_id = get_param("POST", 'reference_id');
    $regulation = (int)get_param("POST", 'regulation');
    $control_number = get_param("POST", 'control_number');
    $location = implode(",", get_param("POST", "location", []));
    $source = (int)get_param("POST", 'source');
    $category = (int)get_param("POST", 'category');
    $team = get_param("POST", 'team', []);
    $technology = get_param("POST", 'technology', []);
    $owner = (int)get_param("POST", "owner");
    $manager = (int)get_param("POST", "manager");
    $assessment = get_param("POST", "assessment");
    $notes = get_param("POST", "notes");
    $assets_asset_groups = get_param("POST", "assets_asset_groups", []);
    $additional_stakeholders =  get_param("POST", "additional_stakeholders", []);
    $risk_tags = get_param("POST", "tags", []);

    if(customization_extra()) {
        $template_group_id = get_param("POST", "template_group_id", "");
    } else $template_group_id = "";

    foreach($risk_tags as $tag){
        if (strlen($tag) > 255) {
            global $lang;
            
            set_alert(true, "bad", $lang['MaxTagLengthWarning']);
            json_response(400, get_alert(true), NULL);
            exit;
        }
    }

    if (jira_extra()) {
        require_once(realpath(__DIR__ . '/../extras/jira/index.php'));
        $issue_key = isset($_POST['jira_issue_key'])?strtoupper(trim($_POST['jira_issue_key'])):"";
        if ($issue_key && !jira_validate_issue_key($issue_key)) {
            json_response(400, get_alert(true), NULL);
            exit;
        }
    }

    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    // 6 = Contributing Risk
    $scoring_method = (int)get_param("POST", "scoring_method");

    // Classic Risk Scoring Inputs
    $CLASSIClikelihood = (int)get_param("POST", "likelihood");
    $CLASSICimpact =(int) get_param("POST", "impact");

    // CVSS Risk Scoring Inputs
    $CVSSAccessVector = get_param("POST", "AccessVector");
    $CVSSAccessComplexity = get_param("POST", "AccessComplexity");
    $CVSSAuthentication = get_param("POST", "Authentication");
    $CVSSConfImpact = get_param("POST", "ConfImpact");
    $CVSSIntegImpact = get_param("POST", "IntegImpact");
    $CVSSAvailImpact = get_param("POST", "AvailImpact");
    $CVSSExploitability = get_param("POST", "Exploitability");
    $CVSSRemediationLevel = get_param("POST", "RemediationLevel");
    $CVSSReportConfidence = get_param("POST", "ReportConfidence");
    $CVSSCollateralDamagePotential = get_param("POST", "CollateralDamagePotential");
    $CVSSTargetDistribution = get_param("POST", "TargetDistribution");
    $CVSSConfidentialityRequirement = get_param("POST", "ConfidentialityRequirement");
    $CVSSIntegrityRequirement = get_param("POST", "IntegrityRequirement");
    $CVSSAvailabilityRequirement = get_param("POST", "AvailabilityRequirement");

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)get_param("POST", "DREADDamage");
    $DREADReproducibility = (int)get_param("POST", "DREADReproducibility");
    $DREADExploitability = (int)get_param("POST", "DREADExploitability");
    $DREADAffectedUsers = (int)get_param("POST", "DREADAffectedUsers");
    $DREADDiscoverability = (int)get_param("POST", "DREADDiscoverability");

    // OWASP Risk Scoring Inputs
    $OWASPSkillLevel = (int)get_param("POST", "OWASPSkillLevel");
    $OWASPMotive = (int)get_param("POST", "OWASPMotive");
    $OWASPOpportunity = (int)get_param("POST", "OWASPOpportunity");
    $OWASPSize = (int)get_param("POST", "OWASPSize");
    $OWASPEaseOfDiscovery = (int)get_param("POST", "OWASPEaseOfDiscovery");
    $OWASPEaseOfExploit = (int)get_param("POST", "OWASPEaseOfExploit");
    $OWASPAwareness = (int)get_param("POST", "OWASPAwareness");
    $OWASPIntrusionDetection = (int)get_param("POST", "OWASPIntrusionDetection");
    $OWASPLossOfConfidentiality = (int)get_param("POST", "OWASPLossOfConfidentiality");
    $OWASPLossOfIntegrity = (int)get_param("POST", "OWASPLossOfIntegrity");
    $OWASPLossOfAvailability = (int)get_param("POST", "OWASPLossOfAvailability");
    $OWASPLossOfAccountability = (int)get_param("POST", "OWASPLossOfAccountability");
    $OWASPFinancialDamage = (int)get_param("POST", "OWASPFinancialDamage");
    $OWASPReputationDamage = (int)get_param("POST", "OWASPReputationDamage");
    $OWASPNonCompliance = (int)get_param("POST", "OWASPNonCompliance");
    $OWASPPrivacyViolation = (int)get_param("POST", "OWASPPrivacyViolation");

    $associate_test = (int)get_param("POST", "associate_test");

    // Custom Risk Scoring
    $custom = (float)get_param("POST", "Custom");

    // Contributing Risk Scoring
    $ContributingLikelihood = (int)get_param("POST", "ContributingLikelihood");
    $ContributingImpacts = get_param("POST", "ContributingImpacts");

    // Submit risk and get back the id
    if($last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes, 0, 0, false, $additional_stakeholders, $risk_catalog_mapping, $threat_catalog_mapping, $template_group_id)){}
    else
    {
        // Display an alert
        ob_end_clean();
        set_alert(true, "bad", $lang['ThereAreUnexpectedProblems']);
        json_response(400, get_alert(true), NULL);
        exit;
    }

    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        // Load the extra
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));

//        create_subject_order($_SESSION['encrypted_pass']);
    }

    // Submit risk scoring
    if (!$scoring_method) { // If the scoring method is invalid then go with the defaults
        submit_risk_scoring($last_insert_id);
    } else { // If there's a valid scoring method use the provided values
        submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
    }

    // Process the data from the Affected Assets widget
    if (!empty($assets_asset_groups)) {
        process_selected_assets_asset_groups_of_type($last_insert_id, $assets_asset_groups, 'risk');
    }

    updateTagsOfType($last_insert_id, 'risk', $risk_tags);

    // Create the connection between the risk and the jira issue
    if (jira_extra()) {
        if ($issue_key) {
            if (jira_update_risk_issue_connection($last_insert_id, $issue_key)) {
                jira_push_changes($issue_key, $last_insert_id);
            }
        } else {
            CreateIssueForRisk($last_insert_id);
        }
    }

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
        set_alert(true, "bad", $error);
        json_response(400, get_alert(true), NULL);
        exit;
    }
    else 
    {
        // If the notification extra is enabled
        if (notification_extra())
        {
            // Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/notification/index.php'));

            // Send the notification
            notify_new_risk($last_insert_id);
        }

        // There is an alert message
        $risk_id = (int)$last_insert_id + 1000;

        //// If the jira extra is enabled
        //if (jira_extra())
        //{
        //    // Include the team jira extra
        //    require_once(realpath(__DIR__ . '/../extras/jira/index.php'));
        //
        //    jira_push_changes_of_risk((int)$last_insert_id);
        //}

        echo "<script> var global_risk_id = " . $risk_id . ";</script>";

        // Display an alert   
        ob_end_clean();
        set_alert(true, "good", _lang("RiskSubmitSuccess", ["subject" => $subject], false));
        json_response(200, get_alert(true), array("risk_id" => $risk_id, "associate_test" => $associate_test));
        exit;
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
<?php
        // Use these jQuery scripts
        $scripts = [
                'jquery.min.js',
        ];

        // Include the jquery javascript source
        display_jquery_javascript($scripts);

        // Use these jquery-ui scripts
        $scripts = [
                'jquery-ui.min.js',
        ];

        // Include the jquery-ui javascript source
        display_jquery_ui_javascript($scripts);

	display_bootstrap_javascript();
?>
        <script src="../js/jquery.dataTables.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/cve_lookup.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/basescript.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/common.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/pages/risk.js?<?php echo current_version("app"); ?>"></script>

    <?php
        // Use these HighCharts scripts
        $scripts = [
                'highcharts.js',
        ];

        // Display the highcharts javascript source
        display_highcharts_javascript($scripts);

?>

        <script src="../js/bootstrap-multiselect.js?<?php echo current_version("app"); ?>"></script>
        <script src="../js/jquery.blockUI.min.js?<?php echo current_version("app"); ?>"></script>

        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/bootstrap.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css?<?php echo current_version("app"); ?>">
<!--        <link rel="stylesheet" href="../css/jquery-ui.min.css">-->

        <link rel="stylesheet" href="../css/jquery.dataTables.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-util.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/divshot-canvas.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/style.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/bootstrap-multiselect.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/theme.css?<?php echo current_version("app"); ?>">
        <link rel="stylesheet" href="../css/side-navigation.css?<?php echo current_version("app"); ?>">

        <link rel="stylesheet" href="../css/selectize.bootstrap3.css?<?php echo current_version("app"); ?>">
        <script src="../js/selectize.min.js?<?php echo current_version("app"); ?>"></script>
        <style>
            #risk-submit-form .row-fluid{max-width: 1400px;}
            .top-panel .span2, .bottom-panel .span2{max-width: 210px;}
            .top-panel .span8, .bottom-panel .span8{margin-left:15px;}
            @media only screen and (min-width: 768px) {
                .top-panel .span2.text-right, .bottom-panel .span2.text-right{margin-left:10px;}
            }
            @media only screen and (min-width: 1400px) {
                .top-panel .span2.text-right, .bottom-panel .span2.text-right{margin-left:22px;}
            }
        </style>

        <?php
            setup_favicon("..");
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
                <?php 
                if(customization_extra()) {
                    $tab_str = "<div class=\"tab-append\">";
                    if(organizational_hierarchy_extra()) {
                        require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
                        $template_groups = get_assigned_template_group_by_user_id($_SESSION['uid'], "risk");
                    } else {
                        require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                        $template_groups = get_custom_template_groups('risk');
                    }
                    foreach($template_groups as $index=>$template_group){
                        $selected = $index == 0?"selected":""; 
                        $tab_str .= "<div class=\"tab tempate_tab {$selected}\" data-content=\"#template_group_".$template_group["id"]."\"><div><span>".$escaper->escapeHtml($template_group["name"])."</span></div></div>\n";
                    }
                    $tab_str .= "</div>\n";
                    echo $tab_str;
                } else { 
                ?>
                    <div class="tab add" id='add-tab'>
                        <span>+</span>
                    </div>
                    <div class="tab-append">
                        <div class="tab selected form-tab tab-show new" id="tab"><div><span><?php echo $escaper->escapeHtml($lang['NewRisk']); ?> (1)</span></div>
                          <button class="close tab-close" aria-label="Close" data-id=""><i class="fa fa-times"></i></button>
                        </div>
                    </div>
                <?php } ?>
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
                    <?php
                    if(customization_extra()) {
                        if(organizational_hierarchy_extra()) {
                            require_once(realpath(__DIR__ . '/../extras/organizational_hierarchy/index.php'));
                            $template_groups = get_assigned_template_group_by_user_id($_SESSION['uid'], "risk");
                        } else {
                            require_once(realpath(__DIR__ . '/../extras/customization/index.php'));
                            $template_groups = get_custom_template_groups('risk');
                        }
                        foreach($template_groups as $index=>$template_group){
                            $hide = $index == 0?"":"hide"; 
                            $template_group_id = $template_group["id"];
                            echo "<div id=\"template_group_".$template_group["id"]."\" class=\"tab-data {$hide}\">";
                            include(realpath(__DIR__ . '/partials/add.php'));
                            echo "</div>\n";
                        }
                    } else {
                        $template_group_id = "";
                        echo  "<div class='tab-data' id='tab-container'>";
                        include(realpath(__DIR__ . '/partials/add.php'));
                        echo "</div>";
                    }
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
        <input type="hidden" id="enable_popup" value="<?php echo $escaper->escapeHtml(get_setting('enable_popup')); ?>">
        <script>
            $(document).ready(function() {

                $('#tab-content-container select.assets-asset-groups-select').each(function() {
                    setupAssetsAssetGroupsWidget($(this));
                });

                window.onbeforeunload = function() {
                    if ($('#subject:enabled').val() != ''){
                        return "Are you sure you want to proceed without saving the risk?";
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
                    $("div.tab-append").append(
                        "<div class='tab new tab-show form-tab selected' id='tab"+num_tabs+"'><div><span><?php echo $escaper->escapeHtml($lang['NewRisk']); ?> ("+num_tabs+")</span></div>"
                        +"<button class='close tab-close' aria-label='Close' data-id='"+num_tabs+"'>"
                        +"<i class='fa fa-times'></i>"
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
                    
                        
                    // Add multiple selects
                    $('.multiselect', "#tab-container"+num_tabs).multiselect({buttonWidth: '100%'});
                    
                    // Add DatePicker
                    if($('.datepicker', "#tab-container"+num_tabs).length){
                        $('.datepicker', "#tab-container"+num_tabs).datepicker();
                    }
                });

                focus_add_css_class("#RiskAssessmentTitle", "#assessment", $("#tab-container"));
                focus_add_css_class("#NotesTitle", "#notes", $("#tab-container"));

                if(typeof max_upload_size == "undefined") max_upload_size = 0;
                if(typeof fileTooBigMessage == "undefined") fileTooBigMessage = "";

                $(".tempate_tab").click(function(){
                    $(".tempate_tab").removeClass("selected");
                    $(this).addClass("selected");
                    $("#tab-content-container .tab-data").hide();
                    var tabContentId = $(this).data('content');
                    $(tabContentId).removeClass("hide").show();
                    return true;
                });

            });

        </script>
        <?php display_set_default_date_format_script(); ?>
    </body>
</html>
