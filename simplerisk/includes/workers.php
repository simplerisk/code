<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/functions.php'));

$GLOBALS['_worker_state'] = [];
$GLOBALS['_worker_flags'] = [
    'shutdown' => false,
    'reload'   => false,
];

/*********************************
 * FUNCTION: ACQUIRE WORKER LOCK *
 *********************************/
function acquire_worker_lock(string $name, int $staleSeconds): bool
{
    // Acquire exclusive worker lock in database
    // Checks for existing PID, handles stale locks, kills if needed
    $lockKey = "{$name}_lock";
    $now = time();
    $host = gethostname();
    $pid = getmypid();

    // Load existing lock
    $lock = json_decode(get_setting($lockKey) ?? '{}', true);

    if (!empty($lock)) {
        $existingPid = $lock['pid'] ?? null;
        $existingHost = $lock['host'] ?? 'unknown';
        $lastHeartbeat = $lock['heartbeat'] ?? 0;

        if ($existingPid && isProcessRunning($existingPid, $existingHost)) {
            if (($now - $lastHeartbeat) < $staleSeconds) {
                write_debug_log(
                    "$name already running on host {$existingHost} (PID {$existingPid}), last heartbeat {$lastHeartbeat}",
                    "info"
                );
                return false;
            }

            // Stale process detected
            write_debug_log(
                "$name stale lock detected (PID {$existingPid} on host {$existingHost}), attempting termination",
                "warning"
            );

            update_or_insert_setting("{$name}_last_restart_reason", "stale_heartbeat");
            update_or_insert_setting("{$name}_last_restart_at", (string)$now);

            if ($existingHost === $host && function_exists('posix_kill')) {
                posix_kill($existingPid, SIGTERM);
                sleep(5);
                if (isProcessRunning($existingPid)) {
                    write_debug_log("$name still running, sending SIGKILL", "error");
                    posix_kill($existingPid, SIGKILL);
                    sleep(1);
                }
            } else {
                write_debug_log(
                    "Cannot send kill signal to process on remote host {$existingHost}, marking as stale",
                    "warning"
                );
            }
        } else {
            write_debug_log(
                "$name lock exists but process {$existingPid} not running, proceeding to acquire",
                "info"
            );
        }
    }

    // Acquire lock
    $newLock = [
        'pid' => $pid,
        'host' => $host,
        'started_at' => $now,
        'heartbeat' => $now,
    ];

    update_or_insert_setting($lockKey, json_encode($newLock));

    write_debug_log("$name lock acquired (PID {$pid} on host {$host})", "info");

    // Maintain local PID file
    $pidFile = sys_get_temp_dir() . "/{$name}.pid";
    file_put_contents($pidFile, json_encode($newLock));

    return true;
}

/**************************************
 * FUNCTION: UPDATE WORKER HEARTBEAT *
 **************************************/
function update_worker_heartbeat(string $name, int $heartbeatInterval = 60): void
{
    // Updates heartbeat in the database at the configured interval to reduce DB load
    static $lastUpdate = [];
    $now = time();

    if (isset($lastUpdate[$name]) && ($now - $lastUpdate[$name]) < $heartbeatInterval) {
        return; // skip frequent updates
    }

    $lockKey = "{$name}_lock";
    $lock = json_decode(get_setting($lockKey) ?? '{}', true);

    if (empty($lock)) return;

    $pid = $lock['pid'] ?? null;
    $host = $lock['host'] ?? null;

    if ($pid !== getmypid() || $host !== gethostname()) {
        write_debug_log(
            "Heartbeat attempt by non-owner process (PID " . getmypid() . " on host " . gethostname() . ") ignored",
            "warning"
        );
        return;
    }

    // Write heartbeat
    $lock['heartbeat'] = $now;
    update_or_insert_setting($lockKey, json_encode($lock));
    $lastUpdate[$name] = $now;
}

/*********************************
 * FUNCTION: RELEASE WORKER LOCK *
 *********************************/
