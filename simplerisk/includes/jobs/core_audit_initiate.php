<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../queues.php'));
require_once(realpath(__DIR__ . '/../compliance.php'));

return [
    'type' => 'core_audit_initiate',

    'task_check' => function(PDO $db) {
        write_debug_log("Audit Initiate: checking gate conditions.", "debug");

        $last = get_setting('queue_timestamp_last_audit_initiate', false, false, db: $db);
        $now  = time();

        // Hourly gate (mirrors the retired cron_audit schedule).
        if (!$last || ($now - (int)$last) >= 60 * 60) {
            $existing = get_queue_items($db, 'core_audit_initiate', ['pending', 'in_progress']);
            if (empty($existing)) {
                $success = queue_task($db, 'core_audit_initiate', ['triggered_at' => $now], 50, 5, 3600);
                if ($success) {
                    write_debug_log("Audit Initiate: scheduled queue task.", "info");
                    return true;
                }
                write_debug_log("Audit Initiate: failed to queue task.", "error");
                return false;
            }
            write_debug_log("Audit Initiate: task already queued or running.", "debug");
            return false;
        }
        return false;
    },

    'queue_check' => function(array $task, PDO $db) {
        return run_timestamped_queue_check($task, $db, 'queue_timestamp_last_audit_initiate', 'Audit Initiate', function() {
            write_debug_log("Audit Initiate: running auto-initiation.", "info");
            run_auto_initiate_test_cron();
            return true;
        });
    },
];
