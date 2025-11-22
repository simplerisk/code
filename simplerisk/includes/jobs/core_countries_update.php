<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Job definition for updating the countries cache from the external API.
 * Placed in includes/jobs/core_countries_update.php
 */

return [
    'type' => 'core_countries_update',

    'queue_check' => function(array $task) {
        write_debug_log("QUEUE_CHECK: Updating countries cache (task #{$task['id']})", "info");

        queue_update_status($task['id'], 'in_progress');

        try {
            // Fetch latest countries
            $countries = fetchCountriesFromAPI(); // Use your existing API logic

            if (empty($countries)) {
                write_debug_log("QUEUE_CHECK: Failed to fetch countries for task #{$task['id']}", "error");
                queue_update_status($task['id'], 'failed');
                return false;
            }

            // Save to settings
            update_setting('countries_cache', json_encode([
                'fetched_at' => time(),
                'countries' => $countries
            ]));

            queue_update_status($task['id'], 'completed');
            write_debug_log("QUEUE_CHECK: Countries cache successfully updated for task #{$task['id']}", "info");
            return true;

        } catch (Exception $e) {
            write_debug_log("QUEUE_CHECK: Exception updating countries cache for task #{$task['id']}: " . $e->getMessage(), "error");
            queue_update_status($task['id'], 'failed');
            return false;
        }
    },

    // No task_check function needed; tasks are queued manually
];

?>