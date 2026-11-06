<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Queue Monitor read side (SR-2408): list, search, status counts, per-task
 * step summaries and the header summary. Pure helpers (priority tier, payload
 * redaction, display status) are DB-free so they can be unit tested anywhere.
 * Spec: docs/superpowers/specs/2026-10-24-queue-monitor-redesign-design.md
 *
 * OUTPUT ENCODING: every string returned here (last_error, error, stage,
 * task_type, the payload _raw text, ...) is raw and unescaped. The display sink
 * must escape it exactly once.
 *
 * SCALING: free-text search and the unfiltered COUNT(*) scans grow with table
 * size; retention pruning is what bounds them.
 */

// Every direct consumer declares its own requires (function reachability rule).
require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/queues.php'));
require_once(realpath(__DIR__ . '/promises.php'));
require_once(realpath(__DIR__ . '/workers.php'));
require_once(realpath(__DIR__ . '/queue_config.php'));

// Retention defaults used by the pruning/settings work in later tasks.
if (!defined('QUEUE_RETENTION_DEFAULT_COMPLETED_DAYS')) {
    define('QUEUE_RETENTION_DEFAULT_COMPLETED_DAYS', 30);
}
if (!defined('QUEUE_RETENTION_DEFAULT_FAILED_DAYS')) {
    define('QUEUE_RETENTION_DEFAULT_FAILED_DAYS', 90);
}

// Retention windows are whole days; 0 means "never prune".
if (!defined('QUEUE_RETENTION_MAX_DAYS')) {
    define('QUEUE_RETENTION_MAX_DAYS', 3650);
}
if (!defined('QUEUE_RETENTION_SETTING_COMPLETED')) {
    define('QUEUE_RETENTION_SETTING_COMPLETED', 'QUEUE_RETENTION_COMPLETED_DAYS');
}
if (!defined('QUEUE_RETENTION_SETTING_FAILED')) {
    define('QUEUE_RETENTION_SETTING_FAILED', 'QUEUE_RETENTION_FAILED_DAYS');
}

// A worker whose heartbeat is older than this is shown as not running.
if (!defined('QUEUE_MONITOR_WORKER_ALIVE_SECONDS')) {
    define('QUEUE_MONITOR_WORKER_ALIVE_SECONDS', 180);
}

/**
 * Display statuses a caller may filter on (stopping is derived, not filterable).
 * The `canceled` pill/filter includes tasks that are still stopping (task row
 * is canceled while an in-flight step finishes).
 */
const QUEUE_MONITOR_FILTER_STATUSES = ['pending', 'running', 'retrying', 'completed', 'failed', 'canceled'];

/** Columns the list may be ordered by. */
const QUEUE_MONITOR_ORDER_COLUMNS = ['id', 'task_type', 'status', 'priority', 'attempts', 'created_at', 'updated_at'];

/** A promise is terminal when EITHER status or state holds one of these. */
const QUEUE_MONITOR_TERMINAL = ['completed', 'fulfilled', 'failed', 'canceled'];

/** Task statuses the Queue Monitor's Delete may act on: finished tasks only. */
const QUEUE_DELETABLE_STATUSES = ['completed', 'failed', 'canceled'];

/**
 * Minutes after a cancel during which Delete still treats the canceled task as
 * Stopping. cancel_queue_task() marks the task canceled while the queue worker
 * may still be inside its handler, and a staged job's handler can insert its
 * promise rows only after slow preparation; deleting the task in that gap would
 * leave those rows pointing at nothing (the step worker ignores the parent's
 * status and would run them). The cancel moves updated_at (ON UPDATE
 * CURRENT_TIMESTAMP), so the window is measured from it. Delete and Restart
 * first check the exact signal — whether a worker still holds the task's claim
 * (queue_task_lock_in_use()); this window is the fallback for the gap the claim
 * cannot see (a handler's last writes, a step created just before it returned).
 * It follows QUEUE_STUCK_TASK_MINUTES (default 30, from config.php via
 * queue_config()), the window after which a dead worker's connection, and so
 * its claim, is gone, but is never shorter than 30 minutes: Delete
 * runs in a concurrent web request, and lowering the stuck threshold must not
 * shrink this safety window below what it has always been. A define() rather
 * than a const because const cannot call a function; functions.php (and with
 * it config.php) is loaded above, so the configured value is in effect here.
 * Deliberately unguarded: it is derived, never a config.php override.
 */
define('QUEUE_CANCEL_SETTLE_MINUTES', max(30, queue_config('QUEUE_STUCK_TASK_MINUTES')));

