<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Cron Queue Loader
 *
 * Periodically run via cron to call all task_check functions in job files.
 * Responsible for enqueueing tasks that need to run.
 */

declare(strict_types=1);

if (php_sapi_name() !== "cli") {
    exit("This script must be run via the command line.\n");
}

require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/queues.php'));
require_once(realpath(__DIR__ . '/../includes/promises.php'));

// === CONFIGURATION ===
$pidFile = sys_get_temp_dir() . '/simplerisk_queue_loader.pid';
$stalePidThreshold = 24 * 3600; // 24 hours

// === SINGLE INSTANCE CHECK WITH STALE PID CLEANUP ===
if (file_exists($pidFile)) {
    $pid = (int) trim(file_get_contents($pidFile));
    $pidAge = time() - filemtime($pidFile);

    if ($pid && isProcessRunning($pid)) {
        echo "Queue loader already running (PID $pid)\n";
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

// Global shutdown flag (in case we want to handle signals in the future)
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

write_debug_log("Queue loader started (PID " . getmypid() . ")", "info");

// === LOAD JOB DEFINITIONS ===
$jobs = load_all_jobs();

foreach ($jobs as $job) {
    if ($shutdownRequested) {
        write_debug_log("Shutdown requested, exiting early...", "info");
        break;
    }

    $jobType = $job['type'] ?? '(unknown)';

    if (isset($job['task_check']) && is_callable($job['task_check'])) {
        try {
            write_debug_log("Running task_check for job type '{$jobType}'", "info");
            $result = $job['task_check']();

            if ($result === true) {
                write_debug_log("task_check for '{$jobType}' queued tasks successfully.", "notice");
            } elseif ($result === false) {
                write_debug_log("task_check for '{$jobType}' found no tasks to queue.", "info");
            } else {
                write_debug_log("task_check for '{$jobType}' returned unexpected value: " . var_export($result, true), "warning");
            }

        } catch (Throwable $e) { // Catch all errors
            write_debug_log("Error running task_check for '{$jobType}': " . $e->getMessage(), "error");
            write_debug_log("Stack trace: " . $e->getTraceAsString(), "debug");
            // Continue to next job instead of dying
        }
    } else {
        write_debug_log(
            "Job type '{$jobType}' does not have an automated task_check — tasks must be queued manually via code.",
            "info"
        );
    }
}

write_debug_log("Queue loader finished.", "info");
?>