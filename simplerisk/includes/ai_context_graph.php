<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/functions.php'));
require_once(realpath(__DIR__ . '/reporting.php'));
require_once(realpath(__DIR__ . '/artificial_intelligence.php'));
require_once(realpath(__DIR__ . '/permissions.php'));
require_once(realpath(__DIR__ . '/entity_graph.php'));
// get_asset_value_by_id() (ai_context_enrich_fetch()'s asset case) is defined
// here. Declared explicitly per CLAUDE.md's function-reachability rule even
// though assets.php is also reachable transitively today.
require_once(realpath(__DIR__ . '/assets.php'));

/**
 * Bounded-scan cap for the Connectivity Explorer launchpad's Level-1 tile
 * counts (ai_context_browse_counts()). Counting exactly would mean
 * materializing every id of a type on every page load, and doing so through
 * L4 (Team Separation) rather than a raw SELECT COUNT(*) -- since a count is
 * itself a disclosure of scale. Instead the scan stops at this many
 * candidate ids and the caller is told the count is capped, matching the
 * bounded-scan cap ai_context_search_entities() already applies when
 * matching encrypted-name types.
 */
define('AI_CONTEXT_BROWSE_COUNT_CAP', 2000);

/**
 * Per-request ceiling on how many UNDERLYING rows either launchpad endpoint
 * may examine before it gives up and reports the answer as incomplete.
 *
 * SECURITY: this is the bound that makes "keep reading until enough L4-VISIBLE
 * rows have accumulated" safe. Without it, a caller who can see nothing could
 * force a full-table scan on every request simply by filtering for a substring
 * that only matches records they are not allowed to see. Hitting the bound is
 * reported honestly (has_more / capped true) rather than being disguised as
 * the end of the list.
 */
define('AI_CONTEXT_BROWSE_SCAN_BUDGET', 2000);

/**
 * How many underlying rows are read per fetch inside that budget. Small
 * enough that a caller who can see everything pays one round trip for a
 * 50-row page, large enough that a caller who can see almost nothing burns
 * the whole budget in a handful of queries rather than hundreds.
 */
define('AI_CONTEXT_BROWSE_SCAN_CHUNK', 500);

/**
 * Upper bound on the `skip` component of a browse continuation cursor. The
 * cursor is caller-supplied and therefore caller-forgeable (see
 * ai_context_browse_decode_cursor()); clamping it stops a forged cursor
 * turning into an arbitrarily expensive SQL OFFSET.
 */
define('AI_CONTEXT_BROWSE_SKIP_CAP', 100000);

/**
 * Sentinel returned by ai_context_type_permission() for a node type that has
 * no entry in its map. It is intentionally not a grantable permission key —
 * no row in `permissions` can ever carry this name, so check_permission() and
 * the L3 $permSet lookup both deny it. Returning a real permission name for an
 * unregistered type (this previously defaulted to 'governance') fails OPEN: a
 * type added to the graph but forgotten here would quietly become readable by
 * everyone holding that permission.
 */
define('AI_CONTEXT_UNREGISTERED_TYPE_PERMISSION', '__unregistered_node_type__');

/**
 * The entity types the context graph exposes, each mapped to the domain
 * permission that gates it (L2/L3) and its base table.
 */
function ai_context_node_types(): array
{
    return [
        'risk'      => ['permission' => 'riskmanagement', 'table' => 'risks',             'prefix' => 'risk_id_'],
        'asset'     => ['permission' => 'asset',          'table' => 'assets',            'prefix' => 'asset_id_'],
        'framework' => ['permission' => 'governance',     'table' => 'frameworks',        'prefix' => 'framework_id_'],
        'control'   => ['permission' => 'governance',     'table' => 'framework_controls','prefix' => 'control_id_'],
        'test'      => ['permission' => 'compliance',     'table' => 'framework_control_tests', 'prefix' => 'test_id_'],
        'audit'     => ['permission' => 'compliance',     'table' => 'framework_control_test_audits', 'prefix' => 'audit_id_'],
        'document'  => ['permission' => 'governance',     'table' => 'documents',         'prefix' => 'document_id_'],
        'exception'       => ['permission' => 'view_exception', 'table' => 'document_exceptions',           'prefix' => 'exception_id_'],
        'assessment'      => ['permission' => 'assessments',    'table' => 'questionnaire_tracking',        'prefix' => 'assessment_id_'],
        'incident'        => ['permission' => 'im_incidents',   'table' => 'incident_management_incidents', 'prefix' => 'incident_id_'],
        'risk_catalog'    => ['permission' => 'riskmanagement', 'table' => 'risk_catalog',   'prefix' => 'risk_catalog_id_'],
        'threat_catalog'  => ['permission' => 'riskmanagement', 'table' => 'threat_catalog', 'prefix' => 'threat_catalog_id_'],
        // `table` is empty because no single table backs a vulnerability:
        // five per-platform vulnmgmt_<platform>_vulnerabilities families do,
        // each created only when that scanner is configured. See
        // vulnmgmt_graph_active_platforms() in entity_graph.php.
        'vulnerability'   => ['permission' => 'vm_vulnerabilities', 'table' => '',           'prefix' => 'vulnerability_id_'],
        'context_profile' => ['permission' => 'any',             'table' => '',                              'prefix' => 'context_profile_'],
    ];
}

/**
 * The domain permission that gates a node type (L2/L3). test_result follows
 * compliance (same as test). self_assessment_result follows assessments
 * (same as the assessment focal type) — the core SCF Self-Assessment
 * feature reuses the "assessments" permission though it is not gated behind
 * the Assessments Extra.
 *
 * An unregistered type returns AI_CONTEXT_UNREGISTERED_TYPE_PERMISSION, which
 * is not a grantable permission, so every consumer denies it: check_permission()
 * finds no matching $_SESSION key, and the L3 filter's $permSet only ever
 * contains real domain permissions. This is deliberately fail-closed — with a
 * 'governance' default, a node type added to the graph but not to this map
 * silently became readable by anyone holding governance, with no error raised
 * and nothing to grep for.
 */
function ai_context_type_permission(string $type): string
{
    $map = [
        'risk' => 'riskmanagement', 'asset' => 'asset',
        'framework' => 'governance', 'control' => 'governance', 'document' => 'governance',
        'test' => 'compliance', 'test_result' => 'compliance', 'audit' => 'compliance',
        'exception' => 'view_exception', 'assessment' => 'assessments',
        'self_assessment_result' => 'assessments',
        'incident' => 'im_incidents', 'context_profile' => 'any',
        'risk_catalog' => 'riskmanagement', 'threat_catalog' => 'riskmanagement',
        'vulnerability' => 'vm_vulnerabilities',
    ];

    if (!isset($map[$type])) {
        // Deduplicated per type per request: the L3 filter calls this once per
        // node, so an unregistered type on a large graph would otherwise emit
        // one identical warning per row.
        static $warned = [];
        if (!isset($warned[$type])) {
            $warned[$type] = true;
            write_debug_log("AI context graph node type '{$type}' has no entry in ai_context_type_permission(); denying access to it. Add the type to the map.", "warning");
        }
        return AI_CONTEXT_UNREGISTERED_TYPE_PERMISSION;
    }

    return $map[$type];
}

/**
 * The subset of graph domain permissions the current session holds.
 */
function ai_context_visible_permissions(): array
{
    $out = [];
    foreach (['riskmanagement', 'asset', 'governance', 'compliance', 'view_exception',
              'assessments', 'im_incidents', 'vm_vulnerabilities'] as $perm) {
        if (check_permission($perm)) {
            $out[] = $perm;
        }
    }
    return $out;
}

/**
 * L2 gate for a type whose permission is the 'any' sentinel (context_profile
 * today): the caller may read it if they are an admin OR hold at least one
 * graph domain permission. Fails closed — an unauthenticated/no-permission
 * caller (empty ai_context_visible_permissions()) is refused.
 */
function ai_context_caller_can_read_any_domain(): bool
{
    return is_admin() || !empty(ai_context_visible_permissions());
}

/**
 * The Extra-backed graph types, mapped from their advertised meta slug to
 * the activation-check function that gates them.
 *
 * This is intentionally forward-looking: the walkers today don't yet cross
 * from a core focal into an Extra-backed neighbor (only an assessment/
 * incident *focal* is gated, via ai_context_entity_name()), but a consuming
 * agent still needs a way to learn, from any single bundle's meta block,
 * whether the assessment/incident domains are unavailable in this
 * environment at all — so it doesn't ask a follow-up question the API can
 * never answer regardless of focal type.
 */
function ai_context_extra_activation_map(): array
{
    return [
        'assessments'              => 'assessments_extra',
        'incident_management'      => 'incident_management_extra',
        // NOTE: for the vulnerability type an ACTIVE Extra still does not
        // imply a queryable domain — the Extra creates its platform tables
        // only when a scanner is configured. meta.inactive_extras therefore
        // reports the Extra's flag; vulnmgmt_graph_active_platforms() is what
        // actually authorizes a query.
        'vulnerability_management' => 'vulnmgmt_extra',
    ];
}

/**
 * The slugs of Extra-backed graph domains whose Extra is inactive in this
 * environment, for meta.inactive_extras.
 */
function ai_context_inactive_extras(): array
{
    $inactive = [];
    foreach (ai_context_extra_activation_map() as $slug => $fn) {
        if (!$fn()) {
            $inactive[] = $slug;
        }
    }
    return $inactive;
}

/**
 * Drop nodes whose domain permission the caller lacks, then drop edges that
 * reference a removed node. $perms is the caller's held permission set.
 */
function ai_context_filter_by_domain(array $nodes, array $edges, array $perms): array
{
    $permSet = array_flip($perms);
    $keptNodeIds = [];
    $keptNodes = [];
    foreach ($nodes as $n) {
        $typePerm = ai_context_type_permission($n['type']);
        // The 'any' sentinel (context_profile) is not itself a permission
        // name, so it can never be a member of $permSet: a node type gated
        // on 'any' is kept when the caller holds AT LEAST ONE graph domain
        // permission OR is an admin — matching the endpoint L2 gate
        // (ai_context_caller_can_read_any_domain()) so an admin who happens
        // to hold zero domain permissions still sees the profile instead of
        // passing L2 and then being silently dropped here.
        $kept = ($typePerm === 'any') ? (!empty($perms) || is_admin()) : isset($permSet[$typePerm]);
        if ($kept) {
            $keptNodes[] = $n;
            $keptNodeIds[$n['node_id']] = true;
        }
    }
    $keptEdges = [];
    foreach ($edges as $e) {
        if (isset($keptNodeIds[$e['from']]) && isset($keptNodeIds[$e['to']])) {
            $keptEdges[] = $e;
        }
    }
    return ['nodes' => $keptNodes, 'edges' => $keptEdges];
}

/**
 * L4: reduce nodes to the records the current user may actually see, reusing
 * the Team Separation Extra's per-entity SQL fragments. When team separation
 * is inactive this is a no-op (domain permission already gated the type).
 *
 * SECURITY: without this, the graph would surface records the caller cannot
 * see in the UI (aggregation-based authorization bypass). The scoped-id query
 * is the same visibility logic the normal list endpoints use.
 */
function ai_context_filter_by_record_visibility(array $nodes, array $edges, ?string $focalNodeId = null): array
{
    if (!team_separation_extra()) {
        return ai_context_drop_orphan_test_results($nodes, $edges, $focalNodeId);
    }
    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

    // Collect ids per scoped type.
    //
    // test_result is in this list even though framework_control_test_results
    // carries no team column of its own: it INHERITS its scoping from the
    // audit that produced it (see ai_context_visible_ids_for_type()'s
    // test_result case). It cannot be left to the orphan-drop below, because
    // the drop is edge-based and the test_result <-> audit edge is not
    // present at depth 1: walking out from a test emits results through
    // get_results_connectivity_for_test(), which links them to the TEST, and
    // only the audit walker emits the audit edge. Without this, a caller who
    // may see a test but not one of its audits was correctly denied the audit
    // node and still handed that audit's result, verdict and date.
    $idsByType = ['risk' => [], 'asset' => [], 'document' => [], 'test' => [],
                  'incident' => [], 'audit' => [], 'test_result' => []];
    foreach ($nodes as $n) {
        if (isset($idsByType[$n['type']])) {
            $idsByType[$n['type']][] = $n['id'];
        }
    }

    $visible = [];
    foreach ($idsByType as $type => $ids) {
        $visible[$type] = ai_context_visible_ids_for_type($type, $ids);
    }

    $keptNodeIds = [];
    $keptNodes = [];
    foreach ($nodes as $n) {
        if (isset($idsByType[$n['type']])) {
            if (in_array($n['id'], $visible[$n['type']], true)) {
                $keptNodes[] = $n;
                $keptNodeIds[$n['node_id']] = true;
            }
            // else: not visible → dropped
        } else {
            // unscoped type (framework/control/exception/…) — domain-gated already
            $keptNodes[] = $n;
            $keptNodeIds[$n['node_id']] = true;
        }
    }
    $keptEdges = [];
    foreach ($edges as $e) {
        if (isset($keptNodeIds[$e['from']]) && isset($keptNodeIds[$e['to']])) {
            $keptEdges[] = $e;
        }
    }
    // Orphan-drop evaluation:
    //   - test_result -> test: existing, kept via the wrapper below. It is
    //     now only HALF the gate: the result must also have an L4-visible
    //     owning AUDIT, which is enforced above via $idsByType rather than
    //     here, because the audit edge does not exist at depth 1.
    //   - incident: L4-scoped directly above (it's in $idsByType), not a
    //     child that only survives via a scoped parent -> no orphan-drop.
    //   - exception / assessment: NOT record-scoped (absent from $idsByType
    //     above), and every neighbor they can reach through -- risks,
    //     documents, controls, frameworks -- is either L4-scoped
    //     independently (graph_risk_edge_rows() strips no-access risks
    //     before this function ever runs; documents go through
    //     $idsByType) or not record-scoped itself (control/framework). An
    //     exception/assessment now also appears as a NEIGHBOR (walked from
    //     control/document/framework/risk focals), not only as a focal, but
    //     its own table carries no team column, so there is nothing for an
    //     orphan-drop to test it against -> no orphan-drop needed either way.
    //   - self_assessment_result -> control: control is NOT
    //     record-scoped (not in $idsByType above, same as framework), so its
    //     self_assessment_result children are not team-scoped either —
    //     visibility is inherited entirely from the parent control, which is
    //     already domain-gated (governance) at L2/L3. The orphan-drop still
    //     applies defensively (chained in the wrapper below) in case a future
    //     change scopes control at L4.
    //   - vulnerability -> asset OR risk: a vulnerability is NOT
    //     directly record-scoped (deliberately absent from $idsByType above —
    //     the vulnmgmt platform tables carry no team columns), so its entire
    //     L4 visibility is INHERITED from its asset and risk parents, both of
    //     which ARE scoped above. That makes the orphan-drop load-bearing
    //     rather than defensive here: without it, a vulnerability found on an
    //     off-team asset and triaged into an off-team risk would survive both
    //     parents being dropped and leak. It takes a LIST of parent types so a
    //     vulnerability the caller can legitimately reach through ONE of the
    //     two is not hidden — and $focalNodeId is exempt entirely, since a
    //     focal the caller named and was authorized for is not a "child"
    //     whose visibility is inherited from a parent.
    return ai_context_drop_orphan_test_results($keptNodes, $keptEdges, $focalNodeId);
}

/**
 * Generalized orphan-drop: a $childType node is only meaningful — and only
 * visible — through a surviving edge to a node of one of $parentTypes. After
 * L4 drops out-of-team parents (and prunes their edges), any $childType node
 * left with no surviving edge to ANY declared parent type is orphaned and
 * must be dropped; otherwise it would leak data belonging to a record the
 * caller is not allowed to see. Returns the filtered {nodes, edges}.
 *
 * $parentTypes is a LIST because a vulnerability hangs off an asset OR a
 * risk: dropping it when only one parent survived would hide a vulnerability
 * the caller is legitimately entitled to see through the other. A
 * single-element list reproduces the original one-parent behavior exactly.
 *
 * $exemptNodeId is the FOCAL node's id, which is never orphan-dropped. The
 * orphan-drop exists to stop a CHILD leaking when its parent is invisible;
 * the focal is not a child in that sense. It was named explicitly by the
 * caller, it already passed the L2 focal-type domain gate at the endpoint,
 * and — for the only child type that can BE a focal, `vulnerability` — there
 * is no L4 record scoping of its own to inherit, because the vulnmgmt
 * platform tables carry no team columns. Without the exemption a
 * vulnerability with no visible asset AND no visible risk (an untriaged
 * finding on an unmapped scanner asset, or any finding seen by a caller
 * holding vm_vulnerabilities but not asset/riskmanagement) would 404 on a
 * record that plainly exists.
 *
 * The exemption is provably inert for the two pre-existing pairs:
 * test_result and self_assessment_result are neighbour-only types, absent
 * from ai_context_node_types(), so no focal node id can ever match one. The
 * security property for NEIGHBOURS is unchanged — only the single focal id is
 * skipped, never any other node of the same type.
 */