/**
 * Task-type prefix the monitor never acts on: the Encrypted Database Extra drives
 * its own lifecycle through these (queue_task_type_is_protected()).
 */
const QUEUE_PROTECTED_TASK_TYPE_PREFIX = 'encryption_';

/**
 * The statuses a Delete with "all matching the filter" may reach: the filter's
 * statuses that are deletable, or every deletable status when the filter has
 * none. An empty result (only non-deletable statuses were asked for) means
 * nothing may be targeted; it never widens to "no status restriction".
 *
 * @param list<string> $statuses normalized filter statuses (queue_monitor_filters())
 * @return list<string>
 */
function queue_delete_target_statuses(array $statuses): array
{
    if (!$statuses) {
        return QUEUE_DELETABLE_STATUSES;
    }
    return array_values(array_intersect(QUEUE_DELETABLE_STATUSES, array_map('strval', $statuses)));
}

/****************************
 * FUNCTION: PRIORITY TIER  *
 ****************************/
function queue_priority_tier(int $priority): string
{
    if ($priority >= 100) {
        return 'immediate';
    }
    if ($priority >= 75) {
        return 'high';
    }
    if ($priority >= 50) {
        return 'medium';
    }
    if ($priority >= 25) {
        return 'low';
    }
    return 'lowest';
}

/*******************************
 * FUNCTION: REDACT PAYLOAD    *
 *******************************/
function queue_redact_key_matches(string $key): bool
{
    return (bool)preg_match(
        '/password|passwd|pwd|passphrase|secret|token|api[_-]?key|apikey|authorization|credential|private|cookie|session|(^|[_-])key$|(?-i:[a-z]Key$)/i',
        $key
    );
}

function queue_redact_payload(mixed $payload): mixed
{
    if (!is_array($payload)) {
        return $payload;
    }

    $out = [];
    foreach ($payload as $key => $value) {
        if (is_string($key) && queue_redact_key_matches($key)) {
            $out[$key] = '[redacted]';
        } else {
            $out[$key] = queue_redact_payload($value);
        }
    }
    return $out;
}

/**
 * Text fallback for payloads that are not valid JSON: redacts string, numeric
 * and bare scalar values under any matching "key": pair. Limitation: object or
 * array values are not blanked as a whole (their inner pairs are checked
 * individually), and non-JSON syntax is only handled on a best-effort basis.
 */
function queue_redact_raw_text(string $raw): string
{
    $out = preg_replace_callback(
        '/("(?:[^"\\\\]|\\\\.)*")(\s*:\s*)("(?:[^"\\\\]|\\\\.)*"|[^\s,}\]{\[][^,}\]\s]*)/',
        static function (array $m): string {
            $key = json_decode($m[1]);
            $key = is_string($key) ? $key : trim($m[1], '"');
            return queue_redact_key_matches($key) ? $m[1] . $m[2] . '"[redacted]"' : $m[0];
        },
        $raw
    );
    return $out ?? '[unreadable]';
}

/**
 * Redacts a payload kept as a JSON string and returns a JSON string, for
 * responses whose payload field is the raw stored text (the legacy
 * GET /admin/queue/promises). Text with nothing to redact is returned
 * unchanged, byte for byte (re-encoding would turn {} into []); text that is
 * not a JSON object/array goes through the same textual fallback as
 * queue_task_payload().
 */
function queue_monitor_redact_payload_json(string $raw): string
{
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return json_last_error() === JSON_ERROR_NONE ? $raw : queue_redact_raw_text($raw);
    }

    $redacted = queue_redact_payload($decoded);
    if ($redacted === $decoded) {
        return $raw;
    }

    $json = json_encode($redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? queue_redact_raw_text($raw) : $json;
}

/*********************************
 * FUNCTION: TASK DISPLAY STATUS *
 *********************************/
