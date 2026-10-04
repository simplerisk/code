<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath( __DIR__ . '/../../../includes/assets.php'));
require_once(realpath( __DIR__ . '/../../../includes/assets_list.php'));
require_once(realpath( __DIR__ . '/../../../includes/asset_groups_list.php'));
require_once(realpath( __DIR__ . '/../../../includes/asset_groups_bulk.php'));
require_once(realpath( __DIR__ . '/../../../includes/assets_bulk.php'));
require_once(realpath( __DIR__ . '/../../../includes/assets_write_rules.php'));
require_once(realpath( __DIR__ . '/../../../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/extras.php'));
// get_risk_connectivity_for_asset(). It moved out of includes/reporting.php
// into entity_graph.php. It is reachable transitively today only because
// api/v2/index.php happens to load governance.php/risks.php (which require
// entity_graph.php directly) before this file -- an include reorder would turn
// the call into a fatal "Call to undefined function", and Phan cannot see it.
// Declared directly per CLAUDE.md's function-reachability rule.
require_once(realpath(__DIR__ . '/../../../includes/entity_graph.php'));

require_once(language_file());

/***************************
 * FUNCTION: API V2 ASSETS *
 * *************************/
function api_v2_assets()
{
    // Check that this user has the ability to view assets
    api_v2_check_permission("asset");

    // Get the asset id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // SR-1760: enforce team-based separation on the single-asset lookup.
        // get_asset_by_id() runs an unscoped query, so without this check any
        // asset-permitted user could read any asset cross-team (IDOR). Deny as
        // 204 (identical to not-found) rather than 403 so the endpoint is not an
        // existence/enumeration oracle for cross-team asset ids.
        // check_access_for_asset() is fail-open when the Team Separation Extra is
        // off (returns true), so behavior is unchanged on the base product.
        if (!check_access_for_asset($id))
        {
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find an asset with the specified id.";
            $data = null;
        }
        // Get just the asset with that id
        elseif (empty($asset = get_asset_by_id($id)))
        {
            // Set the status
            $status_code = 204;
            $status_message = "NO CONTENT: Unable to find an asset with the specified id.";
            $data = null;
        }
        else
        {
            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Decrypt encrypted fields
            if (encryption_extra()) {
                $asset['ip'] = try_decrypt($asset['ip']);
                $asset['name'] = try_decrypt($asset['name']);
                $asset['details'] = try_decrypt($asset['details']);
            }

            // Create the data array
            $data = [
                "asset" => $asset,
            ];
        }
    }
    // Otherwise, return all assets
    else
    {
        // Filters, sorting, paging and column selection. With no paging
        // parameters every matching asset is returned (backwards compatible).
        $columns_param = get_param("GET", "columns", null);
        $columns = (is_string($columns_param) && $columns_param !== '') ? array_filter(array_map('trim', explode(',', (string)$columns_param)), 'strlen') : null;

        $result = assets_list(
            [
                'verified' => get_param("GET", "verified", null),
                'q' => get_param("GET", "q", ""),
                'location' => get_param("GET", "location", ""),
                'team' => get_param("GET", "team", ""),
                'tag' => get_param("GET", "tag", ""),
                'group' => get_param("GET", "group", ""),
                'valuation' => get_param("GET", "valuation", ""),
                // Asset Scoring level ids (1 Low, 2 Moderate, 3 High)
                'categorization' => get_param("GET", "categorization", ""),
                'band' => get_param("GET", "band", ""),
                // The stored ratings by filter id (1 Low, 2 Moderate, 3 High;
                // 0 Not applicable, confidentiality only)
                'confidentiality' => get_param("GET", "confidentiality", ""),
                'integrity' => get_param("GET", "integrity", ""),
                'availability' => get_param("GET", "availability", ""),
            ],
            [
                'sort' => get_param("GET", "sort", "name"),
                'dir' => get_param("GET", "dir", "asc"),
                'page' => get_param("GET", "page", 1),
                'per_page' => get_param("GET", "per_page", 0),
                'columns' => $columns,
                // The asset picker (Manage assets, Asset groups tab) asks for
                // per-facet counts alongside the page.
                'facet_counts' => in_array((string)get_param("GET", "facet_counts", ""), ['1', 'true'], true),
            ]
        );

        // Create the data array
        $data = $result;

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
}

