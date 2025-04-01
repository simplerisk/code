<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['datatables', 'tabs:logic'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RiskAppetiteReport');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $risk_appetite = get_setting("risk_appetite", 0);

?>
<div class="row bg-white">
    <div class="col-12">
        <div id="appetite-tab-content">
            <div class="status-tabs">
                <div class="my-2">
                    <nav class="nav nav-tabs">
                        <a class="nav-link active" data-bs-target="#outside-appetite" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['OutsideAppetite']); ?> (<?= $escaper->escapeHtml("> {$risk_appetite}"); ?>)</a>
                        <a class="nav-link" data-bs-target="#within-appetite" data-bs-toggle="tab"><?= $escaper->escapeHtml($lang['WithinAppetite']); ?> (<?= $escaper->escapeHtml("<= {$risk_appetite}"); ?>)</a>
                    </nav>
                </div>
                <div class="tab-content">
                    <div class="tab-pane active card-body border my-2" id="outside-appetite" tabindex="0">
    <?php 
                        display_appetite_datatable(false); 
    ?>
                    </div>
                    <div class="tab-pane card-body border my-2" id="within-appetite" tabindex="0">
    <?php 
                        display_appetite_datatable(); 
    ?>
                    </div>
    <?php 
                    display_appetite_datatable_script(); 
    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>