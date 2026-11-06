<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*****************************************************************************
 * Background task / queue worker tuning.                                    *
 *                                                                           *
 * Every value below can be overridden by a system administrator with a     *
 * define() in includes/config.php (documented, commented out, in            *
 * includes/config.sample.php). A constant that is absent, or not a whole    *
 * number, falls back to the built-in default; adding nothing is safe (the   *
 * defaults suit most installs). A value outside its range is clamped        *
 * to the nearest bound — never fatal, because a typo in config.php must not *
 * stop the workers.                                                         *
 *                                                                           *
 * The workers read config.php once, at start, and recycle themselves when   *
 * config.php changes (worker_config_changed_since() in workers.php), so an  *
 * edited value takes effect on the next respawn without a manual restart.   *
 *                                                                           *
 * Pure: no database, no logging, no other includes. queue_config() must be  *
 * callable from @group pure tests and from code that runs before the        *
 * database is reachable. Every consumer require_once's this file itself.    *
 *****************************************************************************/

/**
 * The tuning table: constant name => default, inclusive bounds, meaning.
 * Add a row here (and the matching commented line in config.sample.php —
 * QueueConfigTest enforces both) to make another value configurable.
 *
 * @return array<string, array{default:int, min:int, max:int, description:string}>
 */
function queue_config_table(): array
{
    return [
        'QUEUE_WORKER_MEMORY_SOFT_LIMIT_MB' => [
            'default' => 400, 'min' => 64, 'max' => 8192,
            'description' => 'PHP-tracked memory (MB) above which a worker exits cleanly between tasks and cron respawns it.',
        ],
        'QUEUE_WORKER_PHP_MEMORY_LIMIT_MB' => [
            'default' => 512, 'min' => 128, 'max' => 8192,
            'description' => 'Hard PHP memory_limit (MB) for the workers; raised to at least the soft limit + 64.',
        ],
        'QUEUE_WORKER_MAX_RUNTIME_MINUTES' => [
            'default' => 60, 'min' => 5, 'max' => 1440,
            'description' => 'Minutes a worker runs before it exits and cron respawns it.',
        ],
        'QUEUE_WORKER_MAX_IDLE_MINUTES' => [
            'default' => 60, 'min' => 5, 'max' => 1440,
            'description' => 'Minutes a worker may sit with no work before it exits and cron respawns it.',
        ],
        'QUEUE_WORKER_LOOP_DELAY_SECONDS' => [
            'default' => 3, 'min' => 1, 'max' => 60,
            'description' => 'Seconds the queue worker sleeps between tasks and between empty polls.',
        ],
        'PROMISE_WORKER_LOOP_DELAY_SECONDS' => [
            'default' => 5, 'min' => 1, 'max' => 60,
            'description' => 'Seconds the step (promise) worker sleeps between batches.',
        ],
        'QUEUE_STUCK_TASK_MINUTES' => [
            'default' => 30, 'min' => 5, 'max' => 1440,
            'description' => 'The stuck-task window: how long work whose worker is gone shows as running before it is requeued (work a live worker holds is never requeued), how long a worker database connection may sit idle before the database closes it (at least 15 minutes), and the shortest time (never less than 30) after a cancel that Delete waits.',
        ],
        'QUEUE_RECOVERY_MAX_ATTEMPTS' => [
            'default' => 5, 'min' => 1, 'max' => 20,
            'description' => 'Times a task or step that stopped part-way (its worker crashed, ran out of memory, was killed or lost its connection) is put back in the queue before it is marked failed instead (its failure handling and the admin notification then run).',
        ],
        'QUEUE_RETRY_MAX_ATTEMPTS' => [
            'default' => 5, 'min' => 0, 'max' => 20,
            'description' => 'Retries after a failure before a task is marked failed (a step gets this many attempts in total).',
        ],
        'QUEUE_RETRY_BASE_DELAY_SECONDS' => [
            'default' => 5, 'min' => 1, 'max' => 3600,
            'description' => 'Wait (seconds) before the first retry; doubles with every further retry.',
        ],
        'QUEUE_RETRY_MAX_DELAY_SECONDS' => [
            'default' => 3600, 'min' => 1, 'max' => 86400,
            'description' => 'Longest wait (seconds) between two retries; never below the base delay.',
        ],
        'PROMISE_WORKER_BATCH_SIZE' => [
            'default' => 20, 'min' => 1, 'max' => 200,
            'description' => 'Ready steps the step (promise) worker picks up per batch.',
        ],
        'WORKER_STALE_LOCK_SECONDS' => [
            'default' => 86400, 'min' => 300, 'max' => 604800,
            'description' => 'Seconds a running worker may go without a heartbeat before a new worker on the same host treats it as hung, stops it and takes its lock; keep it well above the longest single task.',
        ],
        'QUEUE_WORKER_COUNT' => [
            'default' => 1, 'min' => 1, 'max' => 10,
            'description' => 'Queue workers that may run at the same time across the whole installation; each run of a worker cron entry starts at most one more (cron.php has one entry per configured worker).',
        ],
        'PROMISE_WORKER_COUNT' => [
            'default' => 1, 'min' => 1, 'max' => 10,
            'description' => 'Step (promise) workers that may run at the same time across the whole installation; started the same way as the queue workers.',
        ],
    ];
}

