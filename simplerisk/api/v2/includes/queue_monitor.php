<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Queue Monitor v2 endpoints (SR-2408): thin admin-only wrappers over
 * includes/queue_monitor.php (read side) and includes/queue_actions.php
 * (cancel / restart / run-now / delete).
 *
 * OUTPUT ENCODING: the strings returned by the read side (last_error, step
 * errors, task_type, payload text) are raw. This is a JSON API, so json_encode
 * is the sink and nothing is HTML-escaped here; the page that renders them
 * must escape exactly once.
 *
 * CSRF: the mutating routes are POST, so csrf-magic validates them like
 * every other POST in the app.
 */

require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/queue_monitor.php'));
require_once(realpath(__DIR__ . '/../../../includes/queue_actions.php'));

/**
 * A request value as a scalar string, or '' when it is missing or not a scalar
 * (a caller sending q[]=x must not trigger an array-to-string conversion).
 *
 * @param mixed $value
 */
function api_v2_queue_monitor_text($value): string
{
    return is_scalar($value) ? (string)$value : '';
}

/**
 * A request value as a bounded int, or $default when it is not an integer.
 *
 * @param mixed $value
 */
function api_v2_queue_monitor_int($value, int $default, int $min, int $max): int
{
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false) {
        return $default;
    }
    return max($min, min($max, $int));
}

/**
 * Whole-number 0..QUEUE_RETENTION_MAX_DAYS, or null. Accepts a JSON/PHP int or a
 * plain digit string; rejects floats, signs, whitespace, bools and arrays.
 *
 * @param mixed $value
 */
function api_v2_queue_monitor_parse_days($value): ?int
{
    if (is_string($value) && preg_match('/^\d{1,4}\z/', $value) === 1) {
        $value = (int)$value;
    }
    if (!is_int($value) || $value < 0 || $value > QUEUE_RETENTION_MAX_DAYS) {
        return null;
    }
    return $value;
}

/**
 * Stored retention windows, falling back to the defaults for an absent or
 * unusable value.
 *
 * @return array{completed_days:int, failed_days:int}
 */
function api_v2_queue_monitor_retention(PDO $db): array
{
    $completed = api_v2_queue_monitor_parse_days(get_setting(QUEUE_RETENTION_SETTING_COMPLETED, false, false, $db));
    $failed = api_v2_queue_monitor_parse_days(get_setting(QUEUE_RETENTION_SETTING_FAILED, false, false, $db));

    return [
        'completed_days' => $completed ?? QUEUE_RETENTION_DEFAULT_COMPLETED_DAYS,
        'failed_days'    => $failed ?? QUEUE_RETENTION_DEFAULT_FAILED_DAYS,
    ];
}

// GET /admin/queue?q=&status=&task_type=&date_from=&date_to=&start=&length=&order_col=&order_dir=
function api_v2_admin_queue()
{
    api_v2_check_admin();

    $filters = queue_monitor_filters([
        'q'         => api_v2_queue_monitor_text(get_param("GET", "q", "")),
        'status'    => get_param("GET", "status", ""),
        'task_type' => api_v2_queue_monitor_text(get_param("GET", "task_type", "")),
        'date_from' => api_v2_queue_monitor_text(get_param("GET", "date_from", "")),
        'date_to'   => api_v2_queue_monitor_text(get_param("GET", "date_to", "")),
    ]);
    $start = api_v2_queue_monitor_int(get_param("GET", "start", 0), 0, 0, PHP_INT_MAX);
    $length = api_v2_queue_monitor_int(get_param("GET", "length", 25), 25, 1, 500);
    $order_col = api_v2_queue_monitor_text(get_param("GET", "order_col", "id"));
    $order_dir = api_v2_queue_monitor_text(get_param("GET", "order_dir", "desc"));

    $db = db_open();
    try {
        $page = queue_monitor_list($db, $filters, $start, $length, $order_col, $order_dir);

        // Status pills count within every filter except status, so a pill does
        // not zero out the others while it is selected.
        $status_counts = queue_monitor_status_counts($db, array_merge($filters, ['statuses' => []]));

        // One query for the whole page's step summaries.
        $summaries = queue_monitor_step_summaries($db, array_column($page['rows'], 'id'));

        // In a failed/canceled-only view, how many tasks "Restart selected" with
        // all_filtered would actually target: the same filter minus the tasks
        // already restarted (exactly what the restart resolver excludes).
        $actionable_total = null;
        if ($filters['statuses'] && !array_diff($filters['statuses'], ['failed', 'canceled'])) {
            // Bounded by the same max_id snapshot the list returns, so it is the exact
            // set a select-all restart reaches.
            $actionable_total = queue_monitor_count($db, array_merge($filters, [
                'exclude_restarted' => true,
                'max_id'            => $page['max_id'],
            ]));
        }

        // In a view whose statuses are all deletable (completed / failed / canceled),
        // how many tasks "Delete selected" with all_filtered would target: the
        // delete resolver's own filter (deletable_only drops the protected and
        // kept-history types) bounded by the same max_id snapshot.
        $deletable_total = null;
        if ($filters['statuses'] && !array_diff($filters['statuses'], QUEUE_DELETABLE_STATUSES)) {
            $deletable_total = queue_monitor_count($db, array_merge($filters, [
                'deletable_only' => true,
                'max_id'         => $page['max_id'],
            ]));
        }
    } catch (Throwable $e) {
        write_debug_log("[APIv2] Queue monitor list failed: " . $e->getMessage(), "error");
        api_v2_json_result(500, "ERROR: The queue could not be listed.", null);
        return;
    } finally {
        db_close($db);
    }

    $rows = [];
    foreach ($page['rows'] as $row) {
        $id = (int)$row['id'];
        $summary = $summaries[$id] ?? [];
        $type = (string)$row['task_type'];
        $priority = (int)$row['priority'];

        $rows[] = [
            'id'            => $id,
            'task_type'     => $type,
            'status'        => $row['status'],
            'priority'      => $priority,
            'attempts'      => (int)$row['attempts'],
            'created_at'    => $row['created_at'],
            'updated_at'    => $row['updated_at'],
            'display'       => queue_task_display_status($row, $summary),
            'priority_tier' => queue_priority_tier($priority),
            'progress'      => ['done' => (int)($summary['done'] ?? 0), 'total' => (int)($summary['total'] ?? 0)],
            'error'         => ($summary['error'] ?? null) ?: ($row['last_error'] ?? null),
            'restartable'   => queue_job_is_restartable($type),
            'cancelable'    => queue_job_is_cancelable($type),
            // Job type only, like restartable/cancelable: the row's status decides the rest.
            'deletable'     => queue_job_is_deletable($type),
            // Read from the payload by the list query; the payload itself is never listed.
            'restarted_as'  => isset($row['restarted_as']) ? (int)$row['restarted_as'] : null,
        ];
    }

    api_v2_json_result(200, "SUCCESS", [
        'rows'          => $rows,
        'total'         => $page['total'],
        'filtered'      => $page['filtered'],
        'status_counts' => $status_counts,
        'max_id'           => $page['max_id'],
        'actionable_total' => $actionable_total,
        'deletable_total'  => $deletable_total,
    ]);
}

