<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Queue Monitor mutation side (SR-2408): cancel, restart and run-now, singly or
 * in bulk, and bulk delete of finished tasks. Every action re-reads the row under
 * a lock, so a stale id (the task moved on since the page rendered) is reported
 * as skipped rather than applied.
 * Spec: docs/superpowers/specs/2026-10-24-queue-monitor-redesign-design.md
 *
 * Results are lists of ['id'=>int, 'result'=>'applied'|'skipped', 'reason'=>?string]
 * (restart adds 'new_id'). Reason codes: wrong_state, not_actionable,
 * not_restartable, already_restarted, stopping, steps_running, not_found, error.
 */

// Every direct consumer declares its own requires (function reachability rule).
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/queues.php'));
require_once(realpath(__DIR__ . '/promises.php'));
require_once(realpath(__DIR__ . '/queue_monitor.php'));
require_once(realpath(__DIR__ . '/workers.php'));

const QUEUE_BULK_MAX = 500;

/** Priority that puts a task at the head of the worker's priority-ordered pick. */
const QUEUE_RUN_NOW_PRIORITY = 100;

/**
 * Task types the monitor must never touch: the Encrypted Database Extra drives its
 * own lifecycle through these (hard prefix rule, independent of any job flag).
 */
function queue_task_type_is_protected(string $task_type): bool
{
    return str_starts_with($task_type, QUEUE_PROTECTED_TASK_TYPE_PREFIX);
}

/**
 * True for a task type whose finished rows are read back as program state, so
 * they must survive: the retention purge's exempt list (QUEUE_PURGE_EXEMPT_TASK_TYPES
 * in queues.php), reused so the two can never disagree. Deleting the history of
 * ai_risk_fair_analyze, for one, makes the AI Extra re-analyse risks with paid calls.
 */
function queue_task_type_history_is_kept(string $task_type): bool
{
    return in_array($task_type, QUEUE_PURGE_EXEMPT_TASK_TYPES, true);
}

/** True when finished tasks of this type may be deleted from the Queue Monitor. */
function queue_job_is_deletable(string $task_type): bool
{
    return !queue_task_type_is_protected($task_type) && !queue_task_type_history_is_kept($task_type);
}

/*****************************************
 * FUNCTION: JOB IS RESTARTABLE/CANCELABLE *
 *****************************************/
function queue_job_is_restartable(string $task_type): bool
{
    if (queue_task_type_is_protected($task_type)) {
        return false;
    }

    // Memoized, and read straight from load_all_jobs(): worker_get_job_definition() logs an
    // 'error' (and reloads every job file) for each unknown type.
    static $definitions = null;
    static $memo = [];
    if (!array_key_exists($task_type, $memo)) {
        if ($definitions === null) {
            $definitions = [];
            foreach (load_all_jobs() as $job) {
                if (isset($job['type'])) {
                    $definitions[$job['type']] = $job;
                }
            }
        }
        $memo[$task_type] = ($definitions[$task_type]['restartable'] ?? true) !== false;
    }
    return $memo[$task_type];
}

function queue_job_is_cancelable(string $task_type): bool
{
    return !queue_task_type_is_protected($task_type);
}

/** Decode a task payload (JSON string or already an array) into an array. */
function queue_actions_payload($payload): array
{
    if (is_array($payload)) {
        return $payload;
    }
    $decoded = json_decode((string)$payload, true);
    return is_array($decoded) ? $decoded : [];
}

/*****************************************
 * FUNCTION: ACTION ELIGIBILITY (PURE)   *
 *****************************************/
/**
 * Null when $action may be applied to $task, else the reason code. The optional
 * third argument replaces the job-registry lookup (tests).
 *
 * For delete, $task['live_steps'] is the caller's finding (queue_steps_have_live_step()
 * over the task's locked step rows): deleting the rows under a stage that is still
 * executing, or that the step worker can still pick up, would orphan its later
 * writes, so the task is skipped until its steps end. $task['recently_canceled'] is
 * likewise the caller's finding that a canceled task was canceled within
 * QUEUE_CANCEL_SETTLE_MINUTES (QUEUE_STUCK_TASK_MINUTES from config.php, never
 * less than 30): its handler may still be about to create steps.
 *
 * @param array{task_type?:string,status?:string,payload?:mixed,live_steps?:bool,recently_canceled?:bool} $task
 */