function queue_task_display_status(array $task, array $step_summary = [], ?int $now = null): array
{
    $now = $now ?? time();
    $status = (string)($task['status'] ?? 'pending');
    $result = ['key' => 'pending', 'retry_attempt' => null, 'retry_in' => null];

    if ($status === 'in_progress') {
        if (!empty($step_summary['canceled_live'])) {
            $result['key'] = 'stopping';
        } elseif (!empty($step_summary['retrying'])) {
            $result['key'] = 'retrying';
        } else {
            $result['key'] = 'running';
        }
        return $result;
    }

    if ($status === 'canceled' && !empty($step_summary['canceled_live'])) {
        // Task is canceled but an in-flight step has not ended yet.
        $result['key'] = 'stopping';
        return $result;
    }

    if ($status !== 'pending') {
        $result['key'] = in_array($status, ['completed', 'failed', 'canceled'], true) ? $status : 'pending';
        return $result;
    }

    // Pending: retry metadata is either pre-extracted (list rows) or in the payload.
    if (array_key_exists('retry_attempts', $task) || array_key_exists('next_retry_at', $task)) {
        $attempts = $task['retry_attempts'] ?? null;
        $next = $task['next_retry_at'] ?? null;
    } else {
        $payload = json_decode((string)($task['payload'] ?? ''), true);
        $attempts = is_array($payload) ? ($payload['retry_attempts'] ?? null) : null;
        $next = is_array($payload) ? ($payload['next_retry_at'] ?? null) : null;
    }

    $attempts = is_numeric($attempts) ? (int)$attempts : 0;
    $next_ts = is_string($next) && $next !== '' ? strtotime($next) : false;

    if ($attempts > 0 && $next_ts !== false && $next_ts > $now) {
        $result['key'] = 'retrying';
        $result['retry_attempt'] = $attempts;
        $result['retry_in'] = $next_ts - $now;
    }
    return $result;
}

/*********************************
 * FUNCTION: MONITOR FILTERS     *
 *********************************/