function release_worker_lock(string $name): void
{
    // Releases the worker lock in the database and removes PID file
    $lockKey = "{$name}_lock";

    $lock = json_decode(get_setting($lockKey) ?? '{}', true);
    if (!empty($lock)) {
        $pid = $lock['pid'] ?? null;
        $host = $lock['host'] ?? null;

        if ($pid === getmypid() && $host === gethostname()) {
            delete_setting($lockKey);
            write_debug_log("$name lock released (PID {$pid} on host {$host})", "info");
        } else {
            write_debug_log(
                "$name lock release skipped: non-owner process (PID " . getmypid() . " on host " . gethostname() . ")",
                "warning"
            );
        }
    }

    $pidFile = sys_get_temp_dir() . "/{$name}.pid";
    if (file_exists($pidFile)) unlink($pidFile);
}

/**************************************
 * FUNCTION: RECORD WORKER RESTARTS *
 **************************************/
function record_worker_restart(string $name, string $reason): void
{
    // Record restart reason, timestamp, and increment restart count
    write_debug_log("Worker restart requested: {$reason}", "notice");

    $now = time();
    update_or_insert_setting("{$name}_last_restart_reason", $reason);
    update_or_insert_setting("{$name}_last_restart_at", (string)$now);

    $count = (int)get_setting("{$name}_restart_count");
    update_or_insert_setting("{$name}_restart_count", (string)($count + 1));
}

/**************************************
 * FUNCTION: LIST ZOMBIE WORKERS *
 **************************************/
function list_zombie_workers(string $name, int $staleSeconds): array
{
    // Return any worker locks that appear stale / dead
    $lockKey = "{$name}_lock";
    $lock = json_decode(get_setting($lockKey) ?? '{}', true);
    $zombies = [];

    if (!empty($lock)) {
        $pid = $lock['pid'] ?? null;
        $host = $lock['host'] ?? null;
        $heartbeat = $lock['heartbeat'] ?? 0;

        if ($pid && !isProcessRunning($pid, $host) && (time() - $heartbeat) > $staleSeconds) {
            $zombies[] = [
                'pid' => $pid,
                'host' => $host,
                'last_heartbeat' => $heartbeat,
            ];
        }
    }

    return $zombies;
}

/**************************************
 * FUNCTION: IS PROCESS RUNNING *
 **************************************/
function isProcessRunning(int $pid, string $host = null): bool
{
    // Check if PID exists on local host
    if ($pid <= 0) return false;
    if ($host && $host !== gethostname()) return false;

    if (stripos(PHP_OS, 'WIN') === 0) {
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
        return count($output) > 1;
    }

    if (file_exists("/proc/{$pid}")) return true;

    if (function_exists('posix_kill')) return @posix_kill($pid, 0);

    write_debug_log("Cannot verify PID {$pid}; no /proc and posix_kill unavailable.", "warning");
    return false;
}

/*************************
 * FUNCTION: WORKER TICK *
 *************************/
