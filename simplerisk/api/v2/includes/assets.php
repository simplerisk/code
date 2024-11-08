<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/api.php'));
require_once(realpath( __DIR__ . '/../../../includes/assets.php'));
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));

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
        // Get just the asset with that id
        $asset = get_asset_by_id($id);

        // If the asset value returned is empty then we are unable to find an asset with that id
        if (empty($asset))
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
        // Get the verified value()
        $verified = get_param("GET", "verified", null);

        // Check the verified value
        switch ($verified)
        {
            case "true":
            case "1":
                $assets = get_verified_assets();
                break;
            case "false":
            case "0":
                $assets = get_unverified_assets();
                break;
            default:
                $assets = get_entered_assets();
                break;
        }

        // Decrypt encrypted fields
        if (encryption_extra()) {
            foreach ($assets as &$asset) {
                $asset['ip'] = try_decrypt($asset['ip']);
                $asset['name'] = try_decrypt($asset['name']);
                $asset['details'] = try_decrypt($asset['details']);
            }
        }

        // Create the data array
        $data = [
            "assets" => $assets,
        ];

        // Set the status
        $status_code = 200;
        $status_message = "SUCCESS";
    }

    // Return the result
    api_v2_json_result($status_code, $status_message, $data);
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
            
            $orderDir = strtoupper($_POST['order'][0]['dir']) == "ASC" ? "ASC" : "DESC";
            
            // Get and validate the order column
            $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 0;
            $orderColumnName =
            !empty($_POST['columns'][$orderColumnIndex]['name'])
            && in_array($_POST['columns'][$orderColumnIndex]['name'], $selected_fields)
            && (
                (!empty($field_settings[$type][$_POST['columns'][$orderColumnIndex]['name']]) && $field_settings[$type][$_POST['columns'][$orderColumnIndex]['name']]['orderable'])
                || str_starts_with($_POST['columns'][$orderColumnIndex]['name'], 'custom_field_')
                )
                ? $_POST['columns'][$orderColumnIndex]['name']
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
        
        echo json_encode($result);
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
            switch ($action) {
                case 'verify':
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
            
            switch ($action) {
                case 'verify':
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
                    
                    if(team_separation_extra()){
                        require_once(realpath(__DIR__ . '/../../../extras/separation/index.php'));
                        $where .= get_user_teams_query_for_assets("a", false, true);
                    }
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
                            //asdasdasd
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
                                    $data[$field_name] = [];
                                    // If the data returned isn't empty
                                    if (!empty($value) && $value !== '[]') {
                                        // Decode the json list, iterate through it and gather the ids
                                        foreach (json_decode($value, true) as $associated_risk) {
                                            $data[$field_name] []= (int)$associated_risk['value'];
                                        }
                                    }
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
    
    global $field_settings_views, $lang;

    $view = !empty($_POST['edit_view']) ? $_POST['edit_view'] : false;
    // Only serving asset type views. Also check if the required fields have proper values
    if ($view && !empty($field_settings_views[$view]['view_type']) && $field_settings_views[$view]['view_type'] === 'asset' && isset($_POST['id']) && ctype_digit((string)$_POST['id'])) {

        if (!asset_exists_by_id((int)$_POST['id'])) {
            api_v2_json_result(204, "NO CONTENT: Unable to find an asset with the specified id.", NULL);
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
            // Get the connectivity for the asset
            $risk_associations = get_risk_connectivity_for_asset($id);

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