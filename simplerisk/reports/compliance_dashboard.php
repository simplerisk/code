<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['chart.js'], active_sidebar_submenu: 'Reporting_Compliance', active_sidebar_menu: 'Reporting', breadcrumb_title_key: 'ComplianceDashboard');

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/reporting.php'));
    require_once(realpath(__DIR__ . '/../includes/compliance.php'));

    /// If User has no access permission for compliance menu, enforce to main page.
    if(empty($_SESSION['compliance'])) {
        header("Location: ../index.php");
        exit(0);
    }

    // Get framework controls with test status counts
    $framework_data = get_framework_controls_test_status_counts();
    
    // Prepare data for the chart
    $labels = [];
    $passing_data = [];
    $failing_data = [];
    
    foreach ($framework_data as $framework) {
        $labels[] = $escaper->escapeHtml($framework['framework_name']);
        $passing_data[] = (int)$framework['passing_controls'];
        $failing_data[] = (int)$framework['failing_controls'];
    }
    
    // Create datasets for the chart
    $datasets = [
        [
            'label' => $escaper->escapeHtml($lang['PassingControls']),
            'data' => $passing_data,
            'backgroundColor' => '#66CC00', // Green color
        ],
        [
            'label' => $escaper->escapeHtml($lang['FailingControls']),
            'data' => $failing_data,
            'backgroundColor' => '#FF0000', // Red color
        ]
    ];
?>
<div class="row bg-white">
    <div class="col-12">
        <div class="card-body border my-2">
            <strong><?= $escaper->escapeHtml($lang['ComplianceDashboardDescription']); ?></strong>
            <div class="mt-4">
                <?php
                    create_chartjs_bar_code(
                        $escaper->escapeHtml($lang['ControlsByFramework']),
                        'compliance_dashboard_chart',
                        $labels,
                        $datasets,
                        $escaper->escapeHtml($lang['Framework']),
                        $escaper->escapeHtml($lang['NumberOfControls'])
                    );
                ?>
            </div>
        </div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>