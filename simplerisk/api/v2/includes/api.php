<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));

/********************************
 * FUNCTION: API V2 JSON RESULT *
 ********************************/
function api_v2_json_result($status_code, $status_message, $data)
{
    return json_response($status_code, $status_message, $data);
}

/********************************
 * FUNCTION: API V2 CHECK ADMIN *
 ********************************/
function api_v2_check_admin()
{
    // If the user calling this is not an admin
    if (!is_admin())
    {
        // The user is unauthorized
        $data = null;
        $status_code = 403;
        $status_message = "FORBIDDEN: The user does not have admin privileges.";

        // Return the result
        api_v2_json_result($status_code, $status_message, $data);

        // Do not process anything else
        exit;
    }
}

/*************************************
 * FUNCTION: API V2 CHECK PERMISSION *
 *************************************/
function api_v2_check_permission($permission)
{
    // If the user calling this is not an admin
    if (!check_permission($permission))
    {
        // The user is unauthorized
        $data = null;
        $status_code = 403;
        $status_message = "FORBIDDEN: The user does not have the required permission to perform this action.";

        // Return the result
        api_v2_json_result($status_code, $status_message, $data);

        // Do not process anything else
        exit;
    }
}

/*************************************
 * FUNCTION: API V2 IS AUTHENTICATED *
 *************************************/
function api_v2_is_authenticated()
{
    // If the API Extra is activated
    if (api_extra())
    {
        // Required file
        $required_file = realpath(__DIR__ . '/../../../extras/api/includes/api.php');

        // If the file exists
        if (file_exists($required_file))
        {
            // Include the required file
            require_once($required_file);
        }
    }

    // If the API Extra is enabled and an X-API-KEY header is set
    if (api_extra() && authenticate_key() !== false)
    {
        // Return true
        return true;
    }
    // If we are not authenticated with a key but have an authenticated session
    else if (is_session_authenticated())
    {
        // Return true
        return true;
    }
    // If we are not authenticated with a key but have a system token
    else if (check_system_token())
    {
        // Return true
        return true;
    }
    else if(check_questionnaire_get_token()) {
        return false;
    }
    // Access was not authenticated
    else
    {
        unauthenticated_access();
    }
}


// The function to save the selections
function saveColumnSelectionSettingsAPI() {
    global $lang, $field_settings_views;
    
    $view = $_POST['display_settings_view'];
    if (!empty($view) && in_array($view, array_keys($field_settings_views))) {
        $settings = array_values(array_intersect(array_keys($_POST), display_settings_get_valid_field_keys($view)));
        
        display_settings_save_selection_single($view, $settings);
        
        set_alert(true, "good", $lang['SelectionSaveSuccessful']);
        
        // Not returning the alerts here because on success the page should be refreshed and we let the alerts render on the page load
        api_v2_json_result(200, null, null);
    }
    
    set_alert(true, "bad", $lang['SelectionSaveFailed']);
    api_v2_json_result(400, get_alert(true), null);
}

/**
 * Used for 'POST' API call '/ui/layout'
 */
function api_save_ui_layout() {
    global $lang, $ui_layout_config, $ui_layout_widget_config;

    $layout_name = $_POST['layout_name'];

    // Check the user's permissions
    if (($ui_layout_config[$layout_name]['required_permission'] && !check_permission($ui_layout_config[$layout_name]['required_permission']))) {
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        api_v2_json_result(400, get_alert(true), null);
    }

    $layout = isset($_POST['layout']) ? $_POST['layout'] : '' ;
    $user_id = $_SESSION['uid'];

    if (!empty($layout)) {

        // Remove widget configuration that's not alowed on the layout (sanitizing widget name and type coming from client side)
        $layout = array_filter($layout, function($widget) use($ui_layout_config, $layout_name) {
            return in_array($widget['name'], $ui_layout_config[$layout_name]['available_widgets']);
        });

        if (!empty($layout)) {
            // Sanitizing data
            // Also adding back information that's not sent by the client(width(w) and height(h) information is not sent if it matches the minimum value)
            $layout = array_map(function($w) use($ui_layout_widget_config) {
                    $config = $ui_layout_widget_config[$w['name']];
                    $default = $config['defaults'];
                    return [
                        'name' => $w['name'],
                        'type' => $config['type'],
                        'x' => (int)$w['x'],
                        'y' => (int)$w['y'],
                        'w' => isset($w['w']) ? (int)$w['w'] : $default['minW'],
                        'h' => isset($w['h']) ? (int)$w['h'] : $default['minH'],
                        'minW' => $default['minW'],
                        'minH' => $default['minH'],
                        'layout' => $w['layout']
                    ];
                }
                , $layout
            );
        }
    }

    // Save the sanitized/empty layout data
    save_layout_for_user($user_id, $layout_name, json_encode($layout ?? ''));

    set_alert(true, "good", $lang['LayoutSaved']);
    api_v2_json_result(200, get_alert(true), null);
}

