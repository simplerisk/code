<?php

declare(strict_types=1);

if (php_sapi_name() !== "cli") {
    exit("This script must be run via the command line.\n");
}

require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/mail.php'));
require_once(realpath(__DIR__ . '/../includes/queues.php'));
require_once(realpath(__DIR__ . '/../includes/promises.php'));
require_once(realpath(__DIR__ . '/../includes/files.php'));
require_once(realpath(__DIR__ . '/../includes/Components/DocumentTextHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/CsvHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/PdfHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/SpreadsheetHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/WordHandler.php'));

// === CONFIGURATION ===
$pidFile = sys_get_temp_dir() . '/simplerisk_queue_worker.pid';
$stalePidThreshold = 24 * 3600;
$loopDelaySeconds = 3;
$idleRestartMinutes = 60;
$stuckThresholdMinutes = 30;

// === SYSTEM SETTINGS ===
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');
ob_implicit_flush(true);

// === SINGLE INSTANCE CHECK ===
if (file_exists($pidFile)) {
    $pid = (int) trim(file_get_contents($pidFile));
    $pidAge = time() - filemtime($pidFile);

    if ($pid && isProcessRunning($pid)) {
        echo "Worker already running (PID $pid)\n";
        exit;
    } else {
        write_debug_log("Removing stale/non-running PID file (PID $pid, age {$pidAge}s)...", "info");
        unlink($pidFile);
    }
}

file_put_contents($pidFile, getmypid());

$cleanupPidFile = function() use ($pidFile) {
    if (file_exists($pidFile)) {
        unlink($pidFile);
        write_debug_log("PID file removed.", "info");
    }
};
register_shutdown_function($cleanupPidFile);

// Shutdown & signal handling
$shutdownRequested = false;
if (function_exists('pcntl_signal')) {
    $signalHandler = function($signo) use (&$shutdownRequested) {
        if (in_array($signo, [SIGTERM, SIGINT, SIGHUP, SIGQUIT])) {
            $shutdownRequested = true;
            write_debug_log("Shutdown signal received (signal $signo), exiting gracefully...", "notice");
        }
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
    pcntl_signal(SIGHUP, $signalHandler);
    pcntl_signal(SIGQUIT, $signalHandler);
}

write_debug_log("Queue worker started (PID " . getmypid() . ")", "info");

// === DATABASE CONNECTION ===
$db = db_open();
$startTime = time();
$jobs = load_all_jobs();

while (true) {
    if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
    if ($shutdownRequested) break;

    if ((time() - $startTime) > ($idleRestartMinutes * 60)) break;
    if (memory_get_usage(true) > 400 * 1024 * 1024) break;

    // Recover stuck tasks
    $db->exec("
        UPDATE queue_tasks
        SET status='pending', updated_at=NOW()
        WHERE status='in_progress' AND updated_at < NOW() - INTERVAL {$stuckThresholdMinutes} MINUTE
    ");

    // Ensure DB connection
    try { $db->query("SELECT 1"); } catch (\Throwable $t) { db_close($db); $db = db_open(); }

    // Fetch pending tasks
    $db->beginTransaction();
    $stmt = $db->prepare("
        SELECT * FROM queue_tasks
        WHERE status = 'pending'
        ORDER BY priority DESC, created_at ASC
        LIMIT 10
        FOR UPDATE
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $db->commit();

    $task = null;
    foreach ($tasks as $t) {
        $payload = json_decode($t['payload'] ?? '{}', true);
        $nextRetryAt = isset($payload['next_retry_at']) ? strtotime($payload['next_retry_at']) : null;
        if ($nextRetryAt === null || $nextRetryAt <= time()) {
            $task = $t;
            break;
        }
    }

    if (!$task) {
        sleep($loopDelaySeconds);
        continue;
    }

    $taskType = $task['task_type'];
    write_debug_log("Fetched task #{$task['id']} with type '{$taskType}'", "info");

    // Find job definition
    $job_def = null;
    foreach ($jobs as $job) {
        if ($job['type'] === $taskType) {
            $job_def = $job;
            break;
        }
    }

    try {
        if (!$job_def) throw new \RuntimeException("No job definition for task type '{$taskType}'");

        $handler = $job_def['queue_check'] ?? $job_def['task_check'] ?? null;
        if (!is_callable($handler)) throw new \RuntimeException("No valid handler for task #{$task['id']}");

        $result = $handler($task);
        write_debug_log("Task #{$task['id']} handler returned: " . var_export($result, true), "info");

        if ($result === false) {
            // Task failed
            handle_queue_task_failure($db, $task, "Handler returned false");
        } else {
            // Determine if this task has promise stages
            if (!empty($job_def['stages']) && is_array($job_def['stages'])) {
                // Task has stages → leave in_progress for promise scheduler
                queue_update_status($task['id'], 'in_progress');
            } else {
                // Standalone task → mark completed
                queue_update_status($task['id'], 'completed');
            }
        }
    } catch (\Throwable $t) {
        write_debug_log("Unexpected error processing task #{$task['id']}: " . $t->getMessage(), "error");
        handle_queue_task_failure($db, $task, $t->getMessage(), 5, 5, 3600);
    }

    sleep($loopDelaySeconds);
}

db_close($db);
write_debug_log("Queue worker exited.", "info");

?>