function ai_context_drop_orphaned(array $nodes, array $edges, string $childType,
                                  array $parentTypes, ?string $exemptNodeId = null): array
{
    $parentTypeSet = array_flip($parentTypes);

    // node_ids of surviving parent-type nodes.
    $parentNodeIds = [];
    foreach ($nodes as $n) {
        if (isset($parentTypeSet[$n['type']])) {
            $parentNodeIds[$n['node_id']] = true;
        }
    }
    // For each child-type node, does a surviving edge connect it to a parent node?
    $childHasParent = [];
    foreach ($edges as $e) {
        if (isset($parentNodeIds[$e['from']])) { $childHasParent[$e['to']] = true; }
        if (isset($parentNodeIds[$e['to']]))   { $childHasParent[$e['from']] = true; }
    }
    $keptNodes = [];
    $keptNodeIds = [];
    foreach ($nodes as $n) {
        if ($n['type'] === $childType
            && $n['node_id'] !== $exemptNodeId
            && empty($childHasParent[$n['node_id']])) {
            continue; // orphaned child -> drop (the focal is never dropped)
        }
        $keptNodes[] = $n;
        $keptNodeIds[$n['node_id']] = true;
    }
    $keptEdges = [];
    foreach ($edges as $e) {
        if (isset($keptNodeIds[$e['from']]) && isset($keptNodeIds[$e['to']])) {
            $keptEdges[] = $e;
        }
    }
    return ['nodes' => $keptNodes, 'edges' => $keptEdges];
}

/**
 * Thin wrapper kept for existing callers/tests: a test_result is
 * only meaningful through its parent test. See ai_context_drop_orphaned().
 * Also chains the self_assessment_result -> control orphan-drop: a
 * self_assessment_result is only meaningful through its parent
 * control. Both child/parent pairs share this single wrapper because both
 * call sites in ai_context_filter_by_record_visibility() need the same
 * post-L4 orphan cleanup.
 *
 * A third pair is chained here too: a vulnerability is only meaningful — and
 * only visible — through its asset or its risk, so it is dropped only when
 * BOTH parents were removed by L4.
 *
 * $focalNodeId, when given, is exempt from every drop below — see
 * ai_context_drop_orphaned() for why the focal is not a "child". It is
 * threaded through unchanged rather than special-cased per pair so the
 * exemption cannot drift between them.
 */
function ai_context_drop_orphan_test_results(array $nodes, array $edges, ?string $focalNodeId = null): array
{
    $dropped = ai_context_drop_orphaned($nodes, $edges, 'test_result', ['test'], $focalNodeId);
    $dropped = ai_context_drop_orphaned($dropped['nodes'], $dropped['edges'],
                                        'self_assessment_result', ['control'], $focalNodeId);
    // A vulnerability is visible through its asset OR its risk; it is dropped
    // only when BOTH parents were removed by L4.
    return ai_context_drop_orphaned($dropped['nodes'], $dropped['edges'],
                                    'vulnerability', ['asset', 'risk'], $focalNodeId);
}

/**
 * Return the subset of $ids of the given type that the current user may see,
 * by running an existence query that appends the separation fragment. Caller
 * has already confirmed team_separation_extra() and required the extra.
 */
function ai_context_visible_ids_for_type(string $type, array $ids): array
{
    if (empty($ids)) {
        return [];
    }
    // Fail closed: no authenticated user context ⇒ no record-scoped visibility.
    if (empty($_SESSION['uid'])) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $db = db_open();
    $out = [];

    switch ($type) {
        case 'risk':
            // Mirror the risk catalog join: risk_to_team + additional stakeholder.
            $frag = get_user_teams_query('b', false, true); // returns "AND (...)"
            $sql = "SELECT DISTINCT b.id
                    FROM risks b
                    LEFT JOIN risk_to_team rtt ON rtt.risk_id = b.id
                    LEFT JOIN risk_to_additional_stakeholder rtas ON rtas.risk_id = b.id
                    WHERE b.id IN ($place) $frag";
            break;
        case 'asset':
            $frag = get_user_teams_query_for_assets('a', false, true);
            $sql = "SELECT DISTINCT a.id FROM assets a WHERE a.id IN ($place) $frag";
            break;
        case 'document':
            $frag = get_user_teams_query_for_documents('d', false, true);
            $sql = "SELECT DISTINCT d.id FROM documents d WHERE d.id IN ($place) $frag";
            break;
        case 'test':
            // get_user_teams_query_for_tests() hardcodes fc.control_owner in its
            // allow_control_owner_to_see_test_and_audit branch regardless of the
            // $alias argument, so framework_controls must be joined as fc (per
            // the function's own doc comment) or that branch throws an unknown
            // column error when the setting is enabled.
            $frag = get_user_teams_query_for_tests('fct', false, true);
            $sql = "SELECT DISTINCT fct.id
                    FROM framework_control_tests fct
                    LEFT JOIN framework_controls fc ON fc.id = fct.framework_control_id
                    WHERE fct.id IN ($place) $frag";
            break;
        case 'incident':
            // incident_management_incidents only exists when the Incident
            // Management Extra is active; incident node ids only ever land in
            // the graph via the incident walkers, which are themselves gated
            // on incident_management_extra(), so this case is only reached
            // in practice when the Extra is active. Belt-and-suspenders per
            // CLAUDE.md's Core/Extra DB boundary rule: guard explicitly here
            // too, so a future caller of this function can never trigger a
            // query against an Extra table that doesn't exist.
            if (!incident_management_extra()) {
                db_close($db);
                return [];
            }
            // get_user_teams_query_for_incidents() takes an $alias and
            // internally references "{$alias}id" (i.e. "i.id"), so the table
            // must be aliased as i for the fragment's i.id to resolve.
            $frag = get_user_teams_query_for_incidents('i', false, true); // returns "AND (...)"
            $sql = "SELECT DISTINCT i.id FROM incident_management_incidents i WHERE i.id IN ($place) $frag";
            break;
        case 'audit':
            // Audits are scoped by get_user_teams_query_for_tests_and_audits(),
            // the same helper functions.php's Active Audits / Past Audits /
            // Dynamic Audit Report datatable views apply (aliased "a", see
            // functions.php's get_datatable_data() dispatch on those views).
            // It is settings-driven and role-based — admin, or
            // allow_everyone_to_see_test_and_audit, OR control-owner match, OR
            // tester match, OR stakeholder FIND_IN_SET, OR (only when
            // allow_team_members_to_see_test_and_audit is enabled) team
            // membership via its own items_to_teams EXISTS subquery. It is NOT
            // a plain "team membership or unassigned" check: an audit with no
            // team assigned is invisible unless one of the other OR-branches
            // grants it, and a caller on a foreign team can still see the
            // audit via control-owner/tester/stakeholder/admin.
            //
            // The helper hardcodes "fc.control_owner" and "a.id" internally
            // regardless of the $alias argument (same caveat as the sibling
            // get_user_teams_query_for_tests('fct', ...) case above), so the
            // audits table must be aliased "a" and framework_controls "fc";
            // framework_control_tests must be aliased "fct" for
            // fct.additional_stakeholders. Both joins are LEFT so a row with
            // no matching control/test still gets evaluated (a null column
            // just makes that OR-branch false, not a query error).
            $frag = get_user_teams_query_for_tests_and_audits('a', false, true);
            $sql = "SELECT DISTINCT a.id
                    FROM framework_control_test_audits a
                    LEFT JOIN framework_controls fc ON fc.id = a.framework_control_id
                    LEFT JOIN framework_control_tests fct ON fct.id = a.test_id
                    WHERE a.id IN ($place) $frag";
            break;
        case 'test_result':
            // framework_control_test_results has no team column of its own. A
            // result is produced BY an audit (fctr.test_audit_id) and carries
            // that audit's verdict and date, so its record-level visibility is
            // the audit's — the exact same
            // get_user_teams_query_for_tests_and_audits() fragment and the
            // exact same alias contract as the 'audit' case above (the helper
            // hardcodes "a.id" and "fc.control_owner" regardless of the $alias
            // argument, and fct is needed for fct.additional_stakeholders).
            //
            // The join to framework_control_test_audits is INNER, which is
            // what makes this fail closed: a result whose test_audit_id is 0,
            // or points at an audit row that no longer exists, inherits
            // nothing and is therefore not visible. That costs no reachable
            // data — get_results_connectivity_for_test() reaches results only
            // THROUGH an audit row, so a result with no audit can never
            // legitimately arrive with a surviving test edge anyway.
            $frag = get_user_teams_query_for_tests_and_audits('a', false, true);
            $sql = "SELECT DISTINCT fctr.id
                    FROM framework_control_test_results fctr
                    JOIN framework_control_test_audits a ON a.id = fctr.test_audit_id
                    LEFT JOIN framework_controls fc ON fc.id = a.framework_control_id
                    LEFT JOIN framework_control_tests fct ON fct.id = a.test_id
                    WHERE fctr.id IN ($place) $frag";
            break;
        default:
            db_close($db);
            return $ids;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $vid) {
        $out[] = (int)$vid;
    }
    db_close($db);
    return $out;
}

/**
 * Depth is bounded to [1,2] to prevent unbounded graph walks.
 */
function ai_context_clamp_depth(int $depth): int
{
    if ($depth < 1) return 1;
    if ($depth > 2) return 2;
    return $depth;
}

/**
 * Assemble a normalized, permission-scoped context bundle for one entity.
 * Returns null for an unknown type. (Nodes/edges/enrichment/scoping are
 * layered in by later tasks.)
 */
function ai_get_context(string $type, int $id, int $depth = 1, array $opts = []): ?array
{
    $types = ai_context_node_types();
    if (!isset($types[$type])) {
        return null;
    }
    // Only the vulnerability type reads $opts['platform']; see
    // ai_context_focal_node(). It is validated against the hardcoded
    // allow-list there and again inside every walker.
    $platform = $opts['platform'] ?? null;
    $focal = ai_context_focal_node($type, $id, is_string($platform) ? $platform : null);
    if ($focal === null) {
        return null;
    }
    $depth = ai_context_clamp_depth($depth);
    $associations = ai_context_associations($type, $id, is_string($platform) ? $platform : null);
    $normalized   = ai_context_normalize_associations($type, $associations);

    // Depth 2: walk one more hop out from each first-order neighbor, then
    // merge. Neighbors are deduped by node_id in the normalizer, but that
    // caps DUPLICATES, not FAN-OUT: every distinct first-hop neighbor gets
    // its own ai_context_associations() call, and every one of those
    // orchestrators is itself N+1 (see e.g. connectivity_visualizer_
    // associations_control()'s per-test/per-risk sub-queries). The per-type
    // clustering in ai_context_apply_clustering() below only trims what is
    // RETURNED after the walk already ran — it does nothing to bound the
    // walk itself. On a real framework (500-1000 controls) an uncapped walk
    // issues tens of thousands of queries for one request.
    //
    // Cap the WALK, not just the output: rank each first-hop type the same
    // way the final response will rank it, and only expand the same
    // per-type cap the caller would end up seeing anyway. Fields are
    // enriched here (redundantly with the later ai_context_enrich() call on
    // the full node set) specifically so the ranking signal — maturity gap,
    // risk score, etc. — is real rather than falling back to insertion
    // order; the extra enrichment query is bounded by the number of first-
    // hop types, not by the number of first-hop nodes.
    $perms = $opts['permissions'] ?? ai_context_visible_permissions();

    if ($depth >= 2) {
        // Filter the first-hop set BEFORE ranking and capping it. The final
        // domain + L4 passes further down would remove these nodes from the
        // RESPONSE anyway, but running the walk selection over the unfiltered
        // set let a record the caller cannot see consume one of the per-type
        // expansion slots: a restricted caller silently got a shorter and
        // differently-ranked second hop than they were entitled to, and the
        // ranking that chose the expansion set was influenced by records they
        // cannot see.
        //
        // This does not widen the walk — $walkable is a subset of the nodes
        // that were ranked before, so the number of ai_context_associations()
        // calls the second hop issues can only go down. That matters: capping
        // the walk (rather than only the output) is what stopped an uncapped
        // depth-2 request on a real framework from issuing tens of thousands
        // of queries, and nothing here relaxes that cap. The added cost is one
        // domain filter (pure PHP) plus the L4 pass's handful of id queries,
        // and it buys a cheaper ai_context_enrich() over the smaller set.
        //
        // $normalized itself is deliberately left alone: it still carries
        // every first-hop node, and the authoritative filters below still
        // decide what is returned. Only the choice of what to EXPAND moves.
        $firstHop = ai_context_filter_by_domain(
            array_merge([$focal], $normalized['nodes']), $normalized['edges'], $perms);
        $firstHop = ai_context_filter_by_record_visibility(
            $firstHop['nodes'], $firstHop['edges'], $focal['node_id']);
        $walkable = [];
        foreach ($firstHop['nodes'] as $n) {
            if ($n['node_id'] !== $focal['node_id']) {
                $walkable[] = $n;
            }
        }

        $rankableFirstHop = ai_context_enrich($walkable);
        $byType = [];
        foreach ($rankableFirstHop as $n) {
            $byType[$n['type']][] = $n;
        }
        $expandType  = $opts['expand'] ?? null;
        // ai_context_walk_expand_limit() (small, fixed), NOT
        // ai_context_max_expand_limit() (500, output-only) -- see that
        // function's docblock for why reusing the output ceiling here would
        // let ?expand=&limit= raise the number of WALKED nodes, each of
        // which costs a full ai_context_associations() call.
        $expandLimit = isset($opts['limit'])
            ? max(1, min((int)$opts['limit'], ai_context_walk_expand_limit()))
            : ai_context_cluster_threshold();

        $toWalk = [];
        foreach ($byType as $walkType => $group) {
            // 'framework' keeps only the small, FIXED ai_context_cluster_
            // threshold() cap here -- it is never eligible for the caller-
            // raised $expandLimit above, even when the caller's own
            // ?expand= names it. connectivity_visualizer_associations_
            // framework() fetches every control mapped to the framework with
            // no LIMIT and then issues three queries per control (plus one
            // per resulting test/risk); walking it is what
            // test_depth_two_walks_a_second_hop_the_first_hop_cannot_reach()
            // (AiContextGraphTest) exists to prove still happens -- a
            // sibling control reachable only through the shared framework
            // must still surface at depth 2 -- so it cannot be excluded
            // outright, only kept off the caller-controlled amplification
            // path. At most 25 frameworks are ever walked to a second hop
            // regardless of ?limit=, versus up to 500 before this fix.
            $limit  = ($expandType !== null && $expandType === $walkType && $walkType !== 'framework')
                ? $expandLimit : ai_context_cluster_threshold();
            $ranked = ai_context_rank_neighbors($group, $walkType);
            foreach (array_slice($ranked, 0, $limit) as $n) {
                $toWalk[] = $n;
            }
        }

        $secondHop = ['nodes' => [], 'edges' => []];
        foreach ($toWalk as $n) {
            $assoc = ai_context_associations($n['type'], $n['id'], $n['platform'] ?? null);
            $norm  = ai_context_normalize_associations($n['type'], $assoc);
            $secondHop['nodes'] = array_merge($secondHop['nodes'], $norm['nodes']);
            $secondHop['edges'] = array_merge($secondHop['edges'], $norm['edges']);
        }
        // $normalized still carries EVERY first-hop node (the cap above only
        // limits which of them get walked for a second hop, not which of
        // them are shown) — only the second-hop expansion is bounded here.
        $normalized = ai_context_merge_normalized($normalized, $secondHop, $focal['node_id']);
    }

    // Enrich focal and neighbor nodes with real field data.
    $allNodes = array_merge([$focal], $normalized['nodes']);
    $allNodes = ai_context_enrich($allNodes);
    $focal = array_shift($allNodes);
    // $allNodes are now the enriched neighbors. $focal is never null here —
    // $allNodes was built as array_merge([$focal], …), so it always held at
    // least the focal element — but assert it so the array accesses below are
    // provably non-nullable (and the bundle fails closed if that invariant
    // is ever broken by a refactor).
    if (!is_array($focal)) {
        return null;
    }

    // Apply the domain-permission filter. The focal node anchors every edge
    // (each neighbor edge connects back to it), so include it in the filter's
    // node set — otherwise all edges to the focal are dropped — then split it
    // back out of the neighbor list. The focal already passed the L2 focal-type
    // gate at the endpoint, so it survives the filter.
    // $perms was resolved above the depth-2 block so the pre-walk filter and
    // this authoritative one can never disagree about the caller's scope.
    $filtered = ai_context_filter_by_domain(array_merge([$focal], $allNodes), $normalized['edges'], $perms);

    // Apply the L4 record-level visibility filter (Team Separation). Same
    // rationale as above: keep the focal node in the filtered set so its
    // edges survive, then split it back out afterward.
    // The focal is passed through so the orphan-drop never removes it: it is
    // record-scoped in its own right above (when its type is in $idsByType),
    // but it must not be dropped merely for having lost every parent-type
    // neighbour. See ai_context_drop_orphaned()'s $exemptNodeId.
    $scoped = ai_context_filter_by_record_visibility($filtered['nodes'], $filtered['edges'], $focal['node_id']);
    $focalVisible = false;
    $neighbors = [];
    foreach ($scoped['nodes'] as $n) {
        if ($n['node_id'] === $focal['node_id']) {
            $focalVisible = true;
        } else {
            $neighbors[] = $n;
        }
    }
    // L4 applies to the focal too: if the caller cannot see the record they
    // asked for, the whole bundle is unauthorized — return null rather than
    // leaking the focal's fields (aggregation-based authz bypass otherwise).
    if (!$focalVisible) {
        return null;
    }

    // Cluster + rank per type instead of an unordered global slice. This runs
    // AFTER enrichment (ranking reads the enriched fields) and AFTER the L4
    // record-visibility filter above (counts must reflect only what the
    // caller may see, or `total` would leak the existence of records the
    // caller cannot see).
    $clustered = ai_context_apply_clustering(
        $neighbors,
        $scoped['edges'],
        $focal['node_id'],
        ['expand' => $opts['expand'] ?? null, 'limit' => $opts['limit'] ?? null]
    );
    $neighbors = $clustered['nodes'];
    $edges     = $clustered['edges'];

    // Retained for backward compatibility with consumers reading meta.truncated.
    $truncated = false;
    foreach ($clustered['summary'] as $s) {
        if ($s['clustered']) { $truncated = true; break; }
    }

    return [
        'focal' => $focal,
        'nodes' => $neighbors,
        'edges' => $edges,
        'profile' => ai_context_profile_snapshot(),
        'meta' => [
            'depth'             => $depth,
            'generated_at'      => $opts['now'] ?? null,
            'scope'             => ['permissions' => array_values($perms)],
            'truncated'         => $truncated,
            'neighbor_summary'  => $clustered['summary'],
            'inactive_extras'   => ai_context_inactive_extras(),
        ],
    ];
}

