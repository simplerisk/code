<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// get_setting() / update_or_insert_setting() live in functions.php.
require_once(realpath(__DIR__ . '/../functions.php'));
// promise_chain_exists() lives in promises.php, which functions.php does not
// auto-load. Declare it directly so this consumer's defining file is always
// reachable, not only transitively via the worker (CLAUDE.md).
require_once(realpath(__DIR__ . '/../promises.php'));
// ai_provider_is_configured() lives in artificial_intelligence.php, which
// functions.php does not auto-load either — declare it directly (CLAUDE.md).
require_once(realpath(__DIR__ . '/../artificial_intelligence.php'));
// format_setting_timestamp() lives in setting_values.php — declare it directly
// (CLAUDE.md) rather than relying on any transitive include.
require_once(realpath(__DIR__ . '/../setting_values.php'));

return [
    'type' => 'core_ai_context_update',
    // One task of this type at a time across all queue workers (handler only; task_check keeps one task at a time).
    'concurrency' => 'serial',

    /************************************************************
     * FUNCTION: on_failure
     * Custom failure handler called when a promise fails permanently
     ************************************************************/
    'on_failure' => function(PDO $db, array $promise, string $error_message, int $attempts) {
        write_debug_log(
            "AI Context Update: Permanently failed after {$attempts} attempts: {$error_message}",
            "error"
        );

        // Log the failure for monitoring
        write_log(0, 0, "AI Context Update failed: {$error_message}", 'artificial_intelligence');
    },

    /************************************************************
     * FUNCTION: on_success
     * Custom success handler called when all stages complete
     ************************************************************/
    'on_success' => function(PDO $db, array $final_promise) {
        write_debug_log("AI Context Update: All stages completed successfully", "info");

        try {
            // Update the timestamp to indicate successful completion
            update_setting("ai_context_last_updated", time());
            write_debug_log("AI Context Update: Context last updated timestamp set.", "info");
        } catch (Exception $e) {
            write_debug_log("AI Context Update: Failed to update timestamp: " . $e->getMessage(), "error");
        }
    },

    /************************************************************
     * FUNCTION: task_check
     * Checks if a task should be queued. Can return true if a task
     * already exists, false if nothing to queue.
     ************************************************************/
    'task_check' => function(PDO $db) {
        // If no AI provider is configured (a key, or a keyless-local provider — SR-1931)
        if (!ai_provider_is_configured($db)) {
            write_debug_log("AI provider not configured.", "notice");
            return false;
        }

        write_debug_log("AI Context Update: Checking for pending updates.", "debug");

        $last_saved = get_setting("ai_context_last_saved", false, false, db: $db);
        $last_updated = get_setting("ai_context_last_updated", false, false, db: $db);

        // Both values come out of a string column and may be '' on a row that
        // exists but was never stamped. format_setting_timestamp() coerces so a
        // debug log line can't raise a TypeError and abort this task_check —
        // which silently disabled this job entirely for over a month.
        write_debug_log("AI Context Update: Last saved at " . format_setting_timestamp($last_saved), "debug");
        write_debug_log("AI Context Update: Last updated at " . format_setting_timestamp($last_updated), "debug");

        // Compare as integers for the same reason: '' and '0' must both read as
        // "never", and a string/string comparison of numeric timestamps would
        // otherwise sort lexicographically.
        $last_saved   = coerce_setting_timestamp($last_saved);
        $last_updated = coerce_setting_timestamp($last_updated);

        // The attempt gate (writing-queue-jobs Rule 1): stamped below whenever a
        // task is queued, whatever that task's outcome. ai_context_last_updated
        // is stamped only on success, so on its own it re-queued a failing
        // update on every loader tick.
        $last_attempt = coerce_setting_timestamp(get_setting("queue_timestamp_last_ai_context_update", false, false, db: $db));

        // Attempts that failed since the last one that succeeded, newest
        // first; bounds how long the gate backs off. Canceled tasks are not
        // attempts that failed and are not counted.
        $consecutive_failures = 0;
        try {
            $stmt = $db->prepare("
                SELECT status FROM queue_tasks
                WHERE task_type = 'core_ai_context_update' AND status IN ('completed','failed')
                ORDER BY id DESC
                LIMIT 10
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $status) {
                if ($status !== 'failed') {
                    break;
                }
                $consecutive_failures++;
            }
        } catch (Exception $e) {
            write_debug_log("AI Context Update: DB check failed: " . $e->getMessage(), "error");
            return false;
        }

        if (ai_context_update_due(time(), $last_saved, $last_updated, $last_attempt, $consecutive_failures)) {
            try {
                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM queue_tasks
                    WHERE task_type = 'core_ai_context_update'
                        AND status IN ('pending','in_progress')
                ");
                $stmt->execute();
                $running_count = (int)$stmt->fetchColumn();

                // Check that we have context settings to process
                $stmt = $db->prepare("SELECT COUNT(*) FROM `settings` WHERE name like 'ai_context_%';");
                $stmt->execute();
                $settings_count = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                write_debug_log("AI Context Update: DB check failed: " . $e->getMessage(), "error");
                return false;
            }

            // If the task is not already running and we have settings we can process
            if ($running_count === 0 && $settings_count > 0) {
                // ai_context_* rows existing isn't enough — the questionnaire may
                // have rows with no answers yet, or none of the current context
                // parameters may match what's stored. Queuing in that case just
                // burns 5 exponential-backoff retries before the task permanently
                // fails in generate_message_context() ("Context content empty").
                // Check for actual answerable content and skip quietly instead,
                // matching the "no settings found" branch below.
                $context_content = generate_ai_business_context();
                if (trim($context_content) === '') {
                    write_debug_log("AI Context Update: AI context settings exist but no answers have been provided yet. Skipping queueing.", "notice");
                    return false;
                }

                // Stamp the attempt BEFORE queueing, so the gate advances
                // whatever this task's outcome (see the gate above).
                update_or_insert_setting("queue_timestamp_last_ai_context_update", time(), db: $db);

                $queue_task_payload = [
                    'triggered_at'      => time(),
                ];
                queue_task($db, 'core_ai_context_update', $queue_task_payload, 50, 5, 3600);
                write_debug_log("AI Context Update: Queued new task.", "info");
                return true;
            } elseif ($running_count > 0) {
                write_debug_log("AI Context Update: Task already running ({$running_count}). Skipping queueing.", "debug");
                return false;
            } else {
                write_debug_log("AI Context Update: No AI context settings found (ai_context_* rows missing). Complete the AI context questionnaire to enable recommendations.", "notice");
                return false;
            }
        } else {
            write_debug_log("AI Context Update: No update required, or backing off after {$consecutive_failures} failed attempt(s).", "debug");
            return false;
        }
    },

    /************************************************************
     * FUNCTION: queue_check
     * Called when the task is pulled from the queue. Creates
     * promises for the staged AI work.
     ************************************************************/
    'queue_check' => function(array $task, PDO $db) {
        write_debug_log("AI Context Update: Creating promises for queued task #{$task['id']}.", "info");

        $payload = json_decode($task['payload'], true) ?? [];
        $triggered_at = $payload['triggered_at'] ?? time();

        // Idempotency guard: if a live promise chain already exists for this
        // task, do not create another (re-chaining runaway protection).
        // Shared with core_document_update / core_control_update via the
        // state+status-aware promise_chain_exists() in promises.php.
        if (promise_chain_exists($db, (int)$task['id'], 'core_ai_context_update')) {
            write_debug_log("AI Context Update: Live promise chain already exists for task #{$task['id']}. Skipping duplicate creation.", "info");
            // true, not false: the chain is running, so this attempt succeeded.
            // false would burn the retries and fail the task while its chain
            // still runs; finalize (pending/in_progress only) would then never
            // complete it, and on_success would never stamp the context.
            return true;
        }

        $stages = [
            'generate_message_context',
            'ask_ai_for_recommendations',
            'update_ai_recommendations',
        ];

        $prev_promise_id = null;

        foreach ($stages as $stage_name) {
            write_debug_log("AI Context Update: Creating promise for stage '{$stage_name}' (task #{$task['id']})", "debug");

            $prev_promise_id = create_stage_promise(
                'core_ai_context_update',
                $stage_name,
                $payload,
                $prev_promise_id,
                null, // no specific reference_id
                $task['id'],
                $db
            );

            if (!$prev_promise_id) {
                // Return false and let the queue worker own retry/backoff and the
                // final 'failed' status (anti-storm Rule 2). A handler that sets
                // its own task 'failed' strands the task out of the worker's
                // pending retry pool.
                write_debug_log("AI Context Update: Failed to create promise for stage '{$stage_name}' (task #{$task['id']})", "error");
                // Cancel the steps already created (a partial chain would run on
                // its own and finalize the task early); the retry builds a full one.
                cancel_partial_stage_chain($db, (int)$task['id']);
                return false;
            }
        }

        queue_update_status($task['id'], 'in_progress', $db);
        write_debug_log("AI Context Update: All promises created successfully for task #{$task['id']}", "info");
        return true;
    },

    /************************************************************
     * STAGES
     * Define each stage as a closure that accepts a $promise array.
     ************************************************************/
    'stages' => [
        'generate_message_context' => function(array $promise, PDO $db) {
            write_debug_log("AI Context Update: Generating message context (promise #{$promise['id']}).", "info");

            $payload = json_decode($promise['payload'], true) ?? [];

            try {
                $context_content = generate_ai_business_context();

                if (!$context_content) {
                    throw new Exception("Context content empty");
                }

                write_debug_log("AI Context Update: Context content generated (promise #{$promise['id']}): " . substr($context_content, 0, 200), "debug");

                $payload['context_content'] = $context_content;

                return $payload;

            } catch (Exception $e) {
                write_debug_log("AI Context Update: Failed to generate context for promise #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            }
        },

        'ask_ai_for_recommendations' => function(array $promise, PDO $db) {
            write_debug_log("AI Context Update: Asking AI for recommendations (promise #{$promise['id']}).", "info");

            $payload = json_decode($promise['payload'], true) ?? [];
            $context_content = $payload['context_content'] ?? null;

            try {
                if (!$context_content) {
                    throw new Exception("No context content found for AI recommendations");
                }

                $advice = ai_get_recommendations($context_content);

                if (!$advice) {
                    throw new Exception("AI returned empty recommendations");
                }

                $payload['ai_advice'] = $advice;

                write_log(0, 0, "AI recommendations generated via queue: " . json_encode($advice), 'artificial_intelligence');
                write_debug_log("AI Context Update: AI recommendations completed successfully (promise #{$promise['id']}).", "info");

                return $payload;

            } catch (Exception $e) {
                write_debug_log("AI Context Update: Failed to get AI recommendations: " . $e->getMessage(), "error");
                throw $e;
            }
        },

        'update_ai_recommendations' => function(array $promise, PDO $db) {
            write_debug_log("AI Context Update: Updating AI recommendations setting (promise #{$promise['id']}).", "info");

            $payload = json_decode($promise['payload'], true) ?? [];
            $ai_advice = $payload['ai_advice'] ?? null;

            try {
                if ($ai_advice === null) {
                    throw new Exception("No AI advice found in payload");
                }

                $updated = update_setting("ai_display_recommendations", $ai_advice, db: $db);

                // update_setting() also returns false when the value is
                // unchanged. The model returning the same advice as last time is
                // a success (the paid call already happened) — failing here would
                // fail the chain and pay for it again. Same comparison as
                // update_setting()'s own "unchanged" check.
                if (!$updated && get_setting("ai_display_recommendations", false, false, db: $db) != $ai_advice) {
                    throw new Exception("Failed to update AI recommendations setting");
                }

                write_debug_log("AI Context Update: AI recommendations updated successfully (promise #{$promise['id']}).", "info");

                return $payload;

            } catch (Exception $e) {
                write_debug_log("AI Context Update: Failed to update recommendations: " . $e->getMessage(), "error");
                throw $e;
            }
        },
    ]
];

?>