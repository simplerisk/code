<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. You can obtain one at http://mozilla.org/MPL/2.0/. */

declare(strict_types=1);

if (php_sapi_name() !== "cli") {
    exit("This script must be run via the command line.\n");
}

require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/mail.php'));
require_once(realpath(__DIR__ . '/../includes/queues.php'));
require_once(realpath(__DIR__ . '/../includes/promises.php'));
require_once(realpath(__DIR__ . '/../includes/workers.php'));
require_once(realpath(__DIR__ . '/../includes/queue_config.php'));
require_once(realpath(__DIR__ . '/../includes/files.php'));
require_once(realpath(__DIR__ . '/../includes/Components/DocumentTextHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/CsvHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/PdfHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/SpreadsheetHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/WordHandler.php'));

// === CONFIGURATION ===
// Tuning comes from config.php constants via queue_config() (built-in defaults
// when absent; see includes/queue_config.php and config.sample.php). Retry
// limits and backoff are resolved per failure in handle_queue_task_failure().
// Several queue workers may run at once (QUEUE_WORKER_COUNT, default 1). Each
// cron run fills at most one free slot (acquire_worker_slot()); slot 1 keeps
// this base name, slot k > 1 is "cron_queue_worker_k". $workerName is set to
// the slot taken below and used for heartbeats, metrics and the lock release.
$workerBase = 'cron_queue_worker';
$workerCount = queue_config('QUEUE_WORKER_COUNT');
$stalePidThreshold = queue_config('WORKER_STALE_LOCK_SECONDS');
$loopDelaySeconds = queue_config('QUEUE_WORKER_LOOP_DELAY_SECONDS');
$maxRuntimeMinutes = queue_config('QUEUE_WORKER_MAX_RUNTIME_MINUTES');
$maxIdleMinutes = queue_config('QUEUE_WORKER_MAX_IDLE_MINUTES');
$memoryLimitBytes = queue_config('QUEUE_WORKER_MEMORY_SOFT_LIMIT_MB') * 1024 * 1024;
$stuckThresholdMinutes = queue_config('QUEUE_STUCK_TASK_MINUTES');

$startTime = time();
$lastWorkTime = time();

// === SESSION IDENTITY FOR AUDIT LOGS ===
// Functions that write audit log entries read $_SESSION['user'] and $_SESSION['uid'].
// There is no HTTP session in CLI context, so seed these with a system identity.
$_SESSION['user'] = 'System User';
$_SESSION['uid']  = -1;

// === SYSTEM SETTINGS ===
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', queue_config('QUEUE_WORKER_PHP_MEMORY_LIMIT_MB') . 'M');
ob_implicit_flush(true);

write_debug_log("Queue worker started (PID " . getmypid() . ")", "info");

// === CHECK FOR ZOMBIE WORKERS ===
foreach (worker_slot_names($workerBase, $workerCount) as $slotName) {
    foreach (list_zombie_workers($slotName, $stalePidThreshold) as $zombie) {
        write_debug_log(
            "Zombie worker detected ({$slotName}): PID {$zombie['pid']} on host {$zombie['host']}, last heartbeat {$zombie['last_heartbeat']}",
            "warning"
        );
    }
}

// === ACQUIRE A WORKER SLOT ===
$workerName = acquire_worker_slot($workerBase, $workerCount, $stalePidThreshold);
if ($workerName === null) {
    // 'debug': with cron.php every configured worker's entry runs each
    // minute, so this is the normal outcome of most runs.
    write_debug_log("{$workerBase}: all {$workerCount} worker slot(s) are taken, exiting.", "debug");
    exit(0);
}

// Report config.php tuning once per worker start: 'warning' for a value that
// was ignored or clamped (the edit is not doing what the administrator meant),
// 'info' listing every value that differs from the built-in default. After the
// lock, so the cron runs that find a worker already running (and exit) stay
// silent instead of repeating these lines every few minutes.
foreach (queue_config_startup_log_lines() as [$message, $level]) {
    write_debug_log("{$workerName}: {$message}", $level);
}

// === REGISTER SIGNAL HANDLERS ===
register_worker_signal_handlers($workerName);

// === SHUTDOWN FUNCTION ===
register_shutdown_function(function () use ($workerName) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        write_debug_log("Fatal worker error: {$error['message']} in {$error['file']}:{$error['line']}", "error");
        record_worker_restart($workerName, 'fatal_error: ' . $error['message']);
    }
    release_worker_lock($workerName);
});

// === OPEN DB CONNECTION ===
$db = db_open();
if (!$db) {
    write_debug_log("Failed to open database connection, exiting.", "error");
    exit(1);
}
// Session wait_timeout: bounds how long a connection left behind by a dead
// host keeps its claims (worker_session_wait_timeout()).
worker_prepare_connection($db);

