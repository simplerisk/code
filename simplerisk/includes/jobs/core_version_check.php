<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

return [
    'type' => 'core_version_check',

    /************************************************************
     * FUNCTION: task_check
     * Ensures only one version check task runs at a time.
     ************************************************************/
    'task_check' => function(PDO $db) {
        write_debug_log("Version Check: Checking for existing version check tasks.", "debug");

        // Get the timestamp of the last version check
        $last_check = get_setting('queue_timestamp_last_version_check', false, false, db: $db);
        $now = time();
        write_debug_log("Version Check: Last checked at " . ($last_check ? date("Y-m-d H:i:s", $last_check) : "never"), "debug");

        // Check at most once per 24 hours
        if (!$last_check || ($now - $last_check) >= 24 * 60 * 60)
        {
            // Check if one is already queued or in progress
            $existing = get_queue_items($db, 'core_version_check', ['pending', 'in_progress']);

            // If there is not an existing check queued
            if (empty($existing))
            {
                $queue_task_payload = [
                    'triggered_at' => time(),
                ];
                $success = queue_task($db, 'core_version_check', $queue_task_payload, 50, 5, 3600);

                if ($success)
                {
                    write_debug_log("Version Check: Scheduled queue task.", "info");
                    return true;
                }
                else
                {
                    write_debug_log("Version Check: Failed to queue task.", "error");
                    return false;
                }
            }
            else
            {
                write_debug_log("Version Check: Version check task already queued or running.", "debug");
                return false;
            }
        }

        return false;
    },

    /************************************************************
     * FUNCTION: queue_check
     * Fetches the latest version data and caches it in the DB.
     ************************************************************/
    'queue_check' => function(array $task, PDO $db) {
        write_debug_log("Version Check: Fetching latest version data...", "info");

        try {
            // Force a fresh network fetch by bypassing all caches
            $latest_versions = latest_versions(true);

            if ($latest_versions !== 0 && !empty($latest_versions))
            {
                update_or_insert_setting('latest_version_data', json_encode($latest_versions), db: $db);
                update_or_insert_setting('queue_timestamp_last_version_check', time(), db: $db);

                queue_update_status($task['id'], 'completed', $db);
                write_debug_log("Version Check: Successfully updated latest version data.", "info");
                return true;
            }
            else
            {
                queue_update_status($task['id'], 'failed', $db);
                write_debug_log("Version Check: Failed to fetch latest version data from remote.", "error");
                return false;
            }
        } catch (Exception $e) {
            queue_update_status($task['id'], 'failed', $db);
            write_debug_log("Version Check: Exception during version check — " . $e->getMessage(), "error");
            return false;
        }
    }
];

?>
