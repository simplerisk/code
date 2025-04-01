<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RiskAverageOverTime');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $time = isset($_GET['time']) ? $_GET['time'] : "day";

?>
<div class="row">
    <div class="col-12">
        <div class="card-body border my-2">
            <form method="GET">
                <div class="row">
                    <div class="col-4 d-flex align-items-center">
                        <label style="width: 50px; min-width: 50px;">By :</label>
                        <select name="time" onchange="submit()" class="form-select">
                            <option <?= $time == 'day' ? 'selected ' : '' ?>value="day"><?= $escaper->escapeHtml($lang['Day']) ?></option>
                            <option <?= $time == 'month' ? 'selected ' : '' ?>value="month"><?= $escaper->escapeHtml($lang['Month']) ?></option>
                            <option <?= $time == 'year' ? 'selected ' : '' ?>value="year"><?= $escaper->escapeHtml($lang['Year']) ?></option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body border my-2">
    <?php 
            risk_average_baseline_metric($time); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>