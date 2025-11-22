<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

return [
    'type' => 'core_control_update',

    'on_failure' => function(PDO $db, array $promise, string $error_message, int $attempts) {
        $payload = json_decode($promise['payload'], true) ?? [];
        $control_id = $payload['control_id'] ?? null;
        if (!$control_id) return;

        write_debug_log("Control Update: Marked control {$control_id} as failed after {$attempts} attempts: {$error_message}", "error");
    },

    'on_success' => function(PDO $db, array $final_promise) {
        $payload = json_decode($final_promise['payload'], true) ?? [];
        $control_id = $payload['control_id'] ?? null;

        if ($control_id) write_debug_log("Control Update: Successfully processed control #{$control_id}", "info");
    },

    'task_check' => function() {
        $db = db_open();
        $stmt = $db->prepare("
            SELECT DISTINCT fc.id
            FROM framework_controls fc
            LEFT JOIN queue_tasks qt
                ON qt.task_type = 'core_control_update'
                AND qt.status IN ('pending','in_progress')
                AND CAST(JSON_EXTRACT(qt.payload, '$.control_id') AS UNSIGNED) = fc.id
            WHERE fc.keywords IS NULL
              AND fc.keyword_count = 0
              AND fc.keyword_processing_error = 0
              AND fc.id IS NULL
        ");
        $stmt->execute();
        $control_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        db_close($db);

        foreach ($control_ids as $control_id) {
            $queue_task_payload = [
                'triggered_at' => time(),
                'control_id' => (int)$control_id,
                'refresh' => true,
            ];
            queue_task('core_control_update', $queue_task_payload, 100);
        }

        return true;
    },

    'queue_check' => function(array $task) {
        $payload = json_decode($task['payload'], true) ?? [];
        $control_id = $payload['control_id'] ?? null;
        if (!$control_id) return false;

        $stages = [
            'fetch_control',
            'calculate_keywords',
            'calculate_tfidf',
            'check_and_launch_ai',
            'clean_tmp',
        ];

        $prev_promise_id = null;
        foreach ($stages as $stage_name) {
            $prev_promise_id = create_stage_promise(
                'core_control_update',
                $stage_name,
                $payload,
                $prev_promise_id,
                $control_id,
                $task['id']
            );
        }

        queue_update_status($task['id'], 'in_progress');
        return true;
    },

    'stages' => [
        'fetch_control' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $control_id = $payload['control_id'] ?? null;
            if (!$control_id) throw new Exception("Missing control_id");

            try
            {
                $db = db_open();
                $stmt = $db->prepare("SELECT * FROM framework_controls WHERE id = :id");
                $stmt->execute([':id' => $control_id]);
                $control = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$control) throw new Exception("Control not found in framework_controls");

                $control_ref = save_tmp_data($db, "control_{$control_id}", $control);

                $payload['control_ref'] = $control_ref;
                write_debug_log("Control Update: Fetched and saved control {$control_id} as tmp data {$control_ref}", "debug");
            }  catch (Exception $e) {
                write_debug_log("Control Update: Failed to fetch control for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            } finally {
                // Close the database connection
                db_close($db);
            }

            return $payload;
        },

        'calculate_keywords' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $control_id = $payload['control_id'] ?? null;
            $control_ref = $payload['control_ref'] ?? null;

            if (!$control_ref || !$control_id) throw new Exception("Missing control_id or control_ref");

            try
            {
                $db = db_open();

                // Load the saved control data
                $control = load_tmp_data($db, $control_ref);

                write_debug_log("Analyzing the contents of Control ID: " . $control_id);

                // Get the control text and calculate the control term frequency
                $control_text = "{$control['short_name']}: {$control['description']}";
                write_debug_log("Calculating significant terms from the control.  This may take a while.", "debug");
                $keywords = extractSignificantTerms($control_text);
                $keywords_json = json_encode($keywords);
                write_debug_log("Significant Terms: {$keywords_json}", "debug");
                $keyword_count = array_sum($keywords);
                write_debug_log("Keyword count for Control ID {$control_id}: {$keyword_count}", "debug");

                // Update the control with the keywords and keyword count
                $stmt = $db->prepare("UPDATE framework_controls SET keywords = :keywords, keyword_count = :keyword_count WHERE id = :control_id");
                $stmt->bindParam(":keywords", $keywords_json, PDO::PARAM_STR);
                $stmt->bindParam(":keyword_count", $keyword_count, PDO::PARAM_INT);
                $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
                $stmt->execute();

                write_debug_log("Control Update: Calculated keywords for control {$control_id}", "debug");
            }  catch (Exception $e) {
                write_debug_log("Control Update: Failed to calculate keywords for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            } finally {
                // Close the database connection
                db_close($db);
            }

            return $payload;
        },

        'calculate_tfidf' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $control_id = $payload['control_id'] ?? null;
            if (!$control_id) throw new Exception("Missing control_id");

            // Calculate the TF-IDF score for the new/updated control
            $matches = compute_document_control_scores([], [$control_id]);

            // Store the matches
            $db = db_open();
            $payload['matches_ref'] = save_tmp_data($db, "matches_{$control_id}", $matches);
            db_close($db);

            write_debug_log("Document Update: Completed TF-IDF calculations for document {$control_id}", "info");

            return $payload;
        },

        'check_and_launch_ai' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $matches_ref = $payload['matches_ref'] ?? null;
            $control_id = $payload['control_id'] ?? null;

            // If the Artificial Intelligence Extra is active
            if (artificial_intelligence_extra())
            {
                write_debug_log("Artificial Intelligence Extra is enabled.", "debug");

                // Open the database connection
                $db = db_open();

                // Load the matches
                $matches = load_tmp_data($db, $matches_ref);

                // Filter the matches for only the specified control_id value
                $filteredMatches = array_values(array_filter($matches, fn($row) => $row['control_id'] == $control_id));

                // For each of the documents that are possible matches
                foreach ($filteredMatches as $match)
                {
                    // Get the document_id, control_id and score
                    $document_id = $match['document_id'] ?? null;
                    $control_id = $match['control_id'] ?? null;

                    // If no document_id, control_id or score was provided
                    if (!$control_id || !$document_id) throw new Exception("Missing document_id or control_id");

                    write_debug_log("Queueing ai_control_to_document_process task for document #{$document_id} and control #{$control_id}.", "debug");

                    // Run the AI Control to Document matching process
                    $queue_task_payload = [
                        'triggered_at' => time(),
                        'control_id' => (int)$control_id,
                        'document_id' => (int)$document_id,
                        'update_control' => false,
                    ];
                    queue_task('ai_control_to_document_process', $queue_task_payload, 25);
                }

                // Close the database connection
                db_close($db);
            }
            else write_debug_log("Artificial Intelligence Extra is disabled.", "debug");

            return $payload;
        },

        'clean_tmp' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $control_ref = $payload['control_ref'] ?? null;
            $matches_ref = $payload['matches_ref'] ?? null;

            try {
                $db = db_open();
                $control_ref ? delete_tmp_data($db, $control_ref) : false;
                $matches_ref ? delete_tmp_data($db, $matches_ref) : false;
                unset($payload['control_ref']);
                unset($payload['matches_ref']);
            } catch (Exception $e) {
                write_debug_log("Control Update: Failed to clean tmp for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            } finally {
                // Close the database connection
                db_close($db);
            }

            return $payload;
        },
    ],

    // No task_check needed; queueing is done manually in code
];

?>