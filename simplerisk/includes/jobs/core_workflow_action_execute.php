<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Job definition for executing a single queued workflow action.
 * Queued by the executor when an inline (sync_execution) workflow
 * encounters an action with sync=false.
 *
 * Payload: [
 *   'execution_id' => int,
 *   'step_id'      => int,   // workflow_execution_steps row to update
 *   'action_type'  => string,
 *   'inputs'       => array, // already-resolved inputs
 *   'context'      => array,
 * ]
 */

// Reachability (CLAUDE.md): queue_update_status() lives in queues.php, which
// functions.php does not load; declared directly rather than relying on the worker.
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));

return [
    'type' => 'core_workflow_action_execute',
    // One task of this type at a time across all queue workers (an execution's queued actions must run in order, one after another).
    'concurrency' => 'serial',
    'restartable' => false, // replays one action with a stale resolved context; duplicates side effects

    // An action's side effects (an email sent, a field changed) are not safe to
    // repeat, so a failed action is never retried automatically: the first
    // failure is terminal. The worker then marks the task failed, runs
    // on_terminal_failure below and notifies the administrators — which the
    // handler marking its own task 'failed' never did (writing-queue-jobs Rule 2).
    'max_retry_attempts' => 0,

    'queue_check' => function(array $task, PDO $db) {

        $payload = json_decode($task['payload'], true);

        if (!$payload
            || !isset($payload['execution_id'])
            || !isset($payload['step_id'])
            || !isset($payload['action_type'])
            || !isset($payload['inputs'])
            || !isset($payload['context'])
        ) {
            write_debug_log("CORE_WORKFLOW_ACTION_EXECUTE: Invalid payload for task #{$task['id']} — missing required fields.", 'error');
            return false;
        }

        $execution_id = (int)$payload['execution_id'];
        $step_id      = (int)$payload['step_id'];
        $action_type  = (string)$payload['action_type'];
        $inputs       = (array)$payload['inputs'];
        $context      = (array)$payload['context'];

        write_debug_log("CORE_WORKFLOW_ACTION_EXECUTE: Processing action '{$action_type}' for execution #{$execution_id} (task #{$task['id']}).", 'info');

        queue_update_status($task['id'], 'in_progress', $db);

        try {
            require_once(realpath(__DIR__ . '/../workflows.php'));

            $result      = execute_workflow_action($action_type, $inputs, $context);
            $step_status = $result['status'] ?? 'failed';
            $output_json = json_encode($result['output'] ?? []);
            $error       = $result['error'] ?? null;

            // Update the pre-recorded step row from 'queued' to its final status
            $stmt = $db->prepare("
                UPDATE `workflow_execution_steps`
                SET `status` = :status, `output` = :output, `error` = :error, `executed_at` = NOW()
                WHERE `id` = :id
            ");
            $stmt->bindParam(':status', $step_status, PDO::PARAM_STR);
            $stmt->bindParam(':output', $output_json, PDO::PARAM_STR);
            $stmt->bindParam(':error',  $error,       PDO::PARAM_STR);
            $stmt->bindParam(':id',     $step_id,     PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            write_debug_log("CORE_WORKFLOW_ACTION_EXECUTE: Exception for action '{$action_type}' in execution #{$execution_id} (task #{$task['id']}): " . $e->getMessage(), 'error');
            // Rethrown so the worker records this message as the task's error
            // and on_terminal_failure marks the step failed with it.
            throw $e;
        }

        $succeeded = ($step_status === 'success' || $step_status === 'skipped');
        $log_level = $succeeded ? 'info' : 'error';
        write_debug_log("CORE_WORKFLOW_ACTION_EXECUTE: Action '{$action_type}' for execution #{$execution_id} finished with status '{$step_status}' (task #{$task['id']}).", $log_level);

        if ($succeeded) {
            queue_update_status($task['id'], 'completed', $db);
            return true;
        }

        // The step row already holds the action's own failure and error.
        // Thrown rather than returning false so the worker records the action's
        // error as the task's last_error ("Handler returned false" told the
        // Queue Monitor nothing). With max_retry_attempts 0 this is still a
        // single, terminal failure; on_terminal_failure leaves the already
        // failed step as it is.
        throw new RuntimeException("Workflow action '{$action_type}' failed: " . ($error ?? "status '{$step_status}'"));
    },

    // Runs once the task has failed for good (with max_retry_attempts 0, on its
    // first failure; also when it was found stuck). Marks the step failed when
    // the action never got to record an outcome (an exception, or the task
    // stopped part-way); a step the action itself already finished keeps it.
    'on_terminal_failure' => function(array $task, PDO $db, string $errorMessage) {
        $payload = json_decode($task['payload'] ?? '', true);
        $step_id = is_array($payload) ? (int)($payload['step_id'] ?? 0) : 0;
        if ($step_id <= 0) {
            return;
        }

        $stmt = $db->prepare("UPDATE `workflow_execution_steps` SET `status` = 'failed', `error` = :error, `executed_at` = NOW() WHERE `id` = :id AND `status` = 'queued'");
        $stmt->bindParam(':error', $errorMessage, PDO::PARAM_STR);
        $stmt->bindParam(':id',    $step_id,      PDO::PARAM_INT);
        $stmt->execute();
    },
];