/**
 * Used for 'GET' API call '/ui/layout'
 */
function api_get_ui_layout() {
    global $lang, $ui_layout_config;

    $layout_name = $_GET['layout_name'];
    $type = $_GET['type'];

    // Check the user's permissions
    if (($ui_layout_config[$layout_name]['required_permission'] && !check_permission($ui_layout_config[$layout_name]['required_permission']))
        || !in_array($type, ['default', 'saved'])) {
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        api_v2_json_result(400, get_alert(true), null);
    }

    // Get the requested layout
    switch ($type) {
        case 'saved':
            // Get the user's saved layout
            [$layout, $_, $_] = get_layout_for_user($layout_name);

            set_alert(true, "good", $lang['SavedLayoutRestored']);
            api_v2_json_result(200, get_alert(true), $layout);
        break;

        default:
            // Delete the user's custom layout as we're setting it to the default
            delete_layout_for_user($layout_name);

            set_alert(true, "good", $lang['DefaultLayoutRestored']);
            api_v2_json_result(200, get_alert(true), get_default_layout($layout_name));
        break;
    }
}

/**
 * Used for 'GET' API call '/ui/widget' 
 */
function api_get_ui_widget() {
    global $lang, $ui_layout_config, $ui_layout_widget_config;

    $layout_name = $_GET['layout_name'];
    $widget_name = $_GET['widget_name'];

    if (empty($layout_name) || empty($widget_name) || !in_array($widget_name, $ui_layout_config[$layout_name]['available_widgets'])) {
        set_alert(true, "bad", $lang['InvalidWidgetName']);
        api_v2_json_result(400, get_alert(true), null);
    }

    // Check the user's permissions
    if (($ui_layout_config[$layout_name]['required_permission'] && !check_permission($ui_layout_config[$layout_name]['required_permission']))
        || ($ui_layout_widget_config[$widget_name]['required_permission'] && !check_permission($ui_layout_widget_config[$widget_name]['required_permission']))) {
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        api_v2_json_result(400, get_alert(true), null);
    }

    if ($layout_name == 'overview') {
        $widget_html = get_ui_widget_overview($widget_name);
    } else if ($layout_name == 'dashboard_open') {
        $widget_html = get_ui_widget_dashboard_open($widget_name);
    } else if ($layout_name == 'dashboard_close') {
        $widget_html = get_ui_widget_dashboard_close($widget_name);
    }
    
    api_v2_json_result(200, null, $widget_html);
}

/**
 * Used for 'GET' API call '/ui/default_layout'
 */
function api_update_default_status() {
    global $lang, $ui_layout_config;

    $layout_name = $_POST['layout_name'];

    // Check the user's permissions
    if (($ui_layout_config[$layout_name]['required_permission'] && !check_permission($ui_layout_config[$layout_name]['required_permission']))) {
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        api_v2_json_result(400, get_alert(true), null);
    }

    $user_id = $_SESSION['uid'];
    $default_status = $_POST['default'];

    // Only do these checks if the user tries to set this layout as default(true)
    if ($default_status) {
        [$_, $is_custom, $_] = get_layout_for_user($layout_name, $user_id);

        // Can only save custom layouts as default
        if (!$is_custom) {
            set_alert(true, "bad", $lang['InvalidLayoutOnlyCustomAllowedAsDefault']);
            api_v2_json_result(400, get_alert(true), null);
        }
    }

    set_layout_default_status($user_id, $layout_name, $default_status);

    set_alert(true, "good", $lang['LayoutDefaultStatusUpdated']);
    api_v2_json_result(200, get_alert(true), null);
}