function worker_tick(string $workerName, PDO $db, array $metrics = []): void
{
    if (!isset($GLOBALS['_worker_state'])) {
        $GLOBALS['_worker_state'] = [];
    }
    $state =& $GLOBALS['_worker_state'];

    $now = time();

    // Tunables
    $flushEvery     = 60;  // seconds between DB metric flushes
    $heartbeatEvery = 60;  // seconds between heartbeat updates

    /*
     * ----------------------------------------------------
     * INITIALIZE STATE (once per process)
     * ----------------------------------------------------
     */
    if (!isset($state[$workerName])) {
        $state[$workerName] = [
            'started_at'      => $now,
            'last_tick'       => $now,
            'last_flush'      => 0,
            'last_heartbeat'  => 0,

            // Metric buckets
            'gauges'     => [],   // latest-value metrics
            'counters'   => [],   // monotonic counters
            'loop'       => [],   // timing info
        ];

        // Reset DB-side metrics ONCE per worker start
        update_or_insert_setting("{$workerName}_metrics", json_encode([
            'gauges'   => [],
            'counters' => [],
            'loop'     => [],
            'updated_at' => $now
        ]));

        update_or_insert_setting("{$workerName}_runtime", json_encode([
            'started_at' => $now,
            'last_tick'  => $now,
        ]));
    }

    $s =& $state[$workerName];

    /*
     * ----------------------------------------------------
     * LOOP TIMING
     * ----------------------------------------------------
     */
    $elapsed = $now - $s['last_tick'];

    $loopDurationMs = max(0, $elapsed * 1000);
    $loopLagSeconds = max(0, $elapsed - 1);

    $s['loop'] = [
        'last_duration_ms' => (int)$loopDurationMs,
        'lag_seconds'      => (int)$loopLagSeconds,
    ];

    $s['last_tick'] = $now;

    /*
     * ----------------------------------------------------
     * MERGE METRICS
     * ----------------------------------------------------
     * Convention:
     *  - counters → monotonic (only increase)
     *  - gauges   → latest value
     */

    foreach ($metrics as $key => $value) {
        if (!is_numeric($value)) {
            continue;
        }

        // Heuristic: *_count, *_total, processed_*, etc → counter
        if (
            str_ends_with($key, '_count') ||
            str_ends_with($key, '_total') ||
            str_starts_with($key, 'processed_') ||
            str_starts_with($key, 'error_')
        ) {
            $prev = $s['counters'][$key] ?? 0;
            if ($value >= $prev) {
                $s['counters'][$key] = $value;
            }
        } else {
            // gauge
            $s['gauges'][$key] = $value;
        }
    }

    /*
     * ----------------------------------------------------
     * HEARTBEAT (THROTTLED)
     * ----------------------------------------------------
     */
    if (($now - $s['last_heartbeat']) >= $heartbeatEvery) {
        update_worker_heartbeat($workerName, $heartbeatEvery);
        $s['last_heartbeat'] = $now;
    }

    /*
     * ----------------------------------------------------
     * FLUSH METRICS TO DB (THROTTLED)
     * ----------------------------------------------------
     */
    if (($now - $s['last_flush']) >= $flushEvery) {

        update_or_insert_setting(
            "{$workerName}_metrics",
            json_encode([
                'gauges'     => $s['gauges'],
                'counters'   => $s['counters'],
                'loop'       => $s['loop'],
                'updated_at' => $now,
            ])
        );

        update_or_insert_setting(
            "{$workerName}_runtime",
            json_encode([
                'started_at' => $s['started_at'],
                'last_tick'  => $now,
            ])
        );

        $s['last_flush'] = $now;
    }
}

function reset_worker_metrics(string $workerName): void
{
    $now = time();

    // Reset persistent DB metrics
    update_or_insert_setting("{$workerName}_metrics", json_encode([
        'gauges' => [],
        'counters' => [],
        'loop' => [],
        'updated_at' => $now
    ]));

    // Runtime info
    $runtime = json_decode(get_setting("{$workerName}_runtime") ?? '{}', true);
    $runtime['started_at'] = $now;
    $runtime['last_tick']  = $now;

    update_or_insert_setting("{$workerName}_runtime", json_encode($runtime));

    // Reset in-memory state
    $GLOBALS['_worker_state'][$workerName] = [
        'started_at'     => $now,
        'last_tick'      => $now,
        'last_flush'     => 0,
        'last_heartbeat' => 0,
        'gauges'         => [],
        'counters'       => [],
        'loop'           => [],
    ];
}

/********************************************
 * FUNCTION: REGISTER WORKER SIGNAL HANDLERS
 ********************************************/
function register_worker_signal_handlers(string $workerName): void
{
    if (!function_exists('pcntl_signal')) {
        write_debug_log("[$workerName] pcntl_signal unavailable; signals will not be handled.", "warning");
        return;
    }

    $signals = [SIGTERM, SIGINT, SIGQUIT, SIGHUP];

    foreach ($signals as $signal) {
        $handler = fn(int $signo) => worker_signal_handler($signo, $workerName);

        try {
            pcntl_signal($signal, $handler);
            write_debug_log("[$workerName] Registered signal handler for signal $signal successfully.", "debug");
        } catch (\TypeError $e) {
            write_debug_log(
                "[$workerName] TypeError registering signal $signal: " . $e->getMessage(),
                "error"
            );
        } catch (\Throwable $t) {
            write_debug_log(
                "[$workerName] Unexpected error registering signal $signal: " . $t->getMessage(),
                "error"
            );
        }
    }
}

