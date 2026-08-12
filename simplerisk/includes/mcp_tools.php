<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * MCP tool-handler functions (Spec 2 Plan C, Task 3) — the testable core
 * behind the MCP server adapter (Task 4). Every function here is a plain,
 * exit-free PHP function that returns an array: no exit(), no
 * api_v2_json_result()/api_v2_check_permission() (those call exit() on
 * failure), and no direct HTTP/JSON-RPC concerns. The MCP server adapter
 * (Task 4) maps these return shapes onto MCP tool results; the v2 REST
 * endpoint (extras/artificial_intelligence/includes/api.php) maps
 * ai_enqueue_control_test_generation()'s result onto api_v2_json_result().
 *
 * No tool here writes GRC data directly — mcp_tool_propose_control_tests()
 * only enqueues a background generation job; the AI-drafted tests still land
 * in ai_proposals for human review/approval (see includes/ai_proposals.php).
 */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/permissions.php')); // check_permission() — called directly below
require_once(realpath(__DIR__ . '/ai_context_graph.php')); // ai_get_context()
require_once(realpath(__DIR__ . '/artificial_intelligence.php')); // resolve_ai_context_profile(), ai_capability_enabled()
require_once(realpath(__DIR__ . '/ai_proposals.php'));
require_once(realpath(__DIR__ . '/queues.php')); // queue_task()
require_once(realpath(__DIR__ . '/reporting.php')); // home_risk_separation_sql() — risk L4 team-separation fragment
// NOTE: the Artificial Intelligence Extra's index.php is NOT required at file
// scope. It carries activation side effects and only exists when the customer
// has that Extra, so an unconditional require here would fatal on every MCP
// tool call (including the read-only get_context/get_org_profile tools) when
// the Extra is absent. The one tool that needs the Extra's generation path
// (mcp_tool_propose_control_tests) requires it under an activation guard.

/**
 * The id of a pending/in_progress AI control-test generation queue task for
 * $control_id, or null if none is queued. Shared by the enqueue dedupe gate
 * (ai_enqueue_control_test_generation) and the UI's "still generating?" poll
 * (the `generating` flag on the Extra endpoint GET /ai/controls/{id}/generate-tests
 * — extras/artificial_intelligence/includes/api.php), so both read the queue
 * with one query — the poll needs it to tell "job still running" apart from "job
 * finished with no new proposals", which a proposal-count check alone cannot
 * distinguish. Pass an already-open $db to reuse the caller's connection; omit
 * it to open+close one.
 */
