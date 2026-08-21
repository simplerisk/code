<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Job definition for sending emails immediately from the queue.
 * Placed in includes/jobs/core_email_send.php
 */

// Reachability: these are all loaded by the cron worker, but a job file that
// calls them must declare its own require_once (CLAUDE.md). queues.php gives
// queue_update_status(); mail.php gives send_email_immediate() and
// notify_email_send_failure(); functions.php gives write_debug_log().
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));
require_once(realpath(__DIR__ . '/../mail.php'));

return [
    'type' => 'core_email_send',

    'queue_check' => function(array $task, PDO $db) {
        // Claim the task BEFORE validating the payload, not after.
        //
        // Every exit below returns false so the worker's
        // handle_queue_task_failure() owns retry/backoff and the final status —
        // per writing-queue-jobs (CLAUDE.md), a queue_check must never
        // self-mark its own task 'failed'. But while retries remain,
        // handle_queue_task_failure() ONLY rewrites the payload; it never
        // changes the status. So a task that fails while still 'pending' stays
        // in the pending pool carrying a future next_retry_at. The worker
        // selects with `LIMIT 1 ORDER BY priority DESC` and only then skips a
        // backoff-gated row (cron_queue_worker.php), and send_email() enqueues
        // at priority 100 — so an un-parseable payload would sit at the head of
        // the queue and stall EVERY other background job for the duration of
        // its backoff windows. Claiming the task first moves it out of the
        // pending pool immediately, so a poison payload can't block the queue.
        queue_update_status($task['id'], 'in_progress', $db);

        $payload = json_decode($task['payload'], true);

        // Every failure exit below just returns false. Surfacing the failure to
        // a user is NOT done here — it is done in 'on_terminal_failure' below,
        // which the worker runs only once retries are exhausted.
        if (!$payload || !isset($payload['recipient_name'], $payload['recipient_email'], $payload['subject'], $payload['body'])) {
            write_debug_log("QUEUE_CHECK: Invalid email payload for task #{$task['id']}", "error");
            return false;
        }

        write_debug_log("QUEUE_CHECK: Sending email to {$payload['recipient_email']} (task #{$task['id']})", "info");

        try {
            $success = send_email_immediate(
                $payload['recipient_name'],
                $payload['recipient_email'],
                $payload['subject'],
                $payload['body']
            );

            if ($success === false) {
                write_debug_log("QUEUE_CHECK: Failed to send email for task #{$task['id']}", "error");
                return false;
            }

            queue_update_status($task['id'], 'completed', $db);
            write_debug_log("QUEUE_CHECK: Email successfully sent for task #{$task['id']}", "info");
            return true;

        } catch (Exception $e) {
            write_debug_log("QUEUE_CHECK: Exception sending email for task #{$task['id']}: " . $e->getMessage(), "error");
            return false;
        }
    },

    // Runs once the worker has exhausted this task's retries and marked it
    // 'failed'. Surfacing the failure here rather than from queue_check is the
    // whole point: queue_check runs on every attempt, so notifying from there
    // fires on the first recoverable blip (SMTP reset, relay rate-limit, DNS
    // hiccup) — the retry then succeeds and the user is left holding a
    // permanent, unread notification for an email that WAS delivered, with no
    // retraction path (the dedup guid suppresses any follow-up row).
    //
    // Only the target is passed. Dedup is per (target, hour), not per task, so
    // a systemic fault — SMTP down while a bulk send is in flight — raises one
    // notification instead of one per doomed message. This task's recipient and
    // payload remain individually visible in the Queue Monitor, which the
    // notification body points at.
    'on_terminal_failure' => function(array $task, PDO $db, string $errorMessage) {
        $payload = json_decode($task['payload'] ?? '{}', true) ?: [];

        notify_email_send_failure(
            $db,
            isset($payload['sender_uid']) ? (int)$payload['sender_uid'] : null
        );
    },

    // No task_check needed; queueing is done manually in code
];

?>