<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/queues.php'));
// worker_run_promise(): promise_try_claim(), process_promise().
require_once(realpath(__DIR__ . '/promises.php'));
// queue_config(): retry limits and other tuning, overridable from config.php.
require_once(realpath(__DIR__ . '/queue_config.php'));

$GLOBALS['_worker_state'] = [];
$GLOBALS['_worker_flags'] = [
    'shutdown' => false,
    'reload'   => false,
];

/**
 * Seconds a worker lock held on ANOTHER host may go without a heartbeat before
 * the slot is taken over (worker_lock_holder_alive()). Short on purpose: on
 * container and pod deployments the host name changes with every redeploy and
 * the old processes are killed without releasing their locks, so a long value
 * would keep the new container's workers (and its loader) from starting. A
 * worker heartbeats at least once a minute while idle and after every task,
 * so five missed heartbeats means it is gone or deep inside one long task.
 * Taking its slot in the second case only means one extra worker for a while,
 * which is harmless: every task and step is claimed (queue_claim_task(),
 * promise_try_claim()), and the loader runs under its own named lock
 * (queue_loader_lock_name()). Internal, not a config.php setting.
 */
if (!defined('WORKER_REMOTE_STALE_SECONDS')) {
    define('WORKER_REMOTE_STALE_SECONDS', 300);
}

/**
 * Whether the worker that holds lock $lock (the decoded {name}_lock setting:
 * pid, host, heartbeat) is still alive, as seen from host $host at $now.
 *
 * - Same host: the process is checked directly ($pid_running, default
 *   isProcessRunning()).
 * - Another host (or no host recorded): a PID there cannot be checked from
 *   here, so the holder counts as alive while its heartbeat is newer than
 *   $remote_stale_seconds (the callers pass WORKER_REMOTE_STALE_SECONDS).
 *
 * Before this, any lock held on another host counted as dead, so a second
 * web host's cron run took the lock at once and two workers ran.
 *
 * Pure apart from $pid_running.
 *
 * @param array<string,mixed> $lock
 */
function worker_lock_holder_alive(array $lock, string $host, int $now, int $remote_stale_seconds, ?callable $pid_running = null): bool
{
    $pid = (int)($lock['pid'] ?? 0);
    if ($pid <= 0) {
        return false;
    }

    $lock_host = isset($lock['host']) && is_string($lock['host']) ? $lock['host'] : '';
    if ($lock_host !== '' && $lock_host === $host) {
        $pid_running ??= static fn(int $p): bool => isProcessRunning($p);
        return (bool)$pid_running($pid);
    }

    $beat = is_numeric($lock['heartbeat'] ?? null) ? (int)$lock['heartbeat'] : 0;
    return $beat > 0 && ($now - $beat) < $remote_stale_seconds;
}

/**
 * Seconds a lock held on ANOTHER host may go without a heartbeat before it is
 * treated as dead (worker_lock_holder_alive()): WORKER_REMOTE_STALE_SECONDS.
 */
function worker_remote_lock_stale_seconds(): int
{
    return WORKER_REMOTE_STALE_SECONDS;
}

/** The decoded {name}_lock setting, read from the database (never the request cache). */
function worker_read_lock(string $name): array
{
    $lock = json_decode((string)get_setting("{$name}_lock", false, false), true);
    return is_array($lock) ? $lock : [];
}

/**
 * The longest a worker's database connection may sit idle before the server
 * closes it: max(900, $stuck_minutes * 60) — the stuck-task window
 * (QUEUE_STUCK_TASK_MINUTES, 30 minutes by default), never below 15 minutes.
 * Pure.
 *
 * Why: a worker whose host dies without closing its connection leaves that
 * connection — and the named locks on it (the claim of the task it was
 * running, a serial type lock, the loader lock) — on the server until the
 * server times it out. This bounds that to the stuck-task window, after which
 * the task is free to be claimed and recovered like any other stuck task. A
 * live handler that issues no database statement for that long is, by the
 * same definition, stuck. The worker only ever SHORTENS the server's own
 * value (worker_apply_wait_timeout()).
 */
function worker_session_wait_timeout(?int $stuck_minutes = null): int
{
    $stuck_minutes ??= queue_config('QUEUE_STUCK_TASK_MINUTES');
    return max(900, $stuck_minutes * 60);
}

/**
 * SET SESSION wait_timeout = LEAST(current, $seconds) on $db's connection: it
 * can only shorten the server's value, never lengthen it. Never throws.
 */
function worker_apply_wait_timeout(PDO $db, int $seconds): void
{
    try {
        // MySQL's SET does not take an expression over the variable itself
        // (LEAST(@@session.wait_timeout, n) is error 1232), so compare here.
        $current = (int)$db->query("SELECT @@session.wait_timeout")->fetchColumn();
        $target = max(1, $seconds);
        if ($current <= 0 || $target < $current) {
            $db->exec("SET SESSION wait_timeout = " . $target);
        }
    } catch (\Throwable $t) {
        write_debug_log("Could not set the worker connection's wait_timeout: " . $t->getMessage(), "warning");
    }
}

