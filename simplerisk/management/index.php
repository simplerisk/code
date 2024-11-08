<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['blockUI', 'tabs:logic', 'selectize', 'datatables', 'chart.js', 'WYSIWYG', 'multiselect', 'CUSTOM:common.js', 'CUSTOM:pages/risk.js', 'CUSTOM:cve_lookup.js', 'datetimerangepicker', 'JSLocalization'], ['check_riskmanagement' => true], required_localization_keys: ['MitigationPlanned']);

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
        
        // Only return the alert messages in this response if it will be processed on the Compliance > Active/Past Audits pages
        // because when normally creating a risk we're redirecting to the view risk page and we want the messages to stay in the session
        // so they can be displayed there
        json_response(200, $associate_test ? get_alert(true) : null, array("risk_id" => $risk_id, "associate_test" => $associate_test));
        
        exit;
    }
}

?>
<script>
    var simplerisk = {
        risk: "<?php echo $lang['Risk']; ?>",
        newrisk: "<?php echo $lang['NewRisk']; ?>"
    }
</script>
<div class="row bg-white">
    <div class="col-12">
<?php
    display_add_risk();    
?>
    </div>
</div>

<script>
    $(document).ready(function() {
        $(".datepicker").initAsDatePicker();

        $('.multiselect').multiselect({buttonWidth: '100%'});

        $('#tab-content-container select.assets-asset-groups-select').each(function() {
            setupAssetsAssetGroupsWidget($(this));
        });

        window.onbeforeunload = function() {
            if ($('#subject:enabled').val() != ''){
                return "Are you sure you want to proceed without saving the risk?";
            }
        }
        
        if(typeof max_upload_size == "undefined") max_upload_size = 0;
        if(typeof fileTooBigMessage == "undefined") fileTooBigMessage = "";

    });
</script>
<?php  
// Render the footer of the page. Please don't put code after this part.
render_footer();
?>