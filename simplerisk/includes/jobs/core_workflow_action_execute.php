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

return [
    'type' => 'core_workflow_action_execute',

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
            queue_update_status($task['id'], 'failed', $db);
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

            $task_status = ($step_status === 'success' || $step_status === 'skipped') ? 'completed' : 'failed';
            queue_update_status($task['id'], $task_status, $db);

            $log_level = ($task_status === 'failed') ? 'error' : 'info';
            write_debug_log("CORE_WORKFLOW_ACTION_EXECUTE: Action '{$action_type}' for execution #{$execution_id} finished with status '{$step_status}' (task #{$task['id']}).", $log_level);
            return $task_status === 'completed';

        } catch (Exception $e) {
            write_debug_log("CORE_WORKFLOW_ACTION_EXECUTE: Exception for action '{$action_type}' in execution #{$execution_id} (task #{$task['id']}): " . $e->getMessage(), 'error');

            // Mark step as failed
            try {
                $err_msg = $e->getMessage();
                $stmt = $db->prepare("UPDATE `workflow_execution_steps` SET `status` = 'failed', `error` = :error, `executed_at` = NOW() WHERE `id` = :id");
                $stmt->bindParam(':error', $err_msg, PDO::PARAM_STR);
                $stmt->bindParam(':id',    $step_id, PDO::PARAM_INT);
                $stmt->execute();
            } catch (Exception $inner) {
                // Swallow
            }

            queue_update_status($task['id'], 'failed', $db);
            return false;
        }
    },
];
