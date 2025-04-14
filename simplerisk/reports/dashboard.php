<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    render_header_and_sidebar(['multiselect', 'chart.js', 'UILayoutWidget'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RiskDashboard');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $teamOptions = get_teams_by_login_user();
    array_unshift($teamOptions, array(
        'value' => "0",
        'name' => $lang['Unassigned'],
    ));

    $teams = [];
    // Get teams submitted by user
    if (isset($_GET['teams'])) {
        $teams = array_filter(explode(',', $_GET['teams']), 'ctype_digit');
    } elseif (is_array($teamOptions)) {
        foreach ($teamOptions as $teamOption) {
            $teams[] = (int)$teamOption['value'];
        }
    }

    // Get the risk pie array
    $pie_array = get_pie_array(null, $teams);

    // Get the risk location pie array
    $pie_location_array = get_pie_array("location", $teams);

    // Get the risk team pie array
    $pie_team_array = get_pie_array("team", $teams);

    // Get the risk technology pie array
    $pie_technology_array = get_pie_array("technology", $teams);

?>
<div class="card-body border my-2">
    <div class="row">
        <div class="col-12">
            <h4><?= $escaper->escapeHtml($lang['OpenRisks']); ?> (<?= $escaper->escapeHtml(get_open_risks($teams)); ?>)</h4>
        </div>
    </div>
    <div class="row">
        <div class="col-4">
            <lable><strong><?= $escaper->escapeHtml($lang['Teams']); ?> :</strong></lable>
    <?php 
            create_multiple_dropdown("teams", $teams, NULL, $teamOptions); 
    ?>
            <form id="risks_dashboard_form" method="GET">
                <input type="hidden" value="<?= $escaper->escapeHtml(implode(',', $teams)); ?>" name="teams" id="team_options">
            </form>
    <?php 
            get_report_dashboard_dropdown_script(); 
    ?>
        </div>
    </div>
    <div class="mt-2">
    <?php 
        // Render the 'overview' layout
        (new \includes\Widgets\UILayout('dashboard_open'))->render();
    ?>
    </div>
</div>
<div class="card-body border my-2">
    <div class="row">
        <div class="col-12">
            <h4><?= $escaper->escapeHtml($lang['ClosedRisks']); ?> (<?= $escaper->escapeHtml(get_closed_risks($teams)); ?>)</h4>
        </div>
    </div>
    <div class="mt-2">
    <?php 
        // Render the 'overview' layout
        (new \includes\Widgets\UILayout('dashboard_close'))->render();
    ?>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>