/**
 * The columns a user may enable in Manage assets, for the Columns panel.
 * Core labels are resolved from the language file (raw text; the page escapes
 * it once at render); custom-field labels are the raw field names.
 */
function assets_column_settings_payload(array $settings): array
{
    global $lang;
    $available = [];
    foreach (asset_available_columns() as $col) {
        $available[] = [
            'key' => $col['key'],
            'label_key' => $col['label_key'],
            'label' => $col['label_key'] !== null ? ($lang[$col['label_key']] ?? $col['label_key']) : $col['label'],
            'group' => $col['group'],
            'always_on' => $col['key'] === 'name',
        ];
    }
    return [
        'column_settings' => $settings,
        'available_columns' => $available,
        // Only the defaults offered right now (the scoring ones need the upgrade).
        'default_columns' => array_values(array_intersect(asset_default_column_keys(), asset_allowed_column_keys())),
    ];
}

// GET /assets/column-settings -- the caller's own saved Manage assets columns
function assets_column_settings_get()
{
    api_v2_check_permission("asset");
    $uid = (int)$_SESSION['uid'];
    api_v2_json_result(200, "SUCCESS", assets_column_settings_payload(asset_load_column_settings($uid)));
}

// PUT /assets/column-settings -- replaces the caller's own Manage assets columns.
// Body: {"columns": [[key,"1"|"0"],...], "order": [key,...]}. Unknown keys are dropped.
function assets_column_settings_put()
{
    api_v2_check_permission("asset");
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || (!array_key_exists('columns', $body) && !array_key_exists('order', $body))) {
        assets_api_error(400, 'AssetColumnSettingsBodyInvalid');
        return;
    }
    $uid = (int)$_SESSION['uid'];
    try {
        $saved = asset_save_column_settings($uid, $body);
    } catch (RuntimeException $e) {
        // The settings table is missing until the upgrade runs.
        write_debug_log('Saving Manage assets column settings failed: ' . $e->getMessage(), 'error');
        assets_api_error(500, 'AssetColumnSettingsSaveFailed');
        return;
    }
    api_v2_json_result(200, "SUCCESS", assets_column_settings_payload($saved));
}

/**
 * The Asset groups table's column payload, the same shape as
 * assets_column_settings_payload(): its own layout, every column the caller
 * may enable (labels resolved from the language file, raw text) and the
 * defaults offered right now.
 */
function asset_groups_column_settings_payload(array $settings): array
{
    global $lang;
    $available = [];
    foreach (asset_group_available_columns() as $col) {
        $available[] = [
            'key' => $col['key'],
            'label_key' => $col['label_key'],
            'label' => $lang[$col['label_key']] ?? $col['label_key'],
            'group' => 'group',
            'always_on' => $col['key'] === 'name',
        ];
    }
    return [
        'column_settings' => $settings,
        'available_columns' => $available,
        'default_columns' => array_values(array_intersect(asset_group_default_column_keys(), asset_group_allowed_column_keys())),
    ];
}

// GET /asset-groups/column-settings -- the caller's own saved Asset groups columns
function asset_groups_column_settings_get()
{
    api_v2_check_permission("asset");
    $uid = (int)$_SESSION['uid'];
    api_v2_json_result(200, "SUCCESS", asset_groups_column_settings_payload(asset_group_load_column_settings($uid)));
}

// PUT /asset-groups/column-settings -- replaces the caller's own Asset groups columns.
// Body: {"columns": [[key,"1"|"0"],...], "order": [key,...]}. Unknown keys are dropped.
function asset_groups_column_settings_put()
{
    api_v2_check_permission("asset");
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || (!array_key_exists('columns', $body) && !array_key_exists('order', $body))) {
        assets_api_error(400, 'AssetColumnSettingsBodyInvalid');
        return;
    }
    $uid = (int)$_SESSION['uid'];
    try {
        $saved = asset_group_save_column_settings($uid, $body);
    } catch (RuntimeException $e) {
        // The settings column is missing until the upgrade runs.
        write_debug_log('Saving Asset groups column settings failed: ' . $e->getMessage(), 'error');
        assets_api_error(500, 'AssetColumnSettingsSaveFailed');
        return;
    }
    api_v2_json_result(200, "SUCCESS", asset_groups_column_settings_payload($saved));
}