/**
 * The effective value of a tuning setting.
 *
 * The config.php constant wins when it is defined and a whole number (an int,
 * or a string/float holding one); anything else falls back to the default. The
 * result is clamped to the row's bounds, then two cross-setting rules apply:
 * QUEUE_RETRY_MAX_DELAY_SECONDS is never below QUEUE_RETRY_BASE_DELAY_SECONDS,
 * and QUEUE_WORKER_PHP_MEMORY_LIMIT_MB is never below the soft limit + 64 (so
 * it can reach 8256 when the soft limit is at its 8192 maximum — the hard limit
 * must leave the soft one room to trigger a clean exit first).
 *
 * $lookup is a TEST SEAM: fn(string $constant): mixed, returning null for "not
 * defined". Production callers omit it and the real constants are read.
 *
 * @throws InvalidArgumentException for a key that has no table row — a
 *         programming error (a typo would otherwise silently read a default).
 */
function queue_config(string $key, ?callable $lookup = null): int
{
    $table = queue_config_table();
    if (!isset($table[$key])) {
        throw new InvalidArgumentException("Unknown queue tuning setting '{$key}'.");
    }

    $lookup ??= static fn(string $name) => defined($name) ? constant($name) : null;
    $row = $table[$key];

    $value = queue_config_whole_number($lookup($key)) ?? $row['default'];
    $value = max($row['min'], min($row['max'], $value));

    if ($key === 'QUEUE_RETRY_MAX_DELAY_SECONDS') {
        $value = max($value, queue_config('QUEUE_RETRY_BASE_DELAY_SECONDS', $lookup));
    } elseif ($key === 'QUEUE_WORKER_PHP_MEMORY_LIMIT_MB') {
        $value = max($value, queue_config('QUEUE_WORKER_MEMORY_SOFT_LIMIT_MB', $lookup) + 64);
    }

    return $value;
}

/**
 * $value clamped to the bounds of setting $key's table row. Used for values
 * that do not come from config.php but must stay inside the same limits: a
 * task's stored retry_base_delay / retry_max_delay and a job definition's
 * max_retry_attempts. No cross-setting rules apply here (the caller keeps max
 * >= base itself).
 *
 * @throws InvalidArgumentException for a key that has no table row.
 */
function queue_config_clamp(string $key, int $value): int
{
    $table = queue_config_table();
    if (!isset($table[$key])) {
        throw new InvalidArgumentException("Unknown queue tuning setting '{$key}'.");
    }
    return max($table[$key]['min'], min($table[$key]['max'], $value));
}

