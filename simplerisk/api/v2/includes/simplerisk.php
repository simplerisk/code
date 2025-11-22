<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/queues.php'));
require_once(realpath(__DIR__ . '/../../../includes/promises.php'));
require_once(realpath(__DIR__ . '/../../../includes/upgrade.php'));

/**********************************
 * FUNCTION: API V2 ADMIN VERSION *
 * ********************************/
function api_v2_admin_version()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the current version of the SimpleRisk application
    $current_version_app = current_version("app");

    // Get the current version of the SimpleRisk database
    $current_version_db = current_version("db");

    // Create the data array
    $data = [
        "app" => $current_version_app,
        "db" => $current_version_db,
    ];

    // Set the status
    $status_code = 200;
    $status_message = "SUCCESS";

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/**************************************
 * FUNCTION: API V2 ADMIN VERSION APP *
 **************************************/
function api_v2_admin_version_app()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the current version of the SimpleRisk application
    $current_version_app = current_version("app");

    // Create the data array
    $data = [
        "app" => $current_version_app,
    ];

    // Set the status
    $status_code = 200;
    $status_message = "SUCCESS";

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*************************************
 * FUNCTION: API V2 ADMIN VERSION DB *
 *************************************/
function api_v2_admin_version_db()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the current version of the SimpleRisk database
    $current_version_db = current_version("db");

    // Create the data array
    $data = [
        "db" => $current_version_db,
    ];

    // Set the status
    $status_code = 200;
    $status_message = "SUCCESS";

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*************************************
 * FUNCTION: API V2 ADMIN TAG DELETE *
 *************************************/
function api_v2_admin_tag_delete()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the tag id and type provided
    $id = get_param("GET", "id", null);

    // Delete the tag with that id
    delete_tag($id);

    // Create the result
    $status_code = 200;
    $status_message = "Delete successful";
    $data = null;

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*****************************************
 * FUNCTION: API V2 ADMIN TAG DELETE ALL *
 *****************************************/
function api_v2_admin_tag_delete_all()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the type provided
    $type = get_param("GET", "type", null);

    global $tag_types;
    if ($type === 'all' || in_array($type, $tag_types)) {

        // Delete all tags for the type
        delete_all_tags($type);
    
        // Create the result
        $status_code = 200;
        $status_message = "Delete successful";
    
    } else {
        // Create the result
        $status_code = 400;
        $status_message = "Invalid type";
    }

    $data = null;

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/******************************************
 * FUNCTION: API V2 ADMIN WRITE DEBUG LOG *
 ******************************************/