// POST /asset-groups/bulk -- delete many asset groups (the Asset groups tab's selection).
// Body: {"action": "delete", "ids": [int] | "filter": {...}, "expected_count": int?}.
// Same shape, checks and per-id report as POST /assets/bulk; each group is
// deleted through the single-group delete. Needs asset + asset_group_delete.
function asset_groups_bulk_API()
{
    global $escaper, $lang;

    api_v2_check_permission("asset");

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        assets_api_error(400, 'AssetBulkBodyInvalid');
        return;
    }

    $check = asset_groups_bulk_validate_request($body);
    if (!$check['ok']) {
        assets_api_error($check['status'], $check['error'], $check['params'], $check['params']);
        return;
    }

    // The one narrow permission the single delete needs, checked before
    // anything is resolved or touched.
    if (!has_permission(asset_groups_bulk_required_permission($body['action']))) {
        api_v2_json_result(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), null);
        return;
    }

    $ids = asset_groups_bulk_resolve_ids(array_key_exists('ids', $body) ? ['ids' => $body['ids']] : ['filter' => $body['filter']]);
    if (!$ids) {
        assets_api_error(400, 'AssetGroupBulkNoMatch');
        return;
    }
    if (count($ids) > ASSET_GROUPS_BULK_MAX_DELETE) {
        assets_api_error(422, 'AssetGroupBulkTooManyToDelete', ['max' => ASSET_GROUPS_BULK_MAX_DELETE], ['max' => ASSET_GROUPS_BULK_MAX_DELETE]);
        return;
    }
    // A stale "Select all N": the set changed since the client counted it.
    $mismatch = assets_bulk_count_mismatch($body, count($ids));
    if ($mismatch !== null) {
        assets_api_error(409, 'AssetGroupBulkCountMismatch', $mismatch['params'], $mismatch['params']);
        return;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    api_v2_json_result(200, "SUCCESS", asset_groups_bulk_delete($ids));
}

/**
 * A 4xx/5xx answer whose message is translated: `error` in the data is a
 * stable machine code (the language key), status_message is its text in the
 * caller's language, escaped once here (toastr decodes it once).
 *
 * @param array<string,mixed> $params placeholders of the message
 * @param array<string,mixed> $extra  more machine-readable data
 */
function assets_api_error(int $status, string $code, array $params = [], array $extra = []): void
{
    global $escaper;
    api_v2_json_result($status, $escaper->escapeHtml(_lang_raw($code, $params)), array_merge(['error' => $code], $extra));
}

// POST /assets/bulk -- verify / delete / assign teams / add to group for many assets.
// Body: {"action": "...", "ids": [int] | "filter": {...}, "params": {...}, "expected_count": int?}.
// "Every asset in scope" is filter {"all": true}; an empty filter is rejected.
// Per-id failures are reported in the 200 response; whole-request problems are 4xx
// with a machine code in data.error and a translated status_message.
function assets_bulk_API()
{
    global $escaper, $lang;

    api_v2_check_permission("asset");

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        assets_api_error(400, 'AssetBulkBodyInvalid');
        return;
    }

    $check = assets_bulk_validate_request($body);
    if (!$check['ok']) {
        assets_api_error($check['status'], $check['error'], $check['params'], $check['params']);
        return;
    }
    $action = $body['action'];

    // Narrow per-action permission, checked before anything is resolved or touched.
    if (!assets_bulk_user_may($action)) {
        api_v2_json_result(403, $escaper->escapeHtml($lang['NoPermissionForAsset']), null);
        return;
    }

    $params = is_array($body['params'] ?? null) ? $body['params'] : [];
    $param_error = assets_bulk_check_params($action, $params);
    if ($param_error !== null) {
        assets_api_error(422, $param_error);
        return;
    }

    $ids = assets_bulk_resolve_ids(array_key_exists('ids', $body) ? ['ids' => $body['ids']] : ['filter' => $body['filter']]);
    if (!$ids) {
        assets_api_error(400, 'AssetBulkNoMatch');
        return;
    }
    $too_many = assets_bulk_size_error($action, count($ids));
    if ($too_many !== null) {
        assets_api_error(422, $too_many['error'], $too_many['params'], $too_many['params']);
        return;
    }
    // A stale "Select all N": the set changed since the client counted it.
    $mismatch = assets_bulk_count_mismatch($body, count($ids));
    if ($mismatch !== null) {
        assets_api_error(409, $mismatch['error'], $mismatch['params'], $mismatch['params']);
        return;
    }

    // A large batch can outlast the default 30-second web limit; give this one
    // request room to finish rather than dying part-way with the response lost.
    // Scoped to this request only (set_time_limit() restarts the counter).
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    api_v2_json_result(200, "SUCCESS", assets_bulk_apply($action, $ids, $params));
}

