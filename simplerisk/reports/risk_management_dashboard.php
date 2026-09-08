<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js', 'UILayoutWidget'], ['check_riskmanagement' => true], active_sidebar_submenu: 'Reporting_Dashboards', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RiskManagementDashboard');

    // Include other required files
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));
    require_once(realpath(__DIR__ . '/../includes/settings_catalog.php'));

    // Render the 'risk_dashboard' layout. Edit-layout shows the real control
    // when the Customization Extra is active, or the shared locked teaser
    // otherwise (customization_acquisition_state(), includes/
    // settings_catalog.php) -- see reports/home.php's identical comment.
    (new \includes\Widgets\UILayout('risk_dashboard', [
        'edit_layout_locked_state' => customization_acquisition_state(is_admin(), get_setting('registration_registered') == 1),
    ]))->render();

    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
