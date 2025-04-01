<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js', 'CUSTOM:common.js'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'GraphicalRiskAnalysis');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/graphical.php'));
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $settings = [];
    $selection_id = get_param("GET", "selection", "");
    if ($selection_id) {
        $selection = get_graphical_saved_selection($selection_id);
        if ($selection['type'] == 'private' && $selection['user_id'] != $_SESSION['uid'] && !is_admin()) {
            set_alert(true, "bad", $lang['NoPermissionForThisSelection']);
        } else {
            $settings = json_decode($selection['graphical_display_settings'], true);
        }
    }
?>
<div class="row">
    <div class="col-12" id="selections">
        <div class="well card-body my-2 border">
            <form id="graphical_risk_analysis" name="graphical_risk_analysis" action="" method="POST">
    <?php 
                display_graphic_type_dropdown($settings);
                display_y_axis($settings); 
                display_x_axis($settings); 
                display_save_graphic_selection(); 
    ?>
                <div class="row">
                    <div class="col-6">
                        <input type="submit" name="generate_report" value="Generate Report" class="btn btn-submit"/>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-12">
        <div class="card-body border my-2">
    <?php 
            display_graphical_risk_analysis(); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>