function queue_monitor_filters(array $input): array
{
    // Non-scalar nested values (e.g. q sent as an array) count as absent rather
    // than being cast to the literal string "Array".
    $text = static fn($v): string => is_scalar($v) ? (string)$v : '';

    $statuses = $input['statuses'] ?? ($input['status'] ?? []);
    if (is_string($statuses)) {
        $statuses = explode(',', $statuses);
    }
    $clean_statuses = [];
    foreach ((array)$statuses as $s) {
        $s = strtolower(trim($text($s)));
        if (in_array($s, QUEUE_MONITOR_FILTER_STATUSES, true) && !in_array($s, $clean_statuses, true)) {
            $clean_statuses[] = $s;
        }
    }

    $date = static function ($v) use ($text): string {
        $v = trim($text($v));
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        return ($d !== false && $d->format('Y-m-d') === $v) ? $v : '';
    };

    // max_id: only tasks with id <= max_id (the snapshot a "select all N" was
    // counted against); 0 = no bound. exclude_restarted: skip tasks whose payload
    // already records restarted_as (the restart resolver sets it).
    $raw_max_id = $input['max_id'] ?? 0;
    $max_id = (is_int($raw_max_id) || (is_string($raw_max_id) && ctype_digit($raw_max_id))) ? max(0, (int)$raw_max_id) : 0;

    return [
        'q'                 => mb_substr(trim($text($input['q'] ?? '')), 0, 200),
        'statuses'          => $clean_statuses,
        'task_type'         => mb_substr(trim($text($input['task_type'] ?? '')), 0, 100),
        'date_from'         => $date($input['date_from'] ?? ''),
        'date_to'           => $date($input['date_to'] ?? ''),
        'max_id'            => $max_id,
        'exclude_restarted' => filter_var($input['exclude_restarted'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,
    ];
}

/** SQL for "this pending row has a retry scheduled in the future". */
function queue_monitor_retry_sql(string $now_param): string
{
    return "IFNULL(status = 'pending' AND JSON_VALID(payload)"
        . " AND CASE WHEN JSON_VALID(payload) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.retry_attempts')) AS UNSIGNED) END > 0"
        . " AND CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.next_retry_at')) END > {$now_param}, 0)";
}

/**
 * SQL for the restarted_as task id recorded in a task's payload: the integer, or
 * NULL when the payload is not JSON, the key is absent, or it holds JSON null.
 */
function queue_monitor_restarted_as_sql(): string
{
    // Nested CASE: JSON_EXTRACT raises an error on invalid JSON, and AND does not short-circuit reliably.
    return "CASE WHEN JSON_VALID(payload) THEN"
        . " CASE WHEN JSON_TYPE(JSON_EXTRACT(payload, '$.restarted_as')) IN ('INTEGER', 'UNSIGNED INTEGER')"
        . " THEN CAST(JSON_EXTRACT(payload, '$.restarted_as') AS UNSIGNED) END END";
}

/** SQL for the last_error text of a task row. */
function queue_monitor_error_sql(): string
{
    return "CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.last_error')) END";
}

/**
 * Builds the WHERE clause shared by the list and the counts.
 * LIKE patterns use ESCAPE '!' with !, % and _ escaped (the user's text is literal).
 */
function queue_monitor_where(array $filters, bool $with_status, array &$params): string
{
    $where = [];

    $q = (string)($filters['q'] ?? '');
    if ($q !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $parts = ["task_type LIKE :q_type ESCAPE '!'", queue_monitor_error_sql() . " LIKE :q_err ESCAPE '!'"];
        $params[':q_type'] = $like;
        $params[':q_err'] = $like;
        if (ctype_digit($q) && strlen($q) <= 18) {
            $parts[] = 'id = :q_id';
            $params[':q_id'] = (int)$q;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    if ((int)($filters['max_id'] ?? 0) > 0) {
        $where[] = 'id <= :f_max_id';
        $params[':f_max_id'] = (int)$filters['max_id'];
    }
    if (!empty($filters['exclude_restarted'])) {
        // Mirrors queue_action_eligibility(): a restarted_as key holding any
        // non-null value marks the task as already restarted.
        $where[] = "IFNULL(CASE WHEN JSON_VALID(payload) THEN JSON_TYPE(JSON_EXTRACT(payload, '$.restarted_as')) END <> 'NULL', 0) = 0";
    }
    if (!empty($filters['deletable_only'])) {
        // Set only by the delete resolver and the delete count (a forced filter that
        // queue_monitor_filters() never passes through from a request). Applied
        // whatever $with_status says: a delete can only ever reach finished tasks
        // whose type may be deleted, so pending/in_progress rows never match.
        $statuses = queue_delete_target_statuses((array)($filters['statuses'] ?? []));
        if (!$statuses) {
            $where[] = '1 = 0';
        } else {
            $in = [];
            foreach ($statuses as $i => $s) {
                $in[] = ":del_s{$i}";
                $params[":del_s{$i}"] = $s;
            }
            $where[] = 'status IN (' . implode(', ', $in) . ')';
        }
        // Mirrors queue_job_is_deletable(): never a protected type, never a type whose
        // finished history is program state (the retention purge's exempt list).
        $where[] = "task_type NOT LIKE :del_protected ESCAPE '!'";
        $params[':del_protected'] = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], QUEUE_PROTECTED_TASK_TYPE_PREFIX) . '%';
        $exempt = [];
        foreach (array_values(array_unique(array_map('strval', QUEUE_PURGE_EXEMPT_TASK_TYPES))) as $i => $type) {
            $exempt[] = ":del_ex{$i}";
            $params[":del_ex{$i}"] = $type;
        }
        if ($exempt) {
            $where[] = 'task_type NOT IN (' . implode(', ', $exempt) . ')';
        }
        // A task canceled within the settle window is refused as stopping, so it is
        // not counted or targeted either (queue_action_eligibility(), 'delete').
        $where[] = "NOT (status = 'canceled' AND updated_at >= NOW() - INTERVAL :del_settle MINUTE)";
        $params[':del_settle'] = QUEUE_CANCEL_SETTLE_MINUTES;
    }

    if (($filters['task_type'] ?? '') !== '') {
        $where[] = 'task_type = :f_type';
        $params[':f_type'] = $filters['task_type'];
    }
    if (($filters['date_from'] ?? '') !== '') {
        $where[] = 'created_at >= :f_from';
        $params[':f_from'] = $filters['date_from'] . ' 00:00:00';
    }
    if (($filters['date_to'] ?? '') !== '') {
        // Inclusive of the whole end day.
        $where[] = 'created_at < :f_to';
        $params[':f_to'] = date('Y-m-d', strtotime($filters['date_to'] . ' +1 day')) . ' 00:00:00';
    }

    if ($with_status && !empty($filters['statuses'])) {
        $retry = queue_monitor_retry_sql(':now_ts');
        if (array_intersect(['pending', 'retrying'], $filters['statuses'])) {
            $params[':now_ts'] = date('Y-m-d H:i:s');
        }
        $or = [];
        foreach ($filters['statuses'] as $s) {
            switch ($s) {
                case 'running':
                    $or[] = "status = 'in_progress'";
                    break;
                case 'pending':
                    // Status index narrows first; retrying rows are reported separately.
                    $or[] = "(status = 'pending' AND NOT {$retry})";
                    break;
                case 'retrying':
                    $or[] = "(status = 'pending' AND {$retry})";
                    break;
                default: // completed, failed, canceled
                    $or[] = "status = '" . $s . "'";
            }
        }
        $where[] = '(' . implode(' OR ', $or) . ')';
    }

    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

/*********************************
 * FUNCTION: MATCHING TASK IDS   *
 *********************************/
/**
 * Ids of the tasks matching a normalized filter (queue_monitor_filters()), newest
 * first, capped at $limit. Used to resolve "apply to all filtered" bulk actions
 * with the exact same WHERE as the list, so the two can never disagree.
 *
 * @return list<int>
 */
function queue_monitor_matching_ids(PDO $db, array $filters, int $limit): array
{
    $limit = max(1, $limit);
    $params = [];
    $where = queue_monitor_where($filters, true, $params);
    $stmt = $db->prepare("SELECT id FROM queue_tasks{$where} ORDER BY id DESC LIMIT {$limit}");
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/*************************
 * FUNCTION: LIST TASKS  *
 *************************/
function queue_monitor_list(PDO $db, array $filters, int $offset, int $limit, string $order_col, string $order_dir): array
{
    // Pick the column FROM the allow-list rather than validating the input in place:
    // the value that reaches the SQL is then a constant, which static taint analysis
    // can see (it does not treat in_array() as a sanitizer).
    $safe_col = 'id';
    foreach (QUEUE_MONITOR_ORDER_COLUMNS as $allowed_col) {
        if ($allowed_col === $order_col) {
            $safe_col = $allowed_col;
            break;
        }
    }
    $order_col = $safe_col;
    $order_dir = strtoupper($order_dir) === 'ASC' ? 'ASC' : 'DESC';
    $offset = max(0, $offset);
    $limit = max(1, min(500, $limit));

    // The newest id, read FIRST (primary key, a single index lookup). A "select
    // all N" sends it back as filter.max_id so the action never reaches tasks
    // queued after the count was shown. Reading it before the list and count
    // queries means a task inserted in between can only make the shown counts
    // larger than the max_id-bounded target set, never smaller.
    $max_id = (int)$db->query("SELECT MAX(id) FROM queue_tasks")->fetchColumn();

    $params = [];
    $where = queue_monitor_where($filters, true, $params);

    // Payload itself is never selected; only the three fields the table shows.
    $sql = "SELECT id, task_type, status, priority, attempts, created_at, updated_at,"
        . " " . queue_monitor_error_sql() . " AS last_error,"
        . " CASE WHEN JSON_VALID(payload) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.retry_attempts')) AS UNSIGNED) END AS retry_attempts,"
        . " CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.next_retry_at')) END AS next_retry_at,"
        . " " . queue_monitor_restarted_as_sql() . " AS restarted_as"
        . " FROM queue_tasks{$where}"
        . " ORDER BY `{$order_col}` {$order_dir}, id {$order_dir}"
        . " LIMIT {$limit} OFFSET {$offset}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT COUNT(*) FROM queue_tasks{$where}");
    $stmt->execute($params);
    $filtered = (int)$stmt->fetchColumn();

    $total = (int)$db->query("SELECT COUNT(*) FROM queue_tasks")->fetchColumn();

    return ['rows' => $rows, 'total' => $total, 'filtered' => $filtered, 'max_id' => $max_id];
}

/** Number of tasks matching a normalized filter (queue_monitor_filters()). */
function queue_monitor_count(PDO $db, array $filters): int
{
    $params = [];
    $where = queue_monitor_where($filters, true, $params);
    $stmt = $db->prepare("SELECT COUNT(*) FROM queue_tasks{$where}");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/*******************************
 * FUNCTION: STATUS COUNTS     *
 *******************************/
function queue_monitor_status_counts(PDO $db, array $filters_without_status): array
{
    $counts = array_fill_keys(QUEUE_MONITOR_FILTER_STATUSES, 0);

    $params = [];
    $where = queue_monitor_where($filters_without_status, false, $params);
    $stmt = $db->prepare("SELECT status, COUNT(*) AS c FROM queue_tasks{$where} GROUP BY status");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['status'] === 'in_progress' ? 'running' : (string)$row['status'];
        if (array_key_exists($key, $counts)) {
            $counts[$key] = (int)$row['c'];
        }
    }

    // Retrying is a subset of pending; only pending rows' payloads are read.
    $params = [':now_ts' => date('Y-m-d H:i:s')];
    $where = queue_monitor_where($filters_without_status, false, $params);
    $retry = queue_monitor_retry_sql(':now_ts');
    $where = ($where === '' ? ' WHERE ' : $where . ' AND ') . "status = 'pending' AND {$retry}";
    $stmt = $db->prepare("SELECT COUNT(*) FROM queue_tasks{$where}");
    $stmt->execute($params);
    $retrying = (int)$stmt->fetchColumn();

    $counts['retrying'] = $retrying;
    $counts['pending'] = max(0, $counts['pending'] - $retrying);
    $counts['all'] = array_sum($counts);

    return $counts;
}

/** True when either status or state of a promise row is terminal. */
function queue_monitor_promise_terminal(array $p): bool
{
    return in_array($p['status'] ?? '', QUEUE_MONITOR_TERMINAL, true)
        || in_array($p['state'] ?? '', QUEUE_MONITOR_TERMINAL, true);
}

/**
 * True when any of a task's steps (promise rows with status and state) is still
 * live: a stage is executing (state in_progress, which a cancel leaves in place
 * until the stage ends), or the step worker can still pick it up (neither status
 * nor state terminal). The step worker does not look at the task's own status,
 * so a failed task can still own a live parallel step.
 *
 * @param list<array{status?:?string,state?:?string}> $steps
 */
function queue_steps_have_live_step(array $steps): bool
{
    foreach ($steps as $p) {
        if (($p['state'] ?? '') === 'in_progress' || !queue_monitor_promise_terminal($p)) {
            return true;
        }
    }
    return false;
}

/**************************************
 * FUNCTION: STEP SUMMARIES (BATCHED) *
 **************************************/
function queue_monitor_step_summaries(PDO $db, array $task_ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $task_ids), static fn($i) => $i > 0)));
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = ['total' => 0, 'done' => 0, 'failed_stage' => null, 'error' => null, 'retrying' => false, 'canceled_live' => false];
    }
    if (!$ids) {
        return $out;
    }

    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT id, queue_task_id, current_stage, status, state,"
        . " CASE WHEN JSON_VALID(payload) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$._retry_attempts')) AS UNSIGNED) END AS retry_attempts,"
        . " CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$._last_error')) END AS last_error"
        . " FROM promises WHERE queue_task_id IN ({$in}) ORDER BY queue_task_id, id"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $tid = (int)$p['queue_task_id'];
        $s = &$out[$tid];
        $s['total']++;
        $terminal = queue_monitor_promise_terminal($p);

        if (in_array($p['status'], ['completed', 'fulfilled'], true) || in_array($p['state'], ['completed', 'fulfilled'], true)) {
            $s['done']++;
        }
        if (($p['status'] === 'failed' || $p['state'] === 'failed') && $s['failed_stage'] === null) {
            $s['failed_stage'] = $p['current_stage'];
            $s['error'] = ($p['last_error'] !== null && $p['last_error'] !== '') ? $p['last_error'] : null;
        }
        if (!$terminal && (int)$p['retry_attempts'] > 0) {
            $s['retrying'] = true;
        }
        // Cancel writes status only: an in-flight step keeps state in_progress until it ends.
        if ($p['status'] === 'canceled' && $p['state'] === 'in_progress') {
            $s['canceled_live'] = true;
        }
        unset($s);
    }

    return $out;
}