function ai_control_test_generation_task_id(int $control_id, ?PDO $db = null): ?int
{
    if ($control_id <= 0) {
        return null;
    }
    $own_db = ($db === null);
    if ($own_db) {
        $db = db_open();
    }
    $stmt = $db->prepare("
        SELECT id
        FROM queue_tasks
        WHERE JSON_EXTRACT(payload, '$.control_id') = :control_id
            AND task_type = 'ai_control_test_generate'
            AND status IN ('pending', 'in_progress')
        LIMIT 1
    ");
    $stmt->bindParam(':control_id', $control_id, PDO::PARAM_INT);
    $stmt->execute();
    $id = $stmt->fetchColumn();
    if ($own_db) {
        db_close($db);
    }
    return $id === false ? null : (int)$id;
}

/**
 * Shared governed enqueue for AI-drafted control test generation — extracted
 * from api_artificial_intelligence_control_tests_generate() so the v2 REST
 * endpoint and the MCP propose_control_tests tool share one gate chain
 * instead of two independently-maintained copies.
 *
 * Gate order mirrors the original endpoint exactly: capability -> permission
 * -> id validity -> control existence -> dedupe -> [DB fault -> 500] ->
 * enqueue. `code` mirrors the HTTP status the endpoint used to return for
 * each outcome so the caller (api_v2_json_result() or an MCP tool result)
 * can reuse it without re-deriving the mapping.
 *
 * NEVER calls exit()/api_v2_json_result()/api_v2_check_permission() — this
 * function must be safely callable from a non-HTTP context (the MCP tool
 * handler) as well as the v2 endpoint.
 *
 * @return array{ok:bool,code:int,message:string,data:array}
 */
function ai_enqueue_control_test_generation(int $control_id, int $uid): array
{
    global $lang;

    // Ensure the Control Test Generation capability is enabled — this tool
    // enqueues AI-drafted control tests for review.
    if (!ai_capability_enabled('control_test_generation')) {
        write_debug_log("AI control test generation capability is disabled.", "notice");
        return ['ok' => false, 'code' => 503, 'message' => $lang['AIControlTestGenUnavailable'], 'data' => []];
    }

    // Check permissions — matches the define_tests permission the capability's
    // apply handler (ai_apply_control_test_proposal) is registered under.
    // Uses the plain (non-exiting) check_permission() — the exiting
    // api_v2_check_permission() wrapper is HTTP-endpoint-only and must not be
    // called from here.
    if (!check_permission('define_tests')) {
        return [
            'ok'      => false,
            'code'    => 403,
            'message' => 'FORBIDDEN: The user does not have the required permission to perform this action.',
            'data'    => [],
        ];
    }

    // Validate the control id
    if ($control_id <= 0) {
        write_debug_log("AI control test generation requested with an invalid control id.", "info");
        return ['ok' => false, 'code' => 400, 'message' => $lang['AIControlTestGenInvalidId'], 'data' => []];
    }

    // Open the database connection
    $db = db_open();

    try {
        // Record-access: framework controls are not team-scoped today —
        // is_user_allowed_to_access() only supports the 'test'/'audit' record
        // types (see includes/reporting.php's get_governance_failing_controls_items()
        // for the documented reason), and there is no check_access_for_control()
        // helper alongside check_access_for_risk()/_asset()/_audit()/_test()/_document()
        // in includes/functions.php. With no team-separation check to call, this is
        // a pure existence check — matches the getControlById() convention
        // (includes/api.php) of returning 404 for a nonexistent control.
        $stmt = $db->prepare("SELECT id FROM framework_controls WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $control_id, PDO::PARAM_INT);
        $stmt->execute();
        $control = $stmt->fetch();

        if (!$control) {
            write_debug_log("AI control test generation requested for a non-existent control #{$control_id}.", "info");
            db_close($db);
            return ['ok' => false, 'code' => 404, 'message' => $lang['AIControlTestGenNotFound'], 'data' => []];
        }

        // Check if a queue task already exists for this control
        // Dedupe: if a generation task for this control is already queued, 409
        // with its id (shares the one query with the UI's `generating` poll).
        $existing_task_id = ai_control_test_generation_task_id($control_id, $db);
        if ($existing_task_id !== null) {
            write_debug_log("AI control test generation already queued for control #{$control_id}, task #{$existing_task_id}", "info");
            db_close($db);
            return [
                'ok'      => false,
                'code'    => 409,
                'message' => $lang['AIControlTestGenConflict'],
                'data'    => ['task_id' => $existing_task_id],
            ];
        }
    } catch (\Throwable $e) {
        // A DB fault during the existence or dedupe check must not fall
        // through to queue_task() — that would enqueue past unverified
        // gates. Fail closed with a 500 instead.
        write_debug_log($e->getMessage(), "error");
        db_close($db);
        return ['ok' => false, 'code' => 500, 'message' => $lang['AIControlTestGenCheckFailed'], 'data' => []];
    }

    // Create the queue task
    $queue_task_payload = [
        'control_id'       => $control_id,
        'requested_by_uid' => $uid,
    ];
    $queued = queue_task($db, 'ai_control_test_generate', $queue_task_payload, 75, 5, 3600);

    // Close the database connection
    db_close($db);

    if ($queued) {
        write_debug_log("Queued AI Control Test Generation for control #{$control_id}", "info");
        return ['ok' => true, 'code' => 202, 'message' => $lang['AIControlTestGenQueued'], 'data' => ['control_id' => $control_id]];
    }

    write_debug_log("Failed to create AI Control Test Generation queue task for control #{$control_id}", "error");
    return ['ok' => false, 'code' => 500, 'message' => $lang['AIControlTestGenFailed'], 'data' => []];
}

/**
 * MCP tool: get_context(type, id). Identity scoping (L2-L4) is enforced
 * inside ai_get_context() via $_SESSION['uid'] / the caller's held
 * permissions — this wrapper adds no additional authorization logic.
 *
 * First-order (single-hop) neighbors are the supported contract for this MCP
 * tool. ai_get_context() itself now supports a depth-2 walk (one more hop out
 * from each first-order neighbor) via the v2 REST endpoint's ?depth=2 query
 * parameter, but this tool hardcodes depth=1 and does not accept a
 * caller-supplied depth — exposing it here is a future capability, not yet
 * wired up.
 */
function mcp_tool_get_context(string $type, int $id): array
{
    $ctx = ai_get_context($type, $id, 1);

    if ($ctx === null) {
        return ['ok' => false, 'error' => 'not_found_or_forbidden'];
    }

    return ['ok' => true, 'context' => $ctx];
}

/**
 * MCP tool: get_org_profile(). Returns the three-class Context Profile
 * (asked / derived / authoritative).
 *
 * Authorization parity with get_context: the sibling tool call
 * mcp_tool_get_context('context_profile', 0) is correctly DENIED for a key
 * that holds only the outer ai_context_access permission, because
 * ai_get_context() enforces the finer ai_context_caller_can_read_any_domain()
 * gate on the 'any'-sentinel context_profile type. This tool bypasses
 * ai_get_context() and reads the profile directly, so it MUST enforce the same
 * gate itself — otherwise an ai_context_access-only key would get the full org
 * profile here that get_context refuses it. Denial mirrors get_context's exact
 * failure shape so the MCP adapter maps it to the same authorization result.
 */
function mcp_tool_get_org_profile(): array
{
    // Belt-and-suspenders per CLAUDE.md: ai_context_caller_can_read_any_domain()
    // is defined in ai_context_graph.php (already required at file scope, so
    // this is a no-op include that documents the direct dependency).
    require_once(realpath(__DIR__ . '/ai_context_graph.php'));

    if (!ai_context_caller_can_read_any_domain()) {
        return ['ok' => false, 'error' => 'not_found_or_forbidden'];
    }

    return ['ok' => true, 'profile' => resolve_ai_context_profile()];
}

/**
 * MCP tool: propose_control_tests(control_id). Governed write path — only
 * ever enqueues a background generation job via the shared
 * ai_enqueue_control_test_generation() helper; never writes GRC data
 * directly.
 */
function mcp_tool_propose_control_tests(int $control_id): array
{
    global $lang;

    // The AI-drafted control-test generation path lives in the Artificial
    // Intelligence Extra. Guard the Extra require the same way other Core
    // callers do (function_exists + activation flag + is_dir; see
    // includes/queues.php and includes/artificial_intelligence.php) so a
    // missing/inactive Extra yields a proper "unavailable" tool result instead
    // of a fatal — and so the read-only tools never pull in the Extra at all.
    if (!function_exists('artificial_intelligence_extra') || !artificial_intelligence_extra()
        || !is_dir(realpath(__DIR__ . '/../extras/artificial_intelligence'))) {
        return ['ok' => false, 'code' => 503, 'message' => $lang['AIControlTestGenUnavailable'], 'data' => []];
    }
    require_once(realpath(__DIR__ . '/../extras/artificial_intelligence/index.php'));

    return ai_enqueue_control_test_generation($control_id, (int)($_SESSION['uid'] ?? 0));
}

/**
 * Clamp a caller-supplied MCP `limit` to a sane, token-bounded range.
 * Non-positive → default; anything over $max → $max. Pure (no I/O).
 */
function ai_mcp_clamp_limit(int $limit, int $default = 25, int $max = 100): int
{
    if ($limit <= 0) {
        return $default;
    }
    return min($limit, $max);
}

/**
 * MCP tool: list_control_gaps(framework_id?, limit?). Controls that need
 * attention — failing (control_status = 0) OR below their target maturity —
 * so an agent can find control ids to feed get_context('control', id) /
 * propose_control_tests(id).
 *
 * Framework controls are NOT team-scoped in SimpleRisk (see
 * ai_enqueue_control_test_generation()'s record-access note). In addition to
 * the ai_context_access gate at the MCP entry point, this tool re-enforces
 * the 'governance' domain permission (parity with get_context('control', id)).
 * short_name is try_decrypt()'d to match the plaintext get_context('control', id)
 * returns.
 */
function mcp_tool_list_control_gaps(?int $framework_id = null, int $limit = 25): array
{
    // Domain gate — parity with get_context('control', id), which enforces
    // the permission get_context enforces for this type via
    // ai_context_type_permission(). Discovery must be no more permissive than fetch.
    if (!check_permission(ai_context_type_permission('control'))) {
        return ['ok' => false, 'error' => 'forbidden'];
    }

    $limit = ai_mcp_clamp_limit($limit);

    $fw_join = $fw_clause = '';
    $params = [];
    if ($framework_id !== null && $framework_id > 0) {
        // Only controls mapped to the selected framework (mirrors the reporting
        // helpers' framework scoping via framework_control_mappings).
        $fw_join = "INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
                    INNER JOIN frameworks f ON fcm.framework = f.value AND f.status = 1";
        $fw_clause = "AND f.value = ?";
        $params[] = $framework_id;
    }

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT fc.id, fc.short_name, fc.control_status,
               fc.control_maturity, fc.desired_maturity
        FROM framework_controls fc
        {$fw_join}
        WHERE fc.deleted = 0
          AND (fc.control_status = 0 OR fc.control_maturity < fc.desired_maturity)
        {$fw_clause}
        ORDER BY fc.short_name ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        // A control that is both failing and below-target is reported once as
        // 'fail' (the more urgent state).
        $reason = ((int) $r['control_status'] === 0) ? 'fail' : 'maturity_gap';
        $items[] = [
            'type'             => 'control',
            'id'               => (int) $r['id'],
            'short_name'       => (string) try_decrypt($r['short_name']),
            'maturity'         => is_null($r['control_maturity']) ? null : (int) $r['control_maturity'],
            'desired_maturity' => is_null($r['desired_maturity']) ? null : (int) $r['desired_maturity'],
            'reason'           => $reason,
        ];
    }

    return ['ok' => true, 'items' => $items, 'meta' => ['count' => count($items), 'limit' => $limit]];
}

/**
 * MCP tool: list_tests_due(scope, limit?). Control tests that are due —
 * scope 'upcoming' (due today or later) or 'overdue' (past due) — so an agent
 * can find where test coverage needs action. Retired tests are excluded.
 *
 * Team-scoped: the TEAM-SEPARATION block below mirrors
 * get_home_upcoming_tests_items()'s L4 separation fragment so discovery is
 * symmetric with get_context('test', id) — a caller only sees tests they may
 * access. The mirroring is scoped to that block only: this tool additionally
 * filters out retired tests (t.retired_at IS NULL), a restriction the
 * dashboard function does not apply.
 */
function mcp_tool_list_tests_due(string $scope = 'upcoming', int $limit = 25): array
{
    if ($scope !== 'upcoming' && $scope !== 'overdue') {
        return ['ok' => false, 'error' => 'invalid_scope'];
    }
    // Domain gate — parity with get_context('test', id), which enforces
    // the permission get_context enforces for this type via
    // ai_context_type_permission(). Discovery must be no more permissive than fetch.
    if (!check_permission(ai_context_type_permission('test'))) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    $limit = ai_mcp_clamp_limit($limit);

    // Team separation (optional): restrict to accessible tests. Same shape as
    // get_home_upcoming_tests_items() (includes/reporting.php). Coverage note:
    // this L4 branch mirrors that dashboard function's separation fragment and,
    // like it, is not exercised by a dedicated unit test in this file — no
    // fixture here activates team_separation_extra().
    $team_clause = '';
    $params = [];
    if (team_separation_extra()) {
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        if (!should_skip_test_and_audit_permission_check()) {
            $access = get_compliance_separation_access_info();
            $accessible = $access['framework_control_tests'] ?? [];
            if (empty($accessible)) {
                return ['ok' => true, 'items' => [], 'meta' => ['count' => 0, 'limit' => $limit]];
            }
            $ph = implode(',', array_fill(0, count($accessible), '?'));
            $team_clause = "AND t.id IN ({$ph})";
            $params = array_map('intval', $accessible);
        }
    }

    // upcoming = due today or later; overdue = strictly before today.
    $date_clause = ($scope === 'overdue') ? "AND t.next_date < CURDATE()" : "AND t.next_date >= CURDATE()";

    $db = db_open();
    $stmt = $db->prepare("
        SELECT t.id AS test_id, t.name, t.framework_control_id AS control_id, t.next_date
        FROM framework_control_tests t
        INNER JOIN framework_controls fc ON t.framework_control_id = fc.id AND fc.deleted = 0
        WHERE t.next_date IS NOT NULL
          AND t.next_date != '0000-00-00'
          AND t.retired_at IS NULL
          {$date_clause}
          {$team_clause}
        ORDER BY t.next_date ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        // Midnight-to-midnight day count (test_days_until(), includes/compliance_grid.php,
        // reachable via reporting.php's require chain) — NOT a raw now()-vs-midnight diff,
        // which would misreport a test due today as -1 and due tomorrow as 0.
        // The query above guarantees next_date is non-null and != '0000-00-00',
        // so this always returns an int here.
        $days = test_days_until($r['next_date']);
        $items[] = [
            'type'       => 'test',
            'test_id'    => (int) $r['test_id'],
            'name'       => (string) ($r['name'] ?? ''),
            'control_id' => (int) $r['control_id'],
            'due_date'   => (string) $r['next_date'],
            'days'       => $days,
        ];
    }

    return ['ok' => true, 'items' => $items, 'meta' => ['count' => count($items), 'limit' => $limit]];
}

/**
 * MCP tool: list_frameworks(limit?). Active frameworks (status = 1) so an
 * agent can walk the catalog. name is try_decrypt()'d to match
 * get_context('framework', id). Not team-scoped; in addition to the
 * ai_context_access gate at the MCP entry point, this tool re-enforces the
 * 'governance' domain permission (parity with get_context('framework', id)).
 */
function mcp_tool_list_frameworks(int $limit = 100): array
{
    // Domain gate — parity with get_context('framework', id), which enforces
    // the permission get_context enforces for this type via
    // ai_context_type_permission(). Discovery must be no more permissive than fetch.
    if (!check_permission(ai_context_type_permission('framework'))) {
        return ['ok' => false, 'error' => 'forbidden'];
    }

    $limit = ai_mcp_clamp_limit($limit, 100, 100);

    $db = db_open();
    $stmt = $db->prepare("
        SELECT value AS id, name, status
        FROM frameworks
        WHERE status = 1
        ORDER BY value ASC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'type'   => 'framework',
            'id'     => (int) $r['id'],
            'name'   => (string) try_decrypt($r['name']),
            'status' => (int) $r['status'],
        ];
    }

    return ['ok' => true, 'items' => $items, 'meta' => ['count' => count($items), 'limit' => $limit]];
}

