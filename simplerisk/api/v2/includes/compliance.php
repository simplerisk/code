<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/api.php')); // datatable_response_for_view()
require_once(realpath(__DIR__ . '/../../../includes/compliance.php'));
require_once(realpath(__DIR__ . '/../../../includes/compliance_grid.php')); // parse_grid_request(), build_tests_grid()
require_once(realpath(__DIR__ . '/../../../includes/governance.php')); // get_mapping_control_frameworks(), get_framework_control()
// get_control_connectivity_for_test(), get_results_connectivity_for_test().
// These moved out of includes/reporting.php into entity_graph.php. They are
// reachable transitively today only because api/v2/index.php happens to load
// governance.php/risks.php (which require entity_graph.php directly) before
// this file -- an include reorder would turn these calls into a fatal
// "Call to undefined function", and Phan cannot see it. Declared directly per
// CLAUDE.md's function-reachability rule.
require_once(realpath(__DIR__ . '/../../../includes/entity_graph.php'));
// ai_context_search_visible_ids() -- the L4/Team-Separation record filter
// applied to the test_results bucket below -- is defined here. Declared
// directly for the same function-reachability reason as entity_graph.php
// above.
require_once(realpath(__DIR__ . '/../../../includes/ai_context_graph.php'));

require_once(language_file());

/*************************************
 * FUNCTION: API V2 COMPLIANCE TESTS *
 * ***********************************/