function get_ui_widget_overview($widget_name) {

    global $lang;

    // It's setup this way so we can generate the widget's html on the server side
    // it means we're able to use the UI layout widget for every kind of content
    ob_start();

    switch ($widget_name) {
        case 'chart_open_vs_closed':
            open_closed_pie(js_string_escape($lang['OpenVsClosed']));
            break;
        case 'chart_mitigation_planned_vs_unplanned':
            open_mitigation_pie(js_string_escape($lang['MitigationPlannedVsUnplanned']));
            break;
        case 'chart_reviewed_vs_unreviewed':
            open_review_pie(js_string_escape($lang['ReviewedVsUnreviewed']));
            break;
        case 'table_risks_by_month':
            risks_by_month_table();
            break;
    }

    $widget_html = ob_get_contents();
    ob_end_clean();

    return $widget_html;

}

function get_ui_widget_dashboard_open($widget_name) {

    global $lang;
    
    $teamOptions = get_teams_by_login_user();
    array_unshift($teamOptions, array(
        'value' => "0",
        'name' => $lang['Unassigned'],
    ));

    $teams = [];
    // Get teams submitted by user
    if (isset($_GET['teams'])) {
        $teams = array_filter(explode(',', $_GET['teams']), 'ctype_digit');
    } elseif (is_array($teamOptions)) {
        foreach ($teamOptions as $teamOption) {
            $teams[] = (int)$teamOption['value'];
        }
    }

    // Get the risk pie array
    $pie_array = get_pie_array(null, $teams);

    // Get the risk location pie array
    $pie_location_array = get_pie_array("location", $teams);

    // Get the risk team pie array
    $pie_team_array = get_pie_array("team", $teams);

    // Get the risk technology pie array
    $pie_technology_array = get_pie_array("technology", $teams);

    // It's setup this way so we can generate the widget's html on the server side
    // it means we're able to use the UI layout widget for every kind of content
    ob_start();

    switch ($widget_name) {
        case 'open_risk_level':
            open_risk_level_pie(js_string_escape($lang['RiskLevel']), "open_risk_level_pie", $teams); 
            break;
        case 'open_status':
            open_risk_status_pie($pie_array, js_string_escape($lang['Status'])); 
            break;
        case 'open_site_location':
            open_risk_location_pie($pie_location_array, js_string_escape($lang['SiteLocation'])); 
            break;
        case 'open_risk_source':
            open_risk_source_pie($pie_array, js_string_escape($lang['RiskSource'])); 
            break;
        case 'open_category':
            open_risk_category_pie($pie_array, js_string_escape($lang['Category'])); 
            break;
        case 'open_team':
            open_risk_team_pie($pie_team_array, js_string_escape($lang['Team'])); 
            break;
        case 'open_technology':
            open_risk_technology_pie($pie_technology_array, js_string_escape($lang['Technology'])); 
            break;
        case 'open_owner':
            open_risk_owner_pie($pie_array, js_string_escape($lang['Owner'])); 
            break;
        case 'open_owners_manager':
            open_risk_owners_manager_pie($pie_array, js_string_escape($lang['OwnersManager'])); 
            break;
        case 'open_risk_scoring_method':
            open_risk_scoring_method_pie($pie_array, js_string_escape($lang['RiskScoringMethod'])); 
            break;
    }

    $widget_html = ob_get_contents();
    ob_end_clean();
    
    return $widget_html;

}

function get_ui_widget_dashboard_close($widget_name) {

    global $lang;

    $teamOptions = get_teams_by_login_user();
    array_unshift($teamOptions, array(
        'value' => "0",
        'name' => $lang['Unassigned'],
    ));

    $teams = [];
    // Get teams submitted by user
    if (isset($_GET['teams'])) {
        $teams = array_filter(explode(',', $_GET['teams']), 'ctype_digit');
    } elseif (is_array($teamOptions)) {
        foreach ($teamOptions as $teamOption) {
            $teams[] = (int)$teamOption['value'];
        }
    }

    // Get the risk pie array
    $pie_array = get_pie_array(null, $teams);

    // Get the risk location pie array
    $pie_location_array = get_pie_array("location", $teams);

    // Get the risk team pie array
    $pie_team_array = get_pie_array("team", $teams);

    // Get the risk technology pie array
    $pie_technology_array = get_pie_array("technology", $teams);

    // It's setup this way so we can generate the widget's html on the server side
    // it means we're able to use the UI layout widget for every kind of content
    ob_start();

    switch ($widget_name) {
        case 'close_reason':
            closed_risk_reason_pie(js_string_escape($lang['Reason']), $teams); 
            break;
    }

    $widget_html = ob_get_contents();
    ob_end_clean();
    
    return $widget_html;

}
?>