/**
 * The tuning constants config.php actually defines, for the workers' start-up
 * log: key => ['raw' => the defined value, 'effective' => queue_config()'s
 * result, 'status' => 'ok' | 'clamped' | 'ignored']. 'ignored' means the value
 * is not a whole number, so the default is used; 'clamped' means the effective
 * value differs from the number configured (a bound, or a cross-setting rule
 * such as max delay >= base delay). Undefined constants are omitted. Pure.
 *
 * $lookup is the same TEST SEAM as queue_config()'s.
 *
 * @return array<string, array{raw:mixed, effective:int, status:string}>
 */
function queue_config_overrides(?callable $lookup = null): array
{
    $lookup ??= static fn(string $name) => defined($name) ? constant($name) : null;
    $out = [];
    foreach (array_keys(queue_config_table()) as $key) {
        $raw = $lookup($key);
        if ($raw === null) {
            continue;
        }
        $effective = queue_config($key, $lookup);
        $parsed = queue_config_whole_number($raw);
        $status = $parsed === null ? 'ignored' : ($parsed === $effective ? 'ok' : 'clamped');
        $out[$key] = ['raw' => $raw, 'effective' => $effective, 'status' => $status];
    }
    return $out;
}

/**
 * Start-up log lines for a worker: one 'warning' line per configured value that
 * was ignored or clamped (an administrator's config.php edit is not doing what
 * they meant), and one 'info' line listing every effective value that differs
 * from the built-in default. Returns [[message, level], ...]; the cron drivers
 * pass each to write_debug_log(). Pure, so it is testable without a database.
 *
 * @return list<array{0:string, 1:string}>
 */
function queue_config_startup_log_lines(?callable $lookup = null): array
{
    $lines = [];
    foreach (queue_config_overrides($lookup) as $key => $o) {
        $raw = is_scalar($o['raw']) ? var_export($o['raw'], true) : gettype($o['raw']);
        if ($o['status'] === 'ignored') {
            $lines[] = ["{$key} configured as {$raw} in config.php is not a whole number; ignored, using {$o['effective']}.", 'warning'];
        } elseif ($o['status'] === 'clamped') {
            $lines[] = ["{$key} configured as {$raw} in config.php is out of range; clamped to {$o['effective']}.", 'warning'];
        }
    }

    $non_default = [];
    foreach (queue_config_table() as $key => $row) {
        $effective = queue_config($key, $lookup);
        if ($effective !== $row['default']) {
            $non_default[] = "{$key}={$effective}";
        }
    }
    if ($non_default) {
        $lines[] = ['Queue tuning from config.php: ' . implode(', ', $non_default) . '.', 'info'];
    }

    return $lines;
}

/**
 * $raw as an int when it is a whole number, else null. Accepts ints, integer
 * strings ("400", " 400 ", "-3") and integral floats (400.0). Rejects booleans,
 * fractional or non-finite floats, and anything non-numeric ("400M", "", null).
 */
function queue_config_whole_number(mixed $raw): ?int
{
    if (is_int($raw)) {
        return $raw;
    }
    if (is_float($raw)) {
        return (is_finite($raw) && floor($raw) === $raw && abs($raw) < PHP_INT_MAX) ? (int)$raw : null;
    }
    if (is_string($raw) && preg_match('/^\s*-?\d{1,18}\s*$/', $raw)) {
        return (int)trim($raw);
    }
    return null;
}

/**
 * Seconds to wait before retry number $attempt (1-based): base * 2^(attempt-1),
 * capped at $max. Shared by queue-task and step (promise) retries.
 */
function queue_retry_delay(int $attempt, int $base, int $max): int
{
    $delay = max(0, $base);
    $max = max(0, $max);
    // Double step by step and stop at the cap, so no attempt count or base
    // can overflow an int.
    for ($i = 1; $i < $attempt && $delay < $max && $delay > 0; $i++) {
        $delay = $delay > intdiv(PHP_INT_MAX, 2) ? $max : $delay * 2;
    }
    return min($delay, $max);
}

?>
