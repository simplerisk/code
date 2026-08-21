<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// run_timestamped_queue_check() — no-op when loaded via the worker, which
// requires queues.php before loading job definitions.
require_once(realpath(__DIR__ . '/../queues.php'));

return [
    'type' => 'core_notifications_remote_feed',

    /************************************************************
     * FUNCTION: task_check
     * Once per 24h, gated on the enable setting.
     ************************************************************/
    'task_check' => function(PDO $db) {
        write_debug_log("Notifications Remote Feed: Checking gate conditions.", "debug");

        if (get_setting('NOTIFICATIONS_REMOTE_FEED_ENABLED', 'true', false, db: $db) !== 'true') {
            write_debug_log("Notifications Remote Feed: disabled by setting; skipping.", "debug");
            return false;
        }

        $last = get_setting('queue_timestamp_last_notifications_remote_feed', false, false, db: $db);
        $now  = time();
        write_debug_log("Notifications Remote Feed: Last run at " . ($last ? date("Y-m-d H:i:s", (int)$last) : "never"), "debug");

        // Check at most once per 24 hours
        if (!$last || ($now - (int)$last) >= 24 * 60 * 60)
        {
            // Check if one is already queued or in progress
            $existing = get_queue_items($db, 'core_notifications_remote_feed', ['pending', 'in_progress']);

            if (empty($existing))
            {
                $queue_task_payload = [
                    'triggered_at' => $now,
                ];
                $success = queue_task($db, 'core_notifications_remote_feed', $queue_task_payload, 50, 5, 3600);

                if ($success)
                {
                    write_debug_log("Notifications Remote Feed: Scheduled queue task.", "info");
                    return true;
                }
                else
                {
                    write_debug_log("Notifications Remote Feed: Failed to queue task.", "error");
                    return false;
                }
            }
            else
            {
                write_debug_log("Notifications Remote Feed: Task already queued or running.", "debug");
                return false;
            }
        }

        return false;
    },

    /************************************************************
     * FUNCTION: queue_check
     * Fetch the feed and ingest items.
     * Failure handling (gate stamping, retries, 'failed' status)
     * is owned by run_timestamped_queue_check() and the worker:
     * the gate timestamp advances on every attempt so a failing
     * feed cannot requeue on every worker tick.
     ************************************************************/
    'queue_check' => function(array $task, PDO $db) {
        return run_timestamped_queue_check($task, $db, 'queue_timestamp_last_notifications_remote_feed', 'Notifications Remote Feed', function() use ($db) {
            write_debug_log("Notifications Remote Feed: Fetching feed...", "info");

            $url = get_setting('NOTIFICATIONS_REMOTE_FEED_URL', '', false, db: $db);
            if (empty($url)) {
                write_debug_log("Notifications Remote Feed: NOTIFICATIONS_REMOTE_FEED_URL is not set.", "notice");
                return false;
            }

            require_once(realpath(__DIR__ . '/../notifications.php'));

            $last_modified = get_setting('NOTIFICATIONS_REMOTE_FEED_LAST_MODIFIED', '', false, db: $db) ?: null;

            $result = fetch_remote_feed_json($url, $last_modified);

            if ($result === null) {
                write_debug_log("Notifications Remote Feed: Failed to fetch or parse feed.", "error");
                return false;
            }

            if ($result === 'not_modified') {
                write_debug_log("Notifications Remote Feed: No changes (304).", "info");
                return true;
            }

            // $result is ['payload' => array, 'last_modified' => string|null]
            $payload       = $result['payload'];
            $new_last_modified = $result['last_modified'];

            $inserted = 0;
            $skipped  = 0;
            $rejected = 0;

            foreach (($payload['items'] ?? []) as $item) {
                $item_result = ingest_remote_feed_item($item, $db);
                if ($item_result === 'inserted') {
                    $inserted++;
                } elseif ($item_result === 'duplicate') {
                    $skipped++;
                } else {
                    $rejected++;
                }
            }

            // Persist the Last-Modified header so the next run can send
            // If-Modified-Since and benefit from the 304 short-circuit.
            if ($new_last_modified !== null && $new_last_modified !== '') {
                update_or_insert_setting('NOTIFICATIONS_REMOTE_FEED_LAST_MODIFIED', $new_last_modified, db: $db);
            }

            write_debug_log("Notifications Remote Feed: inserted={$inserted}, skipped={$skipped}, rejected={$rejected}", "info");
            return true;
        });
    },
];
