<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use SimpleRisk\DocumentHandlers\DocumentTextExtractor;
use SimpleRisk\DocumentHandlers\UnsupportedDocumentException;
use SimpleRisk\DocumentHandlers\DocumentTooLargeException;

// promise_chain_exists() lives in promises.php, which functions.php does not
// auto-load. The worker requires it at runtime, but declare it directly here too
// so this consumer's defining file is always reachable (CLAUDE.md).
require_once(realpath(__DIR__ . '/../promises.php'));

// Shared tmp cleanup for both on_failure and the clean_tmp stage. Declared as a
// closure (not a top-level function) because load_all_jobs() include()s this
// file more than once per process — a named function would fatally redeclare.
// Handles the multi-file payload shape ('files' => [...]) and the legacy
// single-file keys, so tasks in flight across a deploy still clean up.
$document_update_clean_tmp = function(PDO $db, array $payload): void {
    $delete = function(?string $tmp_file, ?string $tmp_data) use ($db): void {
        if ($tmp_file) {
            try { delete_tmp_file($db, $tmp_file); } catch (\Throwable $e) {
                write_debug_log("Document Update: Failed to delete tmp file {$tmp_file}: " . $e->getMessage(), "warning");
            }
        }
        if ($tmp_data) {
            try { delete_tmp_data($db, $tmp_data); } catch (\Throwable $e) {
                write_debug_log("Document Update: Failed to delete tmp data {$tmp_data}: " . $e->getMessage(), "warning");
            }
        }
    };

    foreach (($payload['files'] ?? []) as $file) {
        $delete($file['unique_name'] ?? null, $file['text_ref'] ?? null);
    }

    // Legacy single-file keys (pre-multi-file payloads still in the queue).
    $delete($payload['unique_name'] ?? null, $payload['extracted_text_ref'] ?? null);
};

