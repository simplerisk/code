<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. You can obtain one at http://mozilla.org/MPL/2.0/. */

/* Persistent Promise Scheduler with verbose debugging for all jobs */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit("This script must be run from the command line.\n");
}

require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/promises.php'));
require_once(realpath(__DIR__ . '/../includes/Components/WordHandler.php'));
require_once(realpath(__DIR__ . '/../includes/files.php'));
require_once(realpath(__DIR__ . '/../includes/artificial_intelligence.php'));
require_once(realpath(__DIR__ . '/../includes/tf_idf_enrichment.php'));
require_once(realpath(__DIR__ . '/../includes/Components/DocumentTextHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/CsvHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/PdfHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/SpreadsheetHandler.php'));
require_once(realpath(__DIR__ . '/../includes/Components/WordHandler.php'));

$pidFile = sys_get_temp_dir() . '/simplerisk_promise_worker.pid';
$stalePidThreshold = 24 * 3600; // 24 hours
$loopDelaySeconds = 5;
$idleRestartMinutes = 60;
$stuckThresholdMinutes = 30;
$heartbeatInterval = 3600;
$maxRetryAttempts = 5;
$baseRetryDelay = 5;
$maxRetryDelay = 3600;

ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');
ob_implicit_flush(true);

// === SINGLE INSTANCE CHECK WITH STALE PID CLEANUP ===
if (file_exists($pidFile)) {
    $pid = (int) trim(file_get_contents($pidFile));
    $pidAge = time() - filemtime($pidFile);

    if ($pid && isProcessRunning($pid)) {
        echo "Worker already running (PID $pid)\n";
        exit;
    } elseif ($pidAge > $stalePidThreshold) {
        write_debug_log("Stale PID file detected (PID $pid, age {$pidAge}s), removing...", "warning");
        unlink($pidFile);
    } else {
        write_debug_log("Removing PID file for non-running process (PID $pid, age {$pidAge}s)...", "info");
        unlink($pidFile);
    }
}

// Write current PID
file_put_contents($pidFile, getmypid());

// Function to safely remove PID file
$cleanupPidFile = function() use ($pidFile) {
    if (file_exists($pidFile)) {
        unlink($pidFile);
        write_debug_log("PID file removed.", "info");
    }
};

// Ensure PID file is removed on normal shutdown
register_shutdown_function($cleanupPidFile);

// Handle fatal PHP errors to clean up PID file
register_shutdown_function(function() use ($cleanupPidFile) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        write_debug_log("Fatal error encountered: {$error['message']} in {$error['file']}:{$error['line']}", "error");
        $cleanupPidFile();
    }
});

// Global shutdown flag
$shutdownRequested = false;

// Setup signal handling if available
if (function_exists('pcntl_signal')) {
    $signalHandler = function($signo) use (&$shutdownRequested) {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
            case SIGHUP:
            case SIGQUIT:
                $shutdownRequested = true;
                write_debug_log("Shutdown signal received (signal $signo), exiting gracefully...", "notice");
                break;
        }
    };

    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
    pcntl_signal(SIGHUP, $signalHandler);
    pcntl_signal(SIGQUIT, $signalHandler);
}

write_debug_log("Promise scheduler started (PID " . getmypid() . ")", "info");

$startTime = time();
$lastHeartbeat = time();
$db = db_open();

// Load all jobs (AI and non-AI)
$all_jobs = load_all_jobs();
write_debug_log("Loaded " . count($all_jobs) . " job definitions.", "debug");

