<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Helpers for regulating how db_open() takes database connections: a bounded
 * retry for MySQL's "Too many connections" (error 1040), and an optional
 * per-host cap on concurrent web connections. Kept free of any database or
 * settings access (db_open() calls these while it has no connection), so every
 * function here can be exercised without a database.
 */

/**
 * Read an integer connection-regulation constant from config.php.
 *
 * @param string $name    The constant name, for example DB_MAX_CONCURRENT_CONNECTIONS
 * @param int    $default Value used when the constant is not defined
 * @return int            The value, never negative
 */
function db_connection_setting(string $name, int $default): int
{
    return defined($name) ? max(0, (int) constant($name)) : $default;
}

/**
 * Whether a failed connection is MySQL refusing it because max_connections is
 * reached (error 1040). PDO reports a failed connect with the driver error
 * number as the exception code and in the message, and only populates
 * errorInfo for errors after the connection exists, so check all three.
 */
function db_is_too_many_connections(PDOException $e): bool
{
    if (is_array($e->errorInfo) && isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1040) {
        return true;
    }

    return $e->getCode() === 1040 || strpos($e->getMessage(), '[1040]') !== false;
}

/**
 * Milliseconds to wait before retry number $attempt (1-based): the base delay
 * doubled each attempt, capped at 5 seconds, then spread by +/- $jitter (a
 * fraction, 0 for none) so requests that failed together do not retry together.
 */
function db_connect_retry_delay_ms(int $attempt, int $base_ms, float $jitter = 0.25): int
{
    $attempt = max(1, $attempt);
    $delay = min(5000, $base_ms * (2 ** ($attempt - 1)));

    if ($jitter > 0 && $delay > 0) {
        $delay += (int) round($delay * $jitter * (random_int(-100, 100) / 100));
    }

    return max(0, $delay);
}

/**
 * Run $connect (which opens the connection and returns it), retrying when
 * MySQL refuses it with error 1040. Up to $max_retries more attempts are made,
 * pausing db_connect_retry_delay_ms() between them; any other failure, or the
 * last 1040, is rethrown unchanged for the caller to handle.
 *
 * @param callable      $connect     Opens and returns the connection; may throw PDOException
 * @param int           $max_retries Retries after the first attempt (0 = no retry)
 * @param int           $base_ms     First pause in milliseconds
 * @param callable|null $sleep_ms    Receives each pause in milliseconds; defaults to usleep()
 * @param float         $jitter      Passed to db_connect_retry_delay_ms()
 * @return mixed                     Whatever $connect returned
 * @throws PDOException
 */
function db_connect_with_retry(callable $connect, int $max_retries, int $base_ms, ?callable $sleep_ms = null, float $jitter = 0.25)
{
    $sleep_ms ??= static function (int $ms): void {
        usleep($ms * 1000);
    };

    for ($attempt = 0; ; $attempt++) {
        try {
            return $connect();
        } catch (PDOException $e) {
            if ($attempt >= $max_retries || !db_is_too_many_connections($e)) {
                throw $e;
            }

            $sleep_ms(db_connect_retry_delay_ms($attempt + 1, $base_ms, $jitter));
        }
    }
}

/**
 * Take one of $max connection slots, each a flock() on its own file in $dir.
 * The lock is released when the returned handle is closed, and PHP closes it
 * (and the kernel drops the lock) when the process ends or dies, so a crashed
 * request never leaks a slot. One caveat: a child process started from the
 * request (exec, proc_open) inherits the descriptor and keeps the slot until it
 * exits, so web requests must not start long-lived children.
 *
 * @param int    $max     Number of slots (> 0)
 * @param string $dir     Directory the lock files live in
 * @param string $key     Names this pool, so two installs on one host do not share slots
 * @param int    $wait_ms How long to wait for a slot to free up
 * @return array{status: string, handle: resource|null}
 *         status 'acquired' (hold the handle for the life of the connection),
 *         'timeout' (every slot stayed busy) or 'unavailable' (the lock files
 *         could not be opened at all; the caller should fail open rather than
 *         refuse every request over a broken temp directory)
 */
function db_acquire_connection_slot(int $max, string $dir, string $key, int $wait_ms): array
{
    if ($max < 1) {
        return ['status' => 'unavailable', 'handle' => null];
    }

    $deadline = microtime(true) + ($wait_ms / 1000);
    $key = preg_replace('/[^A-Za-z0-9]/', '', $key);

    do {
        $opened_any = false;
        // Start at a random slot so requests do not all queue on slot 0
        $first = random_int(0, $max - 1);

        for ($i = 0; $i < $max; $i++) {
            $path = $dir . '/simplerisk-db-slot-' . $key . '-' . (($first + $i) % $max) . '.lock';

            // The names are predictable and the directory is usually the
            // shared temp dir, so never follow a link planted at one
            if (is_link($path)) {
                continue;
            }

            $existed = file_exists($path);
            $handle = @fopen($path, 'c');

            if ($handle === false) {
                continue;
            }

            $opened_any = true;

            // Let every PHP user on the host open the pool (Apache and a
            // php-fpm pool can run as different users). Only the creator's
            // chmod succeeds, so only the request that created the file tries.
            if (!$existed) {
                @chmod($path, 0666);
            }

            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return ['status' => 'acquired', 'handle' => $handle];
            }

            fclose($handle);
        }

        if (!$opened_any) {
            return ['status' => 'unavailable', 'handle' => null];
        }

        usleep(random_int(40000, 80000));
    } while (microtime(true) < $deadline);

    return ['status' => 'timeout', 'handle' => null];
}

/**
 * Tell the client the database is at its connection limit: 503 plus
 * Retry-After, instead of the 200 the outage page would otherwise be served
 * with. A no-op once output has started.
 */
function db_send_busy_headers(int $retry_after_seconds = 5): void
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: ' . max(1, $retry_after_seconds));
    }
}
