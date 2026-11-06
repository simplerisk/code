<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Job definition for executing a queued workflow.
 * Payload: ['execution_id' => int]
 */

// Reachability (CLAUDE.md): queue_update_status() lives in queues.php, which
// functions.php does not load; declared directly rather than relying on the worker.
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));

return [
    'type' => 'core_workflow_execute',
    // One task of this type at a time across all queue workers (executions change records and send email; run them in the order they were queued).
    'concurrency' => 'serial',
    'restartable' => false, // re-runs an execution whose actions may already have fired; duplicates side effects

    // A workflow's actions (emails, field changes) are not safe to repeat, so a
    // failed execution is never retried automatically: the first failure is
    // terminal. The worker then marks the task failed, runs on_terminal_failure
    // below and notifies the administrators — which the handler marking its own
    // task 'failed' never did (writing-queue-jobs Rule 2).
    'max_retry_attempts' => 0,

    'queue_check' => function(array $task, PDO $db) {

        $payload = json_decode($task['payload'], true);

        if (!$payload || !isset($payload['execution_id'])) {
            write_debug_log("CORE_WORKFLOW_EXECUTE: Invalid payload for task #{$task['id']} — missing execution_id.", 'error');
            return false;
        }

        $execution_id = (int)$payload['execution_id'];

        write_debug_log("CORE_WORKFLOW_EXECUTE: Processing workflow execution #{$execution_id} (task #{$task['id']}).", 'info');

        queue_update_status($task['id'], 'in_progress', $db);

        try {
            // Load the workflow engine (action files, executor, variables)
            require_once(realpath(__DIR__ . '/../workflows.php'));

            $success = execute_workflow_execution($execution_id, $db);
        } catch (Exception $e) {
            write_debug_log("CORE_WORKFLOW_EXECUTE: Exception in execution #{$execution_id} (task #{$task['id']}): " . $e->getMessage(), 'error');
            // Rethrown so the worker records this message as the task's error
            // and hands it to on_terminal_failure.
            throw $e;
        }

        if ($success) {
            queue_update_status($task['id'], 'completed', $db);
            write_debug_log("CORE_WORKFLOW_EXECUTE: Execution #{$execution_id} finished successfully (task #{$task['id']}).", 'info');
            return true;
        }

        write_debug_log("CORE_WORKFLOW_EXECUTE: Execution #{$execution_id} failed (task #{$task['id']}).", 'error');
        return false;
    },

    // Runs once the task has failed for good (with max_retry_attempts 0, on its
    // first failure; also when it was found stuck). Marks the workflow
    // execution failed if it had not ended; the executor already does this on
    // most of its own failure paths, so the status guard makes this a no-op then.
    'on_terminal_failure' => function(array $task, PDO $db, string $errorMessage) {
        $payload = json_decode($task['payload'] ?? '', true);
        $execution_id = is_array($payload) ? (int)($payload['execution_id'] ?? 0) : 0;
        if ($execution_id <= 0) {
            return;
        }

        $stmt = $db->prepare("UPDATE `workflow_executions` SET `status` = 'failed' WHERE `id` = :id AND `status` IN ('queued','in_progress')");
        $stmt->bindParam(':id', $execution_id, PDO::PARAM_INT);
        $stmt->execute();
    },
];
