<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));

require_once(language_file());

/**************************
 * FUNCTION: API V2 RISKS *
 * ************************/
function api_v2_risks()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get just the risk with that id
        $risk = get_risk_by_id($id);

        // If the risk value returned is empty then we are unable to find a risk with that id
        if (empty($risk))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a risk with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "risk" => $risk,
            ];
        }
    }
    // Otherwise, return all risks
    else
    {
        // Get the risks array
        $risks = get_risks(0, "id", "asc");

        // Create the data array
        $data = [
            "risks" => $risks,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***************************************
 * FUNCTION: API V2 RISKS ASSOCIATIONS *
 * *************************************/
function api_v2_risks_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this risk id
        if (check_access_for_risk($id))
        {
            // Get the connectivity for the risk
            $asset_associations = get_asset_connectivity_for_risk($id);
            $control_associations = get_control_connectivity_for_risk($id);

            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "assets" => $asset_associations,
                "controls" => $control_associations,
            ];
        }
        // If the user should not have access to this risk id
        else
        {
            // Set the status
            $status_code = 403;
            $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";
            $data = null;
        }
    }
    // Otherwise, return an empty data array
    else
    {
        // Create the data array
        $data = [];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***********************************
 * FUNCTION: API V2 RISKS TAGS GET *
 * *********************************/
function api_v2_risks_tags_get()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("riskmanagement");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // Open a database connection
    $db = db_open();

    // If we received an id
    if (!empty($id))
    {
        // Get just the tag with that id
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT r.id+1000 ORDER BY r.id ASC) as risk_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `risks` r ON r.id=tt.taggee_id WHERE tt.type='risk' AND t.id=:id GROUP BY t.id;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a tag with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the risk_id string into an array
                $tags[$key]['risk_ids'] = explode(',', $tag['risk_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each risk id
                    foreach ($tags[$key]['risk_ids'] as $risk_id)
                    {
                        // If the user should not have access to this risk id
                        if (!extra_grant_access($_SESSION['uid'], $risk_id))
                        {
                            // Remove it from the array
                            $tags[$key]['risk_ids'] = array_diff($tags[$key]['risk_ids'], [$risk_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }
    // Otherwise, return all tags
    else
    {
        // Get the list of tags and associated risks
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT r.id+1000 ORDER BY r.id ASC) as risk_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `risks` r ON r.id=tt.taggee_id WHERE tt.type='risk' GROUP BY t.id;");
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: No tags found.";
            $data = null;
        }
        else
        {
            // Return the result
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the risk_id string into an array
                $tags[$key]['risk_ids'] = explode(',', $tag['risk_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each risk id
                    foreach ($tags[$key]['risk_ids'] as $risk_id)
                    {
                        // If the user should not have access to this risk id
                        if (!extra_grant_access($_SESSION['uid'], $risk_id))
                        {
                            // Remove it from the array
                            $tags[$key]['risk_ids'] = array_diff($tags[$key]['risk_ids'], [$risk_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/**
 * FUNCTION: MANAGEMENT-LIKE RISK SUBMIT (API)
 * Mirrors the submit behavior used by the management risk submission form.
 * - Reads the richer set of POST params (mappings, template group, assets_asset_groups, files, associate_test)
 * - Validates tags and optional Jira issue key
 * - Submits the risk, scoring, assets/groups, tags, Jira association
 * - Uploads files; on upload error, deletes the risk and returns error
 * - Sends notification after successful creation
 * - Returns JSON with risk_id and associate_test
 */
function api_v2_risk_submit()
{
    global $lang, $escaper;

    // Permission check
    if (!isset($_SESSION["submit_risks"]) || $_SESSION["submit_risks"] != 1) {
        api_v2_json_result(401, $escaper->escapeHtml($lang['RiskAddPermissionMessage']), null);
        return;
    }

    // Subject validation (non-empty)
    $subject = get_param("POST", 'subject', "");
    if (!trim($subject)) {
        api_v2_json_result(400, $escaper->escapeHtml($lang['SubjectRiskCannotBeEmpty']), null);
        return;
    }

    // Tags validation
    $risk_tags = get_param("POST", "tags", []);
    foreach ($risk_tags as $tag) {
        if (strlen($tag) > 255) {
            api_v2_json_result(400, $escaper->escapeHtml($lang['MaxTagLengthWarning']), null);
            return;
        }
    }

    // Jira validation (if enabled)
    if (jira_extra()) {
        require_once(realpath(__DIR__ . '/../extras/jira/index.php'));
        $issue_key = isset($_POST['jira_issue_key']) ? strtoupper(trim($_POST['jira_issue_key'])) : "";
        if ($issue_key && !jira_validate_issue_key($issue_key)) {
            api_v2_json_result(400, get_alert(true), null);
            return;
        }
    } else {
        $issue_key = "";
    }

    // Gather core fields
    $status                  = "New";
    $risk_catalog_mapping    = get_param("POST", 'risk_catalog_mapping', []);
    $threat_catalog_mapping  = get_param("POST", 'threat_catalog_mapping', []);
    $reference_id            = get_param("POST", 'reference_id', "");
    $regulation              = (int)get_param("POST", 'regulation', 0);
    $control_number          = get_param("POST", 'control_number', "");
    $location                = implode(",", get_param("POST", "location", []));
    $source                  = (int)get_param("POST", 'source', 0);
    $category                = (int)get_param("POST", 'category', 0);
    $team                    = get_param("POST", 'team', []);
    $technology              = get_param("POST", 'technology', []);
    $owner                   = (int)get_param("POST", "owner", 0);
    $manager                 = (int)get_param("POST", "manager", 0);
    $assessment              = get_param("POST", "assessment", "");
    $notes                   = get_param("POST", "notes", "");
    $assets_asset_groups     = get_param("POST", "assets_asset_groups", []);
    $additional_stakeholders = get_param("POST", "additional_stakeholders", []);
    $associate_test          = (int)get_param("POST", "associate_test", 0);

    // Template group (Customization Extra)
    if (customization_extra()) {
        $template_group_id = get_param("POST", "template_group_id", "");
    } else {
        $template_group_id = "";
    }

    // Scoring method and fields
    // 1 = Classic, 2 = CVSS, 3 = DREAD, 4 = OWASP, 5 = Custom, 6 = Contributing Risk
    $scoring_method = (int)get_param("POST", "scoring_method", 0);

    // Classic
    $CLASSIClikelihood = (int)get_param("POST", "likelihood", 0);
    $CLASSICimpact     = (int)get_param("POST", "impact", 0);

    // CVSS
    $CVSSAccessVector                = get_param("POST", "AccessVector", "");
    $CVSSAccessComplexity            = get_param("POST", "AccessComplexity", "");
    $CVSSAuthentication              = get_param("POST", "Authentication", "");
    $CVSSConfImpact                  = get_param("POST", "ConfImpact", "");
    $CVSSIntegImpact                 = get_param("POST", "IntegImpact", "");
    $CVSSAvailImpact                 = get_param("POST", "AvailImpact", "");
    $CVSSExploitability              = get_param("POST", "Exploitability", "");
    $CVSSRemediationLevel            = get_param("POST", "RemediationLevel", "");
    $CVSSReportConfidence            = get_param("POST", "ReportConfidence", "");
    $CVSSCollateralDamagePotential   = get_param("POST", "CollateralDamagePotential", "");
    $CVSSTargetDistribution          = get_param("POST", "TargetDistribution", "");
    $CVSSConfidentialityRequirement  = get_param("POST", "ConfidentialityRequirement", "");
    $CVSSIntegrityRequirement        = get_param("POST", "IntegrityRequirement", "");
    $CVSSAvailabilityRequirement     = get_param("POST", "AvailabilityRequirement", "");

    // DREAD
    $DREADDamage            = (int)get_param("POST", "DREADDamage", 0);
    $DREADReproducibility   = (int)get_param("POST", "DREADReproducibility", 0);
    $DREADExploitability    = (int)get_param("POST", "DREADExploitability", 0);
    $DREADAffectedUsers     = (int)get_param("POST", "DREADAffectedUsers", 0);
    $DREADDiscoverability   = (int)get_param("POST", "DREADDiscoverability", 0);

    // OWASP
    $OWASPSkillLevel            = (int)get_param("POST", "OWASPSkillLevel", 0);
    $OWASPMotive                = (int)get_param("POST", "OWASPMotive", 0);
    $OWASPOpportunity           = (int)get_param("POST", "OWASPOpportunity", 0);
    $OWASPSize                  = (int)get_param("POST", "OWASPSize", 0);
    $OWASPEaseOfDiscovery       = (int)get_param("POST", "OWASPEaseOfDiscovery", 0);
    $OWASPEaseOfExploit         = (int)get_param("POST", "OWASPEaseOfExploit", 0);
    $OWASPAwareness             = (int)get_param("POST", "OWASPAwareness", 0);
    $OWASPIntrusionDetection    = (int)get_param("POST", "OWASPIntrusionDetection", 0);
    $OWASPLossOfConfidentiality = (int)get_param("POST", "OWASPLossOfConfidentiality", 0);
    $OWASPLossOfIntegrity       = (int)get_param("POST", "OWASPLossOfIntegrity", 0);
    $OWASPLossOfAvailability    = (int)get_param("POST", "OWASPLossOfAvailability", 0);
    $OWASPLossOfAccountability  = (int)get_param("POST", "OWASPLossOfAccountability", 0);
    $OWASPFinancialDamage       = (int)get_param("POST", "OWASPFinancialDamage", 0);
    $OWASPReputationDamage      = (int)get_param("POST", "OWASPReputationDamage", 0);
    $OWASPNonCompliance         = (int)get_param("POST", "OWASPNonCompliance", 0);
    $OWASPPrivacyViolation      = (int)get_param("POST", "OWASPPrivacyViolation", 0);

    // Custom score
    $custom = (float)get_param("POST", "Custom", 0);

    // Contributing risk
    $ContributingLikelihood = (int)get_param("POST", "ContributingLikelihood", 0);
    $ContributingImpacts    = get_param("POST", "ContributingImpacts", []);

    // Submit risk
    $last_insert_id = submit_risk(
        $status,
        $subject,
        $reference_id,
        $regulation,
        $control_number,
        $location,
        $source,
        $category,
        $team,
        $technology,
        $owner,
        $manager,
        $assessment,
        $notes,
        0,                // mitigation_id
        0,                // mgmt_review
        false,            // user_submitted
        $additional_stakeholders,
        $risk_catalog_mapping,
        $threat_catalog_mapping,
        $template_group_id
    );

    if (!$last_insert_id) {
        set_alert(true, "bad", $escaper->escapeHtml($lang['ThereAreUnexpectedProblems']));
        api_v2_json_result(400, get_alert(true), null);
        return;
    }

    // Encryption extra hook (no-op unless needed)
    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
        // create_subject_order(...) intentionally omitted
    }

    // Submit scoring
    if (!$scoring_method) {
        submit_risk_scoring($last_insert_id);
    } else {
        submit_risk_scoring(
            $last_insert_id,
            $scoring_method,
            $CLASSIClikelihood,
            $CLASSICimpact,
            $CVSSAccessVector,
            $CVSSAccessComplexity,
            $CVSSAuthentication,
            $CVSSConfImpact,
            $CVSSIntegImpact,
            $CVSSAvailImpact,
            $CVSSExploitability,
            $CVSSRemediationLevel,
            $CVSSReportConfidence,
            $CVSSCollateralDamagePotential,
            $CVSSTargetDistribution,
            $CVSSConfidentialityRequirement,
            $CVSSIntegrityRequirement,
            $CVSSAvailabilityRequirement,
            $DREADDamage,
            $DREADReproducibility,
            $DREADExploitability,
            $DREADAffectedUsers,
            $DREADDiscoverability,
            $OWASPSkillLevel,
            $OWASPMotive,
            $OWASPOpportunity,
            $OWASPSize,
            $OWASPEaseOfDiscovery,
            $OWASPEaseOfExploit,
            $OWASPAwareness,
            $OWASPIntrusionDetection,
            $OWASPLossOfConfidentiality,
            $OWASPLossOfIntegrity,
            $OWASPLossOfAvailability,
            $OWASPLossOfAccountability,
            $OWASPFinancialDamage,
            $OWASPReputationDamage,
            $OWASPNonCompliance,
            $OWASPPrivacyViolation,
            $custom,
            $ContributingLikelihood,
            $ContributingImpacts
        );
    }

    // Process assets & asset groups (widget payload)
    if (!empty($assets_asset_groups)) {
        process_selected_assets_asset_groups_of_type($last_insert_id, $assets_asset_groups, 'risk');
    }

    // Tags
    updateTagsOfType($last_insert_id, 'risk', $risk_tags);

    // Jira association
    if (jira_extra()) {
        if ($issue_key) {
            if (jira_update_risk_issue_connection($last_insert_id, $issue_key)) {
                jira_push_changes($issue_key, $last_insert_id);
            }
        } else {
            CreateIssueForRisk($last_insert_id);
        }
    }

    // File uploads (if any). On error -> delete risk and return error.
    $uploadError = 1;
    if (!empty($_FILES) && isset($_FILES['file'])) {
        // Normalize to an array of files
        $isMulti = is_array($_FILES['file']['name']);
        $count   = $isMulti ? count($_FILES['file']['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            $file = $isMulti
                ? [
                    'name'     => $_FILES['file']['name'][$i],
                    'type'     => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'size'     => $_FILES['file']['size'][$i],
                    'error'    => $_FILES['file']['error'][$i],
                ]
                : [
                    'name'     => $_FILES['file']['name'],
                    'type'     => $_FILES['file']['type'],
                    'tmp_name' => $_FILES['file']['tmp_name'],
                    'size'     => $_FILES['file']['size'],
                    'error'    => $_FILES['file']['error'],
                ];

            // Skip errored entries silently; treat as upload failure
            if (!empty($file['error'])) {
                $uploadError = $file['error'];
                break;
            }

            $uploadError = upload_file($last_insert_id, $file, 1);
            if ($uploadError != 1) {
                break;
            }
        }
    }

    if ($uploadError != 1) {
        // Rollback risk if any upload failed
        delete_risk($last_insert_id);
        set_alert(true, "bad", $escaper->escapeHtml(is_string($uploadError) ? $uploadError : $lang['ThereAreUnexpectedProblems']));
        api_v2_json_result(400, get_alert(true), null);
        return;
    }

    // Notify after successful creation
    if (notification_extra()) {
        require_once(realpath(__DIR__ . '/../extras/notification/index.php'));
        notify_new_risk($last_insert_id);
    }

    $risk_id = (int)$last_insert_id + 1000;

    // Compose response
    set_alert(true, "good", _lang("RiskSubmitSuccess", ["subject" => $subject], false));
    api_v2_json_result(
        200,
        $associate_test ? get_alert(true) : null,
        ["risk_id" => $risk_id, "associate_test" => $associate_test]
    );
}

?>