<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Background asset discovery (asset management redesign, spec section 7).
 *
 * On-demand only: POST /api/v2/assets/discovery-runs enqueues one task per
 * run ({"run_id": N}); task_check never schedules anything, so there is no
 * cadence gate to stamp. queue_check scans one time slice of the run and
 * returns a bool -- it never sets its own task status (writing-queue-jobs
 * Rule 2); the worker's handle_queue_task_failure() owns retries, and
 * on_terminal_failure marks the RUN failed once they are exhausted. See
 * includes/assets_discovery.php for the scan, resume and idempotence design.
 */

// Reachability: the cron worker loads these, but a job file declares its own
// require_once for what it calls (CLAUDE.md). queues.php is not auto-loaded.
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));
require_once(realpath(__DIR__ . '/../assets.php'));
require_once(realpath(__DIR__ . '/../assets_discovery.php'));

return [
    'type' => 'core_asset_discovery',

    // Runs are enqueued by the API when a user starts one; never here.
    'task_check' => function (PDO $db) {
        return false;
    },

    'queue_check' => function (array $task, PDO $db) {
        return assets_discovery_queue_check($task, $db);
    },

    'on_terminal_failure' => function (array $task, PDO $db, string $errorMessage) {
        assets_discovery_on_terminal_failure($task, $db, $errorMessage);
    },
];

?>
