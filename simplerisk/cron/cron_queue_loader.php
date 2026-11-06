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
require_once(realpath(__DIR__ . '/../includes/queue_config.php'));

// === CONFIGURATION ===
// Tuning comes from config.php constants via queue_config() (built-in defaults
// when absent; see includes/queue_config.php and config.sample.php).
$workerName = 'cron_queue_loader';
$stalePidThreshold = queue_config('WORKER_STALE_LOCK_SECONDS');
$maxRuntimeMinutes = queue_config('QUEUE_WORKER_MAX_RUNTIME_MINUTES');
$maxIdleMinutes = queue_config('QUEUE_WORKER_MAX_IDLE_MINUTES');
$memoryLimitBytes = queue_config('QUEUE_WORKER_MEMORY_SOFT_LIMIT_MB') * 1024 * 1024;

$startTime = time();

// === SYSTEM SETTINGS ===
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', queue_config('QUEUE_WORKER_PHP_MEMORY_LIMIT_MB') . 'M');
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
// Always exactly one loader: task_check deduplication is read-then-insert, so
// two loaders could queue the same work twice. The slot call (count 1) still
// gives the loader the atomic acquisition and the remote-host liveness rule.
if (acquire_worker_slot($workerName, 1, $stalePidThreshold) === null) {
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
worker_prepare_connection($db);

// One loader pass at a time across every host (queue_loader_lock_name()):
// the worker lock above lets a new container take over a presumed-dead
// loader's slot within minutes, and task_check deduplication is
// read-then-insert, so this named lock is what keeps two passes from queueing
// the same work. Released when this process ends.
try {
    $loaderLocked = queue_lock_try($db, queue_loader_lock_name($db));
} catch (\Throwable $t) {
    // Fail-safe: without the lock no pass runs. Logged, not left to cron output.
    write_debug_log("The database refused the queue loader's named lock (GET_LOCK); no pass runs: " . $t->getMessage(), "error");
    exit(0);
}
if (!$loaderLocked) {
    write_debug_log("Another queue loader pass is running; exiting.", "info");
    exit(0);
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

    // SIGHUP means "recycle with fresh code". An in-process reload is
    // impossible (require_once is a no-op once a file is loaded), so stop
    // the pass gracefully and let cron rerun the loader with the new code.
    if (worker_should_reload()) {
        record_worker_restart($workerName, 'reload_signal');
        break;
    }

    // A deploy landed mid-pass: stop queueing tasks off the stale in-memory
    // job map. The next cron tick reruns the loader with fresh code.
    if (worker_restart_requested_since($startTime, worker_restart_flag_timestamp())) {
        record_worker_restart($workerName, 'restart_requested');
        break;
    }

    // config.php changed mid-pass: stop queueing off the stale configuration.
    // The next cron tick reruns the loader with the edited file (same
    // fail-safe check as the workers — worker_config_changed_since()).
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
        // The lost connection also lost the loader lock: stop this pass rather
        // than run on unguarded; the next cron run starts a fresh one.
        write_debug_log("Lost the database connection during the queue loader pass; stopping it.", "warning");
        break;
    }

    $jobType = $job['type'] ?? '(unknown)';

    if (isset($job['task_check']) && is_callable($job['task_check'])) {
        try {
            write_debug_log("Running task_check for job type '{$jobType}'", "debug");

            // Check if task_check accepts $db
            $reflection = new ReflectionFunction(Closure::fromCallable($job['task_check']));
            $params = $reflection->getParameters();

            $result = count($params) > 0 ? $job['task_check']($db) : $job['task_check']();

            if ($result === true) {
                $queuedTasks++;
                worker_metric_inc($workerName, 'last_queued_tasks', 1);
                write_debug_log("task_check for '{$jobType}' queued tasks successfully.", "info");
            } elseif ($result === false) {
                write_debug_log("task_check for '{$jobType}' found no tasks to queue.", "debug");
            } else {
                write_debug_log("task_check for '{$jobType}' returned unexpected value: " . var_export($result, true), "warning");
            }

        } catch (Throwable $e) {
            write_debug_log("Error running task_check for '{$jobType}': " . $e->getMessage(), "error");
            write_debug_log("Stack trace: " . $e->getTraceAsString(), "debug");
        }
    } else {
        write_debug_log("Job type '{$jobType}' does not have an automated task_check; tasks must be queued manually.", "debug");
    }

    // === WORKER TICK ===
    worker_tick($workerName, $db);
}

// === CLOSE DATABASE CONNECTION ===
db_close($db);

write_debug_log("Queue loader finished. Tasks queued: {$queuedTasks}", "info");

?>