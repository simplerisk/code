<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use SimpleRisk\DocumentHandlers\DocumentTextExtractor;

return [
    'type' => 'core_document_update',

    'on_failure' => function(PDO $db, array $promise, string $error_message, int $attempts) {
        $payload = json_decode($promise['payload'], true) ?? [];
        $document_id = $payload['document_id'] ?? null;
        if (!$document_id) return;

        $stmt = $db->prepare("UPDATE compliance_files SET keyword_processing_error = 1 WHERE ref_id = :id");
        $stmt->execute([':id' => $document_id]);

        write_debug_log("Document Update: Marked document {$document_id} as failed after {$attempts} attempts: {$error_message}", "error");
    },

    'on_success' => function(PDO $db, array $final_promise) {
        $payload = json_decode($final_promise['payload'], true) ?? [];
        $document_id = $payload['document_id'] ?? null;

        if ($document_id) write_debug_log("Document Update: Successfully processed document #{$document_id}", "info");
    },

    'task_check' => function() {
        $db = db_open();
        $stmt = $db->prepare("
            SELECT DISTINCT cf.ref_id
            FROM compliance_files cf
            LEFT JOIN queue_tasks qt
                ON qt.task_type = 'core_document_update'
                AND qt.status IN ('pending','in_progress')
                AND CAST(JSON_EXTRACT(qt.payload, '$.document_id') AS UNSIGNED) = cf.ref_id
            WHERE cf.keywords IS NULL
              AND cf.keyword_count = 0
              AND cf.keyword_processing_error = 0
              AND qt.id IS NULL
        ");
        $stmt->execute();
        $document_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        db_close($db);

        foreach ($document_ids as $document_id) {
            $queue_task_payload = [
                'triggered_at' => time(),
                'document_id' => (int)$document_id,
                'refresh' => true,
            ];
            queue_task('core_document_update', $queue_task_payload, 100);
        }

        return true;
    },

    'queue_check' => function(array $task) {
        $payload = json_decode($task['payload'], true) ?? [];
        $document_id = $payload['document_id'] ?? null;
        if (!$document_id) return false;

        $stages = [
            'fetch_document',
            'convert_document_to_text',
            'calculate_keywords',
            'calculate_tfidf',
            'check_and_launch_ai',
            'clean_tmp',
        ];

        $prev_promise_id = null;
        foreach ($stages as $stage_name) {
            $prev_promise_id = create_stage_promise(
                'core_document_update',
                $stage_name,
                $payload,
                $prev_promise_id,
                $document_id,
                $task['id']
            );
        }

        queue_update_status($task['id'], 'in_progress');
        return true;
    },

    'stages' => [

        'fetch_document' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $document_id = $payload['document_id'] ?? null;
            if (!$document_id) throw new Exception("Missing document_id");

            try
            {
                $db = db_open();
                $stmt = $db->prepare("SELECT name, content, type FROM compliance_files WHERE ref_id = :id");
                $stmt->execute([':id' => $document_id]);
                $document = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$document) throw new Exception("Document not found in compliance_files");

                $extension = pathinfo($document['name'], PATHINFO_EXTENSION);
                $unique_name = save_tmp_file($db, $document['name'], $document['content'], $document['type'], $extension, 0);

                $payload['unique_name'] = $unique_name;
                write_debug_log("Document Update: Fetched and saved document {$document_id} as tmp file {$unique_name}", "debug");
            }  catch (Exception $e) {
                write_debug_log("Control Update: Failed to fetch document for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            } finally {
                // Close the database connection
                db_close($db);
            }

            return $payload;
        },

        'convert_document_to_text' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $unique_name = $payload['unique_name'] ?? null;
            if (!$unique_name) throw new Exception("Missing unique_name");

            try
            {
                $db = db_open();
                $file = load_tmp_file($db, $unique_name);
                $content = $file['content'];
                $mimeType = $file['type'];
                $fileName = $file['name'];

                if (!$file) throw new Exception("Temporary file not found: {$unique_name}");

                $text = DocumentTextExtractor::extractText($content, $mimeType, $fileName);
                unset($file); // free memory
                $payload['extracted_text_ref'] = save_tmp_data($db, "text_{$document_id}", $text);
                write_debug_log("Document Update: Extracted text for {$unique_name}", "debug");
            }  catch (Exception $e) {
                write_debug_log("Control Update: Failed to convert document to text for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            } finally {
                // Close the database connection
                db_close($db);
            }

            return $payload;
        },

        'calculate_keywords' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $document_id = $payload['document_id'] ?? null;
            $extracted_text_ref = $payload['extracted_text_ref'] ?? '';

            try
            {
                // Open the database connection
                $db = db_open();

                $text = load_tmp_data($db, $extracted_text_ref);
                $keywordsWithCounts = $text ? extractSignificantTerms($text) : [];

                // Update compliance_files directly
                $stmt = $db->prepare("
                    UPDATE compliance_files
                    SET keywords = :keywords,
                        keyword_count = :count
                    WHERE ref_id = :id
                ");
                $stmt->execute([
                    ':keywords' => json_encode($keywordsWithCounts),
                    ':count' => count($keywordsWithCounts),
                    ':id' => $document_id
                ]);

                write_debug_log("Keywords with counts: " . var_dump($keywordsWithCounts), "debug");
                write_debug_log("Document Update: Calculated keywords for document {$document_id}", "debug");
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
            $document_id = $payload['document_id'] ?? null;
            if (!$document_id) throw new Exception("Missing document_id");

            // Calculate the TF-IDF score for the new/updated document
            compute_document_control_scores([$document_id]);

            write_debug_log("Document Update: Completed TF-IDF calculations for document {$document_id}", "info");

            return $payload;
        },

        'check_and_launch_ai' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $document_id = $payload['document_id'] ?? null;

            // If the Artificial Intelligence Extra is active
            if (artificial_intelligence_extra())
            {
                write_debug_log("Artificial Intelligence Extra is enabled.", "debug");

                // Run the AI Document to Control the chunking process
                $queue_task_payload = [
                    'triggered_at' => time(),
                    'document_id' => $document_id,
                    'update_document' => false,
                ];
                queue_task('ai_document_to_control_chunker', $queue_task_payload, 25);
            }
            else write_debug_log("Artificial Intelligence Extra is disabled.", "debug");

            return $payload;
        },

        'clean_tmp' => function(array $promise) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $unique_name = $payload['unique_name'] ?? null;
            $extracted_text_ref = $payload['extracted_text_ref'] ?? null;

            try {
                $db = db_open();
                $unique_name ? delete_tmp_file($db, $unique_name) : false;
                $extracted_text_ref ? delete_tmp_data($db, $extracted_text_ref) : false;
                unset($payload['unique_name']);
                unset($payload['extracted_text_ref']);
            } catch (Exception $e) {
                write_debug_log("Document Update: Failed to clean tmp for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            } finally {
                // Close the database connection
                db_close($db);
            }

            return $payload;
        },
    ],
];

?>