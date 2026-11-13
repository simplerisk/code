<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Retention janitor for finished queue_tasks (SR-2408). Declares its own
// requires so it is reachable when its closures are invoked directly (tests);
// queue_monitor.php supplies the QUEUE_RETENTION_* names and defaults and is
// not otherwise loaded by the worker's job loader.
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));
require_once(realpath(__DIR__ . '/../queue_monitor.php'));

return [
    'type' => 'core_queue_purge',
    // One task of this type at a time across all queue workers (periodic table maintenance).
    'concurrency' => 'serial',

    'task_check' => function(PDO $db) {
        $last = get_setting('queue_timestamp_last_queue_purge', false, false, db: $db);
        $now  = time();

        if (!$last || ($now - (int)$last) >= 24 * 60 * 60) {
            $existing = get_queue_items($db, 'core_queue_purge', ['pending', 'in_progress']);
            if (empty($existing)) {
                if (queue_task($db, 'core_queue_purge', ['triggered_at' => $now], 25, 5, 3600)) {
                    write_debug_log("Queue Purge: scheduled queue task.", "info");
                    return true;
                }
                write_debug_log("Queue Purge: failed to queue task.", "error");
                return false;
            }
            return false;
        }
        return false;
    },

    // Failure handling (gate stamping, retries, 'failed' status) is owned by
    // run_timestamped_queue_check() and the worker.
    'queue_check' => function(array $task, PDO $db) {
        return run_timestamped_queue_check($task, $db, 'queue_timestamp_last_queue_purge', 'Queue Purge', function() use ($db) {
            // Junk (non-numeric) setting values fall back to the default; the
            // result is clamped to 0..QUEUE_RETENTION_MAX_DAYS (0 = keep forever).
            $read_days = function(string $setting, int $default) use ($db): int {
                $raw = get_setting($setting, $default, false, db: $db);
                $days = (is_int($raw) || (is_string($raw) && preg_match('/^\s*-?\d+\s*$/', $raw))) ? (int)$raw : $default;
                return max(0, min(QUEUE_RETENTION_MAX_DAYS, $days));
            };

            $completed_days = $read_days(QUEUE_RETENTION_SETTING_COMPLETED, QUEUE_RETENTION_DEFAULT_COMPLETED_DAYS);
            $failed_days    = $read_days(QUEUE_RETENTION_SETTING_FAILED, QUEUE_RETENTION_DEFAULT_FAILED_DAYS);

            $deleted = purge_finished_queue_tasks($db, $completed_days, $failed_days);

            write_debug_log("Queue Purge: deleted {$deleted} finished queue task(s) (completed/canceled kept {$completed_days}d, failed kept {$failed_days}d).", "info");
            return true;
        });
    },
];
