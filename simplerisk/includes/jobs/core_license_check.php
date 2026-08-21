<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));

return [
    'type' => 'core_license_check',

    /************************************************************
     * FUNCTION: task_check
     * Ensures only one license check task runs at a time.
     ************************************************************/
    'task_check' => function(PDO $db) {
        write_debug_log("License Check Daily: Checking for existing license check tasks.", "debug");

        // Get the timestamp of the last license check.
        // During the upgrade-Extra-runs-first window the rename of
        // queue_timestamp_last_ping → queue_timestamp_last_license_check
        // hasn't run yet. Fall back to the legacy name so an existing
        // customer's "last fired" timestamp is honored across the upgrade
        // boundary. Remove this fallback in a future release once all
        // customers are past the upgrade.
        $last_ping = get_setting('queue_timestamp_last_license_check', false, false, db: $db)
                  ?: get_setting('queue_timestamp_last_ping', false, false, db: $db);
        $now = time();
        write_debug_log("License Check Daily: Last updated at " . date("Y-m-d H:i:s", $last_ping), "debug");

        // Run at most once per 24 hours
        if (!$last_ping || ($now - $last_ping) >= 24 * 60 * 60)
        {
            // Check if one is already queued or in progress
            $existing = get_queue_items($db, 'core_license_check', ['pending', 'in_progress']);

            // If there is not an existing license check queued
            if (empty($existing))
            {
                // Queue the license check
                $queue_task_payload = [
                    'triggered_at' => time(),
                ];
                $success = queue_task($db, 'core_license_check', $queue_task_payload, 50, 5, 3600);

                // If the task was successfully queued
                if ($success)
                {
                    write_debug_log("License Check Daily: Scheduled queue task.", "info");
                    return true;
                }
                else
                {
                    write_debug_log("License Check Daily: Failed to queue task.", "error");
                    return false;
                }
            }
            else
            {
                write_debug_log("License Check Daily: License check task already running.", "debug");
                return false;
            }
        }

        return false;
    },

    /************************************************************
     * FUNCTION: queue_check
     * Runs the actual license check operation.
     ************************************************************/
    'queue_check' => function(array $task, PDO $db) {
        write_debug_log("License Check Daily: Starting license check operation...", "info");

        // Stamp the attempt time up front so task_check's 24h gate holds even
        // when the check fails (malformed result shape, exception). Stamping
        // only on success would let a failing check requeue on every worker
        // tick — a once-per-minute storm of failed queue_tasks rows. (Mirrors
        // the core_notifications_remote_feed fix; the worker's
        // handle_queue_task_failure() owns retries and the final 'failed'.)
        update_or_insert_setting('queue_timestamp_last_license_check', time(), db: $db);

        // When a check does NOT land a fresh license — a hard failure (bad shape
        // or exception) or a degraded 'unknown' transport result — don't make the
        // instance wait the full 24h before retrying. Re-arm the gate so task_check
        // re-queues after ~1h instead. The up-front stamp above still prevents a
        // same-tick storm; this only shortens the wait so entitlements aren't left
        // stale for a day after a transient licensing-service outage.
        $rearm_for_retry = function() use ($db) {
            update_or_insert_setting(
                'queue_timestamp_last_license_check',
                time() - (24 * 60 * 60) + (60 * 60),
                db: $db
            );
        };

        try {
            // Run the daily license check
            $results = license_check_daily();

            // license_check_daily() (via license_check()) returns the parsed
            // /license/check response shape: ['enforcement_level', 'entries',
            // 'mode', 'ping_processed']. There is no 'return_code' key.
            // We treat presence of 'enforcement_level' as "the helper ran
            // end-to-end" — the value may be 'unknown' on a transport failure
            // (license_check() writes a degraded cache and returns parsed-empty
            // defaults), but the task itself completed.
            if (!is_array($results) || !isset($results['enforcement_level'])) {
                // Leave the task status alone on failure — the worker owns
                // retries and the final 'failed' status.
                write_debug_log(
                    "License Check Daily: license_check_daily() returned an unexpected shape (got "
                    . gettype($results) . ").",
                    "warning"
                );
                $rearm_for_retry();
                return false;
            }

            // Mark the queue task as completed (the gate timestamp was already
            // stamped at the top of the attempt).
            queue_update_status($task['id'], 'completed', $db);
            write_debug_log(
                "License Check Daily: License check successful (enforcement_level: "
                . $results['enforcement_level'] . ").",
                "info"
            );

            // Queue admin notifications for licenses approaching/past expiry. Only
            // on a landed refresh (a degraded 'unknown' result leaves the cache
            // untouched). Isolated in its own try/catch so a notification problem
            // never fails the completed license-check task.
            if (license_refresh_landed($results)) {
                try {
                    require_once(realpath(__DIR__ . '/../licensing.php'));
                    queue_license_expiration_notifications($db);
                } catch (\Throwable $e) {
                    write_debug_log("License Check Daily: license-expiration notifications failed — " . $e->getMessage(), "warning");
                }
            } else {
                // Task completed, but the refresh didn't land (degraded 'unknown'
                // result — transport failure left the cache untouched). Retry
                // sooner than 24h so stale entitlements self-heal once the service
                // is reachable again.
                $rearm_for_retry();
            }

            return true;
        } catch (\Throwable $e) {
            // Leave the task status alone on failure — the worker owns retries
            // and the final 'failed' status. Catch \Throwable (not just Exception)
            // so an \Error/\TypeError also triggers the retry backoff below.
            write_debug_log("License Check Daily: Exception during license check — " . $e->getMessage(), "error");
            $rearm_for_retry();
            return false;
        }
    }
];

?>