// Gets the assets displayed in the Manage Assets datatables
function assets_for_view_API() {
    
    // Check that this user has the ability to view assets
    api_v2_check_permission("asset");
    
    global $field_settings, $field_settings_views;
    
    $view = !empty($_GET['view']) ? $_GET['view'] : false;
    
    // Only serving asset type views
    if (!empty($field_settings_views[$view]['view_type']) && $field_settings_views[$view]['view_type'] === 'asset') {

        $type = $field_settings_views[$view]['view_type'];
        $customization = customization_extra();
        
        $selected_fields = display_settings_get_display_settings_for_view($view);
        
        // if verified isn't set then it displays all assets so we're passing null
        $verified = isset($_GET['verified']) ? (int)$_GET['verified'] : null;
        
        // Validating and defaulting for the paging data
        $start = !empty($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = !empty($_POST['length']) ? (int)$_POST['length'] : 10;
        
        // In case there's no column selected that is orderable the order won't be sent from the client
        if (!empty($_POST['order'])) {
            /** @var array[] $post_order */
            $post_order = $_POST['order'];
            /** @var array[] $post_columns */
            $post_columns = $_POST['columns'];

            // @phan-suppress-next-line PhanTypeMismatchDimFetch
            $orderDir = strtoupper($post_order[0]['dir']) == "ASC" ? "ASC" : "DESC";

            // Get and validate the order column
            // @phan-suppress-next-line PhanTypeMismatchDimFetch
            $orderColumnIndex = isset($post_order[0]['column']) ? $post_order[0]['column'] : 0;
            $orderColumnName =
            // @phan-suppress-next-line PhanTypeMismatchDimFetch -- DataTables sends $post_columns as nested array
            !empty($post_columns[$orderColumnIndex]['name'])
            // @phan-suppress-next-line PhanTypeMismatchDimFetch
            && in_array($post_columns[$orderColumnIndex]['name'], $selected_fields)
            && (
                // @phan-suppress-next-line PhanTypeMismatchDimFetch
                (!empty($field_settings[$type][$post_columns[$orderColumnIndex]['name']]) && $field_settings[$type][$post_columns[$orderColumnIndex]['name']]['orderable'])
                // @phan-suppress-next-line PhanTypeMismatchDimFetch
                || str_starts_with($post_columns[$orderColumnIndex]['name'], 'custom_field_')
                )
                // @phan-suppress-next-line PhanTypeMismatchDimFetch
                ? $post_columns[$orderColumnIndex]['name']
                : 'id';
        } else {
            // so we're defaulting to ordering by the asset's id
            $orderColumnName = 'id';
            $orderDir = "ASC";
        }
        
        $column_filters = [];
        for ($i=0; $i<count($_POST['columns']); $i++) {
            
            // Gathering filter data for only the fields that are either set as searchable in the field settings
            // or a custom field which is searchable by default
            if (
                !empty($_POST['columns'][$i]['name']) &&
                !empty($_POST['columns'][$i]['search']['value']) &&
                in_array($_POST['columns'][$i]['name'], $selected_fields) &&
                (
                    (!empty($field_settings[$type][$_POST['columns'][$i]['name']]['searchable']) && $field_settings[$type][$_POST['columns'][$i]['name']]['searchable'])
                    ||
                    ($customization && str_starts_with($_POST['columns'][$i]['name'], 'custom_field_'))
                    )
                ) {
                    $column_filters[$_POST['columns'][$i]['name']] = $_POST['columns'][$i]['search']['value'];
                }
        }
        
        // Query the risks
        $data = get_assets_data_for_view_v2($view, $selected_fields, $verified, $start, $length, $orderColumnName, $orderDir, $column_filters);
        
        $result = array(
            'draw' => (int)$_POST['draw'],
            'data' => $data['rows'],
            'recordsTotal' => $data['recordsTotal'],
            'recordsFiltered' => $data['recordsFiltered'],
        );
        
        // @phan-suppress-next-line SecurityCheck-XSS -- JSON response consumed by JavaScript/DataTables, not rendered as HTML; values are pre-escaped
        echo json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

function assets_view_action_API() {
    
    // Check that this user has the ability to view assets
    api_v2_check_permission("asset");
    
    global $lang, $escaper;
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if (isset($_POST['all']) && $_POST['all']) {

            // verify_all_assets()/delete_all_assets() act on every asset with no team
            // filter. Under Team Separation a team-scoped user must not run them, or they
            // could verify/delete other teams' assets wholesale. Admins bypass separation.
            if (team_separation_extra() && !is_admin()) {
                set_alert(true, "bad", $lang['NoPermissionForAsset']);
                api_v2_json_result(403, get_alert(true), null);
                return;
            }

            switch ($action) {
                case 'verify':
                    // Verifying is limited to users holding asset_verify (spec section 10).
                    if (!asset_user_can('verify')) {
                        set_alert(true, "bad", $lang['NoPermissionForAsset']);
                        api_v2_json_result(403, get_alert(true), null);
                        return;
                    }
                    if (verify_all_assets()) {
                        set_alert(true, "good", $lang['AssetsWereVerifiedSuccessfully']);
                        api_v2_json_result(200, get_alert(true), null);
                    } else {
                        set_alert(true, "bad", $lang['ThereWasAProblemVerifyingTheAssets']);
                        api_v2_json_result(400, get_alert(true), NULL);
                    }
                    break;
                case 'discard':
                case 'delete':
                    if (!asset_user_can('delete')) {
                        set_alert(true, "bad", $lang['NoPermissionForAsset']);
                        api_v2_json_result(403, get_alert(true), null);
                        return;
                    }
                    if (delete_all_assets($action === 'delete')) {
                        set_alert(true, "good", $action === 'delete' ? $lang['AssetsWereDeletedSuccessfully']: $lang['AssetsWereDiscardedSuccessfully']);
                        api_v2_json_result(200, get_alert(true), null);
                    } else {
                        set_alert(true, "bad", $action === 'delete' ? $lang['ThereWasAProblemDeletingTheAssets'] : $lang['ThereWasAProblemDiscardingTheAssets']);
                        api_v2_json_result(400, get_alert(true), NULL);
                    }
                    break;
            }
        } elseif (isset($_POST['id'])) {
            $id = (int)$_POST['id'];

            // Object-level authorization: when Team Separation is installed, confirm
            // the caller may act on this specific asset before verify/discard/delete/edit.
            // Mirrors updateAssetById()/deleteAssetById(); without it the verify/delete
            // branches act on any asset id (cross-team). check_access_for_asset() returns
            // true when Team Separation is not installed, so base installs are unaffected.
            if (!check_access_for_asset($id)) {
                set_alert(true, "bad", $lang['NoPermissionForAsset']);
                api_v2_json_result(403, get_alert(true), null);
                return;
            }

            switch ($action) {
                case 'verify':
                    // Verifying is limited to users holding asset_verify (spec section 10).
                    if (!asset_user_can('verify')) {
                        set_alert(true, "bad", $lang['NoPermissionForAsset']);
                        api_v2_json_result(403, get_alert(true), null);
                        return;
                    }
                    if (verify_asset($id)) {
                        set_alert(true, "good", $lang['AssetWasVerifiedSuccessfully']);
                        api_v2_json_result(200, get_alert(true), null);
                    } else {
                        set_alert(true, "bad", $lang['ThereWasAProblemVerifyingTheAsset']);
                        api_v2_json_result(400, get_alert(true), NULL);
                    }
                    break;
                case 'discard':
                case 'delete':
                    // Deleting is limited to users holding asset_delete (same operation as bulk delete).
                    if (!asset_user_can('delete')) {
                        set_alert(true, "bad", $lang['NoPermissionForAsset']);
                        api_v2_json_result(403, get_alert(true), null);
                        return;
                    }
                    if (delete_asset($id)) {
                        set_alert(true, "good", $action === 'discard' ? $lang['AssetWasDiscardedSuccessfully']: $lang['AssetWasDeletedSuccessfully']);
                        api_v2_json_result(200, get_alert(true), null);
                    } else {
                        set_alert(true, "bad", $action === 'discard' ? $lang['ThereWasAProblemDiscardingTheAsset'] : $lang['ThereWasAProblemDeletingTheAsset']);
                        api_v2_json_result(400, get_alert(true), NULL);
                    }
                    break;
                case 'edit':
                    $view = $_POST['view'];
                    
                    global $field_settings_views, $field_settings;
                    $id_field = $field_settings_views[$view]['id_field'];
                    
                    // Check if the view sent is valid
                    if (empty($field_settings_views[$view]) || $field_settings_views[$view]['view_type'] !== 'asset') {
                        set_alert(true, "bad", $lang['AssetEditFailed_InvalidView']);
                        api_v2_json_result(400, get_alert(true), NULL);
                    }
                    
                    $where = "
                        WHERE `a`.`id` = :id";
                    
                    $where .= call_extra_function(
                        'team_separation_extra',
                        __DIR__ . '/../../../extras/separation/index.php',
                        'get_user_teams_query_for_assets',
                        ['a', false, true],
                        ''
                    );
                    $encryption = encryption_extra();
                    $customization = customization_extra();
                    
                    $active_field_names = display_settings_get_valid_field_keys($view);
                    
                    // We have to get the join parts for all the active fields and not just for the selected ones
                    list($select_parts, $join_parts) = field_settings_get_join_parts($view, $active_field_names);
                    
                    $db = db_open();
                    
                    $sql = "
                        SELECT
                            " . implode(',', $select_parts) . "
                        FROM
                            `assets` a
                            " . implode(' ', $join_parts) . "
                        {$where}
                        GROUP BY
                            `a`.`id`;
                    ";

                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                    $stmt->execute();
                    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

                    db_close($db);

                    global $field_settings;
                    $data = [];
                    foreach ($asset as $field_name => $value) {
                        $field_setting = !empty($field_settings['asset'][$field_name]) ? $field_settings['asset'][$field_name] : false;
                        
                        // Only run this logic if it's not a custom field(has a valid field setting)
                        if ($field_setting && !empty($value) && ($field_setting['editable'] || $id_field === $field_name)) {
                            
                            if ($value && $encryption && !empty($field_setting['encrypted']) && $field_setting['encrypted']) {
                                $value = try_decrypt($value);
                            }

                            // For fields that need custom formatting
                            switch($field_name) {
                                case "teams":
                                case "location":
                                    $data[$field_name] = array_map('intval', explode(',', (string)$value));
                                    break;
                                case "details":
                                    $data[$field_name] = $escaper->purifyHtml($value);
                                    break;
                                case 'tags':
                                    if ($value) {
                                        $tags = [];
                                        foreach(explode("|", $value) as $tag) {
                                            // We're not escaping the tags here on purpose as the way it's used on the UI needs no escaping
                                            $tags []= $tag;
                                        }
                                        $data[$field_name] = $tags;
                                    }
                                    break;
                                case 'associated_risks':
                                    // Only the ids of risks the caller may see (Team
                                    // Separation). A save from this form never removes
                                    // the hidden ones (update_asset_risks_associations()).
                                    $data[$field_name] = array_column(assets_visible_associated_risk_entries($value), 'value');
                                    break;
                                case "mapped_controls":
                                    if (!empty($value) && $value !== '[]') {
                                        $data[$field_name] = array_map(function($mapping) {
                                            return array(
                                                'control_maturity' => (int)$mapping['control_maturity'],
                                                'control_id' => explode(',', $mapping['control_id']),
                                            );
                                        }, json_decode($value, true));
                                    } else {
                                        $data[$field_name] = [];
                                    }
                                    break;
                                default:
                                    // Only have to escape non-custom fields as those are already escaped
                                    $data[$field_name] = $escaper->escapeHtml($value);
                            }
                        }
                    }
                    
                    if ($customization && !empty($asset['field_data']) && $asset['field_data'] !== '[]') {
                             // extract it as normal fields, but only the values, we don't need the _display fields here
                        foreach (json_decode($asset['field_data'], true) as $field_data) {
                            if (in_array($field_data['type'], ["multidropdown", "user_multidropdown"])) {
                                $data["custom_field_{$field_data['field_id']}"] = array_map('intval', explode(',', (string)$field_data['value']));
                            } elseif ($field_data['type'] === 'longtext') {
                                // Long Text fields are rendered through the WYSIWYG editor (see 'details' above), which
                                // needs sanitized HTML rather than fully entity-encoded plain text. 'longtext' (no
                                // underscore) is the Customization extra's actual stored custom_fields.type value
                                // (get_custom_value_join_parts() in extras/customization/index.php selects `cf`.`type`
                                // as-is) -- 'long_text' is a distinct synthetic string used only for the built-in
                                // 'Details' field elsewhere (display.php/functions.php); the two must not be confused.
                                $value = (int)$field_data['encryption'] ? try_decrypt($field_data['value']) : $field_data['value'];
                                $data["custom_field_{$field_data['field_id']}"] = $escaper->purifyHtml($value);
                            } elseif ((int)$field_data['encryption']) {
                                $data["custom_field_{$field_data['field_id']}"] = $escaper->escapeHtml(try_decrypt($field_data['value']));
                            } elseif($field_data['type'] === 'date') {
                                $data["custom_field_{$field_data['field_id']}"] = format_date($field_data['value']);
                            } else {
                                $data["custom_field_{$field_data['field_id']}"] = $escaper->escapeHtml($field_data['value']);
                            }
                        }
                    }
                    
                    api_v2_json_result(200, get_alert(true), $data);
                    break;
            }
        }
    }
}

function assets_update_asset_API() {
    
    // Check that this user has the ability to view assets
    api_v2_check_permission("asset");

    // Editing needs the narrow asset_edit permission on top of `asset`.
    if (!has_permission(assets_endpoint_required_permission('update_asset_legacy'))) {
        global $lang;
        set_alert(true, "bad", $lang['NoPermissionForAsset']);
        api_v2_json_result(403, get_alert(true), null);
        return;
    }
    
    global $field_settings_views, $lang;

    $view = !empty($_POST['edit_view']) ? $_POST['edit_view'] : false;
    // Only serving asset type views. Also check if the required fields have proper values
    if ($view && !empty($field_settings_views[$view]['view_type']) && $field_settings_views[$view]['view_type'] === 'asset' && isset($_POST['id']) && ctype_digit((string)$_POST['id'])) {

        if (!asset_exists_by_id((int)$_POST['id']) || !check_access_for_asset((int)$_POST['id'])) {
            api_v2_json_result(204, "NO CONTENT: Unable to find an asset with the specified id.", NULL);
        }

        // Choosing associated risks needs Risk Management (SR-2313).
        if (assets_body_has_associated_risks_input($_POST) && !assets_caller_can_see_associated_risks()) {
            set_alert(true, "bad", $lang['ChoosingRisksNeedsRiskManagementPermission']);
            api_v2_json_result(403, get_alert(true), NULL);
            return;
        }

        $mapped_controls = process_asset_control_mapping($_POST['mapped_controls'] ?? []);
        if (validate_asset_control_mapping($mapped_controls)) {
            $_POST['mapped_controls'] = $mapped_controls;
            update_asset_API_v2($view);
        } else {
            set_alert(true, "bad", $lang['ControlMappedToDifferentMaturitiesOnAsset']);
            api_v2_json_result(400, get_alert(true), NULL);
        }
    } else {
        set_alert(true, "bad", $lang['AssetEditFailed_IncorrectOrEmptyRequiredFields']);
        api_v2_json_result(400, get_alert(true), NULL);
    }
}

function assets_create_asset_API() {

    // Check that this user has the ability to view assets
    api_v2_check_permission("asset");

    global $field_settings_views, $lang;

    $view = !empty($_POST['create_view']) ? $_POST['create_view'] : false;
    // Only serving asset type views. Also check if the required fields have proper values
    if ($view && !empty($field_settings_views[$view]['view_type']) && $field_settings_views[$view]['view_type'] === 'asset') {

        // Choosing associated risks needs Risk Management (SR-2313).
        if (assets_body_has_associated_risks_input($_POST) && !assets_caller_can_see_associated_risks()) {
            set_alert(true, "bad", $lang['ChoosingRisksNeedsRiskManagementPermission']);
            api_v2_json_result(403, get_alert(true), NULL);
            return;
        }

        $mapped_controls = process_asset_control_mapping($_POST['mapped_controls'] ?? []);
        if (validate_asset_control_mapping($mapped_controls)) {
            $_POST['mapped_controls'] = $mapped_controls;
            create_asset_API_v2($view);
        } else {
            set_alert(true, "bad", $lang['ControlMappedToDifferentMaturitiesOnAsset']);
            api_v2_json_result(400, get_alert(true), NULL);
        }
    }
}

/****************************************
 * FUNCTION: API V2 ASSETS ASSOCIATIONS *
 * **************************************/
function api_v2_assets_associations()
{
    // Check that this user has the ability to view risks
    api_v2_check_permission("asset");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // If we received an id
    if (!empty($id))
    {
        // If the user should have access to this asset id
        if (check_access_for_asset($id))
        {
            // Get the connectivity for the asset. Risk subjects need Risk
            // Management (SR-2313): the bucket is empty without it.
            $risk_associations = graph_bucket_if_permitted('riskmanagement', fn() => get_risk_connectivity_for_asset($id));

            // Set the status
            $status_code = 200;
            $status_message = "SUCCESS";

            // Create the data array
            $data = [
                "risks" => $risk_associations,
            ];
        }
        // If the user should not have access to this asset id
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

/************************************
 * FUNCTION: API V2 ASSETS TAGS GET *
 * **********************************/
function api_v2_assets_tags_get()
{
    // Check that this user has the ability to view assets
    api_v2_check_permission("asset");

    // Get the risk id
    $id = get_param("GET", "id", null);

    // Open a database connection
    $db = db_open();

    // If we received an id
    if (!empty($id))
    {
        // Get just the tag with that id
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT a.id ORDER BY a.id ASC) as asset_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `assets` a ON a.id=tt.taggee_id WHERE tt.type='asset' AND t.id=:id GROUP BY t.id;");
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
                // Convert the asset_ids string into an array
                $tags[$key]['asset_ids'] = explode(',', $tag['asset_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each asset id
                    foreach ($tags[$key]['asset_ids'] as $asset_id)
                    {
                        // If the user should not have access to this asset id
                        if (!is_user_allowed_to_access_asset($asset_id))
                        {
                            // Remove it from the array
                            $tags[$key]['asset_ids'] = array_diff($tags[$key]['asset_ids'], [$asset_id]);
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
        $stmt = $db->prepare("SELECT t.id, t.tag value, group_concat(DISTINCT a.id ORDER BY a.id ASC) as asset_ids FROM `tags` t LEFT JOIN `tags_taggees` tt ON t.id=tt.tag_id LEFT JOIN `assets` a ON a.id=tt.taggee_id WHERE tt.type='asset' GROUP BY t.id;");
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
                // Convert the asset_ids string into an array
                $tags[$key]['asset_ids'] = explode(',', $tag['asset_ids']);

                // If team separation is enabled
                if (team_separation_extra())
                {
                    // Include the team separation extra
                    require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));

                    // For each asset id
                    foreach ($tags[$key]['asset_ids'] as $asset_id)
                    {
                        // If the user should not have access to this asset id
                        if (!is_user_allowed_to_access_asset($asset_id))
                        {
                            // Remove it from the array
                            $tags[$key]['asset_ids'] = array_diff($tags[$key]['asset_ids'], [$asset_id]);
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

?>