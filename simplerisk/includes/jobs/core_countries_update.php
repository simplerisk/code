<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Job definition for updating the countries cache from the external API.
 * Placed in includes/jobs/core_countries_update.php
 */

// Reachability: these are all loaded by the cron worker, but a job file that
// calls them must declare its own require_once (CLAUDE.md). functions.php
// gives write_debug_log(), update_setting(), and fetchCountriesFromAPI();
// queues.php gives queue_update_status().
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));

return [
    'type' => 'core_countries_update',

    // $fetcher is an injectable seam for tests only -- production callers never
    // pass a third argument, so this always resolves to the real API call.
    'queue_check' => function(array $task, PDO $db, ?callable $fetcher = null) {
        $fetcher ??= 'fetchCountriesFromAPI';

        write_debug_log("QUEUE_CHECK: Updating countries cache (task #{$task['id']})", "info");

        queue_update_status($task['id'], 'in_progress', $db);

        try {
            // Fetch latest countries
            $countries = $fetcher();

            if (empty($countries)) {
                write_debug_log("QUEUE_CHECK: Failed to fetch countries for task #{$task['id']}", "error");
                // Leave the task status alone -- the worker's
                // handle_queue_task_failure() owns bounded backoff retries and
                // the final 'failed' transition. Pre-marking 'failed' here
                // would dead-end the task before the retry machinery (which
                // only re-fetches 'pending' rows) ever sees it.
                return false;
            }

            // Save to settings
            update_setting('countries_cache', json_encode([
                'fetched_at' => time(),
                'countries' => $countries
            ]), db: $db);

            queue_update_status($task['id'], 'completed', $db);
            write_debug_log("QUEUE_CHECK: Countries cache successfully updated for task #{$task['id']}", "info");
            return true;

        } catch (\Throwable $e) {
            // \Throwable, not \Exception -- PHP's own \Error hierarchy (e.g. a
            // \TypeError from a misbehaving $fetcher) does not extend
            // \Exception. A narrower catch would let that escape uncaught
            // instead of hitting the return false below, matching the
            // catch(\Throwable) the worker itself uses one layer up.
            write_debug_log("QUEUE_CHECK: Exception updating countries cache for task #{$task['id']}: " . $e->getMessage(), "error");
            // Same rationale as the empty-fetch branch above: return false and
            // let the worker own retries/backoff instead of self-marking 'failed'.
            return false;
        }
    },

    // No task_check function needed; tasks are queued manually
];

?>