/**
 * Build the focal node record (type, id, node_id, display name, enriched
 * fields). Enrichment fields are filled in separately; for now name only.
 *
 * $platform is only meaningful for the vulnerability type, whose node id is
 * composite (vulnerability_id_<platform>_<id>) because each vulnmgmt platform
 * table has its own auto-increment id. Every other type ignores it.
 */
function ai_context_focal_node(string $type, int $id, ?string $platform = null): ?array
{
    $types = ai_context_node_types();
    if (!isset($types[$type])) {
        return null;
    }
    // vulnerability is only addressable WITH an allow-listed platform. Both
    // rejections below happen before any table name is built.
    if ($type === 'vulnerability') {
        if ($platform === null || !in_array($platform, vulnmgmt_graph_platforms(), true) || $id <= 0) {
            return null;
        }
        $name = ai_context_entity_name_vulnerability($platform, $id);
        if ($name === null) {
            return null;
        }
        return [
            'type'     => 'vulnerability',
            'id'       => $id,
            'platform' => $platform,
            'node_id'  => "vulnerability_id_{$platform}_{$id}",
            'name'     => $name,
            'fields'   => [],
        ];
    }
    // context_profile is a SINGLETON, org-level focal (no underlying record),
    // so id 0 is valid and there is nothing to look up: synthesize the focal
    // directly rather than going through ai_context_entity_name()'s id lookup.
    if ($type === 'context_profile') {
        // Intentional: the singleton always resolves to id 0 regardless of
        // the $id argument passed in. There is no per-id record to look up,
        // so a caller requesting .../context_profile/999 gets the same
        // (only) profile as .../context_profile/0 rather than a 404 — this
        // is a silent alias, not a bug, and leaks nothing since there is
        // only ever one profile.
        return [
            'type'    => 'context_profile',
            'id'      => 0,
            'node_id' => $types['context_profile']['prefix'] . '0',
            'name'    => ai_context_entity_name('context_profile', 0),
            'fields'  => [],
        ];
    }
    if ($id <= 0) {
        return null;
    }
    $name = ai_context_entity_name($type, $id);
    if ($name === null) {
        return null;
    }
    return [
        'type'    => $type,
        'id'      => $id,
        'node_id' => $types[$type]['prefix'] . $id,
        'name'    => $name,
        'fields'  => [],
    ];
}

/**
 * Return the display name of an entity, or null if it does not exist.
 * Used to validate the focal id and label the focal node.
 */
