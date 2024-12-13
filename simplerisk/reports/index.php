<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js']);

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

?>
<div class="row my-2">
    <div class="col-md-4">
        <div class="card-body border">
    <?php 
            open_closed_pie(js_string_escape($lang['OpenVsClosed'])); 
    ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-body border">
    <?php 
            open_mitigation_pie(js_string_escape($lang['MitigationPlannedVsUnplanned'])); 
    ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-body border">
    <?php 
            open_review_pie(js_string_escape($lang['ReviewedVsUnreviewed'])); 
    ?>
        </div>
    </div>
</div>
<div class="row my-2">
    <div class="col-md-12">
        <div class="card-body border">
    <?php 
            risks_by_month_table(); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>