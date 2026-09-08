<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js', 'UILayoutWidget'], ['check_any_of' => ['riskmanagement', 'compliance', 'governance']], active_sidebar_submenu: 'Reporting_Dashboards', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'HomeDashboard');

    // Include other required files
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));
    require_once(realpath(__DIR__ . '/../includes/settings_catalog.php'));

    // Render the 'home' layout. Edit-layout shows the real control when the
    // Customization Extra is active, or the shared locked teaser otherwise
    // (customization_acquisition_state(), includes/settings_catalog.php) --
    // this dashboard previously showed the real control unconditionally
    // (show_edit_layout defaults to true with no Extra awareness at all),
    // unlike the insights bands (define_tests_insights, etc.), which have
    // gated it on customization_extra() since they were built.
    (new \includes\Widgets\UILayout('home', [
        'edit_layout_locked_state' => customization_acquisition_state(is_admin(), get_setting('registration_registered') == 1),
    ]))->render();

    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
