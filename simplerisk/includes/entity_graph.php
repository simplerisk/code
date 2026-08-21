<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * The entity graph's data layer: the walkers that, given a focal entity,
 * return its typed neighbors as association rows.
 *
 * NOT to be confused with includes/connectivity.php, which is HTTP fetching
 * and unrelated.
 *
 * Two consumers: includes/ai_context_graph.php (the AI/MCP context bundle)
 * and the Connectivity Visualizer report. Both require_once this file
 * directly rather than relying on transitive loading.
 */

require_once(realpath(__DIR__ . '/functions.php'));

/**
 * The canonical node-type -> color map for the entity graph. Previously these
 * hex literals were duplicated across every walker; a single map keeps the
 * report legend, the Sigma render, and the API in agreement.
 *
 * Risk nodes are the exception: their color is score-derived per row via
 * get_risk_color(), so the 'risk' entry here is only a fallback for a risk
 * with no score.
 */
function graph_node_palette(): array
{
    return [
        'risk'                   => '#c0392b',
        'asset'                  => '#f7dc6f',
        'framework'              => '#4a235a',
        'control'                => '#154360',
        'test'                   => '#2e86c1',
        'test_result'            => '#7fb3d5',
        'document'               => '#a2d9ce',
        'exception'              => '#d35400',
        'assessment'             => '#8e44ad',
        'incident'               => '#b03a2e',
        'self_assessment_result' => '#76d7c4',
        'audit'                  => '#7d6608',
        'risk_catalog'           => '#5d6d7e',
        'threat_catalog'         => '#873600',
        'vulnerability'          => '#117864',
        // Non-node-type entries, still part of the SAME single source of
        // truth: the Connectivity Explorer (connectivity-visualizer.js) used
        // to carry its own hardcoded hex for these three cases rather than
        // reading them off data-node-palette like every node color. 'default'
        // is also graph_node_color()'s own fallback below, so the two never
        // drift.
        'default'                => '#7f8c8d',
        'edge'                   => '#999999',
        'edge_cluster'           => '#bbbbbb',
    ];
}

/**
 * Palette lookup with a neutral fallback so an unmapped type renders as a
 * visible grey node rather than an invalid color attribute.
 */
function graph_node_color(string $type): string
{
    return graph_node_palette()[$type] ?? graph_node_palette()['default'];
}

/**
 * Reduce a set of association rows (as returned by graph_edge_rows() or a
 * hand-built walker in this file) to the ones whose id -- keyed by $idKey --
 * appears in $visibleIds.
 *
 * Pure array filtering: no DB access and no Team Separation awareness of its
 * own. Callers compute $visibleIds via ai_context_search_visible_ids()
 * (includes/ai_context_graph.php) BEFORE calling this, so this helper stays
 * independent of that file and of the Team Separation Extra -- consistent
 * with this file's docblock role as the entity graph's pure data layer.
 * Used by the /governance/controls/associations, /risks/associations, and
 * /compliance/tests/associations API handlers to apply the same L4 (Team
 * Separation) record filter the /ai/context/{type}/{id} bundle's
 * orchestrator applies, since those endpoints call the walkers below
 * directly rather than through the orchestrator.
 */
function graph_filter_rows_by_visible_ids(array $rows, string $idKey, array $visibleIds): array
{
    $visibleSet = array_flip(array_map('intval', $visibleIds));
    return array_values(array_filter($rows, function ($row) use ($idKey, $visibleSet) {
        return isset($row[$idKey]) && isset($visibleSet[(int)$row[$idKey]]);
    }));
}

/**
 * Bucket-level domain permission gate (L2/L3): some association buckets
 * require a DIFFERENT domain permission than the endpoint's own gate --
 * e.g. the "tests" bucket on /governance/controls/associations requires
 * "compliance" even though the endpoint itself is gated on "governance"
 * alone (governance never implies test visibility; see
 * ai_context_type_permission()'s 'test' => 'compliance' mapping). Runs
 * $fetch() only when the permission is held,
 * otherwise returns an empty bucket -- omitting the bucket rather than
 * 403-ing the whole request, since the endpoint's other buckets remain
 * valid.
 *
 * check_permission() itself is a side-effect-free session read, so this
 * wrapper is a pure decision extracted for direct testability: the
 * api_v2_* endpoint handlers that call it are otherwise awkward to unit
 * test because json_response() (includes/services.php) calls exit().
 */
function graph_bucket_if_permitted(string $permission, callable $fetch): array
{
    return check_permission($permission) ? $fetch() : [];
}

/**
 * Build association rows for a UNIFORM neighbor type — one whose node id is
 * its raw db id, whose label is a single column, and whose color is the fixed
 * palette entry for its type.
 *
 * Risk neighbors are NOT uniform (display-id offset, per-row score color,
 * bracketed label, extra team-separation strip) — use graph_risk_edge_rows().
 *
 * $sql must select $idCol and $nameCol. $params are bound positionally.
 * Names carry raw UTF-8; escaping happens at the caller's sink.
 *
 * $focalId is deliberately untyped: most focals are an int db id, but a later
 * walker (vulnerability) passes a composite string focal (e.g. "qualys_5").
 */
function graph_edge_rows(string $sql, array $params, string $neighborType,
                         string $focalType, $focalId, string $idCol, string $nameCol,
                         bool $decrypt = false): array
{
    $db = db_open();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $focalNodeId = "{$focalType}_id_{$focalId}";
    $color = graph_node_color($neighborType);
    $rows  = [];

    foreach ($fetched as $r) {
        $id   = (int)$r[$idCol];
        $name = $decrypt ? try_decrypt($r[$nameCol]) : $r[$nameCol];

        // Skip rows whose join produced a NULL neighbor (LEFT JOIN misses).
        if ($id <= 0 || $name === null || $name === '') {
            continue;
        }

        $rows[] = [
            // The focal key is part of the PUBLIC shape: these rows are
            // returned verbatim by /governance/{controls,frameworks,documents}
            // /associations. Dropping it breaks external clients.
            "{$focalType}_id"      => $focalId,
            "{$neighborType}_id"   => $id,
            "{$neighborType}_name" => $name,
            'node_id'              => "{$neighborType}_id_{$id}",
            'node_name'            => $name,
            'connected_node_id'    => $focalNodeId,
            'color'                => $color,
        ];
    }

    return $rows;
}

/**
 * Build association rows for RISK neighbors, which follow four conventions
 * the uniform builder cannot express:
 *
 *   1. `risk_id` in the row is the DISPLAY id (db id + 1000) because callers
 *      pass it straight back into get_asset_connectivity_for_risk(), which
 *      subtracts 1000 again.
 *   2. `node_id` uses the RAW db id, so graphology keys match every other
 *      risk edge in the graph.
 *   3. The label is "[displayId] subject", truncated to 50 characters.
 *   4. The color is score-derived via get_risk_color(), not the palette.
 *
 * Also applies strip_no_access_risks() when the Team Separation Extra is
 * active, matching the pre-existing behavior of get_risk_connectivity_for_control().
 *
 * $sql MUST select r.id, r.subject and rs.calculated_risk.
 *
 * $focalId is deliberately untyped: see graph_edge_rows() for why.
 */
function graph_risk_edge_rows(string $sql, array $params, string $focalType, $focalId): array
{
    $db = db_open();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    if (team_separation_extra()) {
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));
        $risks = strip_no_access_risks($risks, null, "id");
    }

    $focalNodeId = "{$focalType}_id_{$focalId}";
    $rows = [];
    foreach ($risks as $value) {
        $rawId     = (int)$value['id'];
        if ($rawId <= 0) {
            continue;
        }
        $displayId = $rawId + 1000;
        $subject   = try_decrypt($value['subject']);

        if (mb_strlen($subject) > 50) {
            $subject = "[" . $displayId . "] " . mb_substr($subject, 0, 50) . "...";
        } else {
            $subject = "[" . $displayId . "] " . $subject;
        }

        $rows[] = [
            // The focal key is part of the PUBLIC shape: these rows are
            // returned verbatim by /governance/{controls,frameworks,documents}
            // /associations. Dropping it breaks external clients.
            "{$focalType}_id"   => $focalId,
            'risk_id'           => $displayId,
            'risk_name'         => $subject,
            'node_id'           => "risk_id_{$rawId}",
            'node_name'         => $subject,
            'connected_node_id' => $focalNodeId,
            'color'             => get_risk_color($value['calculated_risk']),
        ];
    }

    return $rows;
}

/*******************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS RISK *
 *******************************************************/
