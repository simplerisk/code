<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

return [
    'type' => 'core_server_ping',

    /************************************************************
     * FUNCTION: task_check
     * Ensures only one ping task runs at a time.
     ************************************************************/
    'task_check' => function(PDO $db) {
        write_debug_log("Ping Server: Checking for existing ping tasks.", "debug");

        // Get the timestamp of the last ping check
        $last_ping = get_setting('queue_timestamp_last_ping', false, false, db: $db);
        $now = time();
        write_debug_log("Ping Server: Last updated at " . date("Y-m-d H:i:s", $last_ping), "debug");

        // Ping at most once per 24 hours
        if (!$last_ping || ($now - $last_ping) >= 24 * 60 * 60)
        {
            // Check if one is already queued or in progress
            $existing = get_queue_items($db, 'core_server_ping', ['pending', 'in_progress']);

            // If there is not an existing ping queued
            if (empty($existing))
            {
                // Queue the ping
                $queue_task_payload = [
                    'triggered_at' => time(),
                ];
                $success = queue_task($db, 'core_server_ping', $queue_task_payload, 50, 5, 3600);

                // If the task was successfully queued
                if ($success)
                {
                    write_debug_log("Ping Server: Scheduled queue task.", "info");
                    return true;
                }
                else
                {
                    write_debug_log("Ping Server: Failed to queue task.", "error");
                    return false;
                }
            }
            else
            {
                write_debug_log("Ping Server: Ping task already running.", "info");
                return false;
            }
        }

        return false;
    },

    /************************************************************
     * FUNCTION: queue_check
     * Runs the actual ping operation.
     ************************************************************/
    'queue_check' => function(array $task, PDO $db) {
        write_debug_log("Ping Server: Starting ping operation...", "info");

        try {
            // Ping the server
            $results = ping_server();
            $return_code = $results['return_code'];
            $response = $results['response'];

            if ($return_code === 200) {
                // Mark the queue task as completed
                queue_update_status($task['id'], 'completed', $db);
                $now = time();
                update_or_insert_setting('queue_timestamp_last_ping', $now, db: $db);
                write_debug_log("Ping Server: Ping successful.", "info");
                return true;
            } else {
                queue_update_status($task['id'], 'failed', $db);
                write_debug_log("Ping Server: Ping failed.  Return code {$return_code}. - {$response}", "warning");
                return false;
            }
        } catch (Exception $e) {
            queue_update_status($task['id'], 'failed', $db);
            write_debug_log("Ping Server: Exception during ping — " . $e->getMessage(), "error");
            return false;
        }
    }
];

?>