<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Notifies a document/exception owner when its next_review_date has arrived
 * (SR-189).
 *
 * task_check scans `documents` and `document_exceptions` for rows whose
 * next_review_date has passed and enqueues one `governance_review_due` queue
 * task per record; queue_check emails the owner.
 *
 * Per writing-queue-jobs' anti-requeue-storm rules:
 *  - Each table's own gate column (`queue_timestamp_last_review_due`) is
 *    stamped at the START of every queue_check attempt, never only on
 *    success -- so a failing send doesn't cause task_check to re-select and
 *    requeue the same record on every worker tick.
 *  - queue_check never marks its own task 'failed'; it returns false and lets
 *    the worker's handle_queue_task_failure() own retries/backoff.
 *
 * WHY review_frequency > 0 IS PART OF THE SCAN: `review_frequency` defaults to
 * 0 on both the DB column and the add/edit modals, and approve_document()
 * computes next_review_date = approval_date + review_frequency days -- so a
 * record left on the default gets next_review_date = today, forever, and the
 * per-record gate re-arms every day (queue_timestamp_last_review_due <
 * CURDATE()). Without this predicate the owner of every default-configured
 * document and exception is emailed EVERY SINGLE DAY, indefinitely.
 * review_frequency = 0 means "no periodic review scheduled" -- a legitimate and
 * common state -- not "review due daily".
 *
 * FIRST-RUN BLAST RADIUS: upgrade_from_20260820001() stamps
 * queue_timestamp_last_review_due = NOW() on every already-past-due record when
 * it adds the gate columns, so deploying this feature does not sweep an
 * instance's entire history of overdue records into one mass-email event. Those
 * records re-arm naturally on their next due date.
 */

// Reachability: queue_task() / get_queue_items() live in queues.php, which
// functions.php does not load -- the cron worker requires it at runtime, but
// a direct consumer declares its own require_once (CLAUDE.md). mail.php
// gives send_email().
require_once(realpath(__DIR__ . '/../functions.php'));
require_once(realpath(__DIR__ . '/../queues.php'));
require_once(realpath(__DIR__ . '/../mail.php'));

// Declared as a local variable (not a top-level function or const) because
// load_all_jobs() include()s this file on every loader/worker tick -- a
// top-level function or const declaration would fatally redeclare on the
// second include. Mirrors the closure-variable pattern in
// core_document_update.php.
$governance_review_due_entities = [
    'documents' => [
        'id_col'    => 'id',
        'owner_col' => 'document_owner',
        'name_col'  => 'document_name',
        'key'       => 'document_id',
    ],
    'document_exceptions' => [
        'id_col'    => 'value',
        'owner_col' => 'owner',
        'name_col'  => 'name',
        'key'       => 'exception_id',
    ],
];

