<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * A helper function to render the necessary parts for the header and sidebar. 
 * With this function call the following things also happen:
 * - initializing the $escaper, $lang and $current_app_version global variables
 * - adding the security headers and doing a session check(using the permissions provided in the $permissions parameter)
 * - initializing the CSRF Magic library
 * 
 * The aim is that the pages shouldn't contain code that's required on every one of them, so we're adding everything in the header part.
 * 
 * The $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu are for forcing the breadcrumb and menu selections on pages where it's not possible automatically.
 * The sidebar logic can set the active state of the menu/submenu based on the url.  It's not possible though in case of pages opened from a submenu page.
 * One example is if you open an assessment result which opens as a separate page from the assessment results page, or when you open an extra's configuration page.
 * 
 * The $required_scripts_or_css parameter is a list of javascript and css required for the page to be properly rendered and work as intended. The rendering of these is happening in the header.php.
 * 
 * The $required_localization_keys is a list of localization keys which will be used to generate a Javascript array called '_lang' that contains the translations(for the current language) for the listed keys
 * 
 * @param string $breadcrumb_title_key (optional)
 * @param string $active_sidebar_menu (optional)
 * @param string $active_sidebar_submenu (optional)
 * @param array $permissions (optional)
 * @param array $required_scripts_or_css (optional)
 * @param array $additional_render_info (optional)
 * @param array $required_localization_keys (optional)
 */
function render_header_and_sidebar($required_scripts_or_css = [], $permissions = [], $breadcrumb_title_key = '', $active_sidebar_menu = '', $active_sidebar_submenu = '', $additional_render_info = null, $required_localization_keys = []) {
    // $title = 'SimpleRisk: Enterprise Risk Management Simplified';
    
    require_once(realpath(__DIR__ . '/../sidebar.php'));
    // These variables doesn't need to be declared global
    $local_variables = ['local_variables', 'required_scripts_or_css', 'permissions', 'breadcrumb_title_key', 'active_sidebar_menu', 'active_sidebar_submenu', 'additional_render_info', 'required_script', 'matches', 'required_script_or_css', 'localization_key', 'required_localization_keys', 'localization_required_by_scripts', 'scripts_with_localization_needs', 'script_with_localization_needs', 'script_dependency'];
    // but we're printing a warning about every other variables as they might not be accessible in other parts of the application if they're not declared as global variables
    foreach (array_keys(get_defined_vars()) as $key) {
        if (!in_array($key, $local_variables) && !isset($GLOBALS[$key])) {
            // TODO Leave it here as this message should only be seen during development. A variable name detected by this logic should either be added to the above list as something that doesn't need to have a global scope or declared as global
            error_log("'{$key}' isn't defined as a global variable");
        }
    }
}

/**
 * Renders the footer. Wrapped in a function call so we can add additional logic if we need it.
 */
function render_footer() {
    require_once(realpath(__DIR__ . '/../footer.php'));
}

/**
 * Just a simple helper function to be able to use when rendering boolean values.
 *
 * @param Boolean $bool
 * @return string either 'true' or 'false'
 */
function boolean_to_string($bool) {
    return $bool ? 'true' : 'false';
}
?>