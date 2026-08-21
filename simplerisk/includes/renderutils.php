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
 * @param array $required_scripts_or_css (optional)
 * @param array $permissions (optional)
 * @param string $breadcrumb_title_key (optional)
 * @param string $active_sidebar_menu (optional)
 * @param string $active_sidebar_submenu (optional)
 * @param array $additional_render_info (optional)
 * @param array $required_localization_keys (optional)
 */
function render_header_and_sidebar($required_scripts_or_css = [], $permissions = [], $breadcrumb_title_key = '', $active_sidebar_menu = '', $active_sidebar_submenu = '', $active_sidebar_thirdmenu = '', $active_sidebar_forthmenu = '', $additional_render_info = null, $required_localization_keys = []) {
    // $title = 'SimpleRisk: Enterprise Risk Management Simplified';
    
    require_once(realpath(__DIR__ . '/../sidebar.php'));
    // These variables doesn't need to be declared global
    $local_variables = ['local_variables', 'required_scripts_or_css', 'permissions', 'breadcrumb_title_key', 'active_sidebar_menu', 'active_sidebar_submenu', 'active_sidebar_thirdmenu', 'active_sidebar_forthmenu', 'additional_render_info', 'required_script', 'required_script_or_css', 'localization_key', 'required_localization_keys', 'localization_required_by_scripts', 'script_dependency', 'hub_current_script', 'hub_current_menu', 'hub_breadcrumb', 'lang_json', 'asset'];
    // but we're printing a warning about every other variables as they might not be accessible in other parts of the application if they're not declared as global variables
    foreach (array_keys(get_defined_vars()) as $key) {
        if (!in_array($key, $local_variables) && !isset($GLOBALS[$key])) {
            // TODO Leave it here as this message should only be seen during development. A variable name detected by this logic should either be added to the above list as something that doesn't need to have a global scope or declared as global
            write_debug_log("'{$key}' isn't defined as a global variable", 'warning');
        }
    }
}

/**
 * Renders the footer. Wrapped in a function call so we can add additional logic if we need it.
 */
function render_footer() {
    // footer.php is require_once'd into THIS function's scope (unlike header.php,
    // which head.php pulls into render_header_and_sidebar()'s frame where these
    // globals are already declared). Declare them here so shell markup added to
    // footer.php can safely use $escaper/$lang/$current_app_version — without this,
    // any such use is a fatal "undefined variable" 500 the moment it's added.
    global $escaper, $lang, $current_app_version;
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

/**
 * Build the avatar initials for the topbar profile menu from a user's display
 * name: the first letter of the first and last words, or the first two letters
 * of a single-word name. Returns '' when there's no usable name (the caller then
 * falls back to a generic person glyph). Multibyte-safe.
 *
 * @param string $name the user's display name (e.g. $_SESSION['name'])
 * @return string uppercase initials (0, 1, or 2 characters)
 */
function build_profile_initials($name) {
    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }
    // /u so PCRE's Unicode whitespace class applies — a name separated by a
    // non-ASCII space (e.g. U+00A0 NBSP) still splits into words. On a name that
    // isn't valid UTF-8 (e.g. a legacy import or charset mismatch), /u makes
    // preg_split() return false; fall back to the byte-based split so count()
    // below can't fatal — this runs on every authenticated page render.
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (empty($parts)) {
        return '';
    }
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return mb_strtoupper(mb_substr($parts[0], 0, 2));
}
?>