/**
 * Prepare a worker's database connection: call right after db_open() and after
 * every reconnect, in both workers and the loader. Applies the session
 * wait_timeout bound (worker_session_wait_timeout()). Never throws.
 */
function worker_prepare_connection(PDO $db): void
{
    worker_apply_wait_timeout($db, worker_session_wait_timeout());
}

/**
 * Whether $t is the database connection going away (MySQL 2006 "server has gone
 * away", 2013 "lost connection"; e.g. the server closed a worker connection
 * left idle past its wait_timeout during a long call). Pure.
 */
function worker_is_connection_lost(\Throwable $t): bool
{
    if ($t instanceof \PDOException && in_array($t->errorInfo[1] ?? null, [2006, 2013], true)) {
        return true;
    }
    // The driver's own wording, matched as whole phrases (a message that only
    // happens to contain the digits 2006 or 2013 is not a lost connection).
    return (bool)preg_match('/\b(2006|2013)\b.*\b(gone away|Lost connection)\b|MySQL server has gone away|Lost connection to MySQL server/i', $t->getMessage());
}

/**
 * Reconnect a worker to the database for real. db_close() does not clear
 * db_open()'s shared connection, so "close, then open" handed the dead
 * connection straight back; the shared connection is dropped first. Returns
 * the new connection, or exits: when the database cannot be reached db_open()
 * renders the outage page and exits the process (includes/healthcheck.php),
 * and cron starts a new worker later. The caller must run
 * worker_prepare_connection() on the new connection.
 */
function worker_reconnect_db(): PDO
{
    $GLOBALS['db_global'] = null;
    return db_open();
}

/*********************************
 * FUNCTION: ACQUIRE WORKER LOCK *
 *********************************/
