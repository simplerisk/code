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

/*************************************************************
 * FUNCTION: QUEUE TASK                                      *
 * Inserts a new task into the queue_tasks table             *
 * PRIORITY                                                  *
 * 100 = Do Immediately (Ex: send_email)                     *
 * 75 = Important, but can wait (Ex: ai_document_install)    *
 * 50 = Wait until spare cycles (Ex: core_ai_context_update) *
 * 25 = Not a high priority (Ex: core_countries_update)      *
 * 0 = Lowest possible priority (No Examples)                *
 *************************************************************/
function queue_task(
    PDO $db,
    string $task_type,
    array $payload = [],
    int $priority = 0,
    int $baseDelay = 5,
    int $maxDelay = 3600
): bool {
    try {
        // Initialize retry metadata in payload
        $payload['retry_attempts'] = $payload['retry_attempts'] ?? 0;
        $payload['next_retry_at'] = $payload['next_retry_at'] ?? date('Y-m-d H:i:s');
        $payload['last_error'] = $payload['last_error'] ?? null;

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
        $stmt = $db->prepare("UPDATE queue_tasks SET status = :status, updated_at = NOW() WHERE id = :id");
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
                write_debug_log("queue_update_status: task #{$task_id} already had status '{$status}'", "debug");
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

    // Only an active account may be impersonated (mirrors the login gate:
    // enabled = 1 AND lockout = 0).
    $stmt = $db->prepare("SELECT `username` FROM `user` WHERE `value` = :uid AND `enabled` = 1 AND `lockout` = 0 LIMIT 1;");
    $stmt->bindValue(":uid", $uid, PDO::PARAM_INT);
    $stmt->execute();
    $username = $stmt->fetchColumn();
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
 **************************************************************************/
function handle_queue_task_failure(PDO $db, array $task, string $errorMessage, int $maxRetryAttempts = 5, int $baseRetryDelay = 5, int $maxRetryDelay = 3600): bool
{
    $payload = json_decode($task['payload'] ?? '{}', true);
    $payload['last_error'] = $errorMessage;

    $retryAttempts = (int)($payload['retry_attempts'] ?? 0) + 1;
    $nextRetryDelay = min($baseRetryDelay * (2 ** ($retryAttempts - 1)), $maxRetryDelay);
    $payload['retry_attempts'] = $retryAttempts;
    $payload['next_retry_at'] = date('Y-m-d H:i:s', time() + $nextRetryDelay);

    if ($retryAttempts <= $maxRetryAttempts) {
        write_debug_log("Task #{$task['id']} failed, scheduling retry #{$retryAttempts} at {$payload['next_retry_at']}", "warning");
        $stmt = $db->prepare("
            UPDATE queue_tasks
            SET payload = :payload
            WHERE id = :id
        ");
        $stmt->execute([
            ':payload' => json_encode($payload),
            ':id' => $task['id'],
        ]);

        // Retries remain — the failure is not yet terminal.
        return false;
    }

    write_debug_log("Task #{$task['id']} failed after {$retryAttempts} attempts, marking as failed.", "error");
    queue_update_status($task['id'], 'failed', $db);

    // Terminal: retries are exhausted and the task is now 'failed'. The caller
    // uses this to decide whether to run a job's on_terminal_failure hook, so
    // that a job surfacing a failure to a user does so ONCE, after the retry
    // machinery has had its say — not on the first recoverable blip.
    return true;
}

/**************************************************************************
 * FUNCTION: RECOVER STUCK QUEUE TASKS                                    *
 * Re-queues tasks stuck 'in_progress' past $thresholdMinutes — but ONLY  *
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
 * are checked. Returns the number of tasks recovered.                    *
 **************************************************************************/
function recover_stuck_queue_tasks(PDO $db, int $thresholdMinutes): int
{
    $stmt = $db->prepare("
        UPDATE queue_tasks t
        SET t.status='pending', t.updated_at=NOW()
        WHERE t.status='in_progress'
          AND t.updated_at < NOW() - INTERVAL :mins MINUTE
          AND NOT EXISTS (
              SELECT 1 FROM promises p
              WHERE p.queue_task_id = t.id
                AND p.state  NOT IN ('completed','fulfilled','failed','canceled')
                AND p.status NOT IN ('completed','fulfilled','failed','canceled')
          )
    ");
    $stmt->execute([':mins' => $thresholdMinutes]);

    return $stmt->rowCount();
}

?>