function connectivity_visualizer_associations_risk($id)
{
    // Create the default empty arrays
    $asset_associations = [];
    $control_associations = [];
    $framework_associations = [];
    $test_associations = [];
    $document_associations = [];
    $test_result_associations = [];
    $exception_associations = [];
    $risk_catalog_associations = [];
    $threat_catalog_associations = [];
    $vulnerability_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $asset_associations = get_asset_connectivity_for_risk($id);
        $control_associations = get_control_connectivity_for_risk($id);
        // $id here is the DISPLAY id; the exception walker keys on the raw id.
        $exception_associations = get_exception_connectivity_for_risk($id - 1000);
        // $id is the DISPLAY id; catalog mappings key on the raw id.
        $risk_catalog_associations   = get_risk_catalog_connectivity_for_risk($id - 1000);
        $threat_catalog_associations = get_threat_catalog_connectivity_for_risk($id - 1000);
        // $id is the DISPLAY id; simplerisk_risk_id stores the raw id.
        $vulnerability_associations  = get_vulnerability_connectivity_for_risk($id - 1000);
        // Direct result->risk edges (framework_control_test_results_to_risks).
        // $id is the DISPLAY id here; the walker keys on the raw id. Placed
        // inside this guard (not after, where it previously sat) so a null
        // $id skips it instead of firing a get_test_result_connectivity_for_
        // risk(-1000) query on the no-selection path.
        $test_result_associations = get_test_result_connectivity_for_risk($id - 1000);
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        $control_id = $value['control_id'];
        $framework_associations = array_merge($framework_associations, get_framework_connectivity_for_control($control_id));
        $test_associations = array_merge($test_associations, get_test_connectivity_for_control($control_id));
        $document_associations = array_merge($document_associations, get_document_connectivity_for_control($control_id));
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        $test_id = $value['test_id'];
        $test_result_associations = array_merge($test_result_associations, get_results_connectivity_for_test($test_id));
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "controls" => $control_associations,
        "frameworks" => $framework_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
        "exceptions" => $exception_associations,
        "risk_catalogs"   => $risk_catalog_associations,
        "threat_catalogs" => $threat_catalog_associations,
        "vulnerabilities" => $vulnerability_associations,
    ];

    // Return the associations
    return $associations;
}

/********************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS ASSET *
 ********************************************************/
function connectivity_visualizer_associations_asset($id)
{
    // Create the default empty arrays
    $risk_associations = [];
    $control_associations = [];
    $framework_associations = [];
    $test_associations = [];
    $document_associations = [];
    $test_result_associations = [];
    $vulnerability_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $risk_associations = get_risk_connectivity_for_asset($id);

        // Direct asset -> control scoping (no risk in between).
        $control_associations = array_merge(
            $control_associations,
            get_control_connectivity_for_asset($id)
        );

        // Scanner findings on this asset (Vulnerability Management Extra).
        $vulnerability_associations = get_vulnerability_connectivity_for_asset($id);
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        $risk_id = $value['risk_id'];
        $control_associations = array_merge($control_associations, get_control_connectivity_for_risk($risk_id));
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        $control_id = $value['control_id'];
        $framework_associations = array_merge($framework_associations, get_framework_connectivity_for_control($control_id));
        $test_associations = array_merge($test_associations, get_test_connectivity_for_control($control_id));
        $document_associations = array_merge($document_associations, get_document_connectivity_for_control($control_id));
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        $test_id = $value['test_id'];
        $test_result_associations = array_merge($test_result_associations, get_results_connectivity_for_test($test_id));
    }

    // Merge the association arrays
    $associations = [
        "risks" => $risk_associations,
        "controls" => $control_associations,
        "frameworks" => $framework_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
        "vulnerabilities" => $vulnerability_associations,
    ];

    // Return the associations
    return $associations;
}

/************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS FRAMEWORK *
 ************************************************************/
function connectivity_visualizer_associations_framework($id)
{
    // Create the default empty arrays
    $control_associations = [];
    $test_associations = [];
    $document_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];
    $exception_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $control_associations = get_control_connectivity_for_framework($id);
        $exception_associations = get_exception_connectivity_for_framework($id);

        // Direct framework -> document mappings (no control in between).
        $document_associations = array_merge(
            $document_associations,
            get_document_connectivity_for_framework($id)
        );
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        $control_id = $value['control_id'];
        $test_associations = array_merge($test_associations, get_test_connectivity_for_control($control_id));
        $document_associations = array_merge($document_associations, get_document_connectivity_for_control($control_id));
        $risk_associations = array_merge($risk_associations, get_risk_connectivity_for_control($control_id));
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        $test_id = $value['test_id'];
        $test_result_associations = array_merge($test_result_associations, get_results_connectivity_for_test($test_id));
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        $risk_id = $value['risk_id'];
        $asset_associations = array_merge($asset_associations, get_asset_connectivity_for_risk($risk_id));
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "controls" => $control_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
        "risks" => $risk_associations,
        "exceptions" => $exception_associations,
    ];

    // Return the associations
    return $associations;
}

/**********************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS CONTROL *
 **********************************************************/
function connectivity_visualizer_associations_control($id)
{
    // Create the default empty arrays
    $framework_associations = [];
    $test_associations = [];
    $document_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];
    $self_assessment_result_associations = [];
    $exception_associations = [];
    $audit_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $framework_associations = get_framework_connectivity_for_control($id);
        $test_associations = get_test_connectivity_for_control($id);
        $document_associations = get_document_connectivity_for_control($id);
        $risk_associations = get_risk_connectivity_for_control($id);
        $self_assessment_result_associations = get_self_assessment_result_connectivity_for_control($id);
        $exception_associations = get_exception_connectivity_for_control($id);
        $audit_associations = get_audit_connectivity_for_control($id);

        // Direct control -> asset scoping (no risk in between).
        $asset_associations = array_merge(
            $asset_associations,
            get_asset_connectivity_for_control($id)
        );
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        $test_id = $value['test_id'];
        $test_result_associations = array_merge($test_result_associations, get_results_connectivity_for_test($test_id));
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        $risk_id = $value['risk_id'];
        $asset_associations = array_merge($asset_associations, get_asset_connectivity_for_risk($risk_id));
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "frameworks" => $framework_associations,
        "tests" => $test_associations,
        "documents" => $document_associations,
        "test_results" => $test_result_associations,
        "risks" => $risk_associations,
        "self_assessment_results" => $self_assessment_result_associations,
        "exceptions" => $exception_associations,
        "audits" => $audit_associations,
    ];

    // Return the associations
    return $associations;
}

/*******************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS TEST *
 *******************************************************/
function connectivity_visualizer_associations_test($id)
{
    // Create the default empty arrays
    $framework_associations = [];
    $control_associations = [];
    $document_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];
    $audit_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $test_result_associations = get_results_connectivity_for_test($id);
        $control_associations = get_control_connectivity_for_test($id);
        $audit_associations = get_audit_connectivity_for_test($id);
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        $control_id = $value['control_id'];
        $framework_associations = array_merge($framework_associations, get_framework_connectivity_for_control($control_id));
        $document_associations = array_merge($document_associations, get_document_connectivity_for_control($control_id));
        $risk_associations = array_merge($risk_associations, get_risk_connectivity_for_control($control_id));
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        $risk_id = $value['risk_id'];
        $asset_associations = array_merge($asset_associations, get_asset_connectivity_for_risk($risk_id));
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "frameworks" => $framework_associations,
        "test_results" => $test_result_associations,
        "documents" => $document_associations,
        "controls" => $control_associations,
        "risks" => $risk_associations,
        "audits" => $audit_associations,
    ];

    // Return the associations
    return $associations;
}

/***********************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS DOCUMENT *
 ***********************************************************/
function connectivity_visualizer_associations_document($id)
{
    // Create the default empty arrays
    $framework_associations = [];
    $control_associations = [];
    $test_associations = [];
    $risk_associations = [];
    $test_result_associations = [];
    $asset_associations = [];
    $exception_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $control_associations = get_control_connectivity_for_document($id);
        $exception_associations = get_exception_connectivity_for_document($id);

        // Direct policy -> framework mappings (no control in between).
        $framework_associations = array_merge(
            $framework_associations,
            get_framework_connectivity_for_document($id)
        );
    }

    // For each control association
    foreach ($control_associations as $value)
    {
        $control_id = $value['control_id'];
        $framework_associations = array_merge($framework_associations, get_framework_connectivity_for_control($control_id));
        $test_associations = array_merge($test_associations, get_test_connectivity_for_control($control_id));
        $risk_associations = array_merge($risk_associations, get_risk_connectivity_for_control($control_id));
    }

    // For each risk association
    foreach ($risk_associations as $value)
    {
        $risk_id = $value['risk_id'];
        $asset_associations = array_merge($asset_associations, get_asset_connectivity_for_risk($risk_id));
    }

    // For each test association
    foreach ($test_associations as $value)
    {
        $test_id = $value['test_id'];
        $test_result_associations = array_merge($test_result_associations, get_results_connectivity_for_test($test_id));
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "frameworks" => $framework_associations,
        "test_results" => $test_result_associations,
        "tests" => $test_associations,
        "controls" => $control_associations,
        "risks" => $risk_associations,
        "exceptions" => $exception_associations,
    ];

    // Return the associations
    return $associations;
}

/*********************************************
 * FUNCTION: GET ASSET CONNECTIVITY FOR RISK *
 *********************************************/