function ai_context_entity_name(string $type, int $id): ?string
{
    // context_profile is synthetic (org-level config, no backing table): no
    // DB query, just the display label. The label is user-facing, so it
    // comes from a language lookup. It carries no interpolated params, so the
    // plain _lang() lookup is the right escaping contract here.
    if ($type === 'context_profile') {
        return _lang('OrganizationContextProfile', []);
    }

    // Extra-backed types are handled first, gated on their activation flag,
    // since their tables don't exist (and must never be queried) when the
    // owning Extra is inactive.
    if ($type === 'assessment') {
        if (!assessments_extra()) {
            return null;
        }
        $db = db_open();
        // questionnaire_tracking has NO name column; the human label is the
        // parent questionnaire's name.
        $stmt = $db->prepare("SELECT q.name AS name
                              FROM questionnaire_tracking qt
                              JOIN questionnaires q ON q.id = qt.questionnaire_id
                              WHERE qt.id = :id LIMIT 1;");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        db_close($db);
        return $row === false ? null : try_decrypt($row['name']);
    }

    if ($type === 'incident') {
        if (!incident_management_extra()) {
            return null;
        }
        $db = db_open();
        // incident_management_incidents PK is `id`; summary is the display name.
        $stmt = $db->prepare("SELECT summary AS name
                              FROM incident_management_incidents
                              WHERE id = :id LIMIT 1;");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        db_close($db);
        return $row === false ? null : $row['name'];
    }

    $col = [
        'risk'      => ['risks',                'subject',        'id',    null],
        'asset'     => ['assets',               'name',           'id',    null],
        'framework' => ['frameworks',           'name',           'value', null],
        // deleted = 0 / retired_at IS NULL: same soft-delete/retirement
        // filter as ai_context_search_columns() -- without it a deleted
        // control or retired test still resolves as a valid focal name
        // (and 200s as a focal) instead of 404ing like any other id the
        // application no longer considers to exist.
        'control'   => ['framework_controls',   'short_name',     'id',    'deleted = 0'],
        'test'      => ['framework_control_tests','name',         'id',    'retired_at IS NULL'],
        'audit'     => ['framework_control_test_audits', 'name', 'id',     null],
        'document'  => ['documents',            'document_name',  'id',   null],
        'exception' => ['document_exceptions',  'name',           'value', null],
        'risk_catalog'   => ['risk_catalog',   'name', 'id', null],
        'threat_catalog' => ['threat_catalog', 'name', 'id', null],
    ];
    if (!isset($col[$type])) {
        return null;
    }
    [$table, $column, $pk, $extraWhere] = $col[$type];
    $db = db_open();
    // Table/column/pk are from a hardcoded allow-list above, never user input.
    $extraCond = $extraWhere !== null ? " AND ({$extraWhere})" : '';
    $stmt = $db->prepare("SELECT `{$column}` AS name FROM `{$table}` WHERE `{$pk}` = :id{$extraCond} LIMIT 1;");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);
    if ($row === false) {
        return null;
    }
    return try_decrypt($row['name']);
}

/**
 * Display name for one vulnerability. Separate from ai_context_entity_name()
 * because the table is platform-dependent, the label column differs per
 * platform (tenable has no `title` — its label is `plugin_name`), and the
 * label is an encrypted BLOB.
 *
 * SECURITY: $platform is validated against vulnmgmt_graph_active_platforms()
 * — the hardcoded allow-list intersected with table_exists() — before it
 * reaches the interpolated table name, and the label column comes from the
 * hardcoded vulnmgmt_graph_platform_schema() map. Neither can be caller-
 * controlled. Returns null when the Extra is off, the platform is unknown, or
 * that platform's tables were never created.
 *
 * `hidden = 0` also excludes a dismissed finding, so a focal that a triager
 * dismissed resolves to no name at all rather than surfacing as a live node.
 */
function ai_context_entity_name_vulnerability(string $platform, int $id): ?string
{
    if (!in_array($platform, vulnmgmt_graph_active_platforms(), true)) {
        return null;
    }
    if ($id <= 0) {
        return null;
    }
    $label = vulnmgmt_graph_platform_schema()[$platform]['label_column'];
    $db = db_open();
    $stmt = $db->prepare("SELECT `{$label}` AS label FROM `vulnmgmt_{$platform}_vulnerabilities`
                          WHERE id = :id AND hidden = 0 LIMIT 1;");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    db_close($db);
    if ($row === false) {
        return null;
    }
    $title = try_decrypt($row['label']);
    return ($title === null || $title === '') ? ($platform . ' #' . $id) : $title;
}

/**
 * Map a graphology node_id (e.g. "framework_id_3", "risk_id_7") back to a
 * (type, id) pair. Returns null for an unrecognized prefix. Note the
 * risk prefix embeds the RAW db id, which is what we key on.
 *
 * The vulnerability type additionally returns a 'platform' key.
 */
function ai_context_parse_node_id(string $node_id): ?array
{
    // vulnerability is the ONE type whose node id is not <prefix><digits>:
    // each vulnmgmt platform table has its own auto-increment id, so the
    // platform slug must disambiguate. Form: vulnerability_id_<platform>_<id>.
    //
    // SECURITY: the slug is matched against the hardcoded allow-list in
    // vulnmgmt_graph_platforms() so it can never be attacker-controlled input
    // flowing toward an interpolated table name. Anything that does not match
    // an allow-listed slug followed by digits returns null.
    //
    // The allow-list contains overlapping slugs ('insightvm' is a prefix of
    // 'insightvmcloud'), so the LONGEST match must win — matching 'insightvm'
    // first against "insightvmcloud_17" would leave a non-numeric remainder
    // and reject a perfectly valid id.
    if (str_starts_with($node_id, 'vulnerability_id_')) {
        $rest = substr($node_id, strlen('vulnerability_id_'));
        $platforms = vulnmgmt_graph_platforms();
        usort($platforms, fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($platforms as $platform) {
            $needle = $platform . '_';
            if (str_starts_with($rest, $needle)) {
                $digits = substr($rest, strlen($needle));
                if ($digits !== '' && ctype_digit($digits)) {
                    return ['type' => 'vulnerability', 'id' => (int)$digits, 'platform' => $platform];
                }
            }
        }
        return null; // unknown platform or non-numeric id
    }

    $map = [
        'risk_catalog_id_'   => 'risk_catalog',
        'threat_catalog_id_' => 'threat_catalog',
        'risk_id_'        => 'risk',
        'asset_id_'       => 'asset',
        'framework_id_'   => 'framework',
        'control_id_'     => 'control',
        'test_id_'        => 'test',
        'document_id_'    => 'document',
        'test_result_id_' => 'test_result',
        'audit_id_'       => 'audit',
        'exception_id_'   => 'exception',
        'assessment_id_'  => 'assessment',
        'self_assessment_result_id_' => 'self_assessment_result',
        'incident_id_'    => 'incident',
    ];
    foreach ($map as $prefix => $type) {
        if (str_starts_with($node_id, $prefix)) {
            $rest = substr($node_id, strlen($prefix));
            if (ctype_digit($rest)) {
                return ['type' => $type, 'id' => (int)$rest];
            }
        }
    }
    return null;
}

/**
 * The declared orientation for every edge pair in the graph: which type is
 * the "child" (named first) and which is the "parent".
 *
 * Keyed on the two type names sorted and joined with '|', so a lookup is
 * direction-independent. This exists because walkers now run in BOTH
 * directions for most pairs — without it, one physical edge would carry two
 * different rel names depending on which end the caller started from, and an
 * API/MCP consumer would see them as two distinct relationships.
 */
function ai_context_edge_direction_map(): array
{
    $pairs = [
        ['control', 'framework'],
        ['control', 'document'],
        ['control', 'asset'],
        ['test', 'control'],
        ['test_result', 'test'],
        ['test_result', 'risk'],
        ['test_result', 'audit'],
        ['audit', 'test'],
        ['audit', 'control'],
        ['exception', 'control'],
        ['exception', 'document'],
        ['exception', 'framework'],
        ['exception', 'risk'],
        ['document', 'framework'],
        ['risk', 'asset'],
        ['risk', 'control'],
        ['risk_catalog', 'risk'],
        ['threat_catalog', 'risk'],
        ['vulnerability', 'asset'],
        ['vulnerability', 'risk'],
        ['assessment', 'control'],
        ['assessment', 'framework'],
        ['incident', 'asset'],
        ['incident', 'risk'],
        ['self_assessment_result', 'control'],
    ];

    $map = [];
    foreach ($pairs as [$child, $parent]) {
        $key = ai_context_edge_pair_key($child, $parent);
        // First declaration wins, so an accidental duplicate above cannot
        // silently flip an established label.
        if (!isset($map[$key])) {
            $map[$key] = [$child, $parent];
        }
    }
    return $map;
}

/**
 * Direction-independent key for a type pair.
 */
function ai_context_edge_pair_key(string $a, string $b): string
{
    $pair = [$a, $b];
    sort($pair);
    return $pair[0] . '|' . $pair[1];
}

/**
 * A stable, human-readable relationship label for an edge between two node
 * types, e.g. control_of_framework.
 *
 * Direction-stable: the same physical relationship always yields the same
 * label regardless of which end the walk started from. An undeclared pair
 * falls back to alphabetical order so the result is still deterministic.
 */
function ai_context_edge_rel(string $fromType, string $toType): string
{
    $map = ai_context_edge_direction_map();
    $key = ai_context_edge_pair_key($fromType, $toType);

    if (isset($map[$key])) {
        [$child, $parent] = $map[$key];
        return $child . '_of_' . $parent;
    }

    $pair = [$fromType, $toType];
    sort($pair);
    return $pair[0] . '_of_' . $pair[1];
}

/**
 * Turn the connectivity_visualizer_associations_* bucket output into a
 * normalized node list (deduped by node_id) and typed edge list (deduped
 * undirected). Node names carry raw UTF-8; escaping happens at the JSON sink.
 */
function ai_context_normalize_associations(string $focalType, array $associations): array
{
    $nodes = [];
    $edges = [];
    $seenNodes = [];
    $seenEdges = [];

    foreach ($associations as $bucket) {
        foreach ($bucket as $el) {
            $node = ai_context_parse_node_id($el['node_id']);
            $conn = ai_context_parse_node_id($el['connected_node_id']);
            if ($node === null || $conn === null) {
                continue;
            }
            if (!isset($seenNodes[$el['node_id']])) {
                $seenNodes[$el['node_id']] = true;
                $nodeEntry = [
                    'type'    => $node['type'],
                    'id'      => $node['id'],
                    'node_id' => $el['node_id'],
                    'name'    => $el['node_name'],
                    'fields'  => [],
                ];
                // Only vulnerability node ids carry a platform. Preserving it
                // here is what lets the depth-2 walk (and the Explorer's
                // composite-id construction) address the node without
                // re-parsing its node_id.
                if (isset($node['platform'])) {
                    $nodeEntry['platform'] = $node['platform'];
                }
                $nodes[] = $nodeEntry;
            }
            // Undirected dedupe key.
            $edgeKey = ($el['node_id'] < $el['connected_node_id'])
                ? $el['node_id'] . '|' . $el['connected_node_id']
                : $el['connected_node_id'] . '|' . $el['node_id'];
            if (!isset($seenEdges[$edgeKey])) {
                $seenEdges[$edgeKey] = true;
                $edges[] = [
                    'from' => $el['node_id'],
                    'to'   => $el['connected_node_id'],
                    'rel'  => ai_context_edge_rel($node['type'], $conn['type']),
                ];
            }
        }
    }
    return ['nodes' => $nodes, 'edges' => $edges];
}

/**
 * Merge two normalized {nodes, edges} sets, deduping nodes by node_id and
 * edges undirected. $focalNodeId is excluded from the node list so the focal
 * never appears as its own neighbor after a second-hop walk returns to it.
 */
function ai_context_merge_normalized(array $a, array $b, string $focalNodeId): array
{
    $nodes = [];
    $seenN = [];
    foreach (array_merge($a['nodes'], $b['nodes']) as $n) {
        if ($n['node_id'] === $focalNodeId || isset($seenN[$n['node_id']])) {
            continue;
        }
        $seenN[$n['node_id']] = true;
        $nodes[] = $n;
    }

    $edges = [];
    $seenE = [];
    foreach (array_merge($a['edges'], $b['edges']) as $e) {
        $key = ($e['from'] < $e['to'])
            ? $e['from'] . '|' . $e['to']
            : $e['to'] . '|' . $e['from'];
        if (isset($seenE[$key])) {
            continue;
        }
        $seenE[$key] = true;
        $edges[] = $e;
    }

    return ['nodes' => $nodes, 'edges' => $edges];
}

/**
 * Dispatch to the correct connectivity_visualizer_associations_* orchestrator.
 *
 * $platform is only consumed by the vulnerability case: (type, id) alone is
 * ambiguous there because each vulnmgmt platform table has its own
 * auto-increment id, so the slug has to be threaded down from the focal.
 */
function ai_context_associations(string $type, int $id, ?string $platform = null): array
{
    switch ($type) {
        case 'risk':      return connectivity_visualizer_associations_risk($id + 1000);
        case 'asset':     return connectivity_visualizer_associations_asset($id);
        case 'framework': return connectivity_visualizer_associations_framework($id);
        case 'control':   return connectivity_visualizer_associations_control($id);
        case 'test':      return connectivity_visualizer_associations_test($id);
        case 'audit':     return connectivity_visualizer_associations_audit($id);
        case 'document':  return connectivity_visualizer_associations_document($id);
        case 'exception': return connectivity_visualizer_associations_exception($id);
        case 'risk_catalog':   return connectivity_visualizer_associations_risk_catalog($id);
        case 'threat_catalog': return connectivity_visualizer_associations_threat_catalog($id);
        case 'assessment':
            // Activation gate: questionnaire_* tables only exist when the
            // Assessments Extra is active. Every raw SQL query against them
            // lives behind connectivity_visualizer_associations_assessment(),
            // whose only caller is this gated branch.
            if (!assessments_extra()) {
                return [];
            }
            return connectivity_visualizer_associations_assessment($id);
        case 'incident':
            // Activation gate: incident_management_* tables only exist when
            // the Incident Management Extra is active. Every raw SQL query
            // against them lives behind
            // connectivity_visualizer_associations_incident(), whose only
            // caller is this gated branch.
            if (!incident_management_extra()) {
                return [];
            }
            return connectivity_visualizer_associations_incident($id);
        case 'vulnerability':
            // Activation gate + per-platform table existence are BOTH checked
            // inside connectivity_visualizer_associations_vulnerability()
            // (via vulnmgmt_graph_active_platforms()); an unknown or inactive
            // platform yields no neighbors and touches no table. A missing
            // platform is not resolvable at all — (type, id) is ambiguous
            // across the five platform tables — so bail before the walker.
            if ($platform === null) {
                return [];
            }
            return connectivity_visualizer_associations_vulnerability($platform, $id);
        case 'context_profile':
            // No neighbors: the org-context data lives in the bundle's
            // `profile` key, not as graph nodes/edges.
            return [];
        default:          return [];
    }
}

/**
 * Fill each node's `fields` with the real column data agents reason over.
 * Groups nodes by type and runs one query per type. Names/values carry raw
 * UTF-8; escaping happens at the JSON sink.
 */
function ai_context_enrich(array $nodes): array
{
    if (empty($nodes)) {
        return $nodes;
    }
    // Bucket node ids by type, remembering their position for write-back.
    $byType = [];
    foreach ($nodes as $i => $n) {
        $byType[$n['type']][$n['id']] = $i;
    }

    // Vulnerability ids are only unique WITHIN a platform, so bucketing them
    // by $n['id'] like every other type would collide across platforms (and
    // would hand ai_context_enrich_fetch() platform-ambiguous ids). Enrich
    // them separately, keyed on the composite node_id.
    $vulnPositions = [];
    foreach ($nodes as $i => $n) {
        if ($n['type'] === 'vulnerability') {
            $vulnPositions[$n['node_id']] = $i;
        }
    }
    unset($byType['vulnerability']);

    $db = db_open();
    if (!empty($vulnPositions)) {
        foreach (ai_context_enrich_vulnerabilities($db, array_keys($vulnPositions)) as $nodeId => $fields) {
            if (isset($vulnPositions[$nodeId])) {
                $nodes[$vulnPositions[$nodeId]]['fields'] = $fields;
            }
        }
    }
    foreach ($byType as $type => $idMap) {
        $ids = array_keys($idMap);
        $fieldsById = ai_context_enrich_fetch($db, $type, $ids);
        foreach ($fieldsById as $id => $fields) {
            if (isset($idMap[$id])) {
                $nodes[$idMap[$id]]['fields'] = $fields;
            }
        }
    }
    db_close($db);
    return $nodes;
}

/**
 * Enrich vulnerability nodes, grouped by platform. Returns [node_id => fields].
 *
 * The five platform tables do NOT share a schema — tenable has no `title`,
 * only qualys has `status` / `patchable` / `first_found` / `last_found` — so
 * the column list is read per platform from the hardcoded
 * vulnmgmt_graph_platform_schema() map. Absent columns come back as null so
 * the emitted `fields` shape stays stable across platforms.
 *
 * SECURITY: the platform slug comes from ai_context_parse_node_id(), which
 * only ever returns an allow-listed slug, and is then re-checked against
 * vulnmgmt_graph_active_platforms() before the table name is built. Column
 * names come from the hardcoded map. Ids are bound as placeholders.
 *
 * `hidden = 0` excludes dismissed findings, so a dismissed vulnerability that
 * somehow reached this call (e.g. a stale node id passed in directly) still
 * enriches to nothing rather than surfacing fields for a finding a triager
 * closed out.
 */
function ai_context_enrich_vulnerabilities(PDO $db, array $nodeIds): array
{
    $byPlatform = [];
    foreach ($nodeIds as $nodeId) {
        $parsed = ai_context_parse_node_id($nodeId);
        if ($parsed !== null && $parsed['type'] === 'vulnerability' && isset($parsed['platform'])) {
            $byPlatform[$parsed['platform']][] = (int)$parsed['id'];
        }
    }
    if (empty($byPlatform)) {
        return [];
    }

    $active = vulnmgmt_graph_active_platforms();
    $schema = vulnmgmt_graph_platform_schema();
    // The stable superset of field keys across every platform, so a consumer
    // never has to branch on which scanner produced the node.
    $allKeys = [];
    foreach ($schema as $spec) {
        foreach (array_keys($spec['fields']) as $k) {
            $allKeys[$k] = null;
        }
    }

    $out = [];
    foreach ($byPlatform as $platform => $ids) {
        if (!in_array($platform, $active, true) || empty($ids)) {
            continue;
        }
        $fields = $schema[$platform]['fields'];
        $select = ['id'];
        foreach ($fields as $alias => $field) {
            $select[] = "`{$field['column']}` AS `{$alias}`";
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            'SELECT ' . implode(', ', $select) .
            " FROM `vulnmgmt_{$platform}_vulnerabilities` WHERE id IN ($place) AND hidden = 0");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $row = $allKeys;
            $row['platform'] = $platform;
            foreach ($fields as $alias => $field) {
                $value = $r[$alias] ?? null;
                switch ($field['cast']) {
                    case 'int':
                        $row[$alias] = is_null($value) ? null : (int)$value;
                        break;
                    case 'decrypt':
                        // try_decrypt() passes plaintext through unchanged, so
                        // this is correct whether or not the Encrypted
                        // Database Extra is active.
                        $row[$alias] = is_null($value) ? null : try_decrypt($value);
                        break;
                    default:
                        $row[$alias] = $value;
                }
            }
            $out["vulnerability_id_{$platform}_" . (int)$r['id']] = $row;
        }
    }
    return $out;
}

/**
 * control_status has no dedicated lookup table. The governance UI resolves
 * it via a hardcoded CASE (get_framework_controls_by_filter(),
 * governance.php: "CASE t1.control_status WHEN 1 THEN 'Pass' WHEN 0 THEN
 * 'Fail' ELSE 'Not Tested' END") and display_control_status_view()'s
 * $status_text array. Reproduced here through the same 'Pass'/'Fail'/
 * 'NotTested' lang keys the UI already uses. Mirrors the CASE's ELSE branch
 * exactly: anything other than 1 or 0 -- including null/unset -- resolves to
 * "Not Tested", so this always returns a string, never null.
 */
function ai_context_control_status_label($status): string
{
    if ($status !== null && (int)$status === 1) {
        return _lang('Pass', []);
    }
    if ($status !== null && (int)$status === 0) {
        return _lang('Fail', []);
    }
    return _lang('NotTested', []);
}

/**
 * An audit's approval_state is a fixed 'none'|'pending'|'approved'|'rejected'
 * enum column (framework_control_test_audits), not DB-backed vocabulary.
 * Mirrors approvalStateLabel() in
 * js/simplerisk/pages/compliance-define-tests.js exactly: 'none' means
 * approval never applied to this run (no approvers configured), so it -- and
 * any unrecognized value -- returns null rather than a label; the raw value
 * already reads fine on its own in that case.
 */
function ai_context_approval_state_label(?string $state): ?string
{
    switch ($state) {
        case 'pending':  return _lang('Pending', []);
        case 'approved': return _lang('Approved', []);
        case 'rejected': return _lang('Rejected', []);
        default:         return null;
    }
}

/**
 * questionnaire_tracking.status has no dedicated lookup table. Mirrors
 * get_questionnaire_result_status_name() (extras/assessments/index.php)
 * through the same lang keys, reimplemented locally rather than
 * require_once'ing that ~24k-line Extra file for two small switch
 * statements (which would also re-run upgrade_assessment_extra_database() on
 * every graph enrichment call -- see its top-level activation check).
 */
function ai_context_questionnaire_status_label(?int $status): ?string
{
    switch ($status) {
        case 0: return _lang('PendingResponse', []);
        case 1: return _lang('PendingReview', []);
        case 2: return _lang('Reviewed', []);
        default: return null;
    }
}

/**
 * questionnaire_tracking.approval_status has no dedicated lookup table.
 * Mirrors get_questionnaire_result_approval_status_name()
 * (extras/assessments/index.php) -- see
 * ai_context_questionnaire_status_label()'s doc comment for why this is a
 * local reimplementation rather than a require_once of the Extra file.
 */
function ai_context_questionnaire_approval_status_label(?int $status): ?string
{
    switch ($status) {
        case 0: return _lang('PendingResponse', []);
        case 1: return _lang('PendingReview', []);
        case 2: return _lang('Approved', []);
        case 3: return _lang('Rejected', []);
        case 4: return _lang('NotApproved', []);
        default: return null;
    }
}

/**
 * Normalize an assignee/classification foreign-key field for display.
 *
 * SimpleRisk uses 0 (and, on some columns, NULL) as the "unassigned"
 * sentinel for owner/approver/tester/family-style reference columns --
 * framework_controls.control_owner, framework_controls.family, risks.owner,
 * risks.manager, document_exceptions.approver, questionnaire_tracking.approver,
 * incident_management_incidents.owner, and
 * framework_control_test_audits.tester are all populated with 0 by the
 * application when nothing has been assigned (risks.owner/manager are even
 * NOT NULL, so 0 is the ONLY way the column can express "unassigned"). The
 * enrichment join only ever nulls a label on a genuinely missing/unresolvable
 * row, so an unassigned 0 survives as a "meaningful" raw id with no label,
 * and the Inspector falls back to printing the bare 0.
 *
 * This is intentionally NOT applied to every enriched field: a maturity level
 * (control_maturity/desired_maturity, value 0 = "Not Performed") and a
 * plain enum/flag (control_status, mitigation_percent, approved, ...) use 0
 * as a real, meaningful value, not a "nothing assigned" placeholder, and must
 * keep showing it.
 *
 * A resolved 0 (a customer-defined family/user row that itself happens to use
 * value 0) is intentionally still treated as unassigned here: 0 is the
 * universal "not set" convention for these columns across the app, so
 * hiding it is consistent with every other unassigned-0 row even in the
 * (unlikely) case a lookup row also matches it.
 *
 * A non-zero id whose row was deleted/renamed away resolves to a null label
 * from the LEFT JOIN; that dangling id is nulled out too, rather than left as
 * a bare, meaningless number the Inspector would otherwise print raw.
 *
 * Returns [raw, label] -- both null when unassigned or unresolvable.
 */
function ai_context_ref_field(?int $raw, ?string $label): array
{
    if (empty($raw) || $label === null) {
        return [null, null];
    }
    return [$raw, $label];
}

/**
 * Per-type enrichment fetch. Returns [id => fields[]]. $ids are integers from
 * the graph (never user input); built into an IN() list of placeholders.
 */
function ai_context_enrich_fetch(PDO $db, string $type, array $ids): array
{
    if (empty($ids)) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $out = [];

    switch ($type) {
        case 'control':
            // Join control_maturity twice (current/desired), family, and the
            // owner's user row so every coded field carries a human label
            // alongside its raw value — one row per control, no per-id
            // follow-up query. LEFT JOINs so an unset/unresolvable id yields a
            // null label rather than dropping the row.
            $stmt = $db->prepare(
                "SELECT fc.id, fc.control_maturity, fc.desired_maturity, fc.family,
                        fc.control_owner, fc.control_status, fc.mitigation_percent,
                        cm.name AS maturity_label, dm.name AS desired_maturity_label,
                        fam.name AS family_label, owner_u.name AS owner_label
                 FROM framework_controls fc
                 LEFT JOIN control_maturity cm ON fc.control_maturity = cm.value
                 LEFT JOIN control_maturity dm ON fc.desired_maturity = dm.value
                 LEFT JOIN `family` fam ON fc.family = fam.value
                 LEFT JOIN `user` owner_u ON fc.control_owner = owner_u.value
                 WHERE fc.id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                [$family, $familyLabel] = ai_context_ref_field(
                    is_null($r['family']) ? null : (int)$r['family'], $r['family_label'] ?? null);
                [$owner, $ownerLabel] = ai_context_ref_field(
                    is_null($r['control_owner']) ? null : (int)$r['control_owner'], $r['owner_label'] ?? null);
                $out[(int)$r['id']] = [
                    'maturity'               => is_null($r['control_maturity']) ? null : (int)$r['control_maturity'],
                    'desired_maturity'       => is_null($r['desired_maturity']) ? null : (int)$r['desired_maturity'],
                    'maturity_label'         => $r['maturity_label'] ?? null,
                    'desired_maturity_label' => $r['desired_maturity_label'] ?? null,
                    'family'                 => $family,
                    'family_label'           => $familyLabel,
                    'owner'                  => $owner,
                    'owner_label'            => $ownerLabel,
                    'status'                 => is_null($r['control_status']) ? null : (int)$r['control_status'],
                    // control_status has no dedicated lookup table -- the
                    // governance UI resolves it via a hardcoded 1/0/else CASE
                    // (get_framework_controls_by_filter(), governance.php) and
                    // display_control_status_view()'s $status_text array. This
                    // mirrors that exact vocabulary through lang lookups
                    // (enum-in-code, not DB data).
                    'status_label'           => ai_context_control_status_label($r['control_status']),
                    'mitigation_percent'     => is_null($r['mitigation_percent']) ? null : (int)$r['mitigation_percent'],
                ];
            }
            break;

        case 'test':
            // desired_frequency is left RAW: it is an INT column
            // (framework_control_tests, mirrored from test_frequency on
            // creation) but no lookup table or lang-keyed vocabulary for it
            // was found anywhere in the codebase -- neither compliance.php
            // nor the Define Tests JS render it as anything but a bare
            // number/filter value. Guessing a vocabulary here would violate
            // the "verify against the real schema" rule, so it stays
            // unresolved. last_result is already a plaintext column
            // (framework_control_test_results.test_result VARCHAR, e.g.
            // "Pass"/"Fail" or a customer-configured label) -- human-readable
            // as stored, no label needed.
            $stmt = $db->prepare(
                "SELECT fct.id, fct.objective, fct.test_steps, fct.expected_results,
                        fct.desired_frequency, fct.last_date,
                        (SELECT fctr.test_result FROM framework_control_test_results fctr
                         JOIN framework_control_test_audits fcta ON fctr.test_audit_id = fcta.id
                         WHERE fcta.test_id = fct.id ORDER BY fctr.test_date DESC LIMIT 1) AS last_result,
                        (SELECT fctr.test_date FROM framework_control_test_results fctr
                         JOIN framework_control_test_audits fcta ON fctr.test_audit_id = fcta.id
                         WHERE fcta.test_id = fct.id ORDER BY fctr.test_date DESC LIMIT 1) AS last_result_date
                 FROM framework_control_tests fct WHERE fct.id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int)$r['id']] = [
                    'objective'         => try_decrypt($r['objective']),
                    'test_steps'        => try_decrypt($r['test_steps']),
                    'expected_results'  => try_decrypt($r['expected_results']),
                    'desired_frequency' => is_null($r['desired_frequency']) ? null : (int)$r['desired_frequency'],
                    'last_date'         => $r['last_date'],
                    'last_result'       => $r['last_result'],
                    'last_result_date'  => $r['last_result_date'],
                ];
            }
            break;

        case 'asset':
            $stmt = $db->prepare("SELECT id, value, verified FROM assets WHERE id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $value = is_null($r['value']) ? null : (int)$r['value'];
                $out[(int)$r['id']] = [
                    'valuation' => $value,
                    // get_asset_value_by_id() (assets.php) is the same
                    // function the asset list/detail views use to render a
                    // valuation id as a currency range (+ optional custom
                    // level name), e.g. "$0 to $1,000 (Low)". It caches the
                    // small `asset_values` table in $GLOBALS on its first
                    // call per request, so calling it once per asset row here
                    // costs at most ONE extra query for the whole batch, not
                    // one per row.
                    'valuation_label' => is_null($value) ? null : get_asset_value_by_id($value),
                    'verified'        => (int)$r['verified'],
                    // verified is a plain boolean flag, not DB-backed
                    // vocabulary -- Yes/No via the shared lang keys.
                    'verified_label'  => ((int)$r['verified'] === 1) ? _lang('Yes', []) : _lang('No', []),
                ];
            }
            break;

        case 'risk':
            $stmt = $db->prepare(
                "SELECT r.id, r.status, r.owner, r.manager, rs.calculated_risk,
                        owner_u.name AS owner_label, manager_u.name AS manager_label
                 FROM risks r
                 LEFT JOIN risk_scoring rs ON rs.id = r.id
                 LEFT JOIN `user` owner_u ON r.owner = owner_u.value
                 LEFT JOIN `user` manager_u ON r.manager = manager_u.value
                 WHERE r.id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                [$owner, $ownerLabel] = ai_context_ref_field(
                    is_null($r['owner']) ? null : (int)$r['owner'], $r['owner_label'] ?? null);
                [$manager, $managerLabel] = ai_context_ref_field(
                    is_null($r['manager']) ? null : (int)$r['manager'], $r['manager_label'] ?? null);
                $out[(int)$r['id']] = [
                    'calculated_risk' => is_null($r['calculated_risk']) ? null : (float)$r['calculated_risk'],
                    // risks.status is already a plaintext column ('Open',
                    // 'Closed', ...) -- human-readable as stored, no label
                    // needed.
                    'status'          => $r['status'],
                    'owner'           => $owner,
                    'owner_label'     => $ownerLabel,
                    'manager'         => $manager,
                    'manager_label'   => $managerLabel,
                ];
            }
            break;

        case 'exception':
            // CORE type, no activation gate. document_exceptions' PK is
            // `value`, not `id` — key the result on it accordingly.
            // document_exceptions_status resolves the status VALUE to its
            // name ("Open"/"Closed") the same way governance.php's exception
            // views do (LEFT JOIN document_exceptions_status des ON
            // des.value = de.status).
            $stmt = $db->prepare(
                "SELECT de.value, de.status, de.approved, de.approver, de.justification, de.next_review_date,
                        des.name AS status_label, approver_u.name AS approver_label
                 FROM document_exceptions de
                 LEFT JOIN document_exceptions_status des ON des.value = de.status
                 LEFT JOIN `user` approver_u ON de.approver = approver_u.value
                 WHERE de.value IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                [$approver, $approverLabel] = ai_context_ref_field(
                    is_null($r['approver']) ? null : (int)$r['approver'], $r['approver_label'] ?? null);
                $out[(int)$r['value']] = [
                    'status'           => is_null($r['status']) ? null : (int)$r['status'],
                    'status_label'     => $r['status_label'] ?? null,
                    'approved'         => (int)$r['approved'],
                    'approved_label'   => ((int)$r['approved'] === 1) ? _lang('Yes', []) : _lang('No', []),
                    'approver'         => $approver,
                    'approver_label'   => $approverLabel,
                    'justification'    => try_decrypt($r['justification']),
                    'next_review_date' => $r['next_review_date'],
                ];
            }
            break;

        case 'assessment':
            // Extra-gated: questionnaire_tracking only exists when the
            // Assessments Extra is active. Defense-in-depth alongside the
            // walker's own activation gate (see ai_context_associations()).
            if (!assessments_extra()) {
                break;
            }
            $stmt = $db->prepare(
                "SELECT qt.id, qt.status, qt.percent, qt.approver, qt.approval_status,
                        u.name AS approver_label
                 FROM questionnaire_tracking qt
                 LEFT JOIN `user` u ON qt.approver = u.value
                 WHERE qt.id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $status         = is_null($r['status']) ? null : (int)$r['status'];
                $approvalStatus = is_null($r['approval_status']) ? null : (int)$r['approval_status'];
                [$approver, $approverLabel] = ai_context_ref_field(
                    is_null($r['approver']) ? null : (int)$r['approver'], $r['approver_label'] ?? null);
                $out[(int)$r['id']] = [
                    'status'                => $status,
                    // status/approval_status have no dedicated lookup table --
                    // the vocabulary mirrors get_questionnaire_result_status_name()
                    // / get_questionnaire_result_approval_status_name()
                    // (extras/assessments/index.php), reimplemented locally via
                    // lang lookups rather than require_once'ing that ~24k-line
                    // Extra file (which would also re-run its own DB migration
                    // check on every graph enrichment call).
                    'status_label'          => ai_context_questionnaire_status_label($status),
                    'percent'               => is_null($r['percent']) ? null : (int)$r['percent'],
                    'approver'              => $approver,
                    'approver_label'        => $approverLabel,
                    'approval_status'       => $approvalStatus,
                    'approval_status_label' => ai_context_questionnaire_approval_status_label($approvalStatus),
                ];
            }
            break;

        case 'self_assessment_result':
            // CORE type, no activation gate — but table_exists() guarded
            // belt-and-suspenders per the Core/Extra boundary convention.
            // Keyed on the response-row id (the {id} embedded in
            // self_assessment_result_id_{id}), matching the walker's node_id
            // and NOT the parent control_id/self_assessment_id.
            if (!table_exists('self_assessment_responses')) {
                break;
            }
            $stmt = $db->prepare(
                "SELECT r.id, r.response, r.updated_at, sa.framework_name,
                        DATE(COALESCE(sa.completed_at, sa.created_at)) AS assessment_date
                 FROM self_assessment_responses r
                 JOIN self_assessments sa ON sa.id = r.self_assessment_id
                 WHERE r.id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int)$r['id']] = [
                    'response'         => $r['response'],
                    'assessment_date'  => $r['assessment_date'],
                    'framework_name'   => $r['framework_name'],
                ];
            }
            break;

        case 'incident':
            // Extra-gated: incident_management_incidents only exists when
            // the Incident Management Extra is active. Defense-in-depth
            // alongside the walker's own activation gate (see
            // ai_context_associations()).
            if (!incident_management_extra()) {
                break;
            }
            $hasSeverityMatrix = table_exists('incident_management_severity_matrix');
            // incident_management_incident_status carries a `status` group
            // ("Response"/"Closed") and a `substatus` ("New"/"Containment"/...).
            // The Incident Management UI renders both joined as
            // "{status}: {substatus}" (includes/display.php); mirrored here.
            // Guarded with table_exists() the same way the severity matrix
            // already is, since this table was introduced after the base
            // incidents table.
            $hasStatusTable = table_exists('incident_management_incident_status');
            $statusSelect = $hasStatusTable ? ', ims.status AS status_group, ims.substatus AS status_sub' : '';
            $statusJoin = $hasStatusTable
                ? 'LEFT JOIN `incident_management_incident_status` ims ON ims.value = i.status'
                : '';
            $stmt = $db->prepare(
                "SELECT i.id, i.status, i.score, i.owner, i.playbook,
                        owner_u.name AS owner_label{$statusSelect}
                 FROM incident_management_incidents i
                 LEFT JOIN `user` owner_u ON i.owner = owner_u.value
                 {$statusJoin}
                 WHERE i.id IN ($place)");
            $stmt->execute($ids);
            $sevStmt = $hasSeverityMatrix
                ? $db->prepare("SELECT severity FROM incident_management_severity_matrix
                                 WHERE :score BETWEEN min_score AND max_score LIMIT 1")
                : null;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $score = is_null($r['score']) ? null : (int)$r['score'];
                $severity = null;
                if ($hasSeverityMatrix && $sevStmt !== null && $score !== null) {
                    $sevStmt->bindValue(':score', $score, PDO::PARAM_INT);
                    $sevStmt->execute();
                    $sevRow = $sevStmt->fetch(PDO::FETCH_ASSOC);
                    $severity = $sevRow === false ? null : $sevRow['severity'];
                }
                $statusLabel = null;
                if ($hasStatusTable && !empty($r['status_group']) && !empty($r['status_sub'])) {
                    $statusLabel = $r['status_group'] . ': ' . $r['status_sub'];
                }
                [$owner, $ownerLabel] = ai_context_ref_field(
                    is_null($r['owner']) ? null : (int)$r['owner'], $r['owner_label'] ?? null);
                $out[(int)$r['id']] = [
                    'status'       => is_null($r['status']) ? null : (int)$r['status'],
                    'status_label' => $statusLabel,
                    'score'        => $score,
                    'owner'        => $owner,
                    'owner_label'  => $ownerLabel,
                    'playbook'     => is_null($r['playbook']) ? null : (int)$r['playbook'],
                    'severity'     => $severity,
                ];
            }
            break;

        case 'audit':
            // test_status resolves the numeric status the same way
            // get_test_audit_history() (compliance_grid.php) does (LEFT JOIN
            // test_status status_lookup ON status_lookup.value = audits.status).
            $stmt = $db->prepare(
                "SELECT a.id, a.status, a.approval_state, a.last_date, a.next_date, a.tester,
                        a.framework_control_id, a.test_id,
                        ts.name AS status_label, tester_u.name AS tester_label
                 FROM framework_control_test_audits a
                 LEFT JOIN `test_status` ts ON ts.value = a.status
                 LEFT JOIN `user` tester_u ON a.tester = tester_u.value
                 WHERE a.id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                [$tester, $testerLabel] = ai_context_ref_field(
                    is_null($r['tester']) ? null : (int)$r['tester'], $r['tester_label'] ?? null);
                $out[(int)$r['id']] = [
                    'status'               => is_null($r['status']) ? null : (int)$r['status'],
                    'status_label'         => $r['status_label'] ?? null,
                    'approval_state'       => $r['approval_state'],
                    // approval_state is a fixed 'none'|'pending'|'approved'|
                    // 'rejected' enum (not DB-backed vocabulary) -- mirrors
                    // approvalStateLabel() in compliance-define-tests.js via
                    // the same lang keys. 'none'/unrecognized -> null; the raw
                    // value already reads fine on its own.
                    'approval_state_label' => ai_context_approval_state_label($r['approval_state']),
                    'last_date'            => $r['last_date'],
                    'next_date'            => $r['next_date'],
                    'tester'               => $tester,
                    'tester_label'         => $testerLabel,
                    'control_id'           => is_null($r['framework_control_id']) ? null : (int)$r['framework_control_id'],
                    'test_id'              => is_null($r['test_id']) ? null : (int)$r['test_id'],
                ];
            }
            break;

        case 'risk_catalog':
            // `grouping` is a MySQL reserved word — must stay backticked.
            $stmt = $db->prepare(
                "SELECT id, number, `grouping`, description FROM risk_catalog WHERE id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int)$r['id']] = [
                    'number'      => $r['number'],
                    'grouping'    => is_null($r['grouping']) ? null : (int)$r['grouping'],
                    'description' => $r['description'],
                ];
            }
            break;

        case 'threat_catalog':
            // `grouping` is a MySQL reserved word — must stay backticked.
            $stmt = $db->prepare(
                "SELECT id, number, `grouping`, description FROM threat_catalog WHERE id IN ($place)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(int)$r['id']] = [
                    'number'      => $r['number'],
                    'grouping'    => is_null($r['grouping']) ? null : (int)$r['grouping'],
                    'description' => $r['description'],
                ];
            }
            break;

        // framework / document: name-only, no extra fields today.
        default:
            break;
    }
    return $out;
}