function worker_should_shutdown(): bool
{
    return !empty($GLOBALS['_worker_flags']['shutdown']);
}

function worker_should_reload(): bool
{
    return !empty($GLOBALS['_worker_flags']['reload']);
}

function worker_clear_reload_flag(): void
{
    $GLOBALS['_worker_flags']['reload'] = false;
}

/**************************************
 * FUNCTION: WORKER METRIC INC
 **************************************/
function worker_metric_inc(string $workerName, string $key, int $delta = 1): void
{
    if (!isset($GLOBALS['_worker_state'][$workerName])) {
        return;
    }

    if (!isset($GLOBALS['_worker_state'][$workerName]['counters'][$key])) {
        $GLOBALS['_worker_state'][$workerName]['counters'][$key] = 0;
    }

    $GLOBALS['_worker_state'][$workerName]['counters'][$key] += $delta;
}

/**************************************
 * FUNCTION: WORKER METRIC SET
 **************************************/
function worker_metric_set(string $workerName, string $key, $value): void
{
    if (!isset($GLOBALS['_worker_state'][$workerName])) {
        return;
    }

    $GLOBALS['_worker_state'][$workerName]['gauges'][$key] = $value;
}

/***********************************
 * FUNCTION: WORKER SIGNAL HANDLER *
 ***********************************/
function worker_signal_handler(int $signo, string $workerName): void
{
    $pid  = getmypid();
    $host = gethostname();

    switch ($signo) {
        case SIGTERM:
        case SIGINT:
        case SIGQUIT:
            write_debug_log(
                "[$workerName] Shutdown signal received (signal $signo) on PID $pid at host $host, exiting gracefully...",
                "notice"
            );
            $GLOBALS['_worker_flags']['shutdown'] = true;
            break;

        case SIGHUP:
            write_debug_log(
                "[$workerName] Reload jobs signal received (SIGHUP) on PID $pid at host $host",
                "notice"
            );
            $GLOBALS['_worker_flags']['reload'] = true;
            break;

        default:
            write_debug_log(
                "[$workerName] Unknown signal $signo received on PID $pid at host $host",
                "warning"
            );
            break;
    }
}

/**
 * Get a job definition by type, reloading job definitions if the type is missing.
 *
 * @param string $jobType The job/promise type to look up
 * @param int $reloadAttempts How many times to try reloading before giving up
 * @return array|null The job definition array, or null if not found
 */
function worker_get_job_definition(string $jobType, int $reloadAttempts = 1): ?array
{
    static $jobMap = null;

    // Initialize job map on first call
    if ($jobMap === null) {
        $jobs = load_all_jobs();
        $jobMap = [];
        foreach ($jobs as $job) {
            $jobMap[$job['type']] = $job;
        }
        write_debug_log("Loaded " . count($jobMap) . " job definitions on first access.", "debug");
    }

    // Check if the jobType exists in cache
    if (isset($jobMap[$jobType])) {
        return $jobMap[$jobType];
    }

    // Reload loop if missing
    for ($i = 0; $i < $reloadAttempts; $i++) {
        write_debug_log("Job type '{$jobType}' missing, reloading job definitions (attempt " . ($i + 1) . ")...", "info");

        $jobs = load_all_jobs();
        foreach ($jobs as $job) {
            $jobMap[$job['type']] = $job; // update cache
        }

        if (isset($jobMap[$jobType])) {
            return $jobMap[$jobType];
        }
    }

    // Still missing after reload attempts
    write_debug_log("No job definition found for type '{$jobType}' after {$reloadAttempts} reload attempt(s).", "error");
    return null;
}

/**
 * Sleep for a given number of seconds, but wake early if a shutdown or reload signal is received.
 */
function worker_interruptible_sleep(int $seconds): void
{
    $start = time();
    while ((time() - $start) < $seconds) {
        if (worker_should_shutdown() || worker_should_reload()) break;
        sleep(1);
        if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
    }
}

?>