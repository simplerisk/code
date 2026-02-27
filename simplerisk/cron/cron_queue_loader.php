<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

declare(strict_types=1);

if (php_sapi_name() !== "cli") {
    exit("This script must be run from the command line.\n");
}

require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/queues.php'));
require_once(realpath(__DIR__ . '/../includes/promises.php'));
require_once(realpath(__DIR__ . '/../includes/workers.php'));

// === CONFIGURATION ===
$workerName = 'cron_queue_loader';
$stalePidThreshold = 24 * 3600; // 24 hours
$maxRuntimeMinutes = 60;
$maxIdleMinutes = 60;
$memoryLimitBytes = 400 * 1024 * 1024;

$startTime = time();

// === SYSTEM SETTINGS ===
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');
ob_implicit_flush(true);

write_debug_log("Queue loader started (PID " . getmypid() . ")", "info");

// === CHECK FOR ZOMBIE WORKERS ===
$zombies = list_zombie_workers($workerName, $stalePidThreshold);
foreach ($zombies as $zombie) {
    write_debug_log(
        "Zombie worker detected: PID {$zombie['pid']} on host {$zombie['host']}, last heartbeat {$zombie['last_heartbeat']}",
        "warning"
    );
}

// === ACQUIRE WORKER LOCK ===
if (!acquire_worker_lock($workerName, $stalePidThreshold)) {
    write_debug_log("Queue loader lock exists and is active; exiting.", "info");
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

// === LOAD JOB DEFINITIONS ===
$jobs = load_all_jobs();
$queuedTasks = 0;

// === RUN JOBS ===
foreach ($jobs as $job) {
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
        worker_metric_set($workerName, 'last_queued_tasks', 0);
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

    $jobType = $job['type'] ?? '(unknown)';

    if (isset($job['task_check']) && is_callable($job['task_check'])) {
        try {
            write_debug_log("Running task_check for job type '{$jobType}'", "info");

            // Check if task_check accepts $db
            $reflection = new ReflectionFunction($job['task_check']);
            $params = $reflection->getParameters();

            $result = count($params) > 0 ? $job['task_check']($db) : $job['task_check']();

            if ($result === true) {
                $queuedTasks++;
                worker_metric_inc($workerName, 'last_queued_tasks', 1);
                write_debug_log("task_check for '{$jobType}' queued tasks successfully.", "notice");
            } elseif ($result === false) {
                write_debug_log("task_check for '{$jobType}' found no tasks to queue.", "info");
            } else {
                write_debug_log("task_check for '{$jobType}' returned unexpected value: " . var_export($result, true), "warning");
            }

        } catch (Throwable $e) {
            write_debug_log("Error running task_check for '{$jobType}': " . $e->getMessage(), "error");
            write_debug_log("Stack trace: " . $e->getTraceAsString(), "debug");
        }
    } else {
        write_debug_log("Job type '{$jobType}' does not have an automated task_check; tasks must be queued manually.", "info");
    }

    // === WORKER TICK ===
    worker_tick($workerName, $db);
}

// === CLOSE DATABASE CONNECTION ===
db_close($db);

write_debug_log("Queue loader finished. Tasks queued: {$queuedTasks}", "info");

?>