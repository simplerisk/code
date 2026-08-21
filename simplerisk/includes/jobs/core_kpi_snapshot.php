<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// run_timestamped_queue_check() — no-op when loaded via the worker, which
// requires queues.php before loading job definitions.
require_once(realpath(__DIR__ . '/../queues.php'));
// record_kpi_snapshots() lives in reporting.php; its metric helpers call into
// governance.php / compliance.php (get_frameworks_count, get_framework_controls_count),
// which the worker context does not otherwise load.
require_once(realpath(__DIR__ . '/../reporting.php'));
require_once(realpath(__DIR__ . '/../governance.php'));
require_once(realpath(__DIR__ . '/../compliance.php'));

return [
    'type' => 'core_kpi_snapshot',

    /************************************************************
     * FUNCTION: task_check
     * Enqueues one KPI-snapshot task at most once per 24 hours,
     * self-healing if a cron tick was missed. One snapshot row
     * per metric per calendar day (the worker does the upsert).
     ************************************************************/
    'task_check' => function(PDO $db) {
        // Get the timestamp of the last snapshot run
        $last = get_setting('queue_timestamp_last_kpi_snapshot', false, false, db: $db);
        $now = time();

        // Snapshot at most once per 24 hours
        if (!$last || ($now - (int)$last) >= 24 * 60 * 60) {
            // Don't double-queue: a pending/in_progress task already covers this
            $existing = get_queue_items($db, 'core_kpi_snapshot', ['pending', 'in_progress']);
            if (empty($existing)) {
                $success = queue_task($db, 'core_kpi_snapshot', ['triggered_at' => time()], 50, 5, 3600);
                if ($success) {
                    write_debug_log("KPI Snapshot: Scheduled queue task.", "info");
                    return true;
                }
                write_debug_log("KPI Snapshot: Failed to queue task.", "error");
                return false;
            }
            write_debug_log("KPI Snapshot: Task already queued or running.", "debug");
            return false;
        }

        return false;
    },

    /************************************************************
     * FUNCTION: queue_check
     * Records today's KPI snapshot. Gate stamping, retries and
     * the final 'failed' status are owned by
     * run_timestamped_queue_check() and the worker.
     ************************************************************/
    'queue_check' => function(array $task, PDO $db) {
        return run_timestamped_queue_check($task, $db, 'queue_timestamp_last_kpi_snapshot', 'KPI Snapshot', function() use ($db) {
            record_kpi_snapshots($db);
            write_debug_log("KPI Snapshot: Recorded today's KPI snapshot.", "info");
            return true;
        });
    },
];

?>
