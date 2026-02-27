<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Job definition for sending emails immediately from the queue.
 * Placed in includes/jobs/core_email_send.php
 */

return [
    'type' => 'core_email_send',

    'queue_check' => function(array $task, PDO $db) {
        $payload = json_decode($task['payload'], true);

        if (!$payload || !isset($payload['recipient_name'], $payload['recipient_email'], $payload['subject'], $payload['body'])) {
            write_debug_log("QUEUE_CHECK: Invalid email payload for task #{$task['id']}", "error");
            queue_update_status($task['id'], 'failed', $db);
            return false;
        }

        write_debug_log("QUEUE_CHECK: Sending email to {$payload['recipient_email']} (task #{$task['id']})", "info");

        queue_update_status($task['id'], 'in_progress', $db);

        try {
            $success = send_email_immediate(
                $payload['recipient_name'],
                $payload['recipient_email'],
                $payload['subject'],
                $payload['body']
            );

            if ($success === false) {
                write_debug_log("QUEUE_CHECK: Failed to send email for task #{$task['id']}", "error");
                queue_update_status($task['id'], 'failed', $db);
                return false;
            }

            queue_update_status($task['id'], 'completed', $db);
            write_debug_log("QUEUE_CHECK: Email successfully sent for task #{$task['id']}", "info");
            return true;

        } catch (Exception $e) {
            write_debug_log("QUEUE_CHECK: Exception sending email for task #{$task['id']}: " . $e->getMessage(), "error");
            queue_update_status($task['id'], 'failed', $db);
            return false;
        }
    },

    // No task_check needed; queueing is done manually in code
];

?>