function api_v2_admin_write_debug_log()
{
    // Open the database connection
    $db = db_open();

    try {
        // Start a transaction
        $db->beginTransaction();

        // Get the list of all debug_log messages
        $stmt = $db->prepare("SELECT id, message FROM `debug_log` FOR UPDATE;");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each of the results
        foreach ($results as $result)
        {
            // Write the message to the Apache debug log
            write_debug_log($result['message']);

            // Delete the message
            $stmt = $db->prepare("DELETE FROM `debug_log` WHERE id=:id");
            $stmt->bindParam(":id", $result['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // Commit the transaction
        $db->commit();
    } catch (Exception $e) {
        // If an error occurs, rollback the transaction
        $db->rollBack();
        write_debug_log("Error in api_v2_admin_write_debug_log: " . $e->getMessage());
    } finally {
        // Close the database connection
        db_close($db);
    }
}

/*************************************
 * FUNCTION: API V2 ADMIN UPGRADE DB *
 *************************************/
function api_v2_admin_upgrade_db()
{
    global $releases;

    // Check that this is an admin user
    api_v2_check_admin();

    // Get the raw POST data
    $requestBody = file_get_contents("php://input");

    // Decode JSON into an associative array
    $data = json_decode($requestBody, true);

    // If the version provided is in the correct format
    if (isset($data['version']) && preg_match('/^\d{8}-\d{3}$/', $data['version']))
    {
        // Extract the version number
        $version = $data['version'];

        // If the version is not in the releases array, return an error
        if (!in_array($version, $releases))
        {
            // Create the result
            $status_code = 400;
            $status_message = "Invalid version";
            $data = null;
        }
        else
        {
            // Get the upgrade function to call for this release version
            $release_function_name = get_database_upgrade_function_for_release($version);

            // If a release function name was found
            if ($release_function_name != false)
            {

                // If the release function exists
                if (function_exists($release_function_name))
                {
                    // Open the database connection
                    $db = db_open();

                    // Call the release function
                    call_user_func($release_function_name, $db);

                    // Close the database
                    db_close($db);

                    // Create the result
                    $status_code = 200;
                    $status_message = "Upgrade successful";
                    $data = null;
                }
                // If the upgrade function does not exist
                else
                {
                    // Create the result
                    $status_code = 400;
                    $status_message = "Upgrade function does not exist";
                    $data = null;
                }
            }
            // If a release function name was not found
            else
            {
                // Create the result
                $status_code = 400;
                $status_message = "Upgrade function not found";
                $data = null;
            }
        }
    }
    // If no version was provided, run the upgrade process for the current version
    else
    {
        // Create the result
        $status_code = 400;
        $status_message = "Invalid version format";
        $data = null;
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*********************************************************
 * FUNCTION: API V2 UPDATE ALL DOCUMENT CONTROL MAPPINGS *
 *********************************************************/
function api_v2_update_all_document_control_mappings()
{
    // Allow this to run as long as it needs
    ini_set('max_execution_time', 0);

    // Check that this is an admin user
    api_v2_check_admin();

    // Open the database connection
    $db = db_open();

    // Get the list of all document ids
    $stmt = $db->prepare("SELECT `id` FROM `documents`");
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create an array to store the list of updated documents
    $updated_documents = [];

    // For each document in the results
    foreach ($documents as $document)
    {
        // Get the document id
        $document_id = $document['id'];

        write_debug_log("Updating control mapping suggestions for document id: " . $document_id);

        // Update the document to control mappings for the document
        $mappings = get_document_to_control_mappings($document_id, true);

        // If we successfully processed mappings
        if ($mappings !== false)
        {
            // Add the document id to the list of updated documents
            $updated_documents[] = (int)$document_id;
        }
    }

    // Close the database connection
    db_close($db);

    // Create the result
    $status_code = 200;
    $status_message = "Update successful";
    $data = [
        'updated_documents' => $updated_documents
    ];

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/********************************
 * FUNCTION: API V2 ADMIN QUEUE *
 ********************************/
function api_v2_admin_queue()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // DataTables server-side parameters
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
    $order_column_index = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 3; // Default to created_at
    $order_dir = isset($_GET['order'][0]['dir']) && in_array(strtolower($_GET['order'][0]['dir']), ['asc','desc']) ? $_GET['order'][0]['dir'] : 'desc';

    // Columns mapping
    $columns = ["id", "task_type", "status", "created_at", "updated_at", "attempts", "priority", "payload"];
    $order_column = $columns[$order_column_index] ?? 'created_at';

    // Filters
    $task_type = isset($_GET['task_type']) && $_GET['task_type'] !== '' ? trim($_GET['task_type']) : null;

    // Normalize status input (string, comma-separated, or array)
    $status = null;
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        if (is_array($_GET['status'])) {
            // Remove empty strings
            $status = array_filter($_GET['status'], fn($s) => $s !== '');
            if (in_array('all', $status, true)) {
                $status = null; // 'all' means no filtering
            }
        } else {
            // Split comma-separated string into array
            $status_list = array_map('trim', explode(',', $_GET['status']));
            if (in_array('all', $status_list, true)) {
                $status = null; // 'all' means no filtering
            } else {
                $status = $status_list;
            }
        }
    }

    // Get all queue items with filters applied
    $queue_items = get_queue_items($task_type, $status);

    // Total records before filtering
    $records_total = count(get_queue_items());

    // Total records after filtering
    $records_filtered = count($queue_items);

    // Apply sorting
    usort($queue_items, function($a, $b) use ($order_column, $order_dir) {
        if ($a[$order_column] == $b[$order_column]) return 0;
        if ($order_dir === 'asc') {
            return ($a[$order_column] < $b[$order_column]) ? -1 : 1;
        } else {
            return ($a[$order_column] > $b[$order_column]) ? -1 : 1;
        }
    });

    // Apply paging
    $queue_items_page = array_slice($queue_items, $start, $length);

    // Build response for DataTables
    $response = [
        "draw" => isset($_GET['draw']) ? (int)$_GET['draw'] : 0,
        "recordsTotal" => $records_total,
        "recordsFiltered" => $records_filtered,
        "data" => $queue_items_page,
    ];

    api_v2_json_result(200, "SUCCESS", $response);
}

function api_v2_admin_queue_promises()
{
    // Check that this is an admin user
    api_v2_check_admin();

    // Get the queue_task_id from the query
    if (!isset($_GET['queue_task_id']) || !is_numeric($_GET['queue_task_id'])) {
        api_v2_json_result(400, "Missing or invalid queue_task_id", []);
        return;
    }
    $queue_task_id = (int)$_GET['queue_task_id'];

    // Open the database connection
    $db = db_open();

    // Fetch all promises associated with this queue_task_id
    $stmt = $db->prepare("SELECT * FROM promises WHERE queue_task_id = :queue_task_id ORDER BY created_at ASC");
    $stmt->execute(['queue_task_id' => $queue_task_id]);
    $promises = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Check if any promises were found
    if (empty($promises)) {
        api_v2_json_result(404, "No promises found for queue_task_id {$queue_task_id}", []);
        return;
    }

    // Return promises as JSON array
    api_v2_json_result(200, "SUCCESS", $promises);
}

?>