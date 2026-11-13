<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));
// run_as_user_for_queue() below calls set_user_permissions(); functions.php
// already pulls authenticate.php into scope, but per CLAUDE.md's reachability
// rule a direct consumer declares its own require_once.
require_once(realpath(__DIR__ . '/authenticate.php'));
// queue_config(): retry/backoff defaults, overridable from config.php.
require_once(realpath(__DIR__ . '/queue_config.php'));

/**
 * Queue priority of core_document_update. Keyword extraction / TF-IDF is
 * background enrichment, so it sits at the low tier (SR-2411); it was 100
 * ("do immediately"), which let every retry jump ahead of all other work. Every
 * enqueue site and the job's task_check use this so they cannot drift apart.
 */
const CORE_DOCUMENT_UPDATE_PRIORITY = 25;

/**
 * Task types whose FINISHED queue_tasks rows are program state, so the
 * retention purge (purge_finished_queue_tasks()) never deletes them, in any
 * status. Add a type here whenever code reads its completed/failed history
 * back to decide what to do next.
 *
 * - ai_risk_fair_analyze: the AI Extra derives its re-run gate from this
 *   history (extras/artificial_intelligence/index.php
 *   ai_risk_last_analysis_at(), ai_risk_batch_queue_history(),
 *   ai_risk_stored_fingerprint(), ai_risk_consecutive_failures()). Without the
 *   completed rows a risk looks never analysed and is re-analysed with paid
 *   AI calls; without the failed rows the three-strikes bound counts fewer
 *   failures (retention is configurable down to one day).
 */
if (!defined('QUEUE_PURGE_EXEMPT_TASK_TYPES')) {
    define('QUEUE_PURGE_EXEMPT_TASK_TYPES', ['ai_risk_fair_analyze']);
}

/*************************************************************
 * FUNCTION: QUEUE TASK                                      *
 * Inserts a new task into the queue_tasks table             *
 * PRIORITY                                                  *
 * 100 = Do Immediately (Ex: send_email)                     *
 * 75 = Important, but can wait (Ex: ai_document_install)    *
 * 50 = Wait until spare cycles (Ex: core_ai_context_update) *
 * 25 = Not a high priority (Ex: core_queue_purge)           *
 * 0 = Lowest possible priority (No Examples)                *
 *                                                           *
 * RETRY BACKOFF                                             *
 * $baseDelay / $maxDelay set this task's retry backoff      *
 * (handle_queue_task_failure()). A value that differs from  *
 * the BUILT-IN default (QUEUE_RETRY_BASE_DELAY_SECONDS /    *
 * QUEUE_RETRY_MAX_DELAY_SECONDS in queue_config_table()) is *
 * stored in the payload as retry_base_delay /               *
 * retry_max_delay and wins over config.php. null, or the    *
 * built-in default itself (the 5, 3600 most call sites pass *
 * by rote), stores nothing, so the task follows whatever    *
 * config.php sets when it fails. The comparison is against  *
 * the built-in default on purpose: comparing against the    *
 * configured value would pin every rote "5, 3600" caller    *
 * to 5/3600 and silently override the administrator. A     *
 * payload that already carries the keys keeps them.         *
 *************************************************************/