/** True when a queue_tasks row with this id exists. */
function queue_task_exists(PDO $db, int $task_id): bool
{
    $stmt = $db->prepare("SELECT 1 FROM queue_tasks WHERE id = :t");
    $stmt->execute([':t' => $task_id]);
    return $stmt->fetchColumn() !== false;
}

/*************************
 * FUNCTION: TASK STEPS  *
 *************************/
function queue_task_steps(PDO $db, int $task_id): array
{
    $stmt = $db->prepare(
        "SELECT id, depends_on, current_stage, status, state, created_at, updated_at,"
        . " CASE WHEN JSON_VALID(payload) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$._retry_attempts')) AS UNSIGNED) END AS retry_attempts,"
        . " CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$._last_error')) END AS last_error"
        . " FROM promises WHERE queue_task_id = :t ORDER BY id"
    );
    $stmt->execute([':t' => $task_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return ['steps' => [], 'purged' => true];
    }

    $by_id = [];
    $children = [];
    foreach ($rows as $r) {
        $by_id[(int)$r['id']] = $r;
    }
    $roots = [];
    foreach ($rows as $r) {
        $dep = $r['depends_on'] !== null && $r['depends_on'] !== '' ? (int)$r['depends_on'] : 0;
        if ($dep > 0 && isset($by_id[$dep])) {
            $children[$dep][] = (int)$r['id'];
        } else {
            $roots[] = (int)$r['id'];
        }
    }

    // Walk the dependency chain from each root; leftovers (cycles) fall back to id order.
    $ordered = [];
    $seen = [];
    $stack = array_reverse($roots);
    while ($stack) {
        $id = array_pop($stack);
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $ordered[] = $id;
        foreach (array_reverse($children[$id] ?? []) as $c) {
            $stack[] = $c;
        }
    }
    foreach (array_keys($by_id) as $id) {
        if (!isset($seen[$id])) {
            $ordered[] = $id;
        }
    }

    $steps = [];
    foreach ($ordered as $id) {
        $r = $by_id[$id];
        $duration = null;
        // A canceled step whose state is still in_progress has not ended yet.
        if (queue_monitor_promise_terminal($r) && !($r['status'] === 'canceled' && $r['state'] === 'in_progress')) {
            $c = strtotime((string)$r['created_at']);
            $u = strtotime((string)$r['updated_at']);
            if ($c !== false && $u !== false) {
                $duration = max(0, $u - $c);
            }
        }
        $steps[] = [
            'id'               => $id,
            'stage'            => $r['current_stage'],
            'status'           => $r['status'],
            'state'            => $r['state'],
            'created_at'       => $r['created_at'],
            'updated_at'       => $r['updated_at'],
            'duration_seconds' => $duration,
            'retry_attempt'    => (int)$r['retry_attempts'] > 0 ? (int)$r['retry_attempts'] : null,
            'error'            => ($r['last_error'] !== null && $r['last_error'] !== '') ? $r['last_error'] : null,
        ];
    }

    return ['steps' => $steps, 'purged' => false];
}

/***************************
 * FUNCTION: TASK PAYLOAD  *
 ***************************/
function queue_task_payload(PDO $db, int $task_id): ?array
{
    $stmt = $db->prepare("SELECT payload FROM queue_tasks WHERE id = :t");
    $stmt->execute([':t' => $task_id]);
    $raw = $stmt->fetchColumn();
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        return queue_redact_payload($decoded);
    }

    // Unparseable: redact values under secret-looking keys textually.
    $text = queue_redact_raw_text((string)$raw);
    return ['_raw' => $text];
}

