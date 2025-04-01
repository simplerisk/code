<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['multiselect', 'datetimerangepicker'], active_sidebar_submenu: 'Reporting_RiskManagement', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'RisksAndIssues');

    // Include required functions file
    // require_once(realpath(__DIR__ . '/../includes/reporting.php'));

    $user_info = get_user_by_id($_SESSION['uid']);
    $tag_ids = explode(',', (string)$user_info['custom_risks_and_issues_settings']);

    $risk_tags = isset($_REQUEST['risk_tags']) ? $_REQUEST['risk_tags'] : $tag_ids;
    $start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : format_date(date('Y-m-d', strtotime('-30 days')));
    $end_date = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : format_date(date('Y-m-d'));

    setting_risks_and_issues_tags($risk_tags);

?>
<div class="row bg-white">
    <div class="col-12 my-2">
        <div id="selections">
    <?php 
            view_risks_and_issues_selections($risk_tags, $start_date, $end_date); 
    ?>
        </div>
    </div>
    <div class="col-12 mb-2">
        <div class="card-body border">
    <?php 
            risks_and_issues_table($risk_tags, $start_date, $end_date); 
    ?>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>