function api_v2_compliance_tests()
{
    // Check that this user has the ability to view governance
    api_v2_check_permission("compliance");

    // Get the framework id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this test id
        if (check_access_for_test($id))
        {
            // Get just the test with that id
            $test = get_framework_control_test_by_id($id);

            // If the test value returned is empty then we are unable to find a test with that id
            if (empty($test))
            {
                // Set the status
                $status_code = 204;
                $status_message = "NO CONTENT: Unable to find a test with the specified id.";
                $data = null;
            }
            else
            {
                // Set the status
                $status_code = 200;
                $status_message = "SUCCESS";

                // Create the data array
                $data = [
                    "test" => $test,
                ];
            }
        }
        // If the user should not have access to this test id
        else
        {
            // Set the status
            $status_code = 403;
            $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";
            $data = null;
        }
    }
    // Otherwise, return all tests
    else
    {
        // Get the tests array
        $tests = get_audit_tests("test_name");

        // Create the data array
        $data = [
            "tests" => $tests,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***************************************
 * FUNCTION: API V2 TESTS ASSOCIATIONS *
 * *************************************/
function api_v2_compliance_tests_associations()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this test id
        if (check_access_for_test($id))
        {
            // Get the connectivity for the control
            $test_result_associations = get_results_connectivity_for_test($id);

            // The "controls" bucket additionally requires the governance
            // domain permission -- holding "compliance" alone does not grant
            // control visibility (mirrors ai_context_type_permission()'s
            // 'control' => 'governance' mapping used by the /ai/context
            // bundle, and the same graph_bucket_if_permitted() gate already
            // applied to the sibling association endpoints in governance.php
            // and risks.php).
            $control_associations = graph_bucket_if_permitted("governance", fn() => get_control_connectivity_for_test($id));

            // L4 (Team Separation) record filter. get_results_connectivity_for_test()
            // has no L4 awareness of its own: a test result's visibility is
            // inherited from the audit that produced it (ai_context_graph.php's
            // 'test_result' case in ai_context_visible_ids_for_type()), and this
            // endpoint calls the walker directly rather than through the
            // /ai/context orchestrator (which applies its own L4 pass over the
            // assembled node set) -- so without this, a caller who may see this
            // test but not one of its audits is still handed that audit's
            // result, verdict and date.
            if (!empty($test_result_associations)) {
                $visibleTestResultIds = ai_context_search_visible_ids('test_result', array_column($test_result_associations, 'test_result_id'));
                $test_result_associations = graph_filter_rows_by_visible_ids($test_result_associations, 'test_result_id', $visibleTestResultIds);
            }

            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "test_results" => $test_result_associations,
                "controls" => $control_associations,
            ];
        }
        // If the user should not have access to this test id
        else
        {
            // Set the status
            $status_code = 403;
            $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";
            $data = null;
        }
    }
    // Otherwise, return an empty data array
    else
    {
        // Create the data array
        $data = [];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/**********************************************
 * FUNCTION: API V2 COMPLIANCE TESTS TAGS GET *
 * ********************************************/
function api_v2_compliance_tests_tags_get()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // Open a database connection
    $db = db_open();

    // If we received an id
    if (!empty($id))
    {
        // Get just the tag with that id
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fct.id ORDER BY fct.id ASC) as test_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_tests` fct ON fct.id=tt.taggee_id WHERE tt.type='test' AND t.id=:id GROUP BY t.id;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a tag with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the test_ids string into an array
                $tags[$key]['test_ids'] = explode(',', $tag['test_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each test id
                    foreach ($tags[$key]['test_ids'] as $test_id)
                    {
                        // If the user should not have access to this test id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test'))
                        {
                            // Remove it from the array
                            $tags[$key]['test_ids'] = array_diff($tags[$key]['test_ids'], [$test_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }
    // Otherwise, return all tags
    else
    {
        // Get the list of tags and associated risks
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fct.id ORDER BY fct.id ASC) as test_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_tests` fct ON fct.id=tt.taggee_id WHERE tt.type='test' GROUP BY t.id;");
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: No tags found.";
            $data = null;
        }
        else
        {
            // Return the result
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the test_ids string into an array
                $tags[$key]['test_ids'] = explode(',', $tag['test_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each asset id
                    foreach ($tags[$key]['test_ids'] as $test_id)
                    {
                        // If the user should not have access to this test id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $test_id, 'test'))
                        {
                            // Remove it from the array
                            $tags[$key]['test_ids'] = array_diff($tags[$key]['test_ids'], [$test_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/***********************************************
 * FUNCTION: API V2 COMPLIANCE AUDITS TAGS GET *
 * *********************************************/
function api_v2_compliance_audits_tags_get()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // Open a database connection
    $db = db_open();

    // If we received an id
    if (!empty($id))
    {
        // Get just the tag with that id
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fcta.id ORDER BY fcta.id ASC) as audit_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_test_audits` fcta ON fcta.id=tt.taggee_id WHERE tt.type='test_audit' AND t.id=:id GROUP BY t.id;");
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find a tag with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the audit_ids string into an array
                $tags[$key]['audit_ids'] = explode(',', $tag['audit_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each test id
                    foreach ($tags[$key]['audit_ids'] as $audit_id)
                    {
                        // If the user should not have access to this audit id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $audit_id, 'audit'))
                        {
                            // Remove it from the array
                            $tags[$key]['audit_ids'] = array_diff($tags[$key]['audit_ids'], [$audit_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }
    // Otherwise, return all tags
    else
    {
        // Get the list of tags and associated risks
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT fcta.id ORDER BY fcta.id ASC) as audit_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `framework_control_test_audits` fcta ON fcta.id=tt.taggee_id WHERE tt.type='test_audit' GROUP BY t.id;");
        $stmt->execute();
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If the tags returned is empty then we are unable to find a tag with that id
        if (empty($tags))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: No tags found.";
            $data = null;
        }
        else
        {
            // Return the result
            $status_code = 200;
            $status_message = "SUCCESS";

            // For each tag returned
            foreach ($tags as $key => $tag)
            {
                // Convert the audit_ids string into an array
                $tags[$key]['audit_ids'] = explode(',', $tag['audit_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each asset id
                    foreach ($tags[$key]['audit_ids'] as $audit_id)
                    {
                        // If the user should not have access to this audit id
                        if (!is_user_allowed_to_access($_SESSION['uid'], $audit_id, 'audit'))
                        {
                            // Remove it from the array
                            $tags[$key]['audit_ids'] = array_diff($tags[$key]['audit_ids'], [$audit_id]);
                        }
                    }
                }
            }

            // Create the data array
            $data = [
                "tags" => $tags,
            ];
        }
    }

    // Close the database connection
    db_close($db);

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/*******************************************************************************
 * FUNCTIONS: COMPLIANCE AUDIT DATATABLE FEEDS                                  *
 * Server-side DataTables feeds for the audit views, each gated on the          *
 * `compliance` module permission (SR-1721). One route per view so the view is  *
 * hardcoded — a compliance-gated route cannot be tricked into serving another  *
 * module's view via a `?view=` param. datatable_response_for_view() (core in   *
 * includes/api.php, loaded in the v2 request) builds the response.             *
 *******************************************************************************/
function api_v2_compliance_active_audits_datatable() {
    api_v2_check_permission("compliance");
    datatable_response_for_view('active_audits');
}

function api_v2_compliance_past_audits_datatable() {
    api_v2_check_permission("compliance");
    datatable_response_for_view('past_audits');
}

function api_v2_compliance_dynamic_audit_report_datatable() {
    api_v2_check_permission("compliance");
    datatable_response_for_view('dynamic_audit_report');
}

function api_v2_compliance_audit_timeline_datatable() {
    api_v2_check_permission("compliance");
    datatable_response_for_view('audit_timeline');
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE TESTS GRID                                       *
 * POST /compliance/tests_grid -- Define Tests redesign grid data feed          *
 * (Phase 1, Task 4). Parses the JSON request body via parse_grid_request()     *
 * and delegates to build_tests_grid() (includes/compliance_grid.php) for the   *
 * query + enrichment + filtering + pagination.                                 *
 *******************************************************************************/
function api_v2_compliance_tests_grid()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // The grid filters arrive as a JSON body, not $_POST -- fall back to
    // $_POST for form-encoded callers/tests (same pattern as
    // getSchedulePreviewResponse()).
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $filters = parse_grid_request($body);
    $result = build_tests_grid($filters);

    // Return the result
    api_v2_json_result(200, "SUCCESS", $result);
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE CONTROL MAPPINGS                                 *
 * GET /compliance/control_mappings?control_id= -- the framework mappings for  *
 * a single control (Define Tests redesign's SCF expand). Reuses the existing   *
 * get_mapping_control_frameworks() (includes/governance.php).                  *
 *******************************************************************************/
function api_v2_compliance_control_mappings()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    $control_id = (int) get_param("GET", "control_id", 0);

    if ($control_id <= 0) {
        api_v2_json_result(400, "BAD REQUEST: control_id is required.", null);
        return;
    }

    $control = get_framework_control($control_id);

    if (empty($control)) {
        api_v2_json_result(404, "NOT FOUND: Unable to find a control with the specified id.", null);
        return;
    }

    $mapping_rows = get_mapping_control_frameworks($control_id);

    // framework_id comes along so the panel can GROUP by framework rather than
    // rendering one flat list -- two frameworks can share a display name, and
    // grouping on the name alone would silently merge them.
    // get_mapping_control_frameworks() already orders by framework name then
    // reference, so the grouped order is stable without a second sort.
    $mappings = array_map(static function ($row) {
        return [
            'framework_id' => (int) ($row['framework_id'] ?? 0),
            'framework_name' => $row['framework_name'],
            'reference_name' => $row['reference_name'],
            'reference_text' => $row['reference_text'],
        ];
    }, $mapping_rows);

    $data = [
        'description' => purify_rich_text_output($control['description'] ?? ''),
        'mappings' => $mappings,
    ];

    // Return the result
    api_v2_json_result(200, "SUCCESS", $data);
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE CONTROL ROSTER                                   *
 * GET /compliance/control_roster -- the lightweight id/control_number/         *
 * short_name list for every non-deleted control (Define Tests redesign's       *
 * Add-Test modal control <select>). Deliberately a plain SELECT with no        *
 * test/last-result/tag enrichment -- unlike build_tests_grid() (which the      *
 * roster used to piggyback on via a length=-1 request), this never needs to    *
 * enrich or paginate, so it stays a single cheap query even as the control     *
 * count grows.                                                                 *
 *                                                                              *
 * Each control also carries the two ids the picker narrows by -- `family`      *
 * (one per control) and `frameworks` (a control maps into several) -- so the   *
 * picker's framework and family columns can count and filter client-side       *
 * without a request per facet click. NAMES are not sent: the page already      *
 * renders both lists as <option>s for the grid's own toolbar filters, so       *
 * repeating ~1,500 framework names here would be payload for nothing and a     *
 * second source of truth for a label. That costs one extra query over the      *
 * mappings table, not a join that could fan the control rows out.              *
 *******************************************************************************/
function api_v2_compliance_control_roster()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Open a database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT `id`, `control_number`, `short_name`, `family`, `description`
        FROM `framework_controls`
        WHERE `deleted` = 0
        ORDER BY `control_number`, `id`
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Framework membership, gathered in one pass. Joined to `frameworks` so a
    // mapping pointing at a deleted/inactive framework can't put a facet in the
    // picker that the toolbar's own framework list -- built from
    // getAvailableControlFrameworkList() -- doesn't offer.
    $stmt = $db->prepare("
        SELECT m.`control_id`, m.`framework`
        FROM `framework_control_mappings` m
            INNER JOIN `frameworks` f ON f.`value` = m.`framework`
        ORDER BY m.`control_id`
    ");
    $stmt->execute();
    $mapping_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Grouping/dedupe is pure -- see shape_control_roster()
    // (includes/compliance_grid.php), which is unit-tested without a DB.
    $controls = shape_control_roster($rows, $mapping_rows);

    // Return the result
    api_v2_json_result(200, "SUCCESS", $controls);
}
