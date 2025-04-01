<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js', 'UILayoutWidget'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'Overview');

    // Include other required files
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    // Render the 'overview' layout
    (new \includes\Widgets\UILayout('overview'))->render();

    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>