/*************************
 * FUNCTION: SUMMARY     *
 *************************/
/**
 * One worker type's health across its slots (QUEUE_WORKER_COUNT /
 * PROMISE_WORKER_COUNT; slot 1 is $base itself, slot k is "{$base}_k").
 *
 * $lock_values maps a worker name to its raw {name}_lock setting value (a
 * missing slot may be absent or false). Every possible slot (1 .. the setting's
 * maximum) is read, not only the configured ones, so a worker still running
 * after the count was lowered is still counted.
 *
 * Returns the two keys the summary has always had — 'alive' (any slot has a
 * heartbeat within QUEUE_MONITOR_WORKER_ALIVE_SECONDS) and 'last_heartbeat'
 * (the newest heartbeat of any slot, or null) — plus 'workers_alive' (slots
 * with a fresh heartbeat) and 'workers_configured' ($configured). Pure.
 *
 * @param array<string, mixed> $lock_values
 * @return array{alive: bool, last_heartbeat: ?int, workers_alive: int, workers_configured: int}
 */
function queue_monitor_worker_status(array $lock_values, string $base, int $configured, int $now, int $max_slots = 10): array
{
    $alive = 0;
    $newest = null;
    foreach (worker_slot_names($base, $max_slots) as $name) {
        $lock = json_decode((string)($lock_values[$name] ?? ''), true);
        if (!is_array($lock) || !isset($lock['heartbeat']) || !is_numeric($lock['heartbeat'])) {
            continue;
        }
        $beat = (int)$lock['heartbeat'];
        $newest = $newest === null ? $beat : max($newest, $beat);
        if (($now - $beat) <= QUEUE_MONITOR_WORKER_ALIVE_SECONDS) {
            $alive++;
        }
    }

    return [
        'alive'              => $alive > 0,
        'last_heartbeat'     => $newest,
        'workers_alive'      => $alive,
        'workers_configured' => $configured,
    ];
}