// === MAIN WORKER LOOP ===
while (true) {
    // Dispatch signals
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    $now = time();

    // === SHUTDOWN & RELOAD CHECKS ===
    if (worker_should_shutdown()) {
        write_debug_log("Shutdown requested, exiting main loop...", "info");
        break;
    }

    // SIGHUP means "recycle with fresh code". An in-process reload is
    // impossible (require_once is a no-op once a file is loaded), so exit
    // gracefully and let cron respawn the worker with the new code.
    if (worker_should_reload()) {
        record_worker_restart($workerName, 'reload_signal');
        break;
    }

    // A deploy/upgrade requested a restart after this worker started, so
    // the code on disk is newer than the code in memory. Exit gracefully —
    // the current task already finished — and let cron respawn with fresh
    // code. Workers started after the request keep running.
    if (worker_restart_requested_since($startTime, worker_restart_flag_timestamp())) {
        record_worker_restart($workerName, 'restart_requested');
        break;
    }

    // config.php is read once, at start. When a system administrator edits it
    // (for example the asset discovery allowlist or a queue tuning constant),
    // exit between tasks so cron respawns the worker with the new
    // configuration. Fail-safe: a stat failure or a future mtime never triggers
    // this, and the respawned worker starts after the edit, so it does not loop
    // (worker_config_changed_since()).
    if (worker_config_file_changed_since($startTime, $now)) {
        record_worker_restart($workerName, 'config_changed');
        break;
    }

    // === METRICS: memory usage ===
    worker_metric_set($workerName, 'memory_bytes', memory_get_usage(true));
    worker_metric_set($workerName, 'peak_memory_bytes', memory_get_peak_usage(true));

    // === RESOURCE / TIME CHECKS ===
    if (($now - $startTime) > ($maxRuntimeMinutes * 60)) {
        record_worker_restart($workerName, 'max_runtime_exceeded');
        break;
    }

    if (($now - $lastWorkTime) > ($maxIdleMinutes * 60)) {
        record_worker_restart($workerName, 'max_idle_exceeded');
        break;
    }

    if (memory_get_usage(true) > $memoryLimitBytes) {
        record_worker_restart($workerName, 'memory_limit_exceeded');
        break;
    }

    // === ENSURE DB CONNECTION ===
    try {
        $db->query("SELECT 1");
    } catch (\Throwable $t) {
        // A lost connection also lost every claim it held: the server dropped
        // them. Reconnect for real (db_close() alone keeps the dead shared
        // connection) and set the new connection up like the first one.
        write_debug_log("Reconnecting to DB after connection loss...", "warning");
        // Returns a working connection or exits: db_open() renders the outage
        // page and exits when the database cannot be reached, and cron starts
        // a fresh worker later.
        $db = worker_reconnect_db();
        worker_prepare_connection($db);
    }

    // === RECOVER STUCK TASKS ===
    // Only tasks with NO live promise chain are recovered — a task whose chain
    // is still being resolved is legitimately 'in_progress' and re-running its
    // queue_check would re-create the whole chain (the re-chaining runaway).
    // See recover_stuck_queue_tasks() in queues.php for the full rationale and
    // the liveness predicate (kept in sync with promise_chain_exists()).
    // Bounded: a task recovered QUEUE_RECOVERY_MAX_ATTEMPTS times is marked
    // failed instead, with its terminal hook and admin notification.
    try {
        $recovery = worker_recover_stuck_tasks($db, $stuckThresholdMinutes);
        $recovered = $recovery['recovered'];
        if ($recovered > 0) {
            // 'notice': reclaiming a stale/abandoned task (in_progress past the
            // threshold with no live chain) is an infrequent, operator-relevant
            // event — the "stale lock killed" analog — not a routine info event.
            write_debug_log("Recovered {$recovered} stuck tasks (no live promise chain).", "notice");
        }
        if (!empty($recovery['failed'])) {
            worker_metric_inc($workerName, 'failed_queue_tasks', count($recovery['failed']));
        }
    } catch (\Throwable $t) {
        write_debug_log("Error recovering stuck tasks: " . $t->getMessage(), "error");
    }

    // === CLAIM AND RUN THE NEXT TASK ===
    // worker_run_next_task() claims the highest-priority runnable task (a
    // MySQL named lock per task, so no two workers ever run the same one;
    // tasks still waiting out a retry back-off are skipped, SR-2307), runs its
    // handler, records the outcome and releases the claim. Jobs marked
    // 'concurrency' => 'serial' also run one at a time across all workers.
    try {
        $run = worker_run_next_task($db, $now, $workerName);
    } catch (\Throwable $t) {
        write_debug_log("Error picking or running a queue task: " . $t->getMessage(), "error");
        if (worker_is_connection_lost($t)) {
            // The connection (and with it the task's claim) is gone; the
            // task's run-open marker is still set, so its next claim counts
            // it as a recovery instead of simply running it again.
            $db = worker_reconnect_db();
            worker_prepare_connection($db);
        }
        update_worker_heartbeat($workerName);
        worker_interruptible_sleep($loopDelaySeconds);
        continue;
    }

    // Nothing to run, or a task with no job definition: keep the heartbeat
    // fresh (an idle worker is alive; the Queue Monitor and the other hosts'
    // workers judge that by the heartbeat) and wait.
    if ($run === null || $run['outcome'] === 'no_job_definition') {
        update_worker_heartbeat($workerName);
        worker_interruptible_sleep($loopDelaySeconds);
        continue;
    }

    $lastWorkTime = time();

    // === WORKER TICK ===
    worker_tick($workerName, $db);

    worker_interruptible_sleep($loopDelaySeconds);
}

// === CLEANUP ===
db_close($db);
write_debug_log("Queue worker exited.", "info");
?>