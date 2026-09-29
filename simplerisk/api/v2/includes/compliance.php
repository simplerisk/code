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

function api_v2_compliance_all_audits_datatable() {
    api_v2_check_permission("compliance");
    datatable_response_for_view('all_audits');
}

// Upper bound on how many ids "Select all N" (compliance.php's Manage
// Audits inline script) will ever resolve in one request. The only bulk
// action on this page (Delete) loops one HTTP request per selected id --
// not one atomic bulk write -- so an unbounded resolved set would mean
// that many sequential requests. Same 2000-row reasoning as the SR-2234
// "Select all N" rollout's other pages, tracked separately per page rather
// than as a shared constant since those pages are on their own branches.
const MANAGE_AUDITS_SELECT_ALL_MAX = 2000;

/*******************************************************************************
 * FUNCTION: MANAGE AUDITS SELECT ALL SQL ORDERABLE                            *
 * Pure mirror of get_data_for_datatable()'s (includes/functions.php) own      *
 * $sql_orderable computation for a single already-resolved order column's     *
 * catalog settings -- encrypted columns can't be ordered in SQL unless the    *
 * Encrypted Database Extra is inactive, and force_php_ordering always routes  *
 * ordering through PHP regardless of encryption. Extracted to a standalone,   *
 * side-effect-free function (rather than left inline) so the encrypted/       *
 * force_php_ordering branches -- which flip $sql_paging_eligible to false --  *
 * have a regression test independent of any DB/encryption fixture.           *
 *******************************************************************************/
function manage_audits_select_all_sql_orderable(array $order_column_settings, bool $encryption_enabled): bool
{
    return (!$encryption_enabled || empty($order_column_settings['encrypted']))
        && (!array_key_exists('force_php_ordering', $order_column_settings) || !$order_column_settings['force_php_ordering']);
}

/*******************************************************************************
 * FUNCTION: MANAGE AUDITS SELECT ALL IDS EXCEED CAP                           *
 * Pure predicate for api_v2_compliance_audits_filtered_ids()'s cap check --   *
 * whether the resolved id set is larger than MANAGE_AUDITS_SELECT_ALL_MAX.    *
 * Inclusive of the cap itself (exactly MANAGE_AUDITS_SELECT_ALL_MAX ids does  *
 * NOT exceed it). Extracted to a standalone, side-effect-free function (same  *
 * shape as manage_audits_select_all_sql_orderable() above) so the boundary    *
 * has a regression test independent of any DB fixture.                       *
 *******************************************************************************/