function get_asset_connectivity_for_risk($risk_id)
{
    global $lang, $escaper;

    // Get the id
    $id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Create empty arrays
    $associations = [];
    $assets_added = [];

    // Get the assets
    $stmt = $db->prepare("SELECT DISTINCT id, name FROM assets a LEFT JOIN risks_to_assets rta ON a.id = rta.asset_id WHERE rta.risk_id = :risk_id AND verified=1;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the asset name and id
        $asset_id = $value['id'];
        $asset_name = try_decrypt($value['name']);

        // Create the asset to risk association
        $associations[] =[
            "risk_id" => $id,
            "asset_id" => $asset_id,
            "asset_name" => $asset_name,
            "node_id" => "asset_id_{$asset_id}",
            "node_name" => $asset_name,
            "connected_node_id" => "risk_id_{$id}",
            "color" => "#f7dc6f",
        ];

        // Track the added asset id
        $assets_added[] = $asset_id;
    }

    // Get the asset groups
    $stmt = $db->prepare("SELECT DISTINCT a.id, a.name FROM risks_to_asset_groups rtag LEFT JOIN risks r ON rtag.risk_id = r.id LEFT JOIN assets_asset_groups aag ON aag.asset_group_id = rtag.asset_group_id LEFT JOIN assets a ON a.id = aag.asset_id WHERE rtag.risk_id = :risk_id;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the asset name and id
        $asset_id = $value['id'];
        $asset_name = try_decrypt($value['name']);

        // If the asset name is not empty or null
        if ($asset_name != null && $asset_name != '')
        {
            // If the asset has not already been added
            if (!in_array($asset_id, $assets_added))
            {
                // Add the association
                $associations[] = [
                    "risk_id" => $id,
                    "asset_id" => $asset_id,
                    "asset_name" => $asset_name,
                    "node_id" => "asset_id_{$asset_id}",
                    "node_name" => $asset_name,
                    "connected_node_id" => "risk_id_{$id}",
                    "color" => "#f7dc6f",
                ];

                // Track the added asset id
                $assets_added[] = $asset_id;
            }
        }
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***********************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR RISK *
 ***********************************************/
function get_control_connectivity_for_risk($risk_id)
{
    global $lang, $escaper;

    // Get the id
    $id = $risk_id - 1000;

    // Open the database connection
    $db = db_open();

    $associations = [];

    // Query the database
    $stmt = $db->prepare("SELECT DISTINCT fc.id, fc.short_name FROM mitigations m LEFT JOIN mitigation_to_controls mtc ON m.id = mtc.mitigation_id LEFT JOIN framework_controls fc ON mtc.control_id = fc.id WHERE m.risk_id = :risk_id;");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the control name and id
        $control_name = $value['short_name'];
        $control_id = $value['id'];

        // If the control name is not empty or null
        if ($control_name != null && $control_name != '')
        {
            $associations[] = [
                "risk_id" => $risk_id,
                "control_id" => $control_id,
                "control_name" => $control_name,
                "node_id" => "control_id_{$control_id}",
                "node_name" => $control_name,
                "connected_node_id" => "risk_id_{$id}",
                "color" => "#154360",
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/*********************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR ASSET *
 *********************************************/
function get_risk_connectivity_for_asset($asset_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    $associations = [];

    // Get the assets
    $stmt = $db->prepare("SELECT DISTINCT r.id, r.subject, rs.calculated_risk FROM risks r LEFT JOIN risk_scoring rs ON r.id = rs.id LEFT JOIN risks_to_assets rta ON r.id = rta.risk_id WHERE rta.asset_id = :asset_id;");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Strip risks that the user shouldn't have access to
        $array = strip_no_access_risks($array, null, "id");
    }

    // For each item in the array
    foreach ($array as $value)
    {
        // Get the risk id and subject
        $risk_id = $value['id']+1000;
        $subject = try_decrypt($value['subject']);
        $color = get_risk_color($value['calculated_risk']);

        // If the subject is more than 50 characters
        if (strlen($subject) > 50)
        {
            // Truncate the selected subject to 50 characters
            $subject = "[" . $risk_id . "] " . substr($subject, 0, 50) . "...";
        }
        else $subject = "[" . $risk_id . "] " . $subject;

        // Create the risk to asset association
        $associations[] = [
            "asset_id" => $asset_id,
            "risk_id" => $risk_id,
            "risk_name" => $subject,
            "node_id" => "risk_id_{$value['id']}",
            "node_name" => $subject,
            "connected_node_id" => "asset_id_{$asset_id}",
            "color" => $color,
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/****************************************************
 * FUNCTION: GET FRAMEWORK CONNECTIVITY FOR CONTROL *
 ****************************************************/
function get_framework_connectivity_for_control($control_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT f.value, f.name FROM framework_control_mappings fcm
         LEFT JOIN frameworks f ON f.value = fcm.framework
         WHERE fcm.control_id = ?",
        [(int)$control_id], 'framework', 'control', (int)$control_id,
        'value', 'name', true
    );
}

/***********************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR CONTROL *
 ***********************************************/
function get_risk_connectivity_for_control($control_id)
{
    return graph_risk_edge_rows(
        "SELECT DISTINCT r.id, r.subject, rs.calculated_risk FROM risks r
         LEFT JOIN risk_scoring rs ON r.id = rs.id
         LEFT JOIN mitigations m ON r.id = m.risk_id
         LEFT JOIN mitigation_to_controls mtc ON m.id = mtc.mitigation_id
         WHERE mtc.control_id = ?",
        [(int)$control_id], 'control', (int)$control_id
    );
}

/***********************************************
 * FUNCTION: GET TEST CONNECTIVITY FOR CONTROL *
 ***********************************************/
function get_test_connectivity_for_control($control_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT fct.id, fct.name FROM framework_control_tests fct
         JOIN test_control_map tcm ON tcm.test_id = fct.id
         WHERE tcm.framework_control_id = ?",
        [(int)$control_id], 'test', 'control', (int)$control_id,
        'id', 'name'
    );
}

/****************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR FRAMEWORK *
 ****************************************************/
function get_control_connectivity_for_framework($framework_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT fc.id, fc.short_name FROM framework_controls fc
         LEFT JOIN framework_control_mappings fcm ON fc.id = fcm.control_id
         WHERE fcm.framework = ?",
        [(int)$framework_id], 'control', 'framework', (int)$framework_id,
        'id', 'short_name'
    );
}

/***********************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR TEST *
 ***********************************************/
function get_control_connectivity_for_test($test_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT fc.id, fc.short_name FROM framework_controls fc
         JOIN test_control_map tcm ON tcm.framework_control_id = fc.id
         JOIN framework_control_tests fct ON fct.id = tcm.test_id
         WHERE fct.id = ?",
        [(int)$test_id], 'control', 'test', (int)$test_id,
        'id', 'short_name'
    );
}

/***************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR DOCUMENT *
 ***************************************************/
function get_control_connectivity_for_document($document_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT fc.id, fc.short_name FROM framework_controls fc
         JOIN document_control_mappings dcm ON fc.id = dcm.control_id
         WHERE dcm.document_id = ? AND dcm.selected = 1",
        [(int)$document_id], 'control', 'document', (int)$document_id,
        'id', 'short_name'
    );
}

/***************************************************
 * FUNCTION: GET DOCUMENT CONNECTIVITY FOR CONTROL *
 ***************************************************/
function get_document_connectivity_for_control($control_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT d.id, d.document_name FROM documents d
         JOIN document_control_mappings dcm ON d.id = dcm.document_id
         WHERE dcm.control_id = ? AND dcm.selected = 1",
        [(int)$control_id], 'document', 'control', (int)$control_id,
        'id', 'document_name'
    );
}

/***********************************************
 * FUNCTION: GET RESULTS CONNECTIVITY FOR TEST *
 ***********************************************/
function get_results_connectivity_for_test($test_id)
{
    global $lang, $escaper;

    // Open the database connection
    $db = db_open();

    $associations = [];

    // Get the controls for this framework
    $stmt = $db->prepare("SELECT DISTINCT fctr.id, fctr.test_result, fctr.test_date FROM framework_control_test_results fctr LEFT JOIN framework_control_test_audits fcta ON fctr.test_audit_id = fcta.id LEFT JOIN framework_control_tests fct ON fct.id = fcta.test_id WHERE fct.id = :test_id;");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each test result
    foreach ($results as $value)
    {
        // Get the result name and id
        $result_id = $value['id'];
        $result_name = $value['test_result'];
        $result_date = $value['test_date'];

        // Get the color based on the test result
        switch ($result_name)
        {
            case "Pass":
                $color = "#27a9e3";
                break;
            case "Fail":
                $color = "#ed3139";
                break;
            default:
                $color = "#D3D3D3";
                break;
        }

        // Display the connectivity to results
        $associations[] = [
            "test_id" => $test_id,
            "test_result_id" => $result_id,
            "test_result_name" => $result_date . " [" . $result_name . "]",
            "node_id" => "test_result_id_{$result_id}",
            "node_name" => $result_date . " [" . $result_name . "]",
            "connected_node_id" => "test_id_{$test_id}",
            "color" => $color,
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/************************************************************
 * FUNCTION: GET SELF ASSESSMENT RESULT CONNECTIVITY FOR CONTROL *
 ************************************************************
 * Mirrors get_results_connectivity_for_test() above: a control's core SCF
 * Self-Assessment results (self_assessment_responses/self_assessments) are
 * surfaced as neighbor nodes of the control, never as a focal type. Core
 * tables (not an Extra), but table_exists() guarded belt-and-suspenders per
 * the Core/Extra boundary convention used throughout this file.
 ************************************************************/
function get_self_assessment_result_connectivity_for_control(int $control_id): array
{
    global $lang;

    if (!table_exists('self_assessment_responses'))
    {
        return [];
    }

    // Open the database connection
    $db = db_open();

    $associations = [];

    $stmt = $db->prepare("
        SELECT r.id, r.self_assessment_id, r.response, r.updated_at,
               sa.framework_name, sa.status,
               DATE(COALESCE(sa.completed_at, sa.created_at)) AS assessment_date
        FROM self_assessment_responses r
        JOIN self_assessments sa ON sa.id = r.self_assessment_id
        WHERE r.control_id = :control_id AND r.response <> ''
        ORDER BY COALESCE(sa.completed_at, sa.created_at) DESC;
    ");
    $stmt->bindParam(":control_id", $control_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each self-assessment result
    foreach ($results as $value)
    {
        $result_id = $value['id'];
        $result_date = $value['assessment_date'];
        $framework_name = $value['framework_name'];

        // Get the display label and color based on the response. Labels are
        // user-facing, so they come from the language file (Pass/Fail already
        // exist; NotApplicable => 'N/A'). node_name is escaped once at the
        // render sink, matching get_results_connectivity_for_test() — do not
        // pre-escape here.
        switch ($value['response'])
        {
            case "pass":
                $result_label = $lang['Pass'];
                $color = "#10793F";
                break;
            case "fail":
                $result_label = $lang['Fail'];
                $color = "#ed3139";
                break;
            default:
                $result_label = $lang['NotApplicable'];
                $color = "#6c757d";
                break;
        }

        // Display the connectivity to self-assessment results
        $associations[] = [
            "control_id" => $control_id,
            "self_assessment_result_id" => $result_id,
            "node_id" => "self_assessment_result_id_{$result_id}",
            "node_name" => "{$result_date} [{$result_label}] — {$framework_name}",
            "connected_node_id" => "control_id_{$control_id}",
            "color" => $color,
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/*************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS EXCEPTION  *
 *************************************************************/
/**
 * Walks OUT from a `document_exceptions`
 * row to its neighbors. An exception is control XOR policy
 * (control_framework_id > 0 OR policy_document_id > 0, never both in
 * practice — see includes/functions.php 'document_exception' view_type),
 * and may separately reference the framework it was filed under via the
 * standalone `framework_id` column.
 *
 * NOTE on "tests": `framework_control_test_schedule_exceptions` is a
 * DIFFERENT, unrelated "exception" concept (a skipped/overridden test
 * occurrence). The table DOES exist, but it has no column linking it back to
 * document_exceptions.value, and the graph's 'exception' node type maps only
 * to document_exceptions (see ai_context_node_types()), so there is still no
 * valid join to compute a "tests" bucket for a document-exception id. The
 * bucket key is kept (per the orchestrator's declared shape) but is always
 * empty; get_test_connectivity_for_exception() is a documented no-op
 * placeholder for when such a relationship is defined.
 */
function connectivity_visualizer_associations_exception($id)
{
    // Create the default empty arrays
    $control_associations = [];
    $framework_associations = [];
    $document_associations = [];
    $test_associations = [];
    $risk_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $control_associations = get_control_connectivity_for_exception($id);
        $framework_associations = get_framework_connectivity_for_exception($id);
        $document_associations = get_document_connectivity_for_exception($id);
        $test_associations = get_test_connectivity_for_exception($id);
        $risk_associations = get_risk_connectivity_for_exception($id);
    }

    // Merge the association arrays
    $associations = [
        "controls" => $control_associations,
        "frameworks" => $framework_associations,
        "documents" => $document_associations,
        "tests" => $test_associations,
        "risks" => $risk_associations,
    ];

    // Return the associations
    return $associations;
}

/*****************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR EXCEPTION  *
 *****************************************************/
function get_control_connectivity_for_exception($exception_id)
{
    return graph_edge_rows(
        "SELECT fc.id, fc.short_name FROM framework_controls fc
         JOIN document_exceptions de ON de.control_framework_id > 0
              AND de.control_framework_id = fc.id
         WHERE de.value = ?",
        [(int)$exception_id], 'control', 'exception', (int)$exception_id,
        'id', 'short_name'
    );
}

/*******************************************************
 * FUNCTION: GET DOCUMENT CONNECTIVITY FOR EXCEPTION   *
 *******************************************************/
function get_document_connectivity_for_exception($exception_id)
{
    return graph_edge_rows(
        "SELECT d.id, d.document_name FROM documents d
         JOIN document_exceptions de ON de.policy_document_id > 0
              AND de.policy_document_id = d.id AND d.document_type = 'policies'
         WHERE de.value = ?",
        [(int)$exception_id], 'document', 'exception', (int)$exception_id,
        'id', 'document_name'
    );
}

/********************************************************
 * FUNCTION: GET FRAMEWORK CONNECTIVITY FOR EXCEPTION   *
 ********************************************************/
function get_framework_connectivity_for_exception($exception_id)
{
    return graph_edge_rows(
        "SELECT f.value, f.name FROM frameworks f
         JOIN document_exceptions de ON de.framework_id > 0 AND de.framework_id = f.value
         WHERE de.value = ?",
        [(int)$exception_id], 'framework', 'exception', (int)$exception_id,
        'value', 'name', true
    );
}

/*******************************************************
 * FUNCTION: GET EXCEPTION CONNECTIVITY FOR CONTROL    *
 *******************************************************/
/**
 * Reverse of get_control_connectivity_for_exception(): the exceptions filed
 * against this control. `document_exceptions.name` is a plain varchar (only
 * description/justification are encrypted blobs), so no decrypt is needed.
 */
function get_exception_connectivity_for_control($control_id)
{
    return graph_edge_rows(
        "SELECT de.value, de.name FROM document_exceptions de
         WHERE de.control_framework_id > 0 AND de.control_framework_id = ?",
        [(int)$control_id], 'exception', 'control', (int)$control_id,
        'value', 'name'
    );
}

/********************************************************
 * FUNCTION: GET EXCEPTION CONNECTIVITY FOR DOCUMENT    *
 ********************************************************/
/**
 * Reverse of get_document_connectivity_for_exception(). Constrained to
 * document_type='policies' to match the forward walker — an exception's
 * policy_document_id only ever points at a policy.
 */
function get_exception_connectivity_for_document($document_id)
{
    return graph_edge_rows(
        "SELECT de.value, de.name FROM document_exceptions de
         JOIN documents d ON d.id = de.policy_document_id AND d.document_type = 'policies'
         WHERE de.policy_document_id > 0 AND de.policy_document_id = ?",
        [(int)$document_id], 'exception', 'document', (int)$document_id,
        'value', 'name'
    );
}

/*********************************************************
 * FUNCTION: GET EXCEPTION CONNECTIVITY FOR FRAMEWORK    *
 *********************************************************/
/**
 * Reverse of get_framework_connectivity_for_exception(). Note this is the
 * exception's OWN framework_id column, which is distinct from the framework
 * reachable transitively through control_framework_id.
 */
function get_exception_connectivity_for_framework($framework_id)
{
    return graph_edge_rows(
        "SELECT de.value, de.name FROM document_exceptions de
         WHERE de.framework_id > 0 AND de.framework_id = ?",
        [(int)$framework_id], 'exception', 'framework', (int)$framework_id,
        'value', 'name'
    );
}

/****************************************************
 * FUNCTION: GET TEST CONNECTIVITY FOR EXCEPTION    *
 ****************************************************/
/**
 * Documented no-op — see the block comment above
 * connectivity_visualizer_associations_exception(). The
 * framework_control_test_schedule_exceptions table exists, but has no
 * column linking it back to document_exceptions.value, so there is no
 * schema relationship today between a document_exceptions row and a
 * framework_control_test_schedule_exceptions row; this stub exists so the
 * orchestrator's "tests" bucket has a stable call site to fill in once that
 * link is defined.
 */
function get_test_connectivity_for_exception($exception_id)
{
    return [];
}

/****************************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR EXCEPTION    *
 ****************************************************/
/**
 * The risks this exception accepts. document_exceptions.associated_risks is a
 * comma-separated list of RAW risk ids, so the join is FIND_IN_SET — which
 * matches whole list members only and therefore cannot be fooled by a
 * substring (id 7 does not match "17,71").
 *
 * Routed through graph_risk_edge_rows() so the display-id offset, raw-id
 * node_id, bracketed label and score-derived color all match every other
 * risk edge in the graph.
 */
function get_risk_connectivity_for_exception($exception_id)
{
    return graph_risk_edge_rows(
        "SELECT DISTINCT r.id, r.subject, rs.calculated_risk
         FROM document_exceptions de
         JOIN risks r ON FIND_IN_SET(r.id, de.associated_risks) > 0
         LEFT JOIN risk_scoring rs ON rs.id = r.id
         WHERE de.value = ?",
        [(int)$exception_id], 'exception', (int)$exception_id
    );
}

/****************************************************
 * FUNCTION: GET EXCEPTION CONNECTIVITY FOR RISK    *
 ****************************************************/
/**
 * The exceptions accepting this risk. Takes the RAW db risk id (not the
 * display id), matching the raw ids stored in associated_risks.
 */
function get_exception_connectivity_for_risk($risk_id)
{
    return graph_edge_rows(
        "SELECT de.value, de.name FROM document_exceptions de
         WHERE FIND_IN_SET(?, de.associated_risks) > 0",
        [(int)$risk_id], 'exception', 'risk', (int)$risk_id,
        'value', 'name'
    );
}

/*************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS ASSESSMENT *
 *************************************************************/
/**
 * Walks OUT from a `questionnaire_tracking`
 * row (the "assessment" node type) to its neighbors. Every query below is
 * against Assessments-Extra-only tables and is reachable ONLY through the
 * activation-gated dispatch in ai_context_associations() ('assessment' case,
 * guarded by assessments_extra()) — there is no unguarded call path here.
 *
 * controls: questionnaire_tracking -> questionnaire_responses -> question_id
 *           -> questionnaire_question_to_control -> framework_controls.
 * frameworks (secondary/best-effort): questionnaire_responses -> question_id
 *           -> questionnaire_questions.scf_framework_id -> frameworks.value.
 */
function connectivity_visualizer_associations_assessment($id)
{
    // Create the default empty arrays
    $control_associations = [];
    $framework_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $control_associations = get_control_connectivity_for_assessment($id);
        $framework_associations = get_framework_connectivity_for_assessment($id);
    }

    // Merge the association arrays
    $associations = [
        "controls" => $control_associations,
        "frameworks" => $framework_associations,
    ];

    // Return the associations
    return $associations;
}

/******************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR ASSESSMENT  *
 ******************************************************/
function get_control_connectivity_for_assessment($assessment_id)
{
    global $lang, $escaper;

    // Belt-and-suspenders Core/Extra guard: this walks Assessments-Extra-only
    // tables. Callers gate on the Extra's activation, but guard here too so a
    // missing table returns the normal empty shape instead of a fatal.
    if (!table_exists('questionnaire_responses'))
    {
        return [];
    }

    // Open the database connection
    $db = db_open();

    $associations = [];

    // Get the controls answered questions on this assessment map to
    $stmt = $db->prepare("SELECT DISTINCT fc.id, fc.short_name
                          FROM questionnaire_responses qr
                          JOIN questionnaire_question_to_control qqtc ON qqtc.question_id = qr.question_id
                          JOIN framework_controls fc ON fc.id = qqtc.control_id
                          WHERE qr.questionnaire_tracking_id = :id;");
    $stmt->bindParam(":id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each control
    foreach ($controls as $value)
    {
        // Get the control name and id
        $control_id = $value['id'];
        $control_name = $value['short_name'];

        // Display the connectivity to controls
        $associations[] = [
            "assessment_id" => $assessment_id,
            "control_id" => $control_id,
            "control_name" => $control_name,
            "node_id" => "control_id_{$control_id}",
            "node_name" => $control_name,
            "connected_node_id" => "assessment_id_{$assessment_id}",
            "color" => "#154360",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/********************************************************
 * FUNCTION: GET FRAMEWORK CONNECTIVITY FOR ASSESSMENT  *
 ********************************************************/
function get_framework_connectivity_for_assessment($assessment_id)
{
    global $lang, $escaper;

    // Belt-and-suspenders Core/Extra guard: this walks Assessments-Extra-only
    // tables. Callers gate on the Extra's activation, but guard here too so a
    // missing table returns the normal empty shape instead of a fatal.
    if (!table_exists('questionnaire_responses'))
    {
        return [];
    }

    // Open the database connection
    $db = db_open();

    $associations = [];

    // Get the frameworks the answered questions' controls belong to, via each
    // question's scf_framework_id
    $stmt = $db->prepare("SELECT DISTINCT f.value, f.name
                          FROM questionnaire_responses qr
                          JOIN questionnaire_questions qq ON qq.id = qr.question_id
                          JOIN frameworks f ON f.value = qq.scf_framework_id
                          WHERE qr.questionnaire_tracking_id = :id AND qq.scf_framework_id > 0;");
    $stmt->bindParam(":id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each framework
    foreach ($frameworks as $value)
    {
        // Get the framework name and id
        $framework_id = $value['value'];
        $framework_name = try_decrypt($value['name']);

        // Display the connectivity to frameworks
        $associations[] = [
            "assessment_id" => $assessment_id,
            "framework_id" => $framework_id,
            "framework_name" => $framework_name,
            "node_id" => "framework_id_{$framework_id}",
            "node_name" => $framework_name,
            "connected_node_id" => "assessment_id_{$assessment_id}",
            "color" => "#4a235a",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/*************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS INCIDENT   *
 *************************************************************/
/**
 * Walks OUT from an
 * `incident_management_incidents` row (the "incident" node type) to its
 * neighbors. Every query below is against Incident-Management-Extra-only
 * tables and is reachable ONLY through the activation-gated dispatch in
 * ai_context_associations() ('incident' case, guarded by
 * incident_management_extra()) — there is no unguarded call path here.
 *
 * assets: incident_management_incidents -> incident_management_incident_to_assets -> assets.
 * risks:  incident_management_incidents -> incident_management_incident_to_risks -> risks.
 */
function connectivity_visualizer_associations_incident($id)
{
    // Create the default empty arrays
    $asset_associations = [];
    $risk_associations = [];

    // If a value has been selected
    if (!is_null($id))
    {
        $asset_associations = get_asset_connectivity_for_incident($id);
        $risk_associations = get_risk_connectivity_for_incident($id);
    }

    // Merge the association arrays
    $associations = [
        "assets" => $asset_associations,
        "risks" => $risk_associations,
    ];

    // Return the associations
    return $associations;
}

/****************************************************
 * FUNCTION: GET ASSET CONNECTIVITY FOR INCIDENT    *
 ****************************************************/
function get_asset_connectivity_for_incident($incident_id)
{
    global $lang, $escaper;

    // Belt-and-suspenders Core/Extra guard: this walks Incident-Management-
    // Extra-only tables. Callers gate on the Extra's activation, but guard
    // here too so a missing table returns the normal empty shape, not a fatal.
    if (!table_exists('incident_management_incident_to_assets'))
    {
        return [];
    }

    // Open the database connection
    $db = db_open();

    $associations = [];

    // Get the assets linked to this incident via the junction table
    $stmt = $db->prepare("SELECT DISTINCT a.id, a.name
                          FROM incident_management_incident_to_assets i2a
                          JOIN assets a ON a.id = i2a.asset_id
                          WHERE i2a.incident_id = :id;");
    $stmt->bindParam(":id", $incident_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each asset
    foreach ($assets as $value)
    {
        // Get the asset name and id
        $asset_id = $value['id'];
        $asset_name = try_decrypt($value['name']);

        // Display the connectivity to assets
        $associations[] = [
            "incident_id" => $incident_id,
            "asset_id" => $asset_id,
            "asset_name" => $asset_name,
            "node_id" => "asset_id_{$asset_id}",
            "node_name" => $asset_name,
            "connected_node_id" => "incident_id_{$incident_id}",
            "color" => "#f7dc6f",
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/***************************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR INCIDENT    *
 ***************************************************/
function get_risk_connectivity_for_incident($incident_id)
{
    global $lang, $escaper;

    // Belt-and-suspenders Core/Extra guard: this walks Incident-Management-
    // Extra-only tables. Callers gate on the Extra's activation, but guard
    // here too so a missing table returns the normal empty shape, not a fatal.
    if (!table_exists('incident_management_incident_to_risks'))
    {
        return [];
    }

    // Open the database connection
    $db = db_open();

    $associations = [];

    // Get the risks linked to this incident via the junction table
    $stmt = $db->prepare("SELECT DISTINCT r.id, r.subject, rs.calculated_risk
                          FROM incident_management_incident_to_risks i2r
                          JOIN risks r ON r.id = i2r.risk_id
                          LEFT JOIN risk_scoring rs ON r.id = rs.id
                          WHERE i2r.incident_id = :id;");
    $stmt->bindParam(":id", $incident_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If team separation is enabled
    if (team_separation_extra())
    {
        //Include the team separation extra
        require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

        // Strip risks that the user shouldn't have access to
        $risks = strip_no_access_risks($risks, null, "id");
    }

    // For each risk
    foreach ($risks as $value)
    {
        // Get the risk id and subject
        $risk_id = $value['id']+1000;
        $subject = try_decrypt($value['subject']);
        $color = get_risk_color($value['calculated_risk']);

        // If the subject is more than 50 characters
        if (strlen($subject) > 50)
        {
            // Truncate the selected subject to 50 characters
            $subject = "[" . $risk_id . "] " . substr($subject, 0, 50) . "...";
        }
        else $subject = "[" . $risk_id . "] " . $subject;

        // Display the connectivity to risks
        $associations[] = [
            "incident_id" => $incident_id,
            "risk_id" => $risk_id,
            "risk_name" => $subject,
            "node_id" => "risk_id_{$value['id']}",
            "node_name" => $subject,
            "connected_node_id" => "incident_id_{$incident_id}",
            "color" => $color,
        ];
    }

    // Close the database connection
    db_close($db);

    // Return the associations
    return $associations ?? [];
}

/*********************************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR TEST RESULT       *
 *********************************************************/
/**
 * The risks raised from this test result. This is the causal edge between a
 * failed control test and the risk it produced; previously risk and test were
 * only connected transitively through the control, which lost the causality.
 *
 * NEIGHBOR-ONLY, currently unreachable: 'test_result' has no
 * connectivity_visualizer_associations_*() orchestrator and is deliberately
 * absent from ai_context_node_types(), so nothing calls this as a FOCAL
 * walker today. The forward direction (get_test_result_connectivity_for_risk())
 * is wired from the risk orchestrator, so the edge itself is not missing from
 * the graph -- only this reverse walker is reserved for a future test_result
 * focal. Covered by tests/unit/EntityGraphComplianceEdgesTest.php, which
 * exercises it directly.
 */
function get_risk_connectivity_for_test_result($test_result_id)
{
    return graph_risk_edge_rows(
        "SELECT DISTINCT r.id, r.subject, rs.calculated_risk
         FROM framework_control_test_results_to_risks fctrr
         JOIN risks r ON r.id = fctrr.risk_id
         LEFT JOIN risk_scoring rs ON rs.id = r.id
         WHERE fctrr.test_results_id = ?",
        [(int)$test_result_id], 'test_result', (int)$test_result_id
    );
}

/*********************************************************
 * FUNCTION: GET TEST RESULT CONNECTIVITY FOR RISK       *
 *********************************************************/
/**
 * The test results that produced this risk. Takes the RAW db risk id.
 *
 * The label is "<result> — <test name>" so a bare "Fail" node is
 * identifiable on the canvas; test names are not encrypted.
 */
function get_test_result_connectivity_for_risk($risk_id)
{
    return graph_edge_rows(
        "SELECT fctr.id,
                CONCAT(fctr.test_result, ' — ', COALESCE(fct.name, '')) AS result_label
         FROM framework_control_test_results_to_risks fctrr
         JOIN framework_control_test_results fctr ON fctr.id = fctrr.test_results_id
         LEFT JOIN framework_control_test_audits fcta ON fcta.id = fctr.test_audit_id
         LEFT JOIN framework_control_tests fct ON fct.id = fcta.test_id
         WHERE fctrr.risk_id = ?",
        [(int)$risk_id], 'test_result', 'risk', (int)$risk_id,
        'id', 'result_label'
    );
}

/**********************************************************
 * FUNCTION: GET FRAMEWORK CONNECTIVITY FOR DOCUMENT      *
 **********************************************************/
/**
 * Frameworks this document maps to DIRECTLY (document_framework_mappings).
 * Previously a document only reached frameworks transitively via its
 * controls, so a policy mapped straight to a framework had no edge at all.
 */
function get_framework_connectivity_for_document($document_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT f.value, f.name FROM document_framework_mappings dfm
         JOIN frameworks f ON f.value = dfm.framework_id
         WHERE dfm.document_id = ?",
        [(int)$document_id], 'framework', 'document', (int)$document_id,
        'value', 'name', true
    );
}

/**********************************************************
 * FUNCTION: GET DOCUMENT CONNECTIVITY FOR FRAMEWORK      *
 **********************************************************/
function get_document_connectivity_for_framework($framework_id)
{
    return graph_edge_rows(
        "SELECT DISTINCT d.id, d.document_name FROM document_framework_mappings dfm
         JOIN documents d ON d.id = dfm.document_id
         WHERE dfm.framework_id = ?",
        [(int)$framework_id], 'document', 'framework', (int)$framework_id,
        'id', 'document_name'
    );
}

/**********************************************************
 * FUNCTION: GET ASSET CONNECTIVITY FOR CONTROL           *
 **********************************************************/
/**
 * Assets this control is scoped to DIRECTLY, via control_to_assets and via
 * control_to_asset_groups expanded to member assets. Previously a control
 * only reached assets transitively through a risk.
 *
 * Verified assets only on BOTH arms. get_asset_connectivity_for_risk() only
 * filters verified on its direct (risks_to_assets) arm — its asset-group arm
 * has no verified check — so this is stricter than that legacy function, not
 * an exact match; that's intentional here. The UNION dedupes an asset
 * reachable both directly and through a group.
 */
function get_asset_connectivity_for_control($control_id)
{
    return graph_edge_rows(
        "SELECT a.id, a.name FROM control_to_assets cta
         JOIN assets a ON a.id = cta.asset_id
         WHERE cta.control_id = ? AND a.verified = 1
         UNION
         SELECT a.id, a.name FROM control_to_asset_groups ctag
         JOIN assets_asset_groups aag ON aag.asset_group_id = ctag.asset_group_id
         JOIN assets a ON a.id = aag.asset_id
         WHERE ctag.control_id = ? AND a.verified = 1",
        [(int)$control_id, (int)$control_id],
        'asset', 'control', (int)$control_id,
        'id', 'name', true
    );
}

/**********************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR ASSET           *
 **********************************************************/
function get_control_connectivity_for_asset($asset_id)
{
    return graph_edge_rows(
        "SELECT fc.id, fc.short_name FROM control_to_assets cta
         JOIN framework_controls fc ON fc.id = cta.control_id
         WHERE cta.asset_id = ?
         UNION
         SELECT fc.id, fc.short_name FROM control_to_asset_groups ctag
         JOIN assets_asset_groups aag ON aag.asset_group_id = ctag.asset_group_id
         JOIN framework_controls fc ON fc.id = ctag.control_id
         WHERE aag.asset_id = ?",
        [(int)$asset_id, (int)$asset_id],
        'control', 'asset', (int)$asset_id,
        'id', 'short_name'
    );
}

/*****************************************************
 * FUNCTION: GET AUDIT CONNECTIVITY FOR TEST         *
 *****************************************************/
/**
 * Audits of this test. framework_control_test_audits.name is a longtext
 * snapshot of the test name at audit time and is not encrypted.
 */
function get_audit_connectivity_for_test($test_id)
{
    return graph_edge_rows(
        "SELECT fcta.id, fcta.name FROM framework_control_test_audits fcta
         WHERE fcta.test_id = ?",
        [(int)$test_id], 'audit', 'test', (int)$test_id,
        'id', 'name'
    );
}

/*****************************************************
 * FUNCTION: GET AUDIT CONNECTIVITY FOR CONTROL      *
 *****************************************************/
/**
 * Audits touching this control, from both directions the schema supports:
 * the audit's own framework_control_id snapshot, and the audit_control_map
 * junction used when an audit spans several controls.
 */
function get_audit_connectivity_for_control($control_id)
{
    return graph_edge_rows(
        "SELECT fcta.id, fcta.name FROM framework_control_test_audits fcta
         WHERE fcta.framework_control_id = ?
         UNION
         SELECT fcta.id, fcta.name FROM audit_control_map acm
         JOIN framework_control_test_audits fcta ON fcta.id = acm.audit_id
         WHERE acm.framework_control_id = ?",
        [(int)$control_id, (int)$control_id],
        'audit', 'control', (int)$control_id,
        'id', 'name'
    );
}

/*********************************************************
 * FUNCTION: GET AUDIT CONNECTIVITY FOR TEST RESULT      *
 *********************************************************/
/**
 * NEIGHBOR-ONLY, currently unreachable: like get_risk_connectivity_for_
 * test_result() above, 'test_result' has no focal orchestrator, so nothing
 * calls this today. The forward direction (get_results_connectivity_for_
 * audit()) is wired from the audit orchestrator, so the edge is not missing
 * from the graph -- only this reverse walker is reserved for a future
 * test_result focal. Covered by tests/unit/EntityGraphComplianceEdgesTest.php.
 */
function get_audit_connectivity_for_test_result($test_result_id)
{
    return graph_edge_rows(
        "SELECT fcta.id, fcta.name FROM framework_control_test_results fctr
         JOIN framework_control_test_audits fcta ON fcta.id = fctr.test_audit_id
         WHERE fctr.id = ?",
        [(int)$test_result_id], 'audit', 'test_result', (int)$test_result_id,
        'id', 'name'
    );
}

/*****************************************************
 * FUNCTION: GET TEST CONNECTIVITY FOR AUDIT         *
 *****************************************************/
function get_test_connectivity_for_audit($audit_id)
{
    return graph_edge_rows(
        "SELECT fct.id, fct.name FROM framework_control_test_audits fcta
         JOIN framework_control_tests fct ON fct.id = fcta.test_id
         WHERE fcta.id = ?",
        [(int)$audit_id], 'test', 'audit', (int)$audit_id,
        'id', 'name'
    );
}

/*****************************************************
 * FUNCTION: GET CONTROL CONNECTIVITY FOR AUDIT      *
 *****************************************************/
function get_control_connectivity_for_audit($audit_id)
{
    return graph_edge_rows(
        "SELECT fc.id, fc.short_name FROM framework_control_test_audits fcta
         JOIN framework_controls fc ON fc.id = fcta.framework_control_id
         WHERE fcta.id = ?
         UNION
         SELECT fc.id, fc.short_name FROM audit_control_map acm
         JOIN framework_controls fc ON fc.id = acm.framework_control_id
         WHERE acm.audit_id = ?",
        [(int)$audit_id, (int)$audit_id],
        'control', 'audit', (int)$audit_id,
        'id', 'short_name'
    );
}

/*****************************************************
 * FUNCTION: GET RESULTS CONNECTIVITY FOR AUDIT      *
 *****************************************************/
function get_results_connectivity_for_audit($audit_id)
{
    return graph_edge_rows(
        "SELECT fctr.id,
                CONCAT(fctr.test_result, ' — ', DATE_FORMAT(fctr.test_date, '%Y-%m-%d')) AS result_label
         FROM framework_control_test_results fctr
         WHERE fctr.test_audit_id = ?",
        [(int)$audit_id], 'test_result', 'audit', (int)$audit_id,
        'id', 'result_label'
    );
}

/**********************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS AUDIT   *
 **********************************************************/
function connectivity_visualizer_associations_audit($id)
{
    $test_associations        = [];
    $control_associations     = [];
    $framework_associations   = [];
    $test_result_associations = [];

    if (!is_null($id))
    {
        $test_associations        = get_test_connectivity_for_audit($id);
        $control_associations     = get_control_connectivity_for_audit($id);
        $test_result_associations = get_results_connectivity_for_audit($id);
    }

    // Frameworks are reached through the audit's controls.
    foreach ($control_associations as $value)
    {
        $framework_associations = array_merge(
            $framework_associations,
            get_framework_connectivity_for_control($value['control_id'])
        );
    }

    return [
        "tests"        => $test_associations,
        "controls"     => $control_associations,
        "frameworks"   => $framework_associations,
        "test_results" => $test_result_associations,
    ];
}

/**********************************************************
 * FUNCTION: GET RISK CATALOG CONNECTIVITY FOR RISK       *
 **********************************************************/
/**
 * The risk-catalog taxonomy entries this risk is classified under. Takes the
 * RAW db risk id. The label is "<number> <name>" so catalog entries are
 * identifiable on the canvas by their reference number.
 */
function get_risk_catalog_connectivity_for_risk($risk_id)
{
    return graph_edge_rows(
        "SELECT rc.id, CONCAT(rc.number, ' ', rc.name) AS catalog_label
         FROM risk_catalog_mappings rcm
         JOIN risk_catalog rc ON rc.id = rcm.risk_catalog_id
         WHERE rcm.risk_id = ?",
        [(int)$risk_id], 'risk_catalog', 'risk', (int)$risk_id,
        'id', 'catalog_label'
    );
}

/************************************************************
 * FUNCTION: GET THREAT CATALOG CONNECTIVITY FOR RISK       *
 ************************************************************/
function get_threat_catalog_connectivity_for_risk($risk_id)
{
    return graph_edge_rows(
        "SELECT tc.id, CONCAT(tc.number, ' ', tc.name) AS catalog_label
         FROM threat_catalog_mappings tcm
         JOIN threat_catalog tc ON tc.id = tcm.threat_catalog_id
         WHERE tcm.risk_id = ?",
        [(int)$risk_id], 'threat_catalog', 'risk', (int)$risk_id,
        'id', 'catalog_label'
    );
}

/**********************************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR RISK CATALOG       *
 **********************************************************/
function get_risk_connectivity_for_risk_catalog($catalog_id)
{
    return graph_risk_edge_rows(
        "SELECT DISTINCT r.id, r.subject, rs.calculated_risk
         FROM risk_catalog_mappings rcm
         JOIN risks r ON r.id = rcm.risk_id
         LEFT JOIN risk_scoring rs ON rs.id = r.id
         WHERE rcm.risk_catalog_id = ?",
        [(int)$catalog_id], 'risk_catalog', (int)$catalog_id
    );
}

/************************************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR THREAT CATALOG       *
 ************************************************************/
function get_risk_connectivity_for_threat_catalog($catalog_id)
{
    return graph_risk_edge_rows(
        "SELECT DISTINCT r.id, r.subject, rs.calculated_risk
         FROM threat_catalog_mappings tcm
         JOIN risks r ON r.id = tcm.risk_id
         LEFT JOIN risk_scoring rs ON rs.id = r.id
         WHERE tcm.threat_catalog_id = ?",
        [(int)$catalog_id], 'threat_catalog', (int)$catalog_id
    );
}

/*****************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS RISK CATALOG   *
 *****************************************************************/
/**
 * A taxonomy focal: its only neighbors are the risks classified under it.
 * On a real instance this can be hundreds of risks, which the clustering
 * introduced in the neighbor-summary task collapses to a single counted node.
 */
function connectivity_visualizer_associations_risk_catalog($id)
{
    $risk_associations = [];

    if (!is_null($id))
    {
        $risk_associations = get_risk_connectivity_for_risk_catalog($id);
    }

    return ["risks" => $risk_associations];
}

/*******************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS THREAT CATALOG   *
 *******************************************************************/
function connectivity_visualizer_associations_threat_catalog($id)
{
    $risk_associations = [];

    if (!is_null($id))
    {
        $risk_associations = get_risk_connectivity_for_threat_catalog($id);
    }

    return ["risks" => $risk_associations];
}

/*****************************************************************
 * VULNERABILITY NODE TYPE (Vulnerability Management Extra)      *
 *****************************************************************
 *
 * Unlike every other graph type, `vulnerability` is not backed by one table.
 * The Vulnerability Management Extra creates a SEPARATE table family per
 * scanner platform — vulnmgmt_<platform>_vulnerabilities and
 * vulnmgmt_<platform>_vulnerabilities_to_assets — and it creates them lazily,
 * only when the customer has configured that platform. A stock instance with
 * the Extra enabled and no scanner configured has vulnmgmt_extra() === true
 * and ZERO vulnmgmt_* tables, so vulnmgmt_extra() alone is NOT a sufficient
 * guard: the per-platform table_exists() check in
 * vulnmgmt_graph_active_platforms() is load-bearing, not defence in depth.
 *
 * Because each platform table carries its own AUTO_INCREMENT id, a raw id is
 * ambiguous across platforms — so vulnerability node ids are COMPOSITE:
 * vulnerability_id_<platform>_<id>. The platform slug in that id is validated
 * against vulnmgmt_graph_platforms() (a hardcoded allow-list) before it is
 * ever interpolated into a table name.
 */

/**
 * The vulnmgmt platform slugs the graph knows about. This is an ALLOW-LIST:
 * every slug that reaches a table name in this file is validated against it,
 * so a slug parsed out of a node id can never become SQL injection.
 *
 * (insightappsec exists in the Extra's source but its table family is
 * commented out, so it is deliberately absent here.)
 */
function vulnmgmt_graph_platforms(): array
{
    return ['insightvm', 'insightvmcloud', 'nexpose', 'qualys', 'tenable'];
}

/**
 * The per-platform column map. The five platform tables do NOT share a
 * schema, so a single hardcoded SELECT would be a fatal unknown-column error
 * on four of the five. Verified against the CREATE TABLE statements in
 * simplerisk/extras/vulnmgmt/includes/<platform>.php:
 *
 *   - `title` exists on insightvm / insightvmcloud / nexpose / qualys.
 *     tenable has NO title column; its human label is `plugin_name`.
 *   - `status`, `patchable`, `first_found`, `last_found` exist only on qualys.
 *     tenable uses `seen_first` / `seen_last`; the InsightVM/Nexpose family
 *     uses `added` / `modified`.
 *   - `solution` exists on qualys (TEXT, becomes BLOB under the Encrypted
 *     Database Extra) and tenable (BLOB); the InsightVM/Nexpose family has
 *     none.
 *   - `severity` is an INT on qualys/tenable and a VARCHAR label
 *     ("Critical"/"Severe"/…) on the InsightVM/Nexpose family, hence the
 *     per-platform cast.
 *
 * EVERY value in this map is a string literal. Nothing here is derived from
 * input, which is what makes it safe to interpolate into SQL.
 *
 * `cast` is one of: 'raw' (pass through), 'int' (null-preserving int cast),
 * 'decrypt' (try_decrypt(), for columns the Extra encrypts).
 */
function vulnmgmt_graph_platform_schema(): array
{
    // Present on all five platform tables.
    $common = [
        'hidden'  => ['column' => 'hidden',             'cast' => 'int'],
        'risk_id' => ['column' => 'simplerisk_risk_id', 'cast' => 'int'],
    ];

    // The InsightVM, InsightVM Cloud and Nexpose tables are column-identical.
    $rapid7 = [
        'label_column'    => 'title',
        'fields' => array_merge([
            'severity'    => ['column' => 'severity', 'cast' => 'raw'],
            'first_found' => ['column' => 'added',    'cast' => 'raw'],
            'last_found'  => ['column' => 'modified', 'cast' => 'raw'],
        ], $common),
    ];

    return [
        'insightvm'      => $rapid7,
        'insightvmcloud' => $rapid7,
        'nexpose'        => $rapid7,
        'qualys' => [
            'label_column'    => 'title',
            'fields' => array_merge([
                'severity'    => ['column' => 'severity',    'cast' => 'int'],
                'status'      => ['column' => 'status',      'cast' => 'raw'],
                'first_found' => ['column' => 'first_found', 'cast' => 'raw'],
                'last_found'  => ['column' => 'last_found',  'cast' => 'raw'],
                'patchable'   => ['column' => 'patchable',   'cast' => 'int'],
                'solution'    => ['column' => 'solution',    'cast' => 'decrypt'],
            ], $common),
        ],
        'tenable' => [
            // tenable has no `title` column at all.
            'label_column'    => 'plugin_name',
            'fields' => array_merge([
                'severity'    => ['column' => 'severity',   'cast' => 'int'],
                'first_found' => ['column' => 'seen_first', 'cast' => 'raw'],
                'last_found'  => ['column' => 'seen_last',  'cast' => 'raw'],
                'solution'    => ['column' => 'solution',   'cast' => 'decrypt'],
            ], $common),
        ],
    ];
}

/**
 * The subset of platforms usable right now: the Vulnerability Management
 * Extra is active AND that platform's tables were actually created AND the
 * shared vulnmgmt_assets table (which every asset-side join needs) exists.
 *
 * The Extra creates platform tables only when the customer has configured
 * that platform (see extras/vulnmgmt/includes/<platform>.php), so
 * vulnmgmt_extra() alone is NOT sufficient to query
 * vulnmgmt_qualys_vulnerabilities. Both guards are required by the Core/Extra
 * DB boundary rule.
 *
 * This function is the ONLY thing that authorizes a platform slug to be
 * interpolated into a table name anywhere in this file.
 *
 * MEMOIZED per request. table_exists() is itself uncached — each call does a
 * fresh db_open() plus an information_schema.tables query — and this function
 * makes 1 + 2N of them. It is called once per asset and per risk Connectivity
 * Visualizer render, and up to five times for a single vulnerability AI
 * bundle (both walkers, the focal name lookup, and enrichment), which without
 * the memo is ~55 information_schema round-trips for one bundle.
 *
 * Pass $flush_cache = true to recompute. Production has no need for it — the
 * schema cannot change mid-request — but activating the Extra or configuring
 * a scanner in a LATER request must not be served a stale answer, and tests
 * that toggle $GLOBALS['extra_vulnmgmt'] would otherwise pass spuriously off
 * the memo.
 */
function vulnmgmt_graph_active_platforms(bool $flush_cache = false): array
{
    static $memo = null;

    if ($flush_cache) {
        $memo = null;
    }
    if ($memo !== null) {
        return $memo;
    }

    if (!vulnmgmt_extra()) {
        return $memo = [];
    }
    // Shared prerequisite for the asset-side joins; created by the Extra's
    // activation, but absent on an enabled-but-unconfigured instance.
    if (!table_exists('vulnmgmt_assets')) {
        return $memo = [];
    }
    $active = [];
    foreach (vulnmgmt_graph_platforms() as $platform) {
        if (table_exists("vulnmgmt_{$platform}_vulnerabilities")
            && table_exists("vulnmgmt_{$platform}_vulnerabilities_to_assets")) {
            $active[] = $platform;
        }
    }
    return $memo = $active;
}

/**
 * Assemble the per-platform rows for a vulnerability neighbor list.
 *
 * Cannot use graph_edge_rows(): node ids are composite
 * (vulnerability_id_<platform>_<id>) and labels are encrypted BLOBs, so the
 * rows are built here. $platform always comes from
 * vulnmgmt_graph_active_platforms(), i.e. the allow-list.
 *
 * $sql MUST select `id` and `label`. Names carry raw UTF-8; escaping happens
 * at the caller's sink.
 */
function vulnmgmt_graph_rows(string $platform, string $sql, array $params,
                             string $focalType, $focalId): array
{
    $focalNodeId = "{$focalType}_id_{$focalId}";
    $db = db_open();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
    db_close($db);

    $color = graph_node_color('vulnerability');
    $rows  = [];
    foreach ($fetched as $r) {
        $id = (int)$r['id'];
        if ($id <= 0) {
            continue;
        }
        $title = try_decrypt($r['label']);
        if ($title === null || $title === '') {
            // Every label column is nullable and, on an encrypted instance, a
            // decrypt failure yields "". Fall back to a stable synthetic label
            // so the node is still addressable on the canvas.
            $title = $platform . ' #' . $id;
        }
        $rows[] = [
            // Focal key matches graph_edge_rows()'s public shape.
            "{$focalType}_id"        => $focalId,
            'vulnerability_id'       => $id,
            'vulnerability_platform' => $platform,
            'vulnerability_name'     => $title,
            'node_id'                => "vulnerability_id_{$platform}_{$id}",
            'node_name'              => $title,
            'connected_node_id'      => $focalNodeId,
            'color'                  => $color,
        ];
    }
    return $rows;
}

/*************************************************************
 * FUNCTION: GET VULNERABILITY CONNECTIVITY FOR ASSET        *
 *************************************************************/
/**
 * Vulnerabilities found on this SimpleRisk asset, unioned across every
 * ACTIVE platform. Returns [] when the Extra is off or no platform is
 * configured, so no query ever touches a missing table.
 *
 * The junction's `asset_id` stores the SCANNER's own asset identifier —
 * vulnmgmt_assets.uuid — NOT vulnmgmt_assets.id. Verified against every
 * platform's import path (e.g. tenable.php's INSERT binds $uuid, and its
 * immediately-following lookup joins `ON vnvta.asset_id = va.uuid`). Some
 * older reporting queries inside the Extra join on va.id instead; those only
 * coincide when the scanner's identifier happens to equal the local
 * autoincrement, so the uuid join is the correct one.
 *
 * `v.hidden = 0` excludes findings a triager has dismissed — matching the
 * Extra's own triage-queue filter (e.g. qualys.php / nexpose.php) so the
 * graph never shows a node the triager already closed out.
 */
function get_vulnerability_connectivity_for_asset($asset_id)
{
    // vulnmgmt_assets.simplerisk_asset_id is 0 for a scanner asset that was
    // never mapped to a SimpleRisk asset — the Extra's normal unmapped state —
    // so an id of 0 would match every unmapped finding rather than nothing.
    // Unreachable today (every entry point requires a positive id), but guard
    // structurally like the walker's siblings do.
    //
    // The risk-side walker needs no equivalent: untriaged findings carry
    // simplerisk_risk_id NULL (the Extra's triage queries key on IS NULL), not
    // 0, so `WHERE simplerisk_risk_id = 0` is not a wildcard there.
    if ((int)$asset_id <= 0) {
        return [];
    }
    $schema = vulnmgmt_graph_platform_schema();
    $rows = [];
    foreach (vulnmgmt_graph_active_platforms() as $platform) {
        $label = $schema[$platform]['label_column'];
        $rows = array_merge($rows, vulnmgmt_graph_rows(
            $platform,
            "SELECT DISTINCT v.id, v.`{$label}` AS label
             FROM `vulnmgmt_{$platform}_vulnerabilities` v
             JOIN `vulnmgmt_{$platform}_vulnerabilities_to_assets` vta ON vta.vulnerability_id = v.id
             JOIN `vulnmgmt_assets` va ON va.uuid = vta.asset_id
             WHERE va.simplerisk_asset_id = ? AND v.hidden = 0",
            [(int)$asset_id], 'asset', (int)$asset_id
        ));
    }
    return $rows;
}

/*************************************************************
 * FUNCTION: GET VULNERABILITY CONNECTIVITY FOR RISK         *
 *************************************************************/
/**
 * Vulnerabilities triaged into this risk. Takes the RAW db risk id.
 *
 * `v.hidden = 0` excludes dismissed findings — see
 * get_vulnerability_connectivity_for_asset() above.
 */
function get_vulnerability_connectivity_for_risk($risk_id)
{
    $schema = vulnmgmt_graph_platform_schema();
    $rows = [];
    foreach (vulnmgmt_graph_active_platforms() as $platform) {
        $label = $schema[$platform]['label_column'];
        $rows = array_merge($rows, vulnmgmt_graph_rows(
            $platform,
            "SELECT v.id, v.`{$label}` AS label
             FROM `vulnmgmt_{$platform}_vulnerabilities` v
             WHERE v.simplerisk_risk_id = ? AND v.hidden = 0",
            [(int)$risk_id], 'risk', (int)$risk_id
        ));
    }
    return $rows;
}

/*************************************************************
 * FUNCTION: GET ASSET CONNECTIVITY FOR VULNERABILITY        *
 *************************************************************/
/**
 * The SimpleRisk assets this vulnerability was found on. $platform is
 * re-validated against the ACTIVE list here (not just the allow-list) because
 * this is a public entry point reachable from a parsed node id.
 *
 * This walker starts FROM the vulnerability, so `v.hidden = 0` is joined in
 * to reject a dismissed focal too, not just a dismissed neighbor: a triager
 * who dismissed the finding must get no neighbors back, matching the rule
 * that a dismissed finding is not a live node in the graph in either
 * direction.
 */
function get_asset_connectivity_for_vulnerability($platform, $id)
{
    if (!in_array($platform, vulnmgmt_graph_active_platforms(), true)) {
        return [];
    }
    return graph_edge_rows(
        "SELECT DISTINCT a.id, a.name
         FROM `vulnmgmt_{$platform}_vulnerabilities_to_assets` vta
         JOIN `vulnmgmt_{$platform}_vulnerabilities` v ON v.id = vta.vulnerability_id
         JOIN `vulnmgmt_assets` va ON va.uuid = vta.asset_id
         JOIN assets a ON a.id = va.simplerisk_asset_id
         WHERE vta.vulnerability_id = ? AND a.verified = 1 AND v.hidden = 0",
        [(int)$id], 'asset', 'vulnerability', "{$platform}_" . (int)$id,
        'id', 'name', true
    );
}

/*************************************************************
 * FUNCTION: GET RISK CONNECTIVITY FOR VULNERABILITY         *
 *************************************************************/
/**
 * The risk this vulnerability was triaged into. Same dismissed-focal rule as
 * get_asset_connectivity_for_vulnerability() above: `v.hidden = 0` guards the
 * FOCAL row here (v.id = ?), so a dismissed finding yields no risk neighbor
 * either.
 */
function get_risk_connectivity_for_vulnerability($platform, $id)
{
    if (!in_array($platform, vulnmgmt_graph_active_platforms(), true)) {
        return [];
    }
    return graph_risk_edge_rows(
        "SELECT DISTINCT r.id, r.subject, rs.calculated_risk
         FROM `vulnmgmt_{$platform}_vulnerabilities` v
         JOIN risks r ON r.id = v.simplerisk_risk_id
         LEFT JOIN risk_scoring rs ON rs.id = r.id
         WHERE v.id = ? AND v.hidden = 0",
        [(int)$id], 'vulnerability', "{$platform}_" . (int)$id
    );
}

/******************************************************************
 * FUNCTION: CONNECTIVITY VISUALIZER ASSOCIATIONS VULNERABILITY   *
 ******************************************************************/
/**
 * A vulnerability focal: its neighbors are the assets it was found on and the
 * risk it was triaged into. Both are also its L4 visibility parents (see
 * ai_context_drop_orphaned()'s $parentTypes list).
 */
function connectivity_visualizer_associations_vulnerability($platform, $id)
{
    $asset_associations = [];
    $risk_associations  = [];

    if (!is_null($id) && in_array($platform, vulnmgmt_graph_active_platforms(), true))
    {
        $asset_associations = get_asset_connectivity_for_vulnerability($platform, $id);
        $risk_associations  = get_risk_connectivity_for_vulnerability($platform, $id);
    }

    return [
        "assets" => $asset_associations,
        "risks"  => $risk_associations,
    ];
}
