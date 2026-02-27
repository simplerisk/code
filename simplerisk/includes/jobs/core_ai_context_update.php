<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

return [
    'type' => 'core_ai_context_update',

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
        // If an Anthropic API key is not set
        if (get_setting("anthropic_api_key", false, false, db: $db) === false) {
            write_debug_log("Anthropic API Key not set.", "notice");
            return false;
        }

        write_debug_log("AI Context Update: Checking for pending updates.", "info");

        $last_saved = get_setting("ai_context_last_saved", false, false, db: $db);
        $last_updated = get_setting("ai_context_last_updated", false, false, db: $db);

        write_debug_log("AI Context Update: Last saved at " . date("Y-m-d H:i:s", $last_saved), "debug");
        write_debug_log("AI Context Update: Last updated at " . date("Y-m-d H:i:s", $last_updated), "debug");

        if (!$last_updated || $last_updated < $last_saved) {
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
            if ($running_count === 0  && $settings_count > 0) {
                $queue_task_payload = [
                    'triggered_at'      => time(),
                ];
                queue_task($db, 'core_ai_context_update', $queue_task_payload, 50, 5, 3600);
                write_debug_log("AI Context Update: Queued new task.", "notice");
                return true;
            } else {
                write_debug_log("AI Context Update: Task already running ({$running_count}). Skipping queueing.", "info");
                return false;
            }
        } else {
            write_debug_log("AI Context Update: No update required.", "info");
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

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM promises 
                WHERE queue_task_id = :task_id 
                    AND promise_type = 'core_ai_context_update'
                    AND status IN ('pending','in_progress')
            ");
            $stmt->execute([':task_id' => $task['id']]);
            $existing = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            write_debug_log("AI Context Update: Failed to check for existing promises (task #{$task['id']}): " . $e->getMessage(), "error");
            return false;
        }

        if ($existing > 0) {
            write_debug_log("AI Context Update: Found {$existing} existing pending/in-progress promise(s) for task #{$task['id']}. Skipping duplicate creation.", "info");
            return false;
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
                write_debug_log("AI Context Update: Failed to create promise for stage '{$stage_name}' (task #{$task['id']})", "error");
                queue_update_status($task['id'], 'failed', $db);
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
                $context_content = generate_anthropic_message_context();

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

                $advice = ask_anthropic_for_recommendations($context_content);

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

                $updated = update_setting("ai_display_recommendations", $ai_advice);

                if (!$updated) {
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