function queue_monitor_summary(PDO $db): array
{
    $now = time();
    $types = [
        'cron_queue_worker'   => 'QUEUE_WORKER_COUNT',
        'cron_promise_worker' => 'PROMISE_WORKER_COUNT',
    ];

    // Every slot's lock row of both worker types, in one read.
    $names = [];
    foreach ($types as $base => $setting) {
        foreach (worker_slot_names($base, queue_config_table()[$setting]['max']) as $name) {
            $names[] = "{$name}_lock";
        }
    }
    $in = implode(',', array_fill(0, count($names), '?'));
    $stmt = $db->prepare("SELECT `name`, `value` FROM `settings` WHERE `name` IN ({$in})");
    $stmt->execute($names);
    $lock_values = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lock_values[substr((string)$row['name'], 0, -strlen('_lock'))] = $row['value'];
    }

    $workers = [];
    foreach ($types as $base => $setting) {
        $workers[$base] = queue_monitor_worker_status($lock_values, $base, queue_config($setting), $now, queue_config_table()[$setting]['max']);
    }

    $oldest = $db->query(
        "SELECT TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) FROM queue_tasks WHERE status = 'pending'"
    )->fetchColumn();

    $failed = $db->query(
        "SELECT COUNT(*) FROM queue_tasks WHERE status = 'failed' AND updated_at >= (NOW() - INTERVAL 1 DAY)"
    )->fetchColumn();

    return [
        'workers'                    => $workers,
        'oldest_pending_age_seconds' => ($oldest === null || $oldest === false) ? null : max(0, (int)$oldest),
        'failed_24h'                 => (int)$failed,
    ];
}

