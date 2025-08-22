<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/governance.php'));
require_once (realpath(__DIR__ . '/../../../includes/worddoc.php'));
require_once (realpath(__DIR__ . '/../../../includes/tf_idf_enrichment.php'));

require_once(language_file());

/******************************************
 * FUNCTION: API V2 GOVERNANCE FRAMEWORKS *
 ******************************************/
function api_v2_governance_frameworks()
{
    // Check that this user has the ability to view governance
    api_v2_check_permission("governance");

    // Get the framework id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get just the framework with that id
        $framework = get_framework($id);

        // If the framework value returned is empty then we are unable to find a framework with that id
        if (empty($framework))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a framework with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "framework" => $framework,
            ];
        }
    }
    // Otherwise, return all frameworks
    else
    {
        // Get the requested status defaulted to 1
        $status = get_param("GET", "status", 1);

        // Get the frameworks array
        $frameworks = get_frameworks($status, true, true, "name");

        // Create the data array
        $data = [
            "frameworks" => $frameworks,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***************************************************
 * FUNCTION: API V2 GOVERNANCE FRAMEWORKS TREEGRID *
 ***************************************************/
function api_v2_governance_frameworks_treegrid()
{
    global $lang, $escaper;

    // If the user has governance permissions
    if (check_permission("governance"))
    {
        $status = (int)$_GET['status'];
        $result = get_frameworks_as_treegrid($status);
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

/****************************************
 * FUNCTION: API V2 GOVERNANCE CONTROLS *
 ****************************************/
function api_v2_governance_controls()
{
    // Check that this user has the ability to view governance
    api_v2_check_permission("governance");

    // Get the control id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get just the control with that id
        $control = get_framework_control($id);

        // If the control value returned is empty then we are unable to find a control with that id
        if (empty($control))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a control with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "control" => $control,
            ];
        }
    }
    // Otherwise, return all controls
    else
    {
        // Get the controls array
        $controls = get_framework_controls();

        // Create the data array
        $data = [
            "controls" => $controls,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*****************************************
 * FUNCTION: API V2 GOVERNANCE DOCUMENTS *
 *****************************************/
function api_v2_governance_documents()
{
    // Check that this user has the ability to view governance
    api_v2_check_permission("governance");

    // Get the document id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get just the document with that id
        $document = get_document_by_id($id);

        // If the document value returned is empty then we are unable to find a document with that id
        if (empty($document))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a document with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "document" => $document,
            ];
        }
    }
    // Otherwise, return all documents
    else
    {
        // Get the documents array
        $documents = get_documents();

        // Create the data array
        $data = [
            "documents" => $documents,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/************************************************
 * FUNCTION: API V2 GOVERNANCE DOCUMENTS DELETE *
 ************************************************/
function api_v2_governance_documents_delete()
{
    global $lang;

    // Check that this user has the proper permissions
    api_v2_check_permission("governance");
    api_v2_check_permission("delete_documentation");

    // Get the document id and version
    $id = get_param("GET", "document_id", null);
    $version = get_param("GET", "version", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user has the permission to access this document
        if (check_access_for_document($id))
        {
            // Get just the document with that id
            $document = get_document_by_id($id);

            // If the document value returned is empty then we are unable to find a document with that id
            if (empty($document))
            {
                // Set the status
                $status_code = 204;
                $status_message = "NO CONTENT: Unable to find a document with the specified id.";
                $data = [];
            }
            else
            {
                // Attempt to delete the document
                if ($result = delete_document($id, $version))
                {
                    $status_code = 200;
                    $status_message = "Document was deleted successfully.";

                    // Create the data array
                    $data = [
                        "document_id" => $id,
                        "document_name" => $document['document_name'],
                        "document_type" => $document['document_type'],
                    ];
                }
                else
                {
                    $status_code = 400;
                    $status_message = "BAD REQUEST: " . $lang['ErrorDeletingDocument'];
                    $data = [];
                }
            }
        }
        else
        {
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a document with the specified id.";
            $data = [];
        }
    }
    else
    {
        $status_code = 204;
        $status_message = "NO CONTENT: Unable to find a document with the specified id.";
        $data = [];
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/******************************************
 * FUNCTION: API V2 CONTROLS ASSOCIATIONS *
 ******************************************/
function api_v2_governance_controls_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the control id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get the connectivity for the control
        $framework_associations = get_framework_connectivity_for_control($id);
        $test_associations = get_test_connectivity_for_control($id);
        $document_associations = get_document_connectivity_for_control($id);
        $risk_associations = get_risk_connectivity_for_control($id);

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";

        // Create the data array
        $data = [
            "frameworks" => $framework_associations,
            "tests" => $test_associations,
            "documents" => $document_associations,
            "risks" => $risk_associations,
        ];
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

/********************************************
 * FUNCTION: API V2 FRAMEWORKS ASSOCIATIONS *
 ********************************************/
function api_v2_governance_frameworks_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // Get the connectivity for the control
        $control_associations = get_control_connectivity_for_framework($id);

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";

        // Create the data array
        $data = [
            "controls" => $control_associations,
        ];
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

/*******************************************
 * FUNCTION: API V2 DOCUMENTS ASSOCIATIONS *
 *******************************************/
function api_v2_governance_documents_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the document id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this document id
        if (check_access_for_document($id))
        {
            // Get the connectivity for the control
            $control_associations = get_control_connectivity_for_document($id);

            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "controls" => $control_associations,
            ];
        }
        // If the user should not have access to this document id
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

/************************************************
 * FUNCTION: API V2 DOCUMENTS SIGNIFICANT TERMS *
 ************************************************/
function api_v2_governance_documents_significant_terms()
{
    write_debug_log("FUNCTION: API V2 GOVERNANCE DOCUMENTS SIGNIFICANT TERMS");

    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the document id
    $document_id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($document_id))
    {
        // If the user should have access to this document id
        if (check_access_for_document($document_id))
        {
            try
            {
                // Get the document
                $document = get_document_by_id($document_id);

                // If the document doesn't exist, return;
                if (empty($document))
                {
                    // Return a 204 response
                    $status_code = 204;
                    $status_message = "NOT FOUND: The requested document was not found.";
                    $data = [];
                    api_v2_json_result($status_code, $status_message, $data);
                }
                // If the document exists
                else
                {
                    // Open the database connection
                    $db = db_open();

                    // Get the file from the database
                    $unique_name = $document['unique_name'];
                    $stmt = $db->prepare("SELECT * FROM compliance_files WHERE BINARY unique_name=:unique_name");
                    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                    $stmt->execute();

                    // Store the results in an array
                    $array = $stmt->fetch();

                    // Close the database connection
                    db_close($db);

                    // If the array is empty
                    if (empty($array))
                    {
                        // Set the content to null
                        $content = null;
                    }
                    else
                    {
                        // Set the file contents
                        $content = $array['content'];

                        // Write the content to a temporary file
                        $temp_file = tempnam(sys_get_temp_dir(), 'doc_');
                        file_put_contents($temp_file, $content);

                        // Read the Word document
                        $phpWord = PhpOffice\PhpWord\IOFactory::load($temp_file, 'Word2007');

                        // Delete the temporary file
                        unlink($temp_file);

                        // Extract the text from the Word document
                        $document_text = extract_text_content($phpWord);
                        write_debug_log("Extracted Text: " . $document_text);

                        // Get the significant terms for the document
                        $significant_terms = extractSignificantTerms($document_text, 150);
                        write_debug_log("Significant Terms: " . json_encode($significant_terms));

                        // Set the status
                        $status_code = 200;
                        $status_message = "SUCCESS";

                        // Create the data array
                        $data = [
                            "terms" => $significant_terms,
                        ];
                    }

                }
            } catch (\Exception $e) {
                write_debug_log("Error processing document: " . $e->getMessage());
                // Return a 500 response
                $status_code = 500;
                $status_message = "Error processing document.";
                $data = [];
                api_v2_json_result($status_code, $status_message, $data);
            }
        }
        // If the user should not have access to this document id
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

/*******************************************
 * FUNCTION: API V2 DOCUMENTS TOP CONTROLS *
 *******************************************/
function api_v2_governance_documents_top_controls()
{
    write_debug_log("FUNCTION: API V2 GOVERNANCE DOCUMENTS TOP CONTROLS");

    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the document id
    $document_id = get_param("GET", "id", null);

    // Get the minimum score
    $minimum_score = get_param("GET", "minimum_score", 0.3);

    // If refresh is set to true
    if (isset($_GET['refresh']) && $_GET['refresh'] == 'true')
    {
        $refresh = true;
    }
    // If refresh is not set or is set to something other than true
    else
    {
        $refresh = false;
    }

    // If we received an id
    if (!empty($document_id))
    {
        // If the user should have access to this document id
        if (check_access_for_document($document_id))
        {
            try
            {
                // Get the document to control mappings for the selected document ID
                $mappings = get_document_to_control_mappings($document_id, $refresh);

                // If the mappings returned false
                if ($mappings === false)
                {
                    // Return a 204 response
                    $status_code = 204;
                    $status_message = "NOT FOUND: The requested document was not found.";
                    $data = [];
                    api_v2_json_result($status_code, $status_message, []);
                }
                // If we have mappings
                else
                {
                    // Take only the mappings with a score higher than the minimum score
                    $filtered = array_filter($mappings, function ($item) use ($minimum_score) {
                        return isset($item['score']) && $item['score'] >= $minimum_score;
                    });

                    // Get the controls that we want to return
                    $controls = array_map(function ($item) {
                        return [
                            'control_id' => $item['control_id'] ?? null,
                            'score' => $item['score'] ?? 0,
                            'tfidf_similarity' => $item['tfidf_similarity'] ?? 0,
                            'keyword_matches' => $item['keyword_match'] ?? 0
                        ];
                    }, $filtered);

                    // Set the status
                    $status_code = 200;
                    $status_message = "SUCCESS";

                    // Get the data that we want to return
                    $data = [
                        'document_id' => $document_id,
                        'controls' => $controls
                    ];

                    // If the AI Extra is enabled
                    if (artificial_intelligence_extra())
                    {
                        // Required file
                        $required_file = realpath(__DIR__ . '/../../../extras/artificial_intelligence/includes/api.php');

                        // If the file exists
                        if (file_exists($required_file))
                        {
                            // Include the required file
                            require_once($required_file);

                            // Get the AI document to control recommendations
                            $endpoint = "/api/v2/ai/document/enhance?document_id={$document_id}";
                            @call_simplerisk_api_endpoint($endpoint, "GET", false, 1);
                        }
                    }
                }
            } catch (\Exception $e) {
                write_debug_log("Error processing document: " . $e->getMessage());
                // Return a 500 response
                $status_code = 500;
                $status_message = "Error processing document.";
                $data = [];
                api_v2_json_result($status_code, $status_message, $data);
            }
        }
        // If the user should not have access to this document id
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

/*******************************************
 * FUNCTION: API V2 CONTROLS TOP DOCUMENTS *
 *******************************************/
function api_v2_governance_controls_top_documents()
{
    write_debug_log("FUNCTION: API V2 GOVERNANCE CONTROLS TOP DOCUMENTS");

    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the control id
    $control_id = get_param("GET", "id", null);

    // Get the minimum score
    $minimum_score = get_param("GET", "minimum_score", 0.3);

    // If refresh is set to true
    if (isset($_GET['refresh']) && $_GET['refresh'] == 'true')
    {
        $refresh = true;
    }
    // If refresh is not set or is set to something other than true
    else
    {
        $refresh = false;
    }

    // If we received an id
    if (!empty($control_id))
    {
        try
        {
            // Get the document to control mappings for the selected control ID
            $mappings = get_control_to_document_mappings($control_id, $refresh);

            // If the mappings returned false
            if ($mappings === false)
            {
                // Return a 204 response
                $status_code = 204;
                $status_message = "NOT FOUND: The requested control was not found.";
                $data = [];
                api_v2_json_result($status_code, $status_message, []);
            }
            // If we have mappings
            else
            {
                // Take only the mappings with a score higher than the minimum score
                $filtered = array_filter($mappings, function ($item) use ($minimum_score) {
                    return isset($item['score']) && $item['score'] >= $minimum_score;
                });

                // Get the controls that we want to return
                $documents = array_map(function ($item) {
                    return [
                        'document_id' => $item['document_id'] ?? null,
                        'score' => $item['score'] ?? 0,
                        'tfidf_similarity' => $item['tfidf_similarity'] ?? 0,
                        'keyword_matches' => $item['keyword_match'] ?? 0
                    ];
                }, $filtered);

                // If the AI Extra is enabled
                if (artificial_intelligence_extra())
                {
                    // Required file
                    $required_file = realpath(__DIR__ . '/../../../extras/artificial_intelligence/includes/api.php');

                    // If the file exists
                    if (file_exists($required_file))
                    {
                        // Include the required file
                        require_once($required_file);

                        // For each document
                        foreach ($documents as $document)
                        {
                            // Get the document id
                            $document_id = $document['document_id'];

                            // If the document id is not empty
                            if (!empty($document_id))
                            {
                                // Get the AI document to control recommendations
                                $endpoint = "/api/v2/ai/document/enhance?document_id={$document_id}";
                                @call_simplerisk_api_endpoint($endpoint, "GET", false, 1);
                            }
                        }
                    }
                }

                // Set the status
                $status_code = 200;
                $status_message = "SUCCESS";

                // Get the data that we want to return
                $data = [
                    'control_id' => $control_id,
                    'documents' => $documents
                ];
            }
        } catch (\Exception $e) {
            write_debug_log("Error processing control: " . $e->getMessage());
            // Return a 500 response
            $status_code = 500;
            $status_message = "Error processing control.";
            $data = [];
            api_v2_json_result($status_code, $status_message, $data);
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

/****************************************
 * FUNCTION: API V2 GOVERNANCE KEYWORDS *
 ****************************************/
function api_v2_governance_keywords()
{
    write_debug_log("FUNCTION: API V2 GOVERNANCE KEYWORDS");

    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the governance type
    $type = get_param("GET", "type", null);

    // Get the governance id
    $id = get_param("GET", "id", null);

    // Switch based on the type provided
    switch ($type)
    {
        case "document":
            $result = get_keywords_for_document($id);
            break;
        case "control":
            $result = get_keywords_for_control($id);
            break;
        default:
            $result = [
                'status_code' => 400,
                'status_message' => "BAD REQUEST: The type provided is not valid.",
                'data' => []
            ];
            // Set the status
            $status_code = 400;
            $status_message = "BAD REQUEST: The type provided is not valid.";
            $data = [];
            api_v2_json_result($status_code, $status_message, $data);
            break;
    }

    // Get the result values
    $status_code = $result['status_code'];
    $status_message = $result['status_message'];
    $data = $result['data'];

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*****************************************************
 * FUNCTION: API V2 GOVERNANCE DOCUMENTS TO CONTROLS *
 *****************************************************/
function api_v2_governance_documents_to_controls()
{
    write_debug_log("FUNCTION: API V2 GOVERNANCE DOCUMENTS TO CONTROLS");

    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the document id
    $document_id = get_param("GET", "document_id", null);

    // Get the documents to controls array
    $documents_to_controls = get_documents_to_controls(0, false, false, false, false, [], $document_id);

    // If documents were found
    if (!empty($documents_to_controls['data']))
    {
        // Get the result values
        $status_code = 200;
        $status_message = "SUCCESS";
        $data = $documents_to_controls['data'];
    }
    // If no documents were found
    else
    {
        $status_code = 204;
        $status_message = "NO CONTENT: Unable to find a document with the specified id.";
        $data = [];
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/**********************************************
 * FUNCTION: SAVE CUSTOM DISPLAY SETTINGS API *
 *********************************************/
function saveCustomDocumentsToControlsDisplaySettingsAPI(){
    global $escaper, $lang;
    if (!check_permission("governance")){
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
        return;
    }
    if(isset($_POST["document_columns"]) && isset($_POST["control_columns"]) && isset($_POST["matching_columns"])){
        $data = array(
            "document_columns" => $_POST["document_columns"],
            "control_columns" => $_POST["control_columns"],
            "matching_columns" => $_POST["matching_columns"],
        );
        save_custom_risk_display_settings("custom_documents_to_controls_display_settings", $data);
        set_alert(true, "good", $lang['SavedSuccess']);
        json_response(200, get_alert(true), null);
    } else {
        set_alert(true, "bad", $lang['NoDataAvailable']);
        json_response(400, get_alert(true), NULL);
    }
    return;
}

/******************************************************************
 * FUNCTION: RETURN JSON DATA FOR DOCUMENTS TO CONTROLS DATATABLE *
 ******************************************************************/
function getDocumentsToControlsDatatableResponse()
{
    global $lang;
    global $escaper;

    // If the user has governance permissions
    if (check_permission("governance")) {

        $user = get_user_by_id($_SESSION['uid']);
        $settings = json_decode($user["custom_documents_to_controls_display_settings"] ?? '', true);
        $document_columns_setting = isset($settings["document_columns"])?$settings["document_columns"]:[];
        $control_columns_setting = isset($settings["control_columns"])?$settings["control_columns"]:[];
        $matching_columns_setting = isset($settings["matching_columns"])?$settings["matching_columns"]:[];
        $columns_setting = array_merge($document_columns_setting, $control_columns_setting, $matching_columns_setting);
        $columns = [];

        foreach($columns_setting as $column) {
            if(stripos($column[0], "custom_field_") !== false) {
                if(customization_extra() && $column[1] == 1) $columns[] = $column[0];
            } else if($column[1] == 1) {
                $columns[] = $column[0];
            }
        }
        if(!count($columns)){
            $columns = ["document","control_number","selected","matching","recommendation"];
        }

        $draw = $escaper->escapeHtml($_POST['draw']);

        $start  = $_POST['start'] ? (int)$_POST['start'] : 0;
        $length = $_POST['length'] ? (int)$_POST['length'] : 10;
        $orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : "";
        $orderColumnName = isset($_POST['columns'][$orderColumn]['name']) ? $_POST['columns'][$orderColumn]['name'] : null;;
        $orderDir = !empty($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc'? 'asc' : 'desc';

        $column_filters = [];
        for ($i=0; $i<count($_POST['columns']); $i++) {
            if (isset($_POST['columns'][$i]) && $_POST['columns'][$i]['searchable'] == "true" && $_POST['columns'][$i]['search']['value'] != '') {
                $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
            }
        }

        // Pass column filters to the get_documents_to_controls function
        $documents_to_controls = get_documents_to_controls(1, $orderColumnName, $orderDir, $start, $length, $column_filters, null);
        $recordsTotal = $documents_to_controls['total'];

        // Now $recordsTotal already accounts for the filters
        $recordsFiltered = $recordsTotal;

        $documents_data = [];
        foreach ($documents_to_controls['data'] as $key=>$document)
        {
            $data_row = [];
            foreach($columns as $column){
                switch ($column) {
                    default :
                        $data_row[] = $escaper->escapeHtml($document[$column]);
                        break;
                    case "document_id":
                        $document_id = $document['document_id'];
                        $data_row[] = "<div data-document_id='{$document_id}'>{$document_id}</div>";
                        break;
                    case "document":
                        $data_row[] = "<div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/compliance/download.php?id=".$escaper->escapeHtml($document['unique_name'])."\">".$escaper->escapeHtml($document['document_name'])."</a></div>";
                        break;
                    case "control_id":
                        $control_id = $document['control_id'];
                        $data_row[] = "<div data-control_id='{$control_id}'>{$control_id}</div>";
                        break;
                    case "selected":
                        $data_row[] = ($document['selected'] === 1 ? "Yes" : "No");
                        break;
                    case "ai_match":
                        $data_row[] = ($document['ai_match'] === 1 ? "Yes" : "No");
                        break;
                    case "ai_confidence":
                        $data_row[] = "{$document['ai_confidence']}%";
                        break;
                    case "ai_reasoning":
                        $data_row[] = (!empty($document['ai_reasoning']) ? $escaper->escapeHtml($document['ai_reasoning']) : $escaper->escapeHtml($lang['ToBeDetermined']));
                        break;
                    case "matching":
                        $matching = $document['matching'];
                        $data_row[] = "<div class=\"matching\">" . $escaper->escapeHtml($lang[$matching]) . "</div>";
                        break;
                    case "recommendation":
                        $recommendation = $document['recommendation'];
                        $data_row[] = "<div class=\"recommendation\">" . $escaper->escapeHtml($lang[$recommendation]) . "</div>";
                        break;
                }
            }
            $documents_data[] = $data_row;
        }

        // The filtered data is already properly handled by the database query
        $documents_by_page = $documents_data;

        $result = [
            'draw' => $draw,
            'data' => $documents_data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
        ];
        echo json_encode($result);
        exit;
    }
    else
    {
        json_response(400, $escaper->escapeHtml($lang['NoPermissionForGovernance']), NULL);
    }
}

?>