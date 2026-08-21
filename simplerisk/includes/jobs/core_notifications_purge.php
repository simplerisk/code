<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// run_timestamped_queue_check() — no-op when loaded via the worker, which
// requires queues.php before loading job definitions.
require_once(realpath(__DIR__ . '/../queues.php'));

return [
    'type' => 'core_notifications_purge',

    'task_check' => function(PDO $db) {
        $last = get_setting('queue_timestamp_last_notifications_purge', false, false, db: $db);
        $now  = time();

        if (!$last || ($now - (int)$last) >= 24 * 60 * 60) {
            $existing = get_queue_items($db, 'core_notifications_purge', ['pending', 'in_progress']);
            if (empty($existing)) {
                if (queue_task($db, 'core_notifications_purge', ['triggered_at' => $now], 30, 5, 3600)) {
                    write_debug_log("Notifications Purge: scheduled queue task.", "info");
                    return true;
                }
                write_debug_log("Notifications Purge: failed to queue task.", "error");
                return false;
            }
            return false;
        }
        return false;
    },

    // Failure handling (gate stamping, retries, 'failed' status) is owned by
    // run_timestamped_queue_check() and the worker.
    'queue_check' => function(array $task, PDO $db) {
        return run_timestamped_queue_check($task, $db, 'queue_timestamp_last_notifications_purge', 'Notifications Purge', function() use ($db) {
            write_debug_log("Notifications Purge: starting retention sweep.", "info");

            require_once(realpath(__DIR__ . '/../notifications.php'));

            $read_days  = (int)get_setting('NOTIFICATION_READ_RETENTION_DAYS', 90, false, db: $db);
            $trash_days = (int)get_setting('NOTIFICATION_TRASH_RETENTION_DAYS', 30, false, db: $db);

            $stats = purge_expired_notifications($read_days, $trash_days, $db);

            write_debug_log(
                "Notifications Purge: purged_read={$stats['purged_read']}, " .
                "purged_trash={$stats['purged_trash']}, purged_expired={$stats['purged_expired']}",
                "info"
            );
            return true;
        });
    },
];