return [
    'type' => 'governance_review_due',

    'task_check' => function (PDO $db) use ($governance_review_due_entities) {
        // Admin kill-switch, mirroring core_notifications_remote_feed's
        // NOTIFICATIONS_REMOTE_FEED_ENABLED gate. Seeded 'true' by
        // upgrade_from_20260820001(); the get_setting() default keeps the job
        // working on an instance whose settings row is missing.
        if (get_setting('GOVERNANCE_REVIEW_DUE_ENABLED', 'true', false, db: $db) !== 'true') {
            write_debug_log("Governance Review Due: disabled by setting; skipping.", 'debug');
            return false;
        }

        $enqueued = 0;

        foreach ($governance_review_due_entities as $table => $entity) {
            // LEFT JOIN against any pending/in_progress task already covering
            // this record, so a record isn't requeued on every loader tick
            // while its first task is still waiting on the worker -- the gate
            // column below is stamped only once queue_check actually runs.
            $json_path = '$.' . $entity['key'];
            $sql = "
                SELECT t.`{$entity['id_col']}` as id
                FROM `{$table}` t
                LEFT JOIN queue_tasks qt
                    ON qt.task_type = 'governance_review_due'
                    AND qt.status IN ('pending', 'in_progress')
                    AND CAST(JSON_EXTRACT(qt.payload, '{$json_path}') AS UNSIGNED) = t.`{$entity['id_col']}`
                WHERE t.`next_review_date` <= CURDATE()
                  AND t.`next_review_date` != '0000-00-00'
                  AND t.`review_frequency` > 0
                  AND (t.`queue_timestamp_last_review_due` IS NULL OR t.`queue_timestamp_last_review_due` < CURDATE())
                  AND qt.id IS NULL
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $payload = [
                    'triggered_at'   => time(),
                    'entity_type'    => $table,
                    $entity['key']   => (int)$row['id'],
                ];

                if (queue_task($db, 'governance_review_due', $payload, 50, 5, 3600)) {
                    $enqueued++;
                }
            }
        }

        write_debug_log("Governance Review Due: task_check enqueued {$enqueued} task(s).", 'debug');

        return $enqueued > 0;
    },

    'queue_check' => function (array $task, PDO $db) use ($governance_review_due_entities) {
        $payload = json_decode($task['payload'], true) ?? [];
        $table = $payload['entity_type'] ?? null;

        if (!isset($governance_review_due_entities[$table])) {
            write_debug_log("Governance Review Due: Unknown/missing entity_type in task #{$task['id']} payload.", 'error');
            return false;
        }

        $entity = $governance_review_due_entities[$table];
        $id = (int)($payload[$entity['key']] ?? 0);

        if (!$id) {
            write_debug_log("Governance Review Due: Missing {$entity['key']} in task #{$task['id']} payload.", 'error');
            return false;
        }

        // Stamp the gate FIRST, before any lookup/send -- a failing send must
        // not requeue this record every worker tick.
        $stmt = $db->prepare("UPDATE `{$table}` SET `queue_timestamp_last_review_due` = NOW() WHERE `{$entity['id_col']}` = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $db->prepare("SELECT `{$entity['name_col']}` as name, `{$entity['owner_col']}` as owner_id FROM `{$table}` WHERE `{$entity['id_col']}` = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            write_debug_log("Governance Review Due: {$table}.{$entity['id_col']}={$id} no longer exists; nothing to notify.", 'info');
            return true;
        }

        if (empty($record['owner_id'])) {
            write_debug_log("Governance Review Due: No owner set for {$table}.{$entity['id_col']}={$id}; nothing to notify.", 'info');
            return true;
        }

        $owner = get_user_by_id((int)$record['owner_id']);
        if (!$owner || empty($owner['email'])) {
            write_debug_log("Governance Review Due: Owner #{$record['owner_id']} has no usable email for {$table}.{$entity['id_col']}={$id}.", 'warning');
            return true;
        }

        $name = (string)$record['name'];
        // The SUBJECT is plain text -- send_email()/PHPMailer sets it via
        // Subject with no HTML decode step (mail.php), so _lang()'s
        // unconditional escapeHtml() of its params would surface literally:
        // `Bob's Q1 & Q2 Policy` -> `Bob&#039;s Q1 &amp; Q2 Policy`. Use
        // _lang_raw() there (CLAUDE.md's _lang()/_lang_raw() rule).
        //
        // The BODY stays on _lang(): mail.php calls isHTML(true), so the body
        // IS rendered as HTML and the entities decode exactly once -- and the
        // escape is what keeps a document name out of the markup.
        $subject = _lang_raw('GovernanceReviewDueEmailSubject', ['name' => $name]);
        $body = _lang('GovernanceReviewDueEmailBody', ['name' => $name]);

        $recipient_name = !empty($owner['name']) ? $owner['name'] : $owner['email'];
        $sent = send_email($db, $recipient_name, $owner['email'], $subject, $body);

        if (!$sent) {
            // Do NOT mark the task 'failed' here -- return false and let the
            // worker's handle_queue_task_failure() own retries/backoff.
            write_debug_log("Governance Review Due: Failed to queue notification email for {$table}.{$entity['id_col']}={$id}.", 'warning');
            return false;
        }

        return true;
    },
];

?>