function queue_action_eligibility(array $task, string $action, ?callable $restartable = null): ?string
{
    $status = (string)($task['status'] ?? '');
    $type = (string)($task['task_type'] ?? '');

    switch ($action) {
        case 'cancel':
            if (!in_array($status, ['pending', 'in_progress'], true)) {
                return 'wrong_state';
            }
            return queue_job_is_cancelable($type) ? null : 'not_actionable';

        case 'run_now':
            if ($status !== 'pending') {
                return 'wrong_state';
            }
            return queue_job_is_cancelable($type) ? null : 'not_actionable';

        case 'restart':
            if (!in_array($status, ['failed', 'canceled'], true)) {
                return 'wrong_state';
            }
            $payload = queue_actions_payload($task['payload'] ?? []);
            if (isset($payload['restarted_as'])) {
                return 'already_restarted';
            }
            $ok = $restartable !== null ? (bool)$restartable($type) : queue_job_is_restartable($type);
            return $ok && !queue_task_type_is_protected($type) ? null : 'not_restartable';

        case 'delete':
            if (!in_array($status, QUEUE_DELETABLE_STATUSES, true)) {
                return 'wrong_state';
            }
            if (!queue_job_is_deletable($type)) {
                return 'not_actionable';
            }
            if (!empty($task['live_steps'])) {
                // A canceled task with a step still running is what the list shows as Stopping.
                return $status === 'canceled' ? 'stopping' : 'steps_running';
            }
            if ($status === 'canceled' && !empty($task['recently_canceled'])) {
                // Not shown as Stopping (no step rows yet), but it may still be settling.
                return 'stopping';
            }
            return null;
    }

    return 'wrong_state';
}

/*****************************************
 * FUNCTION: RESOLVE TARGET IDS          *
 *****************************************/
/**
 * Turn a request ({ids:[...]} or {all_filtered:true, filter:{...}}) into task ids.
 * error is null on success, else too_many_targets | no_targets.
 *
 * @return array{ids:list<int>,error:?string}
 */
function queue_resolve_target_ids(PDO $db, array $request, array $forced_filters = []): array
{
    if (filter_var($request['all_filtered'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
        // $forced_filters are set by the caller, never by the request (restart
        // passes exclude_restarted so already-restarted tasks are not counted).
        $filters = array_merge(queue_monitor_filters(is_array($request['filter'] ?? null) ? $request['filter'] : []), $forced_filters);
        $ids = queue_monitor_matching_ids($db, $filters, QUEUE_BULK_MAX + 1);
    } else {
        $raw = (array)($request['ids'] ?? []);
        if (count($raw) > QUEUE_BULK_MAX) {
            // Cheap refusal before looping over a huge array.
            return ['ids' => [], 'error' => 'too_many_targets'];
        }
        $ids = [];
        foreach ($raw as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int)$id > 0) {
                $ids[(int)$id] = (int)$id;
            }
        }
        $ids = array_values($ids);
    }

    if (count($ids) > QUEUE_BULK_MAX) {
        return ['ids' => [], 'error' => 'too_many_targets'];
    }
    if (!$ids) {
        return ['ids' => [], 'error' => 'no_targets'];
    }
    return ['ids' => $ids, 'error' => null];
}

/** Audit-log one applied action (risk id 1000 maps to 0 inside write_log). */
function queue_actions_audit(int $uid, int $task_id, string $task_type, string $action, string $extra = ''): void
{
    write_log(1000, $uid, "Queue task #{$task_id} ({$task_type}) {$action} by user #{$uid}" . $extra, 'queue_monitor');
}

/**
 * Shared per-id loop: lock the row, check eligibility, run $apply inside the
 * transaction, commit, then audit. $apply returns extra result fields.
 *
 * @param callable(PDO,array):array $apply
 */
function queue_actions_run(PDO $db, array $ids, int $uid, string $action, callable $apply): array
{
    if ($db->inTransaction()) {
        // Row locks and rollbacks here must not run inside a caller's transaction.
        throw new LogicException('Queue actions cannot run inside an open transaction');
    }
    $results = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        $result = ['id' => $id, 'result' => 'skipped', 'reason' => null];
        if ($action === 'restart') {
            $result['new_id'] = null;
        }

        try {
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT id, task_type, status, priority, payload FROM queue_tasks WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $db->rollBack();
                $result['reason'] = 'not_found';
                $results[] = $result;
                continue;
            }

            $reason = queue_action_eligibility($row, $action);
            if ($reason !== null) {
                $db->rollBack();
                $result['reason'] = $reason;
                $results[] = $result;
                continue;
            }

            $outcome = $apply($db, $row);
            if (isset($outcome['reason'])) {
                $db->rollBack();
                $result['reason'] = $outcome['reason'];
                $results[] = $result;
                continue;
            }

            $db->commit();
            $result['result'] = 'applied';
            $result = array_merge($result, array_diff_key($outcome, ['reason' => 1]));
            unset($result['audit']);
            try {
                queue_actions_audit($uid, $id, (string)$row['task_type'], $outcome['audit'] ?? $action);
            } catch (Throwable $e) {
                // The action is committed; a failed audit write must not report it as skipped.
                write_debug_log("Queue monitor {$action} on task #{$id} applied but audit log failed: " . $e->getMessage(), 'error');
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            write_debug_log("Queue monitor {$action} failed for task #{$id}: " . $e->getMessage(), 'error');
            $result['result'] = 'skipped';
            $result['reason'] = 'error';
        }
        $results[] = $result;
    }
    return $results;
}

