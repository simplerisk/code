<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. You can obtain one at http://mozilla.org/MPL/2.0/. */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit("This script must be run from the command line.\n");
}

require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/promises.php'));
require_once(realpath(__DIR__ . '/../includes/workers.php'));
require_once(realpath(__DIR__ . '/../includes/queue_config.php'));
require_once(realpath(__DIR__ . '/../includes/files.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
require_once(realpath(__DIR__ . '/../includes/tf_idf_enrichment.php'));
require_once(realpath(__DIR__ . '/../includes/Components/DocumentTextHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/CsvHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/PdfHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/SpreadsheetHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/WordHandler.php'));

// === CONFIGURATION ===
// Tuning comes from config.php constants via queue_config() (built-in defaults
// when absent; see includes/queue_config.php and config.sample.php).
// Several step workers may run at once (PROMISE_WORKER_COUNT, default 1). Each
// cron run fills at most one free slot (acquire_worker_slot()); slot 1 keeps
// this base name, slot k > 1 is "cron_promise_worker_k". $workerName is set to
// the slot taken below and used for heartbeats, metrics and the lock release.
$workerBase = 'cron_promise_worker';
$workerCount = queue_config('PROMISE_WORKER_COUNT');
$stalePidThreshold = queue_config('WORKER_STALE_LOCK_SECONDS');
$loopDelaySeconds = queue_config('PROMISE_WORKER_LOOP_DELAY_SECONDS');
$maxRuntimeMinutes = queue_config('QUEUE_WORKER_MAX_RUNTIME_MINUTES');
$maxIdleMinutes = queue_config('QUEUE_WORKER_MAX_IDLE_MINUTES');
$maxRetryAttempts = queue_config('QUEUE_RETRY_MAX_ATTEMPTS');
$baseRetryDelay = queue_config('QUEUE_RETRY_BASE_DELAY_SECONDS');
$maxRetryDelay = queue_config('QUEUE_RETRY_MAX_DELAY_SECONDS');
$stuckThresholdMinutes = queue_config('QUEUE_STUCK_TASK_MINUTES');
$batchSize = queue_config('PROMISE_WORKER_BATCH_SIZE');
// Soft guard: checked at each loop boundary (between promise batches). When
// PHP's tracked memory crosses this, the worker exits cleanly and cron respawns
// it, keeping a clean fail-fast at a low blast radius.
$memoryLimitBytes = queue_config('QUEUE_WORKER_MEMORY_SOFT_LIMIT_MB') * 1024 * 1024;

$startTime = time();
$lastWorkTime = time();

// === SYSTEM SETTINGS ===
ini_set('max_execution_time', 0);
set_time_limit(0);
// Keep memory_limit DELIBERATELY modest. Raising it is the wrong lever for the
// XLSX OOM: PhpSpreadsheet parses zipped XML through libxml/ZipArchive, which
// allocate C memory OUTSIDE the Zend allocator that memory_limit accounts for,
// so a pathological file escapes this limit entirely and grows the process to
// multiple GB until the OS OOM-kills it (destabilizing the whole box). At 512M
// a runaway parse instead hits a clean PHP-fatal at low RSS. The real defense
// against oversized files is the pre-parse DocumentSizeGuard (and, as a tracked
// follow-up, a memory-bounded extraction subprocess) — NOT a higher limit here.
// QUEUE_WORKER_PHP_MEMORY_LIMIT_MB (default 512) lets an administrator change it.
ini_set('memory_limit', queue_config('QUEUE_WORKER_PHP_MEMORY_LIMIT_MB') . 'M');
ob_implicit_flush(true);

write_debug_log("Promise worker started (PID " . getmypid() . ")", "info");

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

    // config.php is read once, at start: exit when it was edited since, so cron
    // respawns the worker with the new settings (same fail-safe check as the
    // queue worker — worker_config_changed_since()).
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

    // === RECOVER STUCK PROMISES ===
    // A step another step worker is still running (its claim lock is held)
    // is never reset, however long it takes (recover_stuck_promises()).
    try {
        // Bounded: a step recovered QUEUE_RECOVERY_MAX_ATTEMPTS times is failed
        // through the normal terminal path instead.
        $recoveredPromises = recover_stuck_promises($db, $stuckThresholdMinutes, null, static fn(string $type): ?array => worker_get_job_definition($type));
        if ($recoveredPromises > 0) {
            // 'notice' for consistency with the queue worker's stuck-task
            // recovery — an infrequent, operator-relevant reclamation event.
            write_debug_log("Recovered {$recoveredPromises} stuck promises.", "notice");
        }
    } catch (\Throwable $t) {
        write_debug_log("Error recovering stuck promises: " . $t->getMessage(), "error");
    }

    // === FETCH AND PROCESS PROMISES ===
    try {
        $promises = fetch_ready_promises($db, $batchSize);
        $pendingCount = count($promises);

        // Update pending count gauge
        worker_metric_set($workerName, 'pending_promises', count($promises));

        if ($pendingCount === 0) {
            write_debug_log("No ready promises to process at " . date('Y-m-d H:i:s'), "debug");
        }

        // Set the failed promise count
        $failedCount = 0;

        // Steps this worker claimed and ran; with several step workers the
        // batches overlap and a step another worker claimed is skipped.
        $processedCount = 0;

        foreach ($promises as $promise) {
            // === CLAIM, LOOK UP THE JOB AND PROCESS THE STEP ===
            // worker_run_promise() takes the step's claim (a named lock plus a
            // re-read, so each step runs once however many step workers there
            // are), runs it and releases the claim. null = not claimed.
            $failed = worker_run_promise($db, $promise, $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay);
            if ($failed === null) {
                continue;
            }
            $processedCount++;
            $failedCount += $failed;
        }

        // Increment metrics
        worker_metric_inc($workerName, 'processed_promises', $processedCount);
        worker_metric_inc($workerName, 'failed_promises', $failedCount);

        if ($pendingCount > 0) {
            $lastWorkTime = time();
        }
    } catch (\Throwable $t) {
        write_debug_log("Error selecting or running ready promises: " . $t->getMessage(), "error");
        if (worker_is_connection_lost($t)) {
            // The step in hand stays in_progress; stuck-step recovery puts it
            // back (counted and bounded) once its claim is gone.
            $db = worker_reconnect_db();
            worker_prepare_connection($db);
        }
    }

    // === WORKER TICK ===
    worker_tick($workerName, $db);

    worker_interruptible_sleep($loopDelaySeconds);
}

// === CLEANUP ===
db_close($db);
write_debug_log("Promise worker exited.", "info");
?>