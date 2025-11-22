<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));

/*************************************************************
 * FUNCTION: QUEUE TASK                                      *
 * Inserts a new task into the queue_tasks table             *
 * PRIORITY                                                  *
 * 100 = Do Immediately (Ex: send_email)                     *
 * 75 = Important, but can wait (Ex: ai_document_install)    *
 * 50 = Wait until spare cycles (Ex: core_ai_context_update) *
 * 25 = Not a high priority (Ex: core_countries_update)      *
 * 0 = Lowest possible priority (No Examples)                *
 *************************************************************/
function queue_task(
    string $task_type,
    array $payload = [],
    int $priority = 0,
    int $baseDelay = 5,
    int $maxDelay = 3600
): bool {
    try {
        $db = db_open();

        // Initialize retry metadata in payload
        $payload['retry_attempts'] = $payload['retry_attempts'] ?? 0;
        $payload['next_retry_at'] = $payload['next_retry_at'] ?? date('Y-m-d H:i:s');
        $payload['last_error'] = $payload['last_error'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO queue_tasks (task_type, payload, status, priority, created_at, updated_at)
            VALUES (:task_type, :payload, 'pending', :priority, NOW(), NOW())
        ");

        $stmt->execute([
            ':task_type' => $task_type,
            ':payload'   => json_encode($payload),
            ':priority'  => $priority,
        ]);

        write_debug_log("Queued task '{$task_type}' with priority {$priority} successfully.", 'info');

        db_close($db);
        return true;

    } catch (Exception $e) {
        write_debug_log("Failed to queue task '{$task_type}': " . $e->getMessage(), 'error');
        if (isset($db)) db_close($db);
        return false;
    }
}

/***************************
 * FUNCTION: LOAD ALL JOBS *
 ***************************/
function load_all_jobs(): array
{
    $jobs = [];

    // --- Load Core Jobs ---
    $coreDir = realpath(__DIR__ . '/jobs');
    if (is_dir($coreDir)) {
        foreach (glob($coreDir . '/*.php') as $file) {
            $job_def = include($file);
            if (is_array($job_def)) {
                $jobs[] = $job_def;
                write_debug_log("Loaded core job module: " . basename($file), "debug");
            } else {
                write_debug_log("Skipped invalid core job module: " . basename($file), "warning");
            }
        }
    }

    // --- Load AI Extra Jobs (if enabled) ---
    if (function_exists('artificial_intelligence_extra') && artificial_intelligence_extra()) {
        require_once(realpath(__DIR__ . '/../extras/artificial_intelligence/index.php'));
        $aiDir = realpath(__DIR__ . '/../extras/artificial_intelligence/jobs');
        if (is_dir($aiDir)) {
            foreach (glob($aiDir . '/*.php') as $file) {
                $job_def = include($file);
                if (is_array($job_def)) {
                    $jobs[] = $job_def;
                    write_debug_log("Loaded AI extra job module: " . basename($file), "debug");
                } else {
                    write_debug_log("Skipped invalid AI extra job module: " . basename($file), "warning");
                }
            }
        }
    }

    write_debug_log("Loaded " . count($jobs) . " total job definitions.", "debug");

    return $jobs;
}

/******************************************************************************************************************
 * FUNCTION: GET QUEUE ITEMS                                                                                      *
 * Get a list of items currently in the queue, optionally filtered by task type and/or status(es).                *
 ******************************************************************************************************************/
function get_queue_items(string|array|null $task_type = null, string|array|null $status = null): array
{
    $db = db_open();
    if (!$db) {
        write_debug_log("GET_QUEUE_ITEMS: Failed to open DB connection.", "error");
        return [];
    }

    try {
        $query = "SELECT * FROM queue_tasks WHERE 1=1";
        $params = [];

        if (!empty($task_type)) {
            if (is_array($task_type)) {
                $likeClauses = [];
                foreach ($task_type as $type) {
                    $likeClauses[] = "task_type LIKE ?";
                    $params[] = '%' . $type . '%';
                }
                $query .= " AND (" . implode(" OR ", $likeClauses) . ")";
            } else {
                $query .= " AND task_type LIKE ?";
                $params[] = '%' . $task_type . '%';
            }
        }

        if (!empty($status)) {
            if (is_array($status)) {
                $placeholders = implode(',', array_fill(0, count($status), '?'));
                $query .= " AND status IN ($placeholders)";
                $params = array_merge($params, $status);
            } else {
                $query .= " AND status = ?";
                $params[] = $status;
            }
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results ?: [];

    } catch (Exception $e) {
        write_debug_log("GET_QUEUE_ITEMS: Error retrieving queue items: " . $e->getMessage(), "error");
        return [];
    } finally {
        db_close($db);
    }
}

/**
 * FUNCTION: QUEUE UPDATE STATUS
 * Update the status of a queue task and log the result.
 */
function queue_update_status($task_id, $status): bool {
    write_debug_log("queue_update_status called for task #{$task_id} with status '{$status}'", "debug");

    try {
        $db = db_open();
        $stmt = $db->prepare("UPDATE queue_tasks SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $task_id]);

        $affected = $stmt->rowCount();

        if ($affected > 0) {
            write_debug_log("queue_update_status successfully updated task #{$task_id} (rows affected: {$affected})", "debug");
            return true;
        } else {
            $check = $db->prepare("SELECT status FROM queue_tasks WHERE id = :id");
            $check->execute([':id' => $task_id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                write_debug_log("queue_update_status: task #{$task_id} already had status '{$status}'", "debug");
                return true;
            } else {
                write_debug_log("queue_update_status: task #{$task_id} does not exist", "error");
                return false;
            }
        }
    } catch (Exception $e) {
        write_debug_log("queue_update_status failed for task #{$task_id}: " . $e->getMessage(), "error");
        return false;
    } finally {
        if (isset($db)) db_close($db);
    }
}

/**************************************************************************
 * FUNCTION: HANDLE QUEUE TASK FAILURE                                    *
 * Handle a failed queue task with exponential backoff and error storage. *
 **************************************************************************/
function handle_queue_task_failure(PDO $db, array $task, string $errorMessage, int $maxRetryAttempts = 5, int $baseRetryDelay = 5, int $maxRetryDelay = 3600): void
{
    $payload = json_decode($task['payload'] ?? '{}', true);
    $payload['last_error'] = $errorMessage;

    $retryAttempts = (int)($payload['retry_attempts'] ?? 0) + 1;
    $nextRetryDelay = min($baseRetryDelay * (2 ** ($retryAttempts - 1)), $maxRetryDelay);
    $payload['retry_attempts'] = $retryAttempts;
    $payload['next_retry_at'] = date('Y-m-d H:i:s', time() + $nextRetryDelay);

    if ($retryAttempts <= $maxRetryAttempts) {
        write_debug_log("Task #{$task['id']} failed, scheduling retry #{$retryAttempts} at {$payload['next_retry_at']}", "warning");
        $stmt = $db->prepare("
            UPDATE queue_tasks
            SET payload = :payload
            WHERE id = :id
        ");
        $stmt->execute([
            ':payload' => json_encode($payload),
            ':id' => $task['id'],
        ]);
    } else {
        write_debug_log("Task #{$task['id']} failed after {$retryAttempts} attempts, marking as failed.", "error");
        queue_update_status($task['id'], 'failed');
    }
}

?>