while (true) {
    // Dispatch signals
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    if ($shutdownRequested) {
        write_debug_log("Shutdown requested, exiting main loop...", "info");
        break;
    }

    if ((time() - $startTime) > ($idleRestartMinutes * 60)) {
        write_debug_log("Scheduler restarting after $idleRestartMinutes minutes...", "info");
        break;
    }

    if (memory_get_usage(true) > 400 * 1024 * 1024) { // 400MB threshold
        write_debug_log(
            "Memory usage (" . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB) approaching limit, restarting worker...",
            "warning"
        );
        break;
    }

    if ((time() - $lastHeartbeat) > $heartbeatInterval) {
        write_debug_log("Promise scheduler heartbeat — still alive", "info");
        $lastHeartbeat = time();
    }

    // Ensure DB connection
    try {
        $db->query("SELECT 1");
    } catch (Exception $e) {
        write_debug_log("Reconnecting to DB after connection loss...", "warning");
        db_close($db);
        $db = db_open();
    }

    // Recover stuck promises
    try {
        $stmt = $db->prepare("
            UPDATE promises
            SET state='pending', updated_at=NOW()
            WHERE state='in_progress'
              AND updated_at < NOW() - INTERVAL :mins MINUTE
        ");
        $stmt->execute([':mins' => $stuckThresholdMinutes]);
        if ($stmt->rowCount() > 0) {
            write_debug_log("Recovered {$stmt->rowCount()} stuck promises.", "info");
        }
    } catch (Exception $e) {
        write_debug_log("Error recovering stuck promises: " . $e->getMessage(), "error");
    }

    // Fetch ready promises
    try {
        $stmt = $db->prepare("
            SELECT p.*
            FROM promises p
            WHERE p.state = 'pending'
              AND (
                  p.depends_on IS NULL
                  OR EXISTS (
                      SELECT 1 FROM promises d
                      WHERE d.id = p.depends_on AND d.state = 'completed'
                  )
              )
            ORDER BY p.created_at ASC
            LIMIT 20
        ");
        $stmt->execute();
        $promises = $stmt->fetchAll(PDO::FETCH_ASSOC);

        write_debug_log("Fetched " . count($promises) . " pending promises for processing", "debug");

        foreach ($promises as $promise) {
            $promise_id = $promise['id'];
            $promise_type = $promise['promise_type'];
            $stage_name = $promise['current_stage'] ?? 'N/A';
            $payload = json_decode($promise['payload'], true) ?? [];

            $retry_attempts = $payload['_retry_attempts'] ?? 0;
            $retry_delay = $payload['_retry_delay'] ?? 0;

            if (!empty($payload['_retry_available_at'])) {
                $available_at = (int)$payload['_retry_available_at'];
                if (time() < $available_at) {
                    $sleep_time = $available_at - time();
                    write_debug_log(
                        "Promise #{$promise_id} waiting for backoff delay: {$sleep_time}s remaining (attempt {$retry_attempts})",
                        "debug"
                    );
                    continue;
                } else {
                    // retry window reached; remove the helper keys so processing starts fresh
                    unset($payload['_retry_available_at'], $payload['_retry_delay']);
                }
            }

            write_debug_log("Processing promise #{$promise_id}: type={$promise_type}, stage={$stage_name}, retries={$retry_attempts}", "info");

            update_promise_stage($promise_id, null, 'in_progress');

            try {
                $job_def = null;
                foreach ($all_jobs as $job) {
                    if ($job['type'] === $promise_type) {
                        $job_def = $job;
                        break;
                    }
                }

                if (!$job_def) {
                    write_debug_log("No job definition found for promise type '{$promise_type}'", "error");
                    continue;
                }

                if (!isset($job_def['stages'][$stage_name])) {
                    write_debug_log("Stage function '{$stage_name}' not found for job type '{$promise_type}'", "error");
                    continue;
                }

                write_debug_log("Found stage function '{$stage_name}' for job type '{$promise_type}'", "debug");

                $stage_fn = $job_def['stages'][$stage_name];

                // === BEGIN Option B: Catch all Throwables ===
                try {
                    $result = $stage_fn($promise);
                } catch (\Throwable $t) {
                    $msg = "Stage '{$stage_name}' threw error: " . $t->getMessage() . " in {$t->getFile()}:{$t->getLine()}";
                    write_debug_log($msg, "error");
                    handle_promise_failure($db, $promise, $msg, $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay);
                    continue; // move to next promise
                }
                // === END Option B ===

                $result_str = is_array($result) ? json_encode($result) : var_export($result, true);
                write_debug_log("Stage function '{$stage_name}' returned: {$result_str}", "debug");

                if ($result === false || $result === null) {
                    handle_promise_failure(
                        $db,
                        $promise,
                        "Stage function returned " . var_export($result, true),
                        $maxRetryAttempts,
                        $baseRetryDelay,
                        $maxRetryDelay
                    );
                } else {
                    if (is_array($result)) {
                        update_promise_stage($promise_id, null, 'completed', $result);
                        propagate_payload_to_next($db, $promise_id, $result);
                    } else {
                        update_promise_stage($promise_id, null, 'completed');
                    }

                    write_debug_log("Promise #{$promise_id} stage '{$stage_name}' completed successfully", "info");
                    check_and_finalize_task($db, $promise, $job_def);
                }

            } catch (\Throwable $e) {
                $msg = "Unexpected error in stage '{$stage_name}' for promise #{$promise_id}: " . $e->getMessage();
                write_debug_log($msg, "error");
                handle_promise_failure($db, $promise, $msg, $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay);
            }
        }
    } catch (Exception $e) {
        write_debug_log("Error selecting ready promises: " . $e->getMessage(), "error");
    }

    sleep($loopDelaySeconds);
}

db_close($db);
write_debug_log("Promise scheduler exited.", "info");
?>