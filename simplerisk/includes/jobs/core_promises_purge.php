<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// run_timestamped_queue_check() lives in queues.php; purge_terminal_promises()
// lives in promises.php. Both are no-ops when loaded via the worker (which
// requires them before loading job definitions), but declared here so the job
// is reachable when its closures are invoked directly (e.g. from tests).
require_once(realpath(__DIR__ . '/../queues.php'));
require_once(realpath(__DIR__ . '/../promises.php'));

return [
    'type' => 'core_promises_purge',

    'task_check' => function(PDO $db) {
        $last = get_setting('queue_timestamp_last_promises_purge', false, false, db: $db);
        $now  = time();

        if (!$last || ($now - (int)$last) >= 24 * 60 * 60) {
            $existing = get_queue_items($db, 'core_promises_purge', ['pending', 'in_progress']);
            if (empty($existing)) {
                if (queue_task($db, 'core_promises_purge', ['triggered_at' => $now], 25, 5, 3600)) {
                    write_debug_log("Promises Purge: scheduled queue task.", "info");
                    return true;
                }
                write_debug_log("Promises Purge: failed to queue task.", "error");
                return false;
            }
            return false;
        }
        return false;
    },

    // Failure handling (gate stamping, retries, 'failed' status) is owned by
    // run_timestamped_queue_check() and the worker.
    'queue_check' => function(array $task, PDO $db) {
        return run_timestamped_queue_check($task, $db, 'queue_timestamp_last_promises_purge', 'Promises Purge', function() use ($db) {
            $retention_days = (int)get_setting('PROMISES_PURGE_RETENTION_DAYS', 7, false, db: $db);
            $batch_limit    = (int)get_setting('PROMISES_PURGE_BATCH_LIMIT', 50000, false, db: $db);

            $deleted = purge_terminal_promises($db, $retention_days, $batch_limit);

            write_debug_log("Promises Purge: deleted {$deleted} terminal promise row(s) older than {$retention_days} day(s).", "info");
            return true;
        });
    },
];