/**
 * MCP tool: list_controls(framework_id, limit?, offset?). The controls mapped
 * to a framework, paged, so an agent can systematically walk coverage.
 * short_name is try_decrypt()'d to match get_context('control', id). Not
 * team-scoped; in addition to the ai_context_access gate at the MCP entry
 * point, this tool re-enforces the 'governance' domain permission (parity
 * with get_context('control', id)).
 */
function mcp_tool_list_controls(int $framework_id, int $limit = 25, int $offset = 0): array
{
    if ($framework_id <= 0) {
        return ['ok' => false, 'error' => 'invalid_framework_id'];
    }
    // Domain gate — parity with get_context('control', id), which enforces
    // the permission get_context enforces for this type via
    // ai_context_type_permission(). Discovery must be no more permissive than fetch.
    if (!check_permission(ai_context_type_permission('control'))) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    $limit = ai_mcp_clamp_limit($limit);
    $offset = max(0, $offset);

    $db = db_open();
    $stmt = $db->prepare("
        SELECT DISTINCT fc.id, fc.short_name, fc.family, fc.control_maturity, fc.control_status
        FROM framework_controls fc
        INNER JOIN framework_control_mappings fcm ON fcm.control_id = fc.id
        WHERE fc.deleted = 0 AND fcm.framework = :framework_id
        ORDER BY fc.short_name ASC, fc.id ASC
        LIMIT " . (int) $limit . " OFFSET " . (int) $offset . "
    ");
    $stmt->bindValue(':framework_id', $framework_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'type'           => 'control',
            'id'             => (int) $r['id'],
            'short_name'     => (string) try_decrypt($r['short_name']),
            'family'         => is_null($r['family']) ? null : (int) $r['family'],
            'maturity'       => is_null($r['control_maturity']) ? null : (int) $r['control_maturity'],
            'control_status' => is_null($r['control_status']) ? null : (int) $r['control_status'],
        ];
    }

    return ['ok' => true, 'items' => $items, 'meta' => ['count' => count($items), 'limit' => $limit, 'offset' => $offset]];
}