function manage_audits_select_all_ids_exceed_cap(array $ids): bool
{
    return count($ids) > MANAGE_AUDITS_SELECT_ALL_MAX;
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE AUDITS FILTERED IDS                              *
 * POST /compliance/audits/filtered_ids -- "Select all N" (Manage Audits):     *
 * resolves every audit id matching the current status chip + toolbar filters  *
 * across every page -- via the exact same get_data_for_datatable() engine     *
 * (includes/functions.php) the three status-chip datatable endpoints above    *
 * already use, forced to length=-1, so the resolved set can never disagree    *
 * with what the grid shows for the SAME status chip. `status` selects which   *
 * of the three views' SQL WHERE (get_wheres_for_view()) to scope to -- it is  *
 * validated strictly (no silent default) since resolving against the wrong    *
 * scope could hand a bulk Delete more ids than the viewer ever saw.           *
 *******************************************************************************/
function api_v2_compliance_audits_filtered_ids()
{
    api_v2_check_permission("compliance");

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $view = match ($body['status'] ?? '') {
        'active' => 'active_audits',
        'past' => 'past_audits',
        'all' => 'all_audits',
        default => null,
    };
    if ($view === null) {
        api_v2_json_result(400, "BAD REQUEST: status must be one of active, past, all.", null);
        return;
    }

    global $field_settings, $field_settings_views;

    // Same view_type/selected_fields resolution datatable_response_for_view()
    // (includes/api.php) uses -- $view_type from the REQUESTED (unaliased)
    // view (it drives the real SQL WHERE via get_wheres_for_view($view)
    // inside get_data_for_datatable()), $selected_fields aliased to
    // 'all_audits' for active/past (see that function's own comment for why
    // all three chips must resolve the identical column set).
    $view_type = $field_settings_views[$view]['view_type'];
    $selected_fields = display_settings_get_display_settings_for_view(
        in_array($view, ['active_audits', 'past_audits'], true) ? 'all_audits' : $view
    );

    $column_filters = merge_out_of_band_column_filters(
        [],
        $body['audits_column_filters'] ?? null,
        $field_settings[$view_type] ?? []
    );

    $global_search = isset($body['search']) && is_scalar($body['search']) ? (string) $body['search'] : '';
    $test_date_range = isset($body['audits_test_date_range']) && is_scalar($body['audits_test_date_range']) ? (string) $body['audits_test_date_range'] : '';

    // get_data_for_datatable() only applies a SQL-level LIMIT when its own
    // $sql_paging eligibility holds (no column/global/date-range filtering AND
    // $sql_orderable -- see that function's $sql_paging computation,
    // includes/functions.php). Mirror that same condition here, including the
    // $sql_orderable conjunct: with no filters and an orderable sort column,
    // request one more row than the cap so the DB does the early bound and the
    // count($ids) check below can short-circuit before any row is
    // decrypted/formatted. With any filter present, or an unorderable sort
    // column, $sql_paging is false there regardless of $length, so a length
    // cap would silently TRUNCATE the PHP-filtered/ordered result instead of
    // bounding the SQL fetch -- request everything (-1) so the cap check below
    // sees the true filtered count.
    //
    // The sort column here is always the literal 'id' passed to
    // get_data_for_datatable() below, so $sql_orderable reduces to that
    // function's own encrypted/force_php_ordering check for the 'id' field --
    // replicated here rather than hardcoded true, so a future catalog change
    // marking 'id' encrypted or force_php_ordering for this view_type flips
    // this eligibility the same way it would flip get_data_for_datatable()'s.
    $order_column_settings = $field_settings[$view_type]['id'] ?? [];
    $sql_orderable = manage_audits_select_all_sql_orderable($order_column_settings, encryption_extra());
    $sql_paging_eligible = empty($column_filters) && $global_search === '' && empty($test_date_range) && $sql_orderable;
    $length = $sql_paging_eligible ? (MANAGE_AUDITS_SELECT_ALL_MAX + 1) : -1;

    $data = get_data_for_datatable($view, $selected_fields, 0, $length, 'id', 'ASC', $column_filters, $global_search, $test_date_range);

    $ids = array_map(static fn ($row) => (int) $row['id'], $data['rows']);

    if (manage_audits_select_all_ids_exceed_cap($ids)) {
        api_v2_json_result(400, select_all_too_many_matches_message(MANAGE_AUDITS_SELECT_ALL_MAX, 'Audits'), null);
        return;
    }

    api_v2_json_result(200, "SUCCESS", ['ids' => $ids, 'total' => count($ids)]);
}

/*******************************************************************************
 * FUNCTION: MANAGE AUDITS NORMALIZE BATCH DELETE IDS                          *
 * Normalises the batch-delete endpoint's POSTed `ids` array into a capped     *
 * list of positive integer audit ids. Same contract/shape as governance.php's *
 * normalize_bulk_approve_ids(): drops any member that isn't a scalar decimal- *
 * digit string (a nested-array member would otherwise reach the (string)      *
 * cast and raise "Array to string conversion"), and caps the result at        *
 * MANAGE_AUDITS_SELECT_ALL_MAX so one authorized request can't drive an       *
 * unbounded number of deletes. Kept as its own function -- rather than reused *
 * -- because normalize_bulk_approve_ids()'s cap is a fixed module constant     *
 * (GOVERNANCE_MAX_BULK_APPROVE_IDS), not a parameter, and this endpoint needs *
 * the audits-specific MANAGE_AUDITS_SELECT_ALL_MAX (2000) instead. $truncated *
 * is set true only when a real (numeric, positive) id was dropped by the cap  *
 * -- never for junk that was filtered out anyway -- so the batch response can *
 * tell "you sent exactly the cap" apart from "you sent more than the cap and  *
 * some were silently never touched". Pure: no session, no DB -- directly      *
 * unit-testable.                                                              *
 *******************************************************************************/
function manage_audits_normalize_batch_delete_ids($raw_ids, &$truncated = null): array
{
    // Thin wrapper: the actual sanitize/cap/truncate-signal logic lives once in
    // the shared normalize_bulk_ids() (includes/functions.php) -- see that
    // function's docblock. This wrapper just supplies this endpoint's own
    // fixed cap constant, keeping the existing name and call site unchanged.
    return normalize_bulk_ids($raw_ids, MANAGE_AUDITS_SELECT_ALL_MAX, $truncated);
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE AUDITS BATCH DELETE                             *
 * POST /compliance/audits/batch-delete -- "Select all N" (Manage Audits):     *
 * deletes every audit id in the POSTed `ids` array in ONE request instead of  *
 * the client looping one POST /compliance/delete_audit per id (up to         *
 * MANAGE_AUDITS_SELECT_ALL_MAX = 2000 ids from a single "Select all N"        *
 * click, which used to mean that many sequential/batched HTTP requests).      *
 *                                                                             *
 * Runs the EXACT SAME authorization deleteTestAuditResponse() (includes/      *
 * api.php) runs for a single delete -- just applied per id instead of once:   *
 *   1. "compliance" permission + $_SESSION['delete_audits'] -- neither is     *
 *      id-dependent, so this is checked once for the whole request, exactly   *
 *      as the single-delete endpoint checks it once per its one id.          *
 *   2. check_access_for_audit() (includes/functions.php) -- the SAME object-  *
 *      level gate the /compliance/audits/{id} CRUD delete route              *
 *      (deleteAuditById()) already uses, and functionally identical to       *
 *      deleteTestAuditResponse()'s own inline team-separation check (both    *
 *      reduce to is_user_allowed_to_access($_SESSION['uid'], $id, 'audit')   *
 *      once team_separation_extra() is on) -- is re-run for EVERY id         *
 *      individually. An id that fails is skipped and counted as denied,      *
 *      never silently dropped and never allowed to abort the rest of the     *
 *      batch. This is the check that must not be "run once for the batch":   *
 *      it depends on the specific audit's control owner/tester/stakeholders/ *
 *      team, which varies per id.                                            *
 *******************************************************************************/
function api_v2_compliance_audits_batch_delete()
{
    api_v2_check_permission("compliance");

    // Mirrors deleteTestAuditResponse()'s own gate exactly -- "compliance" alone
    // is necessary but not sufficient; delete_audits is the module's specific
    // delete permission.
    if (!isset($_SESSION["delete_audits"]) || $_SESSION["delete_audits"] != 1) {
        api_v2_json_result(403, "FORBIDDEN: The user does not have the required permission to perform this action.", null);
        return;
    }

    if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
        api_v2_json_result(400, "BAD REQUEST: ids must be a non-empty array.", null);
        return;
    }

    $truncated = false;
    $ids = manage_audits_normalize_batch_delete_ids($_POST['ids'], $truncated);

    // Permission was already checked once above (not id-dependent) -- the
    // shared run_batch_mutation() (includes/api.php) still takes a
    // $checkPermission closure, but here it's a no-op that always allows,
    // reproducing this loop's original shape exactly: no per-id permission
    // check, only the per-id object-level check below.
    $denied_ids = [];
    $result = run_batch_mutation(
        $ids,
        fn($id) => true,
        function ($id) use (&$denied_ids) {
            // Per-id object-level check -- see the function docblock above. Never
            // checked "once for the batch": every id gets its own evaluation.
            if (!check_access_for_audit($id)) {
                $denied_ids[] = $id;
                return false;
            }
            return true;
        },
        fn($id) => delete_test_audit($id)
    );

    api_v2_json_result(200, "SUCCESS", [
        'deleted' => $result['processed'],
        'denied' => count($denied_ids),
        'denied_ids' => $denied_ids,
        'truncated' => $truncated,
        'limit' => MANAGE_AUDITS_SELECT_ALL_MAX,
    ]);
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE AUDITS FILTER COUNTS                            *
 * GET /compliance/audits/filter_counts?status=active|past|all -- per-option    *
 * counts for Manage Audits' 6 quickfilters, scoped to the requested status    *
 * chip (defaults to 'active'). Backs get_all_audits_filter_counts()           *
 * (includes/compliance.php), which mirrors the same status/team scoping       *
 * get_data_for_datatable() itself uses.                                       *
 *******************************************************************************/
function api_v2_compliance_audits_filter_counts() {
    api_v2_check_permission("compliance");

    $status = in_array($_GET['status'] ?? '', ['active', 'past', 'all'], true) ? $_GET['status'] : 'active';

    $data = get_all_audits_filter_counts($status);

    api_v2_json_result(200, "OK", $data);
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

// Upper bound on how many ids "Select all N" (compliance-define-tests.js)
// will ever resolve in one request. Retire/Delete both loop client-side,
// firing one HTTP request per selected id -- not one atomic bulk write --
// so a resolved set this large would mean that many sequential requests.
// Same cap and reasoning as the parallel "Select all N" work on Review Risk
// (REVIEW_RISK_SELECT_ALL_MAX, includes/api.php, SR-2234-family) -- these are
// sibling in-progress branches, not both merged into development yet.
const DEFINE_TESTS_SELECT_ALL_MAX = 500;

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE TESTS GRID FILTERED IDS                          *
 * POST /compliance/tests_grid/filtered_ids -- "Select all N" (Define Tests):  *
 * resolves every REAL test id matching the current filter set across every    *
 * page, not just one page's worth -- via the exact same build_tests_grid()    *
 * pipeline (includes/compliance_grid.php) the paginated grid above uses,      *
 * forced to length=-1, so the two can never disagree about which tests match. *
 *******************************************************************************/
function api_v2_compliance_tests_grid_filtered_ids()
{
    // Check that this user has the ability to view compliance
    api_v2_check_permission("compliance");

    // Same JSON-body-with-form-fallback convention as api_v2_compliance_tests_grid() above.
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $filters = parse_grid_request($body);
    $filters['start'] = 0;
    $filters['length'] = -1;
    $result = build_tests_grid($filters);

    $ids = flatten_tests_grid_ids($result);

    if (count($ids) > DEFINE_TESTS_SELECT_ALL_MAX) {
        api_v2_json_result(400, select_all_too_many_matches_message(DEFINE_TESTS_SELECT_ALL_MAX, 'Tests'), null);
        return;
    }

    api_v2_json_result(200, "SUCCESS", ['ids' => $ids, 'total' => count($ids)]);
}

/*******************************************************************************
 * FUNCTION: DEFINE TESTS NORMALIZE BATCH IDS                                   *
 * Sanitizes the `ids` array POSTed to the batch retire/delete endpoints below  *
 * to a list of positive ints, dropping anything that isn't a plausible id (an  *
 * int or numeric string) -- same is_int()/is_string() guard as                *
 * normalize_bulk_approve_ids() (includes/governance.php) and this file's own  *
 * manage_audits_normalize_batch_delete_ids() above, so a nested-array member   *
 * can't reach the (string) cast and raise "Array to string conversion". Capped *
 * to DEFINE_TESTS_SELECT_ALL_MAX -- the same bound the "Select all N" resolver *
 * above enforces on the id list it hands back, so a caller posting straight to *
 * this endpoint (bypassing filtered_ids) can't drive unbounded per-id work.    *
 * $truncated reports whether the cap actually dropped a real id, same contract *
 * as manage_audits_normalize_batch_delete_ids(). Pure: no session, no DB.      *
 *******************************************************************************/
function define_tests_normalize_batch_ids($raw_ids, &$truncated = null): array
{
    // Thin wrapper: the actual sanitize/cap/truncate-signal logic lives once in
    // the shared normalize_bulk_ids() (includes/functions.php) -- see that
    // function's docblock. This wrapper just supplies this endpoint's own
    // fixed cap constant, keeping the existing name and call sites unchanged.
    return normalize_bulk_ids($raw_ids, DEFINE_TESTS_SELECT_ALL_MAX, $truncated);
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE TESTS BATCH RETIRE                               *
 * POST /compliance/tests/batch-retire -- "Select all N" (Define Tests):        *
 * retires every id in the POSTed `ids` array in ONE request instead of the     *
 * client firing one POST /compliance/tests/{id}/retire per id (up to           *
 * DEFINE_TESTS_SELECT_ALL_MAX = 500 ids from a single "Select all N" click,    *
 * which previously meant that many batched-25-at-a-time requests --            *
 * compliance-define-tests.js's runSequential()/runBulkAction()).               *
 *                                                                               *
 * Runs the EXACT SAME authorization retireTestById() (includes/api.php) runs   *
 * for a single retire -- just applied per id instead of once:                  *
 *   1. can_retire_tests() (edit_tests OR delete_tests) -- not id-dependent, so  *
 *      this is checked once for the whole request, exactly as the single-      *
 *      retire endpoint checks it once per its one id.                         *
 *   2. check_access_for_test($id) (includes/functions.php) is re-run for EVERY *
 *      id individually -- an id that fails this object-level check (Team      *
 *      Separation active, caller not on the test's team) is skipped and       *
 *      counted as denied, never silently dropped and never allowed to abort   *
 *      the rest of the batch. This is the check that must not be "run once    *
 *      for the batch": it depends on the specific test's control owner/       *
 *      tester/stakeholders/team, which varies per id.                         *
 *   3. get_framework_control_test_by_id($id) existence check -- an id with no  *
 *      matching row (already deleted by another request, a stale client-side  *
 *      selection) is skipped and counted as failed rather than 404ing the     *
 *      whole batch.                                                           *
 *******************************************************************************/
function api_v2_compliance_tests_batch_retire()
{
    if (!can_retire_tests()) {
        api_v2_json_result(403, "FORBIDDEN: The user does not have the required permission to perform this action.", null);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    if (empty($body['ids']) || !is_array($body['ids'])) {
        api_v2_json_result(400, "BAD REQUEST: ids must be a non-empty array.", null);
        return;
    }

    $truncated = false;
    $ids = define_tests_normalize_batch_ids($body['ids'], $truncated);

    // Permission was already checked once above (not id-dependent) -- see
    // api_v2_compliance_audits_batch_delete()'s identical comment on the
    // always-true $checkPermission closure.
    $denied_ids = [];
    $failed_ids = [];
    $result = run_batch_mutation(
        $ids,
        fn($id) => true,
        function ($id) use (&$denied_ids) {
            // Per-id object-level check -- see the function docblock above. Never
            // checked "once for the batch": every id gets its own evaluation.
            if (!check_access_for_test($id)) {
                $denied_ids[] = $id;
                return false;
            }
            return true;
        },
        fn($id) => retire_framework_control_test($id),
        function ($id) use (&$failed_ids) {
            $test = get_framework_control_test_by_id($id);
            if (empty($test['id'])) {
                $failed_ids[] = $id;
                return false;
            }
            return true;
        }
    );

    api_v2_json_result(200, "SUCCESS", [
        'processed' => $result['processed'],
        'denied' => count($denied_ids),
        'denied_ids' => $denied_ids,
        'failed' => count($failed_ids),
        'failed_ids' => $failed_ids,
        'total' => count($ids),
        'truncated' => $truncated,
        'limit' => DEFINE_TESTS_SELECT_ALL_MAX,
    ]);
}

/*******************************************************************************
 * FUNCTION: API V2 COMPLIANCE TESTS BATCH DELETE                               *
 * POST /compliance/tests/batch-delete -- "Select all N" (Define Tests):        *
 * deletes every id in the POSTed `ids` array in ONE request instead of the     *
 * client firing one DELETE /compliance/tests/{id} per id. Same shape and same  *
 * reasoning as api_v2_compliance_tests_batch_retire() above -- see that        *
 * function's docblock -- except the base gate is the single "delete_tests"     *
 * permission deleteTestById() (includes/api.php) checks, and the mutation is   *
 * delete_framework_control_test() instead of retire_framework_control_test().  *
 * The per-id check_access_for_test() + existence check are IDENTICAL to the    *
 * retire endpoint's: they are what deleteTestById() itself runs per id.        *
 *******************************************************************************/
function api_v2_compliance_tests_batch_delete()
{
    api_v2_check_permission("delete_tests");

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    if (empty($body['ids']) || !is_array($body['ids'])) {
        api_v2_json_result(400, "BAD REQUEST: ids must be a non-empty array.", null);
        return;
    }

    $truncated = false;
    $ids = define_tests_normalize_batch_ids($body['ids'], $truncated);

    // Permission was already checked once above (not id-dependent) -- see
    // api_v2_compliance_audits_batch_delete()'s identical comment on the
    // always-true $checkPermission closure.
    $denied_ids = [];
    $failed_ids = [];
    $result = run_batch_mutation(
        $ids,
        fn($id) => true,
        function ($id) use (&$denied_ids) {
            // Per-id object-level check -- see api_v2_compliance_tests_batch_retire()'s
            // docblock above. Never checked "once for the batch": every id gets its
            // own evaluation.
            if (!check_access_for_test($id)) {
                $denied_ids[] = $id;
                return false;
            }
            return true;
        },
        fn($id) => delete_framework_control_test($id),
        function ($id) use (&$failed_ids) {
            $test = get_framework_control_test_by_id($id);
            if (empty($test['id'])) {
                $failed_ids[] = $id;
                return false;
            }
            return true;
        }
    );

    api_v2_json_result(200, "SUCCESS", [
        'processed' => $result['processed'],
        'denied' => count($denied_ids),
        'denied_ids' => $denied_ids,
        'failed' => count($failed_ids),
        'failed_ids' => $failed_ids,
        'total' => count($ids),
        'truncated' => $truncated,
        'limit' => DEFINE_TESTS_SELECT_ALL_MAX,
    ]);
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