/**
 * An empty payload may have been stored as the JSON array [] (json_encode([])),
 * which JSON_SET cannot add keys to. Rewrite it as {} before editing in SQL.
 *
 * Every Queue Monitor write to an existing row sets `updated_at = updated_at`:
 * the column is ON UPDATE CURRENT_TIMESTAMP, and these are bookkeeping edits,
 * not task activity. Bumping it would move the failed-in-24h count, Duration,
 * "last tried", the sort order and the retention clock of a restarted task, and
 * would make a never-started encryption task look touched to
 * encryption_pipeline_task_abandoned() (which stops its self-heal).
 */
function queue_actions_normalize_payload(PDO $db, int $id): void
{
    $stmt = $db->prepare(
        "UPDATE queue_tasks SET payload = JSON_OBJECT(), updated_at = updated_at
         WHERE id = :id AND JSON_TYPE(payload) = 'ARRAY' AND JSON_LENGTH(payload) = 0"
    );
    $stmt->execute([':id' => $id]);
}

/*****************************************
 * FUNCTION: CANCEL                      *
 *****************************************/
function queue_cancel_tasks(PDO $db, array $ids, int $uid): array
{
    return queue_actions_run($db, $ids, $uid, 'cancel', function (PDO $db, array $row) use ($uid): array {
        if (!cancel_queue_task($db, (int)$row['id'], "canceled by user #{$uid}")) {
            throw new RuntimeException('cancel_queue_task returned false');
        }
        return ['audit' => 'canceled'];
    });
}

/*****************************************
 * FUNCTION: RESTART                     *
 *****************************************/
function queue_restart_tasks(PDO $db, array $ids, int $uid): array
{
    return queue_actions_run($db, $ids, $uid, 'restart', function (PDO $db, array $row): array {
        $id = (int)$row['id'];
        queue_actions_normalize_payload($db, $id);

        // A canceled task whose in-flight step has not ended yet is still Stopping.
        $stmt = $db->prepare(
            "SELECT 1 FROM promises WHERE queue_task_id = :id AND status = 'canceled' AND state = 'in_progress' LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        if ($stmt->fetchColumn() !== false) {
            return ['reason' => 'stopping'];
        }
        // A worker still holds the task's claim: its handler is running right
        // now (a cancel does not interrupt a handler). Restarting it would run
        // the work twice. Exact, unlike the settle window, which stays the
        // fallback for Delete.
        if (queue_task_lock_in_use($db, $id)) {
            return ['reason' => 'stopping'];
        }

        // The claim: only one concurrent restart can flip restarted_as from absent.
        $stmt = $db->prepare(
            "UPDATE queue_tasks SET payload = JSON_SET(payload, '$.restarted_as', 0), updated_at = updated_at
             WHERE id = :id AND status IN ('failed','canceled')
               AND (JSON_EXTRACT(payload, '$.restarted_as') IS NULL
                    OR JSON_TYPE(JSON_EXTRACT(payload, '$.restarted_as')) = 'NULL')"
        );
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            return ['reason' => 'already_restarted'];
        }

        $payload = queue_actions_payload($row['payload']);
        $priority = isset($payload['original_priority']) && is_numeric($payload['original_priority'])
            ? (int)$payload['original_priority']
            : (int)$row['priority'];
        // recovery_count too: a restart is a fresh run, with a fresh allowance
        // of stuck-task recoveries (recover_stuck_queue_tasks()).
        foreach (['original_priority', 'retry_attempts', 'next_retry_at', 'last_error', 'restarted_as', 'recovery_count', 'run_open'] as $key) {
            unset($payload[$key]);
        }
        // Fresh retry metadata, as queue_task() initializes it.
        $payload['retry_attempts'] = 0;
        $payload['next_retry_at'] = date('Y-m-d H:i:s');
        $payload['last_error'] = null;
        $payload['restarted_from'] = $id;

        $stmt = $db->prepare(
            "INSERT INTO queue_tasks (task_type, payload, status, priority, created_at, updated_at)
             VALUES (:task_type, :payload, 'pending', :priority, NOW(), NOW())"
        );
        $stmt->execute([
            ':task_type' => $row['task_type'],
            ':payload'   => json_encode($payload),
            ':priority'  => $priority,
        ]);
        $new_id = (int)$db->lastInsertId();

        $stmt = $db->prepare("UPDATE queue_tasks SET payload = JSON_SET(payload, '$.restarted_as', :new_id), updated_at = updated_at WHERE id = :id");
        // PARAM_INT: emulated prepares would otherwise store the id as a JSON string.
        $stmt->bindValue(':new_id', $new_id, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return ['new_id' => $new_id, 'audit' => "restarted as task #{$new_id}"];
    });
}