// GET /admin/queue/summary
function api_v2_admin_queue_summary()
{
    api_v2_check_admin();

    $db = db_open();
    try {
        $summary = queue_monitor_summary($db);
    } catch (Throwable $e) {
        write_debug_log("[APIv2] Queue monitor summary failed: " . $e->getMessage(), "error");
        api_v2_json_result(500, "ERROR: The queue summary could not be read.", null);
        return;
    } finally {
        db_close($db);
    }

    api_v2_json_result(200, "SUCCESS", $summary);
}

/** The positive queue_task_id query parameter, or null when missing/invalid. */
function api_v2_queue_monitor_task_id(): ?int
{
    $raw = get_param("GET", "queue_task_id", null);
    if (is_int($raw)) {
        $id = $raw;
    } elseif (is_string($raw) && ctype_digit($raw)) {
        $id = (int)$raw;
    } else {
        return null;
    }
    return $id > 0 ? $id : null;
}

// GET /admin/queue/steps?queue_task_id=
function api_v2_admin_queue_steps()
{
    api_v2_check_admin();

    $task_id = api_v2_queue_monitor_task_id();
    if ($task_id === null) {
        api_v2_json_result(400, "BAD REQUEST: queue_task_id must be a positive integer.", null);
        return;
    }

    $db = db_open();
    try {
        $steps = queue_task_steps($db, $task_id);
        // No step rows is "purged" for an existing task, but a task id that does not exist is a 404.
        $task_found = !$steps['purged'] || queue_task_exists($db, $task_id);
    } catch (Throwable $e) {
        write_debug_log("[APIv2] Queue monitor steps failed for task #{$task_id}: " . $e->getMessage(), "error");
        api_v2_json_result(500, "ERROR: The task steps could not be read.", null);
        return;
    } finally {
        db_close($db);
    }

    if (!$task_found) {
        api_v2_json_result(404, "NOT FOUND: No queue task with that id.", null);
        return;
    }

    api_v2_json_result(200, "SUCCESS", $steps);
}

// GET /admin/queue/payload?queue_task_id=   (secret-looking values redacted)
function api_v2_admin_queue_payload()
{
    api_v2_check_admin();

    $task_id = api_v2_queue_monitor_task_id();
    if ($task_id === null) {
        api_v2_json_result(400, "BAD REQUEST: queue_task_id must be a positive integer.", null);
        return;
    }

    $db = db_open();
    try {
        $payload = queue_task_payload($db, $task_id);
    } catch (Throwable $e) {
        write_debug_log("[APIv2] Queue monitor payload failed for task #{$task_id}: " . $e->getMessage(), "error");
        api_v2_json_result(500, "ERROR: The task payload could not be read.", null);
        return;
    } finally {
        db_close($db);
    }

    if ($payload === null) {
        api_v2_json_result(404, "NOT FOUND: No queue task with that id.", null);
        return;
    }

    api_v2_json_result(200, "SUCCESS", ['queue_task_id' => $task_id, 'payload' => $payload]);
}

