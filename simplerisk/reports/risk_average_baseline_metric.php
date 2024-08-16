<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Render the header and sidebar
require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
render_header_and_sidebar(['chart.js']);

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/reporting.php'));

$time = isset($_GET['time']) ? $_GET['time'] : "day";

?>
<div class="row">
    <div class="col-12">
        <div class="card-body">
            <form method="GET" style="margin-bottom: 0px;">
                <div class="form-group col-4">
                	<label>By &nbsp;</label>
                    <select name="time" onchange="submit()" class="form-select">
                        <option <?= $time == 'day' ? 'selected ' : '' ?>value="day"><?= $escaper->escapeHtml($lang['Day']) ?></option>
                        <option <?= $time == 'month' ? 'selected ' : '' ?>value="month"><?= $escaper->escapeHtml($lang['Month']) ?></option>
                        <option <?= $time == 'year' ? 'selected ' : '' ?>value="year"><?= $escaper->escapeHtml($lang['Year']) ?></option>
                    </select>
                </div>
            </form>
        </div>
        <div>
            <?php risk_average_baseline_metric($time); ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>