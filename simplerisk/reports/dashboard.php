<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    render_header_and_sidebar(['multiselect', 'chart.js']);

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
    <div class="row mt-2">
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_level_pie(js_string_escape($lang['RiskLevel']), "open_risk_level_pie", $teams); 
    ?>
            </div>
        </div>
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_status_pie($pie_array, js_string_escape($lang['Status'])); 
    ?>
            </div>
        </div>
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_location_pie($pie_location_array, js_string_escape($lang['SiteLocation'])); 
    ?>
            </div>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_source_pie($pie_array, js_string_escape($lang['RiskSource'])); 
    ?>
            </div>
        </div>
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_category_pie($pie_array, js_string_escape($lang['Category'])); 
    ?>
            </div>
        </div>
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_team_pie($pie_team_array, js_string_escape($lang['Team'])); 
    ?>
            </div>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_technology_pie($pie_technology_array, js_string_escape($lang['Technology'])); 
    ?>
            </div>
        </div>
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_owner_pie($pie_array, js_string_escape($lang['Owner'])); 
    ?>
            </div>
        </div>
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_owners_manager_pie($pie_array, js_string_escape($lang['OwnersManager'])); 
    ?>
            </div>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-4">
            <div class="card-body border">
    <?php 
                open_risk_scoring_method_pie($pie_array, js_string_escape($lang['RiskScoringMethod'])); 
    ?>
            </div>
        </div>
    </div>
</div>
<div class="card-body border my-2">
    <div class="row">
        <div class="col-12">
            <h4><?= $escaper->escapeHtml($lang['ClosedRisks']); ?> (<?= $escaper->escapeHtml(get_closed_risks($teams)); ?>)</h4>
        </div>
    </div>
    <div class="row mt-2">
        <div class="col-4">
            <div class="card-body border">
    <?php 
                closed_risk_reason_pie(js_string_escape($lang['Reason']), $teams); 
    ?>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>