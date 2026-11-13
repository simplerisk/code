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
    // Tasks of this type may run on several queue workers at once (each message is independent; the per-task claim sends it once).
    'concurrency' => 'parallel',

    'queue_check' => function(array $task, PDO $db) {
        // No up-front 'in_progress' claim. Every exit below returns false and
        // leaves the status to the worker: while retries remain the task stays
        // 'pending' with a future next_retry_at, and the worker's pick
        // (queue_claim_next_runnable_task()) skips a pending task that is still
        // backing off and runs the next runnable one — so a poison payload at
        // priority 100 does not block the head of the queue. The pick applies
        // the back-off in its SQL, so even a large backlog of failing emails (a
        // bulk send during a mail outage) never fills its window. Claiming the task
        // here (as this job used to) took it out of the pending pool, so the
        // worker's retry back-off never applied and only the stuck-task
        // recovery ever ran it again.
        //
        // Sent once: the task stays 'pending' while this handler runs, so the
        // worker's per-task claim (a named lock held from the pick until the
        // outcome is written) is what keeps a second queue worker, or a worker
        // on another host, from sending the same email meanwhile.
        //
        // The retry window comes from send_email(), which stores a 300-second
        // base delay on the task; the longest wait and the number of retries
        // follow the retry settings. At the defaults that is 5, 10, 20, 40 and
        // 60 minutes (about two and a quarter hours) before the task fails and
        // on_terminal_failure notifies.

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