/*****************************************
 * FUNCTION: RUN NOW                     *
 *****************************************/
function queue_run_now(PDO $db, array $ids, int $uid): array
{
    return queue_actions_run($db, $ids, $uid, 'run_now', function (PDO $db, array $row): array {
        queue_actions_normalize_payload($db, (int)$row['id']);
        $payload = queue_actions_payload($row['payload']);
        $set = '';
        $params = [':id' => (int)$row['id'], ':prio' => QUEUE_RUN_NOW_PRIORITY];
        if (!isset($payload['original_priority'])) {
            $set = "'$.original_priority', :orig";
            $params[':orig'] = (int)$row['priority'];
        }
        // Keeps retry_attempts; drops the backoff gate so the worker may pick it now.
        // updated_at is kept (see queue_actions_normalize_payload()): run-now does no work.
        $payload_sql = $set !== ''
            ? "JSON_REMOVE(JSON_SET(payload, {$set}), '$.next_retry_at')"
            : "JSON_REMOVE(payload, '$.next_retry_at')";
        $stmt = $db->prepare(
            "UPDATE queue_tasks SET priority = :prio, payload = {$payload_sql}, updated_at = updated_at
             WHERE id = :id AND status = 'pending'"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        // No rowCount check: a repeat changes nothing (0 rows), and the row is locked and
        // already verified pending.
        return ['audit' => 'run now (priority ' . QUEUE_RUN_NOW_PRIORITY . ')'];
    });
}

/*****************************************
 * FUNCTION: DELETE                      *
 *****************************************/
/**
 * Permanently deletes finished tasks and their step rows (bulk only in the UI).
 * Per task, in one transaction: the task row is locked by queue_actions_run(),
 * its steps are locked here so none can start or end between the check and the
 * delete, eligibility is re-checked with what the steps show (and, for a canceled
 * task, whether the cancel is still inside QUEUE_CANCEL_SETTLE_MINUTES), then the steps go
 * FIRST and the task second (the DELETE re-checks the status). The audit entry is
 * written after the commit by queue_actions_run().
 *
 * A task another task points at (restarted_as / restarted_from) may be deleted:
 * those links are informational and the list tolerates a missing target.
 */
function queue_delete_tasks(PDO $db, array $ids, int $uid): array
{
    return queue_actions_run($db, $ids, $uid, 'delete', function (PDO $db, array $row): array {
        $id = (int)$row['id'];

        // A worker still holds the task's claim: its handler is running right
        // now (a canceled task's handler finishes on its own). Deleting it
        // would leave whatever that handler writes next pointing at nothing.
        if (queue_task_lock_in_use($db, $id)) {
            return ['reason' => 'stopping'];
        }

        $stmt = $db->prepare("SELECT status, state FROM promises WHERE queue_task_id = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $row['live_steps'] = queue_steps_have_live_step($stmt->fetchAll(PDO::FETCH_ASSOC));
        // Measured on the DB clock, the same clock that stamped the cancel.
        $stmt = $db->prepare("SELECT updated_at >= NOW() - INTERVAL :m MINUTE FROM queue_tasks WHERE id = :id");
        $stmt->bindValue(':m', QUEUE_CANCEL_SETTLE_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row['recently_canceled'] = (int)$stmt->fetchColumn() === 1;
        $reason = queue_action_eligibility($row, 'delete');
        if ($reason !== null) {
            return ['reason' => $reason];
        }

        $db->prepare("DELETE FROM promises WHERE queue_task_id = :id")->execute([':id' => $id]);

        $statuses = [];
        foreach (QUEUE_DELETABLE_STATUSES as $i => $status) {
            $statuses[":s{$i}"] = $status;
        }
        $stmt = $db->prepare("DELETE FROM queue_tasks WHERE id = :id AND status IN (" . implode(', ', array_keys($statuses)) . ")");
        $stmt->execute([':id' => $id] + $statuses);
        if ($stmt->rowCount() !== 1) {
            // Unreachable while the row lock holds; rolls the step delete back if it ever is.
            return ['reason' => 'wrong_state'];
        }

        return ['audit' => 'deleted'];
    });
}
