<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/data_integrity.php'));
require_once(realpath(__DIR__ . '/../../../includes/queues.php'));

// GET /admin/data-integrity/issues?issue_type=<optional>
function api_v2_data_integrity_issues_list()
{
    api_v2_check_admin();

    $issue_type = get_param("GET", "issue_type", null) ?: null;
    $db = db_open();
    $issues = get_open_data_integrity_issues($db, $issue_type);
    $total = count_open_data_integrity_issues($db, $issue_type);
    db_close($db);

    $detectors = data_integrity_detectors();
    $targets = data_integrity_text_encoding_scan_targets();
    $result = array_map(function ($issue) use ($detectors, $targets) {
        $issue['repair_mode'] = $detectors[$issue['issue_type']]['repair_mode'] ?? null;

        // Decrypt for display only -- this is a normal authenticated API
        // response (same exposure the source table's own decrypted content
        // already has everywhere else), not a new at-rest copy. The
        // staging table itself stores these encrypted for encrypted-capable
        // columns (see scan_invalid_text_encoding()).
        $is_encrypted = $targets[$issue['table_name']][$issue['column_name']] ?? false;
        if ($is_encrypted) {
            if ($issue['broken_value'] !== null) {
                $issue['broken_value'] = try_decrypt($issue['broken_value']);
            }
            if ($issue['suggested_value'] !== null) {
                $issue['suggested_value'] = try_decrypt($issue['suggested_value']);
            }
        }

        return $issue;
    }, $issues);

    // 'total' is the true, unbounded count -- 'issues' is capped at
    // DATA_INTEGRITY_ISSUES_LIST_LIMIT. The two can diverge (a pathological
    // import leaving more open issues than the cap), and the client needs
    // both to render a "showing N of M" indicator rather than silently
    // rendering the capped list as if it were the whole backlog.
    api_v2_json_result(200, "SUCCESS", [
        'issues' => $result,
        'total'  => $total,
    ]);
}

// PATCH /admin/data-integrity/issues/{id}  { "value": "..." }
//
// NOTE on CSRF: csrf-magic's csrf_check() short-circuits for any non-POST
// method, so this endpoint's CSRF-TOKEN header is sent by the frontend but
// never actually validated. This is a pre-existing, codebase-wide gap
// shared by ~10 other PATCH/PUT v2 endpoints already in production
// (updateAssetById, updateFrameworkById, updateControlById, updateTestById,
// updateAuditById, updateRisk, etc.) -- fixing it means changing the shared
// csrf-magic vendor wrapper's behavior for every PATCH/PUT endpoint in the
// app. The session cookie is SameSite=Strict (is_session_authenticated(),
// includes/api.php), which is the existing compensating control for the
// whole class. Known, accepted, and not new to this endpoint.
function api_v2_data_integrity_issue_apply($id)
{
    api_v2_check_admin();

    // PHP only auto-populates $_POST for POST requests -- PATCH needs the
    // body parsed by hand. parse_non_post_body_into_post() is the codebase's
    // established fix for this (includes/api.php); its own docblock and
    // reference/reference_patch_body_parse_bug.md document why the naive
    // `if (empty($_POST)) { parse... }` guard is itself the bug -- csrf-magic
    // leaves a CSRF token key in $_POST on any session-authenticated call
    // (exactly how the Data Integrity admin page's browser-based repair UI
    // calls this endpoint), which makes that guard false and silently drops
    // the whole PATCH body while the endpoint still answers 200 SUCCESS.
    // There is also no "PATCH" case in get_param()'s method switch --
    // calling get_param("PATCH", ...) skips $_POST entirely and falls
    // straight to a bare php://input JSON-only read, a path used nowhere
    // else in this codebase. Always look up via "POST" after this call,
    // matching every other PATCH/PUT handler (e.g. updateFrameworkById()).
    parse_non_post_body_into_post();

    $id = (int)$id;
    $new_value = get_param("POST", "value", null);

    $db = db_open();
    $stmt = $db->prepare("SELECT * FROM `data_integrity_issues` WHERE `id` = :id AND `status` = 'open'");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $issue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$issue) {
        db_close($db);
        api_v2_json_result(404, "NOT FOUND: No open issue with that id.", null);
        return;
    }

    $detectors = data_integrity_detectors();
    $apply_fn = $detectors[$issue['issue_type']]['apply_fn'] ?? null;

    if ($apply_fn === null || !is_string($new_value) || $new_value === '') {
        db_close($db);
        api_v2_json_result(400, "BAD REQUEST: This issue type has no applicable repair, or no value was provided.", null);
        return;
    }

    $applied = apply_data_integrity_repair_safely($apply_fn, $db, $issue, $new_value, $id);
    if ($applied) {
        resolve_data_integrity_issue($db, $id);
    }
    db_close($db);

    if (!$applied) {
        api_v2_json_result(500, "ERROR: The repair could not be applied.", null);
        return;
    }

    api_v2_json_result(200, "SUCCESS", ["id" => $id, "status" => "resolved"]);
}

// POST /admin/data-integrity/issues/bulk-repair  { "repairs": [{"id":1,"value":"..."}, ...] }
function api_v2_data_integrity_issues_bulk_repair()
{
    api_v2_check_admin();

    $repairs = get_param("POST", "repairs", []);
    if (!is_array($repairs) || empty($repairs)) {
        api_v2_json_result(400, "BAD REQUEST: No repairs provided.", null);
        return;
    }

    $db = db_open();
    $detectors = data_integrity_detectors();
    $results = [];

    foreach ($repairs as $repair) {
        $id = (int)($repair['id'] ?? 0);
        $new_value = $repair['value'] ?? null;

        $stmt = $db->prepare("SELECT * FROM `data_integrity_issues` WHERE `id` = :id AND `status` = 'open'");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);

        $apply_fn = $issue ? ($detectors[$issue['issue_type']]['apply_fn'] ?? null) : null;

        if (!$issue || $apply_fn === null || !is_string($new_value) || $new_value === '') {
            $results[] = ['id' => $id, 'applied' => false];
            continue;
        }

        // Defense-in-depth: a bad row (e.g. an apply_fn that throws on
        // unexpected input) must not abort the loop and discard the
        // $results already accumulated for rows processed earlier in this
        // same request -- those repairs already committed to the DB.
        $applied = apply_data_integrity_repair_safely($apply_fn, $db, $issue, $new_value, $id);
        if ($applied) {
            resolve_data_integrity_issue($db, $id);
        }
        $results[] = ['id' => $id, 'applied' => $applied];
    }

    db_close($db);
    api_v2_json_result(200, "SUCCESS", $results);
}

// POST /admin/data-integrity/scan
function api_v2_data_integrity_scan_trigger()
{
    api_v2_check_admin();

    $db = db_open();
    if (!empty(get_queue_items($db, 'core_data_integrity_scan', ['pending', 'in_progress']))) {
        db_close($db);
        api_v2_json_result(409, "CONFLICT: A scan is already in progress.", null);
        return;
    }

    queue_task($db, 'core_data_integrity_scan', ['triggered_at' => time(), 'manual' => true], 100, 5, 3600);
    db_close($db);

    api_v2_json_result(200, "SUCCESS", ["message" => "Scan queued."]);
}
