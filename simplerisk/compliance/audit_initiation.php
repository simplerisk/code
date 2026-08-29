<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    $breadcrumb_title_key = "InitiateAudits";
    $active_sidebar_menu = "Compliance";
    $active_sidebar_submenu = "InitiateAudits";
    render_header_and_sidebar(['blockUI', 'selectize', 'datatables', 'CUSTOM:sr-select.js', 'CUSTOM:pages/compliance.js', 'CUSTOM:pages/compliance-initiate-audits.js', 'CUSTOM:common.js'], ['check_compliance' => true], $breadcrumb_title_key, $active_sidebar_menu, $active_sidebar_submenu);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

?>
<?php
    // No .row/.col-12 grid wrapper (see audits.php for the full rationale --
    // col-12's padding was insetting the card ~10px from the page title/
    // breadcrumb above it). .sr-table-card floats on the gray page ground --
    // no bg-white wrapper here either.
    display_initiate_audits_flat();
?>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
