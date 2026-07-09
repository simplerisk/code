<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../../includes/extras.php'));
require_once(realpath(__DIR__ . '/../../../includes/reports_catalog.php'));

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
            return in_array($widget['name'], $ui_layout_config[$layout_name]['available_widgets']) || (!empty($ui_layout_config[$layout_name]['available_custom_widgets']) && in_array($widget['name'], $ui_layout_config[$layout_name]['available_custom_widgets']));
        });

        if (!empty($layout)) {
            // Sanitizing data
            // Also adding back information that's not sent by the client(width(w) and height(h) information is not sent if it matches the minimum value)
            $layout = array_map(function($w) use($user_id, $ui_layout_widget_config) {
                    $config = $ui_layout_widget_config[$w['name']];
                    $default = $config['defaults'];

                    $custom = $config['custom'] ?? false;

                    return [
                        ...($custom ? ['custom' => true, 'data' => purify_html($w['data'])] : []),
                        ...[
                            'name' => $w['name'],
                            'type' => $config['type'],
                            'x' => (int)$w['x'],
                            'y' => (int)$w['y'],
                            'w' => isset($w['w']) ? (int)$w['w'] : $default['minW'],
                            'h' => isset($w['h']) ? (int)$w['h'] : $default['minH'],
                            'minW' => $default['minW'],
                            'minH' => $default['minH'],
                            'layout' => $w['layout']
                        ]
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

    // Checking if the widget name is allowed
    if (empty($layout_name) || empty($widget_name) || 
        // Either there're no custom widgets configured and it's not in the list of available widgets
        (empty($ui_layout_config[$layout_name]['available_custom_widgets']) && !in_array($widget_name, $ui_layout_config[$layout_name]['available_widgets']))
        // or there are custom widgets configured, but it's not in the list of available widgets nor the list of available custom widgets
        || (!empty($ui_layout_config[$layout_name]['available_custom_widgets']) && !in_array($widget_name, $ui_layout_config[$layout_name]['available_custom_widgets']) && !in_array($widget_name, $ui_layout_config[$layout_name]['available_widgets']))) {
        set_alert(true, "bad", $lang['InvalidWidgetName']);
        api_v2_json_result(400, get_alert(true), null);
    }

    // Check the user's permissions
    if (($ui_layout_config[$layout_name]['required_permission'] && !check_permission($ui_layout_config[$layout_name]['required_permission']))
        || ($ui_layout_widget_config[$widget_name]['required_permission'] && !check_permission($ui_layout_widget_config[$widget_name]['required_permission']))) {
        set_alert(true, "bad", $lang['NoPermissionForThisAction']);
        api_v2_json_result(400, get_alert(true), null);
    }

    $widget_html = '';
    if ($layout_name == 'overview') {
        $widget_html = get_ui_widget_overview($widget_name);
    } else if ($layout_name == 'dashboard_open') {
        $widget_html = get_ui_widget_dashboard_open($widget_name);
    } else if ($layout_name == 'dashboard_close') {
        $widget_html = get_ui_widget_dashboard_close($widget_name);
    } else {
        // Generic fallback: extras register their own get_ui_widget_{layout_name}()
        // via api/v2/index.php which loads each active extra's api.php at boot time.
        $handler = 'get_ui_widget_' . preg_replace('/[^a-z0-9]/', '_', $layout_name);
        if (function_exists($handler)) {
            $widget_html = $handler($widget_name);
        }
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
            open_closed_pie($lang['OpenVsClosed']);
            break;
        case 'chart_mitigation_planned_vs_unplanned':
            open_mitigation_pie($lang['MitigationPlannedVsUnplanned']);
            break;
        case 'chart_reviewed_vs_unreviewed':
            open_review_pie($lang['ReviewedVsUnreviewed']);
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
            open_risk_level_pie($lang['RiskLevel'], "open_risk_level_pie", $teams);
            break;
        case 'open_status':
            open_risk_status_pie($pie_array, $lang['Status']);
            break;
        case 'open_site_location':
            open_risk_location_pie($pie_location_array, $lang['SiteLocation']);
            break;
        case 'open_risk_source':
            open_risk_source_pie($pie_array, $lang['RiskSource']);
            break;
        case 'open_category':
            open_risk_category_pie($pie_array, $lang['Category']);
            break;
        case 'open_team':
            open_risk_team_pie($pie_team_array, $lang['Team']);
            break;
        case 'open_technology':
            open_risk_technology_pie($pie_technology_array, $lang['Technology']);
            break;
        case 'open_owner':
            open_risk_owner_pie($pie_array, $lang['Owner']);
            break;
        case 'open_owners_manager':
            open_risk_owners_manager_pie($pie_array, $lang['OwnersManager']);
            break;
        case 'open_risk_scoring_method':
            open_risk_scoring_method_pie($pie_array, $lang['RiskScoringMethod']);
            break;
        case 'open_team_exposure':
            open_risk_team_exposure_pie($teams, $lang['ExposureByTeam']);
            break;
        case 'open_category_exposure':
            open_risk_category_exposure_pie($teams, $lang['ExposureByCategory']);
            break;
        case 'open_location_exposure':
            open_risk_location_exposure_pie($teams, $lang['ExposureByLocation']);
            break;
        case 'open_risk_sla_status':
            open_risk_sla_status($teams, $lang['SLABreachStatus']);
            break;
        // Organizational Hierarchy Extra widgets — only reachable when extra is enabled,
        // because the widget is not registered in available_widgets otherwise.
        case 'open_business_unit_exposure':
            call_extra_function(
                'organizational_hierarchy_extra',
                __DIR__ . '/../../../extras/organizational_hierarchy/index.php',
                'open_risk_business_unit_exposure_pie',
                [$teams, $lang['ExposureByBusinessUnit']]
            );
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

    // It's setup this way so we can generate the widget's html on the server side
    // it means we're able to use the UI layout widget for every kind of content
    ob_start();

    switch ($widget_name) {
        case 'close_reason':
            closed_risk_reason_pie($lang['Reason'], $teams);
            break;
    }

    $widget_html = ob_get_contents();
    ob_end_clean();
    
    return $widget_html;

}

/*******************************************************************************
 * This function is used to get the compliance dashboard widget's html content *
 *******************************************************************************/
function get_ui_widget_compliance_dashboard($widget_name) {

    ob_start();
    
    switch ($widget_name) {
        case 'compliance_controls_by_framework_bar_chart':
            compliance_controls_by_framework_bar_chart();
            break;
        case 'compliance_pass_rate_trend_line_chart': 
            compliance_pass_rate_trend_line_chart();
            break;
        case 'compliance_pass_fail_pie_chart':
            compliance_pass_fail_pie_chart();
            break;
    }

    $widget_html = ob_get_contents();
    ob_end_clean();

    return $widget_html;

}

/*******************************************************************************
 * This function is used to get the governance dashboard widget's html content *
 *******************************************************************************/
function get_ui_widget_governance_dashboard($widget_name) {

    ob_start();

    switch ($widget_name) {
        case 'governance_current_control_maturity_pie_chart':
            governance_current_control_maturity_pie_chart();
            break;
        case 'governance_framework_maturity_stacked_bar_chart':
            governance_framework_maturity_stacked_bar_chart();
            break;
    }

    $widget_html = ob_get_contents();
    ob_end_clean();

    return $widget_html;

}

/******************************************
 * FUNCTION: API V2 REPORTS FAVORITES ADD *
 ******************************************/
// POST /reports/favorites
// Body: {"report_key": "<catalog key>"}
// Adds (or no-ops) a row in user_favorite_reports for the current user.
// Authentication only — no module permission required (you can favorite a
// report you can't currently access; the report's own page-level gate
// still applies on click).
function api_v2_reports_favorites_add()
{
    $body = json_decode(file_get_contents('php://input'), true);
    $report_key = $body['report_key'] ?? null;

    if (!is_string($report_key) || $report_key === '' || strlen($report_key) > 64) {
        api_v2_json_result(400, 'BAD REQUEST: report_key is required and must be 1-64 chars.', null);
        return;
    }

    $catalog = reports_catalog();
    if (!array_key_exists($report_key, $catalog)) {
        api_v2_json_result(400, 'BAD REQUEST: unknown report_key.', null);
        return;
    }

    if (!user_can_access_report($catalog[$report_key])) {
        api_v2_json_result(403, 'FORBIDDEN: you do not have access to this report.', null);
        return;
    }

    $user_id = (int)($_SESSION['uid'] ?? 0);
    if ($user_id <= 0) {
        api_v2_json_result(401, 'UNAUTHORIZED: no session user.', null);
        return;
    }

    try {
        if (!add_user_favorite_report($user_id, $report_key)) {
            api_v2_json_result(500, 'INTERNAL: failed to record favorite.', null);
            return;
        }
    } catch (\PDOException $e) {
        write_debug_log("API v2 reports favorites add failed: " . $e->getMessage(), 'error');
        api_v2_json_result(500, 'INTERNAL: failed to record favorite.', null);
        return;
    }

    api_v2_json_result(200, 'OK', [
        'favorites' => list_user_favorite_reports($user_id),
    ]);
}

/**************************************
 * FUNCTION: API V2 REPORTS CATALOG   *
 **************************************/
// GET /reports/catalog
// Returns the per-user-filtered Reports Hub catalog plus per-entry
// favorited flag. Authentication-only — favoriting/viewing the catalog
// does not require a module permission, but the per-entry filter
// (user_can_access_report) limits the response to entries this user
// can actually open. Each report's own page-level gate still applies
// on click.
function api_v2_reports_catalog()
{
    global $lang;

    $user_id = (int)($_SESSION['uid'] ?? 0);
    if ($user_id <= 0) {
        api_v2_json_result(401, 'UNAUTHORIZED: no session user.', null);
        return;
    }

    try {
        $favorites_set = array_flip(list_user_favorite_reports($user_id));
    } catch (\PDOException $e) {
        write_debug_log("API v2 reports catalog: failed to read favorites: " . $e->getMessage(), 'error');
        api_v2_json_result(500, 'INTERNAL: failed to read favorites.', null);
        return;
    }

    $reports = [];
    foreach (reports_catalog() as $key => $entry) {
        if (!user_can_access_report($entry)) {
            continue;
        }
        // $lang fallback returns the raw key when the language file is missing
        // the entry (only expected during development before language keys land).
        $reports[] = [
            'key'         => $key,
            'label'       => $lang[$entry['label_key']] ?? $entry['label_key'],
            // $lang fallback returns the raw key when the language file is missing
            // the entry (only expected during development before language keys land).
            'description' => $lang[$entry['desc_key']] ?? '',
            'path'        => $entry['path'],
            'kind'        => $entry['kind'],
            'tags'        => $entry['tags'],
            'favorited'   => isset($favorites_set[$key]),
        ];
    }

    // Alphabetize by resolved (localized) label. reports-hub.js groups tiles
    // by `tag` into sections and renders non-favorited tiles within a section
    // in API response order, so a single label-sort here lands as alpha order
    // within every category. Favorites-first behavior is handled client-side
    // and is unaffected.
    usort($reports, function ($a, $b) {
        return strnatcasecmp($a['label'], $b['label']);
    });

    api_v2_json_result(200, 'OK', ['reports' => $reports]);
}

/**********************************************
 * FUNCTION: API V2 ADMIN SETTINGS CATALOG   *
 **********************************************/
// GET /admin/settings/catalog
// Returns the Settings Hub catalog filtered to entries the current admin
// can access. Each entry includes its localized label/description, the
// relative path of the page it links to, its tag list, and whether the
// caller has favorited it.
function api_v2_admin_settings_catalog()
{
    global $lang;

    require_once(realpath(__DIR__ . '/../../../includes/settings_catalog.php'));

    if (!user_can_access_settings_hub()) {
        api_v2_json_result(403, 'FORBIDDEN: insufficient permissions.', null);
        return;
    }

    $user_id = (int)($_SESSION['uid'] ?? 0);
    try {
        $favorites_set = array_flip(list_user_favorite_settings($user_id));
    } catch (\PDOException $e) {
        write_debug_log("API v2 settings catalog: failed to read favorites: " . $e->getMessage(), 'error');
        $favorites_set = [];
    }

    $tiles = [];
    foreach (settings_catalog() as $key => $entry) {
        if (!user_can_access_settings_tile($entry)) {
            continue;
        }
        $tile = [
            'key'         => $key,
            'label'       => $lang[$entry['label_key']] ?? $entry['label_key'],
            'description' => $lang[$entry['desc_key']]  ?? '',
            'path'        => $entry['path'],
            'tags'        => $entry['tags'],
            'favorited'   => isset($favorites_set[$key]),
            'state'       => compute_extra_tile_state(
                $entry,
                static function (string $name): bool {
                    $fn = extra_state_check_function($name);
                    return function_exists($fn) && $fn();
                },
                static fn(string $name): bool => is_extra_installed($name),
            ),
            'extra_name'  => $entry['extra_name'] ?? ($entry['visibility']['extra'] ?? null),
        ];

        // Resolve sub_hub label_key values server-side so the JS doesn't
        // need access to the full $lang array. Only attached when the
        // catalog entry declares a sub_hub block.
        if (isset($entry['sub_hub']) && is_array($entry['sub_hub'])) {
            $sub = $entry['sub_hub'];
            $resolved_sub_tiles = [];
            foreach ($sub['tiles'] as $sub_tile) {
                $resolved_sub_tiles[] = [
                    'key'   => $sub_tile['key'],
                    'label' => $lang[$sub_tile['label_key']] ?? $sub_tile['label_key'],
                    'path'  => $sub_tile['path'],
                ];
            }
            $tile['sub_hub'] = [
                'section_key' => $sub['section_key'],
                'heading'     => $lang[$sub['heading_lang_key']] ?? $sub['heading_lang_key'],
                'tiles'       => $resolved_sub_tiles,
            ];
        }

        $tiles[] = $tile;
    }

    // Alphabetize by localized label — JS groups tiles by tag into sections
    // and renders within a section in API order, so a single label-sort
    // lands as alpha order within every category.
    usort($tiles, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));

    // Piggyback the licensing enforcement_level on the catalog response so
    // settings-hub.js can gate Install/Upgrade buttons in the same fetch
    // that loads the tiles. This is an intentional cross-concern — the
    // catalog endpoint is consumed only by settings-hub.js (no mobile or
    // monitoring callers today). If a future caller treats the catalog as
    // pure tile metadata, factor enforcement_level out into a dedicated
    // /admin/licensing/status endpoint instead of removing the field here.
    require_once(realpath(__DIR__ . '/../../../includes/licensing.php'));
    $enforcement_level = get_cached_enforcement_level();

    api_v2_json_result(200, 'OK', ['tiles' => $tiles, 'enforcement_level' => $enforcement_level]);
}

/****************************************************************
 * FUNCTION: API V2 ADMIN SETTINGS EXTRAS LICENSES              *
 ****************************************************************/
// GET /admin/settings/extras/licenses
// Returns the Extra slugs the current customer has purchased. The Settings
// Hub renders synchronously with 'Checking…' badges on uninstalled tiles and
// then fires this deferred request to upgrade each tile to either
// 'Ready to Download' (license confirmed) or 'Purchase' (not licensed).
//
// core_check_all_purchases() reads from the local license cache
// (settings.license_check_response) populated daily by license_check_daily().
// It never hits the network and always returns an associative array of
// [short_name => bool], so this endpoint always answers 200 (or 403 for a
// non-admin) — there is no network-failure path to surface here.
function api_v2_admin_settings_extras_licenses()
{
    if (!is_admin()) {
        api_v2_json_result(403, 'FORBIDDEN: admin permission required.', null);
        return;
    }

    api_v2_json_result(200, 'OK', ['licensed' => api_v2_collect_licensed_extras()]);
}

/****************************************************************
 * FUNCTION: API V2 COLLECT LICENSED EXTRAS                     *
 ****************************************************************/
// Build the licensed-Extra slug list from the local license cache
// (settings.license_check_response). Free Extras (upgrade, complianceforgescf)
// are always licensed. Reads only — never hits the network. Shared by the
// licenses listing and the license-refresh endpoints.
function api_v2_collect_licensed_extras(): array
{
    require_once(realpath(__DIR__ . '/../../../includes/extras.php'));

    // Returns [short_name => bool] from the local license cache.
    $purchases = core_check_all_purchases();

    $licensed = [];
    $free_extras = free_extra_short_names();
    foreach (available_extra_short_names() as $short_name) {
        $purchased = ($purchases[$short_name] ?? false) || in_array($short_name, $free_extras, true);
        if ($purchased) {
            $licensed[] = $short_name;
        }
    }

    return $licensed;
}

/****************************************************************
 * FUNCTION: API V2 BUILD LICENSE OVERVIEW PAYLOAD             *
 ****************************************************************/
// Assemble the per-Extra license overview the Licenses page renders, from the
// local cache only (no network). Descriptions are resolved server-side from
// $lang so the client receives localized text. Shared by GET /admin/licenses
// and POST /admin/license/refresh.
function api_v2_build_license_overview_payload(): array
{
    global $lang;

    require_once(realpath(__DIR__ . '/../../../includes/extras.php'));
    require_once(realpath(__DIR__ . '/../../../includes/licensing.php'));

    $descriptions = [];
    foreach (extra_description_keys() as $short_name => $lang_key) {
        if (isset($lang[$lang_key])) {
            $descriptions[$short_name] = $lang[$lang_key];
        }
    }

    return [
        'enforcement_level' => get_cached_enforcement_level(),
        'extras'            => build_license_overview(
            available_extras(),
            get_cached_license_entries(),
            $descriptions
        ),
    ];
}

/****************************************************************
 * FUNCTION: API V2 ADMIN LICENSES                             *
 ****************************************************************/
// GET /admin/licenses
// Returns the per-Extra license overview (classification, dates, status) for
// the Licenses Settings Hub page. Reads the local cache only — never the
// network. Admin-gated.
function api_v2_admin_licenses()
{
    if (!is_admin()) {
        api_v2_json_result(403, 'FORBIDDEN: admin permission required.', null);
        return;
    }

    api_v2_json_result(200, 'OK', api_v2_build_license_overview_payload());
}

/****************************************************************
 * FUNCTION: API V2 ADMIN LICENSE REFRESH                       *
 ****************************************************************/
// POST /admin/license/refresh
// Forces a synchronous /license/check against the licensing service,
// rewriting the local cache (settings.license_check_response) on the spot
// instead of waiting for the daily core_license_check queue job. Admin-gated.
//
// This is the on-demand counterpart to that queue job: an admin who has just
// purchased or renewed an Extra can call this to pull fresh entitlements
// immediately rather than waiting up to 24h.
//
// On success returns the fresh enforcement_level plus the licensed Extra list.
// On a transport failure or non-200, license_check_daily() leaves the prior
// cache untouched and returns 'unknown' defaults — surfaced here as 503 so the
// caller knows the refresh did not land. The cache is never clobbered on
// failure.
function api_v2_admin_license_refresh()
{
    global $lang;

    if (!is_admin()) {
        api_v2_json_result(403, 'FORBIDDEN: admin permission required.', null);
        return;
    }

    require_once(realpath(__DIR__ . '/../../../includes/licensing.php'));

    // One synchronous /license/check. Writes the cache on a 200; on a transport
    // failure or non-200 it keeps the prior cache and returns parsed-empty
    // ('unknown') defaults. Single attempt by design: license_check() makes one
    // 5s curl call, and re-running it rebuilt the full telemetry payload (four
    // DB queries incl. a multi-CTE login aggregate) each pass — costly, and a
    // retry-loop-inside-one-request shape we use nowhere else. If the refresh
    // doesn't land, the admin can simply click again. license_check() logs the
    // transport failure; these breadcrumbs trace the endpoint-level outcome.
    write_debug_log("Admin-initiated license refresh: calling license_check_daily().", 'info');
    $result = license_check_daily();

    if (!license_refresh_landed($result)) {
        write_debug_log("Admin-initiated license refresh did not land (prior cache retained); returning 503.", 'warning');
        api_v2_json_result(503, $lang['LicenseStateUnknownRetryShortly'], null);
        return;
    }

    write_debug_log("Admin-initiated license refresh succeeded (enforcement_level=" . ($result['enforcement_level'] ?? 'unknown') . ").", 'info');

    // Return the same overview shape as GET /admin/licenses so the Refresh
    // button can redraw in one round-trip. Keep 'licensed' for back-compat.
    $payload = api_v2_build_license_overview_payload();
    $payload['licensed'] = api_v2_collect_licensed_extras();
    api_v2_json_result(200, 'OK', $payload);
}

/****************************************************************
 * FUNCTION: API V2 ADMIN EXTRAS INSTALL                        *
 ****************************************************************/
/**
 * Install a SimpleRisk Extra by name. Wraps download_extra() from
 * services.php — that function fetches the Extra package, unzips it
 * into simplerisk/extras/<name>/, and updates the on-disk tree.
 *
 * Admin-gated. POST body must contain { name: <slug> } and the slug
 * must match an entry in available_extra_short_names().
 */
function api_v2_admin_extras_install()
{
    global $lang;

    if (!is_admin()) {
        api_v2_json_result(403, 'FORBIDDEN: admin permission required.', null);
        return;
    }

    require_once(realpath(__DIR__ . '/../../../includes/services.php'));
    require_once(realpath(__DIR__ . '/../../../includes/extras.php'));
    require_once(realpath(__DIR__ . '/../../../includes/licensing.php'));

    // Accept JSON body (the JS sends application/json) AND form-encoded
    // POST (for symmetry with the activation endpoint that uses FormData).
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $name = isset($body['name']) ? trim((string)$body['name']) : '';
    if ($name === '') {
        api_v2_json_result(400, $lang['MissingExtraName'] ?? 'Missing Extra name.', null);
        return;
    }

    $valid = available_extra_short_names();
    if (!in_array($name, $valid, true)) {
        api_v2_json_result(400, $lang['UnknownExtra'] ?? 'Unknown Extra name.', null);
        return;
    }

    // Enforcement-level gate: paid Extra install is only allowed when the
    // licensing service says 'normal'. Free Extras (the bundled Upgrade/SCF set,
    // always free, plus anything the license cache marks is_free) bypass the gate
    // regardless of enforcement_level.
    $free_extras = free_extra_short_names();
    if (!in_array($name, $free_extras, true)) {
        $enforcement_level = get_cached_enforcement_level();
        if ($enforcement_level === 'unknown') {
            // Cold cache or transient network failure — not a server-authoritative
            // enforcement decision. Return 503 so the client knows to retry once
            // the daily job (or the next admin-triggered check) warms the cache.
            api_v2_json_result(503, $lang['LicenseStateUnknownRetryShortly'], null);
            return;
        }
        if ($enforcement_level !== 'normal') {
            // Server-authoritative enforcement decision (lock_extras,
            // remove_extras, or anonymous). The lang key is guaranteed to
            // exist in lang.en.php; no hardcoded fallback.
            api_v2_json_result(403, $lang['ExtraInstallDisabledByEnforcement'], null);
            return;
        }
    }

    // download_extra() queues a toast on every code path via set_alert(),
    // including success. The Configure Hub install flow is fully reactive
    // (modal closes, catalog refetches) so nothing in this request consumes
    // those toasts — left in the session, they'd surface on the user's next
    // unrelated page load. We always capture-and-clear via get_alert(true, true)
    // and either drop the message (success) or surface it as the response's
    // status_message (failure), so the user sees the specific reason for the
    // failure ("Not Purchased", "Invalid Instance or Key", etc.) instead of
    // the generic InstallExtraError fallback.
    try {
        // $name was validated against available_extra_short_names() (a
        // hard-coded list of known Extra short names) via in_array(...,
        // strict=true) above. Phan can't see through that allowlist into
        // download_extra(), which uses $name in shell + path operations
        // inside services.php, so the suppression carries the reasoning.
        // taint-check 9.1.0 splits the old SecurityCheckMulti into granular
        // PathTraversal/ShellInjection issue types, so all three are listed.
        // @phan-suppress-next-line SecurityCheckMulti, SecurityCheck-PathTraversal, SecurityCheck-ShellInjection -- $name validated against available_extra_short_names() hard-coded allowlist via in_array() before reaching download_extra(); only known Extra short names can reach here
        $result = download_extra($name);
    } catch (\Throwable $e) {
        write_debug_log('Extra install failed for ' . $name . ': ' . $e->getMessage(), 'error');
        $alert = get_alert(true, true);
        $message = (is_string($alert) && $alert !== '')
            ? $alert
            : ($lang['InstallExtraError'] ?? 'Install failed.');
        api_v2_json_result(500, $message, null);
        return;
    }

    if (!is_string($result)) {
        $err_message = $result['reason'] ?? $result['error'] ?? null;
        $http_status = $result['http_status'] ?? 0;
        if ($err_message !== null) {
            write_debug_log("download_extra failed for '{$name}': {$err_message} (HTTP {$http_status})", 'warning');
        }
        $alert = get_alert(true, true);
        $message = (is_string($alert) && $alert !== '')
            ? $alert
            : ($err_message ?? ($lang['InstallExtraError'] ?? 'Install failed.'));
        // Use 403 for license/auth failures, 500 for transport or unknown errors
        $response_code = ($http_status === 401 || $http_status === 403 || $http_status === 404) ? 403 : 500;
        api_v2_json_result($response_code, $message, null);
        return;
    }

    // Success: drop the queued "good" toast so it doesn't leak to the
    // next navigation. The JS closes the modal and reloads the catalog —
    // visible-state change is its own success signal.
    get_alert(true, true);
    api_v2_json_result(200, 'OK', ['installed' => true]);
}

/******************************************************
 * FUNCTION: API V2 ADMIN RESET REGISTRATION          *
 * POST /api/v2/admin/reset_registration              *
 * Clears local identity (instance_id, services_api_key)
 * and the cached entitlements row. Does NOT contact  *
 * the licensing service. The admin completes the     *
 * reset by re-submitting /admin/register.php.        *
 ******************************************************/
function api_v2_admin_reset_registration()
{
    global $lang;

    if (!is_admin()) {
        api_v2_json_result(403, 'FORBIDDEN: admin permission required.', null);
        return;
    }

    // Delete the local identity rows outright so create_simplerisk_instance_id()
    // can regenerate them on next use (add_setting is INSERT IGNORE — a row
    // with value='' would block regeneration). The cache row is also cleared.
    $db = db_open();
    $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` IN ('instance_id', 'services_api_key', 'license_check_response')");
    $stmt->execute();
    db_close($db);

    // Drop the "this instance is registered" flag so admin/register.php
    // shows the fresh-registration form on the admin's next visit.
    update_or_insert_setting('registration_registered', 0);

    write_debug_log('Reset Registration: local identity and license cache cleared', 'notice');

    api_v2_json_result(
        200,
        $lang['LocalRegistrationStateCleared'],
        ['registered' => false]
    );
}

/**********************************************
 * FUNCTION: API V2 ADMIN FAVORITE ADD       *
 **********************************************/
// POST /admin/settings/favorites
// Body: {"key": "<settings_key>"}
function api_v2_admin_settings_favorite_add()
{
    require_once(realpath(__DIR__ . '/../../../includes/settings_catalog.php'));

    if (!user_can_access_settings_hub()) {
        api_v2_json_result(403, 'FORBIDDEN: insufficient permissions.', null);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $key  = is_array($body) ? (string)($body['key'] ?? '') : '';
    if ($key === '') {
        api_v2_json_result(400, 'BAD REQUEST: key is required.', null);
        return;
    }

    $catalog = settings_catalog();
    if (!array_key_exists($key, $catalog)) {
        api_v2_json_result(400, 'BAD REQUEST: unknown settings key.', null);
        return;
    }

    $user_id = (int)($_SESSION['uid'] ?? 0);
    if ($user_id <= 0) {
        api_v2_json_result(401, 'UNAUTHORIZED: no session user.', null);
        return;
    }

    if (!add_user_favorite_settings($user_id, $key)) {
        api_v2_json_result(500, 'INTERNAL: failed to add favorite.', null);
        return;
    }
    api_v2_json_result(200, 'OK', null);
}

/**********************************************
 * FUNCTION: API V2 ADMIN FAVORITE REMOVE    *
 **********************************************/
// DELETE /admin/settings/favorites/{key}
function api_v2_admin_settings_favorite_remove($key)
{
    require_once(realpath(__DIR__ . '/../../../includes/settings_catalog.php'));

    if (!user_can_access_settings_hub()) {
        api_v2_json_result(403, 'FORBIDDEN: insufficient permissions.', null);
        return;
    }

    $key = (string)$key;
    if ($key === '') {
        api_v2_json_result(400, 'BAD REQUEST: key is required.', null);
        return;
    }

    $user_id = (int)($_SESSION['uid'] ?? 0);
    if ($user_id <= 0) {
        api_v2_json_result(401, 'UNAUTHORIZED: no session user.', null);
        return;
    }

    if (!remove_user_favorite_settings($user_id, $key)) {
        api_v2_json_result(500, 'INTERNAL: failed to remove favorite.', null);
        return;
    }
    api_v2_json_result(200, 'OK', null);
}

/********************************************
 * FUNCTION: API V2 REPORTS FAVORITES LIST  *
 ********************************************/
// GET /reports/favorites
// Lightweight read of the caller's favorited report keys. Complements
// the POST/DELETE endpoints for clients that want just the favorite
// set without the full catalog payload.
function api_v2_reports_favorites_list()
{
    $user_id = (int)($_SESSION['uid'] ?? 0);
    if ($user_id <= 0) {
        api_v2_json_result(401, 'UNAUTHORIZED: no session user.', null);
        return;
    }

    try {
        $favorites = list_user_favorite_reports($user_id);
    } catch (\PDOException $e) {
        write_debug_log("API v2 reports favorites list: " . $e->getMessage(), 'error');
        api_v2_json_result(500, 'INTERNAL: failed to read favorites.', null);
        return;
    }

    $catalog = reports_catalog();
    $favorites = array_values(array_filter($favorites, function ($key) use ($catalog) {
        return isset($catalog[$key]) && user_can_access_report($catalog[$key]);
    }));

    api_v2_json_result(200, 'OK', ['favorites' => $favorites]);
}

/*********************************************
 * FUNCTION: API V2 REPORTS FAVORITES DELETE *
 *********************************************/
// DELETE /reports/favorites/{report_key}
// Removes a row in user_favorite_reports. Validates that the key exists in the
// catalog and that the requesting user currently has access to that report.
// Unknown or inaccessible keys return 400/403 respectively.
function api_v2_reports_favorites_delete($report_key)
{
    if (!is_string($report_key) || $report_key === '' || strlen($report_key) > 64) {
        api_v2_json_result(400, 'BAD REQUEST: report_key path parameter invalid.', null);
        return;
    }

    $user_id = (int)($_SESSION['uid'] ?? 0);
    if ($user_id <= 0) {
        api_v2_json_result(401, 'UNAUTHORIZED: no session user.', null);
        return;
    }

    $catalog = reports_catalog();
    if (!array_key_exists($report_key, $catalog)) {
        api_v2_json_result(400, 'BAD REQUEST: unknown report_key.', null);
        return;
    }

    if (!user_can_access_report($catalog[$report_key])) {
        api_v2_json_result(403, 'FORBIDDEN: you do not have access to this report.', null);
        return;
    }

    try {
        if (!remove_user_favorite_report($user_id, $report_key)) {
            api_v2_json_result(500, 'INTERNAL: failed to remove favorite.', null);
            return;
        }
    } catch (\PDOException $e) {
        write_debug_log("API v2 reports favorites delete failed: " . $e->getMessage(), 'error');
        api_v2_json_result(500, 'INTERNAL: failed to remove favorite.', null);
        return;
    }

    api_v2_json_result(200, 'OK', [
        'favorites' => list_user_favorite_reports($user_id),
    ]);
}
?>