return [
    'type' => 'core_document_update',

    'on_failure' => function(PDO $db, array $promise, string $error_message, int $attempts) use ($document_update_clean_tmp) {
        $payload = json_decode($promise['payload'], true) ?? [];
        $document_id = $payload['document_id'] ?? null;
        if (!$document_id) return;

        // Flag only the not-yet-processed files of this bundle, so a permanent
        // chain failure stops the retry loop without clobbering siblings that
        // already extracted successfully (those have keywords set).
        $stmt = $db->prepare("UPDATE compliance_files SET keyword_processing_error = 1 WHERE ref_id = :id AND keywords IS NULL");
        $stmt->execute([':id' => $document_id]);

        // Clean up any tmp files/data written before the failure.
        $document_update_clean_tmp($db, $payload);

        write_debug_log("Document Update: Marked document {$document_id} as failed after {$attempts} attempts: {$error_message}", "error");
    },

    'on_success' => function(PDO $db, array $final_promise) {
        $payload = json_decode($final_promise['payload'], true) ?? [];
        $document_id = $payload['document_id'] ?? null;

        if ($document_id) write_debug_log("Document Update: Successfully processed document #{$document_id}", "info");
    },

    'task_check' => function(PDO $db) {
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

        foreach ($document_ids as $document_id) {
            $queue_task_payload = [
                'triggered_at' => time(),
                'document_id' => (int)$document_id,
                'refresh' => true,
            ];
            queue_task($db, 'core_document_update', $queue_task_payload, 100, 5, 3600);
        }

        return !empty($document_ids);
    },

    'queue_check' => function(array $task, PDO $db) {
        $payload = json_decode($task['payload'], true) ?? [];
        $document_id = $payload['document_id'] ?? null;
        if (!$document_id) return false;

        // Idempotency guard: if a live promise chain already exists for this
        // task, do not create another. Without this, the queue worker's
        // stuck-task recovery re-invokes queue_check on every cycle and each
        // call re-chains every stage (the re-chaining runaway). See
        // promise_chain_exists() in promises.php.
        if (promise_chain_exists($db, (int)$task['id'], 'core_document_update')) {
            write_debug_log("Document Update: Live promise chain already exists for task #{$task['id']}; skipping duplicate creation.", "info");
            return false;
        }

        $stages = [
            'fetch_document',
            'convert_document_to_text',
            'calculate_keywords',
            'calculate_tfidf',
        ];

        // If the Artificial Intelligence Extra is active
        if (artificial_intelligence_extra())
        {
            // Add it to the stage
            $stages[] = 'check_and_launch_ai';
        }

        // Add the final clean_tmp stage
        $stages[] = 'clean_tmp';

        $prev_promise_id = null;
        foreach ($stages as $stage_name) {
            $prev_promise_id = create_stage_promise(
                'core_document_update',
                $stage_name,
                $payload,
                $prev_promise_id,
                $document_id,
                $task['id'],
                $db
            );
        }

        queue_update_status($task['id'], 'in_progress', $db);
        return true;
    },

    'stages' => [

        // Fetch EVERY file attached to this ref_id. A document (audit bundle)
        // can hold several files of mixed types under one ref_id; processing
        // only the first row left supported siblings unextracted (Defect 3).
        // Two-pass — list the rows first, then stream each file's content into
        // tmp storage — so we never hold every blob in memory at once.
        'fetch_document' => function(array $promise, PDO $db) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $document_id = $payload['document_id'] ?? null;
            if (!$document_id) throw new Exception("Missing document_id");

            try {
                $stmt = $db->prepare("SELECT id, name, type FROM compliance_files WHERE ref_id = :id ORDER BY id");
                $stmt->execute([':id' => $document_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($rows)) throw new Exception("Document not found in compliance_files");

                $files = [];
                foreach ($rows as $row) {
                    $file_id = (int)$row['id'];

                    $contentStmt = $db->prepare("SELECT content FROM compliance_files WHERE id = :id");
                    $contentStmt->execute([':id' => $file_id]);
                    $content = $contentStmt->fetchColumn();
                    if ($content === false) {
                        // Row removed between the two queries — skip it.
                        continue;
                    }

                    $extension = pathinfo($row['name'], PATHINFO_EXTENSION);
                    $unique_name = save_tmp_file($db, $row['name'], $content, $row['type'], $extension, 0);
                    unset($content); // free the blob before the next iteration

                    $files[] = [
                        'file_id'     => $file_id,
                        'name'        => $row['name'],
                        'unique_name' => $unique_name,
                    ];
                }

                if (empty($files)) throw new Exception("No readable files found for document {$document_id}");

                $payload['files'] = $files;
                write_debug_log("Document Update: Fetched " . count($files) . " file(s) for document {$document_id}", "debug");
            } catch (Exception $e) {
                write_debug_log("Document Update: Failed to fetch document for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            }

            return $payload;
        },

        // Extract text from each supported file independently. A file that is
        // unsupported or too large to parse safely is flagged per-file (by its
        // own id) and skipped so its supported siblings still process — the
        // error flag is NOT applied bundle-wide (Defect 3 / Defect 2).
        'convert_document_to_text' => function(array $promise, PDO $db) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $document_id = $payload['document_id'] ?? null;
            $files = $payload['files'] ?? [];
            if (empty($files)) throw new Exception("Missing files list");

            // tmp data refs saved during THIS stage execution. On a transient
            // rethrow the stage's payload mutations are discarded (only
            // persisted on success) and the worker re-runs the stage from
            // scratch, so without this these rows would be orphaned in tmp_files.
            $saved_refs = [];

            foreach ($files as $idx => $file) {
                $unique_name = $file['unique_name'] ?? null;
                $file_id = $file['file_id'] ?? null;
                if (!$unique_name || !$file_id) continue;

                try {
                    $tmp = load_tmp_file($db, $unique_name);
                    if (!$tmp) throw new Exception("Temporary file not found: {$unique_name}");

                    $content  = $tmp['content'];
                    $mimeType = $tmp['type'];
                    $fileName = $tmp['name'];

                    try {
                        $text = DocumentTextExtractor::extractText($content, $mimeType, $fileName);
                        unset($tmp, $content); // free memory
                        $ref = save_tmp_data($db, "text_{$document_id}_{$file_id}", $text);
                        $payload['files'][$idx]['text_ref'] = $ref;
                        $saved_refs[] = $ref;
                        write_debug_log("Document Update: Extracted text for file {$file_id} ({$unique_name})", "debug");
                    } catch (UnsupportedDocumentException | DocumentTooLargeException $e) {
                        // Permanent, per-file skip: flag ONLY this file and keep
                        // going so supported siblings still process.
                        write_debug_log("Document Update: Skipping file {$file_id} of document {$document_id}: " . $e->getMessage(), "warning");

                        $stmt = $db->prepare("UPDATE compliance_files SET keyword_processing_error = 1 WHERE id = :id");
                        $stmt->execute([':id' => $file_id]);

                        $payload['files'][$idx]['skipped'] = true;
                    }
                } catch (Exception $e) {
                    // Unexpected (possibly transient) error — discard tmp data
                    // saved this run so the retry doesn't orphan it, then fail
                    // the stage so the worker retries the whole chain.
                    foreach ($saved_refs as $r) {
                        try { delete_tmp_data($db, $r); } catch (\Throwable $t) {
                            write_debug_log("Document Update: Failed to delete tmp data {$r} during transient-error cleanup: " . $t->getMessage(), "warning");
                        }
                    }
                    write_debug_log("Document Update: Failed to convert file {$file_id} for #{$promise['id']}: " . $e->getMessage(), "error");
                    throw $e;
                }
            }

            return $payload;
        },

        // Compute and store keywords per file (keyed by the file's own id, not
        // bundle-wide). Skipped/unsupported files (no text_ref) are left as-is —
        // they already carry their per-file keyword_processing_error flag.
        'calculate_keywords' => function(array $promise, PDO $db) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $document_id = $payload['document_id'] ?? null;
            $files = $payload['files'] ?? [];

            try {
                foreach ($files as $file) {
                    $file_id  = $file['file_id'] ?? null;
                    $text_ref = $file['text_ref'] ?? null;
                    if (!$file_id || !$text_ref) continue; // skipped/unsupported file

                    $text = load_tmp_data($db, $text_ref);
                    $keywordsWithCounts = $text ? extractSignificantTerms($text) : [];

                    $stmt = $db->prepare("
                        UPDATE compliance_files
                        SET keywords = :keywords,
                            keyword_count = :count
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':keywords' => json_encode($keywordsWithCounts),
                        ':count' => count($keywordsWithCounts),
                        ':id' => $file_id,
                    ]);

                    write_debug_log("Document Update: Calculated keywords for file {$file_id} (document {$document_id})", "debug");
                }
            } catch (Exception $e) {
                write_debug_log("Document Update: Failed to calculate keywords for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            }

            return $payload;
        },

        'calculate_tfidf' => function(array $promise, PDO $db) {
            $payload = json_decode($promise['payload'], true) ?? [];
            $document_id = $payload['document_id'] ?? null;
            if (!$document_id) throw new Exception("Missing document_id");

            // Calculate the TF-IDF score for the new/updated document. The
            // scorer reads every compliance_files row for the ref_id, so it
            // naturally picks up each file's per-row keywords.
            compute_document_control_scores([$document_id]);

            write_debug_log("Document Update: Completed TF-IDF calculations for document {$document_id}", "info");

            return $payload;
        },

        'check_and_launch_ai' => function(array $promise, PDO $db) {
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
                queue_task($db, 'ai_document_to_control_chunker', $queue_task_payload, 25, 5, 3600);
            }
            else write_debug_log("Artificial Intelligence Extra is disabled.", "debug");

            return $payload;
        },

        'clean_tmp' => function(array $promise, PDO $db) use ($document_update_clean_tmp) {
            $payload = json_decode($promise['payload'], true) ?? [];

            try {
                $document_update_clean_tmp($db, $payload);
                unset($payload['files']);
                unset($payload['unique_name']);
                unset($payload['extracted_text_ref']);
            } catch (Exception $e) {
                write_debug_log("Document Update: Failed to clean tmp for #{$promise['id']}: " . $e->getMessage(), "error");
                throw $e;
            }

            return $payload;
        },
    ],
];

?>
