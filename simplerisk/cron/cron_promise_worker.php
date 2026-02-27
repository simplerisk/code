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
require_once(realpath(__DIR__ . '/../includes/files.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
require_once(realpath(__DIR__ . '/../includes/tf_idf_enrichment.php'));
require_once(realpath(__DIR__ . '/../includes/Components/DocumentTextHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/CsvHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/PdfHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/SpreadsheetHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/WordHandler.php'));

// === CONFIGURATION ===
$workerName = 'cron_promise_worker';
$stalePidThreshold = 24 * 3600; // 24 hours
$loopDelaySeconds = 5;
$maxRuntimeMinutes = 60;
$maxIdleMinutes = 60;
$maxRetryAttempts = 5;
$baseRetryDelay = 5;
$maxRetryDelay = 3600;
$stuckThresholdMinutes = 30;
$memoryLimitBytes = 400 * 1024 * 1024;

$startTime = time();

// === SYSTEM SETTINGS ===
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');
ob_implicit_flush(true);

write_debug_log("Promise worker started (PID " . getmypid() . ")", "info");

// === CHECK FOR ZOMBIE WORKERS ===
$zombies = list_zombie_workers($workerName, $stalePidThreshold);
foreach ($zombies as $zombie) {
    write_debug_log(
        "Zombie worker detected: PID {$zombie['pid']} on host {$zombie['host']}, last heartbeat {$zombie['last_heartbeat']}",
        "warning"
    );
}

// === ACQUIRE LOCK ===
if (!acquire_worker_lock($workerName, $stalePidThreshold)) {
    write_debug_log("$workerName lock not acquired, exiting.", "info");
    exit(0);
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

    if (worker_should_reload()) {
        write_debug_log("Reload requested, resetting worker metrics...", "info");
        reset_worker_metrics($workerName);
        worker_clear_reload_flag();
    }

    // === METRICS: memory usage ===
    worker_metric_set($workerName, 'memory_bytes', memory_get_usage(true));
    worker_metric_set($workerName, 'peak_memory_bytes', memory_get_peak_usage(true));

    // === RESOURCE / TIME CHECKS ===
    if (($now - $startTime) > ($maxRuntimeMinutes * 60)) {
        record_worker_restart($workerName, 'max_runtime_exceeded');
        break;
    }

    if (($now - $startTime) > ($maxIdleMinutes * 60)) {
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
        write_debug_log("Reconnecting to DB after connection loss...", "warning");
        db_close($db);
        $db = db_open();
        if (!$db) {
            write_debug_log("Failed to reconnect to database, exiting.", "error");
            break;
        }
    }

    // === RECOVER STUCK PROMISES ===
    try {
        $stmt = $db->prepare("
            UPDATE promises
            SET state='pending', updated_at=NOW()
            WHERE state='in_progress'
              AND updated_at < NOW() - INTERVAL :mins MINUTE
        ");
        $stmt->execute([':mins' => $stuckThresholdMinutes ?? 30]);
        if ($stmt->rowCount() > 0) {
            write_debug_log("Recovered {$stmt->rowCount()} stuck promises.", "warning");
        }
    } catch (\Throwable $t) {
        write_debug_log("Error recovering stuck promises: " . $t->getMessage(), "error");
    }

    // === FETCH AND PROCESS PROMISES ===
    try {
        $stmt = $db->prepare("
            SELECT p.*
            FROM promises p
            WHERE p.state = 'pending'
              AND (p.depends_on IS NULL OR EXISTS (
                  SELECT 1 FROM promises d
                  WHERE d.id = p.depends_on AND d.state = 'completed'
              ))
            ORDER BY p.created_at ASC
            LIMIT 20
        ");
        $stmt->execute();
        $promises = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pendingCount = count($promises);

        // Update pending count gauge
        worker_metric_set($workerName, 'pending_promises', count($promises));

        if ($pendingCount === 0) {
            write_debug_log("No ready promises to process at " . date('Y-m-d H:i:s'), "debug");
        }

        // Set the failed promise count
        $failedCount = 0;

        foreach ($promises as $promise) {
            $jobType = $promise['promise_type'];

            // === LOOKUP JOB DEFINITION ===
            $jobDef = worker_get_job_definition($jobType);
            if (!$jobDef) {
                handle_promise_failure($db, $promise, "No job definition", $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay);
                continue;
            }

            // === PROCESS PROMISE ===
            $failedCount += process_promise($promise, $jobDef, $db, $maxRetryAttempts ?? 5, $baseRetryDelay ?? 5, $maxRetryDelay ?? 3600);
        }

        // Increment metrics
        worker_metric_inc($workerName, 'processed_promises', count($promises));
        worker_metric_inc($workerName, 'failed_promises', $failedCount);
    } catch (\Throwable $t) {
        write_debug_log("Error selecting ready promises: " . $t->getMessage(), "error");
    }

    // === WORKER TICK ===
    worker_tick($workerName, $db);

    worker_interruptible_sleep($loopDelaySeconds);
}

// === CLEANUP ===
db_close($db);
write_debug_log("Promise worker exited.", "info");
?>