function queue_task(
    PDO $db,
    string $task_type,
    array $payload = [],
    int $priority = 0,
    ?int $baseDelay = null,
    ?int $maxDelay = null
): bool {
    try {
        // Initialize retry metadata in payload
        $payload['retry_attempts'] = $payload['retry_attempts'] ?? 0;
        $payload['next_retry_at'] = $payload['next_retry_at'] ?? date('Y-m-d H:i:s');
        $payload['last_error'] = $payload['last_error'] ?? null;

        // Per-task backoff override (see RETRY BACKOFF above), clamped to the
        // same bounds config.php values are. A key the caller's payload already
        // carries is kept but clamped too (or dropped when it is not a whole
        // number), so nothing out of range is ever stored.
        $table = queue_config_table();
        foreach ([
            'retry_base_delay' => [$baseDelay, 'QUEUE_RETRY_BASE_DELAY_SECONDS'],
            'retry_max_delay'  => [$maxDelay, 'QUEUE_RETRY_MAX_DELAY_SECONDS'],
        ] as $payload_key => [$value, $setting]) {
            if (array_key_exists($payload_key, $payload)) {
                $existing = queue_config_whole_number($payload[$payload_key]);
                if ($existing === null) {
                    unset($payload[$payload_key]);
                } else {
                    $payload[$payload_key] = queue_config_clamp($setting, $existing);
                }
            } elseif ($value !== null && $value !== $table[$setting]['default']) {
                $payload[$payload_key] = queue_config_clamp($setting, $value);
            }
        }

        $stmt = $db->prepare("
            INSERT INTO queue_tasks (task_type, payload, status, priority, created_at, updated_at)
            VALUES (:task_type, :payload, 'pending', :priority, NOW(), NOW())
        ");

        $stmt->execute([
            ':task_type' => $task_type,
            ':payload'   => json_encode($payload),
            ':priority'  => $priority,
        ]);

        write_debug_log("Queued task '{$task_type}' with priority {$priority} successfully.", 'info');

        return true;

    } catch (Exception $e) {
        write_debug_log("Failed to queue task '{$task_type}': " . $e->getMessage(), 'error');
        return false;
    }
}

/***************************
 * FUNCTION: LOAD ALL JOBS *
 ***************************/
function load_all_jobs(): array
{
    $jobs = [];

    // Helper function to load jobs from a directory
    $load_jobs_from_dir = function(string $dir, string $label) use (&$jobs) {
        $index_file = $dir . '/index.php';
        if (!file_exists($index_file)) {
            write_debug_log("{$label} jobs index file not found: " . basename($index_file), "warning");
            return;
        }

        $job_list = include($index_file);
        if (!is_array($job_list)) {
            write_debug_log("Invalid {$label} jobs index format in: " . basename($index_file), "warning");
            return;
        }

        foreach ($job_list as $job_name => $enabled) {
            if (!$enabled) {
                write_debug_log("Skipping disabled {$label} job: {$job_name}", "debug");
                continue;
            }

            $file = $dir . '/' . $job_name . '.php';
            if (file_exists($file)) {
                $job_def = include($file);
                if (is_array($job_def)) {
                    $jobs[] = $job_def;
                    write_debug_log("Loaded {$label} job module: {$job_name}", "debug");
                } else {
                    write_debug_log("Skipped invalid {$label} job module: {$job_name}", "warning");
                }
            } else {
                write_debug_log("{$label} job module file not found: " . basename($file), "warning");
            }
        }
    };

    // --- Load Core Jobs ---
    $coreDir = realpath(__DIR__ . '/jobs');
    if (is_dir($coreDir)) {
        $load_jobs_from_dir($coreDir, 'Core');
    }

    // --- Load AI Extra Jobs ---
    $aiDir = realpath(__DIR__ . '/../extras/artificial_intelligence/jobs');
    if (is_dir($aiDir)) {
        require_once(realpath(__DIR__ . '/../extras/artificial_intelligence/index.php'));
        $load_jobs_from_dir($aiDir, 'AI Extra');
    }

    // --- Load SCF Extra Jobs ---
    $scfDir = realpath(__DIR__ . '/../extras/complianceforgescf/jobs');
    if (is_dir($scfDir)) {
        require_once(realpath(__DIR__ . '/../extras/complianceforgescf/index.php'));
        $load_jobs_from_dir($scfDir, 'SCF Extra');
    }

    // --- Load Import-Export Extra Jobs ---
    $importExportDir = realpath(__DIR__ . '/../extras/import-export/jobs');
    if (is_dir($importExportDir)) {
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
        $load_jobs_from_dir($importExportDir, 'Import-Export Extra');
    }

    // --- Load Encryption Extra Jobs ---
    $encryptionDir = realpath(__DIR__ . '/../extras/encryption/jobs');
    if (is_dir($encryptionDir)) {
        require_once(realpath(__DIR__ . '/../extras/encryption/index.php'));
        $load_jobs_from_dir($encryptionDir, 'Encryption Extra');
    }

    write_debug_log("Loaded " . count($jobs) . " total job definitions.", "debug");

    return $jobs;
}

/******************************************************************************************************************
 * FUNCTION: GET QUEUE ITEMS                                                                                      *
 * Get a list of items currently in the queue, optionally filtered by task type and/or status(es).                *
 ******************************************************************************************************************/
function get_queue_items(PDO $db, string|array|null $task_type = null, string|array|null $status = null): array
{
    if (!$db) {
        write_debug_log("GET_QUEUE_ITEMS: Failed to open DB connection.", "error");
        return [];
    }

    try {
        $query = "SELECT * FROM queue_tasks WHERE 1=1";
        $params = [];

        if (!empty($task_type)) {
            if (is_array($task_type)) {
                $likeClauses = [];
                foreach ($task_type as $type) {
                    $likeClauses[] = "task_type LIKE ?";
                    $params[] = '%' . $type . '%';
                }
                $query .= " AND (" . implode(" OR ", $likeClauses) . ")";
            } else {
                $query .= " AND task_type LIKE ?";
                $params[] = '%' . $task_type . '%';
            }
        }

        if (!empty($status)) {
            if (is_array($status)) {
                $placeholders = implode(',', array_fill(0, count($status), '?'));
                $query .= " AND status IN ($placeholders)";
                $params = array_merge($params, $status);
            } else {
                $query .= " AND status = ?";
                $params[] = $status;
            }
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $results ?: [];

    } catch (Exception $e) {
        write_debug_log("GET_QUEUE_ITEMS: Error retrieving queue items: " . $e->getMessage(), "error");
        return [];
    }
}

/**
 * FUNCTION: QUEUE UPDATE STATUS
 * Update the status of a queue task and log the result.
 */
function queue_update_status($task_id, $status, PDO $db): bool {
    write_debug_log("queue_update_status called for task #{$task_id} with status '{$status}'", "debug");

    try {
        // A user cancel (Queue Monitor) is final: nothing the worker does afterwards
        // (post-handler completed/in_progress, terminal failed) may move a task out of
        // 'canceled'. Only an explicit 'canceled' write may touch a canceled row.
        //
        // 'in_progress' is written only over 'pending': a stage job calls this
        // after building its chain, and a fast chain may already have been
        // finalized (completed) or failed by then — reopening it as in_progress
        // left it with no live chain, looping through stuck-task recovery
        // (SR-2410). Recovery paths that set a status use their own UPDATEs.
        $guard = match ($status) {
            'canceled'    => '',
            'in_progress' => " AND status = 'pending'",
            default       => " AND status <> 'canceled'",
        };
        $stmt = $db->prepare("UPDATE queue_tasks SET status = :status, updated_at = NOW() WHERE id = :id{$guard}");
        $stmt->execute([':status' => $status, ':id' => $task_id]);

        $affected = $stmt->rowCount();

        if ($affected > 0) {
            write_debug_log("queue_update_status successfully updated task #{$task_id} (rows affected: {$affected})", "debug");
            return true;
        } else {
            $check = $db->prepare("SELECT status FROM queue_tasks WHERE id = :id");
            $check->execute([':id' => $task_id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $current = (string)$row['status'];
                write_debug_log($current === $status
                    ? "queue_update_status: task #{$task_id} already had status '{$status}'"
                    : "queue_update_status: task #{$task_id} left as '{$current}' (not changed to '{$status}')", "debug");
                return true;
            } else {
                write_debug_log("queue_update_status: task #{$task_id} does not exist", "error");
                return false;
            }
        }
    } catch (Exception $e) {
        write_debug_log("queue_update_status failed for task #{$task_id}: " . $e->getMessage(), "error");
        return false;
    }
}

/**************************************************************************
 * FUNCTION: RUN TIMESTAMPED QUEUE CHECK                                  *
 * Shared queue_check wrapper for periodic jobs whose task_check is      *
 * gated by a queue_timestamp_last_* setting.                            *
 *                                                                        *
 * Stamps the gate timestamp at the START of every attempt so the        *
 * cadence holds regardless of outcome — stamping only on success would  *
 * requeue a failing job on every worker tick, a per-minute retry storm  *
 * against whatever the job talks to.                                     *
 *                                                                        *
 * Runs $body and marks the task completed when it returns true. On      *
 * failure ($body returns false or throws) the task status is left       *
 * alone: handle_queue_task_failure() in the worker owns bounded backoff *
 * retries and the final 'failed'. Job handlers must never pre-mark      *
 * their own task 'failed' — that dead-ends it before the retry          *
 * machinery (which only re-fetches 'pending' rows) ever sees it.        *
 **************************************************************************/
function run_timestamped_queue_check(array $task, PDO $db, string $timestamp_setting, string $log_prefix, callable $body): bool
{
    update_or_insert_setting($timestamp_setting, time(), db: $db);

    try {
        if ($body()) {
            queue_update_status($task['id'], 'completed', $db);
            return true;
        }
        return false;
    } catch (\Throwable $e) {
        write_debug_log("{$log_prefix}: Exception during queue task — " . $e->getMessage(), "error");
        return false;
    }
}

/**************************************************************************
 * FUNCTION: ACTIVE ACCOUNT USERNAME                                      *
 * The username for $uid if it is an active account (enabled, not locked  *
 * out — mirrors the login gate), or false. The single definition of      *
 * "active account" for queue-adjacent identity resolution: shared by     *
 * run_as_user_for_queue() (which impersonates it) and any caller that    *
 * only needs to know whether a candidate uid is currently usable         *
 * (e.g. ai_risk_context_uid()'s owner/manager/submitter fallback chain), *
 * so the predicate cannot drift between the two.                         *
 **************************************************************************/
function active_account_username(int $uid, PDO $db): string|false
{
    $stmt = $db->prepare("SELECT `username` FROM `user` WHERE `value` = :uid AND `enabled` = 1 AND `lockout` = 0 LIMIT 1;");
    $stmt->bindValue(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchColumn();
}

/**************************************************************************
 * FUNCTION: RUN AS USER FOR QUEUE                                        *
 * Run $body() with the identity + permissions of a specific user, then   *
 * ALWAYS restore the worker's original session.                          *
 *                                                                        *
 * The queue worker authenticates as "System User" (uid -1) with no       *
 * domain permissions. A job that reads permission-gated data on behalf   *
 * of the user who enqueued it — e.g. ai_get_context(), which enforces    *
 * the L2/L3/L4 authorization filters — must run that read as THAT user,  *
 * or the read is denied and the job silently produces nothing. This      *
 * helper impersonates $uid for the duration of $body() and restores the  *
 * prior $_SESSION in a finally, so the next task in the same worker loop *
 * never inherits the elevated identity (even if $body() throws).         *
 *                                                                        *
 * Not a privilege escalation: the enqueuing endpoint sets $uid from the  *
 * authenticated caller's OWN uid (never client-supplied), only an active *
 * account (enabled, not locked out) is impersonated, and the data the    *
 * impersonated read returns is still authz-filtered for that user. A     *
 * missing/disabled/locked-out $uid is refused — $body() does not run and *
 * $on_invalid (default false) is returned.                               *
 **************************************************************************/
function run_as_user_for_queue(int $uid, PDO $db, callable $body, mixed $on_invalid = false): mixed
{
    if ($uid <= 0) {
        // A missing/invalid uid is a malformed request from the caller (the
        // enqueue side always records a real uid) — an error, consistent with
        // how a job treats a missing required payload field.
        write_debug_log("run_as_user_for_queue: no user id supplied; refusing to impersonate.", "error");
        return $on_invalid;
    }

    // Only an active account may be impersonated.
    $username = active_account_username($uid, $db);
    if ($username === false) {
        // The requester exists no more / is disabled or locked out — an
        // expected operational condition that halts the workflow, so notice.
        write_debug_log("run_as_user_for_queue: user #{$uid} is not an active account; refusing to impersonate.", "notice");
        return $on_invalid;
    }

    // Snapshot the worker's session, impersonate, and always restore.
    // NOTE: set_user_permissions() has one non-session side effect — with the
    // Organizational Hierarchy Extra active it may normalize the impersonated
    // user's stored selected_business_unit (a DB write on their own row). That
    // write is intentional login-time behavior and is not reverted by the
    // session restore below; it is harmless here (the user's own UI preference,
    // the same correction their next login performs) and cannot be steered by
    // an attacker (uid is the requester's own, server-set).
    $saved_session = $_SESSION ?? [];
    try {
        set_user_permissions($username);
        return $body();
    } finally {
        $_SESSION = $saved_session;
    }
}

/**************************************************************************
 * FUNCTION: HANDLE QUEUE TASK FAILURE                                    *
 * Handle a failed queue task with exponential backoff and error storage. *
 *                                                                        *
 * Retry policy, per value, first match wins:                             *
 *   1. the explicit argument (non-null);                                 *
 *   2. the task's payload: retry_base_delay / retry_max_delay, written   *
 *      by queue_task() for a caller that asked for its own backoff;      *
 *   3. config.php via queue_config() (QUEUE_RETRY_MAX_ATTEMPTS,          *
 *      QUEUE_RETRY_BASE_DELAY_SECONDS, QUEUE_RETRY_MAX_DELAY_SECONDS).   *
 * The attempt limit has no payload source; a job definition's            *
 * 'max_retry_attempts' reaches it as the explicit argument from          *
 * worker_handle_task_failure(). The delay before retry k is              *
 * base * 2^(k-1), capped at max (and max is never below base). A limit   *
 * of N allows N retries, so N + 1 attempts; 0 fails on the first failure.*
 **************************************************************************/
function handle_queue_task_failure(PDO $db, array $task, string $errorMessage, ?int $maxRetryAttempts = null, ?int $baseRetryDelay = null, ?int $maxRetryDelay = null): bool
{
    // A task the user canceled (Queue Monitor) while its handler ran is not a
    // failure: no retry bookkeeping, no terminal 'failed', no hooks.
    if (queue_task_current_status($db, $task['id']) === 'canceled') {
        write_debug_log("Task #{$task['id']} failed after it was canceled; leaving it canceled.", "info");
        queue_task_clear_run_open($db, (int)$task['id']);
        return false;
    }

    $payload = json_decode($task['payload'] ?? '{}', true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $maxRetryAttempts ??= queue_config('QUEUE_RETRY_MAX_ATTEMPTS');
    // Payload values are data (an admin can edit a payload, an old row can
    // carry anything), so they are clamped to the settings' bounds before use:
    // an enormous base would park the task for decades, or overflow into a
    // next_retry_at strtotime() cannot parse; 0 would retry with no wait.
    // Explicit arguments are the caller's code and are used as given (tests
    // pass 0/1), apart from the final cap on the computed wait below.
    $stored_base = queue_config_whole_number($payload['retry_base_delay'] ?? null);
    $stored_max = queue_config_whole_number($payload['retry_max_delay'] ?? null);
    $baseRetryDelay ??= $stored_base !== null ? queue_config_clamp('QUEUE_RETRY_BASE_DELAY_SECONDS', $stored_base) : queue_config('QUEUE_RETRY_BASE_DELAY_SECONDS');
    $maxRetryDelay ??= $stored_max !== null ? queue_config_clamp('QUEUE_RETRY_MAX_DELAY_SECONDS', $stored_max) : queue_config('QUEUE_RETRY_MAX_DELAY_SECONDS');
    $baseRetryDelay = max(0, $baseRetryDelay);
    $maxRetryDelay = max($baseRetryDelay, $maxRetryDelay);

    // A hand-edited payload can hold any number; keep the +1 an int.
    $retryAttempts = min(max(0, (int)($payload['retry_attempts'] ?? 0)), PHP_INT_MAX - 1) + 1;
    // Never schedule further out than the longest allowed retry wait, whatever
    // the source, so next_retry_at is always a date strtotime() can read back.
    $nextRetryDelay = min(
        queue_retry_delay($retryAttempts, $baseRetryDelay, $maxRetryDelay),
        queue_config_table()['QUEUE_RETRY_MAX_DELAY_SECONDS']['max']
    );
    $nextRetryAt = date('Y-m-d H:i:s', time() + $nextRetryDelay);

    if ($retryAttempts <= $maxRetryAttempts) {
        write_debug_log("Task #{$task['id']} failed, scheduling retry #{$retryAttempts} at {$nextRetryAt}", "warning");
        // Only the three retry keys are written, onto the CURRENT row: $task is
        // the worker's copy from before the handler ran, and rewriting the whole
        // payload from it would wipe keys an admin set meanwhile (restarted_as,
        // original_priority). An empty payload stored as [] is turned into {}
        // first, since JSON_SET cannot add keys to an array (the retry count
        // would then never advance). A task canceled meanwhile is left alone.
        $stmt = $db->prepare("
            UPDATE queue_tasks
            SET payload = JSON_REMOVE(JSON_SET(
                    CASE WHEN JSON_TYPE(payload) = 'OBJECT' THEN payload ELSE JSON_OBJECT() END,
                    '$.retry_attempts', :attempts,
                    '$.next_retry_at', :next_retry_at,
                    '$.last_error', :last_error
                ), '$.run_open')
            WHERE id = :id AND status <> 'canceled'
        ");
        // PARAM_INT: emulated prepares would otherwise store the count as a JSON string.
        $stmt->bindValue(':attempts', $retryAttempts, PDO::PARAM_INT);
        $stmt->bindValue(':next_retry_at', $nextRetryAt);
        $stmt->bindValue(':last_error', $errorMessage);
        $stmt->bindValue(':id', $task['id'], PDO::PARAM_INT);
        $stmt->execute();

        // Retries remain — the failure is not yet terminal.
        return false;
    }

    write_debug_log("Task #{$task['id']} failed after {$retryAttempts} attempts, marking as failed.", "error");
    // Keep the final error with the task, as each retry does, so the Queue
    // Monitor shows why it failed (with a limit of 0 there was no retry to
    // record it). Same canceled guard and key-only JSON_SET as the retry write.
    $stmt = $db->prepare("
        UPDATE queue_tasks
        SET payload = JSON_REMOVE(JSON_SET(
                CASE WHEN JSON_TYPE(payload) = 'OBJECT' THEN payload ELSE JSON_OBJECT() END,
                '$.last_error', :last_error
            ), '$.run_open')
        WHERE id = :id AND status <> 'canceled'
    ");
    $stmt->bindValue(':last_error', $errorMessage);
    $stmt->bindValue(':id', $task['id'], PDO::PARAM_INT);
    $stmt->execute();
    queue_update_status($task['id'], 'failed', $db);

    // queue_update_status() never moves a task out of 'canceled' but still
    // returns true then, so re-read: a cancel that landed after the check above
    // must not be reported as a terminal failure either.
    if (queue_task_current_status($db, $task['id']) !== 'failed') {
        write_debug_log("Task #{$task['id']} was not marked failed (canceled meanwhile); no terminal failure reported.", "info");
        return false;
    }

    // Terminal: retries are exhausted and the task is now 'failed'. The caller
    // uses this to decide whether to run a job's on_terminal_failure hook, so
    // that a job surfacing a failure to a user does so ONCE, after the retry
    // machinery has had its say — not on the first recoverable blip.
    return true;
}

/**
 * The live status of a queue task, or null when the row does not exist.
 */
function queue_task_current_status(PDO $db, $task_id): ?string
{
    $stmt = $db->prepare("SELECT status FROM queue_tasks WHERE id = :id");
    $stmt->execute([':id' => $task_id]);
    $status = $stmt->fetchColumn();
    return ($status === false || $status === null) ? null : (string)$status;
}

/**************************************************************************
 * NAMED LOCKS (multi-worker safety)                                      *
 *                                                                        *
 * Several queue workers and step (promise) workers may run at once       *
 * (QUEUE_WORKER_COUNT / PROMISE_WORKER_COUNT), and on a multi-host       *
 * install one per host. They coordinate through MySQL named locks        *
 * (GET_LOCK): no schema, no grant, and the server drops a lock the moment *
 * its connection ends, so a worker that dies never leaves a claim behind. *
 *                                                                        *
 * - A lock belongs to the CONNECTION that took it. Every lock here is    *
 *   taken on the connection that then runs the work (db_open()'s shared  *
 *   connection) and released in a finally. If that connection is lost    *
 *   while a handler runs, the claim is gone with it; that is why a claim *
 *   always re-reads its row after acquiring and skips anything that is   *
 *   no longer runnable.                                                  *
 * - Names are server-wide, so they carry the database name, and MySQL    *
 *   refuses names over 64 characters (queue_lock_name()).                *
 * - Named locks are used at EVERY worker count, including the default  *
 *   of one: the claims, the worker-slot scan and the loader lock all     *
 *   depend on them. Where GET_LOCK errors (some proxies refuse it) the   *
 *   workers and the loader do not start — fail-safe: nothing runs twice, *
 *   nothing runs at all. acquire_worker_slot() and the loader's lock     *
 *   catch the error and write it to the debug log at 'error'.            *
 * - A host that vanishes without closing its connection keeps that       *
 *   connection's locks until the server times it out; the workers bound  *
 *   that with their session wait_timeout (worker_session_wait_timeout(): *
 *   the stuck-task window, at least 15 minutes, never above the server's *
 *   own value).                                                          *
 * - Locks are per server: on a multi-writer cluster (Galera and similar) *
 *   they do not exclude workers connected to different nodes, and a      *
 *   connection pooler that multiplexes transactions over shared backend  *
 *   connections breaks them. On such setups every worker host must use   *
 *   the same node, or only one host may run the cron jobs.               *
 **************************************************************************/

/**
 * The MySQL named-lock name for $kind/$key in database $database: always at
 * most 64 bytes (MySQL rejects longer names with an error), and the key is
 * never cut off — two different keys always give two different names, which a
 * LEFT(..., 64) would not guarantee for a long database name. The database name
 * is replaced by a short hash when the readable form is too long, and as a last
 * resort the whole name is hashed. Pure.
 *
 * MySQL compares lock names case-insensitively; two databases on one server
 * whose names differ only by case would share locks. That only makes a worker
 * skip work another database's worker holds for a moment — never unsafe.
 */
function queue_lock_name(string $database, string $kind, string $key): string
{
    $name = "sr:{$database}:{$kind}:{$key}";
    if (strlen($name) <= 64) {
        return $name;
    }
    $name = 'sr:' . substr(sha1($database), 0, 12) . ":{$kind}:{$key}";
    if (strlen($name) <= 64) {
        return $name;
    }
    return 'sr:' . sha1("{$database}:{$kind}:{$key}");
}

/**
 * The current database name of $db's connection (read once per connection).
 */
function queue_lock_database(PDO $db): string
{
    static $names = [];
    $id = spl_object_id($db);
    if (!isset($names[$id])) {
        $names[$id] = (string)$db->query("SELECT IFNULL(DATABASE(), '')")->fetchColumn();
    }
    return $names[$id];
}

/**
 * The lock one queue loader pass holds (cron_queue_loader.php): task_check
 * deduplication is read-then-insert, so two loaders running at once (two
 * hosts, or a takeover of a presumed-dead loader) could queue the same work
 * twice. A loader that cannot take it exits.
 */
function queue_loader_lock_name(PDO $db): string
{
    return queue_lock_name(queue_lock_database($db), 'loader', 'pass');
}

/** The claim lock of queue task $task_id. */
function queue_task_lock_name(PDO $db, int $task_id): string
{
    return queue_lock_name(queue_lock_database($db), 'qtask', (string)$task_id);
}

/** The lock that keeps a 'concurrency' => 'serial' job type to one running task. */
function queue_type_lock_name(PDO $db, string $task_type): string
{
    return queue_lock_name(queue_lock_database($db), 'qtype', $task_type);
}

/**
 * Try to take named lock $name on $db's connection, waiting up to
 * $waitSeconds (0 = do not wait). True only when the lock is now held.
 * GET_LOCK returns 1 (taken), 0 (timed out) or NULL (error); only 1 counts.
 * Taking a lock this connection already holds succeeds (and nests).
 */
function queue_lock_try(PDO $db, string $name, int $waitSeconds = 0): bool
{
    $stmt = $db->prepare("SELECT GET_LOCK(:name, :wait)");
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':wait', max(0, $waitSeconds), PDO::PARAM_INT);
    $stmt->execute();
    return (string)$stmt->fetchColumn() === '1';
}

/**
 * Release named lock $name on $db's connection. Never throws: after a lost
 * connection the server has already dropped the lock.
 */
function queue_lock_release(PDO $db, string $name): void
{
    try {
        $stmt = $db->prepare("DO RELEASE_LOCK(:name)");
        $stmt->execute([':name' => $name]);
    } catch (\Throwable $t) {
        write_debug_log("Could not release lock '{$name}' (the connection may be gone, which releases it): " . $t->getMessage(), "debug");
    }
}

/**
 * Whether any connection (this one included) currently holds named lock $name.
 */
function queue_lock_in_use(PDO $db, string $name): bool
{
    $stmt = $db->prepare("SELECT IS_USED_LOCK(:name)");
    $stmt->execute([':name' => $name]);
    return $stmt->fetchColumn() !== null;
}

/**************************************************************************
 * FUNCTION: QUEUE RUNNABLE CANDIDATES                                    *
 * The pending tasks that are due at $now, highest priority first, at     *
 * most $window of them. A plain, non-locking read: picking a task is     *
 * done by claiming it (queue_claim_next_runnable_task()), never by row   *
 * locks, so backed-off and claimed rows are not locked by every pick.    *
 *                                                                        *
 * The "due" test runs IN SQL, so the bounded window only ever holds      *
 * runnable rows. Filtering in PHP alone let $window backed-off rows fill *
 * the window and starve everything behind them — e.g. a mail outage      *
 * leaving 100+ failing emails pending at priority 100. A row is due in   *
 * SQL when its payload is not an object, next_retry_at is missing, JSON  *
 * null, not in the 'Y-m-d H:i:s' form queue_task() /                     *
 * handle_queue_task_failure() write, in that form but not a real date    *
 * ('2026-13-45 10:00:00', for which STR_TO_DATE() is NULL) — unreadable  *
 * counts as due, as in queue_task_retry_due() — or at or before $now.    *
 * $now is formatted with date(), the same PHP time zone the stored       *
 * values were written in, so the string comparison is exact.             *
 *                                                                        *
 * Daylight saving: next_retry_at is a local-time string, so a retry due  *
 * in the repeated hour when clocks fall back can run up to an hour early *
 * (its string is reached during the first pass through that hour). That *
 * is harmless for a retry; storing UTC (gmdate()) or a unix time would   *
 * avoid it, at the cost of a payload format change.                      *
 *                                                                        *
 * $exclude_types leaves those task types out (the claim's second pass    *
 * skips serial types that are busy). $task_type_prefix is a TEST SEAM:   *
 * when non-null only task types starting with it are returned, so a test *
 * can drive the real pick against a shared database. Production passes  *
 * null.                                                                  *
 **************************************************************************/
function queue_runnable_candidates(PDO $db, int $now, int $window = 100, ?string $task_type_prefix = null, array $exclude_types = []): array
{
    $retry_at = "CASE WHEN JSON_TYPE(payload) = 'OBJECT' THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.next_retry_at')) END";

    $extra = '';
    $params = [];
    if ($task_type_prefix !== null) {
        $extra .= " AND task_type LIKE :prefix ESCAPE '!'";
        $params[':prefix'] = strtr($task_type_prefix, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
    $exclude_types = array_values(array_unique(array_map('strval', $exclude_types)));
    if ($exclude_types) {
        $names = [];
        foreach ($exclude_types as $i => $type) {
            $names[] = ":ex{$i}";
            $params[":ex{$i}"] = $type;
        }
        $extra .= ' AND task_type NOT IN (' . implode(', ', $names) . ')';
    }

    $stmt = $db->prepare("
        SELECT * FROM queue_tasks
        WHERE status = 'pending'
          AND (
                ({$retry_at}) IS NULL
             OR ({$retry_at}) NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'
             OR STR_TO_DATE(({$retry_at}), '%Y-%m-%d %H:%i:%s') IS NULL
             OR ({$retry_at}) <= :now
          ){$extra}
        ORDER BY priority DESC, created_at ASC
        LIMIT :window
    ");
    $stmt->bindValue(':now', date('Y-m-d H:i:s', $now));
    $stmt->bindValue(':window', max(1, $window), PDO::PARAM_INT);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**************************************************************************
 * FUNCTION: QUEUE PICK NEXT RUNNABLE TASK                                *
 * Returns the highest-priority pending task that is runnable at $now, or *
 * null, WITHOUT claiming it. The queue worker does not use this: it      *
 * claims with queue_claim_next_runnable_task(), which applies the same   *
 * selection. Kept as the plain "what would run next" read (tests,        *
 * diagnostics).                                                          *
 *                                                                        *
 * A pending task waiting out a retry back-off (payload next_retry_at in  *
 * the future) is skipped, so failing high-priority tasks cannot stall    *
 * the tasks behind them; see queue_runnable_candidates() for the SQL due *
 * test.                                                                  *
 *                                                                        *
 * queue_task_retry_due() stays as a second pass: a value the SQL counts  *
 * as unreadable but strtotime() can parse (another date format) is still *
 * honoured, and it logs the one-per-task warning for unreadable values.  *
 **************************************************************************/
function queue_pick_next_runnable_task(PDO $db, int $now, int $window = 100): ?array
{
    foreach (queue_runnable_candidates($db, $now, $window) as $row) {
        if (queue_task_retry_due($row, $now)) {
            return $row;
        }
    }

    return null;
}

/**************************************************************************
 * FUNCTION: QUEUE CLAIM TASK                                             *
 * Claim queue task $task_id for this connection: take its named lock     *
 * (queue_task_lock_name()) without waiting, then RE-READ the row and keep *
 * the claim only when the task is still pending and due at $now. Returns *
 * the fresh row (the one the handler must see: its payload carries the   *
 * current retry count), or null with nothing held.                       *
 *                                                                        *
 * The re-read is what makes the claim safe: the candidate list was read  *
 * before the lock, so another worker may have run and finished the task  *
 * in between, and a connection lost mid-handler drops the lock while the *
 * work may already be done.                                              *
 *                                                                        *
 * Claiming does not change the task's status (a task stays 'pending'    *
 * while its handler runs; the retry path and stage jobs rely on that).   *
 * Release with queue_release_claim() / queue_lock_release().             *
 **************************************************************************/
function queue_claim_task(PDO $db, int $task_id, int $now): ?array
{
    $name = queue_task_lock_name($db, $task_id);
    if (!queue_lock_try($db, $name)) {
        return null;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM queue_tasks WHERE id = :id");
        $stmt->execute([':id' => $task_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $t) {
        queue_lock_release($db, $name);
        throw $t;
    }

    if (is_array($row) && $row['status'] === 'pending' && queue_task_retry_due($row, $now)) {
        return $row;
    }

    queue_lock_release($db, $name);
    return null;
}

/**************************************************************************
 * FUNCTION: QUEUE CLAIM NEXT RUNNABLE TASK                               *
 * The queue worker's pick: the highest-priority runnable task (same      *
 * selection as queue_pick_next_runnable_task()) that this worker can     *
 * CLAIM. Candidates are tried in order and the first one claimed wins;   *
 * one another worker holds is skipped, so N workers never run the same   *
 * task. Active with a single worker too: it also covers two workers on   *
 * different hosts and a task whose handler is still running.             *
 *                                                                        *
 * Serial job types ($is_serial(type) true; a job declares                *
 * 'concurrency' => 'serial') also take the type lock after the task      *
 * claim. When another worker is running a task of that type the claim is *
 * released and the next candidate is tried: the task is not failed and   *
 * loses no retry. Types found busy are remembered, and when the window   *
 * was full a second pass excludes them, so a backlog of one busy serial  *
 * type cannot hide runnable work behind it.                              *
 *                                                                        *
 * Returns ['task' => fresh row, 'locks' => names held] or null. The      *
 * caller must queue_release_claim() in a finally once the task's whole   *
 * processing (handler and status write) is done.                         *
 *                                                                        *
 * $task_type_prefix is the TEST SEAM of queue_runnable_candidates().     *
 **************************************************************************/
function queue_claim_next_runnable_task(PDO $db, int $now, int $window = 100, ?callable $is_serial = null, ?string $task_type_prefix = null): ?array
{
    $busy = [];
    for ($pass = 0; $pass < 2; $pass++) {
        $candidates = queue_runnable_candidates($db, $now, $window, $task_type_prefix, array_keys($busy));
        foreach ($candidates as $row) {
            $type = (string)$row['task_type'];
            if (isset($busy[$type]) || !queue_task_retry_due($row, $now)) {
                continue;
            }

            $task = queue_claim_task($db, (int)$row['id'], $now);
            if ($task === null) {
                continue;
            }
            $locks = [queue_task_lock_name($db, (int)$task['id'])];

            try {
                $serial = $is_serial !== null && $is_serial($type);
                if ($serial) {
                    $type_lock = queue_type_lock_name($db, $type);
                    if (!queue_lock_try($db, $type_lock)) {
                        queue_lock_release($db, $locks[0]);
                        $busy[$type] = true;
                        continue;
                    }
                    $locks[] = $type_lock;
                }
            } catch (\Throwable $t) {
                queue_release_claim($db, ['locks' => $locks]);
                throw $t;
            }

            return ['task' => $task, 'locks' => $locks];
        }

        // A second pass only helps when busy serial types may have filled a
        // full window.
        if (empty($busy) || count($candidates) < max(1, $window)) {
            break;
        }
    }

    return null;
}

/**
 * Whether a worker currently holds queue task $task_id's claim (a handler is
 * running it right now). Usable from any connection, including a web request:
 * lock names are server-wide and carry the database name.
 */
function queue_task_lock_in_use(PDO $db, int $task_id): bool
{
    return queue_lock_in_use($db, queue_task_lock_name($db, $task_id));
}

/**************************************************************************
 * RUN-OPEN MARKER (crash detection)                                      *
 *                                                                        *
 * A task stays 'pending' while its handler runs, and the claim lock dies *
 * with the worker's connection. A handler that KILLS its worker (out of  *
 * memory, a PHP fatal, exit(), SIGKILL) therefore left the task pending  *
 * and first in line, so the next worker ran it again and died again: the *
 * whole queue stopped, with no bound and no notification.                *
 *                                                                        *
 * So the worker writes payload.run_open (unix time) right after claiming *
 * a task and before running it, and every recorded outcome removes it    *
 * (worker_record_task_success(), handle_queue_task_failure(), the crash  *
 * recovery below). A claim that finds run_open still set knows the last  *
 * run never recorded an outcome: it is a recovery event, not a run       *
 * (queue_recover_crashed_run()). Both writes keep updated_at, so the     *
 * Queue Monitor's times, Delete's settle window and the encryption       *
 * abandoned-task check are unaffected. Restart strips the key.           *
 **************************************************************************/

/** The run_open time recorded in $row's payload, or null when not set. Pure. */
function queue_task_run_open(array $row): ?int
{
    $payload = json_decode((string)($row['payload'] ?? ''), true);
    if (!is_array($payload) || !array_key_exists('run_open', $payload) || $payload['run_open'] === null) {
        return null;
    }
    return is_numeric($payload['run_open']) ? (int)$payload['run_open'] : 0;
}

/**
 * Record that a run of claimed task $task_id is starting (autocommitted, before
 * the handler). Only a still-pending task is marked.
 */
function queue_task_mark_run_open(PDO $db, int $task_id, int $now): void
{
    $stmt = $db->prepare("
        UPDATE queue_tasks
        SET payload = JSON_SET(CASE WHEN JSON_TYPE(payload) = 'OBJECT' THEN payload ELSE JSON_OBJECT() END, '$.run_open', :ts),
            updated_at = updated_at
        WHERE id = :id AND status = 'pending'
    ");
    $stmt->bindValue(':ts', $now, PDO::PARAM_INT);
    $stmt->bindValue(':id', $task_id, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Remove the run-open marker (the run recorded its outcome). Any status: removing
 * the key is harmless everywhere. Keeps updated_at.
 */
function queue_task_clear_run_open(PDO $db, int $task_id): void
{
    $stmt = $db->prepare("
        UPDATE queue_tasks
        SET payload = JSON_REMOVE(payload, '$.run_open'), updated_at = updated_at
        WHERE id = :id AND JSON_TYPE(payload) = 'OBJECT' AND JSON_CONTAINS_PATH(payload, 'one', '$.run_open')
    ");
    $stmt->execute([':id' => $task_id]);
}

/**
 * Seconds to wait before attempt $attempt (1-based) of $payload's task: the
 * task's own stored backoff (retry_base_delay / retry_max_delay, clamped) or
 * config.php, as in handle_queue_task_failure(); never above the longest
 * allowed wait. Pure apart from config.
 *
 * @param array<string,mixed> $payload
 */
function queue_task_backoff_seconds(array $payload, int $attempt): int
{
    $stored_base = queue_config_whole_number($payload['retry_base_delay'] ?? null);
    $stored_max = queue_config_whole_number($payload['retry_max_delay'] ?? null);
    $base = $stored_base !== null ? queue_config_clamp('QUEUE_RETRY_BASE_DELAY_SECONDS', $stored_base) : queue_config('QUEUE_RETRY_BASE_DELAY_SECONDS');
    $max = $stored_max !== null ? queue_config_clamp('QUEUE_RETRY_MAX_DELAY_SECONDS', $stored_max) : queue_config('QUEUE_RETRY_MAX_DELAY_SECONDS');
    $max = max($base, $max);
    return min(queue_retry_delay(max(1, $attempt), $base, $max), queue_config_table()['QUEUE_RETRY_MAX_DELAY_SECONDS']['max']);
}

/**************************************************************************
 * FUNCTION: QUEUE RECOVER CRASHED RUN                                    *
 * For a claimed task whose previous run never recorded an outcome (its  *
 * run_open marker is still set: the worker died mid-handler). The        *
 * handler is NOT run now. Same accounting as recover_stuck_queue_tasks():*
 * payload.recovery_count + 1 while it is below $limit                    *
 * (QUEUE_RECOVERY_MAX_ATTEMPTS, or 0 for a job that must never re-run on *
 * its own), otherwise the task is failed with last_error.                *
 *                                                                        *
 * Either way any LIVE step chain of the task is canceled — a crash       *
 * between two create_stage_promise() calls leaves a partial chain that   *
 * would otherwise run and finalize the task early — and the marker is    *
 * removed. A recovered task gets next_retry_at = $now + the normal back- *
 * off for that recovery count, so it is not picked again at once and    *
 * cannot crash-loop the queue; the next run builds a fresh chain.        *
 *                                                                        *
 * Only a still-pending task is touched. Returns ['outcome' =>            *
 * 'recovered' | 'failed' | 'skipped', 'task' => the row read back]. The  *
 * caller runs the terminal hook and the admin notification for 'failed'.*
 **************************************************************************/
function queue_recover_crashed_run(PDO $db, array $task, int $now, int $limit): array
{
    $id = (int)$task['id'];
    $payload = json_decode((string)($task['payload'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $count = max(0, queue_config_whole_number($payload['recovery_count'] ?? null) ?? 0);
    $limit = max(0, $limit);
    $object = "CASE WHEN JSON_TYPE(payload) = 'OBJECT' THEN payload ELSE JSON_OBJECT() END";

    if ($count < $limit) {
        $error = "The worker running this task stopped before it finished (a crash, running out of memory, or the process being killed); it will run again.";
        $next = date('Y-m-d H:i:s', $now + queue_task_backoff_seconds($payload, $count + 1));
        $stmt = $db->prepare("
            UPDATE queue_tasks
            SET payload = JSON_REMOVE(JSON_SET({$object}, '$.recovery_count', :count, '$.next_retry_at', :next, '$.last_error', :err), '$.run_open'),
                updated_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->bindValue(':count', $count + 1, PDO::PARAM_INT);
        $stmt->bindValue(':next', $next);
        $stmt->bindValue(':err', $error);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $outcome = $stmt->rowCount() === 1 ? 'recovered' : 'skipped';
        if ($outcome === 'recovered') {
            write_debug_log("Task #{$id} ({$task['task_type']}): its last run stopped without finishing; not run now, will retry at {$next} (recovery " . ($count + 1) . " of {$limit}).", "notice");
        }
    } else {
        $error = $limit === 0
            ? "The worker running this task stopped before it finished, and this kind of task is not re-run automatically."
            : "The worker running this task stopped before it finished {$count} times after being restarted automatically; not restarted again.";
        $stmt = $db->prepare("
            UPDATE queue_tasks
            SET status = 'failed', updated_at = NOW(),
                payload = JSON_REMOVE(JSON_SET({$object}, '$.recovery_count', :count, '$.last_error', :err), '$.run_open')
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->bindValue(':count', $count, PDO::PARAM_INT);
        $stmt->bindValue(':err', $error);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $outcome = $stmt->rowCount() === 1 ? 'failed' : 'skipped';
        if ($outcome === 'failed') {
            write_debug_log("Task #{$id} ({$task['task_type']}): {$error} Marked failed.", "error");
        }
    }

    if ($outcome !== 'skipped') {
        // The crashed run's chain, if any, never got its task recorded: cancel
        // its live steps (completed ones are left as they are).
        $db->prepare("
            UPDATE promises SET status = 'canceled', updated_at = NOW()
            WHERE queue_task_id = :id AND status IN ('pending', 'in_progress')
        ")->execute([':id' => $id]);
    }

    $reread = $db->prepare("SELECT * FROM queue_tasks WHERE id = :id");
    $reread->execute([':id' => $id]);
    return ['outcome' => $outcome, 'task' => $reread->fetch(PDO::FETCH_ASSOC) ?: $task];
}

/**
 * Release everything a claim from queue_claim_next_runnable_task() holds.
 * Never throws.
 *
 * @param array{locks: list<string>} $claim
 */
function queue_release_claim(PDO $db, array $claim): void
{
    foreach (array_reverse($claim['locks'] ?? []) as $name) {
        queue_lock_release($db, $name);
    }
}

/**************************************************************************
 * FUNCTION: QUEUE TASK RETRY DUE                                         *
 * Whether a pending task's back-off has elapsed at $now: true when the   *
 * payload has no next_retry_at or it is at or before $now.               *
 *                                                                        *
 * An unreadable next_retry_at (not a string, or a date strtotime()       *
 * cannot parse) counts as DUE, and is logged once per task per process   *
 * at 'warning'. Due is the safer of the two choices: "never due" would   *
 * strand the task in 'pending' forever with nothing to surface it, while *
 * running it either succeeds or fails into handle_queue_task_failure(),  *
 * which writes a fresh, valid next_retry_at — so it cannot hot-loop.     *
 * handle_queue_task_failure() itself caps every wait so it never writes  *
 * an unreadable date.                                                    *
 **************************************************************************/
function queue_task_retry_due(array $row, int $now): bool
{
    static $warned = [];

    $payload = json_decode($row['payload'] ?? '{}', true);
    if (!is_array($payload) || !array_key_exists('next_retry_at', $payload) || $payload['next_retry_at'] === null) {
        return true;
    }

    $raw = $payload['next_retry_at'];
    $nextRetryAt = is_string($raw) ? strtotime($raw) : false;
    if ($nextRetryAt === false) {
        $id = (int)($row['id'] ?? 0);
        if (!isset($warned[$id])) {
            $warned[$id] = true;
            write_debug_log("Task #{$id} has an unreadable next_retry_at; treating it as due now.", "warning");
        }
        return true;
    }

    return $nextRetryAt <= $now;
}

/**************************************************************************
 * FUNCTION: RECOVER STUCK QUEUE TASKS                                    *
 * Re-queues tasks stuck 'in_progress' past $thresholdMinutes (null =     *
 * QUEUE_STUCK_TASK_MINUTES) — but ONLY                                   *
 * those with NO live promise chain. A multi-stage task is legitimately   *
 * 'in_progress' for as long as its promise chain is being resolved by    *
 * the promise worker, which can far exceed the threshold under load.     *
 * Recovering such a task flips it back to 'pending' and re-runs its      *
 * queue_check, re-creating the whole stage chain — the re-chaining       *
 * runaway. So only recover tasks whose chain is gone/terminal.           *
 *                                                                        *
 * The liveness predicate (a promise is live only when NEITHER `state`    *
 * NOR `status` is terminal) MUST stay in sync with promise_chain_exists()*
 * in promises.php — cancellation writes only `status`, so both columns   *
 * are checked.                                                           *
 *                                                                        *
 * BOUNDED. Each recovery is counted in the task's payload                *
 * (recovery_count). A task found stuck again once it has been recovered  *
 * $maxRecoveries times (null = QUEUE_RECOVERY_MAX_ATTEMPTS) is marked    *
 * 'failed' with last_error set instead of being re-queued, so a task     *
 * whose run keeps dying is not re-run every half hour forever (each re-  *
 * run of a stage job rebuilds its whole chain, paid AI steps included).  *
 * $maxRecoveriesFor, when given, is fn(string $task_type): ?int — a      *
 * per-type limit that replaces $maxRecoveries when it returns an int (the*
 * worker passes 0 for jobs that must never re-run on their own).         *
 *                                                                        *
 * Concurrency: candidates are read first, then each row is written with  *
 * the full stuck predicate re-checked in its own UPDATE, so a task that  *
 * was picked up, finished, canceled or recovered by another process in   *
 * between is left alone. The count only ever changes together with the   *
 * status leaving 'in_progress', so a stale read cannot double count.     *
 *                                                                        *
 * This pass counts the event, so it also removes any run_open marker    *
 * (the next claim must not count the same death again).                  *
 *                                                                        *
 * Lease: a candidate whose claim lock (queue_task_lock_name()) is held  *
 * by any connection is skipped — a live worker is still running it, for *
 * however long. Only a worker that has died (its connection, and so its  *
 * lock, gone) or a task nobody is running is recovered. With several     *
 * workers this is what stops one worker's recovery pass from re-running  *
 * a task another worker is in the middle of.                             *
 *                                                                        *
 * Returns ['recovered' => int, 'failed' => list of the task rows marked  *
 * failed here, read back after the write]. The caller (the queue worker) *
 * runs each failed task's terminal-failure hook and admin notification.  *
 **************************************************************************/
function recover_stuck_queue_tasks(PDO $db, ?int $thresholdMinutes = null, ?int $maxRecoveries = null, ?callable $maxRecoveriesFor = null): array
{
    // null = QUEUE_STUCK_TASK_MINUTES / QUEUE_RECOVERY_MAX_ATTEMPTS from config.php.
    $thresholdMinutes ??= queue_config('QUEUE_STUCK_TASK_MINUTES');
    $maxRecoveries ??= queue_config('QUEUE_RECOVERY_MAX_ATTEMPTS');

    $stuck = "
        t.status = 'in_progress'
        AND t.updated_at < NOW() - INTERVAL :mins MINUTE
        AND NOT EXISTS (
            SELECT 1 FROM promises p
            WHERE p.queue_task_id = t.id
              AND p.state  NOT IN ('completed','fulfilled','failed','canceled')
              AND p.status NOT IN ('completed','fulfilled','failed','canceled')
        )
    ";

    $select = $db->prepare("SELECT t.id, t.task_type, t.payload FROM queue_tasks t WHERE {$stuck} ORDER BY t.id LIMIT 1000");
    $select->bindValue(':mins', $thresholdMinutes, PDO::PARAM_INT);
    $select->execute();
    $candidates = $select->fetchAll(PDO::FETCH_ASSOC);

    $recover = $db->prepare("
        UPDATE queue_tasks t
        SET t.status = 'pending', t.updated_at = NOW(),
            t.payload = JSON_REMOVE(JSON_SET(
                CASE WHEN JSON_TYPE(t.payload) = 'OBJECT' THEN t.payload ELSE JSON_OBJECT() END,
                '$.recovery_count', :count
            ), '$.run_open')
        WHERE t.id = :id AND {$stuck}
    ");
    $fail = $db->prepare("
        UPDATE queue_tasks t
        SET t.status = 'failed', t.updated_at = NOW(),
            t.payload = JSON_REMOVE(JSON_SET(
                CASE WHEN JSON_TYPE(t.payload) = 'OBJECT' THEN t.payload ELSE JSON_OBJECT() END,
                '$.recovery_count', :count,
                '$.last_error', :last_error
            ), '$.run_open')
        WHERE t.id = :id AND {$stuck}
    ");
    $reread = $db->prepare("SELECT * FROM queue_tasks WHERE id = :id");

    $recovered = 0;
    $failed = [];
    foreach ($candidates as $row) {
        $payload = json_decode((string)$row['payload'], true);
        $count = max(0, queue_config_whole_number(is_array($payload) ? ($payload['recovery_count'] ?? null) : null) ?? 0);
        $limit = $maxRecoveries;
        if ($maxRecoveriesFor !== null) {
            $own = $maxRecoveriesFor((string)$row['task_type']);
            if (is_int($own)) {
                $limit = $own;
            }
        }
        $limit = max(0, $limit);

        // Lease: a task whose claim lock is held is being worked on by a live
        // worker (a handler may legitimately run past the threshold); never
        // reset or fail it under that worker. A worker that died lost its
        // connection and with it the lock, so its task is reclaimed as before.
        if (queue_lock_in_use($db, queue_task_lock_name($db, (int)$row['id']))) {
            write_debug_log("Task #{$row['id']} ({$row['task_type']}) looks stuck but a worker still holds it; left alone.", "debug");
            continue;
        }

        if ($count < $limit) {
            $recover->bindValue(':count', $count + 1, PDO::PARAM_INT);
            $recover->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
            $recover->bindValue(':mins', $thresholdMinutes, PDO::PARAM_INT);
            $recover->execute();
            $recovered += $recover->rowCount();
            continue;
        }

        $error = $limit === 0
            ? "Stopped running without finishing and is not re-run automatically."
            : "Stopped running without finishing {$count} times after being restarted automatically; not restarted again.";
        $fail->bindValue(':count', $count, PDO::PARAM_INT);
        $fail->bindValue(':last_error', $error);
        $fail->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $fail->bindValue(':mins', $thresholdMinutes, PDO::PARAM_INT);
        $fail->execute();
        if ($fail->rowCount() === 1) {
            write_debug_log("Task #{$row['id']} ({$row['task_type']}): {$error} Marked failed.", "error");
            $reread->execute([':id' => (int)$row['id']]);
            $failed[] = $reread->fetch(PDO::FETCH_ASSOC) ?: $row;
        }
    }

    return ['recovered' => $recovered, 'failed' => $failed];
}

/**************************************************************************
 * FUNCTION: PURGE FINISHED QUEUE TASKS                                   *
 * Retention for queue_tasks (SR-2408). Deletes `completed` and           *
 * `canceled` tasks whose updated_at is older than $completed_days and    *
 * `failed` tasks older than $failed_days. A value <= 0 disables that     *
 * class. Never touches `pending` or `in_progress`.                       *
 *                                                                        *
 * One status at a time, in batches of $batch_limit, until nothing is     *
 * left or the 20-second budget (checked between batches) is spent; a     *
 * later daily run resumes. Each batch is its own short transaction:      *
 * lock the ids with a bounded range on idx_queue_status_updated          *
 * (status, updated_at) -- equality on status, range on updated_at, ORDER *
 * BY updated_at, so it stops after LIMIT rows with no filesort and locks *
 * only the rows it returns (never live pending/in_progress rows) --      *
 * delete their leftover `promises` rows FIRST, then the tasks (the DELETE*
 * re-checks status and age).                                             *
 *                                                                        *
 * Task types in QUEUE_PURGE_EXEMPT_TASK_TYPES are never deleted, in any *
 * status: their finished history is read back as program state.          *
 *                                                                        *
 * $task_type_prefix is a TEST SEAM: when non-null, only tasks whose      *
 * task_type starts with it are eligible, so a test can exercise the real *
 * code against a shared database without touching other rows. Production *
 * callers pass null (no restriction). $exempt_task_types is a test seam  *
 * too (null = QUEUE_PURGE_EXEMPT_TASK_TYPES).                            *
 *                                                                        *
 * Returns the number of queue_tasks rows deleted.                        *
 **************************************************************************/
function purge_finished_queue_tasks(PDO $db, int $completed_days, int $failed_days, int $batch_limit = 1000, ?string $task_type_prefix = null, ?array $exempt_task_types = null): int
{
    $batch_limit = max(1, $batch_limit);
    $deadline = microtime(true) + 20;
    $total = 0;
    $exhausted = false;

    // Exempt types are excluded inside the range SELECT (status equality +
    // updated_at range on idx_queue_status_updated stays the access path; the
    // NOT IN is a row filter). Named placeholders, matching the rest of the
    // statement; no clause at all for an empty list (NOT IN () is invalid).
    $exempt = array_values(array_unique(array_map('strval', $exempt_task_types ?? QUEUE_PURGE_EXEMPT_TASK_TYPES)));
    $exempt_params = [];
    foreach ($exempt as $i => $type) {
        $exempt_params[":ex{$i}"] = $type;
    }
    $exempt_sql = $exempt_params ? ' AND task_type NOT IN (' . implode(', ', array_keys($exempt_params)) . ')' : '';

    // [status, retention days] -- each status is queried on its own so the
    // plan is a bounded range on idx_queue_status_updated.
    $classes = [
        ['completed', $completed_days],
        ['canceled', $completed_days],
        ['failed', $failed_days],
    ];
    $counts = [];

    $like = null;
    if ($task_type_prefix !== null) {
        $like = strtr($task_type_prefix, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
    $prefix_sql = $like === null ? '' : " AND task_type LIKE :prefix ESCAPE '!'";

    foreach ($classes as [$status, $days]) {
        if ($days <= 0) {
            continue;
        }
        $counts[$status] = 0;

        // Keyset cursor over (updated_at, id): each batch starts after the last row
        // the previous one returned, so exempt rows (which stay behind) are walked
        // once per run instead of once per batch.
        $cursor_at = '1000-01-01 00:00:00';
        $cursor_id = 0;

        while (true) {
            if (microtime(true) >= $deadline) {
                $exhausted = true;
                break;
            }

            $db->beginTransaction();
            try {
                $sel = $db->prepare("
                    SELECT id, updated_at FROM queue_tasks
                    WHERE status = :s
                      AND updated_at < NOW() - INTERVAL :days DAY
                      AND (updated_at > :cur_at OR (updated_at = :cur_at2 AND id > :cur_id)){$prefix_sql}{$exempt_sql}
                    ORDER BY updated_at ASC, id ASC
                    LIMIT :lim
                    FOR UPDATE
                ");
                $sel->bindValue(':s', $status);
                $sel->bindValue(':days', $days, PDO::PARAM_INT);
                $sel->bindValue(':lim', $batch_limit, PDO::PARAM_INT);
                $sel->bindValue(':cur_at', $cursor_at);
                $sel->bindValue(':cur_at2', $cursor_at);
                $sel->bindValue(':cur_id', $cursor_id, PDO::PARAM_INT);
                if ($like !== null) {
                    $sel->bindValue(':prefix', $like);
                }
                foreach ($exempt_params as $name => $type) {
                    $sel->bindValue($name, $type);
                }
                $sel->execute();
                $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
                $ids = array_map('intval', array_column($rows, 'id'));

                if (empty($ids)) {
                    $db->commit();
                    break;
                }

                $last = end($rows);
                $cursor_at = $last['updated_at'];
                $cursor_id = (int)$last['id'];

                $in = implode(',', array_fill(0, count($ids), '?'));
                $db->prepare("DELETE FROM promises WHERE queue_task_id IN ({$in})")->execute($ids);
                $del = $db->prepare("
                    DELETE FROM queue_tasks
                    WHERE id IN ({$in}) AND status = ? AND updated_at < NOW() - INTERVAL ? DAY
                ");
                $del->execute(array_merge($ids, [$status, $days]));
                $deleted = $del->rowCount();
                $db->commit();
                $counts[$status] += $deleted;
                $total += $deleted;
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            if (count($ids) < $batch_limit) {
                break;
            }
        }

        if ($exhausted) {
            break;
        }
    }

    if (!empty($counts)) {
        $parts = [];
        foreach ($counts as $st => $n) {
            $parts[] = "{$st}={$n}";
        }
        write_debug_log("Queue purge: deleted " . implode(', ', $parts) . ".", "info");
    }
    if ($exhausted) {
        write_debug_log("Queue purge: 20s budget exhausted with rows possibly remaining, resuming next run.", "info");
    }

    return $total;
}

?>