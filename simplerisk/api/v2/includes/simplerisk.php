<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/queues.php'));
require_once(realpath(__DIR__ . '/../../../includes/promises.php'));
require_once(realpath(__DIR__ . '/../../../includes/upgrade.php'));
// apply_database_release() and is_newest_known_release() live here. Reachable
// transitively through upgrade.php today, but declared directly per the
// reachability rule: an include reorder there would otherwise turn the calls in
// api_v2_admin_upgrade_db() into a fatal.
require_once(realpath(__DIR__ . '/../../../includes/upgrade/common.php'));

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
    // Check that this is an admin user
    api_v2_check_admin();

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
            write_debug_log($result['message'], 'info');

            // Delete the message
            $stmt = $db->prepare("DELETE FROM `debug_log` WHERE id=:id");
            $stmt->bindParam(":id", $result['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // Commit the transaction
        if ($db->inTransaction()) {
            $db->commit();
        }
    } catch (Exception $e) {
        // If an error occurs, rollback the transaction
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        write_debug_log("Error in api_v2_admin_write_debug_log: " . $e->getMessage(), 'error');
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

    // Get version from POST parameters
    $version = $_POST['version'] ?? null;

    // ── No version: bring the database fully up to date ─────────────────────
    //
    // This walks the whole chain from wherever the database actually is, and
    // finishes by running the in-flight migration for the release currently in
    // development if there is one -- which is what makes this endpoint usable
    // for testing a release that has no version number yet.
    //
    // It used to resolve to end($releases) and apply that single function. That
    // was wrong in both directions: on a database that was behind, it ran the
    // NEWEST release's migration and silently skipped every one in between,
    // because it dispatched on the version the caller named rather than on the
    // version the database was on.
    if (!$version)
    {
        if (empty($releases))
        {
            api_v2_json_result(500, "No releases available", null);
            return;
        }

        $db = db_open();
        // true: this endpoint is how an in-development release is tested, so it
        // wants the in-flight migration. The management extra's /upgrade leaves
        // it off, so an already-current hosted instance stays a no-op.
        $result = run_database_upgrade_structured($db, null, null, true);
        db_close($db);

        // A refusal is not a failure. Same condition, same status, as the
        // named-version branch below.
        if (!empty($result['refused'])) {
            api_v2_json_result(
                409,
                $GLOBALS['lang']['UpgradeAlreadyRunning'] ?? "An upgrade is already running on this instance.",
                $result
            );
            return;
        }

        $status_code    = $result['success'] ? 200 : 500;
        $status_message = $result['success']
            ? "Upgrade successful"
            : "The upgrade did not complete. See the releases detail and the server log";

        api_v2_json_result($status_code, $status_message, $result);
        return;
    }

    // ── An explicit version: apply exactly that one release ─────────────────
    //
    // Deliberately a single hop, for targeting one migration during
    // development. It dispatches on the version NAMED, which is only the same
    // thing as the database's own version when the caller says so -- omit the
    // parameter to walk the chain instead.
    if ($version && preg_match('/^\d{8}-\d{3}$/', $version))
    {
        // If the version is not in the releases array, return an error
        if (!in_array($version, $releases, true))
        {
            // Create the result
            $status_code = 400;
            $status_message = "Invalid version";
            $data = null;
        }
        else
        {
            // Behind the same lock as the no-version branch.
            //
            // A single named hop is still an entire upgrade_from_* body of DDL,
            // and finalize_database_upgrade() below rewrites every table's
            // engine and charset. Running either underneath another channel's
            // chain is the interleaved-ALTER hazard with_upgrade_lock() exists
            // to prevent -- just with a smaller blast radius, which is not a
            // reason to leave it unserialised.
            $upgrade_refused = new stdClass();

            $outcome = with_upgrade_lock(function () use ($version, $releases) {
                $status_code = 500;
                $status_message = "";
                $data = null;

                // Open the database connection
                $db = db_open();

                // The DB user must hold the privileges the migrations need. This
                // endpoint used to skip the check the other channels all make, so a
                // migration could fail partway through on a missing GRANT and be
                // reported as a successful upgrade.
                if (!check_grants($db))
                {
                    db_close($db);

                    $status_code = 500;
                    $status_message = "The database user is missing privileges required to run the upgrade";
                    $data = null;
                }
                else
                {
                    // Apply the release through the step every upgrade channel
                    // shares. This endpoint previously called the release function
                    // directly, which meant it alone had no captured exception and
                    // no check that the version actually moved -- a migration that
                    // threw, or that did nothing, still answered "Upgrade
                    // successful". See apply_database_release().
                    $step = apply_database_release($db, $version);

                    db_close($db);

                    if (!$step['ran'])
                    {
                        $status_code = 400;
                        $status_message = "Upgrade function not found";
                        $data = null;
                    }
                    elseif ($step['error'] !== '')
                    {
                        // The detail goes to the log, not to the caller: exception
                        // text carries table and column names and file paths, and
                        // this endpoint answers anyone holding an admin key.
                        write_debug_log("api_v2_admin_upgrade_db: migration error on release {$version}: " . $step['error'], 'error');

                        $status_code = 500;
                        $status_message = "The upgrade encountered an error. See the server log for details";
                        $data = null;
                    }
                    elseif (!$step['advanced'])
                    {
                        // Not advancing is EXPECTED for the newest known release
                        // mid-cycle: that function targets a placeholder version
                        // which update_database_version() refuses to write. On any
                        // earlier release it means the migration did nothing, and
                        // reporting success would tell hosted automation the
                        // instance moved when it did not.
                        if (is_newest_known_release($version, $releases))
                        {
                            $status_code = 200;
                            $status_message = "Changes applied; the database version stays at {$step['to']} because this upgrade targets a release that has not been cut yet";
                            $data = null;
                        }
                        else
                        {
                            write_debug_log("api_v2_admin_upgrade_db: release {$version} did not advance db_version from {$step['from']}.", 'error');

                            $status_code = 500;
                            $status_message = "The upgrade did not advance the database version";
                            $data = null;
                        }
                    }
                    else
                    {
                        // Post-chain conversions, the same ones every other upgrade
                        // channel runs. Without them an instance upgraded through
                        // this endpoint finished on utf8mb3 while the upgrade page,
                        // the one-click flow and the Upgrade Extra all finished on
                        // utf8mb4 -- a difference in column TYPE, not just
                        // collation, since CONVERT TO CHARACTER SET widens TEXT to
                        // MEDIUMTEXT. Idempotent, and opens its own connection.
                        finalize_database_upgrade();

                        // And the standing integrity checks, once the hop has
                        // actually landed the database on the application
                        // version. finalize_database_upgrade() is only the two
                        // conversions; these are the checks documented as
                        // running on EVERY upgrade, and every other channel runs
                        // both. A named single hop is a development-targeting
                        // mode, so the exposure was small -- but the point of
                        // this consolidation is that no caller can introduce a
                        // difference by forgetting a line, and this one had.
                        if (current_version("db") == current_version("app")) {
                            $checks_db = db_open();
                            run_upgrade_integrity_checks($checks_db);
                            db_close($checks_db);
                        }

                        $status_code = 200;
                        $status_message = "Upgrade successful";
                        $data = null;
                    }
                }
        
                return array($status_code, $status_message, $data);
            }, 0, $upgrade_refused);

            if ($outcome === $upgrade_refused) {
                $status_code = 409;
                $status_message = $GLOBALS['lang']['UpgradeAlreadyRunning']
                    ?? "An upgrade is already running on this instance.";
                $data = null;
            } else {
                list($status_code, $status_message, $data) = $outcome;
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

        write_debug_log("Updating control mapping suggestions for document id: " . $document_id, 'debug');

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
    /** @var array[] $get_order */
    $get_order = $_GET['order'] ?? [];
    $order_column_index = isset($get_order[0]['column']) ? (int)$get_order[0]['column'] : 3; // Default to created_at
    $order_dir = isset($get_order[0]['dir']) && in_array(strtolower($get_order[0]['dir']), ['asc','desc']) ? $get_order[0]['dir'] : 'desc';

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

    // Open the database connection
    $db = db_open();

    // Get all queue items with filters applied
    $queue_items = get_queue_items($db, $task_type, $status);

    // Close the database connection
    db_close($db);

    // Total records before filtering
    $records_total = count(get_queue_items($db));

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
        api_v2_json_result(200, "Missing or invalid queue_task_id", []);
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
        api_v2_json_result(200, "No promises found for queue_task_id {$queue_task_id}", []);
        return;
    }

    // Return promises as JSON array
    api_v2_json_result(200, "SUCCESS", $promises);
}

?>