/**
 * Dedup token for a queue-failure notification: "queue_fail:{type}:YmdH".
 *
 * notifications.external_guid is VARCHAR(64) and create_notification() uses
 * INSERT IGNORE, so an over-long guid is silently truncated, which would cut
 * the hour bucket off and suppress later failures. The stored guid is
 * "email_fail:" + token (33 + len(type) chars with the hour), so a type longer
 * than 31 chars is shortened to 18 chars + "~" + 12 hex of sha1(type): still
 * readable and distinct per type, and the ":YmdH" suffix stays intact.
 *
 * Pure: same input, same output.
 */
function queue_failure_dedup_token(string $task_type, ?int $now = null): string
{
    if (strlen($task_type) > 31) {
        $task_type = substr($task_type, 0, 18) . '~' . substr(sha1($task_type), 0, 12);
    }

    return "queue_fail:{$task_type}:" . date('YmdH', $now ?? time());
}

/**
 * Tell admins that a queue task has permanently failed (SR-2408).
 *
 * Called from the two terminal-failure paths: worker_handle_task_failure()
 * (workers.php) and the terminal branch of handle_promise_failure()
 * (promises.php). Callers wrap it in try/catch; a notification problem must
 * never break failure handling.
 *
 * - core_email_send is skipped: it has its own notification
 *   (notify_email_send_failure()), so this would double-notify.
 * - The task row is re-read and the notification is only raised when it is
 *   'failed' now. A user-canceled task stays 'canceled' (queue_update_status
 *   never moves it out) and a cancel is not a failure.
 * - One notification per task type per hour: the dedup token is
 *   "queue_fail:{task_type}:YmdH", so a systemic fault that dooms N tasks of one
 *   type raises one row, not N.
 *
 * ESCAPING (see notify_email_send_failure() in mail.php): the title is stored
 * RAW (_lang_raw) because the notification center escapes it at render with no
 * decode step; the body is stored HTML-ESCAPED (_lang) because it is rendered
 * through stripHtml(), whose innerHTML round-trip decodes entities once. The
 * body's only interpolated value is the task type, passed as a _lang() param
 * so it is escaped at source. No payload, error text or other task data is
 * included; the Queue Monitor, which the link opens, carries the detail.
 *
 * @param PDO $db an open connection
 * @param array $task the queue_tasks row (needs id; task_type is re-read)
 * @param ?int $now timestamp used for the hour bucket (tests); defaults to now
 */
function notify_queue_task_failure(PDO $db, array $task, ?int $now = null): void
{
    // Direct consumers declare their own requires (function reachability rule).
    require_once(realpath(__DIR__ . '/functions.php'));
    require_once(realpath(__DIR__ . '/mail.php'));
    require_once(realpath(__DIR__ . '/notifications.php'));

    $task_id = (int)($task['id'] ?? 0);
    if ($task_id <= 0) {
        return;
    }

    // Current state, not the caller's possibly stale copy.
    $stmt = $db->prepare("SELECT `task_type`, `status` FROM `queue_tasks` WHERE `id` = :id");
    $stmt->execute([':id' => $task_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current || $current['status'] !== 'failed') {
        return;
    }

    $task_type = (string)$current['task_type'];
    if ($task_type === '' || $task_type === 'core_email_send') {
        return;
    }

    $title = _lang_raw('QueueTaskFailedNotificationTitle');
    $body  = _lang('QueueTaskFailedNotificationBody', ['task_type' => $task_type]);

    // Same base-URL read as notify_email_send_failure(); see
    // queue_monitor_url_from_base() for why get_base_url() is not used here.
    $base_url = get_setting('simplerisk_base_url', null, db: $db);
    $link     = queue_monitor_url_from_base(is_string($base_url) ? $base_url : null);
    if ($link !== null) {
        $link .= '?status=failed';
        if (!is_safe_notification_link($link)) {
            $link = null;
        }
    }

    create_email_failure_notification(
        $db,
        null,
        $title,
        $body,
        queue_failure_dedup_token($task_type, $now),
        $link
    );
}