/**
 * Org-context snapshot for the bundle's `profile` key: delegates to
 * resolve_ai_context_profile() (artificial_intelligence.php), which returns
 * the full structured three-class profile — `asked` (answered
 * `ai_context_*` settings), `derived` (computed facts, e.g.
 * frameworks_in_use), and `authoritative` (e.g. risk appetite) — plus
 * `_meta` bookkeeping (last_saved / last_updated timestamps).
 *
 * ON THE GATE DIFFERENCE, decided rather than left open. This block ships in
 * every bundle to any caller who cleared the focal type's domain gate, while
 * the dedicated endpoint returning the SAME payload,
 * api_v2_ai_context_questions_get(), requires api_v2_check_admin(). That
 * looks like an inconsistency and it is worth stating which side is right,
 * because the answer is "this one":
 *
 *  - The payload is org-level GRC posture — organisation description,
 *    industry, data classes handled, regulatory exposure, implementation
 *    resourcing bands, frameworks in use, risk appetite. It identifies no
 *    record and is subject to no record-level scoping. It is exactly the
 *    grounding a GRC user (or an AI capability acting for them) needs to
 *    interpret the graph around it, which is why ai_build_control_test_prompt()
 *    reads it straight out of this bundle on behalf of whoever enqueued the
 *    job — including non-admins.
 *  - The MCP `get_org_profile` tool already publishes the same payload behind
 *    ai_context_caller_can_read_any_domain(), not an admin check, so "any
 *    domain permission" is the established gate for the DATA.
 *  - GET /ai/context is admin-gated because of what that ENDPOINT is, not
 *    what it returns: it is the read half of the admin AI-context settings
 *    screen, paired with PATCH /ai/context, which is admin-only for obvious
 *    reasons. Loosening it would widen an admin settings surface for no gain.
 *
 * So the two gates differ on purpose and neither is changed here. Tightening
 * this one would silently strip org grounding from every non-admin AI
 * generation path and contradict the MCP tool's already-reviewed gate.
 */
function ai_context_profile_snapshot(): array
{
    return resolve_ai_context_profile();
}

/**
 * Neighbor count above which a type collapses into a single cluster node
 * carrying its true count, rather than rendering individually.
 */
function ai_context_cluster_threshold(): int
{
    return 25;
}

/**
 * Hard ceiling on how many neighbors of ONE type an expand request may pull.
 * Bounds the response even when a caller asks for everything.
 *
 * OUTPUT-ONLY: this bounds ai_context_apply_clustering()'s in-memory slice
 * of an already-fetched node set -- it costs no additional query regardless
 * of its value. It must NEVER be reused to size how many
 * ai_context_associations() calls the depth-2 walk issues; see
 * ai_context_walk_expand_limit() for that cap, and its docblock for why the
 * two must stay decoupled.
 */
function ai_context_max_expand_limit(): int
{
    return 500;
}

