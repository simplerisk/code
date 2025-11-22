<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/queues.php'));

/****************************
 * FUNCTION: CREATE PROMISE *
 ****************************/
function create_promise(string $promise_type, array $stages, array $payload = []): int
{
    $db = db_open();
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
    $id = (int) $db->lastInsertId();
    db_close($db);
    return $id;
}

/***********************************************************
 * FUNCTION: CREATE STAGE PROMISE                          *
 * Creates a single-stage promise with optional dependency *
 ***********************************************************/
function create_stage_promise(
    string $promise_type,
    string $stage_name,
    array $payload = [],
    ?int $depends_on = null,
    ?int $reference_id = null,
    ?int $queue_task_id = null
): int {
    $db = db_open();
    if (!$db) {
        write_debug_log("CREATE_STAGE_PROMISE: Failed to open DB for stage '{$stage_name}'", "error");
        return 0;
    }

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

        db_close($db);
        return $id;

    } catch (PDOException $e) {
        write_debug_log("CREATE_STAGE_PROMISE: SQL error for stage '{$stage_name}': " . $e->getMessage(), "error");
        db_close($db);
        return 0;
    } catch (Exception $e) {
        write_debug_log("CREATE_STAGE_PROMISE: General error for stage '{$stage_name}': " . $e->getMessage(), "error");
        db_close($db);
        return 0;
    }

}

/*******************************************************
 * FUNCTION: UPDATE PROMISE STAGE                      *
 * Updates promise state and triggers completion logic *
 *******************************************************/
function update_promise_stage(int $promise_id, ?string $current_stage = null, ?string $status = null, ?array $payload = null): bool
{
    $db = db_open();
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
        db_close($db);
        return false;
    }

    $sql = "UPDATE promises SET " . implode(', ', $parts) . ", updated_at = NOW() WHERE id = :pid";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);

    // If this promise completed, propagate payload to next and check parent completion
    if ($status === 'completed') {
        if ($payload !== null) {
            propagate_payload_to_dependent($db, $promise_id, $payload);
        }
        recursively_fulfill_parent($db, $promise_id);
    }

    // If this promise failed, fail all dependent promises
    if ($status === 'failed') {
        fail_dependent_promises($db, $promise_id);
    }

    db_close($db);
    return (bool)$result;
}

/***************************************************************
 * FUNCTION: PROPAGATE PAYLOAD TO DEPENDENT                    *
 * Passes payload data from completed promise to next in chain *
 ***************************************************************/
function propagate_payload_to_dependent(PDO $db, int $completed_promise_id, array $payload): void
{
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

/**************************************************************************
 * FUNCTION: PROPAGATE PAYLOAD TO NEXT                                    *
 * Helper function to propagate payload data to the next promise in chain *
 **************************************************************************/
function propagate_payload_to_next(PDO $db, int $completed_promise_id, array $payload): void {
    // Find the next promise that depends on this one
    $stmt = $db->prepare("
        SELECT id FROM promises
        WHERE depends_on = :pid
          AND state = 'pending'
        LIMIT 1
    ");
    $stmt->execute([':pid' => $completed_promise_id]);
    $next_promise_id = $stmt->fetchColumn();

    if ($next_promise_id) {
        // Update the next promise's payload with the accumulated data
        $stmt = $db->prepare("
            UPDATE promises
            SET payload = :payload, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':payload' => json_encode($payload),
            ':id' => $next_promise_id
        ]);
        write_debug_log("Propagated payload from promise #{$completed_promise_id} to #{$next_promise_id}", "debug");
    }
}

/***********************************************************************
 * FUNCTION: HANDLE PROMISE FAILURE                                    *
 * Helper function to handle promise failures with exponential backoff *
 ***********************************************************************/
/**
 * Handle promise failure with retry logic and custom failure handlers
 */
function handle_promise_failure(
    PDO $db,
    array $promise,
    string $error_message,
    int $maxAttempts = 5,
    int $baseDelay = 5,
    int $maxDelay = 3600
): void {
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
        // Permanently fail the promise
        update_promise_stage($promise_id, null, 'failed', $payload);

        write_debug_log(
            "Promise #{$promise_id} permanently failed after {$attempts} attempts: {$error_message}",
            "error"
        );

        // Call custom failure handler if it exists
        $promise_type = $promise['promise_type'] ?? null;
        if ($promise_type) {
            try {
                $all_jobs = load_all_jobs();
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
            queue_update_status($queue_task_id, 'failed');
        }
        else
        {
            write_debug_log("No queue_task_id specified for promise '{$promise_id}'", "debug");
        }

        // Fail all dependent promises
        fail_dependent_promises($db, $promise_id);

    } else {
        // Calculate exponential backoff delay
        $delay = min($baseDelay * (2 ** ($attempts - 1)), $maxDelay);

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
        update_promise_stage($promise_id, null, 'pending', $payload);

    }
}

/*************************************************************
 * FUNCTION: FAIL DEPENDENT PROMISES                         *
 * Helper function to cascade failures to dependent promises *
 *************************************************************/
function fail_dependent_promises(PDO $db, int $failed_promise_id): void {
    // Find all promises that depend on this failed one
    $stmt = $db->prepare("
        SELECT id, description FROM promises
        WHERE depends_on = :pid
          AND state NOT IN ('failed', 'completed')
    ");
    $stmt->execute([':pid' => $failed_promise_id]);
    $dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dependents as $dependent) {
        // Mark as failed
        $update = $db->prepare("
            UPDATE promises
            SET state = 'failed', status = 'failed', updated_at = NOW()
            WHERE id = :id
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

/*************************************
 * FUNCTION: CHECK AND FINALIZE TASK *
 *************************************/
function check_and_finalize_task(PDO $db, array $completed_promise, array $job_def): void {
    $queue_task_id = $completed_promise['queue_task_id'] ?? null;

    if (!$queue_task_id) {
        return;
    }

    try {
        // Check if all promises for this task are completed
        $stmt = $db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM promises
            WHERE queue_task_id = :task_id
        ");
        $stmt->execute([':task_id' => $queue_task_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        // If all promises are completed, finalize the task
        if ($counts['total'] > 0 && $counts['total'] == $counts['completed']) {
            write_debug_log("All promises completed for task #{$queue_task_id}, finalizing...", "info");

            // Mark queue task as completed
            $stmt = $db->prepare("
                UPDATE queue_tasks 
                SET status='completed', updated_at=NOW() 
                WHERE id=:id
            ");
            $stmt->execute([':id' => $queue_task_id]);

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

?>