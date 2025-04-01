<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    render_header_and_sidebar(['datatables', 'tabs:logic', 'chart.js'], active_sidebar_submenu: 'Reporting_Governance', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'ControlGapAnalysis');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    // If User has no access permission for governance menu, enforce to main page.
    if(empty($_SESSION['governance'])) {

        header("Location: ../index.php");
        exit(0);
        
    }

?>
<div class="row bg-white">
    <div class="col-12">
    <?php 
        display_control_gap_analysis(); 
    ?>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>