/**
 * MCP tool: list_highest_risks(limit?). Open risks ranked by calculated
 * risk, so an agent can find the risks that matter and feed each RAW id to
 * get_context('risk', id).
 *
 * Team-scoped: reuses home_risk_separation_sql() (includes/reporting.php) —
 * the same L4 fragment the Home dashboard and get_context('risk', id) apply —
 * so discovery is symmetric with fetch. subject is try_decrypt()'d to match
 * the plaintext get_context returns. Returns the RAW risks.id (NOT
 * convert_to_risk_id()) because that is what get_context('risk', id) keys on.
 */
function mcp_tool_list_highest_risks(int $limit = 25): array
{
    // Domain gate — parity with get_context('risk', id), which enforces
    // the permission get_context enforces for this type via
    // ai_context_type_permission(). Discovery must be no more permissive than fetch.
    if (!check_permission(ai_context_type_permission('risk'))) {
        return ['ok' => false, 'error' => 'forbidden'];
    }
    $limit = ai_mcp_clamp_limit($limit);

    [$sep_from, $sep_where] = home_risk_separation_sql();

    $db = db_open();
    $stmt = $db->prepare("
        SELECT rsk.id, rsk.subject, rsk.status, rsk.owner, scoring.calculated_risk
        FROM risks rsk
        INNER JOIN risk_scoring scoring ON rsk.id = scoring.id
        {$sep_from}
        WHERE rsk.status != 'Closed'
        {$sep_where}
        GROUP BY rsk.id
        ORDER BY scoring.calculated_risk DESC
        LIMIT " . (int) $limit . "
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'type'            => 'risk',
            'id'              => (int) $r['id'], // RAW db id — what get_context('risk', id) expects
            'subject'         => (string) try_decrypt($r['subject']),
            'calculated_risk' => is_null($r['calculated_risk']) ? null : (float) $r['calculated_risk'],
            'status'          => (string) ($r['status'] ?? ''),
            'owner'           => is_null($r['owner']) ? null : (int) $r['owner'],
        ];
    }

    return ['ok' => true, 'items' => $items, 'meta' => ['count' => count($items), 'limit' => $limit]];
}
