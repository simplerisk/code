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
require_once(realpath(__DIR__ . '/../includes/files.php'));
require_once(realpath(__DIR__ . '/../includes/Components/DocumentTextHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/CsvHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/PdfHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/SpreadsheetHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/WordHandler.php'));

// === CONFIGURATION ===
$workerName = 'cron_queue_worker';
$stalePidThreshold = 24 * 3600; // 24 hours
$loopDelaySeconds = 3;
$maxRuntimeMinutes = 60;
$maxIdleMinutes = 60;
$memoryLimitBytes = 400 * 1024 * 1024;
$stuckThresholdMinutes = 30;

$startTime = time();

// === SYSTEM SETTINGS ===
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');
ob_implicit_flush(true);

write_debug_log("Queue worker started (PID " . getmypid() . ")", "info");

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

    // === RECOVER STUCK TASKS ===
    try {
        $stmt = $db->prepare("
            UPDATE queue_tasks
            SET status='pending', updated_at=NOW()
            WHERE status='in_progress' AND updated_at < NOW() - INTERVAL :mins MINUTE
        ");
        $stmt->execute([':mins' => $stuckThresholdMinutes]);
        if ($stmt->rowCount() > 0) {
            write_debug_log("Recovered {$stmt->rowCount()} stuck tasks.", "warning");
        }
    } catch (\Throwable $t) {
        write_debug_log("Error recovering stuck tasks: " . $t->getMessage(), "error");
    }

    // === FETCH NEXT PENDING TASK ===
    $task = null;
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("
            SELECT * FROM queue_tasks
            WHERE status = 'pending'
            ORDER BY priority DESC, created_at ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($db->inTransaction()) {
            $db->commit();
        }

        foreach ($tasks as $t) {
            $payload = json_decode($t['payload'] ?? '{}', true);
            $nextRetryAt = isset($payload['next_retry_at']) ? strtotime($payload['next_retry_at']) : null;
            if ($nextRetryAt === null || $nextRetryAt <= $now) {
                $task = $t;
                break;
            }
        }
    } catch (\Throwable $t) {
        if ($db->inTransaction()) $db->rollBack();
        write_debug_log("Error fetching tasks: " . $t->getMessage(), "error");
        worker_interruptible_sleep($loopDelaySeconds);
        continue;
    }

    if (!$task) {
        worker_interruptible_sleep($loopDelaySeconds);
        continue;
    }

    $taskType = $task['task_type'];
    write_debug_log("Fetched task #{$task['id']} with type '{$taskType}'", "info");

    // === TASK LOOKUP USING KEYED JOB MAP ===
    $job_def = worker_get_job_definition($taskType);
    if (!$job_def) {
        handle_queue_task_failure($db, $task, "No job definition");
        worker_interruptible_sleep($loopDelaySeconds);
        continue;
    }

    // === TASK HANDLER EXECUTION ===
    try {
        $handler = $job_def['queue_check'] ?? $job_def['task_check'] ?? null;
        if (!is_callable($handler)) throw new \RuntimeException("No valid handler for task #{$task['id']}");

        $result = $handler($task, $db);
        write_debug_log("Task #{$task['id']} handler returned: " . var_export($result, true), "info");

        if ($result === false) {
            handle_queue_task_failure($db, $task, "Handler returned false");
        } else {
            if (!empty($job_def['stages']) && is_array($job_def['stages'])) {
                queue_update_status($task['id'], 'in_progress', $db);
            } else {
                queue_update_status($task['id'], 'completed', $db);
            }
        }
    } catch (\Throwable $t) {
        write_debug_log("Unexpected error processing task #{$task['id']}: " . $t->getMessage(), "error");
        handle_queue_task_failure($db, $task, $t->getMessage(), 5, 5, 3600);
    }

    // === WORKER TICK ===
    worker_tick($workerName, $db);

    worker_interruptible_sleep($loopDelaySeconds);
}

// === CLEANUP ===
db_close($db);
write_debug_log("Queue worker exited.", "info");
?>