/**
 * Hard ceiling on how many first-hop neighbors of ONE type ai_get_context()'s
 * depth>=2 block will WALK (i.e. call ai_context_associations() on) when the
 * caller raises that type's expansion via ?expand=&limit=.
 *
 * Deliberately NOT ai_context_max_expand_limit(): that constant bounds an
 * in-memory slice and costs nothing to raise, but every node this cap
 * admits triggers a full ai_context_associations() orchestrator call, and
 * several of those orchestrators are themselves N+1 over their own
 * neighbor set (e.g. connectivity_visualizer_associations_framework() in
 * entity_graph.php cascades three queries per mapped control, unbounded).
 * Reusing the 500-ceiling here let a caller raise the WALK to 500 -- up to
 * 500 full second-hop cascades in one request -- which reinstated most of
 * the amplification ai_get_context()'s depth-2 cap exists to prevent (see
 * that function's own comment on the walk cap). This ceiling is small and
 * fixed regardless of what the caller asks for; only the OUTPUT/display cap
 * is caller-raisable.
 */
function ai_context_walk_expand_limit(): int
{
    return 50;
}

/**
 * The advertised ordering label for a type, surfaced as
 * neighbor_summary.<type>.ranked_by so a consumer knows what "top 25" means.
 */
function ai_context_rank_key(string $type): string
{
    switch ($type) {
        case 'control':       return 'maturity_gap_desc';
        case 'risk':          return 'calculated_risk_desc';
        case 'test':          return 'recent_failure_first';
        case 'exception':     return 'next_review_date_asc';
        case 'vulnerability': return 'severity_desc';
        default:              return 'name_asc';
    }
}

/**
 * Deterministic, risk-weighted ordering for one type's neighbors.
 *
 * "Show me what needs attention" is the question a GRC user is actually
 * asking, and it gives an AI consumer a defensible relevance signal — unlike
 * the previous behavior, which kept whatever the walkers happened to emit
 * first.
 *
 * Sorting is STABLE (ties preserve input order) so the same bundle always
 * produces the same ordering; usort() is not stable before PHP 8.0 but is
 * guaranteed stable from 8.0 onward, and this project's floor is 8.3.
 */
function ai_context_rank_neighbors(array $neighbors, string $type): array
{
    // Sort key: lower sorts first. Missing data sorts last (PHP_INT_MAX).
    $keyOf = function (array $n) use ($type) {
        $f = $n['fields'] ?? [];
        switch ($type) {
            case 'control':
                if (!isset($f['maturity'], $f['desired_maturity'])
                    || $f['maturity'] === null || $f['desired_maturity'] === null) {
                    return [PHP_INT_MAX, ''];
                }
                // Largest gap first => negate.
                return [-((int)$f['desired_maturity'] - (int)$f['maturity']), ''];

            case 'risk':
                if (!isset($f['calculated_risk']) || $f['calculated_risk'] === null) {
                    return [PHP_INT_MAX, ''];
                }
                return [-(float)$f['calculated_risk'], ''];

            case 'vulnerability':
                // severity is NOT uniformly numeric: qualys and tenable store
                // an INT, but the Rapid7 family (insightvm, insightvmcloud,
                // nexpose) stores a VARCHAR label. (int)"Critical" is 0 for
                // EVERY label, so a plain int cast silently collapsed ranking
                // to insertion order on 3 of the 5 platforms. Route through
                // the ordinal helper instead.
                if (!isset($f['severity']) || $f['severity'] === null || $f['severity'] === '') {
                    return [PHP_INT_MAX, ''];
                }
                return [-ai_context_severity_ordinal($f['severity']), ''];

            case 'test':
                // Failures first, then most recent within each group.
                $failed = (isset($f['last_result']) && strcasecmp((string)$f['last_result'], 'Fail') === 0) ? 0 : 1;
                $date   = (string)($f['last_result_date'] ?? '');
                // Descending date => invert the string comparison via negation
                // of the group and a reversed date key.
                return [$failed, ai_context_invert_date_key($date)];

            case 'exception':
                // document_exceptions.next_review_date is DATE NOT NULL
                // DEFAULT '0000-00-00' — the MySQL zero-date sentinel, not
                // NULL, for a legacy/imported row with no real review date
                // set. Treating only '' as missing would sort that sentinel
                // FIRST (it string-compares before any real date), putting a
                // junk row at the top of "needs attention" first — exactly
                // backwards. Treat it as missing, same as ''.
                $d = (string)($f['next_review_date'] ?? '');
                $missing = ($d === '' || $d === '0000-00-00');
                return [$missing ? PHP_INT_MAX : 0, $missing ? '' : $d];

            default:
                return [0, (string)($n['name'] ?? '')];
        }
    };

    // Decorate-sort-undecorate keeps the comparator cheap and the sort stable.
    $decorated = [];
    foreach ($neighbors as $i => $n) {
        $decorated[] = ['key' => $keyOf($n), 'seq' => $i, 'node' => $n];
    }
    usort($decorated, function ($a, $b) {
        if ($a['key'][0] != $b['key'][0]) {
            return $a['key'][0] <=> $b['key'][0];
        }
        $cmp = strcasecmp((string)$a['key'][1], (string)$b['key'][1]);
        if ($cmp !== 0) {
            return $cmp;
        }
        return $a['seq'] <=> $b['seq']; // stable tiebreak
    });

    return array_column($decorated, 'node');
}

/**
 * Turn a YYYY-MM-DD date into a string that sorts ASCENDING for what is
 * semantically DESCENDING date order (most recent first), by complementing
 * each digit. An empty date sorts last.
 */
function ai_context_invert_date_key(string $date): string
{
    if ($date === '') {
        return 'zzzzzzzzzz';
    }
    $out = '';
    foreach (str_split($date) as $ch) {
        $out .= ctype_digit($ch) ? (string)(9 - (int)$ch) : $ch;
    }
    return $out;
}

/**
 * Map a raw vulnerability `severity` field value to a numeric ordinal so
 * ranking is coherent whether the source platform stores an INT (qualys,
 * tenable, per vulnmgmt_graph_platform_schema()'s 'int' cast) or a VARCHAR
 * label (the Rapid7 family — insightvm, insightvmcloud, nexpose — whose
 * schema entry uses 'raw'). A prior version did a bare (int) cast in the
 * ranking comparator: (int)"Critical" is 0 in PHP, so EVERY Rapid7 label
 * collapsed to the same rank key and ranking silently degraded to insertion
 * order on 3 of the 5 supported platforms.
 *
 * Label vocabulary — DERIVED, not guessed: "Critical" / "Severe" / "Moderate"
 * are attested by simplerisk/extras/vulnmgmt/includes/insightvmcloud.php's
 * asset-summary fields `critical_vulnerabilities` / `severe_vulnerabilities`
 * / `moderate_vulnerabilities` (~lines 570-595), which name the same Rapid7
 * severity taxonomy that populates the per-vulnerability `severity` VARCHAR
 * column on insightvm/insightvmcloud/nexpose — all three import via the
 * identical column set (vulnmgmt_graph_platform_schema()'s shared $rapid7
 * entry). No fourth tier (e.g. "Low" or "Info") is evidenced anywhere in the
 * Extra's code, so none is mapped here.
 *
 * The NUMERIC ordinal VALUES below (5/4/3) are a best-effort GUESS, NOT
 * directly derived from the code: nothing in the Extra declares an explicit
 * ceiling for Qualys's or Tenable's INT severity column via a CHECK
 * constraint or comment. The closest code evidence is qualys.php's
 * `$details['inherent_risk'] = (int)$severity * 2` (used elsewhere as a
 * cvss2_score-style value and compared against a 0-10 threshold setting),
 * which implies a severity ceiling around 5 for qualys. The three labels are
 * placed on that same small integer scale — Critical at the top (5, tying
 * with a qualys 5 rather than sitting on a disjoint always-wins range) down
 * through Severe (4) and Moderate (3) — so a label and a number are
 * genuinely comparable rather than labels unconditionally outranking every
 * number regardless of its real value. If real platform data needs a wider
 * range this mapping should be revisited; it is a judgment call, not a fact
 * read from the code.
 *
 * An unrecognized (non-numeric, non-vocabulary) label sorts BELOW every
 * numeric severity, including 0 — an unknown signal must never outrank a
 * known Critical, and returning something in the middle of the range would
 * risk exactly that for a low-numbered numeric platform.
 */
function ai_context_severity_ordinal(int|float|string $severity): int
{
    if (is_numeric($severity)) {
        return (int)$severity;
    }
    switch (strtolower(trim((string)$severity))) {
        case 'critical': return 5;
        case 'severe':   return 4;
        case 'moderate': return 3;
        default:         return -1; // unrecognized label: sorts last, not first
    }
}

/**
 * Group neighbors by type, rank each group, and keep only the top N per type,
 * reporting the TRUE totals in a summary block.
 *
 * This replaces a flat array_slice(0, 500) that kept whatever the walkers
 * emitted first. That was misleading for a UI (an unreadable hairball of an
 * arbitrary subset) and actively wrong for an AI consumer, which reasoned over
 * an unidentified fraction of the data with no signal about what was missing.
 *
 * Counts are computed AFTER L4 record-visibility filtering, so `total` never
 * reveals the existence of records the caller cannot see.
 *
 * $opts: ['expand' => <type>, 'limit' => <int>] raises the cap for ONE type.
 */
function ai_context_apply_clustering(array $neighbors, array $edges, string $focalNodeId, array $opts): array
{
    $threshold   = ai_context_cluster_threshold();
    $expandType  = $opts['expand'] ?? null;
    $expandLimit = isset($opts['limit'])
        ? max(1, min((int)$opts['limit'], ai_context_max_expand_limit()))
        : $threshold;

    // Bucket by type, preserving walker order for stable tiebreaks.
    $byType = [];
    foreach ($neighbors as $n) {
        $byType[$n['type']][] = $n;
    }

    $kept    = [];
    $summary = [];

    foreach ($byType as $type => $group) {
        $total = count($group);
        $limit = ($expandType !== null && $expandType === $type) ? $expandLimit : $threshold;

        if ($total > $limit) {
            $ranked = ai_context_rank_neighbors($group, $type);
            $group  = array_slice($ranked, 0, $limit);
        } else {
            // Rank anyway so ordering is deterministic even below threshold.
            $group = ai_context_rank_neighbors($group, $type);
        }

        // `clustered` means exactly one thing: MORE EXIST THAN WERE RETURNED.
        // It is deliberately independent of whether the caller expanded this
        // type — expanding tells you the caller drilled in, it does NOT tell
        // you whether anything is still hidden, and the UI needs the latter
        // to decide whether to draw a "+N more" affordance. Deriving it from
        // the counts also makes it structurally impossible for the flag and
        // the numbers to disagree.
        $clustered = count($group) < $total;

        $summary[$type] = [
            'total'     => $total,
            'returned'  => count($group),
            'clustered' => $clustered,
            'ranked_by' => ai_context_rank_key($type),
        ];

        foreach ($group as $n) {
            $kept[] = $n;
        }
    }

    // Prune edges to surviving nodes. The focal always survives.
    $keepIds = array_flip(array_column($kept, 'node_id'));
    $keepIds[$focalNodeId] = true;
    $keptEdges = array_values(array_filter(
        $edges,
        fn($e) => isset($keepIds[$e['from']]) && isset($keepIds[$e['to']])
    ));

    // Reachability prune: the per-type truncation above ran independently
    // per bucket, with no notion of WHICH edges tie one type's survivors to
    // another's. A node whose only surviving edge ran through some OTHER
    // node that just got clustered out of ITS OWN type's ranked top-N is
    // still sitting in $kept at this point, with zero surviving edges --
    // structurally disconnected from the focal even though it individually
    // passed its own type's cap. See ai_context_prune_unreachable()'s
    // docblock for why this is a separate walk rather than being folded
    // into ai_context_drop_orphaned().
    $pruned = ai_context_prune_unreachable($kept, $keptEdges, $focalNodeId);

    // neighbor_summary must describe $pruned, not the pre-reachability
    // $kept: `returned` is a promise about what is actually in the response
    // body, and `clustered` is DERIVED from `returned` vs. `total` (see the
    // comment above) specifically so the two can never disagree. Recompute
    // both from the final node list. `total` is deliberately left alone --
    // it already reports how many of that type were discovered as
    // candidates at this depth (computed above, after L4), and a later
    // structural check removing one for having lost its path to the focal
    // doesn't make that earlier count untrue.
    $returnedByType = [];
    foreach ($pruned['nodes'] as $n) {
        $returnedByType[$n['type']] = ($returnedByType[$n['type']] ?? 0) + 1;
    }
    foreach ($summary as $type => &$typeSummary) {
        $typeSummary['returned']  = $returnedByType[$type] ?? 0;
        $typeSummary['clustered'] = $typeSummary['returned'] < $typeSummary['total'];
    }
    unset($typeSummary);

    return ['nodes' => $pruned['nodes'], 'edges' => $pruned['edges'], 'summary' => $summary];
}

/**
 * Drop any node with no path to the focal, treating every edge as
 * UNDIRECTED: ai_context_edge_rel() canonicalizes a pair's `rel` label per
 * type-pair regardless of which end a walker started from, so the same
 * physical edge can come back with `from`/`to` pointing either way -- a
 * directed walk would incorrectly drop legitimately-connected nodes whose
 * only edge happens to run "backwards". The focal always survives (it is
 * the BFS root and is never a member of $nodes to begin with).
 *
 * This exists because ai_context_apply_clustering() truncates each node
 * type's bucket independently, then prunes edges down to the surviving
 * node set. That leaves a real gap: a node whose only edge ran to some
 * OTHER node that just got clustered out of ITS OWN type's ranked top-N
 * survives the edge-prune as a node with zero surviving edges -- floating
 * on the graph, asserting a connection to the focal that no longer exists
 * in the returned bundle. This is not limited to the depth-2 second-hop
 * walk: a framework focal's own first-hop association call already
 * cascades through EVERY one of its controls to fetch their tests/
 * documents/risks (see connectivity_visualizer_associations_framework() in
 * entity_graph.php), so a test attached only to a control that clustering
 * later drops from the top-25 strands exactly the same way at depth 1.
 * The Connectivity Explorer page has its own client-side prune-to-
 * connected-component step for this (see connectivity-visualizer.js), but
 * the MCP/AI path consumes this bundle directly with no such client-side
 * safety net -- the API itself must never emit an unreachable node.
 *
 * Deliberately NOT built on / folded into ai_context_drop_orphaned():
 * that function checks a single declared child-type -> parent-TYPE-LIST
 * edge (one hop, to a fixed set of type names) as an L4 visibility-
 * inheritance rule, evaluated BEFORE clustering ever runs, for exactly
 * three known pairs. This is a general graph-connectivity check -- ANY
 * node, ANY number of hops, to the FOCAL specifically, evaluated AFTER
 * clustering has removed nodes. Folding one into the other would either
 * turn ai_context_drop_orphaned's declarative childType/parentTypes model
 * into a full BFS engine (losing the simplicity its existing L4 call sites
 * rely on) or restrict this check to declared type pairs, which would miss
 * exactly the case this exists to catch: a stranded node of a type that
 * has no declared parent-type relationship registered at all.
 *
 * Pure in-memory graph walk over the already-capped candidate set (at most
 * a few hundred nodes/edges) -- no new DB queries, so this cannot turn into
 * the uncapped-walk problem ai_get_context()'s depth-2 cap exists to avoid.
 */
function ai_context_prune_unreachable(array $nodes, array $edges, string $focalNodeId): array
{
    $adjacency = [];
    foreach ($edges as $e) {
        $adjacency[$e['from']][] = $e['to'];
        $adjacency[$e['to']][] = $e['from'];
    }

    $reachable = [$focalNodeId => true];
    $queue = [$focalNodeId];
    while (!empty($queue)) {
        $current = array_shift($queue);
        foreach ($adjacency[$current] ?? [] as $next) {
            if (!isset($reachable[$next])) {
                $reachable[$next] = true;
                $queue[] = $next;
            }
        }
    }

    $keptNodes = array_values(array_filter(
        $nodes,
        fn($n) => isset($reachable[$n['node_id']])
    ));
    // The focal is never a member of $nodes/$keptNodes (callers split it out
    // before invoking this), but it must still anchor surviving edges -- an
    // edge from a kept first-hop neighbor straight to the focal is exactly
    // the case that proves that neighbor reachable, and dropping it here
    // would strand every direct neighbor exactly like the bug this function
    // exists to fix.
    $keptNodeIds = array_flip(array_column($keptNodes, 'node_id'));
    $keptNodeIds[$focalNodeId] = true;
    $keptEdges = array_values(array_filter(
        $edges,
        fn($e) => isset($keptNodeIds[$e['from']]) && isset($keptNodeIds[$e['to']])
    ));

    return ['nodes' => $keptNodes, 'edges' => $keptEdges];
}

