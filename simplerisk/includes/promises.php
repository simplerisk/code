<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/queues.php'));
// queue_config(): retry limits and batch size, overridable from config.php.
require_once(realpath(__DIR__ . '/queue_config.php'));

/**
 * Throw this from a stage handler to signal that the failure is permanent
 * and should not be retried. Unlike a regular exception, this bypasses the
 * retry mechanism and results in immediate permanent failure of the promise.
 */
class NonRetryableException extends \RuntimeException {}

/****************************
 * FUNCTION: CREATE PROMISE *
 ****************************/
function create_promise(string $promise_type, array $stages, array $payload, PDO $db): int
{
    $stmt = $db->prepare("
        INSERT INTO promises (promise_type, current_stage, status, state, stages, payload, created_at, updated_at)
        VALUES (:promise_type, :current_stage, 'pending', 'pending', :stages, :payload, NOW(), NOW())
    ");
    $stmt->execute([
        ':promise_type'  => $promise_type,
        ':current_stage' => $stages[0]['name'],
        ':stages'        => json_encode($stages),
        ':payload'       => json_encode($payload),
    ]);
    return (int) $db->lastInsertId();
}

/***********************************************************
 * FUNCTION: CREATE STAGE PROMISE                          *
 * Creates a single-stage promise with optional dependency *
 ***********************************************************/
function create_stage_promise(
    string $promise_type,
    string $stage_name,
    array $payload,
    ?int $depends_on,
    ?int $reference_id,
    ?int $queue_task_id,
    PDO $db
): int {
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $description = "{$promise_type} - stage: {$stage_name}" . ($reference_id ? " (ref {$reference_id})" : "");
        $stages_json = json_encode([$stage_name]);
        $payload_json = json_encode($payload);

        write_debug_log("CREATE_STAGE_PROMISE: Preparing to insert promise for stage '{$stage_name}' with payload: {$payload_json}", "debug");

        $stmt = $db->prepare("
        INSERT INTO promises 
            (promise_type, reference_id, queue_task_id, current_stage, status, state, stages, payload, depends_on, description, created_at, updated_at)
        VALUES 
            (:pt, :ref, :queue, :stage, 'pending', 'pending', :stages, :payload, :depends, :desc, NOW(), NOW())
    ");

        $stmt->execute([
            ':pt'      => $promise_type,
            ':ref'     => $reference_id,
            ':queue'   => $queue_task_id,
            ':stage'   => $stage_name,
            ':stages'  => $stages_json,
            ':payload' => $payload_json,
            ':depends' => $depends_on,
            ':desc'    => $description,
        ]);

        $id = (int)$db->lastInsertId();

        if ($id) {
            write_debug_log("CREATE_STAGE_PROMISE: Successfully created promise #{$id} for stage '{$stage_name}'" . ($depends_on ? " (depends on #{$depends_on})" : ""), "info");
        } else {
            write_debug_log("CREATE_STAGE_PROMISE: Insert succeeded but lastInsertId returned 0 for stage '{$stage_name}'", "error");
        }

        return $id;

    } catch (PDOException $e) {
        write_debug_log("CREATE_STAGE_PROMISE: SQL error for stage '{$stage_name}': " . $e->getMessage(), "error");
        return 0;
    } catch (Exception $e) {
        write_debug_log("CREATE_STAGE_PROMISE: General error for stage '{$stage_name}': " . $e->getMessage(), "error");
        return 0;
    }
}

/***********************************************************************
 * FUNCTION: PROMISE CHAIN EXISTS                                      *
 * True if a live (non-terminal) promise chain already exists for the  *
 * given queue task. Multi-stage jobs' queue_check must call this and   *
 * skip chain creation when it returns true, otherwise the queue        *
 * worker's stuck-task recovery (which flips a long-running task back   *
 * to 'pending' and re-invokes queue_check) re-creates the entire stage *
 * chain on every recovery — the re-chaining runaway that exploded the  *
 * promises table on a production instance (~7,000x duplication).       *
 *                                                                      *
 * Liveness checks BOTH `state` and `status`: the promise worker drives *
 * `state`, but cancellation (cancel_control_task) only writes          *
 * `status='canceled'`, so a promise can be terminal on one column and  *
 * stale on the other. A promise counts as live only when NEITHER       *
 * column holds a terminal value.                                       *
 ***********************************************************************/
function promise_chain_exists(PDO $db, int $queue_task_id, string $promise_type): bool
{
    $stmt = $db->prepare("
        SELECT 1 FROM promises
        WHERE queue_task_id = :task_id
          AND promise_type = :promise_type
          AND state  NOT IN ('completed','fulfilled','failed','canceled')
          AND status NOT IN ('completed','fulfilled','failed','canceled')
        LIMIT 1
    ");
    $stmt->execute([
        ':task_id'      => $queue_task_id,
        ':promise_type' => $promise_type,
    ]);

    return (bool) $stmt->fetchColumn();
}

/***********************************************************************
 * FUNCTION: FETCH READY PROMISES                                       *
 * Pending promises the worker may run now: their dependency (if any)   *
 * is completed. Anything terminal on EITHER `state` or `status` is     *
 * excluded — cancel_queue_task() writes only `status`, and stuck-      *
 * promise recovery resets only `state`, so a canceled promise can sit   *
 * at state='pending'.                                                   *
 * $limit null = PROMISE_WORKER_BATCH_SIZE from config.php (default 20). *
 *                                                                     *
 * A step still waiting out a retry back-off (payload                  *
 * _retry_available_at, a unix time, after $now) is excluded HERE, in  *
 * SQL. process_promise() also skips it, but filtering only there let  *
 * a batch-size worth of backed-off steps fill every batch and starve  *
 * the ready steps behind them. A missing, null or non-numeric value,  *
 * or a payload that is not an object, counts as due (process_promise()*
 * still applies its own check). $now null = time().                   *
 ***********************************************************************/
function fetch_ready_promises(PDO $db, ?int $limit = null, ?int $now = null, ?string $promise_type_prefix = null): array
{
    $limit ??= queue_config('PROMISE_WORKER_BATCH_SIZE');
    $now ??= time();

    // TEST SEAM: $promise_type_prefix (null in production) limits the batch to
    // promise types starting with it, so a test can drive the real fetch
    // against a shared database.
    $prefix_sql = $promise_type_prefix === null ? '' : " AND p.promise_type LIKE :prefix ESCAPE '!'";

    $stmt = $db->prepare("
        SELECT p.*
        FROM promises p
        WHERE " . promise_ready_sql() . "{$prefix_sql}
        ORDER BY p.created_at ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':now', $now, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    if ($promise_type_prefix !== null) {
        $stmt->bindValue(':prefix', strtr($promise_type_prefix, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%');
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The SQL condition (on alias p, with a :now unix-time parameter) for "this
 * step may run now": pending, terminal on neither state nor status, its
 * dependency (if any) completed, and no retry back-off still running. Shared
 * by fetch_ready_promises() and promise_try_claim() so the batch read and the
 * claim's re-read can never disagree.
 */
function promise_ready_sql(): string
{
    return "
          p.state = 'pending'
          AND p.status NOT IN ('completed','fulfilled','failed','canceled')
          AND (p.depends_on IS NULL OR EXISTS (
              SELECT 1 FROM promises d
              WHERE d.id = p.depends_on AND d.state = 'completed'
          ))
          AND (
                p.payload IS NULL
             OR JSON_TYPE(p.payload) <> 'OBJECT'
             OR COALESCE(JSON_TYPE(JSON_EXTRACT(p.payload, '$._retry_available_at')), 'NULL') NOT IN ('INTEGER','UNSIGNED INTEGER','DOUBLE','DECIMAL')
             OR JSON_EXTRACT(p.payload, '$._retry_available_at') <= :now
          )
    ";
}

/** The claim lock of step (promise) $promise_id. */
function promise_lock_name(PDO $db, int $promise_id): string
{
    return queue_lock_name(queue_lock_database($db), 'promise', (string)$promise_id);
}

/**
 * Claim step $promise_id for this connection: take its named lock without
 * waiting, then RE-READ it with the same readiness rule the batch fetch uses.
 * Returns the fresh row (its payload may have changed since the batch was
 * read: a retry, or the previous step's output) with the claim held, or null
 * with nothing held — another step worker is running it, or it has already
 * run, been canceled or gone into back-off.
 *
 * Several step workers fetch overlapping batches; this is what keeps each
 * step to one run. Release with promise_release_claim() in a finally. See
 * the NAMED LOCKS note in queues.php for the connection rules.
 */
function promise_try_claim(PDO $db, int $promise_id, ?int $now = null): ?array
{
    $name = promise_lock_name($db, $promise_id);
    if (!queue_lock_try($db, $name)) {
        return null;
    }

    try {
        $stmt = $db->prepare("SELECT p.* FROM promises p WHERE p.id = :id AND " . promise_ready_sql());
        $stmt->bindValue(':id', $promise_id, PDO::PARAM_INT);
        $stmt->bindValue(':now', $now ?? time(), PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $t) {
        queue_lock_release($db, $name);
        throw $t;
    }

    if (is_array($row)) {
        return $row;
    }

    queue_lock_release($db, $name);
    return null;
}

/** Release the claim promise_try_claim() took. Never throws. */
function promise_release_claim(PDO $db, int $promise_id): void
{
    queue_lock_release($db, promise_lock_name($db, $promise_id));
}

/**
 * Stuck-step recovery: steps left 'in_progress' longer than $thresholdMinutes
 * (null = QUEUE_STUCK_TASK_MINUTES) go back to 'pending' so they run again —
 * except a step whose claim lock is held: a live step worker is still running
 * it (a long AI call, a large document), and resetting it would let another
 * step worker start it a second time. A worker that died lost its connection
 * and with it the lock, so its step is recovered.
 *
 * BOUNDED, like stuck queue tasks: each recovery is counted in the step's
 * payload (_recovery_count). A step found stuck again once it has been
 * recovered $maxRecoveries times (null = QUEUE_RECOVERY_MAX_ATTEMPTS) is failed
 * through the normal terminal path instead (handle_promise_failure(): the
 * job's on_failure, the steps after it failed, the queue task failed, the
 * admin notification) — a step that keeps killing its worker (out of memory,
 * a worker connection the server closed during a long call) is no longer
 * re-run every half hour forever, paid AI calls included.
 *
 * Each reset is re-checked in its own UPDATE. $job_def_for (fn(string
 * $promise_type): ?array) gives the job definition for the terminal path (null
 * = handle_promise_failure() looks it up). Returns the number of steps put
 * back in the queue.
 */
function recover_stuck_promises(PDO $db, ?int $thresholdMinutes = null, ?int $maxRecoveries = null, ?callable $job_def_for = null): int
{
    $thresholdMinutes ??= queue_config('QUEUE_STUCK_TASK_MINUTES');
    $maxRecoveries ??= queue_config('QUEUE_RECOVERY_MAX_ATTEMPTS');

    $select = $db->prepare("
        SELECT * FROM promises
        WHERE state = 'in_progress'
          AND updated_at < NOW() - INTERVAL :mins MINUTE
        ORDER BY id
        LIMIT 1000
    ");
    $select->bindValue(':mins', $thresholdMinutes, PDO::PARAM_INT);
    $select->execute();
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    $update = $db->prepare("
        UPDATE promises
        SET state = 'pending', updated_at = NOW(),
            payload = JSON_SET(CASE WHEN JSON_TYPE(payload) = 'OBJECT' THEN payload ELSE JSON_OBJECT() END, '$._recovery_count', :count)
        WHERE id = :id
          AND state = 'in_progress'
          AND updated_at < NOW() - INTERVAL :mins MINUTE
    ");

    $recovered = 0;
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if (queue_lock_in_use($db, promise_lock_name($db, $id))) {
            write_debug_log("Step #{$id} looks stuck but a step worker still holds it; left alone.", "debug");
            continue;
        }

        $payload = json_decode((string)($row['payload'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];
        $count = max(0, queue_config_whole_number($payload['_recovery_count'] ?? null) ?? 0);

        if ($count < max(0, $maxRecoveries)) {
            $update->bindValue(':count', $count + 1, PDO::PARAM_INT);
            $update->bindValue(':id', $id, PDO::PARAM_INT);
            $update->bindValue(':mins', $thresholdMinutes, PDO::PARAM_INT);
            $update->execute();
            $recovered += $update->rowCount();
            continue;
        }

        // Out of recoveries: fail it like a step that ran out of retries.
        // Claimed first, so a step worker cannot start it meanwhile.
        if (!queue_lock_try($db, promise_lock_name($db, $id))) {
            continue;
        }
        try {
            // Re-check under the claim: another worker's recovery pass, or a
            // step worker, may have dealt with it since it was selected; never
            // fail it (and run its on_failure) twice.
            $still = $db->prepare("SELECT * FROM promises WHERE id = :id AND state = 'in_progress' AND updated_at < NOW() - INTERVAL :mins MINUTE");
            $still->bindValue(':id', $id, PDO::PARAM_INT);
            $still->bindValue(':mins', $thresholdMinutes, PDO::PARAM_INT);
            $still->execute();
            $current = $still->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                continue;
            }
            $row = $current;
            $payload = json_decode((string)($row['payload'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $payload['_recovery_count'] = $count;
            $row['payload'] = json_encode($payload);
            $error = "Step stopped without finishing {$count} times after being restarted automatically; not restarted again.";
            $job_def = $job_def_for !== null ? $job_def_for((string)$row['promise_type']) : null;
            write_debug_log("Step #{$id} ({$row['promise_type']}): {$error} Marking it failed.", "error");
            // A limit of 1 attempt makes this failure terminal.
            handle_promise_failure($db, $row, $error, 1, 0, 0, is_array($job_def) ? $job_def : null);
        } finally {
            promise_release_claim($db, $id);
        }
    }

    return $recovered;
}

/***********************************************************************
 * FUNCTION: QUEUE TASK CHAIN FINISHED                                  *
 * True when a task's CURRENT promise chain ran to completion: nothing  *
 * is still live and the most recently created promise (the final       *
 * stage — stages are inserted in order) completed.                     *
 *                                                                      *
 * Deliberately ignores terminal promises from earlier, superseded      *
 * chains. A task re-chained by stuck-task recovery accumulates one     *
 * chain per recovery, and a single failed promise from any old chain   *
 * used to keep "every promise completed" false forever — so the task   *
 * never finalized, recovery flipped it back to 'pending' every ~30     *
 * minutes, and each recovery re-chained (and, for AI jobs, re-billed   *
 * the provider) indefinitely.                                          *
 ***********************************************************************/
function queue_task_chain_finished(PDO $db, int $queue_task_id): bool
{
    $stmt = $db->prepare("
        SELECT status, state FROM promises
        WHERE queue_task_id = :task_id
        ORDER BY id DESC
    ");
    $stmt->execute([':task_id' => $queue_task_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return false;
    }

    $terminal = ['completed', 'fulfilled', 'failed', 'canceled'];
    foreach ($rows as $row) {
        // A live promise anywhere means work is still in flight.
        if (!in_array($row['state'], $terminal, true) && !in_array($row['status'], $terminal, true)) {
            return false;
        }
    }

    // $rows[0] is the highest id: the final stage of the latest chain.
    return $rows[0]['status'] === 'completed';
}

/***********************************************************************
 * FUNCTION: PURGE TERMINAL PROMISES                                   *
 * Deletes terminal-state promise rows older than the retention        *
 * window, in a single bounded batch. The promises table is otherwise  *
 * append-only and grows unbounded (a production instance reached an    *
 * auto-increment id of ~10M); only rows that are done — completed,     *
 * fulfilled, failed, or canceled — are eligible. A promise is terminal *
 * when EITHER `state` or `status` holds a terminal value (cancellation *
 * writes only `status`). Live chains are never touched.               *
 *                                                                      *
 * Returns the number of rows deleted. $batch_limit caps a single run   *
 * so the daily sweep never issues an unbounded DELETE; draining a      *
 * large pre-existing backlog is an operational task, not this job's.   *
 ***********************************************************************/
function purge_terminal_promises(PDO $db, int $retention_days = 7, int $batch_limit = 50000): int
{
    $retention_days = max(1, $retention_days);
    $batch_limit    = max(1, $batch_limit);

    // Perf note: the dominant `state IN (terminal)` branch is served by the
    // existing idx_promises_state_updated (state, updated_at); the `status`
    // branch (only the cancel-divergence remainder) falls back to
    // idx_promises_status. A standalone updated_at index is deliberately NOT
    // added — updated_at changes on every stage transition, so indexing it would
    // add write amplification to this high-churn table for every customer to
    // speed up a daily maintenance DELETE that is only slow on an already-
    // degenerate backlog (an operational one-time cleanup, not this job's).

    // $batch_limit is an internal int (typed param, clamped above), safe to
    // inline — PDO can't bind a LIMIT placeholder under emulated prepares.
    $stmt = $db->prepare("
        DELETE FROM promises
        WHERE updated_at < NOW() - INTERVAL :days DAY
          AND (
               state  IN ('completed','fulfilled','failed','canceled')
            OR status IN ('completed','fulfilled','failed','canceled')
          )
        LIMIT " . $batch_limit . "
    ");
    $stmt->execute([':days' => $retention_days]);

    return $stmt->rowCount();
}

/*******************************************************
 * FUNCTION: UPDATE PROMISE STAGE                      *
 * Updates promise state and triggers completion logic *
 *******************************************************/
function update_promise_stage(int $promise_id, ?string $current_stage, ?string $status, ?array $payload, PDO $db): bool
{
    $parts = [];
    $params = [':pid' => $promise_id];

    if ($current_stage !== null) {
        $parts[] = "current_stage = :current_stage";
        $params[':current_stage'] = $current_stage;
    }

    if ($status !== null) {
        $parts[] = "status = :status, state = :state";
        $params[':status'] = $status;
        $params[':state'] = $status;
    }

    if ($payload !== null) {
        $parts[] = "payload = :payload";
        $params[':payload'] = json_encode($payload);
    }

    if (empty($parts)) {
        return false;
    }

    // A completed step and the hand-over of its output to the next step are
    // written in ONE transaction. With several step workers the next step
    // becomes runnable the moment this one reads 'completed'; written
    // separately, another worker could fetch and run it in between, on its
    // old payload. (Inside a caller's transaction both writes are already
    // atomic, so no nested one is started.)
    $atomic = $status === 'completed' && $payload !== null && !$db->inTransaction();
    if ($atomic) {
        $db->beginTransaction();
    }

    try {
        // Never overwrite a canceled promise: cancel_queue_task() may land while a
        // stage is running, and the stage's own completion write must not undo it.
        $sql = "UPDATE promises SET " . implode(', ', $parts) . ", updated_at = NOW() WHERE id = :pid AND status <> 'canceled'";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);

        // Nothing was written (the promise is canceled): no propagation, no
        // parent fulfillment, no dependent failure.
        if ($stmt->rowCount() === 0) {
            // Record that an in-flight canceled step has now ended: cancel writes
            // status only, so state stays 'in_progress' until the stage returns.
            // The queue monitor reads (status canceled, state in_progress) as "still stopping".
            $end = $db->prepare("UPDATE promises SET state = 'canceled', updated_at = NOW() WHERE id = :pid AND status = 'canceled' AND state <> 'canceled'");
            $end->execute([':pid' => $promise_id]);
            if ($atomic) {
                $db->commit();
            }
            return false;
        }

        if ($status === 'completed' && $payload !== null) {
            propagate_payload_to_dependent($db, $promise_id, $payload);
        }

        if ($atomic) {
            $db->commit();
        }
    } catch (\Throwable $t) {
        if ($atomic && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $t;
    }

    // If this promise completed, check parent completion
    if ($status === 'completed') {
        recursively_fulfill_parent($db, $promise_id);
    }

    // If this promise failed, fail all dependent promises
    if ($status === 'failed') {
        fail_dependent_promises($db, $promise_id);
    }

    return (bool)$result;
}

/***************************************************************
 * FUNCTION: PROPAGATE PAYLOAD TO DEPENDENT                    *
 * Passes payload data from completed promise to next in chain *
 ***************************************************************/
function propagate_payload_to_dependent(PDO $db, int $completed_promise_id, array $payload): void
{
    // The finished step's own bookkeeping is not handed on: stages often return
    // their whole (decoded) payload, and the next step would otherwise start
    // with this step's recovery count (and fail on its first stuck event) and
    // its retry count and last error (and get fewer attempts). The next step
    // keeps everything else. _retry_available_at is kept: on a step that just
    // completed it is in the past, so it gates nothing (and a caller may set it
    // on purpose to hold the next step back).
    unset($payload['_recovery_count'], $payload['_retry_attempts'], $payload['_retry_delay'], $payload['_last_error']);

    // Find promises that depend on this one
    $stmt = $db->prepare("
        SELECT id, current_stage FROM promises
        WHERE depends_on = :pid
          AND state = 'pending'
    ");
    $stmt->execute([':pid' => $completed_promise_id]);
    $dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dependents as $dependent) {
        // Update the dependent promise's payload
        $update = $db->prepare("
            UPDATE promises
            SET payload = :payload, updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            ':payload' => json_encode($payload),
            ':id' => $dependent['id']
        ]);

        write_debug_log(
            "Propagated payload from promise #{$completed_promise_id} to #{$dependent['id']} (stage: {$dependent['current_stage']})",
            "debug"
        );
    }
}

/**********************************************************************
 * FUNCTION: RECURSIVE PARENT FULFILLMENT                             *
 * Checks if all sibling promises are complete, fulfills parent if so *
 **********************************************************************/
function recursively_fulfill_parent(PDO $db, int $promise_id): void
{
    // Get this promise's info
    $stmt = $db->prepare("SELECT id, depends_on, queue_task_id FROM promises WHERE id=:pid LIMIT 1");
    $stmt->execute([':pid'=>$promise_id]);
    $promise = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$promise || !$promise['depends_on']) {
        return; // No parent to fulfill
    }

    // Check if any sibling promises are still incomplete
    $stmtSiblings = $db->prepare("
        SELECT id FROM promises 
        WHERE depends_on=:parent 
          AND state NOT IN ('completed', 'failed')
        LIMIT 1
    ");
    $stmtSiblings->execute([':parent'=>$promise['depends_on']]);
    $incomplete_sibling = $stmtSiblings->fetch(PDO::FETCH_ASSOC);

    if (!$incomplete_sibling) {
        // All siblings are done (completed or failed), mark parent as fulfilled
        $stmtParent = $db->prepare("
            UPDATE promises 
            SET state='fulfilled', updated_at=NOW() 
            WHERE id=:pid
        ");
        $stmtParent->execute([':pid'=>$promise['depends_on']]);

        write_debug_log("Parent promise #{$promise['depends_on']} fulfilled (all dependencies complete)", "info");

        // Recursively check the parent's parent
        recursively_fulfill_parent($db, (int)$promise['depends_on']);
    }
}

/***********************************************************************
 * FUNCTION: HANDLE PROMISE FAILURE                                    *
 * Helper function to handle promise failures with exponential backoff *
 *                                                                     *
 * $job_def is the promise's job definition when the caller already    *
 * has it (process_promise() does); its on_failure hook is used. When  *
 * null, the definition is looked up by promise_type in load_all_jobs().*
 *                                                                     *
 * A null limit or delay comes from config.php via queue_config()      *
 * (QUEUE_RETRY_MAX_ATTEMPTS, QUEUE_RETRY_BASE_DELAY_SECONDS,          *
 * QUEUE_RETRY_MAX_DELAY_SECONDS). Unlike a queue task, a step counts  *
 * TOTAL attempts: a limit of 5 means 5 attempts (queue tasks get 5    *
 * retries, so 6), and 0 or 1 fails the step on its first failure.     *
 ***********************************************************************/
function handle_promise_failure(
    PDO $db,
    array $promise,
    string $error_message,
    ?int $maxAttempts = null,
    ?int $baseDelay = null,
    ?int $maxDelay = null,
    ?array $job_def = null
): void {
    $maxAttempts ??= queue_config('QUEUE_RETRY_MAX_ATTEMPTS');
    $baseDelay ??= queue_config('QUEUE_RETRY_BASE_DELAY_SECONDS');
    $maxDelay ??= queue_config('QUEUE_RETRY_MAX_DELAY_SECONDS');
    $baseDelay = max(0, $baseDelay);
    $maxDelay = max($baseDelay, $maxDelay);

    $promise_id = $promise['id'];
    $queue_task_id = $promise['queue_task_id'];
    $payload = json_decode($promise['payload'], true) ?? [];
    $attempts = ($payload['_retry_attempts'] ?? 0) + 1;

    write_debug_log("Handling promise failure for promise #{$promise_id} queue_task_id={$queue_task_id}", "debug");

    // Sanitize the error message to prevent JSON encoding issues
    $sanitized_error = str_replace(['"', "'", "\\"], ['', '', ''], $error_message);
    $sanitized_error = mb_substr($sanitized_error, 0, 500);

    $payload['_last_error'] = $sanitized_error;
    $payload['_retry_attempts'] = $attempts;

    if ($attempts >= $maxAttempts) {
        // Permanently fail the promise. update_promise_stage() never overwrites
        // a canceled promise; when that is why nothing was written, the stage
        // was stopped by a user (Queue Monitor cancel/stop) and its failure is
        // not one: no on_failure hook, no failed task, no admin notification,
        // and the canceled steps after it stay canceled.
        if (!update_promise_stage($promise_id, null, 'failed', $payload, $db) && promise_current_status($db, (int)$promise_id) === 'canceled') {
            write_debug_log("Promise #{$promise_id} failed after it was canceled; leaving the chain canceled.", "info");
            return;
        }

        write_debug_log(
            "Promise #{$promise_id} permanently failed after {$attempts} attempts: {$error_message}",
            "error"
        );

        // Call custom failure handler if it exists
        $promise_type = $promise['promise_type'] ?? null;
        if ($promise_type) {
            try {
                $all_jobs = $job_def !== null ? [$job_def] : load_all_jobs();
                foreach ($all_jobs as $job) {
                    if ($job['type'] === $promise_type && isset($job['on_failure'])) {
                        write_debug_log("Calling on_failure handler for job type '{$promise_type}'", "debug");
                        $job['on_failure']($db, $promise, $error_message, $attempts);
                        break;
                    }
                }
            } catch (Exception $e) {
                write_debug_log(
                    "Error calling custom failure handler for '{$promise_type}': " . $e->getMessage(),
                    "error"
                );
            }
        }

        // Mark parent queue task as failed
        if (!empty($queue_task_id)) {
            write_debug_log("Calling queue_update_status for task #{$queue_task_id}", "debug");
            queue_update_status($queue_task_id, 'failed', $db);

            // Admin notification for the permanently failed parent task. The
            // helper re-reads the task and stays silent unless it is now
            // 'failed' (a canceled task is not a failure). Never throws.
            try {
                require_once(realpath(__DIR__ . '/queue_monitor.php'));
                notify_queue_task_failure($db, ['id' => $queue_task_id]);
            } catch (\Throwable $e) {
                write_debug_log("Could not notify about failed queue task #{$queue_task_id}: " . $e->getMessage(), 'warning');
            }
        }
        else
        {
            write_debug_log("No queue_task_id specified for promise '{$promise_id}'", "debug");
        }

        // Fail all dependent promises
        fail_dependent_promises($db, $promise_id);

    } else {
        // Calculate exponential backoff delay
        $delay = queue_retry_delay($attempts, $baseDelay, $maxDelay);

        write_debug_log(
            "Promise #{$promise_id} failed attempt {$attempts}, retrying in {$delay}s: {$error_message}",
            "warning"
        );

        // Update payload with retry metadata
        $payload['_retry_delay'] = $delay;
        $payload['_retry_attempts'] = $attempts;
        // explicit timestamp (epoch seconds) when we should next try
        $payload['_retry_available_at'] = time() + $delay;

        // Reset promise to pending with updated payload
        update_promise_stage($promise_id, null, 'pending', $payload, $db);
    }
}

/*************************************************************
 * FUNCTION: FAIL DEPENDENT PROMISES                         *
 * Helper function to cascade failures to dependent promises *
 *************************************************************/
function fail_dependent_promises(PDO $db, int $failed_promise_id): void {
    // Find all promises that depend on this failed one
    // A canceled dependent stays canceled: the user stopped it, it did not fail.
    $stmt = $db->prepare("
        SELECT id, description FROM promises
        WHERE depends_on = :pid
          AND state NOT IN ('failed', 'completed')
          AND status <> 'canceled'
    ");
    $stmt->execute([':pid' => $failed_promise_id]);
    $dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dependents as $dependent) {
        // Mark as failed
        $update = $db->prepare("
            UPDATE promises
            SET state = 'failed', status = 'failed', updated_at = NOW()
            WHERE id = :id AND status <> 'canceled'
        ");
        $update->execute([':id' => $dependent['id']]);

        write_debug_log(
            "Failed promise #{$dependent['id']} ({$dependent['description']}) due to dependency failure",
            "warning"
        );

        // Recursively fail any promises depending on this one
        fail_dependent_promises($db, (int)$dependent['id']);
    }
}

/**
 * The live status of a promise, or null when the row does not exist.
 */
function promise_current_status(PDO $db, int $promise_id): ?string
{
    $stmt = $db->prepare("SELECT status FROM promises WHERE id = :id");
    $stmt->execute([':id' => $promise_id]);
    $status = $stmt->fetchColumn();
    return ($status === false || $status === null) ? null : (string)$status;
}

/*************************************
 * FUNCTION: CHECK AND FINALIZE TASK *
 *************************************/
function check_and_finalize_task(PDO $db, array $completed_promise, array $job_def): void {
    $queue_task_id = $completed_promise['queue_task_id'] ?? null;

    if (!$queue_task_id) {
        return;
    }

    try {
        // Finalize once the task's current chain has fully completed. Failed
        // promises from earlier, superseded chains must not block this — see
        // queue_task_chain_finished().
        $stmt = $db->prepare("SELECT status FROM queue_tasks WHERE id = :id");
        $stmt->execute([':id' => $queue_task_id]);
        if ($stmt->fetchColumn() === 'canceled') {
            write_debug_log("Task #{$queue_task_id} was canceled, not finalizing", "info");
            return;
        }

        if (queue_task_chain_finished($db, (int)$queue_task_id)) {
            write_debug_log("All promises completed for task #{$queue_task_id}, finalizing...", "info");

            // Mark queue task as completed — only from a live status, re-checked
            // in the UPDATE itself. The canceled SELECT above is advisory: a
            // cancel landing between it and this write must stay canceled, and a
            // task already marked failed (an older chain's permanent failure)
            // must not be flipped to completed. When nothing was finalized here,
            // on_success does not run either.
            $stmt = $db->prepare("
                UPDATE queue_tasks
                SET status='completed', updated_at=NOW()
                WHERE id=:id AND status IN ('pending','in_progress')
            ");
            $stmt->execute([':id' => $queue_task_id]);
            if ($stmt->rowCount() === 0) {
                write_debug_log("Task #{$queue_task_id} was no longer pending or in progress; not finalizing it.", "info");
                return;
            }

            // Call the on_success handler if it exists
            if (isset($job_def['on_success']) && is_callable($job_def['on_success'])) {
                write_debug_log("Calling on_success handler for task #{$queue_task_id}", "debug");
                try {
                    $job_def['on_success']($db, $completed_promise);
                } catch (Exception $e) {
                    write_debug_log(
                        "Error in on_success handler for task #{$queue_task_id}: " . $e->getMessage(),
                        "error"
                    );
                }
            }

            write_debug_log("Task #{$queue_task_id} finalized successfully", "info");
        }
    } catch (Exception $e) {
        write_debug_log("Error checking task finalization: " . $e->getMessage(), "error");
    }
}

/**
 * Process a single promise with stage handling, retries, and logging.
 *
 * @param array $promise The promise row from DB
 * @param array $jobDef The definition of the promise job
 * @param PDO $db Database connection
 * @param ?int $maxRetryAttempts Maximum attempts (null = QUEUE_RETRY_MAX_ATTEMPTS)
 * @param ?int $baseRetryDelay Base retry delay in seconds (null = QUEUE_RETRY_BASE_DELAY_SECONDS)
 * @param ?int $maxRetryDelay Maximum retry delay in seconds (null = QUEUE_RETRY_MAX_DELAY_SECONDS)
 * @param ?int $now The time the back-off is measured against (null = time(); a test seam)
 */
function process_promise(array $promise, array $jobDef, PDO $db, ?int $maxRetryAttempts = null, ?int $baseRetryDelay = null, ?int $maxRetryDelay = null, ?int $now = null): int
{
    $promiseId = $promise['id'];
    $stageName = $promise['current_stage'] ?? 'N/A';
    $payload = json_decode($promise['payload'] ?? '{}', true) ?: [];

    $retryAttempts = $payload['_retry_attempts'] ?? 0;
    $retryAvailableAt = $payload['_retry_available_at'] ?? 0;
    $now ??= time();

    // Respect backoff
    if ($now < $retryAvailableAt) {
        $sleepTime = $retryAvailableAt - $now;
        write_debug_log("Promise #{$promiseId} waiting for backoff: {$sleepTime}s remaining (attempt {$retryAttempts})", "debug");
        return 0; // Not failed yet
    }

    write_debug_log("Processing promise #{$promiseId}: type={$jobDef['type']}, stage={$stageName}, retries={$retryAttempts}", "info");

    // The row was read before this call; re-read the live status so a promise
    // canceled in the meantime is not started (and not flipped to in_progress).
    // Also skip when its queue task is canceled: a cancel can land between the
    // queue worker picking the task and its handler creating this chain, and
    // then no promise of the chain was canceled. Those still-live promises are
    // canceled here, or they would be fetched (and skipped) on every tick.
    $stmt = $db->prepare("
        SELECT p.status AS promise_status, t.status AS task_status
        FROM promises p LEFT JOIN queue_tasks t ON t.id = p.queue_task_id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $promiseId]);
    $live = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (($live['promise_status'] ?? null) === 'canceled') {
        write_debug_log("Promise #{$promiseId} was canceled, skipping", "info");
        return 0;
    }
    if (($live['task_status'] ?? null) === 'canceled') {
        $cancel = $db->prepare("
            UPDATE promises SET status = 'canceled', updated_at = NOW()
            WHERE queue_task_id = :t AND status IN ('pending', 'in_progress')
        ");
        $cancel->execute([':t' => $promise['queue_task_id']]);
        write_debug_log("Promise #{$promiseId} belongs to canceled task #{$promise['queue_task_id']}, skipping and canceling its chain", "info");
        return 0;
    }

    // Mark in progress
    update_promise_stage($promiseId, null, 'in_progress', null, $db);

    // Find stage function
    if (!isset($jobDef['stages'][$stageName])) {
        $msg = "Stage '{$stageName}' not found for job type '{$jobDef['type']}'";
        write_debug_log($msg, "error");
        handle_promise_failure($db, $promise, $msg, $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay, $jobDef);
        return 1;
    }

    $stageFn = $jobDef['stages'][$stageName];

    // Execute stage function safely
    try {
        $result = $stageFn($promise, $db);
    } catch (NonRetryableException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Permanent failure — do not retry regardless of maxRetryAttempts
        $msg = "Stage '{$stageName}' non-retryable failure: {$e->getMessage()}";
        write_debug_log($msg, "error");
        handle_promise_failure($db, $promise, $msg, 1, 0, 0, $jobDef);
        return 1;
    } catch (\Throwable $t) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Two-message split: full message with server-side path/line goes
        // to the debug log (operator inspecting a file on disk); the
        // message persisted to settings + returned to admin API consumers
        // strips the path/line to avoid disclosing filesystem layout to
        // anyone holding an admin API key. CIA: low-impact information
        // disclosure, defense-in-depth.
        $fullMsg     = "Stage '{$stageName}' threw error: {$t->getMessage()} in {$t->getFile()}:{$t->getLine()}";
        $sanitizedMsg = "Stage '{$stageName}' threw error: {$t->getMessage()}";
        write_debug_log($fullMsg, "error");
        handle_promise_failure($db, $promise, $sanitizedMsg, $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay, $jobDef);
        return 1;
    }

    // A stage that began a transaction and never ended it: roll its
    // uncommitted work back so the outcome below is committed on its own (and
    // the step's 'completed' is not lost when its claim is released).
    if ($db->inTransaction()) {
        write_debug_log("Stage '{$stageName}' of promise #{$promiseId} left a transaction open; its uncommitted writes are rolled back.", "warning");
        $db->rollBack();
    }

    $resultStr = is_array($result) ? json_encode($result) : var_export($result, true);
    write_debug_log("Stage '{$stageName}' returned: {$resultStr}", "debug");

    // Failure detection
    // @phan-suppress-next-line PhanTypeComparisonFromArray -- $stageFn is a dynamic callable that may return false/null/array/scalar
    if ($result === false || $result === null) {
        handle_promise_failure($db, $promise, "Stage returned " . var_export($result, true), $maxRetryAttempts, $baseRetryDelay, $maxRetryDelay, $jobDef);
        return 1;
    }

    // Success handling
    if (is_array($result)) {
        update_promise_stage($promiseId, null, 'completed', $result, $db);
    } else {
        update_promise_stage($promiseId, null, 'completed', null, $db);
    }

    write_debug_log("Promise #{$promiseId} stage '{$stageName}' completed successfully", "info");
    check_and_finalize_task($db, $promise, $jobDef);

    return 0; // success
}

/*********************************************************************
 * FUNCTION: CANCEL QUEUE TASK                                       *
 * Stop a queued task and any chain still to run for it.             *
 *                                                                   *
 * The promise fetcher treats a promise as terminal when EITHER      *
 * `state` or `status` holds a terminal value, so writing            *
 * status='canceled' is enough to make the worker skip it. A stage    *
 * already executing runs to completion — an in-flight AI call       *
 * cannot be interrupted — but nothing downstream of it starts.       *
 *                                                                   *
 * Deliberately does NOT touch tmp data: refs are job-specific, and   *
 * the caller knows which of its own it needs to delete.             *
 *                                                                   *
 * Only a live task (pending / in_progress) is canceled, re-checked  *
 * in the UPDATE itself: a cancel racing a completion must not erase *
 * a 'completed' row (ai_risk_fair_analyze's completed rows are the  *
 * AI re-analysis gate) or a 'failed' one. Returns false when the    *
 * task had already finished (it is left as it is) or on an error;   *
 * true when it is now canceled, or does not exist.                  *
 *********************************************************************/
function cancel_queue_task(PDO $db, int $task_id, string $reason): bool
{
    if ($task_id <= 0) {
        return true;
    }

    try {
        $stmt = $db->prepare("UPDATE `queue_tasks` SET `status` = 'canceled' WHERE `id` = :task_id AND `status` IN ('pending','in_progress')");
        $stmt->execute([':task_id' => $task_id]);

        if ($stmt->rowCount() === 0) {
            $current = queue_task_current_status($db, $task_id);
            if ($current !== null && $current !== 'canceled') {
                write_debug_log("[cancel_queue_task] Task {$task_id} had already finished ({$current}); left as it is: {$reason}", "info");
                return false;
            }
        }

        $stmt = $db->prepare("
            UPDATE `promises` SET `status` = 'canceled'
            WHERE `queue_task_id` = :task_id AND `status` IN ('pending','in_progress')
        ");
        $stmt->execute([':task_id' => $task_id]);

        write_debug_log("[cancel_queue_task] Canceled task {$task_id}: {$reason}", "info");

        return true;
    } catch (Throwable $e) {
        write_debug_log("[cancel_queue_task] Error canceling task {$task_id}: " . $e->getMessage(), "error");
        return false;
    }
}

/*********************************************************************
 * FUNCTION: CANCEL PARTIAL STAGE CHAIN                              *
 * For a queue_check whose stage chain could not be built in full    *
 * (create_stage_promise() returned 0 part-way): cancel the steps    *
 * already created for the task, so they do not run on their own and *
 * finalize the task early, then let the handler return false. The   *
 * worker's retry builds a fresh, complete chain. The task's own      *
 * status is not touched: the worker owns it (writing-queue-jobs).    *
 *********************************************************************/
function cancel_partial_stage_chain(PDO $db, int $task_id): void
{
    if ($task_id <= 0) {
        return;
    }

    try {
        $stmt = $db->prepare("
            UPDATE `promises` SET `status` = 'canceled', `updated_at` = NOW()
            WHERE `queue_task_id` = :task_id AND `status` IN ('pending','in_progress')
        ");
        $stmt->execute([':task_id' => $task_id]);
        if ($stmt->rowCount() > 0) {
            write_debug_log("[cancel_partial_stage_chain] Canceled {$stmt->rowCount()} step(s) of task {$task_id}'s incomplete chain.", "warning");
        }
    } catch (Throwable $e) {
        write_debug_log("[cancel_partial_stage_chain] Error canceling task {$task_id}'s incomplete chain: " . $e->getMessage(), "error");
    }
}

/*********************************
 * FUNCTION: CANCEL CONTROL TASK *
 *********************************/
function cancel_control_task(PDO $db, array $promise, string $reason): bool
{
    try
    {
        // Get the task_id
        $task_id = (int)$promise['queue_task_id'];

        write_debug_log(
            "[cancel_control_task] Canceling task {$task_id} for deleted control: {$reason}",
            "info"
        );

        // Cancel the task and any chain still to run for it. Delegated so the
        // two functions cannot drift: this one adds only the control-specific
        // tmp-data cleanup below.
        cancel_queue_task($db, $task_id, $reason);

        // Clean the tmp_files
        $payload = json_decode($promise['payload'], true) ?? [];
        $control_ref = $payload['control_ref'] ?? null;
        $matches_ref = $payload['matches_ref'] ?? [];

        // Delete the control tmp data
        if ($control_ref)
        {
            delete_tmp_data($db, $control_ref);
            write_debug_log("[clean_tmp_files] Deleted tmp control_ref: {$control_ref}", "debug");
        }

        foreach ($matches_ref as $ref)
        {
            if ($ref)
            {
                delete_tmp_data($db, $ref);
                write_debug_log("[clean_tmp_files] Deleted tmp matches_ref: {$ref}", "debug");
            }
        }

        unset($payload['control_ref']);
        unset($payload['matches_ref']);

        return true;
    } catch (Exception $e)
    {
        write_debug_log(
            "[cancel_control_task] Error: " . $e->getMessage(),
            "error"
        );
        return false;
    }
}

?>