/**
 * Shared body of the three mutating routes: resolve the targets, run the action
 * and report per-id results with applied/skipped counts.
 *
 * @param callable(PDO, list<int>, int):array $action queue_cancel_tasks | queue_restart_tasks | queue_run_now | queue_delete_tasks
 * @param array $forced_filters merged into an all_filtered filter after normalization
 */
function api_v2_queue_monitor_run_action(callable $action, array $forced_filters = []): void
{
    api_v2_check_admin();

    $request = [
        'ids'          => get_param("POST", "ids", []),
        'all_filtered' => get_param("POST", "all_filtered", false),
        'filter'       => get_param("POST", "filter", []),
    ];
    $uid = (int)($_SESSION['uid'] ?? 0);

    $db = db_open();
    try {
        $targets = queue_resolve_target_ids($db, $request, $forced_filters);
        // data.code is the machine-readable reason, so a client can tell the two apart.
        if ($targets['error'] === 'too_many_targets') {
            api_v2_json_result(400, "BAD REQUEST: Too many tasks selected; the limit is " . QUEUE_BULK_MAX . " per request.", ['code' => 'too_many_targets']);
            return;
        }
        if ($targets['error'] !== null) {
            api_v2_json_result(400, "BAD REQUEST: No tasks were selected.", ['code' => 'no_targets']);
            return;
        }

        $results = $action($db, $targets['ids'], $uid);
    } catch (Throwable $e) {
        write_debug_log("[APIv2] Queue monitor action failed: " . $e->getMessage(), "error");
        api_v2_json_result(500, "ERROR: The action could not be completed.", null);
        return;
    } finally {
        db_close($db);
    }

    $applied = count(array_filter($results, static fn(array $r): bool => ($r['result'] ?? '') === 'applied'));

    api_v2_json_result(200, "SUCCESS", [
        'results' => $results,
        'applied' => $applied,
        'skipped' => count($results) - $applied,
    ]);
}

// POST /admin/queue/cancel  { ids: [int] } | { all_filtered: true, filter: {...} }
function api_v2_admin_queue_cancel()
{
    api_v2_queue_monitor_run_action('queue_cancel_tasks');
}

// POST /admin/queue/restart
function api_v2_admin_queue_restart()
{
    // Select all N never counts tasks that were already restarted.
    api_v2_queue_monitor_run_action('queue_restart_tasks', ['exclude_restarted' => true]);
}

// POST /admin/queue/run-now
function api_v2_admin_queue_run_now()
{
    api_v2_queue_monitor_run_action('queue_run_now');
}

// POST /admin/queue/delete
function api_v2_admin_queue_delete()
{
    // An all_filtered delete can only ever reach finished tasks of a deletable type,
    // whatever statuses the request's filter names (pending/running never match).
    api_v2_queue_monitor_run_action('queue_delete_tasks', ['deletable_only' => true]);
}

// GET /admin/queue/settings
function api_v2_admin_queue_settings_get()
{
    api_v2_check_admin();

    $db = db_open();
    try {
        $retention = api_v2_queue_monitor_retention($db);
    } catch (Throwable $e) {
        write_debug_log("[APIv2] Queue monitor settings read failed: " . $e->getMessage(), "error");
        api_v2_json_result(500, "ERROR: The retention settings could not be read.", null);
        return;
    } finally {
        db_close($db);
    }

    api_v2_json_result(200, "SUCCESS", $retention);
}

// POST /admin/queue/settings  { completed_days: 0..3650, failed_days: 0..3650 }
function api_v2_admin_queue_settings_save()
{
    api_v2_check_admin();

    $completed = api_v2_queue_monitor_parse_days(get_param("POST", "completed_days", null));
    $failed = api_v2_queue_monitor_parse_days(get_param("POST", "failed_days", null));

    // Validate both before writing either, so an invalid request changes nothing;
    // the two writes then share one transaction, so a failure of the second rolls
    // the first back.
    if ($completed === null || $failed === null) {
        api_v2_json_result(400, "BAD REQUEST: completed_days and failed_days must be whole numbers from 0 to " . QUEUE_RETENTION_MAX_DAYS . " (0 keeps tasks forever).", null);
        return;
    }

    $db = db_open();
    try {
        $saved = false;
        $db->beginTransaction();
        try {
            $saved = update_or_insert_setting(QUEUE_RETENTION_SETTING_COMPLETED, (string)$completed, $db)
                && update_or_insert_setting(QUEUE_RETENTION_SETTING_FAILED, (string)$failed, $db);
            if ($saved) {
                $db->commit();
            }
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    } catch (Throwable $e) {
        write_debug_log("[APIv2] Queue monitor settings save failed: " . $e->getMessage(), "error");
        $saved = false;
    } finally {
        db_close($db);
    }

    if (!$saved) {
        api_v2_json_result(500, "ERROR: The retention settings could not be saved.", null);
        return;
    }

    api_v2_json_result(200, "SUCCESS", ['completed_days' => $completed, 'failed_days' => $failed]);
}