/**
 * The name column to match a free-text query against, per type, for entities
 * whose display name is a PLAINTEXT column. Types whose name column is an
 * ENCRYPTED blob (risk, asset, framework) cannot be matched with SQL LIKE and
 * are handled separately inside ai_context_search_entities(). context_profile
 * is an org-level singleton with no per-id record, so there is nothing to
 * search for; it is absent from both this map and the encrypted one, so it is
 * never returned regardless of the caller's permissions.
 *
 * The 4th element is an optional extra SQL predicate (no placeholders,
 * hardcoded here -- never derived from caller input) that every consumer of
 * this map ANDs into its WHERE clause: the type's soft-delete/retirement
 * filter, so a record the application otherwise treats as removed from view
 * is not still enumerable, listable, filterable or countable through the
 * search/launchpad surfaces.
 *
 *   - 'control': framework_controls.deleted is the soft-delete flag every
 *     other governance read path filters on (delete_frameworks_controls()
 *     sets it; see e.g. governance.php:427, 512, 1678...).
 *   - 'test': framework_control_tests.retired_at is the equivalent lifecycle
 *     flag for tests (retire_test()/reactivate_test() in compliance.php) --
 *     mcp_tools.php's own test-listing tool already filters
 *     `retired_at IS NULL` for exactly this reason (see its docblock at
 *     line ~352), so the launchpad/search surfaces should not be the one
 *     path that still lists a retired test.
 *
 * 'document', 'exception', 'audit', 'risk_catalog' and 'threat_catalog' have
 * no such flag (their `status` columns, where present, are workflow/lifecycle
 * state -- e.g. an exception's Open/Closed status -- not a "this record is
 * gone" marker, and filtering on them would hide records the application
 * still considers browsable).
 */
function ai_context_search_columns(): array
{
    return [
        // type          => [table, id column, name column, extra WHERE predicate]
        'control'        => ['framework_controls',            'id',    'short_name', 'deleted = 0'],
        'test'           => ['framework_control_tests',       'id',    'name',       'retired_at IS NULL'],
        'document'       => ['documents',                     'id',    'document_name', null],
        'exception'      => ['document_exceptions',          'value', 'name',        null],
        'audit'          => ['framework_control_test_audits', 'id',    'name',       null],
        'risk_catalog'   => ['risk_catalog',                  'id',    'name',       null],
        'threat_catalog' => ['threat_catalog',                'id',    'name',       null],
    ];
}

/**
 * The name column to match a free-text query against, per type, for entities
 * whose display name is an ENCRYPTED blob -- these cannot be matched with SQL
 * LIKE (see ai_context_search_entities()'s docblock) and are instead fetched
 * via a bounded candidate scan and matched in PHP after try_decrypt().
 *
 * Lifted out of ai_context_search_entities() so the Connectivity Explorer
 * launchpad's browse/count engine (ai_context_browsable_types()) can share
 * the single literal map rather than forking a second copy of it.
 */
function ai_context_encrypted_search_columns(): array
{
    return [
        // type        => [table, id column, name column]
        'risk'      => ['risks',      'id',    'subject'],
        'asset'     => ['assets',     'id',    'name'],
        'framework' => ['frameworks', 'value', 'name'],
    ];
}

/**
 * Escape LIKE's own metacharacters ('%', '_', and the escape character
 * itself) in a caller-supplied substring BEFORE it is wrapped in the
 * leading/trailing '%' wildcards by the caller -- otherwise a literal '%' or
 * '_' typed into a search/filter box is interpreted as a wildcard (matching
 * far more than intended) and a literal '\' escapes the following character
 * unpredictably. Order matters: backslash first, so the backslashes this
 * introduces for '%'/'_' are not themselves re-escaped.
 *
 * Lifted out of ai_context_search_entities() so the launchpad's plaintext
 * browse path (ai_context_browse_page_plaintext()) can share the exact same
 * escaping rather than forking a second copy of it.
 */
function ai_context_escape_like(string $q): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
}

/**
 * L4 gate for the search endpoint: reduce $ids to the ones the current caller
 * may see, via ai_context_visible_ids_for_type().
 *
 * That function's own doc comment states its caller must have already
 * confirmed team_separation_extra() and required extras/separation/index.php
 * — for 'risk', 'asset', 'document', 'test', 'audit' and 'incident' its
 * switch runs a get_user_teams_query_for_*() fragment, and those helpers are
 * defined ONLY in that Extra file (grep confirms there is no core fallback).
 * Calling ai_context_visible_ids_for_type() directly for one of those types
 * without the gate below would fatal with "Call to undefined function" on
 * any instance that has not activated Team Separation, which is the common
 * case and true of this task's own dev/test environment. When the extra is
 * inactive this is the genuine no-op the rest of the graph's L4 documentation
 * describes: every id passes through unchanged.
 */
function ai_context_search_visible_ids(string $type, array $ids): array
{
    if (empty($ids)) {
        return [];
    }
    if (!team_separation_extra()) {
        return $ids;
    }
    require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
    return ai_context_visible_ids_for_type($type, $ids);
}

/**
 * Free-text entity lookup for the Connectivity Visualizer's focal picker.
 *
 * SECURITY: this must not become an enumeration oracle. Two gates apply, the
 * same two the bundle endpoint (ai_get_context()) uses:
 *   L2/L3 — a type is searched only when the caller holds its domain
 *           permission (ai_context_type_permission()), checked BEFORE that
 *           type's table is ever queried.
 *   L4    — rows already fetched are reduced to the ones the caller may
 *           actually see via ai_context_search_visible_ids() (a no-op when
 *           Team Separation is inactive) BEFORE they are appended to the
 *           return value — never returned first and filtered later.
 *
 * risk, asset and framework are handled separately below because their name
 * columns are encrypted blobs (risks.subject, assets.name, frameworks.name),
 * which SQL LIKE cannot match — they are fetched via a BOUNDED candidate scan
 * (the 2000 most-recently-created rows of that type) and matched in PHP after
 * try_decrypt(). That bound exists so a search can never trigger a decrypt of
 * an entire table. It is a genuine completeness gap, not merely a performance
 * safeguard: a matching row older than the 2000 most recently created rows of
 * that type will never be found, silently. Do not remove the bound and do not
 * raise it without re-deriving the cost, since it runs on every keystroke of
 * a live typeahead.
 *
 * $types, when non-empty, restricts the search to those type slugs. A slug
 * absent from both maps below (including an unrecognized one) simply never
 * matches anything -- not an error, matching the "unknown expand type is
 * ignored" convention the bundle endpoint already uses for its own 'expand'
 * parameter.
 */
