<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Job definition for executing a queued workflow.
 * Payload: ['execution_id' => int]
 */

return [
    'type' => 'core_workflow_execute',

    'queue_check' => function(array $task, PDO $db) {

        $payload = json_decode($task['payload'], true);

        if (!$payload || !isset($payload['execution_id'])) {
            write_debug_log("CORE_WORKFLOW_EXECUTE: Invalid payload for task #{$task['id']} — missing execution_id.", 'error');
            queue_update_status($task['id'], 'failed', $db);
            return false;
        }

        $execution_id = (int)$payload['execution_id'];

        write_debug_log("CORE_WORKFLOW_EXECUTE: Processing workflow execution #{$execution_id} (task #{$task['id']}).", 'info');

        queue_update_status($task['id'], 'in_progress', $db);

        try {
            // Load the workflow engine (action files, executor, variables)
            require_once(realpath(__DIR__ . '/../workflows.php'));

            $success = execute_workflow_execution($execution_id, $db);

            if ($success) {
                queue_update_status($task['id'], 'completed', $db);
                write_debug_log("CORE_WORKFLOW_EXECUTE: Execution #{$execution_id} finished successfully (task #{$task['id']}).", 'info');
                return true;
            } else {
                queue_update_status($task['id'], 'failed', $db);
                write_debug_log("CORE_WORKFLOW_EXECUTE: Execution #{$execution_id} failed (task #{$task['id']}).", 'error');
                return false;
            }

        } catch (Exception $e) {
            write_debug_log("CORE_WORKFLOW_EXECUTE: Exception in execution #{$execution_id} (task #{$task['id']}): " . $e->getMessage(), 'error');
            queue_update_status($task['id'], 'failed', $db);

            // Mark the workflow execution as failed too
            try {
                $stmt = $db->prepare("UPDATE `workflow_executions` SET `status` = 'failed' WHERE `id` = :id AND `status` = 'in_progress'");
                $stmt->bindParam(':id', $execution_id, PDO::PARAM_INT);
                $stmt->execute();
            } catch (Exception $inner) {
                // Swallow — don't mask the original exception in the log
            }

            return false;
        }
    },
];
