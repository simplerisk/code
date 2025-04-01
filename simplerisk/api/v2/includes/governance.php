<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/governance.php'));

require_once(language_file());

/******************************************
 * FUNCTION: API V2 GOVERNANCE FRAMEWORKS *
 * ****************************************/
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
 * *************************************************/
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
 * **************************************/
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
 * ***************************************/
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
 * **********************************************/
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
 * ****************************************/
function api_v2_governance_controls_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the risk id
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
 * ******************************************/
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
 * *****************************************/
function api_v2_governance_documents_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("governance");

    // Get the risk id
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

?>