function ai_context_search_entities(string $q, array $types, int $limit): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }
    $limit   = max(1, min($limit, 50));
    $perms   = array_flip(ai_context_visible_permissions());
    $wanted  = empty($types) ? null : array_flip($types);
    $results = [];

    $allowed = function (string $type) use ($perms, $wanted): bool {
        if ($wanted !== null && !isset($wanted[$type])) {
            return false;
        }
        return isset($perms[ai_context_type_permission($type)]);
    };

    $db = db_open();

    // Escape LIKE's own metacharacters in the user-supplied query BEFORE
    // wrapping it in the leading/trailing '%' wildcards below. See
    // ai_context_escape_like()'s docblock.
    $likeQ = ai_context_escape_like($q);

    // --- Plaintext name columns: matched in SQL. ---
    foreach (ai_context_search_columns() as $type => [$table, $idCol, $nameCol, $extraWhere]) {
        if (count($results) >= $limit || !$allowed($type)) {
            continue;
        }
        // Table/column names come from the hardcoded map above, never input.
        // $extraWhere (also from that hardcoded map) is the type's soft-
        // delete/retirement filter -- see ai_context_search_columns()'s
        // docblock -- ANDed in so a deleted control or retired test is not
        // still findable by name here.
        $extraCond = $extraWhere !== null ? " AND ({$extraWhere})" : '';
        $stmt = $db->prepare(
            "SELECT `{$idCol}` AS id, `{$nameCol}` AS name FROM `{$table}`
             WHERE `{$nameCol}` LIKE :q{$extraCond} ORDER BY `{$nameCol}` ASC LIMIT :lim");
        $stmt->bindValue(':q', '%' . $likeQ . '%', PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids  = array_map(fn($r) => (int)$r['id'], $rows);
        $visible = array_flip(ai_context_search_visible_ids($type, $ids));

        foreach ($rows as $r) {
            if (count($results) >= $limit) {
                break;
            }
            if (!isset($visible[(int)$r['id']])) {
                continue; // L4 dropped it
            }
            $results[] = ['type' => $type, 'id' => (int)$r['id'], 'name' => $r['name']];
        }
    }

    // --- Encrypted name columns: fetched then filtered in PHP. ---
    foreach (ai_context_encrypted_search_columns() as $type => [$table, $idCol, $nameCol]) {
        if (count($results) >= $limit || !$allowed($type)) {
            continue;
        }
        // Bounded scan: encrypted columns cannot be filtered in SQL, so cap
        // the candidate set rather than decrypting an entire table. See the
        // completeness note in this function's docblock.
        $stmt = $db->prepare(
            "SELECT `{$idCol}` AS id, `{$nameCol}` AS name FROM `{$table}`
             ORDER BY `{$idCol}` DESC LIMIT 2000");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matched = [];
        foreach ($rows as $r) {
            $name = try_decrypt($r['name']);
            if ($name !== null && $name !== '' && mb_stripos($name, $q) !== false) {
                $matched[] = ['id' => (int)$r['id'], 'name' => $name];
            }
        }

        $visible = array_flip(ai_context_search_visible_ids(
            $type, array_map(fn($m) => $m['id'], $matched)
        ));

        foreach ($matched as $m) {
            if (count($results) >= $limit) {
                break;
            }
            if (!isset($visible[$m['id']])) {
                continue;
            }
            // 'id' is the RAW risks.id -- NOT the display id (raw + 1000)
            // shown elsewhere in the SimpleRisk UI. Every other lookup this
            // graph does for a risk focal (node_id construction, the entity
            // name lookup, and GET /ai/context/risk/{id} itself, which binds
            // {id} straight against risks.id) keys on the raw id -- see
            // ai_context_parse_node_id()'s doc comment ("the risk prefix
            // embeds the RAW db id, which is what we key on"). Returning
            // raw+1000 here would make this row un-addressable by the bundle
            // endpoint it is supposed to feed.
            $results[] = ['type' => $type, 'id' => $m['id'], 'name' => $m['name']];
        }
    }

    db_close($db);
    return $results;
}

// ---------------------------------------------------------------------------
// Connectivity Explorer "canvas launchpad"
// (docs/superpowers/specs/2026-07-27-connectivity-explorer-launchpad.md):
// Level-1 tile counts and a Level-2 browsable entity list, both scoped through the SAME three
// gates the focal picker's search endpoint applies (see
// ai_context_search_entities()'s docblock) -- nothing new is invented here.
// ---------------------------------------------------------------------------

/**
 * The exact browsable type list for the launchpad: the union of
 * ai_context_search_columns() (plaintext name columns) and
 * ai_context_encrypted_search_columns() (encrypted name columns). Keyed by
 * type; each value carries the [table, id column, name column] triple plus
 * whether the name column is encrypted (and therefore must be matched in PHP
 * after try_decrypt(), never via SQL LIKE, and cannot be ORDER BY'd in SQL).
 *
 * 'assessment', 'incident', 'vulnerability' and 'context_profile' are valid
 * ai_context_node_types() focal types but are absent from BOTH source maps,
 * so they are absent here too -- they are not browsable. See this task's
 * spec ("Browsable types") for why that gap is not widened by this change.
 */
function ai_context_browsable_types(): array
{
    $out = [];
    foreach (ai_context_search_columns() as $type => [$table, $idCol, $nameCol, $extraWhere]) {
        $out[$type] = [
            'table' => $table, 'id_col' => $idCol, 'name_col' => $nameCol,
            'encrypted' => false, 'extra_where' => $extraWhere,
        ];
    }
    foreach (ai_context_encrypted_search_columns() as $type => [$table, $idCol, $nameCol]) {
        // No encrypted-name type currently carries a soft-delete/retirement
        // flag equivalent to 'control'/'test' above -- explicit null rather
        // than an absent key, so every consumer of this map can rely on
        // 'extra_where' always being set.
        $out[$type] = [
            'table' => $table, 'id_col' => $idCol, 'name_col' => $nameCol,
            'encrypted' => true, 'extra_where' => null,
        ];
    }
    return $out;
}

/**
 * Level-1 tile counts for the Connectivity Explorer launchpad: one entry per
 * browsable type the caller holds the domain permission for (L2/L3 --
 * ai_context_type_permission(), checked against ai_context_visible_
 * permissions() BEFORE that type's table is ever queried, exactly as
 * ai_context_search_entities()'s $allowed() closure does). A type the caller
 * cannot see is OMITTED from the result, never returned as a zero count --
 * see this endpoint's spec for why a zero would itself be a disclosure.
 *
 * SECURITY: every count is the number of L4-VISIBLE ids
 * (ai_context_search_visible_ids(), which carries the team_separation_extra()
 * guard), never a raw SELECT COUNT(*) -- a raw table count would disclose
 * scale ("you have 4,000 risks") to a caller whose Team Separation
 * visibility is far narrower. See ai_context_browse_count_type() for the
 * bounded-scan mechanics that make counting *visible* ids affordable.
 */
function ai_context_browse_counts(): array
{
    $perms = array_flip(ai_context_visible_permissions());
    $db = db_open();
    $out = [];
    foreach (ai_context_browsable_types() as $type => $spec) {
        // L2/L3: a type the caller holds no permission for is silently
        // omitted -- not a zero-count tile, which would still confirm the
        // type exists and is empty.
        if (!isset($perms[ai_context_type_permission($type)])) {
            continue;
        }
        [$count, $capped] = ai_context_browse_count_type($db, $type, $spec);
        $out[] = ['type' => $type, 'count' => $count, 'capped' => $capped];
    }
    db_close($db);
    return $out;
}

/**
 * Count the L4-visible ids of one browsable type.
 *
 * Only the id column is ever read here -- an encrypted-name type (risk,
 * asset, framework) needs no decrypt at all for a COUNT, unlike the search
 * endpoint's name-matching path, since there is no free-text query to match
 * against. Table/id-column come from the hardcoded ai_context_browsable_
 * types() map, never from caller input.
 *
 * Returns [count, capped].
 *
 * SECURITY -- what `capped` is allowed to mean. It reports that the returned
 * count is a FLOOR rather than an exact total, and it may only become true
 * for reasons the caller is entitled to know:
 *
 *   - more than AI_CONTEXT_BROWSE_COUNT_CAP L4-VISIBLE ids were found (the
 *     tile renders "2,000+"), or
 *   - the scan budget ran out without either that visible cap or the end of
 *     the table being reached.
 *
 * capped == false therefore means "this is an exact, complete post-L4 count".
 * The earlier single-shot implementation could not honestly claim that: it
 * set capped from the raw PRE-L4 row count, so it announced "this table holds
 * more than 2,000 rows" to a caller who might be permitted to see none of
 * them, and it under-counted every restricted caller on a large table by
 * scanning only the newest 2,000 ids and filtering afterwards. Here the scan
 * is L4-filtered as it goes and keeps going, so visible ids accumulate across
 * the window instead of being capped by it.
 *
 * The common case costs exactly what it did before -- one id read plus one L4
 * query -- because the first read takes the whole cap window at once and a
 * table no larger than that is finished in one pass. Only a table LARGER than
 * the cap, read by a caller who can see little of it, pays for the additional
 * chunked reads, and that extra work is itself bounded.
 *
 * The residual signal in the second bullet is deliberate and is NOT an
 * enumeration oracle in the sense of ai_context_browse_entities(): this
 * endpoint accepts no parameters at all, so the flag is a single fixed bit
 * per type per install that no caller-controlled input can vary, sweep, or
 * combine with a substring. It discloses at most "this install holds more
 * records of a type you already hold the domain permission for than one
 * request is willing to scan".
 */
function ai_context_browse_count_type(PDO $db, string $type, array $spec): array
{
    $cap = AI_CONTEXT_BROWSE_COUNT_CAP;
    // One cap-sized window, plus a bounded tail for the case where the table
    // is bigger than the cap but the caller can see very little of it.
    $budget = $cap + 1 + AI_CONTEXT_BROWSE_SCAN_BUDGET;
    // $spec['extra_where'] is the type's soft-delete/retirement filter (see
    // ai_context_search_columns()'s docblock) -- ANDed in so the tile count
    // reflects only records the application still treats as browsable.
    $extraCond = ($spec['extra_where'] ?? null) !== null ? " WHERE ({$spec['extra_where']})" : '';
    $sql = "SELECT `{$spec['id_col']}` AS id FROM `{$spec['table']}`
            {$extraCond} ORDER BY `{$spec['id_col']}` DESC LIMIT :lim OFFSET :off";

    $count     = 0;
    $scanned   = 0;
    $exhausted = false;
    // The first read takes cap+1 rows in a single query: that is both the
    // fast path for the overwhelming majority of installs and what lets an
    // exactly-cap-sized table be reported as an EXACT count rather than
    // "2,000+". Subsequent reads (only reached when the table is larger than
    // the cap) are chunked.
    $take = $cap + 1;
    while ($count <= $cap && !$exhausted && $scanned < $budget) {
        $take = (int)min($take, $budget - $scanned);
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':lim', $take, PDO::PARAM_INT);
        $stmt->bindValue(':off', $scanned, PDO::PARAM_INT);
        $stmt->execute();
        $ids = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        $got = count($ids);
        if ($got < $take) {
            $exhausted = true;
        }
        if ($got > 0) {
            // L4 per chunk -- never a raw table count. A no-op pass-through
            // when Team Separation is inactive (every scanned id survives).
            $count += count(ai_context_search_visible_ids($type, $ids));
        }
        $scanned += $got;
        $take = AI_CONTEXT_BROWSE_SCAN_CHUNK;
    }

    $capped = ($count > $cap) || !$exhausted;
    if ($count > $cap) {
        $count = $cap;
    }
    return [$count, $capped];
}

/**
 * Level-2 entity list for the Connectivity Explorer launchpad: one page of
 * a single browsable type, optionally filtered by substring. Returns null
 * only when $type is not browsable -- the caller (the API handler) is
 * expected to have already checked ai_context_browsable_types() for the 400
 * case and the type's domain permission (L2/L3) for the 403 case, matching
 * how api_v2_ai_context_get() divides that work for the bundle endpoint;
 * this function itself re-checks browsability defensively but does not
 * re-check the domain permission, since a caller of this *function*
 * (as opposed to the endpoint) is trusted to have already gated that.
 *
 * SECURITY: every signal this function emits -- the rows, has_more, AND the
 * continuation cursor -- is derived ONLY from rows that survived L4
 * (ai_context_search_visible_ids()). That is the whole point of the design
 * below, and it is a correction of an earlier one that published a `has_more`
 * computed from the UNDERLYING, pre-L4 row count: a caller could sweep the
 * offset at limit=1 against a substring matching only records they were not
 * allowed to see and read off the exact number of them, then extend the
 * substring a character at a time to reconstruct names -- a blind-search
 * side channel, and precisely the oracle L4 exists to prevent.
 *
 * The rules that keep it closed:
 *
 *  1. The underlying query is read in bounded chunks and L4-filtered chunk by
 *     chunk, accumulating until $limit + 1 VISIBLE rows exist (the +1 is the
 *     lookahead that decides has_more honestly), the underlying query is
 *     exhausted, or the per-request scan budget runs out.
 *  2. has_more is true only when a (limit+1)-th VISIBLE row was actually
 *     found, or when the scan budget ran out before the underlying query did
 *     -- never because invisible rows remain. Exhausting the underlying query
 *     with zero visible rows is indistinguishable from a substring that
 *     matches nothing at all, which is the property the sweep attacked.
 *  3. The continuation cursor is OPAQUE and keyset-based: it names the last
 *     VISIBLE row handed to the caller, plus a skip that only ever grows by
 *     the constant scan budget. It therefore encodes nothing the caller was
 *     not already shown. A raw integer offset would have re-opened the same
 *     oracle from the other side -- the distance between two visible rows is
 *     a count of the invisible rows between them.
 *  4. Because the cursor is caller-supplied it is treated as untrusted input:
 *     it is validated and clamped on decode, never trusted for anything but
 *     "where to resume", and forging one buys no visibility a fresh request
 *     would not also grant.
 *
 * The one residual signal, deliberately accepted: a caller who exhausts the
 * scan budget without collecting a full page learns that at least
 * AI_CONTEXT_BROWSE_SCAN_BUDGET underlying rows matched beyond their cursor.
 * That is a coarse scale bound at 2000-row granularity, not a per-record
 * oracle, and reporting it honestly is what stops a caller from mistaking a
 * bounded scan for the end of the list.
 *
 * L4 still runs AFTER the substring filter, never before it, for the reason
 * the spec gives: filtering by visibility first and then matching would make
 * the filter box an oracle in the opposite direction.
 *
 * $cursor is null (or an unparseable string, which is treated as null) for
 * the first page, and otherwise the opaque `next_cursor` from the previous
 * response. Returns ['results' => [...], 'next_cursor' => string|null,
 * 'has_more' => bool]; next_cursor is null exactly when has_more is false.
 */
function ai_context_browse_entities(string $type, string $filter, ?string $cursor, int $limit): ?array
{
    $types = ai_context_browsable_types();
    if (!isset($types[$type])) {
        return null;
    }
    $spec   = $types[$type];
    $limit  = max(1, min($limit, 100));
    $filter = trim($filter);
    $start  = ai_context_browse_decode_cursor($cursor);

    $db = db_open();
    $page = $spec['encrypted']
        ? ai_context_browse_page_encrypted($db, $type, $spec, $filter, $start, $limit)
        : ai_context_browse_page_plaintext($db, $type, $spec, $filter, $start, $limit);
    db_close($db);

    return $page;
}

/**
 * Encode a browse continuation cursor. $after is either null (start of the
 * ordered list) or [name, id] of the last VISIBLE row already handed to the
 * caller; $skip is how many further underlying rows to step over past that
 * point, and only ever takes values the caller could compute themselves
 * (multiples of AI_CONTEXT_BROWSE_SCAN_BUDGET).
 *
 * base64url of a tiny JSON payload: opaque enough that no client is tempted
 * to do arithmetic on it, and cheap enough that no server-side state is
 * needed. It is deliberately NOT signed -- see
 * ai_context_browse_decode_cursor() for why a forged cursor is harmless.
 */
function ai_context_browse_encode_cursor(?array $after, int $skip): string
{
    $payload = json_encode(['a' => $after, 's' => $skip]);
    return rtrim(strtr(base64_encode($payload === false ? '{}' : $payload), '+/', '-_'), '=');
}

/**
 * Decode a caller-supplied browse cursor into ['after' => ['name','id']|null,
 * 'skip' => int]. Anything unparseable, mistyped, or out of range degrades to
 * "start from the beginning" rather than erroring -- a malformed cursor is a
 * client bug, not an attack surface.
 *
 * SECURITY: the cursor is NOT authenticated, and does not need to be. It only
 * ever answers "where in the ordered list do I resume", and every row that
 * resume produces is still put through L4 before the caller sees it. Forging
 * a cursor is therefore equivalent to issuing a request with a different
 * filter -- it grants no visibility. The only abuse it enables is an
 * expensive SQL OFFSET, which is why `skip` is clamped to
 * AI_CONTEXT_BROWSE_SKIP_CAP here and the per-request read is bounded by
 * AI_CONTEXT_BROWSE_SCAN_BUDGET regardless.
 */
function ai_context_browse_decode_cursor(?string $cursor): array
{
    $out = ['after' => null, 'skip' => 0];
    if ($cursor === null || $cursor === '') {
        return $out;
    }
    $json = base64_decode(strtr($cursor, '-_', '+/'), true);
    if ($json === false) {
        return $out;
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $out;
    }
    if (isset($data['a']) && is_array($data['a']) && count($data['a']) === 2
        && is_string($data['a'][0] ?? null) && is_numeric($data['a'][1] ?? null)) {
        $out['after'] = ['name' => (string)$data['a'][0], 'id' => (int)$data['a'][1]];
    }
    if (isset($data['s']) && is_numeric($data['s'])) {
        $out['skip'] = max(0, min((int)$data['s'], AI_CONTEXT_BROWSE_SKIP_CAP));
    }
    return $out;
}

/**
 * Shared "read until enough VISIBLE rows" accumulator behind both browse
 * paths. $fetch(?array $after, int $offset, int $take) returns up to $take
 * underlying rows (['id'=>int,'name'=>string]) starting $offset rows past
 * $after in the type's canonical order, and returns FEWER than $take only
 * when the underlying query is exhausted -- that short read is how the loop
 * distinguishes "end of list" from "budget ran out", which is the whole basis
 * of an honest has_more.
 *
 * See ai_context_browse_entities()'s docblock for why every emitted signal is
 * computed from post-L4 rows only.
 */
function ai_context_browse_collect_visible(string $type, array $start, int $limit, callable $fetch): array
{
    $skip      = $start['skip'];
    $after     = $start['after'];
    $scanned   = 0;
    $visible   = [];
    $exhausted = false;

    while (count($visible) <= $limit && !$exhausted && $scanned < AI_CONTEXT_BROWSE_SCAN_BUDGET) {
        $take = (int)min(AI_CONTEXT_BROWSE_SCAN_CHUNK, AI_CONTEXT_BROWSE_SCAN_BUDGET - $scanned);
        $rows = array_values($fetch($after, $skip + $scanned, $take));
        $got  = count($rows);
        if ($got < $take) {
            $exhausted = true;
        }
        if ($got > 0) {
            // L4 on the chunk -- the ONLY place visibility is decided, and it
            // decides the rows, has_more and the cursor alike.
            $ok = array_flip(ai_context_search_visible_ids($type, array_column($rows, 'id')));
            foreach ($rows as $r) {
                if (isset($ok[$r['id']])) {
                    $visible[] = $r;
                }
            }
        }
        $scanned += $got;
    }

    if (count($visible) > $limit) {
        // A genuine (limit+1)-th VISIBLE row exists: that, and only that, is
        // what makes has_more true here. Resume keyed on the last row the
        // caller is actually being handed.
        $rows = array_slice($visible, 0, $limit);
        $last = $rows[$limit - 1];
        $next = ai_context_browse_encode_cursor([$last['name'], $last['id']], 0);
        $more = true;
    } else {
        // Two ways to land here: the underlying query ran out (honest end of
        // the list -- has_more false, no cursor, and indistinguishable from a
        // filter that matched nothing), or the per-request scan budget ran
        // out first (more may exist -- say so, and resume from the same place
        // plus the budget, a step size that carries no information about how
        // many rows were invisible).
        $rows = $visible;
        $more = !$exhausted;
        $next = $more
            ? ai_context_browse_encode_cursor(
                $after === null ? null : [$after['name'], $after['id']],
                $skip + AI_CONTEXT_BROWSE_SCAN_BUDGET)
            : null;
    }

    return [
        'results'     => array_map(fn($r) => ['type' => $type, 'id' => $r['id'], 'name' => $r['name']], $rows),
        'next_cursor' => $next,
        'has_more'    => $more,
    ];
}

/**
 * Read a PLAINTEXT-name browsable type: filtered (optionally) and ordered by
 * name ascending then id ascending, entirely in SQL. Table/id/name columns
 * come from the hardcoded ai_context_browsable_types() map, never from caller
 * input; the filter substring is bound as a parameter and its own LIKE
 * metacharacters are escaped via ai_context_escape_like() before wrapping.
 *
 * The id tiebreaker is load-bearing, not cosmetic: name alone is not unique,
 * and the keyset cursor below resumes on (name, id), so without a total order
 * paging could repeat or skip rows.
 *
 * Delegates the chunked read-and-L4 loop to
 * ai_context_browse_collect_visible() and returns its shape unchanged.
 */
function ai_context_browse_page_plaintext(PDO $db, string $type, array $spec, string $filter, array $start, int $limit): array
{
    $conds  = [];
    $params = [];
    if (($spec['extra_where'] ?? null) !== null) {
        // The type's soft-delete/retirement filter (see
        // ai_context_search_columns()'s docblock) -- ANDed in so a deleted
        // control or retired test is never listed, filtered to, or paged
        // through on the launchpad.
        $conds[] = "({$spec['extra_where']})";
    }
    if ($filter !== '') {
        $conds[] = "`{$spec['name_col']}` LIKE :q";
        $params[':q'] = '%' . ai_context_escape_like($filter) . '%';
    }
    if ($start['after'] !== null) {
        // Keyset resume: strictly after (name, id). Two placeholders for the
        // same value because a named parameter may not be reused when PDO's
        // prepare emulation is off.
        $conds[] = "(`{$spec['name_col']}` > :an1
                     OR (`{$spec['name_col']}` = :an2 AND `{$spec['id_col']}` > :ai))";
        $params[':an1'] = $start['after']['name'];
        $params[':an2'] = $start['after']['name'];
    }
    $where = empty($conds) ? '' : ('WHERE ' . implode(' AND ', $conds));
    $sql = "SELECT `{$spec['id_col']}` AS id, `{$spec['name_col']}` AS name FROM `{$spec['table']}`
            {$where} ORDER BY `{$spec['name_col']}` ASC, `{$spec['id_col']}` ASC LIMIT :lim OFFSET :off";

    $afterId = $start['after'] === null ? null : $start['after']['id'];
    $fetch = function (?array $after, int $offset, int $take) use ($db, $sql, $params, $afterId) {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        if ($afterId !== null) {
            $stmt->bindValue(':ai', $afterId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $take, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(
            fn($r) => ['id' => (int)$r['id'], 'name' => (string)$r['name']],
            $stmt->fetchAll(PDO::FETCH_ASSOC));
    };

    return ai_context_browse_collect_visible($type, $start, $limit, $fetch);
}

/**
 * Read an ENCRYPTED-name browsable type (risk, asset, framework). These
 * cannot be filtered, ordered, or paged in SQL, so -- exactly like
 * ai_context_search_entities()'s encrypted branch -- the candidate set is
 * pulled via a bounded scan (the AI_CONTEXT_BROWSE_COUNT_CAP most recently
 * created rows), decrypted, optionally substring-matched, then sorted here in
 * PHP. A match older than the scan window will not be found; see this task's
 * spec ("Encrypted-name types cannot be ordered or filtered...") for why that
 * bound exists.
 *
 * How that pre-existing bound interacts with the cursor: the decrypted
 * candidate list IS the whole underlying query for this path, and it is at
 * most AI_CONTEXT_BROWSE_COUNT_CAP rows -- no larger than
 * AI_CONTEXT_BROWSE_SCAN_BUDGET. The accumulator below therefore always
 * reaches the end of the candidate list within one request's budget, so an
 * encrypted-name browse never reports has_more for the budget reason, only
 * ever because a genuine (limit+1)-th VISIBLE row exists. The `skip`
 * component of the cursor consequently stays 0 on this path.
 *
 * Returns the same shape as the plaintext path, so the shared caller
 * (ai_context_browse_entities()) does not need to know which produced it.
 */
function ai_context_browse_page_encrypted(PDO $db, string $type, array $spec, string $filter, array $start, int $limit): array
{
    $stmt = $db->prepare(
        "SELECT `{$spec['id_col']}` AS id, `{$spec['name_col']}` AS name FROM `{$spec['table']}`
         ORDER BY `{$spec['id_col']}` DESC LIMIT :lim");
    $stmt->bindValue(':lim', AI_CONTEXT_BROWSE_COUNT_CAP, PDO::PARAM_INT);
    $stmt->execute();

    $candidates = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $name = try_decrypt($r['name']);
        if ($name === null || $name === '') {
            continue;
        }
        if ($filter !== '' && mb_stripos($name, $filter) === false) {
            continue;
        }
        $candidates[] = ['id' => (int)$r['id'], 'name' => $name];
    }
    // One comparator for both the sort and the keyset seek, so the resume
    // point can never disagree with the order it is seeking into.
    $cmp = fn(array $a, array $b) => strcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']);
    usort($candidates, $cmp);

    $fetch = function (?array $after, int $offset, int $take) use ($candidates, $cmp) {
        $base = 0;
        if ($after !== null) {
            foreach ($candidates as $i => $c) {
                if ($cmp($c, ['name' => $after['name'], 'id' => $after['id']]) > 0) {
                    $base = $i;
                    break;
                }
                $base = $i + 1;
            }
        }
        return array_slice($candidates, $base + $offset, $take);
    };

    return ai_context_browse_collect_visible($type, $start, $limit, $fetch);
}