function acquire_worker_lock(string $name, int $staleSeconds): bool
{
    // Acquire exclusive worker lock in database
    // Checks for existing PID, handles stale locks, kills if needed.
    // Callers that may race another acquirer for the same name go through
    // acquire_worker_slot(), which serializes this read-then-write.
    $lockKey = "{$name}_lock";
    $now = time();
    $host = gethostname();
    $pid = getmypid();

    // Load existing lock (uncached: another process may have written it)
    $lock = worker_read_lock($name);

    if (!empty($lock)) {
        $existingPid = (int)($lock['pid'] ?? 0);
        $existingHost = $lock['host'] ?? 'unknown';
        $lastHeartbeat = (int)($lock['heartbeat'] ?? 0);

        if (worker_lock_holder_alive($lock, (string)$host, $now, worker_remote_lock_stale_seconds())) {
            if (($now - $lastHeartbeat) < $staleSeconds) {
                write_debug_log(
                    "$name already running on host {$existingHost} (PID {$existingPid}), last heartbeat {$lastHeartbeat}",
                    "debug"
                );
                return false;
            }

            // Stale process detected
            write_debug_log(
                "$name stale lock detected (PID {$existingPid} on host {$existingHost}), attempting termination",
                "notice"
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
                "$name lock exists but its holder (PID {$existingPid} on host {$existingHost}) is not running, proceeding to acquire",
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

/**
 * The worker name of slot $slot (1-based) of worker type $base. Slot 1 keeps
 * the type's own name (cron_queue_worker), so its lock, heartbeat and metrics
 * settings are the ones every earlier release used; slot k > 1 is
 * "{$base}_{$k}" (cron_queue_worker_2, lock setting cron_queue_worker_2_lock).
 */
function worker_slot_name(string $base, int $slot): string
{
    return $slot <= 1 ? $base : "{$base}_{$slot}";
}

/**
 * The worker names of slots 1..$count of $base (at least slot 1).
 *
 * @return list<string>
 */
function worker_slot_names(string $base, int $count): array
{
    $names = [];
    for ($slot = 1; $slot <= max(1, $count); $slot++) {
        $names[] = worker_slot_name($base, $slot);
    }
    return $names;
}

/**
 * Take the first free worker slot of $base among slots 1..$count and return
 * its worker name (use it for heartbeats, metrics, restart records and the
 * lock release), or null when every slot is held by a live worker.
 *
 * This is how several workers of one kind run (QUEUE_WORKER_COUNT /
 * PROMISE_WORKER_COUNT): each cron run starts the script once, and the script
 * either fills one free slot or exits. So with the count raised, workers ramp
 * up one per cron run; nothing spawns processes. A worker in a slot above a
 * lowered count is not started again (every worker recycles when config.php
 * changes, and the new count is then in force).
 *
 * The scan-and-take runs under a MySQL named lock per worker type, so two cron
 * runs starting at the same moment (on one host or several) can never take
 * the same slot — the read-then-write in acquire_worker_lock() was not atomic
 * on its own. Liveness of an existing holder follows
 * worker_lock_holder_alive(), including the remote-host heartbeat rule.
 */
function acquire_worker_slot(string $base, int $count, int $staleSeconds, ?PDO $db = null): ?string
{
    $db ??= db_open();

    // The database must support named locks (they keep every task to one run).
    // When it refuses them nothing starts: logged here at 'error' so the
    // reason is in the debug log, not only in cron's own output.
    try {
        $mutex = queue_lock_name(queue_lock_database($db), 'worker-slot', $base);
        $taken = queue_lock_try($db, $mutex, 10);
    } catch (\Throwable $t) {
        write_debug_log("{$base}: the database refused the named lock workers need (GET_LOCK), so no worker starts: " . $t->getMessage(), "error");
        return null;
    }

    if (!$taken) {
        write_debug_log("{$base}: another process is taking a worker slot right now; exiting without one.", "info");
        return null;
    }

    try {
        foreach (worker_slot_names($base, $count) as $name) {
            if (acquire_worker_lock($name, $staleSeconds)) {
                return $name;
            }
        }
        return null;
    } finally {
        queue_lock_release($db, $mutex);
    }
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
    // Uncached: if another worker has taken this slot over (this one was
    // presumed dead), the request cache would still show our own lock and the
    // heartbeat would overwrite theirs.
    $lock = worker_read_lock($name);

    if (empty($lock)) return;

    $pid = $lock['pid'] ?? null;
    $host = $lock['host'] ?? null;

    if ($pid !== getmypid() || $host !== gethostname()) {
        // Another worker has taken this slot over (this one was presumed dead,
        // or sat in one task past the takeover rule). Stop after the current
        // task instead of running on as an extra worker, and warn once per ten
        // minutes rather than on every loop.
        static $warnedAt = [];
        if (!isset($warnedAt[$name]) || ($now - $warnedAt[$name]) >= 600) {
            $warnedAt[$name] = $now;
            write_debug_log(
                "$name: its slot is now held by another worker (PID " . ($pid ?? '?') . " on host " . ($host ?? '?') . "); this worker (PID " . getmypid() . " on host " . gethostname() . ") will exit after its current task.",
                "warning"
            );
        }
        $GLOBALS['_worker_flags']['shutdown'] = true;
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

    $lock = worker_read_lock($name);
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
    $lock = worker_read_lock($name);
    $zombies = [];

    if (!empty($lock)) {
        $pid = $lock['pid'] ?? null;
        $host = $lock['host'] ?? null;
        $heartbeat = $lock['heartbeat'] ?? 0;

        if ($pid && !worker_lock_holder_alive($lock, (string)gethostname(), time(), worker_remote_lock_stale_seconds()) && (time() - $heartbeat) > $staleSeconds) {
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
function isProcessRunning(int $pid, ?string $host = null): bool
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

/********************************************
 * FUNCTION: REGISTER WORKER SIGNAL HANDLERS
 ********************************************/
function register_worker_signal_handlers(string $workerName): void
{
    if (!function_exists('pcntl_signal')) {
        write_debug_log("[$workerName] pcntl_signal unavailable; signals will not be handled.", "notice");
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

/***********************************************
 * FUNCTION: WORKER RESTART REQUESTED SINCE
 * Pure decision helper: should a worker that
 * started at $workerStartTime exit because a
 * restart was requested at $flagTimestamp?
 * Strictly-greater comparison so a worker
 * started at or after the request (already on
 * new code) keeps running. Settings values
 * arrive as strings; an absent setting arrives
 * as false; anything non-numeric fails safe.
 ***********************************************/
function worker_restart_requested_since(int $workerStartTime, $flagTimestamp): bool
{
    if (!is_numeric($flagTimestamp)) {
        return false;
    }

    return ((int)$flagTimestamp) > $workerStartTime;
}

/***********************************************
 * FUNCTION: WORKER CONFIG CHANGED SINCE
 * Pure decision helper: should a worker that
 * started at $workerStartTime exit because
 * config.php (read once, at start) was edited
 * after it started? $configMtime is filemtime()
 * (false on a stat failure). Fails safe: a stat
 * failure, a non-number, or an mtime in the
 * future (clock skew, touch -d) never requests
 * a restart -- a future mtime would otherwise
 * make every freshly spawned worker exit at
 * once. Strictly greater than the start time,
 * so the respawned worker (started after the
 * edit) keeps running: no respawn storm.
 ***********************************************/
function worker_config_changed_since(int $workerStartTime, $configMtime, int $now): bool
{
    if (!is_int($configMtime)) {
        return false;
    }
    return $configMtime > $workerStartTime && $configMtime <= $now;
}

/***********************************************
 * FUNCTION: WORKER CONFIG FILE CHANGED SINCE
 * Stats config.php (uncached) and applies
 * worker_config_changed_since(). All three cron
 * drivers (queue worker, promise worker, queue
 * loader) call this each loop iteration and
 * exit when it is true, so cron respawns them
 * with the edited configuration — including the
 * queue tuning constants read by queue_config().
 * $configPath is a test seam (null = the real
 * includes/config.php).
 ***********************************************/
function worker_config_file_changed_since(int $workerStartTime, int $now, ?string $configPath = null): bool
{
    $configPath ??= __DIR__ . '/config.php';
    clearstatcache(true, $configPath);
    return worker_config_changed_since($workerStartTime, @filemtime($configPath), $now);
}

/***********************************************
 * FUNCTION: REQUEST WORKER RESTART
 * Writes the current Unix timestamp to the
 * worker_restart_requested_at setting. Every
 * running worker compares that flag against its
 * own start time each loop iteration and exits
 * gracefully when the flag is newer, so cron
 * respawns it with fresh code (the Laravel
 * queue:restart pattern). Idempotent — repeated
 * calls move the timestamp forward, which is
 * correct: any worker older than the latest
 * request must recycle. Host-agnostic: works
 * across multi-container/multi-host deployments
 * because the DB is the shared channel.
 ***********************************************/
function request_worker_restart(string $reason = 'deploy'): void
{
    $now = time();

    update_or_insert_setting('worker_restart_requested_at', (string)$now);

    write_debug_log(
        "Worker restart requested ({$reason}); running workers will exit gracefully on their next loop iteration and cron will respawn them with fresh code.",
        "notice"
    );
}

/***********************************************
 * FUNCTION: WORKER RESTART FLAG TIMESTAMP
 * Uncached read of worker_restart_requested_at.
 * The third get_setting() argument disables the
 * $GLOBALS request cache — essential in a
 * long-lived worker process, which would
 * otherwise pin the first value it read forever
 * and never see a new restart request. Returns
 * false when no request has ever been made.
 ***********************************************/
function worker_restart_flag_timestamp(): string|false
{
    return get_setting('worker_restart_requested_at', false, false);
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
        write_debug_log("Job type '{$jobType}' missing, reloading job definitions (attempt " . ($i + 1) . ")...", "warning");

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
 * Run a job's optional 'on_terminal_failure' hook.
 *
 * A job that wants to surface a failure to a user (a notification, an alert)
 * must do it HERE rather than from its own queue_check. queue_check runs on
 * every attempt, so notifying from there fires on the first recoverable blip —
 * a connection reset or a DNS hiccup — and the retry then succeeds, leaving the
 * user holding a permanent alert for work that actually completed. This hook
 * runs only once handle_queue_task_failure() has exhausted the retry budget and
 * marked the task 'failed', which is the point at which the failure is real.
 *
 * "The task exhausted its retries" is queue-framework knowledge, not per-job
 * knowledge — a job can only declare its limit ('max_retry_attempts'); the worker
 * counts the attempts. Keeping the decision here also means the next job that
 * wants this surface declares one callable instead of reimplementing the retry
 * accounting.
 *
 * The hook is best-effort: a notifier that throws must never mask the original
 * task failure or take the worker down, so everything is caught and logged.
 *
 * @param array<string,mixed> $job_def The job definition from worker_get_job_definition().
 * @param array<string,mixed> $task    The failed task row.
 */
function worker_run_terminal_failure_hook(array $job_def, PDO $db, array $task, string $errorMessage): void
{
    $hook = $job_def['on_terminal_failure'] ?? null;
    if (!is_callable($hook)) {
        return;
    }

    try {
        $hook($task, $db, $errorMessage);
    } catch (\Throwable $t) {
        write_debug_log(
            "on_terminal_failure hook for task #{$task['id']} ({$task['task_type']}) threw: " . $t->getMessage(),
            'error'
        );
    }
}

/**
 * Record a task attempt's failure and, only once it becomes terminal, run the
 * job's on_terminal_failure hook.
 *
 * Extracted out of cron_queue_worker.php's while(true) loop so this decision —
 * "is this failure terminal, and if so, tell the job" — is testable on its own,
 * independent of the loop's live-queue/signal-handling machinery it would
 * otherwise be entangled with. Both of the worker's failure paths (a handler
 * returning false, and a caught \Throwable) call this one function, so a
 * regression in either wiring point is caught by the same test.
 *
 * Retry limit: the explicit $maxRetryAttempts when given, else the job
 * definition's 'max_retry_attempts' (worker_job_max_retry_attempts()), else
 * QUEUE_RETRY_MAX_ATTEMPTS from config.php. null delays defer to the task's
 * payload and then config.php (see handle_queue_task_failure()).
 *
 * @param array<string,mixed> $job_def The job definition from worker_get_job_definition().
 * @param array<string,mixed> $task    The failed task row.
 */
function worker_handle_task_failure(array $job_def, PDO $db, array $task, string $errorMessage, ?int $maxRetryAttempts = null, ?int $baseRetryDelay = null, ?int $maxRetryDelay = null): void
{
    $maxRetryAttempts ??= worker_job_max_retry_attempts($job_def);
    if (handle_queue_task_failure($db, $task, $errorMessage, $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay)) {
        worker_run_terminal_failure_hook($job_def, $db, $task, $errorMessage);
        worker_notify_queue_task_failure($db, $task);
    }
}

/**
 * A job definition's own retry limit for its queue tasks, or null to use
 * QUEUE_RETRY_MAX_ATTEMPTS. A job declares 'max_retry_attempts' => N (an int,
 * clamped to the same 0..20 range as the config.php setting) when the global
 * limit is wrong for it; 0 means the first failure is terminal (the task is
 * marked failed, its on_terminal_failure hook runs and the admin is notified).
 * Anything other than an int is ignored. Applies to queue tasks only — a job's
 * promise steps keep the global limit.
 *
 * @param array<string,mixed> $job_def
 */
function worker_job_max_retry_attempts(array $job_def): ?int
{
    $value = $job_def['max_retry_attempts'] ?? null;
    if (!is_int($value)) {
        return null;
    }
    return queue_config_clamp('QUEUE_RETRY_MAX_ATTEMPTS', $value);
}

/**
 * Record the outcome of a queue_check that returned a non-false value.
 *
 * A job without 'stages' is finished when its handler returns, so the task is
 * marked 'completed' — only from 'pending' or 'in_progress', so a canceled or
 * already failed task is never flipped to completed.
 *
 * A job WITH 'stages' hands its work to a promise chain, so the task moves to
 * 'in_progress' and the promise worker finalizes it later — but only when the
 * task is still 'pending'. The handler may already have settled it: the AI risk
 * analysis completes the task itself when the risk no longer exists or no
 * capability is enabled, and a fast chain can be finalized to 'completed' by
 * the promise worker before this runs. Writing 'in_progress' unconditionally
 * reopened those tasks with no live chain, so stuck-task recovery reset them
 * to 'pending' every 30 minutes and they looped forever (SR-2410).
 *
 * The status is re-checked in the UPDATE itself (not from the stale $task row
 * the worker picked) so a concurrent finalize or cancel always wins. A task the
 * handler already set 'in_progress' is left alone, which also keeps its
 * updated_at from being bumped for no change. Claiming a task does not change
 * its status (queue_claim_task() only takes its named lock), so a stage job
 * whose handler leaves the task 'pending' still gets moved here.
 *
 * @param array<string,mixed> $job_def The job definition from worker_get_job_definition().
 * @param array<string,mixed> $task    The task row the handler ran for.
 */
function worker_record_task_success(array $job_def, PDO $db, array $task): void
{
    if (!empty($job_def['stages']) && is_array($job_def['stages'])) {
        $stmt = $db->prepare("UPDATE queue_tasks SET status = 'in_progress', updated_at = NOW() WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $task['id']]);
        if ($stmt->rowCount() === 0) {
            write_debug_log("Task #{$task['id']} was no longer pending after its handler returned; left its status as the handler set it.", "debug");
        }
        // The run recorded its outcome (also when the handler set the status).
        queue_task_clear_run_open($db, (int)$task['id']);
        return;
    }

    // Completed only from a live status, re-checked in the UPDATE: a task that
    // was canceled, or failed by a stuck-task recovery pass, while its handler
    // ran keeps that status (queue_update_status() guards only 'canceled').
    $stmt = $db->prepare("UPDATE queue_tasks SET status = 'completed', updated_at = NOW() WHERE id = :id AND status IN ('pending','in_progress')");
    $stmt->execute([':id' => $task['id']]);
    if ($stmt->rowCount() === 0) {
        write_debug_log("Task #{$task['id']} was not pending or in progress after its handler returned; left its status as it is.", "debug");
    }
    queue_task_clear_run_open($db, (int)$task['id']);
}

/**
 * Whether a job runs its queue tasks one at a time across every queue worker.
 *
 * SERIAL BY DEFAULT. Job definition key: 'concurrency' => 'parallel' opts a
 * job in to running its tasks on several queue workers at once (with
 * QUEUE_WORKER_COUNT above 1); no key, 'serial' or anything else means serial.
 * A job written before multi-worker queues assumed it was the only one of its
 * kind running, so an unclassified job (an Extra older than this Core
 * included) stays safe. Opt in only when tasks of the type are independent:
 * per-entity work with no shared state and no ordering between tasks.
 * Serial applies to the queue task's handler (queue_check) only, never to a
 * job's promise steps, and it is per job type (two different serial types may
 * run side by side). A serial task whose type is busy is simply left for later
 * — it is not failed and loses no retry.
 *
 * @param array<string,mixed> $job_def
 */
function worker_job_is_serial(array $job_def): bool
{
    return ($job_def['concurrency'] ?? null) !== 'parallel';
}

/**
 * Roll back a transaction left open on the worker's connection by a handler or
 * a step (it began one and returned or threw without ending it). Its writes
 * were never committed; rolling them back before the outcome is recorded and
 * before the claim is released is what keeps the outcome — and so "this ran"
 * — durable. Logged at 'warning': it is a bug in the job.
 *
 * Returns true when no transaction is open afterwards. When the rollback
 * fails (or a transaction is somehow still open), it logs at 'error', asks the
 * worker to exit after this unit of work, and returns false: the caller must
 * then NOT release its claim — releasing it over uncommitted writes would let
 * another worker run the task again. Ending the process closes the connection,
 * and the server then rolls the transaction back and drops the claims with it.
 * Never throws.
 */
function worker_rollback_leaked_transaction(PDO $db, string $what): bool
{
    try {
        if ($db->inTransaction()) {
            write_debug_log("A transaction was left open by {$what}; its uncommitted writes are rolled back.", "warning");
            $db->rollBack();
        }
        if (!$db->inTransaction()) {
            return true;
        }
        write_debug_log("The transaction left open by {$what} is still open after the rollback.", "error");
    } catch (\Throwable $t) {
        write_debug_log("Could not roll back the transaction left open by {$what}: " . $t->getMessage(), "error");
    }
    write_debug_log("Keeping the claim of {$what} and stopping this worker; closing its connection ends the transaction and releases the claim.", "error");
    $GLOBALS['_worker_flags']['shutdown'] = true;
    return false;
}

/**
 * A claimed task whose previous run never recorded an outcome (see the
 * RUN-OPEN MARKER note in queues.php): count it as a recovery with
 * queue_recover_crashed_run() — the same limit as stuck-task recovery
 * (QUEUE_RECOVERY_MAX_ATTEMPTS; 0 for a job with 'max_retry_attempts' => 0,
 * such as a workflow, which is failed on the first detection) — and, once it
 * is failed, run the job's terminal hook and the admin notification, as
 * worker_recover_stuck_tasks() does. The handler is not run.
 *
 * @param array<string,mixed>      $task
 * @param array<string,mixed>|null $job_def
 * @return array{task: array<string,mixed>, outcome: string} outcome 'recovered' | 'failed' | 'skipped'
 */
function worker_handle_crashed_run(PDO $db, array $task, ?array $job_def, int $now, string $workerName): array
{
    $limit = (is_array($job_def) && worker_job_max_retry_attempts($job_def) === 0) ? 0 : queue_config('QUEUE_RECOVERY_MAX_ATTEMPTS');
    $result = queue_recover_crashed_run($db, $task, $now, $limit);

    if ($result['outcome'] === 'failed') {
        worker_metric_inc($workerName, 'failed_queue_tasks');
        try {
            $payload = json_decode((string)($result['task']['payload'] ?? ''), true);
            $error = is_array($payload) && is_string($payload['last_error'] ?? null) ? $payload['last_error'] : 'Stopped without finishing';
            worker_run_terminal_failure_hook($job_def ?? [], $db, $result['task'], $error);
            worker_notify_queue_task_failure($db, $result['task']);
        } catch (\Throwable $t) {
            write_debug_log("Could not run the failure handling for task #{$task['id']}: " . $t->getMessage(), "error");
        }
    }

    return ['task' => $result['task'], 'outcome' => $result['outcome']];
}

/**
 * The queue worker's unit of work: claim the next runnable task, run its
 * handler, record the outcome, release the claim.
 *
 * The claim (queue_claim_next_runnable_task()) is a MySQL named lock per task,
 * held on $db's connection for the WHOLE processing — handler, then
 * worker_record_task_success() or the failure handling — and released in a
 * finally. No other worker can pick the task between the handler returning
 * and its status being written, so each task runs once however many workers
 * there are (and on a multi-host install). Serial job types
 * (worker_job_is_serial()) also hold their type lock for the same span.
 *
 * If the connection is lost while a handler runs, the server drops the claim;
 * the task may then be claimed again once it is pending and due. The claim's
 * re-read of the row is what keeps a task finished meanwhile from running
 * twice.
 *
 * A transaction left open by an earlier handler is rolled back first (the old
 * pick did the same, by failing to begin its own), so the claim's re-read is
 * never served from a stale snapshot.
 *
 * Returns null when nothing was claimed, else ['task' => the row,
 * 'outcome' => 'completed' | 'failed' | 'no_job_definition' | 'recovered' |
 * 'skipped']. 'failed' means this attempt failed (it may still be retried) or,
 * for a crashed run, that it was failed after its recoveries. 'recovered':
 * the task's previous run crashed (run_open still set), so it was counted and
 * backed off instead of run (worker_handle_crashed_run()).
 *
 * $job_def_for and $task_type_prefix are TEST SEAMS (defaults:
 * worker_get_job_definition(); no type restriction).
 *
 * @return array{task: array<string,mixed>, outcome: string}|null
 */
function worker_run_next_task(PDO $db, int $now, string $workerName, ?callable $job_def_for = null, ?string $task_type_prefix = null): ?array
{
    $job_def_for ??= static fn(string $type): ?array => worker_get_job_definition($type);

    // One lookup per type per pick: an unknown type would otherwise be
    // reloaded (and logged) once for the serial check and again below.
    $defs = [];
    $lookup = static function (string $type) use (&$defs, $job_def_for): ?array {
        if (!array_key_exists($type, $defs)) {
            $defs[$type] = $job_def_for($type);
        }
        return $defs[$type];
    };

    if (!worker_rollback_leaked_transaction($db, "an earlier task of {$workerName}")) {
        return null; // the worker exits; nothing is picked over an open transaction
    }

    $claim = queue_claim_next_runnable_task(
        $db,
        $now,
        100,
        static fn(string $type): bool => worker_job_is_serial($lookup($type) ?? []),
        $task_type_prefix
    );
    if ($claim === null) {
        return null;
    }

    $task = $claim['task'];
    try {
        $taskType = $task['task_type'];
        write_debug_log("Fetched task #{$task['id']} with type '{$taskType}'", "info");

        // === TASK LOOKUP USING KEYED JOB MAP ===
        $job_def = $lookup($taskType);

        // === CRASHED RUN? ===
        // The last run of this task never recorded an outcome (its worker died
        // in the handler: out of memory, a fatal error, killed). Running it
        // again right away could kill this worker too and stop the queue, so
        // it is counted as a recovery and backed off instead (bounded by
        // QUEUE_RECOVERY_MAX_ATTEMPTS, then failed with hook + notification).
        if (queue_task_run_open($task) !== null) {
            return worker_handle_crashed_run($db, $task, $job_def, $now, $workerName);
        }

        // === RUN-OPEN MARKER ===
        // Autocommitted before the handler; every outcome below removes it.
        queue_task_mark_run_open($db, (int)$task['id'], $now);

        if (!$job_def) {
            // Counts every failed attempt, retried or not, like failed_promises. Each
            // increment comes before the failure handler so a throwing terminal hook
            // cannot skip it.
            worker_metric_inc($workerName, 'failed_queue_tasks');
            handle_queue_task_failure($db, $task, "No job definition");
            return ['task' => $task, 'outcome' => 'no_job_definition'];
        }

        // === TASK HANDLER EXECUTION ===
        try {
            $handler = $job_def['queue_check'] ?? $job_def['task_check'] ?? null;
            if (!is_callable($handler)) throw new \RuntimeException("No valid handler for task #{$task['id']}");

            $result = $handler($task, $db);
            write_debug_log("Task #{$task['id']} handler returned: " . var_export($result, true), "debug");

            // A handler that began a transaction and never ended it: roll its
            // uncommitted work back now, so the outcome below is committed on
            // its own. Written inside the leaked transaction, the 'completed'
            // would vanish once the claim is released and another worker would
            // find the task 'pending' and run it again.
            worker_rollback_leaked_transaction($db, "task #{$task['id']} ({$taskType})");

            if ($result === false) {
                // worker_handle_task_failure() only surfaces the failure to a user
                // once retries are exhausted — handle_queue_task_failure() (which it
                // calls) returns true exactly then.
                worker_metric_inc($workerName, 'failed_queue_tasks');
                worker_handle_task_failure($job_def, $db, $task, "Handler returned false");
                return ['task' => $task, 'outcome' => 'failed'];
            }

            // Stage jobs move to in_progress only if the handler left the task
            // pending; never reopen one it already completed (SR-2410).
            worker_record_task_success($job_def, $db, $task);
            return ['task' => $task, 'outcome' => 'completed'];
        } catch (\Throwable $t) {
            write_debug_log("Unexpected error processing task #{$task['id']}: " . $t->getMessage(), "error");
            // A handler that throws inside its own transaction would otherwise
            // leave the failure bookkeeping below inside it.
            worker_rollback_leaked_transaction($db, "task #{$task['id']} ({$taskType})");
            // Same retry/backoff policy as the false-return path above — left to the
            // function's defaults on both so the two can't drift.
            worker_metric_inc($workerName, 'failed_queue_tasks');
            worker_handle_task_failure($job_def, $db, $task, $t->getMessage());
            return ['task' => $task, 'outcome' => 'failed'];
        }
    } finally {
        // Backstop for any path above that left a transaction open: never
        // release the claim over uncommitted status writes. When the rollback
        // fails the claim is kept and the worker exits (see the helper).
        if (worker_rollback_leaked_transaction($db, "task #{$task['id']}")) {
            queue_release_claim($db, $claim);
        }
    }
}

/**
 * The step worker's unit of work for one fetched step: claim it
 * (promise_try_claim(): named lock plus a re-read with the fetch's own
 * readiness rule), run it with process_promise(), release the claim in a
 * finally. Several step workers fetch overlapping batches; the claim keeps
 * each step to one run, and the re-read hands process_promise() the step's
 * current payload rather than the batch's copy.
 *
 * Returns null when the step was not claimed (another worker has it, or it
 * already ran, was canceled or is backing off), else process_promise()'s
 * failed count (0 or 1). A step with no job definition goes to
 * handle_promise_failure() and counts 0, as the worker always did.
 *
 * $job_def_for and $now are TEST SEAMS (defaults: worker_get_job_definition();
 * time()).
 *
 * @param array<string,mixed> $promise A row from fetch_ready_promises().
 */
function worker_run_promise(PDO $db, array $promise, ?int $maxRetryAttempts = null, ?int $baseRetryDelay = null, ?int $maxRetryDelay = null, ?callable $job_def_for = null, ?int $now = null): ?int
{
    $job_def_for ??= static fn(string $type): ?array => worker_get_job_definition($type);
    $promise_id = (int)$promise['id'];

    $fresh = promise_try_claim($db, $promise_id, $now);
    if ($fresh === null) {
        return null;
    }

    try {
        // === LOOKUP JOB DEFINITION ===
        $jobDef = $job_def_for((string)$fresh['promise_type']);
        if (!$jobDef) {
            handle_promise_failure($db, $fresh, "No job definition", $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay);
            return 0;
        }

        // === PROCESS PROMISE ===
        return process_promise($fresh, $jobDef, $db, $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay, $now);
    } finally {
        // Never release the claim over uncommitted writes (process_promise()
        // already ends a transaction a stage leaked; this is the backstop).
        // When the rollback fails the claim is kept and the worker exits.
        if (worker_rollback_leaked_transaction($db, "step #{$promise_id}")) {
            promise_release_claim($db, $promise_id);
        }
    }
}

/**
 * The worker's stuck-task pass: recover_stuck_queue_tasks() re-queues tasks
 * stuck 'in_progress' with no live step chain, and marks failed those that
 * have used up their recoveries (QUEUE_RECOVERY_MAX_ATTEMPTS). Each task failed
 * here gets the same terminal handling as one that ran out of retries: the
 * job's on_terminal_failure hook, then the admin notification.
 *
 * A job that declares 'max_retry_attempts' => 0 (no automatic re-run, e.g. a
 * workflow whose actions are not safe to repeat) is not re-queued either: a
 * stuck task of that type is failed the first time it is found.
 *
 * $job_def_for is a test seam: fn(string $task_type): ?array (default:
 * worker_get_job_definition()).
 *
 * @return array{recovered:int, failed:list<array<string,mixed>>}
 */
function worker_recover_stuck_tasks(PDO $db, ?int $thresholdMinutes = null, ?int $maxRecoveries = null, ?callable $job_def_for = null): array
{
    $job_def_for ??= static fn(string $type): ?array => worker_get_job_definition($type);

    $result = recover_stuck_queue_tasks(
        $db,
        $thresholdMinutes,
        $maxRecoveries,
        static function (string $type) use ($job_def_for): ?int {
            // A definition that cannot be loaded falls back to the global limit
            // rather than aborting the whole recovery pass.
            try {
                $job_def = $job_def_for($type);
            } catch (\Throwable $t) {
                write_debug_log("Could not load the job definition for '{$type}' during stuck-task recovery: " . $t->getMessage(), "error");
                return null;
            }
            return (is_array($job_def) && worker_job_max_retry_attempts($job_def) === 0) ? 0 : null;
        }
    );

    // Each task is already committed as failed, so one task's problem (a job
    // definition that cannot be loaded, a DB error) must not cost the rest
    // their hook and notification.
    foreach ($result['failed'] as $task) {
        try {
            $job_def = $job_def_for((string)($task['task_type'] ?? '')) ?? [];
            $payload = json_decode((string)($task['payload'] ?? ''), true);
            $error = is_array($payload) && is_string($payload['last_error'] ?? null) ? $payload['last_error'] : 'Stuck task not recovered';
            worker_run_terminal_failure_hook($job_def, $db, $task, $error);
            worker_notify_queue_task_failure($db, $task);
        } catch (\Throwable $t) {
            write_debug_log("Could not run the failure handling for stuck task #" . ($task['id'] ?? '?') . ": " . $t->getMessage(), "error");
        }
    }

    return $result;
}

/**
 * Raise the admin notification for a permanently failed task. Never throws: a
 * notification problem must not break failure handling.
 */
function worker_notify_queue_task_failure(PDO $db, array $task): void
{
    try {
        require_once(realpath(__DIR__ . '/queue_monitor.php'));
        notify_queue_task_failure($db, $task);
    } catch (\Throwable $e) {
        write_debug_log("Could not notify about failed queue task #" . ($task['id'] ?? '?') . ": " . $e->getMessage(), 'warning');
    }
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